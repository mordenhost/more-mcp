<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Log_Store {

	const DEFAULT_RETENTION_DAYS = 30;

	const DEFAULT_ROW_CAP = 50000;

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
			
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total > $cap ) {
				$excess = min( $total - $cap, self::DELETE_BATCH_CEILING );
				
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM `{$table}` ORDER BY timestamp ASC LIMIT %d",
						$excess
					)
				);
			}
		}
	}
}
