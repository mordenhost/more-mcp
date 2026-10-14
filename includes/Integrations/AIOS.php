<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIOS {

	const LOG_LEVELS = array( 'info', 'warning', 'fatal', 'error', 'debug', 'trace' );

	public static function is_available() {
		return defined( 'AIO_WP_SECURITY_VERSION' ) || class_exists( 'AIO_WP_Security' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'aios' ),
			'capabilities' => array( 'security' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'aios_get_status',
				'description' => 'All-In-One Security (AIOS) overview: plugin, database, and firewall versions, whether the PHP firewall is loaded, which headline protections are on (login lockout, login CAPTCHA, renamed login page, cookie brute-force prevention, IP/user-agent blacklisting, basic/6G/pingback firewall rules, file-change detection, file editing disabled, user enumeration blocked, REST API restricted, maintenance lockout), whether file changes were detected, and counts of active login lockouts, permanently blocked IPs, failed logins in the last 24 hours, and firewall blocks in the last 24 hours. Never returns a secret key, the renamed login slug, or an IP list. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'aios_get_lockouts',
				'description' => 'List IP addresses currently locked out of logging in by All-In-One Security: the IP, the username tried, the lock reason(s) (e.g. too_many_failed_logins, invalid_username, 404), when the lockout started and when it is released. Never returns the unlock key. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum number of lockouts to return (default 50, max 200). The total count is always returned.' ),
					),
				),
			),
			array(
				'name'        => 'aios_get_blocked_ips',
				'description' => 'List IP addresses on All-In-One Security\'s permanent block list, with the block reason (e.g. spam, registration_spam), country of origin when known, and when the block was added. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum number of blocked IPs to return (default 50, max 200). The total count is always returned.' ),
					),
				),
			),
			array(
				'name'        => 'aios_get_events',
				'description' => 'Read All-In-One Security\'s audit log, newest first: event type (e.g. failed_login, successful_login, rule_triggered, plugin_activated, user_registration, password_reset), level (info, warning, error, ...), username, IP, country code, and time. Filter by event_type and/or level. Never returns the event details payload or stack trace. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'event_type' => array( 'type' => 'string', 'description' => 'Filter to one event type, e.g. "failed_login" or "rule_triggered". Omit for all types.' ),
						'level'      => array( 'type' => 'string', 'description' => 'Filter to one level: info, warning, fatal, error, debug, or trace. Omit for all levels.' ),
						'limit'      => array( 'type' => 'integer', 'description' => 'Maximum number of entries to return (default 25, max 100). The total matching count is always returned.' ),
					),
				),
			),
			array(
				'name'        => 'aios_get_firewall_settings',
				'description' => 'Summarize All-In-One Security\'s firewall and brute-force configuration: the PHP firewall rules (pingback, proxy comments, bad query strings, advanced character filter, blank-header POSTs, fake Googlebots, 6G sections and blocked request methods), the .htaccess rules (basic firewall, legacy 5G, debug-log access, index views, TRACE/TRACK, upload size limit), IP/user-agent blacklisting with entry counts, login lockout thresholds (attempts, retry window, lockout lengths in minutes), the lockout allowlist entry count, login CAPTCHA provider, honeypot, renamed login page, cookie-based brute-force prevention, forced logout, and 404 lockout. On/off flags and numbers only: never a secret word, key, login slug, IP, or user agent. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use All-In-One Security tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'All-In-One Security is not active.' );
		}

		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'aios_get_status':
				return self::get_status();

			case 'aios_get_lockouts':
				return self::get_lockouts( $args );

			case 'aios_get_blocked_ips':
				return self::get_blocked_ips( $args );

			case 'aios_get_events':
				return self::get_events( $args );

			case 'aios_get_firewall_settings':
				return self::get_firewall_settings();

			default:
				throw new \Exception( 'Unknown All-In-One Security tool: ' . esc_html( $name ) );
		}
	}

	private static function get_status() {
		$since = time() - 86400;

		return array(
			'provider'               => 'aios',
			'plugin_version'         => defined( 'AIO_WP_SECURITY_VERSION' ) ? (string) constant( 'AIO_WP_SECURITY_VERSION' ) : null,
			'db_version'             => defined( 'AIO_WP_SECURITY_DB_VERSION' ) ? (string) constant( 'AIO_WP_SECURITY_DB_VERSION' ) : null,
			'firewall_version'       => defined( 'AIO_WP_SECURITY_FIREWALL_VERSION' ) ? (string) constant( 'AIO_WP_SECURITY_FIREWALL_VERSION' ) : null,
			'php_firewall_loaded'    => self::php_firewall_loaded(),
			'features'               => array(
				'login_lockout'                 => self::is_on( 'aiowps_enable_login_lockdown' ),
				'login_captcha'                 => self::is_on( 'aiowps_enable_login_captcha' ),
				'renamed_login_page'            => self::is_on( 'aiowps_enable_rename_login_page' ),
				'cookie_brute_force_prevention' => self::is_on( 'aiowps_enable_brute_force_attack_prevention' ),
				'ip_user_agent_blacklisting'    => self::is_on( 'aiowps_enable_blacklisting' ),
				'basic_firewall'                => self::is_on( 'aiowps_enable_basic_firewall' ),
				'6g_firewall'                   => self::is_on( 'aiowps_enable_6g_firewall' ),
				'pingback_firewall'             => self::is_on( 'aiowps_enable_pingback_firewall' ),
				'file_change_detection'         => self::is_on( 'aiowps_enable_automated_fcd_scan' ),
				'file_editing_disabled'         => self::is_on( 'aiowps_disable_file_editing' ),
				'user_enumeration_blocked'      => self::is_on( 'aiowps_prevent_users_enumeration' ),
				'rest_api_restricted'           => self::is_on( 'aiowps_disallow_unauthorized_rest_requests' ),
				'maintenance_lockout'           => self::is_on( 'aiowps_site_lockout' ),
			),

			'file_changes_detected'  => (bool) self::setting( 'aiowps_fcds_change_detected' ),
			'active_lockouts'        => self::count_active_lockouts(),
			'permanently_blocked_ips' => self::count_rows( self::table( 'perm_block' ) ),
			'failed_logins_24h'      => self::count_audit_events( 'failed_login', $since ),
			'firewall_blocks_24h'    => self::count_audit_events( 'rule_triggered', $since ),
		);
	}

	private static function get_lockouts( $args ) {
		global $wpdb;
		$table = self::table( 'lockout' );
		$limit = self::limit( $args, 50, 200 );

		if ( ! self::table_exists( $table ) ) {
			return array( 'available' => false, 'total' => 0, 'limit' => $limit, 'lockouts' => array() );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the plugin's own constant
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, user_login, created, released, failed_login_ip, lock_reason FROM `{$table}` WHERE `released` > %d ORDER BY `created` DESC LIMIT %d",
				time(),
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$reason = isset( $row['lock_reason'] ) ? (string) $row['lock_reason'] : '';
			$out[]  = array(
				'id'          => isset( $row['id'] ) ? (int) $row['id'] : null,
				'ip'          => isset( $row['failed_login_ip'] ) ? (string) $row['failed_login_ip'] : '',
				'username'    => isset( $row['user_login'] ) ? (string) $row['user_login'] : '',
				'user_id'     => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
				
				'reasons'     => array_values( array_filter( array_map( 'trim', explode( ',', $reason ) ) ) ),
				'locked_at'   => self::iso( $row['created'] ?? 0 ),
				'released_at' => self::iso( $row['released'] ?? 0 ),
			);
		}

		return array(
			'available' => true,
			'total'     => (int) self::count_active_lockouts(),
			'limit'     => $limit,
			'lockouts'  => $out,
		);
	}

	private static function get_blocked_ips( $args ) {
		global $wpdb;
		$table = self::table( 'perm_block' );
		$limit = self::limit( $args, 50, 200 );

		if ( ! self::table_exists( $table ) ) {
			return array( 'available' => false, 'total' => 0, 'limit' => $limit, 'blocked_ips' => array() );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the plugin's own constant
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, blocked_ip, block_reason, country_origin, created FROM `{$table}` ORDER BY `created` DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'         => isset( $row['id'] ) ? (int) $row['id'] : null,
				'ip'         => isset( $row['blocked_ip'] ) ? (string) $row['blocked_ip'] : '',
				'reason'     => isset( $row['block_reason'] ) ? (string) $row['block_reason'] : '',
				'country'    => isset( $row['country_origin'] ) && '' !== (string) $row['country_origin'] ? (string) $row['country_origin'] : null,
				'blocked_at' => self::iso( $row['created'] ?? 0 ),
			);
		}

		return array(
			'available'   => true,
			'total'       => (int) self::count_rows( $table ),
			'limit'       => $limit,
			'blocked_ips' => $out,
		);
	}

	private static function get_events( $args ) {
		global $wpdb;
		$table = self::table( 'audit' );
		$limit = self::limit( $args, 25, 100 );

		$where  = array();
		$params = array();
		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = '`event_type` = %s';
			$params[] = sanitize_key( (string) $args['event_type'] );
		}
		if ( ! empty( $args['level'] ) ) {
			$level = sanitize_key( (string) $args['level'] );
			if ( ! in_array( $level, self::LOG_LEVELS, true ) ) {
				throw new \Exception( 'Unknown All-In-One Security log level: ' . esc_html( $level ) . '. Use one of: ' . implode( ', ', self::LOG_LEVELS ) . '.' );
			}
			$where[]  = '`level` = %s';
			$params[] = $level;
		}
		$site = self::site_scope();
		if ( null !== $site ) {
			$where[]  = '`site_id` = %d';
			$params[] = $site;
		}

		if ( ! self::table_exists( $table ) ) {
			return array( 'available' => false, 'total' => 0, 'count' => 0, 'entries' => array() );
		}

		$where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name from the plugin's own constant; the WHERE fragment holds placeholders only, its values go through prepare().
		$total = $params
			? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}`{$where_sql}", $params ) )
			: $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, site_id, username, ip, level, event_type, created, country_code FROM `{$table}`{$where_sql} ORDER BY `created` DESC, `id` DESC LIMIT %d",
				array_merge( $params, array( $limit ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'           => isset( $row['id'] ) ? (int) $row['id'] : null,
				'event_type'   => isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
				'level'        => isset( $row['level'] ) ? (string) $row['level'] : '',
				'username'     => isset( $row['username'] ) ? (string) $row['username'] : '',
				'ip'           => isset( $row['ip'] ) ? (string) $row['ip'] : '',
				'country_code' => isset( $row['country_code'] ) && '' !== (string) $row['country_code'] ? (string) $row['country_code'] : null,
				'site_id'      => isset( $row['site_id'] ) ? (int) $row['site_id'] : 0,
				'created_at'   => self::iso( $row['created'] ?? 0 ),
			);
		}

		return array(
			'available' => true,
			'total'     => (int) $total,
			'count'     => count( $out ),
			'entries'   => $out,
		);
	}

	private static function get_firewall_settings() {
		$methods = self::firewall_setting( 'aiowps_6g_block_request_methods' );
		$methods = is_array( $methods ) ? array_values( array_map( 'strtoupper', array_filter( array_map( 'strval', $methods ) ) ) ) : array();

		return array(
			'provider'     => 'aios',
			'php_firewall' => array(
				'loaded'                      => self::php_firewall_loaded(),
				'pingback_firewall'           => self::is_on( 'aiowps_enable_pingback_firewall' ),
				'forbid_proxy_comments'       => self::is_on( 'aiowps_forbid_proxy_comments' ),
				'deny_bad_query_strings'      => self::is_on( 'aiowps_deny_bad_query_strings' ),
				'advanced_char_string_filter' => self::is_on( 'aiowps_advanced_char_string_filter' ),
				'ban_post_blank_headers'      => self::is_on( 'aiowps_ban_post_blank_headers' ),
				'block_fake_googlebots'       => self::is_on( 'aiowps_block_fake_googlebots' ),
				'6g'                          => array(
					'enabled'               => self::is_on( 'aiowps_enable_6g_firewall' ),
					'blocked_request_methods' => $methods,
					'block_query'           => self::is_on( 'aiowps_6g_block_query' ),
					'block_request'         => self::is_on( 'aiowps_6g_block_request' ),
					'block_referrers'       => self::is_on( 'aiowps_6g_block_referrers' ),
					'block_agents'          => self::is_on( 'aiowps_6g_block_agents' ),
				),
			),
			'htaccess_firewall' => array(
				'basic_firewall'             => self::is_on( 'aiowps_enable_basic_firewall' ),
				'legacy_5g_firewall'         => self::is_on( 'aiowps_enable_5g_firewall' ),
				'block_debug_log_access'     => self::is_on( 'aiowps_block_debug_log_file_access' ),
				'disable_index_views'        => self::is_on( 'aiowps_disable_index_views' ),
				'disable_trace_and_track'    => self::is_on( 'aiowps_disable_trace_and_track' ),
				'max_file_upload_size_mb'    => self::int_setting( 'aiowps_max_file_upload_size' ),
				'xmlrpc_pingback_methods_disabled' => self::is_on( 'aiowps_disable_xmlrpc_pingback_methods' ),
				'rss_and_atom_feeds_disabled' => self::is_on( 'aiowps_disable_rss_and_atom_feeds' ),
			),
			'blacklist'    => array(
				'enabled'                   => self::is_on( 'aiowps_enable_blacklisting' ),
				
				'banned_ip_entries'         => self::count_entries( self::setting( 'aiowps_banned_ip_addresses' ) ),
				'banned_user_agent_entries' => self::count_entries( self::setting( 'aiowps_banned_user_agents' ) ),
			),
			'login_lockout' => array(
				'enabled'                          => self::is_on( 'aiowps_enable_login_lockdown' ),
				'max_login_attempts'               => self::int_setting( 'aiowps_max_login_attempts' ),
				'retry_time_period_minutes'        => self::int_setting( 'aiowps_retry_time_period' ),
				'lockout_time_length_minutes'      => self::int_setting( 'aiowps_lockout_time_length' ),
				'max_lockout_time_length_minutes'  => self::int_setting( 'aiowps_max_lockout_time_length' ),
				'instant_lockout_invalid_usernames' => self::is_on( 'aiowps_enable_invalid_username_lockdown' ),
				'instant_lockout_username_entries' => self::count_entries( self::setting( 'aiowps_instantly_lockout_specific_usernames' ) ),
				'allow_unlock_requests'            => self::is_on( 'aiowps_allow_unlock_requests' ),
				'generic_login_error_message'      => self::is_on( 'aiowps_set_generic_login_msg' ),
				'email_notification'               => self::is_on( 'aiowps_enable_email_notify' ),
				'allowlist_enabled'                => self::is_on( 'aiowps_lockdown_enable_whitelisting' ),
				'allowlist_entries'                => self::count_entries( self::setting( 'aiowps_lockdown_allowed_ip_addresses' ) ),
			),
			'login_protection' => array(
				'captcha_enabled'               => self::is_on( 'aiowps_enable_login_captcha' ),
				'captcha_provider'              => self::string_setting( 'aiowps_default_captcha' ),
				'honeypot'                      => self::is_on( 'aiowps_enable_login_honeypot' ),
				
				'renamed_login_page'            => self::is_on( 'aiowps_enable_rename_login_page' ),
				
				'cookie_brute_force_prevention' => self::is_on( 'aiowps_enable_brute_force_attack_prevention' ),
				'application_passwords_disabled' => self::is_on( 'aiowps_disable_application_password' ),
				'forced_logout'                 => self::is_on( 'aiowps_enable_forced_logout' ),
				'forced_logout_after_minutes'   => self::int_setting( 'aiowps_logout_time_period' ),
			),
			'404_detection' => array(
				'logging'                => self::is_on( 'aiowps_enable_404_logging' ),
				'ip_lockout'             => self::is_on( 'aiowps_enable_404_IP_lockout' ),
				'lockout_length_minutes' => self::int_setting( 'aiowps_404_lockout_time_length' ),
			),
		);
	}

	

	
	private static function setting( $key ) {
		if ( class_exists( 'AIOWPSecurity_Config' ) && method_exists( 'AIOWPSecurity_Config', 'get_instance' ) ) {
			$configs = \AIOWPSecurity_Config::get_instance();
			if ( is_object( $configs ) && method_exists( $configs, 'get_value' ) ) {
				return $configs->get_value( $key );
			}
		}
		$stored = get_option( 'aio_wp_security_configs', array() );
		return is_array( $stored ) && isset( $stored[ $key ] ) ? $stored[ $key ] : '';
	}

	private static function firewall_setting( $key ) {
		if ( ! class_exists( 'AIOS_Firewall_Resource' ) || ! method_exists( 'AIOS_Firewall_Resource', 'request' ) ) {
			return null;
		}
		$config = \AIOS_Firewall_Resource::request( \AIOS_Firewall_Resource::CONFIG );
		return is_object( $config ) ? $config->get_value( $key ) : null;
	}

	private static function php_firewall_loaded() {
		if ( ! class_exists( 'AIOS_Firewall_Resource' ) || ! method_exists( 'AIOS_Firewall_Resource', 'all_loaded' ) ) {
			return null;
		}
		return (bool) \AIOS_Firewall_Resource::all_loaded();
	}

	private static function is_on( $key ) {
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- mirrors the plugin's own check.
		return '1' == self::firewall_setting( $key ) || '1' == self::setting( $key );
	}

	private static function int_setting( $key ) {
		$value = self::setting( $key );
		return is_numeric( $value ) ? (int) $value : null;
	}

	private static function string_setting( $key ) {
		$value = self::setting( $key );
		return is_scalar( $value ) && '' !== (string) $value ? sanitize_key( (string) $value ) : null;
	}

	private static function count_entries( $value ) {
		if ( is_array( $value ) ) {
			$lines = $value;
		} elseif ( is_string( $value ) ) {
			$lines = explode( "\n", str_replace( "\r", '', $value ) );
		} else {
			return 0;
		}
		return count( array_filter( array_map( 'trim', array_map( 'strval', $lines ) ), 'strlen' ) );
	}

	

	
	private static function table( $which ) {
		global $wpdb;
		switch ( $which ) {
			case 'lockout':
				return defined( 'AIOWPSEC_TBL_LOGIN_LOCKOUT' ) ? (string) constant( 'AIOWPSEC_TBL_LOGIN_LOCKOUT' ) : $wpdb->prefix . 'aiowps_login_lockdown';
			case 'perm_block':
				return defined( 'AIOWPSEC_TBL_PERM_BLOCK' ) ? (string) constant( 'AIOWPSEC_TBL_PERM_BLOCK' ) : $wpdb->prefix . 'aiowps_permanent_block';
			default:
				return defined( 'AIOWPSEC_TBL_AUDIT_LOG' ) ? (string) constant( 'AIOWPSEC_TBL_AUDIT_LOG' ) : $wpdb->base_prefix . 'aiowps_audit_log';
		}
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function count_rows( $table ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the plugin's own constant
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	}

	private static function count_active_lockouts() {
		global $wpdb;
		$table = self::table( 'lockout' );
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the plugin's own constant
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `released` > %d", time() ) );
	}

	private static function count_audit_events( $event_type, $since ) {
		global $wpdb;
		$table = self::table( 'audit' );
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		$site = self::site_scope();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the plugin's own constant
		if ( null === $site ) {
			$n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `event_type` = %s AND `created` > %d", $event_type, $since ) );
		} else {
			$n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `event_type` = %s AND `created` > %d AND `site_id` = %d", $event_type, $since, $site ) );
		}
		// phpcs:enable
		return (int) $n;
	}

	private static function site_scope() {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_super_admin' ) && ! is_super_admin() ) {
			return (int) get_current_blog_id();
		}
		return null;
	}

	private static function limit( $args, $default, $max ) {
		return isset( $args['limit'] ) ? max( 1, min( $max, (int) $args['limit'] ) ) : $default;
	}

	private static function iso( $ts ) {
		$ts = (int) $ts;
		return $ts > 0 ? gmdate( 'c', $ts ) : null;
	}
}
