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
			[
				'name'        => 'redis_get_cache_stats',
				'description' => 'Read the Redis object-cache hit/miss statistics for the current request from the drop-in\'s own info() accessor: hits, misses, hit ratio, bytes held in the local runtime cache, cache calls, time spent, and any recorded errors. Counters reset on every page load, so these describe the request that ran the tool, not a historical window. Aggregate counters only, never a key name, value, or credential. Read-only.',
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

			case 'redis_get_cache_stats':
				return self::build_cache_stats();

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

	private static function build_cache_stats() {
		if ( ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) || ! method_exists( $GLOBALS['wp_object_cache'], 'info' ) ) {
			return [
				'provider'  => 'redis-cache',
				'available' => false,
				'message'   => 'The Redis object-cache drop-in is not active, so no hit/miss counters exist for this request.',
			];
		}

		try {
			$info = $GLOBALS['wp_object_cache']->info();
		} catch ( \Throwable $e ) {
			return [
				'provider'  => 'redis-cache',
				'available' => false,
				'message'   => 'The Redis object-cache statistics could not be read.',
			];
		}
		$info = is_object( $info ) ? (array) $info : ( is_array( $info ) ? $info : array() );

		$errors = null;
		if ( isset( $info['errors'] ) && is_array( $info['errors'] ) && ! empty( $info['errors'] ) ) {
			
			$errors = array_values( array_map( 'strval', $info['errors'] ) );
		}

		return [
			'provider'  => 'redis-cache',
			'available' => true,

			
			'scope'     => 'current_request',
			'hits'      => isset( $info['hits'] ) && is_numeric( $info['hits'] ) ? (int) $info['hits'] : null,
			'misses'    => isset( $info['misses'] ) && is_numeric( $info['misses'] ) ? (int) $info['misses'] : null,
			'ratio'     => isset( $info['ratio'] ) && is_numeric( $info['ratio'] ) ? (float) $info['ratio'] : null,
			'bytes'     => isset( $info['bytes'] ) && is_numeric( $info['bytes'] ) ? (int) $info['bytes'] : null,
			'time'      => isset( $info['time'] ) && is_numeric( $info['time'] ) ? (float) $info['time'] : null,
			'calls'     => isset( $info['calls'] ) && is_numeric( $info['calls'] ) ? (int) $info['calls'] : null,
			'errors'    => $errors,
		];
	}
}
