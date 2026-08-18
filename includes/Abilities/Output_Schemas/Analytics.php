<?php
namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Output schemas for normalized read-only analytics tools. */
class Analytics {

	public static function get( string $tool_name ): ?array {
		$provider_result = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'status'   => array( 'type' => 'string' ),
				'provider' => array( 'type' => 'string' ),
				'message'  => array( 'type' => array( 'string', 'null' ) ),
				'error'    => array( 'type' => array( 'string', 'null' ) ),
				'data'     => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		);

		$schemas = array(
			'analytics_get_status' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'providers' => array( 'type' => 'object', 'additionalProperties' => true ),
				),
				'required'             => array( 'providers' ),
			),
			'analytics_get_summary' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'results' => array( 'type' => 'object', 'additionalProperties' => $provider_result ),
				),
				'required'             => array( 'results' ),
			),
			'analytics_get_top_content' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'results' => array( 'type' => 'object', 'additionalProperties' => $provider_result ),
				),
				'required'             => array( 'results' ),
			),
		);

		return $schemas[ $tool_name ] ?? null;
	}
}
