<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_media', 'description' => 'List media library attachments, paginated. Returns {total, page, per_page, pages, returned, has_more, media[]}; each row has id, title, url, mime_type, date, and alt (also exposed as alt_text — same value, both keys present). Use has_more or total to know when to stop paging. Filter by mime_type to narrow to images / videos / audio / documents.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Items per page (default 10, max 100)'], 'page' => ['type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.'], 'mime_type' => ['type' => 'string', 'description' => 'Filter by mime type prefix or full type (image, video, audio, application/pdf)']]]],
			['name' => 'wp_get_media_item', 'description' => 'Get single media item by ID', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'wp_upload_media_from_url', 'description' => 'Download an image from a public HTTPS URL and add it to the WordPress media library. Use this when you have an image URL (Unsplash, Pexels, client asset, etc) that needs to become a library attachment — for example before setting it as a featured image. Returns the new attachment ID.', 'inputSchema' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'Public HTTPS URL of the image to download'], 'filename' => ['type' => 'string', 'description' => 'Optional filename (with extension). Derived from URL if omitted.'], 'alt_text' => ['type' => 'string', 'description' => 'Alt text for accessibility and SEO'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['url']]],
			['name' => 'wp_upload_media', 'description' => 'Upload an image to the media library from base64-encoded bytes. Use this for AI-generated images or pasted screenshots where you have raw bytes rather than a URL. For images already hosted somewhere, prefer wp_upload_media_from_url.', 'inputSchema' => ['type' => 'object', 'properties' => ['filename' => ['type' => 'string', 'description' => 'Filename with extension (e.g. hero.jpg)'], 'content_base64' => ['type' => 'string', 'description' => 'Base64-encoded file bytes'], 'alt_text' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['filename', 'content_base64']]],
			['name' => 'wp_set_featured_image', 'description' => 'Set or replace the featured image on a post or page. Accepts EITHER an existing media_id from wp_get_media, OR an image_url that will be downloaded into the library first. Pass media_id=0 to remove the featured image.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Post or page ID'], 'media_id' => ['type' => 'integer', 'description' => 'Existing attachment ID (use 0 to remove the featured image)'], 'image_url' => ['type' => 'string', 'description' => 'Public HTTPS image URL to download and use instead of media_id'], 'alt_text' => ['type' => 'string', 'description' => 'Alt text applied when image_url is provided']], 'required' => ['post_id']]],
			['name' => 'wp_update_media', 'description' => 'Update metadata on an existing media attachment: alt text, caption, title, description. Great for adding SEO-friendly alt text to images already in the library.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'alt_text' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string']], 'required' => ['id']]],
			['name' => 'wp_delete_media', 'description' => 'Delete media item', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],
			['name' => 'wp_count_media', 'description' => 'Get media counts by type', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_media', 'wp_get_media_item', 'wp_upload_media_from_url', 'wp_upload_media',
			'wp_set_featured_image', 'wp_update_media', 'wp_delete_media', 'wp_count_media',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_media':
				if (!current_user_can('upload_files')) {
					throw new \Exception('You do not have permission to view the media library.');
				}
				$media_per_page = max(1, min(intval($args['per_page'] ?? 10), 100));
				$media_page     = max(1, intval($args['page'] ?? 1));
				$media_args = [
					'post_type'   => 'attachment',
					'post_status' => 'inherit',

					
					
					'numberposts' => $media_per_page,
					'offset'      => ($media_page - 1) * $media_per_page,
				];
				if (!empty($args['mime_type'])) $media_args['post_mime_type'] = sanitize_text_field($args['mime_type']);
				$media = get_posts($media_args);

				
				$media_total_ids = get_posts(array_merge($media_args, [
					'numberposts' => -1,
					'offset'      => 0,
					'fields'      => 'ids',
				]));
				$media_total = is_array($media_total_ids) ? count($media_total_ids) : 0;

				$media_rows = array_map(function($m) {
					$alt = get_post_meta($m->ID, '_wp_attachment_image_alt', true);
					return [
						'id'        => $m->ID,
						'title'     => $m->post_title,
						'url'       => wp_get_attachment_url($m->ID),
						'mime_type' => $m->post_mime_type,
						'date'      => $m->post_date,

						

						
						'alt'       => is_string($alt) ? $alt : '',
						'alt_text'  => is_string($alt) ? $alt : '',
					];
				}, $media);

				return [
					'total'    => $media_total,
					'page'     => $media_page,
					'per_page' => $media_per_page,
					'pages'    => (int) ceil($media_total / $media_per_page),
					'returned' => count($media_rows),
					'has_more' => (($media_page - 1) * $media_per_page + count($media_rows)) < $media_total,
					'media'    => $media_rows,
				];

			case 'wp_get_media_item':
				$media = get_post(self::resolve_post_id_arg($args));
				if (!$media || $media->post_type !== 'attachment') throw new \Exception('Media not found');
				if (!current_user_can('read_post', $media->ID)) {
					throw new \Exception('You do not have permission to read this media item.');
				}
				return [
					'id' => $media->ID,
					'title' => $media->post_title,
					'url' => wp_get_attachment_url($media->ID),
					'mime_type' => $media->post_mime_type,
					'alt' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
					'caption' => $media->post_excerpt,
					'description' => $media->post_content,
				];

			case 'wp_upload_media_from_url':
				if (!current_user_can('upload_files')) {
					throw new \Exception('You do not have permission to upload files.');
				}
				$url = isset($args['url']) ? esc_url_raw(trim($args['url'])) : '';
				if (empty($url)) throw new \Exception('A url is required.');
				$attachment_id = Media_Support::sideload_image_from_url(
					$url,
					isset($args['filename']) ? sanitize_file_name($args['filename']) : '',
					isset($args['title']) ? sanitize_text_field($args['title']) : '',
					isset($args['caption']) ? sanitize_text_field($args['caption']) : '',
					isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
				);
				return [
					'id' => $attachment_id,
					'url' => wp_get_attachment_url($attachment_id),
					'message' => 'Image uploaded to media library.',
				];

			case 'wp_upload_media':
				if (!current_user_can('upload_files')) {
					throw new \Exception('You do not have permission to upload files.');
				}
				$filename = isset($args['filename']) ? sanitize_file_name($args['filename']) : '';
				$b64      = isset($args['content_base64']) ? (string) $args['content_base64'] : '';
				if (empty($filename) || empty($b64)) {
					throw new \Exception('filename and content_base64 are required.');
				}
				
				if (strpos($b64, 'base64,') !== false) {
					$b64 = substr($b64, strpos($b64, 'base64,') + 7);
				}
				$bytes = base64_decode($b64, true);
				if ($bytes === false) throw new \Exception('content_base64 is not valid base64.');
				$attachment_id = Media_Support::sideload_image_from_bytes(
					$bytes,
					$filename,
					isset($args['title']) ? sanitize_text_field($args['title']) : '',
					isset($args['caption']) ? sanitize_text_field($args['caption']) : '',
					isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
				);
				return [
					'id' => $attachment_id,
					'url' => wp_get_attachment_url($attachment_id),
					'message' => 'Image uploaded to media library.',
				];

			case 'wp_set_featured_image':
				$post_id = intval($args['post_id'] ?? 0);
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit this post.');
				}
				
				if (!empty($args['image_url'])) {
					if (!current_user_can('upload_files')) {
						throw new \Exception('You do not have permission to upload files.');
					}
					$media_id = Media_Support::sideload_image_from_url(
						esc_url_raw(trim($args['image_url'])),
						'',
						'',
						'',
						isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
					);
				} else {
					$media_id = isset($args['media_id']) ? intval($args['media_id']) : -1;
					if ($media_id < 0) throw new \Exception('Provide either media_id or image_url.');
				}
				Media_Support::apply_featured_media($post_id, $media_id);
				return [
					'post_id'  => $post_id,
					'media_id' => $media_id,
					'url'      => $media_id > 0 ? wp_get_attachment_url($media_id) : null,
					'message'  => $media_id > 0 ? 'Featured image set.' : 'Featured image removed.',
				];

			case 'wp_update_media':
				$media_id = self::resolve_post_id_arg($args);
				$media = $media_id > 0 ? get_post($media_id) : null;
				if (!$media || $media->post_type !== 'attachment') throw new \Exception('Media not found.');
				if (!current_user_can('edit_post', $media_id)) {
					throw new \Exception('You do not have permission to edit this media item.');
				}
				$update = ['ID' => $media_id];
				
				if (isset($args['title']) && $args['title'] !== '')       $update['post_title']   = sanitize_text_field($args['title']);
				if (isset($args['caption']) && $args['caption'] !== '')     $update['post_excerpt'] = sanitize_text_field($args['caption']);
				if (isset($args['description']) && $args['description'] !== '') $update['post_content'] = wp_kses_post($args['description']);
				if (count($update) > 1) {
					$res = wp_update_post($update, true);
					if (is_wp_error($res)) throw new \Exception(esc_html($res->get_error_message()));
				}
				if (isset($args['alt_text']) && $args['alt_text'] !== '') {
					update_post_meta($media_id, '_wp_attachment_image_alt', sanitize_text_field($args['alt_text']));
				}
				return ['id' => $media_id, 'message' => 'Media updated successfully'];

			case 'wp_delete_media':
				$media_id = self::resolve_post_id_arg($args);
				$existing_media = $media_id > 0 ? get_post($media_id) : null;
				if (!$existing_media || $existing_media->post_type !== 'attachment') throw new \Exception('Media not found.');
				if (!current_user_can('delete_post', $media_id)) {
					throw new \Exception('You do not have permission to delete this media item.');
				}
				$force = !empty($args['force']);
				$result = wp_delete_attachment($media_id, $force);
				if (!$result) throw new \Exception('Failed to delete media');
				return ['message' => 'Media deleted successfully'];

			case 'wp_count_media':
				if (!current_user_can('upload_files')) {
					throw new \Exception('You do not have permission to view media library counts.');
				}
				$counts = wp_count_attachments();
				return (array) $counts;
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function resolve_post_id_arg( array $args ): int {
		return intval( $args['post_id'] ?? $args['id'] ?? 0 );
	}
}
