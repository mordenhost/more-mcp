<?php
/**
 * Plugin and theme lifecycle operations.
 *
 * These tools install, update, activate, deactivate, and delete code that then
 * runs on the site. That is a different risk class from every other tool in
 * this plugin: a bad content write corrupts one field and a revision can undo
 * it, while `wp_delete_plugin` on the wrong slug takes a site down and nothing
 * here can put it back.
 *
 * So the gates are deliberately layered, and each one exists for a reason a
 * later maintainer should not have to guess at:
 *
 * 1. MASTER TOGGLE, default off. While `allow_plugin_management` is unset the
 *    tools are not merged into tools/list at all — an agent cannot call what it
 *    cannot see. Installing this plugin does not, by itself, expose the surface.
 *
 * 2. WORDPRESS CAPABILITY, checked in the handler. Route-level checks are not
 *    enough here (see the plugin's `permission_callback => '__return_true'`
 *    convention), so every operation re-checks its own capability:
 *    install_plugins, update_plugins, activate_plugins, delete_plugins,
 *    switch_themes, install_themes, update_themes, delete_themes.
 *
 * 3. CONFIRMATION, two-part. `confirm: true` alone is too easy for an agent to
 *    set reflexively, so a mutation also has to echo back the exact slug it
 *    intends to touch in `confirm_slug`. Getting both right requires having
 *    read the preview, which is the point. A call without confirmation is not
 *    an error — it returns the preview, so the natural first call is also the
 *    dry run.
 *
 * 4. SELF-PROTECTION. More MCP cannot deactivate or delete itself. The request
 *    is being served BY the plugin; deactivating mid-request would cut the
 *    connection and leave the caller unable to tell success from a crash.
 *
 * 5. INSTALL IS SLUG-ONLY. `wp_install_plugin` takes a wp.org slug and nothing
 *    else. Accepting a package URL would turn this into "download and execute
 *    arbitrary code", which is not a capability worth adding for convenience.
 *    Filesystem credentials are never accepted as arguments and never logged.
 */

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	/**
	 * Settings key for the master toggle.
	 */
	const TOGGLE = 'allow_plugin_management';

	/**
	 * Whether the site owner has opted into these tools.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'more_mcp_settings', array() );
		return ! empty( $settings[ self::TOGGLE ] );
	}

	/**
	 * Tool definitions. Returns an empty array while the toggle is off, so the
	 * tools never appear in tools/list on a site that has not opted in.
	 *
	 * @return array
	 */
	public static function get_tools(): array {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$confirm_props = array(
			'confirm'      => array(
				'type'        => 'boolean',
				'description' => 'Must be true to perform the operation. Omit it (or send false) to receive a preview of what would change instead — that preview is the intended first call.',
			),
			'confirm_slug' => array(
				'type'        => 'string',
				'description' => 'Repeat the exact slug being operated on. Both this and confirm=true are required before anything is written. This is a deliberate second step: it cannot be satisfied without having read the preview.',
			),
		);

		return array(
			// ---------- Read ----------
			array(
				'name'        => 'wp_get_plugin_updates',
				'description' => 'List installed plugins that have an update available, with current and new version, plus whether each is active. Read-only. Use before wp_update_plugin to see what is pending.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wp_get_themes_status',
				'description' => 'List installed themes with version, active flag, parent theme, and whether an update is available. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),

			// ---------- Plugins ----------
			array(
				'name'        => 'wp_activate_plugin',
				'description' => 'Activate an installed plugin. Requires the activate_plugins capability. Call without confirm to preview; call with confirm=true and confirm_slug to apply. Returns the post-operation active state read back from WordPress. A plugin that fatals on activation is reported as an error and left inactive.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file path as returned by wp_get_plugins, e.g. "akismet/akismet.php". A bare directory slug is also accepted when it resolves unambiguously.' ) ),
						$confirm_props
					),
					'required'   => array( 'plugin' ),
				),
			),
			array(
				'name'        => 'wp_deactivate_plugin',
				'description' => 'Deactivate an active plugin. Requires activate_plugins. More MCP itself cannot be deactivated through this tool — the request is served by this plugin, so deactivating mid-request would drop the connection. Call without confirm to preview.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file path, e.g. "akismet/akismet.php".' ) ),
						$confirm_props
					),
					'required'   => array( 'plugin' ),
				),
			),
			array(
				'name'        => 'wp_update_plugin',
				'description' => 'Update one installed plugin to the latest version from its update source. Requires update_plugins. Call without confirm to preview the version change. Returns the version actually installed, read back after the upgrade — a reported success with an unchanged version means the upgrader silently no-opped.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file path, e.g. "akismet/akismet.php".' ) ),
						$confirm_props
					),
					'required'   => array( 'plugin' ),
				),
			),
			array(
				'name'        => 'wp_install_plugin',
				'description' => 'Install a plugin from the WordPress.org repository by slug, then optionally activate it. Requires install_plugins. ONLY wp.org slugs are accepted — package URLs are rejected, because accepting one would make this a download-and-execute-arbitrary-code tool. Call without confirm to preview what would be installed (name, version, author, active install count) so the target can be verified before anything is written.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array(
							'slug'     => array( 'type' => 'string', 'description' => 'WordPress.org plugin slug, e.g. "wp-super-cache". Not a URL, not a file path.' ),
							'activate' => array( 'type' => 'boolean', 'description' => 'Activate immediately after a successful install. Default false — install and activate are separate decisions.' ),
						),
						$confirm_props
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'wp_delete_plugin',
				'description' => 'Permanently delete an installed plugin\'s files. Requires delete_plugins. The plugin must be inactive first — this tool will not deactivate on your behalf, because deletion is irreversible and deactivation is the natural checkpoint. More MCP itself cannot be deleted. Call without confirm to preview.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file path, e.g. "akismet/akismet.php".' ) ),
						$confirm_props
					),
					'required'   => array( 'plugin' ),
				),
			),

			// ---------- Themes ----------
			array(
				'name'        => 'wp_activate_theme',
				'description' => 'Switch the active theme. Requires switch_themes. This changes what every visitor sees immediately and can break layouts built for the previous theme, so the preview reports both the outgoing and incoming theme. Call without confirm to preview.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'stylesheet' => array( 'type' => 'string', 'description' => 'Theme directory name (stylesheet), e.g. "twentytwentyfive".' ) ),
						array(
							'confirm'      => $confirm_props['confirm'],
							'confirm_slug' => array( 'type' => 'string', 'description' => 'Repeat the exact stylesheet name. Required alongside confirm=true.' ),
						)
					),
					'required'   => array( 'stylesheet' ),
				),
			),
			array(
				'name'        => 'wp_update_theme',
				'description' => 'Update one installed theme to the latest version. Requires update_themes. Call without confirm to preview the version change.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'stylesheet' => array( 'type' => 'string', 'description' => 'Theme directory name.' ) ),
						array(
							'confirm'      => $confirm_props['confirm'],
							'confirm_slug' => array( 'type' => 'string', 'description' => 'Repeat the exact stylesheet name.' ),
						)
					),
					'required'   => array( 'stylesheet' ),
				),
			),
			array(
				'name'        => 'wp_delete_theme',
				'description' => 'Permanently delete an installed theme\'s files. Requires delete_themes. The active theme and any parent of the active theme cannot be deleted. Call without confirm to preview.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'stylesheet' => array( 'type' => 'string', 'description' => 'Theme directory name.' ) ),
						array(
							'confirm'      => $confirm_props['confirm'],
							'confirm_slug' => array( 'type' => 'string', 'description' => 'Repeat the exact stylesheet name.' ),
						)
					),
					'required'   => array( 'stylesheet' ),
				),
			),
		);
	}

	/**
	 * Dispatch a lifecycle tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 * @return mixed
	 * @throws \Exception
	 */
	public static function execute_tool( string $name, array $args ) {
		// Re-check the toggle on execute, not only on list. A tools/list result
		// can be cached by a client, so an agent may hold a tool name after the
		// admin has switched the feature off.
		if ( ! self::is_enabled() ) {
			throw new \Exception( 'Plugin and theme management is disabled. Enable "Allow AI to manage plugins and themes" under More MCP > Settings > Permissions.' );
		}

		switch ( $name ) {
			case 'wp_get_plugin_updates':
				return Plugins::list_updates();
			case 'wp_get_themes_status':
				return Themes::list_status();

			case 'wp_activate_plugin':
				return Plugins::activate( $args );
			case 'wp_deactivate_plugin':
				return Plugins::deactivate( $args );
			case 'wp_update_plugin':
				return Plugins::update( $args );
			case 'wp_install_plugin':
				return Plugins::install( $args );
			case 'wp_delete_plugin':
				return Plugins::delete( $args );

			case 'wp_activate_theme':
				return Themes::activate( $args );
			case 'wp_update_theme':
				return Themes::update( $args );
			case 'wp_delete_theme':
				return Themes::delete( $args );
		}

		throw new \Exception( 'Unknown lifecycle tool: ' . esc_html( $name ) );
	}

	/**
	 * Tool names this module owns. Used by the dispatcher to route without
	 * hardcoding a prefix, since these tools share the generic `wp_` prefix
	 * with the core content tools.
	 *
	 * Deliberately a static list rather than derived from get_tools(): get_tools()
	 * returns nothing while the toggle is off, and the dispatcher still needs to
	 * recognise these names in order to produce the "feature disabled" message
	 * rather than a confusing "unknown tool".
	 *
	 * @return string[]
	 */
	public static function tool_names(): array {
		return array(
			'wp_get_plugin_updates',
			'wp_get_themes_status',
			'wp_activate_plugin',
			'wp_deactivate_plugin',
			'wp_update_plugin',
			'wp_install_plugin',
			'wp_delete_plugin',
			'wp_activate_theme',
			'wp_update_theme',
			'wp_delete_theme',
		);
	}
}
