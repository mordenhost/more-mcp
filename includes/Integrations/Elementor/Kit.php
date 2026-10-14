<?php
namespace Mordenhost_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Kit {

	
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

	
	public static function invalidate_if_active_kit( $post_id ) {
		$kit_id = self::get_active_kit_id();
		if ( $kit_id <= 0 || (int) $post_id !== $kit_id ) {
			return null;
		}
		return self::invalidate_kit_state();
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

	
	private static function paginate_list( array $items, array $args, $default, $max ) {
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : $default;
		if ( $per_page < 1 ) {
			$per_page = $default;
		}
		if ( $per_page > $max ) {
			$per_page = $max;
		}
		$page = isset( $args['page'] ) ? (int) $args['page'] : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$total  = count( $items );
		$pages  = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
		$offset = ( $page - 1 ) * $per_page;
		$slice  = array_slice( array_values( $items ), $offset, $per_page );

		return [
			'items' => $slice,
			'meta'  => [
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'pages'    => $pages,
				'returned' => count( $slice ),
				'has_more' => ( $offset + count( $slice ) ) < $total,
			],
		];
	}

	
	public static function describe_setting_value( $value ) {
		if ( is_array( $value ) ) {
			
			
			
			
			if ( empty( $value ) ) {
				return [ 'type' => 'empty', 'count' => 0 ];
			}
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			return [
				'type'  => $is_list ? 'list' : 'object',
				'count' => count( $value ),
			];
		}
		if ( is_string( $value ) ) {
			return [
				'type'   => 'string',
				'length' => strlen( $value ),
				'empty'  => ( '' === $value ),
			];
		}
		if ( is_bool( $value ) ) {
			return [ 'type' => 'boolean', 'value' => $value ];
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return [ 'type' => 'number', 'value' => $value ];
		}
		if ( null === $value ) {
			return [ 'type' => 'null' ];
		}
		return [ 'type' => gettype( $value ) ];
	}

	
	public static function get_kit( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		$kit_document = self::require_kit_document();
		$settings     = $kit_document->get_settings();
		$settings     = is_array( $settings ) ? $settings : [];
		$kit_id       = (int) $kit_document->get_id();

		
		
		
		
		$css_search = isset( $args['css_search'] ) ? trim( (string) $args['css_search'] ) : '';
		if ( '' !== $css_search ) {
			return self::search_custom_css( $settings, $kit_id, $css_search, $args );
		}

		
		$requested = [];
		if ( isset( $args['keys'] ) ) {
			foreach ( (array) $args['keys'] as $key ) {
				$key = sanitize_text_field( (string) $key );
				if ( '' !== $key ) {
					$requested[] = $key;
				}
			}
		}

		
		
		
		
		$search    = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
		$max_chars = isset( $args['max_chars'] ) ? max( 100, min( 100000, (int) $args['max_chars'] ) ) : null;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		if ( ! empty( $requested ) ) {
			$picked  = [];
			$unknown = [];
			foreach ( $requested as $key ) {
				if ( array_key_exists( $key, $settings ) ) {
					$picked[ $key ] = $settings[ $key ];
				} else {
					$unknown[] = $key;
				}
			}
			$response = [
				'success'   => true,
				'kit_id'    => $kit_id,
				'mode'      => 'keys',
				'settings'  => $picked,
				'total_keys' => count( $settings ),
			];
			if ( ! empty( $unknown ) ) {
				
				
				
				$response['unset_keys'] = $unknown;
				$response['note']       = 'These keys are not present in the kit\'s saved settings, which means they still hold Elementor\'s default. Read elementor_get_kit_schema for the default.';
			}
			return self::with_long_strings_paged( $response, $max_chars, $offset );
		}

		$pool = $settings;
		if ( '' !== $search ) {
			$pool = array_filter(
				$settings,
				static function ( $key ) use ( $search ) {
					return false !== strpos( strtolower( (string) $key ), $search );
				},
				ARRAY_FILTER_USE_KEY
			);
		}
		$search_fields = ( '' === $search ) ? [] : [
			'search'       => $search,
			'matched_keys' => count( $pool ),
		];

		if ( ! empty( $args['include_all'] ) ) {
			return self::with_long_strings_paged( array_merge( [
				'success'    => true,
				'kit_id'     => $kit_id,
				'mode'       => 'full',
				'settings'   => $pool,
				'total_keys' => count( $settings ),
			], $search_fields ), $max_chars, $offset );
		}

		$index = [];
		foreach ( $pool as $key => $value ) {
			$index[ $key ] = self::describe_setting_value( $value );
		}
		ksort( $index );

		$response = array_merge( [
			'success'    => true,
			'kit_id'     => $kit_id,
			'mode'       => 'index',
			'total_keys' => count( $settings ),
			'keys'       => $index,
			'note'       => 'This is a key index, not the values. The full kit settings object is too large for one tool result on most sites. Pass keys:["system_colors","custom_css"] to read specific values (max_chars / offset page through a long string), search:"button" to index only matching keys, or include_all:true to force the whole object.',
		], $search_fields );
		if ( '' === $search && count( $settings ) > 150 ) {
			$response['hint'] = 'Large kit (' . count( $settings ) . ' keys). Pass search to index only the keys whose name contains a term.';
		}
		return $response;
	}

	
	private static function with_long_strings_paged( array $response, $max_chars, $offset ) {
		$long      = [];
		$truncated = [];
		foreach ( $response['settings'] as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			$length = mb_strlen( $value, 'UTF-8' );
			if ( null === $max_chars && 0 === $offset ) {
				if ( $length > 20000 ) {
					$long[] = $key . ' (' . $length . ' characters)';
				}
				continue;
			}
			if ( 0 === $offset && $length <= $max_chars ) {
				continue;
			}
			$part     = mb_substr( $value, $offset, $max_chars, 'UTF-8' );
			$returned = mb_strlen( $part, 'UTF-8' );
			$response['settings'][ $key ] = $part;
			$truncated[ $key ] = [
				'length'      => $length,
				'offset'      => min( $offset, $length ),
				'returned'    => $returned,
				'next_offset' => ( $offset + $returned < $length ) ? $offset + $returned : null,
			];
		}
		if ( ! empty( $truncated ) ) {
			$response['truncated'] = $truncated;
		}
		if ( ! empty( $long ) ) {
			$response['hint'] = 'Long string value(s): ' . implode( ', ', $long ) . '. Pass max_chars (and offset to continue) to read them in pages.';
		}
		return $response;
	}

	
	private static function search_custom_css( array $settings, $kit_id, $term, array $args ) {
		$term     = mb_substr( $term, 0, 200, 'UTF-8' );
		$budget   = isset( $args['max_chars'] ) ? max( 100, min( 100000, (int) $args['max_chars'] ) ) : 20000;
		$css      = ( isset( $settings['custom_css'] ) && is_string( $settings['custom_css'] ) ) ? $settings['custom_css'] : '';
		$response = [
			'success'    => true,
			'kit_id'     => $kit_id,
			'mode'       => 'css_search',
			'css_search' => $term,
			'key'        => 'custom_css',
			'css_length' => mb_strlen( $css, 'UTF-8' ),
			'total_keys' => count( $settings ),
		];
		if ( '' === trim( $css ) ) {
			return $response + [
				'blocks_total' => 0,
				'matched'      => 0,
				'matches'      => [],
				'note'         => 'The kit has no custom_css.',
			];
		}

		$found     = Css_Blocks::search( $css, $term, $budget, 100 );
		$response += [
			'blocks_total'   => $found['blocks_total'],
			'matched'        => $found['matched'],
			'matches'        => $found['matches'],
			'returned_chars' => $found['returned_chars'],
		];
		if ( 0 === $found['matched'] ) {
			$response['note'] = 'No custom_css block contains "' . $term . '" (case-insensitive). css_search matches the CSS text; search matches setting key names.';
			return $response;
		}
		$notes = [ 'offset and length are in characters, the unit elementor_get_kit offset / max_chars use, so keys:["custom_css"] with that offset and max_chars reads a block in place. A group rule such as @media matched only inside lists the inner blocks that matched under nested.' ];
		if ( $found['omitted'] > 0 ) {
			$response['css_omitted_blocks'] = $found['omitted'];
			$notes[] = sprintf( '%d matching block(s) did not fit in %d characters and are listed by offset and length only (css_omitted): raise max_chars, narrow css_search, or read them with keys:["custom_css"], offset and max_chars.', $found['omitted'], $budget );
		}
		if ( $found['matched'] > $found['listed'] ) {
			$response['matches_truncated'] = true;
			$notes[] = sprintf( 'Only the first %d of %d matching blocks are listed; narrow css_search.', $found['listed'], $found['matched'] );
		}
		$response['note'] = implode( ' ', $notes );
		return $response;
	}

	
	public static function get_kit_schema( $args ) {
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

		$requested_tab = isset( $args['tab'] ) ? sanitize_text_field( (string) $args['tab'] ) : '';
		$search        = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
		$include_all   = ! empty( $args['include_all'] );

		$tabs         = $kit->get_tabs();
		$tab_controls = [];

		if ( isset( \Elementor\Plugin::$instance->controls_manager ) ) {
			\Elementor\Plugin::$instance->controls_manager->clear_stack_cache();
		}

		foreach ( $tabs as $tab_id => $tab ) {
			
			
			
			
			if ( '' !== $requested_tab && (string) $tab_id !== $requested_tab ) {
				continue;
			}
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
				if ( '' !== $search ) {
					$label = isset( $control['label'] ) ? strtolower( (string) $control['label'] ) : '';
					if ( false === strpos( strtolower( (string) $control_id ), $search )
						&& ( '' === $label || false === strpos( $label, $search ) ) ) {
						continue;
					}
				}
				$tab_controls[ $tab_id ][ $control_id ] = self::process_kit_control_schema( $control );
			}
		}

		if ( '' !== $requested_tab && empty( $tab_controls ) ) {
			throw new \Exception(
				'No kit tab named "' . esc_html( $requested_tab ) . '". Call elementor_get_kit_schema with no arguments to list the tabs this site registers.'
			);
		}

		
		
		
		if ( '' !== $requested_tab || '' !== $search || $include_all ) {
			if ( '' !== $search ) {
				
				
				$tab_controls = array_filter( $tab_controls, fn( $c ) => ! empty( $c ) );
			}
			$control_total = 0;
			foreach ( $tab_controls as $controls ) {
				$control_total += count( $controls );
			}
			$response = [
				'success'       => true,
				'mode'          => '' !== $requested_tab ? 'tab' : ( '' !== $search ? 'search' : 'full' ),
				'control_count' => $control_total,
				'tabs'          => $tab_controls,
			];
			if ( '' !== $search ) {
				$response['search'] = $search;
				if ( empty( $tab_controls ) ) {
					$response['note'] = 'No kit control id or label matched. Call with no arguments to list the tabs, then pass tab:"<id>" to read one in full.';
				}
			}
			return $response;
		}

		
		
		$index = [];
		foreach ( $tabs as $tab_id => $tab ) {
			if ( isset( \Elementor\Plugin::$instance->controls_manager ) ) {
				\Elementor\Plugin::$instance->controls_manager->delete_stack( $kit );
			}
			$tab->register_controls();
			$controls = $kit->get_controls();
			$settable = 0;
			foreach ( $controls as $control ) {
				$type = $control['type'] ?? '';
				if ( 'section' === $type || 'heading' === $type || 'popover_toggle' === $type ) {
					continue;
				}
				$settable++;
			}
			$index[ (string) $tab_id ] = [ 'control_count' => $settable ];
		}

		return [
			'success' => true,
			'mode'    => 'index',
			'tabs'    => $index,
			'note'    => 'This is a tab index, not the controls. The complete kit schema is far too large for one tool result. Pass tab:"global-colors" to read one tab in full, search:"typography" to find controls across tabs, or include_all:true to force everything (expect a very large response).',
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

	
	public static function list_widget_types( $args ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			throw new \Exception( 'Elementor is not bootstrapped; the widget registry is unavailable.' );
		}
		Runtime::ensure_widgets_registered();
		$manager = \Elementor\Plugin::$instance->widgets_manager ?? null;
		if ( ! $manager || ! method_exists( $manager, 'get_widget_types' ) ) {
			throw new \Exception( 'Elementor widget manager is unavailable.' );
		}
		$registered = $manager->get_widget_types();
		if ( ! is_array( $registered ) ) {
			throw new \Exception( 'Elementor widget registry returned no types.' );
		}

		$category_filter = isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '';
		$search          = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';

		$widgets = [];
		foreach ( $registered as $name => $widget ) {
			if ( ! is_object( $widget ) ) {
				continue;
			}
			$title      = method_exists( $widget, 'get_title' ) ? (string) $widget->get_title() : (string) $name;
			$categories = method_exists( $widget, 'get_categories' ) ? (array) $widget->get_categories() : [];
			$keywords   = method_exists( $widget, 'get_keywords' ) ? (array) $widget->get_keywords() : [];
			$icon       = method_exists( $widget, 'get_icon' ) ? (string) $widget->get_icon() : '';

			if ( '' !== $category_filter && ! in_array( $category_filter, $categories, true ) ) {
				continue;
			}
			if ( '' !== $search
				&& false === strpos( strtolower( (string) $name ), $search )
				&& false === strpos( strtolower( $title ), $search ) ) {
				continue;
			}

			$widgets[] = [
				'name'       => (string) $name,
				'title'      => $title,
				'categories' => array_values( array_filter( array_map( 'strval', $categories ) ) ),
				'keywords'   => array_values( array_filter( array_map( 'strval', $keywords ) ) ),
				'icon'       => $icon,
			];
		}

		usort( $widgets, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

		
		
		
		
		
		$paged = self::paginate_list( $widgets, $args, 60, 200 );

		return array_merge(
			[
				'success'  => true,
				'total'    => $paged['meta']['total'],
				'provider' => Runtime::detect_provider_capability(),
			],
			$paged['meta'],
			[ 'widget_types' => $paged['items'] ]
		);
	}

	
	public static function get_widget_type_schema( $args ) {
		$widget_type = isset( $args['widget_type'] ) ? sanitize_text_field( (string) $args['widget_type'] ) : '';
		if ( '' === $widget_type ) {
			throw new \Exception( 'widget_type is required.' );
		}

		$state = Runtime::classify_widget_type( $widget_type );
		if ( 'atomic' === $state ) {
			
			
			
			return [
				'success'     => true,
				'widget_type' => $widget_type,
				'atomic'      => true,
				'controls'    => new \stdClass(),
				'note'        => 'This is an Editor V4 Atomic widget (a-*/e-*). Its schema is not exposed through the classic control stack; pass its settings object opaquely to elementor_add_widget.',
			];
		}
		if ( 'unavailable' === $state ) {
			throw new \Exception( 'Elementor is not bootstrapped; the widget registry is unavailable.' );
		}
		if ( 'registered' !== $state ) {
			throw new \Exception( esc_html( Runtime::unregistered_widget_message( $widget_type, Runtime::detect_provider_capability() ) ) );
		}

		$manager = \Elementor\Plugin::$instance->widgets_manager;
		$widget  = $manager->get_widget_types( $widget_type );
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_controls' ) ) {
			throw new \Exception( 'Widget type "' . esc_html( $widget_type ) . '" exposes no control stack.' );
		}

		
		
		if ( class_exists( '\Elementor\Core\Frontend\Performance' )
			&& method_exists( '\Elementor\Core\Frontend\Performance', 'set_use_style_controls' ) ) {
			\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
		}

		$controls = $widget->get_controls();
		if ( ! is_array( $controls ) ) {
			$controls = [];
		}

		$search      = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
		$include_all = ! empty( $args['include_all'] );
		
		
		
		$tab_filter = isset( $args['tab'] ) ? sanitize_text_field( (string) $args['tab'] ) : '';
		if ( '' === $tab_filter && '' === $search && ! $include_all ) {
			$tab_filter = 'content';
		}

		$skip           = [ 'section', 'heading', 'popover_toggle', 'tab', 'divider', 'raw_html' ];
		$schema         = [];
		$tab_counts     = [];
		$settable_total = 0;
		foreach ( $controls as $control_id => $control ) {
			if ( ! is_array( $control ) ) {
				continue;
			}
			$type = $control['type'] ?? '';
			if ( in_array( $type, $skip, true ) ) {
				continue;
			}

			
			
			
			
			
			
			$settable_total++;
			$control_tab = isset( $control['tab'] ) && '' !== $control['tab'] ? (string) $control['tab'] : 'content';
			$tab_counts[ $control_tab ] = ( $tab_counts[ $control_tab ] ?? 0 ) + 1;

			if ( '' !== $tab_filter && $control_tab !== $tab_filter ) {
				continue;
			}
			if ( '' !== $search ) {
				$label = isset( $control['label'] ) ? strtolower( (string) $control['label'] ) : '';
				if ( false === strpos( strtolower( (string) $control_id ), $search )
					&& ( '' === $label || false === strpos( $label, $search ) ) ) {
					continue;
				}
			}

			$entry        = self::process_kit_control_schema( $control );
			$entry['tab'] = $control_tab;
			$schema[ $control_id ] = $entry;
		}

		$title      = method_exists( $widget, 'get_title' ) ? (string) $widget->get_title() : $widget_type;
		$categories = method_exists( $widget, 'get_categories' ) ? array_values( (array) $widget->get_categories() ) : [];

		ksort( $tab_counts );

		$response = [
			'success'         => true,
			'widget_type'     => $widget_type,
			'title'           => $title,
			'categories'      => $categories,
			'atomic'          => false,
			'control_count'   => count( $schema ),
			'total_controls'  => $settable_total,
			'controls_by_tab' => $tab_counts,
			'controls'        => empty( $schema ) ? new \stdClass() : $schema,
		];

		if ( '' !== $search ) {
			$response['mode']   = 'search';
			$response['search'] = $search;
			if ( empty( $schema ) ) {
				$response['note'] = 'No control id or label matched. controls_by_tab lists every tab and its control count; pass tab:"<name>" to read one in full.';
			}
		} elseif ( $include_all ) {
			$response['mode'] = 'full';
		} else {
			$response['mode'] = 'tab';
			$response['tab']  = $tab_filter;
			if ( $settable_total > count( $schema ) ) {
				$response['note'] = sprintf(
					'Showing the "%s" tab (%d of %d controls). The remaining controls are styling and responsive variants; see controls_by_tab and pass tab:"style" / tab:"advanced", search:"<term>" for one control, or include_all:true for everything (expect a very large response).',
					$tab_filter,
					count( $schema ),
					$settable_total
				);
			}
			if ( empty( $schema ) && $settable_total > 0 ) {
				$response['note'] = sprintf(
					'This widget registers no controls on the "%s" tab. controls_by_tab lists the tabs it does use.',
					$tab_filter
				);
			}
		}

		return $response;
	}

	
	public static function get_kit_fonts( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		if ( ! class_exists( '\Elementor\Fonts' ) ) {
			throw new \Exception( 'Elementor Fonts class is unavailable.' );
		}

		$fonts       = \Elementor\Fonts::get_fonts();
		$fonts       = is_array( $fonts ) ? $fonts : [];
		$font_groups = method_exists( '\Elementor\Fonts', 'get_font_groups' )
			? \Elementor\Fonts::get_font_groups()
			: [];
		$font_groups = is_array( $font_groups ) ? $font_groups : [];

		$total_fonts = count( $fonts );

		
		
		
		$group_counts = [];
		foreach ( $fonts as $group ) {
			$key                  = (string) $group;
			$group_counts[ $key ] = ( $group_counts[ $key ] ?? 0 ) + 1;
		}
		ksort( $group_counts );

		$search       = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
		$group_filter = isset( $args['group'] ) ? sanitize_text_field( (string) $args['group'] ) : '';

		$filtered = [];
		foreach ( $fonts as $family => $group ) {
			if ( '' !== $group_filter && (string) $group !== $group_filter ) {
				continue;
			}
			if ( '' !== $search && false === strpos( strtolower( (string) $family ), $search ) ) {
				continue;
			}
			$filtered[] = [
				'family' => (string) $family,
				'group'  => (string) $group,
			];
		}

		$paged = self::paginate_list( $filtered, $args, 100, 500 );

		
		
		$page_map = [];
		foreach ( $paged['items'] as $entry ) {
			$page_map[ $entry['family'] ] = $entry['group'];
		}

		$response = array_merge(
			[
				'success'              => true,
				'fonts'                => $page_map,
				'font_groups'          => $font_groups,
				'google_fonts_enabled' => method_exists( '\Elementor\Fonts', 'is_google_fonts_enabled' )
					? (bool) \Elementor\Fonts::is_google_fonts_enabled()
					: null,
				'font_display_setting' => method_exists( '\Elementor\Fonts', 'get_font_display_setting' )
					? \Elementor\Fonts::get_font_display_setting()
					: null,
				'total_fonts'          => $total_fonts,
				'fonts_by_group'       => $group_counts,
			],
			$paged['meta']
		);

		if ( '' !== $search ) {
			$response['search'] = $search;
			if ( 0 === $paged['meta']['total'] ) {
				$response['note'] = 'No font family matched. fonts_by_group lists the groups this site has; note that a Google font is available to Elementor whether or not it is currently used on the site.';
			}
		}
		if ( '' !== $group_filter ) {
			$response['group'] = $group_filter;
		}
		if ( '' === $search && '' === $group_filter && $paged['meta']['has_more'] ) {
			$response['note'] = 'Elementor bundles the entire Google Fonts catalogue, so this list is paginated. To check one family pass search:"Roboto"; to browse one group pass group:"system" (see fonts_by_group).';
		}

		return $response;
	}

	
	public static function update_kit( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}

		$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : null;
		if ( null === $settings ) {
			throw new \Exception( 'settings (object) is required.' );
		}
		if ( empty( $settings ) ) {
			throw new \Exception( 'settings is empty. Nothing to write.' );
		}
		$replace = ! empty( $args['replace_settings'] );
		$dry_run = ! empty( $args['dry_run'] );

		
		
		
		
		
		
		$css_mode = isset( $args['custom_css_mode'] ) ? strtolower( trim( (string) $args['custom_css_mode'] ) ) : 'replace';
		if ( ! in_array( $css_mode, [ 'replace', 'append' ], true ) ) {
			throw new \Exception( 'custom_css_mode must be "replace" (the default) or "append".' );
		}
		if ( 'append' === $css_mode ) {
			if ( $replace ) {
				throw new \Exception( 'custom_css_mode "append" cannot be combined with replace_settings: append keeps the kit\'s current custom CSS, replace_settings discards it. Send one or the other.' );
			}
			if ( ! array_key_exists( 'custom_css', $settings ) || ! is_string( $settings['custom_css'] ) ) {
				throw new \Exception( 'custom_css_mode "append" needs settings.custom_css as a string: the CSS to add after the kit\'s current custom CSS.' );
			}
			if ( '' === trim( $settings['custom_css'] ) ) {
				throw new \Exception( 'settings.custom_css is empty: nothing to append.' );
			}
		}

		$kit_document     = self::require_kit_document();
		$kit_id           = (int) $kit_document->get_id();
		$current_settings = $kit_document->get_settings();
		$current_settings = is_array( $current_settings ) ? $current_settings : [];

		$append_report = null;
		if ( 'append' === $css_mode ) {
			$existing = $current_settings['custom_css'] ?? '';
			if ( ! is_string( $existing ) ) {
				throw new \Exception( 'The kit\'s stored custom_css is not a string, so nothing can be appended to it. Read it with elementor_get_kit keys:["custom_css"] and send the whole value without custom_css_mode.' );
			}
			$block     = $settings['custom_css'];
			$separator = ( '' === $existing || "\n" === substr( $existing, -1 ) ) ? '' : "\n";
			$settings['custom_css'] = $existing . $separator . $block;
			$append_report = [
				'mode'           => 'append',
				'length_before'  => mb_strlen( $existing, 'UTF-8' ),
				'appended_at'    => mb_strlen( $existing . $separator, 'UTF-8' ),
				'appended_chars' => mb_strlen( $block, 'UTF-8' ),
				'length_after'   => mb_strlen( $settings['custom_css'], 'UTF-8' ),
			];
			if ( false !== strpos( $existing, trim( $block ) ) ) {
				
				$append_report['already_present'] = true;
				$append_report['note']            = 'The kit\'s custom CSS already contained this block; it was appended again. Read it with elementor_get_kit css_search to check for a duplicate.';
			}
		}

		if ( $replace ) {
			$merged       = $settings;
			$removed_keys = array_values( array_diff( array_keys( $current_settings ), array_keys( $settings ) ) );
		} else {
			$merged       = array_merge( $current_settings, $settings );
			$removed_keys = [];
		}

		if ( $dry_run ) {
			
			
			
			
			
			
			
			if ( ! empty( $args['include_all'] ) ) {
				return self::with_append_report( [
					'success'         => true,
					'written'         => false,
					'dry_run'         => true,
					'kit_id'          => $kit_id,
					'mode'            => $replace ? 'replace' : 'merge',
					'settings_before' => $current_settings,
					'settings_after'  => $merged,
					'keys_removed'    => $removed_keys,
				], $append_report );
			}

			$changes = [];
			foreach ( $settings as $key => $new_value ) {
				$had = array_key_exists( $key, $current_settings );
				$changes[ $key ] = [
					'action' => $had ? ( $current_settings[ $key ] === $new_value ? 'unchanged' : 'replaced' ) : 'added',
					'before' => $had ? self::describe_setting_value( $current_settings[ $key ] ) : null,
					'after'  => self::describe_setting_value( $new_value ),
				];
			}

			return self::with_append_report( [
				'success'      => true,
				'written'      => false,
				'dry_run'      => true,
				'kit_id'       => $kit_id,
				'mode'         => $replace ? 'replace' : 'merge',
				'changes'      => $changes,
				'keys_removed' => $removed_keys,
				'keys_total_after' => count( $merged ),
				'note'         => 'changes describes only the keys this call touches; every other kit key is preserved' . ( $replace ? ' — except the ones listed in keys_removed, which a replace discards' : '' ) . '. Pass include_all:true for the full settings_before / settings_after objects (expect a very large response). Read current values with elementor_get_kit.',
			], $append_report );
		}

		
		
		
		
		$pre_op_settings = $current_settings;

		
		
		
		
		
		$kit_document->save( [ 'settings' => $merged ] );

		$inval = self::invalidate_kit_state();

		
		
		
		
		
		
		
		$stored = null;
		try {
			$verify_document = self::require_kit_document();
			$stored          = $verify_document->get_settings();
			$stored          = is_array( $stored ) ? $stored : [];
		} catch ( \Exception $e ) {
			$stored = null;
		}

		
		$undo = \Mordenhost_MCP\MCP\Undo_Store::store( [
			'op'      => 'elementor_kit_write',
			'summary' => sprintf(
				'elementor_update_kit on kit %d (%s mode)',
				$kit_id,
				$replace ? 'replace' : 'merge'
			),
			'target'  => [ 'kit_id' => $kit_id ],
			'pre_op_state' => [ 'settings' => $pre_op_settings ],
		] );

		$response = [
			'success'      => true,
			'written'      => true,
			'kit_id'       => $kit_id,
			'mode'         => $replace ? 'replace' : 'merge',
			'keys_removed' => $removed_keys,
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $kit_id . '&action=elementor' ),
		];
		$response = self::with_append_report( $response, $append_report );

		
		
		
		
		
		
		
		
		
		if ( null === $stored ) {
			$response = array_merge(
				$response,
				\Mordenhost_MCP\Integrations\Write_Verify::report(
					\Mordenhost_MCP\Integrations\Write_Verify::UNKNOWN,
					__( 'kit settings', 'mordenhost-mcp' ),
					__( 'The kit document could not be re-resolved after the save, so the stored values were never read. The save itself committed and the undo token is valid.', 'mordenhost-mcp' )
				)
			);
			if ( ! empty( $inval['warnings'] ) ) {
				$response['cache_invalidation'] = [
					'cleared'  => $inval['invalidated'],
					'warnings' => $inval['warnings'],
				];
			}
			return $response;
		}

