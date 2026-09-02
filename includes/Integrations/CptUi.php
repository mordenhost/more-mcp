<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CptUi {

	public static function is_available() {
		return defined( 'CPTUI_VERSION' )
			|| function_exists( 'cptui_get_post_type_data' )
			|| function_exists( 'cptui_get_taxonomy_data' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'cptui' ),
			'capabilities' => array( 'data_models' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'cptui_list_post_types',
				'description' => 'List every custom post type registered through Custom Post Type UI, with each one\'s slug, singular and plural labels, description, public and hierarchical flags, the editor features it supports, the taxonomies bound to it, whether it has an archive, and whether it is exposed in the REST API. Returns registration DEFINITIONS only — the post type\'s content is read through the core post tools, not here. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'cptui_list_taxonomies',
				'description' => 'List every taxonomy registered through Custom Post Type UI, with each one\'s slug, singular and plural labels, description, hierarchical flag, the post types it attaches to, and whether it is public and exposed in the REST API. Returns registration DEFINITIONS only — the taxonomy\'s terms are read through the core term tools, not here. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'cptui_save_post_type',
				'description' => 'Create or update ONE Custom Post Type UI post-type DEFINITION (the registration, not any post content). Pass the post-type slug plus the fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. If the slug already exists this updates it; otherwise it creates a new one, unless create_only is true (then an existing slug is refused). Set dry_run=true to preview the resulting definition and whether it would create or update, without writing. Emits an undo token restoring the prior state. This changes site structure: registering or reshaping a post type affects routing, admin menus, and REST exposure site-wide. It does NOT touch existing posts of this type.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'           => array( 'type' => 'string', 'description' => 'Post-type slug (lowercased; max 20 chars per WordPress). Required.' ),
						'label'          => array( 'type' => 'string', 'description' => 'Plural label, e.g. "Books". Defaults to a title-cased slug on create.' ),
						'singular_label' => array( 'type' => 'string', 'description' => 'Singular label, e.g. "Book".' ),
						'description'    => array( 'type' => 'string', 'description' => 'Human description of the post type.' ),
						'public'         => array( 'type' => 'boolean', 'description' => 'Whether the type is public (queryable on the front end, shown in UI). Default true on create.' ),
						'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Page-like (true) vs post-like (false). Default false on create.' ),
						'has_archive'    => array( 'type' => 'boolean', 'description' => 'Whether the type has a post-type archive. Default false on create.' ),
						'show_in_rest'   => array( 'type' => 'boolean', 'description' => 'Expose in the REST API / block editor. Default true on create.' ),
						'supports'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Editor features, e.g. ["title","editor","thumbnail"]. Default ["title","editor"] on create.' ),
						'taxonomies'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Slugs of taxonomies to bind to this type.' ),
						'create_only'    => array( 'type' => 'boolean', 'description' => 'If true, refuse when the slug already exists (never silently overwrite an existing definition). Default false.' ),
						'dry_run'        => array( 'type' => 'boolean', 'description' => 'Preview the resulting definition and the create/update decision without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'cptui_delete_post_type',
				'description' => 'Remove ONE Custom Post Type UI post-type DEFINITION (unregister it). Existing posts of this type are NOT deleted — they remain in the database, orphaned (no admin UI, no front-end route) until the type is registered again. Refuses unless the slug currently exists. Set dry_run=true to preview what would be removed; otherwise confirm=true is required because this changes site structure and hides content. Emits an undo token restoring the prior definition.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'    => array( 'type' => 'string', 'description' => 'Slug of the post-type definition to remove. Required.' ),
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to actually remove the definition (guards a structural change). Ignored when dry_run is true.' ),
						'dry_run' => array( 'type' => 'boolean', 'description' => 'Preview the removal (what would be unregistered) without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'cptui_save_taxonomy',
				'description' => 'Create or update ONE Custom Post Type UI taxonomy DEFINITION (the registration, not any terms). Pass the taxonomy slug plus fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. object_types (the post types this taxonomy attaches to) is required on create and must be non-empty, mirroring CPT UI\'s own rule. If the slug exists this updates it; otherwise it creates one, unless create_only is true. Set dry_run=true to preview without writing. Emits an undo token restoring the prior state. Changes site structure; does NOT touch existing terms.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'           => array( 'type' => 'string', 'description' => 'Taxonomy slug (lowercased; max 32 chars per WordPress). Required.' ),
						'label'          => array( 'type' => 'string', 'description' => 'Plural label, e.g. "Genres".' ),
						'singular_label' => array( 'type' => 'string', 'description' => 'Singular label, e.g. "Genre".' ),
						'description'    => array( 'type' => 'string', 'description' => 'Human description of the taxonomy.' ),
						'hierarchical'   => array( 'type' => 'boolean', 'description' => 'Category-like (true) vs tag-like (false). Default false on create.' ),
						'public'         => array( 'type' => 'boolean', 'description' => 'Whether the taxonomy is public. Default true on create.' ),
						'show_in_rest'   => array( 'type' => 'boolean', 'description' => 'Expose in the REST API / block editor. Default true on create.' ),
						'object_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post-type slugs this taxonomy attaches to. Required and non-empty on create (CPT UI\'s own rule).' ),
						'create_only'    => array( 'type' => 'boolean', 'description' => 'If true, refuse when the slug already exists. Default false.' ),
						'dry_run'        => array( 'type' => 'boolean', 'description' => 'Preview the resulting definition and the create/update decision without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'cptui_delete_taxonomy',
				'description' => 'Remove ONE Custom Post Type UI taxonomy DEFINITION (unregister it). Existing terms are NOT deleted — they remain in the database, orphaned until the taxonomy is registered again. Refuses unless the slug currently exists. Set dry_run=true to preview; otherwise confirm=true is required. Emits an undo token restoring the prior definition.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'    => array( 'type' => 'string', 'description' => 'Slug of the taxonomy definition to remove. Required.' ),
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to actually remove the definition. Ignored when dry_run is true.' ),
						'dry_run' => array( 'type' => 'boolean', 'description' => 'Preview the removal without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use data-model tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Custom Post Type UI is not active.' );
		}

		if ( 'cptui_list_post_types' === $name ) {
			return self::list_post_types();
		}
		if ( 'cptui_list_taxonomies' === $name ) {
			return self::list_taxonomies();
		}
		if ( 'cptui_save_post_type' === $name ) {
			return self::save_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'cptui_delete_post_type' === $name ) {
			return self::delete_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'cptui_save_taxonomy' === $name ) {
			return self::save_taxonomy( is_array( $args ) ? $args : array() );
		}
		if ( 'cptui_delete_taxonomy' === $name ) {
			return self::delete_taxonomy( is_array( $args ) ? $args : array() );
		}
		throw new \Exception( 'Unknown data-model tool: ' . esc_html( $name ) );
	}

	private static function list_post_types() {
		$raw = self::read_option( 'cptui_get_post_type_data', 'cptui_post_types' );

		$post_types = array();
		foreach ( $raw as $slug => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$name = isset( $def['name'] ) && '' !== $def['name'] ? (string) $def['name'] : (string) $slug;

			$post_types[] = array(
				'slug'           => $name,
				'label'          => isset( $def['label'] ) ? (string) $def['label'] : '',
				'singular_label' => isset( $def['singular_label'] ) ? (string) $def['singular_label'] : '',
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'public'         => self::disp_bool( $def, 'public', true ),
				'hierarchical'   => self::disp_bool( $def, 'hierarchical', false ),
				'has_archive'    => self::disp_bool( $def, 'has_archive', false ),
				'show_in_rest'   => self::disp_bool( $def, 'show_in_rest', true ),
				'supports'       => self::string_list( $def, 'supports' ),
				'taxonomies'     => self::string_list( $def, 'taxonomies' ),
			);
		}

		usort(
			$post_types,
			static function ( $a, $b ) {
				return strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return array(
			'provider'   => 'cptui',
			'available'  => true,
			'total'      => count( $post_types ),
			'post_types' => $post_types,
		);
	}

	private static function list_taxonomies() {
		$raw = self::read_option( 'cptui_get_taxonomy_data', 'cptui_taxonomies' );

		$taxonomies = array();
		foreach ( $raw as $slug => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$name = isset( $def['name'] ) && '' !== $def['name'] ? (string) $def['name'] : (string) $slug;

			$taxonomies[] = array(
				'slug'           => $name,
				'label'          => isset( $def['label'] ) ? (string) $def['label'] : '',
				'singular_label' => isset( $def['singular_label'] ) ? (string) $def['singular_label'] : '',
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'hierarchical'   => self::disp_bool( $def, 'hierarchical', false ),
				'public'         => self::disp_bool( $def, 'public', true ),
				'show_in_rest'   => self::disp_bool( $def, 'show_in_rest', true ),
				
				'object_types'   => self::string_list( $def, 'object_types' ),
			);
		}

		usort(
			$taxonomies,
			static function ( $a, $b ) {
				return strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return array(
			'provider'   => 'cptui',
			'available'  => true,
			'total'      => count( $taxonomies ),
			'taxonomies' => $taxonomies,
		);
	}

	private static function save_post_type( array $args ) {
		$slug = self::clean_slug( $args['slug'] ?? '' );
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required.' );
		}
		if ( strlen( $slug ) > 20 ) {
			throw new \Exception( 'Post-type slug must be 20 characters or fewer (WordPress limit).' );
		}

		$dry_run     = ! empty( $args['dry_run'] );
		$create_only = ! empty( $args['create_only'] );

		$all      = self::read_option( 'cptui_get_post_type_data', 'cptui_post_types' );
		$before   = $all;
		$existing = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : null;
		$is_create = null === $existing;

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A post type with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		
		$defaults = array(
			'name'           => $slug,
			'label'          => self::titleize( $slug ),
			'singular_label' => self::titleize( $slug ),
			'description'    => '',
			'public'         => 'true',
			'hierarchical'   => 'false',
			'has_archive'    => 'false',
			'show_in_rest'   => 'true',
			'supports'       => array( 'title', 'editor' ),
			'taxonomies'     => array(),
		);
		$entry = $is_create ? $defaults : array_merge( $defaults, $existing );

		$entry['name'] = $slug;

		if ( array_key_exists( 'label', $args ) ) {
			$entry['label'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$entry['singular_label'] = self::scalar_string( $args['singular_label'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$entry['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'public', 'hierarchical', 'has_archive', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$entry[ $flag ] = self::to_cptui_bool( $args[ $flag ] );
			}
		}
		if ( array_key_exists( 'supports', $args ) ) {
			$entry['supports'] = self::clean_string_list( $args['supports'] );
		}
		if ( array_key_exists( 'taxonomies', $args ) ) {
			$entry['taxonomies'] = self::clean_string_list( $args['taxonomies'] );
		}

		$preview = self::preview_post_type( $slug, $entry );

		if ( $dry_run ) {
			return array(
				'provider'   => 'cptui',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$all[ $slug ] = $entry;
		self::write_option( 'cptui_post_types', $all );
		self::flag_rewrite_flush();

		

		

		
		$state = Write_Verify::state_of(
			self::reread_option( 'cptui_post_types' )[ $slug ] ?? Write_Verify::UNREADABLE,
			$entry,
			$is_create ? Write_Verify::NO_PRIOR : $existing
		);
		Write_Verify::refuse_if_discarded( $state, __( 'post-type definition', 'more-mcp' ) );
		$verify = Write_Verify::report( $state, __( 'post-type definition', 'more-mcp' ) );

		
		
		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'cptui_data_model_write',
				'summary'      => ( $is_create ? 'Undo create of' : 'Undo update of' ) . ' CPT UI post type "' . $slug . '"',
				'target'       => array( 'kind' => 'post_type', 'slug' => $slug ),
				'pre_op_state' => array( 'option' => 'cptui_post_types', 'data' => $before ),
			)
		);

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'cptui',
				'action'     => $is_create ? 'created' : 'updated',
				'slug'       => $slug,
				'definition' => $preview,
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	private static function delete_post_type( array $args ) {
		$slug = self::clean_slug( $args['slug'] ?? '' );
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required.' );
		}

		$dry_run = ! empty( $args['dry_run'] );
		$confirm = ! empty( $args['confirm'] );

		$all = self::read_option( 'cptui_get_post_type_data', 'cptui_post_types' );
		if ( ! isset( $all[ $slug ] ) || ! is_array( $all[ $slug ] ) ) {
			throw new \Exception( 'No Custom Post Type UI post type with slug "' . esc_html( $slug ) . '" exists.' );
		}

		$preview = self::preview_post_type( $slug, $all[ $slug ] );

		if ( $dry_run ) {
			return array(
				'provider'   => 'cptui',
				'dry_run'    => true,
				'would'      => 'delete',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => 'Existing posts of this type are not deleted; they are orphaned until the type is registered again.',
			);
		}
		if ( ! $confirm ) {
			throw new \Exception( 'Deleting a post-type definition changes site structure. Re-call with confirm=true (or dry_run=true to preview).' );
		}

		$before = $all;
		unset( $all[ $slug ] );
		self::write_option( 'cptui_post_types', $all );
		self::flag_rewrite_flush();

		

		$state = Write_Verify::state_of_presence( ! isset( self::reread_option( 'cptui_post_types' )[ $slug ] ) );
		Write_Verify::refuse_if_discarded(
			$state,
			__( 'post-type definition removal', 'more-mcp' ),
			__( 'The definition is still present in the CPT UI option.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, __( 'post-type definition removal', 'more-mcp' ) );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'cptui_data_model_write',
				'summary'      => 'Undo delete of CPT UI post type "' . $slug . '"',
				'target'       => array( 'kind' => 'post_type', 'slug' => $slug ),
				'pre_op_state' => array( 'option' => 'cptui_post_types', 'data' => $before ),
			)
		);

		return array_merge(
			array(
				'deleted'    => true,
				'provider'   => 'cptui',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => 'Definition removed. Existing posts of this type remain in the database, orphaned until the type is registered again.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	private static function save_taxonomy( array $args ) {
		$slug = self::clean_slug( $args['slug'] ?? '' );
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required.' );
		}
		if ( strlen( $slug ) > 32 ) {
			throw new \Exception( 'Taxonomy slug must be 32 characters or fewer (WordPress limit).' );
		}

		$dry_run     = ! empty( $args['dry_run'] );
		$create_only = ! empty( $args['create_only'] );

		$all      = self::read_option( 'cptui_get_taxonomy_data', 'cptui_taxonomies' );
		$before   = $all;
		$existing = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : null;
		$is_create = null === $existing;

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A taxonomy with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		$object_types = array_key_exists( 'object_types', $args ) ? self::clean_string_list( $args['object_types'] ) : null;
		if ( $is_create && empty( $object_types ) ) {

			throw new \Exception( 'object_types is required and must list at least one post-type slug when creating a taxonomy.' );
		}

		$defaults = array(
			'name'           => $slug,
			'label'          => self::titleize( $slug ),
			'singular_label' => self::titleize( $slug ),
			'description'    => '',
			'hierarchical'   => 'false',
			'public'         => 'true',
			'show_in_rest'   => 'true',
			'object_types'   => array(),
		);
		$entry = $is_create ? $defaults : array_merge( $defaults, $existing );

		$entry['name'] = $slug;

		if ( array_key_exists( 'label', $args ) ) {
			$entry['label'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$entry['singular_label'] = self::scalar_string( $args['singular_label'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$entry['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'hierarchical', 'public', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$entry[ $flag ] = self::to_cptui_bool( $args[ $flag ] );
			}
		}
		if ( null !== $object_types ) {
			$entry['object_types'] = $object_types;
		}

		$preview = self::preview_taxonomy( $slug, $entry );

		if ( $dry_run ) {
			return array(
				'provider'   => 'cptui',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$all[ $slug ] = $entry;
		self::write_option( 'cptui_taxonomies', $all );
		self::flag_rewrite_flush();

		$state = Write_Verify::state_of(
			self::reread_option( 'cptui_taxonomies' )[ $slug ] ?? Write_Verify::UNREADABLE,
			$entry,
			$is_create ? Write_Verify::NO_PRIOR : $existing
		);
		Write_Verify::refuse_if_discarded( $state, __( 'taxonomy definition', 'more-mcp' ) );
		$verify = Write_Verify::report( $state, __( 'taxonomy definition', 'more-mcp' ) );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'cptui_data_model_write',
				'summary'      => ( $is_create ? 'Undo create of' : 'Undo update of' ) . ' CPT UI taxonomy "' . $slug . '"',
				'target'       => array( 'kind' => 'taxonomy', 'slug' => $slug ),
				'pre_op_state' => array( 'option' => 'cptui_taxonomies', 'data' => $before ),
			)
		);

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'cptui',
				'action'     => $is_create ? 'created' : 'updated',
				'slug'       => $slug,
				'definition' => $preview,
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	private static function delete_taxonomy( array $args ) {
		$slug = self::clean_slug( $args['slug'] ?? '' );
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required.' );
		}

		$dry_run = ! empty( $args['dry_run'] );
		$confirm = ! empty( $args['confirm'] );

		$all = self::read_option( 'cptui_get_taxonomy_data', 'cptui_taxonomies' );
		if ( ! isset( $all[ $slug ] ) || ! is_array( $all[ $slug ] ) ) {
			throw new \Exception( 'No Custom Post Type UI taxonomy with slug "' . esc_html( $slug ) . '" exists.' );
		}

		$preview = self::preview_taxonomy( $slug, $all[ $slug ] );

		if ( $dry_run ) {
			return array(
				'provider'   => 'cptui',
				'dry_run'    => true,
				'would'      => 'delete',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => 'Existing terms are not deleted; they are orphaned until the taxonomy is registered again.',
			);
		}
		if ( ! $confirm ) {
			throw new \Exception( 'Deleting a taxonomy definition changes site structure. Re-call with confirm=true (or dry_run=true to preview).' );
		}

		$before = $all;
		unset( $all[ $slug ] );
		self::write_option( 'cptui_taxonomies', $all );
		self::flag_rewrite_flush();

		$state = Write_Verify::state_of_presence( ! isset( self::reread_option( 'cptui_taxonomies' )[ $slug ] ) );
		Write_Verify::refuse_if_discarded(
			$state,
			__( 'taxonomy definition removal', 'more-mcp' ),
			__( 'The definition is still present in the CPT UI option.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, __( 'taxonomy definition removal', 'more-mcp' ) );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'cptui_data_model_write',
				'summary'      => 'Undo delete of CPT UI taxonomy "' . $slug . '"',
				'target'       => array( 'kind' => 'taxonomy', 'slug' => $slug ),
				'pre_op_state' => array( 'option' => 'cptui_taxonomies', 'data' => $before ),
			)
		);

		return array_merge(
			array(
				'deleted'    => true,
				'provider'   => 'cptui',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => 'Definition removed. Existing terms remain in the database, orphaned until the taxonomy is registered again.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function undo_data_model_write( array $snapshot ) {
		$pre    = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();
		$option = isset( $pre['option'] ) ? (string) $pre['option'] : '';
		$data   = isset( $pre['data'] ) && is_array( $pre['data'] ) ? $pre['data'] : null;

		if ( 'cptui_post_types' !== $option && 'cptui_taxonomies' !== $option ) {
			throw new \Exception( 'CPT UI undo snapshot names an unexpected option.' );
		}
		if ( null === $data ) {
			throw new \Exception( 'CPT UI undo snapshot has no prior data to restore.' );
		}

		self::write_option( $option, $data );
		self::flag_rewrite_flush();

		$target = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();

		return array(
			'success'          => true,
			'op'               => 'cptui_data_model_write',
			'provider'         => 'cptui',
			'option'           => $option,
			'target'           => $target,
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}

	private static function preview_post_type( $slug, $def ) {
		return array(
			'slug'           => (string) $slug,
			'label'          => isset( $def['label'] ) ? (string) $def['label'] : '',
			'singular_label' => isset( $def['singular_label'] ) ? (string) $def['singular_label'] : '',
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'public'         => self::disp_bool( $def, 'public', true ),
			'hierarchical'   => self::disp_bool( $def, 'hierarchical', false ),
			'has_archive'    => self::disp_bool( $def, 'has_archive', false ),
			'show_in_rest'   => self::disp_bool( $def, 'show_in_rest', true ),
			'supports'       => self::string_list( $def, 'supports' ),
			'taxonomies'     => self::string_list( $def, 'taxonomies' ),
		);
	}

	private static function preview_taxonomy( $slug, $def ) {
		return array(
			'slug'           => (string) $slug,
			'label'          => isset( $def['label'] ) ? (string) $def['label'] : '',
			'singular_label' => isset( $def['singular_label'] ) ? (string) $def['singular_label'] : '',
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'hierarchical'   => self::disp_bool( $def, 'hierarchical', false ),
			'public'         => self::disp_bool( $def, 'public', true ),
			'show_in_rest'   => self::disp_bool( $def, 'show_in_rest', true ),
			'object_types'   => self::string_list( $def, 'object_types' ),
		);
	}

	private static function write_option( $option, $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $option, $value );
		}
	}

	private static function reread_option( $option ) {
		$data = function_exists( 'get_option' ) ? get_option( $option, array() ) : array();
		return is_array( $data ) ? $data : array();
	}

	private static function flag_rewrite_flush() {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'cptui_flush_rewrite_rules', 'true', 5 * 60 );
		}
	}

	private static function clean_slug( $raw ) {
		$raw = is_scalar( $raw ) ? strtolower( trim( (string) $raw ) ) : '';
		if ( '' === $raw ) {
			return '';
		}
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $raw );
		}
		return preg_replace( '/[^a-z0-9_\-]/', '', $raw );
	}

	private static function scalar_string( $v ) {
		return is_scalar( $v ) ? trim( (string) $v ) : '';
	}

	private static function titleize( $slug ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $slug ) );
	}

	private static function to_cptui_bool( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'true' : 'false';
		}
		$s = strtolower( trim( (string) $v ) );
		if ( 'false' === $s || '0' === $s || '' === $s ) {
			return 'false';
		}
		return 'true';
	}

	private static function clean_string_list( $v ) {
		if ( ! is_array( $v ) ) {
			return array();
		}
		$out = array();
		foreach ( $v as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$out[] = trim( (string) $item );
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function read_option( $accessor, $option ) {
		$data = null;
		if ( function_exists( $accessor ) ) {
			$data = call_user_func( $accessor );
		}
		if ( ! is_array( $data ) ) {
			$data = function_exists( 'get_option' ) ? get_option( $option, array() ) : array();
		}
		return is_array( $data ) ? $data : array();
	}

	private static function disp_bool( $def, $key, $default ) {
		if ( ! array_key_exists( $key, $def ) || '' === $def[ $key ] || null === $def[ $key ] ) {
			return (bool) $default;
		}
		$v = $def[ $key ];
		if ( is_bool( $v ) ) {
			return $v;
		}
		$v = strtolower( (string) $v );
		if ( 'false' === $v || '0' === $v ) {
			return false;
		}
		if ( 'true' === $v || '1' === $v ) {
			return true;
		}
		return (bool) $default;
	}

	private static function string_list( $def, $key ) {
		if ( empty( $def[ $key ] ) || ! is_array( $def[ $key ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $def[ $key ] as $item ) {
			if ( is_scalar( $item ) && '' !== (string) $item ) {
				$out[] = (string) $item;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
