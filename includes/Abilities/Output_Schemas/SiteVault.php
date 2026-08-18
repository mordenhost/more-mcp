<?php
/**
 * SiteVault output schemas. Backup manager helpers return varying shapes;
 * loose validation keeps this useful.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteVault {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'sv_get_backups' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'         => array( 'type' => array( 'integer', 'string' ) ),
						'created_at' => array( 'type' => 'string' ),
						'size_bytes' => array( 'type' => 'integer' ),
						'status'     => array( 'type' => 'string' ),
					),
				),
			),
			'sv_get_backup' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'sv_create_backup' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'      => array( 'type' => array( 'integer', 'string' ) ),
					'status'  => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'sv_get_backup_status' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'sv_get_backup_stats' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'sv_get_schedules' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
			),
		);
	}
}
