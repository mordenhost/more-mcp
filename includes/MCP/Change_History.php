<?php

namespace More_MCP\MCP;

use More_MCP\Access\Classifier;
use More_MCP\Tools\Options_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Change_History {

	const TYPE_POST      = 'post';
	const TYPE_OPTION    = 'option';
	const TYPE_THEME_MOD = 'theme_mod';
	const TYPE_CSS       = 'custom_css';
	const TYPE_TERM      = 'term';
	const TYPE_COMMENT   = 'comment';
	const TYPE_USER      = 'user';

	const POST_BY_ID = array(
		'wp_update_post',
		'wp_update_page',
		'wp_replace_in_post',
		'wp_replace_in_page',
		'wp_delete_post',
		'wp_delete_page',
		'wp_update_media',
		'wp_process_image',
		'wp_delete_media',
	);

	const POST_CREATORS = array(
		'wp_create_post',
		'wp_create_page',
		'elementor_clone_page',
		'elementor_import_template',
		'blocks_create_reusable',
	);

	const COMMENT_BY_COMMENT_ID = array(
		'wp_approve_comment',
		'wp_spam_comment',
		'wp_trash_comment',
		'wp_unapprove_comment',
		'wp_unspam_comment',
		'wp_untrash_comment',
	);

	const VOLATILE_META = array( '_edit_lock', '_edit_last', '_encloseme', '_pingme', '_wp_old_slug', '_wp_old_date' );

	const MAX_STATE_BYTES = 4194304;

	const DEFAULT_RETENTION_DAYS = 90;
	const DEFAULT_ROW_CAP        = 5000;

	const POST_FIELDS = array(
		'post_title',
		'post_content',
		'post_excerpt',
		'post_status',
		'post_name',
		'post_parent',
		'menu_order',
		'post_author',
		'post_password',
		'comment_status',
		'ping_status',
		'post_type',
		'post_date',
		'post_date_gmt',
	);

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'more_mcp_history';
	}

	

	
	public static function begin( string $tool, array $args ): ?array {
		if ( ! self::enabled() || 'wp_history_apply' === $tool || ! empty( $args['dry_run'] ) ) {
			return null;
		}
		if ( Classifier::is_read_only( $tool ) ) {
			return null;
		}

		$targets = self::targets( $tool, $args );
		$creates = in_array( $tool, self::POST_CREATORS, true );
		if ( ! $targets && ! $creates ) {
			return null;
		}

		$before = array();
		foreach ( $targets as $key => $target ) {
			$before[ $key ] = self::capture( $target['type'], $target['id'] );
		}
		return array(
			'tool'    => $tool,
			'targets' => $targets,
			'before'  => $before,
			'creates' => $creates,
		);
	}

	public static function finish( ?array $context, $result ): void {
		if ( null === $context ) {
			return;
		}
		try {
			$targets = $context['targets'];
			if ( ! empty( $context['creates'] ) && is_array( $result ) ) {
				$new_id = (int) ( $result['id'] ?? $result['post_id'] ?? $result['template_id'] ?? 0 );
				if ( $new_id > 0 && get_post( $new_id ) ) {
					$targets[ self::TYPE_POST . ':' . $new_id ] = array(
						'type' => self::TYPE_POST,
						'id'   => (string) $new_id,
					);
					$context['before'][ self::TYPE_POST . ':' . $new_id ] = null;
				}
			}

			foreach ( $targets as $key => $target ) {
				$before = $context['before'][ $key ] ?? null;
				$after  = self::capture( $target['type'], $target['id'] );
				if ( self::same( $before, $after ) ) {
					continue;
				}
				self::record(
					array(
						'tool'        => $context['tool'],
						'object_type' => $target['type'],
						'object_id'   => $target['id'],
						'before'      => $before,
						'after'       => $after,
					)
				);
			}
		} catch ( \Throwable $e ) {
			unset( $e ); 
		}
	}

	public static function enabled(): bool {
		$settings = get_option( 'more_mcp_settings', array() );
		return ! is_array( $settings ) || ! isset( $settings['change_history'] ) || ! empty( $settings['change_history'] );
	}

	public static function targets( string $tool, array $args ): array {
		$out = array();
		$add = static function ( string $type, $id ) use ( &$out ) {
			$id = (string) $id;
			if ( '' !== $id && '0' !== $id ) {
				$out[ $type . ':' . $id ] = array(
					'type' => $type,
					'id'   => $id,
				);
			}
		};

		if ( in_array( $tool, self::POST_BY_ID, true ) ) {
			$add( self::TYPE_POST, (int) ( $args['id'] ?? 0 ) );
		} elseif ( 'wp_restore_revision' === $tool ) {
			$revision = wp_get_post_revision( (int) ( $args['revision_id'] ?? 0 ) );
			if ( $revision ) {
				$add( self::TYPE_POST, (int) $revision->post_parent );
			}
		} elseif ( isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ) {

			$add( self::TYPE_POST, (int) $args['post_id'] );
		}

		switch ( $tool ) {
			case 'wp_update_option':
				$name = sanitize_key( (string) ( $args['name'] ?? '' ) );
				if ( '' !== $name && ! Options_Support::is_sensitive_key( $name ) ) {
					$add( self::TYPE_OPTION, $name );
				}
				break;
			case 'wp_update_theme_mod':
			case 'wp_delete_theme_mod':
				$add( self::TYPE_THEME_MOD, sanitize_key( (string) ( $args['mod_name'] ?? '' ) ) );
				break;
			case 'wp_update_custom_css':
				$add( self::TYPE_CSS, '' !== (string) ( $args['theme_slug'] ?? '' ) ? sanitize_key( (string) $args['theme_slug'] ) : get_stylesheet() );
				break;
			case 'wp_update_term':
			case 'wp_delete_term':
				$add( self::TYPE_TERM, (int) ( $args['id'] ?? 0 ) );
				break;
			case 'wp_update_term_meta':
			case 'wp_delete_term_meta':
			case 'wp_update_term_seo_meta':
				$add( self::TYPE_TERM, (int) ( $args['term_id'] ?? 0 ) );
				break;
			case 'wp_update_comment':
			case 'wp_delete_comment':
				$add( self::TYPE_COMMENT, (int) ( $args['id'] ?? 0 ) );
				break;
			case 'wp_update_user':
				$add( self::TYPE_USER, (int) ( $args['id'] ?? 0 ) );
				break;
		}
		if ( in_array( $tool, self::COMMENT_BY_COMMENT_ID, true ) ) {
			$add( self::TYPE_COMMENT, (int) ( $args['comment_id'] ?? 0 ) );
		}

		return $out;
	}

	

	
	public static function capture( string $type, string $id ): ?array {
		switch ( $type ) {
			case self::TYPE_POST:
				return self::capture_post( (int) $id );
			case self::TYPE_OPTION:
				return self::capture_option( $id );
			case self::TYPE_THEME_MOD:
				$mods = get_theme_mods();
				return is_array( $mods ) && array_key_exists( $id, $mods ) ? array( 'value' => $mods[ $id ] ) : null;
			case self::TYPE_CSS:
				return array( 'css' => (string) wp_get_custom_css( $id ) );
			case self::TYPE_TERM:
				return self::capture_term( (int) $id );
			case self::TYPE_COMMENT:
				return self::capture_comment( (int) $id );
			case self::TYPE_USER:
				return self::capture_user( (int) $id );
		}
		return null;
	}

	private static function capture_post( int $id ): ?array {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || 'revision' === $post->post_type ) {
			return null;
		}

		$fields = array();
		foreach ( self::POST_FIELDS as $field ) {
			$fields[ $field ] = $post->$field;
		}

		
		if ( '0000-00-00 00:00:00' === $post->post_date_gmt && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			unset( $fields['post_date'] );
		}

		$meta      = array();
		$meta_skip = array();
		foreach ( (array) get_post_meta( $id ) as $key => $values ) {
			$key = (string) $key;
			if ( in_array( $key, self::VOLATILE_META, true ) ) {
				continue;
			}
			if ( Options_Support::is_sensitive_key( $key ) ) {
				$meta_skip[] = $key;
				continue;
			}
			$meta[ $key ] = array_map( 'strval', (array) $values );
		}
		ksort( $meta );

		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			$ids = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) ) {
				$ids = array_map( 'intval', $ids );
				sort( $ids );
				if ( $ids ) {
					$terms[ $taxonomy ] = $ids;
				}
			}
		}

		$state = array(
			'fields' => $fields,
			'meta'   => $meta,
			'terms'  => $terms,
		);
		if ( $meta_skip ) {
			$state['meta_withheld'] = $meta_skip;
		}

		$encoded = wp_json_encode( $state );
		if ( is_string( $encoded ) && strlen( $encoded ) > self::MAX_STATE_BYTES ) {
			$state['meta']           = array_fill_keys( array_keys( $meta ), '[omitted: state too large]' );
			$state['meta_truncated'] = true;
		}
		return $state;
	}

	private static function capture_option( string $name ): ?array {
		$sentinel = new \stdClass();
		$value    = get_option( $name, $sentinel );
		return $sentinel === $value ? null : array( 'value' => $value );
	}

	private static function capture_term( int $id ): ?array {
		$term = $id > 0 ? get_term( $id ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$meta = array();
		foreach ( (array) get_term_meta( $id ) as $key => $values ) {
			if ( Options_Support::is_sensitive_key( (string) $key ) ) {
				continue;
			}
			$meta[ (string) $key ] = array_map( 'strval', (array) $values );
		}
		ksort( $meta );
		return array(
			'fields' => array(
				'taxonomy'    => $term->taxonomy,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => (int) $term->parent,
			),
			'meta'   => $meta,
		);
	}

	private static function capture_comment( int $id ): ?array {
		$comment = $id > 0 ? get_comment( $id ) : null;
		if ( ! $comment ) {
			return null;
		}
		return array(
			'fields' => array(
				'comment_post_ID'      => (int) $comment->comment_post_ID,
				'comment_content'      => $comment->comment_content,
				'comment_approved'     => (string) $comment->comment_approved,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_parent'       => (int) $comment->comment_parent,
				'comment_type'         => $comment->comment_type,
				'user_id'              => (int) $comment->user_id,
			),
		);
	}

	private static function capture_user( int $id ): ?array {
		$user = $id > 0 ? get_userdata( $id ) : false;
		if ( ! $user ) {
			return null;
		}
		$roles = array_values( (array) $user->roles );
		sort( $roles );
		return array(
			'fields' => array(
				'user_email'   => $user->user_email,
				'display_name' => $user->display_name,
				'user_url'     => $user->user_url,
				'first_name'   => (string) get_user_meta( $id, 'first_name', true ),
				'last_name'    => (string) get_user_meta( $id, 'last_name', true ),
				'description'  => (string) get_user_meta( $id, 'description', true ),
			),
			'roles'  => $roles,
		);
	}

	private static function same( ?array $a, ?array $b ): bool {
		return wp_json_encode( $a ) === wp_json_encode( $b );
	}

	

	
	public static function record( array $row ): int {
		global $wpdb;

		$summary = isset( $row['summary'] ) ? (string) $row['summary'] : self::summarise( $row );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into this plugin's own history table.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'user_id'      => get_current_user_id(),
				'credential'   => substr( Request_Context::credential(), 0, 100 ),
				'tool'         => substr( $row['tool'], 0, 100 ),
				'object_type'  => $row['object_type'],
				'object_id'    => substr( $row['object_id'], 0, 191 ),
				'summary'      => substr( $summary, 0, 255 ),
				'before_state' => null === $row['before'] ? null : wp_json_encode( $row['before'] ),
				'after_state'  => null === $row['after'] ? null : wp_json_encode( $row['after'] ),
				'state'        => 'applied',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	private static function summarise( array $row ): string {
		$type   = $row['object_type'];
		$before = $row['before'] ?? null;
		$after  = $row['after'] ?? null;

		$label = '';
		if ( self::TYPE_POST === $type ) {
			$ref   = $after ?? $before;
			$label = isset( $ref['fields']['post_title'] ) ? ' "' . wp_strip_all_tags( (string) $ref['fields']['post_title'] ) . '"' : '';
		}
		$name = str_replace( '_', ' ', $type ) . ' ' . $row['object_id'] . $label;

		if ( null === $before ) {
			return 'Created ' . $name;
		}
		if ( null === $after ) {
			return 'Deleted ' . $name;
		}
		$changed = array_keys( self::changed_parts( $before, $after ) );
		return 'Changed ' . $name . ( $changed ? ': ' . implode( ', ', array_slice( $changed, 0, 8 ) ) : '' );
	}

	private static function changed_parts( array $before, array $after ): array {
		$out = array();
		foreach ( array( 'fields', 'meta', 'terms' ) as $section ) {
			$b = isset( $before[ $section ] ) && is_array( $before[ $section ] ) ? $before[ $section ] : array();
			$a = isset( $after[ $section ] ) && is_array( $after[ $section ] ) ? $after[ $section ] : array();
			foreach ( array_unique( array_merge( array_keys( $b ), array_keys( $a ) ) ) as $key ) {
				if ( wp_json_encode( $b[ $key ] ?? null ) !== wp_json_encode( $a[ $key ] ?? null ) ) {
					$out[ 'fields' === $section ? (string) $key : $section . '.' . $key ] = true;
				}
			}
		}
		foreach ( array( 'value', 'css', 'roles' ) as $scalar ) {
			if ( ( isset( $before[ $scalar ] ) || isset( $after[ $scalar ] ) )
				&& wp_json_encode( $before[ $scalar ] ?? null ) !== wp_json_encode( $after[ $scalar ] ?? null ) ) {
				$out[ $scalar ] = true;
			}
		}
		return $out;
	}

	public static function get_row( int $id ): ?array {
		global $wpdb;
		$table = esc_sql( self::table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function query( array $filters ): array {
		global $wpdb;
		$table = esc_sql( self::table() );

		$where  = array( '1=1' );
		$params = array();
		foreach ( array( 'object_type', 'tool', 'state' ) as $column ) {
			if ( ! empty( $filters[ $column ] ) ) {
				$where[]  = "{$column} = %s";
				$params[] = (string) $filters[ $column ];
			}
		}
		if ( isset( $filters['object_id'] ) && '' !== (string) $filters['object_id'] ) {
			$where[]  = 'object_id = %s';
			$params[] = (string) $filters['object_id'];
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['since'] ) ) {
			$ts = strtotime( (string) $filters['since'] );
			if ( $ts ) {
				$where[]  = 'created_at >= %s';
				$params[] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		$where_sql = implode( ' AND ', $where );

		$limit  = max( 1, min( 100, (int) ( $filters['limit'] ?? 25 ) ) );
		$offset = max( 0, (int) ( $filters['offset'] ?? 0 ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; the WHERE text is built from fixed column names with bound values.
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql = "SELECT id, created_at, user_id, credential, tool, object_type, object_id, summary, state, restored_at FROM `{$table}` WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $limit, $offset ) ) ), ARRAY_A );
		// phpcs:enable

		return array(
			'total' => $total,
			'rows'  => is_array( $rows ) ? $rows : array(),
		);
	}

	

	
	public static function can_access( string $type, string $id ): bool {
		switch ( $type ) {
			case self::TYPE_POST:
				$post_id = (int) $id;
				return get_post( $post_id )
					? current_user_can( 'edit_post', $post_id )
					: current_user_can( 'edit_others_posts' );
			case self::TYPE_COMMENT:
				return current_user_can( 'moderate_comments' );
			case self::TYPE_TERM:
				$term = get_term( (int) $id );
				if ( $term && ! is_wp_error( $term ) ) {
					return current_user_can( 'edit_term', (int) $id );
				}
				return current_user_can( 'manage_categories' );
			case self::TYPE_THEME_MOD:
			case self::TYPE_CSS:
				return current_user_can( 'edit_theme_options' );
			case self::TYPE_USER:
				return current_user_can( 'edit_users' );
			case self::TYPE_OPTION:
			default:
				return current_user_can( 'manage_options' );
		}
	}

	

	
	public static function present( array $row, bool $with_states = false, int $max_chars = 2000 ): array {
		$user = get_userdata( (int) $row['user_id'] );
		$out  = array(
			'id'          => (int) $row['id'],
			'created_at'  => gmdate( 'c', (int) strtotime( $row['created_at'] . ' UTC' ) ),
			'user'        => $user ? array( 'id' => (int) $user->ID, 'login' => $user->user_login ) : array( 'id' => (int) $row['user_id'] ),
			'credential'  => $row['credential'],
			'tool'        => $row['tool'],
			'object_type' => $row['object_type'],
			'object_id'   => $row['object_id'],
			'summary'     => $row['summary'],
			'state'       => $row['state'],
		);
		if ( ! empty( $row['restored_at'] ) ) {
			$out['restored_at'] = gmdate( 'c', (int) strtotime( $row['restored_at'] . ' UTC' ) );
		}
		if ( $with_states ) {
			foreach ( array( 'before_state' => 'before', 'after_state' => 'after' ) as $column => $label ) {
				$decoded       = isset( $row[ $column ] ) && null !== $row[ $column ] ? json_decode( (string) $row[ $column ], true ) : null;
				$out[ $label ] = $max_chars > 0 ? self::clip( $decoded, $max_chars ) : $decoded;
			}
		}
		return $out;
	}

	private static function clip( $value, int $max ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::clip( $v, $max );
			}
			return $value;
		}
		if ( is_string( $value ) && strlen( $value ) > $max ) {
			return substr( $value, 0, $max ) . '… [' . strlen( $value ) . ' chars; pass full:true for all of it]';
		}
		return $value;
	}

	public static function diff( array $row ): array {
		$before = isset( $row['before_state'] ) && null !== $row['before_state'] ? json_decode( (string) $row['before_state'], true ) : null;
		$after  = isset( $row['after_state'] ) && null !== $row['after_state'] ? json_decode( (string) $row['after_state'], true ) : null;

		$out = array(
			'id'      => (int) $row['id'],
			'summary' => $row['summary'],
			'change'  => null === $before ? 'created' : ( null === $after ? 'deleted' : 'modified' ),
		);
		$b = is_array( $before ) ? $before : array();
		$a = is_array( $after ) ? $after : array();

		$fields = array();
		$bf     = isset( $b['fields'] ) && is_array( $b['fields'] ) ? $b['fields'] : array();
		$af     = isset( $a['fields'] ) && is_array( $a['fields'] ) ? $a['fields'] : array();
		foreach ( array_unique( array_merge( array_keys( $bf ), array_keys( $af ) ) ) as $key ) {
			$old = $bf[ $key ] ?? null;
			$new = $af[ $key ] ?? null;
			if ( wp_json_encode( $old ) === wp_json_encode( $new ) ) {
				continue;
			}
			if ( is_string( $old ) && is_string( $new ) && ( false !== strpos( $old . $new, "\n" ) || strlen( $old ) + strlen( $new ) > 400 ) ) {
				$fields[ $key ] = array( 'diff' => Line_Diff::diff( $old, $new ) );
			} else {
				$fields[ $key ] = array(
					'before' => is_string( $old ) ? self::clip( $old, 300 ) : $old,
					'after'  => is_string( $new ) ? self::clip( $new, 300 ) : $new,
				);
			}
		}
		if ( $fields ) {
			$out['fields'] = $fields;
		}

		$meta = array();
		$bm   = isset( $b['meta'] ) && is_array( $b['meta'] ) ? $b['meta'] : array();
		$am   = isset( $a['meta'] ) && is_array( $a['meta'] ) ? $a['meta'] : array();
		foreach ( array_unique( array_merge( array_keys( $bm ), array_keys( $am ) ) ) as $key ) {
			$old = $bm[ $key ] ?? null;
			$new = $am[ $key ] ?? null;
			if ( wp_json_encode( $old ) === wp_json_encode( $new ) ) {
				continue;
			}
			$old_s = null === $old ? null : implode( "\n", (array) $old );
			$new_s = null === $new ? null : implode( "\n", (array) $new );
			if ( null === $old_s ) {
				$meta[ $key ] = array( 'added' => self::clip( $new_s, 300 ), 'length' => strlen( (string) $new_s ) );
			} elseif ( null === $new_s ) {
				$meta[ $key ] = array( 'removed' => self::clip( $old_s, 300 ), 'length' => strlen( $old_s ) );
			} elseif ( strlen( $old_s ) > 300 || strlen( $new_s ) > 300 ) {
				$meta[ $key ] = array(
					'changed'       => true,
					'length_before' => strlen( $old_s ),
					'length_after'  => strlen( $new_s ),
				);
			} else {
				$meta[ $key ] = array( 'before' => $old_s, 'after' => $new_s );
			}
		}
		if ( $meta ) {
			$out['meta'] = $meta;
		}

		foreach ( array( 'terms', 'roles' ) as $list ) {
			if ( wp_json_encode( $b[ $list ] ?? null ) !== wp_json_encode( $a[ $list ] ?? null ) ) {
				$out[ $list ] = array( 'before' => $b[ $list ] ?? null, 'after' => $a[ $list ] ?? null );
			}
		}
		foreach ( array( 'value', 'css' ) as $scalar ) {
			if ( wp_json_encode( $b[ $scalar ] ?? null ) !== wp_json_encode( $a[ $scalar ] ?? null ) ) {
				$old = $b[ $scalar ] ?? null;
				$new = $a[ $scalar ] ?? null;
				if ( is_string( $old ) && is_string( $new ) ) {
					$out[ $scalar ] = array( 'diff' => Line_Diff::diff( $old, $new ) );
				} else {
					$out[ $scalar ] = array( 'before' => $old, 'after' => $new );
				}
			}
		}
		return $out;
	}

	

	
	public static function apply( array $row, bool $force ): array {
		$type = (string) $row['object_type'];
		$id   = (string) $row['object_id'];

		if ( 'applied' !== $row['state'] ) {
			throw new \Exception( 'This change was already restored. Restore the entry it created to go back again.' );
		}
		if ( ! self::can_access( $type, $id ) ) {
			throw new \Exception( 'You do not have permission to change this object.' );
		}

		$target  = isset( $row['before_state'] ) && null !== $row['before_state'] ? json_decode( (string) $row['before_state'], true ) : null;
		$expect  = isset( $row['after_state'] ) && null !== $row['after_state'] ? json_decode( (string) $row['after_state'], true ) : null;
		$current = self::capture( $type, $id );

		if ( ! $force && ! self::same( $current, $expect ) ) {
			throw new \Exception(
				'This ' . esc_html( str_replace( '_', ' ', $type ) ) . ' has changed since history entry ' . (int) $row['id'] . ' was recorded, so restoring it would discard those later changes. '
				. 'Review them with wp_history_list (object_type + object_id), restore the newest entry first, or pass force:true to restore anyway.'
			);
		}

		if ( null === $target ) {
			
			self::remove( $type, $id );
		} else {
			self::restore_state( $type, $id, $target );
		}

		$after    = self::capture( $type, $id );
		$verified = null === $target ? null === $after || ( self::TYPE_POST === $type && 'trash' === ( $after['fields']['post_status'] ?? '' ) ) : self::same_restored( $target, $after );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own history table.
		$wpdb->update(
			self::table(),
			array(
				'state'       => 'restored',
				'restored_at' => gmdate( 'Y-m-d H:i:s' ),
				'restored_by' => get_current_user_id(),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		$new_id = 0;
		if ( ! self::same( $current, $after ) ) {
			$new_id = self::record(
				array(
					'tool'        => 'wp_history_apply',
					'object_type' => $type,
					'object_id'   => $id,
					'before'      => $current,
					'after'       => $after,
					'summary'     => 'Restored ' . str_replace( '_', ' ', $type ) . ' ' . $id . ' to its state before entry ' . (int) $row['id'],
				)
			);
		}

		return array(
			'restored'          => (int) $row['id'],
			'object_type'       => $type,
			'object_id'         => $id,
			'action'            => null === $target ? ( self::TYPE_POST === $type ? 'moved to trash' : 'removed' ) : 'restored',
			'verified'          => $verified,
			'verify_note'       => $verified ? 'Read back after the restore: the stored state matches the snapshot.' : 'The restore ran but the stored state differs from the snapshot (a plugin may have filtered the write). Check with wp_history_diff.',
			'new_history_entry' => $new_id,
		);
	}

	private static function same_restored( array $target, ?array $actual ): bool {
		if ( null === $actual ) {
			return false;
		}
		foreach ( array( 'fields', 'terms', 'roles', 'value', 'css' ) as $part ) {
			if ( wp_json_encode( $target[ $part ] ?? null ) !== wp_json_encode( $actual[ $part ] ?? null ) ) {
				return false;
			}
		}
		
		return ! empty( $target['meta_truncated'] ) || wp_json_encode( $target['meta'] ?? null ) === wp_json_encode( $actual['meta'] ?? null );
	}

	private static function remove( string $type, string $id ): void {
		switch ( $type ) {
			case self::TYPE_POST:
				if ( ! wp_trash_post( (int) $id ) ) {
					throw new \Exception( 'WordPress could not move that post to the trash.' );
				}
				return;
			case self::TYPE_OPTION:
				delete_option( $id );
				return;
			case self::TYPE_THEME_MOD:
				remove_theme_mod( $id );
				return;
			case self::TYPE_TERM:
				$term = get_term( (int) $id );
				if ( $term && ! is_wp_error( $term ) ) {
					wp_delete_term( (int) $id, $term->taxonomy );
				}
				return;
			case self::TYPE_COMMENT:
				wp_delete_comment( (int) $id, true );
				return;
		}
		throw new \Exception( 'This kind of change cannot be undone by removing the object.' );
	}

	private static function restore_state( string $type, string $id, array $state ): void {
		switch ( $type ) {
			case self::TYPE_POST:
				self::restore_post( (int) $id, $state );
				return;
			case self::TYPE_OPTION:
				update_option( $id, $state['value'] );
				return;
			case self::TYPE_THEME_MOD:
				set_theme_mod( $id, $state['value'] );
				return;
			case self::TYPE_CSS:
				$result = wp_update_custom_css_post( (string) ( $state['css'] ?? '' ), array( 'stylesheet' => $id ) );
				if ( is_wp_error( $result ) ) {
					throw new \Exception( esc_html( $result->get_error_message() ) );
				}
				return;
			case self::TYPE_TERM:
				self::restore_term( (int) $id, $state );
				return;
			case self::TYPE_COMMENT:
				self::restore_comment( (int) $id, $state );
				return;
			case self::TYPE_USER:
				self::restore_user( (int) $id, $state );
				return;
		}
		throw new \Exception( 'Unknown object type in history entry.' );
	}

	private static function restore_post( int $id, array $state ): void {
		$fields = isset( $state['fields'] ) && is_array( $state['fields'] ) ? $state['fields'] : array();
		if ( ! $fields ) {
			throw new \Exception( 'The history entry has no post fields to restore.' );
		}

		$data = array_intersect_key( $fields, array_flip( self::POST_FIELDS ) );
		if ( isset( $data['post_date'] ) ) {
			
			$data['edit_date'] = true;
		}
		if ( get_post( $id ) ) {
			$data['ID'] = $id;
			$result     = wp_update_post( wp_slash( $data ), true );
		} else {
			
			$data['import_id'] = $id;
			$result            = wp_insert_post( wp_slash( $data ), true );
		}
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}
		if ( ! $result ) {
			throw new \Exception( 'WordPress could not write the post.' );
		}
		$id = (int) $result;

		if ( empty( $state['meta_truncated'] ) && isset( $state['meta'] ) && is_array( $state['meta'] ) ) {
			$current = array_keys( (array) get_post_meta( $id ) );
			foreach ( $current as $key ) {
				$key = (string) $key;
				if ( in_array( $key, self::VOLATILE_META, true ) || Options_Support::is_sensitive_key( $key ) || array_key_exists( $key, $state['meta'] ) ) {
					continue;
				}
				delete_post_meta( $id, $key );
			}
			foreach ( $state['meta'] as $key => $values ) {
				$key = (string) $key;
				if ( Options_Support::is_sensitive_key( $key ) ) {
					continue;
				}
				delete_post_meta( $id, $key );
				foreach ( (array) $values as $value ) {
					add_post_meta( $id, $key, wp_slash( maybe_unserialize( $value ) ) );
				}
			}
		}

		$taxonomies = get_object_taxonomies( (string) ( $fields['post_type'] ?? get_post_type( $id ) ), 'names' );
		$terms      = isset( $state['terms'] ) && is_array( $state['terms'] ) ? $state['terms'] : array();
		foreach ( $taxonomies as $taxonomy ) {
			wp_set_object_terms( $id, array_map( 'intval', (array) ( $terms[ $taxonomy ] ?? array() ) ), $taxonomy );
		}

		clean_post_cache( $id );
		if ( isset( $state['meta']['_elementor_data'] ) && class_exists( '\More_MCP\Integrations\Elementor' ) ) {
			\More_MCP\Integrations\Elementor::invalidate_derived_state_public( $id );
		}
	}

	private static function restore_term( int $id, array $state ): void {
		$fields = isset( $state['fields'] ) && is_array( $state['fields'] ) ? $state['fields'] : array();
		$tax    = (string) ( $fields['taxonomy'] ?? '' );
		if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
			throw new \Exception( 'The history entry names a taxonomy that no longer exists.' );
		}
		$args = array(
			'name'        => (string) ( $fields['name'] ?? '' ),
			'slug'        => (string) ( $fields['slug'] ?? '' ),
			'description' => (string) ( $fields['description'] ?? '' ),
			'parent'      => (int) ( $fields['parent'] ?? 0 ),
		);
		$term = get_term( $id, $tax );
		if ( $term && ! is_wp_error( $term ) ) {
			$result = wp_update_term( $id, $tax, $args );
		} else {
			$result = wp_insert_term( $args['name'], $tax, $args );
			if ( ! is_wp_error( $result ) ) {
				$id = (int) $result['term_id'];
			}
		}
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}
		if ( isset( $state['meta'] ) && is_array( $state['meta'] ) ) {
			foreach ( array_keys( (array) get_term_meta( $id ) ) as $key ) {
				if ( ! array_key_exists( (string) $key, $state['meta'] ) && ! Options_Support::is_sensitive_key( (string) $key ) ) {
					delete_term_meta( $id, (string) $key );
				}
			}
			foreach ( $state['meta'] as $key => $values ) {
				delete_term_meta( $id, (string) $key );
				foreach ( (array) $values as $value ) {
					add_term_meta( $id, (string) $key, wp_slash( maybe_unserialize( $value ) ) );
				}
			}
		}
	}

	private static function restore_comment( int $id, array $state ): void {
		$fields = isset( $state['fields'] ) && is_array( $state['fields'] ) ? $state['fields'] : array();
		if ( ! $fields ) {
			throw new \Exception( 'The history entry has no comment fields to restore.' );
		}
		if ( ! get_comment( $id ) ) {
			throw new \Exception( 'That comment no longer exists, so it cannot be restored from history.' );
		}
		$fields['comment_ID'] = $id;
		$result               = wp_update_comment( wp_slash( $fields ), true );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}
	}

	private static function restore_user( int $id, array $state ): void {
		$fields = isset( $state['fields'] ) && is_array( $state['fields'] ) ? $state['fields'] : array();
		$data   = array(
			'ID'           => $id,
			'user_email'   => (string) ( $fields['user_email'] ?? '' ),
			'display_name' => (string) ( $fields['display_name'] ?? '' ),
			'user_url'     => (string) ( $fields['user_url'] ?? '' ),
			'first_name'   => (string) ( $fields['first_name'] ?? '' ),
			'last_name'    => (string) ( $fields['last_name'] ?? '' ),
			'description'  => (string) ( $fields['description'] ?? '' ),
		);
		$user = get_userdata( $id );
		if ( ! $user ) {
			throw new \Exception( 'That user no longer exists, so they cannot be restored from history.' );
		}

		
		$roles = isset( $state['roles'] ) && is_array( $state['roles'] ) ? $state['roles'] : null;
		if ( null !== $roles && wp_json_encode( array_values( (array) $user->roles ) ) !== wp_json_encode( $roles ) ) {
			if ( get_current_user_id() === $id ) {
				throw new \Exception( 'You cannot change your own role through a restore.' );
			}
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$editable = array_keys( get_editable_roles() );
			foreach ( array_merge( $roles, (array) $user->roles ) as $role ) {
				if ( ! in_array( $role, $editable, true ) ) {
					throw new \Exception( 'You are not allowed to assign or remove the "' . esc_html( $role ) . '" role.' );
				}
			}
		}

		$result = wp_update_user( wp_slash( $data ) );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}
		if ( null !== $roles && wp_json_encode( array_values( (array) $user->roles ) ) !== wp_json_encode( $roles ) ) {
			$fresh = new \WP_User( $id );
			foreach ( (array) $fresh->roles as $role ) {
				$fresh->remove_role( $role );
			}
			foreach ( $roles as $role ) {
				$fresh->add_role( (string) $role );
			}
		}
	}

	

	
	public static function cleanup_expired(): void {
		global $wpdb;
		$table    = esc_sql( self::table() );
		$settings = get_option( 'more_mcp_settings', array() );
		$days     = is_array( $settings ) && isset( $settings['history_retention_days'] ) && is_numeric( $settings['history_retention_days'] )
			? (int) $settings['history_retention_days']
			: self::DEFAULT_RETENTION_DAYS;
		$days     = (int) apply_filters( 'more_mcp_history_retention_days', $days );

		if ( $days > 0 ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE created_at < %s ORDER BY created_at ASC LIMIT %d",
					gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) ),
					Log_Store::DELETE_BATCH_CEILING
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$cap = (int) apply_filters( 'more_mcp_history_row_cap', self::DEFAULT_ROW_CAP );
		if ( $cap > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total > $cap ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from prefix.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM `{$table}` ORDER BY id ASC LIMIT %d",
						min( $total - $cap, Log_Store::DELETE_BATCH_CEILING )
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}
}
