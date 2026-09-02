<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W3TotalCache {

	public static function is_available() {
		return defined( 'W3TC_VERSION' ) && function_exists( 'w3tc_flush_all' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'w3-total-cache' ),
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
				'name'        => 'w3tc_purge_all',
				'description' => 'Purge every W3 Total Cache cache type at once (page cache, minified assets, database cache, object cache) via the plugin\'s w3tc_flush_all() API. Use after a major site update, content migration, or when troubleshooting stale content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'w3tc_purge_url',
				'description' => 'Purge the W3 Total Cache page-cache entry for a single URL on this site. Resolves the URL to a WordPress post or page and purges that post\'s cached page and its associated archive pages via w3tc_flush_post(), leaving the rest of the cache intact.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url' => [ 'type' => 'string', 'description' => 'Full URL on this site (e.g. https://yoursite.com/about/)' ],
					],
					'required'   => [ 'url' ],
				],
			],
			[
				'name'        => 'w3tc_purge_minify',
				'description' => 'Purge only W3 Total Cache\'s minified/combined CSS and JS assets via w3tc_minify_flush(), leaving cached pages and other cache types intact. Use after changing theme or plugin styles/scripts when cached HTML is still fine but combined assets are stale.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'w3tc_purge_object_cache',
				'description' => 'Flush only the object cache (Redis/Memcached/APCu when W3 Total Cache manages it) via w3tc_objectcache_flush(), leaving the page cache and minified assets intact.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use W3 Total Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'W3 Total Cache is not active' );
		}

		switch ( $name ) {
			case 'w3tc_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the W3 Total Cache.' );
				}
				w3tc_flush_all();
				return [
					'success' => true,
					'message' => 'All W3 Total Cache cache types purged.',
				];

			case 'w3tc_purge_url':
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
				w3tc_flush_post( $post_id );
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'W3 Total Cache purged for post ID ' . $post_id,
				];

			case 'w3tc_purge_minify':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge W3 Total Cache minified assets.' );
				}
				w3tc_minify_flush();
				return [
					'success' => true,
					'message' => 'W3 Total Cache minified CSS/JS assets purged.',
				];

			case 'w3tc_purge_object_cache':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to flush the W3 Total Cache object cache.' );
				}
				w3tc_objectcache_flush();
				return [
					'success' => true,
					'message' => 'W3 Total Cache object cache flushed.',
				];

			default:
				throw new \Exception( 'Unknown W3 Total Cache tool: ' . esc_html( $name ) );
		}
	}
}
