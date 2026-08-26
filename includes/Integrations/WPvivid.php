<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPvivid {

	public static function is_available() {
		return defined( 'WPVIVID_PLUGIN_VERSION' ) && class_exists( 'WPvivid_Backuplist' ) && class_exists( 'WPvivid_taskmanager' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wpvivid' ),
			'capabilities' => array( 'backup' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wpvivid_get_backups',
				'description' => 'List WPvivid backups, newest first: id, backup type, creation time, name prefix (if set), security-lock flag (if set), and computed total size in bytes. Never returns local storage paths, the log file path, or remote-destination detail. Use this to answer "is there a recent recoverable WPvivid backup?" before risky maintenance.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of backups to return (default 20, max 50).' ],
					],
				],
			],
			[
				'name'        => 'wpvivid_get_status',
				'description' => 'Get a WPvivid overview: plugin version, whether a backup task is currently running, and the total number of stored backups. Read-only; there is no start-backup tool because WPvivid backups run through a multi-step AJAX task-queue protocol with no single safe entry point.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use WPvivid tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WPvivid is not active' );
		}

		switch ( $name ) {
			case 'wpvivid_get_backups':
				return self::get_backups( $args );

			case 'wpvivid_get_status':
				return self::get_status();

			default:
				throw new \Exception( 'Unknown WPvivid tool: ' . esc_html( $name ) );
		}
	}

	private static function get_backups( $args ) {
		$limit = min( max( 1, intval( $args['limit'] ?? 20 ) ), 50 );
		$list  = \WPvivid_Backuplist::get_backuplist();
		$list  = is_array( $list ) ? $list : [];

		$out = [];
		foreach ( $list as $id => $entry ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$out[] = [
				'id'             => (string) $id,
				'type'           => isset( $entry['type'] ) ? sanitize_text_field( (string) $entry['type'] ) : '',
				'create_time'    => isset( $entry['create_time'] ) ? (int) $entry['create_time'] : null,
				'backup_prefix'  => isset( $entry['backup_prefix'] ) ? sanitize_text_field( (string) $entry['backup_prefix'] ) : '',
				'locked'         => ! empty( $entry['lock'] ),
				'size_bytes'     => (int) \WPvivid_Backuplist::get_size( $id ),
			];
		}

		return [
			'provider' => 'wpvivid',
			'count'    => count( $out ),
			'total'    => count( $list ),
			'backups'  => $out,
		];
	}

	private static function get_status() {
		$list    = \WPvivid_Backuplist::get_backuplist();
		$list    = is_array( $list ) ? $list : [];
		$running = method_exists( 'WPvivid_taskmanager', 'is_tasks_backup_running' ) ? (bool) \WPvivid_taskmanager::is_tasks_backup_running() : null;

		return [
			'provider'        => 'wpvivid',
			'plugin_version'  => defined( 'WPVIVID_PLUGIN_VERSION' ) ? WPVIVID_PLUGIN_VERSION : '',
			'backup_running'  => $running,
			'total_backups'   => count( $list ),
		];
	}
}
