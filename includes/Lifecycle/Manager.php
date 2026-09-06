<?php

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	const TOGGLE = 'allow_plugin_management';

	public static function is_enabled(): bool {
		$settings = get_option( 'more_mcp_settings', array() );
		return ! empty( $settings[ self::TOGGLE ] );
	}

	public static function get_tools(): array {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$confirm_props = array(
			'confirm'      => array(
				'type'        => 'boolean',
				'description' => 'Must be true to perform the operation. Omit it (or send false) to receive a preview of what would change instead. That preview is the intended first call.',
			),
			'confirm_slug' => array(
				'type'        => 'string',
				'description' => 'Repeat the exact slug being operated on. Both this and confirm=true are required before anything is written. This is a deliberate second step: it cannot be satisfied without having read the preview.',
			),
		);

		return array(
			
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
				'description' => 'Deactivate an active plugin. Requires activate_plugins. More MCP itself cannot be deactivated through this tool: the request is served by this plugin, so deactivating mid-request would drop the connection. Call without confirm to preview.',
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
				'description' => 'Update one installed plugin to the latest version from its update source. Requires update_plugins. Call without confirm to preview the version change. Returns the version actually installed, read back after the upgrade: a reported success with an unchanged version means the upgrader silently no-opped.',
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
				'description' => 'Install a plugin from the WordPress.org repository by slug, then optionally activate it. Requires install_plugins. ONLY wp.org slugs are accepted: package URLs are rejected, because accepting one would make this a download-and-execute-arbitrary-code tool. Call without confirm to preview what would be installed (name, version, author, active install count) so the target can be verified before anything is written.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array(
							'slug'     => array( 'type' => 'string', 'description' => 'WordPress.org plugin slug, e.g. "wp-super-cache". Not a URL, not a file path.' ),
							'activate' => array( 'type' => 'boolean', 'description' => 'Activate immediately after a successful install. Default false: install and activate are separate decisions.' ),
						),
						$confirm_props
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'wp_delete_plugin',
				'description' => 'Permanently delete an installed plugin\'s files. Requires delete_plugins. The plugin must be inactive first: this tool will not deactivate on your behalf, because deletion is irreversible and deactivation is the natural checkpoint. More MCP itself cannot be deleted. Call without confirm to preview.',
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

	public static function execute_tool( string $name, array $args ) {

		
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
