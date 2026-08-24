<?php

namespace More_MCP\SEO_Data;

use More_MCP\Platform\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Http {

	private const TIMEOUT = 10;

	public static function request( string $slug, string $path, array $args = array() ) {
		$provider = Providers::get( $slug );
		if ( ! $provider ) {
			return new \WP_Error( 'seo_data_unknown_provider', 'Unknown SEO data provider: ' . $slug );
		}

		$config = Credentials::config( $slug );
		$method = strtoupper( $args['method'] ?? 'GET' );
		$query  = isset( $args['query'] ) && is_array( $args['query'] ) ? $args['query'] : array();

		

		if ( preg_match( '#^https?://#i', $path ) ) {
			$url = $path;
		} else {
			$url = rtrim( (string) $provider['endpoint'], '/' ) . '/' . ltrim( $path, '/' );
		}

		
		
		$headers = array( 'Accept' => 'application/json' );
		$auth    = is_array( $provider['auth'] ) ? $provider['auth'] : array( 'type' => 'none' );

		switch ( $auth['type'] ) {
			case 'bearer':
				$token = $config['access_token'] ?? ( $config['api_key'] ?? '' );
				if ( '' === $token ) {
					return new \WP_Error( 'seo_data_no_credentials', 'No credential configured for ' . $provider['label'] . '.' );
				}
				$headers['Authorization'] = 'Bearer ' . $token;
				break;

			case 'header':
				$header_name = $auth['header'] ?? 'X-API-Key';
				if ( empty( $config['api_key'] ) ) {
					return new \WP_Error( 'seo_data_no_credentials', 'No API key configured for ' . $provider['label'] . '.' );
				}
				$headers[ $header_name ] = $config['api_key'];
				break;

			case 'query':
				$param = $auth['param'] ?? 'key';
				if ( empty( $config['api_key'] ) ) {
					return new \WP_Error( 'seo_data_no_credentials', 'No API key configured for ' . $provider['label'] . '.' );
				}
				$query[ $param ] = $config['api_key'];
				break;

			case 'basic':
				if ( empty( $config['login'] ) || empty( $config['password'] ) ) {
					return new \WP_Error( 'seo_data_no_credentials', 'API login and password required for ' . $provider['label'] . '.' );
				}
				$headers['Authorization'] = 'Basic ' . base64_encode( $config['login'] . ':' . $config['password'] );
				break;

			case 'none':
			default:
				break;
		}

		if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
			$headers = array_merge( $headers, $args['headers'] );
		}

		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		$url_check = Registry::validate_external_url( $url );
		if ( is_wp_error( $url_check ) ) {
			return $url_check;
		}

		$request_args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : self::TIMEOUT,
		);

		if ( 'GET' !== $method && isset( $args['body'] ) ) {
			$request_args['headers']['Content-Type'] = 'application/json';
			$request_args['body'] = is_string( $args['body'] ) ? $args['body'] : wp_json_encode( $args['body'] );
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			
			return new \WP_Error( 'seo_data_request_failed', $provider['label'] . ' request failed: ' . $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$data    = ( null === $decoded && '' !== trim( (string) $raw ) ) ? $raw : $decoded;

		return self::normalize( $provider, $status, $data );
	}

	private static function normalize( array $provider, int $status, $data ) {
		if ( $status >= 200 && $status < 300 ) {
			return array( 'status' => $status, 'data' => $data );
		}

		$label   = $provider['label'];
		$message = self::extract_message( $data );

		if ( 401 === $status || 403 === $status ) {
			return new \WP_Error(
				'seo_data_auth_rejected',
				sprintf( '%s rejected the credentials (HTTP %d)%s. Check the key and its permissions.', $label, $status, $message ),
				array( 'status' => $status )
			);
		}
		if ( 402 === $status ) {
			return new \WP_Error(
				'seo_data_payment_required',
				sprintf( '%s reports the account is out of API credits/units (HTTP 402)%s.', $label, $message ),
				array( 'status' => $status )
			);
		}
		if ( 429 === $status ) {
			return new \WP_Error(
				'seo_data_rate_limited',
				sprintf( '%s rate limit hit (HTTP 429)%s. Retry after a short wait.', $label, $message ),
				array( 'status' => $status )
			);
		}

		return new \WP_Error(
			'seo_data_error',
			sprintf( '%s returned HTTP %d%s.', $label, $status, $message ),
			array( 'status' => $status )
		);
	}

	private static function extract_message( $data ): string {
		if ( is_array( $data ) ) {
			foreach ( array( 'error', 'message', 'error_message', 'status_message', 'detail' ) as $key ) {
				if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
					return ': ' . $data[ $key ];
				}
			}
			if ( isset( $data['error'] ) && is_array( $data['error'] ) && ! empty( $data['error']['message'] ) ) {
				return ': ' . $data['error']['message'];
			}
		} elseif ( is_string( $data ) && '' !== trim( $data ) ) {
			return ': ' . wp_trim_words( $data, 20 );
		}
		return '';
	}
}
