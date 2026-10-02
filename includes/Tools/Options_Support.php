<?php

namespace More_MCP\Tools;

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

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE {$where} ORDER BY option_name ASC",
				$values
			)
		);
		if (empty($rows)) return [];

		$result = [];
		foreach ($rows as $row) {
			$value = maybe_unserialize($row->option_value);
			$result[$row->option_name] = self::redact_sensitive_keys($value);
		}
		return $result;
	}

	public static function redact_sensitive_keys( $value ) {
		if (is_object($value)) {
			$value = (array) $value;
		}
		if (!is_array($value)) {
			return $value;
		}
		$out = [];
		foreach ($value as $k => $v) {
			if (self::is_sensitive_key($k)) {
				$out[$k] = '[REDACTED]';
				continue;
			}
			$out[$k] = self::redact_sensitive_keys($v);
		}
		return $out;
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
			'more_mcp_settings', 
		];
		if (in_array($name_lc, $exact, true)) return true;

		if (strpos($name_lc, 'more_mcp_') === 0) return true;

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
}
