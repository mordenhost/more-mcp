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
			array(
				'name'        => 'ai1wm_get_status',
				'description' => 'All-in-One WP Migration configuration status: plugin version, which premium extensions are active (name and version each — never a purchase key), and the record type of the most recent export/import status (e.g. done, error, progress). The status message text is never returned; it can carry archive filenames and server paths. Read-only.',
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
		if ( 'ai1wm_get_status' === $name ) {
			return self::get_status();
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

	private static function get_status() {
		$extensions = array();
		if ( class_exists( '\Ai1wm_Extensions' ) && method_exists( '\Ai1wm_Extensions', 'get' ) ) {
			$active = \Ai1wm_Extensions::get();
			if ( is_array( $active ) ) {
				foreach ( $active as $extension ) {
					if ( ! is_array( $extension ) ) {
						continue;
					}
					$extensions[] = array(

						'title'    => isset( $extension['title'] ) ? (string) $extension['title'] : '',
						'version'  => isset( $extension['version'] ) ? (string) $extension['version'] : '',
						'requires' => isset( $extension['requires'] ) ? (string) $extension['requires'] : '',
					);
				}
			}
		}

		
		$last_status_type = null;
		if ( defined( 'AI1WM_STATUS' ) ) {
			$record = get_option( AI1WM_STATUS, null );
			if ( is_array( $record ) && isset( $record['type'] ) && is_scalar( $record['type'] ) ) {
				$last_status_type = (string) $record['type'];
			}
		}

		return array(
			'provider'         => 'all-in-one-wp-migration',
			'version'          => defined( 'AI1WM_VERSION' ) ? AI1WM_VERSION : null,
			'extensions'       => $extensions,
			'last_status_type' => $last_status_type,
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
