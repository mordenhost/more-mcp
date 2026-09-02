<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Duplicator {

	private const STATUS_LABELS = [
		-1  => 'error',
		0   => 'created',
		10  => 'scan_complete',
		20  => 'database_started',
		30  => 'database_complete',
		40  => 'archive_started',
		60  => 'archive_validating',
		65  => 'archive_complete',
		100 => 'complete',
	];

	public static function is_available() {
		return defined( 'DUPLICATOR_VERSION' ) && class_exists( 'DUP_Package' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'duplicator' ),
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
				'name'        => 'dup_get_packages',
				'description' => 'List Duplicator backup packages, newest first: id, name, build status (created/scanning/database/archiving/complete/error), and creation time. Never returns the package hash (the secret needed to construct its download URL) or the serialized package configuration. Use this to answer "is there a recent recoverable Duplicator package?" before risky maintenance.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of packages to return (default 20, max 50).' ],
					],
				],
			],
			[
				'name'        => 'dup_get_status',
				'description' => 'Get a Duplicator overview: plugin version, whether a package build is currently in progress, and the number of packages that have reached COMPLETE status. Read-only; there is no start-build tool because Duplicator\'s build process is a multi-step wizard with no single safe entry point.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		

		
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Duplicator tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Duplicator is not active' );
		}

		switch ( $name ) {
			case 'dup_get_packages':
				return self::get_packages( $args );

			case 'dup_get_status':
				return self::get_status();

			default:
				throw new \Exception( 'Unknown Duplicator tool: ' . esc_html( $name ) );
		}
	}

	private static function get_packages( $args ) {
		if ( ! method_exists( 'DUP_Package', 'get_row_by_status' ) ) {
			throw new \Exception( 'Duplicator package listing is not available in this version.' );
		}

		$limit = min( max( 1, intval( $args['limit'] ?? 20 ) ), 50 );
		$rows  = \DUP_Package::get_row_by_status( [], $limit, 0, '`id` DESC' );
		$rows  = is_array( $rows ) ? $rows : [];

		$out = [];
		foreach ( $rows as $row ) {
			$status_code = isset( $row->status ) ? (int) $row->status : null;
			$out[]       = [

				'id'         => isset( $row->id ) ? (int) $row->id : null,
				'name'       => isset( $row->name ) ? sanitize_text_field( (string) $row->name ) : '',
				'status'     => self::STATUS_LABELS[ $status_code ] ?? 'unknown',
				'status_code' => $status_code,
				'created'    => isset( $row->created ) ? (string) $row->created : '',
				'owner_user_id' => isset( $row->owner ) ? (int) $row->owner : null,
			];
		}

		return [
			'count'    => count( $out ),
			'packages' => $out,
		];
	}

	private static function get_status() {
		$running = method_exists( 'DUP_Package', 'isPackageRunning' ) ? (bool) \DUP_Package::isPackageRunning() : null;
		$complete = method_exists( 'DUP_Package', 'getNumCompletePackages' ) ? (int) \DUP_Package::getNumCompletePackages() : null;

		return [
			'provider'          => 'duplicator',
			'plugin_version'    => defined( 'DUPLICATOR_VERSION' ) ? DUPLICATOR_VERSION : '',
			'build_running'     => $running,
			'complete_packages' => $complete,
		];
	}
}
