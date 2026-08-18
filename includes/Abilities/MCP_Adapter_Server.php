<?php
/**
 * Registers "more-mcp-server" with the WordPress MCP Adapter (Option C from
 * SCOPE_1.4.38.md). Enables customers running MCP Adapter to reach More MCP tools
 * over MCP Adapter's HTTP transport in addition to our native /wp-json/more-mcp/mcp
 * endpoint and WP core /wp-json/wp-abilities/v1/* REST.
 *
 * Named-server registration + explicit ability list means our abilities do NOT
 * auto-enroll on MCP Adapter's default server (that path requires meta.mcp.public=true,
 * which we intentionally do not set — see Meta_Config.php + SCOPE addendum).
 *
 * Silent no-op if the MCP Adapter plugin isn't installed or hasn't loaded yet.
 */

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

	/**
	 * Fires on mcp_adapter_init (dispatched by MCP Adapter core on its own init).
	 * By this hook, wp_abilities_api_init has already fired and the abilities
	 * registry is populated — we can safely collect ability names.
	 */
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
			null, // error_handler → NullMcpErrorHandler default
			null, // observability_handler → NullMcpObservabilityHandler default
			$ability_names // tools (explicit list)
		);
	}

	/**
	 * Walk WP abilities registry, return every more-mcp/* ability name.
	 */
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
