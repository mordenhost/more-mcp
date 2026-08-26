<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin_Catalog {

	const ABILITY_KEY_PREFIX = 'ability:';

	const CORE_ABILITY_NAMESPACES = array( 'core', 'wp', 'wordpress' );

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
			$importable   = \More_MCP\Abilities\Importer::importable_abilities();
			$enabled_abs  = array_flip( \More_MCP\Abilities\Importer::enabled_ability_names() );
			$by_namespace = array();

			foreach ( $importable as $ability_name => $ability ) {
				$ns = self::namespace_of( (string) $ability_name );
				if ( '' === $ns || in_array( $ns, self::CORE_ABILITY_NAMESPACES, true ) ) {
					continue; 
				}
				if ( ! isset( $by_namespace[ $ns ] ) ) {
					$by_namespace[ $ns ] = array( 'names' => array(), 'enabled' => 0 );
				}
				$by_namespace[ $ns ]['names'][] = (string) $ability_name;
				if ( isset( $enabled_abs[ (string) $ability_name ] ) ) {
					$by_namespace[ $ns ]['enabled']++;
				}
			}

			foreach ( $by_namespace as $ns => $data ) {
				$key   = self::ABILITY_KEY_PREFIX . $ns;
				$count = count( $data['names'] );
				$out[ $key ] = array(
					'label'         => self::humanize_namespace( $ns ),
					'active'        => true, 
					'via_abilities' => true,
					'tools'         => null,
					'settings'      => null,
					'abilities'     => array(
						'namespace'     => $ns,
						'count'         => $count,
						'enabled_count' => (int) $data['enabled'],

						
						
						'enabled'       => $count > 0 && (int) $data['enabled'] === $count,
						'names'         => $data['names'],
					),
				);
			}
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
