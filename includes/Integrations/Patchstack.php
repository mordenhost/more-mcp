<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Patchstack {

	public static function is_available() {

		
		return class_exists( '\Patchstack' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'patchstack' ),
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
				'name'        => 'patchstack_get_status',
				'description' => 'Patchstack security overview: plugin version, license edition (free/paid), last successful cloud-sync time, the locally mirrored count of known vulnerabilities affecting installed plugins/themes/core and available fixes, the number of entries in the IP block list, and aggregate counts of blocked requests (firewall log) and failed logins (event log). Vulnerability and fix counts are a local mirror written by Patchstack after each cloud sync; this tool never calls the Patchstack API. Aggregate counts only — never an IP address, user agent, request URI, or request body. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'patchstack_get_events',
				'description' => 'Aggregate view of Patchstack\'s local event log: counts of recorded site events grouped by the plugin\'s own object vocabulary (post, plugin, user, comment, attachment, core, option) and by action vocabulary (created, updated, deleted, logged in, failed login, password reset, etc.), plus the total event count. Grouped counts only — never an event\'s author, IP address, object name, or object id. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Patchstack tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Patchstack is not active.' );
		}
		if ( 'patchstack_get_status' === $name ) {
			return self::get_status();
		}
		if ( 'patchstack_get_events' === $name ) {
			return self::get_events();
		}
		throw new \Exception( 'Unknown Patchstack tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
		global $wpdb;

		$vulns = get_option( 'patchstack_vulns_present', null );
		$fixes = get_option( 'patchstack_fixes_present', null );

		
		
		$last_sync = (int) get_option( 'patchstack_last_sync', 0 );
		if ( $last_sync <= 0 ) {
			$last_sync = (int) get_option( 'patchstack_last_license_check', 0 );
		}

		
		
		$vuln_state = array(
			'count'     => is_numeric( $vulns ) ? (int) $vulns : null,
			'fix_count' => is_numeric( $fixes ) ? (int) $fixes : null,
			'synced'    => null !== $vulns && '?' !== $vulns,
		);

		return array(
			'provider'      => 'patchstack',
			'version'       => defined( 'Patchstack::VERSION' ) ? constant( 'Patchstack::VERSION' ) : null,
			'edition'       => (string) get_option( 'patchstack_license_free', '0' ) === '1' ? 'free' : 'paid',
			'last_sync'     => $last_sync > 0 ? $last_sync : null,
			'last_sync_iso' => $last_sync > 0 ? gmdate( 'c', $last_sync ) : null,
			'vulnerabilities' => $vuln_state,
			
			'blocked_ip_entries' => self::count_block_list(),
			'firewall'      => array(
				'blocked_requests' => self::table_count( 'patchstack_firewall_log' ),
				'available'        => self::table_exists( 'patchstack_firewall_log' ),
			),
			'events'        => array(
				'failed_logins' => self::failed_login_count(),
				'available'     => self::table_exists( 'patchstack_event_log' ),
			),
		);
	}

	private static function get_events() {
		global $wpdb;
		if ( ! self::table_exists( 'patchstack_event_log' ) ) {
			return array(
				'provider'  => 'patchstack',
				'available' => false,
				'message'   => 'The Patchstack event log table does not exist (no events recorded yet).',
				'total'     => 0,
				'by_object' => array(),
				'by_action' => array(),
			);
		}

		$table = $wpdb->prefix . 'patchstack_event_log';
		
		$rows = $wpdb->get_results(
			"SELECT object, action, COUNT(*) AS n FROM {$table} GROUP BY object, action",
			\ARRAY_A
		);

		$by_object = array();
		$by_action = array();
		$total     = 0;
		foreach ( (array) $rows as $row ) {
			$object = isset( $row['object'] ) ? (string) $row['object'] : '';
			$action = isset( $row['action'] ) ? (string) $row['action'] : '';
			$n      = isset( $row['n'] ) ? (int) $row['n'] : 0;
			$total += $n;
			if ( '' !== $object ) {
				$by_object[ $object ] = ( $by_object[ $object ] ?? 0 ) + $n;
			}
			if ( '' !== $action ) {
				$by_action[ $action ] = ( $by_action[ $action ] ?? 0 ) + $n;
			}
		}

		return array(
			'provider'  => 'patchstack',
			'available' => true,
			'total'     => $total,
			'by_object' => $by_object,
			'by_action' => $by_action,
		);
	}

	private static function count_block_list() {
		$list = (string) get_option( 'patchstack_ip_block_list', '' );
		if ( '' === trim( $list ) ) {
			return 0;
		}
		$lines = array_filter( array_map( 'trim', explode( "\n", $list ) ) );
		return count( $lines );
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function table_count( $suffix ) {
		global $wpdb;
		if ( ! self::table_exists( $suffix ) ) {
			return null;
		}
		
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $suffix );
	}

	private static function failed_login_count() {
		global $wpdb;
		if ( ! self::table_exists( 'patchstack_event_log' ) ) {
			return null;
		}
		
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE action = %s', 'failed login' )
		);
	}
}
