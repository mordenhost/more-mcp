<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LiteSpeed Cache MCP Integration
 *
 * Registers MCP tools for the LiteSpeed Cache plugin.
 * Only loaded when LiteSpeed Cache is active.
 *
 * Unlike ForgeCache, LiteSpeed exposes no static cache API — its public
 * contract is a set of WordPress action hooks (litespeed_purge_all,
 * litespeed_purge_url), documented at
 * https://docs.litespeedtech.com/lscache/lscwp/api/ . We drive those hooks
 * rather than calling internal classes, so the integration survives the
 * plugin's frequent internal refactors.
 *
 * There is deliberately no ls_get_cache_stats: LiteSpeed's cache spans disk,
 * object cache, and its CDN with no stable public accessor for a file/size
 * count, and inventing one would report figures that do not reflect what is
 * actually cached. Purge is the operation with a supported public API, so
 * purge is what we expose.
 */
class LiteSpeed {

	/**
	 * Check if LiteSpeed Cache is available.
	 *
	 * LSCWP_V is the version constant the plugin defines on load; the
	 * LiteSpeed\Core class is its main bootstrap. Either is sufficient.
	 */
	public static function is_available() {
		return defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Core' );
	}

	/**
	 * Get tool definitions for MCP tools/list response.
	 */
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

	/**
	 * Execute a LiteSpeed Cache MCP tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 * @return mixed Result data.
	 * @throws \Exception If tool fails.
	 */
	public static function execute_tool( $name, $args ) {
		// Umbrella cap check fires BEFORE the active-check, matching
		// ForgeCache: without this order a Subscriber-tier OAuth Bearer would
		// receive "LiteSpeed Cache is not active" and learn whether the
		// integration is present. The per-case caps below still enforce the
		// finer-grained gate (manage_options for site-wide purge; edit_post
		// for per-post purge).
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use LiteSpeed Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'LiteSpeed Cache is not active' );
		}

		switch ( $name ) {
			case 'ls_purge_all':
				// Site-wide purge is admin-tier and destructive (forces
				// re-generation across the whole site).
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
				// Purging the cache for a specific post requires edit_post on
				// the target (so a Subscriber can't purge an admin's draft cache).
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to purge the cache for this post.' );
				}
				// litespeed_purge_url takes the URL string, not a post ID. We
				// resolve to a post first only to run the per-post capability
				// gate; the purge itself is keyed on the URL.
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
