<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LearnPress {

	const COURSE_CPT = 'lp_course';

	const PRICE_META = '_lp_price';

	const CATEGORY_TAX = 'course_category';

	public static function is_available() {
		return class_exists( 'LearnPress' )
			|| defined( 'LP_PLUGIN_FILE' )
			|| ( function_exists( 'post_type_exists' ) && post_type_exists( self::COURSE_CPT ) );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'learnpress' ),
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
				'name'        => 'lms_get_status',
				'description' => 'Read LearnPress LMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'lms_get_course_structure',
				'description' => 'Read the curriculum outline of one LearnPress course (by course ID): its ordered sections, and within each section the ordered items (lessons and quizzes) with their title, type, and order. Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the lp_course to outline.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
			array(
				'name'        => 'lms_list_courses',
				'description' => 'List the LearnPress course catalogue: each course\'s id, title, slug, status, and price. Read-only enumeration so an agent can discover course IDs to feed lms_get_course_structure. Catalogue fields only — never enrolment rows, learner/user ids, per-student progress, or lesson content. Cannot modify anything.',
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
				'name'        => 'lms_list_categories',
				'description' => 'List the LearnPress course categories: each term\'s id, name, slug, and course count. Read-only taxonomy enumeration for narrowing the course catalogue. No student data, no course content. Cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use LMS tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'LearnPress is not active.' );
		}

		if ( 'lms_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'lms_list_courses' === $name ) {
			return self::list_courses( $args );
		}
		if ( 'lms_list_categories' === $name ) {
			return self::list_categories();
		}
		if ( 'lms_get_status' !== $name ) {
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

		$students = self::enrolled_students();

		return array(
			'provider'          => 'learnpress',
			'courses_total'     => $total,
			'courses_by_status' => $by_status,
			'enrolled_students' => $students,
		);
	}

	private static function get_course_structure( $args ) {
		global $wpdb;

		$course_id = isset( $args['course_id'] ) ? absint( $args['course_id'] ) : 0;
		if ( $course_id <= 0 ) {
			throw new \Exception( 'course_id (a positive integer) is required.' );
		}
		if ( self::COURSE_CPT !== get_post_type( $course_id ) ) {
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a LearnPress course.' );
		}

		$sections_table = $wpdb->prefix . 'learnpress_sections';
		$items_table    = $wpdb->prefix . 'learnpress_section_items';

		$sec_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sections_table ) );
		$item_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $items_table ) );
		if ( $sec_exists !== $sections_table || $item_exists !== $items_table ) {
			return array(
				'provider'  => 'learnpress',
				'available' => false,
				'course_id' => $course_id,
				'message'   => 'LearnPress curriculum tables were not found; cannot read structure on this version.',
			);
		}

		
		
		$section_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT section_id, section_name, section_order FROM {$sections_table} WHERE section_course_id = %d ORDER BY section_order ASC",
				$course_id
			),
			ARRAY_A
		);

		$sections = array();
		if ( is_array( $section_rows ) ) {
			foreach ( $section_rows as $srow ) {
				$section_id = isset( $srow['section_id'] ) ? (int) $srow['section_id'] : 0;

				
				
				$item_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT si.item_id, si.item_type, si.item_order, p.post_title
						 FROM {$items_table} si
						 LEFT JOIN {$wpdb->posts} p ON p.ID = si.item_id
						 WHERE si.section_id = %d
						 ORDER BY si.item_order ASC",
						$section_id
					),
					ARRAY_A
				);

				$items = array();
				if ( is_array( $item_rows ) ) {
					foreach ( $item_rows as $irow ) {
						$items[] = array(
							'item_id' => isset( $irow['item_id'] ) ? (int) $irow['item_id'] : 0,
							'title'   => isset( $irow['post_title'] ) ? (string) $irow['post_title'] : '',
							'type'    => isset( $irow['item_type'] ) ? (string) $irow['item_type'] : '',
							'order'   => isset( $irow['item_order'] ) ? (int) $irow['item_order'] : 0,
						);
					}
				}

				$sections[] = array(
					'section_id' => $section_id,
					'name'       => isset( $srow['section_name'] ) ? (string) $srow['section_name'] : '',
					'order'      => isset( $srow['section_order'] ) ? (int) $srow['section_order'] : 0,
					'items'      => $items,
				);
			}
		}

		return array(
			'provider'       => 'learnpress',
			'available'      => true,
			'course_id'      => $course_id,
			'course_title'   => get_the_title( $course_id ),
			'section_count'  => count( $sections ),
			'sections'       => $sections,
		);
	}

	private static function list_courses( $args ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider' => 'learnpress',
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
			$price       = get_post_meta( $id, self::PRICE_META, true );
			$courses[]   = array(
				'id'     => $id,
				'title'  => (string) get_the_title( $id ),
				'slug'   => isset( $post->post_name ) ? (string) $post->post_name : '',
				'status' => isset( $post->post_status ) ? (string) $post->post_status : '',
				'price'  => ( '' === $price || null === $price ) ? null : self::normalize_price( $price ),
			);
		}

		return array(
			'provider' => 'learnpress',
			'courses'  => $courses,
			'total'    => count( $courses ),
			'limit'    => $limit,
			'offset'   => $offset,
		);
	}

	private static function list_categories() {
		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array(
				'provider'   => 'learnpress',
				'categories' => array(),
				'total'      => 0,
			);
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::CATEGORY_TAX,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			$terms = array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
				continue;
			}
			$categories[] = array(
				'id'    => (int) $term->term_id,
				'name'  => isset( $term->name ) ? (string) $term->name : '',
				'slug'  => isset( $term->slug ) ? (string) $term->slug : '',
				'count' => isset( $term->count ) ? (int) $term->count : 0,
			);
		}

		return array(
			'provider'   => 'learnpress',
			'categories' => $categories,
			'total'      => count( $categories ),
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

	private static function normalize_price( $price ) {
		return (float) $price;
	}

	private static function enrolled_students() {
		global $wpdb;
		$table = $wpdb->prefix . 'learnpress_user_items';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return null;
		}

		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE item_type = %s",
				self::COURSE_CPT
			)
		);
		return null === $count ? null : (int) $count;
	}
}
