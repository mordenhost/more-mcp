<?php

namespace More_MCP\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Key {

	const PREFIX = 'mmcp_live_';

	const RANDOM_LENGTH = 22;

	const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	public static function generate(): string {
		$alphabet = self::ALPHABET;
		$max      = strlen( $alphabet ) - 1;
		$out      = '';

		for ( $i = 0; $i < self::RANDOM_LENGTH; $i++ ) {

			
			$out .= $alphabet[ random_int( 0, $max ) ];
		}

		return self::PREFIX . $out;
	}

	public static function is_valid_format( $key ): bool {
		if ( ! is_string( $key ) || $key === '' ) {
			return false;
		}

		$pattern = '/^' . preg_quote( self::PREFIX, '/' )
			. '[' . preg_quote( self::ALPHABET, '/' ) . ']{' . self::RANDOM_LENGTH . '}$/';

		return (bool) preg_match( $pattern, $key );
	}

	public static function is_legacy_format( $key ): bool {
		return is_string( $key ) && (bool) preg_match( '/^[0-9a-f]{32}$/', $key );
	}

	public static function mask( $key ): string {
		if ( ! is_string( $key ) || $key === '' ) {
			return '';
		}
		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return self::PREFIX . '…' . substr( $key, -4 );
	}
}
