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

	public static function process_attachment_to_new( $source_id, array $opts ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$path = get_attached_file( $source_id );
		if ( ! $path || ! file_exists( $path ) ) {
			throw new \Exception( 'Source image file is not available on disk.' );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			throw new \Exception( 'This server cannot process images: ' . esc_html( $editor->get_error_message() ) );
		}

		if ( ! empty( $opts['quality'] ) ) {
			$q = max( 1, min( 100, (int) $opts['quality'] ) );
			$editor->set_quality( $q );
		}

		
		
		$size       = $editor->get_size();
		$src_w      = is_array( $size ) ? (int) ( $size['width'] ?? 0 ) : 0;
		$src_h      = is_array( $size ) ? (int) ( $size['height'] ?? 0 ) : 0;
		$max_w      = (int) $opts['max_width'];
		$max_h      = (int) $opts['max_height'];
		$crop       = ! empty( $opts['crop'] );
		$did_resize = false;
		if ( $max_w > 0 || $max_h > 0 ) {
			
			$target_w = $max_w > 0 ? min( $max_w, $src_w ?: $max_w ) : null;
			$target_h = $max_h > 0 ? min( $max_h, $src_h ?: $max_h ) : null;
			
			$do_crop  = ( $crop && $target_w && $target_h );
			$resized  = $editor->resize( $target_w, $target_h, $do_crop );
			if ( is_wp_error( $resized ) ) {
				throw new \Exception( 'Resize failed: ' . esc_html( $resized->get_error_message() ) );
			}
			$did_resize = true;
		}

		$format = strtolower( (string) $opts['format'] );
		$fmt_to_mime = array(
			'webp' => 'image/webp',
			'jpeg' => 'image/jpeg',
			'jpg'  => 'image/jpeg',
			'png'  => 'image/png',
		);
		$out_mime = '';
		$out_ext  = '';
		if ( '' !== $format ) {
			if ( ! isset( $fmt_to_mime[ $format ] ) ) {
				throw new \Exception( 'Unsupported target format: ' . esc_html( $format ) . '.' );
			}
			$out_mime = $fmt_to_mime[ $format ];
			$out_ext  = ( 'jpg' === $format ) ? 'jpg' : $format;
		} else {
			
			$src_ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$out_ext  = $src_ext ?: 'jpg';
			$out_mime = null; 
		}

		if ( ! $did_resize && '' === $format ) {
			throw new \Exception( 'Nothing to do: no resize dimensions and no format change.' );
		}

		$base    = pathinfo( $path, PATHINFO_FILENAME );
		$suffix  = $did_resize ? '-processed' : '-' . $out_ext;
		$tmp_name = wp_tempnam( $base . $suffix . '.' . $out_ext );
		if ( ! $tmp_name ) {
			throw new \Exception( 'Could not create a temp file for the processed image.' );
		}
		$saved = $editor->save( $tmp_name, $out_mime );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $tmp_name );
			throw new \Exception( 'Could not save the processed image (' . esc_html( $format ?: 'source format' ) . '): ' . esc_html( $saved->get_error_message() ) );
		}

		
		$saved_path = isset( $saved['path'] ) ? $saved['path'] : $tmp_name;
		$bytes      = file_get_contents( $saved_path );
		
		if ( $saved_path !== $tmp_name && file_exists( $tmp_name ) ) {
			wp_delete_file( $tmp_name );
		}
		wp_delete_file( $saved_path );
		if ( false === $bytes || '' === $bytes ) {
			throw new \Exception( 'Processed image was empty.' );
		}

		$src_post   = get_post( $source_id );
		$title      = '' !== (string) $opts['title']
			? (string) $opts['title']
			: trim( ( $src_post ? $src_post->post_title : $base ) . ' (processed)' );
		$alt        = '' !== (string) $opts['alt_text']
			? (string) $opts['alt_text']
			: (string) get_post_meta( $source_id, '_wp_attachment_image_alt', true );
		$new_name   = sanitize_file_name( $base . $suffix . '.' . $out_ext );

		
		return self::sideload_image_from_bytes( $bytes, $new_name, $title, '', $alt );
	}
}
