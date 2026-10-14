<?php

namespace Mordenhost_MCP\SEO;

use Mordenhost_MCP\Tools\Meta_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	const META_KEY = '_mordenhost_mcp_schema';

	const MAX_NODES = 20;

	const MAX_BYTES = 65536;

	const SCRIPT_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	public static function register(): void {
		add_action( 'wp_head', array( __CLASS__, 'print_script' ), 30 );
		add_filter( 'wpseo_schema_graph', array( __CLASS__, 'filter_yoast_graph' ), 99, 2 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter_rank_math' ), 99, 2 );
	}

	

	
	public static function stored( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true, 64 );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$nodes = array();
		foreach ( $raw as $node ) {
			if ( is_array( $node ) && array() !== $node && array_keys( $node ) !== range( 0, count( $node ) - 1 ) ) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	public static function encode_for_storage( array $nodes ): string {
		return (string) wp_json_encode( array_values( $nodes ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public static function store( int $post_id, array $nodes ): array {
		if ( array() === $nodes ) {
			delete_post_meta( $post_id, self::META_KEY );
			$left = get_post_meta( $post_id, self::META_KEY, true );
			return array(
				'written'  => true,
				'verified' => '' === $left || false === $left || array() === $left,
				'warnings' => array(),
			);
		}
		return Meta_Support::write_meta_verified( 'post', $post_id, self::META_KEY, self::encode_for_storage( $nodes ) );
	}

	public static function merge( array $existing, array $incoming ): array {
		$nodes    = array_values( $existing );
		$stored   = count( $nodes );
		$taken    = array();
		$replaced = array();
		$added    = array();
		foreach ( $incoming as $node ) {
			$label = self::label( $node );
			$hit   = null;
			for ( $i = 0; $i < $stored; $i++ ) {
				if ( ! isset( $taken[ $i ] ) && self::same_slot( $nodes[ $i ], $node, true ) ) {
					$hit = $i;
					break;
				}
			}
			if ( null === $hit ) {
				$nodes[] = $node;
				$added[] = $label;
			} else {
				$nodes[ $hit ] = $node;
				$taken[ $hit ] = true;
				$replaced[]    = $label;
			}
		}
		return array(
			'nodes'    => array_values( $nodes ),
			'replaced' => $replaced,
			'added'    => $added,
		);
	}

	public static function same_slot( array $a, array $b, bool $strict_ids ): bool {
		$id_a = isset( $a['@id'] ) && is_string( $a['@id'] ) ? $a['@id'] : '';
		$id_b = isset( $b['@id'] ) && is_string( $b['@id'] ) ? $b['@id'] : '';
		if ( '' !== $id_a && $id_a === $id_b ) {
			return true;
		}
		if ( $strict_ids && '' !== $id_a && '' !== $id_b ) {
			return false;
		}
		$types_a = Schema_Validator::types_of( $a );
		$types_b = Schema_Validator::types_of( $b );
		if ( array_intersect( $types_a, $types_b ) ) {
			return true;
		}
		$bases_a = self::singleton_bases( $types_a );
		return (bool) array_intersect( $bases_a, self::singleton_bases( $types_b ) );
	}

	private static function singleton_bases( array $types ): array {
		$out = array();
		foreach ( $types as $type ) {
			$base = Schema_Validator::base_type( $type );
			if ( in_array( $base, Schema_Validator::SINGLETON_BASES, true ) ) {
				$out[] = $base;
			}
		}
		return $out;
	}

	public static function label( array $node ): string {
		$types = Schema_Validator::types_of( $node );
		return $types ? implode( '/', $types ) : '(untyped)';
	}

	

	
	public static function output_mode(): string {
		$primary = Detector::primary();
		if ( 'yoast' === $primary ) {
			return 'yoast';
		}
		if ( 'rankmath' === $primary ) {
			$modules = get_option( 'rank_math_modules', array() );
			if ( is_array( $modules ) && in_array( 'rich-snippet', $modules, true ) ) {
				return 'rankmath';
			}
		}
		return 'script';
	}

	public static function output_report(): array {
		$mode = self::output_mode();
		switch ( $mode ) {
			case 'yoast':
				$text = 'Yoast SEO is the active SEO plugin, so these nodes are added to Yoast\'s own schema graph (wpseo_schema_graph) rather than printed separately. A node whose type Yoast already outputs (for example Article or WebPage, or another type of the same family) is merged into Yoast\'s node: these properties win and Yoast\'s @id is kept. The page still has one graph.';
				break;
			case 'rankmath':
				$text = 'Rank Math is the active SEO plugin with its Schema module on, so these nodes are added to Rank Math\'s graph (rank_math/json_ld) rather than printed separately. A node whose type Rank Math already outputs is merged into Rank Math\'s node: these properties win and Rank Math\'s @id is kept.';
				break;
			default:
				$primary = Detector::primary();
				$text    = 'These nodes are printed in their own <script type="application/ld+json"> tag in the page head.';
				if ( 'rankmath' === $primary ) {
					$text .= ' Rank Math is active but its Schema module is off, so it prints no graph to join.';
				} elseif ( 'none' !== $primary ) {
					$text .= ' ' . Detector::label( $primary ) . ' prints its own schema graph too and offers no supported way to join it, so check the page with wp_validate_schema for duplicate types.';
				}
		}
		return array(
			'mode'        => $mode,
			'explanation' => $text,
		);
	}

	public static function script_tag( array $nodes ): string {
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $nodes ),
		);
		$json = wp_json_encode( $graph, self::SCRIPT_FLAGS );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}
		return '<script type="application/ld+json" class="mordenhost-mcp-schema">' . $json . "</script>\n";
	}

	public static function print_script(): void {
		if ( 'script' !== self::output_mode() || ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 || post_password_required( $post_id ) ) {
			return;
		}
		$nodes = self::stored( $post_id );
		if ( $nodes ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG/AMP/APOS/QUOT inside a fixed script element; see script_tag().
			echo self::script_tag( $nodes );
		}
	}

	public static function filter_yoast_graph( $graph, $context = null ) {
		if ( ! is_array( $graph ) || 'yoast' !== self::output_mode() ) {
			return $graph;
		}
		$post_id = 0;
		if ( is_object( $context ) && isset( $context->indexable ) && is_object( $context->indexable ) ) {
			$indexable = $context->indexable;
			if ( isset( $indexable->object_type ) && 'post' === $indexable->object_type ) {
				$post_id = (int) ( $indexable->object_id ?? 0 );
			}
		} elseif ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		}
		return self::join_graph( $graph, $post_id );
	}

	public static function filter_rank_math( $data, $jsonld = null ) {
		if ( ! is_array( $data ) || 'rankmath' !== self::output_mode() ) {
			return $data;
		}
		$post_id = 0;
		if ( is_object( $jsonld ) && isset( $jsonld->post_id ) && (int) $jsonld->post_id > 0 ) {
			$post_id = (int) $jsonld->post_id;
		} elseif ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		}
		return self::join_graph( $data, $post_id );
	}

	public static function join_graph( array $graph, int $post_id ): array {
		if ( $post_id <= 0 || ( function_exists( 'post_password_required' ) && post_password_required( $post_id ) ) ) {
			return $graph;
		}
		$nodes = self::stored( $post_id );
		if ( ! $nodes ) {
			return $graph;
		}
		$is_list = array() === $graph || array_keys( $graph ) === range( 0, count( $graph ) - 1 );
		foreach ( $nodes as $i => $node ) {
			$node = self::neutralize( $node );
			unset( $node['@context'] );
			$hit = null;
			foreach ( $graph as $key => $piece ) {
				if ( is_array( $piece ) && self::same_slot( $piece, $node, false ) ) {
					$hit = $key;
					break;
				}
			}
			if ( null === $hit ) {
				if ( $is_list ) {
					$graph[] = $node;
				} else {
					$graph[ 'mordenhost_mcp_' . $i ] = $node;
				}
				continue;
			}
			$piece  = $graph[ $hit ];
			$merged = array_merge( $piece, $node );
			if ( isset( $piece['@id'] ) ) {
				$merged['@id'] = $piece['@id'];
			}
			$types = array_values( array_unique( array_merge( Schema_Validator::types_of( $piece ), Schema_Validator::types_of( $node ) ) ) );
			if ( $types ) {
				$merged['@type'] = 1 === count( $types ) ? $types[0] : $types;
			}
			$graph[ $hit ] = $merged;
		}
		return $graph;
	}

	public static function neutralize( $value ) {
		if ( is_string( $value ) ) {
			return (string) preg_replace( '#<(?=/?script|!--)#i', '<\\\\', $value );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ is_string( $key ) ? self::neutralize( $key ) : $key ] = self::neutralize( $item );
			}
			return $out;
		}
		return $value;
	}
}
