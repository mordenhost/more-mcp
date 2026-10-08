<?php

namespace More_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Locked_Settings {

	const MAP = array(
		'more_mcp_settings'       => array(
			'enabled'                  => array( 'enabled', 'bool' ),
			'access_token_ttl_seconds' => array( 'access_token_ttl', 'seconds' ),
			'log_retention_days'       => array( 'log_retention_days', 'int' ),
			'log_row_cap'              => array( 'log_row_cap', 'int' ),
			'history_retention_days'   => array( 'history_retention_days', 'int' ),
			'change_history'           => array( 'change_history', 'bool' ),
		),
		'more_mcp_access'         => array(
			'read_only'            => array( 'read_only', 'bool' ),
			'default_oauth_access' => array( 'default_oauth_access', 'enum:full|read_only' ),
			'ip_allowlist'         => array( 'ip_allowlist', 'lines' ),
			'trusted_proxies'      => array( 'trusted_proxies', 'lines' ),
		),
		'more_mcp_token_settings' => array(
			'self_service' => array( 'self_service', 'bool' ),
		),
		'more_mcp_safety'         => array(
			'paused'              => array( 'paused', 'bool' ),
			'force_draft'         => array( 'force_draft', 'bool' ),
			'require_approval'    => array( 'require_approval', 'bool' ),
			'disable_destructive' => array( 'disable_destructive', 'bool' ),
			'allow_privileged'    => array( 'allow_privileged', 'bool' ),
			'approval_mode'       => array( 'approval_mode', 'enum:admin|chat' ),
			'disabled_tools'      => array( 'disabled_tools', 'list' ),
			'whitelist'           => array( 'tool_whitelist', 'list' ),
		),
	);

	private static $raw_read = false;

	public static function register(): void {
		foreach ( array_keys( self::MAP ) as $option ) {
			add_filter( 'option_' . $option, array( __CLASS__, 'overlay' ), 5, 2 );
			add_filter( 'default_option_' . $option, array( __CLASS__, 'overlay' ), 5, 2 );
			add_filter( 'sanitize_option_' . $option, array( __CLASS__, 'restore_stored' ), 99, 2 );
		}
	}

	

	
	public static function is_locked( string $option, string $key ): bool {
		return isset( self::MAP[ $option ][ $key ] )
			&& null !== self::coerce( self::MAP[ $option ][ $key ][0], self::MAP[ $option ][ $key ][1] );
	}

	public static function locked_keys( string $option ): array {
		$out = array();
		foreach ( self::MAP[ $option ] ?? array() as $key => $def ) {
			if ( null !== self::coerce( $def[0], $def[1] ) ) {
				$out[ $key ] = Config::name( $def[0] );
			}
		}
		return $out;
	}

	public static function all_locked(): array {
		$out = array();
		foreach ( array_keys( self::MAP ) as $option ) {
			$out = array_merge( $out, self::locked_keys( $option ) );
		}
		return $out;
	}

	public static function first_locked( string $option, array $keys ): ?string {
		foreach ( $keys as $key ) {
			if ( self::is_locked( $option, (string) $key ) || self::legacy_locked( $option, (string) $key ) ) {
				return (string) $key;
			}
		}
		return null;
	}

	public static function notice( string $option, string $key ): string {
		$name = isset( self::MAP[ $option ][ $key ] ) ? Config::name( self::MAP[ $option ][ $key ][0] ) : '';
		return sprintf(
			/* translators: %s: name of a PHP constant or environment variable, e.g. MORE_MCP_READ_ONLY */
			__( 'Fixed by %s in wp-config.php or the server environment. Remove it there to change this here.', 'mordenhost-mcp-server' ),
			$name
		);
	}

	public static function lock_note( string $option, string $key ): string {
		if ( ! self::is_locked( $option, $key ) && ! self::legacy_locked( $option, $key ) ) {
			return '';
		}
		return '<p class="description mmcp-locked"><span class="dashicons dashicons-lock" aria-hidden="true"></span> '
			. esc_html( self::notice( $option, $key ) ) . '</p>';
	}

	private static function legacy_locked( string $option, string $key ): bool {
		return 'more_mcp_safety' === $option && \More_MCP\Access\Safety::locked( $key );
	}

	public static function strip( string $option, array $input ): array {
		$skipped = array();
		foreach ( array_keys( $input ) as $key ) {
			if ( self::is_locked( $option, (string) $key ) || self::legacy_locked( $option, (string) $key ) ) {
				$skipped[] = (string) $key;
				unset( $input[ $key ] );
			}
		}
		return array( $input, $skipped );
	}

	public static function refuse( string $option, array $keys ): void {
		$hit = self::first_locked( $option, $keys );
		if ( null === $hit ) {
			return;
		}
		wp_send_json_error(
			array(
				'locked'  => $hit,
				'message' => esc_html( self::notice( $option, $hit ) ),
			)
		);
	}

	

	
	public static function overlay( $value, $option = '' ) {
		if ( self::$raw_read || ! isset( self::MAP[ $option ] ) ) {
			return $value;
		}
		$pinned = self::pinned_values( $option );
		if ( ! $pinned ) {
			return $value;
		}
		$base = is_array( $value ) ? $value : array();
		return array_merge( $base, $pinned );
	}

	public static function restore_stored( $value, $option = '' ) {
		if ( ! is_array( $value ) || ! isset( self::MAP[ $option ] ) ) {
			return $value;
		}
		$locked = self::locked_keys( $option );
		if ( ! $locked ) {
			return $value;
		}
		$stored = self::stored( $option );
		foreach ( array_keys( $locked ) as $key ) {
			if ( array_key_exists( $key, $stored ) ) {
				$value[ $key ] = $stored[ $key ];
			} else {
				unset( $value[ $key ] );
			}
		}
		return $value;
	}

	

	
	private static function stored( string $option ): array {
		self::$raw_read = true;
		try {
			$value = get_option( $option, array() );
		} finally {
			self::$raw_read = false;
		}
		return is_array( $value ) ? $value : array();
	}

	private static function pinned_values( string $option ): array {
		$out = array();
		foreach ( self::MAP[ $option ] as $key => $def ) {
			$value = self::coerce( $def[0], $def[1] );
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	private static function coerce( string $config_key, string $type ) {
		if ( ! Config::locked( $config_key ) ) {
			return null;
		}
		switch ( true ) {
			case 'bool' === $type:
				return Config::bool( $config_key ) ? 1 : 0;

			case 'int' === $type:
			case 'seconds' === $type:
				$raw = Config::raw( $config_key );
				if ( ! is_numeric( $raw ) ) {
					return null;
				}
				$n = (int) $raw;
				if ( $n < 0 || ( 'seconds' === $type && $n < 1 ) ) {
					return null;
				}
				return $n;

			case 'list' === $type:
				return Config::list( $config_key );

			case 'lines' === $type:
				return implode( "\n", Config::list( $config_key ) );

			case 0 === strpos( $type, 'enum:' ):
				$allowed = explode( '|', substr( $type, 5 ) );
				$value   = strtolower( Config::string( $config_key ) );
				return in_array( $value, $allowed, true ) ? $value : null;
		}
		return null;
	}
}
