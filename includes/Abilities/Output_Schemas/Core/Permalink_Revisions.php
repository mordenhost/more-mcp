<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Permalink_Revisions {
	public static function map(): array {
		return array(

			'wp_get_permalink_structure' => array(
				'type'       => 'object',
				'properties' => array(
					'permalink_structure' => array( 'type' => 'string' ),
					'category_base'       => array( 'type' => 'string' ),
					'tag_base'            => array( 'type' => 'string' ),
				),
			),
			'wp_update_permalink_structure' => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'previous' => array( 'type' => 'string' ),
					'current'  => array( 'type' => 'string' ),
				),
			),

			'wp_get_post_revisions' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer' ),
					'total'     => array( 'type' => 'integer' ),
					'offset'    => array( 'type' => 'integer' ),
					'limit'     => array( 'type' => 'integer' ),
					'returned'  => array( 'type' => 'integer' ),
					'has_more'  => array( 'type' => 'boolean' ),
					'note'      => array( 'type' => 'string' ),
					'revisions' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'revision_id'           => array( 'type' => 'integer' ),
								'parent_id'             => array( 'type' => 'integer' ),
								'author_id'             => array( 'type' => 'integer' ),
								'author_name'           => array( 'type' => 'string' ),
								'date'                  => array( 'type' => 'string' ),
								'title'                 => array( 'type' => 'string' ),
								'word_count'            => array( 'type' => 'integer' ),
								'content_length'        => array( 'type' => 'integer' ),
								'differs_from_parent'   => array( 'type' => 'boolean' ),
								'elementor_data_length' => array( 'type' => array( 'integer', 'null' ) ),
							),
						),
					),
				),
			),
			'wp_restore_revision' => array(
				'type'       => 'object',
				'properties' => array(
					'success'              => array( 'type' => 'boolean' ),
					'parent_id'            => array( 'type' => 'integer' ),
					'restored_revision_id' => array( 'type' => 'integer' ),
				),
			),
		);
	}

	

	
	private static function post_summary_schema(): array {
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

	private static function post_full_schema(): array {
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

	private static function post_write_response_schema(): array {
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

	private static function replace_in_content_response_schema(): array {
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

	private static function message_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	private static function meta_verification_props(): array {
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

	private static function term_summary_schema(): array {
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

	private static function term_write_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'term_id' => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	private static function comment_summary_schema(): array {
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

	private static function comment_status_change_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'comment_id' => array( 'type' => 'integer' ),
				'new_status' => array( 'type' => 'string' ),
			),
		);
	}

	private static function media_summary_schema(): array {
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

	private static function media_full_schema(): array {
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

	private static function media_upload_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'         => array( 'type' => 'integer' ),
				'source_url' => array( 'type' => 'string' ),
			),
		);
	}

	private static function menu_item_schema(): array {
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

	private static function menu_item_write_response_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'menu_item_id' => array( 'type' => 'integer' ),
				'menu_id'      => array( 'type' => 'integer' ),
			),
		);
	}
}
