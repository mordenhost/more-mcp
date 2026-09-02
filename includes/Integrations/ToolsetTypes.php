<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolsetTypes {

	const OPTION_TYPES      = 'wpcf-custom-types';
	const OPTION_TAXONOMIES = 'wpcf-custom-taxonomies';
	const OPTION_FIELDS     = 'wpcf-fields';

	public static function is_available() {
		return defined( 'TYPES_VERSION' ) || defined( 'WPCF_VERSION' ) || function_exists( 'wpcf_get_active_custom_types' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'toolset-types' ),
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
				'name'        => 'toolset_list_post_types',
				'description' => 'List every custom post type registered through Toolset Types, with each one\'s slug, singular/plural labels, description, public and hierarchical flags, the editor features it supports, and the taxonomies bound to it. Registration DEFINITIONS only — the post type\'s content is read through the core post tools. Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'toolset_list_taxonomies',
				'description' => 'List every taxonomy registered through Toolset Types, with each one\'s slug, singular/plural labels, description, hierarchical flag, and the post types it attaches to. Registration DEFINITIONS only — the taxonomy\'s terms are read through the core term tools. Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'toolset_list_fields',
				'description' => 'List every Toolset Types custom field DEFINITION: its slug, name, type (textfield/checkbox/date/image/…), and description. Field definitions only — a field\'s stored values on any post are read through the meta tools, not here. Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'toolset_save_post_type',
				'description' => 'Create or update ONE Toolset Types post-type DEFINITION (the registration, not any post content). Pass the post-type slug plus fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. If the slug exists this updates it; otherwise it creates one, unless create_only is true. Passing taxonomies also binds this post type to those taxonomies (mirrored into each taxonomy definition, exactly as the Types admin does). Set dry_run=true to preview the resulting definition and the create/update decision without writing. Emits an undo token restoring the prior state of both the post-type and taxonomy options. Changes site structure: registering or reshaping a post type affects routing, admin menus, and REST exposure site-wide. It does NOT touch existing posts of this type.',
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
						'taxonomies'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Slugs of taxonomies to bind to this type. Only taxonomies that already exist as Types definitions are bound (mirrored into each taxonomy\'s attached-post-types map).' ),
						'create_only'    => array( 'type' => 'boolean', 'description' => 'If true, refuse when the slug already exists (never silently overwrite an existing definition). Default false.' ),
						'dry_run'        => array( 'type' => 'boolean', 'description' => 'Preview the resulting definition and the create/update decision without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'toolset_delete_post_type',
				'description' => 'Remove ONE Toolset Types post-type DEFINITION (unregister it). Existing posts of this type are NOT deleted — they remain in the database, orphaned (no admin UI, no front-end route) until the type is registered again. The type is also unbound from every taxonomy that attached to it (mirrored, as the Types admin does). Refuses unless the slug currently exists. Set dry_run=true to preview what would be removed; otherwise confirm=true is required because this changes site structure and hides content. Emits an undo token restoring the prior definition and bindings.',
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
				'name'        => 'toolset_save_taxonomy',
				'description' => 'Create or update ONE Toolset Types taxonomy DEFINITION (the registration, not any terms). Pass the taxonomy slug plus fields to set; omitted fields keep their prior value on an update, or take WordPress-sensible defaults on a create. object_types (the post types this taxonomy attaches to) is required and non-empty on create — this is what Types registers the taxonomy against, mirrored into each post-type definition. If the slug exists this updates it; otherwise it creates one, unless create_only is true. Set dry_run=true to preview without writing. Emits an undo token restoring the prior state of both options. Changes site structure; does NOT touch existing terms.',
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
						'object_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Post-type slugs this taxonomy attaches to. Required and non-empty on create. Only post types that already exist (Types-defined or a WordPress built-in) are bound.' ),
						'create_only'    => array( 'type' => 'boolean', 'description' => 'If true, refuse when the slug already exists. Default false.' ),
						'dry_run'        => array( 'type' => 'boolean', 'description' => 'Preview the resulting definition and the create/update decision without writing. Default false.' ),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'toolset_delete_taxonomy',
				'description' => 'Remove ONE Toolset Types taxonomy DEFINITION (unregister it). Existing terms are NOT deleted — they remain in the database, orphaned until the taxonomy is registered again. The taxonomy is also unbound from every post type it attached to (mirrored, as the Types admin does). Refuses unless the slug currently exists. Set dry_run=true to preview; otherwise confirm=true is required. Emits an undo token restoring the prior definition and bindings.',
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
			throw new \Exception( 'Toolset Types is not active.' );
		}
		if ( 'toolset_list_post_types' === $name ) {
			return self::list_post_types();
		}
		if ( 'toolset_list_taxonomies' === $name ) {
			return self::list_taxonomies();
		}
		if ( 'toolset_list_fields' === $name ) {
			return self::list_fields();
		}
		if ( 'toolset_save_post_type' === $name ) {
			return self::save_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'toolset_delete_post_type' === $name ) {
			return self::delete_post_type( is_array( $args ) ? $args : array() );
		}
		if ( 'toolset_save_taxonomy' === $name ) {
			return self::save_taxonomy( is_array( $args ) ? $args : array() );
		}
		if ( 'toolset_delete_taxonomy' === $name ) {
			return self::delete_taxonomy( is_array( $args ) ? $args : array() );
		}
		throw new \Exception( 'Unknown data-model tool: ' . esc_html( $name ) );
	}

	private static function list_post_types() {
		$raw = self::read( 'wpcf_get_active_custom_types', self::OPTION_TYPES );

		$post_types = array();
		foreach ( $raw as $slug => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$labels = isset( $def['labels'] ) && is_array( $def['labels'] ) ? $def['labels'] : array();
			$post_types[] = array(
				'slug'           => isset( $def['slug'] ) && '' !== $def['slug'] ? (string) $def['slug'] : (string) $slug,
				'label'          => isset( $labels['name'] ) ? (string) $labels['name'] : '',
				'singular_label' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'public'         => self::flag( $def, 'public', true ),
				'hierarchical'   => self::flag( $def, 'hierarchical', false ),

				'supports'       => self::enabled_keys( $def, 'supports' ),
				'taxonomies'     => self::enabled_keys( $def, 'taxonomies' ),
			);
		}
		usort( $post_types, static function ( $a, $b ) { return strcmp( (string) $a['slug'], (string) $b['slug'] ); } );

		return array(
			'provider'   => 'toolset-types',
			'available'  => true,
			'total'      => count( $post_types ),
			'post_types' => $post_types,
		);
	}

	private static function list_taxonomies() {
		$raw = self::read( 'wpcf_get_active_custom_taxonomies', self::OPTION_TAXONOMIES );

		$taxonomies = array();
		foreach ( $raw as $slug => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$labels = isset( $def['labels'] ) && is_array( $def['labels'] ) ? $def['labels'] : array();
			$taxonomies[] = array(
				'slug'           => isset( $def['slug'] ) && '' !== $def['slug'] ? (string) $def['slug'] : (string) $slug,
				'label'          => isset( $labels['name'] ) ? (string) $labels['name'] : '',
				'singular_label' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
				'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
				'hierarchical'   => self::flag( $def, 'hierarchical', false ),
				'public'         => self::flag( $def, 'public', true ),
				
				'object_types'   => self::enabled_keys( $def, 'supports' ),
			);
		}
		usort( $taxonomies, static function ( $a, $b ) { return strcmp( (string) $a['slug'], (string) $b['slug'] ); } );

		return array(
			'provider'   => 'toolset-types',
			'available'  => true,
			'total'      => count( $taxonomies ),
			'taxonomies' => $taxonomies,
		);
	}

	private static function list_fields() {

		
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION_FIELDS, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();

		$fields = array();
		foreach ( $raw as $slug => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$fields[] = array(
				'slug'        => isset( $def['slug'] ) && '' !== $def['slug'] ? (string) $def['slug'] : (string) $slug,
				'name'        => isset( $def['name'] ) ? (string) $def['name'] : '',
				'type'        => isset( $def['type'] ) ? (string) $def['type'] : '',
				'description' => isset( $def['description'] ) ? (string) $def['description'] : '',
			);
		}
		usort( $fields, static function ( $a, $b ) { return strcmp( (string) $a['slug'], (string) $b['slug'] ); } );

		return array(
			'provider'  => 'toolset-types',
			'available' => true,
			'total'     => count( $fields ),
			'fields'    => $fields,
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

		$types = self::read_raw( self::OPTION_TYPES );
		$taxes = self::read_raw( self::OPTION_TAXONOMIES );

		
		
		$types_before = $types;
		$taxes_before = $taxes;

		$existing  = isset( $types[ $slug ] ) && is_array( $types[ $slug ] ) ? $types[ $slug ] : null;
		$is_create = null === $existing;

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A post type with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		
		
		$defaults = array(
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
			'supports'     => array( 'title' => 1, 'editor' => 1 ),
			'taxonomies'   => array(),
		);
		$entry = $is_create ? $defaults : array_merge( $defaults, $existing );

		$entry['slug'] = $slug;
		if ( ! isset( $entry['labels'] ) || ! is_array( $entry['labels'] ) ) {
			$entry['labels'] = array();
		}

		if ( array_key_exists( 'label', $args ) ) {
			$entry['labels']['name'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$entry['labels']['singular_name'] = self::scalar_string( $args['singular_label'] );
		}
		if ( '' === (string) ( $entry['labels']['name'] ?? '' ) ) {
			$entry['labels']['name'] = self::titleize( $slug );
		}
		if ( '' === (string) ( $entry['labels']['singular_name'] ?? '' ) ) {
			$entry['labels']['singular_name'] = $entry['labels']['name'];
		}
		if ( array_key_exists( 'description', $args ) ) {
			$entry['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'public', 'hierarchical', 'has_archive', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$entry[ $flag ] = (bool) self::truthy( $args[ $flag ] );
			}
		}
		
		if ( array_key_exists( 'supports', $args ) ) {
			$entry['supports'] = self::to_key_map( $args['supports'] );
		}
		if ( array_key_exists( 'taxonomies', $args ) ) {

			$requested = self::clean_string_list( $args['taxonomies'] );
			$bound     = array();
			foreach ( $requested as $tax_slug ) {
				if ( isset( $taxes[ $tax_slug ] ) ) {
					$bound[ $tax_slug ] = 1;
				}
			}
			$entry['taxonomies'] = $bound;
		}

		

		
		$taxes_changed = false;
		if ( array_key_exists( 'taxonomies', $args ) ) {
			$bound_map = isset( $entry['taxonomies'] ) && is_array( $entry['taxonomies'] ) ? $entry['taxonomies'] : array();
			foreach ( $taxes as $tax_slug => $tax_def ) {
				if ( ! is_array( $tax_def ) ) {
					continue;
				}
				$supports = isset( $tax_def['supports'] ) && is_array( $tax_def['supports'] ) ? $tax_def['supports'] : array();
				$want     = isset( $bound_map[ $tax_slug ] );
				$has      = isset( $supports[ $slug ] );
				if ( $want && ! $has ) {
					$supports[ $slug ] = 1;
					$taxes[ $tax_slug ]['supports'] = $supports;
					$taxes_changed = true;
				} elseif ( ! $want && $has ) {
					unset( $supports[ $slug ] );
					$taxes[ $tax_slug ]['supports'] = $supports;
					$taxes_changed = true;
				}
			}
		}

		$preview = self::preview_post_type( $slug, $entry );

		if ( $dry_run ) {
			return array(
				'provider'   => 'toolset-types',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$types[ $slug ] = $entry;
		self::write_option( self::OPTION_TYPES, $types );
		if ( $taxes_changed ) {
			self::write_option( self::OPTION_TAXONOMIES, $taxes );
		}

		

		

		
		
		$states = array(
			Write_Verify::state_of(
				self::read_raw( self::OPTION_TYPES )[ $slug ] ?? Write_Verify::UNREADABLE,
				$entry,
				$is_create ? Write_Verify::NO_PRIOR : ( $types_before[ $slug ] ?? Write_Verify::NO_PRIOR )
			),
		);
		if ( $taxes_changed ) {
			$states[] = Write_Verify::state_of(
				self::read_raw( self::OPTION_TAXONOMIES ),
				$taxes,
				$taxes_before
			);
		}
		$state   = Write_Verify::combine( $states );
		$subject = $taxes_changed
			? __( 'post-type definition and its taxonomy bindings', 'more-mcp' )
			: __( 'post-type definition', 'more-mcp' );

		Write_Verify::refuse_if_discarded( $state, $subject );
		$verify = Write_Verify::report( $state, $subject );

		
		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'toolset_data_model_write',
				'summary'      => ( $is_create ? 'Undo create of' : 'Undo update of' ) . ' Toolset Types post type "' . $slug . '"',
				'target'       => array( 'kind' => 'post_type', 'slug' => $slug ),
				'pre_op_state' => array(
					self::OPTION_TYPES      => $types_before,
					self::OPTION_TAXONOMIES => $taxes_before,
				),
			)
		);

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'toolset-types',
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

		$types = self::read_raw( self::OPTION_TYPES );
		if ( ! isset( $types[ $slug ] ) || ! is_array( $types[ $slug ] ) ) {
			throw new \Exception( 'No Toolset Types post type with slug "' . esc_html( $slug ) . '" exists.' );
		}
		$taxes = self::read_raw( self::OPTION_TAXONOMIES );

		$preview = self::preview_post_type( $slug, $types[ $slug ] );

		if ( $dry_run ) {
			return array(
				'provider'   => 'toolset-types',
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

		$types_before = $types;
		$taxes_before = $taxes;

		unset( $types[ $slug ] );

		$taxes_changed = false;
		foreach ( $taxes as $tax_slug => $tax_def ) {
			if ( is_array( $tax_def ) && isset( $tax_def['supports'] ) && is_array( $tax_def['supports'] ) && isset( $tax_def['supports'][ $slug ] ) ) {
				unset( $taxes[ $tax_slug ]['supports'][ $slug ] );
				$taxes_changed = true;
			}
		}

		self::write_option( self::OPTION_TYPES, $types );
		if ( $taxes_changed ) {
			self::write_option( self::OPTION_TAXONOMIES, $taxes );
		}

		

		$states = array(
			Write_Verify::state_of_presence( ! isset( self::read_raw( self::OPTION_TYPES )[ $slug ] ) ),
		);
		if ( $taxes_changed ) {
			$states[] = Write_Verify::state_of(
				self::read_raw( self::OPTION_TAXONOMIES ),
				$taxes,
				$taxes_before
			);
		}
		$state   = Write_Verify::combine( $states );
		$subject = $taxes_changed
			? __( 'post-type definition removal and its taxonomy unbinding', 'more-mcp' )
			: __( 'post-type definition removal', 'more-mcp' );

		Write_Verify::refuse_if_discarded(
			$state,
			$subject,
			__( 'The definition is still present in the Types option.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, $subject );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'toolset_data_model_write',
				'summary'      => 'Undo delete of Toolset Types post type "' . $slug . '"',
				'target'       => array( 'kind' => 'post_type', 'slug' => $slug ),
				'pre_op_state' => array(
					self::OPTION_TYPES      => $types_before,
					self::OPTION_TAXONOMIES => $taxes_before,
				),
			)
		);

		return array_merge(
			array(
				'deleted'    => true,
				'provider'   => 'toolset-types',
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

		$taxes = self::read_raw( self::OPTION_TAXONOMIES );
		$types = self::read_raw( self::OPTION_TYPES );
		
		$taxes_before = $taxes;
		$types_before = $types;

		$existing  = isset( $taxes[ $slug ] ) && is_array( $taxes[ $slug ] ) ? $taxes[ $slug ] : null;
		$is_create = null === $existing;

		if ( ! $is_create && $create_only ) {
			throw new \Exception( 'A taxonomy with slug "' . esc_html( $slug ) . '" already exists and create_only was set.' );
		}

		$object_types = array_key_exists( 'object_types', $args ) ? self::clean_string_list( $args['object_types'] ) : null;
		if ( $is_create && empty( $object_types ) ) {
			throw new \Exception( 'object_types is required and must list at least one post-type slug when creating a taxonomy.' );
		}

		$defaults = array(
			'slug'         => $slug,
			'labels'       => array(
				'name'          => self::titleize( $slug ),
				'singular_name' => self::titleize( $slug ),
			),
			'description'  => '',
			'hierarchical' => false,
			'public'       => true,
			'show_in_rest' => true,
			'supports'     => array(),
		);
		$entry = $is_create ? $defaults : array_merge( $defaults, $existing );

		$entry['slug'] = $slug;
		if ( ! isset( $entry['labels'] ) || ! is_array( $entry['labels'] ) ) {
			$entry['labels'] = array();
		}

		if ( array_key_exists( 'label', $args ) ) {
			$entry['labels']['name'] = self::scalar_string( $args['label'] );
		}
		if ( array_key_exists( 'singular_label', $args ) ) {
			$entry['labels']['singular_name'] = self::scalar_string( $args['singular_label'] );
		}
		if ( '' === (string) ( $entry['labels']['name'] ?? '' ) ) {
			$entry['labels']['name'] = self::titleize( $slug );
		}
		if ( '' === (string) ( $entry['labels']['singular_name'] ?? '' ) ) {
			$entry['labels']['singular_name'] = $entry['labels']['name'];
		}
		if ( array_key_exists( 'description', $args ) ) {
			$entry['description'] = self::scalar_string( $args['description'] );
		}
		foreach ( array( 'hierarchical', 'public', 'show_in_rest' ) as $flag ) {
			if ( array_key_exists( $flag, $args ) ) {
				$entry[ $flag ] = (bool) self::truthy( $args[ $flag ] );
			}
		}

		
		
		$types_changed = false;
		if ( null !== $object_types ) {
			$supports = array();
			foreach ( $object_types as $pt ) {
				if ( isset( $types[ $pt ] ) || self::post_type_is_builtin( $pt ) ) {
					$supports[ $pt ] = 1;
				}
			}
			$entry['supports'] = $supports;

			foreach ( $types as $pt_slug => $pt_def ) {
				if ( ! is_array( $pt_def ) ) {
					continue;
				}
				$pt_taxes = isset( $pt_def['taxonomies'] ) && is_array( $pt_def['taxonomies'] ) ? $pt_def['taxonomies'] : array();
				$want     = isset( $supports[ $pt_slug ] );
				$has      = isset( $pt_taxes[ $slug ] );
				if ( $want && ! $has ) {
					$pt_taxes[ $slug ] = 1;
					$types[ $pt_slug ]['taxonomies'] = $pt_taxes;
					$types_changed = true;
				} elseif ( ! $want && $has ) {
					unset( $pt_taxes[ $slug ] );
					$types[ $pt_slug ]['taxonomies'] = $pt_taxes;
					$types_changed = true;
				}
			}
		}

		$preview = self::preview_taxonomy( $slug, $entry );

		if ( $dry_run ) {
			return array(
				'provider'   => 'toolset-types',
				'dry_run'    => true,
				'would'      => $is_create ? 'create' : 'update',
				'slug'       => $slug,
				'definition' => $preview,
			);
		}

		$taxes[ $slug ] = $entry;
		self::write_option( self::OPTION_TAXONOMIES, $taxes );
		if ( $types_changed ) {
			self::write_option( self::OPTION_TYPES, $types );
		}

		$states = array(
			Write_Verify::state_of(
				self::read_raw( self::OPTION_TAXONOMIES )[ $slug ] ?? Write_Verify::UNREADABLE,
				$entry,
				$is_create ? Write_Verify::NO_PRIOR : ( $taxes_before[ $slug ] ?? Write_Verify::NO_PRIOR )
			),
		);
		if ( $types_changed ) {
			$states[] = Write_Verify::state_of(
				self::read_raw( self::OPTION_TYPES ),
				$types,
				$types_before
			);
		}
		$state   = Write_Verify::combine( $states );
		$subject = $types_changed
			? __( 'taxonomy definition and its post-type bindings', 'more-mcp' )
			: __( 'taxonomy definition', 'more-mcp' );

		Write_Verify::refuse_if_discarded( $state, $subject );
		$verify = Write_Verify::report( $state, $subject );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'toolset_data_model_write',
				'summary'      => ( $is_create ? 'Undo create of' : 'Undo update of' ) . ' Toolset Types taxonomy "' . $slug . '"',
				'target'       => array( 'kind' => 'taxonomy', 'slug' => $slug ),
				'pre_op_state' => array(
					self::OPTION_TYPES      => $types_before,
					self::OPTION_TAXONOMIES => $taxes_before,
				),
			)
		);

		return array_merge(
			array(
				'written'    => true,
				'provider'   => 'toolset-types',
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

		$taxes = self::read_raw( self::OPTION_TAXONOMIES );
		if ( ! isset( $taxes[ $slug ] ) || ! is_array( $taxes[ $slug ] ) ) {
			throw new \Exception( 'No Toolset Types taxonomy with slug "' . esc_html( $slug ) . '" exists.' );
		}
		$types = self::read_raw( self::OPTION_TYPES );

		$preview = self::preview_taxonomy( $slug, $taxes[ $slug ] );

		if ( $dry_run ) {
			return array(
				'provider'   => 'toolset-types',
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

		$types_before = $types;
		$taxes_before = $taxes;

		unset( $taxes[ $slug ] );

		$types_changed = false;
		foreach ( $types as $pt_slug => $pt_def ) {
			if ( is_array( $pt_def ) && isset( $pt_def['taxonomies'] ) && is_array( $pt_def['taxonomies'] ) && isset( $pt_def['taxonomies'][ $slug ] ) ) {
				unset( $types[ $pt_slug ]['taxonomies'][ $slug ] );
				$types_changed = true;
			}
		}

		self::write_option( self::OPTION_TAXONOMIES, $taxes );
		if ( $types_changed ) {
			self::write_option( self::OPTION_TYPES, $types );
		}

		$states = array(
			Write_Verify::state_of_presence( ! isset( self::read_raw( self::OPTION_TAXONOMIES )[ $slug ] ) ),
		);
		if ( $types_changed ) {
			$states[] = Write_Verify::state_of(
				self::read_raw( self::OPTION_TYPES ),
				$types,
				$types_before
			);
		}
		$state   = Write_Verify::combine( $states );
		$subject = $types_changed
			? __( 'taxonomy definition removal and its post-type unbinding', 'more-mcp' )
			: __( 'taxonomy definition removal', 'more-mcp' );

		Write_Verify::refuse_if_discarded(
			$state,
			$subject,
			__( 'The definition is still present in the Types option.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, $subject );

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'toolset_data_model_write',
				'summary'      => 'Undo delete of Toolset Types taxonomy "' . $slug . '"',
				'target'       => array( 'kind' => 'taxonomy', 'slug' => $slug ),
				'pre_op_state' => array(
					self::OPTION_TYPES      => $types_before,
					self::OPTION_TAXONOMIES => $taxes_before,
				),
			)
		);

		return array_merge(
			array(
				'deleted'    => true,
				'provider'   => 'toolset-types',
				'slug'       => $slug,
				'definition' => $preview,
				'note'       => 'Definition removed. Existing terms remain in the database, orphaned until the taxonomy is registered again.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function undo_data_model_write( array $snapshot ) {
		$pre = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();

		$has_types = array_key_exists( self::OPTION_TYPES, $pre ) && is_array( $pre[ self::OPTION_TYPES ] );
		$has_taxes = array_key_exists( self::OPTION_TAXONOMIES, $pre ) && is_array( $pre[ self::OPTION_TAXONOMIES ] );
		if ( ! $has_types && ! $has_taxes ) {
			throw new \Exception( 'Toolset Types undo snapshot has no prior data to restore.' );
		}

		if ( $has_types ) {
			self::write_option( self::OPTION_TYPES, $pre[ self::OPTION_TYPES ] );
		}
		if ( $has_taxes ) {
			self::write_option( self::OPTION_TAXONOMIES, $pre[ self::OPTION_TAXONOMIES ] );
		}

		$target = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();

		return array(
			'success'          => true,
			'op'               => 'toolset_data_model_write',
			'provider'         => 'toolset-types',
			'restored'         => array_values( array_filter( array( $has_types ? self::OPTION_TYPES : null, $has_taxes ? self::OPTION_TAXONOMIES : null ) ) ),
			'target'           => $target,
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}

	private static function preview_post_type( $slug, $def ) {
		$labels = isset( $def['labels'] ) && is_array( $def['labels'] ) ? $def['labels'] : array();
		return array(
			'slug'           => (string) $slug,
			'label'          => isset( $labels['name'] ) ? (string) $labels['name'] : '',
			'singular_label' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'public'         => self::flag( $def, 'public', true ),
			'hierarchical'   => self::flag( $def, 'hierarchical', false ),
			'has_archive'    => self::flag( $def, 'has_archive', false ),
			'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),
			'supports'       => self::enabled_keys( $def, 'supports' ),
			'taxonomies'     => self::enabled_keys( $def, 'taxonomies' ),
		);
	}

	private static function preview_taxonomy( $slug, $def ) {
		$labels = isset( $def['labels'] ) && is_array( $def['labels'] ) ? $def['labels'] : array();
		return array(
			'slug'           => (string) $slug,
			'label'          => isset( $labels['name'] ) ? (string) $labels['name'] : '',
			'singular_label' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
			'description'    => isset( $def['description'] ) ? (string) $def['description'] : '',
			'hierarchical'   => self::flag( $def, 'hierarchical', false ),
			'public'         => self::flag( $def, 'public', true ),
			'show_in_rest'   => self::flag( $def, 'show_in_rest', true ),
			
			'object_types'   => self::enabled_keys( $def, 'supports' ),
		);
	}

	private static function read_raw( $option ) {
		$data = function_exists( 'get_option' ) ? get_option( $option, array() ) : array();
		return is_array( $data ) ? $data : array();
	}

	private static function write_option( $option, $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $option, $value );
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

	private static function truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		$s = strtolower( trim( (string) $v ) );
		if ( '' === $s || '0' === $s || 'false' === $s ) {
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

	private static function to_key_map( $v ) {
		$map = array();
		foreach ( self::clean_string_list( $v ) as $key ) {
			$map[ $key ] = 1;
		}
		return $map;
	}

	private static function post_type_is_builtin( $slug ) {
		$builtin = array( 'post', 'page', 'attachment' );
		return in_array( (string) $slug, $builtin, true );
	}

	private static function read( $accessor, $option ) {
		$data = null;
		if ( function_exists( $accessor ) ) {
			$data = call_user_func( $accessor );
		}
		if ( ! is_array( $data ) ) {
			$data = function_exists( 'get_option' ) ? get_option( $option, array() ) : array();
		}
		return is_array( $data ) ? $data : array();
	}

	private static function flag( $def, $key, $default ) {
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

	private static function enabled_keys( $def, $key ) {
		if ( empty( $def[ $key ] ) || ! is_array( $def[ $key ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $def[ $key ] as $name => $enabled ) {
			if ( $enabled && is_string( $name ) && '' !== $name ) {
				$out[] = $name;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
