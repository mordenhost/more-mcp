<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RedisObjectCache {

	public static function is_available() {
		return defined( 'WP_REDIS_VERSION' ) && function_exists( 'redis_object_cache' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'redis-cache' ),
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
				'name'        => 'redis_get_status',
				'description' => 'Read the Redis object-cache status on this site: whether the object-cache.php drop-in is present, valid, and outdated, whether Redis is currently connected, the Redis server version, the client library in use, and whether the plugin is disabled via WP_REDIS_DISABLED. Read-only diagnostic; no settings are returned or changed.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'redis_flush_object_cache',
				'description' => 'Flush the Redis object cache via the WordPress core wp_cache_flush() API, which routes through the Redis Object Cache drop-in and honours its WP_REDIS_SELECTIVE_FLUSH / WP_REDIS_PREFIX configuration. When selective flush is not enabled this flushes the ENTIRE Redis database if it is shared with other applications. Use to clear stale cached options, aliases, and transients.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Redis Object Cache tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Redis Object Cache is not active' );
		}

		switch ( $name ) {
			case 'redis_get_status':
				return self::build_status();

			case 'redis_flush_object_cache':

				
				
				if ( ! current_user_can( 'manage_options' ) ) {
					throw new \Exception( 'You do not have permission to flush the Redis object cache.' );
				}
				$flushed = wp_cache_flush();
				if ( false === $flushed ) {
					throw new \Exception( 'Redis object cache flush failed (Redis may be disconnected).' );
				}
				return [
					'success' => true,
					'message' => 'Redis object cache flushed.',
				];

			default:
				throw new \Exception( 'Unknown Redis Object Cache tool: ' . esc_html( $name ) );
		}
	}

	private static function build_status() {
		$plugin = redis_object_cache();

		$dropin_exists = is_object( $plugin ) && method_exists( $plugin, 'object_cache_dropin_exists' )
			? (bool) $plugin->object_cache_dropin_exists()
			: false;
		$dropin_valid = $dropin_exists && is_object( $plugin ) && method_exists( $plugin, 'validate_object_cache_dropin' )
			? (bool) $plugin->validate_object_cache_dropin()
			: false;
		$dropin_outdated = $dropin_valid && is_object( $plugin ) && method_exists( $plugin, 'object_cache_dropin_outdated' )
			? (bool) $plugin->object_cache_dropin_outdated()
			: false;

		
		
		$connected = false;
		if ( isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) && method_exists( $GLOBALS['wp_object_cache'], 'redis_status' ) ) {
			try {
				$connected = (bool) $GLOBALS['wp_object_cache']->redis_status();
			} catch ( \Throwable $e ) {
				$connected = false;
			}
		}

		$redis_version = is_object( $plugin ) && method_exists( $plugin, 'get_redis_version' )
			? $plugin->get_redis_version()
			: null;
		$client = is_object( $plugin ) && method_exists( $plugin, 'get_redis_client_name' )
			? $plugin->get_redis_client_name()
			: null;

		
		
		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			$state = 'disabled';
		} elseif ( ! $dropin_exists ) {
			$state = 'not_enabled';
		} elseif ( $dropin_outdated ) {
			$state = 'dropin_outdated';
		} elseif ( ! $dropin_valid ) {
			$state = 'dropin_invalid';
		} elseif ( $connected ) {
			$state = 'connected';
		} else {
			$state = 'not_connected';
		}

		return [
			'provider'         => 'redis-cache',
			'plugin_version'   => defined( 'WP_REDIS_VERSION' ) ? WP_REDIS_VERSION : '',
			'state'            => $state,
			'dropin'           => [
				'exists'   => $dropin_exists,
				'valid'    => $dropin_valid,
				'outdated' => $dropin_outdated,
			],
			'connected'        => $connected,
			'redis_version'    => is_null( $redis_version ) ? '' : (string) $redis_version,
			'client'           => is_null( $client ) ? '' : (string) $client,
			'external_object_cache' => function_exists( 'wp_using_ext_object_cache' ) ? (bool) wp_using_ext_object_cache() : null,
		];
	}
}
