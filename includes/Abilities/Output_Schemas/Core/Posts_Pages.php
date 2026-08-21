<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Posts_Pages {
	public static function map(): array {
		return array(

			'wp_get_posts'      => array(
				'type'  => 'array',
				'items' => Shared::post_summary_schema(),
			),
			'wp_get_post'       => Shared::post_full_schema(),
			'wp_create_post'     => Shared::post_write_response_schema(),
			'wp_update_post'     => Shared::post_write_response_schema(),
			'wp_replace_in_post' => Shared::replace_in_content_response_schema(),
			'wp_get_post_types' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string' ),
						'label'        => array( 'type' => 'string' ),
						'hierarchical' => array( 'type' => 'boolean' ),
						'public'       => array( 'type' => 'boolean' ),
					),
				),
			),
			'wp_delete_post'    => Shared::message_schema(),
			'wp_count_posts'    => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'integer' ),
			),

			'wp_get_pages'    => array(
				'type'  => 'array',
				'items' => Shared::post_summary_schema(),
			),
			'wp_get_page'     => Shared::post_full_schema(),
			'wp_create_page'     => Shared::post_write_response_schema(),
			'wp_update_page'     => Shared::post_write_response_schema(),
			'wp_replace_in_page' => Shared::replace_in_content_response_schema(),
			'wp_delete_page'  => Shared::message_schema(),

					);
	}
}
