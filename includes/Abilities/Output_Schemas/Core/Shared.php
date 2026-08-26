<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Shared {
	public static function post_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'status' => array( 'type' => 'string' ),
				'url'    => array( 'type' => 'string' ),
				'date'   => array( 'type' => 'string' ),
			),
		);
	}

	public static function post_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
				'date'    => array( 'type' => 'string' ),
			),
		);
	}

	public static function post_write_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'              => array( 'type' => 'integer' ),
				'saved_fields'    => array( 'type' => 'object', 'additionalProperties' => true ),
				'modified_by_wp'  => array( 'type' => 'object', 'additionalProperties' => true ),
				'message'         => array( 'type' => 'string' ),
			),
		);
	}

	public static function replace_in_content_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'                    => array( 'type' => 'integer' ),
				'occurrences'           => array( 'type' => 'integer' ),
				'replaced'              => array( 'type' => 'integer' ),
				'verified'              => array( 'type' => 'boolean' ),
				'dry_run'               => array( 'type' => 'boolean' ),
				'content_length'        => array( 'type' => 'integer' ),
				'content_length_before' => array( 'type' => 'integer' ),
				'content_length_after'  => array( 'type' => 'integer' ),
				'modified_by_wp'        => array( 'type' => 'object', 'additionalProperties' => true ),
				'message'               => array( 'type' => 'string' ),
			),
		);
	}

	public static function message_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	public static function meta_verification_props(): array {
		return array(
			'saved_value_matches_sent' => array( 'type' => 'boolean' ),
			'stored_type'              => array( 'type' => 'string' ),
			'stored_length'            => array( 'type' => array( 'integer', 'null' ) ),
			'warnings'                 => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);
	}

	public static function term_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'          => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'count'       => array( 'type' => 'integer' ),
				'parent'      => array( 'type' => 'integer' ),
			),
		);
	}

	public static function term_write_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'term_id' => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	public static function comment_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'      => array( 'type' => 'integer' ),
				'post_id' => array( 'type' => 'integer' ),
				'author'  => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'date'    => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
			),
		);
	}

	public static function comment_status_change_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'comment_id' => array( 'type' => 'integer' ),
				'new_status' => array( 'type' => 'string' ),
			),
		);
	}

	public static function media_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),

				'url'       => array( 'type' => 'string' ),
				'mime_type' => array( 'type' => 'string' ),
				'date'      => array( 'type' => 'string' ),

				'alt'       => array( 'type' => 'string' ),
				'alt_text'  => array( 'type' => 'string' ),
			),
		);
	}

	public static function media_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'         => array( 'type' => 'integer' ),
				'title'      => array( 'type' => 'string' ),
				'source_url' => array( 'type' => 'string' ),
				'alt_text'   => array( 'type' => 'string' ),
				'mime_type'  => array( 'type' => 'string' ),
			),
		);
	}

	public static function media_upload_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'         => array( 'type' => 'integer' ),
				'source_url' => array( 'type' => 'string' ),
			),
		);
	}

	public static function menu_item_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'url'    => array( 'type' => 'string' ),
				'parent' => array( 'type' => array( 'integer', 'string' ) ),
				'order'  => array( 'type' => 'integer' ),
			),
		);
	}

	public static function menu_item_write_response_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'menu_item_id' => array( 'type' => 'integer' ),
				'menu_id'      => array( 'type' => 'integer' ),
			),
		);
}

}
