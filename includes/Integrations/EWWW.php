<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EWWW {

	const MAX_ERRORS = 5;

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
				'description' => 'List media-library images still pending optimization in EWWW\'s queue: id, attachment id, path, image size, and original size. Read-only and bounded by limit (default 20, max 100); returns no credentials and runs no optimization. Optimize one with ewww_optimize_attachment.',
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
			array(
				'name'        => 'ewww_get_attachment_status',
				'description' => 'Read EWWW Image Optimizer\'s state for one media attachment: optimized, unoptimized or pending (queued for the background optimizer), each tracked size with bytes before and after, total savings, the compression level used, the backup mode (local, cloud or none) and whether a backup of the original is available. Read-only.',
				'inputSchema' => Optimizer_Target::schema(),
			),
			array(
				'name'        => 'ewww_optimize_attachment',
				'description' => 'Optimize one media attachment with EWWW Image Optimizer, exactly as the media library\'s "Optimize now" button does: queued for EWWW\'s background optimizer when that is on, otherwise run now, rewriting the image files. Call it first without confirm: that returns a preview (current state, whether a backup will be kept, whether it would queue or run now) and changes nothing. Call again with confirm=true to run it. With EWWW backups off the change cannot be undone. Refuses an image already queued.',
				'inputSchema' => Optimizer_Target::schema( true ),
			),
			array(
				'name'        => 'ewww_restore_attachment',
				'description' => 'Restore one optimized media attachment to the original EWWW backed up (local or cloud backup), exactly as the media library\'s "Restore original" button does, for every size EWWW has a backup of. Refuses when EWWW backups are off, the image was never optimized, or no backup is available (cloud backups expire after 30 days).',
				'inputSchema' => Optimizer_Target::schema(),
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
		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'ewww_get_status':
				return self::get_status();
			case 'ewww_get_settings':
				return self::get_settings();
			case 'ewww_list_pending':
				return self::list_pending( $args );
			case 'ewww_get_attachment_status':
				return self::attachment_status( $args );
			case 'ewww_optimize_attachment':
				return self::optimize_attachment( $args );
			case 'ewww_restore_attachment':
				return self::restore_attachment( $args );
		}

		throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
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

				$original_bytes  = null;
				$optimized_bytes = null;
				$bytes_saved     = null;
				$savings_percent = null;
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
		$keys     = array(
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
		$limit = max( 1, min( 100, $limit ) );

		$list = array();
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array(
				'provider' => 'ewww',
				'count'    => 0,
				'pending'  => $list,
			);
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

	private static function attachment_status( array $args ) {
		$post = Optimizer_Target::attachment( $args, false );
		$id   = (int) $post->ID;
		return array_merge(
			array( 'provider' => 'ewww' ),
			Optimizer_Target::summary( $post ),
			self::describe( $id, self::attachment_rows( $id ) )
		);
	}

	private static function optimize_attachment( array $args ) {
		$post = Optimizer_Target::attachment( $args, true );
		$id   = (int) $post->ID;
		$mime = (string) ( $post->post_mime_type ?? '' );
		if ( 0 !== strpos( $mime, 'image/' ) && 'application/pdf' !== $mime ) {
			throw new \Exception( esc_html( 'EWWW Image Optimizer only optimizes images and PDFs; attachment ' . $id . ' is ' . ( '' === $mime ? 'of unknown type' : $mime ) . '.' ) );
		}
		$meta = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $id ) : false;
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			throw new \Exception( esc_html( 'Attachment ' . $id . ' has no file metadata, so EWWW has nothing to optimize. Regenerate its thumbnails first.' ) );
		}
		foreach ( array( 'ewwwio', 'ewww_image_optimizer_test_background_opt', 'ewww_image_optimizer_add_attachment_to_queue', 'ewww_image_optimizer_resize_from_meta_data' ) as $fn ) {
			if ( ! function_exists( $fn ) ) {
				throw new \Exception( 'This EWWW Image Optimizer version does not offer the single-image optimizer this tool uses.' );
			}
		}

		$now = self::describe( $id, self::attachment_rows( $id ) );
		if ( 'pending' === $now['state'] ) {
			throw new \Exception( esc_html( 'Attachment ' . $id . ' is already queued for EWWW\'s background optimizer. Check it again with ewww_get_attachment_status once that finishes.' ) );
		}

		$would_run  = 'optimized' !== $now['state'];
		$background = function_exists( 'ewww_image_optimizer_background_mode_enabled' ) ? (bool) ewww_image_optimizer_background_mode_enabled() : null;
		$base       = array_merge( array( 'provider' => 'ewww' ), Optimizer_Target::summary( $post ) );

		if ( ! Optimizer_Target::confirmed( $args ) ) {
			if ( ! $would_run ) {
				$message = 'Preview only. EWWW has already optimized this image, so a confirmed call would change nothing.';
			} elseif ( 'none' === $now['backup_mode'] ) {
				$message = 'Preview only. To optimize this image, call again with confirm=true. EWWW backups are off, so the original files will be overwritten and this cannot be undone.';
			} else {
				$message = 'Preview only. To optimize this image, call again with confirm=true. EWWW keeps a ' . $now['backup_mode'] . ' backup of the original' . ( 'cloud' === $now['backup_mode'] ? ' for 30 days' : '' ) . ', so ewww_restore_attachment can undo it.';
			}
			return array_merge(
				$base,
				array(
					'preview'          => true,
					'would_run'        => $would_run,
					'will_keep_backup' => 'none' !== $now['backup_mode'],
					'runs_in'          => null === $background ? null : ( $background ? 'background' : 'this_request' ),
					'current'          => $now,
					'message'          => $message,
				)
			);
		}

		if ( ! $would_run ) {
			return array_merge(
				$base,
				array(
					'ran'     => false,
					'state'   => $now['state'],
					'message' => 'EWWW has already optimized this image; nothing was run.',
				)
			);
		}

		$queued = self::run_manual_optimize( $id, $meta );
		$quota  = self::quota_problem();

		if ( $queued ) {
			return array_merge(
				$base,
				array(
					'ran'         => true,
					'queued'      => true,
					'quota'       => $quota,
					'verified'    => false,
					'write_state' => Write_Verify::UNKNOWN,
					'verify_note' => 'EWWW queued this image for its background optimizer; no file has been rewritten yet. Check it with ewww_get_attachment_status in a minute.',
				)
			);
		}

		$after = self::describe( $id, self::attachment_rows( $id ) );
		if ( null === $after['sizes'] ) {
			$state = Write_Verify::UNKNOWN;
		} else {
			$state = Write_Verify::state_of_presence( $after['optimized_sizes'] > 0 );
		}

		return array_merge(
			$base,
			array(
				'ran'    => true,
				'queued' => false,
				'quota'  => $quota,
				'after'  => $after,
			),
			Write_Verify::report( $state, 'EWWW optimization of attachment ' . $id, 'EWWW recorded no optimized size. Check that its optimization tools or API key work (ewww_get_settings).' )
		);
	}

	private static function run_manual_optimize( $id, array $meta ) {
		global $ewww_convert;
		$plugin       = ewwwio();
		$has_flags    = is_object( $plugin );
		$prev_defer   = $has_flags && isset( $plugin->defer ) ? $plugin->defer : null;
		$prev_force   = $has_flags && isset( $plugin->force ) ? $plugin->force : null;
		$prev_convert = $ewww_convert;
		$filtered     = function_exists( 'add_filter' );

		$ewww_convert = false;
		if ( $has_flags ) {
			$plugin->force = false;
			$plugin->defer = true;
		}
		if ( $filtered ) {
			add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
		}
		try {
			if ( ewww_image_optimizer_test_background_opt() ) {
				ewww_image_optimizer_add_attachment_to_queue( $id, false, false, false );
				return true;
			}
			$new_meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id );
			if ( is_array( $new_meta ) && function_exists( 'wp_update_attachment_metadata' ) ) {
				wp_update_attachment_metadata( $id, $new_meta );
			}
			return false;
		} finally {
			if ( $filtered && function_exists( 'remove_filter' ) ) {
				remove_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
			}
			if ( $has_flags ) {
				$plugin->defer = $prev_defer;
				$plugin->force = $prev_force;
			}
			$ewww_convert = $prev_convert;
		}
	}

	private static function restore_attachment( array $args ) {
		global $eio_backup;
		$post = Optimizer_Target::attachment( $args, true );
		$id   = (int) $post->ID;
		$now  = self::describe( $id, self::attachment_rows( $id ) );

		if ( null === $now['sizes'] ) {
			throw new \Exception( 'EWWW\'s image table could not be read, so nothing can be restored.' );
		}
		if ( 'none' === $now['backup_mode'] ) {
			throw new \Exception( esc_html( 'EWWW backups are off, so attachment ' . $id . ' cannot be restored.' ) );
		}
		if ( 0 === $now['optimized_sizes'] ) {
			throw new \Exception( esc_html( 'Attachment ' . $id . ' has not been optimized by EWWW, so there is nothing to restore.' ) );
		}
		if ( 'pending' === $now['state'] ) {
			throw new \Exception( esc_html( 'Attachment ' . $id . ' is queued for EWWW\'s background optimizer. Try again once that finishes.' ) );
		}
		if ( true !== $now['has_backup'] ) {
			throw new \Exception( esc_html( 'No EWWW backup of the original is available for attachment ' . $id . ( 'cloud' === $now['backup_mode'] ? ' (cloud backups expire after 30 days)' : '' ) . '.' ) );
		}
		if ( ! is_object( $eio_backup ) || ! method_exists( $eio_backup, 'restore_backup_from_meta_data' ) ) {
			throw new \Exception( 'This EWWW Image Optimizer version does not offer the restore call this tool uses.' );
		}

		$before_ids = self::optimized_row_ids( self::attachment_rows( $id ) );
		$meta       = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $id ) : array();
		$new_meta   = $eio_backup->restore_backup_from_meta_data( $id, 'media', is_array( $meta ) ? $meta : array() );
		if ( is_array( $new_meta ) && function_exists( 'wp_update_attachment_metadata' ) ) {
			wp_update_attachment_metadata( $id, $new_meta );
		}
		$errors = method_exists( $eio_backup, 'get_error' ) ? self::clean_messages( array( $eio_backup->get_error() ) ) : array();

		$after_rows = self::attachment_rows( $id );
		if ( null === $after_rows ) {
			$state    = Write_Verify::UNKNOWN;
			$restored = array();
			$left     = array();
		} else {
			$still    = self::optimized_row_ids( $after_rows );
			$restored = array_values( array_diff( $before_ids, $still ) );
			$left     = array_values( array_intersect( $before_ids, $still ) );
			if ( ! $restored ) {

				$reason = $errors ? implode( ' ', $errors ) : 'EWWW returned no reason.';
				throw new \Exception( esc_html( 'EWWW could not restore attachment ' . $id . ': ' . $reason ) );
			}
			$state = $left ? Write_Verify::TRANSFORMED : Write_Verify::LANDED;
		}

		return array_merge(
			array( 'provider' => 'ewww' ),
			Optimizer_Target::summary( $post ),
			array(
				'restored_sizes'     => self::size_names( $after_rows, $restored ),
				'not_restored_sizes' => self::size_names( $after_rows, $left ),
				'errors'             => $errors,
				'after'              => self::describe( $id, $after_rows ),
			),
			Write_Verify::report( $state, 'restored original of attachment ' . $id, 'Some sizes still read as optimized; their backups may be missing.' )
		);
	}

	private static function attachment_rows( $id ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}
		$table = $wpdb->prefix . 'ewwwio_images';
		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, resize, path, image_size, orig_size, level, pending, backup, updated FROM {$table} WHERE attachment_id = %d AND gallery = %s ORDER BY orig_size DESC",
					(int) $id,
					'media'
				),
				ARRAY_A
			);
		} catch ( \Throwable $e ) {
			return null;
		}
		return is_array( $rows ) ? $rows : null;
	}

	private static function describe( $id, $rows ) {
		$mode   = self::backup_mode();
		$queued = null;
		if ( function_exists( 'ewww_image_optimizer_image_is_pending' ) ) {
			try {
				$queued = (bool) ewww_image_optimizer_image_is_pending( (int) $id, 'media-async' );
			} catch ( \Throwable $e ) {
				$queued = null;
			}
		}

		if ( null === $rows ) {
			return array(
				'state'           => 'unknown',
				'sizes'           => null,
				'optimized_sizes' => null,
				'pending_sizes'   => null,
				'queued'          => $queued,
				'level'           => null,
				'original_bytes'  => null,
				'optimized_bytes' => null,
				'bytes_saved'     => null,
				'savings_percent' => null,
				'backup_mode'     => $mode,
				'has_backup'      => null,
			);
		}

		$sizes     = array();
		$optimized = 0;
		$pending   = 0;
		$before    = 0;
		$after     = 0;
		$level     = null;
		$full      = null;
		foreach ( $rows as $row ) {
			$image_size = isset( $row['image_size'] ) ? (int) $row['image_size'] : 0;
			$orig_size  = isset( $row['orig_size'] ) ? (int) $row['orig_size'] : 0;
			$is_pending = ! empty( $row['pending'] );
			$is_done    = 0 !== $image_size;
			if ( $is_done ) {
				++$optimized;
				$before += $orig_size;
				$after  += $image_size;
			}
			if ( $is_pending ) {
				++$pending;
			}
			$size_name = isset( $row['resize'] ) ? (string) $row['resize'] : '';
			if ( 'full' === $size_name ) {
				$full  = $row;
				$level = isset( $row['level'] ) ? (int) $row['level'] : null;
			}
			$sizes[] = array(
				'size'            => $size_name,
				'optimized'       => $is_done,
				'pending'         => $is_pending,
				'original_bytes'  => $orig_size,
				'optimized_bytes' => $is_done ? $image_size : null,
			);
		}

		if ( true === $queued || $pending > 0 ) {
			$state = 'pending';
		} elseif ( $optimized > 0 ) {
			$state = 'optimized';
		} else {
			$state = 'unoptimized';
		}

		return array(
			'state'           => $state,
			'sizes'           => $sizes,
			'optimized_sizes' => $optimized,
			'pending_sizes'   => $pending,
			'queued'          => $queued,
			'level'           => $level,
			'original_bytes'  => $optimized ? $before : null,
			'optimized_bytes' => $optimized ? $after : null,
			'bytes_saved'     => $optimized ? max( 0, $before - $after ) : null,
			'savings_percent' => $optimized ? Optimizer_Target::percent( $before, $after ) : null,
			'backup_mode'     => $mode,
			'has_backup'      => self::backup_available( $mode, $full ),
		);
	}

	private static function backup_mode() {
		if ( ! function_exists( 'ewww_image_optimizer_get_option' ) ) {
			return 'none';
		}
		$option = ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' );
		if ( 'local' === $option ) {
			return 'local';
		}
		if ( ! empty( $option ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			return 'cloud';
		}
		return 'none';
	}

	private static function backup_available( $mode, $full ) {
		global $eio_backup;
		if ( 'none' === $mode ) {
			return false;
		}
		if ( ! is_array( $full ) || empty( $full['path'] ) ) {
			return false;
		}
		if ( ! is_object( $eio_backup ) || ! method_exists( $eio_backup, 'is_backup_available' ) ) {
			return null;
		}
		try {
			return (bool) $eio_backup->is_backup_available( $full['path'], $full );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function optimized_row_ids( $rows ) {
		$ids = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['id'] ) && ! empty( $row['image_size'] ) ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}

	private static function size_names( $rows, array $ids ) {
		$names = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['id'] ) && in_array( (int) $row['id'], $ids, true ) ) {
				$names[] = isset( $row['resize'] ) ? (string) $row['resize'] : '';
			}
		}
		return $names;
	}

	private static function quota_problem() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$status = get_transient( 'ewww_image_optimizer_cloud_status' );
		$map    = array(
			'exceeded'        => 'EWWW reports the API account is out of credits.',
			'exceeded subkey' => 'EWWW reports the API sub-key is out of credits.',
			'exceeded quota'  => 'EWWW reports the API account is over its soft quota.',
		);
		return is_string( $status ) && isset( $map[ $status ] ) ? $map[ $status ] : null;
	}

	private static function clean_messages( array $messages ) {
		$out = array();
		foreach ( $messages as $message ) {
			$message = sanitize_text_field( html_entity_decode( (string) $message, ENT_QUOTES, 'UTF-8' ) );
			
			$message = (string) preg_replace( '#(?:[A-Za-z]:)?[\\\\/](?:[^\\\\/\s]+[\\\\/])+([^\\\\/\s]+)#', '$1', $message );
			if ( '' !== $message ) {
				$out[] = $message;
			}
			if ( count( $out ) >= self::MAX_ERRORS ) {
				break;
			}
		}
		return $out;
	}
}
