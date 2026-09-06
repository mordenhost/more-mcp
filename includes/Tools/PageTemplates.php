<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PageTemplates implements Handler {

	const META_KEY = '_wp_page_template';

	const DEFAULT_VALUE = 'default';

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'wp_list_page_templates',
				'description' => 'List the templates a given post or post type can be rendered with, which is the set of valid values for wp_set_page_template. Resolved live from WordPress (get_page_templates), so the list reflects what is actually installed: the active theme\'s page templates, a block theme\'s custom templates, and any option a plugin injects. On an Elementor site that includes Elementor Canvas (no header or footer), Elementor Full Width (theme header and footer, full-width content), and Theme. Always includes "default", which means no explicit choice: the theme\'s own page template, or on an Elementor page the default set in Site Settings. Call this before wp_set_page_template rather than guessing a slug, because the set is per-site and per-post-type. Requires edit_posts, or edit_post on the target when post_id is given.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Resolve the list for this specific post, and report which template it currently uses. Some providers vary the list per document, so this is more accurate than post_type alone.',
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Resolve the list for a post type instead of a specific post (default "page"). Ignored when post_id is supplied.',
						),
					),
				),
			),
			array(
				'name'        => 'wp_set_page_template',
				'description' => 'Set which template renders one post or page, the per-document render context. This is what makes a single page a bare canvas with no theme header or footer, or wraps it in a specific custom template from a block theme. Pass a template value from wp_list_page_templates; a value outside that list is refused by name rather than stored, because WordPress would accept any string, read it back correctly, and silently fall through to the default at render time. Pass "default" to clear an explicit choice. Set dry_run=true to preview. The write is verified by reading the value back, so a host plugin blocking the change is reported instead of returning a false success. Requires edit_post on the target.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Post or page ID to change.',
						),
						'template' => array(
							'type'        => 'string',
							'description' => 'Template value from wp_list_page_templates (e.g. "elementor_canvas", "elementor_header_footer", "elementor_theme", a theme file like "page-wide.php", or a block theme\'s custom template slug). Use "default" for no explicit choice.',
						),
						'dry_run' => array(
							'type'        => 'boolean',
							'description' => 'Report what would change without writing. Default false.',
						),
					),
					'required'   => array( 'post_id', 'template' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array( 'wp_list_page_templates', 'wp_set_page_template' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_list_page_templates':
				return self::list_templates( $args );
			case 'wp_set_page_template':
				return self::set_template( $args );
		}
		throw new \Exception( 'Unknown page template tool: ' . esc_html( $name ) );
	}

	private static function resolve_choices( $post, string $post_type ): array {
		$choices = array( self::DEFAULT_VALUE => 'Default' );

		if ( ! function_exists( 'get_page_templates' ) ) {

			
			$theme_admin = ABSPATH . 'wp-admin/includes/theme.php';
			if ( file_exists( $theme_admin ) ) {
				require_once $theme_admin;
			}
		}

		if ( function_exists( 'get_page_templates' ) ) {
			$resolved = get_page_templates( $post, $post_type );
			if ( is_array( $resolved ) ) {
				
				foreach ( $resolved as $label => $slug ) {
					$slug = (string) $slug;
					if ( '' === $slug ) {
						continue;
					}
					$choices[ $slug ] = (string) $label;
				}
			}
		}

		return $choices;
	}

	private static function list_templates( array $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$post    = null;

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				throw new \Exception( 'Post ' . esc_html( (string) $post_id ) . ' does not exist.' );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				throw new \Exception( 'edit_post capability required on the target post.' );
			}
			$post_type = (string) $post->post_type;
		} else {
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new \Exception( 'edit_posts capability required.' );
			}
			$post_type = isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'page';
			if ( '' === $post_type ) {
				$post_type = 'page';
			}
		}

		$choices = self::resolve_choices( $post, $post_type );

		$response = array(
			'success'   => true,
			'post_type' => $post_type,
			'templates' => $choices,
			'count'     => count( $choices ),
		);

		if ( $post_id > 0 ) {
			$current = (string) get_post_meta( $post_id, self::META_KEY, true );
			$response['post_id'] = $post_id;
			$response['current'] = ( '' === $current ) ? self::DEFAULT_VALUE : $current;

			
			$response['current_is_stored'] = ( '' !== $current );
			if ( '' !== $current && ! isset( $choices[ $current ] ) ) {

				
				
				$response['warnings'][] = sprintf(
					'The stored template "%s" is not in the resolved list, so WordPress falls back to the default when rendering. This usually means the theme was switched or the plugin that provided it was deactivated.',
					$current
				);
			}
		}

		if ( 1 === count( $choices ) ) {
			$response['note'] = 'Only "default" is available: the active theme registers no page templates and no plugin injected any. There is nothing to choose between, so wp_set_page_template would only ever be able to set "default".';
		}

		return $response;
	}

	private static function set_template( array $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! array_key_exists( 'template', $args ) || ! is_string( $args['template'] ) || '' === trim( $args['template'] ) ) {
			throw new \Exception( 'template is required. Call wp_list_page_templates for the valid values on this site.' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post ' . esc_html( (string) $post_id ) . ' does not exist.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on the target post.' );
		}

		

		$template = sanitize_text_field( (string) $args['template'] );
		$choices  = self::resolve_choices( $post, (string) $post->post_type );

		if ( ! isset( $choices[ $template ] ) ) {

			
			throw new \Exception(
				'Template "' . esc_html( $template ) . '" is not available for post type '
				. esc_html( (string) $post->post_type ) . ' on this site. Valid values: '
				. esc_html( implode( ', ', array_keys( $choices ) ) )
				. '. Call wp_list_page_templates for the labels.'
			);
		}

		$before = (string) get_post_meta( $post_id, self::META_KEY, true );
		$before_effective = ( '' === $before ) ? self::DEFAULT_VALUE : $before;

		if ( ! empty( $args['dry_run'] ) ) {
			return array(
				'success'      => true,
				'dry_run'      => true,
				'post_id'      => $post_id,
				'from'         => $before_effective,
				'to'           => $template,
				'label'        => $choices[ $template ],
				'would_change' => ( $before_effective !== $template ),
			);
		}

		update_post_meta( $post_id, self::META_KEY, $template );

		
		
		$after     = (string) get_post_meta( $post_id, self::META_KEY, true );
		$after_eff = ( '' === $after ) ? self::DEFAULT_VALUE : $after;
		$verified  = ( $after === $template );

		$response = array(
			'success'  => true,
			'post_id'  => $post_id,
			'from'     => $before_effective,
			'to'       => $after_eff,
			'label'    => $choices[ $template ],
			'changed'  => ( $before_effective !== $after_eff ),
			'verified' => $verified,
		);

		if ( ! $verified ) {
			$response['verification_warning'] = sprintf(
				'The stored value is "%s", not the "%s" that was sent. A plugin filtering update_post_metadata for %s rejected the write.',
				$after_eff,
				$template,
				self::META_KEY
			);
		}

		

		
		$inval = self::invalidate_builder_state( $post_id );
		if ( ! empty( $inval ) ) {
			$response['cache_invalidation'] = $inval;
		}

		return $response;
	}

	private static function invalidate_builder_state( int $post_id ): array {
		if ( ! class_exists( '\More_MCP\Integrations\Elementor' ) ) {
			return array();
		}
		if ( ! method_exists( '\More_MCP\Integrations\Elementor', 'is_available' )
			|| ! \More_MCP\Integrations\Elementor::is_available() ) {
			return array();
		}
		
		if ( '' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return array();
		}
		try {
			return (array) \More_MCP\Integrations\Elementor::invalidate_derived_state_public( $post_id );
		} catch ( \Throwable $e ) {
			return array(
				'warnings' => array(
					'The template change was written, but clearing Elementor\'s cached render state failed: ' . $e->getMessage(),
				),
			);
		}
	}
}
