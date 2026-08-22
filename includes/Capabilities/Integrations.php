<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Integrations {

	public static function all(): array {
		return array(
			\More_MCP\Integrations\WooCommerce::class,
			\More_MCP\Integrations\LiteSpeed::class,
			\More_MCP\Integrations\Elementor::class,
			\More_MCP\Integrations\Divi::class,
			\More_MCP\Integrations\ACF::class,
			\More_MCP\Integrations\MetaBox::class,
			\More_MCP\Integrations\Redirection::class,
			\More_MCP\Integrations\Analytics::class,
			\More_MCP\Integrations\Email::class,
			\More_MCP\Integrations\Forms::class,
			\More_MCP\Integrations\WPRocket::class,
			\More_MCP\Integrations\UpdraftPlus::class,
			\More_MCP\Integrations\BackWPup::class,
			\More_MCP\Integrations\Wordfence::class,
			\More_MCP\Integrations\Defender::class,
			\More_MCP\Integrations\Akismet::class,
			\More_MCP\Integrations\Imagify::class,
			\More_MCP\Integrations\TranslatePress::class,
			\More_MCP\Integrations\FluentCRM::class,
			\More_MCP\Integrations\LearnPress::class,
		);
	}
}
