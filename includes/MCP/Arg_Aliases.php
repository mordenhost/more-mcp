<?php

namespace Mordenhost_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Arg_Aliases {

	const MAP = array(
		
		'wp_get_post'                       => array( 'post_id' => 'id' ),
		'wp_get_post_full'                  => array( 'post_id' => 'id' ),
		'wp_update_post'                    => array( 'post_id' => 'id' ),
		'wp_replace_in_post'                => array(
			'post_id' => 'id',
			'search'  => 'find',
		),
		'wp_delete_post'                    => array( 'post_id' => 'id' ),
		'wp_create_preview_link'            => array( 'post_id' => 'id' ),
		'wp_get_page'                       => array( 'post_id' => 'id' ),
		'wp_update_page'                    => array( 'post_id' => 'id' ),
		'wp_replace_in_page'                => array(
			'post_id' => 'id',
			'search'  => 'find',
		),
		'wp_delete_page'                    => array( 'post_id' => 'id' ),
		'wp_get_media_item'                 => array( 'post_id' => 'id' ),
		'wp_update_media'                   => array( 'post_id' => 'id' ),
		'wp_process_image'                  => array( 'post_id' => 'id' ),
		'wp_delete_media'                   => array( 'post_id' => 'id' ),

		'wp_get_post_meta'                  => array( 'id' => 'post_id' ),
		'wp_update_post_meta'               => array( 'id' => 'post_id' ),
		'wp_add_post_meta'                  => array( 'id' => 'post_id' ),
		'wp_delete_post_meta'               => array( 'id' => 'post_id' ),
		'wp_get_post_revisions'             => array( 'id' => 'post_id' ),
		'wp_add_post_terms'                 => array( 'id' => 'post_id' ),
		'wp_get_seo_meta'                   => array( 'id' => 'post_id' ),
		'wp_update_seo_meta'                => array( 'id' => 'post_id' ),
		'wp_set_page_template'              => array( 'id' => 'post_id' ),
		'seo_get_head'                      => array( 'id' => 'post_id' ),
		'seo_get_content_analysis'          => array( 'id' => 'post_id' ),
		'seo_audit_meta_tags'               => array( 'id' => 'post_id' ),

		
		'blocks_get_post_tree'              => array( 'id' => 'post_id' ),
		'blocks_get_block'                  => array( 'id' => 'post_id' ),
		'blocks_insert'                     => array( 'id' => 'post_id' ),
		'blocks_update'                     => array( 'id' => 'post_id' ),
		'blocks_delete'                     => array( 'id' => 'post_id' ),
		'blocks_move'                       => array( 'id' => 'post_id' ),

		'divi_get_page_outline'             => array( 'id' => 'post_id' ),
		'divi_get_module'                   => array( 'id' => 'post_id' ),
		'divi_replace_module'               => array( 'id' => 'post_id' ),
		'divi_insert_module'                => array( 'id' => 'post_id' ),
		'divi_delete_module'                => array( 'id' => 'post_id' ),

		
		'beaver_get_page_outline'           => array( 'id' => 'post_id' ),

		'elementor_get_page_outline'        => array( 'id' => 'post_id' ),
		'elementor_replace_text'            => array(
			'id'     => 'post_id',
			'search' => 'find',
		),
		'elementor_replace_image'           => array( 'id' => 'post_id' ),
		'elementor_set_page_data'           => array( 'id' => 'post_id' ),

		'elementor_sync_library_type'       => array(
			'id'          => 'post_id',
			'template_id' => 'post_id',
		),
		'elementor_set_template_conditions' => array(
			'id'          => 'post_id',
			'template_id' => 'post_id',
		),

		
		'elementor_get_widget_settings'     => array( 'widget_id' => 'element_id' ),
		'elementor_update_widget'           => array( 'widget_id' => 'element_id' ),
		'elementor_delete_widget'           => array( 'widget_id' => 'element_id' ),
		'elementor_move_widget'             => array( 'widget_id' => 'element_id' ),
		'elementor_get_loop_template'       => array( 'widget_id' => 'element_id' ),
	);

	public static function apply( string $tool, array $args ): array {
		if ( ! isset( self::MAP[ $tool ] ) ) {
			return $args;
		}
		foreach ( self::MAP[ $tool ] as $alias => $canonical ) {
			if ( ! array_key_exists( $alias, $args ) ) {
				continue;
			}
			if ( ! isset( $args[ $canonical ] ) ) {
				$args[ $canonical ] = $args[ $alias ];
			}
			unset( $args[ $alias ] );
		}
		return $args;
	}

