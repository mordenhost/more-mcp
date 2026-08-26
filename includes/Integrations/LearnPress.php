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
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use LMS tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'LearnPress is not active.' );
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
