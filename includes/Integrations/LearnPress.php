<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LearnPress {

	const COURSE_CPT = 'lp_course';

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
