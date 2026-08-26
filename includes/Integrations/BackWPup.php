<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BackWPup {

	public static function is_available() {
		return class_exists( '\BackWPup_Option' ) || class_exists( '\BackWPup' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'backwpup' ),
			'capabilities' => array( 'backup' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'bwu_get_jobs',
				'description' => 'List BackWPup backup jobs with their normalized status: id, name, backup type, schedule kind, last-run time, and last-run success/warning/error state. BackWPup organizes backups as configured jobs (unlike UpdraftPlus "backup now"), so this is the job inventory. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bwu_get_job',
				'description' => 'Read one BackWPup job in detail: its tasks, destinations, schedule, and last-run summary. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'job_id' => array( 'type' => 'integer', 'description' => 'BackWPup job id, from bwu_get_jobs.' ),
					),
					'required'   => array( 'job_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use backup tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'BackWPup is not active.' );
		}
		if ( ! class_exists( '\BackWPup_Option' ) || ! method_exists( '\BackWPup_Option', 'get_job_ids' ) ) {
			throw new \Exception( 'BackWPup job API is unavailable in this version.' );
		}

		if ( 'bwu_get_jobs' === $name ) {
			return self::get_jobs();
		}
		if ( 'bwu_get_job' === $name ) {
			return self::get_job( absint( $args['job_id'] ?? 0 ) );
		}
		throw new \Exception( 'Unknown backup tool: ' . esc_html( $name ) );
	}

	private static function get_jobs() {
		$ids  = (array) \BackWPup_Option::get_job_ids();
		$jobs = array();
		foreach ( $ids as $job_id ) {
			$job_id = (int) $job_id;

			if ( \BackWPup_Option::get( $job_id, 'tempjob', false ) ) {
				continue;
			}
			$jobs[] = self::job_summary( $job_id );
		}
		return array( 'provider' => 'backwpup', 'jobs' => $jobs );
	}

	private static function get_job( $job_id ) {
		if ( $job_id <= 0 ) {
			throw new \Exception( 'job_id is required.' );
		}
		$ids = array_map( 'intval', (array) \BackWPup_Option::get_job_ids() );
		if ( ! in_array( $job_id, $ids, true ) ) {
			throw new \Exception( 'BackWPup job not found.' );
		}
		$summary = self::job_summary( $job_id );

		$summary['tasks']        = self::string_list( \BackWPup_Option::get( $job_id, 'type', array() ) );
		$summary['destinations'] = self::string_list( \BackWPup_Option::get( $job_id, 'destinations', array() ) );
		return array( 'provider' => 'backwpup', 'job' => $summary );
	}

	private static function job_summary( $job_id ) {
		$last_run  = \BackWPup_Option::get( $job_id, 'lastrun', 0 );
		$last_time = \BackWPup_Option::get( $job_id, 'lastruntime', 0 );

		
		$err_count  = \BackWPup_Option::get( $job_id, 'lasterrors', null );
		$warn_count = \BackWPup_Option::get( $job_id, 'lastwarnings', null );

		$state = 'unknown';
		if ( null !== $err_count || null !== $warn_count ) {
			if ( (int) $err_count > 0 ) {
				$state = 'error';
			} elseif ( (int) $warn_count > 0 ) {
				$state = 'warning';
			} elseif ( $last_run ) {
				$state = 'success';
			}
		} elseif ( $last_run ) {
			$state = 'success';
		}

		return array(
			'provider'      => 'backwpup',
			'id'            => (int) $job_id,
			'name'          => (string) \BackWPup_Option::get( $job_id, 'name', '' ),
			'activetype'    => (string) \BackWPup_Option::get( $job_id, 'activetype', '' ),
			'last_run'      => $last_run ? (int) $last_run : null,
			'last_duration' => $last_time ? (int) $last_time : null,
			'last_state'    => $state,
		);
	}

	private static function string_list( $value ) {
		if ( ! is_array( $value ) ) {
			return '' === (string) $value ? array() : array( (string) $value );
		}
		$out = array();
		foreach ( $value as $v ) {
			$out[] = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
		}
		return $out;
	}
}
