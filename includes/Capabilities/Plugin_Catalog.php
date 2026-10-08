<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin_Catalog {

	const ABILITY_KEY_PREFIX = 'ability:';

	const CORE_ABILITY_NAMESPACES = array( 'core', 'wp', 'wordpress' );

	const NAMESPACE_ALIASES = array(
		'yoast-seo' => 'yoast',
		'rank-math' => 'rank_math',
	);

	public static function build( array $settings ): array {
		$out = array();

		

		$catalog      = Toggles::catalog();
		$availability = Toggles::availability();
		$enabled_ints = array_flip( Toggles::enabled_slugs() );
		$int_classes  = Toggles::classes();

		foreach ( $catalog as $slug => $meta ) {
			$out[ $slug ] = array(
				'label'         => (string) $meta['label'],
				'active'        => ! empty( $availability[ $slug ] ),
				'via_abilities' => false,
				'tools'         => array(
					'slug'    => (string) $slug,
					'count'   => self::tool_count( $int_classes[ $slug ] ?? '' ),
					'enabled' => isset( $enabled_ints[ $slug ] ),
				),
				'settings'      => null,
				'abilities'     => null,
			);
		}

		

		
		if ( class_exists( '\More_MCP\Admin\Option_Presets' ) ) {
			$summaries = \More_MCP\Admin\Option_Presets::source_summaries();
			$split     = \More_MCP\Admin\Option_Presets::split_stored(
				isset( $settings['writable_options_admin'] ) && is_array( $settings['writable_options_admin'] )
					? $settings['writable_options_admin']
					: array()
			);
			$src_on = isset( $split['sources'] ) && is_array( $split['sources'] ) ? $split['sources'] : array();

			foreach ( $summaries as $src_slug => $summary ) {
				if ( \More_MCP\Admin\Option_Presets::SOURCE_CORE === $src_slug ) {
					continue; 
				}
				$settings_row = array(
					'count'       => count( $summary['names'] ),
					'enabled'     => ! empty( $src_on[ $src_slug ] ),
					'names'       => $summary['names'],
					'cautions'    => $summary['cautions'],
					'has_array'   => ! empty( $summary['has_array'] ),
					'source_slug' => (string) $src_slug,
				);
				if ( isset( $out[ $src_slug ] ) ) {
					$out[ $src_slug ]['settings'] = $settings_row;

					

					$out[ $src_slug ]['active'] = true;
				} else {
					$out[ $src_slug ] = array(
						'label'         => (string) $summary['label'],
						'active'        => true, 
						'via_abilities' => false,
						'tools'         => null,
						'settings'      => $settings_row,
						'abilities'     => null,
					);
				}
			}
		}

		

		

		

		

		

		

		

		
		
		if ( class_exists( '\More_MCP\Abilities\Importer' ) ) {
			$importable  = \More_MCP\Abilities\Importer::importable_abilities();
			$enabled_ns  = array_flip( \More_MCP\Abilities\Importer::enabled_namespaces() );
			$by_namespace = array();

			$owned_namespaces = array(); 

			foreach ( $importable as $ability_name => $ability ) {
				$ability_name = (string) $ability_name;
				$ns = self::namespace_of( $ability_name );
				if ( '' === $ns || in_array( $ns, self::CORE_ABILITY_NAMESPACES, true ) ) {
					continue; 
				}

				
				$owner = self::card_key_for_namespace( $ns );
				$owned_namespaces[ isset( $out[ $owner ] ) ? $owner : self::ABILITY_KEY_PREFIX . $ns ][ $ns ] = true;
				if ( \More_MCP\Abilities\Importer::is_native_duplicate( $ability_name ) ) {
					continue; 
				}
				if ( ! isset( $by_namespace[ $ns ] ) ) {
					$by_namespace[ $ns ] = array();
				}
				$by_namespace[ $ns ][] = $ability_name;
			}

			foreach ( $by_namespace as $ns => $names ) {
				$ns            = (string) $ns;
				$abilities_row = array(
					'namespace'     => $ns,
					'namespaces'    => array( $ns ),
					'count'         => count( $names ),

					'enabled_count' => isset( $enabled_ns[ $ns ] ) ? count( $names ) : 0,
					'enabled'       => isset( $enabled_ns[ $ns ] ),
					'names'         => $names,
				);
				$target = self::card_key_for_namespace( $ns );
				if ( isset( $out[ $target ] ) ) {

					$out[ $target ]['abilities'] = self::merge_abilities_rows( $out[ $target ]['abilities'] ?? null, $abilities_row );

					$out[ $target ]['active'] = true;
				} else {
					$out[ self::ABILITY_KEY_PREFIX . $ns ] = array(
						'label'         => self::humanize_namespace( $ns ),
						'active'        => true, 
						'via_abilities' => true,
						'tools'         => null,
						'settings'      => null,
						'abilities'     => $abilities_row,
					);
				}
			}

			foreach ( $owned_namespaces as $key => $set ) {
				if ( isset( $out[ $key ] ) ) {
					$out[ $key ]['ability_namespaces'] = array_keys( $set );
				}
			}
		}

		
		foreach ( $out as $key => $card ) {
			$out[ $key ] += array( 'ability_namespaces' => array() );
		}

		

		
		$out = array_filter( $out, static function ( $card ) {
			return ! empty( $card['active'] );
		} );

		

		
		uasort( $out, static function ( $a, $b ) {
			$a_on = self::card_is_on( $a );
			$b_on = self::card_is_on( $b );
			if ( $a_on === $b_on ) {
				return 0;
			}
			return $a_on ? -1 : 1;
		} );

		return $out;
	}

	private static function card_is_on( array $card ): bool {
		if ( is_array( $card['tools'] ?? null ) && ! empty( $card['tools']['enabled'] ) ) {
			return true;
		}
		if ( is_array( $card['settings'] ?? null ) && ! empty( $card['settings']['enabled'] ) ) {
			return true;
		}
		if ( is_array( $card['abilities'] ?? null ) && ! empty( $card['abilities']['enabled_count'] ) ) {
			return true;
		}
		return false;
	}

	public static function card_state( array $card ): string {
		$rows = array();
		if ( is_array( $card['tools'] ?? null ) ) {
			$rows[] = ! empty( $card['tools']['enabled'] );
		}
		if ( is_array( $card['settings'] ?? null ) ) {
			$rows[] = ! empty( $card['settings']['enabled'] );
		}
		if ( is_array( $card['abilities'] ?? null ) ) {
			$rows[] = ! empty( $card['abilities']['enabled'] );
		}
		if ( empty( $rows ) || ! in_array( true, $rows, true ) ) {
			return 'off';
		}
		return in_array( false, $rows, true ) ? 'partial' : 'on';
	}

	public static function card_key_for_namespace( string $ns ): string {
		return self::NAMESPACE_ALIASES[ $ns ] ?? $ns;
	}

	public static function find( array $settings, string $key ): ?array {
		$cards = self::build( $settings );
		return isset( $cards[ $key ] ) ? $cards[ $key ] : null;
	}

	private static function merge_abilities_rows( ?array $existing, array $row ): array {
		if ( null === $existing ) {
			return $row;
		}
		return array(
			'namespace'     => $existing['namespace'],
			'namespaces'    => array_values( array_unique( array_merge( $existing['namespaces'] ?? array( $existing['namespace'] ), $row['namespaces'] ) ) ),
			'count'         => (int) $existing['count'] + (int) $row['count'],
			'enabled_count' => (int) $existing['enabled_count'] + (int) $row['enabled_count'],
			'enabled'       => ! empty( $existing['enabled'] ) && ! empty( $row['enabled'] ),
			'names'         => array_values( array_merge( $existing['names'], $row['names'] ) ),
		);
	}

	private static function tool_count( string $class ): int {
		if ( '' === $class || ! class_exists( $class ) || ! method_exists( $class, 'get_tools' ) ) {
			return 0;
		}
		$tools = $class::get_tools();
		return is_array( $tools ) ? count( $tools ) : 0;
	}

	private static function namespace_of( string $ability_name ): string {
		$pos = strpos( $ability_name, '/' );
		return false === $pos ? '' : substr( $ability_name, 0, $pos );
	}

	private static function humanize_namespace( string $ns ): string {
		$words = preg_split( '/[-_]+/', $ns );
		$words = array_map( 'ucfirst', $words );
		return implode( ' ', $words );
	}
}
