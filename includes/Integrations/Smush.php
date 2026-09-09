<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smush {

	public static function is_available() {
		return defined( 'WP_SMUSH_VERSION' ) || class_exists( '\WP_Smush' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'smush' ),
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
				'name'        => 'smush_get_status',
				'description' => 'Read WP Smush optimization status: whether it is active, its version, how many images have been smushed, the total size before and after, bytes saved, and savings percent. Read-only diagnostic; runs no optimization and returns no credentials.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'smush_get_settings',
				'description' => 'Read WP Smush configuration as flags/numbers only (auto-smush, backup original, EXIF handling, lazy load, resize), plus whether an API key is configured (a boolean, never the key value). Read-only diagnostic; returns no credentials and changes no settings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'smush_list_unoptimized',
				'description' => 'List media-library attachments WP Smush has not yet smushed (those missing smush metadata): id, title, mime type, file size, and status. Read-only and bounded by limit (default 20, max 100); returns no credentials and runs no optimization.',
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
			throw new \Exception( 'WP Smush is not active.' );
		}

		switch ( $name ) {
			case 'smush_get_settings':
				return self::get_settings();
			case 'smush_list_unoptimized':
				return self::list_unoptimized( $args );
		}

		if ( 'smush_get_status' !== $name ) {
			throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
		}

		$stats = self::read_global_stats();

		$size_before = isset( $stats['size_before'] ) ? (int) $stats['size_before'] : null;
		$size_after  = isset( $stats['size_after'] ) ? (int) $stats['size_after'] : null;
		$bytes_saved = isset( $stats['savings_bytes'] ) ? (int) $stats['savings_bytes'] : null;
		if ( null === $bytes_saved && null !== $size_before && null !== $size_after ) {
			$bytes_saved = max( 0, $size_before - $size_after );
		}
		$percent = null;
		if ( isset( $stats['savings_percent'] ) && is_numeric( $stats['savings_percent'] ) ) {
			$percent = (float) $stats['savings_percent'];
		} elseif ( null !== $bytes_saved && null !== $size_before && $size_before > 0 ) {
			$percent = round( ( $bytes_saved / $size_before ) * 100, 2 );
		}

		return array(
			'provider'        => 'smush',
			'configured'      => true,
			'version'         => defined( 'WP_SMUSH_VERSION' ) ? (string) WP_SMUSH_VERSION : null,
			'optimized_count' => isset( $stats['count_images'] ) ? (int) $stats['count_images'] : ( isset( $stats['count_smushed'] ) ? (int) $stats['count_smushed'] : null ),
			'original_bytes'  => $size_before,
			'optimized_bytes' => $size_after,
			'bytes_saved'     => $bytes_saved,
			'savings_percent' => $percent,
		);
	}

	
	private static function get_settings() {
		$settings = get_option( 'wp-smush-settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		

		$configured = false;
		foreach ( array( 'api_key', 'key' ) as $k ) {
			if ( isset( $settings[ $k ] ) && '' !== (string) $settings[ $k ] ) {
				$configured = true;
				unset( $settings[ $k ] );
			}
		}

		return array(
			'provider'   => 'smush',
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
					'posts_per_page' => $limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}

		$list = array();
		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $post ) {
				$status = 'unoptimized';
				if ( function_exists( 'get_post_meta' ) && get_post_meta( $post->ID, 'wp-smush-smush-data', true ) ) {

					$status = 'smushed';
				}
				$list[] = array(
					'id'       => (int) $post->ID,
					'title'    => (string) $post->post_title,
					'mime'     => (string) $post->post_mime_type,
					'filesize' => self::attachment_filesize( $post ),
					'status'   => $status,
				);
			}
		}

		return array(
			'provider'    => 'smush',
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

	private static function read_global_stats() {
		try {
			if ( ! class_exists( '\WP_Smush' ) || ! method_exists( '\WP_Smush', 'get_instance' ) ) {
				return array();
			}
			$smush = \WP_Smush::get_instance();
			if ( ! is_object( $smush ) || ! method_exists( $smush, 'core' ) ) {
				return array();
			}
			$core = $smush->core();
			if ( ! is_object( $core ) || empty( $core->stats ) || ! is_object( $core->stats ) ) {
				return array();
			}
			if ( ! method_exists( $core->stats, 'get_global_stats' ) ) {
				return array();
			}
			$stats = $core->stats->get_global_stats();
			return is_array( $stats ) ? $stats : array();
		} catch ( \Throwable $e ) {
			return array();
		}
	}
}
