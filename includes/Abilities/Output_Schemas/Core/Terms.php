<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Terms {
	public static function map(): array {
		return array(

			'wp_get_categories' => array( 'type' => 'array', 'items' => Shared::term_summary_schema() ),
			'wp_get_tags'       => array( 'type' => 'array', 'items' => Shared::term_summary_schema() ),
			'wp_create_term'    => Shared::term_write_response_schema(),
			'wp_update_term'    => Shared::term_write_response_schema(),
			'wp_delete_term'    => Shared::message_schema(),
			'wp_add_post_terms' => Shared::message_schema(),
			'wp_get_terms'      => array(
				'type'       => 'object',
				'properties' => array(
					'terms'       => array( 'type' => 'array', 'items' => Shared::term_summary_schema() ),
					'total_count' => array( 'type' => 'integer' ),
					'page'        => array( 'type' => 'integer' ),
					'per_page'    => array( 'type' => 'integer' ),
				),
			),
			'wp_count_terms'    => array( 'type' => 'integer' ),
			'wp_get_taxonomies' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string' ),
						'label'        => array( 'type' => 'string' ),
						'hierarchical' => array( 'type' => 'boolean' ),
						'post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
			),

			'wp_get_term_meta'    => array(
				'type'       => 'object',
				'properties' => array(
					'term_id' => array( 'type' => 'integer' ),
					'key'     => array( 'type' => 'string' ),
					'value'   => array(), 
				),
			),
			'wp_update_term_meta' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'message' => array( 'type' => 'string' ),
						'term_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					Shared::meta_verification_props()
				),
			),
			'wp_delete_term_meta' => Shared::message_schema(),

					);
	}
}
