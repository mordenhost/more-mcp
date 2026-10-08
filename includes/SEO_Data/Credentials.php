<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Credentials {

	private const OPTION_KEY = 'more_mcp_settings';
	private const SUBKEY     = 'seo_data';

	const ENC_PREFIX = 'mmcp1:';

	const SECRET_FIELDS = array( 'api_key', 'password', 'private_key', 'access_token', 'client_secret', 'token' );

	

	
	public static function can_encrypt(): bool {
		return function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}

	private static function key(): string {
		$material = defined( 'MORE_MCP_ENCRYPTION_KEY' ) && '' !== (string) constant( 'MORE_MCP_ENCRYPTION_KEY' )
			? (string) constant( 'MORE_MCP_ENCRYPTION_KEY' )
			: wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		return hash_hkdf( 'sha256', $material, 32, 'more-mcp-credentials-v1' );
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain || 0 === strpos( $plain, self::ENC_PREFIX ) || ! self::can_encrypt() ) {
			return $plain;
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'more-mcp', 16 );
		if ( false === $cipher ) {
			return $plain;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext packed into an option value, not obfuscation.
		return self::ENC_PREFIX . base64_encode( $iv . $tag . $cipher );
	}

	public static function decrypt( string $stored ): string {
		if ( 0 !== strpos( $stored, self::ENC_PREFIX ) ) {
			return $stored;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads back the ciphertext written by encrypt().
		$raw = base64_decode( substr( $stored, strlen( self::ENC_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < 28 || ! self::can_encrypt() ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), 'more-mcp' );
		return false === $plain ? '' : $plain;
	}

	private static function is_secret_field( string $slug, string $field ): bool {
		if ( in_array( $field, self::SECRET_FIELDS, true ) ) {
			return true;
		}
		$provider = Providers::get( $slug );
		return $provider && isset( $provider['fields'][ $field ]['type'] ) && 'password' === $provider['fields'][ $field ]['type'];
	}

	public static function protect( string $slug, array $row ): array {
		foreach ( $row as $field => $value ) {
			if ( is_string( $value ) && '' !== $value && self::is_secret_field( $slug, (string) $field ) ) {
				$row[ $field ] = self::encrypt( $value );
			}
		}
		return $row;
	}

	private static function reveal( string $slug, array $row ): array {
		foreach ( $row as $field => $value ) {
			if ( is_string( $value ) && 0 === strpos( $value, self::ENC_PREFIX ) && self::is_secret_field( $slug, (string) $field ) ) {
				$row[ $field ] = self::decrypt( $value );
			}
		}
		return $row;
	}

	public static function migrate_plaintext(): int {
		if ( ! self::can_encrypt() ) {
			return 0;
		}
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) || empty( $settings[ self::SUBKEY ] ) || ! is_array( $settings[ self::SUBKEY ] ) ) {
			return 0;
		}
		$changed = 0;
		foreach ( $settings[ self::SUBKEY ] as $slug => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$protected = self::protect( (string) $slug, $row );
			if ( $protected !== $row ) {
				$changed                           += count( array_diff_assoc( $protected, $row ) );
				$settings[ self::SUBKEY ][ $slug ] = $protected;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION_KEY, $settings );
		}
		return $changed;
	}

	public static function config( string $slug ): array {
		$settings = get_option( self::OPTION_KEY, array() );
		$seo_data = ( is_array( $settings ) && isset( $settings[ self::SUBKEY ] ) && is_array( $settings[ self::SUBKEY ] ) )
			? $settings[ self::SUBKEY ]
			: array();

		return ( isset( $seo_data[ $slug ] ) && is_array( $seo_data[ $slug ] ) ) ? self::reveal( $slug, $seo_data[ $slug ] ) : array();
	}

	public static function is_enabled( string $slug ): bool {
		$provider = Providers::get( $slug );
		if ( ! $provider ) {
			return false;
		}
		$config = self::config( $slug );

		if ( array_key_exists( 'enabled', $config ) ) {
			return ! empty( $config['enabled'] );
		}
		return self::is_configured( $slug );
	}

	public static function is_configured( string $slug ): bool {
		$provider = Providers::get( $slug );
		if ( ! $provider ) {
			return false;
		}
		$config = self::config( $slug );

		if ( 'service_account' === $provider['status_kind'] ) {
			return ! empty( $config['client_email'] ) && ! empty( $config['private_key'] );
		}

		foreach ( $provider['fields'] as $field_id => $field ) {
			if ( ! empty( $field['required'] ) && empty( $config[ $field_id ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function status( string $slug ): string {
		$provider = Providers::get( $slug );
		if ( ! $provider ) {
			return 'not_configured';
		}

		if ( ! self::is_configured( $slug ) ) {
			return 'not_configured';
		}
		return self::is_enabled( $slug ) ? 'configured' : 'off';
	}

	public static function is_active( string $slug ): bool {
		return self::is_configured( $slug ) && self::is_enabled( $slug );
	}
}
