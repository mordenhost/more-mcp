<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comments implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_comments', 'description' => 'List comments on the site or a specific post. Returns id, post_id, author, content, date, and status. Requires moderate_comments to list any status other than "approve".', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Limit to comments on this post ID'], 'per_page' => ['type' => 'integer', 'description' => 'Number of comments (default 10, max 100)'], 'status' => ['type' => 'string', 'enum' => ['approve', 'hold', 'spam', 'trash', 'all'], 'description' => 'Comment status filter. "approve" is public; other values require moderate_comments.']]]],
			['name' => 'wp_create_comment', 'description' => 'Create a comment. Content may contain WordPress\'s standard comment HTML tags (<a>, <strong>, <em>, <blockquote>, <code>, <cite>, <abbr>, <acronym>) — matches what the WP comment form permits. Other tags are stripped.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'content' => ['type' => 'string'], 'author' => ['type' => 'string'], 'author_email' => ['type' => 'string']], 'required' => ['post_id', 'content']]],
			['name' => 'wp_delete_comment', 'description' => 'Delete a comment', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],
			['name' => 'wp_get_pending_comments', 'description' => 'Get comments awaiting moderation (status=hold). Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => 'Max comments to return (default 20, max 100)']]]],
			['name' => 'wp_approve_comment', 'description' => 'Approve a pending comment. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],
			['name' => 'wp_spam_comment', 'description' => 'Mark a comment as spam. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],
			['name' => 'wp_trash_comment', 'description' => 'Move a comment to trash. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_comments', 'wp_create_comment', 'wp_delete_comment', 'wp_get_pending_comments',
			'wp_approve_comment', 'wp_spam_comment', 'wp_trash_comment',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_comments':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list comments.');
				}
				$comment_args = ['number' => min(intval($args['per_page'] ?? 10), 100)];
				if (!empty($args['post_id'])) $comment_args['post_id'] = intval($args['post_id']);
				if (!empty($args['status'])) {
					$requested_comment_status = sanitize_text_field($args['status']);

					

					if (!in_array($requested_comment_status, ['approve'], true)
						&& !current_user_can('moderate_comments')) {
						throw new \Exception('You do not have permission to list ' . esc_html($requested_comment_status) . ' comments.');
					}
					$comment_args['status'] = $requested_comment_status;
				}
				$comments = get_comments($comment_args);
				return array_map(function($c) {
					return [
						'id' => $c->comment_ID,
						'post_id' => $c->comment_post_ID,
						'author' => $c->comment_author,
						'content' => $c->comment_content,
						'date' => $c->comment_date,
						'status' => $c->comment_approved,
					];
				}, $comments);

			case 'wp_create_comment':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to create comments via the MCP API.');
				}
				$comment_post_id = intval($args['post_id']);
				$comment_target_post = $comment_post_id > 0 ? get_post($comment_post_id) : null;
				if (!$comment_target_post) throw new \Exception('Post not found.');

				if ('open' !== $comment_target_post->comment_status
					&& !current_user_can('edit_post', $comment_post_id)) {
					throw new \Exception('Comments are closed on this post.');
				}

				
				
				$comment_data = [
					'comment_post_ID' => $comment_post_id,
					'comment_content' => wp_filter_kses($args['content']),
					'comment_author' => sanitize_text_field($args['author'] ?? 'Anonymous'),
					'comment_author_email' => sanitize_email($args['author_email'] ?? ''),
				];
				
				$comment_data['comment_approved'] = wp_allow_comment($comment_data);
				$comment_id = wp_insert_comment($comment_data);
				if (!$comment_id) throw new \Exception('Failed to create comment');
				$status = $comment_data['comment_approved'] === 1 ? 'approved' : 'pending moderation';
				return ['id' => $comment_id, 'message' => 'Comment created (' . $status . ')'];

			case 'wp_delete_comment':
				$comment_id = intval($args['id']);
				if ($comment_id <= 0 || !get_comment($comment_id)) throw new \Exception('Comment not found.');

				if (!current_user_can('edit_comment', $comment_id)) {
					throw new \Exception('You do not have permission to delete this comment.');
				}
				$force = !empty($args['force']);
				$result = wp_delete_comment($comment_id, $force);
				if (!$result) throw new \Exception('Failed to delete comment');
				return ['message' => 'Comment deleted successfully'];

			case 'wp_get_pending_comments':
				if (!current_user_can('moderate_comments')) {
					throw new \Exception('moderate_comments capability required.');
				}
				$limit = min(intval($args['limit'] ?? 20), 100);
				$get_args = ['status' => 'hold', 'number' => $limit];
				if (!empty($args['post_id'])) {
					$get_args['post_id'] = intval($args['post_id']);
				}
				$comments = get_comments($get_args);
				return array_map(function($c) {
					return [
						'id' => (int) $c->comment_ID,
						'post_id' => (int) $c->comment_post_ID,
						'post_title' => get_the_title($c->comment_post_ID),
						'author' => $c->comment_author,
						'author_email_redacted' => $c->comment_author_email ? substr($c->comment_author_email, 0, 2) . '***@***' : '',
						'content' => wp_strip_all_tags($c->comment_content),
						'status' => 'pending',
						'date' => $c->comment_date,
					];
				}, $comments);

			case 'wp_approve_comment':
				if (!current_user_can('moderate_comments')) {
					throw new \Exception('moderate_comments capability required.');
				}
				$comment_id = intval($args['comment_id']);
				$result = wp_set_comment_status($comment_id, 'approve');
				if (!$result) throw new \Exception('Failed to approve comment.');
				return ['comment_id' => $comment_id, 'new_status' => 'approved'];

			case 'wp_spam_comment':
				if (!current_user_can('moderate_comments')) {
					throw new \Exception('moderate_comments capability required.');
				}
				$comment_id = intval($args['comment_id']);
				$result = wp_set_comment_status($comment_id, 'spam');
				if (!$result) throw new \Exception('Failed to mark comment as spam.');
				return ['comment_id' => $comment_id, 'new_status' => 'spam'];

			case 'wp_trash_comment':
				if (!current_user_can('moderate_comments')) {
					throw new \Exception('moderate_comments capability required.');
				}
				$comment_id = intval($args['comment_id']);
				$result = wp_trash_comment($comment_id);
				if (!$result) throw new \Exception('Failed to trash comment.');
				return ['comment_id' => $comment_id, 'new_status' => 'trash'];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
