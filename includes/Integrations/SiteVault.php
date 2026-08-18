<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SiteVault MCP Integration
 *
 * Registers MCP tools for SiteVault backup management.
 * Only loaded when SiteVault Pro is active.
 */
class SiteVault {

	public static function is_available() {
		return class_exists( 'RB_Backup_Manager' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'sv_get_backups',
				'description' => 'List available SiteVault backups',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'  => [ 'type' => 'integer', 'description' => 'Number of backups to return (max 50)' ],
						'status' => [ 'type' => 'string', 'description' => 'Filter by status', 'enum' => [ 'completed', 'failed', 'in_progress' ] ],
						'type'   => [ 'type' => 'string', 'description' => 'Filter by backup type', 'enum' => [ 'full', 'database', 'files', 'plugins', 'themes', 'uploads' ] ],
					],
				],
			],
			[
				'name'        => 'sv_get_backup',
				'description' => 'Get details of a specific backup by ID',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Backup ID' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'sv_create_backup',
				'description' => 'Trigger a new backup (runs asynchronously)',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'type' => [ 'type' => 'string', 'description' => 'Backup type', 'enum' => [ 'full', 'database', 'files', 'plugins', 'themes', 'uploads' ] ],
						'name' => [ 'type' => 'string', 'description' => 'Backup name (optional)' ],
					],
				],
			],
			[
				'name'        => 'sv_get_backup_status',
				'description' => 'Check the progress of an in-progress backup',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Backup ID' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'sv_get_backup_stats',
				'description' => 'Get overall backup statistics (total count, size, last backup)',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'sv_get_schedules',
				'description' => 'List backup schedules',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {
		// cap check fires BEFORE the active-check, so a Subscriber-tier
		// OAuth Bearer gets a permission error rather than "SiteVault is not
		// active" (which leaks integration presence). All SiteVault tools are
		// admin-tier — backups can contain the entire site (DB + uploads +
		// plugins) and even read-only listings expose backup contents,
		// schedules, and storage destinations.
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SiteVault tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'SiteVault is not active' );
		}

		$manager = \RB_Backup_Manager::instance();

		switch ( $name ) {
			case 'sv_get_backups':
				$query_args = [
					'limit' => min( intval( $args['limit'] ?? 20 ), 50 ),
				];
				if ( ! empty( $args['status'] ) ) {
					$query_args['status'] = sanitize_text_field( $args['status'] );
				}
				if ( ! empty( $args['type'] ) ) {
					$query_args['type'] = sanitize_text_field( $args['type'] );
				}
				$backups = $manager->get_backups( $query_args );
				return array_map( [ __CLASS__, 'format_backup' ], $backups );

			case 'sv_get_backup':
				$backup = $manager->get_backup( intval( $args['id'] ) );
				if ( ! $backup ) {
					throw new \Exception( 'Backup not found' );
				}
				return self::format_backup( $backup );

			case 'sv_create_backup':
				$backup_args = [
					'type' => in_array( $args['type'] ?? 'full', [ 'full', 'database', 'files', 'plugins', 'themes', 'uploads' ] ) ? $args['type'] : 'full',
				];
				if ( ! empty( $args['name'] ) ) {
					$backup_args['name'] = sanitize_text_field( $args['name'] );
				}

				// Check if a backup is already running
				if ( $manager->has_active_backup() ) {
					throw new \Exception( 'A backup is already in progress. Please wait for it to complete.' );
				}

				// Use async backup to avoid timeout
				if ( class_exists( 'RB_Async_Backup' ) ) {
					$backup_id = \RB_Async_Backup::instance()->start_backup( $backup_args );
				} else {
					$backup_id = \RB_Backup_Engine::instance()->create_backup( $backup_args );
				}

				if ( is_wp_error( $backup_id ) ) {
					throw new \Exception( esc_html( $backup_id->get_error_message() ) );
				}

				return [
					'id'      => $backup_id,
					'message' => 'Backup started successfully. Use sv_get_backup_status to check progress.',
					'type'    => $backup_args['type'],
				];

			case 'sv_get_backup_status':
				$backup_id = intval( $args['id'] );
				$backup    = $manager->get_backup( $backup_id );
				if ( ! $backup ) {
					throw new \Exception( 'Backup not found' );
				}

				$result = [
					'id'     => $backup_id,
					'status' => $backup->status,
					'type'   => $backup->backup_type,
				];

				// If in progress, get detailed progress
				if ( $backup->status === 'in_progress' && class_exists( 'RB_Async_Backup' ) ) {
					$progress = \RB_Async_Backup::instance()->get_status( $backup_id );
					if ( is_array( $progress ) ) {
						$result['percent'] = $progress['percent'] ?? 0;
						$result['step']    = $progress['step'] ?? '';
						$result['message'] = $progress['message'] ?? '';
					}
				} elseif ( $backup->status === 'completed' ) {
					$result['size']         = $backup->backup_size;
					$result['size_human']   = size_format( $backup->backup_size );
					$result['completed_at'] = $backup->completed_at;
				} elseif ( $backup->status === 'failed' ) {
					$meta = json_decode( $backup->metadata ?? '{}', true );
					$result['error'] = $meta['error'] ?? 'Unknown error';
				}

				return $result;

			case 'sv_get_backup_stats':
				if ( ! method_exists( $manager, 'get_stats' ) ) {
					throw new \Exception( 'Backup stats not available in this version' );
				}
				return $manager->get_stats();

			case 'sv_get_schedules':
				if ( ! class_exists( 'RB_Backup_Scheduler' ) ) {
					throw new \Exception( 'Backup scheduler not available' );
				}
				$schedules = \RB_Backup_Scheduler::instance()->get_schedules();
				return array_map( function( $s ) {
					return [
						'id'         => $s->id,
						'name'       => $s->schedule_name,
						'type'       => $s->backup_type,
						'frequency'  => $s->frequency,
						'is_active'  => (bool) $s->is_active,
						'last_run'   => $s->last_run,
						'next_run'   => $s->next_run,
					];
				}, $schedules );

			default:
				throw new \Exception( 'Unknown SiteVault tool: ' . esc_html( $name ) );
		}
	}

	private static function format_backup( $backup ) {
		return [
			'id'           => (int) $backup->id,
			'name'         => $backup->backup_name,
			'type'         => $backup->backup_type,
			'status'       => $backup->status,
			'size'         => (int) $backup->backup_size,
			'size_human'   => size_format( $backup->backup_size ),
			'cloud_synced' => (bool) $backup->cloud_synced,
			'created_at'   => $backup->created_at,
			'completed_at' => $backup->completed_at,
		];
	}
}
