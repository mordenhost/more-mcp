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
				'description' => __( 'Read-only outgoing-email (SMTP) configuration status through WP Mail SMTP, Easy WP SMTP, FluentSMTP, or Post SMTP: active mailer, setup completeness, and non-secret From name/email. Never returns credentials; no write tools.', 'more-mcp' ),
			),
			'wprocket'     => array(
				'label'       => __( 'More MCP: WP Rocket', 'more-mcp' ),
				'description' => __( 'WP Rocket cache purges: full-cache purge, single-URL purge, and minified-asset purge.', 'more-mcp' ),
			),
			'w3tc'         => array(
				'label'       => __( 'More MCP: W3 Total Cache', 'more-mcp' ),
				'description' => __( 'W3 Total Cache purges through the plugin\'s public API: all cache types, single-post page purge, minified assets, and a separate object-cache flush.', 'more-mcp' ),
			),
			'redis-cache'  => array(
				'label'       => __( 'More MCP: Redis Object Cache', 'more-mcp' ),
				'description' => __( 'Redis object-cache status (drop-in state, connection, server and client version) and an object-cache flush through wp_cache_flush().', 'more-mcp' ),
			),
			'autoptimize'  => array(
				'label'       => __( 'More MCP: Autoptimize', 'more-mcp' ),
				'description' => __( 'Autoptimize aggregated-asset cache: purge all cached CSS/JS via the plugin\'s own clearall(), and read its cache statistics. No write tools beyond the purge.', 'more-mcp' ),
			),
			'wp-optimize'  => array(
				'label'       => __( 'More MCP: WP-Optimize', 'more-mcp' ),
				'description' => __( 'WP-Optimize page cache only: purge via the plugin\'s own purge() and read enablement, size, and file count. No database-optimization operations.', 'more-mcp' ),
			),
			'updraftplus'  => array(
				'label'       => __( 'More MCP: UpdraftPlus', 'more-mcp' ),
				'description' => __( 'UpdraftPlus backups: list backup sets, read the last run and running state, and start a backup behind two-part confirmation. No restore or deletion.', 'more-mcp' ),
			),
			'backwpup'     => array(
				'label'       => __( 'More MCP: BackWPup', 'more-mcp' ),
				'description' => __( 'BackWPup backup jobs, read-only: list jobs with schedule and last-run status, and read one job\'s tasks and destinations. No start, edit, or delete.', 'more-mcp' ),
			),
			'duplicator'   => array(
				'label'       => __( 'More MCP: Duplicator', 'more-mcp' ),
				'description' => __( 'Duplicator backup packages, read-only: list packages with build status and read an overview of build activity. Never returns the package hash. No start-build tool.', 'more-mcp' ),
			),
			'wpvivid'      => array(
				'label'       => __( 'More MCP: WPvivid', 'more-mcp' ),
				'description' => __( 'WPvivid backups, read-only: list stored backups with type, time, and size, and read an overview of backup activity. No start-backup tool.', 'more-mcp' ),
			),
			'wordfence'    => array(
				'label'       => __( 'More MCP: Wordfence', 'more-mcp' ),
				'description' => __( 'Wordfence security reads: firewall/scan status, scan findings, blocked IPs, failed-login summaries, and a guarded start-scan.', 'more-mcp' ),
			),
			'defender'     => array(
				'label'       => __( 'More MCP: WP Defender', 'more-mcp' ),
				'description' => __( 'Read-only WP Defender security state: scan results and status, blocked IPs, lockout statistics, and hardening recommendation status.', 'more-mcp' ),
			),
			'solid-security' => array(
				'label'       => __( 'More MCP: Solid Security', 'more-mcp' ),
				'description' => __( 'Read-only Solid Security state: active modules, event-log findings (file changes, brute-force, lockouts), and currently active lockouts. No start-scan tool.', 'more-mcp' ),
			),
			'sucuri'       => array(
				'label'       => __( 'More MCP: Sucuri Security', 'more-mcp' ),
				'description' => __( 'Read-only local Sucuri Security configuration status: account link presence, monitoring API state, alert email, and per-directory hardening. Audit logs and malware scans live in Sucuri\'s own remote dashboard.', 'more-mcp' ),
			),
			'really-simple-security' => array(
				'label'       => __( 'More MCP: Really Simple Security', 'more-mcp' ),
				'description' => __( 'Read-only Really Simple Security state: SSL/firewall enablement and known vulnerabilities affecting installed plugins, themes, and core. No start-scan tool.', 'more-mcp' ),
			),
			'akismet'      => array(
				'label'       => __( 'More MCP: Akismet', 'more-mcp' ),
				'description' => __( 'Read-only Akismet anti-spam status: whether a key is configured (never the key), lifetime spam caught, and spam currently in the moderation queue. No write tools.', 'more-mcp' ),
			),
			'imagify'      => array(
				'label'       => __( 'More MCP: Imagify', 'more-mcp' ),
				'description' => __( 'Read-only Imagify image-optimization status: whether a key is configured (never the key), optimized and errored attachment counts, and total size saved. No write tools.', 'more-mcp' ),
			),
			'ewww'         => array(
				'label'       => __( 'More MCP: EWWW Image Optimizer', 'more-mcp' ),
				'description' => __( 'Read-only EWWW Image Optimizer status: version, total original vs optimized bytes, and derived bytes saved and savings percent. No write tools.', 'more-mcp' ),
			),
			'smush'        => array(
				'label'       => __( 'More MCP: Smush', 'more-mcp' ),
				'description' => __( 'Read-only WP Smush status: images smushed, size before/after, bytes saved, and savings percent. No write tools.', 'more-mcp' ),
			),
			'shortpixel'   => array(
				'label'       => __( 'More MCP: ShortPixel', 'more-mcp' ),
				'description' => __( 'Read-only ShortPixel status: version, average compression, and pending-optimization count. No write tools; no credentials returned.', 'more-mcp' ),
			),
			'instant-images' => array(
				'label'       => __( 'More MCP: Instant Images', 'more-mcp' ),
				'description' => __( 'Stock-photo search across Unsplash and Pexels using the API keys already stored by the Instant Images plugin (never returned). Returns candidates with license and required attribution; the image is added to the library via wp_upload_media_from_url.', 'more-mcp' ),
			),
			'events-calendar' => array(
				'label'       => __( 'More MCP: The Events Calendar', 'more-mcp' ),
				'description' => __( 'Read-only The Events Calendar access: list events by date range and read one event with hydrated venue (name, address, city) and organizer detail. No write tools.', 'more-mcp' ),
			),
			'ai-media'     => array(
				'label'       => __( 'More MCP: AI Media', 'more-mcp' ),
				'description' => __( 'AI image generation and alt-text via a configured AI provider (OpenAI, Google). Off by default and gated by an admin toggle; each call spends the site owner\'s provider credits. No credentials returned.', 'more-mcp' ),
			),
			'snippets'     => array(
				'label'       => __( 'More MCP: Code Snippets', 'more-mcp' ),
				'description' => __( 'Read code snippets, and create/update CSS or JS snippets through the Code Snippets plugin. PHP snippets are read-only (writing executable PHP is refused). Writes require an admin toggle, are saved disabled for a human to activate, and use two-part confirmation with undo.', 'more-mcp' ),
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
			'webhooks'     => array(
				'label'       => __( 'More MCP: Webhooks', 'more-mcp' ),
				'description' => __( 'Event-triggered outbound HTTPS webhook subscriptions with HMAC-signed, privacy-bounded payloads. Create and delete operations require administrator confirmation.', 'more-mcp' ),
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
