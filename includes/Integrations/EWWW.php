<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EWWW {

	public static function is_available() {
		return defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' ) || function_exists( 'ewww_image_optimizer_savings' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'ewww' ),
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
				'name'        => 'ewww_get_status',
				'description' => 'Read EWWW Image Optimizer status: whether it is active, its version, the total original vs optimized bytes across the media library, and the derived bytes saved and savings percent. Read-only diagnostic; runs no optimization and returns no credentials.',
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
			throw new \Exception( 'EWWW Image Optimizer is not active.' );
		}
		if ( 'ewww_get_status' !== $name ) {
			throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
		}

		$original_bytes  = null;
		$optimized_bytes = null;
		$bytes_saved     = null;
		$savings_percent = null;

		if ( function_exists( 'ewww_image_optimizer_savings' ) ) {
			try {
				$savings = ewww_image_optimizer_savings();
				
				if ( is_array( $savings ) && isset( $savings[0], $savings[1] ) ) {
					$optimized_bytes = (int) $savings[0];
					$original_bytes  = (int) $savings[1];
					$bytes_saved     = max( 0, $original_bytes - $optimized_bytes );
					$savings_percent = $original_bytes > 0
						? round( ( $bytes_saved / $original_bytes ) * 100, 2 )
						: 0.0;
				}
			} catch ( \Throwable $e ) {

				$original_bytes = $optimized_bytes = $bytes_saved = $savings_percent = null;
			}
		}

		return array(
			'provider'        => 'ewww',
			'configured'      => true,
			'version'         => defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' ) ? (string) EWWW_IMAGE_OPTIMIZER_VERSION : null,

			'optimized_count' => null,
			'original_bytes'  => $original_bytes,
			'optimized_bytes' => $optimized_bytes,
			'bytes_saved'     => $bytes_saved,
			'savings_percent' => $savings_percent,
		);
	}
}
