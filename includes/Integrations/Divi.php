<?php
namespace More_MCP\Integrations;

use More_MCP\Blocks\Parser as Block_Parser;
use More_MCP\MCP\Undo_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Divi {

	private const DYNAMIC_CACHE_KEYS = [
		'et_enqueued_post_fonts',
		'_et_dynamic_cached_shortcodes',
		'_et_dynamic_cached_attributes',
		'_et_builder_module_features_cache',
		'_divi_dynamic_assets_cached_modules',
		'_divi_dynamic_assets_cached_feature_used',
		'_divi_dynamic_assets_canvases_used',
	];

	public static function is_available(): bool {
		return defined( 'ET_BUILDER_VERSION' )
			|| defined( 'ET_CORE_VERSION' )
			|| function_exists( 'et_pb_is_pagebuilder_used' );
	}

	public static function get_manifest(): array {
		return array(
			'providers'    => array( 'divi' ),
			'capabilities' => array( 'page_building' ),
			'kind'         => 'builder',
		);
	}

	public static function get_tools(): array {
		if ( ! self::is_available() ) {
			return [];
		}

		$write_common = [
			'post_id'      => [ 'type' => 'integer', 'description' => 'Post or page ID to modify.' ],
			'path'         => [ 'type' => 'string', 'description' => 'Current dot-separated 0-based path, e.g. "0.2.1".' ],
			'expected_type' => [ 'type' => 'string', 'description' => 'Guard: abort unless the current node at path has this exact shortcode tag or block name.' ],
			'dry_run'      => [ 'type' => 'boolean', 'description' => 'Preview and validate without writing, snapshotting, or invalidating caches. Default false.' ],
		];

		return [
			[
				'name'        => 'divi_get_page_outline',
				'description' => 'Read the structural outline of a Divi-built post or page without rendering it. Supports legacy Divi 4 et_pb_* shortcode layouts and native Divi 5 divi/* block markup. Returns one positional path model across both formats, plus raw module names, labels, short text snippets, and explicit opaque/unsupported segments. Paths are dot-separated 0-based indices and can shift after the page changes. Divi 5 attributes are passed through verbatim because their internal schema is not a stable public contract. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'depth'   => [ 'type' => 'integer', 'description' => 'Maximum outline nesting depth. Default 6, max 20.' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'divi_get_module',
				'description' => 'Read one Divi node by its positional path from divi_get_page_outline. For Divi 4, attributes are literal shortcode strings; for Divi 5, block attrs are returned verbatim; mixed legacy regions remain opaque rather than being interpreted under a second path space. A valid path that no longer resolves returns found=false. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'path'    => [ 'type' => 'string', 'description' => 'Dot-separated 0-based path, e.g. "0.2.1".' ],
					],
					'required'   => [ 'post_id', 'path' ],
				],
			],
			[
				'name'        => 'divi_replace_module',
				'description' => 'Replace one addressed Divi node as a whole. On Divi 4, markup must be exactly one balanced et_pb_* shortcode subtree; untouched post_content bytes are preserved. On Divi 5, markup must be exactly one divi/* block and survives a serialize-reparse check before writing. Requires expected_type because positional paths shift. Mixed legacy wrappers remain opaque but may be replaced as one advertised D5 node. Supports dry_run and emits an undo token. Requires edit_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => array_merge( $write_common, [ 'markup' => [ 'type' => 'string', 'description' => 'One complete D4 et_pb_* shortcode subtree or one complete D5 divi/* block, matching the target page format.' ] ] ),
					'required'   => [ 'post_id', 'path', 'expected_type', 'markup' ],
				],
			],
			[
				'name'        => 'divi_insert_module',
				'description' => 'Insert one whole Divi node before, after, or as the first/last child of an addressed node. Markup must be one balanced D4 et_pb_* subtree or one D5 divi/* block. Child insertion is refused for D4 leaves/opaque nodes and D5 opaque wrappers; before/after insertion may use an advertised opaque D5 wrapper as the reference. Requires expected_type because paths shift. Supports dry_run and emits an undo token. Requires edit_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => array_merge(
						$write_common,
						[
							'position' => [ 'type' => 'string', 'enum' => [ 'before', 'after', 'first_child', 'last_child' ], 'description' => 'Placement relative to the addressed reference node.' ],
							'markup'   => [ 'type' => 'string', 'description' => 'One complete D4 et_pb_* shortcode subtree or one complete D5 divi/* block.' ],
						]
					),
					'required'   => [ 'post_id', 'path', 'expected_type', 'position', 'markup' ],
				],
			],
			[
				'name'        => 'divi_delete_module',
				'description' => 'Delete one addressed Divi node with all descendants. A D5 opaque legacy wrapper may be deleted at its advertised path, while descendants beneath it remain unaddressable. D4 malformed/opaque nodes are refused because they do not have a reliable subtree boundary. Requires expected_type because paths shift. Supports dry_run and emits an undo token. Requires edit_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => $write_common,
					'required'   => [ 'post_id', 'path', 'expected_type' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Divi tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Divi is not active.' );
		}

		switch ( $name ) {
			case 'divi_get_page_outline':
				return self::get_page_outline( $args );
			case 'divi_get_module':
				return self::get_module( $args );
			case 'divi_replace_module':
				return self::replace_module( $args );
			case 'divi_insert_module':
				return self::insert_module( $args );
			case 'divi_delete_module':
				return self::delete_module( $args );
			default:
				throw new \Exception( 'Unknown Divi tool: ' . esc_html( (string) $name ) );
		}
	}

	public static function detect_format( string $content, string $use_builder = '' ): string {
		$has_d5 = (bool) preg_match( '/<!--\s+wp:divi\//', $content );
		$has_d4 = false !== strpos( $content, '[et_pb_' );
		if ( $has_d5 ) {
			return $has_d4 ? 'd5-mixed' : 'd5-blocks';
		}
		if ( 'on' === $use_builder || $has_d4 ) {
			return 'd4-shortcode';
		}
		return 'none';
	}

	private static function get_page_outline( array $args ): array {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$depth   = isset( $args['depth'] ) ? max( 0, min( 20, (int) $args['depth'] ) ) : 6;
		$post    = self::require_readable_post( $post_id );
		$content = (string) $post->post_content;
		$format  = self::format_for_post( $post );
		$base    = self::base_response( $post, $format );

		return array_merge( $base, self::outline_for_content( $content, $format, $depth ) );
	}

	private static function get_module( array $args ): array {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$path    = self::require_path( $args );
		$post    = self::require_readable_post( $post_id );
		$content = (string) $post->post_content;
		$format  = self::format_for_post( $post );

		if ( 'd4-shortcode' === $format ) {
			$parsed = Divi_Shortcode_Parser::parse( $content );
			$hit = Divi_Shortcode_Parser::resolve_path( $parsed['nodes'], $path );
			if ( null === $hit['node'] ) {
				return self::not_found( $post_id, $path, $format, $hit['searched_count'] );
			}
			$node = $hit['node'];
			return [
				'post_id'                   => $post_id,
				'path'                      => $path,
				'found'                     => true,
				'format'                    => $format,
				'type'                      => $node['type'],
				'kind'                      => $node['kind'],
				'module_id'                 => $node['module_id'],
				'admin_label'               => $node['admin_label'],
				'attributes'                => $node['opaque'] ? new \stdClass() : $node['attributes'],
				'attributes_representation' => $node['opaque'] ? 'opaque' : 'd4-parsed-raw',
				'inner_text'                => $node['inner_text'],
				'child_count'               => count( $node['children'] ),
				'depth'                     => substr_count( $path, '.' ),
				'raw'                       => $node['opaque'] ? $node['raw'] : null,
			];
		}

		if ( 'd5-blocks' !== $format && 'd5-mixed' !== $format ) {
			return self::not_found( $post_id, $path, $format, 0 );
		}

		$blocks = Block_Parser::parse( $content );
		$hit = self::resolve_d5_path( $blocks, $path );
		if ( null === $hit['block'] ) {
			return self::not_found( $post_id, $path, $format, self::count_blocks( $blocks ) );
		}
		$detail = self::d5_detail( $hit['block'], $path, $format );
		$detail['post_id'] = $post_id;
		$detail['found']   = true;
		return $detail;
	}

	private static function replace_module( array $args ): array {
		$context = self::require_write_context( $args );
		$markup = self::require_markup( $args );

		if ( 'd4-shortcode' === $context['format'] ) {
			$replacement = Divi_Shortcode_Parser::parse_single_subtree( $markup );
			if ( ! $replacement['ok'] ) {
				throw new \Exception( esc_html( $replacement['error'] ) );
			}
			$node = self::require_d4_target( $context['content'], $context['path'], $context['expected_type'] );
			if ( ! empty( $node['opaque'] ) ) {
				throw new \Exception( 'Opaque or malformed D4 nodes cannot be replaced because they have no reliable subtree boundary.' );
			}
			$new_content = self::splice( $context['content'], $node['start'], $node['end'], $replacement['markup'] );
			self::assert_d4_candidate( $context['content'], $new_content );
			return self::finalize_content_write( $context, $new_content, 'Replaced Divi node at path ' . $context['path'], $args );
		}

		$blocks = Block_Parser::parse( $context['content'] );
		self::require_d5_target( $blocks, $context['path'], $context['expected_type'] );
		$new_block = self::parse_single_d5_block( $markup );
		$result = Block_Parser::replace_at( $blocks, $context['path'], $new_block );
		return self::finalize_d5_mutation( $context, $result, 'Replaced Divi block at path ' . $context['path'], $args );
	}

	private static function insert_module( array $args ): array {
		$context = self::require_write_context( $args );
		$markup = self::require_markup( $args );
		$position = isset( $args['position'] ) ? (string) $args['position'] : '';
		$allowed = [ 'before', 'after', 'first_child', 'last_child' ];
		if ( ! in_array( $position, $allowed, true ) ) {
			throw new \Exception( 'position must be one of: before, after, first_child, last_child.' );
		}

		if ( 'd4-shortcode' === $context['format'] ) {
			$insert = Divi_Shortcode_Parser::parse_single_subtree( $markup );
			if ( ! $insert['ok'] ) {
				throw new \Exception( esc_html( $insert['error'] ) );
			}
			$node = self::require_d4_target( $context['content'], $context['path'], $context['expected_type'] );
			if ( ! empty( $node['opaque'] ) ) {
				throw new \Exception( 'Opaque or malformed D4 nodes cannot be used as insertion references.' );
			}
			if ( 'before' === $position ) {
				$offset = $node['start'];
			} elseif ( 'after' === $position ) {
				$offset = $node['end'];
			} else {
				if ( null === $node['close_start'] ) {
					throw new \Exception( 'first_child and last_child require a paired D4 container; self-closing nodes have no child boundary.' );
				}
				$offset = 'first_child' === $position ? $node['open_end'] : $node['close_start'];
			}
			$new_content = self::splice( $context['content'], $offset, $offset, $insert['markup'] );
			self::assert_d4_candidate( $context['content'], $new_content );
			return self::finalize_content_write( $context, $new_content, sprintf( 'Inserted Divi node %s path %s', $position, $context['path'] ), $args );
		}

		$blocks = Block_Parser::parse( $context['content'] );
		$target = self::require_d5_target( $blocks, $context['path'], $context['expected_type'] );
		if ( in_array( $position, [ 'first_child', 'last_child' ], true ) && self::is_d5_opaque( $target ) ) {
			throw new \Exception( 'Cannot insert inside an opaque D5 wrapper; it is one indivisible boundary in the Divi path model.' );
		}
		$new_block = self::parse_single_d5_block( $markup );
		$result = Block_Parser::insert_at( $blocks, $context['path'], $new_block, $position );
		return self::finalize_d5_mutation( $context, $result, sprintf( 'Inserted Divi block %s path %s', $position, $context['path'] ), $args );
	}

	private static function delete_module( array $args ): array {
		$context = self::require_write_context( $args );

		if ( 'd4-shortcode' === $context['format'] ) {
			$node = self::require_d4_target( $context['content'], $context['path'], $context['expected_type'] );
			if ( ! empty( $node['opaque'] ) ) {
				throw new \Exception( 'Opaque or malformed D4 nodes cannot be deleted because they have no reliable subtree boundary.' );
			}
			$new_content = self::splice( $context['content'], $node['start'], $node['end'], '' );
			self::assert_d4_candidate( $context['content'], $new_content );
			return self::finalize_content_write( $context, $new_content, 'Deleted Divi node at path ' . $context['path'], $args, [ 'deleted_type' => $node['type'] ] );
		}

		$blocks = Block_Parser::parse( $context['content'] );
		$target = self::require_d5_target( $blocks, $context['path'], $context['expected_type'] );
		$result = Block_Parser::remove_at( $blocks, $context['path'] );
		return self::finalize_d5_mutation( $context, $result, 'Deleted Divi block at path ' . $context['path'], $args, [ 'deleted_type' => $target['blockName'] ?? null ] );
	}

	private static function require_write_context( array $args ): array {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to edit this post.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post not found: ' . $post_id );
		}
		$path = self::require_path( $args );
		$expected_type = isset( $args['expected_type'] ) ? (string) $args['expected_type'] : '';
		if ( '' === $expected_type ) {
			throw new \Exception( 'expected_type is required. Re-read the outline before writing because positional paths shift.' );
		}
		$content = (string) $post->post_content;
		$format = self::format_for_post( $post );
		if ( 'none' === $format ) {
			throw new \Exception( 'The target post does not contain a Divi layout.' );
		}
		if ( 'd4-shortcode' !== $format && 'd5-blocks' !== $format && 'd5-mixed' !== $format ) {
			throw new \Exception( 'Unsupported Divi content format: ' . esc_html( $format ) );
		}

		return compact( 'post', 'post_id', 'path', 'expected_type', 'content', 'format' );
	}

	private static function require_d4_target( string $content, string $path, string $expected_type ): array {
		$parsed = Divi_Shortcode_Parser::parse( $content );
		$hit = Divi_Shortcode_Parser::resolve_path( $parsed['nodes'], $path );
		if ( null === $hit['node'] ) {
			throw new \Exception( sprintf( 'No Divi node found at path "%s"; nothing was changed. Re-read the outline because paths shift.', esc_html( $path ) ) );
		}
		$node = $hit['node'];
		self::assert_expected_type( $expected_type, (string) $node['type'], $path );
		return $node;
	}

	private static function require_d5_target( array $blocks, string $path, string $expected_type ): array {
		$hit = self::resolve_d5_path( $blocks, $path );
		if ( null === $hit['block'] ) {
			throw new \Exception( sprintf( 'No addressable Divi block found at path "%s"; nothing was changed. Re-read the outline because paths shift or an opaque boundary may hide this path.', esc_html( $path ) ) );
		}
		$block = $hit['block'];
		$actual = $block['blockName'] ?? null;
		self::assert_expected_type( $expected_type, is_string( $actual ) ? $actual : 'freeform content', $path );
		if ( ! self::is_d5_opaque( $block ) && ( ! is_string( $actual ) || 0 !== strpos( $actual, 'divi/' ) ) ) {
			throw new \Exception( 'The addressed block is not a divi/* block and cannot be changed through Divi tools.' );
		}
		return $block;
	}

	private static function assert_expected_type( string $expected, string $actual, string $path ): void {
		if ( $expected !== $actual ) {
			throw new \Exception(
				sprintf(
					'Guard failed: expected "%s" at path %s but found "%s". Nothing was changed. Re-read the Divi outline because paths shift.',
					esc_html( $expected ),
					esc_html( $path ),
					esc_html( $actual )
				)
			);
		}
	}

	private static function require_markup( array $args ): string {
		if ( ! isset( $args['markup'] ) || ! is_string( $args['markup'] ) || '' === trim( $args['markup'] ) ) {
			throw new \Exception( 'markup is required.' );
		}
		return $args['markup'];
	}

	private static function parse_single_d5_block( string $markup ): array {
		$parsed = Block_Parser::parse( trim( $markup ) );
		$parsed = array_values(
			array_filter(
				$parsed,
				static function ( $block ) {
					return ( $block['blockName'] ?? null ) !== null || '' !== trim( (string) ( $block['innerHTML'] ?? '' ) );
				}
			)
		);
		if ( count( $parsed ) !== 1 ) {
			throw new \Exception( 'D5 markup must contain exactly one top-level divi/* block.' );
		}
		$name = $parsed[0]['blockName'] ?? null;
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'divi/' ) ) {
			throw new \Exception( 'D5 markup must be a divi/* block.' );
		}
		return $parsed[0];
	}

	private static function assert_d4_candidate( string $before, string $after ): void {
		$before_parsed = Divi_Shortcode_Parser::parse( $before );
		$after_parsed = Divi_Shortcode_Parser::parse( $after );
		if ( count( $after_parsed['unsupported_segments'] ) > count( $before_parsed['unsupported_segments'] ) ) {
			throw new \Exception( 'The D4 mutation introduced malformed shortcode regions, so nothing was written.' );
		}
	}

	private static function splice( string $content, int $start, int $end, string $replacement ): string {
		return substr( $content, 0, $start ) . $replacement . substr( $content, $end );
	}

	private static function finalize_d5_mutation( array $context, array $result, string $summary, array $args, array $extra = [] ): array {
		if ( empty( $result['ok'] ) ) {
			throw new \Exception( esc_html( (string) ( $result['error'] ?? 'Divi block mutation failed.' ) ) );
		}
		$check = Block_Parser::round_trip_check( $result['tree'] );
		if ( empty( $check['ok'] ) ) {
			throw new \Exception( esc_html( (string) $check['reason'] ) );
		}
		return self::finalize_content_write( $context, $check['content'], $summary, $args, $extra );
	}

	private static function finalize_content_write( array $context, string $new_content, string $summary, array $args, array $extra = [] ): array {
		$resulting_format = self::detect_format( $new_content, (string) get_post_meta( $context['post_id'], '_et_pb_use_builder', true ) );
		$preview = self::outline_for_content( $new_content, $resulting_format, 10 );
		$base = array_merge(
			[
				'post_id'           => $context['post_id'],
				'path'              => $context['path'],
				'format'            => $context['format'],
				'resulting_format'  => $resulting_format,
				'summary'           => $summary,
				'resulting_outline' => $preview['outline'],
			],
			$extra
		);

		if ( ! empty( $args['dry_run'] ) ) {
			return array_merge( $base, [ 'dry_run' => true, 'written' => false, 'content_length' => strlen( $new_content ) ] );
		}

		if ( ! self::has_cache_invalidator() ) {
			throw new \Exception( 'Divi cache invalidation is unavailable: ET_Core_PageResource::remove_static_resources() was not found. Nothing was changed.' );
		}

		$undo = Undo_Store::store(
			[
				'op'           => 'divi_content_write',
				'summary'      => $summary,
				'target'       => [ 'post_id' => $context['post_id'] ],
				'pre_op_state' => [ 'post_content' => $context['content'] ],
			]
		);

		$updated = wp_update_post(
			[
				'ID'           => $context['post_id'],
				'post_content' => wp_slash( $new_content ),
			],
			true
		);
		if ( is_wp_error( $updated ) ) {
			throw new \Exception( 'Failed to update Divi post: ' . esc_html( $updated->get_error_message() ) );
		}

		$fresh = get_post( $context['post_id'] );
		$verified = $fresh && (string) $fresh->post_content === $new_content;
		$invalidation = self::invalidate_derived_state( $context['post_id'] );

		return array_merge(
			$base,
			[
				'written'           => true,
				'verified'          => $verified,
				'verify_note'       => $verified
					? 'Stored content matches what was sent.'
					: 'WordPress modified the content on save. Re-read the Divi outline before another write.',
				'cache_invalidation' => $invalidation,
				'undo'              => $undo,
			]
		);
	}

	private static function has_cache_invalidator(): bool {
		return class_exists( 'ET_Core_PageResource' )
			&& is_callable( [ 'ET_Core_PageResource', 'remove_static_resources' ] );
	}

	public static function invalidate_derived_state_public( int $post_id ): array {
		return self::invalidate_derived_state( $post_id );
	}

	private static function invalidate_derived_state( int $post_id ): array {
		$result = [
			'requested' => false,
			'observed'  => false,
			'meta'      => [],
			'warnings'  => [],
		];
		if ( ! self::has_cache_invalidator() ) {
			$result['warnings'][] = 'ET_Core_PageResource::remove_static_resources() is unavailable; Divi derived caches were not invalidated.';
			return $result;
		}

		$observed = false;
		$observer = static function ( $removed_post_id ) use ( $post_id, &$observed ) {
			if ( (int) $removed_post_id === $post_id ) {
				$observed = true;
			}
		};
		if ( function_exists( 'add_action' ) ) {
			add_action( 'et_core_static_resources_removed', $observer, 10, 1 );
		}
		try {
			$result['requested'] = true;
			\ET_Core_PageResource::remove_static_resources( $post_id, 'core', false );
		} catch ( \Throwable $error ) {
			$result['warnings'][] = 'Divi cache invalidation failed after content committed: ' . $error->getMessage();
		}
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'et_core_static_resources_removed', $observer, 10 );
		}

		$result['observed'] = $observed;
		foreach ( self::DYNAMIC_CACHE_KEYS as $key ) {
			$result['meta'][ $key ] = '' === get_post_meta( $post_id, $key, true );
		}
		if ( ! $observed && in_array( false, $result['meta'], true ) ) {
			$result['warnings'][] = 'Divi accepted the invalidation request, but no removal action was observed and one or more dynamic-cache meta keys remain. Recheck the frontend before relying on the write.';
		}
		return $result;
	}

	private static function require_path( array $args ): string {
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';
		if ( ! Divi_Shortcode_Parser::is_valid_path( $path ) ) {
			throw new \Exception( "Invalid Divi path: '{$path}'. Paths are dot-separated 0-based indices, e.g. '0' or '1.2'." );
		}
		return $path;
	}

	private static function require_readable_post( int $post_id ) {
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required on target post.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post not found: ' . $post_id );
		}
		return $post;
	}

	private static function format_for_post( $post ): string {
		return self::detect_format( (string) $post->post_content, (string) get_post_meta( (int) $post->ID, '_et_pb_use_builder', true ) );
	}

	private static function base_response( $post, string $format ): array {
		return [
			'post_id'         => (int) $post->ID,
			'post_title'      => (string) $post->post_title,
			'post_type'       => (string) $post->post_type,
			'divi_enabled'    => 'none' !== $format,
			'format'          => $format,
			'builder_version' => defined( 'ET_BUILDER_VERSION' ) ? (string) constant( 'ET_BUILDER_VERSION' ) : null,
		];
	}

	private static function outline_for_content( string $content, string $format, int $depth ): array {
		if ( 'none' === $format ) {
			return [ 'outline' => [], 'unsupported_segments' => [], 'notes' => [] ];
		}
		if ( 'd4-shortcode' === $format ) {
			$parsed = Divi_Shortcode_Parser::parse( $content );
			return [
				'outline'              => Divi_Shortcode_Parser::outline( $parsed['nodes'], $depth ),
				'unsupported_segments' => $parsed['unsupported_segments'],
				'notes'                => $parsed['unsupported_segments']
					? [ 'Malformed legacy shortcode regions are shown as opaque nodes; no shortcode was executed.' ]
					: [],
			];
		}

		$blocks = Block_Parser::annotate_paths( Block_Parser::parse( $content ) );
		$unsupported = [];
		return [
			'outline'              => self::d5_outline( $blocks, $depth, $unsupported ),
			'unsupported_segments' => $unsupported,
			'notes'                => 'd5-mixed' === $format
				? [ 'This page mixes Divi 5 blocks with legacy Divi shortcodes. Legacy regions remain opaque under the D5 block path tree.' ]
				: [],
		];
	}

	private static function d5_outline( array $blocks, int $depth_remaining, array &$unsupported ): array {
		$out = [];
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;
			$path = (string) ( $block['_path'] ?? '' );
			$is_divi = is_string( $name ) && 0 === strpos( $name, 'divi/' );
			$is_opaque = self::is_d5_opaque( $block );

			$entry = [
				'path'        => $path,
				'type'        => $name,
				'kind'        => $is_divi ? self::d5_kind( (string) $name ) : 'unknown',
				'module_id'   => self::d5_meta( $block['attrs'] ?? [], [ 'module_id', 'moduleId', 'id' ] ),
				'admin_label' => self::d5_meta( $block['attrs'] ?? [], [ 'admin_label', 'adminLabel' ] ),
				'snippet'     => self::plain_snippet( (string) ( $block['innerHTML'] ?? '' ) ),
			];
			if ( $is_opaque ) {
				$entry['opaque'] = true;
				$unsupported[] = [
					'path'   => $path,
					'type'   => $name,
					'reason' => 'Legacy Divi shortcode content inside the Divi 5 block tree is kept opaque.',
				];
			}
			$children = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			if ( $children && ! $is_opaque ) {
				$entry['children'] = $depth_remaining > 0
					? self::d5_outline( $children, $depth_remaining - 1, $unsupported )
					: [ [ 'truncated' => true, 'reason' => 'Depth limit reached.' ] ];
			}
			$out[] = $entry;
		}
		return $out;
	}

	private static function d5_detail( array $block, string $path, string $format ): array {
		$name = $block['blockName'] ?? null;
		$html = (string) ( $block['innerHTML'] ?? '' );
		$opaque = self::is_d5_opaque( $block );
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
		return [
			'path'                      => $path,
			'format'                    => $format,
			'type'                      => $name,
			'kind'                      => is_string( $name ) && 0 === strpos( $name, 'divi/' ) ? self::d5_kind( $name ) : 'unknown',
			'module_id'                 => self::d5_meta( $attrs, [ 'module_id', 'moduleId', 'id' ] ),
			'admin_label'               => self::d5_meta( $attrs, [ 'admin_label', 'adminLabel' ] ),
			'attributes'                => $opaque ? new \stdClass() : $attrs,
			'attributes_representation' => $opaque ? 'opaque' : 'd5-block-attrs',
			'inner_text'                => $html,
			'child_count'               => count( $block['innerBlocks'] ?? [] ),
			'depth'                     => substr_count( $path, '.' ),
			'raw'                       => $opaque ? $html : null,
		];
	}

	private static function resolve_d5_path( array $blocks, string $path ): array {
		$indices = array_map( 'intval', explode( '.', $path ) );
		$current = $blocks;
		$last = count( $indices ) - 1;

		foreach ( $indices as $depth => $index ) {
			if ( ! isset( $current[ $index ] ) || ! is_array( $current[ $index ] ) ) {
				return [ 'block' => null, 'path' => $path ];
			}
			$block = $current[ $index ];
			if ( $depth === $last ) {
				return [ 'block' => $block, 'path' => $path ];
			}
			if ( self::is_d5_opaque( $block ) ) {
				return [ 'block' => null, 'path' => $path ];
			}
			$current = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
		}

		return [ 'block' => null, 'path' => $path ];
	}

	private static function is_d5_opaque( array $block ): bool {
		$name = $block['blockName'] ?? null;
		return 'divi/shortcode-module' === $name
			|| ( ( null === $name || 'core/freeform' === $name || 'core/html' === $name ) && false !== strpos( (string) ( $block['innerHTML'] ?? '' ), '[et_pb_' ) );
	}

	private static function d5_kind( string $name ): string {
		$short = substr( $name, 5 );
		if ( 'section' === $short ) return 'section';
		if ( 'row' === $short ) return 'row';
		if ( 'column' === $short ) return 'column';
		if ( 'shortcode-module' === $short ) return 'shortcode-wrapper';
		return 'module';
	}

	private static function d5_meta( array $attrs, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $attrs[ $key ] ) && is_scalar( $attrs[ $key ] ) ) {
				return (string) $attrs[ $key ];
			}
		}
		return null;
	}

	private static function plain_snippet( string $html ): string {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
		return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $text, 0, 80, '...' ) : substr( $text, 0, 80 );
	}

	private static function count_blocks( array $blocks ): int {
		$count = 0;
		foreach ( $blocks as $block ) {
			$count++;
			$count += self::count_blocks( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [] );
		}
		return $count;
	}

	private static function not_found( int $post_id, string $path, string $format, int $searched ): array {
		return [ 'post_id' => $post_id, 'path' => $path, 'found' => false, 'format' => $format, 'searched_count' => $searched ];
	}
}
