<?php

namespace More_MCP\Blocks;

use More_MCP\MCP\Undo_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Global_Styles {

	const POST_TYPE = 'wp_global_styles';

	const USER_FLAG = 'isGlobalStylesUserThemeJSON';

	const WRITABLE_KEYS = array( 'settings', 'styles' );

	const FALLBACK_SCHEMA = 2;

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'blocks_get_global_styles',
				'description' => 'Read the site\'s global styles (theme.json): the colour palette, typography scale, spacing presets, layout widths, and per-block style overrides that a block theme renders from. This is the Site Editor\'s Styles sidebar, and the Gutenberg counterpart of elementor_get_kit. Pick which layer you want with origin: "user" (default) is what the Site Editor has saved and the only layer that is writable; "theme" is the active theme\'s own theme.json defaults; "merged" is what actually renders, after core, block, theme and user layers are combined in that priority order. IMPORTANT: by default this returns a KEY INDEX, not the values, because merged theme.json data on a real block theme carries a section per registered block and is larger than a single tool result allows. Pass keys:["settings.color.palette","styles.typography"] to read specific dot-paths, or include_all:true to force the whole object. Read the user layer before writing with blocks_update_global_styles, since a list-valued preset such as settings.color.palette is replaced wholesale rather than merged element by element. Requires edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'origin' => array(
							'type'        => 'string',
							'enum'        => array( 'user', 'theme', 'merged' ),
							'description' => 'Which layer to read. "user" (default) is the saved Site Editor customization and the only writable layer. "theme" is the theme\'s theme.json defaults, useful for discovering which preset slugs exist before overriding them. "merged" is the effective rendered result.',
						),
						'keys' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Dot-paths to return the actual values for, e.g. "settings.color.palette", "styles.color.background", or a bare top-level key like "settings". A path that is absent from this layer is reported in unset_paths rather than returned as empty, because "unset here" and "empty" mean different things: an unset user value means the theme default still applies.',
						),
						'include_all' => array(
							'type'        => 'boolean',
							'description' => 'Return the complete theme.json object instead of the key index. Expect a very large response on the merged origin; prefer keys.',
						),
					),
				),
			),
			array(
				'name'        => 'blocks_update_global_styles',
				'description' => 'Write the site\'s user global styles (theme.json), which is what the Site Editor\'s Styles sidebar saves. IMPORTANT: the keys you send are MERGED into the existing user styles, not replaced, so sending only settings.color.palette leaves typography and every per-block override untouched. Nested objects merge recursively, but a LIST value (settings.color.palette, settings.typography.fontSizes, settings.spacing.spacingSizes) is replaced wholesale, the same rule as the Elementor kit repeaters: read the current list with blocks_get_global_styles keys:["settings.color.palette"] and send the full list back with your edits, or you will drop the entries you omitted. Pass replace_settings=true only for a deliberate wholesale swap, which discards every top-level key you do not send and reports what it removed. Accepts the "settings" and "styles" top-level keys; "version" and WordPress\'s isGlobalStylesUserThemeJSON marker are structural and written automatically (without that marker WordPress ignores the stored JSON entirely, so a write would succeed, read back correctly, and change nothing on the page). Set dry_run=true to preview a per-path diff without writing. After a write the response re-reads the stored value to confirm what landed, and the theme_json caches are invalidated so the next render picks the change up. Emits an undo token restoring the full pre-write user styles. Site-wide change. Requires edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'        => 'object',
							'description' => 'theme.json "settings" subtree: presets and opt-ins such as color.palette, typography.fontSizes, spacing.spacingSizes, layout.contentSize. Merged recursively; list values replace wholesale.',
						),
						'styles' => array(
							'type'        => 'object',
							'description' => 'theme.json "styles" subtree: the applied values such as color.background, typography.fontFamily, elements.link, and per-block overrides under blocks. Merged recursively; list values replace wholesale.',
						),
						'replace_settings' => array(
							'type'        => 'boolean',
							'description' => 'Replace the whole user styles object instead of merging. Discards every top-level key you do not send. Default false.',
						),
						'dry_run' => array(
							'type'        => 'boolean',
							'description' => 'Report the per-path diff the call would apply without writing. Default false.',
						),
					),
				),
			),
		);
	}

	public static function tool_names(): array {
		return array( 'blocks_get_global_styles', 'blocks_update_global_styles' );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'blocks_get_global_styles':
				return self::get_global_styles( $args );
			case 'blocks_update_global_styles':
				return self::update_global_styles( $args );
		}
		throw new \Exception( 'Unknown global styles tool: ' . esc_html( $name ) );
	}

	private static function require_global_styles_support(): void {
		if ( ! class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			throw new \Exception( 'This WordPress version has no global styles support (WP_Theme_JSON_Resolver is absent, which means WordPress older than 5.9).' );
		}

		
		if ( function_exists( 'wp_is_block_theme' ) && ! wp_is_block_theme() ) {
			throw new \Exception( 'The active theme is a classic theme, not a block theme, so nothing renders from theme.json. Global styles can be stored but would have no effect. Use wp_update_theme_mod / wp_update_custom_css for a classic theme, or elementor_update_kit on an Elementor site.' );
		}
	}

	private static function schema_version(): int {
		if ( class_exists( '\WP_Theme_JSON' ) && defined( '\WP_Theme_JSON::LATEST_SCHEMA' ) ) {
			return (int) \WP_Theme_JSON::LATEST_SCHEMA;
		}
		return self::FALLBACK_SCHEMA;
	}

	private static function is_list( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function read_raw_user_styles(): array {
		$row = \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), false );

		if ( ! is_array( $row ) || ! isset( $row['post_content'] ) ) {
			return array(
				'post_id'      => isset( $row['ID'] ) ? (int) $row['ID'] : null,
				'data'         => array(),
				'version'      => null,
				'flag_present' => false,
				'malformed'    => false,
			);
		}

		$post_id = isset( $row['ID'] ) ? (int) $row['ID'] : null;
		$raw     = (string) $row['post_content'];

		if ( '' === trim( $raw ) ) {
			return array(
				'post_id'      => $post_id,
				'data'         => array(),
				'version'      => null,
				'flag_present' => false,
				'malformed'    => false,
			);
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {

			
			return array(
				'post_id'      => $post_id,
				'data'         => array(),
				'version'      => null,
				'flag_present' => false,
				'malformed'    => true,
			);
		}

		$flag_present = ! empty( $decoded[ self::USER_FLAG ] );
		$version      = isset( $decoded['version'] ) ? (int) $decoded['version'] : null;
		unset( $decoded[ self::USER_FLAG ], $decoded['version'] );

		return array(
			'post_id'      => $post_id,
			'data'         => $decoded,
			'version'      => $version,
			'flag_present' => $flag_present,
			'malformed'    => false,
		);
	}

	private static function merge_deep( array $base, array $incoming ): array {
		foreach ( $incoming as $key => $value ) {
			if (
				array_key_exists( $key, $base )
				&& is_array( $base[ $key ] )
				&& is_array( $value )
				&& ! self::is_list( $base[ $key ] )
				&& ! self::is_list( $value )
			) {
				$base[ $key ] = self::merge_deep( $base[ $key ], $value );
				continue;
			}

			
			if ( null === $value ) {
				unset( $base[ $key ] );
				continue;
			}

			if ( array() === $value && array_key_exists( $key, $base ) && is_array( $base[ $key ] ) && array() !== $base[ $key ] ) {
				continue;
			}
			$base[ $key ] = $value;
		}
		return $base;
	}

	private static function resolve_path( array $data, string $path ): array {
		$segments = explode( '.', $path );
		$cursor   = $data;
		foreach ( $segments as $segment ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $segment, $cursor ) ) {
				return array( false, null );
			}
			$cursor = $cursor[ $segment ];
		}
		return array( true, $cursor );
	}

	private static function describe( $value ): array {
		if ( is_array( $value ) ) {
			if ( self::is_list( $value ) ) {
				return array(
					'type'  => 'list',
					'count' => count( $value ),
					'note'  => 'Replaced wholesale on write, not merged element by element.',
				);
			}
			return array(
				'type' => 'object',
				'keys' => array_keys( $value ),
			);
		}
		if ( is_string( $value ) ) {
			return array(
				'type'   => 'string',
				'length' => strlen( $value ),
			);
		}
		if ( is_bool( $value ) ) {
			return array(
				'type'  => 'boolean',
				'value' => $value,
			);
		}
		if ( null === $value ) {
			return array( 'type' => 'null' );
		}
		return array(
			'type'  => is_int( $value ) ? 'integer' : 'number',
			'value' => $value,
		);
	}

	private static function index_paths( array $data, string $prefix = '', int $depth = 0 ): array {
		$rows = array();
		foreach ( $data as $key => $value ) {
			$path        = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			$rows[ $path ] = self::describe( $value );
			if ( is_array( $value ) && ! self::is_list( $value ) && $depth < 2 ) {
				$rows = array_merge( $rows, self::index_paths( $value, $path, $depth + 1 ) );
			}
		}
		return $rows;
	}

	private static function invalidate_theme_json_cache(): array {
		$cleared  = array();
		$warnings = array();

		try {
			if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
				wp_clean_theme_json_cache();
				$cleared[] = 'wp_clean_theme_json_cache';
			} elseif ( class_exists( '\WP_Theme_JSON_Resolver' ) && method_exists( '\WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
				\WP_Theme_JSON_Resolver::clean_cached_data();
				$cleared[]  = 'WP_Theme_JSON_Resolver::clean_cached_data';
				$warnings[] = 'wp_clean_theme_json_cache() is unavailable (WordPress older than 6.2), so only the resolver statics were reset. With a persistent object cache the generated stylesheet may still be stale until the next cache flush.';
			} else {
				$warnings[] = 'No theme_json cache invalidation path is available on this install; the change is stored but the rendered stylesheet may be stale.';
			}
		} catch ( \Throwable $e ) {
			
			$warnings[] = 'Cache invalidation failed after the write committed: ' . $e->getMessage();
		}

		return array(
			'cleared'  => $cleared,
			'warnings' => $warnings,
		);
	}

	private static function get_global_styles( array $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		self::require_global_styles_support();

		$origin = isset( $args['origin'] ) ? sanitize_key( (string) $args['origin'] ) : 'user';
		if ( ! in_array( $origin, array( 'user', 'theme', 'merged' ), true ) ) {
			throw new \Exception( 'origin must be one of: user, theme, merged.' );
		}

		$response = array(
			'success'    => true,
			'origin'     => $origin,
			'stylesheet' => get_stylesheet(),
			'writable'   => ( 'user' === $origin ),
		);

		if ( 'user' === $origin ) {
			$raw  = self::read_raw_user_styles();
			$data = $raw['data'];
			$response['post_id'] = $raw['post_id'];
			if ( null === $raw['post_id'] ) {

				
				$response['note'] = 'This site has no saved user global styles yet, so every value still comes from the theme. Read origin:"theme" to see the defaults. A write creates the row.';
			}
			if ( $raw['malformed'] ) {
				$response['warnings'][] = 'The stored wp_global_styles post_content is not valid JSON, so WordPress is ignoring it entirely. A merge write would treat the stored value as empty; use replace_settings:true to overwrite it deliberately.';
			} elseif ( null !== $raw['post_id'] && ! $raw['flag_present'] ) {
				$response['warnings'][] = 'The stored JSON is missing the isGlobalStylesUserThemeJSON marker, so WordPress is ignoring it and the values below do not render. Writing through blocks_update_global_styles restores the marker.';
			}
		} else {
			$resolved = ( 'theme' === $origin )
				? \WP_Theme_JSON_Resolver::get_theme_data()
				: \WP_Theme_JSON_Resolver::get_merged_data();
			if ( ! is_object( $resolved ) || ! method_exists( $resolved, 'get_raw_data' ) ) {
				throw new \Exception( 'WordPress returned no readable theme.json data for origin ' . esc_html( $origin ) . '.' );
			}
			$raw_data = $resolved->get_raw_data();
			$data     = is_array( $raw_data ) ? $raw_data : array();
		}

		$requested = array();
		if ( isset( $args['keys'] ) ) {
			foreach ( (array) $args['keys'] as $key ) {
				$key = sanitize_text_field( (string) $key );
				if ( '' !== $key ) {
					$requested[] = $key;
				}
			}
		}

		if ( ! empty( $requested ) ) {
			$picked = array();
			$unset  = array();
			foreach ( $requested as $path ) {
				list( $found, $value ) = self::resolve_path( $data, $path );
				if ( $found ) {
					$picked[ $path ] = $value;
				} else {
					$unset[] = $path;
				}
			}
			$response['mode']   = 'paths';
			$response['values'] = $picked;
			if ( ! empty( $unset ) ) {
				$response['unset_paths'] = $unset;
				$response['unset_note']  = 'These paths are not set at this origin. On the user origin that means the theme default still applies; read origin:"theme" for that value.';
			}
			return $response;
		}

		if ( ! empty( $args['include_all'] ) ) {
			$response['mode']     = 'full';
			$response['settings'] = $data;
			return $response;
		}

		$response['mode']        = 'index';
		$response['paths']       = self::index_paths( $data );
		$response['top_level']   = array_keys( $data );
		$response['index_note']  = 'Shapes only, not values. Pass keys:[...] with the dot-paths you want, or include_all:true for the whole object. Nesting below two levels is omitted (styles.blocks carries one entry per registered block).';
		return $response;
	}

	private static function validate_payload( array $args ): array {
		$payload  = array();
		$refused  = array();

		foreach ( $args as $key => $value ) {
			
			if ( in_array( $key, array( 'replace_settings', 'dry_run' ), true ) ) {
				continue;
			}
			if ( in_array( $key, self::WRITABLE_KEYS, true ) ) {
				if ( ! is_array( $value ) ) {
					throw new \Exception( esc_html( $key ) . ' must be an object (theme.json ' . esc_html( $key ) . ' subtree).' );
				}
				$payload[ $key ] = $value;
				continue;
			}
			$refused[] = (string) $key;
		}

		if ( ! empty( $refused ) ) {

			throw new \Exception(
				'Unsupported top-level key(s): ' . esc_html( implode( ', ', $refused ) )
				. '. Only ' . esc_html( implode( ' and ', self::WRITABLE_KEYS ) )
				. ' may be written. "version" and the isGlobalStylesUserThemeJSON marker are set automatically; anything else would be stored where WordPress never reads it.'
			);
		}

		if ( empty( $payload ) ) {
			throw new \Exception( 'Nothing to write: supply at least one of ' . esc_html( implode( ', ', self::WRITABLE_KEYS ) ) . '.' );
		}

		return $payload;
	}

	private static function diff_paths( array $before, array $after, string $prefix = '' ): array {
		$changes = array();
		$keys    = array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) );

		foreach ( $keys as $key ) {
			$path       = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			$had        = array_key_exists( $key, $before );
			$has        = array_key_exists( $key, $after );
			$old        = $had ? $before[ $key ] : null;
			$new        = $has ? $after[ $key ] : null;

			if ( $had && $has && $old === $new ) {
				continue;
			}

			
			if (
				$had && $has
				&& is_array( $old ) && is_array( $new )
				&& ! self::is_list( $old ) && ! self::is_list( $new )
			) {
				$changes = array_merge( $changes, self::diff_paths( $old, $new, $path ) );
				continue;
			}

			$entry = array( 'path' => $path );
			if ( ! $had ) {
				$entry['action'] = 'add';
			} elseif ( ! $has ) {
				$entry['action'] = 'remove';
			} else {
				$entry['action'] = 'change';
			}
			$entry['from'] = $had ? self::describe( $old ) : null;
			$entry['to']   = $has ? self::describe( $new ) : null;
			if ( self::is_list( $old ) || self::is_list( $new ) ) {
				$entry['note'] = 'List value: replaced wholesale. Any entry not present in the new list is dropped.';
			}
			$changes[] = $entry;
		}

		return $changes;
	}

	private static function update_global_styles( array $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		self::require_global_styles_support();

		$payload = self::validate_payload( $args );
		$replace = ! empty( $args['replace_settings'] );
		$dry_run = ! empty( $args['dry_run'] );

		$raw     = self::read_raw_user_styles();
		$current = $raw['data'];

		if ( $raw['malformed'] && ! $replace ) {

			throw new \Exception( 'The stored global styles JSON is malformed, so there is nothing to merge into. Re-send with replace_settings:true to overwrite it deliberately.' );
		}

		if ( $replace ) {
			$merged  = $payload;
			$dropped = array_values( array_diff( array_keys( $current ), array_keys( $payload ) ) );
		} else {
			$merged  = self::merge_deep( $current, $payload );
			$dropped = array();
		}

		$changes = self::diff_paths( $current, $merged );

		if ( $dry_run ) {
			$preview = array(
				'success'     => true,
				'dry_run'     => true,
				'mode'        => $replace ? 'replace' : 'merge',
				'post_id'     => $raw['post_id'],
				'stylesheet'  => get_stylesheet(),
				'changes'     => $changes,
				'change_count' => count( $changes ),
			);
			if ( ! empty( $dropped ) ) {
				$preview['would_remove_top_level'] = $dropped;
				$preview['replace_warning']        = 'replace_settings:true discards these top-level keys, which holds every preset and override beneath them.';
			}
			if ( empty( $changes ) ) {
				$preview['note'] = 'The supplied values already match what is stored; this write would change nothing.';
			}
			if ( null === $raw['post_id'] ) {
				$preview['note_post'] = 'No wp_global_styles row exists yet for this theme; the real write creates it.';
			}
			return $preview;
		}

		

		$to_store                    = $merged;
		$to_store['version']         = self::schema_version();
		$to_store[ self::USER_FLAG ] = true;

		$encoded = wp_json_encode( $to_store );
		if ( false === $encoded ) {
			throw new \Exception( 'The merged global styles could not be encoded as JSON, so nothing was written.' );
		}

		

		$post_id = $raw['post_id'];
		if ( null === $post_id || $post_id <= 0 ) {
			$post_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
			$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
			if ( $post_id <= 0 ) {
				throw new \Exception( 'WordPress could not create the wp_global_styles post for this theme, so nothing was written.' );
			}
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $encoded ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			throw new \Exception( 'Failed to write global styles: ' . esc_html( $updated->get_error_message() ) );
		}

		$after          = self::read_raw_user_styles();
		$verified       = ( $after['data'] === $merged ) && $after['flag_present'];
		$inval          = self::invalidate_theme_json_cache();

		$response = array(
			'success'      => true,
			'mode'         => $replace ? 'replace' : 'merge',
			'post_id'      => $post_id,
			'stylesheet'   => get_stylesheet(),
			'created'      => ( null === $raw['post_id'] ),
			'changes'      => $changes,
			'change_count' => count( $changes ),
			'verified'     => $verified,
		);
		if ( ! $verified ) {
			$response['verification_warning'] = 'The value read back from storage does not match what was sent. A filter on the wp_global_styles post content may have altered it. Read blocks_get_global_styles to see what actually landed.';
		}
		if ( ! empty( $dropped ) ) {
			$response['removed_top_level'] = $dropped;
		}
		if ( ! empty( $inval['warnings'] ) ) {
			$response['cache_invalidation'] = $inval;
		}

		

		$response['undo'] = Undo_Store::store(
			array(
				'op'           => 'blocks_global_styles_write',
				'summary'      => sprintf(
					'Restored the site global styles for theme "%s" to their pre-operation state.',
					get_stylesheet()
				),
				'target'       => array( 'post_id' => $post_id ),
				'pre_op_state' => array(
					'styles'      => $current,
					'post_existed' => ( null !== $raw['post_id'] ),
				),
			)
		);

		return $response;
	}

	public static function restore_styles( array $styles, bool $post_existed = true ): array {
		self::require_global_styles_support();

		$raw     = self::read_raw_user_styles();
		$post_id = $raw['post_id'];

		if ( null === $post_id || $post_id <= 0 ) {

			
			throw new \Exception( 'The wp_global_styles post for this theme no longer exists, so the previous global styles cannot be restored into it.' );
		}

		$to_store                    = $styles;
		$to_store['version']         = self::schema_version();
		$to_store[ self::USER_FLAG ] = true;

		$encoded = wp_json_encode( $to_store );
		if ( false === $encoded ) {
			throw new \Exception( 'The stored global styles snapshot could not be encoded as JSON, so nothing was restored.' );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $encoded ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			throw new \Exception( 'Failed to restore global styles: ' . esc_html( $updated->get_error_message() ) );
		}

		$after = self::read_raw_user_styles();
		$inval = self::invalidate_theme_json_cache();

		$result = array(
			'post_id'            => $post_id,
			'verified'           => ( $after['data'] === $styles ) && $after['flag_present'],
			'cache_invalidation' => $inval,
		);
		if ( ! $post_existed && array() === $styles ) {

			
			
			$result['note'] = 'The original write created this row. Its contents were emptied rather than the row deleted, which renders identically to having no user global styles.';
		}
		return $result;
	}
}
