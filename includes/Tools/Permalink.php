<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Permalink implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_permalink_structure', 'description' => 'Get the WordPress permalink structure (e.g. /%postname%/, /%year%/%monthnum%/%postname%/) and the category and tag bases. Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_update_permalink_structure', 'description' => 'Update the WordPress permalink settings (Settings → Permalinks): the post structure, the category base and the tag base. Pass only what changes, at least one of the three. Requires the "Site options" switch (Mordenhost MCP > Access). Common structures: /%postname%/, /%year%/%monthnum%/%postname%/, /%category%/%postname%/. category_base and tag_base replace the "category" and "tag" segment of term archive URLs (category_base "artikel/kategori" serves /artikel/kategori/<term>/); pass "" to go back to the default. Changing any of these rewrites URLs across the site, and rewrite rules are flushed automatically.', 'inputSchema' => ['type' => 'object', 'properties' => ['structure' => ['type' => 'string', 'description' => 'New permalink structure (e.g. /%postname%/)'], 'category_base' => ['type' => 'string', 'description' => 'Category archive base, e.g. "topics" or "artikel/kategori": letters, digits, hyphens, underscores and slashes. Omit it to leave the base unchanged; "" restores the default ("category").'], 'tag_base' => ['type' => 'string', 'description' => 'Tag archive base, e.g. "label". Same rules as category_base. Omit it to leave the base unchanged; "" restores the default ("tag").']]]],
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
				$rmcp_settings = get_option('mordenhost_mcp_settings', []);
				if (empty($rmcp_settings['allow_option_writes'])) {
					throw new \Exception('Permalink writes are disabled. The site owner can turn on the "Site options" switch under Mordenhost MCP > Access.');
				}
				if (!current_user_can('manage_options')) {
					throw new \Exception('manage_options capability required.');
				}
				$structure = null;
				if (isset($args['structure'])) {
					$structure = sanitize_text_field((string) $args['structure']);
					if ('' === $structure) {
						throw new \Exception('structure cannot be empty (e.g. /%postname%/). Omit it to change only the category or tag base.');
					}
				}

				$bases = [];
				foreach (['category_base', 'tag_base'] as $field) {
					if (array_key_exists($field, $args) && null !== $args[$field]) {
						$bases[$field] = self::normalize_base($args[$field], $field);
					}
				}
				if (null === $structure && !$bases) {
					throw new \Exception('Pass at least one of structure, category_base and tag_base (e.g. structure: /%postname%/).');
				}

				$previous = (string) get_option('permalink_structure', '');
				$previous_bases = [];
				foreach ($bases as $field => $value) {
					$previous_bases[$field] = (string) get_option($field, '');
				}
				global $wp_rewrite;
				if (null !== $structure) {
					if ($wp_rewrite) {
						$wp_rewrite->set_permalink_structure($structure);
					} else {
						update_option('permalink_structure', $structure);
					}
				}
				foreach ($bases as $field => $value) {

					if (!$wp_rewrite) {
						update_option($field, $value);
					} elseif ('category_base' === $field) {
						$wp_rewrite->set_category_base($value);
					} else {
						$wp_rewrite->set_tag_base($value);
					}
				}
				if ($wp_rewrite) {
					$wp_rewrite->flush_rules();
				}

				

				
				update_option('rewrite_rules', '');

				$result = [
					'success'  => true,
					'previous' => $previous,
					'current'  => (string) get_option('permalink_structure', ''),
				];
				foreach ($bases as $field => $value) {
					$result[$field] = [
						'previous' => $previous_bases[$field],
						'current'  => (string) get_option($field, ''),
					];
				}
				return $result;
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function normalize_base( $value, string $field ): string {
		if (!is_string($value)) {
			throw new \Exception(esc_html($field) . ' must be a string ("" restores the default).');
		}
		$base = trim(str_replace('#', '', sanitize_text_field($value)), ' /');
		if ('' === $base) {
			return '';
		}
		if (!preg_match('#^[A-Za-z0-9_\-/\x80-\xff]+$#', $base)) {
			throw new \Exception(esc_html($field) . ' may contain only letters, digits, hyphens, underscores and slashes, e.g. "topics" or "artikel/kategori".');
		}
		return '/' . preg_replace('#/+#', '/', $base);
	}
}
