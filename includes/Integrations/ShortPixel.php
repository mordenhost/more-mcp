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
		);
	}

	public static function execute_tool( $name, $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use image-optimization tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'ShortPixel is not active.' );
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
