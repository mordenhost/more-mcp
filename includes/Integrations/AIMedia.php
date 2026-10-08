<?php
namespace More_MCP\Integrations;

use More_MCP\Platform\Url_Guard;
use More_MCP\Tools\Media_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMedia {

	const TOGGLE_KEY = 'allow_ai_media';

	const MAX_VISION_BYTES = 8388608;

	private static $capability_cache = array();

	public static function client_available() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}
		return ! function_exists( 'wp_supports_ai' ) || wp_supports_ai();
	}

	private static function supports( $kind ) {
		if ( ! self::client_available() ) {
			return false;
		}
		if ( isset( self::$capability_cache[ $kind ] ) ) {
			return self::$capability_cache[ $kind ];
		}
		$supported = false;
		try {
			$builder   = wp_ai_client_prompt( 'capability probe' );
			$supported = 'image' === $kind
				? (bool) $builder->is_supported_for_image_generation()
				: (bool) $builder->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			$supported = false;
		}
		self::$capability_cache[ $kind ] = $supported;
		return $supported;
	}

	public static function has_provider_configured() {
		return self::supports( 'image' ) || self::supports( 'text' );
	}

	public static function is_available() {
		if ( ! self::toggle_on() ) {
			return false;
		}
		return self::has_provider_configured();
	}

	private static function toggle_on() {
		$settings = get_option( 'more_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wp-ai-client' ),
			'capabilities' => array( 'ai_media' ),
			'kind'         => 'service',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		$tools = array();
		if ( self::supports( 'image' ) ) {
			$tools[] = array(
				'name'        => 'ai_generate_image',
				'description' => 'Generate an image from a text prompt using the AI provider connected to this site through the WordPress AI Client (Settings > Connectors), and optionally save it to the media library. COSTS MONEY per call against the site owner\'s provider account, and is disabled by default. Synchronous (no video). Returns the new attachment id + url when save=true, else raw base64. Requires manage_options and the "Allow AI media generation" admin toggle.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'       => array( 'type' => 'string', 'description' => 'What to generate.' ),
						'provider'     => array( 'type' => 'string', 'description' => 'Optional AI Client provider id to force (for example "openai" or "google"). Defaults to whichever connected provider supports image generation.' ),
						'aspect_ratio' => array( 'type' => 'string', 'enum' => array( '1:1', '3:4', '4:3', '9:16', '16:9' ), 'description' => 'Aspect ratio. Default 1:1.' ),
						'save'         => array( 'type' => 'boolean', 'description' => 'Save the generated image to the media library (default true). When false, returns base64 instead.' ),
						'title'        => array( 'type' => 'string', 'description' => 'Title for the saved attachment.' ),
						'alt_text'     => array( 'type' => 'string', 'description' => 'Alt text for the saved attachment.' ),
					),
					'required'   => array( 'prompt' ),
				),
			);
		}
		if ( self::supports( 'text' ) ) {
			$tools[] = array(
				'name'        => 'ai_generate_alt_text',
				'description' => 'Generate concise alt text for an existing image attachment using the vision-capable AI provider connected to this site through the WordPress AI Client, and optionally write it to the attachment. The image is sent to that provider. COSTS MONEY per call and is disabled by default. Returns the suggested alt text; writes it to the attachment when write=true. Requires manage_options and the "Allow AI media generation" admin toggle.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Image attachment ID to describe.' ),
						'provider' => array( 'type' => 'string', 'description' => 'Optional AI Client provider id to force. Defaults to whichever connected provider can describe images.' ),
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
		if ( 'ai_generate_image' === $name || 'ai_generate_alt_text' === $name ) {
			self::require_client();
		}
		if ( 'ai_generate_image' === $name ) {
			return self::generate_image( $args );
		}
		if ( 'ai_generate_alt_text' === $name ) {
			return self::generate_alt_text( $args );
		}
		throw new \Exception( 'Unknown AI media tool: ' . esc_html( $name ) );
	}

	private static function require_client() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new \Exception( 'AI media generation needs the WordPress AI Client (WordPress 7.0 or later).' );
		}
		if ( ! self::client_available() ) {
			throw new \Exception( 'AI features are switched off for this site, so AI media generation is unavailable.' );
		}
	}

	private static function with_provider( $builder, $args ) {
		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : '';
		return '' === $provider ? $builder : $builder->using_provider( $provider );
	}

	private static function fail( $error ) {
		throw new \Exception( 'AI request failed: ' . esc_html( $error->get_error_message() ) );
	}

	private static function generate_image( $args ) {
		$prompt = isset( $args['prompt'] ) ? trim( (string) $args['prompt'] ) : '';
		if ( '' === $prompt ) {
			throw new \Exception( 'prompt is required.' );
		}
		$save   = ! isset( $args['save'] ) || ! empty( $args['save'] );
		$aspect = isset( $args['aspect_ratio'] ) ? sanitize_text_field( $args['aspect_ratio'] ) : '1:1';

		$builder = self::with_provider( wp_ai_client_prompt( $prompt ), $args );
		$builder = $builder->as_output_media_aspect_ratio( $aspect ? $aspect : '1:1' );
		$file    = $builder->generate_image();
		if ( is_wp_error( $file ) ) {
			self::fail( $file );
		}

		$mime  = (string) $file->getMimeType();
		$bytes = self::file_bytes( $file );

		if ( ! $save ) {
			return array(
				'saved'        => false,
				'mime_type'    => $mime,
				'image_base64' => base64_encode( $bytes ),
				'message'      => 'Image generated (not saved). Pass save=true to add it to the media library.',
			);
		}

		$title    = isset( $args['title'] ) && '' !== trim( (string) $args['title'] )
			? sanitize_text_field( $args['title'] )
			: 'AI generated image';
		$alt      = isset( $args['alt_text'] ) ? sanitize_text_field( $args['alt_text'] ) : '';
		$filename = 'ai-' . gmdate( 'Ymd-His' ) . '.' . self::extension_for( $mime );
		$id       = Media_Support::sideload_image_from_bytes( $bytes, $filename, $title, '', $alt );

		return array(
			'saved'   => true,
			'id'      => (int) $id,
			'url'     => wp_get_attachment_url( $id ),
			'message' => 'AI-generated image saved to the media library.',
		);
	}

	private static function file_bytes( $file ) {
		$inline = $file->getBase64Data();
		if ( is_string( $inline ) && '' !== $inline ) {
			$bytes = base64_decode( $inline, true );
			if ( false === $bytes || '' === $bytes ) {
				throw new \Exception( 'The AI provider returned image data that was not valid base64.' );
			}
			return $bytes;
		}
		$url = $file->getUrl();
		if ( is_string( $url ) && '' !== $url ) {
			return self::download_bytes( $url );
		}
		throw new \Exception( 'The AI provider returned no image data.' );
	}

	private static function extension_for( $mime ) {
		$map = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		return isset( $map[ $mime ] ) ? $map[ $mime ] : 'png';
	}

	private static function download_bytes( $url ) {
		$ok = Url_Guard::validate_external_url( $url );
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
		$path = get_attached_file( $id );
		if ( ! $path || ! file_exists( $path ) ) {
			throw new \Exception( 'Attachment file is not available on disk.' );
		}
		if ( filesize( $path ) > self::MAX_VISION_BYTES ) {
			throw new \Exception( 'Attachment is larger than 8 MB; resize it before asking for alt text.' );
		}

		$builder = wp_ai_client_prompt( 'Write concise alt text (one sentence, no lead-in phrase) for this image.' )
			->with_file( $path, (string) $att->post_mime_type );
		$builder = self::with_provider( $builder, $args );
		$text    = $builder->generate_text();
		if ( is_wp_error( $text ) ) {
			self::fail( $text );
		}
		$text = trim( (string) $text );
		if ( '' === $text ) {
			throw new \Exception( 'The provider returned empty alt text.' );
		}

		$written = false;
		if ( ! empty( $args['write'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $text ) );
			$written = true;
		}
		return array(
			'id'       => $id,
			'alt_text' => $text,
			'written'  => $written,
			'message'  => $written ? 'Alt text generated and written to the attachment.' : 'Alt text suggested (not written). Pass write=true to save it.',
		);
	}
}
