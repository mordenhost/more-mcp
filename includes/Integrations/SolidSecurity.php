<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SolidSecurity {

	public static function is_available() {
		return class_exists( 'ITSEC_Core' ) && class_exists( 'ITSEC_Modules' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'solid-security' ),
			'capabilities' => array( 'security' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'solid_get_status',
				'description' => 'Get a Solid Security overview: plugin version, which security modules are currently active (e.g. brute-force protection, ban-users, file-change detection), and the number of currently active lockouts. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'solid_get_findings',
				'description' => 'Read Solid Security\'s event log: security-relevant events (file changes, brute-force attempts, lockouts, hardening checks) with module, event code, severity type, timestamp, and remote IP. Filter by module (e.g. "file_change", "brute_force", "ban_users") and/or type (e.g. "notice", "warning", "error", "critical") to narrow results. Never returns raw event payloads. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'module' => [ 'type' => 'string', 'description' => 'Filter to one module slug (e.g. "file_change", "brute_force"). Omit for all modules.' ],
						'type'   => [ 'type' => 'string', 'description' => 'Filter to one severity type (e.g. "notice", "warning", "error", "critical"). Omit for all types.' ],
						'limit'  => [ 'type' => 'integer', 'description' => 'Maximum number of entries to return (default 25, max 100 — the plugin\'s own internal cap).' ],
					],
				],
			],
			[
				'name'        => 'solid_get_lockouts',
				'description' => 'List currently active Solid Security lockouts: the blocked host, user, or username, the lockout type, and when it started and expires. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of lockouts to return (default 50, max 200).' ],
					],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Solid Security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Solid Security is not active' );
		}

		switch ( $name ) {
			case 'solid_get_status':
				return self::get_status();

			case 'solid_get_findings':
				return self::get_findings( $args );

			case 'solid_get_lockouts':
				return self::get_lockouts( $args );

			default:
				throw new \Exception( 'Unknown Solid Security tool: ' . esc_html( $name ) );
		}
	}

	private static function get_status() {
		$version = method_exists( 'ITSEC_Core', 'get_plugin_version' ) ? (string) \ITSEC_Core::get_plugin_version() : '';
		$modules = method_exists( 'ITSEC_Modules', 'get_active_modules' ) ? \ITSEC_Modules::get_active_modules() : [];
		$modules = is_array( $modules ) ? array_values( array_keys( array_filter( $modules ) ) ) : [];

		global $wpdb;
		$active_lockouts = null;
		$table           = $wpdb->base_prefix . 'itsec_lockouts';
		if ( self::table_exists( $table ) ) {
			$active_lockouts = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE `lockout_active` = 1 AND `lockout_expire_gmt` > %s",
					gmdate( 'Y-m-d H:i:s' )
				)
			);
		}

		return [
			'provider'        => 'solid-security',
			'plugin_version'  => $version,
			'active_modules'  => $modules,
			'active_lockouts' => $active_lockouts,
		];
	}

	private static function get_findings( $args ) {
		if ( ! class_exists( 'ITSEC_Log' ) ) {
			throw new \Exception( 'Solid Security event log is not available in this version.' );
		}

		$limit = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 25;

		$filters = [];
		if ( ! empty( $args['module'] ) ) {
			$filters['module'] = sanitize_key( $args['module'] );
		}
		if ( ! empty( $args['type'] ) ) {
			$filters['type'] = sanitize_key( $args['type'] );
		}

		$entries = \ITSEC_Log::get_entries( $filters, $limit, 1, 'timestamp', 'DESC' );
		$total   = \ITSEC_Log::get_number_of_entries( $filters );

		$out = [];
		foreach ( (array) $entries as $entry ) {
			$entry = (array) $entry;
			$out[] = [
				'id'        => isset( $entry['id'] ) ? (int) $entry['id'] : null,
				'module'    => $entry['module'] ?? '',
				'code'      => $entry['code'] ?? '',
				'type'      => $entry['type'] ?? '',
				'timestamp' => $entry['timestamp'] ?? '',
				'remote_ip' => $entry['remote_ip'] ?? '',
				'user_id'   => isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0,
			];
		}

		return [
			'total'   => (int) $total,
			'count'   => count( $out ),
			'entries' => $out,
		];
	}

	private static function get_lockouts( $args ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'itsec_lockouts';
		if ( ! self::table_exists( $table ) ) {
			return [ 'lockouts' => [], 'count' => 0 ];
		}

		$limit = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT lockout_id, lockout_type, lockout_start_gmt, lockout_expire_gmt, lockout_host, lockout_user, lockout_username, lockout_active
				 FROM `{$table}`
				 WHERE `lockout_active` = 1 AND `lockout_expire_gmt` > %s
				 ORDER BY `lockout_start_gmt` DESC
				 LIMIT %d",
				gmdate( 'Y-m-d H:i:s' ),
				$limit
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [
				'id'         => isset( $row['lockout_id'] ) ? (int) $row['lockout_id'] : null,
				'type'       => $row['lockout_type'] ?? '',
				'host'       => $row['lockout_host'] ?? '',
				'user_id'    => isset( $row['lockout_user'] ) ? (int) $row['lockout_user'] : null,
				'username'   => $row['lockout_username'] ?? '',
				'started_at' => $row['lockout_start_gmt'] ?? '',
				'expires_at' => $row['lockout_expire_gmt'] ?? '',
			];
		}

		return [
			'lockouts' => $out,
			'count'    => count( $out ),
		];
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
