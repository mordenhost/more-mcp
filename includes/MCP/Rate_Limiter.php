<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rate_Limiter {

	const ROW_PREFIX = 'more_mcp_rate_row_';

	const TRANSIENT_PREFIX = 'more_mcp_rate_';

	public static function check( $ip, $max, $window ) {
		if ( wp_using_ext_object_cache() ) {
			return self::check_options( $ip, $max, $window );
		}
		return self::check_transient( $ip, $max, $window );
	}

	public static function backend() {
		return wp_using_ext_object_cache() ? 'wp-options' : 'transient';
	}

	private static function check_options( $ip, $max, $window ) {
		$key  = self::ROW_PREFIX . md5( $ip );
		$now  = time();
		$data = get_option( $key );

		if ( ! is_array( $data ) || ! isset( $data['start'] ) || $now - (int) $data['start'] > $window ) {
			update_option( $key, [ 'count' => 1, 'start' => $now ], false );
			return true;
		}

		$data['count']++;
		update_option( $key, $data, false );

		return (int) $data['count'] <= $max;
	}

	private static function check_transient( $ip, $max, $window ) {
		$key  = self::TRANSIENT_PREFIX . md5( $ip );
		$data = get_transient( $key );

		if ( false === $data ) {
			set_transient( $key, [ 'count' => 1, 'start' => time() ], $window );
			return true;
		}

		if ( time() - (int) $data['start'] > $window ) {
			set_transient( $key, [ 'count' => 1, 'start' => time() ], $window );
			return true;
		}

		$data['count']++;
		set_transient( $key, $data, $window );

		return (int) $data['count'] <= $max;
	}

	public static function cleanup_expired() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$wpdb->esc_like( self::ROW_PREFIX ) . '%'
			)
		);
	}
}
