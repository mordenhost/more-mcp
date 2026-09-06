<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sensei {

	const COURSE_CPT   = 'course';
	const LESSON_CPT   = 'lesson';
	const MODULE_TAX   = 'module';
	const ENROL_COMMENT = 'sensei_course_status';

	public static function is_available() {

		
		if ( class_exists( 'Sensei_Main' ) || function_exists( 'Sensei' ) || defined( 'SENSEI_LMS_VERSION' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) && function_exists( 'taxonomy_exists' ) ) {
			return post_type_exists( self::COURSE_CPT ) && taxonomy_exists( self::MODULE_TAX );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'sensei' ),
			'capabilities' => array( 'lms' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'sensei_get_status',
				'description' => 'Read Sensei LMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'sensei_get_course_structure',
				'description' => 'Read the curriculum outline of one Sensei course (by course ID): its ordered modules, the lessons within each module, and any lessons attached to the course directly (ungrouped), each with title and order. Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the Sensei course to outline.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use LMS tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Sensei LMS is not active.' );
		}

		if ( 'sensei_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'sensei_get_status' !== $name ) {
			throw new \Exception( 'Unknown LMS tool: ' . esc_html( $name ) );
		}

		$by_status = array();
		$total     = 0;
		if ( function_exists( 'wp_count_posts' ) ) {
			$counts = wp_count_posts( self::COURSE_CPT );
			foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
				$n                    = isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
				$by_status[ $status ] = $n;
				$total               += $n;
			}
		}

		return array(
			'provider'          => 'sensei',
			'courses_total'     => $total,
			'courses_by_status' => $by_status,
			'enrolled_students' => self::enrolled_students(),
		);
	}

	private static function get_course_structure( $args ) {
		$course_id = isset( $args['course_id'] ) ? absint( $args['course_id'] ) : 0;
		if ( $course_id <= 0 ) {
			throw new \Exception( 'course_id (a positive integer) is required.' );
		}
		if ( self::COURSE_CPT !== get_post_type( $course_id ) ) {
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a Sensei course.' );
		}

		$lessons        = self::course_lessons( $course_id );
		$lesson_module  = array(); 
		foreach ( $lessons as $lesson_id ) {
			$lesson_module[ $lesson_id ] = self::lesson_module_id( $lesson_id, $course_id );
		}

		$modules     = self::course_modules( $course_id );
		$module_out  = array();
		$grouped_ids = array();
		foreach ( $modules as $module ) {
			$mid          = (int) $module['term_id'];
			$module_items = array();
			foreach ( $lessons as $order => $lesson_id ) {
				if ( isset( $lesson_module[ $lesson_id ] ) && $lesson_module[ $lesson_id ] === $mid ) {
					$module_items[]      = self::lesson_summary( $lesson_id, count( $module_items ) );
					$grouped_ids[ $lesson_id ] = true;
				}
			}
			$module_out[] = array(
				'module_id'    => $mid,
				'name'         => $module['name'],
				'order'        => $module['order'],
				'lesson_count' => count( $module_items ),
				'lessons'      => $module_items,
			);
		}

		$ungrouped = array();
		foreach ( $lessons as $lesson_id ) {
			if ( empty( $grouped_ids[ $lesson_id ] ) ) {
				$ungrouped[] = self::lesson_summary( $lesson_id, count( $ungrouped ) );
			}
		}

		return array(
			'provider'          => 'sensei',
			'available'         => true,
			'course_id'         => $course_id,
			'course_title'      => get_the_title( $course_id ),
			'module_count'      => count( $module_out ),
			'modules'           => $module_out,
			'ungrouped_lessons' => $ungrouped,
			'lesson_total'      => count( $lessons ),
		);
	}

	private static function course_lessons( $course_id ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q = new \WP_Query(
			array(
				'post_type'        => self::LESSON_CPT,
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
				'meta_query'       => array(
					array(
						'key'   => '_lesson_course',
						'value' => (int) $course_id,
					),
				),
			)
		);
		$ids = is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : array();

		if ( count( $ids ) > 1 ) {
			$order = array();
			foreach ( $ids as $id ) {
				$o           = (int) get_post_meta( $id, '_order_' . $course_id, true );
				$order[ $id ] = $o ? $o : 100000;
			}
			usort(
				$ids,
				static function ( $a, $b ) use ( $order ) {
					if ( $order[ $a ] === $order[ $b ] ) {
						return $a <=> $b;
					}
					return $order[ $a ] < $order[ $b ] ? -1 : 1;
				}
			);
		}
		return $ids;
	}

	private static function lesson_module_id( $lesson_id, $course_id ) {
		if ( ! function_exists( 'wp_get_post_terms' ) ) {
			return 0;
		}
		$terms = wp_get_post_terms( $lesson_id, self::MODULE_TAX, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}
		
		return (int) $terms[0];
	}

	private static function course_modules( $course_id ) {
		$terms = array();

		
		if ( function_exists( 'Sensei' ) ) {
			$sensei = \Sensei();
			if ( is_object( $sensei ) && isset( $sensei->modules )
				&& is_object( $sensei->modules )
				&& method_exists( $sensei->modules, 'get_course_modules' ) ) {
				$maybe = $sensei->modules->get_course_modules( $course_id );
				if ( is_array( $maybe ) ) {
					$terms = $maybe;
				}
			}
		}

		
		if ( empty( $terms ) && function_exists( 'wp_get_post_terms' ) ) {
			$maybe = wp_get_post_terms( $course_id, self::MODULE_TAX );
			if ( is_array( $maybe ) ) {
				$terms = $maybe;
			}
		}

		$out   = array();
		$order = 0;
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
				continue;
			}
			$out[] = array(
				'term_id' => (int) $term->term_id,
				'name'    => isset( $term->name ) ? (string) $term->name : '',
				'order'   => $order,
			);
			$order++;
		}
		return $out;
	}

	private static function lesson_summary( $lesson_id, $order ) {
		return array(
			'lesson_id' => (int) $lesson_id,
			'title'     => (string) get_the_title( $lesson_id ),
			'order'     => (int) $order,
		);
	}

	private static function enrolled_students() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->comments} WHERE comment_type = %s AND user_id > 0",
				self::ENROL_COMMENT
			)
		);
		return null === $count ? null : (int) $count;
	}
}
