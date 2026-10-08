<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShortPixel {

	public static function is_available() {
		return defined( 'SHORTPIXEL_IMAGE_OPTIMISER_VERSION' ) || class_exists( '\WPShortPixel' ) || class_exists( '\ShortPixel\ShortPixelPlugin' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'shortpixel' ),
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
				'name'        => 'shortpixel_get_status',
				'description' => 'Read ShortPixel Image Optimizer status: whether it is active, its version, the average compression percent, and how many images are still pending optimization (when reachable). ShortPixel exposes no stable public total-bytes-saved accessor, so that field is reported null rather than guessed. Read-only diagnostic; runs no optimization and returns no credentials.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'shortpixel_get_settings',
				'description' => 'Read ShortPixel Image Optimizer configuration as flags/numbers only (compression type, backup, resize, EXIF, WebP/AVIF), plus whether an API key is configured (a boolean, never the key value). Read-only diagnostic; returns no credentials and changes no settings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'shortpixel_list_pending',
				'description' => 'List media-library images still pending optimization in ShortPixel\'s queue (status pending): attachment id, status, compression type, original size, and compressed size. Read-only and bounded by limit (default 20, max 100); returns no credentials and runs no optimization.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of pending images to return (default 20, max 100).',
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
			throw new \Exception( 'ShortPixel is not active.' );
		}

		switch ( $name ) {
			case 'shortpixel_get_settings':
				return self::get_settings();
			case 'shortpixel_list_pending':
				return self::list_pending( $args );
		}

		if ( 'shortpixel_get_status' !== $name ) {
			throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
		}

		$pending_count = null;
		$avg_compression = null;
		$stats = self::read_stats_controller();
		if ( is_object( $stats ) ) {
			try {
				if ( method_exists( $stats, 'totalImagesToOptimize' ) ) {
					$pending_count = (int) $stats->totalImagesToOptimize();
				}
				if ( method_exists( $stats, 'getAverageCompression' ) ) {
					$avg_compression = (float) $stats->getAverageCompression();
				}
			} catch ( \Throwable $e ) {
				$pending_count = null;
				$avg_compression = null;
			}
		}

		return array(
			'provider'             => 'shortpixel',
			'configured'           => true,
			'version'              => defined( 'SHORTPIXEL_IMAGE_OPTIMISER_VERSION' ) ? (string) SHORTPIXEL_IMAGE_OPTIMISER_VERSION : null,
			'average_compression'  => $avg_compression,
			'pending_count'        => $pending_count,

			'bytes_saved'          => null,
		);
	}

	
	private static function get_settings() {
		$configured = false;
		if ( function_exists( 'get_option' ) ) {
			$key = get_option( 'spio_key' );
			$configured = is_string( $key ) && '' !== $key;
		}

		$settings = array();
		if ( function_exists( 'wpSPIO' ) ) {
			try {
				$plugin = \wpSPIO();
				if ( is_object( $plugin ) && method_exists( $plugin, 'settings' ) ) {
					$s = $plugin->settings();
					if ( is_array( $s ) ) {
						$settings = $s;
					} elseif ( is_object( $s ) && method_exists( $s, 'getSettings' ) ) {
						$settings = $s->getSettings();
						if ( ! is_array( $settings ) ) {
							$settings = array();
						}
					}
				}
			} catch ( \Throwable $e ) {
				$settings = array();
			}
		}

		foreach ( array( 'apiKey', 'api_key', 'key' ) as $k ) {
			if ( isset( $settings[ $k ] ) && '' !== (string) $settings[ $k ] ) {
				$configured = true;
				unset( $settings[ $k ] );
			}
		}

		return array(
			'provider'   => 'shortpixel',
			'configured' => $configured,
			'settings'   => $settings,
		);
	}

	private static function list_pending( $args ) {
		global $wpdb;

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}

		$list = array();
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array( 'provider' => 'shortpixel', 'count' => 0, 'pending' => $list );
		}

		$table = $wpdb->prefix . 'shortpixel_postmeta';
		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT attach_id, status, compression_type, original_size, compressed_size FROM {$table} WHERE status = 1 ORDER BY attach_id ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		} catch ( \Throwable $e ) {
			$rows = null;
		}

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$list[] = array(
					'attachment_id'    => isset( $row['attach_id'] ) ? (int) $row['attach_id'] : null,
					'status'           => isset( $row['status'] ) ? (int) $row['status'] : null,
					'compression_type' => isset( $row['compression_type'] ) ? (string) $row['compression_type'] : '',
					'original_size'    => isset( $row['original_size'] ) ? (int) $row['original_size'] : null,
					'compressed_size'  => isset( $row['compressed_size'] ) ? (int) $row['compressed_size'] : null,
				);
			}
		}

		return array(
			'provider' => 'shortpixel',
			'count'    => count( $list ),
			'pending'  => $list,
		);
	}

	private static function read_stats_controller() {
		try {
			$fqcn = '\ShortPixel\Controller\StatsController';
			if ( ! class_exists( $fqcn ) ) {
				return null;
			}
			
			if ( method_exists( $fqcn, 'getInstance' ) ) {
				$inst = $fqcn::getInstance();
				return is_object( $inst ) ? $inst : null;
			}
			return null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
