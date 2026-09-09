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
			array(
				'name'        => 'ewww_get_settings',
				'description' => 'Read EWWW Image Optimizer configuration as flags/numbers only: backup files, lazy load, skip size, and skip PNG size, plus whether a cloud key is configured (a boolean, never the key value). Read-only diagnostic; returns no credentials and changes no settings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'ewww_list_pending',
				'description' => 'List media-library images still pending optimization in EWWW\'s queue: id, attachment id, path, image size, and original size. Read-only and bounded by limit (default 20, max 100); returns no credentials and runs no optimization.',
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
			throw new \Exception( 'EWWW Image Optimizer is not active.' );
		}

		switch ( $name ) {
			case 'ewww_get_settings':
				return self::get_settings();
			case 'ewww_list_pending':
				return self::list_pending( $args );
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

	private static function get_settings() {
		$keys = array(
			'backup_files'  => 'ewww_image_optimizer_backup_files',
			'lazy_load'     => 'ewww_image_optimizer_lazy_load',
			'skip_size'     => 'ewww_image_optimizer_skip_size',
			'skip_png_size' => 'ewww_image_optimizer_skip_png_size',
		);
		$settings = array();
		foreach ( $keys as $label => $option ) {
			$settings[ $label ] = function_exists( 'ewww_image_optimizer_get_option' )
				? ewww_image_optimizer_get_option( $option )
				: null;
		}

		$configured = false;
		if ( function_exists( 'ewww_image_optimizer_cloud_key' ) ) {
			try {
				$configured = '' !== (string) ewww_image_optimizer_cloud_key();
			} catch ( \Throwable $e ) {
				$configured = false;
			}
		}

		return array(
			'provider'   => 'ewww',
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
			return array( 'provider' => 'ewww', 'count' => 0, 'pending' => $list );
		}

		$table = $wpdb->prefix . 'ewwwio_images';
		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, attachment_id, path, image_size, orig_size FROM {$table} WHERE pending = 1 ORDER BY id ASC LIMIT %d",
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
					'id'            => isset( $row['id'] ) ? (int) $row['id'] : null,
					'attachment_id' => isset( $row['attachment_id'] ) ? (int) $row['attachment_id'] : null,
					'path'          => isset( $row['path'] ) ? (string) $row['path'] : '',
					'image_size'    => isset( $row['image_size'] ) ? (int) $row['image_size'] : null,
					'orig_size'     => isset( $row['orig_size'] ) ? (int) $row['orig_size'] : null,
				);
			}
		}

		return array(
			'provider' => 'ewww',
			'count'    => count( $list ),
			'pending'  => $list,
		);
	}
}
