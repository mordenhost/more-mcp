<?php
namespace More_MCP\Blocks\Templates;
use More_MCP\MCP\Undo_Store;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Template_Service {
const THEME_TAXONOMY = 'wp_theme';
const POST_TYPE_TEMPLATE = 'wp_template';
const POST_TYPE_TEMPLATE_PART = 'wp_template_part';
	
	public static function list_templates( array $args ): array {
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

	public static function get_template( array $args ): array {
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

		$existing = get_page_by_path( $slug, OBJECT, $template_type );
		$source   = 'theme';
		if ( $existing instanceof \WP_Post ) {
			
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

	public static function update_template( array $args ): array {
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

		$existing = get_page_by_path( $slug, OBJECT, $template_type );

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
			
			wp_update_post(
				[
					'ID'           => $existing->ID,
					'post_title'   => $title,
					'post_content' => wp_slash( $content ),
					'post_excerpt' => $title,
				],
				true
			);

			if ( $template_type === self::POST_TYPE_TEMPLATE_PART ) {
				wp_set_object_terms( $existing->ID, $area, 'wp_template_part_area' );
			}

			$fresh = get_post( $existing->ID );
			$verified = $fresh instanceof \WP_Post
				&& (string) $fresh->post_content === $content;

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

		wp_set_object_terms( $post_id, $namespace, self::THEME_TAXONOMY );

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

	public static function revert_template( array $args ): array {
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

	public static function require_theme_options(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'You do not have permission to manage site templates. The edit_theme_options capability is required.' );
		}
	}

	public static function stylesheet(): string {
		return get_stylesheet();
	}

	public static function require_block_theme(): void {
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
