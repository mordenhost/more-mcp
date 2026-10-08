<?php

namespace More_MCP\Auth;

use More_MCP\Access\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Api_Tokens {

	const PREFIX       = 'mmcp_t_';
	const RANDOM_BYTES = 24;
	const OPTION       = 'more_mcp_token_settings';

	const MAX_PER_USER = 10;

	const PRESET_READ_ONLY = 'read_only';
	const PRESET_AUTHOR    = 'author';
	const PRESET_CONTENT   = 'content';
	const PRESET_EDITOR    = 'editor';
	const PRESET_FULL      = 'full';
	const PRESET_CUSTOM    = 'custom';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_api_tokens';
	}

	

	
	public static function presets(): array {
		return array(
			self::PRESET_READ_ONLY => array(
				'label'       => __( 'Read only', 'mordenhost-mcp-server' ),
				'description' => __( 'Can look at the site, cannot change anything.', 'mordenhost-mcp-server' ),
			),
			self::PRESET_AUTHOR    => array(
				'label'       => __( 'Author', 'mordenhost-mcp-server' ),
				'description' => __( 'Create and edit posts and upload media.', 'mordenhost-mcp-server' ),
			),
			self::PRESET_CONTENT   => array(
				'label'       => __( 'Content manager', 'mordenhost-mcp-server' ),
				'description' => __( 'Posts, pages, media, comments and SEO fields.', 'mordenhost-mcp-server' ),
			),
			self::PRESET_EDITOR    => array(
				'label'       => __( 'Site editor', 'mordenhost-mcp-server' ),
				'description' => __( 'Content manager plus menus, widgets, theme settings, page builders, custom fields and forms.', 'mordenhost-mcp-server' ),
			),
			self::PRESET_FULL      => array(
				'label'       => __( 'Full access', 'mordenhost-mcp-server' ),
				'description' => __( 'Everything the token\'s WordPress user can do.', 'mordenhost-mcp-server' ),
			),
			self::PRESET_CUSTOM    => array(
				'label'       => __( 'Custom', 'mordenhost-mcp-server' ),
				'description' => __( 'Choose read or read-and-write for each area.', 'mordenhost-mcp-server' ),
			),
		);
	}

	const READABLE_AREAS = array( 'content', 'media', 'comments', 'appearance', 'seo', 'builders', 'fields', 'imported', 'integrations' );

	public static function policy_for( string $preset, array $groups = array() ): array {
		switch ( $preset ) {
			case self::PRESET_READ_ONLY:
				return array( 'access' => Policy::READ_ONLY, 'groups' => array() );
			case self::PRESET_FULL:
				return array( 'access' => Policy::FULL, 'groups' => array() );
			case self::PRESET_CUSTOM:
				return Policy::normalize( array( 'access' => Policy::CUSTOM, 'groups' => $groups ) );
		}

		$write = array(
			self::PRESET_AUTHOR  => array( 'content', 'media' ),
			self::PRESET_CONTENT => array( 'content', 'media', 'comments', 'seo' ),
			self::PRESET_EDITOR  => array( 'content', 'media', 'comments', 'seo', 'appearance', 'builders', 'fields', 'forms' ),
		);
		$levels = array();
		foreach ( self::READABLE_AREAS as $area ) {
			$levels[ $area ] = Policy::LEVEL_READ;
		}
		foreach ( $write[ $preset ] ?? array() as $area ) {
			$levels[ $area ] = Policy::LEVEL_WRITE;
		}
		return Policy::normalize( array( 'access' => Policy::CUSTOM, 'groups' => $levels ) );
	}

	

	
	private static function settings(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function self_service_enabled(): bool {
		$s = self::settings();
		return ! empty( $s['self_service'] );
	}

	public static function save_settings( array $input ): void {
		$s = self::settings();
		if ( array_key_exists( 'self_service', $input ) ) {
			$s['self_service'] = in_array( $input['self_service'], array( true, 1, '1', 'true', 'on' ), true ) ? 1 : 0;
		}
		update_option( self::OPTION, $s, false );
	}

	

	
	public static function key_for( int $id ): string {
		return 'token:' . $id;
	}

	public static function create( array $args ): array {
		global $wpdb;

		$label = trim( sanitize_text_field( (string) ( $args['label'] ?? '' ) ) );
		if ( '' === $label ) {
			throw new \Exception( 'A token needs a label, so you can tell it apart later.' );
		}
		$label = substr( $label, 0, 100 );

		$preset = (string) ( $args['preset'] ?? self::PRESET_READ_ONLY );
		if ( ! isset( self::presets()[ $preset ] ) ) {
			throw new \Exception( 'Unknown access level.' );
		}

		$user_id = (int) ( $args['user_id'] ?? get_current_user_id() );
		if ( ! get_userdata( $user_id ) ) {
			throw new \Exception( 'The WordPress user for this token does not exist.' );
		}

		$days       = isset( $args['expires_in_days'] ) ? (int) $args['expires_in_days'] : 0;
		$expires_at = $days > 0 ? gmdate( 'Y-m-d H:i:s', time() + min( $days, 3650 ) * DAY_IN_SECONDS ) : null;
		$rate_limit = max( 0, min( 6000, (int) ( $args['rate_limit'] ?? 0 ) ) );

		$raw = self::PREFIX . bin2hex( random_bytes( self::RANDOM_BYTES ) );

		$row = array(
			'label'      => $label,
			'token_hash' => Hasher::digest( $raw ),
			'token_hint' => substr( $raw, -4 ),
			'preset'     => $preset,
			'user_id'    => $user_id,
			'site_id'    => get_current_blog_id(),
			'rate_limit' => $rate_limit,
			'expires_at' => $expires_at,
			'created_by' => get_current_user_id(),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$ok = $wpdb->insert( self::table(), $row, array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s' ) );
		if ( ! $ok ) {
			throw new \Exception( 'Could not save the token.' );
		}
		$id = (int) $wpdb->insert_id;

		Policy::save( self::key_for( $id ), self::policy_for( $preset, isset( $args['groups'] ) && is_array( $args['groups'] ) ? $args['groups'] : array() ) );

		return array(
			'id'         => $id,
			'token'      => $raw,
			'label'      => $label,
			'preset'     => $preset,
			'user_id'    => $user_id,
			'expires_at' => $expires_at,
			'rate_limit' => $rate_limit,
		);
	}

	public static function authenticate( string $raw ): array {
		global $wpdb;
		if ( 0 !== strpos( $raw, self::PREFIX ) || strlen( $raw ) !== strlen( self::PREFIX ) + self::RANDOM_BYTES * 2 ) {
			return array( 'row' => null, 'error' => 'malformed' );
		}
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.

		
		$candidates   = Hasher::candidates( $raw );
		$placeholders = implode( ', ', array_fill( 0, count( $candidates ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own table; the IN list is one %s per candidate digest.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE token_hash IN ({$placeholders}) LIMIT 1", $candidates ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return array( 'row' => null, 'error' => 'unknown' );
		}
		if ( ! empty( $row['revoked_at'] ) ) {
			return array( 'row' => $row, 'error' => 'revoked' );
		}
		if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			return array( 'row' => $row, 'error' => 'expired' );
		}
		if ( is_multisite() && (int) $row['site_id'] !== get_current_blog_id() ) {
			return array( 'row' => $row, 'error' => 'wrong_site' );
		}
		if ( ! get_userdata( (int) $row['user_id'] ) ) {
			return array( 'row' => $row, 'error' => 'user_gone' );
		}
		self::rehash_if_stale( $row, $raw );
		return array( 'row' => $row, 'error' => '' );
	}

	private static function rehash_if_stale( array $row, string $raw ): void {
		global $wpdb;
		if ( Hasher::is_current( (string) $row['token_hash'], $raw ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->update( self::table(), array( 'token_hash' => Hasher::digest( $raw ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
	}

	public static function touch( array $row, string $ip ): void {
		global $wpdb;
		$last = ! empty( $row['last_used_at'] ) ? (int) strtotime( $row['last_used_at'] . ' UTC' ) : 0;
		if ( time() - $last < 60 && (string) $row['last_ip'] === $ip ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->update(
			self::table(),
			array(
				'last_used_at' => gmdate( 'Y-m-d H:i:s' ),
				'last_ip'      => substr( $ip, 0, 64 ),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function rate_limit( array $row ): int {
		return max( 0, (int) ( $row['rate_limit'] ?? 0 ) );
	}

	public static function revoke( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$changed = $wpdb->update(
			self::table(),
			array( 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $id, 'revoked_at' => null ),
			array( '%s' ),
			array( '%d', '%s' )
		);
		Policy::forget( self::key_for( $id ) );
		return (bool) $changed;
	}

	public static function delete( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		Policy::forget( self::key_for( $id ) );
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function all( int $user_id = 0 ): array {
		global $wpdb;
		$table = esc_sql( self::table() );
		$cols  = 'id, label, token_hint, preset, user_id, site_id, rate_limit, expires_at, created_by, created_at, last_used_at, last_ip, revoked_at';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; columns are a fixed list.
		if ( $user_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$cols} FROM `{$table}` WHERE user_id = %d AND site_id = %d ORDER BY id DESC", $user_id, get_current_blog_id() ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$cols} FROM `{$table}` WHERE site_id = %d ORDER BY id DESC", get_current_blog_id() ), ARRAY_A );
		}
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	public static function active_count( int $user_id ): int {
		$count = 0;
		foreach ( self::all( $user_id ) as $row ) {
			if ( empty( $row['revoked_at'] ) && ( empty( $row['expires_at'] ) || strtotime( $row['expires_at'] . ' UTC' ) >= time() ) ) {
				++$count;
			}
		}
		return $count;
	}

	public static function cleanup_expired(): void {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE (revoked_at IS NOT NULL AND revoked_at < %s) OR (expires_at IS NOT NULL AND expires_at < %s)", $cutoff, $cutoff ) );
	}
}
