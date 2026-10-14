<?php

namespace Mordenhost_MCP\Auth;

use Mordenhost_MCP\Access\Policy;
use Mordenhost_MCP\MCP\Log_Store;
use Mordenhost_MCP\Platform\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class App_Passwords {

	const METHOD = 'app-password';

	private static $attempted = false;

	public static function register(): void {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'log_core_refusal' ), 10, 3 );
	}

	public static function enabled(): bool {
		return Policy::app_passwords_enabled();
	}

	public static function transport_secure(): bool {
		if ( function_exists( 'is_ssl' ) && is_ssl() ) {
			return true;
		}
		return function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
	}

	public static function available(): bool {
		return function_exists( 'wp_authenticate_application_password' )
			&& function_exists( 'wp_is_application_passwords_available' )
			&& wp_is_application_passwords_available();
	}

	public static function parse_header( string $header ): ?array {
		if ( ! preg_match( '%^Basic ([a-z\d/+]+={0,2})$%i', trim( $header ), $m ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- HTTP Basic credentials are base64 by definition (RFC 7617).
		$decoded = base64_decode( $m[1], true );
		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return null;
		}
		list( $user, $pass ) = explode( ':', $decoded, 2 );
		if ( '' === $user || '' === $pass ) {
			return null;
		}
		return array( $user, $pass );
	}

	public static function authenticate( string $header ): array {
		self::$attempted = true;

		if ( ! self::enabled() ) {
			return self::fail( 'off' );
		}
		if ( ! self::transport_secure() ) {
			return self::fail( 'insecure' );
		}
		if ( ! self::available() ) {
			return self::fail( 'unavailable' );
		}
		$credentials = self::parse_header( $header );
		if ( null === $credentials ) {
			return self::fail( 'malformed' );
		}

		
		$item     = null;
		$listener = static function ( $user, $app_password ) use ( &$item ) {
			unset( $user );
			$item = is_array( $app_password ) ? $app_password : null;
		};
		add_action( 'application_password_did_authenticate', $listener, 10, 2 );
		try {
			$result = wp_authenticate_application_password( null, $credentials[0], $credentials[1] );
		} finally {
			remove_action( 'application_password_did_authenticate', $listener, 10 );
		}
		unset( $credentials );

		if ( is_wp_error( $result ) ) {
			$code = (string) $result->get_error_code();
			return self::fail( self::map_core_code( $code ), $code );
		}

		if ( ! $result instanceof \WP_User || ! $result->exists() ) {
			return self::fail( 'invalid' );
		}
		if ( ! function_exists( 'wp_is_application_passwords_available_for_user' ) || ! wp_is_application_passwords_available_for_user( $result ) ) {
			return self::fail( 'unavailable_for_user' );
		}

		$uuid = is_array( $item ) && isset( $item['uuid'] ) ? strtolower( preg_replace( '/[^a-f0-9\-]/i', '', (string) $item['uuid'] ) ) : '';
		if ( '' === $uuid ) {
			return self::fail( 'invalid' );
		}
		$name = isset( $item['name'] ) ? substr( sanitize_text_field( (string) $item['name'] ), 0, 80 ) : '';

		return array(
			'ok'        => true,
			'error'     => '',
			'core_code' => '',
			'user'      => $result,
			'uuid'      => $uuid,
			'name'      => '' !== $name ? $name : $uuid,
		);
	}

	private static function map_core_code( string $code ): string {
		switch ( $code ) {
			case 'invalid_username':
			case 'invalid_email':
			case 'incorrect_password':
				return 'invalid';
			case 'application_passwords_disabled':
				return 'unavailable';
			case 'application_passwords_disabled_for_user':
				return 'unavailable_for_user';
		}
		return 'refused';
	}

	private static function fail( string $error, string $core_code = '' ): array {
		return array(
			'ok'        => false,
			'error'     => $error,
			'core_code' => $core_code,
			'user'      => null,
			'uuid'      => '',
			'name'      => '',
		);
	}

	public static function message( string $error ): string {
		$messages = array(
			'insecure'             => 'Application Passwords are only accepted over HTTPS. Connect to this site\'s https:// address.',
			'unavailable'          => 'Application Passwords are not available on this site.',
			'unavailable_for_user' => 'Application Passwords are not available for this account.',
			'malformed'            => 'Malformed credentials. Send "Authorization: Basic " followed by base64 of "username:application-password".',
			'refused'              => 'This Application Password was refused.',
		);
		return $messages[ $error ] ?? 'Invalid username or Application Password.';
	}

	public static function failure_reason( string $error, string $core_code = '' ): string {
		$reason = 'application password refused: ' . $error;
		$code   = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $core_code ) );
		if ( '' !== $code ) {
			$reason .= ' (' . substr( $code, 0, 60 ) . ')';
		}
		return $reason;
	}

	public static function is_mcp_route( string $route ): bool {
		$namespaces = 'mordenhost-mcp|' . preg_quote( Legacy::OLD_REST_NAMESPACE, '#' );
		return (bool) preg_match( '#^/(?:' . $namespaces . ')/v1(?:/mcp)?/?$#', $route );
	}

	public static function log_core_refusal( $response, $server, $request ) {
		unset( $server );
		$status = $GLOBALS['wp_rest_application_password_status'] ?? null;
		if ( self::$attempted || ! $status instanceof \WP_Error ) {
			return $response;
		}
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! self::is_mcp_route( (string) $request->get_route() ) ) {
			return $response;
		}
		if ( ! self::enabled() ) {
			return $response;
		}
		self::$attempted = true;
		$code            = (string) $status->get_error_code();
		Log_Store::log_auth_failure( self::failure_reason( self::map_core_code( $code ), $code ), array( 'auth_method' => self::METHOD ) );
		return $response;
	}

	public static function reset_for_tests(): void {
		self::$attempted = false;
	}
}
