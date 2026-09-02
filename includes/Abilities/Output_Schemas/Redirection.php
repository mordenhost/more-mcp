<?php

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Redirection {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'redirection_list_redirects' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'source_url'  => array( 'type' => 'string' ),
						'target_url'  => array( 'type' => 'string' ),
						'status_code' => array( 'type' => 'integer' ),
						'regex'       => array( 'type' => 'boolean' ),
						'enabled'     => array( 'type' => 'boolean' ),
						'group_id'    => array( 'type' => 'integer' ),
						'position'    => array( 'type' => 'integer' ),
					),
				),
			),
			'redirection_create_redirect' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'          => array( 'type' => 'integer' ),
					'source_url'  => array( 'type' => 'string' ),
					'target_url'  => array( 'type' => 'string' ),
					'status_code' => array( 'type' => 'integer' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'redirection_update_redirect' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'      => array( 'type' => 'integer' ),
					'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'redirection_list_groups' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'        => array( 'type' => 'integer' ),
						'name'      => array( 'type' => 'string' ),
						'tracking'  => array( 'type' => 'boolean' ),
						'enabled'   => array( 'type' => 'boolean' ),
						'position'  => array( 'type' => 'integer' ),
						'module_id' => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}
}
