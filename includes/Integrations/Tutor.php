<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tutor {

	const COURSE_CPT     = 'courses';
	const TOPIC_CPT      = 'topics';
	const LESSON_CPT     = 'lesson';
	const QUIZ_CPT       = 'tutor_quiz';
	const ASSIGNMENT_CPT = 'tutor_assignments';

	const ENROL_CPT    = 'tutor_enrolled';
	const ENROL_STATUS = 'completed';
	const CATEGORY_TAX = 'course-category';

	const PRICE_META = '_tutor_course_price';

	public static function is_available() {

		
		if ( defined( 'TUTOR_VERSION' ) || function_exists( 'tutor' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) ) {
			return post_type_exists( self::COURSE_CPT ) && post_type_exists( self::TOPIC_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'tutor' ),
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
				'name'        => 'tutor_get_status',
				'description' => 'Read Tutor LMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'tutor_get_course_structure',
				'description' => 'Read the curriculum outline of one Tutor LMS course (by course ID): its ordered topics, and within each topic the ordered lessons, quizzes, and assignments, each with title, type, and order. Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the Tutor LMS course to outline.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
			array(
				'name'        => 'tutor_list_courses',
				'description' => 'List the Tutor LMS course catalogue: each course\'s id, title, slug, status, and price. Read-only enumeration so an agent can discover course IDs to feed tutor_get_course_structure. Catalogue fields only — never enrolment rows, learner/user ids, per-student progress, or lesson content. Cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Maximum courses to return (1-100, default 50).',
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of courses to skip, for paging past the limit.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use LMS tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Tutor LMS is not active.' );
		}

		if ( 'tutor_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'tutor_list_courses' === $name ) {
			return self::list_courses( $args );
		}
		if ( 'tutor_get_status' !== $name ) {
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
			'provider'          => 'tutor',
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
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a Tutor LMS course.' );
		}

		$topics    = self::child_posts( self::TOPIC_CPT, $course_id );
		$topic_out = array();
		foreach ( $topics as $index => $topic ) {
			$topic_id = (int) $topic->ID;
			$items    = self::topic_items( $topic_id );
			$topic_out[] = array(
				'topic_id'   => $topic_id,
				'name'       => (string) get_the_title( $topic_id ),
				'order'      => (int) $index,
				'item_count' => count( $items ),
				'items'      => $items,
			);
		}

		return array(
			'provider'      => 'tutor',
			'available'     => true,
			'course_id'     => $course_id,
			'course_title'  => get_the_title( $course_id ),
			'categories'    => self::category_names( $course_id ),
			'topic_count'   => count( $topic_out ),
			'topics'        => $topic_out,
		);
	}

	private static function topic_items( $topic_id ) {
		$rows = array();
		foreach ( array(
			self::LESSON_CPT     => 'lesson',
			self::QUIZ_CPT       => 'quiz',
			self::ASSIGNMENT_CPT => 'assignment',
		) as $cpt => $type ) {
			foreach ( self::child_posts( $cpt, $topic_id ) as $post ) {
				$rows[] = array(
					'id'     => (int) $post->ID,
					'type'   => $type,
					'title'  => (string) get_the_title( $post->ID ),
					'_order' => (int) $post->menu_order,
				);
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['_order'] === $b['_order'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['_order'] < $b['_order'] ? -1 : 1;
			}
		);
		$out = array();
		foreach ( $rows as $order => $row ) {
			$out[] = array(
				'item_id' => $row['id'],
				'type'    => $row['type'],
				'title'   => $row['title'],
				'order'   => (int) $order,
			);
		}
		return $out;
	}

	private static function child_posts( $cpt, $parent_id ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q = new \WP_Query(
			array(
				'post_type'        => $cpt,
				'post_parent'      => (int) $parent_id,
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
			)
		);
		return is_array( $q->posts ) ? $q->posts : array();
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

	private static function list_courses( $args ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider' => 'tutor',
				'courses'  => array(),
				'total'    => 0,
				'limit'    => 0,
				'offset'   => 0,
			);
		}

		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 50 );
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$q = new \WP_Query(
			array(
				'post_type'        => self::COURSE_CPT,
				'posts_per_page'   => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'post_status'      => 'any',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
			)
		);
		$posts = is_array( $q->posts ) ? $q->posts : array();

		$courses = array();
		foreach ( $posts as $post ) {
			$id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$price     = get_post_meta( $id, self::PRICE_META, true );
			$courses[] = array(
				'id'     => $id,
				'title'  => (string) get_the_title( $id ),
				'slug'   => isset( $post->post_name ) ? (string) $post->post_name : '',
				'status' => isset( $post->post_status ) ? (string) $post->post_status : '',
				'price'  => ( '' === $price || null === $price ) ? null : (float) $price,
			);
		}

		return array(
			'provider' => 'tutor',
			'courses'  => $courses,
			'total'    => count( $courses ),
			'limit'    => $limit,
			'offset'   => $offset,
		);
	}

	private static function clamp_limit( $limit ) {
		$n = (int) $limit;
		if ( $n < 1 ) {
			return 1;
		}
		if ( $n > 100 ) {
			return 100;
		}
		return $n;
	}

	private static function enrolled_students() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_author > 0",
				self::ENROL_CPT,
				self::ENROL_STATUS
			)
		);
		return null === $count ? null : (int) $count;
	}
}
