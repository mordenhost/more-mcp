<?php

namespace Mordenhost_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Legacy {

	const OLD_PREFIX = 'more_mcp_';

	const PREFIX = 'mordenhost_mcp_';

	const OLD_CONSTANT_PREFIX = 'MORE_MCP_';

	const OLD_REST_NAMESPACE = 'more-mcp';

	const FLAG = 'mordenhost_mcp_legacy_migrated';

	const PENDING_TABLES = 'mordenhost_mcp_legacy_tables_pending';

	const NETWORK_FLAG = 'mordenhost_mcp_legacy_migrated_network';

	const OWN_CONSTANTS = array( 'VERSION', 'PLUGIN_DIR', 'PLUGIN_URL', 'PLUGIN_FILE', 'PLUGIN_BASENAME', 'DOCS_URL' );

	const KEPT_TABLES = 'mordenhost_mcp_legacy_tables_kept';

	const LOCK = 'mordenhost_mcp_legacy_lock';

	const LOCK_TTL = 600;

	private static $serving_legacy = false;

	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'maybe_migrate' ), 1 );
		
		add_action( 'plugins_loaded', array( self::class, 'finish_tables' ), 6 );
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action( 'network_admin_notices', array( self::class, 'admin_notice' ) );
	}

	public static function register_stand_down(): void {
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action( 'network_admin_notices', array( self::class, 'admin_notice' ) );
	}

	public static function alias_constants(): void {
		$constants = get_defined_constants( true );
		if ( empty( $constants['user'] ) ) {
			return;
		}
		$skip = array();
		foreach ( self::OWN_CONSTANTS as $own ) {
			$skip[ self::OLD_CONSTANT_PREFIX . $own ] = true;
		}
		$len = strlen( self::OLD_CONSTANT_PREFIX );
		foreach ( $constants['user'] as $name => $value ) {
			if ( 0 !== strncmp( $name, self::OLD_CONSTANT_PREFIX, $len ) || isset( $skip[ $name ] ) ) {
				continue;
			}
			$new = 'MORDENHOST_MCP_' . substr( $name, $len );
			if ( ! defined( $new ) ) {
				define( $new, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- Always MORDENHOST_MCP_<NAME>, built above.
			}
		}
	}

	public static function legacy_basename(): string {
		return defined( 'MORE_MCP_PLUGIN_BASENAME' ) ? (string) constant( 'MORE_MCP_PLUGIN_BASENAME' ) : '';
	}

	public static function activate_legacy_handover( bool $network_wide ): bool {
		$basename = self::legacy_basename();
		if ( '' === $basename ) {
			return true;
		}
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$network_active = is_multisite() && is_plugin_active_for_network( $basename );
		if ( $network_active && ! $network_wide ) {
			return false;
		}
		deactivate_plugins( $basename, false, $network_active );
		return true;
	}

	public static function deactivate_on_current_site(): void {
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $active as $plugin ) {
			$plugin = (string) $plugin;
			if ( self::is_legacy_plugin_file( $plugin ) ) {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				deactivate_plugins( $plugin, false, false );
			}
		}
	}

	private static function is_legacy_plugin_file( string $plugin ): bool {
		if ( '' !== self::legacy_basename() && self::legacy_basename() === $plugin ) {
			return true;
		}
		return 'more-mcp.php' === basename( $plugin );
	}

	public static function maybe_migrate(): void {
		if ( get_option( self::FLAG ) || '' !== self::legacy_basename() ) {
			return;
		}
		self::migrate_site();
	}

	public static function migrate_site(): bool {
		global $wpdb;

		if ( ! self::acquire_lock() ) {
			return false;
		}

		$migrated_options = self::migrate_options( $wpdb->options, 'option_name' );
		self::migrate_tables();
		foreach ( array( $wpdb->postmeta, $wpdb->termmeta, $wpdb->commentmeta ) as $table ) {
			self::migrate_meta_keys( $table );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off rename of the skills post type.
		$wpdb->update( $wpdb->posts, array( 'post_type' => self::PREFIX . 'skill' ), array( 'post_type' => self::OLD_PREFIX . 'skill' ) );

		if ( ! is_multisite() || ! get_site_option( self::NETWORK_FLAG ) ) {
			self::migrate_meta_keys( $wpdb->usermeta );
			if ( is_multisite() ) {
				self::migrate_options( $wpdb->sitemeta, 'meta_key' );
				update_site_option( self::NETWORK_FLAG, MORDENHOST_MCP_VERSION );
			}
		}

		self::clear_legacy_cron();

		
		foreach ( $migrated_options as $name ) {
			$value = get_option( $name );
			$fixed = self::rename_values( $value );
			if ( $fixed !== $value ) {
				update_option( $name, $fixed );
			}
		}

		wp_cache_flush();
		
		update_option( self::PREFIX . 'flush_rewrites', 1 );
		update_option( self::FLAG, MORDENHOST_MCP_VERSION );
		self::release_lock();
		return true;
	}

	private static function acquire_lock(): bool {
		global $wpdb;
		$now = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic lock row; core table name.
		if ( 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK, (string) $now ) ) ) {
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock row read past the options cache.
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
		if ( $since > $now - self::LOCK_TTL ) {
			return false;
		}
		
		$where = array(
			'option_name'  => self::LOCK,
			'option_value' => (string) $since,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-set on the lock row.
		return 1 === (int) $wpdb->update( $wpdb->options, array( 'option_value' => (string) $now ), $where );
	}

	private static function release_lock(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock row.
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK ) );
	}

	private static function migrate_options( string $table, string $name_column ): array {
		global $wpdb;
		$renamed  = array();
		$prefixes = array( '', '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_' );
		$likes    = array();
		foreach ( $prefixes as $prefix ) {
			$likes[] = $wpdb->esc_like( $prefix . self::OLD_PREFIX ) . '%';
		}
		$where = implode( ' OR ', array_fill( 0, count( $likes ), "{$name_column} LIKE %s" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table and column names are core identifiers passed by migrate_site(); one placeholder per pattern.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT {$name_column} FROM {$table} WHERE {$where}", $likes ) );
		foreach ( (array) $names as $old ) {
			$old = (string) $old;
			if ( 0 !== strpos( $old, self::OLD_PREFIX ) ) {
				
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup of the old copy's caches.
				$wpdb->delete( $table, array( $name_column => $old ) );
				continue;
			}
			$new = self::PREFIX . substr( $old, strlen( self::OLD_PREFIX ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See migrate_site(): the old copy's value wins.
			$wpdb->delete( $table, array( $name_column => $new ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off rename.
			$wpdb->update( $table, array( $name_column => $new ), array( $name_column => $old ) );
			$renamed[] = $new;
		}
		return $renamed;
	}

	private static function migrate_tables(): void {
		global $wpdb;
		$pending = (array) get_option( self::PENDING_TABLES, array() );
		$kept    = (array) get_option( self::KEPT_TABLES, array() );
		foreach ( self::legacy_tables() as $suffix ) {
			$old = $wpdb->prefix . self::OLD_PREFIX . $suffix;
			$new = $wpdb->prefix . self::PREFIX . $suffix;
			if ( in_array( $suffix, $kept, true ) ) {
				continue;
			}
			if ( self::table_exists( $new ) ) {
				if ( self::row_count( $new ) > 0 ) {
					$kept[] = $suffix;
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from the prefix and a fixed string.
				$wpdb->query( 'DROP TABLE `' . esc_sql( $new ) . '`' );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names built from the prefix and fixed strings.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $old ) . '` RENAME TO `' . esc_sql( $new ) . '`' );
			if ( self::table_exists( $old ) ) {
				$pending[] = $suffix;
			}
		}
		$pending = array_values( array_unique( array_map( 'strval', $pending ) ) );
		if ( $pending ) {
			update_option( self::PENDING_TABLES, $pending, false );
		}
		self::save_kept( $kept );
	}

	private static function save_kept( array $kept ): void {
		$kept = array_values( array_unique( array_map( 'strval', $kept ) ) );
		if ( $kept ) {
			update_option( self::KEPT_TABLES, $kept, false );
		}
	}

	public static function finish_tables(): void {
		global $wpdb;
		$pending = get_option( self::PENDING_TABLES );
		if ( ! is_array( $pending ) || ! $pending || '' !== self::legacy_basename() ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			return;
		}
		
		wp_cache_delete( self::PENDING_TABLES, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$pending = get_option( self::PENDING_TABLES );
		if ( ! is_array( $pending ) || ! $pending ) {
			self::release_lock();
			return;
		}
		$left = array();
		$kept = (array) get_option( self::KEPT_TABLES, array() );
		foreach ( $pending as $suffix ) {
			$suffix = (string) $suffix;
			$old    = $wpdb->prefix . self::OLD_PREFIX . $suffix;
			$new    = $wpdb->prefix . self::PREFIX . $suffix;
			if ( ! self::table_exists( $old ) ) {
				continue;
			}
			if ( ! self::table_exists( $new ) && 'memory' === $suffix && class_exists( '\Mordenhost_MCP\Knowledge\Memory_Store' ) ) {

				delete_option( \Mordenhost_MCP\Knowledge\Memory_Store::SCHEMA_OPTION );
				\Mordenhost_MCP\Knowledge\Memory_Store::ensure_table();
			}
			if ( ! self::table_exists( $new ) ) {
				$left[] = $suffix;
				continue;
			}
			if ( self::row_count( $new ) > 0 ) {
				$kept[] = $suffix;
				continue;
			}
			$columns = array_values( array_intersect( self::columns( $old ), self::columns( $new ) ) );
			if ( $columns ) {
				$list = '`' . implode( '`, `', array_map( 'esc_sql', $columns ) ) . '`';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers built from the prefix, fixed strings and the tables' own column names, each through esc_sql().
				$wpdb->query( 'INSERT INTO `' . esc_sql( $new ) . "` ({$list}) SELECT {$list} FROM `" . esc_sql( $old ) . '`' );
			}

			if ( $columns && self::row_count( $new ) >= self::row_count( $old ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from the prefix and a fixed string.
				$wpdb->query( 'DROP TABLE `' . esc_sql( $old ) . '`' );
			} else {
				$kept[] = $suffix;
			}
		}
		if ( $left ) {
			update_option( self::PENDING_TABLES, $left, false );
		} else {
			delete_option( self::PENDING_TABLES );
		}
		self::save_kept( $kept );
		self::release_lock();
	}

	private static function legacy_tables(): array {
		global $wpdb;
		$old_base = $wpdb->prefix . self::OLD_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old_base ) . '%' ) );
		$out    = array();
		foreach ( (array) $tables as $table ) {
			$table = (string) $table;
			if ( 0 === strpos( $table, $old_base ) && preg_match( '/^\w+$/', $table ) ) {
				$out[] = substr( $table, strlen( $old_base ) );
			}
		}
		return $out;
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function row_count( string $table ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from the prefix and a fixed string.
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
	}

	private static function columns( string $table ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name built from the prefix and a fixed string, through esc_sql().
		return array_map( 'strval', (array) $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`' ) );
	}

	private static function migrate_meta_keys( string $table ): void {
		global $wpdb;
		$plain  = $wpdb->esc_like( self::OLD_PREFIX ) . '%';
		$hidden = $wpdb->esc_like( '_' . self::OLD_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Core meta table name passed by migrate_site(); anchored LIKE on the indexed key.
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$table} WHERE meta_key LIKE %s OR meta_key LIKE %s", $plain, $hidden ) );
		foreach ( (array) $keys as $old ) {
			$old = (string) $old;
			$pos = strpos( $old, self::OLD_PREFIX );
			if ( false === $pos || $pos > 1 ) {
				continue;
			}
			$new = substr_replace( $old, self::PREFIX, $pos, strlen( self::OLD_PREFIX ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off rename of this plugin's own keys.
			$wpdb->update( $table, array( 'meta_key' => $new ), array( 'meta_key' => $old ) );
		}
	}

	private static function clear_legacy_cron(): void {
		$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
		$hooks = array();
		foreach ( (array) $crons as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, self::OLD_PREFIX ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	public static function rename_values( $value ) {
		if ( is_string( $value ) ) {
			return 0 === strpos( $value, self::OLD_PREFIX ) ? self::PREFIX . substr( $value, strlen( self::OLD_PREFIX ) ) : $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ is_string( $key ) ? self::rename_values( $key ) : $key ] = self::rename_values( $item );
		}
		return $out;
	}

	public static function register_legacy_routes( callable $handler ): void {
		if ( '' !== self::legacy_basename() ) {
			return; 
		}
		$args = array(
			'methods'             => array( 'GET', 'POST', 'DELETE', 'OPTIONS' ),
			'callback'            => static function ( $request ) use ( $handler ) {
				self::$serving_legacy = true;
				try {
					return call_user_func( $handler, $request );
				} finally {
					self::$serving_legacy = false;
				}
			},
			'permission_callback' => '__return_true', 
		);
		register_rest_route( self::OLD_REST_NAMESPACE . '/v1', '/mcp', $args );
		register_rest_route( self::OLD_REST_NAMESPACE, '/v1', $args );
	}

	public static function serving_legacy(): bool {
		return self::$serving_legacy;
	}

	public static function resource_metadata_suffix(): string {
		return self::$serving_legacy ? '/wp-json/' . self::OLD_REST_NAMESPACE . '/v1/mcp' : '';
	}

	public static function is_legacy_resource_path( string $request_uri ): bool {
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		return (bool) preg_match( '#/\.well-known/oauth-protected-resource/(?:.*/)?wp-json/' . preg_quote( self::OLD_REST_NAMESPACE, '#' ) . '(/|$)#i', $path );
	}

	public static function admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( '' !== self::legacy_basename() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'The previous version of this plugin (More MCP / Mordenhost MCP Server) is still active. Deactivate it: your settings, connections and logs move over to Mordenhost MCP automatically, then the old copy can be deleted.', 'mordenhost-mcp' )
			);
		}
		$kept = get_option( self::KEPT_TABLES );
		if ( is_array( $kept ) && $kept ) {
			global $wpdb;
			$names = array();
			foreach ( $kept as $suffix ) {
				$names[] = $wpdb->prefix . self::OLD_PREFIX . $suffix;
			}
			printf(
				'<div class="notice notice-warning"><p>%s <code>%s</code></p></div>',
				esc_html__( 'Mordenhost MCP could not move some data from the previous version, so these tables were left in place unchanged (they are removed when the plugin is deleted):', 'mordenhost-mcp' ),
				esc_html( implode( ', ', $names ) )
			);
		}
	}
}
