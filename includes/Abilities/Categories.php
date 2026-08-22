<?php

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Categories {

	const NAMESPACE_PREFIX = 'more-mcp';

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
			'litespeed'    => array(
				'label'       => __( 'More MCP: LiteSpeed Cache', 'more-mcp' ),
				'description' => __( 'LiteSpeed Cache purge operations: single-URL purge and full cache purge.', 'more-mcp' ),
			),
			'acf'          => array(
				'label'       => __( 'More MCP: ACF', 'more-mcp' ),
				'description' => __( 'Advanced Custom Fields (ACF) field read/update and group enumeration.', 'more-mcp' ),
			),
			'metabox'      => array(
				'label'       => __( 'More MCP: Meta Box', 'more-mcp' ),
				'description' => __( 'Meta Box custom fields: read one field or all fields on a post (hydrated per field type), update a field with undo, and enumerate registered fields by post type.', 'more-mcp' ),
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
			'email'        => array(
				'label'       => __( 'More MCP: Email', 'more-mcp' ),
				'description' => __( 'Read-only outgoing-email (SMTP) configuration status through WP Mail SMTP or Easy WP SMTP: active mailer, setup completeness, and non-secret From name/email. Never returns credentials; no write tools.', 'more-mcp' ),
			),
			'wprocket'     => array(
				'label'       => __( 'More MCP: WP Rocket', 'more-mcp' ),
				'description' => __( 'WP Rocket cache purges: full-cache purge, single-URL purge, and minified-asset purge.', 'more-mcp' ),
			),
			'updraftplus'  => array(
				'label'       => __( 'More MCP: UpdraftPlus', 'more-mcp' ),
				'description' => __( 'UpdraftPlus backups: list backup sets, read the last run and running state, and start a backup behind two-part confirmation. No restore or deletion.', 'more-mcp' ),
			),
			'backwpup'     => array(
				'label'       => __( 'More MCP: BackWPup', 'more-mcp' ),
				'description' => __( 'BackWPup backup jobs, read-only: list jobs with schedule and last-run status, and read one job\'s tasks and destinations. No start, edit, or delete.', 'more-mcp' ),
			),
			'wordfence'    => array(
				'label'       => __( 'More MCP: Wordfence', 'more-mcp' ),
				'description' => __( 'Wordfence security reads: firewall/scan status, scan findings, blocked IPs, failed-login summaries, and a guarded start-scan.', 'more-mcp' ),
			),
			'defender'     => array(
				'label'       => __( 'More MCP: WP Defender', 'more-mcp' ),
				'description' => __( 'Read-only WP Defender security state: scan results and status, blocked IPs, lockout statistics, and hardening recommendation status.', 'more-mcp' ),
			),
			'akismet'      => array(
				'label'       => __( 'More MCP: Akismet', 'more-mcp' ),
				'description' => __( 'Read-only Akismet anti-spam status: whether a key is configured (never the key), lifetime spam caught, and spam currently in the moderation queue. No write tools.', 'more-mcp' ),
			),
			'imagify'      => array(
				'label'       => __( 'More MCP: Imagify', 'more-mcp' ),
				'description' => __( 'Read-only Imagify image-optimization status: whether a key is configured (never the key), optimized and errored attachment counts, and total size saved. No write tools.', 'more-mcp' ),
			),
			'translatepress' => array(
				'label'       => __( 'More MCP: TranslatePress', 'more-mcp' ),
				'description' => __( 'Read-only TranslatePress multilingual configuration: default language, translation languages with their slugs and published state, and default-language subdirectory routing. No write tools.', 'more-mcp' ),
			),
			'fluentcrm'    => array(
				'label'       => __( 'More MCP: FluentCRM', 'more-mcp' ),
				'description' => __( 'Read-only FluentCRM contact-list health: total contacts and a breakdown by subscription status. Aggregate counts only, never contact records or personal data. No write tools.', 'more-mcp' ),
			),
			'learnpress'   => array(
				'label'       => __( 'More MCP: LearnPress', 'more-mcp' ),
				'description' => __( 'Read-only LearnPress LMS scale: course counts by status and the number of distinct enrolled students. Aggregate counts only, never course or student records. No write tools.', 'more-mcp' ),
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

	public static function category_slug( string $short_slug ): string {
		return self::NAMESPACE_PREFIX . '-' . $short_slug;
	}

	public static function get_all_slugs(): array {
		return array_map( array( __CLASS__, 'category_slug' ), array_keys( self::catalog() ) );
	}
}
