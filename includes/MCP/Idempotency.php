<?php

namespace Mordenhost_MCP\MCP;

use Mordenhost_MCP\Abilities\Tool_Capabilities;
use Mordenhost_MCP\Access\Approvals;
use Mordenhost_MCP\Tools\Options_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Idempotency {

	const ARG = 'idempotency_key';

	const OPTION_PREFIX = 'mordenhost_mcp_idem_';

	const TTL = 3600;

	const RUNNING_GRACE = 300;

	const MAX_RESULT_BYTES = 32768;

	const KEY_PATTERN = '/^[A-Za-z0-9._:\-]{8,128}$/';

	const NOT_KEPT = '[not kept for replay]';

	const SECRET_FRAGMENTS = array( 'password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'credential', 'cookie', 'private_key', 'nonce', 'salt', 'license_key', 'bearer' );

	const NAME_FIELDS = array( 'key', 'meta_key', 'option', 'option_name', 'name', 'mod_name', 'setting' );

	const VALUE_FIELDS = array( 'value', 'values', 'meta_value', 'option_value', 'new_value', 'old_value', 'previous_value', 'saved_value', 'stored_value', 'current_value', 'setting_value' );

	const INVALID_KEY_MESSAGE = 'Refused: idempotency_key must be 8 to 128 characters of letters, digits, dot, underscore, colon or hyphen (a UUID works). Nothing was changed. Send a valid key, or leave the argument out.';

	public static function take_key( &$args ) {
		if ( ! is_array( $args ) || ! array_key_exists( self::ARG, $args ) ) {
			return null;
		}
		$key = $args[ self::ARG ];
		unset( $args[ self::ARG ] );
		return ( null === $key || '' === $key ) ? null : $key;
	}

	public static function valid_key( $key ): bool {
		return is_string( $key ) && 1 === preg_match( self::KEY_PATTERN, $key );
	}

	public static function slot( string $tool, string $key ): string {
		$scope = (string) wp_json_encode( array( 'v1', (int) get_current_user_id(), Request_Context::key(), $tool, $key ) );
		return self::OPTION_PREFIX . hash( 'sha256', $scope );
	}

	public static function args_hash( string $tool, array $args ): string {
		return Approvals::args_hash( $tool, $args );
	}

	public static function find( string $slot ): ?array {
		$entry = get_option( $slot, null );
		if ( null === $entry || false === $entry ) {
			return null;
		}
		if ( ! is_array( $entry ) || ! isset( $entry['state'], $entry['expires_at'] ) || (int) $entry['expires_at'] <= time() ) {
			delete_option( $slot );
			return null;
		}
		return $entry;
	}

	public static function claim( string $slot, string $tool, string $args_hash ): bool {
		$now = time();
		return (bool) add_option(
			$slot,
			array(
				'state'      => 'running',
				'tool'       => $tool,
				'args_hash'  => $args_hash,
				'created_at' => $now,
				'expires_at' => $now + self::TTL,
			),
			'',
			'no'
		);
	}

	public static function release( string $slot ): void {
		delete_option( $slot );
	}

	public static function complete( string $slot, string $tool, string $args_hash, $result ): void {
		$now = time();
		update_option(
			$slot,
			array(
				'state'        => 'done',
				'tool'         => $tool,
				'args_hash'    => $args_hash,
				'created_at'   => $now,
				'completed_at' => $now,
				'expires_at'   => $now + self::TTL,
				'result'       => self::storable( $result ),
			),
			false
		);
	}

	public static function repeat( string $tool, string $key, string $args_hash, ?array $entry ): array {
		if ( null !== $entry && ! hash_equals( (string) ( $entry['args_hash'] ?? '' ), $args_hash ) ) {
			throw new Refused_Exception(
				sprintf(
					'Refused: idempotency_key "%1$s" was already used for a "%2$s" call with different arguments. Nothing was changed. A key names one call: use a new key for a different change, or send the original arguments to get the original result.',
					$key,
					$tool
				)
			);
		}

		if ( null === $entry || 'done' !== ( $entry['state'] ?? '' ) ) {
			$age = null === $entry ? 0 : max( 0, time() - (int) ( $entry['created_at'] ?? 0 ) );
			if ( $age > self::RUNNING_GRACE ) {
				throw new Refused_Exception(
					sprintf(
						'Unknown outcome: an earlier "%1$s" call with this idempotency_key started %2$d seconds ago and never reported a result, so it may or may not have changed the site. Nothing was run now. Check the current state of what it was changing before you try again, and use a new key if you do.',
						$tool,
						$age
					)
				);
			}
			$message = sprintf( 'In progress: an earlier "%s" call with this idempotency_key is still running. Nothing new was started. Wait a few seconds, then repeat this exact call with the same idempotency_key to get its result.', $tool );
			throw new Pending_Exception(
				$message,
				array(
					'status'              => 'in_progress',
					'tool'                => $tool,
					'idempotency_key'     => $key,
					'started_seconds_ago' => $age,
					'message'             => sprintf( 'Pending, not failed: an earlier "%s" call with this idempotency_key is still running. Nothing new was started.', $tool ),
					'next_steps'          => array(
						'Wait a few seconds.',
						'Then repeat this exact call, with the same arguments and the same idempotency_key, to get its result.',
						'Do not switch to a new key: that would make the change a second time.',
					),
				)
			);
		}

		

		if ( ! current_user_can( Tool_Capabilities::for_tool( $tool ) ) ) {
			throw new Refused_Exception(
				sprintf(
					'Blocked: "%s" already ran with this idempotency_key, but this account is no longer allowed to use the tool, so its result is not shown. Nothing was changed again. Do not repeat it with a new key: that would make the change a second time.',
					$tool
				)
			);
		}

		return self::replay( $entry, $key );
	}

	public static function replay( array $entry, string $key ): array {
		$marker = array(
			'replayed'           => true,
			'key'                => $key,
			'first_completed_at' => gmdate( 'Y-m-d\TH:i:s\Z', (int) ( $entry['completed_at'] ?? 0 ) ),
			'note'               => 'This call already ran with this idempotency_key. This is its stored result; nothing was changed again. Values marked "' . self::NOT_KEPT . '" were not stored. Use a new key for a new change.',
		);

		if ( ! isset( $entry['result'] ) || ! is_string( $entry['result'] ) ) {
			$marker['result_kept'] = false;
			$marker['note']        = 'This call already ran with this idempotency_key; nothing was changed again. Its result was too large to keep, so read what it changed to see the current state. Use a new key for a new change.';
			return array(
				'status'      => 'already_applied',
				'idempotency' => $marker,
			);
		}

		$stored = json_decode( $entry['result'], true );
		if ( ! is_array( $stored ) || ( array() !== $stored && array_keys( $stored ) === range( 0, count( $stored ) - 1 ) ) ) {
			
			return array(
				'result'      => $stored,
				'idempotency' => $marker,
			);
		}
		$stored['idempotency'] = $marker;
		return $stored;
	}

	public static function storable( $result ): ?string {
		$encoded = wp_json_encode( $result );
		if ( ! is_string( $encoded ) ) {
			return null;
		}
		$json = wp_json_encode( self::scrub( json_decode( $encoded, true ) ) );
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_RESULT_BYTES ) {
			return null;
		}
		return $json;
	}

	public static function scrub( $value, string $parent = '' ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$secret_name = false;
		foreach ( self::NAME_FIELDS as $field ) {
			if ( isset( $value[ $field ] ) && is_string( $value[ $field ] ) && self::secret_name( $value[ $field ] ) ) {
				$secret_name = true;
				break;
			}
		}
		foreach ( $value as $k => $v ) {
			if ( is_int( $k ) ) {
				$value[ $k ] = self::scrub( $v, $parent );
				continue;
			}
			$lower = strtolower( $k );
			if ( ( 'token' !== $lower || 'undo' !== $parent ) && self::secret_name( $lower ) ) {
				$value[ $k ] = self::NOT_KEPT;
			} elseif ( $secret_name && in_array( $lower, self::VALUE_FIELDS, true ) ) {
				$value[ $k ] = self::NOT_KEPT;
			} else {
				$value[ $k ] = self::scrub( $v, $lower );
			}
		}
		return $value;
	}

	private static function secret_name( string $name ): bool {
		$lower = strtolower( $name );
		foreach ( self::SECRET_FRAGMENTS as $fragment ) {
			if ( false !== strpos( $lower, $fragment ) ) {
				return true;
			}
		}
		return Options_Support::is_sensitive_key( $lower );
	}

	public static function cleanup_expired(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Listing this plugin's own option rows by prefix; each is then read and deleted through the options API.
		$names   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 5000",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);
		$deleted = 0;
		foreach ( (array) $names as $name ) {
			$entry = get_option( (string) $name, null );
			if ( ! is_array( $entry ) || (int) ( $entry['expires_at'] ?? 0 ) <= time() ) {
				delete_option( (string) $name );
				++$deleted;
			}
		}
		return $deleted;
	}
}
