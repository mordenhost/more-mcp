<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GuardPress MCP Integration
 *
 * Registers MCP tools for GuardPress security features.
 * Only loaded when GuardPress is active.
 */
class GuardPress {

	public static function is_available() {
		return class_exists( 'GuardPress' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'gp_get_security_status',
				'description' => 'Get current security score, grade, and factor breakdown',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'gp_get_security_stats',
				'description' => 'Get security statistics (failed logins, blocked IPs, alerts, etc) for a time period',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'period' => [ 'type' => 'string', 'description' => 'Time period: day, week, month', 'enum' => [ 'day', 'week', 'month' ] ],
					],
				],
			],
			[
				'name'        => 'gp_run_vulnerability_scan',
				'description' => 'Run a vulnerability scan checking for outdated plugins, themes, and security misconfigurations',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'gp_get_vulnerability_results',
				'description' => 'Get the latest vulnerability scan results',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'gp_get_failed_logins',
				'description' => 'Get failed login attempt statistics and top offending IPs',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'period' => [ 'type' => 'string', 'description' => 'Time period: day, week, month', 'enum' => [ 'day', 'week', 'month' ] ],
					],
				],
			],
			[
				'name'        => 'gp_get_blocked_ips',
				'description' => 'Get list of currently blocked IP addresses',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'gp_get_audit_log',
				'description' => 'Get recent security audit log entries',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'    => [ 'type' => 'integer', 'description' => 'Number of entries (max 100)' ],
						'severity' => [ 'type' => 'string', 'description' => 'Filter by severity', 'enum' => [ 'info', 'warning', 'critical' ] ],
					],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {
		// cap check fires BEFORE the active-check. Without this order
		// a Subscriber-tier OAuth Bearer would get "GuardPress is not active"
		// and learn whether the integration is present. All GuardPress tools
		// are admin-tier (security state — failed-login lists, blocked IPs,
		// audit logs, vulnerability scan results — is sensitive); manage_options
		// matches GuardPress's own admin-screen gating.
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use GuardPress security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'GuardPress is not active' );
		}

		$guardpress = \GuardPress::get_instance();

		switch ( $name ) {
			case 'gp_get_security_status':
				if ( ! method_exists( 'GuardPress_Settings', 'get_security_score' ) ) {
					throw new \Exception( 'Security score not available in this version of GuardPress' );
				}
				return \GuardPress_Settings::get_security_score();

			case 'gp_get_security_stats':
				if ( ! method_exists( 'GuardPress_Logger', 'get_security_stats' ) ) {
					throw new \Exception( 'Security stats not available' );
				}
				$period = in_array( $args['period'] ?? 'week', [ 'day', 'week', 'month' ] ) ? $args['period'] : 'week';
				return \GuardPress_Logger::get_security_stats( $period );

			case 'gp_run_vulnerability_scan':
				$scanner = $guardpress->get_module( 'vulnerability-scanner' );
				if ( ! $scanner ) {
					throw new \Exception( 'Vulnerability scanner module not available' );
				}
				$results = $scanner->run_scan();
				$stats   = $scanner->get_statistics();
				return [
					'message'        => 'Vulnerability scan completed',
					'vulnerabilities_found' => is_array( $results ) ? count( $results ) : 0,
					'statistics'     => $stats,
				];

			case 'gp_get_vulnerability_results':
				$scanner = $guardpress->get_module( 'vulnerability-scanner' );
				if ( ! $scanner ) {
					throw new \Exception( 'Vulnerability scanner module not available' );
				}
				$results = $scanner->get_results();
				$stats   = $scanner->get_statistics();
				return [
					'last_scan'  => $scanner->get_last_scan(),
					'statistics' => $stats,
					'results'    => is_array( $results ) ? $results : [],
				];

			case 'gp_get_failed_logins':
				$brute_force = $guardpress->get_module( 'brute-force' );
				if ( ! $brute_force || ! method_exists( $brute_force, 'get_statistics' ) ) {
					throw new \Exception( 'Brute force module not available' );
				}
				$period = in_array( $args['period'] ?? 'week', [ 'day', 'week', 'month' ] ) ? $args['period'] : 'week';
				return $brute_force->get_statistics( $period );

			case 'gp_get_blocked_ips':
				$brute_force = $guardpress->get_module( 'brute-force' );
				if ( ! $brute_force || ! method_exists( $brute_force, 'get_blocked_ips' ) ) {
					throw new \Exception( 'Brute force module not available' );
				}
				$blocked = $brute_force->get_blocked_ips();
				return array_map( function( $ip ) {
					return [
						'ip_address'  => $ip->ip_address,
						'blocked_at'  => $ip->blocked_at,
						'reason'      => $ip->reason ?? 'brute_force',
						'is_permanent' => ! empty( $ip->is_permanent ),
					];
				}, $blocked );

			case 'gp_get_audit_log':
				if ( ! method_exists( 'GuardPress_Logger', 'get_audit_logs' ) ) {
					throw new \Exception( 'Audit log not available' );
				}
				$log_args = [
					'limit' => min( intval( $args['limit'] ?? 50 ), 100 ),
				];
				if ( ! empty( $args['severity'] ) ) {
					$log_args['severity'] = sanitize_text_field( $args['severity'] );
				}
				$logs = \GuardPress_Logger::get_audit_logs( $log_args );
				return array_map( function( $log ) {
					return [
						'action'      => $log->action,
						'description' => $log->description,
						'severity'    => $log->severity,
						'ip_address'  => $log->ip_address,
						'username'    => $log->username,
						'created_at'  => $log->created_at,
					];
				}, $logs );

			default:
				throw new \Exception( 'Unknown GuardPress tool: ' . esc_html( $name ) );
		}
	}
}
