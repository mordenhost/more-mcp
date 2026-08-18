<?php

namespace More_MCP\Blocks;

use More_MCP\MCP\Undo_Store;
use More_MCP\Blocks\Templates\Template_Service;
use More_MCP\Blocks\Templates\Pattern_Service;
use More_MCP\Blocks\Templates\Reusable_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Templates {

	const THEME_TAXONOMY = 'wp_theme';

	const POST_TYPE_TEMPLATE      = 'wp_template';
	const POST_TYPE_TEMPLATE_PART = 'wp_template_part';

	public static function get_tools(): array {
		$slug_desc = 'Template slug. For theme-provided templates this is the filename without extension, e.g. "index", "single-post", "page". For database-customizations the ID is theme//slug (e.g. "flavor//single-post"), which this tool resolves automatically.';

		return [
			[
				'name'        => 'blocks_list_templates',
				'description' => 'List site templates available for this theme. Returns each template\'s slug, title, description, and whether it was customized in the database or is still the theme-provided file. Works only when a block theme (Full Site Editing theme) is active. Filters by type (templates or template parts) and area.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'type' => [
							'type'        => 'string',
							'enum'        => [ 'template', 'template_part' ],
							'description' => 'Filter by type. Omit for both.',
						],
						'area' => [
							'type'        => 'string',
							'description' => 'For template parts: filter by area ("header", "footer", "uncategorized"). For templates: omit.',
						],
						'search' => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring search on slug and title.',
						],
					],
				],
			],
			[
				'name'        => 'blocks_get_template',
				'description' => 'Get a single template by slug, returning its full block markup, content hash, and metadata. The slug is the name portion only — e.g. "single-post" for templates/single-post.html in the theme, or just "index" for the fallback index template. The tool resolves both theme-file and database-customized versions and tells you which you received.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => $slug_desc,
						],
						'type' => [
							'type'        => 'string',
							'enum'        => [ 'template', 'template_part' ],
							'description' => 'Default "template".',
						],
					],
					'required'   => [ 'slug' ],
				],
			],
			[
				'name'        => 'blocks_update_template',
				'description' => 'Create or update a database customization of a theme template. Pass the slug (e.g. "single-post" or "header"). If the theme provides the template, the new content shadows it; if no theme template exists, a new custom template is created. The previous state is captured for undo.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => $slug_desc,
						],
						'content' => [
							'type'        => 'string',
							'description' => 'The new block markup for the template body. Must be valid Gutenberg block markup. The title, description, and slug are derived automatically.',
						],
						'title' => [
							'type'        => 'string',
							'description' => 'Optional human-readable title. Defaults to the slug title-cased.',
						],
						'type' => [
							'type'        => 'string',
							'enum'        => [ 'template', 'template_part' ],
							'description' => 'Default "template".',
						],
						'area' => [
							'type'        => 'string',
							'description' => 'Template part area ("header", "footer", "uncategorized"). Only relevant when type is "template_part".',
						],
						'dry_run' => [
							'type'        => 'boolean',
							'description' => 'Preview what would be written without modifying the database.',
						],
					],
					'required'   => [ 'slug', 'content' ],
				],
			],
			[
				'name'        => 'blocks_revert_template',
				'description' => 'Remove a database customization, causing the theme-provided file to resurface. Pass the slug only — if a database post exists for that slug, it is deleted; if none exists, the tool reports that no customization was found and no changes were made. Captures the pre-deletion state for undo.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => $slug_desc,
						],
						'type' => [
							'type'        => 'string',
							'enum'        => [ 'template', 'template_part' ],
							'description' => 'Default "template".',
						],
						'dry_run' => [
							'type'        => 'boolean',
							'description' => 'Preview what would be deleted without modifying the database.',
						],
					],
					'required'   => [ 'slug' ],
				],
			],
			[
				'name'        => 'blocks_list_patterns',
				'description' => 'List registered block patterns available on this site. Read-only — patterns cannot be edited through this tool. Returns the pattern name, slug, category, description, and whether the pattern uses specific block types.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => 'Filter by pattern category slug, e.g. "buttons", "columns", "header".',
						],
						'search' => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring search on name and slug.',
						],
					],
				],
			],
			[
				'name'        => 'blocks_list_reusable',
				'description' => 'List reusable blocks (the wp_block post type). Returns each block\'s ID, title, and a snippet of its content. Reusable blocks are global — updating one is reflected everywhere it is used.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Number of reusable blocks per page. Default 20, max 100.',
						],
						'page' => [
							'type'        => 'integer',
							'description' => 'Page number, 1-indexed. Default 1.',
						],
						'search' => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring search on the block title.',
						],
					],
				],
			],
			[
				'name'        => 'blocks_get_reusable',
				'description' => 'Get a single reusable block by ID, returning its title, full block markup, and a token-budget-friendly summary of its internal block structure.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'reusable_id' => [
							'type'        => 'integer',
							'description' => 'The post ID of the reusable block (the wp_block post type).',
						],
					],
					'required'   => [ 'reusable_id' ],
				],
			],
			[
				'name'        => 'blocks_create_reusable',
				'description' => 'Create a new reusable block. Requires edit_posts capability. Returns the new block\'s ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title' => [
							'type'        => 'string',
							'description' => 'Human-readable title for the reusable block.',
						],
						'content' => [
							'type'        => 'string',
							'description' => 'Block markup for the reusable block. Must be valid Gutenberg block markup.',
						],
					],
					'required'   => [ 'title', 'content' ],
				],
			],
			[
				'name'        => 'blocks_update_reusable',
				'description' => 'Update an existing reusable block\'s content. Reusable blocks are global — this change is reflected everywhere the block is embedded. Captures the previous content for undo. Requires edit_post capability on the wp_block post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'reusable_id' => [
							'type'        => 'integer',
							'description' => 'The post ID of the reusable block to update.',
						],
						'title' => [
							'type'        => 'string',
							'description' => 'New title for the block. Omit to leave unchanged.',
						],
						'content' => [
							'type'        => 'string',
							'description' => 'New block markup. Required.',
						],
						'dry_run' => [
							'type'        => 'boolean',
							'description' => 'Preview without writing. Default false.',
						],
					],
					'required'   => [ 'reusable_id', 'content' ],
				],
			],
			[
				'name'        => 'blocks_delete_reusable',
				'description' => 'Permanently delete a reusable block. All places where this block is embedded will fall back to rendering nothing. Captures the deleted content for undo. Requires delete_post capability on the wp_block post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'reusable_id' => [
							'type'        => 'integer',
							'description' => 'The post ID of the reusable block to delete.',
						],
						'dry_run' => [
							'type'        => 'boolean',
							'description' => 'Preview without deleting. Default false.',
						],
					],
					'required'   => [ 'reusable_id' ],
				],
			],
		];
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'blocks_list_templates':
				return Template_Service::list_templates( $args );
			case 'blocks_get_template':
				return Template_Service::get_template( $args );
			case 'blocks_update_template':
				return Template_Service::update_template( $args );
			case 'blocks_revert_template':
				return Template_Service::revert_template( $args );
			case 'blocks_list_patterns':
				return Pattern_Service::list_patterns( $args );
			case 'blocks_list_reusable':
				return Reusable_Service::list_reusable( $args );
			case 'blocks_get_reusable':
				return Reusable_Service::get_reusable( $args );
			case 'blocks_create_reusable':
				return Reusable_Service::create_reusable( $args );
			case 'blocks_update_reusable':
				return Reusable_Service::update_reusable( $args );
			case 'blocks_delete_reusable':
				return Reusable_Service::delete_reusable( $args );
		}
		throw new \Exception( 'Unknown template/pattern tool: ' . esc_html( $name ) );
	}

}
