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
			array(
				'name'        => 'wpjm_get_job_types',
				'description' => 'List WP Job Manager job types (job_listing_type taxonomy). Returns id, name, slug, and count. Surfaces the option-gated state distinctly: when the "Enable job types" option is off the tool reports types_enabled=false with an empty list, which is NOT the same as zero types defined. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wpjm_get_job_categories',
				'description' => 'List WP Job Manager job categories (job_listing_category taxonomy). Returns id, name, slug, and count. Surfaces the option-gated state: when the "Enable categories" option is off the tool reports categories_enabled=false with an empty list, distinct from zero categories defined. Read-only.',
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
		if ( 'wpjm_get_job_types' === $name ) {
			return self::get_job_types();
		}
		if ( 'wpjm_get_job_categories' === $name ) {
			return self::get_job_categories();
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

	private static function get_job_types() {
		$enabled = (bool) get_option( 'job_manager_enable_types', false );
		if ( ! $enabled ) {
			return array(
				'provider'       => 'wp-job-manager',
				'types_enabled'  => false,
				'message'        => 'Job types are disabled by the "Enable job types" option.',
				'types'          => array(),
			);
		}

		if ( ! function_exists( 'get_job_listing_types' ) ) {
			return array( 'provider' => 'wp-job-manager', 'types_enabled' => true, 'available' => false, 'message' => 'get_job_listing_types() is not available.', 'types' => array() );
		}

		$terms = get_job_listing_types();
		$types = array();
		foreach ( (array) $terms as $term ) {
			if ( ! is_object( $term ) ) {
				continue;
			}
			$types[] = array(
				'id'    => (int) $term->term_id,
				'name'  => (string) $term->name,
				'slug'  => (string) $term->slug,
				'count' => (int) $term->count,
			);
		}

		return array(
			'provider'      => 'wp-job-manager',
			'types_enabled' => true,
			'total'         => count( $types ),
			'types'         => $types,
		);
	}

	private static function get_job_categories() {
		$enabled = (bool) get_option( 'job_manager_enable_categories', false );
		if ( ! $enabled ) {
			return array(
				'provider'           => 'wp-job-manager',
				'categories_enabled' => false,
				'message'            => 'Job categories are disabled by the "Enable categories" option.',
				'categories'         => array(),
			);
		}

		if ( ! function_exists( 'get_job_listing_categories' ) ) {
			return array( 'provider' => 'wp-job-manager', 'categories_enabled' => true, 'available' => false, 'message' => 'get_job_listing_categories() is not available.', 'categories' => array() );
		}

		$terms = get_job_listing_categories();
		$categories = array();
		foreach ( (array) $terms as $term ) {
			if ( ! is_object( $term ) ) {
				continue;
			}
			$categories[] = array(
				'id'    => (int) $term->term_id,
				'name'  => (string) $term->name,
				'slug'  => (string) $term->slug,
				'count' => (int) $term->count,
			);
		}

		return array(
			'provider'           => 'wp-job-manager',
			'categories_enabled' => true,
			'total'              => count( $categories ),
			'categories'         => $categories,
		);
	}
}
