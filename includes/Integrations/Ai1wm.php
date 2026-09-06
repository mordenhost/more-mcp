<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai1wm {

	public static function is_available() {
		return class_exists( '\Ai1wm_Backups' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'all-in-one-wp-migration' ),
			'capabilities' => array( 'backup' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'ai1wm_get_backups',
				'description' => 'List All-in-One WP Migration backup archives: filename, human-readable label when one is assigned, size in bytes, and creation time (unix + ISO 8601), newest first, plus the total count and total size. This is the read that answers "is there a recoverable backup?" before risky maintenance. Reads through the plugin\'s own Ai1wm_Backups::get_files() listing model — no export/import path is touched. Never returns the backup directory path or any download URL. Read-only; no start, restore, or delete.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use backup tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'All-in-One WP Migration is not active.' );
		}
		if ( 'ai1wm_get_backups' === $name ) {
			return self::get_backups();
		}
		throw new \Exception( 'Unknown backup tool: ' . esc_html( $name ) );
	}

	private static function get_backups() {
		if ( ! method_exists( '\Ai1wm_Backups', 'get_files' ) ) {
			throw new \Exception( 'This All-in-One WP Migration version does not expose the backup listing API.' );
		}

		$files  = (array) \Ai1wm_Backups::get_files();
		$labels = self::labels();

		$backups    = array();
		$total_size = 0;
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$filename = isset( $file['filename'] ) ? (string) $file['filename'] : '';
			if ( '' === $filename ) {
				continue;
			}
			$size = isset( $file['size'] ) && is_numeric( $file['size'] ) ? (int) $file['size'] : null;
			if ( null !== $size ) {
				$total_size += $size;
			}
			$mtime = isset( $file['mtime'] ) && is_numeric( $file['mtime'] ) ? (int) $file['mtime'] : null;

			$backups[] = array(
				'filename'    => $filename,

				'label'       => isset( $labels[ $filename ] ) ? (string) $labels[ $filename ] : null,
				'size'        => $size,
				'created_at'  => $mtime,
				'created_iso' => $mtime ? gmdate( 'c', $mtime ) : null,
			);
		}

		return array(
			'provider'   => 'all-in-one-wp-migration',
			'total'      => count( $backups ),
			'total_size' => $total_size,
			'backups'    => $backups,
		);
	}

	private static function labels() {
		if ( ! defined( 'AI1WM_BACKUPS_LABELS' ) ) {
			return array();
		}
		$labels = get_option( AI1WM_BACKUPS_LABELS, array() );
		return is_array( $labels ) ? $labels : array();
	}
}
