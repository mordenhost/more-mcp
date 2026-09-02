<?php
namespace More_MCP\Blocks\Templates;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Pattern_Service {
	
	public static function list_patterns( array $args ): array {
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
			
			if ( $category !== '' && $p->category !== $category ) {
				continue;
			}
			
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
	
	public static function require_theme_options(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'You do not have permission to manage site templates. The edit_theme_options capability is required.' );
		}
	}

	private static function stylesheet(): string {
		return get_stylesheet();
	}

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
