<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appearance implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_plugins', 'description' => 'List all installed plugins. Returns plugin file path, name, version, description, author, and active status for each. Useful for diagnosing plugin conflicts and building a compatibility picture at the start of a debugging conversation.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_themes', 'description' => 'List all installed themes. Returns theme slug, name, version, author, and active flag for each. Use wp_get_active_theme for details on the currently-active theme only.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_active_theme', 'description' => 'Get the active theme with name, version, parent (if child theme), and screenshot URL', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_theme_mods', 'description' => 'Get all customizer settings (theme_mods) for the active theme', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_update_theme_mod', 'description' => 'Update a single theme customizer setting. Requires the "Allow AI to modify theme appearance" admin toggle AND the mod name must be in the allowlist (extend via the more_mcp_writable_theme_mods filter).', 'inputSchema' => ['type' => 'object', 'properties' => ['mod_name' => ['type' => 'string'], 'value' => ['description' => 'New value (any JSON type compatible with set_theme_mod)']], 'required' => ['mod_name', 'value']]],
			['name' => 'wp_get_custom_css', 'description' => 'Get the active theme\'s custom CSS', 'inputSchema' => ['type' => 'object', 'properties' => ['theme_slug' => ['type' => 'string', 'description' => 'Theme slug (defaults to active theme)']]]],
			['name' => 'wp_update_custom_css', 'description' => 'Update the active theme\'s custom CSS. CSS is filtered through wp_kses (script tags stripped). Requires the "Allow AI to modify theme appearance" admin toggle and unfiltered_html capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['css' => ['type' => 'string'], 'theme_slug' => ['type' => 'string', 'description' => 'Theme slug (defaults to active theme)']], 'required' => ['css']]],
			['name' => 'wp_get_widgets', 'description' => 'List widget instances. Uses the WordPress core /wp/v2/widgets REST endpoint so classic and block widgets are returned uniformly. Omit sidebar to return widgets across ALL sidebars including wp_inactive_widgets (orphaned widgets from prior themes — these have rendered:"" and produce no front-end output). Filter by a specific sidebar ID (discover IDs via wp_get_sidebars) to scope results; a non-existent sidebar ID returns an empty array, not an error.', 'inputSchema' => ['type' => 'object', 'properties' => ['sidebar' => ['type' => 'string', 'description' => 'Optional sidebar ID to filter by. Omit to return widgets across all sidebars (includes wp_inactive_widgets).']]]],
			['name' => 'wp_get_sidebars', 'description' => 'List registered sidebars (widget areas) on the active theme with their IDs, names, description, and status. Use to discover sidebar IDs before calling wp_get_widgets or wp_update_widget.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_update_widget', 'description' => 'Update a widget instance by ID. Requires the "Allow AI to modify theme appearance" admin toggle AND edit_theme_options capability. Uses WordPress core /wp/v2/widgets so classic and block widgets are handled uniformly. Pass the id returned by wp_get_widgets.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'description' => 'Widget ID (e.g. text-2, block-15)'], 'sidebar' => ['type' => 'string', 'description' => 'Sidebar ID to place the widget in (omit to leave unchanged)'], 'instance' => ['type' => 'object', 'description' => 'Widget instance data. For classic widgets, either pass the same {encoded, hash} object returned by wp_get_widgets or wrap raw settings as {raw: {…}}.'], 'form_data' => ['type' => 'string', 'description' => 'Serialized form data (classic widgets alternative to instance)']], 'required' => ['id']]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_plugins', 'wp_get_themes', 'wp_get_active_theme', 'wp_get_theme_mods',
			'wp_update_theme_mod', 'wp_get_custom_css', 'wp_update_custom_css',
			'wp_get_widgets', 'wp_get_sidebars', 'wp_update_widget',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_plugins':
				if (!current_user_can('activate_plugins')) {
					throw new \Exception('You do not have permission to list plugins.');
				}
				if (!function_exists('get_plugins')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugins = get_plugins();
				$active = get_option('active_plugins', []);
				$result = [];
				foreach ($plugins as $path => $data) {
					$result[] = [
						'name' => $data['Name'],
						'version' => $data['Version'],
						'active' => in_array($path, $active),
						'author' => $data['Author'],
					];
				}
				return $result;

			case 'wp_get_themes':
				if (!current_user_can('switch_themes')) {
					throw new \Exception('You do not have permission to list themes.');
				}
				$themes = wp_get_themes();
				$active = get_stylesheet();
				$result = [];
				foreach ($themes as $slug => $theme) {
					$result[] = [
						'name' => $theme->get('Name'),
						'version' => $theme->get('Version'),
						'active' => ($slug === $active),
						'author' => $theme->get('Author'),
					];
				}
				return $result;

			case 'wp_get_active_theme':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to view the active theme.');
				}
				$theme = wp_get_theme();
				if (!$theme->exists()) throw new \Exception('Active theme not found.');
				$parent = $theme->parent();
				return [
					'name'           => $theme->get('Name'),
					'slug'           => $theme->get_stylesheet(),
					'template'       => $theme->get_template(),
					'stylesheet'     => $theme->get_stylesheet(),
					'version'        => $theme->get('Version'),
					'author'         => wp_strip_all_tags((string) $theme->get('Author')),
					'description'    => wp_strip_all_tags((string) $theme->get('Description')),
					'parent_slug'    => $parent ? $parent->get_stylesheet() : null,
					'screenshot_url' => $theme->get_screenshot(),
					'status'         => $theme->get('Status'),
				];

			case 'wp_get_theme_mods':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('You do not have permission to read theme mods.');
				}
				$mods = get_theme_mods();
				return is_array($mods) ? $mods : [];

			case 'wp_update_theme_mod':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('You do not have permission to update theme mods.');
				}
				$mod_name = sanitize_text_field($args['mod_name'] ?? '');
				if (empty($mod_name)) throw new \Exception('mod_name is required.');

				$rmcp_settings = get_option('more_mcp_settings', []);
				if (empty($rmcp_settings['allow_theme_writes'])) {
					throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
				}

				$writable = apply_filters('more_mcp_writable_theme_mods', []);
				if (!is_array($writable)) $writable = [];
				if (!in_array($mod_name, $writable, true)) {
					throw new \Exception('Theme mod not in allowlist: ' . esc_html($mod_name) . '. Theme/plugin authors can opt their mods in via add_filter("more_mcp_writable_theme_mods", ...).');
				}

				$previous = get_theme_mod($mod_name);
				$value = $args['value'] ?? null;
				set_theme_mod($mod_name, $value);
				return [
					'mod_name'       => $mod_name,
					'previous_value' => $previous,
					'new_value'      => get_theme_mod($mod_name),
				];

			case 'wp_get_custom_css':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to read custom CSS.');
				}
				$theme_slug = isset($args['theme_slug']) ? sanitize_key($args['theme_slug']) : get_stylesheet();
				$css = wp_get_custom_css($theme_slug);
				$post = wp_get_custom_css_post($theme_slug);
				return [
					'css'        => (string) $css,
					'theme_slug' => $theme_slug,
					'post_id'    => $post ? (int) $post->ID : 0,
				];

			case 'wp_update_custom_css':
				if (!current_user_can('unfiltered_html')) {
					throw new \Exception('unfiltered_html capability required to update custom CSS.');
				}
				$rmcp_settings = get_option('more_mcp_settings', []);
				if (empty($rmcp_settings['allow_theme_writes'])) {
					throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
				}
				$css = $args['css'] ?? '';
				if (!is_string($css)) throw new \Exception('css must be a string.');
				$theme_slug = isset($args['theme_slug']) ? sanitize_key($args['theme_slug']) : get_stylesheet();
				$result = wp_update_custom_css_post($css, ['stylesheet' => $theme_slug]);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return [
					'success'    => true,
					'post_id'    => (int) $result->ID,
					'theme_slug' => $theme_slug,
					'byte_count' => strlen($css),
				];

			case 'wp_get_widgets':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required to list widgets.');
				}
				$request = new \WP_REST_Request('GET', '/wp/v2/widgets');
				if (!empty($args['sidebar'])) {
					$request->set_param('sidebar', sanitize_key((string) $args['sidebar']));
				}
				$response = rest_do_request($request);
				if ($response->is_error()) {
					throw new \Exception(esc_html($response->as_error()->get_error_message()));
				}
				return $response->get_data();

			case 'wp_get_sidebars':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required to list sidebars.');
				}
				$request = new \WP_REST_Request('GET', '/wp/v2/sidebars');
				$response = rest_do_request($request);
				if ($response->is_error()) {
					throw new \Exception(esc_html($response->as_error()->get_error_message()));
				}
				return $response->get_data();

			case 'wp_update_widget':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required to update widgets.');
				}
				$rmcp_settings = get_option('more_mcp_settings', []);
				if (empty($rmcp_settings['allow_theme_writes'])) {
					throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
				}
				$widget_id = isset($args['id']) ? sanitize_text_field((string) $args['id']) : '';
				if ($widget_id === '') {
					throw new \Exception('Widget id is required.');
				}
				$request = new \WP_REST_Request('PUT', '/wp/v2/widgets/' . $widget_id);
				foreach (['sidebar', 'instance', 'form_data'] as $param) {
					if (isset($args[$param])) {
						$request->set_param($param, $args[$param]);
					}
				}
				$response = rest_do_request($request);
				if ($response->is_error()) {
					throw new \Exception(esc_html($response->as_error()->get_error_message()));
				}
				return $response->get_data();
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
