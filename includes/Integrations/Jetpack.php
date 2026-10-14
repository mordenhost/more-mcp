<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jetpack {

	const MODULE_MONITOR       = 'monitor';
	const MODULE_SITEMAPS      = 'sitemaps';
	const MODULE_SUBSCRIPTIONS = 'subscriptions';
	const MODULE_SHORTLINKS    = 'shortlinks';
	const MODULE_STATS         = 'stats';

	const SITEMAP_CRON_HOOK  = 'jp_sitemap_cron_hook';
	const SITEMAP_LOCK       = 'jetpack-sitemap-state-lock';
	const MONITOR_DOWNTIME   = 'monitor_last_downtime';
	const MONITOR_NOTIFY_OPT = 'monitor_receive_notifications';
	const SUBSCRIBER_CACHE   = 'jetpack_newsletter_subscriber_stats';

	const MAX_SHORTLINK_IDS = 100;
	const MAX_SITEMAPS      = 200;
	const MAX_BACKUP_ROWS   = 50;

	const PERIODS             = array( 'day', 'week', 'month', 'year' );
	const VISIT_FIELDS        = array( 'views', 'visitors', 'likes', 'comments' );
	const TOP_CONTENT_TYPES   = array( 'posts', 'referrers', 'search-terms', 'clicks', 'tags', 'authors', 'countries', 'downloads', 'video-plays' );
	const REPLY_TO_VALUES     = array( 'comment', 'author', 'no-reply' );
	const READ_ONLY_TOOLS     = array(
		'jetpack_get_connection_status',
		'jetpack_get_modules',
		'jetpack_get_site_overview',
		'jetpack_get_visits',
		'jetpack_get_top_content',
		'jetpack_get_post_views',
		'jetpack_stats_get_settings',
		'jetpack_get_backup_overview',
		'jetpack_list_backups',
		'jetpack_list_restores',
		'jetpack_get_monitor_status',
		'jetpack_get_newsletter_settings',
		'jetpack_get_subscriber_stats',
		'jetpack_get_sitemap_status',
		'jetpack_get_shortlinks',
	);

	const TOP_CONTENT_MAP = array(
		'posts'        => array( 'list' => 'postviews', 'label' => 'title', 'value' => 'views', 'href' => 'href' ),
		'referrers'    => array( 'list' => 'groups', 'label' => 'name', 'value' => 'total', 'href' => 'url' ),
		'search-terms' => array( 'list' => 'search_terms', 'label' => 'term', 'value' => 'views' ),
		'clicks'       => array( 'list' => 'clicks', 'label' => 'name', 'value' => 'views', 'href' => 'url', 'label_fallback' => 'url' ),
		'authors'      => array( 'list' => 'authors', 'label' => 'name', 'value' => 'views' ),
		'downloads'    => array( 'list' => 'files', 'label' => 'filename', 'value' => 'download_count', 'href' => 'relative_url', 'label_fallback' => 'relative_url' ),
		'video-plays'  => array( 'list' => 'plays', 'label' => 'title', 'value' => 'plays' ),
	);

	public static function is_available() {
		return class_exists( 'Jetpack' ) && class_exists( 'Jetpack_Options' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'jetpack' ),
			'capabilities' => array( 'analytics', 'backup' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		$empty  = array( 'type' => 'object', 'properties' => new \stdClass() );
		$period = array( 'type' => 'string', 'enum' => self::PERIODS, 'description' => 'Aggregation period. Defaults to day.' );
		$date   = array( 'type' => 'string', 'description' => 'End date, YYYY-MM-DD. Defaults to today (UTC).' );

		return array(
			
			array(
				'name'        => 'jetpack_get_connection_status',
				'description' => 'Read the Jetpack connection state: whether the site is registered with WordPress.com, whether any user and the calling user are connected, the connection owner\'s local user ID, the WordPress.com blog ID, offline mode, and the Jetpack version. Read-only and local. Tokens are never returned.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_get_modules',
				'description' => 'List Jetpack modules with slug, name, description, active state, feature tags, plan classes, and whether each needs a site or user connection. Filter by slug, active, feature, or a search string. Read-only and local. Use before jetpack_set_module_status to find a valid slug.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'    => array( 'type' => 'string', 'description' => 'Return one module by slug. An unknown slug returns an empty list.' ),
						'active'  => array( 'type' => 'boolean', 'description' => 'Only return modules whose active state matches.' ),
						'feature' => array( 'type' => 'string', 'description' => 'Case-insensitive match against a feature tag, e.g. "Security", "Performance", "Traffic".' ),
						'search'  => array( 'type' => 'string', 'description' => 'Case-insensitive substring match against slug, name, and description.' ),
					),
				),
			),
			array(
				'name'        => 'jetpack_set_module_status',
				'description' => 'Activate or deactivate one Jetpack module. This changes which Jetpack features run on the site (security, SSO, sitemaps, sharing, and so on), so it is a two-part confirmation: call without confirm to get a preview that changes nothing; call again with confirm=true and confirm_slug set to the same slug to apply. Returns the active state read back from Jetpack. Activation can fail when the module needs a WordPress.com connection or a paid plan, and is refused when a standalone plugin that conflicts with the module is active.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string', 'description' => 'Module slug from jetpack_get_modules, e.g. "stats", "sitemaps", "monitor".' ),
						'active'       => array( 'type' => 'boolean', 'description' => 'true to activate, false to deactivate.' ),
						'confirm'      => array( 'type' => 'boolean', 'description' => 'Must be true to apply. Omit or send false to get a preview instead. The preview is the intended first call.' ),
						'confirm_slug' => array( 'type' => 'string', 'description' => 'Repeat the exact module slug. Required with confirm=true.' ),
					),
					'required'   => array( 'slug', 'active' ),
				),
			),

			array(
				'name'        => 'jetpack_get_site_overview',
				'description' => 'Jetpack Stats snapshot: today\'s views and visitors, this week\'s and this month\'s views, the posting streak, today\'s top post and top referrer. Fetched from WordPress.com through Jetpack\'s own signed client and cached by Jetpack for five minutes. When one of the three underlying reports fails, partial is true and errors names it. Read-only. Requires a connected site.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_get_visits',
				'description' => 'Jetpack Stats traffic over time: one row per period with views, visitors, likes, or comments. Fetched from WordPress.com through Jetpack\'s signed client. Read-only. Requires a connected site.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'unit'     => $period,
						'quantity' => array( 'type' => 'integer', 'description' => 'Number of periods, 1 to 90. Defaults to 30.' ),
						'date'     => $date,
						'fields'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => self::VISIT_FIELDS ),
							'description' => 'Metrics per row. Defaults to views and visitors.',
						),
					),
				),
			),
			array(
				'name'        => 'jetpack_get_top_content',
				'description' => 'Jetpack Stats top items for one type (posts, referrers, search terms, outbound clicks, tags, authors, countries, file downloads, or video plays) as ranked rows of { rank, label, value, href? }. Fetched from WordPress.com through Jetpack\'s signed client. Read-only. Requires a connected site.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'type'   => array( 'type' => 'string', 'enum' => self::TOP_CONTENT_TYPES, 'description' => 'Which report to read.' ),
						'period' => $period,
						'date'   => $date,
						'num'    => array( 'type' => 'integer', 'description' => 'Number of periods to roll up, 1 to 90. Defaults to 1.' ),
						'max'    => array( 'type' => 'integer', 'description' => 'Maximum rows, 1 to 100. Defaults to 20.' ),
					),
					'required'   => array( 'type' ),
				),
			),
			array(
				'name'        => 'jetpack_get_post_views',
				'description' => 'Jetpack Stats views for one post: the total and a per-period series. Fetched from WordPress.com through Jetpack\'s signed client. Read-only. Requires a connected site.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
						'period'  => $period,
						'num'     => array( 'type' => 'integer', 'description' => 'Number of periods, 1 to 90. Defaults to 30.' ),
						'date'    => $date,
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'jetpack_stats_get_settings',
				'description' => 'Read the Jetpack Stats settings: whether the admin-bar item shows, which roles can view Stats, whose visits are counted, and whether Do Not Track is honoured. Read-only and local.',
				'inputSchema' => $empty,
			),

			array(
				'name'        => 'jetpack_get_backup_overview',
				'description' => 'Jetpack VaultPress Backup overview: the Backup state reported by WordPress.com (active, provisioning, awaiting_credentials, unavailable, ...) with its reason and last update, plus the most recent backup. Uses Jetpack\'s own Backup-state reader (cached for 30 minutes) and signed client. Read-only. Backup credentials are never returned.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_list_backups',
				'description' => 'List the most recent Jetpack VaultPress Backup records (WordPress.com returns about the last ten) with rewind ID, start and update times, status, and whether each can be restored. Read-only, fetched through Jetpack\'s signed client.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum rows, 1 to 50. Defaults to 10.' ),
					),
				),
			),
			array(
				'name'        => 'jetpack_list_restores',
				'description' => 'List recent Jetpack VaultPress Backup restore operations with rewind ID, start and update times, status, and progress. Read-only, fetched through Jetpack\'s signed client. Starting a restore is not available through this plugin.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum rows, 1 to 50. Defaults to 10.' ),
					),
				),
			),
			array(
				'name'        => 'jetpack_request_backup',
				'description' => 'Queue an on-demand Jetpack VaultPress Backup on WordPress.com. Needs an active Backup plan and a calling user connected to WordPress.com. Each confirmed call queues a new backup job, so this is a two-part confirmation: call without confirm to get a preview that queues nothing; call with confirm=true AND confirm_backup=true to queue it. Refused while the latest backup is still running. Track it with jetpack_list_backups.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'confirm'        => array( 'type' => 'boolean', 'description' => 'Must be true to queue the backup. Omit or send false to get a preview instead. The preview is the intended first call.' ),
						'confirm_backup' => array( 'type' => 'boolean', 'description' => 'Must also be true, alongside confirm=true. A second flag so a backup is never queued by one unconsidered call.' ),
					),
				),
			),

			array(
				'name'        => 'jetpack_get_monitor_status',
				'description' => 'Jetpack Downtime Monitor status: whether the calling user receives downtime emails and when the site\'s up/down status last changed (UTC). Asks WordPress.com over Jetpack\'s signed XML-RPC client; the notification state needs the calling user to be connected to WordPress.com. Read-only. Needs the Monitor module active.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_set_notifications',
				'description' => 'Turn Jetpack Downtime Monitor email notifications on or off for the calling user only (their own inbox, not other users). Sent to WordPress.com over Jetpack\'s signed XML-RPC client. Idempotent: returns changed=false when already in that state. Needs the Monitor module active and the calling user connected to WordPress.com.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'enabled' => array( 'type' => 'boolean', 'description' => 'true to receive downtime emails, false to stop them.' ),
					),
					'required'   => array( 'enabled' ),
				),
			),

			array(
				'name'        => 'jetpack_get_newsletter_settings',
				'description' => 'Read the Jetpack Newsletter settings: the subscribe checkbox at the end of posts, the follow-comments checkbox, the admin email on new subscribers, the reply-to mode, and the sender name. Also says whether the Subscriptions module is active. Read-only and local.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_get_subscriber_stats',
				'description' => 'Jetpack Newsletter subscriber counts: all, email, and paid. Counts only, never subscriber identities. Fetched from WordPress.com through Jetpack\'s signed client and cached for one hour (the same cache Jetpack uses). Read-only. Requires a connected site.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_update_newsletter_settings',
				'description' => 'Update one or more Jetpack Newsletter settings; omitted fields are left alone. This changes what every visitor sees on posts and comment forms and how newsletter emails are addressed to every subscriber. Fields already at the requested value are not rewritten. Returns the settings read back after the write, the changed field names, and their previous values. Needs the Subscriptions module active.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'subscribe_post_end_enabled' => array( 'type' => 'boolean', 'description' => 'Show a "subscribe to blog" checkbox at the end of every post.' ),
						'subscribe_comments_enabled' => array( 'type' => 'boolean', 'description' => 'Show a "notify me of new comments" checkbox in the comment form.' ),
						'notify_admin_on_subscribe'  => array( 'type' => 'boolean', 'description' => 'Email the site admin when someone subscribes.' ),
						'reply_to'                   => array( 'type' => 'string', 'enum' => self::REPLY_TO_VALUES, 'description' => 'Reply-to for newsletter emails: comment (replies become comments), author (the post author), or no-reply.' ),
						'from_name'                  => array( 'type' => 'string', 'description' => 'Sender name on newsletter emails, up to 200 characters. Empty falls back to the site name.' ),
					),
				),
			),

			array(
				'name'        => 'jetpack_get_sitemap_status',
				'description' => 'Jetpack Sitemaps status: whether the module is active, the public sitemap.xml URL, the child sitemaps listed in the generated index with their last-modified dates, published post and page counts, whether a build is running, and when the next build is scheduled. Read-only and local.',
				'inputSchema' => $empty,
			),
			array(
				'name'        => 'jetpack_request_sitemap_rebuild',
				'description' => 'Schedule a Jetpack sitemap build to run on the next WP-Cron tick by queueing Jetpack\'s own jp_sitemap_cron_hook event. Nothing is deleted: the current sitemap stays live while it is rebuilt. Not queued when a build is already running or one is already due within ten minutes; the response says which. Needs the Sitemaps module active.',
				'inputSchema' => $empty,
			),

			array(
				'name'        => 'jetpack_get_shortlinks',
				'description' => 'Get wp.me shortlinks for up to 100 posts, and optionally for the site homepage. Posts that do not exist, that the caller cannot read, or whose type has no shortlinks are left out. Read-only and local. Needs the WP.me Shortlinks module active and a connected site.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_ids'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Up to 100 post IDs. Order is kept.' ),
						'include_blog' => array( 'type' => 'boolean', 'description' => 'Also return the homepage shortlink (post_id 0, post_type "blog").' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		$args = is_array( $args ) ? $args : array();

		

		
		$cap = 'jetpack_get_shortlinks' === $name ? 'edit_posts' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			throw new \Exception( 'You do not have permission to use Jetpack tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Jetpack is not active.' );
		}

		switch ( $name ) {
			case 'jetpack_get_connection_status':
				return self::get_connection_status();
			case 'jetpack_get_modules':
				return self::get_modules( $args );
			case 'jetpack_set_module_status':
				return self::set_module_status( $args );
			case 'jetpack_get_site_overview':
				return self::get_site_overview();
			case 'jetpack_get_visits':
				return self::get_visits( $args );
			case 'jetpack_get_top_content':
				return self::get_top_content( $args );
			case 'jetpack_get_post_views':
				return self::get_post_views( $args );
			case 'jetpack_stats_get_settings':
				return self::get_stats_settings();
			case 'jetpack_get_backup_overview':
				return self::get_backup_overview();
			case 'jetpack_list_backups':
				return self::list_backups( $args );
			case 'jetpack_list_restores':
				return self::list_restores( $args );
			case 'jetpack_request_backup':
				return self::request_backup( $args );
			case 'jetpack_get_monitor_status':
				return self::get_monitor_status();
			case 'jetpack_set_notifications':
				return self::set_notifications( $args );
			case 'jetpack_get_newsletter_settings':
				return self::get_newsletter_settings();
			case 'jetpack_get_subscriber_stats':
				return self::get_subscriber_stats();
			case 'jetpack_update_newsletter_settings':
				return self::update_newsletter_settings( $args );
			case 'jetpack_get_sitemap_status':
				return self::get_sitemap_status();
			case 'jetpack_request_sitemap_rebuild':
				return self::request_sitemap_rebuild();
			case 'jetpack_get_shortlinks':
				return self::get_shortlinks( $args );
			default:
				throw new \Exception( 'Unknown Jetpack tool: ' . esc_html( $name ) );
		}
	}

	private static function get_connection_status() {
		$manager = self::connection_manager();
		$owner   = (int) \Jetpack_Options::get_option( 'master_user' );
		$blog_id = (int) \Jetpack_Options::get_option( 'id' );

		$offline = null;
		if ( class_exists( '\Automattic\Jetpack\Status' ) && method_exists( '\Automattic\Jetpack\Status', 'is_offline_mode' ) ) {
			$offline = (bool) ( new \Automattic\Jetpack\Status() )->is_offline_mode();
		}

		return array(
			'provider'               => 'jetpack',
			'site_registered'        => $manager ? (bool) $manager->is_connected() : $blog_id > 0,
			'connection_ready'       => method_exists( '\Jetpack', 'is_connection_ready' ) ? (bool) \Jetpack::is_connection_ready() : null,
			'any_user_connected'     => $manager ? (bool) $manager->has_connected_user() : null,
			'has_connection_owner'   => $manager ? (bool) $manager->has_connected_owner() : null,
			'current_user_connected' => $manager ? (bool) $manager->is_user_connected() : null,
			'connection_owner_id'    => $owner > 0 ? $owner : null,
			'blog_id'                => $blog_id > 0 ? $blog_id : null,
			'offline_mode'           => $offline,
			'jetpack_version'        => defined( 'JETPACK__VERSION' ) ? (string) constant( 'JETPACK__VERSION' ) : null,
		);
	}

	private static function get_modules( $args ) {
		self::require_methods( array( 'get_active_modules', 'get_available_modules', 'get_module', 'is_module' ) );

		$active_map = array_flip( (array) \Jetpack::get_active_modules() );

		$slug = isset( $args['slug'] ) && is_string( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
		if ( '' !== $slug ) {
			$candidates = \Jetpack::is_module( $slug ) ? array( $slug ) : array();
		} else {
			$candidates = (array) \Jetpack::get_available_modules();
		}

		$active_filter = null;
		if ( array_key_exists( 'active', $args ) && null !== $args['active'] ) {
			$active_filter = self::to_bool( $args['active'] );
		}
		$feature = isset( $args['feature'] ) && is_string( $args['feature'] ) && '' !== $args['feature'] ? strtolower( $args['feature'] ) : null;
		$search  = isset( $args['search'] ) && is_string( $args['search'] ) && '' !== $args['search'] ? strtolower( $args['search'] ) : null;

		$out = array();
		foreach ( $candidates as $candidate ) {
			$row = self::summarize_module( (string) $candidate, isset( $active_map[ $candidate ] ) );
			if ( null === $row ) {
				continue;
			}
			if ( null !== $active_filter && $row['active'] !== $active_filter ) {
				continue;
			}
			if ( null !== $feature && ! in_array( $feature, array_map( 'strtolower', $row['feature'] ), true ) ) {
				continue;
			}
			if ( null !== $search && false === strpos( strtolower( $row['slug'] . ' ' . $row['name'] . ' ' . $row['description'] ), $search ) ) {
				continue;
			}
			$out[] = $row;
		}

		usort(
			$out,
			static function ( $a, $b ) {
				if ( $a['sort'] === $b['sort'] ) {
					return strcmp( $a['slug'], $b['slug'] );
				}
				return $a['sort'] < $b['sort'] ? -1 : 1;
			}
		);

		return array(
			'provider' => 'jetpack',
			'total'    => count( $out ),
			'modules'  => $out,
		);
	}

	private static function summarize_module( $slug, $is_active ) {
		$mod = \Jetpack::get_module( $slug );
		if ( ! is_array( $mod ) ) {
			return null;
		}

		$i18n = function_exists( 'jetpack_get_module_i18n' ) ? jetpack_get_module_i18n( $slug ) : array();
		$i18n = is_array( $i18n ) ? $i18n : array();

		return array(
			'slug'                     => $slug,
			'name'                     => isset( $i18n['name'] ) ? (string) $i18n['name'] : ( isset( $mod['name'] ) ? (string) $mod['name'] : $slug ),
			'description'              => isset( $i18n['description'] ) ? (string) $i18n['description'] : ( isset( $mod['description'] ) ? (string) $mod['description'] : '' ),
			'active'                   => (bool) $is_active,
			'sort'                     => isset( $mod['sort'] ) ? (int) $mod['sort'] : 10,
			'feature'                  => isset( $mod['feature'] ) && is_array( $mod['feature'] ) ? array_values( array_map( 'strval', $mod['feature'] ) ) : array(),
			'plan_classes'             => isset( $mod['plan_classes'] ) && is_array( $mod['plan_classes'] ) ? array_values( array_map( 'strval', $mod['plan_classes'] ) ) : array(),
			'requires_connection'      => ! empty( $mod['requires_connection'] ),
			'requires_user_connection' => ! empty( $mod['requires_user_connection'] ),
			'auto_activate'            => isset( $mod['auto_activate'] ) ? (string) $mod['auto_activate'] : 'No',
		);
	}

	private static function set_module_status( $args ) {
		self::require_methods( array( 'is_module', 'is_module_active', 'activate_module', 'deactivate_module', 'get_module' ) );

		$slug = isset( $args['slug'] ) && is_string( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
		if ( '' === $slug ) {
			throw new \Exception( 'A module slug is required. Call jetpack_get_modules for valid slugs.' );
		}
		if ( ! array_key_exists( 'active', $args ) || null === self::to_bool( $args['active'] ) ) {
			throw new \Exception( 'active must be true or false.' );
		}
		$desired = self::to_bool( $args['active'] );

		if ( ! \Jetpack::is_module( $slug ) ) {
			throw new \Exception( 'Unknown Jetpack module: ' . esc_html( $slug ) . '. Call jetpack_get_modules for valid slugs.' );
		}

		
		
		$direction_cap = $desired ? 'jetpack_activate_modules' : 'jetpack_deactivate_modules';
		if ( ! current_user_can( 'jetpack_manage_modules' ) || ! current_user_can( $direction_cap ) ) {
			throw new \Exception( 'You do not have permission to change Jetpack modules.' );
		}

		$current = (bool) \Jetpack::is_module_active( $slug );
		$summary = self::summarize_module( $slug, $current );

		

		$conflict = $desired && ! $current ? self::find_conflicting_plugin( $slug ) : null;

		$confirmed = true === self::to_bool( $args['confirm'] ?? false )
			&& isset( $args['confirm_slug'] ) && is_string( $args['confirm_slug'] )
			&& sanitize_key( $args['confirm_slug'] ) === $slug;

		if ( ! $confirmed ) {
			return array(
				'preview'                  => true,
				'provider'                 => 'jetpack',
				'slug'                     => $slug,
				'name'                     => $summary ? $summary['name'] : $slug,
				'currently_active'         => $current,
				'requested_active'         => $desired,
				'would_change'             => $desired !== $current,
				'requires_connection'      => $summary ? $summary['requires_connection'] : null,
				'requires_user_connection' => $summary ? $summary['requires_user_connection'] : null,
				'plan_classes'             => $summary ? $summary['plan_classes'] : array(),
				'conflicting_plugin'       => $conflict,
				'message'                  => $desired === $current
					? 'Nothing to change: the module is already ' . ( $current ? 'active' : 'inactive' ) . '.'
					: 'Preview only. To apply, call again with confirm=true and confirm_slug=' . $slug . '.',
			);
		}

		if ( $desired === $current ) {
			return array(
				'provider' => 'jetpack',
				'slug'     => $slug,
				'active'   => $current,
				'changed'  => false,
			);
		}

		if ( null !== $conflict ) {
			throw new \Exception( 'Cannot activate the module while the conflicting plugin "' . esc_html( $conflict ) . '" is active. Deactivate it first, then retry.' );
		}

		if ( $desired ) {
			
			$ok = \Jetpack::activate_module( $slug, false, false );
			if ( ! $ok ) {
				throw new \Exception( 'Jetpack did not activate the module. It may need a WordPress.com connection or a plan that includes it; check requires_connection and plan_classes in jetpack_get_modules.' );
			}
		} else {
			$ok = \Jetpack::deactivate_module( $slug );
			if ( ! $ok ) {
				throw new \Exception( 'Jetpack did not deactivate the module.' );
			}
		}

		
		$actual = (bool) \Jetpack::is_module_active( $slug );
		if ( $actual !== $desired ) {
			throw new \Exception( 'The module did not reach the requested state. A filter on jetpack_active_modules may be forcing it.' );
		}

		return array(
			'provider' => 'jetpack',
			'slug'     => $slug,
			'active'   => $actual,
			'changed'  => true,
		);
	}

	private static function find_conflicting_plugin( $slug ) {
		if ( ! method_exists( '\Jetpack', 'init' ) ) {
			return null;
		}
		$jetpack = \Jetpack::init();
		if ( ! is_object( $jetpack ) || empty( $jetpack->plugins_to_deactivate[ $slug ] ) || ! is_array( $jetpack->plugins_to_deactivate[ $slug ] ) ) {
			return null;
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = null;
		foreach ( $jetpack->plugins_to_deactivate[ $slug ] as $candidate ) {
			if ( ! is_array( $candidate ) || count( $candidate ) < 2 ) {
				continue;
			}
			list( $plugin_file, $plugin_name ) = $candidate;
			if ( is_plugin_active( $plugin_file ) ) {
				return (string) $plugin_name;
			}
			if ( null === $active_plugins ) {
				$active_plugins = method_exists( '\Jetpack', 'get_active_plugins' ) ? (array) \Jetpack::get_active_plugins() : array();
			}
			foreach ( $active_plugins as $active ) {
				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $active, false, false );
				if ( isset( $data['Name'] ) && $data['Name'] === $plugin_name ) {
					return (string) $plugin_name;
				}
			}
		}
		return null;
	}

	private static function get_site_overview() {
		$stats = self::wpcom_stats( array( 'get_stats_summary', 'get_highlights', 'get_streak' ) );

		$calls  = array(
			'summary'    => $stats->get_stats_summary(),
			'highlights' => $stats->get_highlights(),
			'streak'     => $stats->get_streak(),
		);
		$values = array();
		$errors = array();
		foreach ( $calls as $tag => $result ) {
			if ( is_wp_error( $result ) || ! is_array( $result ) ) {
				$errors[]       = $tag;
				$values[ $tag ] = array();
			} else {
				$values[ $tag ] = $result;
			}
		}
		if ( count( $errors ) === count( $calls ) ) {
			throw new \Exception( 'Jetpack Stats could not be read from WordPress.com. ' . esc_html( self::error_text( $calls['summary'] ) ) );
		}

		$summary  = $values['summary'];
		$today    = isset( $values['highlights']['today'] ) && is_array( $values['highlights']['today'] ) ? $values['highlights']['today'] : array();
		$top_post = isset( $today['top_post'] ) && is_array( $today['top_post'] ) ? $today['top_post'] : null;
		$streak   = isset( $values['streak']['streak'] ) && is_array( $values['streak']['streak'] ) ? $values['streak']['streak'] : array();
		$refs     = isset( $today['top_referrers'] ) && is_array( $today['top_referrers'] ) ? $today['top_referrers'] : array();
		$ref      = isset( $refs[0] ) && is_array( $refs[0] ) ? $refs[0] : null;

		$date = '';
		foreach ( array( $summary, $today ) as $source ) {
			if ( isset( $source['date'] ) && '' !== $source['date'] ) {
				$date = (string) $source['date'];
				break;
			}
		}

		$out = array(
			'provider'            => 'jetpack',
			'stats_module_active' => (bool) \Jetpack::is_module_active( self::MODULE_STATS ),
			'date'                => $date,
			'views_today'         => isset( $summary['views'] ) ? (int) $summary['views'] : 0,
			'visitors_today'      => isset( $summary['visitors'] ) ? (int) $summary['visitors'] : 0,
			'views_week'          => isset( $summary['period_total_views'] ) ? (int) $summary['period_total_views'] : 0,
			'views_month'         => isset( $today['views_month'] ) ? (int) $today['views_month'] : 0,
			'streak'              => array(
				'current_length' => isset( $streak['currentStreakLength'] ) ? (int) $streak['currentStreakLength'] : 0,
				'longest_length' => isset( $streak['longestStreakLength'] ) ? (int) $streak['longestStreakLength'] : 0,
				'longest_start'  => isset( $streak['longestStreakStart'] ) ? (string) $streak['longestStreakStart'] : '',
				'longest_end'    => isset( $streak['longestStreakEnd'] ) ? (string) $streak['longestStreakEnd'] : '',
			),
			'top_post'            => null === $top_post ? null : array(
				'id'    => isset( $top_post['id'] ) ? (int) $top_post['id'] : 0,
				'title' => isset( $top_post['title'] ) ? (string) $top_post['title'] : '',
				'views' => isset( $top_post['views'] ) ? (int) $top_post['views'] : 0,
			),
			'top_referrer'        => null === $ref ? null : array(
				'name'  => isset( $ref['name'] ) ? (string) $ref['name'] : '',
				'views' => isset( $ref['views'] ) ? (int) $ref['views'] : 0,
			),
			'partial'             => ! empty( $errors ),
		);
		if ( $errors ) {
			$out['errors'] = $errors;
		}
		return $out;
	}

	private static function get_visits( $args ) {
		$unit     = self::pick_period( $args['unit'] ?? null );
		$quantity = self::clamp_int( $args['quantity'] ?? 30, 1, 90, 30 );
		$date     = self::sanitize_date( $args['date'] ?? null );

		$fields = isset( $args['fields'] ) && is_array( $args['fields'] )
			? array_values( array_unique( array_intersect( array_map( 'strval', $args['fields'] ), self::VISIT_FIELDS ) ) )
			: array();
		if ( ! $fields ) {
			$fields = array( 'views', 'visitors' );
		}

		$raw = self::wpcom_stats( array( 'get_visits' ) )->get_visits(
			array(
				'unit'        => $unit,
				'quantity'    => $quantity,
				'date'        => $date,
				'stat_fields' => implode( ',', $fields ),
			)
		);
		$raw = self::stats_result( $raw );

		
		$columns = array();
		if ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			foreach ( $raw['fields'] as $idx => $field_name ) {
				$columns[ (string) $field_name ] = $idx;
			}
		}
		$date_idx = $columns['period'] ?? 0;

		$series = array();
		$rows   = isset( $raw['data'] ) && is_array( $raw['data'] ) ? $raw['data'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$entry = array( 'date' => isset( $row[ $date_idx ] ) ? (string) $row[ $date_idx ] : '' );
			foreach ( $fields as $field ) {
				$idx             = $columns[ $field ] ?? null;
				$entry[ $field ] = ( null !== $idx && isset( $row[ $idx ] ) ) ? (int) $row[ $idx ] : 0;
			}
			$series[] = $entry;
		}

		return array(
			'provider' => 'jetpack',
			'unit'     => $unit,
			'quantity' => $quantity,
			'date'     => $date,
			'fields'   => $fields,
			'series'   => $series,
		);
	}

	private static function get_top_content( $args ) {
		$type = isset( $args['type'] ) && is_string( $args['type'] ) ? $args['type'] : '';
		if ( ! in_array( $type, self::TOP_CONTENT_TYPES, true ) ) {
			throw new \Exception( 'type is required and must be one of: ' . esc_html( implode( ', ', self::TOP_CONTENT_TYPES ) ) . '.' );
		}

		$period = self::pick_period( $args['period'] ?? null );
		$date   = self::sanitize_date( $args['date'] ?? null );
		$num    = self::clamp_int( $args['num'] ?? 1, 1, 90, 1 );
		$max    = self::clamp_int( $args['max'] ?? 20, 1, 100, 20 );
		$query  = array( 'period' => $period, 'date' => $date, 'num' => $num, 'max' => $max );

		$methods = array(
			'posts'        => 'get_top_posts',
			'referrers'    => 'get_referrers',
			'search-terms' => 'get_search_terms',
			'clicks'       => 'get_clicks',
			'tags'         => 'get_tags',
			'authors'      => 'get_top_authors',
			'countries'    => 'get_views_by_country',
			'downloads'    => 'get_file_downloads',
			'video-plays'  => 'get_video_plays',
		);
		$method = $methods[ $type ];
		$stats  = self::wpcom_stats( array( $method ) );
		
		$raw = self::stats_result( 'tags' === $type ? $stats->get_tags( array( 'max' => $max ) ) : $stats->$method( $query ) );

		return array(
			'provider' => 'jetpack',
			'type'     => $type,
			'period'   => $period,
			'date'     => $date,
			'num'      => $num,
			'max'      => $max,
			'items'    => self::normalize_top_items( $type, $raw, $max ),
		);
	}

	private static function normalize_top_items( $type, array $raw, $max ) {
		$rows = array();

		if ( 'tags' === $type ) {
			$tags = isset( $raw['tags'] ) && is_array( $raw['tags'] ) ? $raw['tags'] : array();
			foreach ( $tags as $tag ) {
				if ( is_array( $tag ) ) {
					$rows[] = array(
						'label' => isset( $tag['tag'] ) && is_scalar( $tag['tag'] ) ? (string) $tag['tag'] : '',
						'value' => isset( $tag['views'] ) ? (int) $tag['views'] : 0,
					);
				}
			}
			return self::rank_rows( $rows, $max );
		}

		$day = array();
		if ( isset( $raw['days'] ) && is_array( $raw['days'] ) && $raw['days'] ) {
			$first = reset( $raw['days'] );
			$day   = is_array( $first ) ? $first : array();
		}

		if ( 'countries' === $type ) {
			$info = isset( $raw['country-info'] ) && is_array( $raw['country-info'] ) ? $raw['country-info'] : array();
			$list = isset( $day['views'] ) && is_array( $day['views'] ) ? $day['views'] : array();
			foreach ( $list as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$code   = isset( $row['country_code'] ) ? (string) $row['country_code'] : '';
				$rows[] = array(
					'label' => isset( $info[ $code ]['country_full'] ) ? (string) $info[ $code ]['country_full'] : $code,
					'value' => isset( $row['views'] ) ? (int) $row['views'] : 0,
				);
			}
			return self::rank_rows( $rows, $max );
		}

		$map  = self::TOP_CONTENT_MAP[ $type ];
		$list = isset( $day[ $map['list'] ] ) && is_array( $day[ $map['list'] ] ) ? $day[ $map['list'] ] : array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row[ $map['label'] ] ) && is_scalar( $row[ $map['label'] ] ) ? (string) $row[ $map['label'] ] : '';
			if ( '' === $label && isset( $map['label_fallback'], $row[ $map['label_fallback'] ] ) && is_scalar( $row[ $map['label_fallback'] ] ) ) {
				$label = (string) $row[ $map['label_fallback'] ];
			}
			$entry = array(
				'label' => $label,
				'value' => isset( $row[ $map['value'] ] ) ? (int) $row[ $map['value'] ] : 0,
			);
			if ( isset( $map['href'], $row[ $map['href'] ] ) && is_string( $row[ $map['href'] ] ) && '' !== $row[ $map['href'] ] ) {
				$entry['href'] = $row[ $map['href'] ];
			}
			$rows[] = $entry;
		}
		return self::rank_rows( $rows, $max );
	}

	private static function rank_rows( array $rows, $max ) {
		$out = array();
		foreach ( array_slice( $rows, 0, $max ) as $i => $row ) {
			$out[] = array_merge( array( 'rank' => $i + 1 ), $row );
		}
		return $out;
	}

	private static function get_post_views( $args ) {
		$post_id = isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'A positive post_id is required.' );
		}
		$period = self::pick_period( $args['period'] ?? null );
		$num    = self::clamp_int( $args['num'] ?? 30, 1, 90, 30 );
		$date   = self::sanitize_date( $args['date'] ?? null );

		$raw = self::stats_result(
			self::wpcom_stats( array( 'get_post_views' ) )->get_post_views(
				$post_id,
				array( 'period' => $period, 'num' => $num, 'date' => $date )
			)
		);

		$series = array();
		$fields = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array( 'period', 'views' );
		$d_idx  = array_search( 'period', $fields, true );
		$v_idx  = array_search( 'views', $fields, true );

		if ( false !== $d_idx && false !== $v_idx && isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
			foreach ( $raw['data'] as $row ) {
				if ( is_array( $row ) ) {
					$series[] = array(
						'date'  => isset( $row[ $d_idx ] ) ? (string) $row[ $d_idx ] : '',
						'views' => isset( $row[ $v_idx ] ) ? (int) $row[ $v_idx ] : 0,
					);
				}
			}
		}

		return array(
			'provider'    => 'jetpack',
			'post_id'     => $post_id,
			'total_views' => isset( $raw['views'] ) ? (int) $raw['views'] : 0,
			'period'      => $period,
			'num'         => $num,
			'date'        => $date,
			'series'      => $series,
		);
	}

	private static function get_stats_settings() {
		$keys = array( 'admin_bar', 'roles', 'count_roles', 'do_not_track' );

		if ( class_exists( '\Automattic\Jetpack\Stats\Settings' ) && method_exists( '\Automattic\Jetpack\Stats\Settings', 'get' ) ) {
			$settings = \Automattic\Jetpack\Stats\Settings::get( $keys );
		} elseif ( class_exists( '\Automattic\Jetpack\Stats\Options' ) && method_exists( '\Automattic\Jetpack\Stats\Options', 'get_options' ) ) {
			
			$options  = (array) \Automattic\Jetpack\Stats\Options::get_options();
			$settings = array(
				'admin_bar'    => ! empty( $options['admin_bar'] ),
				'roles'        => isset( $options['roles'] ) && is_array( $options['roles'] ) ? array_values( $options['roles'] ) : array(),
				'count_roles'  => isset( $options['count_roles'] ) && is_array( $options['count_roles'] ) ? array_values( $options['count_roles'] ) : array(),
				'do_not_track' => ! empty( $options['do_not_track'] ),
			);
		} else {
			throw new \Exception( 'Jetpack Stats settings are not available in this Jetpack version.' );
		}

		return array(
			'provider'            => 'jetpack',
			'stats_module_active' => (bool) \Jetpack::is_module_active( self::MODULE_STATS ),
			'admin_bar'           => ! empty( $settings['admin_bar'] ),
			'roles'               => isset( $settings['roles'] ) && is_array( $settings['roles'] ) ? array_values( array_map( 'strval', $settings['roles'] ) ) : array(),
			'count_roles'         => isset( $settings['count_roles'] ) && is_array( $settings['count_roles'] ) ? array_values( array_map( 'strval', $settings['count_roles'] ) ) : array(),
			'do_not_track'        => ! empty( $settings['do_not_track'] ),
		);
	}

	private static function get_backup_overview() {
		$state  = self::rewind_state();
		$recent = self::fetch_backup_rows();

		$last = null;
		if ( is_array( $recent ) && $recent ) {
			$last = $recent[0];
		}

		return array(
			'provider'      => 'jetpack',
			'backup_state'  => $state['state'],
			'state_reason'  => $state['reason'],
			'state_updated' => $state['last_updated'],
			'backup_active' => 'active' === $state['state'],
			'last_backup'   => $last,
			'recent_count'  => is_array( $recent ) ? count( $recent ) : null,
			'errors'        => array_values(
				array_filter(
					array(
						null === $state['state'] ? 'backup_state' : null,
						is_array( $recent ) ? null : 'recent_backups',
					)
				)
			),
		);
	}

	private static function list_backups( $args ) {
		$limit = self::clamp_int( $args['limit'] ?? 10, 1, self::MAX_BACKUP_ROWS, 10 );
		$rows  = self::fetch_backup_rows();
		if ( ! is_array( $rows ) ) {
			throw new \Exception( 'Jetpack VaultPress Backup records could not be read from WordPress.com. The site may have no Backup plan or no connection.' );
		}
		return array(
			'provider' => 'jetpack',
			'total'    => count( $rows ),
			'limit'    => $limit,
			'backups'  => array_slice( $rows, 0, $limit ),
		);
	}

	private static function list_restores( $args ) {
		$limit = self::clamp_int( $args['limit'] ?? 10, 1, self::MAX_BACKUP_ROWS, 10 );
		$data  = self::wpcom_get( '/sites/' . self::blog_id() . '/rewind/restores', 'v2' );
		if ( ! is_array( $data ) ) {
			throw new \Exception( 'Jetpack VaultPress Backup restores could not be read from WordPress.com. The site may have no Backup plan or no connection.' );
		}

		$rows = array();
		foreach ( $data as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			
			$rows[] = array(
				'id'           => isset( $raw['rewind_id'] ) && '' !== $raw['rewind_id'] && is_scalar( $raw['rewind_id'] ) ? (string) $raw['rewind_id'] : null,
				'started'      => isset( $raw['started'] ) && is_scalar( $raw['started'] ) ? (string) $raw['started'] : null,
				'last_updated' => isset( $raw['last_updated'] ) && is_scalar( $raw['last_updated'] ) ? (string) $raw['last_updated'] : null,
				'status'       => isset( $raw['status'] ) && is_scalar( $raw['status'] ) ? (string) $raw['status'] : null,
				'progress'     => isset( $raw['progress'] ) && is_numeric( $raw['progress'] ) ? (int) $raw['progress'] : null,
			);
		}

		return array(
			'provider' => 'jetpack',
			'total'    => count( $rows ),
			'limit'    => $limit,
			'restores' => array_slice( $rows, 0, $limit ),
		);
	}

	private static function request_backup( $args ) {
		$confirmed = true === self::to_bool( $args['confirm'] ?? false )
			&& true === self::to_bool( $args['confirm_backup'] ?? false );

		$state = self::rewind_state();

		if ( ! $confirmed ) {
			return array(
				'preview'      => true,
				'provider'     => 'jetpack',
				'backup_state' => $state['state'],
				'message'      => 'Preview only. To queue a backup, call again with confirm=true and confirm_backup=true. WordPress.com runs the backup in the background; each confirmed call queues a new job.',
			);
		}

		if ( 'active' !== $state['state'] ) {
			throw new \Exception( 'Jetpack VaultPress Backup is not active on this site (state: ' . esc_html( null === $state['state'] ? 'unknown' : $state['state'] ) . '). A Backup plan and a connected site are required.' );
		}

		$manager = self::connection_manager();
		if ( ! $manager || ! $manager->is_user_connected() ) {
			throw new \Exception( 'The calling user is not connected to WordPress.com. Jetpack queues backups with the user\'s own connection; connect this user in Jetpack first.' );
		}

		$recent = self::fetch_backup_rows();
		if ( is_array( $recent ) && isset( $recent[0]['status'] ) && 'started' === $recent[0]['status'] ) {
			throw new \Exception( 'A Jetpack backup is already running. Wait for it to finish before queueing another.' );
		}

		$client = '\Automattic\Jetpack\Connection\Client';
		if ( ! class_exists( $client ) || ! method_exists( $client, 'wpcom_json_api_request_as_user' ) ) {
			throw new \Exception( 'The Jetpack connection client is not available in this version.' );
		}
		$response = $client::wpcom_json_api_request_as_user(
			'/sites/' . self::blog_id() . '/rewind/backups/enqueue',
			'v2',
			array( 'method' => 'POST' ),
			null,
			'wpcom'
		);
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WordPress.com did not accept the backup request: ' . esc_html( $response->get_error_message() ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $body ) || empty( $body['success'] ) ) {
			throw new \Exception( 'WordPress.com did not accept the backup request. Retry shortly.' );
		}

		return array(
			'success'  => true,
			'provider' => 'jetpack',
			'message'  => 'Backup queued on WordPress.com. Track it with jetpack_list_backups.',
		);
	}

	private static function rewind_state() {
		$out = array( 'state' => null, 'reason' => null, 'last_updated' => null );
		if ( ! class_exists( 'Jetpack_Core_Json_Api_Endpoints' ) || ! method_exists( 'Jetpack_Core_Json_Api_Endpoints', 'rewind_data' ) ) {
			return $out;
		}
		$data = \Jetpack_Core_Json_Api_Endpoints::rewind_data();
		if ( is_wp_error( $data ) || ( ! is_object( $data ) && ! is_array( $data ) ) ) {
			return $out;
		}
		$data = (array) $data;
		foreach ( array_keys( $out ) as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== (string) $data[ $key ] ) {
				$out[ $key ] = (string) $data[ $key ];
			}
		}
		return $out;
	}

	private static function fetch_backup_rows() {
		$data = self::wpcom_get( '/sites/' . self::blog_id() . '/rewind/backups', 'v2' );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$rows = array();
		foreach ( $data as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			if ( array_key_exists( 'is_backup', $raw ) && empty( $raw['is_backup'] ) ) {
				continue;
			}
			$rows[] = array(
				'id'            => isset( $raw['rewind_id'] ) && '' !== $raw['rewind_id'] && is_scalar( $raw['rewind_id'] ) ? (string) $raw['rewind_id'] : null,
				'started'       => isset( $raw['started'] ) && is_scalar( $raw['started'] ) ? (string) $raw['started'] : null,
				'last_updated'  => isset( $raw['last_updated'] ) && is_scalar( $raw['last_updated'] ) ? (string) $raw['last_updated'] : null,
				'status'        => isset( $raw['status'] ) && is_scalar( $raw['status'] ) ? (string) $raw['status'] : null,
				'is_rewindable' => isset( $raw['is_rewindable'] ) ? (bool) $raw['is_rewindable'] : null,
				'has_warnings'  => isset( $raw['has_warnings'] ) ? (bool) $raw['has_warnings'] : null,
			);
		}
		return $rows;
	}

	private static function get_monitor_status() {
		self::require_module( self::MODULE_MONITOR, 'Monitor' );
		self::require_ixr();

		$manager        = self::connection_manager();
		$user_connected = $manager ? (bool) $manager->is_user_connected() : false;

		$notifications = null;
		$errors        = array();
		if ( $user_connected ) {
			$state = self::monitor_notifications_state();
			if ( is_wp_error( $state ) ) {
				$errors[] = 'notifications';
			} else {
				$notifications = $state;
			}
		}

		$last_change = self::monitor_last_status_change();
		if ( is_wp_error( $last_change ) ) {
			$errors[]    = 'last_status_change';
			$last_change = null;
		}

		if ( count( $errors ) === ( $user_connected ? 2 : 1 ) ) {
			throw new \Exception( 'The Jetpack Monitor service on WordPress.com could not be reached. Retry shortly.' );
		}

		$out = array(
			'provider'              => 'jetpack',
			'module_active'         => true,
			'user_connected'        => $user_connected,
			'notifications_enabled' => $notifications,
			'last_status_change'    => $last_change,
		);
		if ( ! $user_connected ) {
			$out['note'] = 'The calling user is not connected to WordPress.com, so their notification setting cannot be read.';
		}
		if ( $errors ) {
			$out['partial'] = true;
			$out['errors']  = $errors;
		}
		return $out;
	}

	private static function set_notifications( $args ) {
		if ( ! array_key_exists( 'enabled', $args ) || null === self::to_bool( $args['enabled'] ) ) {
			throw new \Exception( 'enabled must be true or false.' );
		}
		$desired = self::to_bool( $args['enabled'] );

		self::require_module( self::MODULE_MONITOR, 'Monitor' );
		self::require_ixr();

		$manager = self::connection_manager();
		if ( ! $manager || ! $manager->is_user_connected() ) {
			throw new \Exception( 'The calling user is not connected to WordPress.com. Monitor notifications are a per-user WordPress.com setting; connect this user in Jetpack first.' );
		}

		$current = self::monitor_notifications_state();
		if ( is_wp_error( $current ) ) {
			throw new \Exception( 'The Jetpack Monitor service on WordPress.com could not be reached: ' . esc_html( $current->get_error_message() ) );
		}

		if ( $current !== $desired ) {
			$xml = new \Jetpack_IXR_Client( array( 'user_id' => get_current_user_id() ) );
			$xml->query( 'jetpack.monitor.setNotifications', $desired );
			if ( $xml->isError() ) {
				throw new \Exception( 'WordPress.com did not apply the notification change: ' . esc_html( (string) $xml->getErrorMessage() ) );
			}
		}

		
		update_option( self::MONITOR_NOTIFY_OPT, $desired );

		return array(
			'provider' => 'jetpack',
			'enabled'  => $desired,
			'changed'  => $current !== $desired,
		);
	}

	private static function monitor_notifications_state() {
		$xml = new \Jetpack_IXR_Client( array( 'user_id' => get_current_user_id() ) );
		$xml->query( 'jetpack.monitor.isUserInNotifications' );
		if ( $xml->isError() ) {
			return new \WP_Error( 'jetpack_monitor_unreachable', (string) $xml->getErrorMessage() );
		}
		return (bool) $xml->getResponse();
	}

	private static function monitor_last_status_change() {
		$cached = get_transient( self::MONITOR_DOWNTIME );
		if ( false === $cached ) {
			$xml = new \Jetpack_IXR_Client();
			$xml->query( 'jetpack.monitor.getLastDowntime' );
			if ( $xml->isError() ) {
				return new \WP_Error( 'jetpack_monitor_unreachable', (string) $xml->getErrorMessage() );
			}
			$cached = $xml->getResponse();
			set_transient( self::MONITOR_DOWNTIME, $cached, 10 * 60 );
		}

		if ( ! is_string( $cached ) ) {
			return null;
		}
		$value = trim( $cached );
		if ( '' === $value || 0 === strncmp( $value, '0000-00-00', 10 ) ) {
			return null;
		}
		$ts = strtotime( $value );
		return ( false === $ts || $ts <= 0 ) ? null : $value;
	}

	
	private static function newsletter_map() {
		return array(
			'subscribe_post_end_enabled' => array( 'option' => 'stb_enabled', 'type' => 'bool', 'default' => 1 ),
			'subscribe_comments_enabled' => array( 'option' => 'stc_enabled', 'type' => 'bool', 'default' => 1 ),
			'notify_admin_on_subscribe'  => array( 'option' => 'social_notifications_subscribe', 'type' => 'on_off', 'default' => 'on' ),
			'reply_to'                   => array( 'option' => 'jetpack_subscriptions_reply_to', 'type' => 'enum', 'default' => 'comment' ),
			'from_name'                  => array( 'option' => 'jetpack_subscriptions_from_name', 'type' => 'string', 'default' => '' ),
		);
	}

	private static function newsletter_values() {
		$out = array();
		foreach ( self::newsletter_map() as $field => $config ) {
			$out[ $field ] = self::newsletter_cast( $config, get_option( $config['option'], $config['default'] ) );
		}
		return $out;
	}

	private static function newsletter_cast( array $config, $value ) {
		switch ( $config['type'] ) {
			case 'bool':
				return 1 === (int) $value;
			case 'on_off':
				return 'on' === (string) $value;
			case 'enum':
				return in_array( (string) $value, self::REPLY_TO_VALUES, true ) ? (string) $value : (string) $config['default'];
			default:
				return is_scalar( $value ) ? (string) $value : '';
		}
	}

	private static function get_newsletter_settings() {
		return array_merge(
			array(
				'provider'      => 'jetpack',
				'module_active' => (bool) \Jetpack::is_module_active( self::MODULE_SUBSCRIPTIONS ),
			),
			self::newsletter_values()
		);
	}

	private static function update_newsletter_settings( $args ) {
		self::require_module( self::MODULE_SUBSCRIPTIONS, 'Subscriptions (Newsletter)' );

		
		$map        = self::newsletter_map();
		$normalized = array();
		foreach ( $map as $field => $config ) {
			if ( ! array_key_exists( $field, $args ) || null === $args[ $field ] ) {
				continue;
			}
			$value = $args[ $field ];
			switch ( $config['type'] ) {
				case 'bool':
				case 'on_off':
					$bool = self::to_bool( $value );
					if ( null === $bool ) {
						throw new \Exception( esc_html( $field ) . ' must be true or false.' );
					}
					$normalized[ $field ] = 'bool' === $config['type'] ? ( $bool ? 1 : 0 ) : ( $bool ? 'on' : 'off' );
					break;
				case 'enum':
					if ( ! is_string( $value ) || ! in_array( $value, self::REPLY_TO_VALUES, true ) ) {
						throw new \Exception( 'reply_to must be one of: ' . esc_html( implode( ', ', self::REPLY_TO_VALUES ) ) . '.' );
					}
					$normalized[ $field ] = $value;
					break;
				default:
					if ( ! is_string( $value ) ) {
						throw new \Exception( 'from_name must be a string.' );
					}
					$clean = sanitize_text_field( $value );
					$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $clean ) : strlen( $clean );
					if ( $len > 200 ) {
						throw new \Exception( 'from_name must be 200 characters or fewer.' );
					}
					$normalized[ $field ] = $clean;
			}
		}

		if ( ! $normalized ) {
			throw new \Exception( 'Provide at least one setting to change: ' . esc_html( implode( ', ', array_keys( $map ) ) ) . '.' );
		}

		$before   = self::newsletter_values();
		$changed  = array();
		$previous = array();
		foreach ( $normalized as $field => $stored ) {
			$current = get_option( $map[ $field ]['option'], $map[ $field ]['default'] );
			if ( (string) $current === (string) $stored ) {
				continue;
			}
			update_option( $map[ $field ]['option'], $stored );
			$changed[]          = $field;
			$previous[ $field ] = $before[ $field ];
		}

		
		$after      = self::newsletter_values();
		$mismatched = array();
		foreach ( $normalized as $field => $stored ) {
			if ( $after[ $field ] !== self::newsletter_cast( $map[ $field ], $stored ) ) {
				$mismatched[] = $field;
			}
		}

		$out = array(
			'provider' => 'jetpack',
			'changed'  => $changed,
			'previous' => (object) $previous,
			'settings' => $after,
		);
		if ( $mismatched ) {
			$out['not_saved_as_requested'] = $mismatched;
		}
		return $out;
	}

	private static function get_subscriber_stats() {
		$cached = get_transient( self::SUBSCRIBER_CACHE );
		if ( is_array( $cached ) && isset( $cached['all'], $cached['email'], $cached['paid'] ) ) {
			return array(
				'provider' => 'jetpack',
				'all'      => (int) $cached['all'],
				'email'    => (int) $cached['email'],
				'paid'     => (int) $cached['paid'],
				'cached'   => true,
			);
		}

		if ( method_exists( '\Jetpack', 'is_connection_ready' ) && ! \Jetpack::is_connection_ready() ) {
			throw new \Exception( 'Subscriber counts need a connected Jetpack site. Connect Jetpack and retry.' );
		}

		$body = self::wpcom_get( '/sites/' . self::blog_id() . '/subscribers/stats', '2' );
		if ( ! is_array( $body ) ) {
			throw new \Exception( 'WordPress.com did not return subscriber counts. Retry shortly.' );
		}
		$counts = isset( $body['counts'] ) && is_array( $body['counts'] ) ? $body['counts'] : array();
		$stats  = array(
			'all'   => isset( $counts['all_subscribers'] ) ? (int) $counts['all_subscribers'] : 0,
			'email' => isset( $counts['email_subscribers'] ) ? (int) $counts['email_subscribers'] : 0,
			'paid'  => isset( $counts['paid_subscribers'] ) ? (int) $counts['paid_subscribers'] : 0,
		);

		set_transient( self::SUBSCRIBER_CACHE, $stats, 60 * 60 );

		return array_merge( array( 'provider' => 'jetpack' ), $stats, array( 'cached' => false ) );
	}

	private static function get_sitemap_status() {
		$active = (bool) \Jetpack::is_module_active( self::MODULE_SITEMAPS );

		$url = function_exists( 'jetpack_sitemap_uri' ) ? (string) jetpack_sitemap_uri( 'sitemap.xml' ) : null;

		return array(
			'provider'          => 'jetpack',
			'module_active'     => $active,
			'url'               => $url,
			'sitemaps'          => $active ? self::sitemap_entries() : array(),
			'post_count'        => self::count_published( 'post' ),
			'page_count'        => self::count_published( 'page' ),
			'build_running'     => true === get_transient( self::SITEMAP_LOCK ),
			'next_scheduled_at' => self::next_sitemap_run(),
		);
	}

	private static function request_sitemap_rebuild() {
		self::require_module( self::MODULE_SITEMAPS, 'Sitemaps' );

		$base = array( 'provider' => 'jetpack' );

		if ( true === get_transient( self::SITEMAP_LOCK ) ) {
			return array_merge( $base, array( 'dispatched' => false, 'status' => 'running', 'next_scheduled_at' => self::next_sitemap_run() ) );
		}

		
		$next = wp_next_scheduled( self::SITEMAP_CRON_HOOK );
		if ( false !== $next && (int) $next <= time() + 10 * 60 ) {
			return array_merge( $base, array( 'dispatched' => false, 'status' => 'queued', 'next_scheduled_at' => self::next_sitemap_run() ) );
		}

		$scheduled = wp_schedule_single_event( time(), self::SITEMAP_CRON_HOOK, array(), true );
		if ( true !== $scheduled ) {
			$why = is_wp_error( $scheduled ) ? $scheduled->get_error_message() : 'WordPress declined to schedule the event.';
			throw new \Exception( 'The sitemap build could not be scheduled: ' . esc_html( $why ) );
		}

		return array_merge( $base, array( 'dispatched' => true, 'status' => 'queued', 'next_scheduled_at' => self::next_sitemap_run() ) );
	}

	private static function sitemap_entries() {
		if ( ! class_exists( 'Jetpack_Sitemap_Librarian' ) || ! function_exists( 'jp_sitemap_filename' ) || ! defined( 'JP_MASTER_SITEMAP_TYPE' ) || ! class_exists( 'DOMDocument' ) ) {
			return array();
		}
		$type      = constant( 'JP_MASTER_SITEMAP_TYPE' );
		$librarian = new \Jetpack_Sitemap_Librarian();
		$xml       = (string) $librarian->get_sitemap_text( jp_sitemap_filename( $type, '0' ), $type );
		if ( '' === $xml ) {
			return array();
		}

		$previous = libxml_use_internal_errors( true );
		$document = new \DOMDocument();
		$loaded   = $document->loadXML( $xml, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return array();
		}

		$entries = array();
		foreach ( $document->getElementsByTagName( 'sitemap' ) as $node ) {
			if ( count( $entries ) >= self::MAX_SITEMAPS ) {
				break;
			}
			$loc = $node->getElementsByTagName( 'loc' );
			if ( 0 === $loc->length ) {
				continue;
			}
			$loc_text = trim( $loc->item( 0 )->textContent );
			if ( '' === $loc_text ) {
				continue;
			}
			$mod       = $node->getElementsByTagName( 'lastmod' );
			$mod_text  = $mod->length > 0 ? trim( $mod->item( 0 )->textContent ) : '';
			$entries[] = array(
				'loc'     => $loc_text,
				'lastmod' => '' === $mod_text ? null : $mod_text,
			);
		}
		return $entries;
	}

	private static function next_sitemap_run() {
		$ts = wp_next_scheduled( self::SITEMAP_CRON_HOOK );
		return false === $ts ? null : gmdate( 'Y-m-d\TH:i:s\Z', (int) $ts );
	}

	private static function count_published( $post_type ) {
		$counts = wp_count_posts( $post_type );
		return is_object( $counts ) && isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	private static function get_shortlinks( $args ) {
		self::require_module( self::MODULE_SHORTLINKS, 'WP.me Shortlinks' );
		if ( self::blog_id() <= 0 ) {
			throw new \Exception( 'wp.me shortlinks need a WordPress.com blog ID. Connect Jetpack first.' );
		}

		$ids = array();
		if ( isset( $args['post_ids'] ) && is_array( $args['post_ids'] ) ) {
			foreach ( $args['post_ids'] as $candidate ) {
				$id = absint( $candidate );
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		$truncated = count( $ids ) > self::MAX_SHORTLINK_IDS;
		$ids       = array_slice( $ids, 0, self::MAX_SHORTLINK_IDS );

		$out = array();
		if ( true === self::to_bool( $args['include_blog'] ?? false ) ) {
			$link = wp_get_shortlink( 0, 'blog' );
			if ( is_string( $link ) && '' !== $link ) {
				$out[] = array(
					'post_id'    => 0,
					'post_title' => (string) get_bloginfo( 'name' ),
					'post_type'  => 'blog',
					'shortlink'  => $link,
					'target_url' => (string) home_url( '/' ),
				);
			}
		}

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$link = wp_get_shortlink( $id, 'post' );
			if ( ! is_string( $link ) || '' === $link ) {
				continue;
			}
			$out[] = array(
				'post_id'    => (int) $post->ID,
				'post_title' => (string) $post->post_title,
				'post_type'  => (string) $post->post_type,
				'shortlink'  => $link,
				'target_url' => (string) get_permalink( $post ),
			);
		}

		$result = array(
			'provider'   => 'jetpack',
			'shortlinks' => $out,
		);
		if ( $truncated ) {
			$result['note'] = 'Only the first ' . self::MAX_SHORTLINK_IDS . ' post IDs were looked up.';
		}
		return $result;
	}

	
	private static function connection_manager() {
		$class = '\Automattic\Jetpack\Connection\Manager';
		if ( ! class_exists( $class ) ) {
			return null;
		}
		return new $class( 'jetpack' );
	}

	private static function blog_id() {
		return (int) \Jetpack_Options::get_option( 'id' );
	}

	private static function wpcom_get( $path, $version ) {
		if ( self::blog_id() <= 0 ) {
			return null;
		}
		$client = '\Automattic\Jetpack\Connection\Client';
		if ( ! class_exists( $client ) || ! method_exists( $client, 'wpcom_json_api_request_as_blog' ) ) {
			return null;
		}
		$response = $client::wpcom_json_api_request_as_blog( $path, $version, array(), null, 'wpcom' );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	private static function wpcom_stats( array $methods ) {
		$class = '\Automattic\Jetpack\Stats\WPCOM_Stats';
		if ( ! class_exists( $class ) ) {
			throw new \Exception( 'Jetpack Stats is not available in this Jetpack version.' );
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $class, $method ) ) {
				throw new \Exception( 'This Jetpack version does not provide the Stats report ' . esc_html( $method ) . '.' );
			}
		}
		return new $class();
	}

	private static function stats_result( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Jetpack Stats could not be read from WordPress.com. ' . esc_html( self::error_text( $result ) ) );
		}
		return is_array( $result ) ? $result : array();
	}

	private static function error_text( $maybe_error ) {
		if ( ! is_wp_error( $maybe_error ) ) {
			return '';
		}
		if ( 'site_not_connected' === $maybe_error->get_error_code() ) {
			return 'This site is not connected to WordPress.com.';
		}
		return (string) $maybe_error->get_error_message();
	}

	private static function require_methods( array $methods ) {
		foreach ( $methods as $method ) {
			if ( ! method_exists( '\Jetpack', $method ) ) {
				throw new \Exception( 'This Jetpack version does not provide Jetpack::' . esc_html( $method ) . '().' );
			}
		}
	}

	private static function require_module( $slug, $label ) {
		if ( ! method_exists( '\Jetpack', 'is_module_active' ) || ! \Jetpack::is_module_active( $slug ) ) {
			throw new \Exception( 'The Jetpack ' . esc_html( $label ) . ' module is not active. Activate it with jetpack_set_module_status (slug "' . esc_html( $slug ) . '") first.' );
		}
	}

	private static function require_ixr() {
		if ( ! class_exists( 'Jetpack_IXR_Client' ) ) {
			throw new \Exception( 'The Jetpack XML-RPC client is not available in this version.' );
		}
	}

	private static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value || '' === $value || ! is_scalar( $value ) ) {
			return null;
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	private static function pick_period( $raw ) {
		return is_string( $raw ) && in_array( $raw, self::PERIODS, true ) ? $raw : 'day';
	}

	private static function sanitize_date( $raw ) {
		if ( is_string( $raw ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return $raw;
		}
		return gmdate( 'Y-m-d' );
	}

	private static function clamp_int( $raw, $min, $max, $fallback ) {
		if ( ! is_numeric( $raw ) ) {
			return $fallback;
		}
		return max( $min, min( $max, (int) $raw ) );
	}
}
