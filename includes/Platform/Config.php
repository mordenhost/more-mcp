<?php

namespace More_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Config {

	const PREFIX = 'MORE_MCP_';

	public static function name( string $key ): string {
		return self::PREFIX . strtoupper( preg_replace( '/[^A-Za-z0-9_]/', '_', $key ) );
	}

	public static function locked( string $key ): bool {
		return null !== self::raw( $key );
	}

	public static function raw( string $key ) {
		$name = self::name( $key );
		if ( defined( $name ) ) {
			$value = constant( $name );
			if ( is_string( $value ) ) {
				return '' === trim( $value ) ? null : $value;
			}
			return null === $value ? null : $value;
		}
		$env = getenv( $name );
		if ( false !== $env && '' !== trim( (string) $env ) ) {
			return (string) $env;
		}
		return null;
	}

	public static function get( string $key, $default = null ) {
		$value = self::raw( $key );
		return null === $value ? $default : $value;
	}

	public static function bool( string $key, bool $default = false ): bool {
		$value = self::raw( $key );
		if ( null === $value ) {
			return $default;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	public static function int( string $key, int $default = 0 ): int {
		$value = self::raw( $key );
		return null === $value || ! is_numeric( $value ) ? $default : (int) $value;
	}

	public static function string( string $key, string $default = '' ): string {
		$value = self::raw( $key );
		return null === $value || is_array( $value ) ? $default : (string) $value;
	}

	public static function list( string $key ): array {
		$value = self::raw( $key );
		if ( null === $value ) {
			return array();
		}
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$items = array_map( static function ( $item ) {
			return trim( (string) $item );
		}, (array) $items );
		return array_values( array_filter( $items, static function ( $item ) {
			return '' !== $item;
		} ) );
	}
}
