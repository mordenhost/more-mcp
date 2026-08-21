<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdraftPlus {

	private const BACKUP_ACTIONS = [
		'full'     => 'updraft_backupnow_backup_all',
		'files'    => 'updraft_backupnow_backup',
		'database' => 'updraft_backupnow_backup_database',
	];

	public static function is_available() {
		return class_exists( 'UpdraftPlus' ) || class_exists( 'UpdraftPlus_Backup_History' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'updraftplus' ),
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
				'name'        => 'up_get_backups',
				'description' => 'List known UpdraftPlus backup sets, newest first. Each entry reports its backup time, which components it contains (database, plugins, themes, uploads, others), the total size on disk of the local archives, and whether the set looks complete. Use this to answer "is there a recent recoverable backup?" before risky maintenance. Does not expose remote-storage credentials.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of backup sets to return (default 20, max 50).' ],
					],
				],
			],
			[
				'name'        => 'up_get_last_backup',
				'description' => 'Get a summary of the most recent UpdraftPlus backup run: when it ran, whether it succeeded, which components were included, and any recorded errors. Reads the updraft_last_backup option.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'up_get_backup_status',
				'description' => 'Check whether an UpdraftPlus backup is currently running. Returns a running flag and, when a job is active, UpdraftPlus\'s own status token describing why it is still considered in progress.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'up_start_backup',
				'description' => 'Start a new UpdraftPlus backup now (runs asynchronously via WP-Cron, exactly as the "Backup Now" button does). A backup consumes real disk, CPU, and network, so this is a two-part confirmation: call without confirm to receive a preview of what would run and write nothing; call with confirm=true AND confirm_components matching the components value to actually start it. Refuses if a backup is already running.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'components'         => [ 'type' => 'string', 'description' => 'What to back up: full (files + database), files, or database. Defaults to full.', 'enum' => [ 'full', 'files', 'database' ] ],
						'confirm'            => [ 'type' => 'boolean', 'description' => 'Must be true to actually start the backup. Omit or false to receive a preview instead. That preview is the intended first call.' ],
						'confirm_components' => [ 'type' => 'string', 'description' => 'Repeat the components value. Required alongside confirm=true; cannot be satisfied without having read the preview.', 'enum' => [ 'full', 'files', 'database' ] ],
					],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use UpdraftPlus tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'UpdraftPlus is not active' );
		}

		switch ( $name ) {
			case 'up_get_backups':
				return self::get_backups( $args );

			case 'up_get_last_backup':
				return self::get_last_backup();

			case 'up_get_backup_status':
				return self::get_backup_status();

			case 'up_start_backup':
				return self::start_backup( $args );

			default:
				throw new \Exception( 'Unknown UpdraftPlus tool: ' . esc_html( $name ) );
		}
	}

	private static function get_backups( $args ) {
		if ( ! class_exists( 'UpdraftPlus_Backup_History' ) || ! method_exists( 'UpdraftPlus_Backup_History', 'get_history' ) ) {
			throw new \Exception( 'UpdraftPlus backup history is not available in this version.' );
		}

		$limit   = min( max( 1, intval( $args['limit'] ?? 20 ) ), 50 );
		$history = \UpdraftPlus_Backup_History::get_history();
		if ( ! is_array( $history ) ) {
			$history = [];
		}

		$out = [];
		foreach ( $history as $timestamp => $set ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$out[] = self::format_backup_set( (int) $timestamp, is_array( $set ) ? $set : [] );
		}

		return [
			'total'   => count( $history ),
			'limit'   => $limit,
			'backups' => $out,
		];
	}

	private static function format_backup_set( $timestamp, array $set ) {

		$entities   = [ 'db', 'plugins', 'themes', 'uploads', 'others' ];
		$components = [];
		$total_size = 0;

		foreach ( $entities as $entity ) {
			if ( empty( $set[ $entity ] ) ) {
				continue;
			}
			$components[] = 'db' === $entity ? 'database' : $entity;

			
			
			foreach ( $set as $key => $value ) {
				if ( is_numeric( $value ) && preg_match( '/^' . preg_quote( $entity, '/' ) . '\d*-size$/', (string) $key ) ) {
					$total_size += (int) $value;
				}
			}
		}

		$nonce = isset( $set['nonce'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) $set['nonce'] ) : '';

		return [
			'backup_time'      => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'timestamp'        => $timestamp,
			'nonce'            => $nonce,
			'components'       => $components,
			'has_database'     => in_array( 'database', $components, true ),
			'local_size_bytes' => $total_size,
			'local_size_human' => size_format( $total_size ),
			'label'            => isset( $set['label'] ) ? sanitize_text_field( (string) $set['label'] ) : '',
		];
	}

	private static function get_last_backup() {
		$last = self::get_option( 'updraft_last_backup' );
		if ( ! is_array( $last ) || empty( $last ) ) {
			return [ 'has_last_backup' => false ];
		}

		
		$errors = [];
		if ( ! empty( $last['errors'] ) && is_array( $last['errors'] ) ) {
			foreach ( $last['errors'] as $err ) {
				if ( is_string( $err ) ) {
					$errors[] = $err;
				} elseif ( is_array( $err ) && isset( $err['message'] ) ) {
					$errors[] = (string) $err['message'];
				}
			}
		}

		$backup_time = null;
		if ( ! empty( $last['backup_time'] ) ) {
			$backup_time = gmdate( 'Y-m-d H:i:s', (int) $last['backup_time'] );
		} elseif ( ! empty( $last['nonincremental_backup_time'] ) ) {
			$backup_time = gmdate( 'Y-m-d H:i:s', (int) $last['nonincremental_backup_time'] );
		}

		return [
			'has_last_backup' => true,
			'backup_time'     => $backup_time,
			'success'         => ! empty( $last['success'] ),
			'error_count'     => count( $errors ),
			'errors'          => $errors,
		];
	}

	private static function get_backup_status() {
		global $updraftplus;
		if ( ! is_object( $updraftplus ) || ! method_exists( $updraftplus, 'is_backup_running' ) ) {
			throw new \Exception( 'UpdraftPlus runtime object is not available; cannot read backup status.' );
		}

		$running = $updraftplus->is_backup_running();

		return [
			'running'    => false !== $running,
			'status'     => false === $running ? 'idle' : (string) $running,
		];
	}

	private static function start_backup( $args ) {
		$components = isset( $args['components'] ) ? sanitize_key( $args['components'] ) : 'full';
		if ( ! isset( self::BACKUP_ACTIONS[ $components ] ) ) {
			throw new \Exception( 'Unknown components value: ' . esc_html( $components ) . '. Use full, files, or database.' );
		}

		$confirmed = ! empty( $args['confirm'] )
			&& isset( $args['confirm_components'] )
			&& sanitize_key( $args['confirm_components'] ) === $components;

		if ( ! $confirmed ) {
			
			return [
				'preview'    => true,
				'components' => $components,
				'message'    => 'Preview only. To start the backup, call again with confirm=true and confirm_components=' . $components . '. The backup runs asynchronously and consumes disk, CPU, and network. Restore and deletion are not available through this tool.',
			];
		}

		
		global $updraftplus;
		if ( is_object( $updraftplus ) && method_exists( $updraftplus, 'is_backup_running' ) && false !== $updraftplus->is_backup_running() ) {
			throw new \Exception( 'A backup is already in progress. Wait for it to finish before starting another.' );
		}

		
		
		do_action( self::BACKUP_ACTIONS[ $components ], [ 'nocloud' => 0 ] );

		return [
			'success'    => true,
			'components' => $components,
			'message'    => 'UpdraftPlus backup started (' . $components . '). It runs in the background; poll up_get_backup_status for progress and up_get_backups once it completes.',
		];
	}

	private static function get_option( $name ) {
		if ( class_exists( 'UpdraftPlus_Options' ) && method_exists( 'UpdraftPlus_Options', 'get_updraft_option' ) ) {
			return \UpdraftPlus_Options::get_updraft_option( $name );
		}
		return get_option( $name );
	}
}
