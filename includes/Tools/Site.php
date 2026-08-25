<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_site_info', 'description' => 'Get user-facing site metadata (name, description, URL, language, timezone, WP version). For operator-facing environment (PHP/MySQL versions, plugin count, memory limits, disk free), use wp_get_site_status.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_site_status', 'description' => 'One-shot site diagnostic. Returns WordPress version, PHP version, MySQL/MariaDB version, active plugin count, active theme details, memory limit, max upload size, timezone, WP_DEBUG_LOG state, disk free space, install age, and site/home URLs. Use this at the start of a debugging or environment-inspection conversation instead of piecing it together from wp_get_site_info + wp_get_plugins + wp_get_active_theme. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
			['name' => 'wp_get_error_log_tail', 'description' => 'Read the tail of wp-content/debug.log. Returns the last N lines (default 100, max 1000), optionally filtered by a case-insensitive substring. Automatically caps file read at last 1MB to prevent memory blowup on huge logs (truncated=true when this happens). Returns status="disabled" with instructions when WP_DEBUG_LOG is not enabled in wp-config.php. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => ['lines' => ['type' => 'integer', 'description' => 'Number of lines to return from the tail (default 100, max 1000).'], 'filter' => ['type' => 'string', 'description' => 'Optional case-insensitive substring filter applied before the last-N slice (e.g. "Fatal error", "Deprecated", a plugin slug).']]]],
			['name' => 'wp_get_cron_schedule', 'description' => 'Enumerate scheduled wp_cron events. Returns each event with hook name, next run (unix + ISO 8601), seconds until next run, is_overdue flag, recurrence (hourly / twicedaily / daily / custom + interval in seconds), and args. Sorted by next-run ascending so overdue events come first. Useful for diagnosing missed schedules, plugin cron conflicts, or unfired hooks. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [ 'wp_get_site_info', 'wp_get_site_status', 'wp_get_error_log_tail', 'wp_get_cron_schedule' ];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_site_info':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to view site info.');
				}
				return [
					'name' => get_bloginfo('name'),
					'description' => get_bloginfo('description'),
					'url' => home_url(),
					'language' => get_locale(),
					'timezone' => wp_timezone_string(),
					'wp_version' => get_bloginfo('version'),
				];

			case 'wp_get_site_status':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read site status.');
				}
				global $wpdb;

				
				
				$db_server_info = null;
				if (method_exists($wpdb, 'db_version')) {
					$db_server_info = $wpdb->db_version();
				}
				if (empty($db_server_info)) {
					$db_server_info = (string) $wpdb->get_var('SELECT VERSION()');
				}

				
				$active_plugins = (array) get_option('active_plugins', []);
				if (is_multisite()) {
					$network_active = (array) get_site_option('active_sitewide_plugins', []);
					$active_plugin_count = count($active_plugins) + count($network_active);
				} else {
					$active_plugin_count = count($active_plugins);
				}

				$theme = wp_get_theme();

				
				$disk_free_bytes = @disk_free_space(ABSPATH);

				
				
				$oldest_ts = null;
				$oldest_row = $wpdb->get_var("SELECT UNIX_TIMESTAMP(MIN(post_date_gmt)) FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future','pending')");
				if ($oldest_row && (int) $oldest_row > 0) {
					$oldest_ts = (int) $oldest_row;
				}

				return [
					'wp_version'          => get_bloginfo('version'),
					'php_version'         => PHP_VERSION,
					'mysql_version'       => $db_server_info,
					'is_multisite'        => is_multisite(),
					'active_plugin_count' => $active_plugin_count,
					'active_theme'        => [
						'name'       => $theme->get('Name'),
						'stylesheet' => get_stylesheet(),
						'template'   => get_template(),
						'version'    => $theme->get('Version'),
					],
					'memory_limit'        => ini_get('memory_limit'),
					'max_upload_size'     => size_format(wp_max_upload_size()),
					'max_execution_time'  => (int) ini_get('max_execution_time'),
					'timezone'            => wp_timezone_string(),
					'debug_log_enabled'   => (defined('WP_DEBUG') && WP_DEBUG) && (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG),
					'disk_free_bytes'     => $disk_free_bytes === false ? null : (int) $disk_free_bytes,
					'disk_free_human'     => $disk_free_bytes === false ? null : size_format((int) $disk_free_bytes),
					'install_age_days'    => $oldest_ts ? (int) floor((time() - $oldest_ts) / DAY_IN_SECONDS) : null,
					'site_url'            => site_url(),
					'home_url'            => home_url(),
				];

			case 'wp_get_error_log_tail':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read the error log.');
				}
				$lines = isset($args['lines']) ? max(1, min(intval($args['lines']), 1000)) : 100;
				$filter = isset($args['filter']) ? (string) $args['filter'] : '';

				$log_status = 'ok';
				$log_path   = defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== ''
					? (string) WP_DEBUG_LOG
					: WP_CONTENT_DIR . '/debug.log';

				if (!(defined('WP_DEBUG') && WP_DEBUG) || !(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) {
					return [
						'status'  => 'disabled',
						'message' => 'WP_DEBUG_LOG is not enabled in wp-config.php. Add: define(\'WP_DEBUG\', true); define(\'WP_DEBUG_LOG\', true); define(\'WP_DEBUG_DISPLAY\', false);',
						'path'    => $log_path,
						'filter'  => $filter,
						'lines'   => [],
						'total_returned' => 0,
					];
				}

				if (!file_exists($log_path)) {
					return [
						'status'  => 'no_log_file',
						'message' => 'debug.log does not exist yet (no errors logged since it was last cleared).',
						'path'    => $log_path,
						'filter'  => $filter,
						'lines'   => [],
						'total_returned' => 0,
					];
				}

				if (!is_readable($log_path)) {
					return [
						'status'  => 'unreadable',
						'message' => 'debug.log exists but is not readable by PHP (check file permissions).',
						'path'    => $log_path,
						'filter'  => $filter,
						'lines'   => [],
						'total_returned' => 0,
					];
				}

				
				$filesize = filesize($log_path);
				$chunk_max = 1 * 1024 * 1024;
				$offset    = ($filesize > $chunk_max) ? ($filesize - $chunk_max) : 0;

				$fh = @fopen($log_path, 'r');
				if (!$fh) {
					throw new \Exception('Could not open debug.log for reading.');
				}
				if ($offset > 0) {
					fseek($fh, $offset);

					fgets($fh);
				}
				$raw = stream_get_contents($fh);
				fclose($fh);

				$all_lines = $raw === false ? [] : preg_split("/\r\n|\n|\r/", (string) $raw);
				
				if (!empty($all_lines) && '' === end($all_lines)) {
					array_pop($all_lines);
				}

				if ($filter !== '') {
					$all_lines = array_values(array_filter($all_lines, function ($ln) use ($filter) {
						return false !== stripos($ln, $filter);
					}));
				}

				$tail = array_slice($all_lines, -$lines);

				return [
					'status'         => $log_status,
					'path'           => $log_path,
					'filesize_bytes' => (int) $filesize,
					'window_bytes'   => (int) ($filesize - $offset),
					'truncated'      => $offset > 0,
					'filter'         => $filter,
					'total_returned' => count($tail),
					'lines'          => array_values($tail),
				];

			case 'wp_get_cron_schedule':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read the cron schedule.');
				}
				$crons = _get_cron_array();
				if (!is_array($crons)) {
					return ['events' => [], 'total_count' => 0];
				}
				$now = time();
				$events = [];
				foreach ($crons as $timestamp => $hooks) {
					if (!is_array($hooks)) continue;
					foreach ($hooks as $hook => $signatures) {
						if (!is_array($signatures)) continue;
						foreach ($signatures as $sig_key => $meta) {
							$recurrence = null;
							if (!empty($meta['schedule'])) {
								$schedules = wp_get_schedules();
								$recurrence = isset($schedules[$meta['schedule']])
									? $meta['schedule'] . ' (' . (int) $schedules[$meta['schedule']]['interval'] . 's)'
									: (string) $meta['schedule'];
							}
							$events[] = [
								'hook'          => (string) $hook,
								'next_run_ts'   => (int) $timestamp,
								'next_run_iso'  => wp_date('c', (int) $timestamp),
								'seconds_until' => (int) $timestamp - $now,
								'is_overdue'    => (int) $timestamp < $now,
								'recurrence'    => $recurrence,
								'args'          => $meta['args'] ?? [],
							];
						}
					}
				}
				
				usort($events, function ($a, $b) {
					return $a['next_run_ts'] <=> $b['next_run_ts'];
				});
				return [
					'events'      => $events,
					'total_count' => count($events),
					'now_ts'      => $now,
					'now_iso'     => wp_date('c', $now),
				];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
