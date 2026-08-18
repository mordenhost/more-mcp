<?php
/**
 * ACF (Advanced Custom Fields) output schemas. Field values are inherently
 * polymorphic per field type (image arrays, post objects, repeaters, etc.) so
 * the value slot uses no type constraint.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'acf_get_field' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'name'  => array( 'type' => 'string' ),
					'value' => array(), // polymorphic per field type
					'type'  => array( 'type' => 'string' ),
					'label' => array( 'type' => 'string' ),
				),
			),
			'acf_get_fields' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'acf_update_field' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success'  => array( 'type' => 'boolean' ),
					'name'     => array( 'type' => 'string' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'acf_get_field_groups' => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'    => array( 'type' => array( 'integer', 'string' ) ),
						'title' => array( 'type' => 'string' ),
						'key'   => array( 'type' => 'string' ),
					),
				),
			),
		);
	}
}
