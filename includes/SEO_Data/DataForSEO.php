<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DataForSEO {

	const SLUG = 'dataforseo';

	private static function tools_spec(): array {
		$location = array( 'type' => 'string', 'description' => 'Location name, e.g. "United States". Defaults to United States.' );
		$language = array( 'type' => 'string', 'description' => 'Language name, e.g. "English". Defaults to English.' );
		$target   = array( 'type' => 'string', 'description' => 'Target domain without scheme, e.g. "example.com".' );

		return array(
			'dataforseo_serp' => array(
				'description' => 'Live Google organic SERP for a keyword: ranked results with position, title, URL, and snippet.',
				'props'       => array(
					'keyword'  => array( 'type' => 'string', 'description' => 'Search query.' ),
					'location' => $location,
					'language' => $language,
				),
				'required'    => array( 'keyword' ),
			),
			'dataforseo_keyword_volume' => array(
				'description' => 'Search volume, CPC, and competition for one or more keywords (Google Ads data).',
				'props'       => array(
					'keywords' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Keywords to look up (up to 1000).' ),
					'location' => $location,
					'language' => $language,
				),
				'required'    => array( 'keywords' ),
			),
			'dataforseo_ranked_keywords' => array(
				'description' => 'Keywords a domain currently ranks for in organic search, with position and volume.',
				'props'       => array(
					'target'   => $target,
					'location' => $location,
					'language' => $language,
					'limit'    => array( 'type' => 'integer', 'description' => 'Max rows (1–1000). Default 100.' ),
				),
				'required'    => array( 'target' ),
			),
			'dataforseo_backlinks_summary' => array(
				'description' => 'Backlink profile summary for a target: total backlinks, referring domains, rank, and spam score.',
				'props'       => array( 'target' => $target ),
				'required'    => array( 'target' ),
			),
			'dataforseo_referring_domains' => array(
				'description' => 'Domains linking to a target, with backlink counts and domain rank.',
				'props'       => array(
					'target' => $target,
					'limit'  => array( 'type' => 'integer', 'description' => 'Max rows (1–1000). Default 100.' ),
				),
				'required'    => array( 'target' ),
			),
			'dataforseo_onpage_instant' => array(
				'description' => 'Instant on-page audit of a single URL: status code, load timing, title/description/heading checks, and detected issues.',
				'props'       => array(
					'url' => array( 'type' => 'string', 'description' => 'Full URL including scheme to audit.' ),
				),
				'required'    => array( 'url' ),
			),
		);
	}

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}
		$tools = array();
		foreach ( self::tools_spec() as $name => $spec ) {
			$tools[] = array(
				'name'        => $name,
				'description' => $spec['description'] . ' Source: DataForSEO (consumes account credit). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $spec['props'],
					'required'   => $spec['required'],
				),
			);
		}
		return $tools;
	}

	public static function tool_names(): array {
		return array_keys( self::tools_spec() );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}
		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'DataForSEO is not configured or is switched off. Add API credentials in Settings → External Services → SEO & analytics.' );
		}

		$location = isset( $args['location'] ) ? (string) $args['location'] : 'United States';
		$language = isset( $args['language'] ) ? (string) $args['language'] : 'English';
		$limit    = isset( $args['limit'] ) ? max( 1, min( 1000, (int) $args['limit'] ) ) : 100;

		switch ( $name ) {
			case 'dataforseo_serp':
				$task = array( array(
					'keyword'       => (string) ( $args['keyword'] ?? '' ),
					'location_name' => $location,
					'language_name' => $language,
				) );
				return self::post( '/v3/serp/google/organic/live/regular', $task );

			case 'dataforseo_keyword_volume':
				$keywords = isset( $args['keywords'] ) && is_array( $args['keywords'] ) ? array_values( array_map( 'strval', $args['keywords'] ) ) : array();
				if ( empty( $keywords ) ) {
					throw new \Exception( 'keywords is required and must be a non-empty array.' );
				}
				$task = array( array(
					'keywords'      => $keywords,
					'location_name' => $location,
					'language_name' => $language,
				) );
				return self::post( '/v3/keywords_data/google_ads/search_volume/live', $task );

			case 'dataforseo_ranked_keywords':
				$task = array( array(
					'target'        => self::host( (string) ( $args['target'] ?? '' ) ),
					'location_name' => $location,
					'language_name' => $language,
					'limit'         => $limit,
				) );
				return self::post( '/v3/dataforseo_labs/google/ranked_keywords/live', $task );

			case 'dataforseo_backlinks_summary':
				$task = array( array( 'target' => self::host( (string) ( $args['target'] ?? '' ) ) ) );
				return self::post( '/v3/backlinks/summary/live', $task );

			case 'dataforseo_referring_domains':
				$task = array( array(
					'target' => self::host( (string) ( $args['target'] ?? '' ) ),
					'limit'  => $limit,
				) );
				return self::post( '/v3/backlinks/referring_domains/live', $task );

			case 'dataforseo_onpage_instant':
				$task = array( array( 'url' => (string) ( $args['url'] ?? '' ) ) );
				return self::post( '/v3/on_page/instant_pages', $task );
		}

		throw new \Exception( 'Unknown DataForSEO tool: ' . $name );
	}

	private static function post( string $path, array $tasks ) {
		$res = Http::request( self::SLUG, $path, array( 'method' => 'POST', 'body' => $tasks ) );
		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}
		$data = $res['data'];

		

		if ( is_array( $data ) && isset( $data['tasks'][0] ) ) {
			$task = $data['tasks'][0];
			$code = isset( $task['status_code'] ) ? (int) $task['status_code'] : 20000;
			if ( $code >= 40000 ) {
				throw new \Exception( 'DataForSEO task error: ' . ( $task['status_message'] ?? ( 'code ' . $code ) ) );
			}
			return array(
				'source' => 'dataforseo',
				'result' => $task['result'] ?? array(),
			);
		}

		return array( 'source' => 'dataforseo', 'result' => $data );
	}

	private static function host( string $input ): string {
		$parsed = wp_parse_url( trim( $input ) );
		$host   = ! empty( $parsed['host'] ) ? $parsed['host'] : preg_replace( '#/.*$#', '', trim( $input ) );
		return strtolower( preg_replace( '#^www\.#i', '', (string) $host ) );
	}
}
