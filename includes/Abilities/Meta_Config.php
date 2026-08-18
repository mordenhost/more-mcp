<?php
/**
 * Meta-key constants for WordPress Abilities API registration.
 *
 * Centralized so a future WP core meta-shape shift is a one-file edit.
 */

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Config {

	// WP core meta: controls REST exposure at /wp-json/wp-abilities/v1/*.
	// Seeds meta.show_in_rest default (WP core wp-includes/abilities-api/class-wp-ability.php:370).
	const PUBLIC_META_KEY = 'public';

	// WP core meta: auto-seeded from PUBLIC_META_KEY when unset. Do not set explicitly.
	const SHOW_IN_REST_META_KEY = 'show_in_rest';

	// MCP Adapter default-server enrollment path is intentionally not set. We register our
	// own named server with an explicit ability list; default-server enrollment is not needed.
	// If future scope wants auto-enrollment, uncomment:
	// const MCP_PUBLIC_META_PATH = [ 'mcp', 'public' ];

	/**
	 * Baseline meta payload attached to every More MCP ability.
	 *
	 * BOTH flags set explicitly because the auto-seed behavior differs by WP version:
	 *   - WP 7.1+: setting `public = true` alone auto-seeds `show_in_rest = true`
	 *     (WP core class-wp-ability.php:370).
	 *   - WP 7.0.x: `public` field is silently ignored (added @since 7.1); only
	 *     `show_in_rest` controls REST exposure. Without explicit show_in_rest,
	 *     abilities registered on 7.0.x get show_in_rest=false and become
	 *     unreachable via /wp-json/wp-abilities/v1/*.
	 * Setting both makes the ability REST-visible on every supported WP version.
	 */
	public static function ability_meta(): array {
		return array(
			self::PUBLIC_META_KEY       => true,
			self::SHOW_IN_REST_META_KEY => true,
		);
	}
}
