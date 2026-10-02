<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SE_Ranking {

	const SLUG = 'se_ranking';

	private static function spec(): array {
		$source = array( 'type' => 'string', 'description' => 'Regional database code, e.g. "us", "uk", "de". Defaults to "us".' );
		$domain = array( 'type' => 'string', 'description' => 'Domain without scheme, e.g. "example.com".' );
		$kw     = array( 'type' => 'string', 'description' => 'Keyword phrase.' );
		$limit  = array( 'type' => 'integer', 'description' => 'Max rows (1–1000). Default 100.' );

		return array(
			'seranking_domain_overview' => array(
				'path' => '/v2/research/domain/overview', 'method' => 'GET',
				'description' => 'Domain overview in one regional database: organic/paid traffic, keyword counts, and estimated traffic cost.',
				'props' => array( 'domain' => $domain, 'source' => $source ), 'required' => array( 'domain' ),
			),
			'seranking_domain_overview_global' => array(
				'path' => '/v2/research/domain/overview-global', 'method' => 'GET',
				'description' => 'Global domain overview aggregating estimated organic metrics across all SE Ranking databases.',
				'props' => array( 'domain' => $domain ), 'required' => array( 'domain' ),
			),
			'seranking_domain_keywords' => array(
				'path' => '/v2/research/domain/keywords', 'method' => 'GET',
				'description' => 'Keywords a domain ranks for organically, with position, volume, and traffic share.',
				'props' => array( 'domain' => $domain, 'source' => $source, 'limit' => $limit ), 'required' => array( 'domain' ),
			),
			'seranking_domain_competitors' => array(
				'path' => '/v2/research/domain/competitors', 'method' => 'GET',
				'description' => 'Organic competitors of a domain with common-keyword counts and overlap.',
				'props' => array( 'domain' => $domain, 'source' => $source, 'limit' => $limit ), 'required' => array( 'domain' ),
			),
			'seranking_top_pages' => array(
				'path' => '/v2/research/domain/pages', 'method' => 'GET',
				'description' => 'Top-performing pages of a domain by estimated organic traffic and keyword count.',
				'props' => array( 'domain' => $domain, 'source' => $source, 'limit' => $limit ), 'required' => array( 'domain' ),
			),
			'seranking_subdomains' => array(
				'path' => '/v2/research/domain/subdomains', 'method' => 'GET',
				'description' => 'Subdomains of a domain with their organic traffic and keyword counts.',
				'props' => array( 'domain' => $domain, 'source' => $source, 'limit' => $limit ), 'required' => array( 'domain' ),
			),
			'seranking_keyword_overview' => array(
				'path' => '/v2/research/keywords/overview', 'method' => 'GET',
				'description' => 'Keyword overview: volume, CPC, competition, and difficulty for a phrase.',
				'props' => array( 'keyword' => $kw, 'source' => $source ), 'required' => array( 'keyword' ),
			),
			'seranking_keyword_compare' => array(
				'path' => '/v2/research/keywords/compare', 'method' => 'GET',
				'description' => 'Compare metrics for several keywords side by side (volume, CPC, difficulty).',
				'props' => array( 'keywords' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Keywords to compare.' ), 'source' => $source ), 'required' => array( 'keywords' ),
			),
			'seranking_related_keywords' => array(
				'path' => '/v2/research/keywords/related', 'method' => 'GET',
				'description' => 'Keywords semantically related to a phrase, with volume and difficulty.',
				'props' => array( 'keyword' => $kw, 'source' => $source, 'limit' => $limit ), 'required' => array( 'keyword' ),
			),
			'seranking_similar_keywords' => array(
				'path' => '/v2/research/keywords/similar', 'method' => 'GET',
				'description' => 'Similar keywords sharing SERP results with a phrase.',
				'props' => array( 'keyword' => $kw, 'source' => $source, 'limit' => $limit ), 'required' => array( 'keyword' ),
			),
			'seranking_question_keywords' => array(
				'path' => '/v2/research/keywords/questions', 'method' => 'GET',
				'description' => 'Question-form keywords containing a phrase, with volume, for FAQ and AI-answer targeting.',
				'props' => array( 'keyword' => $kw, 'source' => $source, 'limit' => $limit ), 'required' => array( 'keyword' ),
			),
			'seranking_longtail_keywords' => array(
				'path' => '/v2/research/keywords/long-tail', 'method' => 'GET',
				'description' => 'Long-tail keyword variations of a phrase, with volume and difficulty.',
				'props' => array( 'keyword' => $kw, 'source' => $source, 'limit' => $limit ), 'required' => array( 'keyword' ),
			),
			'seranking_backlinks' => array(
				'path' => '/v2/backlinks/summary', 'method' => 'GET',
				'description' => 'Backlink profile summary for a domain: total backlinks, referring domains, and anchors overview.',
				'props' => array( 'domain' => $domain ), 'required' => array( 'domain' ),
			),
			'seranking_domain_authority' => array(
				'path' => '/v2/research/domain/trust', 'method' => 'GET',
				'description' => 'Domain trust / authority scores (SE Ranking domain trust and page trust).',
				'props' => array( 'domain' => $domain ), 'required' => array( 'domain' ),
			),
			'seranking_ai_visibility' => array(
				'path' => '/v2/research/domain/ai-visibility', 'method' => 'GET',
				'description' => 'AI-search visibility for a domain: how often it appears in AI Overviews / AI answers across tracked prompts. (SE Ranking beta surface; verify against live account.)',
				'props' => array( 'domain' => $domain, 'source' => $source ), 'required' => array( 'domain' ),
			),
		);
	}

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}
		$tools = array();
		foreach ( self::spec() as $name => $s ) {
			$tools[] = array(
				'name'        => $name,
				'description' => $s['description'] . ' Source: SE Ranking (consumes API credit). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $s['props'],
					'required'   => $s['required'],
				),
			);
		}
		return $tools;
	}

	public static function tool_names(): array {
		return array_keys( self::spec() );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}
		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'SE Ranking is not configured or is switched off. Add an API token in Settings → External Services → SEO & analytics.' );
		}

		$spec = self::spec();
		if ( ! isset( $spec[ $name ] ) ) {
			throw new \Exception( 'Unknown SE Ranking tool: ' . $name );
		}
		$s = $spec[ $name ];

		$query = array();
		if ( isset( $s['props']['source'] ) ) {
			$query['source'] = isset( $args['source'] ) ? sanitize_key( $args['source'] ) : 'us';
		}
		if ( isset( $args['domain'] ) ) {
			$query['domain'] = self::host( (string) $args['domain'] );
		}
		if ( isset( $args['keyword'] ) ) {
			$query['keyword'] = (string) $args['keyword'];
		}
		if ( isset( $args['keywords'] ) && is_array( $args['keywords'] ) ) {
			$query['keywords'] = implode( ',', array_map( 'strval', $args['keywords'] ) );
		}
		if ( isset( $args['limit'] ) ) {
			$query['limit'] = max( 1, min( 1000, (int) $args['limit'] ) );
		}

		$res = Http::request( self::SLUG, $s['path'], array( 'method' => $s['method'], 'query' => $query ) );
		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}

		return array(
			'source' => 'se_ranking',
			'data'   => $res['data'],
		);
	}

	private static function host( string $input ): string {
		$parsed = wp_parse_url( trim( $input ) );
		$host   = ! empty( $parsed['host'] ) ? $parsed['host'] : preg_replace( '#/.*$#', '', trim( $input ) );
		return strtolower( preg_replace( '#^www\.#i', '', (string) $host ) );
	}
}
