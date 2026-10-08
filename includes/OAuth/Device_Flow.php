<?php

namespace More_MCP\OAuth;

use More_MCP\Auth\Hasher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Device_Flow {

	const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:device_code';

	const TTL = 900;

	const INTERVAL = 5;

	const USER_CODE_ALPHABET = 'BCDFGHJKLMNPQRSTVWXZ';

	const USER_CODE_LENGTH = 8;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_device_codes';
	}

	public static function format_user_code( string $code ): string {
		return substr( $code, 0, 4 ) . '-' . substr( $code, 4 );
	}

	public static function normalize_user_code( string $code ): string {
		return strtoupper( preg_replace( '/[^A-Za-z]/', '', $code ) );
	}

	private static function generate_user_code(): string {
		$out = '';
		$max = strlen( self::USER_CODE_ALPHABET ) - 1;
		for ( $i = 0; $i < self::USER_CODE_LENGTH; $i++ ) {
			$out .= self::USER_CODE_ALPHABET[ random_int( 0, $max ) ];
		}
		return $out;
	}

	private static function in_list( string $raw ): array {
		$hashes = Hasher::candidates( $raw );
		return array( implode( ', ', array_fill( 0, count( $hashes ), '%s' ) ), $hashes );
	}

	public static function create( string $client_id, string $scope ): ?array {
		global $wpdb;

		$device_code = bin2hex( random_bytes( 32 ) );
		$expires     = gmdate( 'Y-m-d H:i:s', time() + self::TTL );

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$user_code = self::generate_user_code();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
			$ok = $wpdb->insert(
				self::table(),
				array(
					'device_code_hash' => Hasher::digest( $device_code ),
					'user_code_hash'   => Hasher::digest( $user_code ),
					'client_id'        => $client_id,
					'scope'            => $scope,
					'status'           => 'pending',
					'poll_interval'    => self::INTERVAL,
					'expires_at'       => $expires,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( $ok ) {
				return array(
					'device_code' => $device_code,
					'user_code'   => $user_code,
					'expires_in'  => self::TTL,
					'interval'    => self::INTERVAL,
				);
			}
		}
		return null;
	}

	public static function find_pending_by_user_code( string $raw ): ?array {
		global $wpdb;
		$code = self::normalize_user_code( $raw );
		if ( strlen( $code ) !== self::USER_CODE_LENGTH ) {
			return null;
		}
		$table               = self::table();
		list( $in, $hashes ) = self::in_list( $code );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; one %s per candidate digest.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE user_code_hash IN ({$in}) AND status = 'pending' AND expires_at > %s LIMIT 1",
				array_merge( $hashes, array( gmdate( 'Y-m-d H:i:s' ) ) )
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function decide( int $id, bool $approve, int $user_id ): bool {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from helper; values prepared.
		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = %s, user_id = %d WHERE id = %d AND status = 'pending' AND expires_at > %s",
				$approve ? 'approved' : 'denied',
				$user_id,
				$id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		return 1 === (int) $changed;
	}

	public static function poll( string $raw_device_code, string $client_id ): array {
		global $wpdb;
		$table               = self::table();
		list( $in, $hashes ) = self::in_list( $raw_device_code );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; one %s per candidate digest.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE device_code_hash IN ({$in}) LIMIT 1", $hashes ), ARRAY_A );

		
		if ( ! $row || ! hash_equals( (string) $row['client_id'], $client_id ) ) {
			return array( 'status' => 'invalid' );
		}

		$now = time();
		if ( strtotime( $row['expires_at'] . ' UTC' ) <= $now ) {
			return array( 'status' => 'expired' );
		}

		switch ( $row['status'] ) {
			case 'denied':
				return array( 'status' => 'denied' );

			case 'approved':
				
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from helper; values prepared.
				$claimed = $wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET status = 'used' WHERE id = %d AND status = 'approved'", (int) $row['id'] ) );
				if ( 1 !== (int) $claimed ) {
					return array( 'status' => 'invalid' );
				}
				return array(
					'status'  => 'approved',
					'user_id' => (int) $row['user_id'],
					'scope'   => (string) $row['scope'],
				);

			case 'pending':
				$interval = max( self::INTERVAL, (int) $row['poll_interval'] );
				$last     = empty( $row['last_poll'] ) ? 0 : (int) strtotime( $row['last_poll'] . ' UTC' );
				$too_fast = $last > 0 && ( $now - $last ) < $interval;
				if ( $too_fast ) {
					$interval += self::INTERVAL;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from helper; values prepared.
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET last_poll = %s, poll_interval = %d WHERE id = %d", gmdate( 'Y-m-d H:i:s', $now ), $interval, (int) $row['id'] ) );
				return array( 'status' => $too_fast ? 'slow_down' : 'pending' );
		}

		return array( 'status' => 'invalid' );
	}

	public static function cleanup_expired(): void {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) ) );
	}
}
