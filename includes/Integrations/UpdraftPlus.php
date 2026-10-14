<?php
namespace Mordenhost_MCP\Integrations;

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
				'description' => 'Check whether an UpdraftPlus backup is running or queued. Returns a running flag with UpdraftPlus\'s own status token, the backups queued in WP-Cron but not started yet, and, for a job_id (the one up_start_backup returned, or the running job), that job\'s state: queued, running (with UpdraftPlus\'s job stage), finished (with success and error count when it was the last run) or unknown. Never returns remote-storage settings. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'job_id' => [ 'type' => 'string', 'description' => 'Job id returned by up_start_backup (12 hex characters). Optional.' ],
					],
				],
			],
			[
				'name'        => 'up_get_schedule',
				'description' => 'Read the UpdraftPlus automatic-backup schedule: the files and database intervals, retain counts, which entities are included, and the next runs from wp-cron state. Intervals and next runs only, never a remote-storage credential. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'up_start_backup',
				'description' => 'Start a new UpdraftPlus backup: queues UpdraftPlus\'s own "Backup Now" action as a one-off WP-Cron event, so the backup runs in the background the way its scheduled backups do and this call returns at once with a job_id. Remote storage is used as configured in UpdraftPlus. A backup consumes real disk, CPU, and network, so this is a two-part confirmation: call without confirm to receive a preview of what would run and write nothing; call with confirm=true AND confirm_components matching the components value to actually queue it (dry_run=true always previews). Refuses if a backup is already running or queued. Follow it with up_get_backup_status and the job_id.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'components'         => [ 'type' => 'string', 'description' => 'What to back up: full (files + database), files, or database. Defaults to full.', 'enum' => [ 'full', 'files', 'database' ] ],
						'confirm'            => [ 'type' => 'boolean', 'description' => 'Must be true to actually start the backup. Omit or false to receive a preview instead. That preview is the intended first call.' ],
						'confirm_components' => [ 'type' => 'string', 'description' => 'Repeat the components value. Required alongside confirm=true; cannot be satisfied without having read the preview.', 'enum' => [ 'full', 'files', 'database' ] ],
						'dry_run'            => [ 'type' => 'boolean', 'description' => 'true returns the preview and queues nothing, even with confirm=true.' ],
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
				return self::get_backup_status( is_array( $args ) ? $args : [] );

			case 'up_get_schedule':
				return self::get_schedule();

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

	private static function get_backup_status( array $args = [] ) {
		global $updraftplus;
		if ( ! is_object( $updraftplus ) || ! method_exists( $updraftplus, 'is_backup_running' ) ) {
			throw new \Exception( 'UpdraftPlus runtime object is not available; cannot read backup status.' );
		}

		$job_id = '';
		if ( isset( $args['job_id'] ) && '' !== $args['job_id'] ) {
			$job_id = is_string( $args['job_id'] ) ? strtolower( trim( $args['job_id'] ) ) : '';
			if ( ! preg_match( '/^[a-f0-9]{12}$/', $job_id ) ) {
				throw new \Exception( 'job_id must be the 12-character id up_start_backup returned.' );
			}
		}

		$running = $updraftplus->is_backup_running();
		if ( '' === $job_id && is_string( $running ) && preg_match( '/^job_scheduled_([a-f0-9]+)_/', $running, $m ) ) {
			$job_id = $m[1];
		}
		$queued = self::queued_jobs();

		$result = [
			'running' => false !== $running,
			'status'  => false === $running ? 'idle' : (string) $running,
			'queued'  => $queued,
		];
		if ( '' !== $job_id ) {
			$result['job'] = self::job_summary( $job_id, $queued );
		}
		return $result;
	}

	private static function queued_jobs() {
		$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
		$out   = [];
		foreach ( (array) $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( self::BACKUP_ACTIONS as $components => $hook ) {
				if ( empty( $hooks[ $hook ] ) || ! is_array( $hooks[ $hook ] ) ) {
					continue;
				}
				foreach ( $hooks[ $hook ] as $event ) {
					$options = is_array( $event ) && isset( $event['args'][0] ) && is_array( $event['args'][0] ) ? $event['args'][0] : [];
					$job     = isset( $options['use_nonce'] ) && is_string( $options['use_nonce'] ) && preg_match( '/^[a-f0-9]{12}$/', $options['use_nonce'] ) ? $options['use_nonce'] : null;
					$out[]   = [
						'job_id'          => $job,
						'components'      => $components,
						'scheduled_for'   => gmdate( 'c', (int) $timestamp ),
						'overdue_seconds' => max( 0, time() - (int) $timestamp ),
					];
				}
			}
		}
		return $out;
	}

	private static function job_summary( $job_id, array $queued ) {
		global $updraftplus;
		$jobdata = [];
		if ( is_object( $updraftplus ) && method_exists( $updraftplus, 'jobdata_getarray' ) ) {
			$jobdata = $updraftplus->jobdata_getarray( $job_id );
		} elseif ( function_exists( 'get_site_option' ) ) {
			$jobdata = get_site_option( 'updraft_jobdata_' . $job_id, [] );
		}
		$jobdata = is_array( $jobdata ) ? $jobdata : [];

		$in_queue   = in_array( $job_id, array_column( $queued, 'job_id' ), true );
		$in_history = false;
		if ( class_exists( 'UpdraftPlus_Backup_History' ) && method_exists( 'UpdraftPlus_Backup_History', 'get_history' ) ) {
			foreach ( (array) \UpdraftPlus_Backup_History::get_history() as $set ) {
				if ( is_array( $set ) && isset( $set['nonce'] ) && (string) $set['nonce'] === $job_id ) {
					$in_history = true;
					break;
				}
			}
		}
		$last     = self::get_option( 'updraft_last_backup' );
		$last_run = null;
		if ( is_array( $last ) && isset( $last['backup_nonce'] ) && (string) $last['backup_nonce'] === $job_id ) {
			$last_run = [
				'success'     => ! empty( $last['success'] ),
				'error_count' => isset( $last['errors'] ) && is_array( $last['errors'] ) ? count( $last['errors'] ) : 0,
			];
		}

		$jobstatus = isset( $jobdata['jobstatus'] ) && is_string( $jobdata['jobstatus'] ) ? sanitize_key( $jobdata['jobstatus'] ) : null;
		if ( $jobdata ) {
			$state = 'finished' === $jobstatus ? 'finished' : 'running';
		} elseif ( $in_queue ) {
			$state = 'queued';
		} elseif ( null !== $last_run || $in_history ) {
			$state = 'finished';
		} else {
			$state = 'unknown';
		}

		$notes = [
			'queued'   => 'Waiting in WP-Cron. It starts on the next cron run.',
			'running'  => 'UpdraftPlus is working on it; jobstatus is its own stage name.',
			'finished' => 'Finished. up_get_backups lists the set; up_get_last_backup has its errors.',
			'unknown'  => 'No queued event, progress record or backup set carries this id: a wrong id, or a job that ended without a set (see up_get_last_backup).',
		];

		return [
			'job_id'               => $job_id,
			'state'                => $state,
			'jobstatus'            => $jobstatus,
			'job_type'             => isset( $jobdata['job_type'] ) && is_string( $jobdata['job_type'] ) ? sanitize_key( $jobdata['job_type'] ) : null,
			'includes_database'    => isset( $jobdata['backup_database'] ) ? 'no' !== $jobdata['backup_database'] : null,
			'includes_files'       => isset( $jobdata['backup_files'] ) ? 'no' !== $jobdata['backup_files'] : null,
			'label'                => isset( $jobdata['label'] ) && is_string( $jobdata['label'] ) ? sanitize_text_field( $jobdata['label'] ) : null,
			'started_at'           => isset( $jobdata['job_time_ms'] ) && is_numeric( $jobdata['job_time_ms'] ) ? gmdate( 'c', (int) $jobdata['job_time_ms'] ) : null,
			'resumptions_recorded' => isset( $jobdata['run_times'] ) && is_array( $jobdata['run_times'] ) ? count( $jobdata['run_times'] ) : null,
			'in_backup_history'    => $in_history,
			'last_run'             => $last_run,
			'note'                 => $notes[ $state ],
		];
	}

	private static function get_schedule() {
		$files_interval = self::get_option( 'updraft_interval' );
		$db_interval    = self::get_option( 'updraft_interval_database' );
		$db_interval    = null === $db_interval ? $files_interval : $db_interval;

		$next_files = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'updraft_backup' ) : false;
		$next_db    = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'updraft_backup_database' ) : false;

		$entities = array( 'plugins', 'themes', 'uploads', 'others' );
		$includes = array();
		foreach ( $entities as $entity ) {
			$includes[ $entity ] = (bool) self::get_option( 'updraft_include_' . $entity );
		}

		return [
			'provider'       => 'updraftplus',
			'files_interval' => null === $files_interval ? null : (string) $files_interval,
			'db_interval'    => null === $db_interval ? null : (string) $db_interval,
			'retain_files'   => is_numeric( self::get_option( 'updraft_retain' ) ) ? (int) self::get_option( 'updraft_retain' ) : null,
			'retain_db'      => is_numeric( self::get_option( 'updraft_retain_db' ) ) ? (int) self::get_option( 'updraft_retain_db' ) : null,
			'includes'       => $includes,
			'next_files'     => false === $next_files || ! is_numeric( $next_files ) ? null : (int) $next_files,
			'next_files_iso' => false === $next_files || ! is_numeric( $next_files ) ? null : gmdate( 'c', (int) $next_files ),
			'next_db'        => false === $next_db || ! is_numeric( $next_db ) ? null : (int) $next_db,
			'next_db_iso'    => false === $next_db || ! is_numeric( $next_db ) ? null : gmdate( 'c', (int) $next_db ),
		];
	}

	private static function start_backup( $args ) {
		$args       = is_array( $args ) ? $args : [];
		$components = isset( $args['components'] ) ? sanitize_key( $args['components'] ) : 'full';
		if ( ! isset( self::BACKUP_ACTIONS[ $components ] ) ) {
			throw new \Exception( 'Unknown components value: ' . esc_html( $components ) . '. Use full, files, or database.' );
		}

		
		$dry_run   = \Mordenhost_MCP\Access\Destructive::is_dry_run( 'up_start_backup', $args );
		$confirmed = ! $dry_run
			&& ! empty( $args['confirm'] )
			&& isset( $args['confirm_components'] )
			&& sanitize_key( $args['confirm_components'] ) === $components;

		if ( ! $confirmed ) {
			
			return [
				'preview'    => true,
				'components' => $components,
				'message'    => 'Preview only. To start the backup, call again with confirm=true and confirm_components=' . $components . '. It is queued in WP-Cron and runs in the background, consuming disk, CPU, and network, and goes to remote storage as configured in UpdraftPlus. Restore and deletion are not available through this tool.',
			];
		}

		
		
		global $updraftplus;
		if ( is_object( $updraftplus ) && method_exists( $updraftplus, 'is_backup_running' ) && false !== $updraftplus->is_backup_running() ) {
			throw new \Exception( 'A backup is already in progress. Wait for it to finish before starting another.' );
		}
		$waiting = self::queued_jobs();
		if ( $waiting ) {
			throw new \Exception( 'A backup is already queued (job ' . esc_html( (string) ( $waiting[0]['job_id'] ?? 'unknown' ) ) . ', due ' . esc_html( $waiting[0]['scheduled_for'] ) . '). Poll up_get_backup_status instead of queueing another.' );
		}
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			throw new \Exception( 'WP-Cron is not available, so the backup cannot be queued.' );
		}

		
		$job_id  = bin2hex( random_bytes( 6 ) );
		$options = [
			'nocloud'   => 0,
			'use_nonce' => $job_id,
		];
		$when    = time();
		$result  = wp_schedule_single_event( $when, self::BACKUP_ACTIONS[ $components ], [ $options ], true );
		if ( true !== $result ) {
			$why = is_object( $result ) && method_exists( $result, 'get_error_message' ) ? ' ' . $result->get_error_message() : '';
			throw new \Exception( 'WP-Cron refused to queue the backup.' . esc_html( $why ) );
		}

		
		
		$spawned = false;
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$cron_note = 'WP-Cron is disabled on this site (DISABLE_WP_CRON), so the backup starts at the next server cron run.';
		} elseif ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			$cron_note = 'ALTERNATE_WP_CRON is set, so the backup starts on the next page load.';
		} else {
			$spawned   = function_exists( 'spawn_cron' ) && false !== spawn_cron();
			$cron_note = $spawned ? 'WP-Cron was asked to run now.' : 'WP-Cron did not start now (it may already be running); the backup starts on its next run.';
		}

		return [
			'success'       => true,
			'queued'        => true,
			'job_id'        => $job_id,
			'components'    => $components,
			'scheduled_for' => gmdate( 'c', $when ),
			'cron_spawned'  => $spawned,
			'cron_note'     => $cron_note,
			'message'       => 'UpdraftPlus backup queued (' . $components . '). It runs in the background through WP-Cron; poll up_get_backup_status with job_id=' . $job_id . ' for progress and up_get_backups once it completes.',
		];
	}

	private static function get_option( $name ) {
		if ( class_exists( 'UpdraftPlus_Options' ) && method_exists( 'UpdraftPlus_Options', 'get_updraft_option' ) ) {
			return \UpdraftPlus_Options::get_updraft_option( $name );
		}
		return get_option( $name );
	}
}
