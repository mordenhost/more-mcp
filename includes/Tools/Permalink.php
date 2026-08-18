<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Permalink implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_permalink_structure', 'description' => 'Get the WordPress permalink structure (e.g. /%postname%/, /%year%/%monthnum%/%postname%/). Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_update_permalink_structure', 'description' => 'Update the WordPress permalink structure. Requires the "Allow AI to write WordPress options" admin toggle. Common values: /%postname%/, /%year%/%monthnum%/%postname%/, /%category%/%postname%/. Changing this rewrites every URL on the site — flushes rewrite rules automatically.', 'inputSchema' => ['type' => 'object', 'properties' => ['structure' => ['type' => 'string', 'description' => 'New permalink structure (e.g. /%postname%/)']], 'required' => ['structure']]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_get_permalink_structure' === $name || 'wp_update_permalink_structure' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_permalink_structure':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read permalink structure.');
				}
				return [
					'permalink_structure' => (string) get_option('permalink_structure', ''),
					'category_base'       => (string) get_option('category_base', ''),
					'tag_base'            => (string) get_option('tag_base', ''),
				];

			case 'wp_update_permalink_structure':
				$rmcp_settings = get_option('more_mcp_settings', []);
				if (empty($rmcp_settings['allow_option_writes'])) {
					throw new \Exception('Permalink writes are disabled. Enable "Allow AI to write WordPress options" under More MCP > Settings.');
				}
				if (!current_user_can('manage_options')) {
					throw new \Exception('manage_options capability required.');
				}
				$structure = isset($args['structure']) ? sanitize_text_field((string) $args['structure']) : '';
				if (empty($structure)) {
					throw new \Exception('structure is required (e.g. /%postname%/)');
				}
				$previous = (string) get_option('permalink_structure', '');
				global $wp_rewrite;
				if ($wp_rewrite) {
					$wp_rewrite->set_permalink_structure($structure);
					$wp_rewrite->flush_rules();
				} else {
					update_option('permalink_structure', $structure);
				}
				return [
					'success'  => true,
					'previous' => $previous,
					'current'  => (string) get_option('permalink_structure', ''),
				];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
