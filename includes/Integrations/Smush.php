<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smush {

	const META_KEY = 'wp-smpro-smush-data';

	const MAX_ERRORS = 5;

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
				'description' => 'Read WP Smush optimization status: whether it is active, its version, how many images have been smushed, the total size before and after, bytes saved, savings percent, how many images still wait for Bulk Smush, and whether Smush says its stats need a rescan. Read-only diagnostic; runs no optimization and returns no credentials.',
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
				'description' => 'Bulk Smush queue: the counts Smush\'s own Bulk Smush screen works from (not yet smushed, needing a re-smush after a settings change, failed with an error, skipped) and up to limit of those attachments with id, title, mime type, file size and status (unoptimized, needs_reoptimize or error). Read-only; runs no optimization. Optimize one with smush_optimize_attachment.',
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
			array(
				'name'        => 'smush_get_attachment_status',
				'description' => 'Read WP Smush\'s state for one media attachment: whether it is smushed, needs smushing or a re-smush, is skipped (ignored or animated) or unsupported, whether Smush kept a backup of the original, bytes before and after with savings percent, any stored Smush error, and any reason Smush will not smush it (such as its file size limit). Read-only.',
				'inputSchema' => Optimizer_Target::schema(),
			),
			array(
				'name'        => 'smush_optimize_attachment',
				'description' => 'Smush one media attachment now, exactly as the media library\'s "Smush" button does, rewriting its image files on disk. Call it first without confirm: that returns a preview (current state, whether Smush will keep a backup of the original, whether anything would run) and changes nothing. Call again with confirm=true to run it. When the preview says no backup will be kept the change cannot be undone. Refuses skipped, unsupported or missing images, and refuses when Smush needs a WPMU DEV Hub connection.',
				'inputSchema' => Optimizer_Target::schema( true ),
			),
			array(
				'name'        => 'smush_restore_attachment',
				'description' => 'Restore one smushed media attachment to the original Smush backed up, exactly as the media library\'s "Restore" button does, and clear its Smush data. Refuses when the image was never smushed or Smush kept no original to restore.',
				'inputSchema' => Optimizer_Target::schema(),
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
		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'smush_get_status':
				return self::get_status();
			case 'smush_get_settings':
				return self::get_settings();
			case 'smush_list_unoptimized':
				return self::list_unoptimized( $args );
			case 'smush_get_attachment_status':
				return self::attachment_status( $args );
			case 'smush_optimize_attachment':
				return self::optimize_attachment( $args );
			case 'smush_restore_attachment':
				return self::restore_attachment( $args );
		}

		throw new \Exception( 'Unknown images tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
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
			'remaining_count' => isset( $stats['remaining_count'] ) ? (int) $stats['remaining_count'] : null,
			'stats_outdated'  => isset( $stats['is_outdated'] ) ? (bool) $stats['is_outdated'] : null,
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
		$limit = max( 1, min( 100, $limit ) );

		$queue = self::read_queue();
		if ( null === $queue ) {
			return self::list_unoptimized_from_meta( $limit );
		}

		$list = array();
		foreach ( array( 'unoptimized', 'needs_reoptimize', 'error' ) as $status ) {
			foreach ( $queue['ids'][ $status ] as $id ) {
				if ( count( $list ) >= $limit ) {
					break 2;
				}
				$post = get_post( $id );
				if ( ! is_object( $post ) || 'attachment' !== ( $post->post_type ?? '' ) ) {
					
					continue;
				}
				$list[] = self::list_row( $post, $status );
			}
		}

		return array(
			'provider'       => 'smush',
			'source'         => 'smush_bulk_queue',
			'stats_outdated' => $queue['outdated'],
			'counts'         => $queue['counts'],
			'count'          => count( $list ),
			'attachments'    => $list,
		);
	}

	private static function list_unoptimized_from_meta( $limit ) {
		$attachments = array();
		if ( function_exists( 'get_posts' ) ) {
			$attachments = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'inherit',
					'post_mime_type'   => 'image',
					'numberposts'      => $limit,
					'posts_per_page'   => $limit,
					'orderby'          => 'date',
					'order'            => 'DESC',
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Smush keeps per-item state only in meta; bounded by limit.
						array(
							'key'     => self::META_KEY,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
		}

		$list = array();
		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $post ) {
				if ( is_object( $post ) && isset( $post->ID ) ) {
					$list[] = self::list_row( $post, 'unoptimized' );
				}
			}
		}

		return array(
			'provider'       => 'smush',
			'source'         => 'attachment_meta',
			'stats_outdated' => null,
			'counts'         => array(
				'unoptimized'      => null,
				'needs_reoptimize' => null,
				'error'            => null,
				'remaining'        => null,
				'skipped'          => null,
			),
			'count'          => count( $list ),
			'attachments'    => $list,
		);
	}

	private static function list_row( $post, $status ) {
		return array(
			'id'       => (int) $post->ID,
			'title'    => (string) ( $post->post_title ?? '' ),
			'mime'     => (string) ( $post->post_mime_type ?? '' ),
			'filesize' => self::attachment_filesize( $post ),
			'status'   => $status,
		);
	}

	private static function read_queue() {
		$class = '\Smush\Core\Stats\Global_Stats';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'get' ) ) {
			return null;
		}
		try {
			$stats = $class::get();
			if ( ! is_object( $stats ) ) {
				return null;
			}
			$ids    = array();
			$counts = array();
			$lists  = array(
				'unoptimized'      => 'get_optimize_list',
				'needs_reoptimize' => 'get_reoptimize_list',
				'error'            => 'get_error_list',
			);
			foreach ( $lists as $status => $getter ) {
				$list = method_exists( $stats, $getter ) ? $stats->$getter() : null;
				if ( ! is_object( $list ) || ! method_exists( $list, 'get_ids' ) ) {
					return null;
				}
				$list_ids          = $list->get_ids();
				$ids[ $status ]    = is_array( $list_ids ) ? array_values( array_unique( array_map( 'intval', $list_ids ) ) ) : array();
				$counts[ $status ] = count( $ids[ $status ] );
			}
			$counts['remaining'] = method_exists( $stats, 'get_remaining_count' )
				? (int) $stats->get_remaining_count()
				: $counts['unoptimized'] + $counts['needs_reoptimize'] + $counts['error'];
			$counts['skipped']   = method_exists( $stats, 'get_skipped_count' ) ? (int) $stats->get_skipped_count() : null;

			return array(
				'ids'      => $ids,
				'counts'   => $counts,
				'outdated' => method_exists( $stats, 'is_outdated' ) ? (bool) $stats->is_outdated() : null,
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function attachment_status( array $args ) {
		$post = Optimizer_Target::attachment( $args, false );
		$item = self::media_item( (int) $post->ID );
		return array_merge(
			array( 'provider' => 'smush' ),
			Optimizer_Target::summary( $post ),
			self::describe( $item['media'], $item['optimizer'] )
		);
	}

	private static function optimize_attachment( array $args ) {
		$post = Optimizer_Target::attachment( $args, true );
		$id   = (int) $post->ID;
		$item = self::media_item( $id );
		$now  = self::describe( $item['media'], $item['optimizer'] );

		$refusal = self::optimize_refusal( $now );
		if ( '' !== $refusal ) {
			throw new \Exception( esc_html( $refusal ) );
		}
		$hub_required = self::hub_access_required();
		if ( true === $hub_required ) {
			throw new \Exception( 'Smush needs a WPMU DEV Hub connection to optimize images at the configured compression level. Connect the site in Smush, or lower the compression level, then try again.' );
		}

		
		$would_run   = 'optimized' !== $now['state'];
		$keep_backup = self::backup_active();
		$base        = array_merge( array( 'provider' => 'smush' ), Optimizer_Target::summary( $post ) );

		if ( ! Optimizer_Target::confirmed( $args ) ) {
			if ( ! $would_run ) {
				$message = 'Preview only. Smush reports this image as already optimized with nothing left to do, so a confirmed call would change nothing.';
			} elseif ( true === $keep_backup || true === $now['has_backup'] ) {
				$message = 'Preview only. To smush this image, call again with confirm=true. Smush keeps a backup of the original, so smush_restore_attachment can undo it.';
			} elseif ( false === $keep_backup ) {
				$message = 'Preview only. To smush this image, call again with confirm=true. Smush\'s "backup original" setting is off, so the original files will be overwritten and this cannot be undone.';
			} else {
				$message = 'Preview only. To smush this image, call again with confirm=true. Whether Smush keeps a backup of the original could not be read, so treat this as irreversible.';
			}
			return array_merge(
				$base,
				array(
					'preview'          => true,
					'would_run'        => $would_run,
					'will_keep_backup' => $keep_backup,
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
					'message' => 'Smush reports this image as already optimized with nothing left to do; nothing was run.',
				)
			);
		}

		$optimizer_class = '\Smush\Core\Optimizer';
		if ( ! class_exists( $optimizer_class ) || ! method_exists( $optimizer_class, 'get_instance' ) ) {
			throw new \Exception( 'This Smush version does not offer the single-image optimizer this tool uses.' );
		}
		$optimizer = $optimizer_class::get_instance();
		if ( ! is_object( $optimizer ) || ! method_exists( $optimizer, 'optimize' ) ) {
			throw new \Exception( 'This Smush version does not offer the single-image optimizer this tool uses.' );
		}

		$optimized = (bool) $optimizer->optimize( $id );
		$errors    = method_exists( $optimizer, 'get_errors' ) ? self::error_messages( $optimizer->get_errors() ) : array();
		$after     = self::reread( $id );

		if ( ! $optimized && true !== $now['optimized'] && null !== $after && true !== $after['optimized'] ) {

			
			$reason = $errors ? implode( ' ', $errors ) : 'Smush returned no reason.';
			throw new \Exception( esc_html( 'Smush did not optimize attachment ' . $id . ': ' . $reason ) );
		}

		if ( null === $after || ! $optimized ) {

			
			$state = Write_Verify::UNKNOWN;
		} else {
			$state = Write_Verify::state_of_presence( true === $after['optimized'] );
		}

		return array_merge(
			$base,
			array(
				'ran'             => true,
				'smush_succeeded' => $optimized,
				'after'           => $after,
				'errors'          => $errors,
			),
			Write_Verify::report( $state, 'Smush optimization of attachment ' . $id )
		);
	}

	private static function restore_attachment( array $args ) {
		$post = Optimizer_Target::attachment( $args, true );
		$id   = (int) $post->ID;
		$item = self::media_item( $id );
		$now  = self::describe( $item['media'], $item['optimizer'] );

		if ( false === $now['mime_supported'] ) {
			throw new \Exception( esc_html( 'Smush does not handle this file type (' . (string) ( $post->post_mime_type ?? '' ) . '), so there is nothing to restore.' ) );
		}
		if ( true === $now['in_progress'] ) {
			throw new \Exception( esc_html( 'Smush is already working on attachment ' . $id . '. Try again once it finishes.' ) );
		}
		if ( false === $now['optimized'] ) {
			throw new \Exception( esc_html( 'Attachment ' . $id . ' has not been smushed, so there is nothing to restore.' ) );
		}
		if ( false === $now['has_backup'] ) {
			throw new \Exception( esc_html( 'Smush kept no backup of the original for attachment ' . $id . ' ("backup original" was off when it was smushed), so it cannot be restored.' ) );
		}

		$optimizer = $item['optimizer'];
		if ( ! is_object( $optimizer ) || ! method_exists( $optimizer, 'restore' ) ) {
			throw new \Exception( 'This Smush version does not offer the restore call this tool uses.' );
		}

		$restored = (bool) $optimizer->restore();
		if ( ! $restored ) {

			$errors = method_exists( $optimizer, 'get_restoration_errors' ) ? self::error_messages( $optimizer->get_restoration_errors() ) : array();
			$reason = $errors ? implode( ' ', $errors ) : 'Smush returned no reason.';
			throw new \Exception( esc_html( 'Smush could not restore attachment ' . $id . ': ' . $reason ) );
		}

		$after = self::reread( $id );
		$state = null === $after ? Write_Verify::UNKNOWN : Write_Verify::state_of_presence( false === $after['optimized'] );

		return array_merge(
			array( 'provider' => 'smush' ),
			Optimizer_Target::summary( $post ),
			array(
				'restored' => true,
				'after'    => $after,
			),
			Write_Verify::report( $state, 'restored original of attachment ' . $id )
		);
	}

	private static function optimize_refusal( array $now ) {
		if ( false === $now['valid'] ) {
			return 'Smush cannot read this attachment: its file or image metadata is missing.';
		}
		if ( false === $now['mime_supported'] ) {
			return 'Smush does not optimize this file type.';
		}
		if ( 'skipped' === $now['state'] ) {
			return 'Smush skips this image (' . implode( ', ', $now['skip_reasons'] ) . '). Change that in the media library first if it should be smushed.';
		}
		if ( $now['blocking_errors'] ) {

			return 'Smush will not optimize this image: ' . implode( ' ', $now['blocking_errors'] );
		}
		if ( true === $now['in_progress'] ) {
			return 'Smush is already working on this image. Try again once it finishes.';
		}
		return '';
	}

	private static function media_item( $id, $fresh = false ) {
		$cache_class     = '\Smush\Core\Media\Media_Item_Cache';
		$optimizer_class = '\Smush\Core\Media\Media_Item_Optimizer';
		if ( ! class_exists( $cache_class ) || ! method_exists( $cache_class, 'get_instance' ) || ! class_exists( $optimizer_class ) ) {
			throw new \Exception( 'This Smush version does not offer the per-image API this tool uses (Smush 3.12 or later is needed).' );
		}
		$cache = $cache_class::get_instance();
		if ( ! is_object( $cache ) || ! method_exists( $cache, 'get' ) ) {
			throw new \Exception( 'This Smush version does not offer the per-image API this tool uses (Smush 3.12 or later is needed).' );
		}
		if ( $fresh && method_exists( $cache, 'remove' ) ) {
			$cache->remove( $id );
		}
		$media = $cache->get( $id );
		if ( ! is_object( $media ) ) {
			throw new \Exception( esc_html( 'Smush could not load attachment ' . $id . '.' ) );
		}
		return array(
			'media'     => $media,
			'optimizer' => new $optimizer_class( $media ),
		);
	}

	private static function reread( $id ) {
		try {
			$item = self::media_item( $id, true );
			return self::describe( $item['media'], $item['optimizer'] );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function describe( $media, $optimizer ) {
		$valid       = self::flag( $media, 'is_valid' );
		$supported   = self::flag( $media, 'is_mime_type_supported' );
		$ignored     = self::flag( $media, 'is_ignored' );
		$animated    = self::flag( $media, 'is_animated' );
		$optimized   = self::flag( $optimizer, 'is_optimized' );
		$should      = self::flag( $optimizer, 'should_optimize' );
		$should_redo = self::flag( $optimizer, 'should_reoptimize' );
		$has_error   = self::flag( $optimizer, 'has_errors' );
		$in_progress = ( true === self::flag( $optimizer, 'in_progress' ) ) || ( true === self::flag( $optimizer, 'restore_in_progress' ) );

		$skip_reasons = array();
		if ( true === $ignored ) {
			$skip_reasons[] = 'ignored';
		}
		if ( true === $animated ) {
			$skip_reasons[] = 'animated';
		}

		if ( false === $valid ) {
			$state = 'missing';
		} elseif ( false === $supported ) {
			$state = 'unsupported';
		} elseif ( $skip_reasons ) {
			$state = 'skipped';
		} elseif ( true === $has_error && true !== $optimized ) {
			$state = 'error';
		} elseif ( true === $optimized && true === $should_redo ) {
			$state = 'needs_reoptimize';
		} elseif ( true === $optimized && true !== $should ) {
			$state = 'optimized';
		} elseif ( true === $should || false === $optimized ) {
			$state = 'unoptimized';
		} else {
			$state = 'unknown';
		}

		$before = null;
		$after  = null;
		if ( true === $optimized && method_exists( $optimizer, 'get_total_stats' ) ) {
			try {
				$stats = $optimizer->get_total_stats();
				if ( is_object( $stats ) && method_exists( $stats, 'get_size_before' ) && method_exists( $stats, 'get_size_after' ) ) {
					$before = (int) $stats->get_size_before();
					$after  = (int) $stats->get_size_after();
				}
			} catch ( \Throwable $e ) {
				$before = null;
				$after  = null;
			}
		}

		$sizes = null;
		if ( method_exists( $optimizer, 'get_optimized_sizes_count' ) ) {
			try {
				$sizes = (int) $optimizer->get_optimized_sizes_count();
			} catch ( \Throwable $e ) {
				$sizes = null;
			}
		}

		$errors = array();
		if ( true === $has_error && method_exists( $optimizer, 'get_errors' ) ) {
			try {
				$errors = self::error_messages( $optimizer->get_errors() );
			} catch ( \Throwable $e ) {
				$errors = array();
			}
		}

		
		
		$blocking = array();
		if ( true === self::flag( $media, 'has_errors' ) && method_exists( $media, 'get_errors' ) ) {
			try {
				$blocking = self::error_messages( $media->get_errors() );
			} catch ( \Throwable $e ) {
				$blocking = array();
			}
		}

		return array(
			'state'                 => $state,
			'optimized'             => $optimized,
			'valid'                 => $valid,
			'mime_supported'        => $supported,
			'skip_reasons'          => $skip_reasons,
			'has_backup'            => self::flag( $media, 'can_be_restored' ),
			'in_progress'           => $in_progress,
			'optimized_sizes_count' => $sizes,
			'original_bytes'        => $before,
			'optimized_bytes'       => $after,
			'bytes_saved'           => ( null === $before || null === $after ) ? null : max( 0, $before - $after ),
			'savings_percent'       => Optimizer_Target::percent( $before, $after ),
			'errors'                => $errors,
			'blocking_errors'       => $blocking,
		);
	}

	private static function flag( $obj, $method ) {
		if ( ! is_object( $obj ) || ! method_exists( $obj, $method ) ) {
			return null;
		}
		try {
			return (bool) $obj->$method();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function error_messages( $errors ) {
		if ( ! is_object( $errors ) || ! method_exists( $errors, 'get_error_messages' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) $errors->get_error_messages() as $message ) {

			$message = sanitize_text_field( html_entity_decode( (string) $message, ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $message ) {
				$out[] = $message;
			}
			if ( count( $out ) >= self::MAX_ERRORS ) {
				break;
			}
		}
		return $out;
	}

	private static function backup_active() {
		$class = '\Smush\Core\Settings';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'get_instance' ) ) {
			return null;
		}
		try {
			return self::flag( $class::get_instance(), 'is_backup_active' );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function hub_access_required() {
		$class = '\Smush\Core\Membership\Membership';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'get_instance' ) ) {
			return null;
		}
		try {
			return self::flag( $class::get_instance(), 'is_api_hub_access_required' );
		} catch ( \Throwable $e ) {
			return null;
		}
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
