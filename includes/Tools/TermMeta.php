<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TermMeta implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_term_meta', 'description' => 'Get term meta data — the raw wp_termmeta table. For SEO fields, prefer wp_get_term_seo_meta: Yoast stores taxonomy SEO in the wpseo_taxonomy_meta option and All in One SEO in its own aioseo_terms table, so neither appears here. Rank Math and SEOPress do use term meta, so their keys are readable through this tool.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string', 'description' => 'Specific meta key. Omit to return all meta for the term.']], 'required' => ['term_id']]],
			['name' => 'wp_update_term_meta', 'description' => 'Update term meta data — writes to the raw wp_termmeta table. For SEO fields, use wp_update_term_seo_meta instead. Two SEO plugins do NOT read term meta: Yoast keeps taxonomy SEO in the wpseo_taxonomy_meta option and All in One SEO in its aioseo_terms table, so a write here returns success, round-trips correctly on read, and changes nothing on the rendered archive (GitHub #6). Rank Math (rank_math_title / rank_math_description) and SEOPress do use term meta, so writing their keys here works. String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']]]], 'required' => ['term_id', 'key', 'value']]],
			['name' => 'wp_delete_term_meta', 'description' => 'Delete term meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['term_id', 'key']]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_get_term_meta' === $name || 'wp_update_term_meta' === $name || 'wp_delete_term_meta' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_term_meta':
				$term_id = intval($args['term_id']);
				if (!get_term($term_id)) throw new \Exception('Term not found');
				if (!current_user_can('manage_categories')) {
					throw new \Exception('You do not have permission to read term meta.');
				}

				
				
				if (!empty($args['key'])) {
					$key = sanitize_text_field($args['key']);
					return [
						'term_id' => $term_id,
						'key'     => $key,
						'value'   => get_term_meta($term_id, $key, true),
					];
				}
				return [
					'term_id' => $term_id,
					'meta'    => (array) get_term_meta($term_id),
				];

			case 'wp_update_term_meta':
				$term_id = intval($args['term_id']);
				if (!get_term($term_id)) throw new \Exception('Term not found');
				if (!current_user_can('edit_term', $term_id)) {
					throw new \Exception('You do not have permission to edit this term.');
				}
				if (!array_key_exists('value', $args)) {
					throw new \Exception('A value is required.');
				}
				$meta_key   = sanitize_text_field($args['key']);
				$meta_value = Meta_Support::filter_meta_value($args['value'], $meta_key, $term_id, 'wp_update_term_meta');
				$write      = Meta_Support::write_meta_verified('term', $term_id, $meta_key, $meta_value);
				if (!$write['written']) throw new \Exception('Failed to update term meta');
				return array_merge([
					'term_id' => $term_id,
					'key'     => $meta_key,
					'message' => 'Term meta updated',
				], Meta_Support::meta_write_report($write));

			case 'wp_delete_term_meta':
				$term_id = intval($args['term_id']);
				if (!get_term($term_id)) throw new \Exception('Term not found');
				if (!current_user_can('edit_term', $term_id)) {
					throw new \Exception('You do not have permission to edit this term.');
				}
				$result = delete_term_meta($term_id, sanitize_text_field($args['key']));
				if (!$result) throw new \Exception('Failed to delete term meta (key may not exist)');
				return ['term_id' => $term_id, 'message' => 'Term meta deleted'];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
