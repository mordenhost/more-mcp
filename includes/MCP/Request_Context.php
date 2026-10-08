<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Request_Context {

	private static $data = array();

	public static function set( array $data ): void {
		self::$data = array_merge( self::$data, $data );
	}

	public static function reset(): void {
		self::$data = array();
	}

	public static function get( string $key, $default = '' ) {
		return self::$data[ $key ] ?? $default;
	}

	public static function credential(): string {
		return (string) ( self::$data['label'] ?? '' );
	}

	public static function key(): string {
		return (string) ( self::$data['key'] ?? '' );
	}

	public static function ip(): string {
		if ( isset( self::$data['ip'] ) && '' !== self::$data['ip'] ) {
			return (string) self::$data['ip'];
		}
		return \More_MCP\Access\Policy::client_ip();
	}
}
