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
				'description' => 'Read Imagify image-optimization status: whether an API key is configured (never the key itself), how many media attachments have been optimized, how many errored, and the total size saved with the savings percentage. Read-only diagnostic; returns no credentials and runs no optimization. Imagify\'s own optimize actions, if present, are exposed separately as discovered abilities.',
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
			throw new \Exception( 'Imagify is not active.' );
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

		$optimized = function_exists( 'imagify_count_optimized_attachments' ) ? (int) imagify_count_optimized_attachments() : null;
		$errors    = function_exists( 'imagify_count_error_attachments' ) ? (int) imagify_count_error_attachments() : null;

		$original_size  = null;
		$optimized_size = null;
		$percent        = null;
		if ( function_exists( 'imagify_count_saving_data' ) ) {
			$original_size  = (int) imagify_count_saving_data( 'original_size' );
			$optimized_size = (int) imagify_count_saving_data( 'optimized_size' );
			$percent        = (float) imagify_count_saving_data( 'percent' );
		}

		return array(
			'provider'         => 'imagify',
			'configured'       => $configured,
			'version'          => defined( 'IMAGIFY_VERSION' ) ? (string) IMAGIFY_VERSION : null,
			'optimized_count'  => $optimized,
			'error_count'      => $errors,
			'original_bytes'   => $original_size,
			'optimized_bytes'  => $optimized_size,
			'savings_percent'  => $percent,
		);
	}
}
