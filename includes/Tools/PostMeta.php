<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostMeta implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_post_meta', 'description' => 'Get post meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['post_id']]],
			['name' => 'wp_update_post_meta', 'description' => 'Update post meta. Value can be any JSON type (string, number, boolean, array, object). String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success. Arrays and objects are serialized by WordPress on write and returned as PHP arrays by wp_get_post_meta on read. Overwrites the existing row for this key (use wp_add_post_meta for multi-row keys).', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']], 'description' => 'Any JSON value. Do not pass PHP-serialized strings (a:1:{...}) — pass the structured value directly.']], 'required' => ['post_id', 'key', 'value']]],
			['name' => 'wp_add_post_meta', 'description' => 'Add a meta row without overwriting existing values under the same key. Use for keys that store multiple rows (e.g. tag one post with several IDs under the same key). Value can be any JSON type. String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success. Arrays and objects are serialized by WordPress. If unique=true and a row with this key already exists, the call returns created=false.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']], 'description' => 'Any JSON value. Do not pass PHP-serialized strings.'], 'unique' => ['type' => 'boolean', 'description' => 'If true, fail (return created=false) when a row with this key already exists. Default false.']], 'required' => ['post_id', 'key', 'value']]],
			['name' => 'wp_delete_post_meta', 'description' => 'Delete post meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['post_id', 'key']]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [ 'wp_get_post_meta', 'wp_update_post_meta', 'wp_add_post_meta', 'wp_delete_post_meta' ];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		
		$post_id = intval( $args['post_id'] ?? $args['id'] ?? 0 );

		switch ( $name ) {
			case 'wp_get_post_meta':
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				$key = !empty($args['key']) ? sanitize_text_field($args['key']) : '';

				

				

				

				$needs_edit_cap = ($key === '' || strpos($key, '_') === 0);
				$cap = $needs_edit_cap ? 'edit_post' : 'read_post';
				if (!current_user_can($cap, $post_id)) {
					throw new \Exception('You do not have permission to read meta on this post.');
				}
				if ($key !== '') {
					$value = get_post_meta($post_id, $key, true);
					return ['key' => $key, 'value' => $value];
				}
				return get_post_meta($post_id);

			case 'wp_update_post_meta':
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit meta on this post.');
				}
				if (!array_key_exists('value', $args)) {
					throw new \Exception('A value is required. To remove a key entirely, use wp_delete_post_meta.');
				}
				$meta_key   = sanitize_text_field($args['key']);
				$meta_value = Meta_Support::filter_meta_value($args['value'], $meta_key, $post_id, 'wp_update_post_meta');
				$write      = Meta_Support::write_meta_verified('post', $post_id, $meta_key, $meta_value);
				return array_merge([
					'message' => 'Post meta updated successfully',
					'result'  => $write['written'],
					'post_id' => $post_id,
					'key'     => $meta_key,
				], Meta_Support::meta_write_report($write));

			case 'wp_add_post_meta':
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit meta on this post.');
				}
				if (!array_key_exists('value', $args)) {
					throw new \Exception('A value is required.');
				}
				$key = sanitize_text_field($args['key'] ?? '');
				if ($key === '') throw new \Exception('A meta key is required.');
				$meta_value = Meta_Support::filter_meta_value($args['value'], $key, $post_id, 'wp_add_post_meta');
				$unique = !empty($args['unique']);
				$write  = Meta_Support::write_meta_verified('post', $post_id, $key, $meta_value, true, $unique);
				return array_merge([
					'meta_id' => $write['meta_id'],
					'created' => $write['written'],
					'post_id' => $post_id,
					'key'     => $key,
				], Meta_Support::meta_write_report($write));

			case 'wp_delete_post_meta':
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit meta on this post.');
				}
				$result = delete_post_meta($post_id, sanitize_text_field($args['key']));
				if (!$result) throw new \Exception('Failed to delete post meta');
				return ['message' => 'Post meta deleted successfully'];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
