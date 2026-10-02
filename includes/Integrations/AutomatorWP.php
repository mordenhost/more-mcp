<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutomatorWP {

	const TABLES = array( 'automations', 'triggers', 'actions', 'logs' );

	public static function is_available() {

		return function_exists( 'AutomatorWP' ) && function_exists( 'automatorwp_get_automation_statuses' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'automatorwp' ),
			'capabilities' => array( 'automation' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'automatorwp_get_automations',
				'description' => 'List AutomatorWP automations (recipes): id, title, type, status (active/in-progress/inactive, AutomatorWP\'s own vocabulary), run limits (times, times_per_user, sequential flag), creation date, and the count of triggers and actions each automation has. Paginated, ordered newest first. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'    => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Rows per page, 1 to 50. Default 20.' ),
						'status'  => array( 'type' => 'string', 'description' => 'Optional status filter: active, in-progress, inactive.' ),
					),
				),
			),
			array(
				'name'        => 'automatorwp_get_run_stats',
				'description' => 'Aggregate AutomatorWP run statistics: total log entries and counts by log type (trigger completions, action executions, filter evaluations), plus the number of distinct automations that have any run history. The logs table records which user each entry belongs to — that column is never selected; counts only. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use automation tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'AutomatorWP is not active.' );
		}

		if ( 'automatorwp_get_automations' === $name ) {
			return self::get_automations( $args );
		}
		if ( 'automatorwp_get_run_stats' === $name ) {
			return self::get_run_stats();
		}
		throw new \Exception( 'Unknown AutomatorWP tool: ' . esc_html( $name ) );
	}

	private static function get_automations( $args ) {

		

		if ( ! function_exists( 'ct_setup_table' ) || ! class_exists( 'CT_Query' ) ) {
			throw new \Exception( 'The AutomatorWP custom-tables library is not available.' );
		}

		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, absint( $args['per_page'] ?? 20 ) ) );
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';

		if ( '' !== $status && ! self::status_is_known( $status ) ) {
			throw new \Exception( 'status must be one of: ' . implode( ', ', array_keys( self::statuses() ) ) . '.' );
		}

		ct_setup_table( 'automatorwp_automations' );

		$query = new \CT_Query( array(
			'items_per_page' => -1,
			'orderby'        => 'id',
			'order'          => 'DESC',
		) );
		$rows = $query->get_results();

		$automations = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}

			if ( '' !== $status && (string) $row->status !== $status ) {
				continue;
			}
			$automations[] = array(
				'id'             => (int) $row->id,
				'title'          => (string) $row->title,
				'type'           => (string) $row->type,
				'status'         => (string) $row->status,
				'times'          => (int) $row->times,
				'times_per_user' => (int) $row->times_per_user,
				'sequential'     => (int) $row->sequential === 1,
				'date'           => (string) $row->date,
				'trigger_count'  => self::item_count( 'triggers', (int) $row->id ),
				'action_count'   => self::item_count( 'actions', (int) $row->id ),
			);
		}

		$total  = count( $automations );
		$offset = ( $page - 1 ) * $per_page;
		$slice  = array_slice( $automations, $offset, $per_page );

		return array(
			'provider'   => 'automatorwp',
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'pages'      => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'returned'   => count( $slice ),
			'has_more'   => $offset + count( $slice ) < $total,
			'automations' => $slice,
		);
	}

	private static function get_run_stats() {
		global $wpdb;

		if ( ! self::table_exists( 'logs' ) ) {
			return array(
				'provider'  => 'automatorwp',
				'available' => false,
				'message'   => 'The AutomatorWP logs table does not exist (no automation has ever run).',
			);
		}

		$log = self::table_name( 'logs' );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" ); 
		$by_type = array();
		foreach ( array( 'trigger', 'action', 'filter' ) as $type ) {
			$by_type[ $type ] = (int) $wpdb->get_var( 
				$wpdb->prepare( "SELECT COUNT(*) FROM {$log} WHERE type = %s", $type )
			);
		}

		
		
		$distinct = null;
		if ( self::table_exists( 'triggers' ) ) {
			$triggers = self::table_name( 'triggers' );
			$distinct = (int) $wpdb->get_var( 
				"SELECT COUNT(DISTINCT t.automation_id) FROM {$triggers} t WHERE EXISTS ( SELECT 1 FROM {$log} l WHERE l.type = 'trigger' AND l.object_id = t.id )"
			);
		}

		return array(
			'provider'          => 'automatorwp',
			'available'         => true,
			'total_log_entries' => $total,
			'by_type'           => $by_type,
			'automations_with_runs' => $distinct,
		);
	}

	private static function statuses() {
		$statuses = automatorwp_get_automation_statuses();
		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			return $statuses;
		}
		return array( 'active' => 'Active', 'in-progress' => 'In Progress', 'inactive' => 'Inactive' );
	}

	private static function status_is_known( $status ) {
		return array_key_exists( $status, self::statuses() );
	}

	private static function table_name( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'automatorwp_' . $suffix;
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = self::table_name( $suffix );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}

	private static function item_count( $suffix, $automation_id ) {
		global $wpdb;
		if ( ! self::table_exists( $suffix ) ) {
			return null;
		}
		$table = self::table_name( $suffix );
		return (int) $wpdb->get_var( 
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE automation_id = %d", $automation_id )
		);
	}
}
