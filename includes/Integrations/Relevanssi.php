<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Relevanssi {

	const SEARCH_LIMIT = 20;

	public static function is_available() {

		
		
		return defined( 'RELEVANSSI_PREMIUM' ) && function_exists( 'relevanssi_do_query' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'relevanssi' ),
			'capabilities' => array( 'search' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'relevanssi_get_index_status',
				'description' => 'Relevanssi search-index health: whether the index has been built (index_state: done / not_built), the number of indexed documents and distinct terms (read from Relevanssi\'s own cached count options, with a live COUNT fallback), whether query logging is enabled, and the total logged-query count when it is. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'relevanssi_get_query_stats',
				'description' => 'Aggregate Relevanssi query-log statistics: total logged searches, distinct search terms, average hits per search, and the top search terms by hit count (query text and hit count only). Requires query logging to be enabled (see relevanssi_get_index_status). The log table stores user ids, IPs, and session ids — those columns are never selected. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum top queries to return (default 10, max 50).' ),
					),
				),
			),
			array(
				'name'        => 'relevanssi_get_top_queries',
				'description' => 'The top search terms by frequency with their average hit count, the same aggregate Relevanssi\'s own query-log page shows (relevanssi_date_queries shape). Reflects ONLY logged searches: when the "Keep a log of user queries" option is off, this tool reports logging_enabled=false rather than returning an empty/fabricated traffic figure. user_id/ip/session_id columns are never selected. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit'  => array( 'type' => 'integer', 'description' => 'Maximum top queries to return (default 100, max 100 — the plugin\'s own cap).' ),
						'source' => array( 'type' => 'string', 'description' => 'Optional source filter on the source column (e.g. "wp-admin", "front").' ),
					),
				),
			),
			array(
				'name'        => 'relevanssi_search',
				'description' => 'Run a search through Relevanssi\'s own index (relevanssi_do_query) and return the matching posts: id, title, post type, and permalink, capped at 20 results with the total match count. Bounded by design; not a replacement for the core search tool, but the way to see what the Relevanssi index (which can include custom fields, taxonomies, and user content) actually matches. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array( 'type' => 'string', 'description' => 'Search phrase, passed to Relevanssi as typed.' ),
					),
					'required'   => array( 'query' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use search tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Relevanssi is not active.' );
		}

		if ( 'relevanssi_get_index_status' === $name ) {
			return self::get_index_status();
		}
		if ( 'relevanssi_get_query_stats' === $name ) {
			return self::get_query_stats( isset( $args['limit'] ) ? (int) $args['limit'] : 10 );
		}
		if ( 'relevanssi_get_top_queries' === $name ) {
			return self::get_top_queries( $args );
		}
		if ( 'relevanssi_search' === $name ) {
			$query = isset( $args['query'] ) ? trim( (string) $args['query'] ) : '';
			if ( '' === $query ) {
				throw new \Exception( 'query is required.' );
			}
			return self::search( $query );
		}
		throw new \Exception( 'Unknown Relevanssi tool: ' . esc_html( $name ) );
	}

	private static function get_index_status() {
		$indexed = get_option( 'relevanssi_indexed', '' );

		return array(
			'provider'    => 'relevanssi',
			'edition'     => RELEVANSSI_PREMIUM ? 'premium' : 'free',
			'index_state' => 'done' === $indexed ? 'done' : 'not_built',

			
			'doc_count'   => self::count_with_cache( 'relevanssi_doc_count', 'COUNT(DISTINCT(doc))' ),
			'term_count'  => self::count_with_cache( 'relevanssi_terms_count', 'COUNT(DISTINCT(term))' ),
			'logging'     => array(
				'enabled' => (bool) get_option( 'relevanssi_log_queries', false ),
				'queries' => self::table_exists( 'relevanssi_log' ) ? self::log_count() : null,
			),
		);
	}

	private static function get_query_stats( $limit ) {
		global $wpdb;
		$log = $wpdb->prefix . 'relevanssi_log';

		if ( ! self::table_exists( 'relevanssi_log' ) ) {
			return array(
				'provider' => 'relevanssi',
				'available' => false,
				'message'   => 'The Relevanssi query log table does not exist (logging has never been enabled).',
			);
		}
		if ( ! (bool) get_option( 'relevanssi_log_queries', false ) ) {
			return array(
				'provider' => 'relevanssi',
				'available' => false,
				'message'   => 'Relevanssi query logging is disabled (enable "Keep a log of user queries" in Relevanssi settings).',
			);
		}

		$limit = max( 1, min( 50, $limit ) );

		
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" ); 
		$distinct = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT(query)) FROM {$log}" ); 
		$avg      = $total > 0 ? round( (float) $wpdb->get_var( "SELECT AVG(hits) FROM {$log}" ), 2 ) : 0.0; 

		$top = array();
		if ( $total > 0 ) {
			$rows = $wpdb->get_results( 
				$wpdb->prepare( "SELECT query, MAX(hits) AS hits FROM {$log} GROUP BY query ORDER BY hits DESC LIMIT %d", $limit )
			);
			foreach ( (array) $rows as $row ) {
				$top[] = array(
					'query' => (string) $row->query,
					'hits'  => (int) $row->hits,
				);
			}
		}

		return array(
			'provider'        => 'relevanssi',
			'available'       => true,
			'total_queries'   => $total,
			'distinct_queries' => $distinct,
			'avg_hits'        => $avg,
			'top_queries'     => $top,
		);
	}

	private static function get_top_queries( $args ) {
		global $wpdb;

		if ( ! self::table_exists( 'relevanssi_log' ) ) {
			return array(
				'provider'        => 'relevanssi',
				'available'       => false,
				'logging_enabled' => false,
				'message'         => 'The Relevanssi query log table does not exist (logging has never been enabled).',
			);
		}
		
		if ( 'on' !== get_option( 'relevanssi_log_queries', false ) ) {
			return array(
				'provider'        => 'relevanssi',
				'available'       => false,
				'logging_enabled' => false,
				'message'         => 'Relevanssi query logging is disabled (enable "Keep a log of user queries" in Relevanssi settings); this tool reflects only logged searches.',
			);
		}

		$log    = $wpdb->prefix . 'relevanssi_log';
		$limit  = max( 1, min( 100, isset( $args['limit'] ) ? (int) $args['limit'] : 100 ) );
		$source = isset( $args['source'] ) ? sanitize_text_field( $args['source'] ) : '';

		$where = 'WHERE hits > 0';
		if ( '' !== $source ) {
			$where .= ' AND source = %s';

			
			$sql  = "SELECT COUNT(DISTINCT(id)) AS cnt, query, AVG(hits) AS hits FROM {$log} {$where} GROUP BY query ORDER BY cnt DESC LIMIT %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $source, $limit ) ); 
		} else {
			$sql  = "SELECT COUNT(DISTINCT(id)) AS cnt, query, AVG(hits) AS hits FROM {$log} {$where} GROUP BY query ORDER BY cnt DESC LIMIT %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ) ); 
		}

		$top = array();
		foreach ( (array) $rows as $row ) {
			$top[] = array(
				'query'    => (string) $row->query,
				'count'    => (int) $row->cnt,
				'avg_hits' => is_numeric( $row->hits ) ? round( (float) $row->hits, 2 ) : 0.0,
			);
		}

		return array(
			'provider'        => 'relevanssi',
			'available'       => true,
			'logging_enabled' => true,
			'returned'        => count( $top ),
			'limited_to'      => $limit,
			'top_queries'     => $top,
		);
	}

	private static function search( $query ) {

		
		$wp_query = new \WP_Query();
		$wp_query->query_vars['s']              = $query;
		$wp_query->query_vars['posts_per_page'] = self::SEARCH_LIMIT;
		$wp_query->query_vars['paged']          = 1;
		relevanssi_do_query( $wp_query );

		$results = array();
		foreach ( (array) $wp_query->posts as $post ) {
			$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
			$results[] = array(
				'id'        => $post_id,
				'title'     => is_object( $post ) ? (string) get_the_title( $post ) : (string) get_the_title( $post_id ),
				'post_type' => is_object( $post ) ? (string) $post->post_type : (string) get_post_type( $post_id ),
				'permalink' => (string) get_permalink( $post_id ),
			);
		}

		return array(
			'provider'   => 'relevanssi',
			'query'      => $query,
			'total'      => (int) $wp_query->found_posts,
			'returned'   => count( $results ),
			'capped_at'  => self::SEARCH_LIMIT,
			'results'    => $results,
		);
	}

	private static function count_with_cache( $option, $aggregate ) {
		$cached = get_option( $option, null );
		if ( is_numeric( $cached ) ) {
			return (int) $cached;
		}
		if ( ! self::table_exists( 'relevanssi' ) ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'relevanssi';
		return (int) $wpdb->get_var( "SELECT {$aggregate} FROM {$table}" ); 
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}

	private static function log_count() {
		global $wpdb;
		$log = $wpdb->prefix . 'relevanssi_log';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" ); 
	}
}
