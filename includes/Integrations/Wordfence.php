<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wordfence {

	public static function is_available() {
		return defined( 'WORDFENCE_VERSION' ) || class_exists( 'wfConfig' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wf_get_security_status',
				'description' => 'Get a Wordfence security overview: whether the firewall (WAF) is enabled and its mode, whether live traffic/login security features are on, when the last scan ran, and whether a scan is currently running. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'wf_get_scan_results',
				'description' => 'Get the results of the most recent Wordfence scan: counts of new issues, plus issues the admin has previously ignored (per-issue and per-category), and when the scan last ran. Returns finding titles and severities, never raw file contents or attacker payloads.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of new issues to detail (default 25, max 100). Counts are always returned regardless of this limit.' ],
					],
				],
			],
			[
				'name'        => 'wf_get_blocked_ips',
				'description' => 'List IP addresses currently blocked by Wordfence, with the block reason, type (manual, brute-force, rate-limit, etc.), and when the block expires. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of blocked IPs to return (default 50, max 200).' ],
					],
				],
			],
			[
				'name'        => 'wf_get_failed_logins',
				'description' => 'Get Wordfence\'s recent failed-login summary: the top usernames by failed-attempt count over the plugin\'s reporting window, and whether each username maps to a real account (a repeated failure against a valid admin username is a stronger signal than one against a random string). Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of usernames to return (default 10, max 50).' ],
					],
				],
			],
			[
				'name'        => 'wf_start_scan',
				'description' => 'Start a Wordfence scan now. A scan is CPU- and IO-intensive, so this is a two-part confirmation: call without confirm to receive a preview and start nothing; call with confirm=true AND confirm_scan=true to actually begin. Uses the scan type configured in Wordfence. Refuses if a scan is already running.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'confirm'      => [ 'type' => 'boolean', 'description' => 'Must be true to start the scan. Omit or false to receive a preview instead — that preview is the intended first call.' ],
						'confirm_scan' => [ 'type' => 'boolean', 'description' => 'Must also be true, alongside confirm=true, to start the scan. A deliberate second flag so a scan cannot begin on a single unconsidered call.' ],
					],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Wordfence security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Wordfence is not active' );
		}

		switch ( $name ) {
			case 'wf_get_security_status':
				return self::get_security_status();

			case 'wf_get_scan_results':
				return self::get_scan_results( $args );

			case 'wf_get_blocked_ips':
				return self::get_blocked_ips( $args );

			case 'wf_get_failed_logins':
				return self::get_failed_logins( $args );

			case 'wf_start_scan':
				return self::start_scan( $args );

			default:
				throw new \Exception( 'Unknown Wordfence tool: ' . esc_html( $name ) );
		}
	}

	private static function get_security_status() {
		if ( ! class_exists( 'wfConfig' ) ) {
			throw new \Exception( 'Wordfence configuration store is not available in this version.' );
		}

		$waf_status = (string) \wfConfig::get( 'wafStatus', 'disabled' );

		$status = [
			'firewall_enabled'      => 'disabled' !== $waf_status && '' !== $waf_status,
			'firewall_mode'         => $waf_status,
			'live_traffic_enabled'  => (bool) \wfConfig::get( 'liveTrafficEnabled', false ),
			'login_security_enabled'=> (bool) \wfConfig::get( 'loginSec_enableSeparatePrompt', false ),
			'last_scan_time'        => null,
			'scan_running'          => false,
		];

		if ( class_exists( 'wfScanner' ) && method_exists( 'wfScanner', 'shared' ) ) {
			$scanner = \wfScanner::shared();
			if ( method_exists( $scanner, 'lastScanTime' ) ) {
				$scan_time = $scanner->lastScanTime();
				
				$status['last_scan_time'] = $scan_time ? gmdate( 'Y-m-d H:i:s', (int) $scan_time ) : null;
			}
			if ( method_exists( $scanner, 'isRunning' ) ) {
				$status['scan_running'] = (bool) $scanner->isRunning();
			}
			if ( method_exists( $scanner, 'scanType' ) ) {
				$status['configured_scan_type'] = (string) $scanner->scanType();
			}
		}

		return $status;
	}

	private static function get_scan_results( $args ) {
		if ( ! class_exists( 'wfIssues' ) ) {
			throw new \Exception( 'Wordfence scan results are not available in this version.' );
		}

		$limit  = min( max( 1, intval( $args['limit'] ?? 25 ) ), 100 );
		$issues = new \wfIssues();

		$counts = method_exists( $issues, 'getIssueCounts' ) ? $issues->getIssueCounts() : [];
		if ( ! is_array( $counts ) ) {
			$counts = [];
		}

		$detailed = [];
		if ( method_exists( $issues, 'getIssues' ) ) {
			$raw = $issues->getIssues( 0, $limit, 0, 0 );
			$new = is_array( $raw ) && isset( $raw['new'] ) && is_array( $raw['new'] ) ? $raw['new'] : [];
			foreach ( $new as $issue ) {
				$detailed[] = self::format_issue( $issue );
			}
		}

		return [
			'new_count'     => (int) ( $counts['new'] ?? 0 ),
			'ignored_count' => (int) ( $counts['ignoreP'] ?? 0 ) + (int) ( $counts['ignoreC'] ?? 0 ),
			'last_scan_time'=> self::last_scan_time_string(),
			'new_issues'    => $detailed,
		];
	}

	private static function format_issue( $issue ) {
		if ( ! is_array( $issue ) ) {
			return [ 'type' => 'unknown', 'severity' => null, 'message' => '' ];
		}
		return [
			'type'     => isset( $issue['type'] ) ? sanitize_text_field( (string) $issue['type'] ) : 'unknown',
			'severity' => isset( $issue['severity'] ) ? (int) $issue['severity'] : null,
			'message'  => isset( $issue['shortMsg'] ) ? sanitize_text_field( (string) $issue['shortMsg'] ) : '',
		];
	}

	private static function get_blocked_ips( $args ) {
		if ( ! class_exists( 'wfBlock' ) || ! method_exists( 'wfBlock', 'ipBlocks' ) ) {
			throw new \Exception( 'Wordfence block list is not available in this version.' );
		}

		$limit  = min( max( 1, intval( $args['limit'] ?? 50 ) ), 200 );
		$blocks = \wfBlock::ipBlocks( true );
		if ( ! is_array( $blocks ) ) {
			$blocks = [];
		}

		$out = [];
		foreach ( $blocks as $block ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$out[] = self::format_block( $block );
		}

		return [
			'total'       => count( $blocks ),
			'limit'       => $limit,
			'blocked_ips' => $out,
		];
	}

	private static function block_type_name( $type ) {
		$map = [
			1 => 'ip_manual',
			2 => 'wfsn_temporary',
			3 => 'country',
			4 => 'pattern',
			5 => 'rate_block',
			6 => 'rate_throttle',
			7 => 'lockout',
			8 => 'ip_automatic_temporary',
			9 => 'ip_automatic_permanent',
		];
		return $map[ (int) $type ] ?? ( 'type_' . (int) $type );
	}

	private static function format_block( $block ) {
		if ( ! is_object( $block ) ) {
			return [ 'ip' => '', 'reason' => '', 'type' => '', 'permanent' => false, 'expires_at' => null ];
		}

		$ip         = sanitize_text_field( (string) $block->IP );
		$reason     = sanitize_text_field( (string) $block->reason );
		$type_raw   = $block->type;
		$expiration = (int) $block->expiration;

		return [
			'ip'         => $ip,
			'reason'     => $reason,
			'type'       => self::block_type_name( $type_raw ),
			'permanent'  => 0 === $expiration,
			'expires_at' => $expiration > 0 ? gmdate( 'Y-m-d H:i:s', $expiration ) : null,
		];
	}

	private static function get_failed_logins( $args ) {
		if ( ! class_exists( 'wfActivityReport' ) ) {
			throw new \Exception( 'Wordfence activity report is not available in this version.' );
		}

		$limit  = min( max( 1, intval( $args['limit'] ?? 10 ) ), 50 );
		$report = new \wfActivityReport();
		if ( ! method_exists( $report, 'getTopFailedLogins' ) ) {
			throw new \Exception( 'Wordfence failed-login report is not available in this version.' );
		}

		$rows = $report->getTopFailedLogins( $limit );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$out = [];
		foreach ( $rows as $row ) {
			$out[] = [
				'username'      => isset( $row->username ) ? sanitize_text_field( (string) $row->username ) : '',
				'fail_count'    => isset( $row->fail_count ) ? (int) $row->fail_count : 0,
				'is_valid_user' => ! empty( $row->is_valid_user ),
			];
		}

		return [
			'failed_logins' => $out,
		];
	}

	private static function start_scan( $args ) {
		$confirmed = ! empty( $args['confirm'] ) && ! empty( $args['confirm_scan'] );

		if ( ! $confirmed ) {
			return [
				'preview' => true,
				'message' => 'Preview only. To start the scan, call again with confirm=true and confirm_scan=true. The scan runs in the background and is CPU- and IO-intensive. Uses the scan type configured in Wordfence.',
			];
		}

		if ( ! class_exists( 'wfScanEngine' ) || ! method_exists( 'wfScanEngine', 'startScan' ) ) {
			throw new \Exception( 'Wordfence scan engine is not available in this version.' );
		}

		if ( class_exists( 'wfScanner' ) && method_exists( 'wfScanner', 'shared' ) ) {
			$scanner = \wfScanner::shared();
			if ( method_exists( $scanner, 'isRunning' ) && $scanner->isRunning() ) {
				throw new \Exception( 'A Wordfence scan is already running. Wait for it to finish before starting another.' );
			}
		}

		
		
		\wfScanEngine::startScan( false, false );

		return [
			'success' => true,
			'message' => 'Wordfence scan started. It runs in the background; poll wf_get_security_status (scan_running) and read wf_get_scan_results once it completes.',
		];
	}

	private static function last_scan_time_string() {
		if ( class_exists( 'wfScanner' ) && method_exists( 'wfScanner', 'shared' ) ) {
			$scanner = \wfScanner::shared();
			if ( method_exists( $scanner, 'lastScanTime' ) ) {
				$t = $scanner->lastScanTime();
				return $t ? gmdate( 'Y-m-d H:i:s', (int) $t ) : null;
			}
		}
		return null;
	}
}
