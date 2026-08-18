<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Semrush {

	const SLUG = 'semrush';

	private static function reports(): array {
		$db = array(
			'type'        => 'string',
			'description' => 'Regional database, e.g. "us", "uk", "de". Defaults to "us".',
		);
		$domain = array( 'type' => 'string', 'description' => 'Domain without scheme, e.g. "example.com".' );
		$phrase = array( 'type' => 'string', 'description' => 'Keyword phrase.' );
		$url    = array( 'type' => 'string', 'description' => 'Full URL including scheme.' );
		$limit  = array( 'type' => 'integer', 'description' => 'Max rows (1–1000). Default 100.' );

		return array(
			'semrush_domain_overview' => array(
				'report'      => 'domain_ranks',
				'description' => 'Semrush domain overview: organic/paid keyword counts, traffic estimate, and traffic cost for a domain in one regional database.',
				'props'       => array( 'domain' => $domain, 'database' => $db ),
				'required'    => array( 'domain' ),
			),
			'semrush_organic_keywords' => array(
				'report'      => 'domain_organic',
				'description' => 'Keywords a domain ranks for in organic search, with position, search volume, CPC, and traffic share.',
				'props'       => array( 'domain' => $domain, 'database' => $db, 'limit' => $limit ),
				'required'    => array( 'domain' ),
			),
			'semrush_competitors' => array(
				'report'      => 'domain_organic_organic',
				'description' => 'Organic competitors of a domain — sites competing for the same keywords — with their common-keyword counts.',
				'props'       => array( 'domain' => $domain, 'database' => $db, 'limit' => $limit ),
				'required'    => array( 'domain' ),
			),
			'semrush_keyword_overview' => array(
				'report'      => 'phrase_this',
				'description' => 'Keyword overview: search volume, CPC, competition, and result count for a phrase in one database.',
				'props'       => array( 'phrase' => $phrase, 'database' => $db ),
				'required'    => array( 'phrase' ),
			),
			'semrush_related_keywords' => array(
				'report'      => 'phrase_related',
				'description' => 'Keywords semantically related to a phrase, with volume and competition.',
				'props'       => array( 'phrase' => $phrase, 'database' => $db, 'limit' => $limit ),
				'required'    => array( 'phrase' ),
			),
			'semrush_keyword_difficulty' => array(
				'report'      => 'phrase_kdi',
				'description' => 'Keyword difficulty index (0–100) estimating how hard it is to rank for one or more phrases.',
				'props'       => array( 'phrase' => $phrase, 'database' => $db ),
				'required'    => array( 'phrase' ),
			),
			'semrush_question_keywords' => array(
				'report'      => 'phrase_questions',
				'description' => 'Question-form keywords containing a phrase (who/what/how/…), with volume — useful for FAQ and passage targeting.',
				'props'       => array( 'phrase' => $phrase, 'database' => $db, 'limit' => $limit ),
				'required'    => array( 'phrase' ),
			),
			'semrush_url_keywords' => array(
				'report'      => 'url_organic',
				'description' => 'Keywords a specific URL ranks for organically, with position and volume.',
				'props'       => array( 'url' => $url, 'database' => $db, 'limit' => $limit ),
				'required'    => array( 'url' ),
			),
			'semrush_backlinks_overview' => array(
				'report'      => 'backlinks_overview',
				'description' => 'Backlink profile summary for a domain: total backlinks, referring domains, referring IPs, and authority score.',
				'props'       => array( 'domain' => $domain ),
				'required'    => array( 'domain' ),
				'backlinks'   => true,
			),
			'semrush_backlinks_list' => array(
				'report'      => 'backlinks',
				'description' => 'Individual backlinks pointing at a domain, with source URL, anchor, and first-seen date.',
				'props'       => array( 'domain' => $domain, 'limit' => $limit ),
				'required'    => array( 'domain' ),
				'backlinks'   => true,
			),
			'semrush_referring_domains' => array(
				'report'      => 'backlinks_refdomains',
				'description' => 'Domains linking to a target, ranked by their authority, with backlink counts.',
				'props'       => array( 'domain' => $domain, 'limit' => $limit ),
				'required'    => array( 'domain' ),
				'backlinks'   => true,
			),
			'semrush_backlink_anchors' => array(
				'report'      => 'backlinks_anchors',
				'description' => 'Anchor texts used in backlinks to a target, with counts — reveals over-optimized or branded anchor patterns.',
				'props'       => array( 'domain' => $domain, 'limit' => $limit ),
				'required'    => array( 'domain' ),
				'backlinks'   => true,
			),
		);
	}

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}
		$tools = array();
		foreach ( self::reports() as $name => $spec ) {
			$tools[] = array(
				'name'        => $name,
				'description' => $spec['description'] . ' Source: Semrush (costs API units). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $spec['props'],
					'required'   => $spec['required'],
				),
			);
		}
		
		$tools[] = array(
			'name'        => 'semrush_api_units',
			'description' => 'Remaining Semrush API unit balance for the configured key. Read-only, does not itself cost units.',
			'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
		);
		return $tools;
	}

	public static function tool_names(): array {
		return array_merge( array_keys( self::reports() ), array( 'semrush_api_units' ) );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}
		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'Semrush is not configured or is switched off. Add an API key in Settings → AI Providers → SEO data sources.' );
		}

		if ( 'semrush_api_units' === $name ) {
			$res = Http::request( self::SLUG, '/', array( 'query' => array( 'type' => 'balance' ) ) );
			if ( is_wp_error( $res ) ) {
				throw new \Exception( $res->get_error_message() );
			}
			return array( 'api_units_remaining' => is_scalar( $res['data'] ) ? trim( (string) $res['data'] ) : $res['data'], 'source' => 'semrush' );
		}

		$reports = self::reports();
		if ( ! isset( $reports[ $name ] ) ) {
			throw new \Exception( 'Unknown Semrush tool: ' . $name );
		}
		$spec = $reports[ $name ];

		$query = array( 'type' => $spec['report'] );
		$database = isset( $args['database'] ) ? sanitize_key( $args['database'] ) : 'us';

		
		if ( ! empty( $spec['backlinks'] ) ) {
			$query['target']      = isset( $args['domain'] ) ? self::host( (string) $args['domain'] ) : '';
			$query['target_type'] = 'root_domain';
		} elseif ( isset( $spec['props']['phrase'] ) ) {
			$query['phrase']   = (string) ( $args['phrase'] ?? '' );
			$query['database'] = $database;
		} elseif ( isset( $spec['props']['url'] ) ) {
			$query['url']      = (string) ( $args['url'] ?? '' );
			$query['database'] = $database;
		} else {
			$query['domain']   = isset( $args['domain'] ) ? self::host( (string) $args['domain'] ) : '';
			$query['database'] = $database;
		}

		if ( isset( $args['limit'] ) ) {
			$query['display_limit'] = max( 1, min( 1000, (int) $args['limit'] ) );
		}

		$res = Http::request( self::SLUG, '/', array( 'query' => $query ) );
		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}

		return array(
			'source' => 'semrush',
			'report' => $spec['report'],
			'rows'   => self::parse_csv( $res['data'] ),
		);
	}

	private static function host( string $input ): string {
		$parsed = wp_parse_url( trim( $input ) );
		$host   = ! empty( $parsed['host'] ) ? $parsed['host'] : preg_replace( '#/.*$#', '', trim( $input ) );
		return strtolower( preg_replace( '#^www\.#i', '', (string) $host ) );
	}

	private static function parse_csv( $data ) {
		if ( ! is_string( $data ) || '' === trim( $data ) ) {
			return $data;
		}
		$lines = preg_split( '/\r?\n/', trim( $data ) );
		if ( count( $lines ) < 1 ) {
			return array();
		}
		$header = explode( ';', array_shift( $lines ) );
		$rows   = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = explode( ';', $line );
			$row   = array();
			foreach ( $header as $i => $col ) {
				$row[ trim( $col ) ] = $cells[ $i ] ?? '';
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
