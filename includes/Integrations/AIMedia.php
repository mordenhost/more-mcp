<?php
namespace More_MCP\Integrations;

use More_MCP\Platform\Registry;
use More_MCP\Tools\Media_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMedia {

	const TOGGLE_KEY = 'allow_ai_media';

	private static function image_providers() {
		return array( 'openai', 'google' );
	}

	private static function vision_providers() {
		return array( 'openai', 'google' );
	}

	public static function is_available() {
		if ( ! self::toggle_on() ) {
			return false;
		}
		return null !== self::first_configured( self::image_providers() )
			|| null !== self::first_configured( self::vision_providers() );
	}

	private static function toggle_on() {
		$settings = get_option( 'more_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'openai', 'google' ),
			'capabilities' => array( 'ai_media' ),
			'kind'         => 'service',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		$tools = array();
		if ( null !== self::first_configured( self::image_providers() ) ) {
			$tools[] = array(
				'name'        => 'ai_generate_image',
				'description' => 'Generate an image from a text prompt using a configured AI provider (OpenAI or Google), and optionally save it to the media library. COSTS MONEY per call against the site owner\'s provider account, and is disabled by default. Synchronous (no video). Returns the new attachment id + url when save=true, else raw base64. Requires manage_options and the "Allow AI media generation" admin toggle.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'   => array( 'type' => 'string', 'description' => 'What to generate.' ),
						'provider' => array( 'type' => 'string', 'enum' => array( 'openai', 'google' ), 'description' => 'Which configured provider to use. Defaults to the first configured one.' ),
						'size'     => array( 'type' => 'string', 'description' => 'OpenAI: 1024x1024 (default), 1536x1024, 1024x1536. Google Imagen uses aspect_ratio instead.' ),
						'aspect_ratio' => array( 'type' => 'string', 'enum' => array( '1:1', '3:4', '4:3', '9:16', '16:9' ), 'description' => 'Google Imagen aspect ratio. Default 1:1.' ),
						'save'     => array( 'type' => 'boolean', 'description' => 'Save the generated image to the media library (default true). When false, returns base64 instead.' ),
						'title'    => array( 'type' => 'string', 'description' => 'Title for the saved attachment.' ),
						'alt_text' => array( 'type' => 'string', 'description' => 'Alt text for the saved attachment.' ),
					),
					'required'   => array( 'prompt' ),
				),
			);
		}
		if ( null !== self::first_configured( self::vision_providers() ) ) {
			$tools[] = array(
				'name'        => 'ai_generate_alt_text',
				'description' => 'Generate concise alt text for an existing image attachment using a configured vision-capable AI provider, and optionally write it to the attachment. COSTS MONEY per call and is disabled by default. Returns the suggested alt text; writes it to the attachment when write=true. Requires manage_options and the "Allow AI media generation" admin toggle.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Image attachment ID to describe.' ),
						'provider' => array( 'type' => 'string', 'enum' => array( 'openai', 'google' ), 'description' => 'Which configured provider to use. Defaults to the first configured one.' ),
						'write'    => array( 'type' => 'boolean', 'description' => 'Write the generated alt text to the attachment (default false — returns a suggestion only).' ),
					),
					'required'   => array( 'id' ),
				),
			);
		}
		return $tools;
	}

	public static function execute_tool( $name, $args ) {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use AI media generation.' );
		}
		if ( ! self::toggle_on() ) {
			throw new \Exception( 'AI media generation is disabled. Enable "Allow AI media generation" under More MCP settings first.' );
		}
		if ( 'ai_generate_image' === $name ) {
			return self::generate_image( $args );
		}
		if ( 'ai_generate_alt_text' === $name ) {
			return self::generate_alt_text( $args );
		}
		throw new \Exception( 'Unknown AI media tool: ' . esc_html( $name ) );
	}

	
	private static function platform_config( $platform_id ) {
		$settings = get_option( 'more_mcp_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['platforms'] ) || ! is_array( $settings['platforms'] ) ) {
			return null;
		}
		foreach ( $settings['platforms'] as $cfg ) {
			if ( is_array( $cfg ) && ( $cfg['platform'] ?? '' ) === $platform_id
				&& ! empty( $cfg['enabled'] ) && ! empty( $cfg['api_key'] ) ) {
				return $cfg;
			}
		}
		return null;
	}

	private static function first_configured( array $ids ) {
		foreach ( $ids as $id ) {
			if ( null !== self::platform_config( $id ) ) {
				return $id;
			}
		}
		return null;
	}

	private static function resolve_provider( $args, array $candidates ) {
		$requested = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : '';
		if ( '' !== $requested ) {
			if ( null === self::platform_config( $requested ) ) {
				throw new \Exception( 'Provider "' . esc_html( $requested ) . '" is not configured or not enabled in the AI Providers panel.' );
			}
			return $requested;
		}
		$first = self::first_configured( $candidates );
		if ( null === $first ) {
			throw new \Exception( 'No AI provider is configured for this operation.' );
		}
		return $first;
	}

	private static function post_json( $url, $headers, $body ) {
		$ok = Registry::validate_external_url( $url );
		if ( is_wp_error( $ok ) ) {
			throw new \Exception( esc_html( $ok->get_error_message() ) );
		}
		$response = wp_safe_remote_post( $url, array(
			'timeout' => 60, 
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $json ) && isset( $json['error']['message'] ) ? $json['error']['message'] : ( 'HTTP ' . $code );
			throw new \Exception( 'AI provider request failed: ' . esc_html( $msg ) );
		}
		if ( ! is_array( $json ) ) {
			throw new \Exception( 'AI provider returned an unreadable response.' );
		}
		return $json;
	}

	private static function generate_image( $args ) {
		$prompt = isset( $args['prompt'] ) ? trim( (string) $args['prompt'] ) : '';
		if ( '' === $prompt ) {
			throw new \Exception( 'prompt is required.' );
		}
		$provider = self::resolve_provider( $args, self::image_providers() );
		$cfg      = self::platform_config( $provider );
		$save     = ! isset( $args['save'] ) || ! empty( $args['save'] );

		if ( 'openai' === $provider ) {
			$bytes = self::openai_image_bytes( $cfg, $prompt, isset( $args['size'] ) ? sanitize_text_field( $args['size'] ) : '1024x1024' );
		} else {
			$bytes = self::google_image_bytes( $cfg, $prompt, isset( $args['aspect_ratio'] ) ? sanitize_text_field( $args['aspect_ratio'] ) : '1:1' );
		}

		if ( ! $save ) {
			return array(
				'provider'      => $provider,
				'saved'         => false,
				'image_base64'  => base64_encode( $bytes ),
				'message'       => 'Image generated (not saved). Pass save=true to add it to the media library.',
			);
		}

		$title = isset( $args['title'] ) && '' !== trim( (string) $args['title'] )
			? sanitize_text_field( $args['title'] )
			: 'AI generated image';
		$alt = isset( $args['alt_text'] ) ? sanitize_text_field( $args['alt_text'] ) : '';
		$filename = 'ai-' . gmdate( 'Ymd-His' ) . '.png';
		$id = Media_Support::sideload_image_from_bytes( $bytes, $filename, $title, '', $alt );

		return array(
			'provider' => $provider,
			'saved'    => true,
			'id'       => (int) $id,
			'url'      => wp_get_attachment_url( $id ),
			'message'  => 'AI-generated image saved to the media library.',
		);
	}

	private static function openai_image_bytes( $cfg, $prompt, $size ) {
		$endpoint = Registry::get_endpoint( 'openai', $cfg );
		$headers  = Registry::get_auth_headers( 'openai', $cfg );
		$model    = ! empty( $cfg['model'] ) ? $cfg['model'] : 'gpt-image-1';
		$body     = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => $size ?: '1024x1024',
		);
		$json = self::post_json( $endpoint . '/v1/images/generations', $headers, $body );
		$b64  = $json['data'][0]['b64_json'] ?? '';
		if ( '' === $b64 ) {
			
			$url = $json['data'][0]['url'] ?? '';
			if ( '' !== $url ) {
				return self::download_via_sideloader_bytes( $url );
			}
			throw new \Exception( 'OpenAI returned no image data.' );
		}
		$bytes = base64_decode( $b64, true );
		if ( false === $bytes || '' === $bytes ) {
			throw new \Exception( 'OpenAI image data was not valid base64.' );
		}
		return $bytes;
	}

	private static function google_image_bytes( $cfg, $prompt, $aspect ) {
		$endpoint = Registry::get_endpoint( 'google', $cfg );
		$model    = ! empty( $cfg['model'] ) ? $cfg['model'] : 'imagen-3.0-generate-001';

		$headers  = Registry::get_auth_headers( 'google', $cfg );
		$headers['x-goog-api-key'] = $cfg['api_key']; 
		$body = array(
			'instances'  => array( array( 'prompt' => $prompt ) ),
			'parameters' => array( 'sampleCount' => 1, 'aspectRatio' => $aspect ?: '1:1' ),
		);
		$json = self::post_json( $endpoint . '/models/' . rawurlencode( $model ) . ':predict', $headers, $body );
		$b64  = $json['predictions'][0]['bytesBase64Encoded'] ?? '';
		if ( '' === $b64 ) {
			throw new \Exception( 'Google Imagen returned no image data.' );
		}
		$bytes = base64_decode( $b64, true );
		if ( false === $bytes || '' === $bytes ) {
			throw new \Exception( 'Google image data was not valid base64.' );
		}
		return $bytes;
	}

	private static function download_via_sideloader_bytes( $url ) {
		$ok = Registry::validate_external_url( $url );
		if ( is_wp_error( $ok ) ) {
			throw new \Exception( esc_html( $ok->get_error_message() ) );
		}
		$response = wp_safe_remote_get( $url, array( 'timeout' => 30, 'limit_response_size' => 20 * 1024 * 1024 ) );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}
		$bytes = wp_remote_retrieve_body( $response );
		if ( '' === $bytes ) {
			throw new \Exception( 'Downloaded image was empty.' );
		}
		return $bytes;
	}

	private static function generate_alt_text( $args ) {
		$id  = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$att = $id > 0 ? get_post( $id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type ) {
			throw new \Exception( 'Media not found.' );
		}
		if ( 0 !== strpos( (string) $att->post_mime_type, 'image/' ) ) {
			throw new \Exception( 'Attachment ' . intval( $id ) . ' is not an image.' );
		}
		$image_url = wp_get_attachment_url( $id );
		if ( ! $image_url ) {
			throw new \Exception( 'Could not resolve the attachment URL.' );
		}
		$provider = self::resolve_provider( $args, self::vision_providers() );
		$cfg      = self::platform_config( $provider );

		if ( 'openai' === $provider ) {
			$text = self::openai_alt_text( $cfg, $image_url );
		} else {
			$text = self::google_alt_text( $cfg, $id, $att->post_mime_type );
		}
		$text = trim( $text );
		if ( '' === $text ) {
			throw new \Exception( 'The provider returned empty alt text.' );
		}

		$written = false;
		if ( ! empty( $args['write'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $text ) );
			$written = true;
		}
		return array(
			'provider' => $provider,
			'id'       => $id,
			'alt_text' => $text,
			'written'  => $written,
			'message'  => $written ? 'Alt text generated and written to the attachment.' : 'Alt text suggested (not written). Pass write=true to save it.',
		);
	}

	private static function openai_alt_text( $cfg, $image_url ) {
		$endpoint = Registry::get_endpoint( 'openai', $cfg );
		$headers  = Registry::get_auth_headers( 'openai', $cfg );
		$model    = ! empty( $cfg['model'] ) ? $cfg['model'] : 'gpt-4o';
		$body = array(
			'model'      => $model,
			'max_tokens' => 100,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => 'Write concise alt text (one sentence, no lead-in phrase) for this image.' ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => $image_url ) ),
					),
				),
			),
		);
		$json = self::post_json( $endpoint . '/v1/chat/completions', $headers, $body );
		return (string) ( $json['choices'][0]['message']['content'] ?? '' );
	}

	private static function google_alt_text( $cfg, $attachment_id, $mime ) {
		$endpoint = Registry::get_endpoint( 'google', $cfg );
		$model    = ! empty( $cfg['model'] ) ? $cfg['model'] : 'gemini-2.0-flash';
		$headers  = Registry::get_auth_headers( 'google', $cfg );
		$headers['x-goog-api-key'] = $cfg['api_key'];
		
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			throw new \Exception( 'Attachment file is not available on disk for inline vision.' );
		}
		$data = base64_encode( (string) file_get_contents( $path ) );
		$body = array(
			'contents' => array( array( 'parts' => array(
				array( 'text' => 'Write concise alt text for this image in one sentence.' ),
				array( 'inline_data' => array( 'mime_type' => (string) $mime, 'data' => $data ) ),
			) ) ),
		);
		$json = self::post_json( $endpoint . '/models/' . rawurlencode( $model ) . ':generateContent', $headers, $body );
		return (string) ( $json['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}
}
