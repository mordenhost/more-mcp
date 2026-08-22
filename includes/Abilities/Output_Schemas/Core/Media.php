<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Media {
	public static function map(): array {
		return array(

			'wp_get_media'             => array(
				'type'       => 'object',
				'properties' => array(
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'pages'    => array( 'type' => 'integer' ),
					'returned' => array( 'type' => 'integer' ),
					'has_more' => array( 'type' => 'boolean' ),
					'media'    => array(
						'type'  => 'array',
						'items' => Shared::media_summary_schema(),
					),
				),
			),
			'wp_get_media_item'        => Shared::media_full_schema(),
			'wp_upload_media_from_url' => Shared::media_upload_response_schema(),
			'wp_upload_media'          => Shared::media_upload_response_schema(),
			'wp_set_featured_image'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer' ),
					'media_id'    => array( 'type' => array( 'integer', 'null' ) ),
					'action'      => array( 'type' => 'string' ),
				),
			),
			'wp_update_media' => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'wp_delete_media' => Shared::message_schema(),
			'wp_count_media'  => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'integer' ),
			),

					);
	}
}
