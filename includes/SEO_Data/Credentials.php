<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Credentials {

	private const OPTION_KEY = 'more_mcp_settings';
	private const SUBKEY     = 'seo_data';

	public static function config( string $slug ): array {
		$settings = get_option( self::OPTION_KEY, array() );
		$seo_data = ( is_array( $settings ) && isset( $settings[ self::SUBKEY ] ) && is_array( $settings[ self::SUBKEY ] ) )
			? $settings[ self::SUBKEY ]
			: array();

		return ( isset( $seo_data[ $slug ] ) && is_array( $seo_data[ $slug ] ) ) ? $seo_data[ $slug ] : array();
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
