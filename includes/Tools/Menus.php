<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menus implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_menus', 'description' => 'List all registered navigation menus (nav_menu taxonomy). Returns id, name, slug, and item count for each. Use wp_get_menu_items to enumerate items within a specific menu.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_menu_items', 'description' => 'Get menu items', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer']], 'required' => ['menu_id']]],
			['name' => 'wp_create_menu_item', 'description' => 'Create a menu item in a navigation menu. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string', 'description' => 'External URL (leave empty if linking to a post/page via object_id)'], 'object_id' => ['type' => 'integer', 'description' => 'WordPress object ID (post, page, or term)'], 'object_type' => ['type' => 'string', 'enum' => ['post', 'page', 'category', 'custom'], 'description' => 'Type of object being linked (default: custom)'], 'parent_id' => ['type' => 'integer', 'description' => 'Parent menu item ID for nested items (0 = top level)'], 'position' => ['type' => 'integer', 'description' => 'Position in menu order (default: end)'], 'target' => ['type' => 'string', 'enum' => ['_self', '_blank'], 'description' => 'Link target']], 'required' => ['menu_id', 'title']]],
			['name' => 'wp_update_menu_item', 'description' => 'Update an existing menu item. Only the fields you pass will change; unspecified fields are preserved from the existing item. The tool will refuse explicit-empty values for title or url that would destroy a non-empty existing value: to intentionally clear those, use wp_delete_menu_item then wp_create_menu_item. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_item_id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string'], 'parent_id' => ['type' => 'integer'], 'position' => ['type' => 'integer'], 'target' => ['type' => 'string', 'enum' => ['_self', '_blank']]], 'required' => ['menu_item_id']]],
			['name' => 'wp_delete_menu_item', 'description' => 'Delete a menu item. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_item_id' => ['type' => 'integer']], 'required' => ['menu_item_id']]],
			['name' => 'wp_reorder_menu_items', 'description' => 'Reorder menu items by passing an array of menu_item_ids in the desired order. Existing titles, URLs, parents, and other fields are preserved on every item touched. Every response includes an "undo" envelope with a token that more_mcp_undo_last_operation can consume for 72 hours to restore the pre-op menu order. If the response includes a "skipped" array, those items could not be safely reordered (e.g. missing or recently deleted); the rest were reordered correctly. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer'], 'item_order' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Array of menu_item_ids in the desired order']], 'required' => ['menu_id', 'item_order']]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_menus', 'wp_get_menu_items', 'wp_create_menu_item',
			'wp_update_menu_item', 'wp_delete_menu_item', 'wp_reorder_menu_items',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_menus':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('You do not have permission to list menus.');
				}
				$menus = wp_get_nav_menus();
				return array_map(function($m) {
					return ['id' => $m->term_id, 'name' => $m->name, 'slug' => $m->slug];
				}, $menus);

			case 'wp_get_menu_items':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('You do not have permission to list menu items.');
				}
				$items = wp_get_nav_menu_items(intval($args['menu_id']));
				if (!$items) return [];
				return array_map(function($i) {
					return [
						'id' => $i->ID,
						'title' => $i->title,
						'url' => $i->url,
						'parent' => $i->menu_item_parent,
						'order' => $i->menu_order,
					];
				}, $items);

			case 'wp_create_menu_item':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required.');
				}
				$menu_id = intval($args['menu_id']);
				if (!wp_get_nav_menu_object($menu_id)) {
					throw new \Exception('Menu not found: ' . esc_html((string) $menu_id));
				}
				$object_type = sanitize_text_field($args['object_type'] ?? 'custom');
				$item_args = [
					'menu-item-title'     => sanitize_text_field($args['title']),
					'menu-item-url'       => esc_url_raw($args['url'] ?? ''),
					'menu-item-status'    => 'publish',
					'menu-item-type'      => $object_type === 'category' ? 'taxonomy' : ($object_type === 'custom' ? 'custom' : 'post_type'),
					'menu-item-object'    => $object_type === 'category' ? 'category' : ($object_type === 'custom' ? '' : $object_type),
					'menu-item-object-id' => intval($args['object_id'] ?? 0),
					'menu-item-parent-id' => intval($args['parent_id'] ?? 0),
					'menu-item-position'  => intval($args['position'] ?? 0),
					'menu-item-target'    => sanitize_text_field($args['target'] ?? ''),
				];
				$item_id = wp_update_nav_menu_item($menu_id, 0, $item_args);
				if (is_wp_error($item_id)) throw new \Exception(esc_html($item_id->get_error_message()));
				return ['menu_item_id' => (int) $item_id, 'menu_id' => $menu_id];

			case 'wp_update_menu_item':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required.');
				}
				$item_id = intval($args['menu_item_id']);
				$existing = get_post($item_id);
				if (!$existing || $existing->post_type !== 'nav_menu_item') {
					throw new \Exception('Menu item not found.');
				}
				$menus = wp_get_post_terms($item_id, 'nav_menu', ['fields' => 'ids']);
				$menu_id = (!empty($menus) && !is_wp_error($menus)) ? (int) $menus[0] : 0;

				
				
				$overrides = [];
				if (isset($args['title']))     $overrides['menu-item-title']     = sanitize_text_field($args['title']);
				if (isset($args['url']))       $overrides['menu-item-url']       = esc_url_raw($args['url']);
				if (isset($args['parent_id'])) $overrides['menu-item-parent-id'] = intval($args['parent_id']);
				if (isset($args['position']))  $overrides['menu-item-position']  = intval($args['position']);
				if (isset($args['target']))    $overrides['menu-item-target']    = sanitize_text_field($args['target']);
				$merged = self::build_safe_menu_item_args($item_id, $overrides);
				if (is_wp_error($merged)) {
					throw new \Exception(esc_html($merged->get_error_message()));
				}
				$result = wp_update_nav_menu_item($menu_id, $item_id, $merged);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return ['menu_item_id' => $item_id, 'menu_id' => $menu_id];

			case 'wp_delete_menu_item':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required.');
				}
				$item_id = intval($args['menu_item_id']);
				$existing = get_post($item_id);
				if (!$existing || $existing->post_type !== 'nav_menu_item') {
					throw new \Exception('Menu item not found.');
				}
				$result = wp_delete_post($item_id, true);
				if (!$result) throw new \Exception('Failed to delete menu item.');
				return ['success' => true, 'menu_item_id' => $item_id];

			case 'wp_reorder_menu_items':
				if (!current_user_can('edit_theme_options')) {
					throw new \Exception('edit_theme_options capability required.');
				}
				$menu_id = intval($args['menu_id']);
				$menu_obj = wp_get_nav_menu_object($menu_id);
				if (!$menu_obj) {
					throw new \Exception('Menu not found.');
				}
				$order = $args['item_order'] ?? [];
				if (!is_array($order)) throw new \Exception('item_order must be an array of menu_item_ids.');

				

				$pre_op_items = wp_get_nav_menu_items($menu_id) ?: [];
				$pre_op_state = [];
				foreach ($pre_op_items as $item) {
					$pre_op_state[(int) $item->db_id] = [
						'menu_order'       => (int) $item->menu_order,
						'menu_item_parent' => (int) $item->menu_item_parent,
					];
				}

				

				$position = 1;
				$reordered = [];
				$skipped = [];
				foreach ($order as $iid) {
					$iid = intval($iid);
					if ($iid <= 0) {
						continue;
					}
					$merged = self::build_safe_menu_item_args($iid, [
						'menu-item-position' => $position,
					]);
					if (is_wp_error($merged)) {
						$skipped[] = ['menu_item_id' => $iid, 'reason' => $merged->get_error_message()];
						continue;
					}
					$result = wp_update_nav_menu_item($menu_id, $iid, $merged);
					if (is_wp_error($result)) {
						$skipped[] = ['menu_item_id' => $iid, 'reason' => $result->get_error_message()];
						continue;
					}
					$reordered[] = $iid;
					$position++;
				}

				$undo_envelope = \More_MCP\MCP\Undo_Store::store([
					'op'      => 'wp_reorder_menu_items',
					'summary' => sprintf('Restore menu "%s" (%d items) to prior order', $menu_obj->name, count($pre_op_state)),
					'target'  => ['menu_id' => $menu_id, 'menu_name' => $menu_obj->name],
					'pre_op_state' => $pre_op_state,
				]);

				$response = ['success' => true, 'menu_id' => $menu_id, 'count' => count($reordered), 'reordered' => $reordered];
				if (!empty($skipped)) {
					$response['skipped'] = $skipped;
				}
				$response['undo'] = $undo_envelope;
				return $response;
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function build_safe_menu_item_args( $item_id, $overrides ) {
		$post = get_post($item_id);
		if (!$post || $post->post_type !== 'nav_menu_item') {
			return new \WP_Error('item_not_found', "Menu item {$item_id} not found.");
		}
		$existing = wp_setup_nav_menu_item($post);
		if (!$existing || is_wp_error($existing)) {
			return new \WP_Error('item_setup_failed', "Could not read menu item {$item_id} for safe merge.");
		}
		$classes = $existing->classes ?? '';
		if (is_array($classes)) {
			$classes = implode(' ', $classes);
		}
		$base = [
			'menu-item-db-id'       => (int) $item_id,
			'menu-item-object-id'   => (int) ($existing->object_id ?? 0),
			'menu-item-object'      => (string) ($existing->object ?? ''),
			'menu-item-parent-id'   => (int) ($existing->menu_item_parent ?? 0),
			'menu-item-position'    => (int) ($existing->menu_order ?? 0),
			'menu-item-type'        => (string) ($existing->type ?? 'custom'),
			'menu-item-title'       => (string) ($existing->title ?? ''),
			'menu-item-url'         => (string) ($existing->url ?? ''),
			'menu-item-description' => (string) ($existing->description ?? ''),
			'menu-item-attr-title'  => (string) ($existing->attr_title ?? ''),
			'menu-item-target'      => (string) ($existing->target ?? ''),
			'menu-item-classes'     => (string) $classes,
			'menu-item-xfn'         => (string) ($existing->xfn ?? ''),
			'menu-item-status'      => 'publish',
		];

		

		
		$destructive_fields = ['menu-item-title', 'menu-item-url'];
		foreach ($destructive_fields as $arg_key) {
			if (!array_key_exists($arg_key, $overrides)) {
				continue;
			}
			$existing_value = (string) ($base[$arg_key] ?? '');
			$new_value = (string) $overrides[$arg_key];
			if ($existing_value !== '' && $new_value === '') {
				return new \WP_Error(
					'destructive_operation_blocked',
					"Refused: passing empty '{$arg_key}' would zero a non-empty value on menu item {$item_id}. To clear intentionally, use wp_delete_menu_item + wp_create_menu_item."
				);
			}
		}
		return array_merge($base, $overrides);
	}
}
