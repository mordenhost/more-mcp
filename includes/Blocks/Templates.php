<?php
/**
 * FSE site template management via the wp_template and wp_template_part
 * post types.
 *
 * WordPress resolves templates in a shadow-override pattern: a DB post
 * customizing a given slug always wins over the same-named file in the
 * active theme's templates/ directory. The theme file itself is never
 * touched by these tools — to revert, we delete the DB post so the theme
 * file surfaces again.
 *
 * Every tool in this file requires edit_theme_options capability, matching
 * WordPress core's registration of wp_template and wp_template_part, where
 * every post-level capability maps to edit_theme_options.
 *
 * The wp_theme taxonomy scopes customization to the current stylesheet, so
 * customizations to child themes do not leak into parent theme files.
 */

namespace More_MCP\Blocks;

use More_MCP\MCP\Undo_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Templates {

	/**
	 * The wp_theme taxonomy slug used by wp_template and wp_template_part.
	 */
	const THEME_TAXONOMY = 'wp_theme';

	/**
	 * Post types managed by this module.
	 */
	const POST_TYPE_TEMPLATE      = 'wp_template';
	const POST_TYPE_TEMPLATE_PART = 'wp_template_part';

	/**
	 * Tool definitions.
	 *
	 * @return array
	 */
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

	/**
	 * Dispatch a blocks_* template/reusable-block/pattern tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 * @return mixed
	 * @throws \Exception
	 */
	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'blocks_list_templates':
				return self::list_templates( $args );
			case 'blocks_get_template':
				return self::get_template( $args );
			case 'blocks_update_template':
				return self::update_template( $args );
			case 'blocks_revert_template':
				return self::revert_template( $args );
			case 'blocks_list_patterns':
				return self::list_patterns( $args );
			case 'blocks_list_reusable':
				return self::list_reusable( $args );
			case 'blocks_get_reusable':
				return self::get_reusable( $args );
			case 'blocks_create_reusable':
				return self::create_reusable( $args );
			case 'blocks_update_reusable':
				return self::update_reusable( $args );
			case 'blocks_delete_reusable':
				return self::delete_reusable( $args );
		}
		throw new \Exception( 'Unknown template/pattern tool: ' . esc_html( $name ) );
	}

	// ------------------------------------------------------------------
	//  Template tools
	// ------------------------------------------------------------------

	/**
	 * blocks_list_templates handler.
	 */
	private static function list_templates( array $args ): array {
		self::require_theme_options();
		self::require_block_theme();

		$requested = $args['type'] ?? null;
		$types     = [];
		if ( $requested === 'template' ) {
			$types = [ self::POST_TYPE_TEMPLATE ];
		} elseif ( $requested === 'template_part' ) {
			$types = [ self::POST_TYPE_TEMPLATE_PART ];
		} else {
			$types = [ self::POST_TYPE_TEMPLATE, self::POST_TYPE_TEMPLATE_PART ];
		}

		$area   = isset( $args['area'] ) ? sanitize_text_field( (string) $args['area'] ) : '';
		$search = strtolower( trim( (string) ( $args['search'] ?? '' ) ) );

		$templates = [];

		foreach ( $types as $template_type ) {
			$query = [];
			if ( $area !== '' && $template_type === self::POST_TYPE_TEMPLATE_PART ) {
				$query['area'] = $area;
			}

			// get_block_templates() merges both layers: theme files on disk and
			// database customizations, with the DB post winning per slug. Its
			// `source` field is what tells the caller which layer they got.
			$found = get_block_templates( $query, $template_type );

			foreach ( $found as $tpl ) {
				if ( ! $tpl instanceof \WP_Block_Template ) {
					continue;
				}
				if ( $search !== '' ) {
					$haystack = strtolower( $tpl->slug . ' ' . $tpl->title );
					if ( strpos( $haystack, $search ) === false ) {
						continue;
					}
				}

				$templates[] = [
					'id'           => $tpl->id,
					'slug'         => $tpl->slug,
					'type'         => $template_type,
					'title'        => $tpl->title,
					'description'  => $tpl->description,
					'source'       => $tpl->source,
					'is_custom'    => ( 'custom' === $tpl->source ),
					'area'         => $tpl->area ?? null,
					'content_hash' => hash( 'sha256', (string) $tpl->content ),
				];
			}
		}

		return [
			'count'     => count( $templates ),
			'theme'     => self::stylesheet(),
			'templates' => $templates,
			'note'      => 'source "theme" means the template still renders from the theme file; source "custom" means a database customization is shadowing it. Content is omitted here to keep the listing small — use blocks_get_template for a single template body.',
		];
	}

	/**
	 * blocks_get_template handler.
	 *
	 * @param array $args Tool args.
	 * @return array
	 * @throws \Exception
	 */
	private static function get_template( array $args ): array {
		self::require_theme_options();
		self::require_block_theme();

		if ( empty( $args['slug'] ) || ! is_string( $args['slug'] ) ) {
			throw new \Exception( 'slug is required.' );
		}

		$template_type = ( $args['type'] ?? 'template' ) === 'template_part'
			? self::POST_TYPE_TEMPLATE_PART
			: self::POST_TYPE_TEMPLATE;

		$slug = sanitize_title( $args['slug'] );

		$namespace = self::stylesheet();

		// 1. Check for a database-customized version first.
		$existing = get_page_by_path( $slug, OBJECT, $template_type );
		$source   = 'theme';
		if ( $existing instanceof \WP_Post ) {
			// Confirm it belongs to the current stylesheet.
			$terms = wp_get_object_terms( $existing->ID, self::THEME_TAXONOMY );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term->name === $namespace ) {
						$source = 'customized';
						break;
					}
				}
			}
		}

		// 2. Resolve via get_block_templates, which handles both paths.
		$found = get_block_templates(
			[ 'slug__in' => [ $slug ] ],
			$template_type
		);

		if ( empty( $found ) || ! $found[0] instanceof \WP_Block_Template ) {
			throw new \Exception(
				sprintf(
					'Template "%s" was not found as a theme file or a database customization.',
					esc_html( $slug )
				)
			);
		}

		$tpl = $found[0];

		return [
			'id'          => $tpl->id,
			'slug'        => $tpl->slug,
			'title'       => $tpl->title,
			'description' => $tpl->description,
			'content'     => $tpl->content,
			'content_hash' => hash( 'sha256', $tpl->content ),
			'source'      => $source,
			'is_custom'   => ( $source === 'customized' ),
			'wp_id'       => $tpl->wp_id,
			'post_type'   => $template_type,
			'theme'       => $namespace,
		];
	}

	/**
	 * blocks_update_template handler.
	 *
	 * Creates or updates a database-customized template post, shadowing
	 * the theme-provided file. The wp_theme taxonomy term is set to the
	 * current stylesheet so customizations are scoped correctly.
	 *
	 * @param array $args Tool args.
	 * @return array
	 * @throws \Exception
	 */
	private static function update_template( array $args ): array {
		self::require_theme_options();
		self::require_block_theme();

		if ( empty( $args['slug'] ) || ! is_string( $args['slug'] ) ) {
			throw new \Exception( 'slug is required.' );
		}
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) || $args['content'] === '' ) {
			throw new \Exception( 'content is required and must be non-empty.' );
		}

		$template_type = ( $args['type'] ?? 'template' ) === 'template_part'
			? self::POST_TYPE_TEMPLATE_PART
			: self::POST_TYPE_TEMPLATE;

		$slug      = sanitize_title( $args['slug'] );
		$namespace = self::stylesheet();
		$content   = $args['content'];
		$title     = ! empty( $args['title'] )
			? sanitize_text_field( $args['title'] )
			: ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		$area      = ! empty( $args['area'] ) ? sanitize_text_field( $args['area'] ) : 'uncategorized';

		// Find existing DB post, if any.
		$existing = get_page_by_path( $slug, OBJECT, $template_type );

		// Snapshot for undo (pre-mutation content or false if new).
		$pre_content = ( $existing instanceof \WP_Post ) ? $existing->post_content : false;

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'    => true,
				'action'     => $existing instanceof \WP_Post ? 'update' : 'create',
				'slug'       => $slug,
				'type'       => $template_type,
				'namespace'  => $namespace,
				'title'      => $title,
				'content'    => $content,
				'content_hash' => hash( 'sha256', $content ),
				'post_id'    => $existing instanceof \WP_Post ? $existing->ID : null,
			];
		}

		if ( $existing instanceof \WP_Post ) {
			// Update existing DB customization.
			wp_update_post(
				[
					'ID'           => $existing->ID,
					'post_title'   => $title,
					'post_content' => wp_slash( $content ),
					'post_excerpt' => $title,
				],
				true
			);

			// Ensure the area term is set for template parts.
			if ( $template_type === self::POST_TYPE_TEMPLATE_PART ) {
				wp_set_object_terms( $existing->ID, $area, 'wp_template_part_area' );
			}

			$fresh = get_post( $existing->ID );
			$verified = $fresh instanceof \WP_Post
				&& (string) $fresh->post_content === $content;

			// Undo: restoring the previous content.
			$undo = Undo_Store::store( [
				'op'           => 'blocks_template_update',
				'summary'      => sprintf( 'Reverted template "%s" to its previous content.', $slug ),
				'target'       => [ 'post_id' => $existing->ID, 'slug' => $slug ],
				'pre_op_state' => [ 'post_content' => (string) $pre_content ],
			] );

			return [
				'action'      => 'update',
				'post_id'     => $existing->ID,
				'slug'        => $slug,
				'content_hash' => hash( 'sha256', $content ),
				'verified'    => $verified,
				'undo'        => $undo,
			];
		}

		// Create a new database customization post.
		$post_id = wp_insert_post( [
			'post_type'    => $template_type,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
			'post_excerpt' => $title,
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create template: ' . esc_html( $post_id->get_error_message() ) );
		}

		// Tag with the current stylesheet so WP resolves the shadow layer.
		wp_set_object_terms( $post_id, $namespace, self::THEME_TAXONOMY );

		// For template parts, assign the area term.
		if ( $template_type === self::POST_TYPE_TEMPLATE_PART ) {
			wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
		}

		$undo = Undo_Store::store( [
			'op'           => 'blocks_template_create',
			'summary'      => sprintf( 'Removed database customization for template "%s", restoring the theme file.', $slug ),
			'target'       => [ 'post_id' => $post_id, 'slug' => $slug ],
			'pre_op_state' => [ 'deleted' => true ],
		] );

		return [
			'action'      => 'create',
			'post_id'     => $post_id,
			'slug'        => $slug,
			'theme'       => $namespace,
			'content_hash' => hash( 'sha256', $content ),
			'undo'        => $undo,
		];
	}

	/**
	 * blocks_revert_template handler.
	 *
	 * Deletes the DB-customized template post so the theme file resurfaces.
	 * If no DB post exists, this is a clean no-op.
	 *
	 * @param array $args Tool args.
	 * @return array
	 * @throws \Exception
	 */
	private static function revert_template( array $args ): array {
		self::require_theme_options();
		self::require_block_theme();

		if ( empty( $args['slug'] ) || ! is_string( $args['slug'] ) ) {
			throw new \Exception( 'slug is required.' );
		}

		$template_type = ( $args['type'] ?? 'template' ) === 'template_part'
			? self::POST_TYPE_TEMPLATE_PART
			: self::POST_TYPE_TEMPLATE;

		$slug = sanitize_title( $args['slug'] );

		$existing = get_page_by_path( $slug, OBJECT, $template_type );
		if ( ! $existing instanceof \WP_Post ) {
			throw new \Exception(
				sprintf(
					'Template "%s" has no database customization to revert. It is already rendering from the theme file (or does not exist).',
					esc_html( $slug )
				)
			);
		}

		// Confirm the customization belongs to the active theme rather than a
		// leftover from a previously-active one, so we never delete another
		// theme's stored template.
		$terms = wp_get_object_terms( $existing->ID, self::THEME_TAXONOMY );
		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}
		$current = self::stylesheet();
		$is_own  = false;
		foreach ( $terms as $term ) {
			if ( $term->name === $current ) {
				$is_own = true;
				break;
			}
		}
		if ( ! $is_own ) {
			throw new \Exception(
				sprintf(
					'The stored customization for "%s" belongs to a different theme, so it was not deleted.',
					esc_html( $slug )
				)
			);
		}

		// Snapshot for undo. Capture everything needed to re-create the post
		// BEFORE deleting it, so the undo branch never depends on a stale
		// object or a second database read.
		$pre_content   = (string) $existing->post_content;
		$pre_title     = (string) $existing->post_title;
		$pre_post_id   = (int) $existing->ID;

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'    => true,
				'action'     => 'revert',
				'slug'       => $slug,
				'type'       => $template_type,
				'post_id'    => $existing->ID,
				'pre_content' => $pre_content,
			];
		}

		$deleted = wp_delete_post( $pre_post_id, true );
		if ( ! $deleted ) {
			throw new \Exception( 'Failed to delete template post.' );
		}

		// Undo: re-create the post from the snapshot. post_type and title are
		// carried in the target because the post is gone by redemption time
		// and cannot be re-read to recover them.
		$undo = Undo_Store::store( [
			'op'           => 'blocks_template_revert',
			'summary'      => sprintf( 'Re-created the database customization for template "%s" from the undo snapshot.', $slug ),
			'target'       => [
				'post_id'   => $pre_post_id,
				'slug'      => $slug,
				'post_type' => $template_type,
				'title'     => $pre_title,
			],
			'pre_op_state' => [ 'post_content' => $pre_content ],
		] );

		return [
			'action'      => 'revert',
			'slug'        => $slug,
			'deleted_id'  => $pre_post_id,
			'note'        => 'The database customization has been removed. The theme-provided template file now renders for this slug.',
			'undo'        => $undo,
		];
	}

	// ------------------------------------------------------------------
	//  Pattern tools
	// ------------------------------------------------------------------

	/**
	 * blocks_list_patterns handler.
	 */
	private static function list_patterns( array $args ): array {
		self::require_theme_options();

		if ( ! function_exists( 'wp_register_block_pattern_category' ) ) {
			throw new \Exception( 'Block patterns are not available on this site.' );
		}

		if ( ! class_exists( '\WP_Block_Patterns_Registry' ) ) {
			throw new \Exception( 'Block pattern registry not available.' );
		}

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$all      = $registry->get_registered_patterns();

		$category = sanitize_title( $args['category'] ?? '' );
		$search   = strtolower( $args['search'] ?? '' );

		$patterns = [];
		foreach ( $all as $p ) {
			// Category filter.
			if ( $category !== '' && $p->category !== $category ) {
				continue;
			}
			// Search filter.
			if ( $search !== '' ) {
				$haystack = strtolower( $p->name . ' ' . $p->title . ' ' . $p->description );
				if ( strpos( $haystack, $search ) === false ) {
					continue;
				}
			}
			$patterns[] = [
				'name'        => $p->name,
				'title'       => $p->title,
				'description' => $p->description,
				'category'    => $p->category,
				'content'     => $p->content,
				'content_hash' => hash( 'sha256', $p->content ),
				'block_types' => $p->block_types ?? [],
			];
		}

		return [
			'count'    => count( $patterns ),
			'patterns' => $patterns,
		];
	}

	// ------------------------------------------------------------------
	//  Reusable block tools
	// ------------------------------------------------------------------

	/**
	 * blocks_list_reusable handler.
	 */
	private static function list_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to list reusable blocks.' );
		}

		$per_page = min( max( (int) ( $args['per_page'] ?? 20 ), 1 ), 100 );
		$page     = max( (int) ( $args['page'] ?? 1 ), 1 );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		$query = [
			'post_type'      => 'wp_block',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		if ( $search !== '' ) {
			$query['s'] = $search;
		}

		$posts = get_posts( $query );
		$total = wp_count_posts( 'wp_block' )->publish ?? 0;

		$blocks = [];
		foreach ( $posts as $post ) {
			$blocks[] = [
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'snippet'     => Parser::plain_text_snippet( $post->post_content, 200 ),
				'content_hash' => hash( 'sha256', $post->post_content ),
			];
		}

		return [
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'pages'         => (int) ceil( $total / $per_page ),
			'reusable_count' => count( $blocks ),
			'reusable'      => $blocks,
		];
	}

	/**
	 * blocks_get_reusable handler.
	 */
	private static function get_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read reusable blocks.' );
		}
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		$blocks = Parser::parse( $post->post_content );
		$summary = Parser::summarize( $blocks, '', 10, 80 );

		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'content_hash' => hash( 'sha256', $post->post_content ),
			'block_count' => count( $summary ),
			'structure'   => $summary,
			'reusable_note' => 'This block is global. Editing it is reflected everywhere it is embedded, including in pages you do not have edit_posts on.',
		];
	}

	/**
	 * blocks_create_reusable handler.
	 */
	private static function create_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to create reusable blocks.' );
		}
		if ( empty( $args['title'] ) || ! is_string( $args['title'] ) ) {
			throw new \Exception( 'title is required.' );
		}
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) || $args['content'] === '' ) {
			throw new \Exception( 'content is required.' );
		}

		$check = Parser::round_trip_check( Parser::parse( $args['content'] ) );
		if ( ! $check['ok'] ) {
			throw new \Exception( 'Block markup failed the self-consistency check: ' . esc_html( $check['reason'] ) );
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_slash( $check['content'] ),
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create reusable block: ' . esc_html( $post_id->get_error_message() ) );
		}

		return [
			'id'          => $post_id,
			'title'       => sanitize_text_field( $args['title'] ),
			'content_hash' => hash( 'sha256', $check['content'] ),
			'snippet'     => Parser::plain_text_snippet( $check['content'], 200 ),
		];
	}

	/**
	 * blocks_update_reusable handler.
	 */
	private static function update_reusable( array $args ): array {
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( 'You do not have permission to edit this reusable block.' );
		}
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) || $args['content'] === '' ) {
			throw new \Exception( 'content is required.' );
		}

		$pre_content = $post->post_content;
		$new_content = $args['content'];
		$new_title   = isset( $args['title'] ) && is_string( $args['title'] ) && $args['title'] !== ''
			? sanitize_text_field( $args['title'] )
			: $post->post_title;

		$check = Parser::round_trip_check( Parser::parse( $new_content ) );
		if ( ! $check['ok'] ) {
			throw new \Exception( 'Block markup failed the self-consistency check: ' . esc_html( $check['reason'] ) );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'       => true,
				'reusable_id'   => $id,
				'title'         => $new_title,
				'new_content'   => $check['content'],
				'content_hash'  => hash( 'sha256', $check['content'] ),
				'previous_hash' => hash( 'sha256', $pre_content ),
			];
		}

		$undo = Undo_Store::store( [
			'op'           => 'blocks_reusable_update',
			'summary'      => sprintf( 'Restored reusable block %d (%s) to its previous content.', $id, $new_title ),
			'target'       => [ 'post_id' => $id ],
			'pre_op_state' => [ 'post_content' => $pre_content ],
		] );

		$result = wp_update_post( [
			'ID'           => $id,
			'post_title'   => $new_title,
			'post_content' => wp_slash( $check['content'] ),
		], true );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to update reusable block: ' . esc_html( $result->get_error_message() ) );
		}

		$fresh    = get_post( $id );
		$verified = $fresh instanceof \WP_Post && (string) $fresh->post_content === $check['content'];

		return [
			'reusable_id'   => $id,
			'title'         => $new_title,
			'content_hash'  => hash( 'sha256', $check['content'] ),
			'verified'      => $verified,
			'previous_hash' => hash( 'sha256', $pre_content ),
			'undo'          => $undo,
		];
	}

	/**
	 * blocks_delete_reusable handler.
	 */
	private static function delete_reusable( array $args ): array {
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			throw new \Exception( 'You do not have permission to delete this reusable block.' );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'    => true,
				'reusable_id' => $id,
				'title'      => $post->post_title,
				'snippet'    => Parser::plain_text_snippet( $post->post_content, 200 ),
			];
		}

		$pre_content = $post->post_content;
		$title       = $post->post_title;

		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			throw new \Exception( 'Failed to delete reusable block.' );
		}

		$undo = Undo_Store::store( [
			'op'           => 'blocks_reusable_delete',
			'summary'      => sprintf( 'Restored deleted reusable block "%s".', $title ),
			'target'       => [ 'post_id' => $id ],
			'pre_op_state' => [
				'post_content' => $pre_content,
				'post_title'   => $title,
			],
		] );

		return [
			'reusable_id' => $id,
			'title'       => $title,
			'snippet'     => Parser::plain_text_snippet( $pre_content, 200 ),
			'undo'        => $undo,
			'note'        => 'This block has been permanently deleted. All places where it was embedded will now render nothing.',
		];
	}

	// ------------------------------------------------------------------
	//  Helpers
	// ------------------------------------------------------------------

	/**
	 * Require that edit_theme_options capability is available.
	 *
	 * All template operations map to edit_theme_options in WordPress core.
	 * Checking this before any query is first, so an unauthorized caller
	 * cannot discover which templates exist.
	 *
	 * @throws \Exception
	 */
	private static function require_theme_options(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'You do not have permission to manage site templates. The edit_theme_options capability is required.' );
		}
	}

	/**
	 * The stylesheet slug used as the wp_theme taxonomy term.
	 *
	 * Deliberately get_stylesheet() and NOT get_template(): on a child theme
	 * those differ, and WordPress scopes template customizations to the
	 * stylesheet. Using the parent slug would make a child theme's
	 * customizations invisible to core's own resolver.
	 *
	 * @return string
	 */
	private static function stylesheet(): string {
		return get_stylesheet();
	}

	/**
	 * Require an active block theme.
	 *
	 * wp_template / wp_template_part only drive rendering under Full Site
	 * Editing. On a classic theme these tools would happily write rows that
	 * never render, so fail loudly instead.
	 *
	 * @throws \Exception
	 */
	private static function require_block_theme(): void {
		if ( function_exists( 'wp_is_block_theme' ) && ! wp_is_block_theme() ) {
			throw new \Exception(
				sprintf(
					'The active theme (%s) is not a block theme, so site templates are not used for rendering. These tools require a Full Site Editing theme.',
					esc_html( wp_get_theme()->get( 'Name' ) )
				)
			);
		}
	}
}
