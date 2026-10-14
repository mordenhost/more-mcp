<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFastestCache {

	public static function is_available() {
		return class_exists( 'WpFastestCache' ) && function_exists( 'wpfc_clear_all_cache' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wp-fastest-cache' ),
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
				'name'        => 'wpfc_purge_all',
				'description' => 'Purge the entire WP Fastest Cache page cache via the plugin\'s wpfc_clear_all_cache() hook. Minified CSS/JS files are left in place. When WP Fastest Cache\'s own Varnish or Cloudflare integration is configured, the plugin purges those too, and it restarts its preload if preloading is enabled. Use after a major site update, content migration, or when troubleshooting stale content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wpfc_purge_url',
				'description' => 'Purge the WP Fastest Cache entry for a single URL on this site. Resolves the URL to a WordPress post or page and clears its cached page together with the related pages the plugin associates with it (home page, author page, feeds, sitemap, adjacent posts, and the first page of its categories and tags) via wpfc_clear_post_cache_by_id(), leaving the rest of the page cache intact. When WP Fastest Cache\'s own Varnish or Cloudflare integration is configured, the plugin also purges that edge cache in full on this call.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url' => [ 'type' => 'string', 'description' => 'Full URL on this site (e.g. https://yoursite.com/about/)' ],
					],
					'required'   => [ 'url' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use WP Fastest Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Fastest Cache is not active' );
		}

		switch ( $name ) {
			case 'wpfc_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the WP Fastest Cache.' );
				}
				$result = wpfc_clear_all_cache();

				
				if ( is_array( $result ) && isset( $result['success'] ) && false === $result['success'] ) {
					$reason = isset( $result['message'] ) ? (string) $result['message'] : 'the clearing-cache hook is disabled';
					throw new \Exception( 'WP Fastest Cache refused the purge: ' . esc_html( $reason ) );
				}
				return [
					'success' => true,
					'message' => 'WP Fastest Cache cache purged.',
				];

			case 'wpfc_purge_url':
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
				if ( ! function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
					throw new \Exception( 'WP Fastest Cache per-post purge function is unavailable in this version.' );
				}
				wpfc_clear_post_cache_by_id( $post_id );
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'WP Fastest Cache purged for post ID ' . $post_id,
				];

			default:
				throw new \Exception( 'Unknown WP Fastest Cache tool: ' . esc_html( $name ) );
		}
	}
}
