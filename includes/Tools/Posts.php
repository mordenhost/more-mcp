<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Posts implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_posts', 'description' => 'Get WordPress posts (supports custom post types)', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of posts (max 100)'], 'search' => ['type' => 'string', 'description' => 'Search term'], 'status' => ['type' => 'string', 'description' => 'Post status (publish, draft, etc)'], 'post_type' => ['type' => 'string', 'description' => 'Post type slug (default: post). Use wp_get_post_types to discover available types']]]],
			['name' => 'wp_get_post', 'description' => 'Get single post by ID (any post type)', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'Post ID']], 'required' => ['id']]],
			['name' => 'wp_create_post', 'description' => 'Create new post (supports custom post types). Combine status="future" with date to schedule. Excerpt may contain safe HTML (same allow-list as post content).', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'future', 'pending', 'private']], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to schedule. Past dates auto-publish with that timestamp.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional excerpt. May contain safe HTML (same allow-list as post content).'], 'categories' => ['type' => 'array', 'items' => ['type' => 'integer']], 'post_type' => ['type' => 'string', 'description' => 'Post type slug (default: post)'], 'featured_media' => ['type' => 'integer', 'description' => 'Attachment ID to set as featured image'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to assign as the post author. Defaults to the authenticated MCP user (admin). Use wp_get_users to discover available author IDs.']], 'required' => ['title', 'content']]],
			['name' => 'wp_update_post', 'description' => 'Update existing post (any post type). Response includes saved_fields (actual stored values, read back from DB) so silent-drop / silent-modify by WordPress is surfaced rather than hidden. Pass date to reschedule or backdate.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string'], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to reschedule, or use alone to backdate.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional excerpt. May contain safe HTML (same allow-list as post content).'], 'featured_media' => ['type' => 'integer', 'description' => 'Attachment ID to set as featured image (pass 0 to remove)'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to reassign as the post author. Use wp_get_users to discover available author IDs.'], 'menu_order' => ['type' => 'integer', 'description' => 'Order among sibling posts/pages. Lower = earlier.'], 'post_parent' => ['type' => 'integer', 'description' => 'Parent post ID (0 = no parent). Useful for hierarchical CPTs. Throws if the ID does not exist.'], 'password' => ['type' => 'string', 'description' => 'Post password. Empty string removes protection.'], 'comment_status' => ['type' => 'string', 'enum' => ['open', 'closed'], 'description' => 'Allow (open) or disallow (closed) new comments.'], 'ping_status' => ['type' => 'string', 'enum' => ['open', 'closed'], 'description' => 'Allow (open) or disallow (closed) trackbacks / pingbacks.']], 'required' => ['id']]],
			['name' => 'wp_replace_in_post', 'description' => 'Find/replace a literal string inside a post\'s content (any post type) without resending the full body. Use for surgical edits to large posts (page-builder content, embedded base64 payloads) where wp_update_post\'s full-content replacement is impractical. Case-sensitive literal match, no regex. All occurrences are replaced. Response includes occurrence count and read-after-write verification. Set dry_run=true to preview the match count without writing; set expected_count to abort unless exactly that many matches exist.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'find' => ['type' => 'string', 'minLength' => 1, 'description' => 'Literal text to find in post_content (case-sensitive, no regex).'], 'replace' => ['type' => 'string', 'description' => 'Literal replacement text. Empty string deletes the matched text.'], 'expected_count' => ['type' => 'integer', 'description' => 'Optional guard: abort without writing unless the number of occurrences equals this value.'], 'dry_run' => ['type' => 'boolean', 'description' => 'Report the occurrence count without writing. Default false.']], 'required' => ['id', 'find', 'replace']]],
			['name' => 'wp_get_post_types', 'description' => 'Get all registered public post types (including custom post types)', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_delete_post', 'description' => 'Delete post', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean', 'description' => 'Skip trash and permanently delete']], 'required' => ['id']]],
			['name' => 'wp_count_posts', 'description' => 'Get post counts by status', 'inputSchema' => ['type' => 'object', 'properties' => ['post_type' => ['type' => 'string', 'description' => 'Post type (post, page, etc)']]]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_posts', 'wp_get_post', 'wp_create_post', 'wp_update_post',
			'wp_replace_in_post', 'wp_delete_post', 'wp_count_posts', 'wp_get_post_types',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_posts':

				

				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list posts.');
				}
				$query_args = [
					'numberposts' => min(intval($args['per_page'] ?? 10), 100),
					's' => sanitize_text_field($args['search'] ?? ''),
				];
				if (!empty($args['post_type'])) {
					$pt = sanitize_text_field($args['post_type']);
					$pto = get_post_type_object($pt);
					if (!$pto || !$pto->public) throw new \Exception('Invalid or non-public post type: ' . esc_html($pt));
					$query_args['post_type'] = $pt;
				}
				if (!empty($args['status'])) {
					$requested_status = sanitize_text_field($args['status']);

					

					

					$public_statuses = get_post_stati(['public' => true]);
					if (!in_array($requested_status, $public_statuses, true)) {
						$pto_for_caps = !empty($args['post_type']) ? get_post_type_object(sanitize_text_field($args['post_type'])) : get_post_type_object('post');
						$needed_cap = $pto_for_caps && !empty($pto_for_caps->cap->read_private_posts)
							? $pto_for_caps->cap->read_private_posts
							: 'read_private_posts';
						if (!current_user_can($needed_cap)) {
							throw new \Exception('You do not have permission to list ' . esc_html($requested_status) . ' posts.');
						}
					}
					$query_args['post_status'] = $requested_status;
				}
				$posts = get_posts($query_args);
				return array_map(function($p) {
					return [
						'id' => $p->ID,
						'title' => $p->post_title,
						'excerpt' => wp_trim_words($p->post_content, 50),
						'status' => $p->post_status,
						'type' => $p->post_type,
						'url' => get_permalink($p),
						'date' => $p->post_date,
					];
				}, $posts);

			case 'wp_get_post':
				$post = get_post(Content_Support::resolve_post_id_arg($args));
				if (!$post) throw new \Exception('Post not found');

				if (!current_user_can('read_post', $post->ID)) {
					throw new \Exception('You do not have permission to read this post.');
				}
				return [
					'id' => $post->ID,
					'title' => $post->post_title,
					'content' => $post->post_content,
					'excerpt' => $post->post_excerpt,
					'status' => $post->post_status,
					'type' => $post->post_type,
					'url' => get_permalink($post),
					'date' => $post->post_date,
					'modified' => $post->post_modified,
					'author' => get_the_author_meta('display_name', $post->post_author),
				];

			case 'wp_create_post':
				$post_type = sanitize_text_field($args['post_type'] ?? 'post');
				$pto = get_post_type_object($post_type);
				if (!$pto || !$pto->public) throw new \Exception('Invalid or non-public post type: ' . esc_html($post_type));

				
				
				$create_cap = !empty($pto->cap->edit_posts) ? $pto->cap->edit_posts : 'edit_posts';
				if (!current_user_can($create_cap)) {
					throw new \Exception('You do not have permission to create ' . esc_html($post_type) . ' posts.');
				}
				$requested_status = isset($args['status']) ? sanitize_text_field($args['status']) : 'draft';

				

				

				
				if (in_array($requested_status, ['publish', 'future', 'private'], true)) {
					$publish_cap = !empty($pto->cap->publish_posts) ? $pto->cap->publish_posts : 'publish_posts';
					if (!current_user_can($publish_cap)) {
						throw new \Exception('You do not have permission to publish ' . esc_html($post_type) . ' posts.');
					}
				}
				
				if (isset($args['featured_media']) && intval($args['featured_media']) > 0) {
					$fm = get_post(intval($args['featured_media']));
					if (!$fm || $fm->post_type !== 'attachment') throw new \Exception('featured_media attachment not found.');
				}
				
				if (isset($args['post_author']) && intval($args['post_author']) > 0) {
					if (!get_userdata(intval($args['post_author']))) {
						throw new \Exception('post_author user ID not found.');
					}
				}

				

				

				

				

				$post_data = [
					'post_title' => sanitize_text_field($args['title']),
					'post_content' => wp_slash($args['content']),
					'post_status' => in_array($args['status'] ?? 'draft', ['publish', 'draft', 'future', 'pending', 'private']) ? $args['status'] : 'draft',
					'post_type' => $post_type,
				];

				
				if (!empty($args['excerpt'])) $post_data['post_excerpt'] = wp_kses_post($args['excerpt']);
				if (!empty($args['categories'])) $post_data['post_category'] = array_map('intval', $args['categories']);
				if (isset($args['post_author']) && intval($args['post_author']) > 0) {
					$post_data['post_author'] = intval($args['post_author']);
				}

				if (!empty($args['date'])) {
					$ts = strtotime((string) $args['date']);
					if (false === $ts) {
						throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
					}
					$post_data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
					$post_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
				}
				$post_id = wp_insert_post($post_data);
				if (is_wp_error($post_id)) throw new \Exception(esc_html($post_id->get_error_message()));
				if (isset($args['featured_media'])) {
					Media_Support::apply_featured_media($post_id, intval($args['featured_media']));
				}
				return ['id' => $post_id, 'message' => ucfirst($post_type) . ' created successfully', 'url' => get_permalink($post_id)];

			case 'wp_update_post':
				$post_id = Content_Support::resolve_post_id_arg($args);

				
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit this post.');
				}
				
				if (isset($args['featured_media']) && intval($args['featured_media']) > 0) {
					$fm = get_post(intval($args['featured_media']));
					if (!$fm || $fm->post_type !== 'attachment') throw new \Exception('featured_media attachment not found.');
				}
				
				if (isset($args['post_author']) && intval($args['post_author']) > 0) {
					if (!get_userdata(intval($args['post_author']))) {
						throw new \Exception('post_author user ID not found.');
					}
				}

				if (isset($args['post_parent']) && intval($args['post_parent']) > 0) {
					if (!get_post(intval($args['post_parent']))) {
						throw new \Exception('post_parent post ID not found.');
					}
				}
				
				if (array_key_exists('comment_status', $args) && !in_array($args['comment_status'], ['open', 'closed'], true)) {
					throw new \Exception('comment_status must be "open" or "closed".');
				}
				if (array_key_exists('ping_status', $args) && !in_array($args['ping_status'], ['open', 'closed'], true)) {
					throw new \Exception('ping_status must be "open" or "closed".');
				}
				$data = ['ID' => $post_id];

				

				
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
				if (array_key_exists('comment_status', $args)) $data['comment_status'] = $args['comment_status'];
				if (array_key_exists('ping_status', $args)) $data['ping_status'] = $args['ping_status'];

				
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
				if (isset($args['featured_media'])) {
					Media_Support::apply_featured_media($post_id, intval($args['featured_media']));
				}
				return Content_Support::build_update_response($post_id, $args, $data, 'Post updated successfully');

			case 'wp_replace_in_post':
				$post_id = Content_Support::resolve_post_id_arg($args);
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				return Content_Support::replace_in_post_content($post_id, $args, 'post');

			case 'wp_delete_post':
				$post_id = Content_Support::resolve_post_id_arg($args);
				if ($post_id <= 0) throw new \Exception('Post not found.');

				

				
				
				if (!current_user_can('delete_post', $post_id)) {
					throw new \Exception('You do not have permission to delete this post.');
				}
				if (!get_post($post_id)) throw new \Exception('Post not found.');
				$force = !empty($args['force']);
				$result = wp_delete_post($post_id, $force);
				if (!$result) throw new \Exception('Failed to delete post');
				return ['message' => $force ? 'Post permanently deleted' : 'Post moved to trash'];

			case 'wp_count_posts':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to view post counts.');
				}
				$type = sanitize_text_field($args['post_type'] ?? 'post');
				$counts = wp_count_posts($type);
				return (array) $counts;

			case 'wp_get_post_types':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list post types.');
				}
				$types = get_post_types(['public' => true], 'objects');
				return array_values(array_map(function($pt) {
					return [
						'name' => $pt->name,
						'label' => $pt->label,
						'description' => $pt->description,
						'hierarchical' => $pt->hierarchical,
						'has_archive' => (bool) $pt->has_archive,
						'supports' => array_keys(array_filter(get_all_post_type_supports($pt->name))),
					];
				}, $types));
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
