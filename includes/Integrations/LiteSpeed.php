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

			default:
				throw new \Exception( 'Unknown LiteSpeed Cache tool: ' . esc_html( $name ) );
		}
	}
}
