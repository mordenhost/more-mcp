<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin_Switch {

	public static function apply( array $settings, array $card, bool $on ): array {
		if ( is_array( $card['tools'] ?? null ) && '' !== (string) ( $card['tools']['slug'] ?? '' ) ) {
			$settings[ Toggles::OPTION_KEY ] = self::set_members(
				self::string_list( $settings[ Toggles::OPTION_KEY ] ?? array() ),
				array( (string) $card['tools']['slug'] ),
				$on
			);
		}

		if ( is_array( $card['settings'] ?? null ) && ! empty( $card['settings']['names'] ) ) {

			$clean   = static function ( $name ) {
				return sanitize_key( trim( (string) $name ) );
			};
			$names   = array_values( array_filter( array_map( $clean, (array) $card['settings']['names'] ) ) );
			$current = array_values( array_filter( array_map( $clean, self::string_list( $settings['writable_options_admin'] ?? array() ) ) ) );
			$settings['writable_options_admin'] = self::set_members( $current, $names, $on );
		}

		
		
		$namespaces = self::string_list( array_merge(
			(array) ( $card['ability_namespaces'] ?? array() ),
			is_array( $card['abilities'] ?? null ) ? (array) ( $card['abilities']['namespaces'] ?? array( $card['abilities']['namespace'] ?? '' ) ) : array()
		) );
		$namespaces = array_values( array_unique( $namespaces ) );
		if ( ! empty( $namespaces ) ) {
			$key     = \More_MCP\Abilities\Importer::ENABLED_KEY;

			$current = array_values( array_filter(
				self::string_list( $settings[ $key ] ?? array() ),
				static function ( $n ) {
					return false === strpos( $n, '/' );
				}
			) );
			$settings[ $key ] = self::set_members( $current, $namespaces, $on );
		}

		return $settings;
	}

	public static function apply_many( array $settings, array $cards, bool $on ): array {
		foreach ( $cards as $card ) {
			if ( is_array( $card ) ) {
				$settings = self::apply( $settings, $card, $on );
			}
		}
		return $settings;
	}

	private static function set_members( array $list, array $members, bool $on ): array {
		$set = array();
		foreach ( $list as $item ) {
			$set[ $item ] = true;
		}
		foreach ( $members as $m ) {
			if ( '' === $m ) {
				continue;
			}
			if ( $on ) {
				$set[ $m ] = true;
			} else {
				unset( $set[ $m ] );
			}
		}
		return array_keys( $set );
	}

	private static function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $v ) {
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$out[] = trim( $v );
			}
		}
		return $out;
	}
}
