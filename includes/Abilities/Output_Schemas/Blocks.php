<?php

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		
		$block_summary = array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'path'           => array( 'type' => 'string' ),
					'blockName'      => array( 'type' => array( 'string', 'null' ) ),
					'snippet'        => array( 'type' => 'string' ),
					'children_count' => array( 'type' => 'integer' ),
				),
			),
		);

		$undo = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'token'      => array( 'type' => 'string' ),
				'expires_at' => array( 'type' => 'integer' ),
				'summary'    => array( 'type' => 'string' ),
				'ttl_hours'  => array( 'type' => 'integer' ),
			),
		);

		return array(

			'blocks_get_post_tree' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'post_id'     => array( 'type' => 'integer' ),
					'post_type'   => array( 'type' => 'string' ),
					'title'       => array( 'type' => 'string' ),
					'block_count' => array( 'type' => 'integer' ),
					'top_level'   => array( 'type' => 'integer' ),
					'has_blocks'  => array( 'type' => 'boolean' ),
					'blocks'      => $block_summary,
				),
			),

			'blocks_get_block' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'post_id'        => array( 'type' => 'integer' ),
					'path'           => array( 'type' => 'string' ),
					'blockName'      => array( 'type' => array( 'string', 'null' ) ),
					'attributes'     => array( 'type' => 'object' ),
					'inner_html'     => array( 'type' => 'string' ),
					'children_count' => array( 'type' => 'integer' ),
					'markup'         => array( 'type' => 'string' ),
					'is_freeform'    => array( 'type' => 'boolean' ),
				),
			),

			'blocks_list_types' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'count'  => array( 'type' => 'integer' ),
					'note'   => array( 'type' => 'string' ),
					'blocks' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'name'        => array( 'type' => 'string' ),
								'title'       => array( 'type' => 'string' ),
								'category'    => array( 'type' => array( 'string', 'null' ) ),
								'description' => array( 'type' => 'string' ),
								'is_dynamic'  => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			),

			'blocks_get_type_schema' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'name'       => array( 'type' => 'string' ),
					'title'      => array( 'type' => 'string' ),
					'category'   => array( 'type' => array( 'string', 'null' ) ),
					'is_dynamic' => array( 'type' => 'boolean' ),
					'attributes' => array( 'type' => 'object' ),
				),
			),

			
			'blocks_validate_markup' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'confidence'  => array(
						'type' => 'string',
						'enum' => array( 'structural_ok', 'registered', 'schema_ok', 'unknown_block', 'invalid' ),
					),
					'parseable'   => array( 'type' => 'boolean' ),
					'block_count' => array( 'type' => 'integer' ),
					'errors'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'warnings'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'limitation'  => array( 'type' => 'string' ),
					'explanation' => array( 'type' => 'string' ),
				),
			),

			

			'blocks_insert' => self::mutation_schema( $block_summary, $undo ),
			'blocks_update' => self::mutation_schema( $block_summary, $undo ),
			'blocks_delete' => self::mutation_schema( $block_summary, $undo ),
			'blocks_move'   => self::mutation_schema( $block_summary, $undo ),

			'blocks_list_templates' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'count'     => array( 'type' => 'integer' ),
					'theme'     => array( 'type' => 'string' ),
					'note'      => array( 'type' => 'string' ),
					'templates' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'id'           => array( 'type' => 'string' ),
								'slug'         => array( 'type' => 'string' ),
								'type'         => array( 'type' => 'string' ),
								'title'        => array( 'type' => 'string' ),
								'description'  => array( 'type' => 'string' ),
								'source'       => array( 'type' => 'string' ),
								'is_custom'    => array( 'type' => 'boolean' ),
								'area'         => array( 'type' => array( 'string', 'null' ) ),
								'content_hash' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),

			'blocks_get_template' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'           => array( 'type' => 'string' ),
					'slug'         => array( 'type' => 'string' ),
					'title'        => array( 'type' => 'string' ),
					'description'  => array( 'type' => 'string' ),
					'content'      => array( 'type' => 'string' ),
					'content_hash' => array( 'type' => 'string' ),
					'source'       => array( 'type' => 'string' ),
					'is_custom'    => array( 'type' => 'boolean' ),
					'wp_id'        => array( 'type' => array( 'integer', 'null' ) ),
					'post_type'    => array( 'type' => 'string' ),
					'theme'        => array( 'type' => 'string' ),
				),
			),

			'blocks_update_template' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'action'       => array( 'type' => 'string', 'enum' => array( 'create', 'update' ) ),
					'post_id'      => array( 'type' => array( 'integer', 'null' ) ),
					'slug'         => array( 'type' => 'string' ),
					'content_hash' => array( 'type' => 'string' ),
					'verified'     => array( 'type' => 'boolean' ),
					'dry_run'      => array( 'type' => 'boolean' ),
					'undo'         => $undo,
				),
			),

			'blocks_revert_template' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'action'     => array( 'type' => 'string' ),
					'slug'       => array( 'type' => 'string' ),
					'deleted_id' => array( 'type' => 'integer' ),
					'note'       => array( 'type' => 'string' ),
					'dry_run'    => array( 'type' => 'boolean' ),
					'undo'       => $undo,
				),
			),

			'blocks_list_patterns' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'count'    => array( 'type' => 'integer' ),
					'patterns' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'name'        => array( 'type' => 'string' ),
								'title'       => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'category'    => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					),
				),
			),

			'blocks_list_reusable' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'total'          => array( 'type' => 'integer' ),
					'page'           => array( 'type' => 'integer' ),
					'per_page'       => array( 'type' => 'integer' ),
					'pages'          => array( 'type' => 'integer' ),
					'reusable_count' => array( 'type' => 'integer' ),
					'reusable'       => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'id'           => array( 'type' => 'integer' ),
								'title'        => array( 'type' => 'string' ),
								'snippet'      => array( 'type' => 'string' ),
								'content_hash' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),

			'blocks_get_reusable' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'           => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'content'      => array( 'type' => 'string' ),
					'content_hash' => array( 'type' => 'string' ),
					'block_count'  => array( 'type' => 'integer' ),
					'structure'    => $block_summary,
				),
			),

			'blocks_create_reusable' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'           => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'content_hash' => array( 'type' => 'string' ),
					'snippet'      => array( 'type' => 'string' ),
				),
			),

			'blocks_update_reusable' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'reusable_id'   => array( 'type' => 'integer' ),
					'title'         => array( 'type' => 'string' ),
					'content_hash'  => array( 'type' => 'string' ),
					'previous_hash' => array( 'type' => 'string' ),
					'verified'      => array( 'type' => 'boolean' ),
					'dry_run'       => array( 'type' => 'boolean' ),
					'undo'          => $undo,
				),
			),

			'blocks_delete_reusable' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'reusable_id' => array( 'type' => 'integer' ),
					'title'       => array( 'type' => 'string' ),
					'snippet'     => array( 'type' => 'string' ),
					'note'        => array( 'type' => 'string' ),
					'dry_run'     => array( 'type' => 'boolean' ),
					'undo'        => $undo,
				),
			),
		);
	}

	private static function mutation_schema( array $block_summary, array $undo ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'written'        => array( 'type' => 'boolean' ),
				'dry_run'        => array( 'type' => 'boolean' ),
				'summary'        => array( 'type' => 'string' ),
				'post_id'        => array( 'type' => 'integer' ),
				'verified'       => array( 'type' => 'boolean' ),
				'verify_note'    => array( 'type' => 'string' ),
				'resulting_tree' => $block_summary,
				'undo'           => $undo,
			),
		);
	}
}
