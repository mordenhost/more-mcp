<?php
/**
 * Shared guard rails for lifecycle operations.
 *
 * Every mutation in this module runs the same sequence: check the WordPress
 * capability, resolve and validate the target, refuse if the target is
 * protected, then either return a preview or apply the change. Centralising it
 * here means a new operation cannot accidentally skip a step, and the reasoning
 * lives in one place rather than being restated in nine handlers.
 */

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Guard {

	/**
	 * Require a WordPress capability.
	 *
	 * @param string $cap       Capability to check.
	 * @param string $operation Human-readable operation name for the error.
	 * @throws \Exception
	 */
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

	/**
	 * Whether a mutation is confirmed for the given target.
	 *
	 * Two conditions, both required: `confirm` must be true, and `confirm_slug`
	 * must equal the resolved target exactly. The second is what makes this more
	 * than a formality — an agent can set a boolean reflexively, but echoing the
	 * right slug means it read the preview first.
	 *
	 * @param array  $args   Tool arguments.
	 * @param string $target Resolved target identifier (plugin file or stylesheet).
	 * @return bool
	 */
	public static function is_confirmed( array $args, string $target ): bool {
		if ( empty( $args['confirm'] ) || true !== filter_var( $args['confirm'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}
		$echo = isset( $args['confirm_slug'] ) ? (string) $args['confirm_slug'] : '';
		if ( '' === $echo ) {
			return false;
		}
		// Accept either the full plugin file path or its directory slug, since
		// the preview reports both and either is an unambiguous reference.
		$dir = self::slug_of( $target );
		return ( $echo === $target || $echo === $dir );
	}

	/**
	 * Directory slug of a plugin file path ("akismet/akismet.php" -> "akismet").
	 *
	 * Single-file plugins ("hello.php") have no directory, so the filename
	 * without extension is used.
	 *
	 * @param string $plugin_file Plugin file path or stylesheet.
	 * @return string
	 */
	public static function slug_of( string $plugin_file ): string {
		if ( false !== strpos( $plugin_file, '/' ) ) {
			return dirname( $plugin_file );
		}
		return preg_replace( '/\.php$/', '', $plugin_file );
	}

	/**
	 * Build the response returned when a mutation was requested without
	 * confirmation.
	 *
	 * This is not an error path. A caller that has not confirmed yet gets the
	 * information needed to decide, plus the exact arguments that would apply
	 * the change — so the two-step flow is discoverable from the first response
	 * rather than only from the tool description.
	 *
	 * @param string $tool    Tool name, echoed so the caller can repeat it.
	 * @param string $target  Resolved target.
	 * @param string $summary One line describing what would happen.
	 * @param array  $details Operation-specific preview fields.
	 * @return array
	 */
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

	/**
	 * Refuse operations that would target More MCP itself.
	 *
	 * The MCP request is being served by this plugin. Deactivating or deleting
	 * it mid-request would tear down the code handling the call, so the caller
	 * could not distinguish a successful self-destruct from a crash — and on
	 * deletion there would be no way back in through MCP at all.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $operation   Operation name for the message.
	 * @throws \Exception
	 */
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

	/**
	 * Allow a site owner to protect additional plugins from lifecycle changes.
	 *
	 * Mirrors the more_mcp_writable_options pattern: the default list contains
	 * only More MCP, and a site can extend it without patching the plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $operation   Operation name for the message.
	 * @throws \Exception
	 */
	public static function refuse_protected( string $plugin_file, string $operation ): void {
		$self      = defined( 'MORE_MCP_PLUGIN_BASENAME' ) ? MORE_MCP_PLUGIN_BASENAME : '';
		$protected = array_filter( array( $self ) );

		/**
		 * Filter the list of plugins protected from lifecycle operations.
		 *
		 * @param string[] $protected Plugin file paths (e.g. "akismet/akismet.php").
		 * @param string   $operation  'activate' | 'deactivate' | 'update' | 'delete'.
		 */
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

	/**
	 * Require the WordPress upgrader and filesystem APIs.
	 *
	 * These live in wp-admin includes, which are not loaded on a REST request,
	 * so they have to be pulled in explicitly. If the filesystem is not directly
	 * writable the operation is refused rather than prompting for credentials:
	 * FTP/SSH credentials must never travel through MCP arguments, and there is
	 * no interactive channel here to collect them safely.
	 *
	 * @throws \Exception
	 */
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

		// 'direct' means PHP can write to wp-content itself. Anything else needs
		// credentials we deliberately refuse to accept over MCP.
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

	/**
	 * Turn a WP_Error, or a boolean false, into an exception with usable text.
	 *
	 * The upgrader reports failures in several shapes; collapsing them here
	 * keeps handlers from each inventing their own error handling and, more
	 * importantly, prevents a failure from being returned as a success.
	 *
	 * @param mixed  $result    Upgrader / API result.
	 * @param string $operation Operation name for the message.
	 * @throws \Exception
	 */
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
