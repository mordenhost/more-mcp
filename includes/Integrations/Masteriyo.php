<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Masteriyo {

	const COURSE_CPT  = 'mto-course';
	const SECTION_CPT = 'mto-section';
	const LESSON_CPT  = 'mto-lesson';
	const QUIZ_CPT    = 'mto-quiz';

	const CATEGORY_TAX = 'course_cat';

	const COURSE_META = '_course_id';

	const ACTIVITY_TABLE = 'masteriyo_user_activities';
	const ENROL_ACTIVITY = 'course_progress';

	public static function is_available() {

		
		
		if ( defined( 'MASTERIYO_VERSION' ) || function_exists( 'masteriyo' ) || function_exists( 'masteriyo_get_course' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) ) {
			return post_type_exists( self::COURSE_CPT ) && post_type_exists( self::SECTION_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'masteriyo' ),
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
				'name'        => 'mto_get_status',
				'description' => 'Read Masteriyo LMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'mto_get_course_structure',
				'description' => 'Read the curriculum outline of one Masteriyo course (by course ID): its ordered sections, and within each section the ordered lessons and quizzes, each with title, type, and order. Also lists any lessons/quizzes attached to the course but to no section (ungrouped). Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the Masteriyo course to outline.',
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
			throw new \Exception( 'Masteriyo LMS is not active.' );
		}

		if ( 'mto_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'mto_get_status' !== $name ) {
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
			'provider'          => 'masteriyo',
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
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a Masteriyo course.' );
		}

		
		$items = self::course_items( $course_id );

		$sections    = self::course_sections( $course_id );
		$section_out = array();
		$grouped     = array(); 
		foreach ( $sections as $section ) {
			$sid          = (int) $section['id'];
			$section_items = array();
			foreach ( $items as $item ) {
				if ( (int) $item['parent_id'] === $sid ) {
					$section_items[]      = self::item_summary( $item, count( $section_items ) );
					$grouped[ $item['id'] ] = true;
				}
			}
			$section_out[] = array(
				'section_id' => $sid,
				'name'       => $section['name'],
				'order'      => $section['order'],
				'item_count' => count( $section_items ),
				'items'      => $section_items,
			);
		}

		$ungrouped = array();
		foreach ( $items as $item ) {
			if ( empty( $grouped[ $item['id'] ] ) ) {
				$ungrouped[] = self::item_summary( $item, count( $ungrouped ) );
			}
		}

		return array(
			'provider'          => 'masteriyo',
			'available'         => true,
			'course_id'         => $course_id,
			'course_title'      => get_the_title( $course_id ),
			'categories'        => self::category_names( $course_id ),
			'section_count'     => count( $section_out ),
			'sections'          => $section_out,
			'ungrouped_items'   => $ungrouped,
			'item_total'        => count( $items ),
		);
	}

	private static function course_sections( $course_id ) {
		$posts = self::query_by_course( self::SECTION_CPT, $course_id );
		$out   = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'    => (int) $post->ID,
				'name'  => (string) get_the_title( $post->ID ),
				'order' => (int) $post->menu_order,
			);
		}
		return $out;
	}

	private static function course_items( $course_id ) {
		$out = array();
		foreach ( array( self::LESSON_CPT => 'lesson', self::QUIZ_CPT => 'quiz' ) as $cpt => $type ) {
			foreach ( self::query_by_course( $cpt, $course_id ) as $post ) {
				$out[] = array(
					'id'        => (int) $post->ID,
					'type'      => $type,
					'title'     => (string) get_the_title( $post->ID ),
					'parent_id' => (int) $post->post_parent,
					'_order'    => (int) $post->menu_order,
				);
			}
		}
		
		usort(
			$out,
			static function ( $a, $b ) {
				if ( $a['_order'] === $b['_order'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['_order'] < $b['_order'] ? -1 : 1;
			}
		);
		return $out;
	}

	private static function query_by_course( $cpt, $course_id ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q = new \WP_Query(
			array(
				'post_type'        => $cpt,
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
				'meta_query'       => array(
					array(
						'key'   => self::COURSE_META,
						'value' => (int) $course_id,
					),
				),
			)
		);
		return is_array( $q->posts ) ? $q->posts : array();
	}

	private static function item_summary( $item, $order ) {
		return array(
			'item_id' => (int) $item['id'],
			'type'    => (string) $item['type'],
			'title'   => (string) $item['title'],
			'order'   => (int) $order,
		);
	}

	private static function category_names( $course_id ) {
		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array();
		}
		$names = wp_get_object_terms( (int) $course_id, self::CATEGORY_TAX, array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! is_array( $names ) ) {
			return array();
		}
		return array_values( array_map( 'strval', $names ) );
	}

	private static function enrolled_students() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}
		$table = $wpdb->prefix . self::ACTIVITY_TABLE;

		
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( $exists !== $table ) {
			return null;
		}

		
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE activity_type = %s AND user_id > 0",
				self::ENROL_ACTIVITY
			)
		);
		return null === $count ? null : (int) $count;
	}
}
