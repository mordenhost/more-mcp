<?php
/**
 * Block type introspection.
 *
 * Wraps WP_Block_Type_Registry so tools can discover what blocks this site
 * actually has, and what attributes each one accepts.
 *
 * IMPORTANT CAVEAT, surfaced in every response this class produces:
 * the PHP registry only knows about blocks registered server-side (block.json
 * via register_block_type, or a PHP register_block_type call). Blocks
 * registered ONLY in JavaScript are invisible here. Their absence therefore
 * means "not verifiable from PHP", never "does not exist" — treating an
 * unknown block as invalid would reject perfectly good third-party blocks.
 */

namespace More_MCP\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/**
	 * Whether the block registry is usable on this request.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return class_exists( '\WP_Block_Type_Registry' );
	}

	/**
	 * All server-registered block types, keyed by block name.
	 *
	 * @return \WP_Block_Type[]
	 */
	public static function all(): array {
		if ( ! self::is_available() ) {
			return [];
		}
		return \WP_Block_Type_Registry::get_instance()->get_all_registered();
	}

	/**
	 * Whether a block name is registered server-side.
	 *
	 * A false result is NOT proof the block is invalid — see the class docblock.
	 *
	 * @param string $name Fully-qualified block name, e.g. "core/paragraph".
	 * @return bool
	 */
	public static function is_registered( string $name ): bool {
		if ( ! self::is_available() ) {
			return false;
		}
		return \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	/**
	 * Fetch one registered block type.
	 *
	 * @param string $name Block name.
	 * @return \WP_Block_Type|null
	 */
	public static function get( string $name ): ?\WP_Block_Type {
		if ( ! self::is_available() ) {
			return null;
		}
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		return $type instanceof \WP_Block_Type ? $type : null;
	}

	/**
	 * Summarize a block type for tool output.
	 *
	 * Deliberately compact — attribute schemas can be large, so the full
	 * attribute map is only included when $include_attributes is true
	 * (blocks_get_type_schema), not in list output (blocks_list_types).
	 *
	 * @param \WP_Block_Type $type               The block type.
	 * @param bool           $include_attributes Include the full attribute schema.
	 * @return array
	 */
	public static function summarize( \WP_Block_Type $type, bool $include_attributes = false ): array {
		$summary = [
			'name'        => $type->name,
			'title'       => $type->title ?? '',
			'category'    => $type->category ?? null,
			'description' => $type->description ?? '',
			// A dynamic block renders through PHP at display time, so its saved
			// markup is a self-closing comment with no inner HTML.
			'is_dynamic'  => $type->is_dynamic(),
		];

		foreach ( [ 'parent', 'ancestor', 'allowed_blocks' ] as $constraint ) {
			if ( ! empty( $type->{$constraint} ) ) {
				$summary[ $constraint ] = $type->{$constraint};
			}
		}

		if ( ! empty( $type->supports ) ) {
			$summary['supports'] = $type->supports;
		}

		if ( $include_attributes ) {
			$summary['attributes'] = $type->attributes ?? [];
			if ( ! empty( $type->variations ) ) {
				$summary['variations'] = array_map(
					static function ( $variation ) {
						return [
							'name'        => $variation['name'] ?? '',
							'title'       => $variation['title'] ?? '',
							'description' => $variation['description'] ?? '',
							'attributes'  => $variation['attributes'] ?? [],
						];
					},
					$type->variations
				);
			}
		}

		return $summary;
	}

	/**
	 * List block types, optionally filtered.
	 *
	 * @param string|null $category Restrict to a block category slug.
	 * @param string|null $search   Case-insensitive substring match on name/title.
	 * @return array List of block summaries, sorted by name.
	 */
	public static function list_types( ?string $category = null, ?string $search = null ): array {
		$out = [];

		foreach ( self::all() as $name => $type ) {
			if ( $category !== null && $category !== '' && ( $type->category ?? null ) !== $category ) {
				continue;
			}

			if ( $search !== null && $search !== '' ) {
				$haystack = strtolower( $name . ' ' . ( $type->title ?? '' ) );
				if ( strpos( $haystack, strtolower( $search ) ) === false ) {
					continue;
				}
			}

			$out[] = self::summarize( $type );
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * The standard caveat string appended to registry-derived tool output.
	 *
	 * @return string
	 */
	public static function registry_caveat(): string {
		return 'This list reflects blocks registered on the PHP side (block.json or a PHP register_block_type call). Blocks registered only in JavaScript do not appear here, and some blocks register only in block-editor context. A block missing from this list is not necessarily invalid or unavailable.';
	}
}
