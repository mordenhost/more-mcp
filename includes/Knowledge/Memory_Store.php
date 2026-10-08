<?php

namespace More_MCP\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Memory_Store {

	const SCHEMA_VERSION = '1';

	const SCHEMA_OPTION = 'more_mcp_memory_schema';

	const TYPES = array( 'project', 'user', 'feedback', 'reference' );

	const DEFAULT_TYPE = 'project';

	const KEY_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

	const MAX_KEY = 64;

	const MAX_TITLE = 191;

	const MAX_SUMMARY = 300;

	const MAX_BODY_BYTES = 8192;

	const MAX_ENTRIES = 200;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_memory';
	}

	public static function ensure_table(): void {
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION ) ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mem_key varchar(64) NOT NULL,
			title varchar(191) NOT NULL DEFAULT '',
			summary varchar(300) NOT NULL DEFAULT '',
			body text NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'project',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at bigint(20) NOT NULL DEFAULT 0,
			updated_at bigint(20) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY mem_key (mem_key),
			KEY updated_at (updated_at)
		) $charset_collate;" );

		

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check on the plugin-owned table right after creating it.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return;
		}

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, true );
	}

	public static function drop_tables(): void {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup; table name built from the prefix and a literal, then escaped.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		delete_option( self::SCHEMA_OPTION );
	}

	

	
	public static function is_valid_key( string $key ): bool {
		return strlen( $key ) <= self::MAX_KEY && 1 === preg_match( self::KEY_PATTERN, $key );
	}

	

	
	public static function get( string $key ): ?array {
		self::ensure_table();
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; name is prefix + literal. Value is prepared.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE mem_key = %s", $key ), ARRAY_A );
		return is_array( $row ) ? self::shape( $row, true ) : null;
	}

	public static function index( string $type = '' ): array {
		self::ensure_table();
		global $wpdb;
		$table = self::table();
		if ( '' !== $type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; values prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT mem_key, title, summary, type, created_by, updated_by, created_at, updated_at FROM `{$table}` WHERE type = %s ORDER BY updated_at DESC, id DESC LIMIT %d", $type, self::MAX_ENTRIES ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; values prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT mem_key, title, summary, type, created_by, updated_by, created_at, updated_at FROM `{$table}` ORDER BY updated_at DESC, id DESC LIMIT %d", self::MAX_ENTRIES ), ARRAY_A );
		}

		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::shape( $row, false );
			}
		}
		return $out;
	}

	public static function all(): array {
		self::ensure_table();
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table; values prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY updated_at DESC, id DESC LIMIT %d", self::MAX_ENTRIES ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::shape( $row, true );
			}
		}
		return $out;
	}

	public static function count(): int {
		self::ensure_table();
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table, no user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	}

	

	
	public static function save( string $key, array $fields, int $user_id ): string {
		self::ensure_table();
		global $wpdb;
		$now  = time();
		$data = array(
			'title'      => (string) $fields['title'],
			'summary'    => (string) $fields['summary'],
			'body'       => (string) $fields['body'],
			'type'       => (string) $fields['type'],
			'updated_by' => $user_id,
			'updated_at' => $now,
		);

		if ( null !== self::get( $key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; update() prepares.
			$ok = $wpdb->update( self::table(), $data, array( 'mem_key' => $key ), array( '%s', '%s', '%s', '%s', '%d', '%d' ), array( '%s' ) );
			if ( false === $ok ) {
				throw new \RuntimeException( 'The memory entry could not be updated.' );
			}
			return 'updated';
		}

		$data['mem_key']    = $key;
		$data['created_by'] = $user_id;
		$data['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table; insert() prepares.
		$ok = $wpdb->insert( self::table(), $data, array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d' ) );
		if ( false === $ok ) {

			unset( $data['mem_key'], $data['created_by'], $data['created_at'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; update() prepares.
			$updated = null !== self::get( $key )
				? $wpdb->update( self::table(), $data, array( 'mem_key' => $key ), array( '%s', '%s', '%s', '%s', '%d', '%d' ), array( '%s' ) )
				: false;
			if ( false === $updated ) {
				throw new \RuntimeException( 'The memory entry could not be saved.' );
			}
			return 'updated';
		}
		return 'created';
	}

	public static function delete( string $key ): bool {
		self::ensure_table();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table; delete() prepares.
		$deleted = $wpdb->delete( self::table(), array( 'mem_key' => $key ), array( '%s' ) );
		return is_int( $deleted ) && $deleted > 0;
	}

	public static function delete_all(): int {
		self::ensure_table();
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table, no user input.
		$deleted = $wpdb->query( "DELETE FROM `{$table}`" );
		return is_int( $deleted ) ? $deleted : 0;
	}

	

	
	private static function shape( array $row, bool $with_body ): array {
		$out = array(
			'key'        => (string) $row['mem_key'],
			'title'      => (string) $row['title'],
			'summary'    => (string) $row['summary'],
			'type'       => (string) $row['type'],
			'created_by' => (int) $row['created_by'],
			'updated_by' => (int) $row['updated_by'],
			'created_at' => gmdate( 'c', (int) $row['created_at'] ),
			'updated_at' => gmdate( 'c', (int) $row['updated_at'] ),
		);
		if ( $with_body ) {
			$out['body'] = (string) $row['body'];
		}
		return $out;
	}
}
