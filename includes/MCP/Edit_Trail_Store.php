<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Edit_Trail_Store {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_edit_trail';
	}

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE IF NOT EXISTS $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			element_id varchar(64) NOT NULL,
			tool varchar(64) NOT NULL,
			keys_touched text NOT NULL,
			edited_at bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY post_element (post_id, element_id)
		) $charset_collate;" );
	}

	public static function drop_tables() {
		global $wpdb;
		$table = esc_sql( self::table() );
		
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	public static function record( $post_id, $element_id, $tool, array $keys_touched = [] ) {
		global $wpdb;
		$post_id    = (int) $post_id;
		$element_id = (string) $element_id;
		if ( $post_id <= 0 || '' === $element_id ) {
			return false;
		}

		
		$keys = array_values( array_unique( array_map( 'strval', $keys_touched ) ) );
		$json = wp_json_encode( $keys );

		$table = self::table();
		
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, element_id, tool, keys_touched, edited_at)
				 VALUES (%d, %s, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE tool = VALUES(tool), keys_touched = VALUES(keys_touched), edited_at = VALUES(edited_at)",
				$post_id,
				$element_id,
				(string) $tool,
				$json,
				time()
			)
		);

		return false !== $result;
	}

	public static function delete_element( $post_id, $element_id ) {
		global $wpdb;
		$post_id    = (int) $post_id;
		$element_id = (string) $element_id;
		if ( $post_id <= 0 || '' === $element_id ) {
			return;
		}
		
		$wpdb->delete( self::table(), [ 'post_id' => $post_id, 'element_id' => $element_id ], [ '%d', '%s' ] );
	}

	public static function get_for_post( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return [];
		}
		$table = self::table();
		
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT element_id, tool, keys_touched, edited_at FROM {$table} WHERE post_id = %d", $post_id ),
			ARRAY_A
		);
		$out = [];
		foreach ( (array) $rows as $row ) {
			$element_id = (string) ( $row['element_id'] ?? '' );
			if ( '' === $element_id ) {
				continue;
			}
			$keys = json_decode( (string) ( $row['keys_touched'] ?? '[]' ), true );
			$out[ $element_id ] = [
				'tool' => (string) ( $row['tool'] ?? '' ),
				'keys' => is_array( $keys ) ? array_values( $keys ) : [],
				'at'   => isset( $row['edited_at'] ) ? (int) $row['edited_at'] : 0,
			];
		}
		return $out;
	}

	public static function delete_for_post( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		
		$wpdb->delete( self::table(), [ 'post_id' => $post_id ], [ '%d' ] );
	}
}
