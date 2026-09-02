<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteOriginPanels {

	private const SNIPPET_KEYS = [
		'SiteOrigin_Panels_Widget_Call_To_Action' => [ 'title', 'button_text' ],
		'SiteOrigin_Panels_Widget_Button'         => [ 'text' ],
		'SiteOrigin_Panels_Widget_Editor'         => [ 'title', 'text' ],
		'SiteOrigin_Panels_Widgets_Editor'        => [ 'title', 'text' ],
	];

	public static function is_available(): bool {
		return defined( 'SITEORIGIN_PANELS_VERSION' )
			|| defined( 'SITEORIGIN_PANELS_BASE_FILE' )
			|| class_exists( 'SiteOrigin_Panels' );
	}

	public static function get_manifest(): array {
		return array(
			'providers'    => array( 'siteorigin-panels' ),
			'capabilities' => array( 'page_building' ),
			'kind'         => 'builder',
		);
	}

	public static function get_tools(): array {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'siteorigin_get_page_outline',
				'description' => 'Read the structural outline of a SiteOrigin Page Builder layout without rendering it. Returns the row / cell / widget hierarchy as a nested tree with widget classes, index addresses (row, cell, widget_index — use them with siteorigin_get_widget), and short text snippets from text-bearing widgets. Reads both storage paths: the classic panels_data meta layout and any Layout Blocks, each labelled with its storage and block_index. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'depth'   => [ 'type' => 'integer', 'description' => 'Maximum outline nesting depth. Default 4, max 10.' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'siteorigin_get_widget',
				'description' => 'Read one SiteOrigin Page Builder widget by its index address (row, cell, widget_index) from siteorigin_get_page_outline. Choose which layout to address with storage ("meta" for the classic layout, "block" plus block_index for a Layout Block). Returns the widget class, its raw settings, and its index address. An address that no longer exists returns found=false. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'     => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'row'         => [ 'type' => 'integer', 'description' => 'Row (grid) index within the layout.' ],
						'cell'        => [ 'type' => 'integer', 'description' => 'Cell index within the row.' ],
						'widget_index' => [ 'type' => 'integer', 'description' => 'Widget index within the cell.' ],
						'storage'     => [ 'type' => 'string', 'enum' => [ 'meta', 'block' ], 'description' => 'Which layout to address. Default meta (the classic panels_data layout).' ],
						'block_index' => [ 'type' => 'integer', 'description' => 'For storage=block, the 0-based Layout Block index from siteorigin_get_page_outline. Default 0.' ],
					],
					'required'   => [ 'post_id', 'row', 'cell', 'widget_index' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use SiteOrigin Page Builder tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'SiteOrigin Page Builder is not active.' );
		}

		switch ( $name ) {
			case 'siteorigin_get_page_outline':
				return self::get_page_outline( $args );
			case 'siteorigin_get_widget':
				return self::get_widget( $args );
			default:
				throw new \Exception( 'Unknown SiteOrigin Page Builder tool: ' . esc_html( (string) $name ) );
		}
	}

	private static function get_page_outline( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$depth = isset( $args['depth'] ) ? max( 1, min( 10, (int) $args['depth'] ) ) : 4;

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Target post was not found.' );
		}

		$layouts = self::collect_layouts( $post );
		if ( empty( $layouts ) ) {
			throw new \Exception( 'Target post does not have a SiteOrigin Page Builder layout.' );
		}

		return [
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'source'     => self::source_label( $layouts ),
			'layouts'    => array_map( fn( $l ) => self::layout_entry( $l, $depth ), array_values( $layouts ) ),
		];
	}

	private static function get_widget( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		foreach ( array( 'row', 'cell', 'widget_index' ) as $key ) {
			if ( ! isset( $args[ $key ] ) || ! is_numeric( $args[ $key ] ) ) {
				throw new \Exception( $key . ' is required.' );
			}
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$row    = (int) $args['row'];
		$cell   = (int) $args['cell'];
		$windex = (int) $args['widget_index'];
		$storage = ( isset( $args['storage'] ) && 'block' === $args['storage'] ) ? 'block' : 'meta';
		$block_index = isset( $args['block_index'] ) ? max( 0, (int) $args['block_index'] ) : 0;

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Target post was not found.' );
		}

		$layouts = self::collect_layouts( $post );
		$layout  = null;
		foreach ( $layouts as $l ) {
			if ( 'meta' === $storage && 'meta' === $l['storage'] ) {
				$layout = $l;
				break;
			}
			if ( 'block' === $storage && 'block' === $l['storage'] && (int) $l['block_index'] === $block_index ) {
				$layout = $l;
				break;
			}
		}
		if ( null === $layout ) {
			return [
				'post_id' => $post_id,
				'found'   => false,
				'reason'  => 'no layout in the requested storage',
			];
		}

		$widgets = isset( $layout['panels_data']['widgets'] ) && is_array( $layout['panels_data']['widgets'] )
			? $layout['panels_data']['widgets']
			: array();
		$match = null;
		$position = 0;
		foreach ( $widgets as $widget ) {
			if ( ! is_array( $widget ) || empty( $widget['panels_info'] ) || ! is_array( $widget['panels_info'] ) ) {
				continue;
			}
			$w_row  = (int) ( $widget['panels_info']['grid'] ?? -1 );
			$w_cell = (int) ( $widget['panels_info']['cell'] ?? -1 );
			if ( $w_row !== $row || $w_cell !== $cell ) {
				continue;
			}
			if ( $position === $windex ) {
				$match = $widget;
				break;
			}
			$position++;
		}

		if ( null === $match ) {
			return [
				'post_id'     => $post_id,
				'found'       => false,
				'storage'     => $storage,
				'block_index' => 'block' === $storage ? $block_index : null,
				'row'         => $row,
				'cell'        => $cell,
				'widget_index' => $windex,
			];
		}

		$settings = $match;
		unset( $settings['panels_info'] );

		return [
			'post_id'      => $post_id,
			'found'        => true,
			'storage'      => $storage,
			'block_index'  => 'block' === $storage ? $block_index : null,
			'row'          => $row,
			'cell'         => $cell,
			'widget_index' => $windex,
			'widget_class' => isset( $match['panels_info']['class'] ) ? (string) $match['panels_info']['class'] : 'unknown',
			'settings'     => $settings,
		];
	}

	private static function collect_layouts( $post ) {
		$post_id = (int) $post->ID;
		$layouts = array();

		$meta = get_post_meta( $post_id, 'panels_data', true );
		if ( ! empty( $meta ) && is_array( $meta ) ) {
			$layouts[] = array(
				'storage'     => 'meta',
				'block_index' => null,
				'panels_data' => $meta,
			);
		}

		if ( function_exists( 'parse_blocks' ) && ! empty( $post->post_content ) ) {
			if ( class_exists( 'SiteOrigin_Panels_AI_Exposure' ) && method_exists( 'SiteOrigin_Panels_AI_Exposure', 'get_qualifying_block_layouts' ) ) {
				try {
					foreach ( \SiteOrigin_Panels_AI_Exposure::single()->get_qualifying_block_layouts( $post ) as $entry ) {
						$layouts[] = array(
							'storage'     => 'block',
							'block_index' => (int) $entry['block_index'],
							'panels_data' => $entry['panels_data'],
						);
					}
				} catch ( \Throwable $e ) {
					
				}
			} else {
				foreach ( self::qualifying_block_layouts( $post ) as $entry ) {
					$layouts[] = array(
						'storage'     => 'block',
						'block_index' => (int) $entry['block_index'],
						'panels_data' => $entry['panels_data'],
					);
				}
			}
		}

		return $layouts;
	}

	private static function qualifying_block_layouts( $post ) {
		$block_name = 'siteorigin-panels/layout-block';
		if ( class_exists( 'SiteOrigin_Panels_Compat_Layout_Block' ) && defined( 'SiteOrigin_Panels_Compat_Layout_Block::BLOCK_NAME' ) ) {
			$block_name = constant( 'SiteOrigin_Panels_Compat_Layout_Block::BLOCK_NAME' );
		}

		$qualifying = array();
		$index      = 0;
		$blocks     = parse_blocks( $post->post_content );
		foreach ( (array) $blocks as $key => $block ) {
			if (
				empty( $block['blockName'] ) ||
				$block['blockName'] !== $block_name ||
				empty( $block['attrs'] ) ||
				empty( $block['attrs']['panelsData'] )
			) {
				continue;
			}
			$panels = $block['attrs']['panelsData'];
			if ( ! is_array( $panels ) || empty( $panels ) ) {
				continue;
			}
			$qualifying[] = array(
				'block_index' => $index,
				'block_key'   => $key,
				'panels_data' => $panels,
			);
			$index++;
		}
		return $qualifying;
	}

	private static function source_label( array $layouts ) {
		$has_meta  = false;
		$has_block = false;
		foreach ( $layouts as $l ) {
			if ( 'meta' === $l['storage'] ) { $has_meta = true; }
			if ( 'block' === $l['storage'] ) { $has_block = true; }
		}
		if ( $has_meta && $has_block ) { return 'mixed'; }
		if ( $has_block ) { return 'block'; }
		return 'meta';
	}

	private static function layout_entry( array $layout, int $max_depth ) {
		$panels = isset( $layout['panels_data'] ) && is_array( $layout['panels_data'] ) ? $layout['panels_data'] : array();
		$grids = isset( $panels['grids'] ) && is_array( $panels['grids'] ) ? array_values( $panels['grids'] ) : array();
		$cells = isset( $panels['grid_cells'] ) && is_array( $panels['grid_cells'] ) ? $panels['grid_cells'] : array();
		$widgets = isset( $panels['widgets'] ) && is_array( $panels['widgets'] ) ? $panels['widgets'] : array();

		
		$by_cell = array();
		foreach ( $widgets as $widget ) {
			if ( ! is_array( $widget ) || empty( $widget['panels_info'] ) || ! is_array( $widget['panels_info'] ) ) {
				continue;
			}
			$row = (int) ( $widget['panels_info']['grid'] ?? -1 );
			$cell = (int) ( $widget['panels_info']['cell'] ?? -1 );
			$by_cell[ $row ][ $cell ][] = $widget;
		}

		$cells_by_row = array();
		foreach ( $cells as $cell ) {
			if ( ! is_array( $cell ) ) { continue; }
			$row = (int) ( $cell['grid'] ?? -1 );
			$cells_by_row[ $row ][] = $cell;
		}

		$rows_out = array();
		$truncated = false;
		foreach ( $grids as $ri => $grid ) {
			if ( $ri >= $max_depth ) {
				$truncated = true;
				break;
			}
			$row_cells = array();
			$row_cells_raw = isset( $cells_by_row[ $ri ] ) ? $cells_by_row[ $ri ] : array();
			foreach ( $row_cells_raw as $ci => $cell ) {
				$cell_widgets = array();
				$raw_widgets = isset( $by_cell[ $ri ][ $ci ] ) ? $by_cell[ $ri ][ $ci ] : array();
				foreach ( $raw_widgets as $wi => $widget ) {
					$class = isset( $widget['panels_info']['class'] ) ? (string) $widget['panels_info']['class'] : 'unknown';
					$entry = array(
						'row'          => $ri,
						'cell'         => $ci,
						'widget_index' => $wi,
						'class'        => $class,
					);
					$snippet = self::widget_snippet( $widget, $class );
					if ( '' !== $snippet ) {
						$entry['snippet'] = $snippet;
					}
					$cell_widgets[] = $entry;
				}
				$row_cells[] = array(
					'index'   => $ci,
					'weight'  => isset( $cell['weight'] ) ? (float) $cell['weight'] : null,
					'widgets' => $cell_widgets,
				);
			}
			$rows_out[] = array(
				'index'  => $ri,
				'label'  => isset( $grid['label'] ) ? (string) $grid['label'] : '',
				'cells'  => $row_cells,
			);
		}
		if ( $truncated ) {
			$rows_out[] = '...more rows truncated...';
		}

		return array(
			'storage'     => $layout['storage'],
			'block_index' => $layout['block_index'],
			'rows'        => $rows_out,
		);
	}

	private static function widget_snippet( array $widget, string $class ) {
		if ( ! isset( self::SNIPPET_KEYS[ $class ] ) ) {
			return '';
		}
		foreach ( self::SNIPPET_KEYS[ $class ] as $key ) {
			if ( isset( $widget[ $key ] ) && is_string( $widget[ $key ] ) && '' !== $widget[ $key ] ) {
				$plain = wp_strip_all_tags( $widget[ $key ] );
				return mb_strimwidth( $plain, 0, 80, '...' );
			}
		}
		return '';
	}
}
