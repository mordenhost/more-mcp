<?php

namespace More_MCP\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Safety {

	const OPTION = 'more_mcp_safety';

	const ALWAYS_ALLOWED = array( 'more_mcp_connection_health' );

	const PRIVILEGED_TOOLS = array(
		'wp_privileged_search_replace_run',
		'wp_privileged_files_read_query',
		'wp_rest_routes',
		'wp_rest_read',
		'wp_rest_write',
		'wp_rest_delete',
	);

	const DRAFT_EXEMPT_TYPES = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'customize_changeset',
		'custom_css',
		'oembed_cache',
		'user_request',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_block',
		'wp_font_family',
		'wp_font_face',
	);

	private static $active = false;

	private static $notices = array();

	

	
	private static function settings(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	private static function bool_setting( string $key, string $constant ): bool {
		if ( defined( $constant ) ) {
			return (bool) constant( $constant );
		}
		$s = self::settings();
		return ! empty( $s[ $key ] );
	}

	public static function locked( string $setting ): bool {
		$map = array(
			'paused'           => 'MORE_MCP_PAUSED',
			'force_draft'      => 'MORE_MCP_FORCE_DRAFT',
			'disabled_tools'   => 'MORE_MCP_DISABLED_TOOLS',
			'whitelist'        => 'MORE_MCP_TOOL_WHITELIST',
			'require_approval' => 'MORE_MCP_REQUIRE_APPROVAL',
			'allow_privileged' => 'MORE_MCP_ALLOW_PRIVILEGED',
		);
		if ( isset( $map[ $setting ] ) && defined( $map[ $setting ] ) ) {
			return true;
		}

		return \More_MCP\Platform\Locked_Settings::is_locked( self::OPTION, $setting );
	}

	public static function paused(): bool {
		return \More_MCP\Platform\Multisite::network_paused() || self::bool_setting( 'paused', 'MORE_MCP_PAUSED' );
	}

	public static function force_draft(): bool {
		return self::bool_setting( 'force_draft', 'MORE_MCP_FORCE_DRAFT' );
	}

	public static function require_approval(): bool {
		return self::bool_setting( 'require_approval', 'MORE_MCP_REQUIRE_APPROVAL' );
	}

	public static function privileged_enabled(): bool {
		return self::bool_setting( 'allow_privileged', 'MORE_MCP_ALLOW_PRIVILEGED' );
	}

	public static function disable_destructive(): bool {
		$s = self::settings();
		return ! empty( $s['disable_destructive'] );
	}

	public static function approval_mode(): string {
		$s = self::settings();
		return ( isset( $s['approval_mode'] ) && 'chat' === $s['approval_mode'] ) ? 'chat' : 'admin';
	}

	private static function to_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function disabled_tools(): array {
		if ( defined( 'MORE_MCP_DISABLED_TOOLS' ) ) {
			return self::to_list( constant( 'MORE_MCP_DISABLED_TOOLS' ) );
		}
		$s = self::settings();
		return self::to_list( $s['disabled_tools'] ?? array() );
	}

	public static function whitelist_patterns(): array {
		if ( defined( 'MORE_MCP_TOOL_WHITELIST' ) ) {
			return self::to_list( constant( 'MORE_MCP_TOOL_WHITELIST' ) );
		}
		$s = self::settings();
		return self::to_list( $s['whitelist'] ?? array() );
	}

	

	public static function tool_disabled( string $tool ): bool {
		if ( in_array( $tool, self::ALWAYS_ALLOWED, true ) ) {
			return false;
		}
		if ( in_array( $tool, self::PRIVILEGED_TOOLS, true ) && ! self::privileged_enabled() ) {
			return true;
		}
		if ( in_array( $tool, self::disabled_tools(), true ) ) {
			return true;
		}
		return self::disable_destructive() && Destructive::high_impact( $tool );
	}

	public static function tool_allowed_by_patterns( string $tool ): bool {
		if ( in_array( $tool, self::ALWAYS_ALLOWED, true ) ) {
			return true;
		}
		$patterns = self::whitelist_patterns();
		if ( ! $patterns ) {
			return true;
		}
		foreach ( $patterns as $pattern ) {
			if ( self::glob( $pattern, $tool ) ) {
				return true;
			}
		}
		return false;
	}

	private static function glob( string $pattern, string $name ): bool {
		$regex = '';
		$len   = strlen( $pattern );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $pattern[ $i ];
			if ( '*' === $c ) {
				$regex .= '.*';
			} elseif ( '?' === $c ) {
				$regex .= '.';
			} elseif ( '[' === $c && false !== strpos( $pattern, ']', $i + 2 ) ) {
				$end    = (int) strpos( $pattern, ']', $i + 2 );
				$class  = substr( $pattern, $i + 1, $end - $i - 1 );
				$negate = '' !== $class && ( '!' === $class[0] || '^' === $class[0] );
				if ( $negate ) {
					$class = substr( $class, 1 );
				}
				$regex .= '[' . ( $negate ? '^' : '' ) . str_replace( array( '\\', ']', '/', '^' ), array( '\\\\', '\\]', '\\/', '\\^' ), $class ) . ']';
				$i      = $end;
			} else {
				$regex .= preg_quote( $c, '/' );
			}
		}
		return 1 === preg_match( '/^' . $regex . '$/i', $name );
	}

	public static function is_visible( string $tool ): bool {
		return ! self::tool_disabled( $tool ) && self::tool_allowed_by_patterns( $tool );
	}

	public static function gate( string $tool, array $args ): ?string {
		if ( in_array( $tool, self::ALWAYS_ALLOWED, true ) ) {
			return null;
		}

		if ( self::paused() ) {
			return 'Blocked: AI access to this site is paused by the site owner. Do not retry; every tool call is refused until they resume it (More MCP > Safety).';
		}
		if ( ! self::tool_allowed_by_patterns( $tool ) ) {
			return sprintf( 'Blocked: "%s" is not on this site\'s tool allowlist. Do not retry; the site owner controls the list in More MCP > Safety.', $tool );
		}
		if ( in_array( $tool, self::PRIVILEGED_TOOLS, true ) && ! self::privileged_enabled() ) {
			return sprintf( 'Blocked: "%s" is a privileged tool and privileged tools are switched off on this site. Do not retry; the site owner can enable them in More MCP > Safety.', $tool );
		}
		if ( self::tool_disabled( $tool ) ) {
			return sprintf( 'Blocked: "%s" has been switched off by the site owner. Do not retry; they can turn it back on in More MCP > Safety.', $tool );
		}

		if ( self::require_approval() && Destructive::high_impact( $tool ) && ! Destructive::is_dry_run( $tool, $args ) ) {
			if ( Approvals::consume( $tool, $args ) ) {
				return null;
			}
			$request = Approvals::request( $tool, $args );
			return Approvals::refusal_message( $tool, $request );
		}
		return null;
	}

	

	
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'coerce_status' ), 99, 4 );
	}

	public static function enter(): void {
		self::$active  = true;
		self::$notices = array();
	}

	public static function leave(): array {
		self::$active = false;
		$notices      = self::$notices;
		self::$notices = array();
		return $notices;
	}

	public static function in_tool_call(): bool {
		return self::$active;
	}

	public static function coerce_status( $data, $postarr = array(), $unsanitized = array(), $update = false ) {
		if ( ! self::$active || ! is_array( $data ) || ! self::force_draft() ) {
			return $data;
		}
		$requested = (string) ( $data['post_status'] ?? '' );
		if ( 'publish' !== $requested && 'future' !== $requested ) {
			return $data;
		}
		$type = (string) ( $data['post_type'] ?? 'post' );
		if ( in_array( $type, self::DRAFT_EXEMPT_TYPES, true ) ) {
			return $data;
		}
		$type_object = get_post_type_object( $type );
		if ( ! $type_object || ! $type_object->public ) {
			return $data;
		}

		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id > 0 && 'publish' === get_post_status( $id ) && 'publish' === $requested ) {
			return $data;
		}

		$data['post_status'] = 'draft';
		self::$notices[]     = array(
			'post_id'          => $id,
			'title'            => isset( $data['post_title'] ) ? wp_strip_all_tags( (string) $data['post_title'] ) : '',
			'requested_status' => $requested,
		);
		return $data;
	}

	

	
	public static function current(): array {
		return array(
			'paused'              => self::paused(),
			'force_draft'         => self::force_draft(),
			'require_approval'    => self::require_approval(),
			'disable_destructive' => self::disable_destructive(),
			'allow_privileged'    => self::privileged_enabled(),
			'approval_mode'       => self::approval_mode(),
			'disabled_tools'      => self::disabled_tools(),
			'whitelist'           => self::whitelist_patterns(),
		);
	}

	public static function save( array $input ): array {
		$s      = self::settings();
		$errors = array();

		foreach ( array( 'paused', 'force_draft', 'require_approval', 'disable_destructive', 'allow_privileged' ) as $key ) {
			if ( array_key_exists( $key, $input ) && ! self::locked( $key ) ) {
				$s[ $key ] = self::truthy( $input[ $key ] ) ? 1 : 0;
			}
		}
		if ( array_key_exists( 'approval_mode', $input ) && ! self::locked( 'approval_mode' ) ) {
			$s['approval_mode'] = ( 'chat' === $input['approval_mode'] ) ? 'chat' : 'admin';
		}

		if ( array_key_exists( 'disabled_tools', $input ) && ! self::locked( 'disabled_tools' ) ) {
			$names = array();
			foreach ( self::to_list( $input['disabled_tools'] ) as $name ) {
				if ( ! preg_match( '/^[a-z0-9_]{1,100}$/', $name ) ) {
					$errors[] = sprintf( '"%s" is not a tool name (letters, digits and underscores only).', $name );
					continue;
				}
				$names[] = $name;
			}
			$s['disabled_tools'] = array_slice( $names, 0, 300 );
		}

		if ( array_key_exists( 'whitelist', $input ) && ! self::locked( 'whitelist' ) ) {
			$patterns = array();
			foreach ( self::to_list( $input['whitelist'] ) as $pattern ) {
				if ( ! preg_match( '/^[a-z0-9_*?\[\]!^\-]{1,100}$/i', $pattern ) ) {
					$errors[] = sprintf( '"%s" is not a valid pattern (letters, digits, underscores and the wildcards * ? [..]).', $pattern );
					continue;
				}
				$patterns[] = $pattern;
			}
			$s['whitelist'] = array_slice( $patterns, 0, 100 );
		}

		if ( $errors ) {
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}
		update_option( self::OPTION, $s, false );
		return array(
			'ok'     => true,
			'errors' => array(),
		);
	}

	private static function truthy( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}
}
