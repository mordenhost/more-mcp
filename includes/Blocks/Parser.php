<?php

namespace More_MCP\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Parser {

	public static function parse( string $content ): array {
		return \parse_blocks( $content );
	}

	public static function serialize( array $blocks ): string {
		return \serialize_blocks( $blocks );
	}

	public static function round_trip_check( array $mutated_blocks ): array {
		$serialized = self::serialize( $mutated_blocks );

		
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

	public static function path_exists( array $blocks, string $path ): bool {
		return self::resolve_path( $blocks, $path )['block'] !== null;
	}

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

	private static function mutate_recursive( array $level, array $indices, int $depth, callable $mutator ): array {
		$idx      = $indices[ $depth ];
		$is_final = ( $depth === count( $indices ) - 1 );

		if ( $is_final ) {

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

		
		$level[ $idx ]['innerBlocks'] = $child['blocks'];
		self::rebuild_inner_content( $level[ $idx ] );

		return [ 'ok' => true, 'blocks' => $level ];
	}

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

	public static function move( array $blocks, string $from, string $to, string $position ): array {
		if ( $from === $to ) {
			return [ 'ok' => false, 'error' => 'Source and destination paths are identical; nothing to move.' ];
		}
		
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

	private static function strip_move_markers( array $blocks ): array {
		foreach ( $blocks as $i => $block ) {
			unset( $blocks[ $i ]['_moveMarker'] );
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::strip_move_markers( $block['innerBlocks'] );
			}
		}
		return $blocks;
	}

	

	
	public static function rebuild_inner_content( array &$block ): void {
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			
			$block['innerContent'] = [ $block['innerHTML'] ?? '' ];
			return;
		}

		$count = count( $block['innerBlocks'] );
		$parts = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$parts[] = null; 
		}
		
		$parts[] = $block['innerHTML'] ?? '';

		$block['innerContent'] = $parts;
	}

	

	
	public static function plain_text_snippet( string $html, int $length = 160 ): string {
		$text = \wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		if ( mb_strlen( $text ) > $length ) {
			$text = mb_substr( $text, 0, $length ) . '…';
		}
		return $text;
	}

	public static function is_valid_path( string $path ): bool {
		return $path === '' || (bool) preg_match( '/^\d+(\.\d+)*$/', $path );
	}
}
