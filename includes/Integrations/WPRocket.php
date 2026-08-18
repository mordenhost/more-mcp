<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRocket {

	public static function is_available() {
		return defined( 'WP_ROCKET_VERSION' ) && function_exists( 'rocket_clean_domain' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wpr_purge_all',
				'description' => 'Purge the entire WP Rocket cache. Use after a major site update, content migration, or when troubleshooting stale content. If preloading is enabled in WP Rocket, this also starts a preload so the site is re-cached rather than left cold.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wpr_purge_url',
				'description' => 'Purge the WP Rocket cache for a single URL on this site. Resolves the URL to a WordPress post or page and clears its cached HTML together with the related archive pages WP Rocket associates with it, leaving the rest of the cache intact.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url' => [ 'type' => 'string', 'description' => 'Full URL on this site (e.g. https://yoursite.com/about/)' ],
					],
					'required'   => [ 'url' ],
				],
			],
			[
				'name'        => 'wpr_purge_minify',
				'description' => 'Purge only WP Rocket\'s minified/combined CSS and JS assets, leaving cached HTML intact. Use after changing theme or plugin styles/scripts when the page cache is still fine but combined assets are stale.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use WP Rocket tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Rocket is not active' );
		}

		switch ( $name ) {
			case 'wpr_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the WP Rocket cache.' );
				}
				rocket_clean_domain();
				return [
					'success' => true,
					'message' => 'WP Rocket cache purged.',
				];

			case 'wpr_purge_url':
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
				if ( ! function_exists( 'rocket_clean_post' ) ) {
					throw new \Exception( 'WP Rocket per-post purge function is unavailable in this version.' );
				}
				rocket_clean_post( $post_id );
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'WP Rocket cache purged for post ID ' . $post_id,
				];

			case 'wpr_purge_minify':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge WP Rocket minified assets.' );
				}
				if ( ! function_exists( 'rocket_clean_minify' ) ) {
					throw new \Exception( 'WP Rocket minify purge function is unavailable in this version.' );
				}
				rocket_clean_minify();
				return [
					'success' => true,
					'message' => 'WP Rocket minified CSS/JS assets purged.',
				];

			default:
				throw new \Exception( 'Unknown WP Rocket tool: ' . esc_html( $name ) );
		}
	}
}
