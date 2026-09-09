<?php

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	public static function get_for_tool( string $tool_name ): ?array {

		if ( strpos( $tool_name, 'blocks_' ) === 0 )     return Blocks::get( $tool_name );

		
		$lifecycle = Lifecycle::get( $tool_name );
		if ( null !== $lifecycle ) {
			return $lifecycle;
		}

		if ( strpos( $tool_name, 'wc_' ) === 0 )         return WooCommerce::get( $tool_name );
		if ( strpos( $tool_name, 'elementor_' ) === 0 )  return Elementor::get( $tool_name );
		if ( strpos( $tool_name, 'divi_' ) === 0 )       return Divi::get( $tool_name );
		if ( strpos( $tool_name, 'acf_' ) === 0 )        return ACF::get( $tool_name );
		if ( strpos( $tool_name, 'redirection_' ) === 0 ) return Redirection::get( $tool_name );
		if ( strpos( $tool_name, 'analytics_' ) === 0 )   return Analytics::get( $tool_name );
		if ( strpos( $tool_name, 'forms_' ) === 0 )       return Forms::get( $tool_name );

		return Core::get( $tool_name );
	}
}
