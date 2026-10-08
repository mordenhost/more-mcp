<?php

namespace More_MCP\Auth;

use More_MCP\Platform\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hasher {

	const MIN_KEY_LENGTH = 16;

	public static function hmac_enabled(): bool {
		return '' !== self::current_key();
	}

	public static function digest( string $raw ): string {
		return self::digest_with( $raw, self::current_key() );
	}

	public static function candidates( string $raw ): array {
		$keys = array_merge( array( self::current_key() ), self::previous_keys() );
		$out  = array();
		foreach ( $keys as $key ) {
			if ( '' !== $key ) {
				$out[] = self::digest_with( $raw, $key );
			}
		}
		$out[] = hash( 'sha256', $raw );
		return array_values( array_unique( $out ) );
	}

	public static function matches( string $stored, string $raw ): bool {
		foreach ( self::candidates( $raw ) as $candidate ) {
			if ( hash_equals( $stored, $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_current( string $stored, string $raw ): bool {
		return hash_equals( self::digest( $raw ), $stored );
	}

	private static function digest_with( string $raw, string $key ): string {
		return '' === $key ? hash( 'sha256', $raw ) : hash_hmac( 'sha256', $raw, $key );
	}

	private static function current_key(): string {
		$key = Config::string( 'hmac_key' );
		return strlen( $key ) >= self::MIN_KEY_LENGTH ? $key : '';
	}

	private static function previous_keys(): array {
		return array_values( array_filter( Config::list( 'hmac_key_previous' ), static function ( $key ) {
			return strlen( $key ) >= self::MIN_KEY_LENGTH;
		} ) );
	}
}