		$verified = [];
		foreach ( array_keys( $settings ) as $key ) {
			$verified[ $key ] = [
				'stored'  => array_key_exists( $key, $stored ),
				'matches' => array_key_exists( $key, $stored ) && $stored[ $key ] === $settings[ $key ],
			];
		}
		$response['verified']         = $verified;
		$response['keys_total_after'] = count( $stored );

		$mismatched = array_keys( array_filter( $verified, fn( $v ) => ! $v['matches'] ) );
		if ( ! empty( $mismatched ) ) {
			
			
			
			$response['warning'] = 'These keys are stored with a value different from what was sent, most likely Elementor\'s own sanitization: '
				. esc_html( implode( ', ', $mismatched ) ) . '. Read them back with elementor_get_kit keys:[...] to see the stored form.';
		}

		if ( ! empty( $args['include_all'] ) ) {
			$response['settings'] = $stored;
		} else {
			$response['note'] = 'verified reports the stored state of the keys this call wrote. The full kit settings object is omitted because it is too large for one tool result; pass include_all:true to include it, or read specific keys with elementor_get_kit.';
		}

		if ( ! empty( $inval['warnings'] ) ) {
			$response['cache_invalidation'] = [
				'cleared'  => $inval['invalidated'],
				'warnings' => $inval['warnings'],
			];
		}
		return $response;
	}

	
	private static function with_append_report( array $response, $report ) {
		if ( null !== $report ) {
			$response['custom_css_append'] = $report;
		}
		return $response;
	}
}
