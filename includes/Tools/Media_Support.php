<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Support {

	public static function apply_featured_media( $post_id, $media_id ) {
		if ($media_id <= 0) {
			delete_post_thumbnail($post_id);
			return;
		}
		$attachment = get_post($media_id);
		if (!$attachment || $attachment->post_type !== 'attachment') {
			throw new \Exception('Media attachment not found.');
		}
		$result = set_post_thumbnail($post_id, $media_id);
		if (!$result) throw new \Exception('Failed to set featured image.');
	}

	public static function sideload_image_from_url( $url, $filename, $title, $caption, $alt_text ) {
		$parts = wp_parse_url($url);
		if (empty($parts['scheme']) || empty($parts['host'])) {
			throw new \Exception('URL must include scheme and host.');
		}
		$scheme = strtolower($parts['scheme']);
		$host   = strtolower($parts['host']);
		$is_local_host = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
		if ($scheme !== 'https' && !($scheme === 'http' && $is_local_host)) {
			throw new \Exception('Only https:// URLs are allowed.');
		}
		
		if (!$is_local_host) {
			$ips = @gethostbynamel($host);
			if (empty($ips)) throw new \Exception('Could not resolve host.');
			foreach ($ips as $ip) {
				if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					throw new \Exception('URL resolves to a blocked address range.');
				}
			}
		}
		
		$response = wp_safe_remote_get($url, [
			'timeout'             => 10,
			'redirection'         => 3,
			'limit_response_size' => 20 * 1024 * 1024, 
		]);
		if (is_wp_error($response)) throw new \Exception(esc_html($response->get_error_message()));
		$code = wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) throw new \Exception('Download failed with HTTP ' . intval($code) . '.');
		$body = wp_remote_retrieve_body($response);
		if (empty($body)) throw new \Exception('Downloaded file is empty.');
		if (strlen($body) > 20 * 1024 * 1024) throw new \Exception('File exceeds 20 MB limit.');
		
		if (empty($filename)) {
			$path = isset($parts['path']) ? basename($parts['path']) : '';
			$filename = sanitize_file_name($path ?: 'download');
		}

		if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
			$content_type = wp_remote_retrieve_header($response, 'content-type');
			if ($content_type) {
				$content_type = strtolower(trim(explode(';', $content_type)[0]));
			}
			$mime_to_ext = [
				'image/jpeg' => 'jpg',
				'image/jpg'  => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
				'image/avif' => 'avif',
				'image/bmp'  => 'bmp',
			];
			if (isset($mime_to_ext[$content_type])) {
				$filename .= '.' . $mime_to_ext[$content_type];
			} else {
				throw new \Exception('Could not determine image type (Content-Type: ' . esc_html($content_type ?: 'unknown') . ').');
			}
		}
		return self::sideload_image_from_bytes($body, $filename, $title, $caption, $alt_text);
	}

	public static function sideload_image_from_bytes( $bytes, $filename, $title, $caption, $alt_text ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		if (empty($bytes))    throw new \Exception('No file contents provided.');
		if (empty($filename)) throw new \Exception('Filename is required.');
		if (strlen($bytes) > 20 * 1024 * 1024) throw new \Exception('File exceeds 20 MB limit.');

		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$blocked_ext = ['svg', 'svgz', 'html', 'htm', 'xml', 'js', 'php', 'phtml', 'phar', 'exe'];
		if (in_array($ext, $blocked_ext, true)) {
			throw new \Exception('File type .' . esc_html($ext) . ' is not allowed.');
		}

		$tmp = wp_tempnam($filename);
		if (!$tmp) throw new \Exception('Could not create temp file.');
		if (file_put_contents($tmp, $bytes) === false) {
			wp_delete_file($tmp);
			throw new \Exception('Could not write temp file.');
		}
		$check = wp_check_filetype_and_ext($tmp, $filename);
		if (empty($check['type']) || empty($check['ext'])) {
			wp_delete_file($tmp);
			throw new \Exception('File type could not be verified or is not permitted by WordPress.');
		}
		if (strpos($check['type'], 'image/') !== 0) {
			wp_delete_file($tmp);
			throw new \Exception('Only image uploads are supported here (got ' . esc_html($check['type']) . ').');
		}

		$file_array = [
			'name'     => $check['proper_filename'] ?: $filename,
			'tmp_name' => $tmp,
		];
		
		$attachment_id = media_handle_sideload($file_array, 0, $title ?: null);
		if (is_wp_error($attachment_id)) {
			wp_delete_file($tmp);
			throw new \Exception(esc_html($attachment_id->get_error_message()));
		}
		if (!empty($caption)) {
			wp_update_post(['ID' => $attachment_id, 'post_excerpt' => $caption]);
		}
		if (!empty($alt_text)) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
		}
		return (int) $attachment_id;
	}
}
