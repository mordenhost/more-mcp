<?php

namespace More_MCP\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	public static function is_available(): bool {
		return class_exists( '\WP_Block_Type_Registry' );
	}

	public static function all(): array {
		if ( ! self::is_available() ) {
			return [];
		}
		return \WP_Block_Type_Registry::get_instance()->get_all_registered();
	}

	public static function is_registered( string $name ): bool {
		if ( ! self::is_available() ) {
			return false;
		}
		return \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	public static function get( string $name ): ?\WP_Block_Type {
		if ( ! self::is_available() ) {
			return null;
		}
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		return $type instanceof \WP_Block_Type ? $type : null;
	}

	public static function summarize( \WP_Block_Type $type, bool $include_attributes = false ): array {
		$summary = [
			'name'        => $type->name,
			'title'       => $type->title ?? '',
			'category'    => $type->category ?? null,
			'description' => $type->description ?? '',

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

	public static function registry_caveat(): string {
		return 'This list reflects blocks registered on the PHP side (block.json or a PHP register_block_type call). Blocks registered only in JavaScript do not appear here, and some blocks register only in block-editor context. A block missing from this list is not necessarily invalid or unavailable.';
	}
}
