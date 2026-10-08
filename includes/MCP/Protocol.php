<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Protocol {

	const MODERN = '2026-07-28';

	const LEGACY = '2025-11-25';

	const META_VERSION      = 'io.modelcontextprotocol/protocolVersion';
	const META_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';
	const META_SERVER_INFO  = 'io.modelcontextprotocol/serverInfo';

	const ERR_HEADER_MISMATCH      = -32020;
	const ERR_UNSUPPORTED_VERSION  = -32022;

	const CACHEABLE = array(
		'server/discover',
		'tools/list',
		'prompts/list',
		'resources/list',
		'resources/read',
		'resources/templates/list',
	);

	public static function supported_versions(): array {
		return array( self::MODERN, self::LEGACY );
	}

	public static function declared_version( $params ): ?string {
		if ( ! is_array( $params ) || ! isset( $params['_meta'] ) || ! is_array( $params['_meta'] ) ) {
			return null;
		}
		$version = $params['_meta'][ self::META_VERSION ] ?? null;
		return is_string( $version ) && '' !== $version ? $version : null;
	}

	public static function is_modern( $params ): bool {
		return self::MODERN === self::declared_version( $params );
	}

	public static function validate( string $method, $params, array $headers ): ?array {
		$declared = self::declared_version( $params );
		if ( null === $declared ) {
			return null; 
		}

		if ( ! in_array( $declared, self::supported_versions(), true ) ) {
			return array(
				'status'  => 400,
				'code'    => self::ERR_UNSUPPORTED_VERSION,
				'message' => 'Unsupported protocol version',
				'data'    => array(
					'supported' => self::supported_versions(),
					'requested' => $declared,
				),
			);
		}
		if ( self::MODERN !== $declared ) {
			return null; 
		}

		$sent = $headers['protocol_version'] ?? '';
		if ( '' === $sent ) {
			return self::mismatch( 'MCP-Protocol-Version header is required.' );
		}
		if ( $sent !== $declared ) {
			return self::mismatch( sprintf( "MCP-Protocol-Version header '%s' does not match the protocol version in the request body '%s'.", $sent, $declared ) );
		}

		$sent_method = $headers['method'] ?? '';
		if ( '' === $sent_method ) {
			return self::mismatch( 'Mcp-Method header is required.' );
		}
		if ( $sent_method !== $method ) {
			return self::mismatch( sprintf( "Mcp-Method header '%s' does not match the request method '%s'.", $sent_method, $method ) );
		}

		$name_field = self::name_field( $method );
		if ( null !== $name_field ) {
			$body_name = is_array( $params ) && isset( $params[ $name_field ] ) && is_string( $params[ $name_field ] ) ? $params[ $name_field ] : '';
			$sent_name = $headers['name'] ?? '';
			if ( '' === $sent_name ) {
				return self::mismatch( 'Mcp-Name header is required.' );
			}
			$decoded = self::decode_header_value( $sent_name );
			if ( null === $decoded ) {
				return self::mismatch( 'Mcp-Name header is not valid.' );
			}
			if ( $decoded !== $body_name ) {
				return self::mismatch( sprintf( "Mcp-Name header '%s' does not match the request body '%s'.", $decoded, $body_name ) );
			}
		}

		return null;
	}

	private static function name_field( string $method ): ?string {
		switch ( $method ) {
			case 'tools/call':
			case 'prompts/get':
				return 'name';
			case 'resources/read':
				return 'uri';
		}
		return null;
	}

	public static function decode_header_value( string $value ): ?string {
		if ( 0 === strpos( $value, '=?base64?' ) && '?=' === substr( $value, -2 ) && strlen( $value ) >= 11 ) {
			$decoded = base64_decode( substr( $value, 9, -2 ), true );
			if ( false === $decoded || ! mb_check_encoding( $decoded, 'UTF-8' ) ) {
				return null;
			}
			return $decoded;
		}
		return $value;
	}

	private static function mismatch( string $message ): array {
		return array(
			'status'  => 400,
			'code'    => self::ERR_HEADER_MISMATCH,
			'message' => 'Header mismatch: ' . $message,
		);
	}

	public static function decorate( string $method, array $message, array $server_info, int $ttl_ms ): array {
		if ( ! isset( $message['result'] ) ) {
			return $message;
		}
		$result = $message['result'];
		if ( $result instanceof \stdClass ) {
			$result = (array) $result;
		}
		if ( ! is_array( $result ) ) {
			return $message;
		}

		$result = array( 'resultType' => 'complete' ) + $result;

		if ( in_array( $method, self::CACHEABLE, true ) ) {

			$result['ttlMs']      = $ttl_ms;
			$result['cacheScope'] = 'private';
		}

		$meta                              = isset( $result['_meta'] ) && is_array( $result['_meta'] ) ? $result['_meta'] : array();
		$meta[ self::META_SERVER_INFO ]    = $server_info;
		$result['_meta']                   = $meta;
		$message['result']                 = $result;
		return $message;
	}

	public static function discover_result( array $capabilities ): array {
		return array(
			'supportedVersions' => self::supported_versions(),
			'capabilities'      => $capabilities,
		);
	}
}
