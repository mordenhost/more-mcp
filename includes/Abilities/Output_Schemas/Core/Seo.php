<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Seo {
	public static function map(): array {
		return array(

			
			'wp_get_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'           => array( 'type' => 'string' ),
					'plugin_name'      => array( 'type' => 'string' ),
					'all_active'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'post_id'          => array( 'type' => 'integer' ),
					'slug'             => array( 'type' => 'string' ),
					'supported_fields' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'raw'              => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'      => array( 'type' => 'string' ),
					'plugin_name' => array( 'type' => 'string' ),
					'all_active'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'post_id'     => array( 'type' => 'integer' ),
					'updated'     => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_get_term_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'           => array( 'type' => 'string' ),
					'plugin_name'      => array( 'type' => 'string' ),
					'all_active'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'term_id'          => array( 'type' => 'integer' ),
					'taxonomy'         => array( 'type' => 'string' ),
					'slug'             => array( 'type' => 'string' ),
					'supported_fields' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'raw'              => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_term_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'      => array( 'type' => 'string' ),
					'plugin_name' => array( 'type' => 'string' ),
					'all_active'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'term_id'     => array( 'type' => 'integer' ),
					'taxonomy'    => array( 'type' => 'string' ),
					'updated'     => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),

			'seo_audit_meta_tags' => array(
				'type'       => 'object',
				'properties' => array(
					'url'    => array( 'type' => 'string' ),
					'status' => array( 'type' => 'integer' ),
					'title'  => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'length'     => array( 'type' => 'integer' ),
							'duplicates' => array( 'type' => 'integer' ),
						),
					),
					'description' => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'length'     => array( 'type' => 'integer' ),
							'duplicates' => array( 'type' => 'integer' ),
						),
					),
					'canonical' => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'duplicates' => array( 'type' => 'integer' ),
							'is_self'    => array( 'type' => 'boolean' ),
						),
					),
					'viewport' => array(
						'type'       => 'object',
						'properties' => array(
							'present' => array( 'type' => 'boolean' ),
							'content' => array( 'type' => 'string' ),
						),
					),
					'og' => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image'       => array( 'type' => 'string' ),
							'url'         => array( 'type' => 'string' ),
							'type'        => array( 'type' => 'string' ),
						),
					),
					'twitter' => array(
						'type'       => 'object',
						'properties' => array(
							'card'        => array( 'type' => 'string' ),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image'       => array( 'type' => 'string' ),
						),
					),
				),
			),

					);
	}
}
