<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LiteSpeed {

	public static function is_available() {
		return defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Core' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'litespeed' ),
			'capabilities' => array( 'caching' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'ls_purge_all',
				'description' => 'Purge the entire LiteSpeed Cache. Use after a major site update, content migration, or when troubleshooting stale content. This forces the whole site to be re-cached on the next request.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'ls_purge_url',
				'description' => 'Purge the LiteSpeed Cache entry for a single URL on this site. Resolves the URL to a WordPress post or page and clears its cached HTML, leaving the rest of the cache intact.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url' => [ 'type' => 'string', 'description' => 'Full URL on this site (e.g. https://yoursite.com/about/)' ],
					],
					'required'   => [ 'url' ],
				],
			],
			[
				'name'        => 'ls_get_crawler_status',
				'description' => 'Read the LiteSpeed Cache crawler status from the plugin\'s own stored summary: queue size, whether a crawl is running, last crawl position and outcome, and per-crawler hit/miss figures. Crawl positions and counts only, never a crawled URL. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use LiteSpeed Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'LiteSpeed Cache is not active' );
		}

		switch ( $name ) {
			case 'ls_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the LiteSpeed Cache.' );
				}
				do_action( 'litespeed_purge_all' );
				return [
					'success' => true,
					'message' => 'LiteSpeed Cache purged.',
				];

			case 'ls_purge_url':
				$url = esc_url_raw( $args['url'] ?? '' );
				if ( empty( $url ) ) {
					throw new \Exception( 'url is required' );
				}
				$post_id = url_to_postid( $url );
				if ( ! $post_id ) {
					throw new \Exception( 'Could not resolve URL to a WordPress post or page on this site: ' . esc_html( $url ) );
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to purge the cache for this post.' );
				}

				
				do_action( 'litespeed_purge_url', $url );
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'LiteSpeed Cache purged for post ID ' . $post_id,
				];

			case 'ls_get_crawler_status':
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to read the LiteSpeed Cache crawler status.' );
				}
				return self::build_crawler_status();

			default:
				throw new \Exception( 'Unknown LiteSpeed Cache tool: ' . esc_html( $name ) );
		}
	}

	private static function build_crawler_status() {
		if ( ! class_exists( '\LiteSpeed\Crawler' ) || ! method_exists( '\LiteSpeed\Crawler', 'get_summary' ) ) {
			return [
				'provider'  => 'litespeed',
				'available' => false,
				'message'   => 'The LiteSpeed crawler summary is not available in this version.',
			];
		}

		try {
			$summary = \LiteSpeed\Crawler::get_summary();
		} catch ( \Throwable $e ) {
			return [
				'provider'  => 'litespeed',
				'available' => false,
				'message'   => 'The LiteSpeed crawler summary could not be read.',
			];
		}
		$summary = is_array( $summary ) ? $summary : array();

		$int_or_null = function ( $key ) use ( $summary ) {
			return isset( $summary[ $key ] ) && is_numeric( $summary[ $key ] ) ? (int) $summary[ $key ] : null;
		};

		
		
		$crawler_stats = array();
		if ( isset( $summary['crawler_stats'] ) && is_array( $summary['crawler_stats'] ) ) {
			foreach ( array_slice( $summary['crawler_stats'], 0, 20, true ) as $slot => $tallies ) {
				if ( ! is_array( $tallies ) ) {
					continue;
				}
				$clean = array();
				foreach ( $tallies as $code => $count ) {
					$clean[ (string) $code ] = is_numeric( $count ) ? (int) $count : 0;
				}
				$crawler_stats[ (string) $slot ] = $clean;
			}
		}

		return [
			'provider'    => 'litespeed',
			'available'   => true,
			'queue_size'  => $int_or_null( 'list_size' ),
			'is_running'  => isset( $summary['is_running'] ) ? (bool) $summary['is_running'] : null,
			'last_pos'    => $int_or_null( 'last_pos' ),
			'last_count'  => $int_or_null( 'last_count' ),
			'last_status' => isset( $summary['last_status'] ) ? (string) $summary['last_status'] : null,
			'end_reason'  => isset( $summary['end_reason'] ) ? (string) $summary['end_reason'] : null,
			'done'        => isset( $summary['done'] ) ? (int) $summary['done'] : null,
			'last_crawled' => $int_or_null( 'last_crawled' ),
			'crawler_stats' => $crawler_stats,
		];
	}
}
