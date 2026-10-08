<?php
namespace More_MCP\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Policy {

	const OPTION_GLOBAL = 'more_mcp_access';

	const OPTION_POLICIES = 'more_mcp_access_policies';

	const API_KEY = 'api-key';

	const FULL      = 'full';
	const READ_ONLY = 'read_only';
	const CUSTOM    = 'custom';

	const LEVEL_READ  = 'read';
	const LEVEL_WRITE = 'write';

	const ALWAYS_ALLOWED = array( 'more_mcp_connection_health', 'more_mcp_approve_request' );

	const UNDO_TOOL = 'more_mcp_undo_last_operation';

	

	
	private static function global_settings(): array {
		$stored = get_option( self::OPTION_GLOBAL, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function read_only_mode(): bool {
		$s = self::global_settings();
		return ! empty( $s['read_only'] );
	}

	public static function default_oauth_access(): string {
		$s = self::global_settings();
		return ( isset( $s['default_oauth_access'] ) && self::READ_ONLY === $s['default_oauth_access'] )
			? self::READ_ONLY
			: self::FULL;
	}

	public static function ip_allowlist_raw(): string {
		$s = self::global_settings();
		return isset( $s['ip_allowlist'] ) ? (string) $s['ip_allowlist'] : '';
	}

	public static function trusted_proxies_raw(): string {
		$s = self::global_settings();
		return isset( $s['trusted_proxies'] ) ? (string) $s['trusted_proxies'] : '';
	}

	public static function save_global( array $input ): array {
		$settings = self::global_settings();
		$errors   = array();

		if ( array_key_exists( 'read_only', $input ) ) {
			$settings['read_only'] = self::truthy( $input['read_only'] ) ? 1 : 0;
		}

		if ( array_key_exists( 'default_oauth_access', $input ) ) {
			$settings['default_oauth_access'] = ( self::READ_ONLY === $input['default_oauth_access'] )
				? self::READ_ONLY
				: self::FULL;
		}

		foreach ( array( 'ip_allowlist', 'trusted_proxies' ) as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$parsed = Ip_Matcher::parse( is_string( $input[ $key ] ) ? $input[ $key ] : '' );
			if ( ! empty( $parsed['invalid'] ) ) {
				$errors[ $key ] = $parsed['invalid'];
				continue;
			}
			$settings[ $key ] = implode( "\n", $parsed['entries'] );
		}

		if ( ! empty( $errors ) ) {
			return array(
				'ok'       => false,
				'errors'   => $errors,
				'settings' => self::global_settings(),
			);
		}

		update_option( self::OPTION_GLOBAL, $settings, false );
		return array(
			'ok'       => true,
			'errors'   => array(),
			'settings' => $settings,
		);
	}

	

	
	public static function client_ip(): string {
		$server = array(
			'REMOTE_ADDR'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'HTTP_X_FORWARDED_FOR' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '',
		);

		$trusted = Ip_Matcher::parse( self::trusted_proxies_raw() )['entries'];
		$ip      = Ip_Matcher::client_ip( $server, $trusted );

		return (string) apply_filters( 'more_mcp_client_ip', $ip );
	}

	public static function ip_allowed( string $ip ): bool {
		$raw = trim( self::ip_allowlist_raw() );
		if ( '' === $raw ) {
			return true;
		}
		if ( '' === $ip ) {
			return false;
		}
		return Ip_Matcher::matches( $ip, Ip_Matcher::parse( $raw )['entries'] );
	}

	public static function ip_allowlist_enabled(): bool {
		return '' !== trim( self::ip_allowlist_raw() );
	}

	

	
	public static function key_for_grant( string $client_id, int $user_id ): string {
		return 'oauth:' . $client_id . ':' . $user_id;
	}

	private static function stored(): array {
		$stored = get_option( self::OPTION_POLICIES, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function normalize( $raw ): array {
		$raw    = is_array( $raw ) ? $raw : array();
		$access = isset( $raw['access'] ) ? (string) $raw['access'] : self::FULL;
		if ( ! in_array( $access, array( self::FULL, self::READ_ONLY, self::CUSTOM ), true ) ) {
			$access = self::FULL;
		}

		$groups = array();
		if ( self::CUSTOM === $access && isset( $raw['groups'] ) && is_array( $raw['groups'] ) ) {
			foreach ( array_keys( Classifier::groups() ) as $group ) {
				$level = isset( $raw['groups'][ $group ] ) ? (string) $raw['groups'][ $group ] : '';
				if ( self::LEVEL_READ === $level || self::LEVEL_WRITE === $level ) {
					$groups[ $group ] = $level;
				}
			}
		}

		return array(
			'access' => $access,
			'groups' => $groups,
		);
	}

	public static function for_key( ?string $key ): ?array {
		if ( null === $key || '' === $key ) {
			return null;
		}

		$all = self::stored();
		if ( isset( $all[ $key ] ) ) {
			return self::normalize( $all[ $key ] ) + array( 'explicit' => true );
		}

		return array(
			'access'   => self::default_for( $key ),
			'groups'   => array(),
			'explicit' => false,
		);
	}

	public static function default_for( string $key ): string {
		if ( 0 === strpos( $key, 'oauth:' ) ) {
			return self::default_oauth_access();
		}
		return self::FULL;
	}

	public static function save( string $key, $raw ): array {
		$policy = self::normalize( $raw );
		$all    = self::stored();

		if ( self::FULL === $policy['access'] && self::FULL === self::default_for( $key ) ) {
			unset( $all[ $key ] );
		} else {
			$all[ $key ] = $policy;
		}

		update_option( self::OPTION_POLICIES, $all, false );
		return $policy;
	}

	public static function forget( string $key ): void {
		$all = self::stored();
		if ( isset( $all[ $key ] ) ) {
			unset( $all[ $key ] );
			update_option( self::OPTION_POLICIES, $all, false );
		}
	}

	

	
	public static function decide( string $tool, ?array $policy ): ?string {
		if ( in_array( $tool, self::ALWAYS_ALLOWED, true ) ) {
			return null;
		}

		$is_read = Classifier::is_read_only( $tool );

		if ( ! $is_read && self::read_only_mode() ) {
			return sprintf(
				'Blocked: "%s" would change the site, and this site is in read-only mode. Do not retry; ask the site administrator to turn read-only mode off (More MCP > Access) if the change is intended.',
				$tool
			);
		}

		if ( null === $policy || self::FULL === $policy['access'] ) {
			return null;
		}

		if ( self::READ_ONLY === $policy['access'] ) {
			return $is_read ? null : sprintf(
				'Blocked: "%s" would change the site, and this connection is read-only. Do not retry; the site administrator can change this connection\'s access in More MCP > Sessions.',
				$tool
			);
		}

		
		if ( self::UNDO_TOOL === $tool ) {
			foreach ( array_keys( Classifier::groups() ) as $group ) {

				
				if ( 'memory' === $group ) {
					continue;
				}
				if ( self::LEVEL_WRITE !== ( $policy['groups'][ $group ] ?? '' ) ) {
					return 'Blocked: undo can reverse a change in any area of the site, so it needs write access to every area, and this connection does not have that. Do not retry.';
				}
			}
			return null;
		}

		return self::decide_group( Classifier::group_of( $tool ), ! $is_read, $policy, $tool );
	}

	public static function decide_group( string $group, bool $is_write, ?array $policy, string $subject = '' ): ?string {
		if ( null === $policy || self::FULL === $policy['access'] ) {
			return null;
		}
		if ( self::READ_ONLY === $policy['access'] ) {
			return $is_write ? 'Blocked: this connection is read-only.' : null;
		}

		$labels = Classifier::groups();
		$label  = isset( $labels[ $group ] ) ? $labels[ $group ] : $group;
		$level  = $policy['groups'][ $group ] ?? '';

		if ( self::LEVEL_WRITE === $level || ( self::LEVEL_READ === $level && ! $is_write ) ) {
			return null;
		}

		$what = '' !== $subject ? sprintf( '"%s"', $subject ) : 'this request';
		if ( self::LEVEL_READ === $level ) {
			return sprintf(
				'Blocked: %s would change the site, and this connection can only read "%s". Do not retry; the site administrator can change this connection\'s access in More MCP > Sessions.',
				$what,
				$label
			);
		}
		return sprintf(
			'Blocked: this connection has no access to "%s", which %s needs. Do not retry; the site administrator can change this connection\'s access in More MCP > Sessions.',
			$label,
			$what
		);
	}

	public static function decide_rest( string $group, string $http_method, ?array $policy ): ?string {
		$is_write = ! in_array( strtoupper( $http_method ), array( 'GET', 'HEAD', 'OPTIONS' ), true );

		if ( $is_write && self::read_only_mode() ) {
			return 'Blocked: this site is in read-only mode.';
		}
		return self::decide_group( $group, $is_write, $policy );
	}

	public static function is_visible( string $tool, ?array $policy ): bool {
		return null === self::decide( $tool, $policy );
	}

	public static function describe( ?array $policy ): string {
		if ( null === $policy ) {
			return self::FULL;
		}
		return $policy['access'];
	}

	private static function truthy( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value;
	}
}
