<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WpGoMaps {

	public static function is_available(): bool {
		return defined( 'WPGMZA_VERSION' )
			|| defined( 'WPGMAPS' )
			|| class_exists( 'WPGMZA\Plugin' );
	}

	public static function get_manifest(): array {
		return array(
			'providers'    => array( 'wp-go-maps' ),
			'capabilities' => array( 'mapping' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools(): array {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wpgm_get_status',
				'description' => 'WP Go Maps inventory: every map (id, title, centre coordinates, start zoom, active state) with its marker counts split by the plugin\'s approved flag, plus polygon/polyline/circle/rectangle counts per map. Marker rows themselves are never returned — location data stays out; only aggregate counts. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use WP Go Maps tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Go Maps is not active.' );
		}

		switch ( $name ) {
			case 'wpgm_get_status':
				return self::get_status();
			default:
				throw new \Exception( 'Unknown WP Go Maps tool: ' . esc_html( (string) $name ) );
		}
	}

	private static function get_status(): array {
		global $wpdb;

		$maps_table = $wpdb->prefix . 'wpgmza_maps';

		
		
		$rows = $wpdb->get_results(
			"SELECT id, map_title, map_start_lat, map_start_lng, map_start_zoom, active
			 FROM {$maps_table}
			 ORDER BY id ASC"
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$by_id = array();
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->id ] = $row;
		}
		$rows = $by_id;

		$counts = self::counts_by_map( $wpdb->prefix . 'wpgmza', 'approved' );

		$shape_tables = array(
			'polygons'    => $wpdb->prefix . 'wpgmza_polygon',
			'polylines'   => $wpdb->prefix . 'wpgmza_polylines',
			'circles'     => $wpdb->prefix . 'wpgmza_circles',
			'rectangles'  => $wpdb->prefix . 'wpgmza_rectangles',
		);
		$shape_counts = array();
		foreach ( $shape_tables as $label => $table ) {
			$shape_counts[ $label ] = self::table_exists( $table )
				? self::counts_by_map( $table, null )
				: array();
		}

		$maps = array();
		$total_markers = 0;
		foreach ( $rows as $id => $row ) {
			$mc   = isset( $counts[ $id ] ) ? $counts[ $id ] : array( 'total' => 0, 'approved' => 0, 'unapproved' => 0 );
			$maps[ $id ] = array(
				'id'          => (int) $id,
				'title'       => isset( $row->map_title ) ? (string) $row->map_title : '',
				'lat'         => isset( $row->map_start_lat ) ? (string) $row->map_start_lat : '',
				'lng'         => isset( $row->map_start_lng ) ? (string) $row->map_start_lng : '',
				'start_zoom'  => isset( $row->map_start_zoom ) ? (int) $row->map_start_zoom : null,
				'active'      => isset( $row->active ) ? (int) $row->active : null,
				'live'        => ( isset( $row->active ) && 0 === (int) $row->active ),
				'markers'     => $mc,
				'shapes'      => array(
					'polygons'   => isset( $shape_counts['polygons'][ $id ] ) ? $shape_counts['polygons'][ $id ]['total'] : 0,
					'polylines'  => isset( $shape_counts['polylines'][ $id ] ) ? $shape_counts['polylines'][ $id ]['total'] : 0,
					'circles'    => isset( $shape_counts['circles'][ $id ] ) ? $shape_counts['circles'][ $id ]['total'] : 0,
					'rectangles' => isset( $shape_counts['rectangles'][ $id ] ) ? $shape_counts['rectangles'][ $id ]['total'] : 0,
				),
			);
			$total_markers += $mc['total'];
		}

		return array(
			'provider'             => 'wp-go-maps',
			'version'              => defined( 'WPGMZA_VERSION' ) ? (string) constant( 'WPGMZA_VERSION' ) : null,
			'total_maps'           => count( $maps ),
			'total_markers'        => $total_markers,
			'maps'                 => array_values( $maps ),
		);
	}

	private static function counts_by_map( string $table, $flag_column = null ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		$flag_sql = $flag_column ? ", {$flag_column}" : '';
		$rows     = $wpdb->get_results(
			"SELECT map_id, COUNT(*) AS n{$flag_sql}
			 FROM {$table}
			 WHERE map_id IS NOT NULL
			 GROUP BY map_id" . ( $flag_column ? ", {$flag_column}" : '' )
		);
		$rows     = array_map( fn( $r ) => (array) $r, (array) $rows );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$map_id = (int) $r['map_id'];
			if ( ! isset( $out[ $map_id ] ) ) {
				$out[ $map_id ] = array( 'total' => 0, 'approved' => 0, 'unapproved' => 0 );
			}
			$out[ $map_id ]['total'] += (int) $r['n'];
			if ( $flag_column ) {

				if ( (int) $r[ $flag_column ] === 1 ) {
					$out[ $map_id ]['approved'] += (int) $r['n'];
				} else {
					$out[ $map_id ]['unapproved'] += (int) $r['n'];
				}
			}
		}
		return $out;
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}
}
