<?php

namespace Mordenhost_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Image_Alt {

	const META = '_wp_attachment_image_alt';

	const MAX = 50;

	const MAX_DEPTH = 8;

	const MAX_ELEMENT_DEPTH = 32;

	public static function collect( $settings, string $element_id = '' ): array {
		$out = array();
		if ( is_array( $settings ) ) {
			self::walk( $settings, '', $element_id, 0, $out );
		}
		return $out;
	}

	public static function collect_from_element( array $element, int $depth = 0 ): array {
		$out = self::collect( $element['settings'] ?? null, (string) ( $element['id'] ?? '' ) );
		if ( $depth < self::MAX_ELEMENT_DEPTH && isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				if ( is_array( $child ) ) {
					$out = array_merge( $out, self::collect_from_element( $child, $depth + 1 ) );
				}
			}
		}
		return $out;
	}

	public static function attachment_ids_for_call( string $tool, array $args ): array {
		$found = array();
		if ( 'elementor_add_widget' === $tool ) {
			$found = self::collect_from_args( $args, 0 );
		} elseif ( 'elementor_update_widget' === $tool && isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
			$found = self::collect( self::sent_values( $args['settings'] ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', array_column( $found, 'attachment_id' ) ) ) );
		return array_slice( $ids, 0, self::MAX );
	}

	public static function sent_values( array $settings ): array {
		return array_filter(
			$settings,
			static function ( $value ) {
				return null !== $value;
			}
		);
	}

	public static function apply( array $candidates, bool $overwrite, bool $dry_run ): array {
		$written = array();
		$skipped = array();
		$seen    = array();

		foreach ( $candidates as $candidate ) {
			$id    = (int) $candidate['attachment_id'];
			$where = array( 'attachment_id' => $id );
			if ( ! empty( $candidate['element_id'] ) ) {
				$where['element_id'] = (string) $candidate['element_id'];
			}
			$where['setting'] = (string) $candidate['setting'];

			$alt = sanitize_text_field( (string) $candidate['alt'] );
			if ( '' === $alt ) {
				$skipped[] = $where + array( 'reason' => 'empty_after_sanitize' );
				continue;
			}
			if ( array_key_exists( $id, $seen ) ) {

				if ( $seen[ $id ] !== $alt ) {
					$skipped[] = $where + array(
						'reason'   => 'duplicate',
						'alt_used' => $seen[ $id ],
					);
				}
				continue;
			}
			if ( count( $seen ) >= self::MAX ) {
				$skipped[] = $where + array( 'reason' => 'limit' );
				continue;
			}
			$seen[ $id ] = $alt;

			try {
				$post = get_post( $id );
				if ( ! $post || 'attachment' !== ( $post->post_type ?? '' ) ) {
					$skipped[] = $where + array( 'reason' => 'not_an_attachment' );
					continue;
				}
				if ( ! current_user_can( 'edit_post', $id ) ) {
					$skipped[] = $where + array( 'reason' => 'no_permission' );
					continue;
				}
				if ( self::media_write_denied() ) {
					$skipped[] = $where + array( 'reason' => 'connection_policy' );
					continue;
				}
				$current = (string) get_post_meta( $id, self::META, true );
				if ( $current === $alt ) {
					$skipped[] = $where + array( 'reason' => 'unchanged' );
					continue;
				}
				if ( '' !== trim( $current ) && ! $overwrite ) {
					$skipped[] = $where + array(
						'reason'      => 'has_alt',
						'current_alt' => $current,
					);
					continue;
				}
				if ( ! $dry_run ) {

					update_post_meta( $id, self::META, wp_slash( $alt ) );
					$stored = (string) get_post_meta( $id, self::META, true );
					if ( $stored !== $alt ) {
						$skipped[] = $where + array(
							'reason'      => 'not_stored',
							'current_alt' => $stored,
						);
						continue;
					}
				}
				$written[] = $where + array(
					'alt'          => $alt,
					'previous_alt' => $current,
				);
			} catch ( \Throwable $e ) {
				$skipped[] = $where + array( 'reason' => 'error' );
			}
		}

		return array(
			'written' => $written,
			'skipped' => $skipped,
		);
	}

	public static function report( array $response, array $result, bool $dry_run ): array {
		if ( empty( $result['written'] ) && empty( $result['skipped'] ) ) {
			return $response;
		}
		$response[ $dry_run ? 'alt_would_write' : 'alt_written' ] = $result['written'];
		if ( ! empty( $result['skipped'] ) ) {
			$response['alt_skipped'] = $result['skipped'];
			if ( in_array( 'has_alt', array_column( $result['skipped'], 'reason' ), true ) ) {
				$response['alt_note'] = 'An attachment that already has alt text keeps it, because that alt shows everywhere the image is used. Pass overwrite_alt: true to replace it, or use wp_update_media.';
			}
		}
		return $response;
	}

	public static function undo_entries( array $result ): array {
		$out = array();
		foreach ( $result['written'] ?? array() as $entry ) {
			$out[] = array(
				'attachment_id' => (int) $entry['attachment_id'],
				'alt'           => (string) $entry['alt'],
				'previous_alt'  => (string) $entry['previous_alt'],
			);
		}
		return $out;
	}

	public static function restore( array $entries ): array {
		$restored     = array();
		$not_restored = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id       = (int) ( $entry['attachment_id'] ?? 0 );
			$wrote    = (string) ( $entry['alt'] ?? '' );
			$previous = (string) ( $entry['previous_alt'] ?? '' );
			try {
				$post = $id > 0 ? get_post( $id ) : null;
				if ( ! $post || 'attachment' !== ( $post->post_type ?? '' ) ) {
					$not_restored[] = array(
						'attachment_id' => $id,
						'reason'        => 'not_an_attachment',
					);
					continue;
				}
				if ( ! current_user_can( 'edit_post', $id ) ) {
					$not_restored[] = array(
						'attachment_id' => $id,
						'reason'        => 'no_permission',
					);
					continue;
				}
				$current = (string) get_post_meta( $id, self::META, true );
				if ( $current !== $wrote ) {
					$not_restored[] = array(
						'attachment_id' => $id,
						'reason'        => 'changed_since',
						'current_alt'   => $current,
					);
					continue;
				}
				if ( '' === $previous ) {
					delete_post_meta( $id, self::META );
				} else {
					update_post_meta( $id, self::META, wp_slash( $previous ) );
				}
				$restored[] = array(
					'attachment_id' => $id,
					'alt'           => $previous,
				);
			} catch ( \Throwable $e ) {
				$not_restored[] = array(
					'attachment_id' => $id,
					'reason'        => 'error',
				);
			}
		}
		return array(
			'restored'     => $restored,
			'not_restored' => $not_restored,
		);
	}

	private static function walk( array $value, string $path, string $element_id, int $depth, array &$out ): void {
		if ( self::is_media_with_alt( $value ) ) {
			$found = array(
				'attachment_id' => (int) $value['id'],
				'alt'           => (string) $value['alt'],
				'setting'       => $path,
			);
			if ( '' !== $element_id ) {
				$found['element_id'] = $element_id;
			}
			$out[] = $found;
			return;
		}
		if ( $depth >= self::MAX_DEPTH ) {
			return;
		}
		foreach ( $value as $key => $child ) {

			if ( '__dynamic__' === $key || '__globals__' === $key || ! is_array( $child ) ) {
				continue;
			}
			self::walk( $child, '' === $path ? (string) $key : $path . '.' . $key, $element_id, $depth + 1, $out );
		}
	}

	private static function is_media_with_alt( array $value ): bool {
		if ( ! array_key_exists( 'id', $value ) || ! isset( $value['alt'] ) || ! is_string( $value['alt'] ) || '' === trim( $value['alt'] ) ) {
			return false;
		}
		$id = $value['id'];
		if ( is_int( $id ) ) {
			return $id > 0;
		}
		return is_string( $id ) && '' !== $id && ctype_digit( $id ) && (int) $id > 0;
	}

	private static function collect_from_args( array $args, int $depth ): array {
		$out = self::collect( $args['settings'] ?? null );
		if ( $depth < self::MAX_ELEMENT_DEPTH && isset( $args['children'] ) && is_array( $args['children'] ) ) {
			foreach ( $args['children'] as $child ) {
				if ( is_array( $child ) ) {
					$out = array_merge( $out, self::collect_from_args( $child, $depth + 1 ) );
				}
			}
		}
		return $out;
	}

	private static function media_write_denied(): bool {
		if ( ! class_exists( \Mordenhost_MCP\Access\Policy::class ) || ! class_exists( \Mordenhost_MCP\MCP\Request_Context::class ) ) {
			return false;
		}
		$policy = \Mordenhost_MCP\Access\Policy::for_key( \Mordenhost_MCP\MCP\Request_Context::key() );
		return null !== \Mordenhost_MCP\Access\Policy::decide_group( 'media', true, $policy );
	}
}
