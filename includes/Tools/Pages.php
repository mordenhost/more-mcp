<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pages implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_pages', 'description' => 'List WordPress pages. Returns id, title, status, and URL for each. Filter by parent to walk the page hierarchy.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of pages (default 10, max 100)'], 'parent' => ['type' => 'integer', 'description' => 'Parent page ID — returns only direct children of this page']]]],
			['name' => 'wp_get_page', 'description' => 'Get single page by ID', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'Page ID']], 'required' => ['id']]],
			['name' => 'wp_create_page', 'description' => 'Create new page. Combine status="future" with date to schedule. Excerpt (via wp_update_post_meta on _excerpt or via wp_create_post fallback) may contain safe HTML.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'future', 'pending', 'private']], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to schedule.'], 'parent' => ['type' => 'integer', 'description' => 'Parent page ID']], 'required' => ['title', 'content']]],
			['name' => 'wp_update_page', 'description' => 'Update existing page. Response includes saved_fields (actual stored values, read back from DB) so silent-drop / silent-modify by WordPress is surfaced rather than hidden. Pass date to reschedule or backdate.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string'], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to reschedule, or use alone to backdate.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional page excerpt. May contain safe HTML.'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to reassign as page author.'], 'menu_order' => ['type' => 'integer', 'description' => 'Order among sibling pages. Lower = earlier in navigation.'], 'post_parent' => ['type' => 'integer', 'description' => 'Parent page ID (0 = top-level). Throws if the ID does not exist.'], 'password' => ['type' => 'string', 'description' => 'Page password. Empty string removes protection.']], 'required' => ['id']]],
			['name' => 'wp_replace_in_page', 'description' => 'Find/replace a literal string inside a page\'s content without resending the full body. Page-typed variant of wp_replace_in_post — same semantics: case-sensitive literal match, all occurrences replaced, dry_run preview, expected_count guard, read-after-write verification.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'find' => ['type' => 'string', 'minLength' => 1, 'description' => 'Literal text to find in the page content (case-sensitive, no regex).'], 'replace' => ['type' => 'string', 'description' => 'Literal replacement text. Empty string deletes the matched text.'], 'expected_count' => ['type' => 'integer', 'description' => 'Optional guard: abort without writing unless the number of occurrences equals this value.'], 'dry_run' => ['type' => 'boolean', 'description' => 'Report the occurrence count without writing. Default false.']], 'required' => ['id', 'find', 'replace']]],
			['name' => 'wp_delete_page', 'description' => 'Delete page', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_pages', 'wp_get_page', 'wp_create_page',
			'wp_update_page', 'wp_replace_in_page', 'wp_delete_page',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_pages':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list pages.');
				}
				$page_args = ['number' => min(intval($args['per_page'] ?? 10), 100)];
				if (!empty($args['parent'])) $page_args['parent'] = intval($args['parent']);
				$pages = get_pages($page_args);
				return array_map(function($p) {
					return [
						'id' => $p->ID,
						'title' => $p->post_title,
						'url' => get_permalink($p),
						'status' => $p->post_status,
						'parent' => $p->post_parent,
					];
				}, $pages);

			case 'wp_get_page':
				$page = get_post(Content_Support::resolve_post_id_arg($args));
				if (!$page || $page->post_type !== 'page') throw new \Exception('Page not found');
				if (!current_user_can('read_post', $page->ID)) {
					throw new \Exception('You do not have permission to read this page.');
				}
				return [
					'id' => $page->ID,
					'title' => $page->post_title,
					'content' => $page->post_content,
					'status' => $page->post_status,
					'url' => get_permalink($page),
					'parent' => $page->post_parent,
				];

			case 'wp_create_page':
				if (!current_user_can('edit_pages')) {
					throw new \Exception('You do not have permission to create pages.');
				}
				
				$page_status = in_array($args['status'] ?? 'draft', ['publish', 'draft', 'future', 'pending', 'private']) ? $args['status'] : 'draft';
				
				if (in_array($page_status, ['publish', 'future', 'private'], true) && !current_user_can('publish_pages')) {
					throw new \Exception('You do not have permission to publish pages.');
				}
				
				$page_data = [
					'post_title' => sanitize_text_field($args['title']),
					'post_content' => wp_slash($args['content']),
					'post_status' => $page_status,
					'post_type' => 'page',
				];
				if (!empty($args['parent'])) $page_data['post_parent'] = intval($args['parent']);
				
				if (!empty($args['date'])) {
					$ts = strtotime((string) $args['date']);
					if (false === $ts) {
						throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
					}
					$page_data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
					$page_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
				}
				$page_id = wp_insert_post($page_data);
				if (is_wp_error($page_id)) throw new \Exception(esc_html($page_id->get_error_message()));
				return ['id' => $page_id, 'message' => 'Page created successfully', 'url' => get_permalink($page_id)];

			case 'wp_update_page':
				$page_id = Content_Support::resolve_post_id_arg($args);
				$existing_page = $page_id > 0 ? get_post($page_id) : null;
				if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
				if (!current_user_can('edit_post', $page_id)) {
					throw new \Exception('You do not have permission to edit this page.');
				}
				
				if (isset($args['post_author']) && intval($args['post_author']) > 0) {
					if (!get_userdata(intval($args['post_author']))) {
						throw new \Exception('post_author user ID not found.');
					}
				}
				
				if (isset($args['post_parent']) && intval($args['post_parent']) > 0) {
					if (!get_post(intval($args['post_parent']))) {
						throw new \Exception('post_parent page ID not found.');
					}
				}
				$data = ['ID' => $page_id];
				
				if (isset($args['title']) && $args['title'] !== '') $data['post_title'] = sanitize_text_field($args['title']);
				
				if (isset($args['content']) && $args['content'] !== '') $data['post_content'] = wp_slash($args['content']);
				if (isset($args['status'])) $data['post_status'] = sanitize_text_field($args['status']);
				if (isset($args['excerpt']) && $args['excerpt'] !== '') $data['post_excerpt'] = wp_kses_post($args['excerpt']);
				if (isset($args['post_author']) && intval($args['post_author']) > 0) {
					$data['post_author'] = intval($args['post_author']);
				}
				if (array_key_exists('menu_order', $args)) $data['menu_order'] = intval($args['menu_order']);
				if (array_key_exists('post_parent', $args)) $data['post_parent'] = intval($args['post_parent']);
				if (array_key_exists('password', $args)) $data['post_password'] = (string) $args['password'];
				
				if (!empty($args['date'])) {
					$ts = strtotime((string) $args['date']);
					if (false === $ts) {
						throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
					}
					$data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
					$data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
					$data['edit_date'] = true;
				}
				$result = wp_update_post($data);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return Content_Support::build_update_response($page_id, $args, $data, 'Page updated successfully');

			case 'wp_replace_in_page':
				$page_id = Content_Support::resolve_post_id_arg($args);
				$existing_page = $page_id > 0 ? get_post($page_id) : null;
				if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
				return Content_Support::replace_in_post_content($page_id, $args, 'page');

			case 'wp_delete_page':
				$page_id = Content_Support::resolve_post_id_arg($args);
				$existing_page = $page_id > 0 ? get_post($page_id) : null;
				if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
				if (!current_user_can('delete_post', $page_id)) {
					throw new \Exception('You do not have permission to delete this page.');
				}
				$force = !empty($args['force']);
				$result = wp_delete_post($page_id, $force);
				if (!$result) throw new \Exception('Failed to delete page');
				return ['message' => $force ? 'Page permanently deleted' : 'Page moved to trash'];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
