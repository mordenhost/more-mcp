<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPSuperCache {

	const DEFAULT_FILE_PREFIX = 'wp-cache-';

	public static function is_available() {
		return defined( 'WPCACHEHOME' ) && function_exists( 'wp_cache_clean_cache' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wp-super-cache' ),
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
				'name'        => 'wpsc_purge_all',
				'description' => 'Purge this site\'s WP Super Cache page cache via the plugin\'s wp_cache_clean_cache(), the same purge as the "Delete Cache" button on its settings page. On a multisite network only the current site\'s cache is purged. Use after a major site update, content migration, or when troubleshooting stale content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wpsc_purge_url',
				'description' => 'Purge the WP Super Cache entry for a single URL on this site. Resolves the URL to a WordPress post or page and clears that post\'s cached page and its author archive page via wpsc_delete_post_cache(), leaving the rest of the cache intact. The home page and category archives are not cleared; use wpsc_purge_all when listing pages are stale too.',
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
			throw new \Exception( 'You do not have permission to use WP Super Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Super Cache is not active' );
		}

		switch ( $name ) {
			case 'wpsc_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the WP Super Cache.' );
				}

				$file_prefix = isset( $GLOBALS['file_prefix'] ) && is_string( $GLOBALS['file_prefix'] ) && '' !== $GLOBALS['file_prefix']
					? $GLOBALS['file_prefix']
					: self::DEFAULT_FILE_PREFIX;
				wp_cache_clean_cache( $file_prefix );
				return [
					'success' => true,
					'message' => 'WP Super Cache cache purged for this site.',
				];

			case 'wpsc_purge_url':
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
				if ( ! function_exists( 'wpsc_delete_post_cache' ) ) {
					throw new \Exception( 'WP Super Cache per-post purge function is unavailable in this version.' );
				}
				wpsc_delete_post_cache( $post_id );
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'WP Super Cache purged for post ID ' . $post_id,
				];

			default:
				throw new \Exception( 'Unknown WP Super Cache tool: ' . esc_html( $name ) );
		}
	}
}
