<?php

namespace More_MCP\Blocks;

use More_MCP\MCP\Undo_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content {

	public static function get_tools(): array {
		$path_desc = 'Dot-separated 0-based index path, e.g. "0" for the first top-level block or "1.2" for the third child of the second. Paths shift when siblings are inserted or removed. Always re-read the tree after a mutation rather than reusing an old path.';

		return [
			[
				'name'        => 'blocks_get_post_tree',
				'description' => 'Read a post or page as a structured Gutenberg block tree. Returns a flat list of blocks, each with its index path, block name, a plain-text snippet, and child count, far cheaper in tokens than fetching the full post_content. Use this first to find the path of the block you want to edit, then call blocks_get_block for full detail on one block. Works on any post type that stores block markup.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'        => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'depth'          => [ 'type' => 'integer', 'description' => 'How many levels of nesting to include. Default 10. Use 0 for top-level blocks only.' ],
						'snippet_length' => [ 'type' => 'integer', 'description' => 'Max characters of plain text per block. Default 160, max 1000.' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'blocks_get_block',
				'description' => 'Get one block from a post by its index path, including full attributes, raw inner HTML, and its serialized markup. Use after blocks_get_post_tree has told you which path you want.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'path'    => [ 'type' => 'string', 'description' => $path_desc ],
					],
					'required'   => [ 'post_id', 'path' ],
				],
			],
			[
				'name'        => 'blocks_insert',
				'description' => 'Insert a new block into a post at a position relative to an existing block. Supply the block either as structured fields (block_name plus attributes and inner_html) or as raw markup. Set dry_run=true to preview the resulting tree without writing. Requires edit_post capability on the target post. Emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'             => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'path'                => [ 'type' => 'string', 'description' => 'Path of the reference block. ' . $path_desc ],
						'position'            => [ 'type' => 'string', 'enum' => [ 'before', 'after', 'first_child', 'last_child' ], 'description' => 'Where to place the new block relative to the reference block.' ],
						'block_name'          => [ 'type' => 'string', 'description' => 'Block type, e.g. "core/paragraph". Required unless markup is supplied.' ],
						'attributes'          => [ 'type' => 'object', 'description' => 'Block attributes as a JSON object. Use blocks_get_type_schema to discover valid attributes.' ],
						'inner_html'          => [ 'type' => 'string', 'description' => 'The block\'s saved HTML, e.g. "<p>Hello</p>" for a paragraph. Must match what the block\'s save() function would produce.' ],
						'markup'              => [ 'type' => 'string', 'description' => 'Raw block markup as an alternative to the structured fields, e.g. "<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->". Takes precedence over block_name/attributes/inner_html.' ],
						'expected_block_name' => [ 'type' => 'string', 'description' => 'Guard: abort unless the block at path has this name. Protects against a stale path pointing somewhere unintended.' ],
						'dry_run'             => [ 'type' => 'boolean', 'description' => 'Preview the result without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'path', 'position' ],
				],
			],
			[
				'name'        => 'blocks_update',
				'description' => 'Update the block at a given path: replace its attributes, its inner HTML, or both. Attributes are replaced wholesale, not merged: read the block first with blocks_get_block, then send the complete attribute set you want. Set dry_run=true to preview. Requires edit_post. Emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'             => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'path'                => [ 'type' => 'string', 'description' => $path_desc ],
						'attributes'          => [ 'type' => 'object', 'description' => 'Complete replacement attribute object. Omit to leave attributes unchanged.' ],
						'inner_html'          => [ 'type' => 'string', 'description' => 'Replacement saved HTML for the block. Omit to leave unchanged. Ignored for blocks that have child blocks.' ],
						'expected_block_name' => [ 'type' => 'string', 'description' => 'Guard: abort unless the block at path has this name.' ],
						'dry_run'             => [ 'type' => 'boolean', 'description' => 'Preview the result without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'path' ],
				],
			],
			[
				'name'        => 'blocks_delete',
				'description' => 'Delete the block at a given path, including all of its children. Set dry_run=true to preview what would be removed. Requires edit_post. Emits an undo token that restores the full pre-deletion content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'             => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'path'                => [ 'type' => 'string', 'description' => $path_desc ],
						'expected_block_name' => [ 'type' => 'string', 'description' => 'Guard: abort unless the block at path has this name. Strongly recommended for deletes.' ],
						'dry_run'             => [ 'type' => 'boolean', 'description' => 'Preview without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'path' ],
				],
			],
			[
				'name'        => 'blocks_move',
				'description' => 'Move a block (with its children) from one path to another within the same post. The destination is resolved before the source is removed, so you can use the paths exactly as reported by blocks_get_post_tree without compensating for shifts. Requires edit_post. Emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'             => [ 'type' => 'integer', 'description' => 'Post or page ID.' ],
						'from_path'           => [ 'type' => 'string', 'description' => 'Path of the block to move. ' . $path_desc ],
						'to_path'             => [ 'type' => 'string', 'description' => 'Path of the reference block at the destination.' ],
						'position'            => [ 'type' => 'string', 'enum' => [ 'before', 'after', 'first_child', 'last_child' ], 'description' => 'Placement relative to the destination block.' ],
						'expected_block_name' => [ 'type' => 'string', 'description' => 'Guard: abort unless the block at from_path has this name.' ],
						'dry_run'             => [ 'type' => 'boolean', 'description' => 'Preview without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'from_path', 'to_path', 'position' ],
				],
			],
			[
				'name'        => 'blocks_validate_markup',
				'description' => 'Validate Gutenberg block markup server-side and report a confidence level rather than a pass/fail. Checks delimiter balance, attribute JSON, block registration, attribute types against registered schemas, and nesting constraints. IMPORTANT: PHP cannot run a block\'s JavaScript save() function, which is what the editor actually compares against, so this cannot guarantee the editor will accept the markup, and a block that is not registered server-side is reported as "unknown", never as invalid. Use before writing hand-authored markup.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'markup' => [ 'type' => 'string', 'description' => 'Raw block markup to validate.' ],
					],
					'required'   => [ 'markup' ],
				],
			],
			[
				'name'        => 'blocks_list_types',
				'description' => 'List Gutenberg block types registered on this site, with name, title, category, dynamic flag, and nesting constraints. Filter by category or search term. Note that blocks registered only in JavaScript do not appear: absence here does not mean a block is unavailable in the editor.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [ 'type' => 'string', 'description' => 'Filter by block category slug, e.g. "text", "media", "design".' ],
						'search'   => [ 'type' => 'string', 'description' => 'Case-insensitive substring match against block name and title.' ],
					],
				],
			],
			[
				'name'        => 'blocks_get_type_schema',
				'description' => 'Get the full attribute schema for one block type: every attribute with its type and default, plus supports flags and registered variations. Call this before constructing a block with blocks_insert so the attributes you send match what the block actually accepts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name' => [ 'type' => 'string', 'description' => 'Fully-qualified block name, e.g. "core/heading".' ],
					],
					'required'   => [ 'name' ],
				],
			],
		];
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'blocks_get_post_tree':
				return self::get_post_tree( $args );
			case 'blocks_get_block':
				return self::get_block( $args );
			case 'blocks_insert':
				return self::insert( $args );
			case 'blocks_update':
				return self::update( $args );
			case 'blocks_delete':
				return self::delete( $args );
			case 'blocks_move':
				return self::move( $args );
			case 'blocks_validate_markup':
				return self::validate_markup( $args );
			case 'blocks_list_types':
				return self::list_types( $args );
			case 'blocks_get_type_schema':
				return self::get_type_schema( $args );
		}
		throw new \Exception( 'Unknown blocks tool: ' . esc_html( $name ) );
	}

	

	
	private static function get_post_tree( array $args ): array {
		$post = self::require_readable_post( $args );

		$depth   = isset( $args['depth'] ) ? max( 0, (int) $args['depth'] ) : 10;
		$snippet = isset( $args['snippet_length'] ) ? min( 1000, max( 0, (int) $args['snippet_length'] ) ) : 160;

		$blocks  = Parser::parse( (string) $post->post_content );
		$summary = Parser::summarize( $blocks, '', $depth, $snippet );

		return [
			'post_id'      => $post->ID,
			'post_type'    => $post->post_type,
			'title'        => $post->post_title,
			'block_count'  => count( $summary ),
			'top_level'    => count( $blocks ),
			'blocks'       => $summary,
			'has_blocks'   => function_exists( 'has_blocks' ) ? has_blocks( $post->post_content ) : ! empty( $blocks ),
			'path_note'    => 'Index paths are positional and shift when siblings are inserted or removed. Re-read this tree after any mutation instead of reusing paths across calls.',
		];
	}

	private static function get_block( array $args ): array {
		$post = self::require_readable_post( $args );
		$path = self::require_path( $args, 'path' );

		$blocks   = Parser::parse( (string) $post->post_content );
		$resolved = Parser::resolve_path( $blocks, $path );

		if ( $resolved['block'] === null ) {
			throw new \Exception( sprintf( 'No block found at path "%s". Call blocks_get_post_tree to see current paths.', esc_html( $path ) ) );
		}

		$block = $resolved['block'];

		return [
			'post_id'        => $post->ID,
			'path'           => $path,
			'blockName'      => $block['blockName'] ?? null,
			'attributes'     => $block['attrs'] ?? [],
			'inner_html'     => $block['innerHTML'] ?? '',
			'children_count' => is_array( $block['innerBlocks'] ?? null ) ? count( $block['innerBlocks'] ) : 0,
			'markup'         => Parser::serialize( [ $block ] ),
			'is_freeform'    => ( $block['blockName'] ?? null ) === null,
		];
	}

	private static function validate_markup( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to validate block markup.' );
		}
		if ( ! isset( $args['markup'] ) || ! is_string( $args['markup'] ) ) {
			throw new \Exception( 'markup must be a string.' );
		}

		$result                = Validator::validate( $args['markup'] );
		$result['explanation'] = Validator::explain( $result['confidence'] );

		return $result;
	}

	private static function list_types( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to list block types.' );
		}
		if ( ! Registry::is_available() ) {
			throw new \Exception( 'The WordPress block type registry is not available on this site.' );
		}

		$types = Registry::list_types(
			isset( $args['category'] ) ? (string) $args['category'] : null,
			isset( $args['search'] ) ? (string) $args['search'] : null
		);

		return [
			'count'  => count( $types ),
			'blocks' => $types,
			'note'   => Registry::registry_caveat(),
		];
	}

	private static function get_type_schema( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to inspect block types.' );
		}
		if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
			throw new \Exception( 'name is required (a fully-qualified block name such as "core/heading").' );
		}

		$type = Registry::get( $args['name'] );
		if ( $type === null ) {
			throw new \Exception(
				sprintf(
					'Block type "%s" is not registered on the PHP side. %s',
					esc_html( $args['name'] ),
					Registry::registry_caveat()
				)
			);
		}

		return Registry::summarize( $type, true );
	}

	

	
	private static function insert( array $args ): array {
		$post   = self::require_editable_post( $args );
		$path   = self::require_path( $args, 'path' );
		$blocks = Parser::parse( (string) $post->post_content );

		self::assert_expected_block( $blocks, $path, $args );

		$position = isset( $args['position'] ) ? (string) $args['position'] : '';
		$new      = self::build_block_from_args( $args );

		$result = Parser::insert_at( $blocks, $path, $new, $position );
		if ( ! $result['ok'] ) {
			throw new \Exception( esc_html( $result['error'] ) );
		}

		return self::finalize( $post, $result['tree'], $args, sprintf( 'Inserted %s %s path %s', $new['blockName'] ?? 'block', $position, $path ) );
	}

	private static function update( array $args ): array {
		$post   = self::require_editable_post( $args );
		$path   = self::require_path( $args, 'path' );
		$blocks = Parser::parse( (string) $post->post_content );

		self::assert_expected_block( $blocks, $path, $args );

		$resolved = Parser::resolve_path( $blocks, $path );
		if ( $resolved['block'] === null ) {
			throw new \Exception( sprintf( 'No block found at path "%s".', esc_html( $path ) ) );
		}

		$block = $resolved['block'];
		$touched = false;

		if ( array_key_exists( 'attributes', $args ) ) {
			if ( ! is_array( $args['attributes'] ) ) {
				throw new \Exception( 'attributes must be an object.' );
			}
			$block['attrs'] = $args['attributes'];
			$touched        = true;
		}

		if ( array_key_exists( 'inner_html', $args ) ) {
			if ( ! is_string( $args['inner_html'] ) ) {
				throw new \Exception( 'inner_html must be a string.' );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				throw new \Exception( 'This block has child blocks, so its inner_html is derived from those children and cannot be set directly. Edit the child blocks instead, or delete and re-insert this block.' );
			}
			$block['innerHTML']    = $args['inner_html'];
			$block['innerContent'] = [ $args['inner_html'] ];
			$touched               = true;
		}

		if ( ! $touched ) {
			throw new \Exception( 'Nothing to update: supply attributes, inner_html, or both.' );
		}

		$result = Parser::replace_at( $blocks, $path, $block );
		if ( ! $result['ok'] ) {
			throw new \Exception( esc_html( $result['error'] ) );
		}

		return self::finalize( $post, $result['tree'], $args, sprintf( 'Updated %s at path %s', $block['blockName'] ?? 'block', $path ) );
	}

	private static function delete( array $args ): array {
		$post   = self::require_editable_post( $args );
		$path   = self::require_path( $args, 'path' );
		$blocks = Parser::parse( (string) $post->post_content );

		self::assert_expected_block( $blocks, $path, $args );

		$result = Parser::remove_at( $blocks, $path );
		if ( ! $result['ok'] ) {
			throw new \Exception( esc_html( $result['error'] ) );
		}

		$removed_name = $result['removed']['blockName'] ?? 'freeform content';

		$extra = [
			'deleted_block' => [
				'blockName' => $result['removed']['blockName'] ?? null,
				'markup'    => Parser::serialize( [ $result['removed'] ] ),
			],
		];

		return self::finalize( $post, $result['tree'], $args, sprintf( 'Deleted %s at path %s', $removed_name, $path ), $extra );
	}

	private static function move( array $args ): array {
		$post = self::require_editable_post( $args );
		$from = self::require_path( $args, 'from_path' );
		$to   = self::require_path( $args, 'to_path' );

		$blocks = Parser::parse( (string) $post->post_content );

		self::assert_expected_block( $blocks, $from, $args );

		$position = isset( $args['position'] ) ? (string) $args['position'] : '';

		$result = Parser::move( $blocks, $from, $to, $position );
		if ( ! $result['ok'] ) {
			throw new \Exception( esc_html( $result['error'] ) );
		}

		return self::finalize( $post, $result['tree'], $args, sprintf( 'Moved block from %s to %s (%s)', $from, $to, $position ) );
	}

	

	
	private static function finalize( \WP_Post $post, array $tree, array $args, string $summary, array $extra = [] ): array {
		$check = Parser::round_trip_check( $tree );
		if ( ! $check['ok'] ) {
			throw new \Exception( esc_html( $check['reason'] ) );
		}
		$new_content = $check['content'];

		$preview = Parser::summarize( $tree, '', 10, 80 );

		if ( ! empty( $args['dry_run'] ) ) {
			return array_merge(
				[
					'dry_run'         => true,
					'written'         => false,
					'summary'         => $summary,
					'post_id'         => $post->ID,
					'resulting_tree'  => $preview,
					'content_preview' => mb_substr( $new_content, 0, 2000 ),
					'content_length'  => strlen( $new_content ),
				],
				$extra
			);
		}

		$undo = Undo_Store::store(
			[
				'op'           => 'blocks_mutation',
				'summary'      => $summary,
				'target'       => [ 'post_id' => $post->ID ],
				'pre_op_state' => [ 'post_content' => (string) $post->post_content ],
			]
		);

		$updated = wp_update_post(
			[
				'ID'           => $post->ID,

				'post_content' => wp_slash( $new_content ),
			],
			true
		);

		if ( is_wp_error( $updated ) ) {
			throw new \Exception( 'Failed to update post: ' . esc_html( $updated->get_error_message() ) );
		}

		$fresh    = get_post( $post->ID );
		$verified = $fresh instanceof \WP_Post && (string) $fresh->post_content === $new_content;

		return array_merge(
			[
				'written'        => true,
				'summary'        => $summary,
				'post_id'        => $post->ID,
				'verified'       => $verified,
				'verify_note'    => $verified
					? 'Stored content matches what was sent.'
					: 'WordPress modified the content on save (kses filtering, oEmbed handling, or similar). Re-read the post to see the stored result.',
				'resulting_tree' => $preview,
				'undo'           => $undo,
			],
			$extra
		);
	}

	private static function build_block_from_args( array $args ): array {
		if ( ! empty( $args['markup'] ) && is_string( $args['markup'] ) ) {
			$parsed = Parser::parse( $args['markup'] );
			$parsed = array_values(
				array_filter(
					$parsed,
					static function ( $b ) {

						
						return ( $b['blockName'] ?? null ) !== null || trim( (string) ( $b['innerHTML'] ?? '' ) ) !== '';
					}
				)
			);
			if ( empty( $parsed ) ) {
				throw new \Exception( 'markup did not parse into any block.' );
			}
			if ( count( $parsed ) > 1 ) {
				throw new \Exception( 'markup contains more than one top-level block; insert them one at a time.' );
			}
			return $parsed[0];
		}

		if ( empty( $args['block_name'] ) || ! is_string( $args['block_name'] ) ) {
			throw new \Exception( 'Supply either markup, or block_name (plus optional attributes and inner_html).' );
		}

		$attrs = [];
		if ( isset( $args['attributes'] ) ) {
			if ( ! is_array( $args['attributes'] ) ) {
				throw new \Exception( 'attributes must be an object.' );
			}
			$attrs = $args['attributes'];
		}

		$inner = isset( $args['inner_html'] ) ? (string) $args['inner_html'] : '';

		return [
			'blockName'    => $args['block_name'],
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $inner,
			'innerContent' => [ $inner ],
		];
	}

	private static function assert_expected_block( array $blocks, string $path, array $args ): void {
		if ( empty( $args['expected_block_name'] ) || ! is_string( $args['expected_block_name'] ) ) {
			return;
		}

		$resolved = Parser::resolve_path( $blocks, $path );
		if ( $resolved['block'] === null ) {
			throw new \Exception( sprintf( 'No block found at path "%s"; nothing was changed.', esc_html( $path ) ) );
		}

		$actual = $resolved['block']['blockName'] ?? null;
		if ( $actual !== $args['expected_block_name'] ) {
			throw new \Exception(
				sprintf(
					'Guard failed: expected "%s" at path %s but found "%s". Nothing was changed. Re-read the tree with blocks_get_post_tree. Paths shift when siblings change.',
					esc_html( $args['expected_block_name'] ),
					esc_html( $path ),
					esc_html( $actual ?? 'freeform content' )
				)
			);
		}
	}

	private static function require_path( array $args, string $key ): string {
		if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) || $args[ $key ] === '' ) {
			throw new \Exception( sprintf( '%s is required (a dot-separated index path such as "0" or "1.2").', esc_html( $key ) ) );
		}
		if ( ! Parser::is_valid_path( $args[ $key ] ) ) {
			throw new \Exception( sprintf( '%s must contain only digits and dots, e.g. "0" or "1.2". Got: %s', esc_html( $key ), esc_html( $args[ $key ] ) ) );
		}
		return $args[ $key ];
	}

	private static function require_readable_post( array $args ): \WP_Post {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}

		if ( ! current_user_can( 'read' ) ) {
			throw new \Exception( 'You do not have permission to read this post\'s block structure.' );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \Exception( 'Post not found.' );
		}

		
		
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			throw new \Exception( 'You do not have permission to read this post\'s block structure.' );
		}
		return $post;
	}

	private static function require_editable_post( array $args ): \WP_Post {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to edit this post.' );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \Exception( 'Post not found.' );
		}
		return $post;
	}
}
