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
		);
	}

	public static function execute_tool( $name, $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use image-optimization tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'WP Smush is not active.' );
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
