<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Support {

	public static function resolve_post_id_arg( array $args ): int {
		return intval($args['post_id'] ?? $args['id'] ?? 0);
	}

	public static function build_update_response( int $post_id, array $args, array $data, string $message ): array {
		$saved_post = get_post($post_id);
		$saved_fields = [];
		$modified_by_wp = [];
		
		$map = [
			'title'          => ['post_title', 'string', false],
			'content'        => ['post_content', 'string', false],
			'status'         => ['post_status', 'string', true],
			'excerpt'        => ['post_excerpt', 'string', false],
			'post_author'    => ['post_author', 'int', true],
			'menu_order'     => ['menu_order', 'int', true],
			'post_parent'    => ['post_parent', 'int', true],
			'password'       => ['post_password', 'string', true],
			'comment_status' => ['comment_status', 'string', true],
			'ping_status'    => ['ping_status', 'string', true],
		];
		foreach ($map as $arg_key => [$prop, $type, $diff_check]) {
			if (!array_key_exists($arg_key, $args)) continue;
			$stored = $saved_post->{$prop} ?? '';
			$stored = $type === 'int' ? (int) $stored : (string) $stored;
			$saved_fields[$arg_key] = $stored;
			if (!$diff_check) continue;
			if (!array_key_exists($prop, $data)) continue;
			$requested_effective = $type === 'int' ? (int) $data[$prop] : (string) $data[$prop];
			if ($stored !== $requested_effective) {
				$modified_by_wp[$arg_key] = [
					'requested' => $requested_effective,
					'actual'    => $stored,
				];
			}
		}
		$response = [
			'id'           => $post_id,
			'saved_fields' => $saved_fields,
			'message'      => $message,
		];
		if (!empty($modified_by_wp)) {
			$response['modified_by_wp'] = $modified_by_wp;
		}
		return $response;
	}

	public static function replace_in_post_content( int $post_id, array $args, string $noun ): array {

		
		if (!current_user_can('edit_post', $post_id)) {
			throw new \Exception('You do not have permission to edit this ' . esc_html($noun) . '.');
		}
		if (!isset($args['find']) || !is_string($args['find']) || $args['find'] === '') {
			throw new \Exception('find must be a non-empty string.');
		}
		if (!array_key_exists('replace', $args) || !is_string($args['replace'])) {
			throw new \Exception('replace must be a string (empty string deletes the matched text).');
		}
		$find = $args['find'];
		$replace = $args['replace'];

		$content = (string) get_post($post_id)->post_content;
		$occurrences = substr_count($content, $find);
		if (array_key_exists('expected_count', $args) && intval($args['expected_count']) !== $occurrences) {
			throw new \Exception(sprintf('expected_count is %d but %d occurrence(s) found; content unchanged.', intval($args['expected_count']), $occurrences));
		}
		if (!empty($args['dry_run'])) {
			return [
				'id' => $post_id,
				'dry_run' => true,
				'occurrences' => $occurrences,
				'content_length' => strlen($content),
				'message' => sprintf('Dry run: %d occurrence(s) found; nothing written.', $occurrences),
			];
		}
		if ($occurrences === 0) {
			throw new \Exception('find string not found in ' . esc_html($noun) . ' content; nothing to replace. Use dry_run=true to probe safely.');
		}
		if ($find === $replace) {
			throw new \Exception('find and replace are identical; nothing to do.');
		}
		$new_content = str_replace($find, $replace, $content);

		
		$result = wp_update_post(['ID' => $post_id, 'post_content' => wp_slash($new_content)], true);
		if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
		$stored = (string) get_post($post_id)->post_content;
		$verified = ($stored === $new_content);
		$response = [
			'id' => $post_id,
			'occurrences' => $occurrences,
			'replaced' => $occurrences,
			'verified' => $verified,
			'content_length_before' => strlen($content),
			'content_length_after' => strlen($stored),
			'message' => sprintf('%s content updated: %d occurrence(s) replaced.', ucfirst($noun), $occurrences),
		];
		if (!$verified) {
			$response['modified_by_wp'] = [
				'content' => 'Stored content differs from the computed replacement (sanitization or a content filter modified it on save). Re-read the ' . $noun . ' to inspect the stored result.',
			];
		}
		return $response;
	}
}
