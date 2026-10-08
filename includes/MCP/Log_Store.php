<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Log_Store {

	const DEFAULT_RETENTION_DAYS = 30;

	const DEFAULT_ROW_CAP = 50000;

	const RETENTION_DAY_CHOICES = array( 7, 30, 90, 180, 0 );

	const ROW_CAP_CHOICES = array( 10000, 50000, 100000, 250000, 0 );

	const DELETE_BATCH_CEILING = 5000;

	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_logs';
	}

	public static function retention_days() {
		$settings = get_option( 'more_mcp_settings', array() );
		$days     = ( is_array( $settings ) && isset( $settings['log_retention_days'] ) && is_numeric( $settings['log_retention_days'] ) )
			? (int) $settings['log_retention_days']
			: self::DEFAULT_RETENTION_DAYS;
		
		$days = (int) apply_filters( 'more_mcp_log_retention_days', $days );
		return $days;
	}

	public static function row_cap() {
		$settings = get_option( 'more_mcp_settings', array() );
		$cap      = ( is_array( $settings ) && isset( $settings['log_row_cap'] ) && is_numeric( $settings['log_row_cap'] ) )
			? (int) $settings['log_row_cap']
			: self::DEFAULT_ROW_CAP;
		
		$cap = (int) apply_filters( 'more_mcp_log_row_cap', $cap );
		return $cap;
	}

	public static function cleanup_expired() {
		global $wpdb;
		$table = esc_sql( self::logs_table() );

		
		
		$days = self::retention_days();
		if ( $days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE timestamp < %s ORDER BY timestamp ASC LIMIT %d",
					$cutoff,
					self::DELETE_BATCH_CEILING
				)
			);
		}

		
		$cap = self::row_cap();
		if ( $cap > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total > $cap ) {
				$excess = min( $total - $cap, self::DELETE_BATCH_CEILING );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM `{$table}` ORDER BY timestamp ASC LIMIT %d",
						$excess
					)
				);
			}
		}
	}

	

	
	public static function actor() {
		$user = wp_get_current_user();
		return array(
			'user_id'    => (int) get_current_user_id(),
			'user_login' => $user && $user->exists() ? (string) $user->user_login : '',
			'credential' => Request_Context::credential(),
			'ip'         => Request_Context::ip(),
		);
	}

	public static function write( $action, array $request, array $response, $status ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct insert to the logs table.
		$ok = $wpdb->insert(
			self::logs_table(),
			array(
				'mcp_server'    => 'MCP Server',
				'action'        => substr( sanitize_text_field( (string) $action ), 0, 100 ),
				'request_data'  => wp_json_encode( $request ),
				'response_data' => wp_json_encode( $response ),
				'status'        => substr( (string) $status, 0, 50 ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function log_refusal( $tool, $reason, array $args = array() ) {
		return self::write(
			'tools/call:' . $tool,
			array(
				'tool'     => (string) $tool,
				'arg_keys' => array_keys( $args ),
				'args'     => Args_Summary::redact( $args ),
				'actor'    => self::actor(),
			),
			array(
				'status' => 'refused',
				'reason' => (string) $reason,
			),
			'refused'
		);
	}

	public static function log_auth_failure( $reason ) {
		$ip  = Request_Context::ip();
		$key = 'more_mcp_authfail_' . md5( $ip );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );

		self::write(
			'auth:failed',
			array(
				'actor' => array(
					'ip' => $ip,
				),
			),
			array(
				'status' => 'refused',
				'reason' => (string) $reason,
			),
			'refused'
		);
	}

	public static function query( array $filters ) {
		global $wpdb;
		$table = esc_sql( self::logs_table() );

		$where  = array( "(action LIKE 'tools/call:%' OR action LIKE 'auth:%')" );
		$params = array();
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['tool'] ) ) {
			$where[]  = 'action LIKE %s';
			$params[] = 'tools/call:%' . $wpdb->esc_like( (string) $filters['tool'] ) . '%';
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'request_data LIKE %s';
			$params[] = '%"actor":{"user_id":' . (int) $filters['user_id'] . ',%';
		}
		if ( ! empty( $filters['credential'] ) ) {
			$where[]  = 'request_data LIKE %s';
			$params[] = '%"credential":"%' . $wpdb->esc_like( (string) $filters['credential'] ) . '%';
		}
		if ( ! empty( $filters['since'] ) ) {
			$ts = strtotime( (string) $filters['since'] );
			if ( $ts ) {
				$where[]  = 'timestamp >= %s';
				$params[] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		$where_sql = implode( ' AND ', $where );
		$limit     = max( 1, min( 100, (int) ( isset( $filters['limit'] ) ? $filters['limit'] : 25 ) ) );
		$offset    = max( 0, (int) ( isset( $filters['offset'] ) ? $filters['offset'] : 0 ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; WHERE text is fixed column names with bound values.
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, timestamp, action, request_data, response_data, status FROM `{$table}` WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $limit, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'total' => $total,
			'rows'  => is_array( $rows ) ? $rows : array(),
		);
	}
}
