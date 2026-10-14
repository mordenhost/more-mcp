<?php

namespace Mordenhost_MCP\Tools;

use Mordenhost_MCP\Platform\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privileged_Db implements Handler {

	const TOOL = 'wp_privileged_search_replace_run';

	const PAGE_SIZE = 200;

	const TIME_BUDGET = 20;

	const MAX_EXAMPLES = 8;

	const MAX_OTHER_TABLES = 50;

	const ALWAYS_SKIP_COLUMNS = array( 'user_pass', 'user_activation_key', 'user_login', 'user_nicename', 'post_password', 'option_name', 'meta_key' );

	const DEFAULT_SKIP_COLUMNS = array( 'guid' );

	const TEXT_TYPES = array( 'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json' );

	const CORE_TABLES = array( 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'options', 'links' );

	const USER_TABLES = array( 'users', 'usermeta' );

	const NETWORK_TABLES = array( 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'blog_versions' );

	const PROTECTED_KEYS = array(
		'active_plugins',
		'active_sitewide_plugins',
		'template',
		'stylesheet',
		'cron',
		'rewrite_rules',
		'db_version',
		'initial_db_version',
		'default_role',
		'users_can_register',
		'admin_email',
		'new_admin_email',
		'site_admins',
		'recovery_keys',
	);

	const PROTECTED_KEY_PARTS = array( 'capabilities', 'user_level', 'user_roles', 'session_tokens', 'activation_key', 'application_passwords', 'user_pass' );

	const MAX_DEPTH = 256;

	const MAX_NESTED = 4;

	const MODE_WRITE = 0;

	const MODE_KEY = 1;

	const MODE_PROBE = 2;

	public static function get_tools(): array {
		return array(
			array(
				'name'        => self::TOOL,
				'description' => 'Find and replace text across the site database, safely: serialized PHP values are rewritten with correct string lengths (no object is ever unserialized; typed objects are left alone) and JSON stays valid. Runs as a preview (dry_run, the default) that reports every table, column and count with examples and returns a confirm value; to apply, call again with dry_run:false and that confirm. Without tables it covers the WordPress core tables of this site (siteurl and home included); name other tables of this site to add them. Never touched: the plugin\'s own tables and settings, password hashes, post passwords, sessions, roles, credential-shaped options and meta, and on a network the shared user and network tables unless you are a network admin. GUIDs are skipped unless you override skip_columns. There is no undo: a real run is not recorded in change history, so take a database backup first. Swapping search and replace only reverses a replacement whose new text did not already exist. Needs manage_options and privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'           => array( 'type' => 'string', 'description' => 'Text to find (up to 500 characters). A regular expression body when regex is true.' ),
						'replace'          => array( 'type' => 'string', 'description' => 'Replacement text (may be empty to delete the match). With regex, $1 style references work.' ),
						'dry_run'          => array( 'type' => 'boolean', 'description' => 'Preview only. Defaults to true; pass false (with confirm) to write.' ),
						'confirm'          => array( 'type' => 'string', 'description' => 'The confirm value from the preview of these exact arguments. Required when dry_run is false.' ),
						'tables'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tables to cover, with or without the site prefix. Default: the WordPress core tables of this site. A name that is not a table of this site is refused.' ),
						'skip_columns'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Column names to leave alone. Default: guid. Passing a list replaces that default, so leave guid in it unless GUIDs really must change.' ),
						'regex'            => array( 'type' => 'boolean', 'description' => 'Treat search as a regular expression (PCRE, no delimiters).' ),
						'case_insensitive' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'search', 'replace' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return self::TOOL === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( self::TOOL !== $name ) {
			throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'manage_options capability required.' );
		}
		return self::run( $args );
	}

	

	private static function run( array $args ): array {
		global $wpdb;

		$search  = isset( $args['search'] ) ? (string) $args['search'] : '';
		$replace = isset( $args['replace'] ) ? (string) $args['replace'] : '';
		$regex   = ! empty( $args['regex'] ) && ! in_array( $args['regex'], array( 'false', '0' ), true );
		$ci      = ! empty( $args['case_insensitive'] ) && ! in_array( $args['case_insensitive'], array( 'false', '0' ), true );

		if ( '' === $search ) {
			throw new \Exception( 'search cannot be empty.' );
		}
		if ( strlen( $search ) > 500 || strlen( $replace ) > 5000 ) {
			throw new \Exception( 'search is limited to 500 characters and replace to 5000.' );
		}
		if ( $search === $replace ) {
			throw new \Exception( 'search and replace are identical, so there is nothing to do.' );
		}

		$pattern = null;
		if ( $regex ) {
			$pattern = '~' . str_replace( '~', '\~', $search ) . '~u' . ( $ci ? 'i' : '' );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Probing whether the caller's pattern compiles; failure is reported below.
			if ( false === @preg_match( $pattern, '' ) ) {
				throw new \Exception( 'search is not a valid regular expression.' );
			}
		}

		$dry_run = \Mordenhost_MCP\Access\Destructive::is_dry_run( self::TOOL, $args );
		$skip    = isset( $args['skip_columns'] ) && is_array( $args['skip_columns'] )
			? array_map( 'strval', $args['skip_columns'] )
			: self::DEFAULT_SKIP_COLUMNS;
		$skip    = array_values( array_unique( array_merge( $skip, self::ALWAYS_SKIP_COLUMNS ) ) );

		
		$held = array();
		if ( \Mordenhost_MCP\Access\Safety::force_draft() && ! in_array( 'post_status', $skip, true ) ) {
			$skip[] = 'post_status';
			$held[] = 'post_status';
		}

		$requested = isset( $args['tables'] ) && is_array( $args['tables'] ) ? $args['tables'] : array();
		$scope     = self::resolve_tables( $requested );
		$tables    = $scope['tables'];
		if ( ! $tables ) {
			throw new \Exception( 'No tables to search. Table names must belong to this site (they start with ' . esc_html( $wpdb->prefix ) . ').' );
		}

		$confirm = self::confirm_value( $search, $replace, $regex, $ci, $tables, $skip );
		if ( ! $dry_run ) {
			if ( empty( $args['confirm'] ) || ! hash_equals( $confirm, (string) $args['confirm'] ) ) {
				throw new \Exception( 'A real run needs the confirm value from a preview of exactly these arguments. Call this tool with dry_run:true (or no dry_run) first, review the result, then repeat the call with dry_run:false and confirm set to the value it returned.' );
			}
		}

		$ctx = (object) array(
			'search'    => $search,
			'replace'   => $replace,
			'pattern'   => $pattern,
			'ci'        => $ci,
			'json_pair' => self::json_variants( $search, $replace, $regex ),
			'examples'  => array(),
			'skipped'   => array( 'objects' => 0, 'unparsable' => 0, 'invalid_json' => 0 ),
		);

		$started  = microtime( true );
		$report   = array();
		$totals   = array( 'tables' => 0, 'examined' => 0, 'rows' => 0, 'replacements' => 0, 'errors' => 0 );
		$complete = true;
		$stopped  = null;
		$notes    = array();

		foreach ( $tables as $table ) {
			if ( microtime( true ) - $started > self::TIME_BUDGET ) {
				$complete = false;
				$stopped  = $table;
				break;
			}
			$result = self::process_table( $table, $ctx, $skip, ! $dry_run, $started );
			if ( isset( $result['note'] ) ) {
				$notes[ $table ] = $result['note'];
			}
			if ( ! empty( $result['stopped'] ) ) {
				$complete = false;
				$stopped  = $table;
			}
			$totals['examined'] += $result['examined'];
			$totals['errors']   += $result['errors'];
			if ( $result['rows'] > 0 ) {
				$report[ $table ] = array(
					'rows'         => $result['rows'],
					'replacements' => $result['replacements'],
					'columns'      => $result['columns'],
				);
				++$totals['tables'];
				$totals['rows']         += $result['rows'];
				$totals['replacements'] += $result['replacements'];
			}
			if ( ! $complete ) {
				break;
			}
		}

		if ( ! $dry_run && $totals['rows'] > 0 ) {
			wp_cache_flush();
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		$out = array(
			'dry_run'        => $dry_run,
			'search'         => $search,
			'replace'        => $replace,
			'scope'          => $requested ? 'named' : 'core',
			'tables'         => $tables,
			'tables_checked' => count( $tables ),
			'tables_changed' => $totals['tables'],
			'rows_examined'  => $totals['examined'],
			'rows'           => $totals['rows'],
			'replacements'   => $totals['replacements'],
			'complete'       => $complete,
			'per_table'      => (object) $report,
			'examples'       => $ctx->examples,
			'skipped'        => array(
				'serialized_objects'    => $ctx->skipped['objects'],
				'unparsable_serialized' => $ctx->skipped['unparsable'],
				'invalid_json'          => $ctx->skipped['invalid_json'],
				'tables'                => $notes,
			),
			'protected'      => 'Never read or changed: the plugin\'s own tables and settings, password hashes, post passwords, sessions, roles and capabilities, credential-shaped option and meta rows, and active_plugins, template, stylesheet, cron, rewrite_rules, default_role, users_can_register and admin_email.',
		);
		if ( $held ) {
			$out['skipped']['columns_held_by_force_draft'] = $held;
		}
		if ( ! $requested && $scope['others'] ) {
			$out['other_tables'] = array_slice( $scope['others'], 0, self::MAX_OTHER_TABLES );
			$out['other_tables_note'] = 'Tables of this site outside WordPress core (plugin data) were not searched. Name them in tables to include them.';
		}
		if ( $dry_run ) {
			$out['confirm'] = $confirm;
			$out['next']    = $totals['rows'] > 0
				? 'Nothing was changed. To apply, call again with dry_run:false and confirm set to the value above. There is no undo, so take a database backup first.'
				: 'Nothing matched, so there is nothing to apply.';
		} else {
			$out['write_errors']  = $totals['errors'];
			$out['cache_flushed'] = $totals['rows'] > 0;
			$out['undo']          = 'None: a real run is not recorded in change history. Restore the backup to undo it. Swapping search and replace only reverses a replacement whose new text did not already exist on the site.';
		}
		if ( ! $complete ) {
			$out['stopped_in'] = $stopped;
			$out['note']       = 'Stopped at the time limit before every table was covered. Run the same call again to continue: rows already replaced no longer match.';
		}
		return $out;
	}

	

	
	private static function resolve_tables( array $requested ): array {
		global $wpdb;
		$prefix = (string) $wpdb->prefix;
		$base   = isset( $wpdb->base_prefix ) ? (string) $wpdb->base_prefix : $prefix;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table discovery.
		$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		$existing = is_array( $existing ) ? $existing : array();

		$allowed = array();
		foreach ( $existing as $table ) {
			$table = (string) $table;
			if ( 0 !== strpos( $table, $prefix ) ) {
				continue;
			}
			$suffix = substr( $table, strlen( $prefix ) );
			if ( 0 === strpos( $suffix, Legacy::PREFIX ) || 0 === strpos( $suffix, Legacy::OLD_PREFIX ) ) {
				continue;
			}
			
			if ( is_multisite() && $prefix === $base && preg_match( '/^\d+_/', $suffix ) ) {
				continue;
			}

			if ( self::is_network_table( $table ) && ! current_user_can( 'manage_network_options' ) ) {
				continue;
			}
			$allowed[] = $table;
		}

		$core = array();
		foreach ( self::CORE_TABLES as $suffix ) {
			$core[] = $prefix . $suffix;
		}
		if ( ! is_multisite() ) {
			foreach ( self::USER_TABLES as $suffix ) {
				$core[] = $prefix . $suffix;
			}
		}

		if ( ! $requested ) {
			return array(
				'tables' => array_values( array_intersect( $allowed, $core ) ),
				'others' => array_values( array_diff( $allowed, $core ) ),
			);
		}

		$picked  = array();
		$refused = array();
		foreach ( $requested as $name ) {
			$name  = is_scalar( $name ) ? (string) $name : '';
			$found = false;
			foreach ( array( $name, $prefix . $name ) as $candidate ) {
				if ( in_array( $candidate, $allowed, true ) ) {
					$picked[] = $candidate;
					$found    = true;
					break;
				}
			}
			if ( ! $found ) {
				$refused[] = $name;
			}
		}
		if ( $refused ) {
			throw new \Exception(
				'Not a table this tool can search: ' . esc_html( implode( ', ', array_slice( $refused, 0, 10 ) ) ) . '. A table must exist and belong to this site (its name starts with ' . esc_html( $prefix ) . '). The plugin\'s own tables are never searched, and on a network the shared user and network tables need a network administrator. Nothing was changed.'
			);
		}
		return array(
			'tables' => array_values( array_unique( $picked ) ),
			'others' => array(),
		);
	}

	private static function is_network_table( string $table ): bool {
		global $wpdb;
		if ( ! is_multisite() ) {
			return false;
		}
		$base = isset( $wpdb->base_prefix ) ? (string) $wpdb->base_prefix : (string) $wpdb->prefix;
		if ( 0 !== strpos( $table, $base ) ) {
			return false;
		}
		return in_array( substr( $table, strlen( $base ) ), array_merge( self::USER_TABLES, self::NETWORK_TABLES ), true );
	}

	private static function describe( string $table ): array {
		global $wpdb;
		$name = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema read of a table name this class resolved.
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$name}`", ARRAY_A );

		$pk       = null;
		$pk_count = 0;
		$numeric  = false;
		$text     = array();
		foreach ( is_array( $cols ) ? $cols : array() as $col ) {
			$type = strtolower( (string) ( $col['Type'] ?? '' ) );
			if ( 'PRI' === ( $col['Key'] ?? '' ) ) {
				++$pk_count;
				$pk      = (string) $col['Field'];
				$numeric = (bool) preg_match( '/int|decimal|numeric/', $type );
			}
			$base = preg_replace( '/\(.*$/', '', $type );
			if ( in_array( trim( (string) $base ), self::TEXT_TYPES, true ) ) {
				$text[] = (string) $col['Field'];
			}
		}
		return array(
			'pk'         => 1 === $pk_count ? $pk : null,
			'numeric_pk' => $numeric,
			'columns'    => $text,
		);
	}

	

	
	private static function process_table( string $table, $ctx, array $skip, bool $write, float $started ): array {
		global $wpdb;
		$out  = array( 'examined' => 0, 'rows' => 0, 'replacements' => 0, 'errors' => 0, 'columns' => array() );
		$info = self::describe( $table );

		if ( null === $info['pk'] ) {
			$out['note'] = 'skipped: no single-column primary key';
			return $out;
		}
		$columns = array_values( array_diff( $info['columns'], $skip ) );
		if ( ! $columns ) {
			return $out;
		}

		$tname = esc_sql( $table );
		$pk    = esc_sql( $info['pk'] );

		$key_col = in_array( 'option_name', $info['columns'], true ) ? 'option_name' : ( in_array( 'meta_key', $info['columns'], true ) ? 'meta_key' : null );
		$select  = $columns;
		if ( null !== $key_col && ! in_array( $key_col, $select, true ) ) {
			$select[] = $key_col;
		}
		$cols_sql = implode(
			', ',
			array_map(
				static function ( $c ) {
					return '`' . esc_sql( $c ) . '`';
				},
				$select
			)
		);

		$where = '';
		if ( null === $ctx->pattern ) {
			$like  = '%' . $wpdb->esc_like( $ctx->search ) . '%';
			$likes = array();
			foreach ( $columns as $c ) {
				$likes[] = $wpdb->prepare( '`' . esc_sql( $c ) . '` LIKE %s', $like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column from the table's own schema.
			}
			
			if ( $ctx->json_pair && $ctx->json_pair[0] !== $ctx->search ) {
				$like_json = '%' . $wpdb->esc_like( $ctx->json_pair[0] ) . '%';
				foreach ( $columns as $c ) {
					$likes[] = $wpdb->prepare( '`' . esc_sql( $c ) . '` LIKE %s', $like_json ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column from the table's own schema.
				}
			}
			$where = ' AND (' . implode( ' OR ', $likes ) . ')';
		}

		$last  = $info['numeric_pk'] ? 0 : '';
		$types = $info['numeric_pk'] ? '%d' : '%s';

		while ( true ) {
			if ( microtime( true ) - $started > self::TIME_BUDGET ) {
				$out['stopped'] = true;
				break;
			}
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Names come from the table's schema; values are bound.
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT `{$pk}`, {$cols_sql} FROM `{$tname}` WHERE `{$pk}` > {$types}{$where} ORDER BY `{$pk}` ASC LIMIT %d", $last, self::PAGE_SIZE ),
				ARRAY_A
			);
			// phpcs:enable
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$last = $row[ $info['pk'] ];
				if ( null !== $key_col && isset( $row[ $key_col ] ) && self::protected_key( (string) $row[ $key_col ] ) ) {
					continue;
				}
				++$out['examined'];
				$changes = array();
				$row_n   = 0;
				$cols_n  = array();
				foreach ( $columns as $c ) {
					if ( ! isset( $row[ $c ] ) || '' === $row[ $c ] ) {
						continue;
					}
					list( $new, $n ) = self::replace_value( (string) $row[ $c ], $ctx );
					if ( $n > 0 && $new !== $row[ $c ] ) {
						$changes[ $c ] = $new;
						$cols_n[ $c ]  = $n;
						$row_n        += $n;
						if ( count( $ctx->examples ) < self::MAX_EXAMPLES ) {
							$ctx->examples[] = self::example( $table, $info['pk'], $row[ $info['pk'] ], $c, (string) $row[ $c ], $new );
						}
					}
				}
				if ( ! $changes ) {
					continue;
				}
				if ( $write ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The point of the tool.
					$written = $wpdb->update( $table, $changes, array( $info['pk'] => $row[ $info['pk'] ] ) );
					if ( false === $written ) {
						++$out['errors'];
						continue;
					}
				}
				++$out['rows'];
				$out['replacements'] += $row_n;
				foreach ( $cols_n as $c => $n ) {
					$out['columns'][ $c ] = ( $out['columns'][ $c ] ?? 0 ) + $n;
				}
			}
			if ( count( $rows ) < self::PAGE_SIZE ) {
				break;
			}
		}
		return $out;
	}

	private static function protected_key( string $key ): bool {
		$base = $key;
		foreach ( array( '_site_transient_timeout_', '_site_transient_', '_transient_timeout_', '_transient_' ) as $wrapper ) {
			if ( 0 === strpos( $base, $wrapper ) ) {
				$base = substr( $base, strlen( $wrapper ) );
				break;
			}
		}
		$bare = strtolower( ltrim( $base, '_' ) );
		foreach ( array( Legacy::PREFIX, Legacy::OLD_PREFIX ) as $own ) {
			if ( 0 === strpos( $bare, $own ) ) {
				return true;
			}
		}
		if ( in_array( $bare, self::PROTECTED_KEYS, true ) ) {
			return true;
		}
		foreach ( self::PROTECTED_KEY_PARTS as $part ) {
			if ( false !== strpos( $bare, $part ) ) {
				return true;
			}
		}
		return Options_Support::is_sensitive_key( $key );
	}

	

	
	private static function json_variants( string $search, string $replace, bool $regex ): ?array {
		if ( $regex ) {
			return null;
		}
		$enc = static function ( $text ) {
			$json = wp_json_encode( $text, JSON_UNESCAPED_UNICODE );
			return is_string( $json ) ? substr( $json, 1, -1 ) : $text;
		};
		return array( $enc( $search ), $enc( $replace ) );
	}

	private static function replace_value( string $value, $ctx ): array {
		if ( self::looks_serialized( $value ) ) {
			$result = self::rewrite_serialized( $value, $ctx, 0 );
			if ( null === $result ) {
				if ( self::count_in( $value, $ctx ) > 0 ) {
					++$ctx->skipped['unparsable'];
				}
				return array( $value, 0 );
			}
			return $result;
		}

		$count = 0;
		$new   = self::replace_text( $value, $ctx, $count );
		return array( $new, $count );
	}

	private static function looks_serialized( string $text ): bool {
		return is_serialized( $text ) || 1 === preg_match( '/^\s*C:\d+:"/', $text );
	}

	private static function rewrite_serialized( string $value, $ctx, int $nested ): ?array {
		$core = trim( $value );
		if ( '' === $core || ! self::decodes( $core ) ) {
			return null;
		}
		$state = (object) array(
			'count'  => 0,
			'probe'  => 0,
			'nested' => $nested,
		);
		$pos   = 0;
		$out   = self::rewrite_token( $core, $pos, $ctx, $state, self::MODE_WRITE, 0 );
		if ( null === $out || strlen( $core ) !== $pos ) {
			return null;
		}
		if ( 0 === $state->count ) {
			return array( $value, 0 );
		}
		
		if ( ! self::decodes( $out ) ) {
			return null;
		}
		$lead = strlen( $value ) - strlen( ltrim( $value ) );
		$tail = strlen( rtrim( $value ) );
		return array( substr( $value, 0, $lead ) . $out . substr( $value, $tail ), $state->count );
	}

	private static function decodes( string $data ): bool {
		if ( 'b:0;' === $data ) {
			return true;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Validation only, with no class allowed; a value that only looks serialized must not raise a notice.
		return false !== @unserialize( $data, array( 'allowed_classes' => false ) );
	}

	private static function rewrite_token( string $s, int &$pos, $ctx, $state, int $mode, int $depth ): ?string {
		if ( $depth > self::MAX_DEPTH || ! isset( $s[ $pos ] ) ) {
			return null;
		}
		$start = $pos;
		switch ( $s[ $pos ] ) {
			case 'N':
				if ( 'N;' !== substr( $s, $pos, 2 ) ) {
					return null;
				}
				$pos += 2;
				return 'N;';

			case 'b':
			case 'i':
			case 'd':
			case 'r':
			case 'R':
				if ( ! preg_match( '/\G(?:b:[01]|i:[+-]?\d+|d:(?:[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?|-?INF|NAN)|[rR]:\d+);/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$pos += strlen( $m[0] );
				return $m[0];

			case 's':
				if ( ! preg_match( '/\Gs:(\d{1,10}):"/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$len  = (int) $m[1];
				$from = $pos + strlen( $m[0] );
				if ( $len > strlen( $s ) - $from - 2 || '";' !== substr( $s, $from + $len, 2 ) ) {
					return null;
				}
				$pos = $from + $len + 2;
				if ( self::MODE_KEY === $mode ) {
					return substr( $s, $start, $pos - $start );
				}
				$text = substr( $s, $from, $len );
				if ( self::MODE_PROBE === $mode ) {
					$state->probe += self::count_in( $text, $ctx );
					return substr( $s, $start, $pos - $start );
				}
				$new = self::replace_leaf( $text, $ctx, $state );
				return $new === $text ? substr( $s, $start, $pos - $start ) : 's:' . strlen( $new ) . ':"' . $new . '";';

			case 'a':
				if ( ! preg_match( '/\Ga:(\d{1,10}):\{/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$pos    += strlen( $m[0] );
				$members = self::rewrite_members( $s, $pos, (int) $m[1], $ctx, $state, $mode, $depth );
				return null === $members ? null : $m[0] . $members;

			case 'O':
				if ( ! preg_match( '/\GO:(\d{1,10}):"/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$name_len = (int) $m[1];
				$name_at  = $pos + strlen( $m[0] );
				$class    = substr( $s, $name_at, $name_len );
				if ( strlen( $class ) !== $name_len || ! preg_match( '/\G":(\d{1,10}):\{/', $s, $m2, 0, $name_at + $name_len ) ) {
					return null;
				}
				$pos  = $name_at + $name_len + strlen( $m2[0] );
				$head = substr( $s, $start, $pos - $start );
				if ( self::MODE_WRITE === $mode && 0 !== strcasecmp( $class, 'stdClass' ) ) {

					
					
					$outer        = $state->probe;
					$state->probe = 0;
					$members      = self::rewrite_members( $s, $pos, (int) $m2[1], $ctx, $state, self::MODE_PROBE, $depth );
					if ( null !== $members && $state->probe > 0 ) {
						++$ctx->skipped['objects'];
					}
					$state->probe = $outer;
				} else {
					$members = self::rewrite_members( $s, $pos, (int) $m2[1], $ctx, $state, $mode, $depth );
				}
				return null === $members ? null : $head . $members;

			case 'C':

				if ( ! preg_match( '/\GC:(\d{1,10}):"/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$name_at = $pos + strlen( $m[0] ) + (int) $m[1];
				if ( ! preg_match( '/\G":(\d{1,10}):\{/', $s, $m2, 0, $name_at ) ) {
					return null;
				}
				$data_at  = $name_at + strlen( $m2[0] );
				$data_len = (int) $m2[1];
				if ( $data_len > strlen( $s ) - $data_at - 1 || '}' !== substr( $s, $data_at + $data_len, 1 ) ) {
					return null;
				}
				$pos = $data_at + $data_len + 1;
				if ( self::MODE_KEY !== $mode ) {
					$found = self::count_in( substr( $s, $data_at, $data_len ), $ctx );
					if ( self::MODE_PROBE === $mode ) {
						$state->probe += $found;
					} elseif ( $found > 0 ) {
						++$ctx->skipped['objects'];
					}
				}
				return substr( $s, $start, $pos - $start );

			case 'E':
				
				if ( ! preg_match( '/\GE:(\d{1,10}):"/', $s, $m, 0, $pos ) ) {
					return null;
				}
				$len  = (int) $m[1];
				$from = $pos + strlen( $m[0] );
				if ( $len > strlen( $s ) - $from - 2 || '";' !== substr( $s, $from + $len, 2 ) ) {
					return null;
				}
				$pos = $from + $len + 2;
				return substr( $s, $start, $pos - $start );
		}
		return null;
	}

	private static function rewrite_members( string $s, int &$pos, int $count, $ctx, $state, int $mode, int $depth ): ?string {
		$out = '';
		for ( $k = 0; $k < $count; $k++ ) {
			if ( ! isset( $s[ $pos ] ) || ( 'i' !== $s[ $pos ] && 's' !== $s[ $pos ] ) ) {
				return null;
			}
			$key = self::rewrite_token( $s, $pos, $ctx, $state, self::MODE_KEY, $depth + 1 );
			if ( null === $key ) {
				return null;
			}
			$value = self::rewrite_token( $s, $pos, $ctx, $state, $mode, $depth + 1 );
			if ( null === $value ) {
				return null;
			}
			$out .= $key . $value;
		}
		if ( ! isset( $s[ $pos ] ) || '}' !== $s[ $pos ] ) {
			return null;
		}
		++$pos;
		return $out . '}';
	}

	private static function replace_leaf( string $text, $ctx, $state ): string {
		if ( self::looks_serialized( $text ) ) {
			if ( $state->nested >= self::MAX_NESTED ) {
				if ( self::count_in( $text, $ctx ) > 0 ) {
					++$ctx->skipped['unparsable'];
				}
				return $text;
			}
			$inner = self::rewrite_serialized( $text, $ctx, $state->nested + 1 );
			if ( null !== $inner ) {
				$state->count += $inner[1];
				return $inner[0];
			}
			
		}
		$n   = 0;
		$new = self::replace_text( $text, $ctx, $n );
		$state->count += $n;
		return $new;
	}

	private static function replace_text( string $text, $ctx, int &$count, bool $guard = true ): string {
		$n = 0;
		if ( null !== $ctx->pattern ) {
			$result = preg_replace( $ctx->pattern, $ctx->replace, $text, -1, $n );
			if ( null === $result || 0 === $n ) {
				return $text;
			}
			if ( $guard && self::json_shaped( $text ) && self::is_json( $text ) && ! self::is_json( $result ) ) {
				++$ctx->skipped['invalid_json'];
				return $text;
			}
			$count += $n;
			return $result;
		}

		$search_esc  = $ctx->json_pair ? $ctx->json_pair[0] : $ctx->search;
		$replace_esc = $ctx->json_pair ? $ctx->json_pair[1] : $ctx->replace;
		$json        = $guard && $ctx->json_pair && self::json_shaped( $text )
			&& ( self::contains( $text, $ctx->search, $ctx->ci ) || self::contains( $text, $search_esc, $ctx->ci ) )
			&& self::is_json( $text );

		if ( $json ) {
			$new = self::swap( $search_esc, $replace_esc, $text, $n, $ctx->ci );
			if ( $search_esc !== $ctx->search ) {
				$m   = 0;
				$new = self::swap( $ctx->search, $replace_esc, $new, $m, $ctx->ci );
				$n  += $m;
			}
			if ( $n > 0 && ! self::is_json( $new ) ) {
				++$ctx->skipped['invalid_json'];
				return $text;
			}
			$count += $n;
			return $new;
		}

		$new = self::swap( $ctx->search, $ctx->replace, $text, $n, $ctx->ci );

		if ( $ctx->json_pair && $search_esc !== $ctx->search && self::json_shaped( $text ) ) {
			$m   = 0;
			$new = self::swap( $search_esc, $replace_esc, $new, $m, $ctx->ci );
			$n  += $m;
		}
		$count += $n;
		return $new;
	}

	private static function swap( string $search, string $replace, string $text, int &$n, bool $ci ): string {
		return $ci ? str_ireplace( $search, $replace, $text, $n ) : str_replace( $search, $replace, $text, $n );
	}

	private static function contains( string $text, string $needle, bool $ci ): bool {
		if ( '' === $needle ) {
			return false;
		}
		return false !== ( $ci ? stripos( $text, $needle ) : strpos( $text, $needle ) );
	}

	private static function json_shaped( string $text ): bool {
		return '' !== $text && ( '{' === $text[0] || '[' === $text[0] );
	}

	private static function is_json( string $text ): bool {
		return null !== json_decode( $text );
	}

	private static function count_in( string $value, $ctx ): int {
		$n = 0;
		self::replace_text( $value, $ctx, $n, false );
		return $n;
	}

	private static function example( string $table, string $pk, $id, string $column, string $before, string $after ): array {
		$at  = 0;
		$len = min( strlen( $before ), strlen( $after ) );
		while ( $at < $len && $before[ $at ] === $after[ $at ] ) {
			++$at;
		}
		$from = max( 0, $at - 40 );
		$clip = static function ( $text ) use ( $from ) {
			return ( $from > 0 ? '…' : '' ) . substr( $text, $from, 120 ) . ( strlen( $text ) > $from + 120 ? '…' : '' );
		};
		return array(
			'table'  => $table,
			'where'  => $pk . '=' . $id,
			'column' => $column,
			'before' => $clip( $before ),
			'after'  => $clip( $after ),
		);
	}

	private static function confirm_value( string $search, string $replace, bool $regex, bool $ci, array $tables, array $skip ): string {
		sort( $tables );
		sort( $skip );
		return substr(
			hash_hmac( 'sha256', (string) wp_json_encode( array( $search, $replace, $regex, $ci, $tables, $skip, (int) get_current_user_id() ) ), wp_salt( 'auth' ) ),
			0,
			16
		);
	}
}
