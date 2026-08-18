<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ForgeCache MCP Integration
 *
 * Registers MCP tools for the ForgeCache page caching plugin.
 * Only loaded when ForgeCache is active.
 */
class ForgeCache {

	/**
	 * Check if ForgeCache is available.
	 */
	public static function is_available() {
		return class_exists( 'ForgeCache_Cache' );
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
				'name'        => 'fc_clear_cache',
				'description' => 'Clear the entire ForgeCache page cache. Use after a major site update, content migration, or when troubleshooting stale content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'fc_get_cache_stats',
				'description' => 'Get ForgeCache statistics: total cached files, total size on disk, oldest and newest cached entries.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'fc_purge_url',
				'description' => 'Purge the ForgeCache entry for a single URL on this site. Resolves the URL to a WordPress post or page and clears its cached HTML.',
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
	 * Execute a ForgeCache MCP tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 * @return mixed Result data.
	 * @throws \Exception If tool fails.
	 */
	public static function execute_tool( $name, $args ) {
		// umbrella cap check fires BEFORE the active-check. Without
		// this order a Subscriber-tier OAuth Bearer would receive "ForgeCache
		// is not active" and learn whether the integration is present. The
		// per-case caps below still enforce the finer-grained gate
		// (manage_options for site-wide flushes; edit_post for per-post purge).
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use ForgeCache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'ForgeCache is not active' );
		}

		switch ( $name ) {
			case 'fc_clear_cache':
				// cache management is admin-tier; flushing site cache
				// is destructive (forces re-generation across the whole site).
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to clear the ForgeCache page cache.' );
				}
				\ForgeCache_Cache::clear_all_cache_static();
				return [
					'success' => true,
					'message' => 'ForgeCache page cache cleared.',
				];

			case 'fc_get_cache_stats':
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to view ForgeCache stats.' );
				}
				$stats = \ForgeCache_Cache::get_cache_stats();
				return [
					'total_files'      => (int) ( $stats['total_files'] ?? 0 ),
					'total_size_bytes' => (int) ( $stats['total_size'] ?? 0 ),
					'total_size_human' => size_format( (int) ( $stats['total_size'] ?? 0 ) ),
					'oldest_file'      => isset( $stats['oldest_file'] ) && $stats['oldest_file'] ? gmdate( 'Y-m-d H:i:s', (int) $stats['oldest_file'] ) : null,
					'newest_file'      => isset( $stats['newest_file'] ) && $stats['newest_file'] ? gmdate( 'Y-m-d H:i:s', (int) $stats['newest_file'] ) : null,
				];

			case 'fc_purge_url':
				$url = esc_url_raw( $args['url'] ?? '' );
				if ( empty( $url ) ) {
					throw new \Exception( 'url is required' );
				}
				$post_id = url_to_postid( $url );
				if ( ! $post_id ) {
					throw new \Exception( 'Could not resolve URL to a WordPress post or page on this site: ' . esc_html( $url ) );
				}
				// purging the cache for a specific post requires
				// edit_post on the target (so a Subscriber can't purge an
				// admin's draft cache).
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to purge the cache for this post.' );
				}
				$cache = \ForgeCache_Cache::instance();
				if ( method_exists( $cache, 'clear_post_cache' ) ) {
					$cache->clear_post_cache( $post_id );
				}
				return [
					'success' => true,
					'url'     => $url,
					'post_id' => $post_id,
					'message' => 'Cache cleared for post ID ' . $post_id,
				];

			default:
				throw new \Exception( 'Unknown ForgeCache tool: ' . esc_html( $name ) );
		}
	}
}
