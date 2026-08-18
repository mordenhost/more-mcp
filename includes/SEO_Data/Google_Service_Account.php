<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Service_Account {

	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

	
	const SCOPES = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';

	const REFRESH_SKEW = 60;

	const ASSERTION_TTL = 3600;

	public static function parse_key_json( string $json ) {
		$json = trim( $json );
		if ( '' === $json ) {
			return new \WP_Error( 'sa_empty', 'No service account JSON provided.' );
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'sa_not_json', 'The service account key is not valid JSON.' );
		}
		if ( ( $data['type'] ?? '' ) !== 'service_account' ) {
			return new \WP_Error( 'sa_wrong_type', 'That JSON is not a service account key (expected "type": "service_account"). Do not paste an OAuth client secret here.' );
		}
		foreach ( array( 'client_email', 'private_key', 'token_uri' ) as $required ) {
			if ( empty( $data[ $required ] ) ) {
				return new \WP_Error( 'sa_missing_field', 'The service account key is missing the "' . $required . '" field.' );
			}
		}
		if ( false === strpos( $data['private_key'], 'PRIVATE KEY' ) ) {
			return new \WP_Error( 'sa_bad_key', 'The private_key field does not look like a PEM private key.' );
		}

		return array(
			'client_email' => (string) $data['client_email'],
			'private_key'  => (string) $data['private_key'],
			'token_uri'    => (string) ( $data['token_uri'] ?: self::TOKEN_ENDPOINT ),
			'project_id'   => (string) ( $data['project_id'] ?? '' ),
		);
	}

	public static function ensure_fresh_token( string $slug ) {
		$config = Credentials::config( $slug );
		if ( empty( $config['client_email'] ) || empty( $config['private_key'] ) ) {
			return new \WP_Error( 'sa_not_configured', 'No service account key is configured for this data source. Add one in Settings → AI Providers → SEO data sources.' );
		}

		$expires = isset( $config['token_expires'] ) ? (int) $config['token_expires'] : 0;
		if ( ! empty( $config['access_token'] ) && $expires > ( time() + self::REFRESH_SKEW ) ) {
			return $config['access_token'];
		}

		return self::mint_token( $slug, $config );
	}

	private static function mint_token( string $slug, array $config ) {
		$now       = time();
		$token_uri = ! empty( $config['token_uri'] ) ? $config['token_uri'] : self::TOKEN_ENDPOINT;

		$header = array( 'alg' => 'RS256', 'typ' => 'JWT' );
		$claims = array(
			'iss'   => $config['client_email'],
			'scope' => self::SCOPES,
			'aud'   => $token_uri,
			'iat'   => $now,
			'exp'   => $now + self::ASSERTION_TTL,
		);

		$signing_input = self::b64url( wp_json_encode( $header ) ) . '.' . self::b64url( wp_json_encode( $claims ) );

		$signature = '';
		$ok = openssl_sign( $signing_input, $signature, $config['private_key'], OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new \WP_Error( 'sa_sign_failed', 'Could not sign the request with the service account key. The private_key may be malformed.' );
		}
		$assertion = $signing_input . '.' . self::b64url( $signature );

		$response = wp_remote_post( $token_uri, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $assertion,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'sa_http', 'Google token request failed: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['error_description'] )
				? $body['error_description']
				: ( is_array( $body ) && ! empty( $body['error'] ) ? $body['error'] : ( 'HTTP ' . $code ) );
			return new \WP_Error( 'sa_rejected', 'Google rejected the service account assertion: ' . $msg );
		}

		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : self::ASSERTION_TTL;
		self::merge_slot( $slug, array(
			'access_token'  => $body['access_token'],
			'token_expires' => time() + $expires_in,
		) );

		return $body['access_token'];
	}

	private static function b64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	public static function merge_slot( string $slug, array $patch ) {
		$settings = get_option( 'more_mcp_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! isset( $settings['seo_data'] ) || ! is_array( $settings['seo_data'] ) ) {
			$settings['seo_data'] = array();
		}
		$slot = isset( $settings['seo_data'][ $slug ] ) && is_array( $settings['seo_data'][ $slug ] )
			? $settings['seo_data'][ $slug ]
			: array();
		$settings['seo_data'][ $slug ] = array_merge( $slot, $patch );
		update_option( 'more_mcp_settings', $settings );
		return true;
	}
}