	public static function accepted( string $tool ): array {
		$accepted = array();
		foreach ( self::MAP[ $tool ] ?? array() as $alias => $canonical ) {
			$accepted[ $canonical ][] = $alias;
		}
		return $accepted;
	}

	public static function explain_error( string $tool, array $args, \Exception $e, callable $tools ): \Exception {
		$class = get_class( $e );
		if ( ! isset( self::MAP[ $tool ] ) || ( \Exception::class !== $class && \RuntimeException::class !== $class ) ) {
			return $e;
		}
		$schema = array();
		try {
			foreach ( $tools() as $definition ) {
				if ( isset( $definition['name'] ) && $tool === $definition['name'] ) {
					$schema = (array) ( $definition['inputSchema'] ?? array() );
					break;
				}
			}
		} catch ( \Throwable $ignored ) {
			$schema = array(); 
		}
		$message = self::explain( $tool, $e->getMessage(), $args, $schema );
		if ( $message === $e->getMessage() ) {
			return $e;
		}

		return new $class( $message, (int) $e->getCode(), $e );
	}

	public static function explain( string $tool, string $message, array $args, array $schema = array() ): string {
		$accepted = self::accepted( $tool );
		if ( ! $accepted ) {
			return $message;
		}
		$props = $schema['properties'] ?? array();
		$props = is_object( $props ) ? get_object_vars( $props ) : (array) $props;
		$required = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

		$notes    = array(); 
		$appended = array();
		foreach ( $accepted as $canonical => $aliases ) {
			$type   = is_array( $props[ $canonical ] ?? null ) ? ( $props[ $canonical ]['type'] ?? null ) : null;
			$reason = self::unusable( $args, $canonical, $type );
			if ( null === $reason ) {
				continue;
			}
			$aliases = array_values( array_filter( $aliases, static fn( $alias ) => null === self::token_at( $message, $alias ) ) );
			if ( ! $aliases ) {
				continue;
			}
			$at = self::token_at( $message, $canonical );
			if ( null !== $at ) {
				$start = self::sentence_start( $message, $at );
				$opens = '' === trim( substr( $message, $start, $at - $start ) );
				$notes[ self::sentence_end( $message, $at ) ][ $canonical ] = array( $aliases, $opens );
			} elseif ( in_array( $canonical, $required, true ) ) {
				$appended[] = $canonical . ' ' . $reason . ' (also accepted: ' . implode( ', ', $aliases ) . ').';
			}
		}

		krsort( $notes );
		foreach ( $notes as $end => $keys ) {
			$first = reset( $keys );
			if ( 1 === count( $keys ) && $first[1] ) {
				$list = implode( ', ', $first[0] );
			} else {
				$parts = array();
				foreach ( $keys as $canonical => $note ) {
					$parts[] = implode( ', ', $note[0] ) . ' for ' . $canonical;
				}
				$list = implode( '; ', $parts );
			}
			$message = substr( $message, 0, $end ) . ' (also accepted: ' . $list . ')' . substr( $message, $end );
		}

		if ( $appended ) {
			$message = rtrim( $message );
			if ( '' !== $message && ! preg_match( '/[.!?]$/', $message ) ) {
				$message .= '.';
			}
			$message = ltrim( $message . ' ' . implode( ' ', $appended ) );
		}
		return $message;
	}

	private static function unusable( array $args, string $key, $type ): ?string {
		$value = $args[ $key ] ?? null;
		if ( null === $value || '' === $value || array() === $value ) {
			return 'is required';
		}
		if ( 'integer' === $type && ( is_bool( $value ) || ! is_numeric( $value ) || (int) $value <= 0 ) ) {
			return 'must be a positive integer';
		}
		return null;
	}

	private static function token_at( string $message, string $name ): ?int {
		if ( preg_match( '/(?<![A-Za-z0-9_$])' . preg_quote( $name, '/' ) . '(?![A-Za-z0-9_])/', $message, $m, PREG_OFFSET_CAPTURE ) ) {
			return (int) $m[0][1];
		}
		return null;
	}

	private static function sentence_start( string $message, int $at ): int {
		$start = 0;
		if ( preg_match_all( '/[.!?]\s+|\R/', substr( $message, 0, $at ), $m, PREG_OFFSET_CAPTURE ) ) {
			$last  = end( $m[0] );
			$start = $last[1] + strlen( $last[0] );
		}
		return $start;
	}

	private static function sentence_end( string $message, int $at ): int {
		if ( preg_match( '/[.!?](?=\s|$)|\R/', $message, $m, PREG_OFFSET_CAPTURE, $at ) ) {
			return (int) $m[0][1];
		}
		return strlen( rtrim( $message ) );
	}
}
