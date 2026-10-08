<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BeaverBuilder {

	private const SNIPPET_KEYS = [
		'heading'   => [ 'heading' ],
		'rich-text' => [ 'text' ],
		'button'    => [ 'text' ],
		'callout'   => [ 'title' ],
		'cta'       => [ 'title' ],
	];

	public static function is_available(): bool {
		return defined( 'FL_BUILDER_VERSION' )
			|| class_exists( 'FLBuilderModel' );
	}

	public static function get_manifest(): array {
		return array(
			'providers'    => array( 'beaver-builder' ),
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
				'name'        => 'beaver_get_page_outline',
				'description' => 'Read the structural outline of a Beaver Builder layout without rendering it. Returns the row / column-group / column / module hierarchy as a nested tree with stable node IDs (address them with beaver_get_node), module types, and short text snippets from text-bearing modules. Reads the published layout by default; pass status=draft to read the draft instead. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'status'  => [ 'type' => 'string', 'enum' => [ 'published', 'draft' ], 'description' => 'Which layout to read. Default published.' ],
						'depth'   => [ 'type' => 'integer', 'description' => 'Maximum outline nesting depth. Default 6, max 20.' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'beaver_get_node',
				'description' => 'Read one Beaver Builder node (row, column-group, column, or module) by its stable node ID from beaver_get_page_outline. Returns the node type, module type for modules, depth, parent, position, and the raw settings object. A node ID that no longer exists returns found=false. Read-only; requires edit_posts generally and read_post on the target.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'  => [ 'type' => 'integer', 'description' => 'Post or page ID to inspect.' ],
						'node_id'  => [ 'type' => 'string', 'description' => 'Node ID from beaver_get_page_outline.' ],
						'status'   => [ 'type' => 'string', 'enum' => [ 'published', 'draft' ], 'description' => 'Which layout to read. Default published.' ],
					],
					'required'   => [ 'post_id', 'node_id' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Beaver Builder tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Beaver Builder is not active.' );
		}

		switch ( $name ) {
			case 'beaver_get_page_outline':
				return self::get_page_outline( $args );
			case 'beaver_get_node':
				return self::get_node( $args );
			default:
				throw new \Exception( 'Unknown Beaver Builder tool: ' . esc_html( (string) $name ) );
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

		$status = ( isset( $args['status'] ) && 'draft' === $args['status'] ) ? 'draft' : 'published';
		$depth  = isset( $args['depth'] ) ? max( 1, min( 20, (int) $args['depth'] ) ) : 6;

		$data = self::layout_data( $post_id, $status );
		if ( empty( $data ) ) {
			throw new \Exception( 'Target post does not have a Beaver Builder layout.' );
		}

		$post = get_post( $post_id );

		return [
			'post_id'    => $post_id,
			'post_title' => $post ? $post->post_title : '',
			'post_type'  => $post ? $post->post_type : '',
			'status'     => $status,
			'enabled'    => (bool) get_post_meta( $post_id, '_fl_builder_enabled', true ),
			'total'      => count( $data ),
			'outline'    => self::build_outline( $data, $depth ),
		];
	}

	private static function get_node( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$node_id = isset( $args['node_id'] ) ? (string) $args['node_id'] : '';
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( '' === $node_id ) {
			throw new \Exception( 'node_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$status = ( isset( $args['status'] ) && 'draft' === $args['status'] ) ? 'draft' : 'published';
		$data   = self::layout_data( $post_id, $status );
		if ( empty( $data ) ) {
			throw new \Exception( 'Target post does not have a Beaver Builder layout.' );
		}

		if ( ! isset( $data[ $node_id ] ) ) {
			return [
				'post_id' => $post_id,
				'node_id' => $node_id,
				'found'   => false,
			];
		}

		$node  = $data[ $node_id ];
		$depth = 0;
		$cur   = $node;
		while ( ! empty( $cur->parent ) && isset( $data[ $cur->parent ] ) && $depth < 50 ) {
			$cur = $data[ $cur->parent ];
			$depth++;
		}

		return [
			'post_id'     => $post_id,
			'node_id'     => $node_id,
			'found'       => true,
			'type'        => isset( $node->type ) ? (string) $node->type : 'unknown',
			'module_type' => ( isset( $node->type ) && 'module' === $node->type && isset( $node->settings->type ) ) ? (string) $node->settings->type : null,
			'parent'      => isset( $node->parent ) ? (string) $node->parent : null,
			'position'    => isset( $node->position ) ? (int) $node->position : null,
			'depth'       => $depth,
			'settings'    => isset( $node->settings ) ? $node->settings : new \stdClass(),
		];
	}

	private static function layout_data( int $post_id, string $status ) {
		if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'get_layout_data' ) ) {
			try {
				$data = \FLBuilderModel::get_layout_data( $status, $post_id );
				if ( is_array( $data ) ) {
					return $data;
				}
			} catch ( \Throwable $e ) {
				
			}
		}
		$raw = get_post_meta( $post_id, 'draft' === $status ? '_fl_builder_draft' : '_fl_builder_data', true );
		return is_array( $raw ) ? $raw : array();
	}

	private static function build_outline( array $data, int $max_depth, $parent_id = '', int $depth = 0 ) {
		if ( $depth >= $max_depth ) {
			return [ '...deep nesting truncated...' ];
		}

		$children = [];
		foreach ( $data as $id => $node ) {
			if ( ! is_object( $node ) || ! isset( $node->node ) ) {
				continue;
			}
			$parent = isset( $node->parent ) ? (string) $node->parent : '';
			if ( '' === $parent_id && '' !== $parent && isset( $data[ $parent ] ) ) {
				continue; 
			}
			if ( '' !== $parent_id && $parent !== $parent_id ) {
				continue;
			}
			$children[] = $node;
		}
		usort( $children, fn( $a, $b ) => (int) ( $a->position ?? 0 ) <=> (int) ( $b->position ?? 0 ) );

		$out = [];
		foreach ( $children as $node ) {
			$entry = [
				'id'   => (string) $node->node,
				'type' => isset( $node->type ) ? (string) $node->type : 'unknown',
			];
			if ( isset( $node->type ) && 'module' === $node->type ) {
				$module_type          = isset( $node->settings->type ) ? (string) $node->settings->type : 'unknown';
				$entry['moduleType']  = $module_type;
				$snippet              = self::module_text_snippet( $node, $module_type );
				if ( '' !== $snippet ) {
					$entry['snippet'] = $snippet;
				}
			}
			$entry['children'] = self::build_outline( $data, $max_depth, (string) $node->node, $depth + 1 );
			if ( empty( $entry['children'] ) ) {
				unset( $entry['children'] );
			}
			$out[] = $entry;
		}
		return $out;
	}

	private static function module_text_snippet( $node, string $module_type ) {
		if ( ! isset( self::SNIPPET_KEYS[ $module_type ] ) || empty( $node->settings ) ) {
			return '';
		}
		foreach ( self::SNIPPET_KEYS[ $module_type ] as $key ) {
			if ( isset( $node->settings->{$key} ) && is_string( $node->settings->{$key} ) && '' !== $node->settings->{$key} ) {
				$plain = wp_strip_all_tags( $node->settings->{$key} );
				return mb_strimwidth( $plain, 0, 80, '...' );
			}
		}
		return '';
	}
}
