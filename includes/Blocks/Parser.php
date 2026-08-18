<?php
/**
 * Block parser, serializer, and index-path navigator.
 *
 * This module owns the parse → mutate → serialize → verify → write pipeline.
 * Every other Blocks module calls into these helpers rather than touching
 * parse_blocks / serialize_blocks directly, because the interleaving between
 * innerHTML, innerContent, and innerBlocks is subtle enough to corrupt silently.
 *
 * Index-path addressing:
 *   Paths are dot-separated integers: "0.2.1" = third child of the first
 *   top-level block's third child. They are 0-based. A path always identifies
 *   a block at read time; they shift when siblings are inserted or removed,
 *   so every mutating tool returns the post-mutation tree for re-anchoring.
 */

namespace More_MCP\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Parser {

	/**
	 * Parse post_content into a flat list of top-level blocks, with children nested.
	 *
	 * @param string $content Raw post_content.
	 * @return array Parsed block list from parse_blocks().
	 */
	public static function parse( string $content ): array {
		return \parse_blocks( $content );
	}

	/**
	 * Serialize a block list back to post_content.
	 *
	 * @param array $blocks Block list (from parse or constructed).
	 * @return string Serialized block markup.
	 */
	public static function serialize( array $blocks ): string {
		return \serialize_blocks( $blocks );
	}

	/**
	 * Self-consistency check for a mutated block tree.
	 *
	 * Serializes the tree, reparses that markup, and reserializes it. A tree
	 * whose innerContent markers correctly describe its innerBlocks is a fixed
	 * point of that operation. A mismatch means the mutation produced markup
	 * WordPress would reparse into a different structure — the corruption mode
	 * this whole module exists to prevent — so the caller must abort the write.
	 *
	 * Note this deliberately does NOT compare against the pre-mutation content:
	 * the tree is *supposed* to differ from the original. What must not differ
	 * is the tree from its own round-trip.
	 *
	 * @param array $mutated_blocks The block list after the intended mutation.
	 * @return array{ok: bool, content?: string, reason?: string, expected?: string, got?: string}
	 */
	public static function round_trip_check( array $mutated_blocks ): array {
		$serialized = self::serialize( $mutated_blocks );

		// parse_blocks() always returns something, so a structural disagreement
		// shows up as a difference on the second serialization pass.
		$reparsed     = self::parse( $serialized );
		$reserialized = self::serialize( $reparsed );

		if ( $serialized === $reserialized ) {
			return [ 'ok' => true, 'content' => $serialized ];
		}

		return [
			'ok'       => false,
			'reason'   => 'Block tree failed its serialize→reparse→serialize self-consistency check. The mutated tree would not survive a WordPress reparse intact, so nothing was written. This indicates malformed block input (most often innerContent markers that do not match innerBlocks).',
			'expected' => substr( $serialized, 0, 2000 ),
			'got'      => substr( $reserialized, 0, 2000 ),
		];
	}

	// ------------------------------------------------------------------
	//  Index-path navigation
	// ------------------------------------------------------------------

	/**
	 * Navigate to the block at a dot-separated index path.
	 *
	 * NOTE: returns a COPY of the block. PHP arrays are value types, so the
	 * returned block cannot be mutated to affect the source tree. Use the
	 * mutation helpers (insert_at / remove_at / replace_at / move) to change
	 * the tree — they rebuild the affected spine rather than aliasing it.
	 *
	 * @param array  $blocks The full top-level block list.
	 * @param string $path   Dot-separated indices, e.g. "0.2.1". Empty string = root.
	 * @return array{block: array|null, index: int|null, path: string}
	 *   block = the target block, or null when the path does not resolve
	 *   index = integer index within its immediate parent, or null
	 *   path  = the resolved path (echoed back for error messages)
	 */
	public static function resolve_path( array $blocks, string $path ): array {
		if ( $path === '' || ! self::is_valid_path( $path ) ) {
			return [ 'block' => null, 'index' => null, 'path' => $path ];
		}

		$indices = array_map( 'intval', explode( '.', $path ) );
		$current = $blocks;
		$last    = count( $indices ) - 1;

		foreach ( $indices as $depth => $idx ) {
			if ( ! isset( $current[ $idx ] ) || ! is_array( $current[ $idx ] ) ) {
				return [ 'block' => null, 'index' => null, 'path' => $path ];
			}

			if ( $depth === $last ) {
				return [
					'block' => $current[ $idx ],
					'index' => $idx,
					'path'  => $path,
				];
			}

			if ( ! isset( $current[ $idx ]['innerBlocks'] ) || ! is_array( $current[ $idx ]['innerBlocks'] ) ) {
				return [ 'block' => null, 'index' => null, 'path' => $path ];
			}

			$current = $current[ $idx ]['innerBlocks'];
		}

		return [ 'block' => null, 'index' => null, 'path' => $path ];
	}

