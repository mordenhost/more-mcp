<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LifterLMS {

	const COURSE_CPT     = 'course';
	const SECTION_CPT    = 'section';
	const LESSON_CPT     = 'lesson';
	const QUIZ_CPT       = 'llms_quiz';
	const MEMBERSHIP_CPT = 'llms_membership';

	const PARENT_COURSE_META  = '_llms_parent_course';
	const PARENT_SECTION_META = '_llms_parent_section';
	const ORDER_META          = '_llms_order';
	const LESSON_QUIZ_META    = '_llms_quiz';
	const PRICE_META          = '_llms_price';

	const CATEGORY_TAX = 'course_cat';

	const ENROL_TABLE  = 'lifterlms_user_postmeta';
	const STATUS_META   = '_status';
	const ENROLLED_VALUE = 'enrolled';

	public static function is_available() {

		

		if ( defined( 'LLMS_VERSION' ) || function_exists( 'llms' ) || class_exists( '\LifterLMS' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) ) {
			return post_type_exists( self::COURSE_CPT )
				&& post_type_exists( self::SECTION_CPT )
				&& post_type_exists( self::QUIZ_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'lifterlms' ),
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
				'name'        => 'llms_get_status',
				'description' => 'Read LifterLMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'llms_get_course_structure',
				'description' => 'Read the curriculum outline of one LifterLMS course (by course ID): its ordered sections, and within each section the ordered lessons (each with title, order, and whether it has a quiz plus that quiz\'s title). Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the LifterLMS course to outline.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
			array(
				'name'        => 'llms_list_courses',
				'description' => 'List the LifterLMS course catalogue: each course\'s id, title, slug, status, and price. Read-only enumeration so an agent can discover course IDs to feed llms_get_course_structure. Catalogue fields only — never enrolment rows, learner/user ids, per-student progress, or lesson content. Cannot modify anything.',
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
			array(
				'name'        => 'llms_list_memberships',
				'description' => 'List the LifterLMS membership catalogue: each membership\'s id, title, slug, and status. Read-only enumeration of the `llms_membership` post type (memberships gate access to courses; the course link itself is not catalogue data). Catalogue fields only — never enrolment rows, member/learner ids, or per-member progress. Cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Maximum memberships to return (1-100, default 50).',
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of memberships to skip, for paging past the limit.',
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
			throw new \Exception( 'LifterLMS is not active.' );
		}

		if ( 'llms_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'llms_list_courses' === $name ) {
			return self::list_catalogue( self::COURSE_CPT, 'lifterlms', $args, true );
		}
		if ( 'llms_list_memberships' === $name ) {
			return self::list_catalogue( self::MEMBERSHIP_CPT, 'lifterlms', $args, false );
		}
		if ( 'llms_get_status' !== $name ) {
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
			'provider'          => 'lifterlms',
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
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a LifterLMS course.' );
		}

		$sections    = self::query_by_meta( self::SECTION_CPT, self::PARENT_COURSE_META, $course_id );
		$section_out = array();
		foreach ( $sections as $index => $section ) {
			$sid     = (int) $section->ID;
			$lessons = self::query_by_meta( self::LESSON_CPT, self::PARENT_SECTION_META, $sid );
			$items   = array();
			foreach ( $lessons as $l_index => $lesson ) {
				$items[] = self::lesson_summary( (int) $lesson->ID, $l_index );
			}
			$section_out[] = array(
				'section_id'   => $sid,
				'name'         => (string) get_the_title( $sid ),
				'order'        => (int) $index,
				'lesson_count' => count( $items ),
				'lessons'      => $items,
			);
		}

		return array(
			'provider'      => 'lifterlms',
			'available'     => true,
			'course_id'     => $course_id,
			'course_title'  => get_the_title( $course_id ),
			'categories'    => self::category_names( $course_id ),
			'section_count' => count( $section_out ),
			'sections'      => $section_out,
		);
	}

	private static function lesson_summary( $lesson_id, $order ) {
		$quiz_id    = 0;
		$quiz_title = null;
		if ( function_exists( 'get_post_meta' ) ) {
			$quiz_id = absint( get_post_meta( $lesson_id, self::LESSON_QUIZ_META, true ) );
		}
		if ( $quiz_id > 0 && self::QUIZ_CPT === get_post_type( $quiz_id ) ) {
			$quiz_title = (string) get_the_title( $quiz_id );
		} else {
			$quiz_id = 0;
		}
		return array(
			'lesson_id'  => (int) $lesson_id,
			'title'      => (string) get_the_title( $lesson_id ),
			'order'      => (int) $order,
			'has_quiz'   => $quiz_id > 0,
			'quiz_title' => $quiz_title,
		);
	}

	private static function query_by_meta( $cpt, $parent_meta_key, $parent_id ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q = new \WP_Query(
			array(
				'post_type'        => $cpt,
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'meta_key'         => self::ORDER_META,
				'orderby'          => 'meta_value_num',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
				'meta_query'       => array(
					array(
						'key'   => $parent_meta_key,
						'value' => (int) $parent_id,
					),
				),
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

	private static function list_catalogue( $cpt, $provider, $args, $with_price ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider' => $provider,
				'items'    => array(),
				'total'    => 0,
				'limit'    => 0,
				'offset'   => 0,
			);
		}

		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 50 );
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$q = new \WP_Query(
			array(
				'post_type'        => $cpt,
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

		$items = array();
		foreach ( $posts as $post ) {
			$id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$row = array(
				'id'     => $id,
				'title'  => (string) get_the_title( $id ),
				'slug'   => isset( $post->post_name ) ? (string) $post->post_name : '',
				'status' => isset( $post->post_status ) ? (string) $post->post_status : '',
			);
			if ( $with_price ) {
				$price        = get_post_meta( $id, self::PRICE_META, true );
				$row['price'] = ( '' === $price || null === $price ) ? null : (float) $price;
			}
			$items[] = $row;
		}

		return array(
			'provider' => $provider,
			'items'    => $items,
			'total'    => count( $items ),
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
		$table = $wpdb->prefix . self::ENROL_TABLE;

		
		
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( $exists !== $table ) {
			return null;
		}

		
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE meta_key = %s AND meta_value = %s AND user_id > 0",
				self::STATUS_META,
				self::ENROLLED_VALUE
			)
		);
		return null === $count ? null : (int) $count;
	}
}
