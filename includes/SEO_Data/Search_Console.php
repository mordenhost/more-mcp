<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Console {

	const SLUG = 'search_console';

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}

		$site = array( 'type' => 'string', 'description' => 'Property URL exactly as verified in Search Console, e.g. "https://example.com/" or "sc-domain:example.com".' );

		return array(
			array(
				'name'        => 'gsc_list_sites',
				'description' => 'List Search Console properties the connected Google account can access, with each property\'s permission level. Source: Google Search Console. Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'gsc_search_analytics',
				'description' => 'Query Search Console performance data: clicks, impressions, CTR, and average position, grouped by the requested dimensions over a date range. Source: Google Search Console. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'site_url'   => $site,
						'start_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to 28 days ago.' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.' ),
						'dimensions' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'query', 'page', 'country', 'device', 'date', 'searchAppearance' ) ), 'description' => 'Group-by dimensions. Defaults to ["query"].' ),
						'limit'      => array( 'type' => 'integer', 'description' => 'Max rows (1–25000). Default 100.' ),
					),
					'required'   => array( 'site_url' ),
				),
			),
			array(
				'name'        => 'gsc_list_sitemaps',
				'description' => 'List sitemaps submitted for a property, with submission state and last-download time. Source: Google Search Console. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'site_url' => $site ),
					'required'   => array( 'site_url' ),
				),
			),
			array(
				'name'        => 'gsc_get_sitemap',
				'description' => 'Get details for one submitted sitemap: contents counts by type, warnings, and errors. Source: Google Search Console. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'site_url'    => $site,
						'sitemap_url' => array( 'type' => 'string', 'description' => 'Full URL of the submitted sitemap.' ),
					),
					'required'   => array( 'site_url', 'sitemap_url' ),
				),
			),
			array(
				'name'        => 'gsc_inspect_url',
				'description' => 'Inspect a URL\'s index status in Google: coverage state, last crawl, canonical, mobile usability, and rich-result detection. Source: Google Search Console. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'site_url'      => $site,
						'inspection_url' => array( 'type' => 'string', 'description' => 'The full URL to inspect. Must belong to the property.' ),
					),
					'required'   => array( 'site_url', 'inspection_url' ),
				),
			),
		);
	}

	public static function tool_names(): array {
		return array( 'gsc_list_sites', 'gsc_search_analytics', 'gsc_list_sitemaps', 'gsc_get_sitemap', 'gsc_inspect_url' );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}
		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'Google Search Console is not connected. Add a service account key in Settings → AI Providers → SEO data sources, then share the property with the service account email.' );
		}

		
		
		$token = Google_Service_Account::ensure_fresh_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			throw new \Exception( $token->get_error_message() );
		}

		switch ( $name ) {
			case 'gsc_list_sites':
				return self::result( Http::request( self::SLUG, '/webmasters/v3/sites', array() ) );

			case 'gsc_search_analytics':
				$site = self::require_site( $args );
				$body = array(
					'startDate'  => isset( $args['start_date'] ) ? (string) $args['start_date'] : gmdate( 'Y-m-d', time() - 28 * DAY_IN_SECONDS ),
					'endDate'    => isset( $args['end_date'] ) ? (string) $args['end_date'] : gmdate( 'Y-m-d' ),
					'dimensions' => ( isset( $args['dimensions'] ) && is_array( $args['dimensions'] ) && $args['dimensions'] ) ? array_values( array_map( 'strval', $args['dimensions'] ) ) : array( 'query' ),
					'rowLimit'   => isset( $args['limit'] ) ? max( 1, min( 25000, (int) $args['limit'] ) ) : 100,
				);
				return self::result( Http::request( self::SLUG, '/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', array( 'method' => 'POST', 'body' => $body ) ) );

			case 'gsc_list_sitemaps':
				$site = self::require_site( $args );
				return self::result( Http::request( self::SLUG, '/webmasters/v3/sites/' . rawurlencode( $site ) . '/sitemaps', array() ) );

			case 'gsc_get_sitemap':
				$site    = self::require_site( $args );
				$sitemap = (string) ( $args['sitemap_url'] ?? '' );
				if ( '' === $sitemap ) {
					throw new \Exception( 'sitemap_url is required.' );
				}
				return self::result( Http::request( self::SLUG, '/webmasters/v3/sites/' . rawurlencode( $site ) . '/sitemaps/' . rawurlencode( $sitemap ), array() ) );

			case 'gsc_inspect_url':
				$site   = self::require_site( $args );
				$target = (string) ( $args['inspection_url'] ?? '' );
				if ( '' === $target ) {
					throw new \Exception( 'inspection_url is required.' );
				}
				$body = array(
					'inspectionUrl' => $target,
					'siteUrl'       => $site,
				);
				return self::result( Http::request( self::SLUG, '/v1/urlInspection/index:inspect', array( 'method' => 'POST', 'body' => $body ) ) );
		}

		throw new \Exception( 'Unknown Search Console tool: ' . $name );
	}

	private static function require_site( array $args ): string {
		$site = isset( $args['site_url'] ) ? (string) $args['site_url'] : '';
		if ( '' === $site ) {
			throw new \Exception( 'site_url is required.' );
		}
		return $site;
	}

	private static function result( $res ) {
		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}
		return array( 'source' => 'search_console', 'data' => $res['data'] );
	}
}
