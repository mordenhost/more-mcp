<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Appearance {
	public static function map(): array {
		return array(

			'wp_get_plugins' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'version' => array( 'type' => 'string' ),
						'active'  => array( 'type' => 'boolean' ),
						'author'  => array( 'type' => 'string' ),
					),
				),
			),
			'wp_get_themes' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'version' => array( 'type' => 'string' ),
						'active'  => array( 'type' => 'boolean' ),
						'author'  => array( 'type' => 'string' ),
					),
				),
			),

			'wp_get_active_theme' => array(
				'type'       => 'object',
				'properties' => array(
					'name'           => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'template'       => array( 'type' => 'string' ),
					'stylesheet'     => array( 'type' => 'string' ),
					'version'        => array( 'type' => 'string' ),
					'author'         => array( 'type' => 'string' ),
					'description'    => array( 'type' => 'string' ),
					'parent_slug'    => array( 'type' => array( 'string', 'null' ) ),
					'screenshot_url' => array( 'type' => array( 'string', 'boolean' ) ),
					'status'         => array( 'type' => 'string' ),
				),
			),
			'wp_get_theme_mods'   => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'wp_update_theme_mod' => array(
				'type'       => 'object',
				'properties' => array(
					'mod_name'       => array( 'type' => 'string' ),
					'previous_value' => array(),
					'new_value'      => array(),
				),
			),
			'wp_get_custom_css'    => array(
				'type'       => 'object',
				'properties' => array(
					'css'        => array( 'type' => 'string' ),
					'theme_slug' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
				),
			),
			'wp_update_custom_css' => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'post_id'    => array( 'type' => 'integer' ),
					'theme_slug' => array( 'type' => 'string' ),
					'byte_count' => array( 'type' => 'integer' ),
				),
			),

					);
	}
}
