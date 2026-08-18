<?php
namespace More_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Runtime {
	public static function execute_tool( $name, $args ) {
		
		
		
		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Elementor tools.' );
		}

		if ( ! \More_MCP\Integrations\Elementor::is_available() ) {
			throw new \Exception( 'Elementor is not active' );
		}

		switch ( $name ) {
			case 'elementor_clone_page':
				return self::clone_page( $args );

			case 'elementor_replace_text':
				return self::replace_text( $args );

			case 'elementor_replace_image':
				return self::replace_image( $args );

			case 'elementor_get_page_outline':
				return self::get_page_outline( $args );

			case 'elementor_get_widget_settings':
				return self::get_widget_settings( $args );

			case 'elementor_list_local_templates':
				return self::list_local_templates( $args );

			case 'elementor_import_template':
				return self::import_template( $args );

			case 'elementor_add_widget':
				return self::add_widget( $args );

			case 'elementor_update_widget':
				return self::update_widget( $args );

			case 'elementor_delete_widget':
				return self::delete_widget( $args );

			case 'elementor_move_widget':
				return self::move_widget( $args );

			case 'elementor_get_loop_template':
				return self::get_loop_template( $args );

			case 'elementor_get_kit':
				return self::get_kit( $args );

			case 'elementor_get_kit_schema':
				return self::get_kit_schema( $args );

			case 'elementor_get_kit_fonts':
				return self::get_kit_fonts( $args );

			case 'elementor_update_kit':
				return self::update_kit( $args );

			case 'elementor_sync_library_type':
				return self::sync_library_type( $args );

			default:
				throw new \Exception( 'Unknown Elementor tool: ' . esc_html( $name ) );
		}
	}

	
	
	

	
	private static function update_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		if ( ! isset( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
			throw new \Exception( 'settings is required and must be an object of setting keys to apply.' );
		}

		$tree    = self::load_tree( $post_id );
		$current = self::find_element_by_id( $tree, $element_id );
		if ( $current === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $current, $args, 'update' );

		
		
		
		
		
		$widget_type   = (string) ( $current['widgetType'] ?? '' );
		$detected_type = $widget_type !== ''
			? $widget_type
			: (string) ( $current['elType'] ?? '' );
		if ( self::is_atomic_widget_type( $detected_type ) ) {
			throw new \Exception( sprintf(
				'Element %s is an Elementor V4 atomic element (%s). Its settings schema is not publicly documented, so More MCP will not edit it rather than risk corrupting it — the same reason elementor_replace_text skips atomic widgets. Edit it in the Elementor editor, or delete and re-add it.',
				esc_html( $element_id ),
				esc_html( $detected_type )
			) );
		}

		$existing = ( isset( $current['settings'] ) && is_array( $current['settings'] ) )
			? $current['settings']
			: [];
		$replace  = ! empty( $args['replace_settings'] );

		if ( $replace ) {
			$new_settings = $args['settings'];
		} else {
			$new_settings = $existing;
			foreach ( $args['settings'] as $key => $value ) {
				
				
				if ( null === $value ) {
					unset( $new_settings[ $key ] );
					continue;
				}
				$new_settings[ $key ] = $value;
			}
		}

		$removed_keys = $replace
			? array_values( array_diff( array_keys( $existing ), array_keys( (array) $new_settings ) ) )
			: array_values( array_filter( array_keys( $args['settings'] ), static function ( $k ) use ( $args ) {
				return null === $args['settings'][ $k ];
			} ) );

		
		
		
		
		
		
		
		$merged_element              = $current;
		$merged_element['settings']  = $new_settings;
		
		
		
		$doc_errors = self::validate_document( [ $merged_element ], true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'          => true,
				'written'          => false,
				'post_id'          => $post_id,
				'element_id'       => $element_id,
				'element_type'     => (string) ( $current['elType'] ?? 'unknown' ),
				'widget_type'      => $widget_type !== '' ? $widget_type : null,
				'mode'             => $replace ? 'replace' : 'merge',
				'settings_before'  => $existing,
				'settings_after'   => $new_settings,
				'keys_removed'     => $removed_keys,
			];
		}

		$updated_tree = self::replace_element_settings( $tree, $element_id, $new_settings );

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_update_widget on element %s of post %d (%s mode)',
				$element_id,
				$post_id,
				$replace ? 'replace' : 'merge'
			),
			'target'       => [ 'post_id' => $post_id ],
			
			
			
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		self::save_tree( $post_id, $updated_tree );
		$inval = self::invalidate_derived_state( $post_id );

		
		
		$verify_tree = self::load_tree( $post_id );
		$verify_el   = self::find_element_by_id( $verify_tree, $element_id );
		$stored      = ( $verify_el !== null && isset( $verify_el['settings'] ) && is_array( $verify_el['settings'] ) )
			? $verify_el['settings']
			: [];

		return self::with_invalidation( [
			'success'      => true,
			'written'      => true,
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'element_type' => (string) ( $current['elType'] ?? 'unknown' ),
			'widget_type'  => $widget_type !== '' ? $widget_type : null,
			'mode'         => $replace ? 'replace' : 'merge',
			'settings'     => $stored,
			'keys_removed' => $removed_keys,
			'verified'     => $stored == $new_settings, 
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	
	private static function delete_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}

		$tree    = self::load_tree( $post_id );
		$current = self::find_element_by_id( $tree, $element_id );
		if ( $current === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $current, $args, 'delete' );

		$widget_type = (string) ( $current['widgetType'] ?? '' );
		
		
		
		$descendants = self::count_descendants( $current );

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'             => true,
				'written'             => false,
				'post_id'             => $post_id,
				'element_id'          => $element_id,
				'element_type'        => (string) ( $current['elType'] ?? 'unknown' ),
				'widget_type'         => $widget_type !== '' ? $widget_type : null,
				'descendants_removed' => $descendants,
				'total_removed'       => $descendants + 1,
				'outline_removed'     => self::build_outline( [ $current ], 0 ),
			];
		}

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_delete_widget removed element %s (+%d descendants) from post %d',
				$element_id,
				$descendants,
				$post_id
			),
			'target'       => [ 'post_id' => $post_id ],
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		$updated_tree = self::remove_element_by_id( $tree, $element_id );
		self::save_tree( $post_id, $updated_tree );
		$inval = self::invalidate_derived_state( $post_id );

		
		$verify_tree = self::load_tree( $post_id );
		$still_there = self::find_element_by_id( $verify_tree, $element_id ) !== null;

		return self::with_invalidation( [
			'success'             => true,
			'written'             => true,
			'post_id'             => $post_id,
			'element_id'          => $element_id,
			'element_type'        => (string) ( $current['elType'] ?? 'unknown' ),
			'widget_type'         => $widget_type !== '' ? $widget_type : null,
			'descendants_removed' => $descendants,
			'total_removed'       => $descendants + 1,
			'verified'            => ! $still_there,
			'undo'                => $undo,
			'edit_url'            => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	
	private static function move_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		$target_id  = isset( $args['target_id'] ) ? (string) $args['target_id'] : '';
		$position   = isset( $args['position'] ) ? (string) $args['position'] : '';
		if ( $post_id <= 0 || $element_id === '' || $target_id === '' ) {
			throw new \Exception( 'post_id, element_id, and target_id are required.' );
		}
		if ( ! in_array( $position, [ 'before', 'after', 'first_child', 'last_child' ], true ) ) {
			throw new \Exception( 'position must be one of: before, after, first_child, last_child.' );
		}
		if ( $element_id === $target_id ) {
			throw new \Exception( 'element_id and target_id must differ — an element cannot be moved relative to itself.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}

		$tree   = self::load_tree( $post_id );
		$source = self::find_element_by_id( $tree, $element_id );
		if ( $source === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $source, $args, 'move' );

		$target = self::find_element_by_id( $tree, $target_id );
		if ( $target === null ) {
			throw new \Exception( 'target_id not found in this page: ' . esc_html( $target_id ) );
		}

		
		
		
		if ( self::find_element_by_id( [ $source ], $target_id ) !== null ) {
			throw new \Exception( 'Cannot move an element into its own subtree; target_id is a descendant of element_id.' );
		}

		
		if ( in_array( $position, [ 'first_child', 'last_child' ], true ) ) {
			$target_type = (string) ( $target['elType'] ?? '' );
			if ( ! in_array( $target_type, [ 'container', 'section', 'column' ], true ) ) {
				throw new \Exception( sprintf(
					'position "%s" requires target_id to be a container, section, or column. Found: %s.',
					esc_html( $position ),
					esc_html( $target_type !== '' ? $target_type : 'unknown' )
				) );
			}
		}

		$widget_type = (string) ( $source['widgetType'] ?? '' );

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'      => true,
				'written'      => false,
				'post_id'      => $post_id,
				'element_id'   => $element_id,
				'target_id'    => $target_id,
				'position'     => $position,
				'element_type' => (string) ( $source['elType'] ?? 'unknown' ),
				'widget_type'  => $widget_type !== '' ? $widget_type : null,
			];
		}

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_move_widget moved element %s %s %s in post %d',
				$element_id,
				$position,
				$target_id,
				$post_id
			),
			'target'       => [ 'post_id' => $post_id ],
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		
		$without = self::remove_element_by_id( $tree, $element_id );
		$moved   = self::insert_relative_to( $without, $target_id, $position, $source );

		self::save_tree( $post_id, $moved );
		$inval = self::invalidate_derived_state( $post_id );

		
		$verify_tree     = self::load_tree( $post_id );
		$verify_parent   = self::parent_id_of( $verify_tree, $element_id );
		$expected_parent = in_array( $position, [ 'first_child', 'last_child' ], true )
			? $target_id
			: self::parent_id_of( $verify_tree, $target_id );
		$verified = self::find_element_by_id( $verify_tree, $element_id ) !== null
			&& $verify_parent === $expected_parent;

		return self::with_invalidation( [
			'success'      => true,
			'written'      => true,
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'target_id'    => $target_id,
			'position'     => $position,
			'element_type' => (string) ( $source['elType'] ?? 'unknown' ),
			'widget_type'  => $widget_type !== '' ? $widget_type : null,
			'verified'     => $verified,
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	

	
	private static function insert_relative_to( $tree, $target_id, $position, $new_element ) {
		if ( 'first_child' === $position || 'last_child' === $position ) {
			$pos = 'first_child' === $position ? 0 : null;
			return self::walk_and_insert( $tree, $target_id, $pos, $new_element );
		}
		return self::walk_and_insert_sibling( $tree, $target_id, $position, $new_element );
	}

	
	private static function walk_and_insert_sibling( $list, $target_id, $position, $new_element ) {
		if ( ! is_array( $list ) ) {
			return $list;
		}
		$index = null;
		foreach ( $list as $i => $el ) {
			if ( is_array( $el ) && isset( $el['id'] ) && (string) $el['id'] === (string) $target_id ) {
				$index = $i;
				break;
			}
		}
		if ( $index !== null ) {
			$insert_at = 'after' === $position ? $index + 1 : $index;
			array_splice( $list, $insert_at, 0, [ $new_element ] );
			return array_values( $list );
		}
		$out = [];
		foreach ( $list as $el ) {
			if ( is_array( $el ) && isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_and_insert_sibling( $el['elements'], $target_id, $position, $new_element );
			}
			$out[] = $el;
		}
		return $out;
	}

	
	private static function parent_id_of( $tree, $child_id, $parent_id = '' ) {
		if ( ! is_array( $tree ) ) {
			return '';
		}
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $child_id ) {
				return (string) $parent_id;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::parent_id_of( $el['elements'], $child_id, (string) ( $el['id'] ?? '' ) );
				if ( $found !== '' ) {
					return $found;
				}
			}
		}
		return '';
	}

	
	private static function get_loop_template( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$tree    = self::load_tree( $post_id );
		$element = self::find_element_by_id( $tree, $element_id );
		if ( $element === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		$widget_type = (string) ( $element['widgetType'] ?? '' );
		$loop_id     = self::loop_template_id_of( $element );
		if ( $loop_id <= 0 ) {
			return [
				'has_loop_template' => false,
				'post_id'           => $post_id,
				'element_id'        => $element_id,
				'widget_type'       => $widget_type !== '' ? $widget_type : null,
				'message'           => 'This widget has no template_id set, so it renders no loop-item template. Loop Grid/Carousel widgets carry a template_id once a loop template is chosen in the editor.',
			];
		}

		$loop_post = get_post( $loop_id );
		if ( ! $loop_post ) {
			return [
				'has_loop_template' => false,
				'post_id'           => $post_id,
				'element_id'        => $element_id,
				'widget_type'       => $widget_type !== '' ? $widget_type : null,
				'loop_post_id'      => $loop_id,
				'message'           => sprintf( 'template_id %d is set but that post no longer exists.', $loop_id ),
			];
		}

		$loop_tree    = get_post_meta( $loop_id, '_elementor_data', true );
		$loop_outline = [];
		if ( ! empty( $loop_tree ) ) {
			$decoded = is_string( $loop_tree ) ? json_decode( $loop_tree, true ) : $loop_tree;
			if ( is_array( $decoded ) ) {
				$loop_outline = self::build_outline( $decoded, 0 );
			}
		}

		return [
			'has_loop_template' => true,
			'post_id'           => $post_id,
			'element_id'        => $element_id,
			'widget_type'       => $widget_type !== '' ? $widget_type : null,
			'loop_post_id'      => $loop_id,
			'template_type'     => get_post_meta( $loop_id, '_elementor_template_type', true ) ?: null,
			'title'             => $loop_post->post_title,
			'outline'           => $loop_outline,
			'edit_hint'         => sprintf(
				'Edit the loop item by passing loop_post_id (%d) as post_id to elementor_get_widget_settings / update_widget / add_widget / delete_widget / move_widget.',
				$loop_id
			),
		];
	}

	
	private static function loop_template_id_of( $element ) {
		$settings = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : [];
		if ( ! isset( $settings['template_id'] ) ) {
			return 0;
		}
		return (int) $settings['template_id'];
	}

	
	private static function assert_expected_type( array $element, array $args, string $operation ) {
		if ( empty( $args['expected_widget_type'] ) || ! is_string( $args['expected_widget_type'] ) ) {
			return;
		}
		$expected = $args['expected_widget_type'];
		$el_type  = (string) ( $element['elType'] ?? 'unknown' );
		$actual   = ( 'widget' === $el_type )
			? (string) ( $element['widgetType'] ?? '' )
			: $el_type;

		if ( $actual === $expected ) {
			return;
		}
		throw new \Exception( sprintf(
			'Refusing to %s: expected_widget_type was "%s" but element %s is "%s". Element IDs shift when a page is rebuilt, so this usually means the ID is stale — re-read the page with elementor_get_page_outline.',
			esc_html( $operation ),
			esc_html( $expected ),
			esc_html( (string) ( $element['id'] ?? '' ) ),
			esc_html( $actual !== '' ? $actual : 'unknown' )
		) );
	}

	
	private static function is_atomic_widget_type( $widget_type ) {
		$widget_type = (string) $widget_type;
		return strpos( $widget_type, 'a-' ) === 0 || strpos( $widget_type, 'e-' ) === 0;
	}

	
	private static function theme_builder_widgets() {
		return [
			'theme-post-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'post-title' ],
			'theme-page-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'page-title' ],
			'theme-archive-title'       => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'archive-title' ],
			'theme-site-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'site-title' ],
			'theme-post-featured-image' => [ 'binding' => 'image',   'requires_binding' => true,  'tag' => 'post-featured-image' ],
			'theme-site-logo'           => [ 'binding' => 'image',   'requires_binding' => true,  'tag' => 'site-logo' ],
			'theme-post-excerpt'        => [ 'binding' => 'excerpt', 'requires_binding' => true,  'tag' => 'post-excerpt' ],
			'theme-post-content'        => [ 'binding' => null,      'requires_binding' => false, 'tag' => null ],
		];
	}

	
	private static function classify_widget_type( $widget_type ) {
		$widget_type = (string) $widget_type;
		if ( self::is_atomic_widget_type( $widget_type ) ) {
			return 'atomic';
		}
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return 'unavailable';
		}
		$manager = isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance->widgets_manager : null;
		if ( ! $manager || ! method_exists( $manager, 'get_widget_types' ) ) {
			return 'unavailable';
		}
		$registered = $manager->get_widget_types();
		if ( ! is_array( $registered ) ) {
			return 'unavailable';
		}
		return isset( $registered[ $widget_type ] ) ? 'registered' : 'unregistered';
	}

	
	private static function detect_provider_capability() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return 'registry_unavailable';
		}
		$has_pro_class    = class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' );
		$is_pro_elements  = defined( 'IS_PRO_ELEMENTS' ) && IS_PRO_ELEMENTS;

		if ( ! $has_pro_class ) {
			
			
			foreach ( array_keys( self::theme_builder_widgets() ) as $slug ) {
				if ( self::classify_widget_type( $slug ) === 'registered' ) {
					return 'unknown_third_party';
				}
			}
			return 'core_only';
		}

		
		
		
		
		if ( $is_pro_elements ) {
			return 'pro_elements';
		}

		return 'official_pro';
	}

	
	private static function validate_dynamic_bindings( array $element ) {
		$errors = [];
		if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
			return $errors;
		}
		$widget_type = (string) ( $element['widgetType'] ?? '' );
		$catalogue   = self::theme_builder_widgets();
		if ( ! isset( $catalogue[ $widget_type ] ) ) {
			return $errors;
		}
		$spec = $catalogue[ $widget_type ];
		if ( ! $spec['requires_binding'] ) {
			return $errors;
		}
		$key      = $spec['binding'];
		$settings = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : [];
		$dynamic  = ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) ) ? $settings['__dynamic__'] : [];
		if ( empty( $dynamic[ $key ] ) || ! is_string( $dynamic[ $key ] ) ) {
			$errors[] = sprintf(
				'Widget "%s" (id %s) is a Theme Builder dynamic widget and needs a dynamic binding on its "%s" setting, or it renders a placeholder instead of live data. '
				. 'Add settings.__dynamic__["%s"] = "[elementor-tag id=\"...\" name=\"%s\" settings=\"%%7B%%7D\"]". '
				. 'The binding value is generated by Elementor from the "%s" dynamic tag; copy one from a working widget of this type rather than inventing the id.',
				$widget_type,
				(string) ( $element['id'] ?? '?' ),
				$key,
				$key,
				(string) $spec['tag'],
				(string) $spec['tag']
			);
		}
		return $errors;
	}

	
	private static function validate_document( array $tree, $allow_unavailable = false ) {
		$errors   = [];
		$provider = null; 
		$walk = function ( $elements ) use ( &$walk, &$errors, &$provider, $allow_unavailable ) {
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$el_type = (string) ( $element['elType'] ?? '' );
				if ( $el_type === 'widget' ) {
					$widget_type = (string) ( $element['widgetType'] ?? '' );
					if ( $widget_type === '' ) {
						$errors[] = sprintf( 'Element id %s has elType "widget" but no widgetType.', (string) ( $element['id'] ?? '?' ) );
					} else {
						$class = self::classify_widget_type( $widget_type );
						if ( $class === 'unregistered' ) {
							if ( $provider === null ) {
								$provider = self::detect_provider_capability();
							}
							$errors[] = self::unregistered_widget_message( $widget_type, $provider );
						} elseif ( $class === 'unavailable' && ! $allow_unavailable ) {
							$errors[] = sprintf(
								'Cannot verify widget "%s" (id %s): Elementor\'s widget registry is not reachable in this request, so the slug cannot be confirmed as registered. '
								. 'Rather than write an unverifiable widget that may render as a blank placeholder, this write is refused. Retry once Elementor is fully loaded.',
								$widget_type,
								(string) ( $element['id'] ?? '?' )
							);
						}
					}
					foreach ( self::validate_dynamic_bindings( $element ) as $binding_error ) {
						$errors[] = $binding_error;
					}
				}
				if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};
		$walk( $tree );
		return $errors;
	}

	
	private static function unregistered_widget_message( $widget_type, $provider ) {
		$catalogue = self::theme_builder_widgets();
		$base = sprintf(
			'widget_type "%s" is not registered with Elementor on this site, so it would serialize into _elementor_data and render as a silent empty placeholder.',
			$widget_type
		);
		if ( isset( $catalogue[ $widget_type ] ) ) {
			
			if ( in_array( $provider, [ 'core_only' ], true ) ) {
				return $base . ' It is an Elementor Pro Theme Builder widget, but only Elementor Core (free) is active here — install Elementor Pro or PRO Elements to use it.';
			}
			if ( $provider === 'registry_unavailable' ) {
				return $base . ' It is a Theme Builder widget; the registry could not be reached to confirm availability.';
			}
			return $base . ' It is a Theme Builder widget and Pro is present, but these widgets only register inside a Theme Builder template document (Single, Archive, Header, Footer, etc.). Confirm the target post is a Theme Builder template, not an ordinary page.';
		}
		$core_equivalents = [
			'post-title'          => 'theme-post-title',
			'post-content'        => 'theme-post-content',
			'post-featured-image' => 'theme-post-featured-image',
			'post-excerpt'        => 'theme-post-excerpt',
		];
		if ( isset( $core_equivalents[ $widget_type ] ) ) {
			return $base . sprintf(
				' Did you mean the Theme Builder widget "%s"? Core-style dynamic slugs like "%s" are not registered widget types.',
				$core_equivalents[ $widget_type ],
				$widget_type
			);
		}
		return $base . ' Use a curated type (' . implode( ', ', self::$curated_widget_types ) . '), an Elementor V4 atomic widget (a-* / e-*), or any slug returned by Elementor\'s widget registry.';
	}

	
	private static function count_descendants( array $element ) {
		if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $element['elements'] as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$count += 1 + self::count_descendants( $child );
		}
		return $count;
	}

	
	private static function replace_element_settings( $tree, $element_id, $new_settings ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $element_id ) {
				
				
				
				$el['settings'] = ( is_array( $new_settings ) && $new_settings === [] )
					? new \stdClass()
					: $new_settings;
				$out[]          = $el;
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::replace_element_settings( $el['elements'], $element_id, $new_settings );
			}
			$out[] = $el;
		}
		return $out;
	}

	
	private static function remove_element_by_id( $tree, $element_id ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $element_id ) {
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::remove_element_by_id( $el['elements'], $element_id );
			}
			$out[] = $el;
		}
		return array_values( $out );
	}

	
	private static function load_tree( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			throw new \Exception( sprintf(
				'Post %d has no Elementor data — was it edited with Elementor?',
				(int) $post_id
			) );
		}
		$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}
		return $tree;
	}

	
	private static function save_tree( $post_id, $tree ) {
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	}

	
	
	

	
	public static function invalidate_derived_state_public( $post_id ) {
		return self::invalidate_derived_state( $post_id );
	}

	
	public static function restore_kit_settings_public( array $settings ) {
		$kit_document = self::require_kit_document();
		$kit_document->save( [ 'settings' => $settings ] );
		$inval = self::invalidate_kit_state();

		$result = [ 'kit_id' => (int) $kit_document->get_id() ];
		if ( ! empty( $inval['warnings'] ) ) {
			$result['cache_invalidation'] = [
				'cleared'  => $inval['invalidated'],
				'warnings' => $inval['warnings'],
			];
		}
		return $result;
	}

	
	private static function invalidate_derived_state( $post_id ) {
		$post_id     = (int) $post_id;
		$invalidated = [];
		$warnings    = [];

		
		
		
		
		
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			try {
				
				
				
				
				\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
				$invalidated[] = 'post_css';
			} catch ( \Throwable $e ) {
				$warnings[] = 'Post CSS cache could not be cleared (' . $e->getMessage() . '). The page may render with stale CSS until the Elementor editor is opened and the page saved.';
			}
		} else {
			$warnings[] = 'Elementor\'s Post CSS class was not available, so the CSS cache was not cleared. The page may render with stale CSS until the Elementor editor is opened and the page saved.';
		}

		
		
		
		
		delete_post_meta( $post_id, '_elementor_element_cache' );
		$invalidated[] = 'element_cache';
		delete_post_meta( $post_id, '_elementor_page_assets' );
		$invalidated[] = 'page_assets';

		return [
			'invalidated' => $invalidated,
			'warnings'    => $warnings,
		];
	}

	
	private static function with_invalidation( array $response, array $inval ) {
		if ( ! empty( $inval['warnings'] ) ) {
			$response['cache_invalidation'] = [
				'cleared'  => $inval['invalidated'],
				'warnings' => $inval['warnings'],
			];
		}
		return $response;
	}

	
	private static function invalidate_kit_state() {
		$invalidated = [];
		$warnings    = [];

		
		
		
		try {
			if (
				class_exists( '\Elementor\Plugin' )
				&& isset( \Elementor\Plugin::$instance->files_manager )
			) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
				$invalidated[] = 'files_manager_cache';
			} else {
				$warnings[] = 'Elementor\'s files manager was not available, so the site-wide CSS cache was not cleared. Pages may render with the previous Site Settings until the Elementor editor is opened and the kit saved.';
			}
		} catch ( \Throwable $e ) {
			$warnings[] = 'Site-wide CSS cache could not be cleared (' . $e->getMessage() . '). Pages may render with the previous Site Settings until the Elementor editor is opened and the kit saved.';
		}

		
		
		
		try {
			$kit_id = self::get_active_kit_id();
			if ( $kit_id > 0 ) {
				delete_post_meta( $kit_id, '_elementor_css' );
				$invalidated[] = 'kit_css';
			}
		} catch ( \Throwable $e ) {
			$warnings[] = 'The kit CSS meta could not be cleared (' . $e->getMessage() . ').';
		}

		return [
			'invalidated' => $invalidated,
			'warnings'    => $warnings,
		];
	}

	
	private static function get_active_kit_id() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			return 0;
		}
		$active_kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $active_kit ) {
			return 0;
		}
		$kit_id = (int) $active_kit->get_id();
		return $kit_id > 0 ? $kit_id : 0;
	}

	
	private static function clone_page( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$source_id = (int) ( $args['source_post_id'] ?? 0 );
		$new_title = sanitize_text_field( $args['new_title'] ?? '' );
		$new_status = isset( $args['new_status'] ) ? sanitize_key( $args['new_status'] ) : 'draft';
		if ( ! in_array( $new_status, [ 'draft', 'publish', 'private', 'pending' ], true ) ) {
			$new_status = 'draft';
		}
		if ( $source_id <= 0 || $new_title === '' ) {
			throw new \Exception( 'source_post_id and new_title are required.' );
		}
		$source = get_post( $source_id );
		if ( ! $source ) {
			throw new \Exception( 'Source post not found.' );
		}
		$elementor_data = get_post_meta( $source_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Source post does not have Elementor data — was it edited with Elementor?' );
		}

		
		
		
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse source _elementor_data as a JSON array.' );
		}
		$regenerated = self::regenerate_element_ids( $tree );

		
		
		
		
		
		
		$doc_errors = self::validate_document( $regenerated, true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html(
				'The source page has invalid Elementor elements, so cloning it would duplicate a page that renders placeholders. ' . implode( ' ', $doc_errors )
			) );
		}

		
		$new_post_data = [
			'post_title'  => $new_title,
			'post_status' => $new_status,
			'post_type'   => $source->post_type,
			'post_author' => get_current_user_id() ?: $source->post_author,
		];
		$new_id = wp_insert_post( $new_post_data, true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( $new_id->get_error_message() );
		}

		
		
		
		update_post_meta( $new_id, '_elementor_data', wp_slash( wp_json_encode( $regenerated ) ) );

		
		
		
		
		
		
		
		$meta_keys_to_copy = [
			'_elementor_edit_mode',
			'_elementor_template_type',
			'_elementor_version',
			'_elementor_pro_version',
			'_elementor_page_settings',
		];
		foreach ( $meta_keys_to_copy as $key ) {
			$value = get_post_meta( $source_id, $key, true );
			if ( $value !== '' && $value !== null && $value !== false ) {
				update_post_meta( $new_id, $key, $value );
			}
		}

		
		if ( get_post_meta( $new_id, '_elementor_edit_mode', true ) === '' ) {
			update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		}

		
		
		$inval = self::invalidate_derived_state( $new_id );

		return self::with_invalidation( [
			'success'        => true,
			'new_post_id'    => (int) $new_id,
			'new_title'      => $new_title,
			'new_status'     => $new_status,
			'source_post_id' => $source_id,
			'edit_url'       => admin_url( 'post.php?post=' . $new_id . '&action=elementor' ),
			'view_url'       => $new_status === 'publish' ? get_permalink( $new_id ) : get_preview_post_link( $new_id ),
		], $inval );
	}

	
	private static function regenerate_element_ids( $elements ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) ) {
				$el['id'] = self::generate_element_id();
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::regenerate_element_ids( $el['elements'] );
			}
			$out[] = $el;
		}
		return $out;
	}

	
	private static function generate_element_id() {
		return bin2hex( random_bytes( 4 ) );
	}

	
	private static function replace_text( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$find = (string) ( $args['find'] ?? '' );
		$replace = (string) ( $args['replace'] ?? '' );
		$case_insensitive = ! empty( $args['case_insensitive'] );
		if ( $post_id <= 0 || $find === '' ) {
			throw new \Exception( 'post_id and find are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$counter = [ 'count' => 0 ];
		$updated = self::walk_widgets_text( $tree, $find, $replace, $case_insensitive, $counter );

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $updated ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		return self::with_invalidation( [
			'success'       => true,
			'post_id'       => $post_id,
			'replacements'  => $counter['count'],
			'find'          => $find,
			'replace'       => $replace,
		], $inval );
	}

	
	private static function walk_widgets_text( $elements, $find, $replace, $case_insensitive, &$counter ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		
		
		
		$text_fields = [
			'heading'        => [ 'title' ],
			'text-editor'    => [ 'editor' ],
			'button'         => [ 'text' ],
			'image'          => [ 'caption', 'alt' ],
			'image-box'      => [ 'title_text', 'description_text' ],
			'icon-box'       => [ 'title_text', 'description_text' ],
			'icon-list'      => [ 'icon_list' ], 
			'video'          => [ 'caption' ],
			'testimonial'    => [ 'testimonial_content', 'testimonial_name', 'testimonial_job' ],
			'tabs'           => [ 'tabs' ], 
			'accordion'      => [ 'tabs' ], 
			'toggle'         => [ 'tabs' ], 
			'star-rating'    => [ 'title' ],
			'call-to-action' => [ 'title', 'description', 'button' ],
			'flip-box'       => [ 'title_text_a', 'description_text_a', 'title_text_b', 'description_text_b', 'button_text' ],
			'blockquote'     => [ 'author_name', 'blockquote_content' ],
		];

		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}

			if ( ( $el['elType'] ?? '' ) === 'widget' ) {
				$widget_type = (string) ( $el['widgetType'] ?? '' );

				
				
				$is_atomic = self::is_atomic_widget_type( $widget_type );

				if ( ! $is_atomic && isset( $text_fields[ $widget_type ] ) && isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
					foreach ( $text_fields[ $widget_type ] as $key ) {
						if ( ! isset( $el['settings'][ $key ] ) ) {
							continue;
						}
						$value = $el['settings'][ $key ];
						if ( is_string( $value ) ) {
							$new_value = self::str_replace_count( $find, $replace, $value, $case_insensitive, $counter );
							$el['settings'][ $key ] = $new_value;
						} elseif ( is_array( $value ) ) {
							
							foreach ( $value as $i => $item ) {
								if ( ! is_array( $item ) ) {
									continue;
								}
								foreach ( $item as $item_key => $item_value ) {
									if ( is_string( $item_value ) ) {
										$value[ $i ][ $item_key ] = self::str_replace_count( $find, $replace, $item_value, $case_insensitive, $counter );
									}
								}
							}
							$el['settings'][ $key ] = $value;
						}
					}
				}
			}

			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_widgets_text( $el['elements'], $find, $replace, $case_insensitive, $counter );
			}

			$out[] = $el;
		}
		return $out;
	}

	
	private static function str_replace_count( $find, $replace, $subject, $case_insensitive, &$counter ) {
		$c = 0;
		if ( $case_insensitive ) {
			$out = str_ireplace( $find, $replace, $subject, $c );
		} else {
			$out = str_replace( $find, $replace, $subject, $c );
		}
		$counter['count'] += $c;
		return $out;
	}

	
	private static function replace_image( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$old_url = esc_url_raw( $args['old_url'] ?? '' );
		$new_url = esc_url_raw( $args['new_url'] ?? '' );
		$old_id = isset( $args['old_id'] ) ? (int) $args['old_id'] : 0;
		$new_id = isset( $args['new_id'] ) ? (int) $args['new_id'] : 0;
		if ( $post_id <= 0 || $old_url === '' || $new_url === '' ) {
			throw new \Exception( 'post_id, old_url, and new_url are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$counter = [ 'count' => 0 ];
		$updated = self::walk_widgets_image( $tree, $old_url, $new_url, $old_id, $new_id, $counter );

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $updated ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		return self::with_invalidation( [
			'success'      => true,
			'post_id'      => $post_id,
			'replacements' => $counter['count'],
			'old_url'      => $old_url,
			'new_url'      => $new_url,
		], $inval );
	}

	
	private static function walk_widgets_image( $elements, $old_url, $new_url, $old_id, $new_id, &$counter ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}

			if ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
				
				
				
				$el['settings'] = self::swap_image_in_settings( $el['settings'], $old_url, $new_url, $old_id, $new_id, $counter );
			}

			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_widgets_image( $el['elements'], $old_url, $new_url, $old_id, $new_id, $counter );
			}

			$out[] = $el;
		}
		return $out;
	}

	
	private static function swap_image_in_settings( $settings, $old_url, $new_url, $old_id, $new_id, &$counter ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				
				if ( isset( $value['url'] ) && is_string( $value['url'] ) && $value['url'] === $old_url ) {
					$settings[ $key ]['url'] = $new_url;
					$counter['count']++;
					if ( $old_id > 0 && $new_id > 0 && isset( $value['id'] ) && (int) $value['id'] === $old_id ) {
						$settings[ $key ]['id'] = $new_id;
					}
				} else {
					
					$settings[ $key ] = self::swap_image_in_settings( $value, $old_url, $new_url, $old_id, $new_id, $counter );
				}
			}
		}
		return $settings;
	}

	
	private static function get_page_outline( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$outline = self::build_outline( $tree, 0 );
		$post = get_post( $post_id );

		return [
			'post_id'        => $post_id,
			'post_title'     => $post ? $post->post_title : '',
			'post_type'      => $post ? $post->post_type : '',
			'edit_mode'      => get_post_meta( $post_id, '_elementor_edit_mode', true ) ?: null,
			'template_type'  => get_post_meta( $post_id, '_elementor_template_type', true ) ?: null,
			'outline'        => $outline,
		];
	}

	
	private static function get_widget_settings( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';

		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( '' === $element_id ) {
			throw new \Exception( 'element_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$searched_count = 0;
		$found = self::find_element_with_depth( $tree, $element_id, 0, $searched_count );

		if ( null === $found ) {
			return [
				'post_id'        => $post_id,
				'element_id'     => $element_id,
				'found'          => false,
				'searched_count' => $searched_count,
			];
		}

		$el       = $found['element'];
		$depth    = $found['depth'];
		$el_type  = (string) ( $el['elType'] ?? 'unknown' );
		$children = ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : [];

		return [
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'found'        => true,
			'element_type' => $el_type,
			'widget_type'  => ( 'widget' === $el_type ) ? (string) ( $el['widgetType'] ?? '' ) : null,
			'depth'        => $depth,
			'has_children' => count( $children ) > 0,
			'child_count'  => count( $children ),
			'settings'     => isset( $el['settings'] ) ? $el['settings'] : new \stdClass(),
		];
	}

	
	private static function find_element_with_depth( $elements, $target_id, $depth = 0, &$searched_count = 0 ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$searched_count++;
			if ( isset( $el['id'] ) && (string) $el['id'] === $target_id ) {
				return [ 'element' => $el, 'depth' => $depth ];
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
				$hit = self::find_element_with_depth( $el['elements'], $target_id, $depth + 1, $searched_count );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	
	private static function build_outline( $elements, $depth ) {
		$out = [];
		if ( $depth > 6 ) {
			return [ '...deep nesting truncated...' ];
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = (string) ( $el['elType'] ?? 'unknown' );
			$entry = [
				'id'     => (string) ( $el['id'] ?? '' ),
				'elType' => $el_type,
			];
			if ( $el_type === 'widget' ) {
				$entry['widgetType'] = (string) ( $el['widgetType'] ?? 'unknown' );
				
				$snippet = self::widget_text_snippet( $el );
				if ( $snippet !== '' ) {
					$entry['snippet'] = $snippet;
				}
				
				
				
				
				$loop_template_id = self::loop_template_id_of( $el );
				if ( $loop_template_id > 0 ) {
					$entry['loop_template_id'] = $loop_template_id;
				}
			} elseif ( $el_type === 'container' && isset( $el['settings']['flex_direction'] ) ) {
				$entry['flex_direction'] = (string) $el['settings']['flex_direction'];
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
				$entry['children'] = self::build_outline( $el['elements'], $depth + 1 );
			}
			$out[] = $entry;
		}
		return $out;
	}

	
	private static function widget_text_snippet( $widget ) {
		$widget_type = (string) ( $widget['widgetType'] ?? '' );
		$s = $widget['settings'] ?? [];
		if ( ! is_array( $s ) ) {
			return '';
		}
		$snippet_candidates = [
			'heading'        => [ 'title' ],
			'text-editor'    => [ 'editor' ],
			'button'         => [ 'text' ],
			'image-box'      => [ 'title_text' ],
			'icon-box'       => [ 'title_text' ],
			'call-to-action' => [ 'title' ],
		];
		if ( ! isset( $snippet_candidates[ $widget_type ] ) ) {
			return '';
		}
		foreach ( $snippet_candidates[ $widget_type ] as $key ) {
			if ( isset( $s[ $key ] ) && is_string( $s[ $key ] ) && $s[ $key ] !== '' ) {
				$plain = wp_strip_all_tags( $s[ $key ] );
				return mb_strimwidth( $plain, 0, 80, '...' );
			}
		}
		return '';
	}

	
	private static function list_local_templates( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$type_filter = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : '';
		$limit = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

		$query_args = [
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];
		if ( $type_filter !== '' ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'elementor_library_type',
					'field'    => 'slug',
					'terms'    => $type_filter,
				],
			];
		}
		$posts = get_posts( $query_args );

		$templates = [];
		foreach ( $posts as $tpl ) {
			$terms = wp_get_post_terms( $tpl->ID, 'elementor_library_type', [ 'fields' => 'slugs' ] );
			$templates[] = [
				'id'            => (int) $tpl->ID,
				'name'          => $tpl->post_title,
				'type'          => is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ? (string) $terms[0] : 'page',
				'date_modified' => $tpl->post_modified_gmt,
			];
		}

		return [
			'count'     => count( $templates ),
			'templates' => $templates,
		];
	}

	
	private static function import_template( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$title = sanitize_text_field( $args['title'] ?? '' );
		$template_type = isset( $args['template_type'] ) ? sanitize_key( $args['template_type'] ) : 'page';
		$template_json = (string) ( $args['template_json'] ?? '' );
		if ( $title === '' || $template_json === '' ) {
			throw new \Exception( 'title and template_json are required.' );
		}

		$decoded = json_decode( $template_json, true );
		if ( ! is_array( $decoded ) ) {
			throw new \Exception( 'template_json must be a JSON-encoded array of Elementor elements.' );
		}
		
		
		if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
			$elements = $decoded['content'];
		} else {
			$elements = $decoded;
		}

		
		$elements = self::regenerate_element_ids( $elements );

		
		
		
		
		
		$doc_errors = self::validate_document( $elements );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		
		$new_id = wp_insert_post( [
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'elementor_library',
			'post_author' => get_current_user_id(),
		], true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( $new_id->get_error_message() );
		}

		
		wp_set_object_terms( $new_id, $template_type, 'elementor_library_type' );

		
		update_post_meta( $new_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $new_id, '_elementor_template_type', $template_type );

		$inval = self::invalidate_derived_state( $new_id );

		return self::with_invalidation( [
			'success'       => true,
			'template_id'   => (int) $new_id,
			'title'         => $title,
			'template_type' => $template_type,
			'edit_url'      => admin_url( 'post.php?post=' . $new_id . '&action=elementor' ),
		], $inval );
	}

	
	
	

	
	private static $curated_widget_types = [
		'container', 'heading', 'text-editor', 'button', 'image',
		'image-box', 'icon-box', 'icon-list', 'video', 'divider', 'spacer',
	];

	
	private static function add_widget( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$widget_type = isset( $args['widget_type'] ) ? sanitize_key( $args['widget_type'] ) : '';
		if ( $post_id <= 0 || $widget_type === '' ) {
			throw new \Exception( 'post_id and widget_type are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data — was it edited with Elementor?' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		
		
		
		
		
		$new_element = self::build_element_from_args( $args );

		$doc_errors = self::validate_document( [ $new_element ], true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		
		$parent_id = isset( $args['parent_id'] ) ? (string) $args['parent_id'] : null;
		$position = isset( $args['position'] ) ? (int) $args['position'] : null;
		if ( $parent_id !== null ) {
			$parent = self::find_element_by_id( $tree, $parent_id );
			if ( $parent === null ) {
				throw new \Exception( 'parent_id not found in this page: ' . esc_html( $parent_id ) );
			}
			if ( ! isset( $parent['elType'] ) || ! in_array( $parent['elType'], [ 'container', 'section', 'column' ], true ) ) {
				throw new \Exception( 'parent_id must reference a container, section, or column. Found: ' . esc_html( $parent['elType'] ?? 'unknown' ) );
			}
			
			if ( $new_element['elType'] === 'container' && $parent['elType'] === 'container' ) {
				$new_element['isInner'] = true;
			}
		}

		
		$tree = self::insert_into_tree( $tree, $parent_id, $position, $new_element );

		
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		$notice = ! empty( $args['settings'] ) && in_array( $widget_type, self::$curated_widget_types, true )
			? 'Raw settings supplied for a curated widget_type — curated params were ignored. To use curated, omit the settings parameter.'
			: null;

		$response = [
			'success'      => true,
			'post_id'      => $post_id,
			'new_id'       => (string) $new_element['id'],
			'widget_type'  => $widget_type,
			'parent_id'    => $parent_id,
			'position'     => $position,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		];
		if ( $notice !== null ) {
			$response['notice'] = $notice;
		}
		return self::with_invalidation( $response, $inval );
	}

	
	private static function build_element_from_args( $args ) {
		$widget_type = isset( $args['widget_type'] ) ? sanitize_key( $args['widget_type'] ) : '';
		if ( $widget_type === '' ) {
			throw new \Exception( 'widget_type is required for every element (including children).' );
		}

		
		
		
		$is_curated = in_array( $widget_type, self::$curated_widget_types, true );
		$has_settings = isset( $args['settings'] ) && is_array( $args['settings'] );

		if ( ! $is_curated && ! $has_settings ) {
			throw new \Exception( 'widget_type "' . esc_html( $widget_type ) . '" is not curated — supply a `settings` object directly.' );
		}

		
		
		
		
		
		
		
		if ( ! $is_curated ) {
			$class = self::classify_widget_type( $widget_type );
			if ( $class === 'unregistered' ) {
				throw new \Exception( esc_html( self::unregistered_widget_message( $widget_type, self::detect_provider_capability() ) ) );
			}
			if ( $class === 'unavailable' ) {
				throw new \Exception( sprintf(
					'Cannot verify widget_type "%s": Elementor\'s widget registry is not reachable in this request. Rather than write an unverifiable widget that may render as a blank placeholder, this write is refused. Retry once Elementor is fully loaded.',
					esc_html( $widget_type )
				) );
			}
		}

		if ( $has_settings ) {
			
			$settings = $args['settings'];
			$el_type = $widget_type === 'container' ? 'container' : 'widget';
		} else {
			
			$settings = self::build_curated_settings( $widget_type, $args );
			$el_type = $widget_type === 'container' ? 'container' : 'widget';
		}

		$element = [
			'id'       => self::generate_element_id(),
			'elType'   => $el_type,
			'settings' => is_array( $settings ) ? $settings : (object) [],
			'elements' => [],
			'isInner'  => false,
		];
		if ( $el_type === 'widget' ) {
			$element['widgetType'] = $widget_type;
		}

		
		
		
		if ( $widget_type === 'container' && isset( $args['children'] ) && is_array( $args['children'] ) ) {
			foreach ( $args['children'] as $child_args ) {
				if ( ! is_array( $child_args ) ) {
					continue;
				}
				$child = self::build_element_from_args( $child_args );
				if ( $child['elType'] === 'container' ) {
					$child['isInner'] = true;
				}
				$element['elements'][] = $child;
			}
		}

		return $element;
	}

	
	private static function build_curated_settings( $widget_type, $args ) {
		switch ( $widget_type ) {
			case 'container':   return self::curated_container( $args );
			case 'heading':     return self::curated_heading( $args );
			case 'text-editor': return self::curated_text_editor( $args );
			case 'button':      return self::curated_button( $args );
			case 'image':       return self::curated_image( $args );
			case 'image-box':   return self::curated_image_box( $args );
			case 'icon-box':    return self::curated_icon_box( $args );
			case 'icon-list':   return self::curated_icon_list( $args );
			case 'video':       return self::curated_video( $args );
			case 'divider':     return self::curated_divider( $args );
			case 'spacer':      return self::curated_spacer( $args );
		}
		
		throw new \Exception( 'No curated builder for widget_type: ' . esc_html( $widget_type ) );
	}

	

	private static function curated_container( $args ) {
		$flex_direction = isset( $args['flex_direction'] ) && in_array( $args['flex_direction'], [ 'row', 'column' ], true )
			? $args['flex_direction'] : 'column';
		$content_width = isset( $args['content_width'] ) && in_array( $args['content_width'], [ 'boxed', 'full' ], true )
			? $args['content_width'] : 'boxed';
		return [
			'content_width'  => $content_width,
			'flex_direction' => $flex_direction,
		];
	}

	private static function curated_heading( $args ) {
		$title = isset( $args['title'] ) ? (string) $args['title'] : '';
		if ( $title === '' ) {
			throw new \Exception( 'Curated heading requires `title`.' );
		}
		$header_size = isset( $args['header_size'] ) && in_array( $args['header_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['header_size'] : 'h2';
		return [
			'title'       => $title,
			'header_size' => $header_size,
		];
	}

	private static function curated_text_editor( $args ) {
		$editor = isset( $args['editor'] ) ? (string) $args['editor'] : '';
		if ( $editor === '' ) {
			throw new \Exception( 'Curated text-editor requires `editor` (HTML content).' );
		}
		return [ 'editor' => $editor ];
	}

	private static function curated_button( $args ) {
		$text = isset( $args['text'] ) ? (string) $args['text'] : '';
		$link_url = isset( $args['link_url'] ) ? esc_url_raw( $args['link_url'] ) : '';
		if ( $text === '' || $link_url === '' ) {
			throw new \Exception( 'Curated button requires `text` and `link_url`.' );
		}
		$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
		return [
			'text' => $text,
			'link' => self::wrap_link( $link_url, $target ),
		];
	}

	private static function curated_image( $args ) {
		$image_url = isset( $args['image_url'] ) ? esc_url_raw( $args['image_url'] ) : '';
		if ( $image_url === '' ) {
			throw new \Exception( 'Curated image requires `image_url`.' );
		}
		$image_alt = isset( $args['image_alt'] ) ? sanitize_text_field( $args['image_alt'] ) : '';
		$settings = [
			'image' => self::wrap_image( $image_url, $image_alt ),
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link_to'] = 'custom';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_image_box( $args ) {
		$image_url = isset( $args['image_url'] ) ? esc_url_raw( $args['image_url'] ) : '';
		$title_text = isset( $args['title_text'] ) ? (string) $args['title_text'] : '';
		if ( $image_url === '' || $title_text === '' ) {
			throw new \Exception( 'Curated image-box requires `image_url` and `title_text`.' );
		}
		$image_alt = isset( $args['image_alt'] ) ? sanitize_text_field( $args['image_alt'] ) : '';
		$description_text = isset( $args['description_text'] ) ? (string) $args['description_text'] : '';
		$title_size = isset( $args['title_size'] ) && in_array( $args['title_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['title_size'] : 'h3';
		$settings = [
			'image'            => self::wrap_image( $image_url, $image_alt ),
			'title_text'       => $title_text,
			'description_text' => $description_text,
			'title_size'       => $title_size,
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_icon_box( $args ) {
		$icon = isset( $args['icon'] ) ? (string) $args['icon'] : '';
		$title_text = isset( $args['title_text'] ) ? (string) $args['title_text'] : '';
		if ( $icon === '' || $title_text === '' ) {
			throw new \Exception( 'Curated icon-box requires `icon` and `title_text`.' );
		}
		$description_text = isset( $args['description_text'] ) ? (string) $args['description_text'] : '';
		$title_size = isset( $args['title_size'] ) && in_array( $args['title_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['title_size'] : 'h3';
		$settings = [
			'selected_icon'    => [
				'value'   => $icon,
				'library' => self::derive_icon_library( $icon ),
			],
			'title_text'       => $title_text,
			'description_text' => $description_text,
			'title_size'       => $title_size,
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_icon_list( $args ) {
		if ( empty( $args['items'] ) || ! is_array( $args['items'] ) ) {
			throw new \Exception( 'Curated icon-list requires `items` (array of {text, icon?, link_url?}).' );
		}
		$icon_list = [];
		foreach ( $args['items'] as $i => $item ) {
			if ( ! is_array( $item ) || empty( $item['text'] ) ) {
				throw new \Exception( 'icon-list item at index ' . (int) $i . ' missing required `text`.' );
			}
			$icon = isset( $item['icon'] ) && $item['icon'] !== '' ? (string) $item['icon'] : 'fas fa-check';
			$row = [
				'_id'           => self::generate_repeater_id(),
				'text'          => (string) $item['text'],
				'selected_icon' => [
					'value'   => $icon,
					'library' => self::derive_icon_library( $icon ),
				],
			];
			if ( ! empty( $item['link_url'] ) ) {
				$row['link'] = self::wrap_link( esc_url_raw( $item['link_url'] ), '_self' );
			}
			$icon_list[] = $row;
		}
		return [
			'icon_list' => $icon_list,
			'view'      => 'traditional',
		];
	}

	private static function curated_video( $args ) {
		$video_url = isset( $args['video_url'] ) ? (string) $args['video_url'] : '';
		if ( $video_url === '' ) {
			throw new \Exception( 'Curated video requires `video_url`.' );
		}
		$routed = self::route_video_url( $video_url );
		$aspect_ratio = isset( $args['aspect_ratio'] ) && in_array( $args['aspect_ratio'], [ '169', '219', '43', '32', '11', '916' ], true )
			? $args['aspect_ratio'] : '169';
		$settings = [
			'video_type'   => $routed['video_type'],
			$routed['url_field'] => $routed['url_value'],
			'aspect_ratio' => $aspect_ratio,
		];
		if ( ! empty( $args['autoplay'] ) ) {
			$settings['autoplay'] = 'yes';
		}
		return $settings;
	}

	private static function curated_divider( $args ) {
		$settings = [ 'style' => 'solid' ];
		if ( isset( $args['weight'] ) ) {
			$settings['weight'] = self::wrap_slider_px( (int) $args['weight'] );
		}
		if ( ! empty( $args['color'] ) ) {
			$settings['color'] = sanitize_hex_color( $args['color'] ) ?: (string) $args['color'];
		}
		return $settings;
	}

	private static function curated_spacer( $args ) {
		$space = isset( $args['space'] ) ? (int) $args['space'] : 50;
		return [ 'space' => self::wrap_slider_px( $space ) ];
	}

	

	
	private static function wrap_link( $url, $target = '_self', $nofollow = false ) {
		return [
			'url'         => (string) $url,
			'is_external' => ( $target === '_blank' ) ? 'on' : '',
			'nofollow'    => $nofollow ? 'on' : '',
		];
	}

	
	private static function wrap_image( $url, $alt = '' ) {
		return [
			'url'    => (string) $url,
			'id'     => '',
			'alt'    => (string) $alt,
			'source' => 'library',
			'size'   => '',
		];
	}

	
	private static function wrap_slider_px( $size ) {
		return [ 'size' => (int) $size, 'unit' => 'px' ];
	}

	
	private static function derive_icon_library( $icon_value ) {
		$icon_value = trim( (string) $icon_value );
		if ( strpos( $icon_value, 'fas ' ) === 0 ) {
			return 'fa-solid';
		}
		if ( strpos( $icon_value, 'far ' ) === 0 ) {
			return 'fa-regular';
		}
		if ( strpos( $icon_value, 'fab ' ) === 0 ) {
			return 'fa-brands';
		}
		return 'fa-solid';
	}

	
	private static function route_video_url( $url ) {
		if ( preg_match( '#(?:youtube\.com|youtu\.be)#i', $url ) ) {
			return [ 'video_type' => 'youtube', 'url_field' => 'youtube_url', 'url_value' => (string) $url ];
		}
		if ( preg_match( '#vimeo\.com#i', $url ) ) {
			return [ 'video_type' => 'vimeo', 'url_field' => 'vimeo_url', 'url_value' => (string) $url ];
		}
		if ( preg_match( '#dailymotion\.com#i', $url ) ) {
			return [ 'video_type' => 'dailymotion', 'url_field' => 'dailymotion_url', 'url_value' => (string) $url ];
		}
		throw new \Exception( 'Curated video supports YouTube, Vimeo, and Dailymotion URLs. For self-hosted or VideoPress, use the raw path with explicit settings (video_type + matching url field).' );
	}

	
	private static function generate_repeater_id() {
		
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	

	
	private static function find_element_by_id( $tree, $id ) {
		if ( ! is_array( $tree ) ) {
			return null;
		}
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $id ) {
				return $el;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::find_element_by_id( $el['elements'], $id );
				if ( $found !== null ) {
					return $found;
				}
			}
		}
		return null;
	}

	
	private static function insert_into_tree( $tree, $parent_id, $position, $new_element ) {
		if ( $parent_id === null ) {
			return self::insert_at_position( $tree, $position, $new_element );
		}
		
		return self::walk_and_insert( $tree, $parent_id, $position, $new_element );
	}

	private static function walk_and_insert( $tree, $parent_id, $position, $new_element ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $parent_id ) {
				$children = isset( $el['elements'] ) && is_array( $el['elements'] ) ? $el['elements'] : [];
				$el['elements'] = self::insert_at_position( $children, $position, $new_element );
				$out[] = $el;
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_and_insert( $el['elements'], $parent_id, $position, $new_element );
			}
			$out[] = $el;
		}
		return $out;
	}

	private static function insert_at_position( $list, $position, $new_element ) {
		$count = count( $list );
		if ( $position === null || $position >= $count ) {
			$list[] = $new_element;
			return $list;
		}
		if ( $position <= 0 ) {
			array_unshift( $list, $new_element );
			return $list;
		}
		array_splice( $list, $position, 0, [ $new_element ] );
		return $list;
	}

	
	
	
	
	
	
	
	

	
	private static function require_kit_document() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			throw new \Exception( 'Elementor kits manager is unavailable.' );
		}
		$active_kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $active_kit ) {
			throw new \Exception( 'No active Elementor kit found.' );
		}
		$kit_id       = (int) $active_kit->get_id();
		$kit_document = \Elementor\Plugin::$instance->documents->get( $kit_id );
		if ( ! $kit_document ) {
			throw new \Exception( 'Kit document not found.' );
		}
		return $kit_document;
	}

	
	private static function get_kit( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		$kit_document = self::require_kit_document();
		$settings     = $kit_document->get_settings();

		return [
			'success'  => true,
			'kit_id'   => (int) $kit_document->get_id(),
			'settings' => is_array( $settings ) ? $settings : [],
		];
	}

	
	private static function get_kit_schema( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			throw new \Exception( 'Elementor kits manager is unavailable.' );
		}

		
		
		
		if ( class_exists( '\Elementor\Core\Frontend\Performance' )
			&& method_exists( '\Elementor\Core\Frontend\Performance', 'set_use_style_controls' ) ) {
			\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit ) {
			throw new \Exception( 'No active Elementor kit found.' );
		}

		$tabs         = $kit->get_tabs();
		$tab_controls = [];

		if ( isset( \Elementor\Plugin::$instance->controls_manager ) ) {
			\Elementor\Plugin::$instance->controls_manager->clear_stack_cache();
		}

		foreach ( $tabs as $tab_id => $tab ) {
			if ( isset( \Elementor\Plugin::$instance->controls_manager ) ) {
				\Elementor\Plugin::$instance->controls_manager->delete_stack( $kit );
			}
			$tab->register_controls();
			$tab_specific_controls = $kit->get_controls();

			$tab_controls[ $tab_id ] = [];
			foreach ( $tab_specific_controls as $control_id => $control ) {
				$type = $control['type'] ?? '';
				if ( 'section' === $type || 'heading' === $type || 'popover_toggle' === $type ) {
					continue;
				}
				$tab_controls[ $tab_id ][ $control_id ] = self::process_kit_control_schema( $control );
			}
		}

		return [
			'success' => true,
			'tabs'    => $tab_controls,
		];
	}

	
	private static function process_kit_control_schema( $control ) {
		$schema = [];
		if ( ! empty( $control['label'] ) ) {
			$schema['label'] = $control['label'];
		}
		if ( ! empty( $control['type'] ) ) {
			$schema['type'] = $control['type'];
		}
		if ( isset( $control['default'] ) && '' !== $control['default'] && [] !== $control['default'] ) {
			$schema['default'] = $control['default'];
		}
		if ( ! empty( $control['options'] ) ) {
			$schema['options'] = $control['options'];
		}
		if ( isset( $control['fields'] ) && is_array( $control['fields'] ) ) {
			$schema['fields'] = [];
			foreach ( $control['fields'] as $field_id => $field ) {
				$schema['fields'][ $field_id ] = self::process_kit_control_schema( $field );
			}
		}
		if ( ( isset( $control['type'] ) && 'repeater' === $control['type'] ) || isset( $control['is_repeater'] ) ) {
			$schema['title_field']   = $control['title_field'] ?? '';
			$schema['prevent_empty'] = $control['prevent_empty'] ?? true;
			$schema['max_items']     = $control['max_items'] ?? 0;
			$schema['min_items']     = $control['min_items'] ?? 0;
		}
		return $schema;
	}

	
	private static function get_kit_fonts( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		if ( ! class_exists( '\Elementor\Fonts' ) ) {
			throw new \Exception( 'Elementor Fonts class is unavailable.' );
		}

		$fonts       = \Elementor\Fonts::get_fonts();
		$font_groups = method_exists( '\Elementor\Fonts', 'get_font_groups' )
			? \Elementor\Fonts::get_font_groups()
			: [];

		return [
			'success'              => true,
			'fonts'                => is_array( $fonts ) ? $fonts : [],
			'font_groups'          => is_array( $font_groups ) ? $font_groups : [],
			'google_fonts_enabled' => method_exists( '\Elementor\Fonts', 'is_google_fonts_enabled' )
				? (bool) \Elementor\Fonts::is_google_fonts_enabled()
				: null,
			'font_display_setting' => method_exists( '\Elementor\Fonts', 'get_font_display_setting' )
				? \Elementor\Fonts::get_font_display_setting()
				: null,
			'total_fonts'          => is_array( $fonts ) ? count( $fonts ) : 0,
		];
	}

	
	private static function update_kit( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}

		$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : null;
		if ( null === $settings ) {
			throw new \Exception( 'settings (object) is required.' );
		}
		if ( empty( $settings ) ) {
			throw new \Exception( 'settings is empty — nothing to write.' );
		}
		$replace = ! empty( $args['replace_settings'] );
		$dry_run = ! empty( $args['dry_run'] );

		$kit_document     = self::require_kit_document();
		$kit_id           = (int) $kit_document->get_id();
		$current_settings = $kit_document->get_settings();
		$current_settings = is_array( $current_settings ) ? $current_settings : [];

		if ( $replace ) {
			$merged       = $settings;
			$removed_keys = array_values( array_diff( array_keys( $current_settings ), array_keys( $settings ) ) );
		} else {
			$merged       = array_merge( $current_settings, $settings );
			$removed_keys = [];
		}

		if ( $dry_run ) {
			return [
				'success'         => true,
				'written'         => false,
				'dry_run'         => true,
				'kit_id'          => $kit_id,
				'mode'            => $replace ? 'replace' : 'merge',
				'settings_before' => $current_settings,
				'settings_after'  => $merged,
				'keys_removed'    => $removed_keys,
			];
		}

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'      => 'elementor_kit_write',
			'summary' => sprintf(
				'elementor_update_kit on kit %d (%s mode)',
				$kit_id,
				$replace ? 'replace' : 'merge'
			),
			'target'  => [ 'kit_id' => $kit_id ],
			
			
			
			'pre_op_state' => [ 'settings' => $current_settings ],
		] );

		
		
		
		
		
		$kit_document->save( [ 'settings' => $merged ] );

		$inval = self::invalidate_kit_state();

		
		$verify_document = self::require_kit_document();
		$stored          = $verify_document->get_settings();
		$stored          = is_array( $stored ) ? $stored : [];

		$response = [
			'success'      => true,
			'written'      => true,
			'kit_id'       => $kit_id,
			'mode'         => $replace ? 'replace' : 'merge',
			'settings'     => $stored,
			'keys_removed' => $removed_keys,
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $kit_id . '&action=elementor' ),
		];
		if ( ! empty( $inval['warnings'] ) ) {
			$response['cache_invalidation'] = [
				'cleared'  => $inval['invalidated'],
				'warnings' => $inval['warnings'],
			];
		}
		return $response;
	}

	
	private static function sync_library_type( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on the target post.' );
		}
		$template_type = isset( $args['template_type'] ) ? sanitize_key( $args['template_type'] ) : '';
		if ( '' === $template_type ) {
			throw new \Exception( 'template_type is required.' );
		}
		if ( 'elementor_library' !== get_post_type( $post_id ) ) {
			throw new \Exception( 'Post must be of type elementor_library.' );
		}

		update_post_meta( $post_id, '_elementor_template_type', $template_type );
		$term_result = wp_set_object_terms( $post_id, $template_type, 'elementor_library_type', false );
		if ( is_wp_error( $term_result ) ) {
			throw new \Exception( 'Failed to set library type term: ' . esc_html( $term_result->get_error_message() ) );
		}

		$terms = wp_get_object_terms( $post_id, 'elementor_library_type', [ 'fields' => 'slugs' ] );
		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}

		return [
			'success'       => true,
			'post_id'       => $post_id,
			'template_type' => $template_type,
			'terms'         => array_values( (array) $terms ),
		];
	}
}
