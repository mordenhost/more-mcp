<?php

namespace More_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	public static function names(): array {
		return array( 'more_mcp_history', 'more_mcp_api_tokens', 'more_mcp_approvals', 'more_mcp_device_codes' );
	}

	public static function tables(): array {
		global $wpdb;
		return array_map(
			static function ( $name ) use ( $wpdb ) {
				return $wpdb->prefix . $name;
			},
			self::names()
		);
	}

	public static function create_all(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$history = $wpdb->prefix . 'more_mcp_history';
		$tokens  = $wpdb->prefix . 'more_mcp_api_tokens';
		$approve = $wpdb->prefix . 'more_mcp_approvals';
		$devices = $wpdb->prefix . 'more_mcp_device_codes';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE IF NOT EXISTS $history (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			user_id bigint(20) NOT NULL DEFAULT 0,
			credential varchar(100) NOT NULL DEFAULT '',
			tool varchar(100) NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id varchar(191) NOT NULL,
			summary varchar(255) NOT NULL DEFAULT '',
			before_state longtext,
			after_state longtext,
			state varchar(16) NOT NULL DEFAULT 'applied',
			restored_at datetime NULL,
			restored_by bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY object_ref (object_type, object_id(64))
		) $charset;" );

		dbDelta( "CREATE TABLE IF NOT EXISTS $tokens (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			label varchar(100) NOT NULL,
			token_hash char(64) NOT NULL,
			token_hint varchar(8) NOT NULL DEFAULT '',
			preset varchar(20) NOT NULL DEFAULT 'full',
			user_id bigint(20) NOT NULL,
			site_id bigint(20) NOT NULL DEFAULT 1,
			rate_limit int(11) NOT NULL DEFAULT 0,
			expires_at datetime NULL,
			created_by bigint(20) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			last_used_at datetime NULL,
			last_ip varchar(64) NOT NULL DEFAULT '',
			revoked_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_id (user_id)
		) $charset;" );

		dbDelta( "CREATE TABLE IF NOT EXISTS $approve (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			tool varchar(100) NOT NULL,
			args_hash char(64) NOT NULL,
			summary varchar(255) NOT NULL DEFAULT '',
			credential varchar(100) NOT NULL DEFAULT '',
			user_id bigint(20) NOT NULL DEFAULT 0,
			status varchar(16) NOT NULL DEFAULT 'pending',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			expires_at datetime NOT NULL,
			decided_at datetime NULL,
			decided_by bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY lookup (tool, args_hash, status),
			KEY expires_at (expires_at)
		) $charset;" );

		dbDelta( "CREATE TABLE IF NOT EXISTS $devices (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			device_code_hash char(64) NOT NULL,
			user_code_hash char(64) NOT NULL,
			client_id varchar(255) NOT NULL,
			scope varchar(255) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'pending',
			user_id bigint(20) NOT NULL DEFAULT 0,
			poll_interval int(11) NOT NULL DEFAULT 5,
			last_poll datetime NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY device_code_hash (device_code_hash),
			UNIQUE KEY user_code_hash (user_code_hash),
			KEY expires_at (expires_at)
		) $charset;" );
	}

	public static function exist(): bool {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false;
			}
		}
		return true;
	}

	public static function drop_all(): void {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			$table = esc_sql( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup on uninstall, name from prefix + fixed string.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}
}
