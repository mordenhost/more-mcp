<?php

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Guard {

	public static function require_cap( string $cap, string $operation ): void {
		if ( ! current_user_can( $cap ) ) {
			throw new \Exception(
				sprintf(
					'You do not have permission to %s. The %s capability is required.',
					esc_html( $operation ),
					esc_html( $cap )
				)
			);
		}
	}

	public static function is_confirmed( array $args, string $target ): bool {
		if ( empty( $args['confirm'] ) || true !== filter_var( $args['confirm'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}
		$echo = isset( $args['confirm_slug'] ) ? (string) $args['confirm_slug'] : '';
		if ( '' === $echo ) {
			return false;
		}

		$dir = self::slug_of( $target );
		return ( $echo === $target || $echo === $dir );
	}

	public static function slug_of( string $plugin_file ): string {
		if ( false !== strpos( $plugin_file, '/' ) ) {
			return dirname( $plugin_file );
		}
		return preg_replace( '/\.php$/', '', $plugin_file );
	}

	public static function preview( string $tool, string $target, string $summary, array $details = array() ): array {
		return array_merge(
			array(
				'confirmed'    => false,
				'written'      => false,
				'tool'         => $tool,
				'target'       => $target,
				'summary'      => $summary,
				'to_apply'     => array(
					'confirm'      => true,
					'confirm_slug' => $target,
				),
				'confirm_note' => 'Nothing was changed. Repeat this call with confirm=true and confirm_slug set to the target shown above.',
			),
			$details
		);
	}

	public static function refuse_self( string $plugin_file, string $operation ): void {
		$self = defined( 'MORE_MCP_PLUGIN_BASENAME' ) ? MORE_MCP_PLUGIN_BASENAME : '';
		if ( '' === $self ) {
			return;
		}
		if ( $plugin_file === $self || self::slug_of( $plugin_file ) === self::slug_of( $self ) ) {
			throw new \Exception(
				sprintf(
					'Refusing to %s More MCP itself. This request is being served by that plugin, so the operation would terminate the connection mid-call. Use wp-admin if that is genuinely what you want.',
					esc_html( $operation )
				)
			);
		}
	}

	public static function refuse_protected( string $plugin_file, string $operation ): void {
		$self      = defined( 'MORE_MCP_PLUGIN_BASENAME' ) ? MORE_MCP_PLUGIN_BASENAME : '';
		$protected = array_filter( array( $self ) );

		$protected = (array) apply_filters( 'more_mcp_protected_plugins', $protected, $operation );

		foreach ( $protected as $entry ) {
			$entry = (string) $entry;
			if ( '' === $entry ) {
				continue;
			}
			if ( $plugin_file === $entry || self::slug_of( $plugin_file ) === self::slug_of( $entry ) ) {
				throw new \Exception(
					sprintf(
						'%s is protected from lifecycle operations on this site (more_mcp_protected_plugins), so it cannot be %sd.',
						esc_html( $plugin_file ),
						esc_html( $operation )
					)
				);
			}
		}
	}

	public static function require_upgrader(): void {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( '\WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		
		$method = get_filesystem_method();
		if ( 'direct' !== $method ) {
			throw new \Exception(
				sprintf(
					'This site\'s filesystem requires %s credentials to modify files, which More MCP will not accept as tool arguments or store. Perform this operation in wp-admin, or configure direct filesystem access.',
					esc_html( $method )
				)
			);
		}
		if ( ! \WP_Filesystem() ) {
			throw new \Exception( 'Could not initialise the WordPress filesystem API. No changes were made.' );
		}
	}

	public static function assert_ok( $result, string $operation ): void {
		if ( is_wp_error( $result ) ) {
			throw new \Exception(
				sprintf( 'Failed to %s: %s', esc_html( $operation ), esc_html( $result->get_error_message() ) )
			);
		}
		if ( false === $result || null === $result ) {
			throw new \Exception(
				sprintf( 'Failed to %s. WordPress reported no further detail.', esc_html( $operation ) )
			);
		}
	}
}
