<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Imagify {

	public static function is_available() {
		return function_exists( 'get_imagify_option' ) || defined( 'IMAGIFY_VERSION' ) || defined( 'IMAGIFY_API_KEY' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'imagify' ),
			'capabilities' => array( 'images' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'imagify_get_status',
				'description' => 'Read Imagify image-optimization status: whether an API key is configured (never the key itself), the total number of media attachments in scope, how many have been optimized, how many are still unoptimized, how many errored, and the total size saved with the savings percentage. Read-only diagnostic; returns no credentials and runs no optimization. Imagify\'s own optimize actions, if present, are exposed separately as discovered abilities.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'imagify_get_settings',
				'description' => 'Read Imagify configuration as flags and numbers only: optimization level, lossless, auto-optimize, backup, resize-larger, display-WebP, convert-to-AVIF, and convert-to-WebP, plus whether an API key is configured (a boolean, never the key value). Read-only diagnostic; returns no credentials and changes no settings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'imagify_list_unoptimized',
				'description' => 'List media-library attachments Imagify has not yet optimized (or that errored): id, title, mime type, file size, and status. Read-only and bounded by limit (default 20, max 100); returns no credentials and runs no optimization.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of attachments to return (default 20, max 100).',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use image-optimization tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Imagify is not active.' );
		}

		switch ( $name ) {
			case 'imagify_get_settings':
				return self::get_settings();
			case 'imagify_list_unoptimized':
				return self::list_unoptimized( $args );
		}

		if ( 'imagify_get_status' !== $name ) {
			throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
		}

		
		$configured = false;
		if ( function_exists( 'get_imagify_option' ) ) {
			$configured = ! empty( get_imagify_option( 'api_key' ) );
		}
		if ( ! $configured && defined( 'IMAGIFY_API_KEY' ) && '' !== (string) constant( 'IMAGIFY_API_KEY' ) ) {
			$configured = true;
		}

		$total       = function_exists( 'imagify_count_attachments' ) ? (int) imagify_count_attachments() : null;
		$optimized   = function_exists( 'imagify_count_optimized_attachments' ) ? (int) imagify_count_optimized_attachments() : null;
		$unoptimized = function_exists( 'imagify_count_unoptimized_attachments' ) ? (int) imagify_count_unoptimized_attachments() : null;
		$errors      = function_exists( 'imagify_count_error_attachments' ) ? (int) imagify_count_error_attachments() : null;

		$original_size  = null;
		$optimized_size = null;
		$percent        = null;
		if ( function_exists( 'imagify_count_saving_data' ) ) {
			$original_size  = (int) imagify_count_saving_data( 'original_size' );
			$optimized_size = (int) imagify_count_saving_data( 'optimized_size' );
			$percent        = (float) imagify_count_saving_data( 'percent' );
		}

		return array(
			'provider'          => 'imagify',
			'configured'        => $configured,
			'version'           => defined( 'IMAGIFY_VERSION' ) ? (string) IMAGIFY_VERSION : null,
			'total_attachments' => $total,
			'optimized_count'   => $optimized,
			'unoptimized_count' => $unoptimized,
			'error_count'       => $errors,
			'original_bytes'    => $original_size,
			'optimized_bytes'   => $optimized_size,
			'savings_percent'   => $percent,
		);
	}

	private static function get_settings() {
		$configured = false;
		if ( function_exists( 'get_imagify_option' ) ) {
			$configured = ! empty( get_imagify_option( 'api_key' ) );
		}
		if ( ! $configured && defined( 'IMAGIFY_API_KEY' ) && '' !== (string) constant( 'IMAGIFY_API_KEY' ) ) {
			$configured = true;
		}

		$keys = array(
			'optimization_level',
			'lossless',
			'auto_optimize',
			'backup',
			'resize_larger',
			'display_webp',
			'convert_to_avif',
			'convert_to_webp',
		);
		$settings = array();
		foreach ( $keys as $key ) {
			$settings[ $key ] = function_exists( 'get_imagify_option' ) ? get_imagify_option( $key ) : null;
		}

		return array(
			'provider'   => 'imagify',
			'configured' => $configured,
			'settings'   => $settings,
		);
	}

	private static function list_unoptimized( $args ) {
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}

		$attachments = array();
		if ( function_exists( 'get_posts' ) ) {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'numberposts'    => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'posts_per_page' => $limit,
				)
			);
		}

		$list = array();
		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $post ) {
				$status = function_exists( 'get_post_meta' ) ? get_post_meta( $post->ID, '_imagify_status', true ) : null;
				if ( ! is_string( $status ) || '' === $status ) {
					$status = 'unoptimized';
				}
				$list[] = array(
					'id'       => (int) $post->ID,
					'title'    => (string) $post->post_title,
					'mime'     => (string) $post->post_mime_type,
					'filesize' => function_exists( 'filesize' ) && ! empty( $post->post_mime_type ) ? self::attachment_filesize( $post ) : null,
					'status'   => (string) $status,
				);
			}
		}

		return array(
			'provider'    => 'imagify',
			'count'       => count( $list ),
			'attachments' => $list,
		);
	}

	private static function attachment_filesize( $post ) {
		try {
			if ( function_exists( 'wp_get_attachment_metadata' ) ) {
				$meta = wp_get_attachment_metadata( $post->ID );
				if ( is_array( $meta ) && isset( $meta['filesize'] ) ) {
					return (int) $meta['filesize'];
				}
			}
		} catch ( \Throwable $e ) {
			return null;
		}
		return null;
	}
}
