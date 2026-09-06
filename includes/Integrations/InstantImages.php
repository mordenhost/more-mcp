<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InstantImages {

	const API_SETTINGS_OPTION = 'instant_img_api_settings';

	private static function providers() {
		return array(
			'unsplash' => array(
				'label'        => 'Unsplash',
				'option_key'   => 'unsplash_api',
				'constant'     => 'INSTANT_IMAGES_UNSPLASH_KEY',
				'endpoint'     => 'https://api.unsplash.com/search/photos',
				'license'      => 'Unsplash License (free, attribution appreciated/required by API terms)',
			),
			'pexels'   => array(
				'label'        => 'Pexels',
				'option_key'   => 'pexels_api',
				'constant'     => 'INSTANT_IMAGES_PEXELS_KEY',
				'endpoint'     => 'https://api.pexels.com/v1/search',
				'license'      => 'Pexels License (free, attribution required)',
			),
		);
	}

	public static function is_available() {
		return defined( 'INSTANT_IMAGES_VERSION' )
			|| function_exists( 'instant_images_get_option' )
			|| class_exists( '\InstantImages' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'instant_images' ),
			'capabilities' => array( 'stock_images' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		if ( empty( self::configured_providers() ) ) {
			return array();
		}
		$available = implode( ', ', array_keys( self::configured_providers() ) );
		return array(
			array(
				'name'        => 'stock_search_images',
				'description' => 'Search stock photos on Unsplash and/or Pexels and return candidate images with their download URL, dimensions, provider, license, and REQUIRED attribution (photographer name + profile/source URL). Uses the API keys already stored by the Instant Images plugin — no key is entered here or ever returned. Pass a returned image_url to wp_upload_media_from_url to add it to the media library; you are responsible for displaying the returned attribution. Read-only against the provider. Configured providers on this site: ' . $available . '.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array( 'type' => 'string', 'description' => 'Search terms, e.g. "mountain sunrise".' ),
						'provider' => array( 'type' => 'string', 'enum' => array( 'unsplash', 'pexels' ), 'description' => 'Which provider to search. Defaults to the first one that has a key configured.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'How many results (1-30, default 10).' ),
						'orientation' => array( 'type' => 'string', 'enum' => array( 'landscape', 'portrait', 'squarish' ), 'description' => 'Optional orientation filter.' ),
					),
					'required'   => array( 'query' ),
				),
			),
		);
	}

	private static function configured_providers() {
		$out      = array();
		$settings = get_option( self::API_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		foreach ( self::providers() as $slug => $p ) {
			$key = '';
			if ( ! empty( $settings[ $p['option_key'] ] ) && is_string( $settings[ $p['option_key'] ] ) ) {
				$key = trim( $settings[ $p['option_key'] ] );
			}
			if ( '' === $key && $p['constant'] && defined( $p['constant'] ) && '' !== (string) constant( $p['constant'] ) ) {
				$key = (string) constant( $p['constant'] );
			}
			if ( '' !== $key ) {
				$out[ $slug ] = $key;
			}
		}
		return $out;
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'upload_files' ) ) {
			throw new \Exception( 'You do not have permission to search or add media.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Instant Images is not active.' );
		}
		if ( 'stock_search_images' !== $name ) {
			throw new \Exception( 'Unknown stock-images tool: ' . esc_html( $name ) );
		}

		$query = isset( $args['query'] ) ? trim( (string) $args['query'] ) : '';
		if ( '' === $query ) {
			throw new \Exception( 'query is required.' );
		}

		$configured = self::configured_providers();
		if ( empty( $configured ) ) {
			throw new \Exception( 'No stock provider has an API key. Configure Unsplash or Pexels in Instant Images first.' );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : '';
		if ( '' !== $provider ) {
			if ( ! isset( $configured[ $provider ] ) ) {
				throw new \Exception( 'Provider "' . esc_html( $provider ) . '" has no API key configured in Instant Images. Available: ' . esc_html( implode( ', ', array_keys( $configured ) ) ) . '.' );
			}
		} else {
			$provider = array_key_first( $configured );
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 30, (int) $args['per_page'] ) ) : 10;
		$orientation = isset( $args['orientation'] ) ? sanitize_key( $args['orientation'] ) : '';
		$key = $configured[ $provider ];

		if ( 'unsplash' === $provider ) {
			return self::search_unsplash( $query, $per_page, $orientation, $key );
		}
		return self::search_pexels( $query, $per_page, $orientation, $key );
	}

	private static function remote_get_json( $url, $headers ) {
		$response = wp_safe_remote_get( $url, array(
			'timeout'             => 12,
			'redirection'         => 2,
			'limit_response_size' => 2 * 1024 * 1024,
			'headers'             => $headers,
		) );
		if ( is_wp_error( $response ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array( 'code' => $code, 'body' => is_array( $body ) ? $body : null, 'error' => null );
	}

	private static function search_unsplash( $query, $per_page, $orientation, $key ) {
		$params = array( 'query' => $query, 'per_page' => $per_page );
		if ( in_array( $orientation, array( 'landscape', 'portrait', 'squarish' ), true ) ) {
			$params['orientation'] = $orientation;
		}
		$url = add_query_arg( array_map( 'rawurlencode', $params ), 'https://api.unsplash.com/search/photos' );
		$res = self::remote_get_json( $url, array( 'Authorization' => 'Client-ID ' . $key, 'Accept-Version' => 'v1' ) );
		if ( $res['code'] < 200 || $res['code'] >= 300 || null === $res['body'] ) {
			throw new \Exception( 'Unsplash search failed (HTTP ' . intval( $res['code'] ) . ').' );
		}
		$results = array();
		foreach ( ( $res['body']['results'] ?? array() ) as $photo ) {
			$results[] = array(
				'provider'    => 'unsplash',
				'id'          => (string) ( $photo['id'] ?? '' ),
				'image_url'   => (string) ( $photo['urls']['regular'] ?? $photo['urls']['full'] ?? '' ),
				'thumb_url'   => (string) ( $photo['urls']['thumb'] ?? '' ),
				'width'       => isset( $photo['width'] ) ? (int) $photo['width'] : null,
				'height'      => isset( $photo['height'] ) ? (int) $photo['height'] : null,
				'description' => (string) ( $photo['alt_description'] ?? $photo['description'] ?? '' ),
				'license'     => 'Unsplash License',
				'attribution' => array(
					'photographer'     => (string) ( $photo['user']['name'] ?? '' ),
					'photographer_url' => (string) ( $photo['user']['links']['html'] ?? '' ),
					'source_url'       => (string) ( $photo['links']['html'] ?? '' ),
				),
			);
		}
		return array(
			'provider'          => 'unsplash',
			'query'             => $query,
			'total'             => isset( $res['body']['total'] ) ? (int) $res['body']['total'] : count( $results ),
			'count'             => count( $results ),
			'results'           => $results,
			'attribution_notice' => 'Unsplash requires crediting the photographer. Display attribution.photographer linking to attribution.photographer_url when you use an image.',
		);
	}

	private static function search_pexels( $query, $per_page, $orientation, $key ) {
		$params = array( 'query' => $query, 'per_page' => $per_page );
		if ( in_array( $orientation, array( 'landscape', 'portrait', 'square' ), true ) ) {
			
			$params['orientation'] = $orientation;
		} elseif ( 'squarish' === $orientation ) {
			$params['orientation'] = 'square';
		}
		$url = add_query_arg( array_map( 'rawurlencode', $params ), 'https://api.pexels.com/v1/search' );
		$res = self::remote_get_json( $url, array( 'Authorization' => $key ) );
		if ( $res['code'] < 200 || $res['code'] >= 300 || null === $res['body'] ) {
			throw new \Exception( 'Pexels search failed (HTTP ' . intval( $res['code'] ) . ').' );
		}
		$results = array();
		foreach ( ( $res['body']['photos'] ?? array() ) as $photo ) {
			$results[] = array(
				'provider'    => 'pexels',
				'id'          => (string) ( $photo['id'] ?? '' ),
				'image_url'   => (string) ( $photo['src']['large'] ?? $photo['src']['original'] ?? '' ),
				'thumb_url'   => (string) ( $photo['src']['tiny'] ?? '' ),
				'width'       => isset( $photo['width'] ) ? (int) $photo['width'] : null,
				'height'      => isset( $photo['height'] ) ? (int) $photo['height'] : null,
				'description' => (string) ( $photo['alt'] ?? '' ),
				'license'     => 'Pexels License',
				'attribution' => array(
					'photographer'     => (string) ( $photo['photographer'] ?? '' ),
					'photographer_url' => (string) ( $photo['photographer_url'] ?? '' ),
					'source_url'       => (string) ( $photo['url'] ?? '' ),
				),
			);
		}
		return array(
			'provider'          => 'pexels',
			'query'             => $query,
			'total'             => isset( $res['body']['total_results'] ) ? (int) $res['body']['total_results'] : count( $results ),
			'count'             => count( $results ),
			'results'           => $results,
			'attribution_notice' => 'Pexels requires crediting the photographer and Pexels. Display attribution.photographer linking to attribution.photographer_url when you use an image.',
		);
	}
}
