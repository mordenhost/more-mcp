<?php

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCP_Adapter_Server {

	const SERVER_ID        = 'more-mcp-server';
	const NAMESPACE_ROUTE  = 'more-mcp-server/v1';
	const ROUTE            = 'mcp';
	const SERVER_NAME      = 'More MCP';
	const SERVER_DESC      = 'More MCP — WordPress core operations plus WooCommerce, Elementor, SEO, and cross-plugin abilities. Same handlers as the native /wp-json/more-mcp/mcp endpoint; MCP Adapter transport is an additional access path, not a replacement.';

	public static function register(): void {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return;
		}
		if ( ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			return;
		}
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		$ability_names = self::collect_our_ability_names();
		if ( empty( $ability_names ) ) {
			return;
		}

		$adapter = \WP\MCP\Core\McpAdapter::instance();
		$adapter->create_server(
			self::SERVER_ID,
			self::NAMESPACE_ROUTE,
			self::ROUTE,
			self::SERVER_NAME,
			self::SERVER_DESC,
			defined( 'MORE_MCP_VERSION' ) ? MORE_MCP_VERSION : '1.0.0',
			array( \WP\MCP\Transport\HttpTransport::class ),
			null, 
			null, 
			$ability_names 
		);
	}

	private static function collect_our_ability_names(): array {
		$names = array();
		$all   = wp_get_abilities();
		foreach ( $all as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
				continue;
			}
			$name = $ability->get_name();
			if ( strpos( $name, 'more-mcp/' ) === 0 ) {
				$names[] = $name;
			}
		}
		return $names;
	}
}
