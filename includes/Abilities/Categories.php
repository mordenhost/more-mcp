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
				'label'       => __( 'More MCP: Core', 'mordenhost-mcp-server' ),
				'description' => __( 'Core WordPress operations: posts, pages, media, terms, comments, users, options, menus, themes, SEO meta (post and term level, six SEO plugins), permalinks, revisions, cron, error log, connection health, search, site info.', 'mordenhost-mcp-server' ),
			),
			'woocommerce'  => array(
				'label'       => __( 'More MCP: WooCommerce', 'mordenhost-mcp-server' ),
				'description' => __( 'WooCommerce products, orders, coupons, variations, customers, and store stats.', 'mordenhost-mcp-server' ),
			),
			'elementor'    => array(
				'label'       => __( 'More MCP: Elementor', 'mordenhost-mcp-server' ),
				'description' => __( 'Elementor page operations: outline read, clone, replace text, replace image, import template, add/update/delete/move widget, resolve loop templates, list local templates.', 'mordenhost-mcp-server' ),
			),
			'divi'         => array(
				'label'       => __( 'More MCP: Divi', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Divi 4 shortcode and Divi 5 block structure, with positional module inspection.', 'mordenhost-mcp-server' ),
			),
			'beaver-builder' => array(
				'label'       => __( 'More MCP: Beaver Builder', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Beaver Builder layout structure: page outline and addressed node inspection by stable node ID.', 'mordenhost-mcp-server' ),
			),
			'siteorigin-panels' => array(
				'label'       => __( 'More MCP: SiteOrigin Page Builder', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only SiteOrigin Page Builder layout structure: page outline across both storage paths (classic panels_data meta and Layout Blocks) and addressed widget inspection by row/cell/index. No write tools.', 'mordenhost-mcp-server' ),
			),
			'litespeed'    => array(
				'label'       => __( 'More MCP: LiteSpeed Cache', 'mordenhost-mcp-server' ),
				'description' => __( 'LiteSpeed Cache purge operations: single-URL purge and full cache purge.', 'mordenhost-mcp-server' ),
			),
			'acf'          => array(
				'label'       => __( 'More MCP: ACF', 'mordenhost-mcp-server' ),
				'description' => __( 'Advanced Custom Fields (ACF) field read/update and group enumeration.', 'mordenhost-mcp-server' ),
			),
			'metabox'      => array(
				'label'       => __( 'More MCP: Meta Box', 'mordenhost-mcp-server' ),
				'description' => __( 'Meta Box custom fields: read one field or all fields on a post (hydrated per field type), update a field with undo, and enumerate registered fields by post type.', 'mordenhost-mcp-server' ),
			),
			'redirection'  => array(
				'label'       => __( 'More MCP: Redirection', 'mordenhost-mcp-server' ),
				'description' => __( 'Redirection plugin: list/create/update redirects, list groups.', 'mordenhost-mcp-server' ),
			),
			'analytics'    => array(
				'label'       => __( 'More MCP: Analytics', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Site Kit, Jetpack Stats, MonsterInsights, Burst Statistics, Matomo, and Independent Analytics status, traffic summaries, and top content. The self-hosted providers (Burst, Matomo, Independent Analytics) report from their own local databases.', 'mordenhost-mcp-server' ),
			),
			'forms'        => array(
				'label'       => __( 'More MCP: Forms', 'mordenhost-mcp-server' ),
				'description' => __( 'Forms and lead capture (Gravity Forms, Fluent Forms): list forms and field schemas, read submissions with privacy-safe summaries, aggregate stats, and guarded entry status/trash writes with confirmation and undo.', 'mordenhost-mcp-server' ),
			),
			'email'        => array(
				'label'       => __( 'More MCP: Email', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only outgoing-email (SMTP) configuration status through WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP, GoSMTP, or SureMail: active mailer, setup completeness, and non-secret From name/email. Never returns credentials; no write tools.', 'mordenhost-mcp-server' ),
			),
			'wprocket'     => array(
				'label'       => __( 'More MCP: WP Rocket', 'mordenhost-mcp-server' ),
				'description' => __( 'WP Rocket cache purges: full-cache purge, single-URL purge, and minified-asset purge.', 'mordenhost-mcp-server' ),
			),
			'w3tc'         => array(
				'label'       => __( 'More MCP: W3 Total Cache', 'mordenhost-mcp-server' ),
				'description' => __( 'W3 Total Cache purges through the plugin\'s public API: all cache types, single-post page purge, minified assets, and a separate object-cache flush.', 'mordenhost-mcp-server' ),
			),
			'redis-cache'  => array(
				'label'       => __( 'More MCP: Redis Object Cache', 'mordenhost-mcp-server' ),
				'description' => __( 'Redis object-cache status (drop-in state, connection, server and client version) and an object-cache flush through wp_cache_flush().', 'mordenhost-mcp-server' ),
			),
			'autoptimize'  => array(
				'label'       => __( 'More MCP: Autoptimize', 'mordenhost-mcp-server' ),
				'description' => __( 'Autoptimize aggregated-asset cache: purge all cached CSS/JS via the plugin\'s own clearall(), and read its cache statistics. No write tools beyond the purge.', 'mordenhost-mcp-server' ),
			),
			'wp-optimize'  => array(
				'label'       => __( 'More MCP: WP-Optimize', 'mordenhost-mcp-server' ),
				'description' => __( 'WP-Optimize page cache only: purge via the plugin\'s own purge() and read enablement, size, and file count. No database-optimization operations.', 'mordenhost-mcp-server' ),
			),
			'updraftplus'  => array(
				'label'       => __( 'More MCP: UpdraftPlus', 'mordenhost-mcp-server' ),
				'description' => __( 'UpdraftPlus backups: list backup sets, read the last run and running state, and start a backup behind two-part confirmation. No restore or deletion.', 'mordenhost-mcp-server' ),
			),
			'backwpup'     => array(
				'label'       => __( 'More MCP: BackWPup', 'mordenhost-mcp-server' ),
				'description' => __( 'BackWPup backup jobs, read-only: list jobs with schedule and last-run status, and read one job\'s tasks and destinations. No start, edit, or delete.', 'mordenhost-mcp-server' ),
			),
			'duplicator'   => array(
				'label'       => __( 'More MCP: Duplicator', 'mordenhost-mcp-server' ),
				'description' => __( 'Duplicator backup packages, read-only: list packages with build status and read an overview of build activity. Never returns the package hash. No start-build tool.', 'mordenhost-mcp-server' ),
			),
			'wpvivid'      => array(
				'label'       => __( 'More MCP: WPvivid', 'mordenhost-mcp-server' ),
				'description' => __( 'WPvivid backups, read-only: list stored backups with type, time, and size, and read an overview of backup activity. No start-backup tool.', 'mordenhost-mcp-server' ),
			),
			'wordfence'    => array(
				'label'       => __( 'More MCP: Wordfence', 'mordenhost-mcp-server' ),
				'description' => __( 'Wordfence security reads: firewall/scan status, scan findings, blocked IPs, failed-login summaries, and a guarded start-scan.', 'mordenhost-mcp-server' ),
			),
			'defender'     => array(
				'label'       => __( 'More MCP: WP Defender', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only WP Defender security state: scan results and status, blocked IPs, lockout statistics, and hardening recommendation status.', 'mordenhost-mcp-server' ),
			),
			'solid-security' => array(
				'label'       => __( 'More MCP: Solid Security', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Solid Security state: active modules, event-log findings (file changes, brute-force, lockouts), and currently active lockouts. No start-scan tool.', 'mordenhost-mcp-server' ),
			),
			'sucuri'       => array(
				'label'       => __( 'More MCP: Sucuri Security', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only local Sucuri Security configuration status: account link presence, monitoring API state, alert email, and per-directory hardening. Audit logs and malware scans live in Sucuri\'s own remote dashboard.', 'mordenhost-mcp-server' ),
			),
			'really-simple-security' => array(
				'label'       => __( 'More MCP: Really Simple Security', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Really Simple Security state: SSL/firewall enablement and known vulnerabilities affecting installed plugins, themes, and core. No start-scan tool.', 'mordenhost-mcp-server' ),
			),
			'akismet'      => array(
				'label'       => __( 'More MCP: Akismet', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Akismet anti-spam status: whether a key is configured (never the key), lifetime spam caught, and spam currently in the moderation queue. Also re-checks one existing comment against Akismet without changing its status. No settings are written.', 'mordenhost-mcp-server' ),
			),
			'imagify'      => array(
				'label'       => __( 'More MCP: Imagify', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Imagify image-optimization status: whether a key is configured (never the key), total attachments in scope, optimized/unoptimized/errored counts, and total size saved. No write tools.', 'mordenhost-mcp-server' ),
			),
			'ewww'         => array(
				'label'       => __( 'More MCP: EWWW Image Optimizer', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only EWWW Image Optimizer status: version, total original vs optimized bytes, and derived bytes saved and savings percent. No write tools.', 'mordenhost-mcp-server' ),
			),
			'smush'        => array(
				'label'       => __( 'More MCP: Smush', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only WP Smush status: images smushed, size before/after, bytes saved, and savings percent. No write tools.', 'mordenhost-mcp-server' ),
			),
			'shortpixel'   => array(
				'label'       => __( 'More MCP: ShortPixel', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only ShortPixel status: version, average compression, and pending-optimization count. No write tools; no credentials returned.', 'mordenhost-mcp-server' ),
			),
			'instant-images' => array(
				'label'       => __( 'More MCP: Instant Images', 'mordenhost-mcp-server' ),
				'description' => __( 'Stock-photo search across Unsplash and Pexels using the API keys already stored by the Instant Images plugin (never returned). Returns candidates with license and required attribution; the image is added to the library via wp_upload_media_from_url.', 'mordenhost-mcp-server' ),
			),
			'events-calendar' => array(
				'label'       => __( 'More MCP: The Events Calendar', 'mordenhost-mcp-server' ),
				'description' => __( 'The Events Calendar access: list events by date range and read one event with hydrated venue (name, address, city) and organizer detail (the listing tools never return organizer contact fields or a venue phone); create, update and delete events and create venues and organizers through the plugin\'s own repository, checked against the event post types\' capabilities; read one venue (phone and website only for users who can edit it).', 'mordenhost-mcp-server' ),
			),
			'sugar-calendar' => array(
				'label'       => __( 'More MCP: Sugar Calendar', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Sugar Calendar access: list events (filter by start-date range, status, keyword; order and paginate) and read one event with full detail (start/end datetimes and time zones, all-day flag, recurrence, linked post id, and calendar/category names). Reads the plugin\'s own events table via its public query API. No write tools.', 'mordenhost-mcp-server' ),
			),
			'events-manager' => array(
				'label'       => __( 'More MCP: Events Manager', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Events Manager access: list events by date range, category, and keyword, and read one event with its timezone-aware start/end datetime, all-day and recurring flags, status, category names, and permalink. Reads the plugin\'s own event schedule table via its EM_Event API. No bookings or attendee data. No write tools.', 'mordenhost-mcp-server' ),
			),
			'ai-media'     => array(
				'label'       => __( 'More MCP: AI Media', 'mordenhost-mcp-server' ),
				'description' => __( 'AI image generation and alt-text via a configured AI provider (OpenAI, Google). Off by default and gated by an admin toggle; each call spends the site owner\'s provider credits. No credentials returned.', 'mordenhost-mcp-server' ),
			),
			'snippets'     => array(
				'label'       => __( 'More MCP: Code Snippets', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only access to the code snippets managed by the Code Snippets or WPCode plugin: list them and read one. No tool creates, edits, activates or deletes a snippet; that stays a human task in the plugin\'s own admin.', 'mordenhost-mcp-server' ),
			),
			'translatepress' => array(
				'label'       => __( 'More MCP: TranslatePress', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only TranslatePress multilingual configuration: default language, translation languages with their slugs and published state, and default-language subdirectory routing. No write tools.', 'mordenhost-mcp-server' ),
			),
			'polylang'     => array(
				'label'       => __( 'More MCP: Polylang', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Polylang multilingual status: configured languages, a post or term\'s assigned language, and its translation siblings. No write tools.', 'mordenhost-mcp-server' ),
			),
			'wpml'         => array(
				'label'       => __( 'More MCP: WPML', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only WPML multilingual status: active languages, an element\'s assigned language, and its translation siblings, via WPML\'s documented hook API. No write tools.', 'mordenhost-mcp-server' ),
			),
			'weglot'       => array(
				'label'       => __( 'More MCP: Weglot', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Weglot multilingual configuration: the original language, the configured destination languages with each one\'s code, custom code, and public flag, and whether Weglot is connected. Weglot translates through its cloud rather than storing one post per language, so it offers site-wide configuration only, no per-object language resolution. The API key is never returned. No write tools.', 'mordenhost-mcp-server' ),
			),
			'geodirectory' => array(
				'label'       => __( 'More MCP: GeoDirectory', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only GeoDirectory scale and field-schema discovery: for each listing post type GeoDirectory registers, the listing counts by status and category-taxonomy count, plus the custom-field schema (each active field\'s name, label, type, data type, and required flag) for a chosen post type. Listings themselves stay on the core post/term/meta tools; this adds the aggregate counts and field definitions those cannot give. Never a listing record, coordinates, owner identity, or the submit IP. No write tools.', 'mordenhost-mcp-server' ),
			),
			'business-directory' => array(
				'label'       => __( 'More MCP: Business Directory Plugin', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Business Directory Plugin scale and field-schema discovery: listing counts by status, category count, fee-plan counts (total and enabled), and the directory form-field schema (each field\'s label, type, association, and shortname). Listings themselves stay on the core post/term/meta tools. Payment and payer data is never read. No write tools.', 'mordenhost-mcp-server' ),
			),
			'ivory-search' => array(
				'label'       => __( 'More MCP: Ivory Search', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Ivory Search index health: whether the inverted index exists, the number of indexed posts and distinct terms, the total index size, and the number of configured search forms, plus a list of those search forms (id, title, status). Read through Ivory Search\'s own count helpers and its is_search_form CPT. No submitted queries, no write tools.', 'mordenhost-mcp-server' ),
			),
			'uncanny-automator' => array(
				'label'       => __( 'More MCP: Uncanny Automator', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Uncanny Automator discovery: list recipes (id, title, post status live/draft, and trigger/action counts from their uo-trigger/uo-action child posts) and aggregate run statistics (total, completed, and incomplete runs from the recipe log, plus recipe counts by status). The recipe log records which user each run belongs to — that column is never selected; counts only. No create/edit/run tools (running a recipe is an outward-facing action).', 'mordenhost-mcp-server' ),
			),
			'blog2social'  => array(
				'label'       => __( 'More MCP: Blog2Social', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Blog2Social social-publishing health: whether Blog2Social is connected (a local API token row exists — the token is never returned), scheduled vs published network-post counts, the publish-error count, and a per-social-network breakdown, read from Blog2Social\'s own local b2s_posts tables. Aggregate counts only, never post content, target URL, or author. No publish or schedule tool. No write tools.', 'mordenhost-mcp-server' ),
			),
			'estatik'      => array(
				'label'       => __( 'More MCP: Estatik', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Estatik real-estate scale and field-schema discovery: property counts by status, per-taxonomy term counts (types, categories, statuses, locations, labels), and the property field schema (each field\'s name, label, type, and section) from Estatik\'s field-builder tables. Properties themselves stay on the core post/term/meta tools; agent/owner contact in post meta is never read. No write tools.', 'mordenhost-mcp-server' ),
			),
			'fluentcrm'    => array(
				'label'       => __( 'More MCP: FluentCRM', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only FluentCRM health: total contacts by subscription status, and email-campaign counts by status with an optional recent-campaign list (title, type, status, recipient count, dates). Metadata and aggregate counts only, never contact records, recipient identities, or email content. No write tools.', 'mordenhost-mcp-server' ),
			),
			'mailpoet'     => array(
				'label'       => __( 'More MCP: MailPoet', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only MailPoet health: subscriber counts by status (subscribed/unconfirmed/unsubscribed/bounced/inactive), list/segment counts by type, and newsletter counts by status and type with an optional recent-campaign list (id, type, status, dates). Reads the plugin\'s own mailpoet_ tables via core $wpdb, each guarded by an existence check, excluding soft-deleted rows. Aggregate counts and campaign state only, never a subscriber record, email address, segment membership, or the email subject/pre-header/body content. No write tools.', 'mordenhost-mcp-server' ),
			),
			'groundhogg'   => array(
				'label'       => __( 'More MCP: Groundhogg', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Groundhogg health: contact counts by opt-in status (unconfirmed/confirmed/unsubscribed/weekly/monthly/hard_bounce/spam/complained/blocked), tag count, funnel (automation) counts by status, and broadcast (campaign) counts by status with an optional recent-broadcast list (id, object type, status, scheduled date). Reads the plugin\'s own gh_ tables via core $wpdb, each guarded by an existence check; the integer optin_status is mapped to a fixed public label vocabulary. Aggregate counts and state only, never a contact record, email address, tag membership, or email content. No write tools.', 'mordenhost-mcp-server' ),
			),
			'givewp'       => array(
				'label'       => __( 'More MCP: GiveWP', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only GiveWP fundraising health: donation-form counts by status, donation counts by status (pending/complete/refunded/failed/cancelled/abandoned/processing), donor count, the site-wide total raised with its currency, and campaign counts by status and goal type with an optional campaign list (id, name, status, goal type, goal amount, dates). Reads form/donation post-status counts via core wp_count_posts, the stored earnings-total option without recalculation, and the give_campaigns table via guarded $wpdb. Aggregate counts and a single total figure only, never a donation record, donor identity, per-donation amount, payment detail, or a campaign\'s marketing description/image/URL. No write tools.', 'mordenhost-mcp-server' ),
			),
			'pmpro'        => array(
				'label'       => __( 'More MCP: Paid Memberships Pro', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Paid Memberships Pro health: the membership-level count, member counts by status (active/inactive/cancelled/expired/changed/admin_changed/admin_cancelled), payment-subscription counts by status (kept separate from membership status), the configured currency, and the membership-level catalogue (per level: id, name, initial/recurring pricing, billing cycle, signup flag, expiration terms). Reads the plugin\'s own pmpro_membership_levels/pmpro_memberships_users/pmpro_subscriptions tables via core $wpdb, each guarded by an existence check. Aggregate counts and level definitions only, never a member record, user id, email, per-member level/expiration/subscription, order/transaction, or a level\'s marketing description. No write tools.', 'mordenhost-mcp-server' ),
			),
			'swpm'         => array(
				'label'       => __( 'More MCP: Simple Membership', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Simple Membership (Simple WP Membership) health: the membership-level count, member counts by account state (active/inactive/activation_required/expired/pending/unsubscribed), and the membership-level catalogue (per level: id, name, the WordPress role granted, and duration terms — No Expiry/Days/Weeks/Months/Years/Fixed Date/Annual Fixed Date plus the period). Reads the plugin\'s own swpm_membership_tbl/swpm_members_tbl tables via core $wpdb, each guarded by an existence check; the numeric duration type is mapped through the plugin\'s published label vocabulary. Aggregate counts and level definitions only, never a member record, name, email, phone, address, IP, per-member level/dates, payment/transaction row, or a level\'s content-protection lists. No write tools.', 'mordenhost-mcp-server' ),
			),
			'profilepress' => array(
				'label'       => __( 'More MCP: ProfilePress', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only ProfilePress membership health: the plan count, subscription counts by status (active/pending/expired/completed/trialling/cancelled), order counts by status (pending/completed/refunded/failed), the customer count, the store currency, and the plan catalogue (per plan: id, name, price, billing frequency, subscription length, signup fee, free-trial terms, and enabled state). Reads the plugin\'s own ppress_plans/ppress_subscriptions/ppress_orders/ppress_customers tables via core $wpdb, each guarded by an existence check; each status is mapped through the plugin\'s published label vocabulary. Aggregate counts and plan definitions only, never a subscription/order/customer record, user id, email, name, billing address, phone, IP, transaction/gateway id, per-member amount, a customer note or total spend, or a plan\'s description or internal metadata. No write tools.', 'mordenhost-mcp-server' ),
			),
				'restrictcontent' => array(
					'label'       => __( 'More MCP: Restrict Content', 'mordenhost-mcp-server' ),
					'description' => __( 'Read-only Restrict Content (Kadence Memberships) health: the membership-level count, membership counts by status (active/inactive/pending/cancelled/expired/free), the customer count, payment counts by status (pending/complete/failed/refunded/abandoned), the store currency, and the membership-level catalogue (per level: id, name, price, duration terms, trial terms, the WordPress role granted, and enabled state). Reads the plugin\'s own restrict_content_pro/rcp_memberships/rcp_customers/rcp_payments tables via core $wpdb, each guarded by an existence check; each status is mapped through the plugin\'s published label vocabulary. Aggregate counts and level definitions only, never a membership/customer/payment record, user id, email, name, a customer\'s stored IP addresses or notes, a membership\'s per-member amount/gateway/subscription-key, a payment\'s amount/transaction id/gateway, or a level\'s description text. No write tools.', 'mordenhost-mcp-server' ),
				),
			'charitable'   => array(
				'label'       => __( 'More MCP: Charitable', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Charitable fundraising health: campaign counts by status, donation counts by status label (Paid/Pending/Failed/Cancelled/Refunded), donor count, and the site-wide total raised with its currency, plus an optional campaign list (id, title, status, date). Reads campaign/donation post-status counts via core wp_count_posts and the plugin\'s own aggregate helpers (get_total, count_donors_with_donations) for the totals. Aggregate counts and a single total figure only, never a donation record, donor identity, per-donation amount, payment detail, or a campaign\'s body content or goal configuration. No write tools.', 'mordenhost-mcp-server' ),
			),
			'bbpress'      => array(
				'label'       => __( 'More MCP: bbPress', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only bbPress community health: site-wide counts of forums, topics (published and non-public), replies (published and non-public), topic tags, and registered users, read through bbPress\'s own bbp_get_statistics() aggregate (integer variants only); plus the forum structure listing for each forum its id, title, status (open/closed), visibility (public/private/hidden), parent forum id, topic count, and reply count via the plugin\'s own forum accessors and core get_posts against bbp_get_forum_post_type(). Aggregate counts and forum structure only, never a topic or reply record, a post body or excerpt, a topic/reply title, an author or user identity (no user id, display name, login, email, or IP), the anonymous-poster fields stored for guest replies, a subscription/favourite/engagement list, or a forum\'s moderator assignment. No write tools.', 'mordenhost-mcp-server' ),
			),
			'buddypress'   => array(
				'label'       => __( 'More MCP: BuddyPress', 'mordenhost-mcp-server' ),
				'description' => __( 'BuddyPress access. Site health: counts of total members, active members and groups, which components (members, groups, activity, friends, messages, notifications, profiles) are active, and the group structure (id, name, slug, status, parent, creation date, member count). Community: list and read members (user id, login and display name only, never an email address), read a group and its member list, list the activity stream, post an activity update, a group post or a reply, and delete an activity item, and read the connected user\'s own message threads. Private and hidden groups, and the activity posted in them, are visible only to their members and to moderators; a group a user cannot see answers "not found". Posting as another user needs moderator rights, and whoever posts into a group must belong to it. Messages are only ever the connected user\'s own and reading them does not mark them as read. No friendship or notification data, and no group, member or message creation.', 'mordenhost-mcp-server' ),
			),
			'fluentsupport' => array(
				'label'       => __( 'More MCP: Fluent Support', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Fluent Support helpdesk diagnostics: total tickets with breakdowns by status (new/active/closed via the plugin\'s own filterable Helper::ticketStatuses() vocabulary), by agent-set priority and by customer-set priority (via Helper::adminTicketPriorities()), and per mailbox id; conversation volume; mailbox, product, agent, and customer totals; and service-timing aggregates read from the integer columns Fluent Support already keeps on the ticket row (average first-response seconds, average time-to-close seconds, resolved count, total responses). Plus desk configuration: the mailbox definitions (id, name, slug, box type, default flag, and whether an inbound and a mapped address are configured) and the product catalogue (id, title, source, mailbox id). Aggregate counts and configuration only. This never returns a ticket record, a ticket title or number, ticket content or secret_content, any conversation or message content, an attachment, or a customer or agent identity (no id, name, email address, avatar, IP address, postal address, phone, note, or description), and no per-ticket or per-person breakdown. A mailbox\'s email address is reported only as configured or not, never as a value, and the mailbox settings blob is never read because on an IMAP-connected mailbox it holds mail credentials; a product\'s description and settings blob are likewise never read. No write tools: replying, changing status, assigning an agent, and closing or deleting a ticket all email the customer, so they are outward-facing actions that stay out of scope.', 'mordenhost-mcp-server' ),
			),
			'learnpress'   => array(
				'label'       => __( 'More MCP: LearnPress', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only LearnPress LMS reads: course counts by status, distinct enrolled-student count, and one course\'s curriculum outline (ordered sections and their lessons/quizzes by title and type). No lesson content, no per-learner progress or grades. No write tools.', 'mordenhost-mcp-server' ),
			),
			'sensei'       => array(
				'label'       => __( 'More MCP: Sensei LMS', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Sensei LMS reads: course counts by status, distinct enrolled-student count, and one course\'s curriculum outline (ordered modules and their lessons, plus ungrouped lessons, by title and order). No lesson or quiz content, no questions, no per-learner progress or grades. No write tools.', 'mordenhost-mcp-server' ),
			),
			'masteriyo'    => array(
				'label'       => __( 'More MCP: Masteriyo LMS', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Masteriyo LMS reads: course counts by status, distinct enrolled-student count, and one course\'s curriculum outline (ordered sections and their lessons/quizzes by title, type, and order, plus ungrouped items and category names). Reads the plugin\'s own mto- CPTs and enrolment activities table. No lesson or quiz content, no questions, no per-learner progress or grades. No write tools.', 'mordenhost-mcp-server' ),
			),
			'tutor'        => array(
				'label'       => __( 'More MCP: Tutor LMS', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Tutor LMS reads: course counts by status, distinct enrolled-student count, and one course\'s curriculum outline (ordered topics and their lessons/quizzes/assignments by title, type, and order, plus category names). Reads the plugin\'s own courses/topics/lesson/tutor_quiz/tutor_assignments CPTs and the tutor_enrolled post type. No lesson or quiz content, no questions, no student identity, and no per-learner progress or grades. No write tools.', 'mordenhost-mcp-server' ),
			),
			'lifterlms'    => array(
				'label'       => __( 'More MCP: LifterLMS', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only LifterLMS reads: course counts by status, distinct enrolled-student count, and one course\'s curriculum outline (ordered sections and their lessons by title and order, each lesson flagged for whether it has a quiz plus that quiz\'s title, and category names). Reads the plugin\'s own course/section/lesson/llms_quiz CPTs via the _llms_parent_* meta and the lifterlms_user_postmeta enrolment table. No lesson or quiz content, no questions, no student identity, and no per-learner progress or grades. No write tools.', 'mordenhost-mcp-server' ),
			),
			'amelia'       => array(
				'label'       => __( 'More MCP: Amelia', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Amelia booking reads: counts of categories, services (by status), employees, appointments (by status), and events (by status), plus the bookable service catalogue (categories with each service\'s name, price, duration, capacity, status). Aggregate counts and service definitions only, never a customer name/email, appointment participant, time slot, or payment record. No write tools.', 'mordenhost-mcp-server' ),
			),
			'latepoint'    => array(
				'label'       => __( 'More MCP: LatePoint', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only LatePoint booking reads: counts of service categories, services (by status), agents (by status), bookings (by status), and customers, plus the bookable service catalogue (categories with each service\'s name, price range, duration, capacity, status). Aggregate counts and service definitions only, never a customer name/email, booking participant, time slot, or payment record. No write tools.', 'mordenhost-mcp-server' ),
			),
			'bookly'       => array(
				'label'       => __( 'More MCP: Bookly', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Bookly booking reads: counts of service categories, services (by visibility), staff (by visibility), scheduled appointment slots, per-customer bookings (by status: pending/approved/cancelled/rejected/waitlisted/done), and customers, plus the bookable service catalogue (categories with each service\'s title, type, price, duration in seconds and minutes, capacity, visibility). Reads the plugin\'s own bookly_ tables via core $wpdb, each guarded by an existence check. Aggregate counts and service definitions only, never a customer name/email/phone, appointment participant, time slot, or payment record. No write tools.', 'mordenhost-mcp-server' ),
			),
			'bookingpress'   => array(
					'label'       => __( 'More MCP: BookingPress', 'mordenhost-mcp-server' ),
					'description' => __( 'Read-only BookingPress booking reads: counts of service categories, services, staff members (when the staff module is present), appointment bookings (total and by status: Approved/Pending/Cancelled/Rejected), and customers, plus the configured currency and the bookable service catalogue (categories with each service\'s name, price, and duration value/unit and derived minutes). Reads the plugin\'s own bookingpress_ tables via core $wpdb, each guarded by an existence check. Aggregate counts and service definitions only, never a customer name/email/phone, appointment participant, time slot, note, or payment record, and never a service description. No write tools.', 'mordenhost-mcp-server' ),
				),
				'pods'         => array(
				'label'       => __( 'More MCP: Pods', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only content-model discovery for models defined with Pods: list every registered model with its type, storage, and field/group counts, and read one model\'s full field schema (name, label, type, required/repeatable, and relationship target type/name). Registration definitions only, never row content or field values. No write tools.', 'mordenhost-mcp-server' ),
			),
			'cptui'        => array(
				'label'       => __( 'More MCP: Custom Post Type UI', 'mordenhost-mcp-server' ),
				'description' => __( 'Registration-definition management for post types and taxonomies created with Custom Post Type UI. Read: list each post type (labels, public/hierarchical flags, supports, bound taxonomies, archive/REST exposure) and each taxonomy (labels, hierarchical flag, attached post types, public/REST exposure). Write: create, update, or delete a post-type or taxonomy DEFINITION (dry-run preview, confirmation on delete, undo token). Registration definitions only, never the content: existing posts and terms are never created, edited, or deleted here, they stay on the core post/term tools. Deleting a definition unregisters it and orphans its existing rows rather than removing them.', 'mordenhost-mcp-server' ),
			),
			'mbcpt'        => array(
				'label'       => __( 'More MCP: MB Custom Post Types', 'mordenhost-mcp-server' ),
				'description' => __( 'Registration-definition management for post types and taxonomies created with MB Custom Post Types (Meta Box). Read: list each post type (labels, public/hierarchical flags, supports, bound taxonomies, archive/REST exposure) and each taxonomy (labels, hierarchical flag, attached post types, public/REST exposure). Write: create, update, or delete a post-type or taxonomy DEFINITION (dry-run preview, confirmation on delete, undo token). Registration definitions only, never the content: existing posts and terms are never created, edited, or deleted here, they stay on the core post/term tools. Deleting a definition trashes its backing definition post, which unregisters it and orphans its existing rows rather than removing them; the undo token restores it.', 'mordenhost-mcp-server' ),
			),
			'toolset-types' => array(
				'label'       => __( 'More MCP: Toolset Types', 'mordenhost-mcp-server' ),
				'description' => __( 'Registration-definition management for post types and taxonomies created with Toolset Types, plus read-only custom-field discovery. Read: list custom post types (labels, public/hierarchical flags, supports, bound taxonomies), taxonomies (labels, hierarchical flag, attached post types), and custom-field definitions (slug, name, type, description). Write: create, update, or delete a post-type or taxonomy DEFINITION (dry-run preview, confirmation on delete, undo token); binding a post type to a taxonomy is mirrored across both Types options exactly as the Types admin does. Registration definitions only, never the content: existing posts and terms are never created, edited, or deleted here, they stay on the core post/term tools, and a field\'s values stay on the meta tools. Field definitions are read-only. Deleting a definition unregisters it and orphans its existing rows rather than removing them.', 'mordenhost-mcp-server' ),
			),
			'edd'          => array(
				'label'       => __( 'More MCP: Easy Digital Downloads', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Easy Digital Downloads store reads: download counts by post status, order counts by payment status, total customers, lifetime earnings and store currency, plus the download catalogue (each product\'s title, type, single or variable price range, variable-tier count, and file count). Aggregate counts and product definitions only, never an order record, a purchase, an order line item, a customer name/email, a license key, or a downloadable file URL. No write tools.', 'mordenhost-mcp-server' ),
			),
			'fluentcart'   => array(
				'label'       => __( 'More MCP: FluentCart', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only FluentCart store reads: product counts by post status, order counts by payment status, total customers, subscription counts by status, and store currency, plus the product catalogue (each product\'s title and its variations\' title, SKU, price, fulfillment type, stock status, and active flag). Aggregate counts and product/variation definitions only, never an order record, an order line item, a customer name/email, a subscriber, a transaction, or a downloadable file. No write tools.', 'mordenhost-mcp-server' ),
			),
			'ai1wm'        => array(
				'label'       => __( 'More MCP: All-in-One WP Migration', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only All-in-One WP Migration backup inventory: list existing .wpress backup archives with filename, user-assigned label, size, and creation time, plus total count and total size. Answers "is there a recoverable backup?" before risky maintenance. Never returns the backup directory path or any download URL, and offers no start, restore, or delete: the export path is a multi-step protocol that is not safe to trigger blind.', 'mordenhost-mcp-server' ),
			),
			'patchstack'   => array(
				'label'       => __( 'More MCP: Patchstack', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Patchstack security status: plugin version, license edition, last successful cloud-sync time, the locally mirrored count of known vulnerabilities and available fixes, the number of IP block list entries, and aggregate counts of blocked requests and failed logins from the plugin\'s own local log tables. Vulnerability data itself lives in Patchstack\'s cloud; this reads only the local mirror the plugin writes after each sync, never calls the API, and never returns an IP address, user agent, request URI, request body, or credential. No start-scan tool.', 'mordenhost-mcp-server' ),
			),
			'relevanssi'   => array(
				'label'       => __( 'More MCP: Relevanssi', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only Relevanssi search-index health: whether the index is built, indexed document and distinct-term counts (read from Relevanssi\'s own cached count options), whether query logging is enabled, aggregate query-log statistics (total and distinct searches, average hits, top queries by hit count), and a bounded search through Relevanssi\'s own index capped at 20 results. The query-log table stores user ids, IPs, and session ids — those columns are never selected. No reindex or index-management tools.', 'mordenhost-mcp-server' ),
			),
			'slicewp'      => array(
				'label'       => __( 'More MCP: SliceWP', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only SliceWP affiliate-program health: affiliate counts total and by status (active/inactive/pending/rejected), commission counts total and by status (paid/unpaid/pending/rejected), aggregate commission amounts per status, and the total visit count, read through SliceWP\'s own public getter functions with the plugin\'s own published status vocabularies. Aggregate counts and amounts only — never an affiliate record, user id, payment email, website, a commission\'s customer or order reference, or a payout. No write tools, and payouts stay out of scope.', 'mordenhost-mcp-server' ),
			),
			'automatorwp'  => array(
				'label'       => __( 'More MCP: AutomatorWP', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only AutomatorWP automation discovery: list automations (recipes) with title, type, status (active/in-progress/inactive, AutomatorWP\'s own vocabulary), run limits, and trigger/action counts, plus aggregate run statistics (total log entries and counts by log type: trigger completions, action executions, filter evaluations). The logs table records which user each entry belongs to — that column is never selected; counts only. No tools to create, edit, or trigger an automation: running a recipe is an outward-facing action that stays out of scope.', 'mordenhost-mcp-server' ),
			),
			'wpjm'         => array(
				'label'       => __( 'More MCP: WP Job Manager', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only WP Job Manager job-board statistics: job counts by status (publish, pending, expired, preview, and pending_payment when the paid listings extension is active), job-category and job-type term counts, filled and featured listing counts, and published listings expired or expiring within 7 days. Job listings themselves stay on the core post, term, and meta tools — this is the one-read operational aggregate. Application data (a paid add-on holding applicant personal data) is never touched. No write tools.', 'mordenhost-mcp-server' ),
			),
			'wp-go-maps'   => array(
				'label'       => __( 'More MCP: WP Go Maps', 'mordenhost-mcp-server' ),
				'description' => __( 'Read-only WP Go Maps inventory: every map with its title, centre, zoom, active state, per-map marker counts split by the plugin\'s approved flag, and polygon/polyline/circle/rectangle counts. Marker rows (addresses, coordinates) are never returned — aggregate counts only. No write tools.', 'mordenhost-mcp-server' ),
			),
			'blocks'       => array(
				'label'       => __( 'More MCP: Blocks', 'mordenhost-mcp-server' ),
				'description' => __( 'Gutenberg block editing, FSE site templates, patterns, and reusable blocks.', 'mordenhost-mcp-server' ),
			),
			'webhooks'     => array(
				'label'       => __( 'More MCP: Webhooks', 'mordenhost-mcp-server' ),
				'description' => __( 'Event-triggered outbound HTTPS webhook subscriptions with HMAC-signed, privacy-bounded payloads. Create and delete operations require administrator confirmation.', 'mordenhost-mcp-server' ),
			),
			'lifecycle'    => array(
				'label'       => __( 'More MCP: Plugins & Themes', 'mordenhost-mcp-server' ),
				'description' => __( 'Install, update, activate, deactivate, and delete plugins and themes. Highest-impact category: these change the code running on the site. Disabled unless an administrator enables plugin management, and every write requires a two-part confirmation.', 'mordenhost-mcp-server' ),
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
