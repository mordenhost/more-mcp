<?php
namespace Mordenhost_MCP\Integrations;

use Mordenhost_MCP\Platform\Url_Guard;
use Mordenhost_MCP\Tools\Media_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIMedia {

	const TOGGLE_KEY = 'allow_ai_media';

	const MAX_VISION_BYTES = 8388608;

	const CAPTION_TONES = array(
		'neutral'   => 'neutral and factual',
		'formal'    => 'formal',
		'casual'    => 'casual and friendly',
		'editorial' => 'editorial, as in a magazine or news article',
	);

	const CAPTION_MAX_WORDS_DEFAULT = 20;
	const CAPTION_MAX_WORDS_MIN     = 3;
	const CAPTION_MAX_WORDS_MAX     = 40;

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
		$settings = get_option( 'mordenhost_mcp_settings', array() );
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
				'description' => 'Generate an image from a text prompt using the AI provider connected to this site through the WordPress AI Client (Settings > Connectors), and optionally save it to the media library. COSTS MONEY per call against the site owner\'s provider account, and is disabled by default. Synchronous (no video). Returns the new attachment id + url when save=true, else raw base64. Requires manage_options and the "AI media generation" switch (Mordenhost MCP > Access).',
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
				'description' => 'Generate concise alt text for an existing image attachment using the vision-capable AI provider connected to this site through the WordPress AI Client, and optionally write it to the attachment. The image is sent to that provider. COSTS MONEY per call and is disabled by default. Returns the suggested alt text; writes it to the attachment when write=true. Requires manage_options and the "AI media generation" switch (Mordenhost MCP > Access).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Image attachment ID to describe.' ),
						'provider' => array( 'type' => 'string', 'description' => 'Optional AI Client provider id to force. Defaults to whichever connected provider can describe images.' ),
						'write'    => array( 'type' => 'boolean', 'description' => 'Write the generated alt text to the attachment (default false — returns a suggestion only). Needs edit_post on the attachment; the previous alt text is returned as previous_alt_text and kept in change history.' ),
					),
					'required'   => array( 'id' ),
				),
			);
			$tools[] = array(
				'name'        => 'ai_generate_caption',
				'description' => 'Generate a short caption for an existing image attachment using the vision-capable AI provider connected to this site through the WordPress AI Client, and optionally save it as the attachment\'s caption. The image is sent to that provider. COSTS MONEY per call and is disabled by default. Returns caption plus previous_caption and new_caption. Saves only when write=true (needs edit_post on the attachment) and never replaces an existing caption unless overwrite=true; both are checked before the provider is called. When the attachment has stored attribution (for example a stock photo), credit holds a line such as "Photo by Jane Doe on Unsplash", built from that attribution rather than by the AI, and it is appended to the caption unless append_credit=false; you remain responsible for crediting the image wherever it is used. Requires manage_options and the "AI media generation" switch (Mordenhost MCP > Access).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => array( 'type' => 'integer', 'description' => 'Image attachment ID to caption.' ),
						'provider'      => array( 'type' => 'string', 'description' => 'Optional AI Client provider id to force. Defaults to whichever connected provider can describe images.' ),
						'write'         => array( 'type' => 'boolean', 'description' => 'Save the caption to the attachment (default false: returns a suggestion only).' ),
						'overwrite'     => array( 'type' => 'boolean', 'description' => 'With write=true, replace a caption the attachment already has (default false: such a write is refused before the provider is called).' ),
						'tone'          => array( 'type' => 'string', 'enum' => array_keys( self::CAPTION_TONES ), 'description' => 'Caption tone. Default neutral.' ),
						'max_words'     => array( 'type' => 'integer', 'minimum' => self::CAPTION_MAX_WORDS_MIN, 'maximum' => self::CAPTION_MAX_WORDS_MAX, 'description' => 'Longest caption in words, 3 to 40 (default 20; values outside are clamped). A longer reply is cut to this length and reported as truncated=true. The credit line does not count.' ),
						'append_credit' => array( 'type' => 'boolean', 'description' => 'Append the credit line from the stored attribution to the caption (default true). No effect when the attachment has no attribution.' ),
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
			throw new \Exception( 'AI media generation is disabled. The site owner can turn on the "AI media generation" switch under Mordenhost MCP > Access.' );
		}
		if ( in_array( $name, array( 'ai_generate_image', 'ai_generate_alt_text', 'ai_generate_caption' ), true ) ) {
			self::require_client();
		}
		if ( 'ai_generate_image' === $name ) {
			return self::generate_image( $args );
		}
		if ( 'ai_generate_alt_text' === $name ) {
			return self::generate_alt_text( $args );
		}
		if ( 'ai_generate_caption' === $name ) {
			return self::generate_caption( $args );
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

	
	private static function vision_source( $id, $purpose ) {
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
			throw new \Exception( 'Attachment is larger than 8 MB; resize it before asking for ' . esc_html( $purpose ) . '.' );
		}
		return array( $att, $path );
	}

	private static function flag( $args, $key ) {
		if ( ! isset( $args[ $key ] ) ) {
			return false;
		}
		$value = $args[ $key ];
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes' ), true );
	}

	private static function generate_alt_text( $args ) {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		list( $att, $path ) = self::vision_source( $id, 'alt text' );

		
		$write    = self::flag( $args, 'write' );
		$previous = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( $write && ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( 'You do not have permission to edit this media item.' );
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
		if ( $write ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $text ) );
			$written = true;
		}
		return array(
			'id'                => $id,
			'alt_text'          => $text,
			'previous_alt_text' => $previous,
			'written'           => $written,
			'message'           => $written ? 'Alt text generated and written to the attachment.' : 'Alt text suggested (not written). Pass write=true to save it.',
		);
	}

	
	private static function generate_caption( $args ) {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		list( $att, $path ) = self::vision_source( $id, 'a caption' );

		$tone = isset( $args['tone'] ) ? strtolower( trim( (string) $args['tone'] ) ) : '';
		$tone = '' === $tone ? 'neutral' : $tone;
		if ( ! isset( self::CAPTION_TONES[ $tone ] ) ) {
			throw new \Exception( 'tone must be one of: ' . esc_html( implode( ', ', array_keys( self::CAPTION_TONES ) ) ) . '.' );
		}
		$max_words = isset( $args['max_words'] ) && is_numeric( $args['max_words'] ) ? (int) $args['max_words'] : self::CAPTION_MAX_WORDS_DEFAULT;
		$max_words = max( self::CAPTION_MAX_WORDS_MIN, min( self::CAPTION_MAX_WORDS_MAX, $max_words ) );

		$write    = self::flag( $args, 'write' );
		$previous = isset( $att->post_excerpt ) ? (string) $att->post_excerpt : '';
		if ( $write ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				throw new \Exception( 'You do not have permission to edit this media item.' );
			}
			if ( '' !== trim( $previous ) && ! self::flag( $args, 'overwrite' ) ) {
				throw new \Exception( 'Attachment ' . intval( $id ) . ' already has a caption. Pass overwrite=true to replace it, or leave write off to get a suggestion.' );
			}
		}

		$prompt  = sprintf(
			'Write a caption for this image, to be shown under it on a web page. At most %d words, one or two sentences. Tone: %s. No lead-in such as "This image shows", no quotation marks, no hashtags, no emoji, and no photo credit.',
			$max_words,
			self::CAPTION_TONES[ $tone ]
		);
		$builder = wp_ai_client_prompt( $prompt )->with_file( $path, (string) $att->post_mime_type );
		$builder = self::with_provider( $builder, $args );
		$text    = $builder->generate_text();
		if ( is_wp_error( $text ) ) {
			self::fail( $text );
		}
		list( $caption, $truncated ) = self::clean_caption( (string) $text, $max_words );
		if ( '' === $caption ) {
			throw new \Exception( 'The provider returned an empty caption.' );
		}

		$attribution = Media_Support::get_attribution( $id );
		$credit      = self::credit_line( $attribution );
		$append      = ! isset( $args['append_credit'] ) || self::flag( $args, 'append_credit' );
		if ( null !== $credit && $append ) {
			$caption .= ( preg_match( '/[.!?]$/u', $caption ) ? ' ' : '. ' ) . $credit;
		}

		$new = $previous;
		if ( $write ) {
			
			$saved = wp_update_post(
				array(
					'ID'           => $id,
					'post_excerpt' => wp_slash( $caption ),
				),
				true
			);
			if ( is_wp_error( $saved ) ) {
				throw new \Exception( 'Saving the caption failed: ' . esc_html( $saved->get_error_message() ) );
			}
			$fresh = get_post( $id );
			$new   = $fresh && isset( $fresh->post_excerpt ) ? (string) $fresh->post_excerpt : $caption;
		}

		if ( $write ) {
			$message = 'Caption generated and written to the attachment.';
		} elseif ( '' !== trim( $previous ) ) {
			$message = 'Caption suggested (not written). The attachment already has a caption; pass write=true with overwrite=true to replace it.';
		} else {
			$message = 'Caption suggested (not written). Pass write=true to save it.';
		}
		if ( null !== $credit && ! $append ) {
			$message .= ' The credit line was left out of the caption; display it wherever the image is used.';
		}

		return array(
			'id'               => $id,
			'caption'          => $caption,
			'credit'           => $credit,
			'attribution'      => $attribution,
			'previous_caption' => $previous,
			'new_caption'      => $new,
			'written'          => $write,
			'truncated'        => $truncated,
			'message'          => $message,
		);
	}

	private static function clean_caption( $text, $max_words ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $text ) ) );
		for ( $pass = 0; $pass < 2; $pass++ ) {
			if ( preg_match( '/^["\'\x{201C}\x{2018}](.*)["\'\x{201D}\x{2019}]$/us', $text, $m ) ) {
				$text = trim( $m[1] );
			}
			$text = trim( (string) preg_replace( '/^caption\s*:\s*/iu', '', $text ) );
		}
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || count( $words ) <= $max_words ) {
			return array( $text, false );
		}
		$cut = implode( ' ', array_slice( $words, 0, $max_words ) );
		return array( (string) preg_replace( '/[\s,;:\-\x{2013}\x{2014}]+$/u', '', $cut ), true );
	}

	private static function credit_line( $attribution ) {
		if ( ! is_array( $attribution ) ) {
			return null;
		}
		$author = isset( $attribution['author'] ) ? trim( (string) $attribution['author'] ) : '';
		$source = isset( $attribution['source'] ) ? trim( (string) $attribution['source'] ) : '';
		if ( '' !== $source && strtolower( $source ) === $source ) {
			$source = ucfirst( $source );
		}
		if ( '' !== $author && '' !== $source ) {
			return 'Photo by ' . $author . ' on ' . $source;
		}
		if ( '' !== $author ) {
			return 'Photo by ' . $author;
		}
		return '' !== $source ? 'Photo via ' . $source : null;
	}
}
