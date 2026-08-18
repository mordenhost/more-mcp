<?php
/**
 * Pre-registers the 12 More MCP ability categories on wp_abilities_api_init (priority 5),
 * before ability registration walks the tool registry at priority 10.
 *
 * WP core requires every ability to reference a pre-registered category slug — an ability
 * whose `category` arg does not resolve to a registered category throws at registration time.
 */

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Categories {

	const NAMESPACE_PREFIX = 'more-mcp';

	/**
	 * Category slug (short key) → label + description.
	 *
	 * Keyed by the short slug; full registered slug is composed via {@see category_slug()}.
	 */
	private static function catalog(): array {
		return array(
			'core'         => array(
				'label'       => __( 'More MCP: Core', 'more-mcp' ),
				'description' => __( 'Core WordPress operations: posts, pages, media, terms, comments, users, options, menus, themes, SEO meta (post and term level, six SEO plugins), permalinks, revisions, cron, error log, connection health, search, site info.', 'more-mcp' ),
			),
			'woocommerce'  => array(
				'label'       => __( 'More MCP: WooCommerce', 'more-mcp' ),
				'description' => __( 'WooCommerce products, orders, coupons, variations, customers, and store stats.', 'more-mcp' ),
			),
			'elementor'    => array(
				'label'       => __( 'More MCP: Elementor', 'more-mcp' ),
				'description' => __( 'Elementor page operations: outline read, clone, replace text, replace image, import template, add/update/delete/move widget, resolve loop templates, list local templates.', 'more-mcp' ),
			),
			'divi'         => array(
				'label'       => __( 'More MCP: Divi', 'more-mcp' ),
				'description' => __( 'Read-only Divi 4 shortcode and Divi 5 block structure, with positional module inspection.', 'more-mcp' ),
			),
			'forgecache'   => array(
				'label'       => __( 'More MCP: ForgeCache', 'more-mcp' ),
				'description' => __( 'ForgeCache cache statistics, URL purge, and full cache clear.', 'more-mcp' ),
			),
			'litespeed'    => array(
				'label'       => __( 'More MCP: LiteSpeed Cache', 'more-mcp' ),
				'description' => __( 'LiteSpeed Cache purge operations: single-URL purge and full cache purge.', 'more-mcp' ),
			),
			'sitevault'    => array(
				'label'       => __( 'More MCP: SiteVault', 'more-mcp' ),
				'description' => __( 'SiteVault backups: create, read, status, schedules, and stats.', 'more-mcp' ),
			),
			'guardpress'   => array(
				'label'       => __( 'More MCP: GuardPress', 'more-mcp' ),
				'description' => __( 'GuardPress security: audit log, blocked IPs, failed logins, vulnerability scan, and security status.', 'more-mcp' ),
			),
			'acf'          => array(
				'label'       => __( 'More MCP: ACF', 'more-mcp' ),
				'description' => __( 'Advanced Custom Fields (ACF) field read/update and group enumeration.', 'more-mcp' ),
			),
			'redirection'  => array(
				'label'       => __( 'More MCP: Redirection', 'more-mcp' ),
				'description' => __( 'Redirection plugin: list/create/update redirects, list groups.', 'more-mcp' ),
			),
			'analytics'    => array(
				'label'       => __( 'More MCP: Analytics', 'more-mcp' ),
				'description' => __( 'Read-only Site Kit, Jetpack Stats, and MonsterInsights status, traffic summaries, and top content.', 'more-mcp' ),
			),
			'forms'        => array(
				'label'       => __( 'More MCP: Forms', 'more-mcp' ),
				'description' => __( 'Forms and lead capture (Gravity Forms, Fluent Forms): list forms and field schemas, read submissions with privacy-safe summaries, aggregate stats, and guarded entry status/trash writes with confirmation and undo.', 'more-mcp' ),
			),
			'blocks'       => array(
				'label'       => __( 'More MCP: Blocks', 'more-mcp' ),
				'description' => __( 'Gutenberg block editing, FSE site templates, patterns, and reusable blocks.', 'more-mcp' ),
			),
			'lifecycle'    => array(
				'label'       => __( 'More MCP: Plugins & Themes', 'more-mcp' ),
				'description' => __( 'Install, update, activate, deactivate, and delete plugins and themes. Highest-impact category: these change the code running on the site. Disabled unless an administrator enables plugin management, and every write requires a two-part confirmation.', 'more-mcp' ),
			),
		);
	}

	/**
	 * Fires on wp_abilities_api_init at priority 5, before ability registration at priority 10.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		foreach ( self::catalog() as $short_slug => $spec ) {
			wp_register_ability_category(
				self::category_slug( $short_slug ),
				array(
					'label'       => $spec['label'],
					'description' => $spec['description'],
				)
			);
		}
	}

	/**
	 * Compose the full registered category slug from a short key.
	 */
	public static function category_slug( string $short_slug ): string {
		return self::NAMESPACE_PREFIX . '-' . $short_slug;
	}

	/**
	 * All registered category slugs (full form). Used by e2e assertions and by the Registrar
	 * lookup path when dispatching an ability to its category.
	 */
	public static function get_all_slugs(): array {
		return array_map( array( __CLASS__, 'category_slug' ), array_keys( self::catalog() ) );
	}
}
