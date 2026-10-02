<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Revisions implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_post_revisions', 'description' => 'Get a post\'s revision history, paginated. Returns {total, offset, limit, returned, has_more, revisions[]}; each row carries revision_id, author, date, word_count, content_length, differs_from_parent, and elementor_data_length so revisions can be told apart without fetching each one. A revision_id is a valid post_id for the meta tools, so wp_get_post_meta(post_id=<revision_id>, key=_elementor_data) recovers a builder page\'s prior state: the practical route when wp_restore_revision is not enough, since it restores post_content while a builder page lives in meta.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => 'Max revisions to return (default 20, max 100)'], 'offset' => ['type' => 'integer', 'description' => 'Skip this many revisions before returning results. Use with limit to page through a long history.']], 'required' => ['post_id']]],
			['name' => 'wp_restore_revision', 'description' => 'Restore a post to a specific revision. The current post content becomes the previous revision (so it can still be reverted again). Requires edit_post capability on the parent post.', 'inputSchema' => ['type' => 'object', 'properties' => ['revision_id' => ['type' => 'integer']], 'required' => ['revision_id']]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_get_post_revisions' === $name || 'wp_restore_revision' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_post_revisions':
				
				$post_id = intval($args['post_id'] ?? $args['id'] ?? 0);
				if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
				if (!get_post($post_id)) throw new \Exception('Post not found.');

				if (!current_user_can('read_post', $post_id)) {
					throw new \Exception('You do not have permission to read revisions on this post.');
				}
				$limit  = max(1, min(intval($args['limit'] ?? 20), 100));
				$offset = max(0, intval($args['offset'] ?? 0));

				

				

				$revisions = wp_get_post_revisions($post_id, [
					'posts_per_page' => $limit,
					'offset'         => $offset,
				]);

				
				
				$all_revision_ids = wp_get_post_revisions($post_id, [
					'posts_per_page' => -1,
					'fields'         => 'ids',
				]);
				$total = is_array($all_revision_ids) ? count($all_revision_ids) : 0;

				$parent_content = (string) get_post_field('post_content', $post_id);
				$rows = array_map(function($r) use ($parent_content) {
					$content = (string) $r->post_content;

					

					$elementor = get_post_meta($r->ID, '_elementor_data', true);
					return [
						'revision_id'         => (int) $r->ID,
						'parent_id'           => (int) $r->post_parent,
						'author_id'           => (int) $r->post_author,
						'author_name'         => get_the_author_meta('display_name', $r->post_author),
						'date'                => $r->post_date,
						'title'               => $r->post_title,
						'word_count'          => str_word_count(wp_strip_all_tags($content)),
						'content_length'      => strlen($content),
						'differs_from_parent' => ($content !== $parent_content),
						'elementor_data_length' => is_string($elementor) ? strlen($elementor) : null,
					];
				}, array_values($revisions));

				return [
					'post_id'   => $post_id,
					'total'     => $total,
					'offset'    => $offset,
					'limit'     => $limit,
					'returned'  => count($rows),
					'has_more'  => ($offset + count($rows)) < $total,
					'revisions' => $rows,
					'note'      => 'A revision_id is a valid post_id for the meta tools, so wp_get_post_meta(post_id=<revision_id>, key=_elementor_data) returns that revision\'s builder data. That is the practical route for recovering a page built with Elementor, since wp_restore_revision restores post_content and a builder page\'s real state lives in meta.',
				];

			case 'wp_restore_revision':
				$revision_id = intval($args['revision_id'] ?? 0);
				if ($revision_id <= 0) throw new \Exception('revision_id is required.');
				$revision = wp_get_post_revision($revision_id);
				if (!$revision) throw new \Exception('Revision not found.');
				if (!current_user_can('edit_post', $revision->post_parent)) {
					throw new \Exception('edit_post capability required for the parent post.');
				}
				$result = wp_restore_post_revision($revision_id);
				if (!$result) throw new \Exception('Failed to restore revision.');
				return [
					'success'   => true,
					'parent_id' => (int) $revision->post_parent,
					'restored_revision_id' => $revision_id,
				];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
