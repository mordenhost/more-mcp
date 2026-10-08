<?php

namespace More_MCP\Knowledge;

use More_MCP\Access\Safety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Skills {

	const POST_TYPE = 'more_mcp_skill';

	const PROMPT_PREFIX = 'skill_';

	const MAX_DESCRIPTION = 1024;

	const MAX_BODY_BYTES = 1048576;

	const MAX_SKILLS = 100;

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 20 );
		add_filter( 'parent_file', array( self::class, 'highlight_parent' ) );
		add_filter( 'submenu_file', array( self::class, 'highlight_submenu' ) );
		add_filter( 'user_can_richedit', array( self::class, 'disable_rich_editing' ) );
		add_action( 'edit_form_after_title', array( self::class, 'render_editor_help' ) );
	}

	const LIST_SLUG = 'edit.php?post_type=' . self::POST_TYPE;

	public static function add_menu(): void {
		add_submenu_page(
			'more-mcp',
			__( 'Skills', 'mordenhost-mcp-server' ),
			__( 'Skills (beta)', 'mordenhost-mcp-server' ),
			'manage_options',
			self::LIST_SLUG
		);
	}

	public static function highlight_parent( $parent_file ) {
		return self::is_skill_screen() ? 'more-mcp' : $parent_file;
	}

	public static function highlight_submenu( $submenu_file ) {
		return self::is_skill_screen() ? self::LIST_SLUG : $submenu_file;
	}

	private static function is_skill_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && isset( $screen->post_type ) && self::POST_TYPE === $screen->post_type;
	}

	public static function register_post_type(): void {
		register_post_type( self::POST_TYPE, self::post_type_args() );
	}

	public static function post_type_args(): array {

		

		

		$caps = array();
		foreach ( array(
			'read', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts',
			'delete_posts', 'delete_private_posts', 'delete_published_posts',
			'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts',
		) as $cap ) {
			$caps[ $cap ] = 'manage_options';
		}

		return array(
			'labels'              => array(
				'name'               => __( 'Skills', 'mordenhost-mcp-server' ),
				'singular_name'      => __( 'Skill', 'mordenhost-mcp-server' ),
				'menu_name'          => __( 'Skills (beta)', 'mordenhost-mcp-server' ),
				'all_items'          => __( 'Skills (beta)', 'mordenhost-mcp-server' ),
				'add_new'            => __( 'Add skill', 'mordenhost-mcp-server' ),
				'add_new_item'       => __( 'Add skill', 'mordenhost-mcp-server' ),
				'edit_item'          => __( 'Edit skill', 'mordenhost-mcp-server' ),
				'new_item'           => __( 'New skill', 'mordenhost-mcp-server' ),
				'search_items'       => __( 'Search skills', 'mordenhost-mcp-server' ),
				'not_found'          => __( 'No skills yet.', 'mordenhost-mcp-server' ),
				'not_found_in_trash' => __( 'No skills in the trash.', 'mordenhost-mcp-server' ),
			),
			'description'         => __( 'Instructions you write once for every AI agent connected to this site. Published skills appear in the agent\'s prompt list.', 'mordenhost-mcp-server' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,

			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => true,
			'delete_with_user'    => false,
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'capabilities'        => $caps,
			'map_meta_cap'        => true,
		);
	}

	public static function disable_rich_editing( $can ) {
		return self::POST_TYPE === get_post_type() ? false : $can;
	}

	public static function render_editor_help( $post ) {
		if ( ! isset( $post->post_type ) || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		echo '<div class="notice notice-info inline"><p>';
		echo esc_html__( 'Write the body in Markdown: connected AI agents receive it exactly as written. Put a one-line "when to use this" in the Excerpt box below; agents see it in their prompt list (up to 1,024 characters). Publish to make the skill available, switch it to Draft to turn it off. Revisions keep every earlier version.', 'mordenhost-mcp-server' );
		echo '</p></div>';
	}

	

	
	public static function list_prompts(): array {
		if ( ! Experimental::is_enabled() ) {
			return array();
		}

		$out = array();
		foreach ( self::catalog_entries() as $name => $skill ) {
			$description = $skill['description'];
			if ( strlen( $skill['body'] ) > self::MAX_BODY_BYTES ) {
				$description .= ' NOTE: this skill is larger than 1 MB and cannot be loaded; the site administrator needs to shorten it.';
			}
			$out[] = array(
				'name'        => $name,
				'title'       => $skill['title'],
				'description' => $description,
			);
		}
		return $out;
	}

	public static function get_prompt( string $name ): ?array {
		if ( 0 !== strpos( $name, self::PROMPT_PREFIX ) || ! Experimental::is_enabled() ) {
			return null;
		}

		$catalog = self::catalog_entries();
		if ( ! isset( $catalog[ $name ] ) ) {
			return null;
		}

		$skill = $catalog[ $name ];
		if ( strlen( $skill['body'] ) > self::MAX_BODY_BYTES ) {
			return array(
				'error' => array(
					'code'    => -32603,
					'message' => 'Skill "' . $name . '" is larger than 1 MB and is not served. Ask the site administrator to shorten it.',
				),
			);
		}

		return array(
			'description' => $skill['description'],
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => $skill['body'],
					),
				),
			),
		);
	}

	public static function catalog_entries(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => self::MAX_SKILLS,
				'orderby'          => 'ID',
				'order'            => 'ASC',

				'suppress_filters' => false,
			)
		);

		$catalog = array();
		foreach ( (array) $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
				continue;
			}
			$id   = (int) $post->ID;
			$name = self::prompt_name( (string) $post->post_name, $id );
			if ( isset( $catalog[ $name ] ) ) {

				
				$base = $name . '_' . $id;
				$name = $base;
				for ( $n = 2; isset( $catalog[ $name ] ); $n++ ) {
					$name = $base . '_' . $n;
				}
			}

			$title       = trim( (string) $post->post_title );
			$description = trim( (string) $post->post_excerpt );
			if ( '' === $description ) {
				$description = '' !== $title ? $title : $name;
			}

			$catalog[ $name ] = array(
				'id'          => $id,
				'title'       => '' !== $title ? $title : $name,
				'description' => self::clip( $description, self::MAX_DESCRIPTION ),
				'body'        => (string) $post->post_content,
			);
		}

		uasort(
			$catalog,
			static function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] ) ?: $a['id'] <=> $b['id'];
			}
		);
		return $catalog;
	}

	public static function deny_tool_writes( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post' ), true ) || ! Safety::in_tool_call() ) {
			return $caps;
		}
		$post = isset( $args[0] ) ? get_post( $args[0] ) : null;
		if ( $post && self::POST_TYPE === $post->post_type ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function prompt_name( string $slug, int $post_id ): string {
		$slug = strtolower( rawurldecode( $slug ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '_', $slug ), '_' );
		if ( '' === $slug ) {
			$slug = (string) $post_id;
		}
		return self::PROMPT_PREFIX . $slug;
	}

	private static function clip( string $text, int $limit ): string {
		if ( mb_strlen( $text, 'UTF-8' ) <= $limit ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $limit - 1, 'UTF-8' ) ) . '…';
	}
}
