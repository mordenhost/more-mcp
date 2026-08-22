<?php

namespace More_MCP\Abilities;

use More_MCP\Abilities\Output_Schemas\Registry as Output_Schemas_Registry;
use More_MCP\MCP\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registrar {

	const NAMESPACE_PREFIX = 'more-mcp/';

	private static function prefix_to_category(): array {
		return array(
			'more_mcp_' => 'core',
			'blocks_'    => 'blocks',
			'wpr_'       => 'wprocket',
			'wp_'        => 'core',
			'wc_'        => 'woocommerce',
			'elementor_' => 'elementor',
			'divi_'      => 'divi',
			'ls_'        => 'litespeed',
			'acf_'       => 'acf',
			'mb_'        => 'metabox',
			'redirection_' => 'redirection',
			'analytics_' => 'analytics',
			'email_'     => 'email',
			'forms_'     => 'forms',
			'up_'        => 'updraftplus',
			'bwu_'       => 'backwpup',
			'wf_'        => 'wordfence',
			'def_'       => 'defender',
			'akismet_'   => 'akismet',
			'imagify_'   => 'imagify',
			'trp_'       => 'translatepress',
			'crm_'       => 'fluentcrm',
			'lms_'       => 'learnpress',
		);
	}

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

			

			
			
			if ( strpos( (string) $tool['name'], Importer::TOOL_PREFIX ) === 0 ) {
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

		
		
		if ( ! empty( $input_schema['properties'] ) ) {
			$args['input_schema'] = $input_schema;
		}

		
		$output_schema = Output_Schemas_Registry::get_for_tool( $tool_name );
		if ( is_array( $output_schema ) ) {
			$args['output_schema'] = $output_schema;
		}

		wp_register_ability( $ability_name, $args );
	}

	public static function transform_name( string $tool_name ): string {
		if ( strpos( $tool_name, 'more_mcp_' ) === 0 ) {
			$tool_name = substr( $tool_name, strlen( 'more_mcp_' ) );
		}
		return self::NAMESPACE_PREFIX . str_replace( '_', '-', $tool_name );
	}

	public static function derive_label( string $tool_name ): string {
		return ucwords( str_replace( '_', ' ', $tool_name ) );
	}

	public static function dispatch_category( string $tool_name ): string {

		

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

	private static function prefix_description( string $description ): string {
		if ( strpos( $description, 'More MCP:' ) === 0 ) {
			return $description;
		}
		return 'More MCP: ' . $description;
	}

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

	private static function build_permission_callback( string $tool_name ): callable {
		$cap = Tool_Capabilities::for_tool( $tool_name );
		return static function () use ( $cap ): bool {
			return current_user_can( $cap );
		};
	}
}
