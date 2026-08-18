<?php
/**
 * Walks the More MCP tool registry and registers every tool as a WordPress
 * ability under the more-mcp/* namespace on wp_abilities_api_init.
 *
 * Handler code (Server::execute_tool switch) is not touched — this is a
 * translation layer only. Ability callers land in the same handler stack as
 * MCP + REST callers.
 */

namespace More_MCP\Abilities;

use More_MCP\Abilities\Output_Schemas\Registry as Output_Schemas_Registry;
use More_MCP\MCP\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registrar {

	const NAMESPACE_PREFIX = 'more-mcp/';

	/**
	 * Tool-name prefix → short category slug. Categories::category_slug()
	 * composes the full more-mcp-<short> registered slug.
	 *
	 * Order matters: longer prefixes must come first (more_mcp_ before wp_).
	 */
	private static function prefix_to_category(): array {
		return array(
			'more_mcp_' => 'core',
			'blocks_'    => 'blocks',
			'wp_'        => 'core',
			'wc_'        => 'woocommerce',
			'elementor_' => 'elementor',
			'divi_'      => 'divi',
			'fc_'        => 'forgecache',
			'ls_'        => 'litespeed',
			'sv_'        => 'sitevault',
			'gp_'        => 'guardpress',
			'acf_'       => 'acf',
			'redirection_' => 'redirection',
			'analytics_' => 'analytics',
			'forms_'     => 'forms',
		);
	}

	/**
	 * Fires on wp_abilities_api_init at default priority 10, after Categories::register
	 * (which runs on wp_abilities_api_categories_init).
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$server = new Server();
		$tools  = $server->get_all_tools();
		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) || empty( $tool['name'] ) || empty( $tool['description'] ) ) {
				continue;
			}
			self::register_one( $tool );
		}
	}

	private static function register_one( array $tool ): void {
		$tool_name       = (string) $tool['name'];
		$ability_name    = self::transform_name( $tool_name );
		$category        = Categories::category_slug( self::dispatch_category( $tool_name ) );
		$label           = self::derive_label( $tool_name );
		$description     = self::prefix_description( (string) $tool['description'] );
		$input_schema    = isset( $tool['inputSchema'] ) && is_array( $tool['inputSchema'] ) ? $tool['inputSchema'] : array();

		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => $category,
			'execute_callback'    => self::build_execute_callback( $tool_name ),
			'permission_callback' => self::build_permission_callback( $tool_name ),
			'meta'                => Meta_Config::ability_meta(),
		);

		// input_schema is optional in WP core; only pass if non-empty. Empty schemas
		// throw at validate_input() time because the ability treats the presence of
		// the schema as "input is expected".
		if ( ! empty( $input_schema['properties'] ) ) {
			$args['input_schema'] = $input_schema;
		}

		// output_schema is also optional — Registry returns null for tools without
		// a mapped schema, and WP core skips validate_output() when unset.
		$output_schema = Output_Schemas_Registry::get_for_tool( $tool_name );
		if ( is_array( $output_schema ) ) {
			$args['output_schema'] = $output_schema;
		}

		wp_register_ability( $ability_name, $args );
	}

	/**
	 * Transform tool name (underscored, e.g. wp_get_posts) into ability name
	 * (dashed, prefixed with more-mcp/, e.g. more-mcp/wp-get-posts).
	 *
	 * Strips the more_mcp_ prefix from More-MCP-native tool names so the ability
	 * name reads "more-mcp/connection-health" instead of the doubled "more-mcp/more-mcp-connection-health".
	 */
	public static function transform_name( string $tool_name ): string {
		if ( strpos( $tool_name, 'more_mcp_' ) === 0 ) {
			$tool_name = substr( $tool_name, strlen( 'more_mcp_' ) );
		}
		return self::NAMESPACE_PREFIX . str_replace( '_', '-', $tool_name );
	}

	/**
	 * Derive a human-readable label from the tool name. Simple title-case pass —
	 * a per-tool override map is a Chunk 3+ concern if labels need polishing.
	 */
	public static function derive_label( string $tool_name ): string {
		return ucwords( str_replace( '_', ' ', $tool_name ) );
	}

	/**
	 * Resolve tool-name prefix to a short category slug (unprefixed).
	 * Falls back to 'core' for names that don't match any known prefix.
	 */
	public static function dispatch_category( string $tool_name ): string {
		// Lifecycle tools carry the generic wp_ prefix, so they must be matched
		// by name before the prefix loop would file them under 'core'. Grouping
		// them separately matters here: an ability listing that mixes "delete a
		// plugin" in with "list posts" gives an operator no way to see the
		// high-impact surface at a glance.
		if ( in_array( $tool_name, \More_MCP\Lifecycle\Manager::tool_names(), true ) ) {
			return 'lifecycle';
		}
		foreach ( self::prefix_to_category() as $prefix => $short_slug ) {
			if ( strpos( $tool_name, $prefix ) === 0 ) {
				return $short_slug;
			}
		}
		return 'core';
	}

	/**
	 * Prepend "More MCP:" identifier so users can identify origin at a glance
	 * in adapter listings. Idempotent — already-prefixed descriptions are unchanged.
	 */
	private static function prefix_description( string $description ): string {
		if ( strpos( $description, 'More MCP:' ) === 0 ) {
			return $description;
		}
		return 'More MCP: ' . $description;
	}

	/**
	 * Build a closure that routes an ability call to Server::invoke().
	 *
	 * WP_Error is returned on thrown Exceptions so the WP core ability layer
	 * surfaces a well-formed error to the caller instead of a fatal.
	 */
	private static function build_execute_callback( string $tool_name ): callable {
		return static function ( $input = null ) use ( $tool_name ) {
			$args = is_array( $input ) ? $input : array();
			try {
				$server = new Server();
				return $server->invoke( $tool_name, $args );
			} catch ( \Throwable $e ) {
				return new \WP_Error(
					'more_mcp_ability_exception',
					esc_html( $e->getMessage() )
				);
			}
		};
	}

	/**
	 * Coarse per-tool cap gate. {@see Tool_Capabilities::for_tool()} resolves
	 * the required capability from the override map + prefix/verb heuristic.
	 *
	 * Fine-grained per-object caps (read_post, edit_post, etc.) still fire inside
	 * Server::execute_tool() — this outer gate only stops callers who could never
	 * legitimately use the tool at all.
	 */
	private static function build_permission_callback( string $tool_name ): callable {
		$cap = Tool_Capabilities::for_tool( $tool_name );
		return static function () use ( $cap ): bool {
			return current_user_can( $cap );
		};
	}
}
