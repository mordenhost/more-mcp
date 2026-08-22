<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Options_Menus {
	public static function map(): array {
		return array(

			'wp_get_option'          => array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array( 'type' => 'string' ),
					'value' => array(), 
				),
			),
			'wp_get_plugin_settings' => array(
				'type'       => 'object',
				'properties' => array(
					'slug'    => array( 'type' => 'string' ),
					'options' => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_option'       => array(
				'type'       => 'object',
				'properties' => array(
					'name'     => array( 'type' => 'string' ),
					'updated'  => array( 'type' => 'boolean' ),
					'previous' => array(), 
				),
			),
			'wp_set_front_page'      => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success'        => array( 'type' => 'boolean' ),
					'show_on_front'  => array( 'type' => 'string' ),
					'page_on_front'  => array( 'type' => 'integer' ),
					'page_for_posts' => array( 'type' => 'integer' ),
					'previous'       => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),

			'wp_get_menus'      => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'name' => array( 'type' => 'string' ),
						'slug' => array( 'type' => 'string' ),
					),
				),
			),
			'wp_get_menu_items' => array(
				'type'  => 'array',
				'items' => Shared::menu_item_schema(),
			),
			'wp_create_menu' => array(
				'type'       => 'object',
				'properties' => array(
					'success'            => array( 'type' => 'boolean' ),
					'menu_id'            => array( 'type' => 'integer' ),
					'name'               => array( 'type' => 'string' ),
					'assigned_locations' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'unknown_locations'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'wp_create_menu_item' => Shared::menu_item_write_response_schema(),
			'wp_update_menu_item' => Shared::menu_item_write_response_schema(),
			'wp_delete_menu_item' => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'menu_item_id' => array( 'type' => 'integer' ),
				),
			),
			'wp_reorder_menu_items' => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'menu_id'   => array( 'type' => 'integer' ),
					'count'     => array( 'type' => 'integer' ),
					'reordered' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'skipped'   => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'menu_item_id' => array( 'type' => 'integer' ),
								'reason'       => array( 'type' => 'string' ),
							),
						),
					),
				),
			),

					);
	}
}
