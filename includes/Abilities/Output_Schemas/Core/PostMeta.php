<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PostMeta {
	public static function map(): array {
		return array(

			'wp_get_post_meta'    => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'wp_update_post_meta' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'message' => array( 'type' => 'string' ),
						'result'  => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					Shared::meta_verification_props()
				),
			),
			'wp_add_post_meta'    => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'meta_id' => array( 'type' => array( 'integer', 'null' ) ),
						'created' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					Shared::meta_verification_props()
				),
			),
			'wp_delete_post_meta' => Shared::message_schema(),

					);
	}
}
