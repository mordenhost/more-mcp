<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Options implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_option', 'description' => 'Get a single WordPress option value (allowlisted, sensitive keys redacted)', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
			['name' => 'wp_get_plugin_settings', 'description' => 'Get all options stored by a plugin, looked up by slug. Sensitive keys (api keys, secrets, tokens, passwords) are redacted before return.', 'inputSchema' => ['type' => 'object', 'properties' => ['plugin_slug' => ['type' => 'string', 'description' => 'Plugin slug, e.g. woocommerce or rank-math']], 'required' => ['plugin_slug']]],
			['name' => 'wp_update_option', 'description' => 'Update a WordPress option. Requires the "Allow AI to write WordPress options" admin toggle to be enabled, and the option name must be in the allowlist (extend via the more_mcp_writable_options filter). Sensitive option names are permanently denylisted.', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'value' => ['description' => 'New value (any JSON type). Full overwrite — read first, merge in your client, then write back.']], 'required' => ['name', 'value']]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_get_option' === $name || 'wp_get_plugin_settings' === $name || 'wp_update_option' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_option':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read site options.');
				}

				

				

				

				$allowed = ['blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'posts_per_page', 'date_format', 'time_format', 'timezone_string', 'googlesitekit_analytics-4_settings', 'wpseo_taxonomy_meta'];
				$name = sanitize_text_field($args['name']);
				if (!in_array($name, $allowed)) throw new \Exception('Option not allowed: ' . esc_html($name));
				return ['name' => $name, 'value' => Options_Support::redact_sensitive_keys(get_option($name))];

			case 'wp_get_plugin_settings':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read plugin settings.');
				}
				$slug = sanitize_text_field($args['plugin_slug'] ?? '');
				if (empty($slug)) throw new \Exception('plugin_slug is required.');
				return [
					'slug'    => $slug,
					'options' => Options_Support::find_plugin_options($slug),
				];

			case 'wp_update_option':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to write site options.');
				}
				$name = sanitize_text_field($args['name'] ?? '');
				if (empty($name)) throw new \Exception('Option name is required.');

				$rmcp_settings = get_option('more_mcp_settings', []);
				if (empty($rmcp_settings['allow_option_writes'])) {
					throw new \Exception('Option writes are disabled. Enable "Allow AI to write WordPress options" under More MCP > Settings > General Settings.');
				}

				if (Options_Support::is_denylisted_option($name)) {
					throw new \Exception('Option is permanently denylisted: ' . esc_html($name));
				}

				$default_writable = ['blogname', 'blogdescription', 'posts_per_page', 'date_format', 'time_format'];
				$writable = apply_filters('more_mcp_writable_options', $default_writable);
				if (!is_array($writable)) $writable = $default_writable;
				if (!in_array($name, $writable, true)) {
					throw new \Exception('Option not in allowlist: ' . esc_html($name) . '. Plugin authors can opt their settings in via add_filter("more_mcp_writable_options", ...).');
				}

				$value = $args['value'] ?? null;
				$previous = get_option($name);
				$result = update_option($name, $value);
				return [
					'name'     => $name,
					'updated'  => (bool) $result,
					'previous' => Options_Support::redact_sensitive_keys($previous),
				];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
