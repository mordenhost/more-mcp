<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privileged_Db implements Handler {

	const TOOL = 'wp_privileged_search_replace_run';

	const PAGE_SIZE = 200;

	const TIME_BUDGET = 20;

	const MAX_EXAMPLES = 8;

	const ALWAYS_SKIP_COLUMNS = array( 'user_pass', 'user_activation_key', 'user_login', 'user_nicename', 'option_name', 'meta_key' );

	const DEFAULT_SKIP_COLUMNS = array( 'guid' );

	const TEXT_TYPES = array( 'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json' );

	public static function get_tools(): array {
		return array(
			array(
				'name'        => self::TOOL,
				'description' => 'Find and replace text across the site database, safely: serialized PHP and JSON values are handled so nothing is corrupted. Runs as a preview (dry_run, the default) that reports every table, column and count with examples and returns a confirm value; to apply, call again with dry_run:false and that confirm. The plugin\'s own tables, password hashes, sessions and GUIDs are never changed. Take a backup first: a real run is not recorded in change history. To reverse a unique replacement, run it again with search and replace swapped. Needs manage_options and privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'           => array( 'type' => 'string', 'description' => 'Text to find (up to 500 characters). A regular expression body when regex is true.' ),
						'replace'          => array( 'type' => 'string', 'description' => 'Replacement text (may be empty to delete the match). With regex, $1 style references work.' ),
						'dry_run'          => array( 'type' => 'boolean', 'description' => 'Preview only. Defaults to true; pass false (with confirm) to write.' ),
						'confirm'          => array( 'type' => 'string', 'description' => 'The confirm value from the preview of these exact arguments. Required when dry_run is false.' ),
						'tables'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tables to cover, with or without the site prefix. Default: every table of this site.' ),
						'skip_columns'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Column names to leave alone. Default: guid.' ),
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

		$dry_run = \More_MCP\Access\Destructive::is_dry_run( self::TOOL, $args );
		$skip    = isset( $args['skip_columns'] ) && is_array( $args['skip_columns'] )
			? array_map( 'strval', $args['skip_columns'] )
			: self::DEFAULT_SKIP_COLUMNS;
		$skip    = array_values( array_unique( array_merge( $skip, self::ALWAYS_SKIP_COLUMNS ) ) );

		$tables = self::resolve_tables( isset( $args['tables'] ) && is_array( $args['tables'] ) ? $args['tables'] : array() );
		if ( ! $tables ) {
			throw new \Exception( 'No matching tables. Table names must belong to this site (they start with ' . esc_html( $wpdb->prefix ) . ').' );
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
			'skipped'   => array( 'objects' => 0, 'unparsable' => 0 ),
		);

		$started   = microtime( true );
		$report    = array();
		$totals    = array( 'tables' => 0, 'rows' => 0, 'replacements' => 0 );
		$complete  = true;
		$stopped   = null;
		$notes     = array();

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
			'dry_run'         => $dry_run,
			'search'          => $search,
			'replace'         => $replace,
			'tables_checked'  => count( $tables ),
			'tables_changed'  => $totals['tables'],
			'rows'            => $totals['rows'],
			'replacements'    => $totals['replacements'],
			'complete'        => $complete,
			'per_table'       => (object) $report,
			'examples'        => $ctx->examples,
			'skipped'         => array(
				'serialized_objects'   => $ctx->skipped['objects'],
				'unparsable_serialized' => $ctx->skipped['unparsable'],
				'tables'               => $notes,
			),
		);
		if ( $dry_run ) {
			$out['confirm'] = $confirm;
			$out['next']    = $totals['rows'] > 0
				? 'Nothing was changed. To apply, call again with dry_run:false and confirm set to the value above. Take a backup first.'
				: 'Nothing matched, so there is nothing to apply.';
		} else {
			$out['cache_flushed'] = true;
			$out['undo']          = 'Not recorded in change history. To reverse a unique replacement, run it again with search and replace swapped.';
		}
		if ( ! $complete ) {
			$out['stopped_in'] = $stopped;
			$out['note']       = 'Stopped at the time limit before every table was covered. Run the same call again to continue: rows already replaced no longer match.';
		}
		return $out;
	}

	

	
	private static function resolve_tables( array $requested ): array {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table discovery.
		$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		$existing = is_array( $existing ) ? $existing : array();

		$allowed = array();
		foreach ( $existing as $table ) {
			$suffix = substr( $table, strlen( $prefix ) );
			if ( 0 === strpos( $suffix, 'more_mcp_' ) ) {
				continue;
			}
			
			if ( is_multisite() && 1 === get_current_blog_id() && preg_match( '/^\d+_/', $suffix ) ) {
				continue;
			}
			$allowed[] = $table;
		}

		if ( ! $requested ) {
			return $allowed;
		}
		$picked = array();
		foreach ( $requested as $name ) {
			$name = (string) $name;
			foreach ( array( $name, $prefix . $name ) as $candidate ) {
				if ( in_array( $candidate, $allowed, true ) ) {
					$picked[] = $candidate;
					break;
				}
			}
		}
		return array_values( array_unique( $picked ) );
	}

	private static function describe( string $table ): array {
		global $wpdb;
		$name = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema read of a table name this class resolved.
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$name}`", ARRAY_A );

		$pk      = null;
		$pk_count = 0;
		$numeric = false;
		$text    = array();
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
		$out  = array( 'rows' => 0, 'replacements' => 0, 'columns' => array() );
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

		$suffix    = substr( $table, strlen( $wpdb->prefix ) );
		$key_col   = 'options' === $suffix ? 'option_name' : ( 'usermeta' === $suffix ? 'meta_key' : null );
		$select    = $columns;
		if ( null !== $key_col && ! in_array( $key_col, $select, true ) && in_array( $key_col, $info['columns'], true ) ) {
			$select[] = $key_col;
		}
		$cols_sql = implode( ', ', array_map( static function ( $c ) {
			return '`' . esc_sql( $c ) . '`';
		}, $select ) );

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

		$last = $info['numeric_pk'] ? 0 : '';
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
				if ( self::row_excluded( $table, $row ) ) {
					continue;
				}
				$changes = array();
				$row_n   = 0;
				foreach ( $columns as $c ) {
					if ( ! isset( $row[ $c ] ) || '' === $row[ $c ] ) {
						continue;
					}
					list( $new, $n ) = self::replace_value( (string) $row[ $c ], $ctx );
					if ( $n > 0 && $new !== $row[ $c ] ) {
						$changes[ $c ] = $new;
						$row_n        += $n;
						$out['columns'][ $c ] = ( $out['columns'][ $c ] ?? 0 ) + $n;
						if ( count( $ctx->examples ) < self::MAX_EXAMPLES ) {
							$ctx->examples[] = self::example( $table, $info['pk'], $row[ $info['pk'] ], $c, (string) $row[ $c ], $new );
						}
					}
				}
				if ( $changes ) {
					++$out['rows'];
					$out['replacements'] += $row_n;
					if ( $write ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The point of the tool.
						$wpdb->update( $table, $changes, array( $info['pk'] => $row[ $info['pk'] ] ) );
					}
				}
			}
			if ( count( $rows ) < self::PAGE_SIZE ) {
				break;
			}
		}
		return $out;
	}

	private static function row_excluded( string $table, array $row ): bool {
		global $wpdb;
		$suffix = substr( $table, strlen( $wpdb->prefix ) );
		if ( 'options' === $suffix && isset( $row['option_name'] ) ) {
			return 0 === strpos( (string) $row['option_name'], 'more_mcp_' ) || 0 === strpos( (string) $row['option_name'], '_transient_more_mcp_' );
		}
		if ( 'usermeta' === $suffix ) {
			return isset( $row['meta_key'] ) && in_array( (string) $row['meta_key'], array( 'session_tokens' ), true );
		}
		return false;
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
		if ( is_serialized( $value ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A value that only looks serialized must not raise a notice.
			$data = @unserialize( $value, array( 'allowed_classes' => array( 'stdClass' ) ) );
			if ( false === $data && 'b:0;' !== $value ) {
				if ( self::count_in( $value, $ctx ) > 0 ) {
					++$ctx->skipped['unparsable'];
				}
				return array( $value, 0 );
			}
			$count = 0;
			$data  = self::walk( $data, $ctx, $count );
			return $count > 0 ? array( serialize( $data ), $count ) : array( $value, 0 );
		}

		$count = 0;
		$new   = self::replace_text( $value, $ctx, $count );
		return array( $new, $count );
	}

	private static function walk( $data, $ctx, int &$count ) {
		if ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Nested value that may only look serialized.
				$inner = @unserialize( $data, array( 'allowed_classes' => array( 'stdClass' ) ) );
				if ( false !== $inner || 'b:0;' === $data ) {
					$inner_count = 0;
					$inner       = self::walk( $inner, $ctx, $inner_count );
					if ( $inner_count > 0 ) {
						$count += $inner_count;
						return serialize( $inner );
					}
					return $data;
				}
			}
			return self::replace_text( $data, $ctx, $count );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $item ) {
				$data[ $key ] = self::walk( $item, $ctx, $count );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			if ( 'stdClass' === get_class( $data ) ) {
				foreach ( get_object_vars( $data ) as $key => $item ) {
					$data->$key = self::walk( $item, $ctx, $count );
				}
				return $data;
			}
			
			$probe = 0;
			self::walk( (array) $data, $ctx, $probe );
			if ( $probe > 0 ) {
				++$ctx->skipped['objects'];
			}
			return $data;
		}
		return $data;
	}

	private static function replace_text( string $text, $ctx, int &$count ): string {
		$n = 0;
		if ( null !== $ctx->pattern ) {
			$result = preg_replace( $ctx->pattern, $ctx->replace, $text, -1, $n );
			if ( null === $result ) {
				return $text;
			}
			$count += $n;
			return $result;
		}

		$text = $ctx->ci ? str_ireplace( $ctx->search, $ctx->replace, $text, $n ) : str_replace( $ctx->search, $ctx->replace, $text, $n );
		$count += $n;

		if ( $ctx->json_pair && $ctx->json_pair[0] !== $ctx->search && '' !== $text && ( '{' === $text[0] || '[' === $text[0] ) ) {
			$n    = 0;
			$text = $ctx->ci ? str_ireplace( $ctx->json_pair[0], $ctx->json_pair[1], $text, $n ) : str_replace( $ctx->json_pair[0], $ctx->json_pair[1], $text, $n );
			$count += $n;
		}
		return $text;
	}

	private static function count_in( string $value, $ctx ): int {
		$n = 0;
		self::replace_text( $value, $ctx, $n );
		return $n;
	}

	private static function example( string $table, string $pk, $id, string $column, string $before, string $after ): array {
		$at = 0;
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
			hash_hmac( 'sha256', (string) wp_json_encode( array( $search, $replace, $regex, $ci, $tables, $skip ) ), wp_salt( 'auth' ) ),
			0,
			16
		);
	}
}
