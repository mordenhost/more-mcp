<?php

namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Loopback {

	private static function host( array $parsed ): string {
		return strtolower( trim( (string) ( $parsed['host'] ?? '' ), '[]' ) );
	}

	public static function is_loopback_host( string $host ): bool {
		$host = strtolower( trim( $host, '[]' ) );
		if ( 'localhost' === $host || '::1' === $host ) {
			return true;
		}
		return 1 === preg_match( '/^127(?:\.(?:25[0-5]|2[0-4]\d|1?\d?\d)){3}$/', $host );
	}

	public static function is_loopback( string $uri ): bool {
		$parsed = wp_parse_url( $uri );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
			return false;
		}
		return self::is_loopback_host( self::host( $parsed ) );
	}

	public static function is_acceptable( string $uri ): bool {
		if ( self::is_loopback( $uri ) ) {
			return true;
		}
		$parsed = wp_parse_url( $uri );
		return is_array( $parsed ) && ! empty( $parsed['host'] ) && isset( $parsed['scheme'] ) && 'https' === strtolower( $parsed['scheme'] )
			&& ! isset( $parsed['user'] ) && ! isset( $parsed['pass'] );
	}

	public static function matches( string $requested, array $registered ): bool {
		if ( in_array( $requested, $registered, true ) ) {
			return true;
		}
		if ( ! self::is_loopback( $requested ) ) {
			return false;
		}
		$want = wp_parse_url( $requested );
		foreach ( $registered as $candidate ) {
			if ( ! is_string( $candidate ) || ! self::is_loopback( $candidate ) ) {
				continue;
			}
			$have = wp_parse_url( $candidate );
			if ( strtolower( (string) $want['scheme'] ) === strtolower( (string) $have['scheme'] )
				&& self::host( $want ) === self::host( $have )
				&& ( $want['path'] ?? '/' ) === ( $have['path'] ?? '/' )
				&& ( $want['query'] ?? '' ) === ( $have['query'] ?? '' )
			) {
				return true;
			}
		}
		return false;
	}
}
