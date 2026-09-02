<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GeoDirectory {

	public static function is_available() {

		return function_exists( 'geodir_get_posttypes' ) && defined( 'GEODIRECTORY_VERSION' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'geodirectory' ),
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
				'name'        => 'geodir_get_status',
				'description' => 'GeoDirectory scale overview: for each listing post type GeoDirectory registers (gd_place and any others), the listing counts by post status and the number of categories in that post type\'s category taxonomy. Listings themselves stay on the core post/term/meta tools; this is the one-read aggregate across every directory CPT. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'geodir_get_field_schema',
				'description' => 'The custom-field schema for a GeoDirectory listing post type: each active field\'s htmlvar name, admin/frontend title, field type (text/select/checkbox/…), data type, and whether it is required. This is the field-definition discovery core tools cannot provide — the shape of a listing, not its content or any listing\'s field values. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'A GeoDirectory listing post type (e.g. gd_place). Defaults to gd_place.' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use directory tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'GeoDirectory is not active.' );
		}
		if ( 'geodir_get_status' === $name ) {
			return self::get_status();
		}
		if ( 'geodir_get_field_schema' === $name ) {
			$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'gd_place';
			return self::get_field_schema( $post_type );
		}
		throw new \Exception( 'Unknown directory tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
		$post_types = self::post_types();
		$out        = array();
		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {

				$out[] = array(
					'post_type'  => $post_type,
					'registered' => false,
				);
				continue;
			}
			$counts    = wp_count_posts( $post_type );
			$by_status = array();
			foreach ( (array) $counts as $status => $n ) {
				if ( is_numeric( $n ) && (int) $n > 0 ) {
					$by_status[ (string) $status ] = (int) $n;
				}
			}
			$taxonomy = $post_type . 'category';
			$out[] = array(
				'post_type'   => $post_type,
				'registered'  => true,
				'by_status'   => $by_status,
				'total'       => array_sum( $by_status ),
				'categories'  => self::count_terms( $taxonomy ),
			);
		}
		return array(
			'provider'   => 'geodirectory',
			'post_types' => $out,
		);
	}

	private static function get_field_schema( $post_type ) {
		global $wpdb;

		if ( ! self::field_table_exists() ) {
			throw new \Exception( 'The GeoDirectory custom-fields table does not exist.' );
		}
		if ( '' === $post_type ) {
			throw new \Exception( 'post_type is required.' );
		}

		$table = self::field_table();

		$rows = $wpdb->get_results( 
			$wpdb->prepare(
				"SELECT htmlvar_name, admin_title, frontend_title, field_type, data_type, is_required, is_default FROM {$table} WHERE post_type = %s AND is_active = 1 ORDER BY sort_order ASC",
				$post_type
			)
		);

		$fields = array();
		foreach ( (array) $rows as $row ) {
			$fields[] = array(
				'name'      => (string) $row->htmlvar_name,
				'label'     => (string) ( $row->frontend_title !== '' ? $row->frontend_title : $row->admin_title ),
				'field_type' => (string) $row->field_type,
				'data_type' => (string) $row->data_type,
				'required'  => (int) $row->is_required === 1,
				'default'   => (int) $row->is_default === 1,
			);
		}

		return array(
			'provider'  => 'geodirectory',
			'post_type' => $post_type,
			'fields'    => $fields,
		);
	}

	private static function post_types() {
		$types = geodir_get_posttypes( 'names' );
		if ( is_array( $types ) && ! empty( $types ) ) {
			return array_values( array_filter( array_map( 'strval', $types ) ) );
		}
		return array( 'gd_place' );
	}

	private static function field_table() {
		if ( defined( 'GEODIR_CUSTOM_FIELDS_TABLE' ) ) {
			return GEODIR_CUSTOM_FIELDS_TABLE;
		}
		global $wpdb;
		return $wpdb->prefix . 'geodir_custom_fields';
	}

	private static function field_table_exists() {
		global $wpdb;
		$table = self::field_table();
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
