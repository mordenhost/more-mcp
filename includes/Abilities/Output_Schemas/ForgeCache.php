<?php
/**
 * ForgeCache output schemas.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ForgeCache {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'fc_clear_cache' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'fc_get_cache_stats' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'fc_purge_url' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success' => array( 'type' => 'boolean' ),
					'url'     => array( 'type' => 'string' ),
					'post_id' => array( 'type' => array( 'integer', 'null' ) ),
				),
			),
		);
	}
}
