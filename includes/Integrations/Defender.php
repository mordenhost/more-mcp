<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Defender {

	public static function is_available() {
		return defined( 'DEFENDER_VERSION' ) || function_exists( 'wd_di' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'def_get_scan_results',
				'description' => 'Get WP Defender\'s most recent malware/file scan results: the count of active (unresolved) issues, the count of issues the admin has chosen to ignore, and when the scan ran. Read-only; returns counts and status, not raw file contents.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'def_get_scan_status',
				'description' => 'Check whether a WP Defender scan is currently running and, if so, its progress percentage and status text. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'def_get_blocked_ips',
				'description' => 'List IP addresses currently blocked (locked out) by WP Defender\'s firewall, newest lock first. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of blocked IPs to return (default 50, max 200).' ],
					],
				],
			],
			[
				'name'        => 'def_get_lockout_stats',
				'description' => 'Get WP Defender lockout statistics: counts of login lockouts and 404 lockouts over the last 7 days, and total lockouts in the last 24 hours, 7 days, and 30 days. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'def_get_hardening_status',
				'description' => 'Get WP Defender\'s security recommendations (hardening tweaks) status: how many recommendations are resolved, how many are outstanding issues, and how many are ignored. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use WP Defender security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Defender is not active' );
		}

		switch ( $name ) {
			case 'def_get_scan_results':
				return self::get_scan_results();

			case 'def_get_scan_status':
				return self::get_scan_status();

			case 'def_get_blocked_ips':
				return self::get_blocked_ips( $args );

			case 'def_get_lockout_stats':
				return self::get_lockout_stats();

			case 'def_get_hardening_status':
				return self::get_hardening_status();

			default:
				throw new \Exception( 'Unknown WP Defender tool: ' . esc_html( $name ) );
		}
	}

	private static function get_scan_results() {
		$scan_class = '\WP_Defender\Model\Scan';
		$item_class = '\WP_Defender\Model\Scan_Item';
		if ( ! class_exists( $scan_class ) || ! method_exists( $scan_class, 'get_last' ) ) {
			throw new \Exception( 'WP Defender scan model is not available in this version.' );
		}

		$last = $scan_class::get_last();
		if ( ! is_object( $last ) || is_wp_error( $last ) ) {
			return [
				'has_scan'      => false,
				'active_issues' => 0,
				'ignored_issues'=> 0,
			];
		}

		$active  = 0;
		$ignored = 0;
		if ( method_exists( $last, 'count' ) && class_exists( $item_class ) ) {
			$active  = (int) $last->count( null, $item_class::STATUS_ACTIVE );
			$ignored = (int) $last->count( null, $item_class::STATUS_IGNORE );
		}

		$scan_time = null;
		if ( property_exists( $last, 'date_start' ) && ! empty( $last->date_start ) ) {
			
			$ts = strtotime( $last->date_start . ' UTC' );
			if ( $ts ) {
				$scan_time = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		return [
			'has_scan'       => true,
			'active_issues'  => $active,
			'ignored_issues' => $ignored,
			'scan_time'      => $scan_time,
			'status'         => property_exists( $last, 'status' ) ? (string) $last->status : null,
		];
	}

	private static function get_scan_status() {
		$scan_class = '\WP_Defender\Model\Scan';
		if ( ! class_exists( $scan_class ) || ! method_exists( $scan_class, 'get_active' ) ) {
			throw new \Exception( 'WP Defender scan model is not available in this version.' );
		}

		$active = $scan_class::get_active();
		if ( ! is_object( $active ) || is_wp_error( $active ) ) {
			return [ 'running' => false ];
		}

		return [
			'running'     => true,
			'percent'     => property_exists( $active, 'percent' ) ? (float) $active->percent : null,
			'status'      => property_exists( $active, 'status' ) ? (string) $active->status : null,
			'status_text' => method_exists( $active, 'get_status_text' ) ? (string) $active->get_status_text() : null,
		];
	}

	private static function get_blocked_ips( $args ) {
		$ip_class = '\WP_Defender\Model\Lockout_Ip';
		if ( ! class_exists( $ip_class ) || ! method_exists( $ip_class, 'get_bulk' ) ) {
			throw new \Exception( 'WP Defender lockout model is not available in this version.' );
		}

		$limit   = min( max( 1, intval( $args['limit'] ?? 50 ) ), 200 );
		$results = $ip_class::get_bulk( $ip_class::STATUS_BLOCKED, null, $limit );
		if ( ! is_array( $results ) ) {
			$results = [];
		}

		$out = [];
		foreach ( $results as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$lock_time    = property_exists( $row, 'lock_time' ) ? (int) $row->lock_time : 0;
			$release_time = property_exists( $row, 'release_time' ) ? (int) $row->release_time : 0;
			$out[] = [
				'ip'           => property_exists( $row, 'ip' ) ? sanitize_text_field( (string) $row->ip ) : '',
				'locked_at'    => $lock_time ? gmdate( 'Y-m-d H:i:s', $lock_time ) : null,
				'release_at'   => $release_time ? gmdate( 'Y-m-d H:i:s', $release_time ) : null,
				'attempts'     => property_exists( $row, 'attempt' ) ? (int) $row->attempt : null,
			];
		}

		return [
			'limit'       => $limit,
			'blocked_ips' => $out,
		];
	}

	private static function get_lockout_stats() {
		$log_class = '\WP_Defender\Model\Lockout_Log';
		if ( ! class_exists( $log_class ) ) {
			throw new \Exception( 'WP Defender lockout log is not available in this version.' );
		}

		$call = function ( $method ) use ( $log_class ) {
			return method_exists( $log_class, $method ) ? (int) $log_class::$method() : null;
		};

		return [
			'login_lockouts_7d' => $call( 'count_login_lockout_last_7_days' ),
			'lockouts_404_7d'   => $call( 'count_404_lockout_last_7_days' ),
			'lockouts_24h'      => $call( 'count_lockout_in_24_hours' ),
			'lockouts_7d'       => $call( 'count_lockout_in_7_days' ),
			'lockouts_30d'      => $call( 'count_lockout_in_30_days' ),
		];
	}

	private static function get_hardening_status() {
		$tweaks_class = '\WP_Defender\Model\Setting\Security_Tweaks';
		if ( ! class_exists( $tweaks_class ) ) {
			throw new \Exception( 'WP Defender hardening model is not available in this version.' );
		}

		$model = null;
		if ( function_exists( 'wd_di' ) ) {
			try {
				$model = wd_di()->get( $tweaks_class );
			} catch ( \Throwable $e ) {
				$model = null;
			}
		}
		if ( ! is_object( $model ) ) {
			
			$model = new $tweaks_class();
		}

		if ( ! method_exists( $model, 'get_tweak_types' ) ) {
			throw new \Exception( 'WP Defender hardening summary is not available in this version.' );
		}

		$types = $model->get_tweak_types();
		if ( ! is_array( $types ) ) {
			$types = [];
		}

		$resolved = (int) ( $types['count_fixed'] ?? 0 );
		$issues   = (int) ( $types['count_issues'] ?? 0 );
		$ignored  = (int) ( $types['count_ignored'] ?? 0 );

		return [
			'resolved'   => $resolved,
			'issues'     => $issues,
			'ignored'    => $ignored,
			'total'      => $resolved + $issues + $ignored,
		];
	}
}
