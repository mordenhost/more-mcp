<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPOptimize {

	public static function is_available() {
		return defined( 'WPO_VERSION' )
			&& function_exists( 'WP_Optimize' )
			&& method_exists( 'WP_Optimize', 'get_page_cache' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wp-optimize' ),
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
				'name'        => 'wpo_purge_cache',
				'description' => 'Purge the WP-Optimize page cache via the plugin\'s own WPO_Page_Cache::purge() (the same path its admin purge button uses), which deletes the cached pages and fires the plugin\'s wpo_cache_flush action. Reports whether page caching is actually enabled; no database optimization runs.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wpo_get_cache_status',
				'description' => 'Read the WP-Optimize page-cache status: whether page caching is enabled and working (plugin config plus the WP_CACHE / WPO_ADVANCED_CACHE drop-in state it checks), the cache size in bytes, and the cached file count. Figures come from the plugin\'s own get_cache_size(), which is transient-cached for up to a day. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use WP-Optimize tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WP-Optimize is not active' );
		}

		switch ( $name ) {
			case 'wpo_purge_cache':

				
				
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the WP-Optimize page cache.' );
				}
				$page_cache = WP_Optimize()->get_page_cache();
				if ( ! is_object( $page_cache ) || ! method_exists( $page_cache, 'is_enabled' ) || ! $page_cache->is_enabled() ) {
					throw new \Exception( 'WP-Optimize page caching is not enabled; there is nothing to purge.' );
				}
				if ( ! method_exists( $page_cache, 'purge' ) ) {
					throw new \Exception( 'WP-Optimize page cache purge is unavailable in this version.' );
				}
				$purged = $page_cache->purge();
				if ( false === $purged ) {
					throw new \Exception( 'WP-Optimize page cache purge failed (the filesystem delete did not complete).' );
				}
				return [
					'success' => true,
					'message' => 'WP-Optimize page cache purged.',
				];

			case 'wpo_get_cache_status':
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to read the WP-Optimize cache status.' );
				}
				return self::build_status();

			default:
				throw new \Exception( 'Unknown WP-Optimize tool: ' . esc_html( $name ) );
		}
	}

	private static function build_status() {
		$page_cache  = WP_Optimize()->get_page_cache();
		$is_object   = is_object( $page_cache );
		$enabled     = $is_object && method_exists( $page_cache, 'is_enabled' ) ? (bool) $page_cache->is_enabled() : false;

		$size       = null;
		$file_count = null;
		if ( $enabled && $is_object && method_exists( $page_cache, 'get_cache_size' ) ) {
			try {
				$cache_size = $page_cache->get_cache_size();
				if ( is_array( $cache_size ) ) {
					$size       = isset( $cache_size['size'] ) ? (int) $cache_size['size'] : null;
					$file_count = isset( $cache_size['file_count'] ) ? (int) $cache_size['file_count'] : null;
				}
			} catch ( \Throwable $e ) {

				$size       = null;
				$file_count = null;
			}
		}

		return [
			'provider'   => 'wp-optimize',
			'subsystem'  => 'page_cache',
			'enabled'    => $enabled,
			'size_bytes' => $size,
			'file_count' => $file_count,
		];
	}
}
