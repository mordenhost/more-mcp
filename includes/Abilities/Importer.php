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
		return array() !== self::enabled_namespaces();
	}

	public static function enabled_namespaces(): array {
		$settings = get_option( 'more_mcp_settings', array() );
		$stored   = isset( $settings[ self::ENABLED_KEY ] ) && is_array( $settings[ self::ENABLED_KEY ] )
			? $settings[ self::ENABLED_KEY ]
			: array();

		$on    = array();
		$names = array();
		foreach ( $stored as $entry ) {
			$entry = is_string( $entry ) ? trim( $entry ) : '';
			if ( '' === $entry ) {
				continue;
			}
			if ( false !== strpos( $entry, '/' ) ) {
				
				$slash  = strpos( $entry, '/' );
				$on[]   = substr( $entry, 0, $slash );
				$names[] = $entry;
			} else {
				$on[] = $entry;
			}
		}

		if ( ! empty( $settings[ self::TOGGLE_KEY ] ) ) {
			foreach ( array_keys( self::importable_abilities() ) as $ability_name ) {
				$slash = strpos( (string) $ability_name, '/' );
				if ( false !== $slash ) {
					$on[] = substr( (string) $ability_name, 0, $slash );
				}
			}
		}

		$live = self::namespaces_of_importable();
		return array_values( array_intersect( array_unique( $on ), $live ) );
	}

	public static function namespace_is_enabled( string $namespace ): bool {
		return in_array( $namespace, self::enabled_namespaces(), true );
	}

	public static function namespaces_of_importable(): array {
		$out = array();
		foreach ( array_keys( self::importable_abilities() ) as $ability_name ) {
			$slash = strpos( (string) $ability_name, '/' );
			if ( false === $slash ) {
				continue;
			}
			$ns = substr( (string) $ability_name, 0, $slash );
			if ( ! in_array( $ns, $out, true ) ) {
				$out[] = $ns;
			}
		}
		return $out;
	}

	public static function is_native_duplicate( string $ability_name ): bool {
		$slash = strpos( $ability_name, '/' );
		if ( false === $slash ) {
			return false;
		}
		$ns = substr( $ability_name, 0, $slash );
		if ( ! class_exists( '\More_MCP\Capabilities\Toggles' ) ) {
			return false;
		}
		$catalog = \More_MCP\Capabilities\Toggles::catalog();
		if ( ! isset( $catalog[ $ns ] ) ) {
			return false;
		}
		return \More_MCP\Capabilities\Toggles::is_enabled( $ns );
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
		$enabled_ns = array_flip( self::enabled_namespaces() );

		$tools      = array();
		$collisions = array();

		foreach ( $importable as $ability_name => $ability ) {
			$slash = strpos( (string) $ability_name, '/' );
			$ns    = false === $slash ? '' : substr( (string) $ability_name, 0, $slash );
			if ( '' === $ns || ! isset( $enabled_ns[ $ns ] ) ) {
				continue; 
			}
			if ( self::is_native_duplicate( (string) $ability_name ) ) {
				continue; 
			}
			$tool_name = self::to_tool_name( (string) $ability_name );
			if ( isset( $tools[ $tool_name ] ) ) {
				$collisions[] = array(
					'tool'    => $tool_name,
					'kept'    => (string) $tools[ $tool_name ]->get_name(),
					'dropped' => (string) $ability_name,
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
				'inputSchema' => self::normalize_input_schema( $schema ),
			);

			$tools[] = $definition;
		}

		return $tools;
	}

	private static function normalize_input_schema( $schema ): array {
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return array( 'type' => 'object', 'properties' => new \stdClass() );
		}
		if ( ! isset( $schema['type'] ) || 'object' !== $schema['type'] ) {

			unset( $schema['type'] );
			$schema = array_merge( array( 'type' => 'object' ), $schema );
		}
		return $schema;
	}

	public static function tool_names(): array {
		if ( ! self::is_enabled() ) {
			return array();
		}
		return array_keys( self::resolve_map()['tools'] );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! self::is_enabled() ) {
			throw new \Exception( 'Imported abilities are turned off for every plugin. Switch a plugin card on under Settings then try again.' );
		}

		$resolved = self::resolve_map()['tools'];
		if ( ! isset( $resolved[ $name ] ) ) {
			
			foreach ( self::importable_abilities() as $ability_name => $ability ) {
				if ( self::to_tool_name( (string) $ability_name ) !== $name ) {
					continue;
				}
				if ( self::is_native_duplicate( (string) $ability_name ) ) {
					$slash = strpos( (string) $ability_name, '/' );
					$ns    = false === $slash ? '' : substr( (string) $ability_name, 0, $slash );
					throw new \Exception(
						'Imported ability ' . (string) $ability_name . ' is covered by the built-in ' . $ns . ' tools. Use those instead, or turn the ' . $ns . ' plugin card off to import it.'
					);
				}
				throw new \Exception( 'Imported ability not available: ' . $name . '. Its plugin card may be off or its plugin deactivated.' );
			}
			throw new \Exception( 'Imported ability not available: ' . $name . '. The ability may be disabled or its plugin deactivated.' );
		}

		$ability = $resolved[ $name ];
		if ( ! method_exists( $ability, 'execute' ) ) {
			throw new \Exception( 'Imported ability cannot be executed: ' . $name );
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
