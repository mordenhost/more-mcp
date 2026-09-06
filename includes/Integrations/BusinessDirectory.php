<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BusinessDirectory {

	const POST_TYPE = 'wpbdp_listing';
	const CATEGORY_TAX = 'wpbdp_category';

	public static function is_available() {

		return function_exists( 'wpbdp' ) && defined( 'WPBDP_VERSION' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'business-directory' ),
			'capabilities' => array( 'directories' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'bdp_get_status',
				'description' => 'Business Directory Plugin scale overview: listing counts by post status, the number of directory categories, and the number of fee plans (total and enabled). Listings themselves stay on the core post/term/meta tools; this is the one-read aggregate. Payment and payer data is never touched. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bdp_get_field_schema',
				'description' => 'The directory form-field schema for Business Directory Plugin: each field\'s label, field type, association (which listing aspect it maps to: title, content, category, meta, …), and shortname, in the plugin\'s own display order. This is the field-definition discovery core tools cannot provide — the shape of a listing form, not any listing\'s values. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use directory tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Business Directory Plugin is not active.' );
		}
		if ( 'bdp_get_status' === $name ) {
			return self::get_status();
		}
		if ( 'bdp_get_field_schema' === $name ) {
			return self::get_field_schema();
		}
		throw new \Exception( 'Unknown directory tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
		$by_status = array();
		if ( post_type_exists( self::POST_TYPE ) ) {
			$counts = wp_count_posts( self::POST_TYPE );
			foreach ( (array) $counts as $status => $n ) {
				if ( is_numeric( $n ) && (int) $n > 0 ) {
					$by_status[ (string) $status ] = (int) $n;
				}
			}
		}

		return array(
			'provider'   => 'business-directory',
			'listings'   => array(
				'total'     => array_sum( $by_status ),
				'by_status' => $by_status,
			),
			'categories' => self::count_terms( self::CATEGORY_TAX ),
			'plans'      => self::plan_counts(),
		);
	}

	private static function get_field_schema() {
		global $wpdb;

		if ( ! self::table_exists( 'wpbdp_form_fields' ) ) {
			throw new \Exception( 'The Business Directory form-fields table does not exist.' );
		}

		$table = $wpdb->prefix . 'wpbdp_form_fields';

		$rows = $wpdb->get_results( 
			"SELECT label, field_type, association, shortname, weight FROM {$table} ORDER BY weight ASC, id ASC"
		);

		$fields = array();
		foreach ( (array) $rows as $row ) {
			$fields[] = array(
				'label'       => (string) $row->label,
				'field_type'  => (string) $row->field_type,
				'association' => (string) $row->association,
				'shortname'   => (string) $row->shortname,
			);
		}

		return array(
			'provider' => 'business-directory',
			'fields'   => $fields,
		);
	}

	private static function plan_counts() {
		global $wpdb;
		if ( ! self::table_exists( 'wpbdp_plans' ) ) {
			return null;
		}
		$table   = $wpdb->prefix . 'wpbdp_plans';
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); 
		$enabled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE enabled = 1" ); 
		return array( 'total' => $total, 'enabled' => $enabled );
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}

	private static function count_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		return is_numeric( $count ) ? (int) $count : 0;
	}
}
