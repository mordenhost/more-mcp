<?php
namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Output schemas for the normalized forms integration (Gravity Forms, Fluent Forms). */
class Forms {

	public static function get( string $tool_name ): ?array {
		$field = array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'string' ),
				'label'    => array( 'type' => 'string' ),
				'type'     => array( 'type' => 'string' ),
				'required' => array( 'type' => 'boolean' ),
			),
		);

		$form = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'provider'    => array( 'type' => 'string' ),
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'active'      => array( 'type' => 'boolean' ),
				'entry_count' => array( 'type' => 'integer' ),
			),
		);

		$entry_summary = array(
			'type'       => 'object',
			'properties' => array(
				'provider' => array( 'type' => 'string' ),
				'id'       => array( 'type' => 'integer' ),
				'date'     => array( 'type' => 'string' ),
				'status'   => array( 'type' => 'string' ),
				'is_read'  => array( 'type' => 'boolean' ),
			),
		);

		$write_result = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'written'  => array( 'type' => 'boolean' ),
				'preview'  => array( 'type' => 'boolean' ),
				'provider' => array( 'type' => 'string' ),
				'entry_id' => array( 'type' => 'integer' ),
				'status'   => array( 'type' => 'string' ),
				'action'   => array( 'type' => 'string' ),
				'message'  => array( 'type' => 'string' ),
				'undo'     => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		);

		$schemas = array(
			'forms_list' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array( 'forms' => array( 'type' => 'array', 'items' => $form ) ),
				'required'             => array( 'forms' ),
			),
			'forms_get' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'provider' => array( 'type' => 'string' ),
					'id'       => array( 'type' => 'integer' ),
					'title'    => array( 'type' => 'string' ),
					'active'   => array( 'type' => 'boolean' ),
					'fields'   => array( 'type' => 'array', 'items' => $field ),
				),
			),
			'forms_list_entries' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'pages'    => array( 'type' => 'integer' ),
					'returned' => array( 'type' => 'integer' ),
					'has_more' => array( 'type' => 'boolean' ),
					'entries'  => array( 'type' => 'array', 'items' => $entry_summary ),
				),
				'required'             => array( 'total', 'entries' ),
			),
			'forms_get_entry' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'provider' => array( 'type' => 'string' ),
					'id'       => array( 'type' => 'integer' ),
					'form_id'  => array( 'type' => 'integer' ),
					'status'   => array( 'type' => 'string' ),
				),
			),
			'forms_get_stats' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'provider'  => array( 'type' => 'string' ),
					'form_id'   => array( 'type' => 'integer' ),
					'total'     => array( 'type' => 'integer' ),
					'by_status' => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'forms_update_entry_status' => $write_result,
			'forms_trash_entry'         => $write_result,
		);

		return $schemas[ $tool_name ] ?? null;
	}
}
