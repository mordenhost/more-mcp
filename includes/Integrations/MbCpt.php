<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MbCpt {

	const PT_STORE  = 'mb-post-type';
	const TAX_STORE = 'mb-taxonomy';

	public static function is_available() {
		if ( defined( 'MB_CPT_VER' ) || defined( 'MB_CPT_DIR' ) ) {
			return true;
		}
		
		if ( function_exists( 'post_type_exists' )
			&& ( post_type_exists( self::PT_STORE ) || post_type_exists( self::TAX_STORE ) ) ) {
			return true;
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'mbcpt' ),
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
				'name'        => 'mbcpt_list_post_types',
				'description' => 'List every custom post type registered through MB Custom Post Types (Meta Box), with each one\'s slug, singular and plural labels, description, public and hierarchical flags, the editor features it supports, the taxonomies bound to it, whether it has an archive, and whether it is exposed in the REST API. Returns registration DEFINITIONS only — the post type\'s content is read through the core post tools, not here. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'mbcpt_list_taxonomies',
				'description' => 'List every taxonomy registered through MB Custom Post Types (Meta Box), with each one\'s slug, singular and plural labels, description, hierarchical flag, the post types it attaches to, and whether it is public and exposed in the REST API. Returns registration DEFINITIONS only — the taxonomy\'s terms are read through the core term tools, not here. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'mbcpt_save_post_type',
				'description' => 'Create or update ONE MB Custom Post Types post-type DEFINITION (the registration, not any post content). Pass the post-type slug plus the fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. If the slug already exists this updates its definition post; otherwise it creates a new published definition post, unless create_only is true (then an existing slug is refused). Set dry_run=true to preview the resulting definition and whether it would create or update, without writing. Emits an undo token. This changes site structure: registering or reshaping a post type affects routing, admin menus, and REST exposure site-wide. It does NOT touch existing posts of this type.',
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
				'name'        => 'mbcpt_delete_post_type',
				'description' => 'Remove ONE MB Custom Post Types post-type DEFINITION (unregister it). The definition post is moved to trash, not permanently deleted: because MB Custom Post Types only registers published definitions, trashing unregisters the post type, and it can be restored later (the undo token restores it). Existing posts of this type are NOT deleted — they remain in the database, orphaned (no admin UI, no front-end route) until the type is registered again. Refuses unless the slug currently exists. Set dry_run=true to preview what would be removed; otherwise confirm=true is required because this changes site structure and hides content. Emits an undo token restoring the definition.',
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
				'name'        => 'mbcpt_save_taxonomy',
				'description' => 'Create or update ONE MB Custom Post Types taxonomy DEFINITION (the registration, not any terms). Pass the taxonomy slug plus fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. object_types (the post types this taxonomy attaches to) is required and non-empty on create. If the slug exists this updates its definition post; otherwise it creates one, unless create_only is true. Set dry_run=true to preview without writing. Emits an undo token. Changes site structure; does NOT touch existing terms.',
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
						'object_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post-type slugs this taxonomy attaches to. Required and non-empty on create.' ),
						'create_only'    => array( 'type' => 'boolean', 'description' => 'If true, refuse when the slug already exists. Default false.' ),
						'dry_run'        => array( 'type' => 'boolean', 'description' => 'Preview the resulting definition and the create/update decision without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'mbcpt_delete_taxonomy',
				'description' => 'Remove ONE MB Custom Post Types taxonomy DEFINITION (unregister it). The definition post is moved to trash, not permanently deleted: trashing unregisters the taxonomy and it can be restored later (the undo token restores it). Existing terms are NOT deleted — they remain in the database, orphaned until the taxonomy is registered again. Refuses unless the slug currently exists. Set dry_run=true to preview; otherwise confirm=true is required. Emits an undo token restoring the definition.',
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
			throw new \Exception( 'MB Custom Post Types is not active.' );
		}

		if ( 'mbcpt_list_post_types' === $name ) {
			return self::list_post_types();
		}
		if ( 'mbcpt_list_taxonomies' === $name ) {
			return self::list_taxonomies();
		}
		if ( 'mbcpt_save_post_type' === $name ) {
			return self::save_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'mbcpt_delete_post_type' === $name ) {
			return self::delete_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'mbcpt_save_taxonomy' === $name ) {
			return self::save_taxonomy( is_array( $args ) ? $args : array() );
		}
		if ( 'mbcpt_delete_taxonomy' === $name ) {
			return self::delete_taxonomy( is_array( $args ) ? $args : array() );
		}
		throw new \Exception( 'Unknown data-model tool: ' . esc_html( $name ) );
	}

	private static function list_post_types() {
		$defs = self::read_definitions( self::PT_STORE );

		$post_types = array();
		foreach ( $defs as $def ) {
			$slug = self::def_slug( $def );
			if ( '' === $slug ) {
				continue;
			}

			$post_types[] = array(
				'slug'           => $slug,
				'label'          => self::label( $def, 'name' ),
				'singular_label' => self::label( $def, 'singular_name' ),
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'public'         => self::flag( $def, 'public', true ),
				'hierarchical'   => self::flag( $def, 'hierarchical', false ),
				'has_archive'    => self::flag( $def, 'has_archive', false ),
				'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),
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
			'provider'   => 'mbcpt',
			'available'  => true,
			'total'      => count( $post_types ),
			'post_types' => $post_types,
		);
	}

	private static function list_taxonomies() {
		$defs = self::read_definitions( self::TAX_STORE );

		$taxonomies = array();
		foreach ( $defs as $def ) {
			$slug = self::def_slug( $def );
			if ( '' === $slug ) {
				continue;
			}

			$taxonomies[] = array(
				'slug'           => $slug,
				'label'          => self::label( $def, 'name' ),
				'singular_label' => self::label( $def, 'singular_name' ),
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'hierarchical'   => self::flag( $def, 'hierarchical', false ),
				'public'         => self::flag( $def, 'public', true ),
				'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),

				'object_types'   => self::string_list( $def, 'types' ),
			);
		}

		usort(
			$taxonomies,
			static function ( $a, $b ) {
				return strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return array(
			'provider'   => 'mbcpt',
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

		$found     = self::find_def_post( self::PT_STORE, $slug );
		$is_create = ( null === $found );

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A post type with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		
		if ( $is_create ) {
			$settings = array(
				'slug'         => $slug,
				'labels'       => array(
					'name'          => self::titleize( $slug ),
					'singular_name' => self::titleize( $slug ),
				),
				'description'  => '',
				'public'       => true,
				'hierarchical' => false,
				'has_archive'  => false,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor' ),
				'taxonomies'   => array(),
			);
		} else {
			$settings = $found['settings'];
			if ( ! isset( $settings['labels'] ) || ! is_array( $settings['labels'] ) ) {
				$settings['labels'] = array();
			}
		}

		$settings['slug'] = $slug;

		
		if ( array_key_exists( 'label', $args ) ) {
			$settings['labels']['name'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$settings['labels']['singular_name'] = self::scalar_string( $args['singular_label'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$settings['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'public', 'hierarchical', 'has_archive', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$settings[ $flag ] = self::to_bool( $args[ $flag ] );
			}
		}
		if ( array_key_exists( 'supports', $args ) ) {
			$settings['supports'] = self::clean_string_list( $args['supports'] );
		}
		if ( array_key_exists( 'taxonomies', $args ) ) {
			$settings['taxonomies'] = self::clean_string_list( $args['taxonomies'] );
		}

		$preview = self::preview_post_type( $slug, $settings );

		if ( $dry_run ) {
			return array(
				'provider'   => 'mbcpt',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$post_title = self::label_or( $settings, 'singular_name', $slug );

		if ( $is_create ) {
			$post_id = self::insert_definition( self::PT_STORE, $post_title, $settings );
			$undo    = self::store_undo( 'post_type', $slug, $post_id, 'create', null );
		} else {
			$post_id = (int) $found['post_id'];
			$prior   = array(
				'post_content' => (string) $found['post_content'],
				'post_title'   => (string) $found['post_title'],
				'post_status'  => (string) $found['post_status'],
			);
			self::update_definition( $post_id, $post_title, $settings );

			
			$undo = self::store_undo( 'post_type', $slug, $post_id, 'update', $prior );
		}

		

		

		
		$prior_settings = $is_create
			? Write_Verify::NO_PRIOR
			: ( is_array( $found['settings'] ?? null ) ? $found['settings'] : Write_Verify::NO_PRIOR );
		$stored = self::reread_settings( $post_id );
		$state  = Write_Verify::state_of(
			null === $stored ? Write_Verify::UNREADABLE : $stored,
			$settings,
			$prior_settings
		);
		Write_Verify::refuse_if_discarded( $state, __( 'post-type definition', 'more-mcp' ) );
		$verify = Write_Verify::report( $state, __( 'post-type definition', 'more-mcp' ) );

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'mbcpt',
				'action'     => $is_create ? 'created' : 'updated',
				'slug'       => $slug,
				'post_id'    => $post_id,
				'definition' => $preview,
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	private static function delete_post_type( array $args ) {
		return self::delete_definition(
			self::PT_STORE,
			'post_type',
			$args,
			'No MB Custom Post Types post type with slug "%s" exists.',
			'Existing posts of this type are not deleted; they are orphaned until the type is registered again.',
			'Definition trashed (unregistered). Existing posts of this type remain in the database, orphaned until the type is registered again. Restore with the undo token.'
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

		$found     = self::find_def_post( self::TAX_STORE, $slug );
		$is_create = ( null === $found );

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A taxonomy with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		$object_types = array_key_exists( 'object_types', $args ) ? self::clean_string_list( $args['object_types'] ) : null;
		if ( $is_create && empty( $object_types ) ) {
			throw new \Exception( 'object_types is required and must list at least one post-type slug when creating a taxonomy.' );
		}

		if ( $is_create ) {
			$settings = array(
				'slug'         => $slug,
				'labels'       => array(
					'name'          => self::titleize( $slug ),
					'singular_name' => self::titleize( $slug ),
				),
				'description'  => '',
				'hierarchical' => false,
				'public'       => true,
				'show_in_rest' => true,
				
				'types'        => array(),
			);
		} else {
			$settings = $found['settings'];
			if ( ! isset( $settings['labels'] ) || ! is_array( $settings['labels'] ) ) {
				$settings['labels'] = array();
			}
		}

		$settings['slug'] = $slug;

		if ( array_key_exists( 'label', $args ) ) {
			$settings['labels']['name'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$settings['labels']['singular_name'] = self::scalar_string( $args['singular_label'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$settings['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'hierarchical', 'public', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$settings[ $flag ] = self::to_bool( $args[ $flag ] );
			}
		}
		if ( null !== $object_types ) {
			$settings['types'] = $object_types;
		}

		$preview = self::preview_taxonomy( $slug, $settings );

		if ( $dry_run ) {
			return array(
				'provider'   => 'mbcpt',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$post_title = self::label_or( $settings, 'singular_name', $slug );

		if ( $is_create ) {
			$post_id = self::insert_definition( self::TAX_STORE, $post_title, $settings );
			$undo    = self::store_undo( 'taxonomy', $slug, $post_id, 'create', null );
		} else {
			$post_id = (int) $found['post_id'];
			$prior   = array(
				'post_content' => (string) $found['post_content'],
				'post_title'   => (string) $found['post_title'],
				'post_status'  => (string) $found['post_status'],
			);
			self::update_definition( $post_id, $post_title, $settings );
			
			$undo = self::store_undo( 'taxonomy', $slug, $post_id, 'update', $prior );
		}

		$prior_settings = $is_create
			? Write_Verify::NO_PRIOR
			: ( is_array( $found['settings'] ?? null ) ? $found['settings'] : Write_Verify::NO_PRIOR );
		$stored = self::reread_settings( $post_id );
		$state  = Write_Verify::state_of(
			null === $stored ? Write_Verify::UNREADABLE : $stored,
			$settings,
			$prior_settings
		);
		Write_Verify::refuse_if_discarded( $state, __( 'taxonomy definition', 'more-mcp' ) );
		$verify = Write_Verify::report( $state, __( 'taxonomy definition', 'more-mcp' ) );

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'mbcpt',
				'action'     => $is_create ? 'created' : 'updated',
				'slug'       => $slug,
				'post_id'    => $post_id,
				'definition' => $preview,
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	private static function delete_taxonomy( array $args ) {
		return self::delete_definition(
			self::TAX_STORE,
			'taxonomy',
			$args,
			'No MB Custom Post Types taxonomy with slug "%s" exists.',
			'Existing terms are not deleted; they are orphaned until the taxonomy is registered again.',
			'Definition trashed (unregistered). Existing terms remain in the database, orphaned until the taxonomy is registered again. Restore with the undo token.'
		);
	}

	private static function delete_definition( $store, $kind, array $args, $missing_fmt, $dry_note, $done_note ) {
		$slug = self::clean_slug( $args['slug'] ?? '' );
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required.' );
		}

		$dry_run = ! empty( $args['dry_run'] );
		$confirm = ! empty( $args['confirm'] );

		$found = self::find_def_post( $store, $slug );
		if ( null === $found ) {
			throw new \Exception( esc_html( sprintf( $missing_fmt, $slug ) ) );
		}

		$preview = ( 'post_type' === $kind )
			? self::preview_post_type( $slug, $found['settings'] )
			: self::preview_taxonomy( $slug, $found['settings'] );

		if ( $dry_run ) {
			return array(
				'provider'   => 'mbcpt',
				'dry_run'    => true,
				'would'      => 'delete',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => $dry_note,
			);
		}
		if ( ! $confirm ) {
			throw new \Exception( 'Deleting a ' . esc_html( $kind ) . ' definition changes site structure. Re-call with confirm=true (or dry_run=true to preview).' );
		}

		$post_id = (int) $found['post_id'];
		$prior   = array(
			'post_content' => (string) $found['post_content'],
			'post_title'   => (string) $found['post_title'],
			'post_status'  => (string) $found['post_status'],
		);

		self::trash_definition( $post_id );

		

		

		
		$subject = ( 'post_type' === $kind )
			? __( 'post-type definition removal', 'more-mcp' )
			: __( 'taxonomy definition removal', 'more-mcp' );

		if ( ! function_exists( 'get_post_status' ) ) {
			$state = Write_Verify::UNKNOWN;
		} else {
			$state = Write_Verify::state_of_presence( 'publish' !== get_post_status( $post_id ) );
		}

		Write_Verify::refuse_if_discarded(
			$state,
			$subject,
			__( 'The definition post is still published, so the type remains registered.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, $subject );

		$undo = self::store_undo( $kind, $slug, $post_id, 'delete', $prior );

		return array_merge(
			array(
				'deleted'    => true,
				'provider'   => 'mbcpt',
				'slug'       => $slug,
				'post_id'    => $post_id,
				'definition' => $preview,
				'note'       => $done_note,
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function undo_data_model_write( array $snapshot ) {
		$pre     = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();
		$action  = isset( $pre['action'] ) ? (string) $pre['action'] : '';
		$post_id = isset( $pre['post_id'] ) ? (int) $pre['post_id'] : 0;
		$target  = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();

		if ( $post_id <= 0 ) {
			throw new \Exception( 'MB Custom Post Types undo snapshot has no target post.' );
		}

		if ( 'create' === $action ) {

			if ( function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( $post_id, true );
			}
		} elseif ( 'update' === $action ) {
			$prior = isset( $pre['prior'] ) && is_array( $pre['prior'] ) ? $pre['prior'] : array();
			if ( ! array_key_exists( 'post_content', $prior ) ) {
				throw new \Exception( 'MB Custom Post Types undo snapshot has no prior state to restore.' );
			}
			self::restore_post(
				$post_id,
				array(
					'ID'           => $post_id,
					'post_content' => function_exists( 'wp_slash' ) ? wp_slash( (string) $prior['post_content'] ) : (string) $prior['post_content'],
					'post_title'   => (string) ( $prior['post_title'] ?? '' ),
					'post_status'  => (string) ( $prior['post_status'] ?? 'publish' ),
				)
			);
		} elseif ( 'delete' === $action ) {
			$prior  = isset( $pre['prior'] ) && is_array( $pre['prior'] ) ? $pre['prior'] : array();
			$status = (string) ( $prior['post_status'] ?? 'publish' );
			
			self::restore_post(
				$post_id,
				array(
					'ID'          => $post_id,
					'post_status' => '' !== $status ? $status : 'publish',
				)
			);
		} else {
			throw new \Exception( 'MB Custom Post Types undo snapshot names an unknown action.' );
		}

		return array(
			'success'          => true,
			'op'               => 'mbcpt_data_model_write',
			'provider'         => 'mbcpt',
			'action'           => $action,
			'target'           => $target,
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}

	private static function store_undo( $kind, $slug, $post_id, $action, $prior ) {
		if ( ! class_exists( '\More_MCP\MCP\Undo_Store' ) ) {
			return null;
		}
		$verb    = array(
			'create' => 'create',
			'update' => 'update',
			'delete' => 'delete',
		);
		$summary = 'Undo ' . ( isset( $verb[ $action ] ) ? $verb[ $action ] : $action ) . ' of MB Custom Post Types ' . str_replace( '_', ' ', $kind ) . ' "' . $slug . '"';

		$pre_op_state = array(
			'action'  => $action,
			'post_id' => (int) $post_id,
		);
		if ( is_array( $prior ) ) {
			$pre_op_state['prior'] = $prior;
		}

		return \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'mbcpt_data_model_write',
				'summary'      => $summary,
				'target'       => array( 'kind' => $kind, 'slug' => $slug, 'post_id' => (int) $post_id ),
				'pre_op_state' => $pre_op_state,
			)
		);
	}

	private static function find_def_post( $store, $slug ) {
		if ( ! function_exists( 'get_posts' ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'posts_per_page'         => -1,
				'post_status'            => 'publish',
				'post_type'              => $store,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( (array) $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->post_content ) ) {
				continue;
			}
			$content  = (string) $post->post_content;
			$settings = '' !== $content ? json_decode( $content, true ) : null;
			if ( ! is_array( $settings ) || empty( $settings ) ) {
				continue;
			}
			if ( self::def_slug( $settings ) === (string) $slug ) {
				return array(
					'post_id'      => isset( $post->ID ) ? (int) $post->ID : 0,
					'post_content' => $content,
					'post_title'   => isset( $post->post_title ) ? (string) $post->post_title : '',
					'post_status'  => isset( $post->post_status ) ? (string) $post->post_status : 'publish',
					'settings'     => $settings,
				);
			}
		}
		return null;
	}

	private static function reread_settings( $post_id ) {
		if ( ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( (int) $post_id );
		if ( ! is_object( $post ) || ! isset( $post->post_content ) ) {
			return null;
		}
		$content  = (string) $post->post_content;
		$settings = '' !== $content ? json_decode( $content, true ) : null;
		return is_array( $settings ) ? $settings : null;
	}

	private static function insert_definition( $store, $post_title, array $settings ) {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			throw new \Exception( 'wp_insert_post is unavailable.' );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => $store,
				'post_status'  => 'publish',
				'post_title'   => (string) $post_title,
				'post_content' => self::encode_settings( $settings ),
			),
			true
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $id ) ) {
			throw new \Exception( 'Failed to create definition: ' . esc_html( $id->get_error_message() ) );
		}
		$id = (int) $id;
		if ( $id <= 0 ) {
			throw new \Exception( 'Failed to create the MB Custom Post Types definition post.' );
		}
		return $id;
	}

	private static function update_definition( $post_id, $post_title, array $settings ) {
		if ( ! function_exists( 'wp_update_post' ) ) {
			throw new \Exception( 'wp_update_post is unavailable.' );
		}
		$result = wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_title'   => (string) $post_title,
				'post_content' => self::encode_settings( $settings ),
			),
			true
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to update definition: ' . esc_html( $result->get_error_message() ) );
		}
	}

	private static function trash_definition( $post_id ) {
		if ( function_exists( 'wp_trash_post' ) ) {
			wp_trash_post( (int) $post_id );
			return;
		}
		if ( function_exists( 'wp_delete_post' ) ) {
			wp_delete_post( (int) $post_id, false );
			return;
		}
		throw new \Exception( 'No post-trash function is available.' );
	}

	private static function restore_post( $post_id, array $postarr ) {
		if ( ! function_exists( 'wp_update_post' ) ) {
			throw new \Exception( 'wp_update_post is unavailable, so this change cannot be undone.' );
		}
		$result = wp_update_post( $postarr, true );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to restore definition: ' . esc_html( $result->get_error_message() ) );
		}
	}

	private static function preview_post_type( $slug, $def ) {
		return array(
			'slug'           => (string) $slug,
			'label'          => self::label( $def, 'name' ),
			'singular_label' => self::label( $def, 'singular_name' ),
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'public'         => self::flag( $def, 'public', true ),
			'hierarchical'   => self::flag( $def, 'hierarchical', false ),
			'has_archive'    => self::flag( $def, 'has_archive', false ),
			'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),
			'supports'       => self::string_list( $def, 'supports' ),
			'taxonomies'     => self::string_list( $def, 'taxonomies' ),
		);
	}

	private static function preview_taxonomy( $slug, $def ) {
		return array(
			'slug'           => (string) $slug,
			'label'          => self::label( $def, 'name' ),
			'singular_label' => self::label( $def, 'singular_name' ),
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'hierarchical'   => self::flag( $def, 'hierarchical', false ),
			'public'         => self::flag( $def, 'public', true ),
			'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),
			'object_types'   => self::string_list( $def, 'types' ),
		);
	}

	private static function encode_settings( array $settings ) {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $settings ) : json_encode( $settings );
		$json = is_string( $json ) ? $json : '';
		return function_exists( 'wp_slash' ) ? wp_slash( $json ) : addslashes( $json );
	}

	private static function label_or( $settings, $key, $fallback ) {
		$v = self::label( $settings, $key );
		return '' !== $v ? $v : (string) $fallback;
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

	private static function to_bool( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (bool) (int) $v;
		}
		$s = strtolower( trim( (string) $v ) );
		if ( 'false' === $s || '0' === $s || 'no' === $s || '' === $s ) {
			return false;
		}
		return true;
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

	private static function read_definitions( $store_post_type ) {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'posts_per_page'         => -1,
				'post_status'            => 'publish',
				'post_type'              => $store_post_type,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$defs = array();
		foreach ( (array) $posts as $post ) {
			$content = is_object( $post ) && isset( $post->post_content ) ? $post->post_content : '';
			if ( '' === (string) $content ) {
				continue;
			}
			$settings = json_decode( (string) $content, true );
			if ( is_array( $settings ) && ! empty( $settings ) ) {
				$defs[] = $settings;
			}
		}
		return $defs;
	}

	private static function def_slug( $def ) {
		foreach ( array( 'slug', 'post_type', 'taxonomy' ) as $key ) {
			if ( isset( $def[ $key ] ) && is_scalar( $def[ $key ] ) && '' !== (string) $def[ $key ] ) {
				return (string) $def[ $key ];
			}
		}
		return '';
	}

	private static function label( $def, $key ) {
		if ( isset( $def['labels'] ) && is_array( $def['labels'] )
			&& isset( $def['labels'][ $key ] ) && is_scalar( $def['labels'][ $key ] ) ) {
			return (string) $def['labels'][ $key ];
		}
		return '';
	}

	private static function flag( $def, $key, $default ) {
		if ( ! array_key_exists( $key, $def ) || '' === $def[ $key ] || null === $def[ $key ] ) {
			return (bool) $default;
		}
		$v = $def[ $key ];
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (bool) (int) $v;
		}
		$v = strtolower( (string) $v );
		if ( 'false' === $v || '0' === $v || 'no' === $v || '' === $v ) {
			return false;
		}
		if ( 'true' === $v || '1' === $v || 'yes' === $v ) {
			return true;
		}
		return (bool) $default;
	}

	private static function string_list( $def, $key ) {
		if ( empty( $def[ $key ] ) ) {
			return array();
		}
		$val = $def[ $key ];
		
		if ( is_scalar( $val ) ) {
			$val = array( $val );
		}
		if ( ! is_array( $val ) ) {
			return array();
		}
		$out = array();
		foreach ( $val as $item ) {
			if ( is_scalar( $item ) && '' !== (string) $item ) {
				$out[] = (string) $item;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
