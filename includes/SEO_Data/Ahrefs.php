<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ahrefs {

	const SLUG = 'ahrefs';

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}

		return array(
			array(
				'name'        => 'wp_ahrefs_domain_rating_free',
				'description' => 'Get the Ahrefs Domain Rating (DR, 0–100) for a domain. Free — no API key, no account. DR estimates the strength of a domain\'s backlink profile relative to others; it is Ahrefs\' metric, not a Google ranking factor. Off by default; an admin enables it in Settings → AI Providers → SEO data sources. Read-only, one outbound call to Ahrefs per invocation.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'domain' => array(
							'type'        => 'string',
							'description' => 'Domain or URL to check, e.g. "example.com" or "https://example.com/". The host is extracted; path and scheme are ignored.',
						),
					),
					'required'   => array( 'domain' ),
				),
			),
		);
	}

	public static function tool_names(): array {
		return array( 'wp_ahrefs_domain_rating_free' );
	}

	public static function execute_tool( string $name, array $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}

		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'Ahrefs Domain Rating is not enabled. Enable it in Settings → AI Providers → SEO data sources.' );
		}
		if ( 'wp_ahrefs_domain_rating_free' !== $name ) {
			throw new \Exception( 'Unknown Ahrefs tool: ' . $name );
		}

		$domain = self::normalize_domain( (string) ( $args['domain'] ?? '' ) );
		if ( '' === $domain ) {
			throw new \Exception( 'A domain is required.' );
		}

		$result = Http::request(
			self::SLUG,
			'/v4/authority-checker',
			array(
				'method' => 'GET',
				'query'  => array( 'input' => $domain, 'mode' => 'subdomains' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		$dr = self::extract_dr( $result['data'] ?? null );

		return array(
			'domain'        => $domain,
			'domain_rating' => $dr, 
			'source'        => 'ahrefs',
			'metric'        => 'Domain Rating (DR)',
		);
	}

	private static function normalize_domain( string $input ): string {
		$input = trim( $input );
		if ( '' === $input ) {
			return '';
		}

		$parsed = wp_parse_url( $input );
		if ( ! empty( $parsed['host'] ) ) {
			$host = $parsed['host'];
		} else {
			$host = preg_replace( '#/.*$#', '', $input );
		}
		$host = preg_replace( '#^www\.#i', '', (string) $host );
		return strtolower( trim( (string) $host ) );
	}

	private static function extract_dr( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		foreach ( array( 'domainRating', 'domain_rating', 'dr' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
				return (int) round( (float) $data[ $key ] );
			}
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			return self::extract_dr( $data['data'] );
		}
		return null;
	}
}
