<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Options_Support {

	public static function find_plugin_options( $slug ) {
		global $wpdb;
		$slug = sanitize_key($slug);
		if (empty($slug)) return [];

		$variants = array_unique([
			$slug,
			str_replace('-', '_', $slug),
		]);

		$clauses = [];
		$values  = [];
		foreach ($variants as $variant) {
			$clauses[] = '(option_name = %s OR option_name LIKE %s)';
			$values[]  = $variant;
			$values[]  = $wpdb->esc_like($variant) . '_%';
		}
		$where = implode(' OR ', $clauses);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE {$where} ORDER BY option_name ASC",
				$values
			)
		);
		if (empty($rows)) return [];

		$result = [];
		foreach ($rows as $row) {

			
			if (self::is_sensitive_key($row->option_name)) {
				$result[$row->option_name] = '[REDACTED]';
				continue;
			}
			$value = maybe_unserialize($row->option_value);
			$result[$row->option_name] = self::redact_sensitive_keys($value);
		}
		return $result;
	}

	public static function redact_sensitive_keys( $value, $in_object = false ) {
		if (is_object($value)) {
			$value     = self::object_properties($value);
			$in_object = true;
		}
		if (!is_array($value)) {
			return ($in_object && is_string($value)) ? self::relative_to_abspath($value) : $value;
		}
		$out = [];
		foreach ($value as $k => $v) {
			if (self::is_sensitive_key($k)) {
				$out[$k] = '[REDACTED]';
				continue;
			}
			$out[$k] = self::redact_sensitive_keys($v, $in_object);
		}
		return $out;
	}

	private static function object_properties( $object ) {
		$props = (array) $object;
		$class = $object instanceof \__PHP_Incomplete_Class ? (string) ( $props['__PHP_Incomplete_Class_Name'] ?? '' ) : get_class($object);
		$own   = [];
		$inherited = [];
		foreach ($props as $k => $v) {
			if (is_string($k) && '' !== $k && "\0" === $k[0]) {
				$end   = strpos($k, "\0", 1);
				$owner = false === $end ? '' : substr($k, 1, $end - 1);
				$k     = false === $end ? substr($k, 1) : substr($k, $end + 1);
				if ('*' !== $owner && $owner !== $class) {
					$inherited[$k] = $v;
					continue;
				}
			}
			$own[$k] = $v;
		}
		return $own + $inherited;
	}

	private static function relative_to_abspath( string $text ): string {
		$root = str_replace('\\', '/', (string) ABSPATH);
		if (strlen($root) < 2) {
			return $text; 
		}
		return str_replace(array(rtrim((string) ABSPATH, '/\\') . DIRECTORY_SEPARATOR, $root), '', $text);
	}

	public static function is_sensitive_key( $key ) {
		if (!is_string($key) || $key === '') return false;
		$needles = [
			'password', 'passwd', 'secret', 'salt', 'token', 'nonce',
			'apikey', 'api_key', 'accesskey', 'access_key',
			'private_key', 'public_key',
			'client_secret', 'client_id', 'auth_key', 'auth_token',
			'bearer', 'license_key', 'consumer_secret', 'consumer_key',
			'webhook_secret', 'session_key', 'credentials',

			'site_key',
		];
		$key_lc = strtolower($key);
		foreach ($needles as $needle) {
			if (strpos($key_lc, $needle) !== false) return true;
		}
		return false;
	}

	public static function is_denylisted_option( $name ) {
		$name_lc = strtolower($name);

		$exact = [
			'siteurl', 'home', 'db_version', 'wp_user_roles', 'cron', 'rewrite_rules',
			'wplang', 'template', 'stylesheet', 'active_plugins',
			'mordenhost_mcp_settings', 
		];
		if (in_array($name_lc, $exact, true)) return true;

		if (strpos($name_lc, 'mordenhost_mcp_') === 0) return true;

		$patterns = [
			'secret', 'salt', 'auth_key', 'logged_in_key', 'nonce_key',
			'license_key', 'api_key', 'auth_token', 'private_key',
			'session_token', 'recovery_key',
		];
		foreach ($patterns as $p) {
			if (strpos($name_lc, $p) !== false) return true;
		}
		return false;
	}

	private const DEDICATED_TOOLS = [
		'show_on_front'          => [null, 'wp_set_front_page (page_id or show_posts)'],
		'page_on_front'          => [null, 'wp_set_front_page (page_id)'],
		'page_for_posts'         => [null, 'wp_set_front_page (posts_page_id)'],
		'permalink_structure'    => [null, 'wp_update_permalink_structure (structure)'],
		'category_base'          => [null, 'wp_update_permalink_structure (category_base)'],
		'tag_base'               => [null, 'wp_update_permalink_structure (tag_base)'],
		'wplang'                 => [null, 'wp_update_site_settings (language)'],
		'sticky_posts'           => [null, 'wp_update_post (sticky)'],
		'siteurl'                => [null, 'wp_update_site_settings (site_url with confirm_url_change)'],
		'home'                   => [null, 'wp_update_site_settings (home_url with confirm_url_change)'],
		'timezone_string'        => [null, 'wp_update_site_settings (timezone)'],
		'start_of_week'          => ['wp_get_site_settings', 'wp_update_site_settings (start_of_week)'],
		'default_comment_status' => ['wp_get_site_settings', 'wp_update_site_settings (default_comment_status)'],
		'default_ping_status'    => ['wp_get_site_settings', 'wp_update_site_settings (default_ping_status)'],
		'blog_public'            => ['wp_get_site_settings', 'wp_update_site_settings (search_engine_visible)'],
	];

	public static function dedicated_tool_hint( $name, $write ) {
		$entry = self::DEDICATED_TOOLS[ strtolower( (string) $name ) ] ?? null;
		$tool  = $entry ? $entry[ $write ? 1 : 0 ] : null;
		return null === $tool ? '' : ' Use ' . $tool . ' instead.';
	}
}
