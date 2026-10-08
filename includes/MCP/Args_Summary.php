<?php

namespace More_MCP\MCP;

use More_MCP\Tools\Options_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Args_Summary {

	const TEXT_KEYS = array(
		'content',
		'post_content',
		'body',
		'html',
		'markup',
		'inner_html',
		'editor',
		'css',
		'code',
		'template_json',
		'message',
		'excerpt',
		'description',
		'caption',
		'text',
		'bio',
	);

	const CARRIED_VALUE_KEYS = array( 'value', 'values', 'meta_value', 'option_value', 'new_value', 'old_value', 'setting_value' );

	const PERSONAL_KEYS = array( 'email', 'user_email', 'phone', 'billing_email', 'billing_phone', 'address', 'ip', 'ip_address' );

	const SECRET_FRAGMENTS = array( 'password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'credential', 'cookie', 'private_key', 'client_secret' );

	const MAX_KEYS   = 24;
	const MAX_SCALAR = 120;
	const MAX_LIST   = 20;

	public static function redact( array $args ): array {
		$out = array();
		foreach ( array_slice( $args, 0, self::MAX_KEYS, true ) as $key => $value ) {
			$key         = (string) $key;
			$out[ $key ] = self::value( $key, $value );
		}
		return $out;
	}

	public static function line( array $args ): string {
		$parts = array();
		foreach ( self::redact( $args ) as $key => $value ) {
			$parts[] = $key . '=' . (string) wp_json_encode( $value );
			if ( count( $parts ) >= 8 ) {
				break;
			}
		}
		return implode( ', ', $parts );
	}

	private static function value( string $key, $value ) {
		$lower = strtolower( $key );
		foreach ( self::SECRET_FRAGMENTS as $fragment ) {
			if ( false !== strpos( $lower, $fragment ) ) {
				return '[redacted]';
			}
		}
		if ( Options_Support::is_sensitive_key( $key ) || in_array( $lower, self::PERSONAL_KEYS, true ) ) {
			return '[redacted]';
		}
		if ( in_array( $lower, self::CARRIED_VALUE_KEYS, true ) ) {
			if ( is_string( $value ) ) {
				return '[' . strlen( $value ) . ' chars]';
			}
			if ( is_array( $value ) ) {
				return '[' . ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ? 'list' : 'object' ) . ' of ' . count( $value ) . ']';
			}
			return is_bool( $value ) || null === $value ? $value : '[value]';
		}

		if ( is_string( $value ) ) {
			if ( in_array( $lower, self::TEXT_KEYS, true ) ) {
				return '[' . strlen( $value ) . ' chars]';
			}
			return strlen( $value ) > self::MAX_SCALAR ? substr( $value, 0, self::MAX_SCALAR ) . '…' : $value;
		}
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$scalars = true;
			foreach ( $value as $item ) {
				if ( ! is_int( $item ) && ! is_bool( $item ) ) {
					$scalars = false;
					break;
				}
			}
			if ( $is_list && $scalars && count( $value ) <= self::MAX_LIST ) {
				return $value;
			}
			return '[' . ( $is_list ? 'list' : 'object' ) . ' of ' . count( $value ) . ']';
		}
		return '[value]';
	}
}
