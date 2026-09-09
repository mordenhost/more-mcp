<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Estatik {

	const POST_TYPE = 'properties';
	
	const TAXONOMIES = array(
		'es_type',
		'es_category',
		'es_status',
		'es_location',
		'es_label',
		'es_rent_period',
		'es_parking',
		'es_roof',
		'es_exterior_material',
		'es_basement',
		'es_floor_covering',
		'es_feature',
		'es_amenity',
		'es_neighborhood',
		'es_tag',
	);

	public static function is_available() {
		
		return class_exists( '\Estatik' ) && defined( 'ES_FILE' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'estatik' ),
			'capabilities' => array( 'real_estate' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'estatik_get_status',
				'description' => 'Estatik real-estate scale overview: property counts by post status, and per-taxonomy term counts across every taxonomy Estatik registers (types, categories, statuses, locations, labels, rent periods, parking, roof, exterior material, basement, floor covering, features, amenities, neighborhoods, tags). Properties themselves stay on the core post/term/meta tools; this is the one-read aggregate. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'estatik_get_field_schema',
				'description' => 'Estatik property field schema: each custom field\'s machine name, label, type, and the section it belongs to, from Estatik\'s field-builder tables. This is the field-definition discovery core tools cannot provide — the shape of a property listing, not any property\'s values. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'estatik_get_settings',
				'description' => 'Read the Estatik pricing and display settings subset: the store currency, the currency position (before/after the price), and the area unit (sq ft / m² / acres / hectares), from the es_options option Estatik\'s own settings container reads. Configuration only — never agent or owner contact details, never a property. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use real estate tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Estatik is not active.' );
		}
		if ( 'estatik_get_status' === $name ) {
			return self::get_status();
		}
		if ( 'estatik_get_field_schema' === $name ) {
			return self::get_field_schema();
		}
		if ( 'estatik_get_settings' === $name ) {
			return self::get_settings();
		}
		throw new \Exception( 'Unknown real estate tool: ' . esc_html( $name ) );
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

		$taxonomies = array();
		foreach ( self::TAXONOMIES as $taxonomy ) {
			$count = self::count_terms( $taxonomy );
			if ( null !== $count ) {
				$taxonomies[ $taxonomy ] = $count;
			}
		}

		return array(
			'provider'   => 'estatik',
			'properties' => array(
				'total'     => array_sum( $by_status ),
				'by_status' => $by_status,
			),
			'taxonomies' => $taxonomies,
		);
	}

	private static function get_field_schema() {
		global $wpdb;

		if ( ! self::table_exists( 'estatik_fb_fields' ) ) {
			throw new \Exception( 'The Estatik field-builder table does not exist.' );
		}

		$table = $wpdb->prefix . 'estatik_fb_fields';

		$rows = $wpdb->get_results( 
			$wpdb->prepare(
				"SELECT machine_name, label, `type`, section_machine_name FROM {$table} WHERE entity_name = %s ORDER BY id ASC",
				'property'
			)
		);

		$fields = array();
		foreach ( (array) $rows as $row ) {
			$fields[] = array(
				'name'    => (string) $row->machine_name,
				'label'   => (string) $row->label,
				'type'    => (string) $row->type,
				'section' => (string) $row->section_machine_name,
			);
		}

		return array(
			'provider' => 'estatik',
			'fields'   => $fields,
		);
	}

	private static function get_settings() {
		$options = get_option( 'es_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$result = array( 'provider' => 'estatik' );

		foreach ( array( 'currency', 'currency_position', 'area_unit' ) as $key ) {
			if ( isset( $options[ $key ] ) && '' !== (string) $options[ $key ] ) {
				$result[ $key ] = (string) $options[ $key ];
			}
		}

		return $result;
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
