<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	private static function provider_classes(): array {
		return array(
			Ahrefs::class,
			Semrush::class,
			DataForSEO::class,
			SE_Ranking::class,
			Search_Console::class,
			Analytics4::class,
		);
	}

	public static function get_tools(): array {
		$tools = array();
		foreach ( self::provider_classes() as $class ) {
			$tools = array_merge( $tools, $class::get_tools() );
		}
		return $tools;
	}

	public static function tool_names(): array {
		$names = array();
		foreach ( self::provider_classes() as $class ) {
			$names = array_merge( $names, $class::tool_names() );
		}
		return $names;
	}

	public static function execute_tool( string $name, array $args ) {
		foreach ( self::provider_classes() as $class ) {
			if ( in_array( $name, $class::tool_names(), true ) ) {
				return $class::execute_tool( $name, $args );
			}
		}
		throw new \Exception( 'Unknown SEO data tool: ' . esc_html( $name ) );
	}
}
