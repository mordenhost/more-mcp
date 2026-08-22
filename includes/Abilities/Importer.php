<?php
namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Importer {

	const TOGGLE_KEY = 'allow_discovered_tools';

	const ENABLED_KEY = 'discovered_abilities';

	const TOOL_PREFIX = 'discovered_';

	const OWN_NAMESPACE = 'more-mcp/';

	public static function is_enabled(): bool {
		$settings = get_option( 'more_mcp_settings', array() );
		return ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	public static function enabled_ability_names(): array {
		$settings = get_option( 'more_mcp_settings', array() );
		$stored   = isset( $settings[ self::ENABLED_KEY ] ) && is_array( $settings[ self::ENABLED_KEY ] )
			? $settings[ self::ENABLED_KEY ]
			: array();
		return array_values( array_filter( array_map( 'strval', $stored ) ) );
	}

	public static function importable_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}
		$out = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
				continue;
			}
			$name = (string) $ability->get_name();
			if ( '' === $name ) {
				continue;
			}
			
			if ( strpos( $name, self::OWN_NAMESPACE ) === 0 ) {
				continue;
			}
			$out[ $name ] = $ability;
		}
		return $out;
	}

	private static function resolve_map(): array {
		$importable = self::importable_abilities();
		$enabled    = array_flip( self::enabled_ability_names() );

		$tools      = array();
		$collisions = array();

		foreach ( $importable as $ability_name => $ability ) {
			if ( ! isset( $enabled[ $ability_name ] ) ) {
				continue; 
			}
			$tool_name = self::to_tool_name( $ability_name );
			if ( isset( $tools[ $tool_name ] ) ) {
				$collisions[] = array(
					'tool'    => $tool_name,
					'kept'    => (string) $tools[ $tool_name ]->get_name(),
					'dropped' => $ability_name,
				);
				continue;
			}
			$tools[ $tool_name ] = $ability;
		}

		return array(
			'tools'      => $tools,
			'collisions' => $collisions,
		);
	}

	public static function get_tools(): array {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$resolved = self::resolve_map();
		$tools    = array();

		foreach ( $resolved['tools'] as $tool_name => $ability ) {
			$description = method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '';
			$label       = method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : '';
			$schema      = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();

			$definition = array(
				'name'        => $tool_name,
				'description' => self::prefix_description( $description, (string) $ability->get_name(), $label ),
				'inputSchema' => is_array( $schema ) && ! empty( $schema ) ? $schema : array( 'type' => 'object', 'properties' => new \stdClass() ),
			);

			$tools[] = $definition;
		}

		return $tools;
	}

	public static function tool_names(): array {
		if ( ! self::is_enabled() ) {
			return array();
		}
		return array_keys( self::resolve_map()['tools'] );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! self::is_enabled() ) {
			throw new \Exception( 'Discovered tools are turned off. Enable them in Settings then enable the specific ability.' );
		}

		$resolved = self::resolve_map()['tools'];
		if ( ! isset( $resolved[ $name ] ) ) {

			throw new \Exception( 'Discovered tool not available: ' . $name . '. The ability may be disabled or its plugin deactivated.' );
		}

		$ability = $resolved[ $name ];
		if ( ! method_exists( $ability, 'execute' ) ) {
			throw new \Exception( 'Discovered ability cannot be executed: ' . $name );
		}

		$result = $ability->execute( $args );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}

		return $result;
	}

	public static function to_tool_name( string $ability_name ): string {
		$slug = strtolower( $ability_name );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug );
		$slug = trim( (string) $slug, '_' );
		return self::TOOL_PREFIX . $slug;
	}

	private static function prefix_description( string $description, string $ability_name, string $label ): string {
		$body = '' !== $description ? $description : $label;
		return sprintf( 'Imported ability (%s): %s', $ability_name, $body );
	}
}
