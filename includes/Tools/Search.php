<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_search', 'description' => 'Search all content. Pass snippet>0 to receive a content excerpt around each match (saves tokens vs. fetching each result with wp_get_page).', 'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'post_type' => ['type' => 'string'], 'per_page' => ['type' => 'integer', 'description' => 'Number of results (default 20, max 100)'], 'snippet' => ['type' => 'integer', 'description' => 'Snippet length in characters around the matched term (default 0 = off, recommended 160-240). When set, results include slug and snippet fields.']], 'required' => ['query']]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_search' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( 'wp_search' !== $name ) {
			throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
		}
		if (!current_user_can('read')) {
			throw new \Exception('You do not have permission to search.');
		}
		$query = sanitize_text_field($args['query']);
		$per_page = isset($args['per_page']) ? max(1, min(intval($args['per_page']), 100)) : 20;
		$snippet_len = isset($args['snippet']) ? max(0, min(intval($args['snippet']), 1000)) : 0;
		$search_args = [
			's' => $query,
			'post_type' => !empty($args['post_type']) ? sanitize_text_field($args['post_type']) : 'any',
			'numberposts' => $per_page,
		];
		$posts = get_posts($search_args);
		return array_map(function($p) use ($query, $snippet_len) {
			$row = ['id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type, 'url' => get_permalink($p)];
			if ($snippet_len > 0) {
				$row['slug'] = $p->post_name;
				$row['snippet'] = self::extract_snippet($p->post_content, $query, $snippet_len);
			}
			return $row;
		}, $posts);
	}

	private static function extract_snippet($content, $query, $len) {
		$text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes((string) $content))));
		if ($text === '') return '';
		$total = mb_strlen($text);
		if ($total <= $len) return $text;

		$needle = $query;
		$pos = ($needle !== '') ? mb_stripos($text, $needle) : false;
		if ($pos === false) {
			$first_word = strtok($query, ' ');
			if ($first_word !== false && $first_word !== '') {
				$pos = mb_stripos($text, $first_word);
			}
		}
		if ($pos === false) {
			return rtrim(mb_substr($text, 0, $len)) . '…';
		}

		$start = max(0, $pos - intval($len / 2));
		$start = min($start, max(0, $total - $len));
		$excerpt = mb_substr($text, $start, $len);
		if ($start > 0) $excerpt = '…' . ltrim($excerpt);
		if ($start + $len < $total) $excerpt = rtrim($excerpt) . '…';
		return $excerpt;
	}
}
