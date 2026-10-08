<?php

namespace More_MCP\Access;

use More_MCP\MCP\Args_Summary;
use More_MCP\MCP\Request_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Approvals {

	const TTL = 900;

	const MAX_PENDING = 100;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_approvals';
	}

	public static function args_hash( string $tool, array $args ): string {
		return hash( 'sha256', $tool . '|' . wp_json_encode( self::canonical( $args ) ) );
	}

	private static function canonical( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = self::canonical( $v );
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		return $value;
	}

	public static function request( string $tool, array $args ): array {
		global $wpdb;
		$table      = esc_sql( self::table() );
		$hash       = self::args_hash( $tool, $args );
		$credential = Request_Context::key();
		$now        = gmdate( 'Y-m-d H:i:s' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, expires_at FROM `{$table}` WHERE tool = %s AND args_hash = %s AND credential = %s AND status = 'pending' AND expires_at > %s ORDER BY id DESC LIMIT 1",
				$tool,
				$hash,
				$credential,
				$now
			),
			ARRAY_A
		);
		if ( $existing ) {
			return array(
				'id'         => (int) $existing['id'],
				'expires_at' => (int) strtotime( $existing['expires_at'] . ' UTC' ),
				'reused'     => true,
			);
		}

		$open = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'pending' AND expires_at > %s", $now )
		);
		// phpcs:enable
		if ( $open >= self::MAX_PENDING ) {
			return array(
				'id'         => 0,
				'expires_at' => 0,
				'reused'     => false,
			);
		}

		$expires = time() + self::TTL;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->insert(
			self::table(),
			array(
				'tool'       => $tool,
				'args_hash'  => $hash,
				'summary'    => substr( Args_Summary::line( $args ), 0, 255 ),
				'credential' => $credential,
				'user_id'    => get_current_user_id(),
				'status'     => 'pending',
				'created_at' => $now,
				'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return array(
			'id'         => (int) $wpdb->insert_id,
			'expires_at' => $expires,
			'reused'     => false,
		);
	}

	public static function consume( string $tool, array $args ): bool {
		global $wpdb;
		$table = esc_sql( self::table() );
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE tool = %s AND args_hash = %s AND credential = %s AND status = 'approved' AND expires_at > %s ORDER BY id DESC LIMIT 1",
				$tool,
				self::args_hash( $tool, $args ),
				Request_Context::key(),
				$now
			)
		);
		if ( $id <= 0 ) {
			return false;
		}
		$spent = $wpdb->query(
			$wpdb->prepare( "UPDATE `{$table}` SET status = 'used' WHERE id = %d AND status = 'approved'", $id )
		);
		// phpcs:enable
		return 1 === (int) $spent;
	}

	public static function decide( int $id, string $decision, int $user_id ): bool {
		global $wpdb;
		if ( ! in_array( $decision, array( 'approved', 'denied' ), true ) ) {
			return false;
		}
		$table = esc_sql( self::table() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix + fixed string.
		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = %s, decided_at = %s, decided_by = %d, expires_at = IF(%s = 'approved', %s, expires_at) WHERE id = %d AND status = 'pending' AND expires_at > %s",
				$decision,
				gmdate( 'Y-m-d H:i:s' ),
				$user_id,
				$decision,
				gmdate( 'Y-m-d H:i:s', time() + self::TTL ),
				$id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return 1 === (int) $changed;
	}

	public static function approve_from_chat( int $id ): bool {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix + fixed string.
		$owned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE id = %d AND credential = %s AND status = 'pending' AND expires_at > %s",
				$id,
				Request_Context::key(),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $owned === $id && self::decide( $id, 'approved', get_current_user_id() );
	}

	public static function recent( int $limit = 30 ): array {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix + fixed string.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, tool, summary, credential, user_id, status, created_at, expires_at, decided_at FROM `{$table}` ORDER BY (status = 'pending' AND expires_at > %s) DESC, id DESC LIMIT %d",
				gmdate( 'Y-m-d H:i:s' ),
				max( 1, $limit )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function pending_count(): int {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'pending' AND expires_at > %s", gmdate( 'Y-m-d H:i:s' ) ) );
	}

	public static function refusal_message( string $tool, array $request ): string {
		if ( $request['id'] <= 0 ) {
			return sprintf( 'Blocked: "%s" needs a person\'s approval and too many approval requests are already waiting. Do not retry; ask the site owner to clear them in More MCP > Safety.', $tool );
		}
		$minutes = (int) round( self::TTL / 60 );
		if ( 'chat' === Safety::approval_mode() ) {
			return sprintf(
				'Approval required: "%1$s" is a high-impact action and this site asks for confirmation first (request #%2$d, valid %3$d minutes). Tell the user exactly what you are about to do and wait for them to clearly agree. Only then call more_mcp_approve_request with approval_id %2$d, and repeat this same call with the same arguments. Do not approve it yourself without their answer.',
				$tool,
				$request['id'],
				$minutes
			);
		}
		return sprintf(
			'Approval required: "%1$s" is a high-impact action and the site owner has to approve it first (request #%2$d, valid %3$d minutes). Tell the user to approve it at %4$s, then repeat this same call with the same arguments. Do not retry before they confirm.',
			$tool,
			$request['id'],
			$minutes,
			admin_url( 'admin.php?page=more-mcp-safety' )
		);
	}

	public static function cleanup_expired(): void {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) ) ) );
	}
}
