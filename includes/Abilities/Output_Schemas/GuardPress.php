<?php
/**
 * GuardPress output schemas.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GuardPress {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'gp_get_security_status' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'gp_get_security_stats' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'gp_run_vulnerability_scan' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'scan_id'   => array( 'type' => array( 'integer', 'string' ) ),
					'status'    => array( 'type' => 'string' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'gp_get_vulnerability_results' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'gp_get_failed_logins' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'gp_get_blocked_ips' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'ip'         => array( 'type' => 'string' ),
						'reason'     => array( 'type' => 'string' ),
						'blocked_at' => array( 'type' => 'string' ),
					),
				),
			),
			'gp_get_audit_log' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'        => array( 'type' => array( 'integer', 'string' ) ),
						'severity'  => array( 'type' => 'string' ),
						'event'     => array( 'type' => 'string' ),
						'timestamp' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}
}