	/**
	 * True when a block exists at the given path.
	 *
	 * @param array  $blocks Block list.
	 * @param string $path   Dot-separated index path.
	 * @return bool
	 */
	public static function path_exists( array $blocks, string $path ): bool {
		return self::resolve_path( $blocks, $path )['block'] !== null;
	}

	/**
	 * Find a block by its `anchor` attribute (the `anchor` key in attrs),
	 * searching recursively through the tree. Returns the first match.
	 *
	 * @param array  $blocks The full top-level block list.
	 * @param string $anchor The anchor string to find.
	 * @return array|null The matching block, or null.
	 */
	public static function find_by_anchor( array $blocks, string $anchor ): ?array {
		foreach ( $blocks as $block ) {
			if ( isset( $block['attrs']['anchor'] ) && $block['attrs']['anchor'] === $anchor ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_by_anchor( $block['innerBlocks'], $anchor );
				if ( $found !== null ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Recursively annotate every block with its current dot-separated path.
	 *
	 * Returns a new array (does not mutate input). Each block gets a `_path` key.
	 * Freeform blocks (blockName === null) are included but annotated as freeform.
	 *
	 * @param array  $blocks The block list (top-level or inner).
	 * @param string $prefix Current path prefix (empty at top level).
	 * @return array Annotated block list.
	 */
	public static function annotate_paths( array $blocks, string $prefix = '' ): array {
		$annotated = [];
		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
			$copy = $block;
			$copy['_path'] = $path;

			if ( ! empty( $copy['innerBlocks'] ) && is_array( $copy['innerBlocks'] ) ) {
				$copy['innerBlocks'] = self::annotate_paths( $copy['innerBlocks'], $path );
			}

			$annotated[] = $copy;
		}
		return $annotated;
	}

	/**
	 * Produce a token-budget-friendly summary of a block tree.
	 *
	 * Each block is represented as:
	 *   { path, blockName, snippet (truncated plain text), children_count }
	 *
	 * Recursion is depth-limited. Snippets are content-truncated.
	 * Designed for blocks_get_post_tree — returns a flat array of summaries,
	 * not a nested tree, so the AI client gets token-efficient output.
	 *
	 * @param array  $blocks          The block list.
	 * @param string $prefix          Current path prefix.
	 * @param int    $depth_remaining How many levels of nesting to recurse.
	 * @param int    $snippet_length  Max characters of plain text per block.
	 * @return array Flat list of block summary arrays.
	 */
	public static function summarize( array $blocks, string $prefix = '', int $depth_remaining = 10, int $snippet_length = 160 ): array {
		$summary = [];
		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
			$inner_count = is_array( $block['innerBlocks'] ?? null ) ? count( $block['innerBlocks'] ) : 0;

			$summary[] = [
				'path'           => $path,
				'blockName'      => $block['blockName'] ?? null,
				'snippet'        => self::plain_text_snippet( $block['innerHTML'] ?? '', $snippet_length ),
				'children_count' => $inner_count,
			];

			if ( $depth_remaining > 0 && $inner_count > 0 ) {
				$summary = array_merge(
					$summary,
					self::summarize( $block['innerBlocks'], $path, $depth_remaining - 1, $snippet_length )
				);
			}
		}
		return $summary;
	}

	// ------------------------------------------------------------------
	//  Mutation helpers
	//
	//  All mutations are implemented as pure functions that return a NEW tree.
	//  PHP arrays are value types, so a helper that "navigates to a node and
	//  edits it" silently edits a copy and loses the change. Every helper here
	//  instead rebuilds the spine from the root down to the touched node.
	// ------------------------------------------------------------------

	/**
	 * Apply a callback to the array of siblings that contains the block at $path.
	 *
	 * The callback receives ($siblings, $index) and must return the new sibling
	 * array. This is the single primitive every mutation is built on: it walks
	 * to the parent of the target, hands the callback that level, and rebuilds
	 * each ancestor on the way back out so the change reaches the caller's tree.
	 *
	 * @param array    $blocks   Full top-level block list.
	 * @param string   $path     Dot-separated path of the TARGET block.
	 * @param callable $mutator  fn(array $siblings, int $index): array
	 * @return array{ok: bool, tree?: array, error?: string}
	 */
	private static function mutate_at( array $blocks, string $path, callable $mutator ): array {
		if ( ! self::is_valid_path( $path ) || $path === '' ) {
			return [ 'ok' => false, 'error' => "Invalid block path: '{$path}'. Paths are dot-separated 0-based indices, e.g. '0' or '1.2'." ];
		}

		$indices = array_map( 'intval', explode( '.', $path ) );
		$result  = self::mutate_recursive( $blocks, $indices, 0, $mutator );

		if ( ! $result['ok'] ) {
			return $result;
		}
		return [ 'ok' => true, 'tree' => $result['blocks'] ];
	}

	/**
	 * Recursive worker for mutate_at(). Returns the rebuilt level.
	 *
	 * @param array    $level   The sibling array at this depth.
	 * @param int[]    $indices Full index path.
	 * @param int      $depth   Current depth into $indices.
	 * @param callable $mutator Callback applied at the final depth.
	 * @return array{ok: bool, blocks?: array, error?: string}
	 */
	private static function mutate_recursive( array $level, array $indices, int $depth, callable $mutator ): array {
		$idx      = $indices[ $depth ];
		$is_final = ( $depth === count( $indices ) - 1 );

		if ( $is_final ) {
			// The mutator decides whether the index must already exist
			// (replace/remove) or may be an insertion point (insert).
			return [ 'ok' => true, 'blocks' => $mutator( $level, $idx ) ];
		}

		if ( ! isset( $level[ $idx ] ) || ! is_array( $level[ $idx ] ) ) {
			return [ 'ok' => false, 'error' => 'No block found at path segment ' . $idx . '.' ];
		}
		if ( ! isset( $level[ $idx ]['innerBlocks'] ) || ! is_array( $level[ $idx ]['innerBlocks'] ) ) {
			return [ 'ok' => false, 'error' => 'Block at path segment ' . $idx . ' has no innerBlocks to descend into.' ];
		}

		$child = self::mutate_recursive( $level[ $idx ]['innerBlocks'], $indices, $depth + 1, $mutator );
		if ( ! $child['ok'] ) {
			return $child;
		}

		// Rebuild this ancestor with the new child level, then repair its
		// innerContent so the null markers match the new child count.
		$level[ $idx ]['innerBlocks'] = $child['blocks'];
		self::rebuild_inner_content( $level[ $idx ] );

		return [ 'ok' => true, 'blocks' => $level ];
	}

	/**
	 * Insert a block relative to a target path.
	 *
	 * @param array  $blocks    Full top-level block list.
	 * @param string $target    Path of the reference block.
	 * @param array  $new_block The block to insert.
	 * @param string $position  'before' | 'after' | 'first_child' | 'last_child'.
	 * @return array{ok: bool, tree?: array, error?: string}
	 */
	public static function insert_at( array $blocks, string $target, array $new_block, string $position ): array {
		$allowed = [ 'before', 'after', 'first_child', 'last_child' ];
		if ( ! in_array( $position, $allowed, true ) ) {
			return [ 'ok' => false, 'error' => "Invalid position '{$position}'. Use one of: " . implode( ', ', $allowed ) . '.' ];
		}

		if ( ! self::path_exists( $blocks, $target ) ) {
			return [ 'ok' => false, 'error' => "No block found at path '{$target}'." ];
		}

		if ( in_array( $position, [ 'first_child', 'last_child' ], true ) ) {
			return self::mutate_at(
				$blocks,
				$target,
				static function ( array $siblings, int $idx ) use ( $new_block, $position ): array {
					if ( ! isset( $siblings[ $idx ]['innerBlocks'] ) || ! is_array( $siblings[ $idx ]['innerBlocks'] ) ) {
						$siblings[ $idx ]['innerBlocks'] = [];
					}
					if ( $position === 'first_child' ) {
						array_unshift( $siblings[ $idx ]['innerBlocks'], $new_block );
					} else {
						$siblings[ $idx ]['innerBlocks'][] = $new_block;
					}
					self::rebuild_inner_content( $siblings[ $idx ] );
					return $siblings;
				}
			);
		}

		return self::mutate_at(
			$blocks,
			$target,
			static function ( array $siblings, int $idx ) use ( $new_block, $position ): array {
				$at = ( $position === 'before' ) ? $idx : $idx + 1;
				array_splice( $siblings, $at, 0, [ $new_block ] );
				return $siblings;
			}
		);
	}

	/**
	 * Remove the block at a given path.
	 *
	 * @param array  $blocks Full top-level block list.
	 * @param string $target Path of the block to remove.
	 * @return array{ok: bool, tree?: array, removed?: array, error?: string}
	 */
	public static function remove_at( array $blocks, string $target ): array {
		$resolved = self::resolve_path( $blocks, $target );
		if ( $resolved['block'] === null ) {
			return [ 'ok' => false, 'error' => "No block found at path '{$target}'." ];
		}
		$removed = $resolved['block'];

		$result = self::mutate_at(
			$blocks,
			$target,
			static function ( array $siblings, int $idx ): array {
				array_splice( $siblings, $idx, 1 );
				return $siblings;
			}
		);

		if ( ! $result['ok'] ) {
			return $result;
		}
		return [ 'ok' => true, 'tree' => $result['tree'], 'removed' => $removed ];
	}

	/**
	 * Replace the block at a given path wholesale.
	 *
	 * @param array  $blocks    Full top-level block list.
	 * @param string $target    Path of the block to replace.
	 * @param array  $new_block The replacement block.
	 * @return array{ok: bool, tree?: array, error?: string}
	 */
	public static function replace_at( array $blocks, string $target, array $new_block ): array {
		if ( ! self::path_exists( $blocks, $target ) ) {
			return [ 'ok' => false, 'error' => "No block found at path '{$target}'." ];
		}

		return self::mutate_at(
			$blocks,
			$target,
			static function ( array $siblings, int $idx ) use ( $new_block ): array {
				$siblings[ $idx ] = $new_block;
				return $siblings;
			}
		);
	}

	/**
	 * Move a block from one path to another.
	 *
	 * Removal happens first, which can shift the destination path when both
	 * live under the same parent and the source sits earlier. Rather than
	 * guessing at the correction, the destination block is resolved BEFORE the
	 * removal and re-located by identity afterwards, so the move lands next to
	 * the block the caller actually named.
	 *
	 * @param array  $blocks   Full top-level block list.
	 * @param string $from     Source path.
	 * @param string $to       Destination path.
	 * @param string $position 'before' | 'after' | 'first_child' | 'last_child'.
	 * @return array{ok: bool, tree?: array, error?: string}
	 */
	public static function move( array $blocks, string $from, string $to, string $position ): array {
		if ( $from === $to ) {
			return [ 'ok' => false, 'error' => 'Source and destination paths are identical; nothing to move.' ];
		}
		// Moving a block into its own subtree would detach the tree.
		if ( strpos( $to, $from . '.' ) === 0 ) {
			return [ 'ok' => false, 'error' => "Cannot move a block into its own descendant (from '{$from}' to '{$to}')." ];
		}

		$source = self::resolve_path( $blocks, $from );
		if ( $source['block'] === null ) {
			return [ 'ok' => false, 'error' => "No block found at source path '{$from}'." ];
		}
		$dest = self::resolve_path( $blocks, $to );
		if ( $dest['block'] === null ) {
			return [ 'ok' => false, 'error' => "No block found at destination path '{$to}'." ];
		}

		// Tag the destination so it can be found again after indices shift.
		$marker = '__more_mcp_move_target_' . md5( $to . wp_json_encode( $dest['block'] ) );
		$tagged = self::replace_at( $blocks, $to, array_merge( $dest['block'], [ '_moveMarker' => $marker ] ) );
		if ( ! $tagged['ok'] ) {
			return $tagged;
		}

		$removed = self::remove_at( $tagged['tree'], $from );
		if ( ! $removed['ok'] ) {
			return $removed;
		}
		$block = $removed['removed'];
		$tree  = $removed['tree'];

		$dest_path = self::find_marker_path( $tree, $marker );
		if ( $dest_path === null ) {
			return [ 'ok' => false, 'error' => 'Destination block was lost during the move; no changes were written.' ];
		}

		$inserted = self::insert_at( $tree, $dest_path, $block, $position );
		if ( ! $inserted['ok'] ) {
			return $inserted;
		}

		return [ 'ok' => true, 'tree' => self::strip_move_markers( $inserted['tree'] ) ];
	}

	/**
	 * Locate the path of the block carrying a given move marker.
	 *
	 * @param array  $blocks Block list.
	 * @param string $marker Marker value.
	 * @param string $prefix Path prefix (internal).
	 * @return string|null Path, or null when not found.
	 */
	private static function find_marker_path( array $blocks, string $marker, string $prefix = '' ): ?string {
		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
			if ( isset( $block['_moveMarker'] ) && $block['_moveMarker'] === $marker ) {
				return $path;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::find_marker_path( $block['innerBlocks'], $marker, $path );
				if ( $found !== null ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Remove internal move markers so they never reach serialization.
	 *
	 * @param array $blocks Block list.
	 * @return array Cleaned block list.
	 */
	private static function strip_move_markers( array $blocks ): array {
		foreach ( $blocks as $i => $block ) {
			unset( $blocks[ $i ]['_moveMarker'] );
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::strip_move_markers( $block['innerBlocks'] );
			}
		}
		return $blocks;
	}

	// ------------------------------------------------------------------
	//  innerContent reconstruction
	// ------------------------------------------------------------------

	/**
	 * Rebuild innerHTML and innerContent from innerBlocks.
	 *
	 * After inserting or removing children, the parent block's innerContent
	 * array must be reconstructed to interleave the remaining innerHTML
	 * fragments with null markers for each child. This prevents the most
	 * common corruption in manual block-tree mutation.
	 *
	 * @param array &$block The block to rebuild (mutated in place).
	 */
	public static function rebuild_inner_content( array &$block ): void {
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			// No children: innerContent is just the innerHTML.
			$block['innerContent'] = [ $block['innerHTML'] ?? '' ];
			return;
		}

		$count = count( $block['innerBlocks'] );
		$parts = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$parts[] = null; // null marker = slot for innerBlocks[$i]
		}
		// The last element must be a string (the trailing innerHTML after the last child).
		$parts[] = $block['innerHTML'] ?? '';

		$block['innerContent'] = $parts;
	}

	// ------------------------------------------------------------------
	//  Utilities
	// ------------------------------------------------------------------

	/**
	 * Strip HTML tags and collapse whitespace to produce a plain-text snippet.
	 *
	 * @param string $html   Block innerHTML.
	 * @param int    $length Max characters.
	 * @return string Truncated plain text.
	 */
	public static function plain_text_snippet( string $html, int $length = 160 ): string {
		$text = \wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		if ( mb_strlen( $text ) > $length ) {
			$text = mb_substr( $text, 0, $length ) . '…';
		}
		return $text;
	}

	/**
	 * Validate that a path string is well-formed (only digits and dots).
	 *
	 * @param string $path The path to validate.
	 * @return bool True if well-formed.
	 */
	public static function is_valid_path( string $path ): bool {
		return $path === '' || (bool) preg_match( '/^\d+(\.\d+)*$/', $path );
	}
}
