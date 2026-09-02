<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJobManager {

	public static function is_available() {
		
		return class_exists( '\WP_Job_Manager_Post_Types' ) && defined( '\WP_Job_Manager_Post_Types::PT_LISTING' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wp-job-manager' ),
			'capabilities' => array( 'job_board' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'wpjm_get_job_stats',
				'description' => 'WP Job Manager aggregate job-board statistics: job counts by status (publish, pending, expired, preview, and pending_payment when the paid listings extension is active), job-category and job-type term counts, the number of filled and featured listings, and the number of published listings already expired or expiring within 7 days. Listings themselves stay on the core post/term/meta tools; this is the one-read operational aggregate. Application data (a paid add-on holding applicant personal data) is never touched. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use job board tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Job Manager is not active.' );
		}
		if ( 'wpjm_get_job_stats' === $name ) {
			return self::get_job_stats();
		}
		throw new \Exception( 'Unknown WP Job Manager tool: ' . esc_html( $name ) );
	}

	private static function get_job_stats() {
		$counts = wp_count_posts( \WP_Job_Manager_Post_Types::PT_LISTING );

		$by_status = array(
			'publish' => (int) ( $counts->publish ?? 0 ),
			'pending' => (int) ( $counts->pending ?? 0 ),
			'expired' => (int) ( $counts->expired ?? 0 ),
			'preview' => (int) ( $counts->preview ?? 0 ),
		);

		$pending_payment = isset( $counts->pending_payment ) ? (int) $counts->pending_payment : null;

		return array(
			'provider'   => 'wp-job-manager',
			'jobs'       => array(
				'total'      => array_sum( $by_status ),
				'by_status'  => $by_status,

				'pending_payment' => $pending_payment,
				'filled'     => self::count_checked_meta( '_filled' ),
				'featured'   => self::count_checked_meta( '_featured' ),
				'expiring'   => self::count_expiring_soon(),
			),
			'taxonomies' => array(
				'categories' => self::count_terms( 'job_listing_category' ),
				'types'      => self::count_terms( 'job_listing_type' ),
			),
		);
	}

	private static function count_checked_meta( $meta_key ) {
		$query = new \WP_Query(
			array(
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => array( 'publish', 'expired' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( 
					array(
						'key'     => $meta_key,
						'value'   => array( '1', 'yes' ),
						'compare' => 'IN',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	private static function count_expiring_soon() {
		$query = new \WP_Query(
			array(
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( 
					array(
						'key'     => '_job_expires',
						'value'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	private static function count_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		return is_numeric( $count ) ? (int) $count : 0;
	}
}
