<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoptimize {

	public static function is_available() {
		return defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) && class_exists( '\autoptimizeCache' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'autoptimize' ),
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
				'name'        => 'ao_purge_all',
				'description' => 'Delete all of Autoptimize\'s cached aggregated CSS/JS files via the plugin\'s own autoptimizeCache::clearall(), which also fires its purge hooks. Use after theme or plugin asset changes leave combined files stale.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'ao_get_cache_stats',
				'description' => 'Read Autoptimize\'s own cache statistics: file count, total size in bytes, and when its statistics scan last ran. Figures come from the plugin\'s autoptimizeCache::stats() (cached in its autoptimize_stats transient for up to an hour), not from a fresh directory walk. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'ao_get_settings',
				'description' => 'Read Autoptimize\'s optimization settings through its own autoptimizeConfig: which pipelines are on (HTML, JavaScript, CSS), the defer/aggregate flags, and the CDN URL. Individual allowlisted keys only, exactly as the plugin\'s settings screen stores them (1/0). Never an exclusion list, never the Critical CSS credential. Configuration only, read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Autoptimize tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Autoptimize is not active' );
		}

		switch ( $name ) {
			case 'ao_purge_all':

				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to purge the Autoptimize cache.' );
				}
				$cleared = \autoptimizeCache::clearall();
				if ( false === $cleared ) {

					
					throw new \Exception( 'Autoptimize cache purge did not run (cache directory unavailable or purging disabled by a filter).' );
				}
				return [
					'success' => true,
					'message' => 'Autoptimize cache purged.',
				];

			case 'ao_get_cache_stats':
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to read Autoptimize cache statistics.' );
				}
				return self::build_stats();

			case 'ao_get_settings':
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to read Autoptimize settings.' );
				}
				return self::build_settings();

			default:
				throw new \Exception( 'Unknown Autoptimize tool: ' . esc_html( $name ) );
		}
	}

	private static function build_stats() {
		$cache_available = \autoptimizeCache::cacheavail();
		$stats           = $cache_available ? \autoptimizeCache::stats() : 0;

		$is_array = is_array( $stats );
		return [
			'provider'       => 'autoptimize',
			'cache_available' => (bool) $cache_available,
			'file_count'     => $is_array ? (int) ( $stats[0] ?? 0 ) : 0,
			'size_bytes'     => $is_array ? (int) ( $stats[1] ?? 0 ) : 0,
			'last_scan'      => $is_array && isset( $stats[2] ) ? (int) $stats[2] : null,
		];
	}

	private static function build_settings() {
		$bool_keys = array(
			'autoptimize_html',
			'autoptimize_js',
			'autoptimize_js_aggregate',
			'autoptimize_js_defer_not_aggregate',
			'autoptimize_js_defer_inline',
			'autoptimize_css',
			'autoptimize_css_aggregate',
			'autoptimize_css_defer',
			'autoptimize_cache_nogzip',
			'autoptimize_optimize_logged',
		);

		$settings = array();
		if ( class_exists( '\autoptimizeConfig' ) && method_exists( '\autoptimizeConfig', 'instance' ) ) {
			try {
				$config = \autoptimizeConfig::instance();
				foreach ( $bool_keys as $key ) {
					if ( ! is_object( $config ) || ! method_exists( $config, 'get' ) ) {
						$settings[ $key ] = null;
						continue;
					}
					$raw = $config->get( $key );
					$settings[ $key ] = null === $raw ? null : (bool) $raw;
				}
				$cdn = method_exists( $config, 'get' ) ? $config->get( 'autoptimize_cdn_url' ) : null;
			} catch ( \Throwable $e ) {
				$config = null;
				$cdn    = null;
				foreach ( $bool_keys as $key ) {
					$settings[ $key ] = null;
				}
			}
		} else {
			foreach ( $bool_keys as $key ) {
				$settings[ $key ] = null;
			}
			$cdn = null;
		}

		return [
			'provider' => 'autoptimize',
			'settings' => $settings,
			'cdn_url'  => is_null( $cdn ) || '' === $cdn ? '' : (string) $cdn,
		];
	}
}
