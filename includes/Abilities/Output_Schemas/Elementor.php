<?php
/**
 * Elementor output schemas. Data + settings shapes vary heavily by widget kind
 * so most tools use loose object schemas with a small anchor of well-known fields.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			'elementor_clone_page' => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'source_post_id' => array( 'type' => 'integer' ),
					'new_post_id'    => array( 'type' => 'integer' ),
					'new_title'      => array( 'type' => 'string' ),
					'new_status'     => array( 'type' => 'string' ),
					'edit_url'       => array( 'type' => 'string' ),
					'view_url'       => array( 'type' => array( 'string', 'null' ) ),
				),
				'additionalProperties' => true,
			),
			'elementor_replace_text' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'replacements' => array( 'type' => 'integer' ),
				),
				'additionalProperties' => true,
			),
			'elementor_replace_image' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'replacements' => array( 'type' => 'integer' ),
				),
				'additionalProperties' => true,
			),
			'elementor_get_page_outline' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'elementor_get_widget_settings' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'elementor_list_local_templates' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'count'     => array( 'type' => 'integer' ),
					'templates' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'id'            => array( 'type' => 'integer' ),
								'name'          => array( 'type' => 'string' ),
								'type'          => array( 'type' => 'string' ),
								'date_modified' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
			'elementor_import_template' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success'       => array( 'type' => 'boolean' ),
					'template_id'   => array( 'type' => 'integer' ),
					'title'         => array( 'type' => 'string' ),
					'template_type' => array( 'type' => 'string' ),
					'edit_url'      => array( 'type' => 'string' ),
				),
			),
			'elementor_add_widget' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'success'     => array( 'type' => 'boolean' ),
					'post_id'     => array( 'type' => 'integer' ),
					'new_id'      => array( 'type' => 'string' ),
					'widget_type' => array( 'type' => 'string' ),
					'parent_id'   => array( 'type' => array( 'string', 'null' ) ),
					'position'    => array( 'type' => array( 'integer', 'null' ) ),
					'edit_url'    => array( 'type' => 'string' ),
				),
			),
			// additionalProperties stays true on both of these: a dry_run
			// response carries a different key set from a written one, and the
			// settings object is arbitrary per widget type.
			'elementor_update_widget' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'post_id'      => array( 'type' => 'integer' ),
					'element_id'   => array( 'type' => 'string' ),
					'element_type' => array( 'type' => 'string' ),
					'widget_type'  => array( 'type' => array( 'string', 'null' ) ),
					'written'      => array( 'type' => 'boolean' ),
					'mode'         => array( 'type' => 'string' ),
					'verified'     => array( 'type' => 'boolean' ),
				),
			),
			'elementor_delete_widget' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'post_id'             => array( 'type' => 'integer' ),
					'element_id'          => array( 'type' => 'string' ),
					'element_type'        => array( 'type' => 'string' ),
					'widget_type'         => array( 'type' => array( 'string', 'null' ) ),
					'written'             => array( 'type' => 'boolean' ),
					'descendants_removed' => array( 'type' => 'integer' ),
					'total_removed'       => array( 'type' => 'integer' ),
					'verified'            => array( 'type' => 'boolean' ),
				),
			),
			'elementor_move_widget' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'post_id'      => array( 'type' => 'integer' ),
					'element_id'   => array( 'type' => 'string' ),
					'target_id'    => array( 'type' => 'string' ),
					'position'     => array( 'type' => 'string' ),
					'element_type' => array( 'type' => 'string' ),
					'widget_type'  => array( 'type' => array( 'string', 'null' ) ),
					'written'      => array( 'type' => 'boolean' ),
					'dry_run'      => array( 'type' => 'boolean' ),
					'verified'     => array( 'type' => 'boolean' ),
				),
			),
			'elementor_get_loop_template' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'has_loop_template' => array( 'type' => 'boolean' ),
					'post_id'           => array( 'type' => 'integer' ),
					'element_id'        => array( 'type' => 'string' ),
					'widget_type'       => array( 'type' => array( 'string', 'null' ) ),
					'loop_post_id'      => array( 'type' => 'integer' ),
					'template_type'     => array( 'type' => array( 'string', 'null' ) ),
					'title'             => array( 'type' => 'string' ),
					'outline'           => array( 'type' => 'array' ),
					'edit_hint'         => array( 'type' => 'string' ),
					'message'           => array( 'type' => 'string' ),
				),
			),
		);
	}
}
