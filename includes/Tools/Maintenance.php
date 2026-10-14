<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Maintenance implements Handler {

	const TOOLS = array( 'wp_flush_rewrite_rules', 'wp_delete_expired_transients', 'wp_flush_object_cache', 'wp_get_db_size' );

	const DEFAULT_TABLE_LIMIT = 25;

	public static function get_tools(): array {
		$none = array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
		return array(
			array(
				'name'        => 'wp_flush_rewrite_rules',
				'description' => 'Regenerate WordPress\'s rewrite rules from the post types, taxonomies and endpoints registered right now. Use it when pretty URLs return 404 after a post type, taxonomy or slug change. Soft flush only: .htaccess and the web server configuration are not touched. Safe to repeat; running it again gives the same result. Returns the number of rules before and after. Needs manage_options.',
				'inputSchema' => $none,
			),
			array(
				'name'        => 'wp_delete_expired_transients',
				'description' => 'Delete transients whose expiry time has passed (cached values WordPress would discard on next read, and clears out on its own once a day). Transients that are still live are not touched. Returns how many expired entries there were before and after. Needs manage_options.',
				'inputSchema' => $none,
			),
			array(
				'name'        => 'wp_flush_object_cache',
				'description' => 'Empty the WordPress object cache (wp_cache_flush). With a persistent cache (Redis, Memcached) the whole cache is emptied and refills as pages load, so the site may be slower for a moment; on a shared cache server that can include other sites using the same cache. No stored data is lost. Without a persistent cache only this request\'s memory is cleared. Needs manage_options, or manage_network_options on a multisite network (the flush covers every site).',
				'inputSchema' => $none,
			),
			array(
				'name'        => 'wp_get_db_size',
				'description' => 'Report the size of this site\'s database tables (data, index, reclaimable space, estimated rows), largest first, with totals, the size of autoloaded options and the number of expired transients. Read-only. Needs manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Largest tables to list (1-200, default 25). Totals always cover every table.' ),
					),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_flush_rewrite_rules':
				return self::flush_rewrite_rules();
			case 'wp_delete_expired_transients':
				return self::delete_expired_transients();
			case 'wp_flush_object_cache':
				return self::flush_object_cache();
			case 'wp_get_db_size':
				return self::db_size( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function require_cap( string $cap, string $what ): void {
		if ( ! current_user_can( $cap ) ) {
			throw new \Exception( 'You do not have permission to ' . esc_html( $what ) . '. The ' . esc_html( $cap ) . ' capability is required.' );
		}
	}

	

	private static function flush_rewrite_rules(): array {
		self::require_cap( 'manage_options', 'flush rewrite rules' );

		$before = get_option( 'rewrite_rules' );

		flush_rewrite_rules( false );
		$after = get_option( 'rewrite_rules' );

		$structure = (string) get_option( 'permalink_structure', '' );
		return array(
			'flushed'             => true,
			'rules_before'        => is_array( $before ) ? count( $before ) : 0,
			'rules_after'         => is_array( $after ) ? count( $after ) : 0,
			'permalink_structure' => $structure,
			'note'                => '' === $structure
				? 'This site uses plain permalinks (?p=123), which need no rewrite rules.'
				: 'Rewrite rules were rebuilt from what is registered now. .htaccess and the web server configuration were not touched.',
		);
	}

	

	private static function delete_expired_transients(): array {
		self::require_cap( 'manage_options', 'delete expired transients' );
		if ( ! function_exists( 'delete_expired_transients' ) ) {
			throw new \Exception( 'This WordPress version has no delete_expired_transients() (added in 4.9).' );
		}

		$before = self::expired_transient_count();

		
		delete_expired_transients( true );
		$after = self::expired_transient_count();

		return array(
			'expired_before' => $before,
			'expired_after'  => $after,
			'deleted'        => max( 0, $before - $after ),
			'object_cache'   => (bool) wp_using_ext_object_cache(),
			'note'           => 'Only transients past their expiry time were deleted. Live transients were not touched.',
		);
	}

	private static function expired_transient_count(): int {
		global $wpdb;
		$now   = time();
		$count = 0;
		foreach ( array( '_transient_timeout_', '_site_transient_timeout_' ) as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A count of expired rows; nothing to cache.
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( $prefix ) . '%', $now ) );
		}
		if ( is_multisite() && is_main_site() && is_main_network() && isset( $wpdb->sitemeta ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A count of expired rows; nothing to cache.
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d", $wpdb->esc_like( '_site_transient_timeout_' ) . '%', $now ) );
		}
		return $count;
	}

	

	private static function flush_object_cache(): array {
		$network = is_multisite();
		self::require_cap( $network ? 'manage_network_options' : 'manage_options', $network ? 'flush the object cache of the whole network' : 'flush the object cache' );

		$persistent = (bool) wp_using_ext_object_cache();
		$flushed    = (bool) wp_cache_flush();

		if ( ! $flushed ) {
			$note = 'The object cache reported that it could not be emptied. Check the cache backend (for example with the cache plugin\'s own status page).';
		} elseif ( $persistent ) {
			$note = 'The persistent object cache was emptied and refills as pages load. No stored data was lost.';
		} else {
			$note = 'This site has no persistent object cache, so only this request\'s in-memory cache was cleared. There was nothing else to empty.';
		}
		return array(
			'flushed'          => $flushed,
			'persistent_cache' => $persistent,
			'scope'            => $network ? 'network' : 'site',
			'note'             => $note,
		);
	}

	

	private static function db_size( array $args ): array {
		self::require_cap( 'manage_options', 'read the database size' );
		global $wpdb;

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : self::DEFAULT_TABLE_LIMIT;
		$limit = max( 1, min( 200, $limit ) );

		$prefix = (string) $wpdb->prefix;
		$base   = isset( $wpdb->base_prefix ) ? (string) $wpdb->base_prefix : $prefix;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table statistics; read once per call.
		$status = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $prefix ) . '%' ), ARRAY_A );

		$tables = array();
		$totals = array(
			'tables'          => 0,
			'data_bytes'      => 0,
			'index_bytes'     => 0,
			'total_bytes'     => 0,
			'free_bytes'      => 0,
			'rows_estimated'  => 0,
		);
		foreach ( is_array( $status ) ? $status : array() as $row ) {
			$name = (string) ( $row['Name'] ?? '' );
			if ( '' === $name || 0 !== strpos( $name, $prefix ) ) {
				continue;
			}
			
			if ( is_multisite() && $prefix === $base && preg_match( '/^\d+_/', substr( $name, strlen( $prefix ) ) ) ) {
				continue;
			}
			$data  = (int) ( $row['Data_length'] ?? 0 );
			$index = (int) ( $row['Index_length'] ?? 0 );
			$free  = (int) ( $row['Data_free'] ?? 0 );
			$rows  = (int) ( $row['Rows'] ?? 0 );

			$tables[] = array(
				'table'          => $name,
				'engine'         => (string) ( $row['Engine'] ?? '' ),
				'rows_estimated' => $rows,
				'data_bytes'     => $data,
				'index_bytes'    => $index,
				'total_bytes'    => $data + $index,
				'total_mb'       => round( ( $data + $index ) / 1048576, 2 ),
				'free_bytes'     => $free,
			);
			++$totals['tables'];
			$totals['data_bytes']     += $data;
			$totals['index_bytes']    += $index;
			$totals['total_bytes']    += $data + $index;
			$totals['free_bytes']     += $free;
			$totals['rows_estimated'] += $rows;
		}
		usort(
			$tables,
			static function ( $a, $b ) {
				return $b['total_bytes'] <=> $a['total_bytes'];
			}
		);
		$totals['total_mb'] = round( $totals['total_bytes'] / 1048576, 2 );

		$out = array(
			'totals'             => $totals,
			'tables'             => array_slice( $tables, 0, $limit ),
			'autoload'           => self::autoload_size(),
			'expired_transients' => self::expired_transient_count(),
			'note'               => 'Row counts are the storage engine\'s estimates. free_bytes is space the table holds but does not use.',
		);
		if ( count( $tables ) > $limit ) {
			$out['tables_not_listed'] = count( $tables ) - $limit;
		}
		if ( ! $tables ) {
			$out['note'] = 'This database did not report table statistics (SHOW TABLE STATUS). Sizes are unavailable here.';
		}
		return $out;
	}

	private static function autoload_size(): array {
		global $wpdb;
		
		$values = function_exists( 'wp_autoload_values_to_autoload' ) ? (array) wp_autoload_values_to_autoload() : array( 'yes' );
		$values = array_values( array_filter( array_map( 'strval', $values ) ) );
		if ( ! $values ) {
			$values = array( 'yes' );
		}
		$in = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per autoload value.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(LENGTH(option_value)), 0) AS bytes FROM {$wpdb->options} WHERE autoload IN ({$in})", $values ), ARRAY_A );
		$n     = is_array( $row ) ? (int) ( $row['n'] ?? 0 ) : 0;
		$bytes = is_array( $row ) ? (int) ( $row['bytes'] ?? 0 ) : 0;
		return array(
			'options' => $n,
			'bytes'   => $bytes,
			'kb'      => round( $bytes / 1024, 1 ),
		);
	}
}
