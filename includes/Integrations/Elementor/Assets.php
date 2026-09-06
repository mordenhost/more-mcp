<?php
namespace More_MCP\Integrations\Elementor;

use More_MCP\Integrations\Write_Verify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	const FONT_CPT            = 'elementor_font';
	const FONT_TAXONOMY       = 'elementor_font_type';
	const FONT_TERM           = 'custom';
	const FONT_FILES_META     = 'elementor_font_files';
	const FONT_FACE_META      = 'elementor_font_face';
	const FONTS_OPTION        = 'elementor_fonts_manager_fonts';
	const FONTS_TYPE_OPTION   = 'elementor_fonts_manager_font_types';

	const ICON_CPT            = 'elementor_icons';
	const ICON_CONFIG_META    = 'elementor_custom_icon_set_config';
	const ICON_SETS_OPTION    = 'elementor_custom_icon_sets_config';

	const FILE_TYPES = array( 'woff2', 'woff', 'ttf', 'eot', 'svg' );

	public static function is_available() {
		return defined( 'ELEMENTOR_PRO_VERSION' ) && post_type_exists( self::FONT_CPT );
	}

	private static function require_cap() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'You do not have permission to manage Elementor fonts and icons (edit_theme_options required).' );
		}
	}

	private static function require_available() {
		if ( ! self::is_available() ) {
			throw new \Exception( 'Elementor Pro custom fonts/icons are not available on this site.' );
		}
	}

	public static function list_fonts() {
		self::require_cap();
		self::require_available();

		$posts = get_posts( array(
			'post_type'      => self::FONT_CPT,
			'post_status'    => 'any',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'suppress_filters' => false,
		) );

		$fonts = array();
		foreach ( $posts as $post ) {
			$variations = get_post_meta( $post->ID, self::FONT_FILES_META, true );
			$fonts[] = array(
				'font_id'         => (int) $post->ID,
				'family'          => $post->post_title,
				'variation_count' => is_array( $variations ) ? count( $variations ) : 0,
				'type'            => self::FONT_TERM,
			);
		}

		return array(
			'count' => count( $fonts ),
			'fonts' => $fonts,
		);
	}

	public static function get_font( $args ) {
		self::require_cap();
		self::require_available();

		$font_id = isset( $args['font_id'] ) ? (int) $args['font_id'] : 0;
		$post    = $font_id > 0 ? get_post( $font_id ) : null;
		if ( ! $post || self::FONT_CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor custom font found for font_id ' . esc_html( (string) $font_id ) . '.' );
		}

		$variations = get_post_meta( $font_id, self::FONT_FILES_META, true );
		$rows = array();
		if ( is_array( $variations ) ) {
			foreach ( $variations as $v ) {
				$files = array();
				foreach ( self::FILE_TYPES as $type ) {
					if ( ! empty( $v[ $type ]['url'] ) ) {
						$files[ $type ] = array(
							'url' => (string) $v[ $type ]['url'],
							'id'  => isset( $v[ $type ]['id'] ) ? (int) $v[ $type ]['id'] : 0,
						);
					}
				}
				$rows[] = array(
					'weight' => isset( $v['font_weight'] ) ? (string) $v['font_weight'] : '',
					'style'  => isset( $v['font_style'] ) ? (string) $v['font_style'] : '',
					'type'   => isset( $v['font_type'] ) ? (string) $v['font_type'] : '',
					'files'  => $files,
				);
			}
		}

		return array(
			'font_id'    => $font_id,
			'family'     => $post->post_title,
			'type'       => self::FONT_TERM,
			'variations' => $rows,
		);
	}

	public static function create_font( $args ) {
		self::require_cap();
		self::require_available();

		$family = isset( $args['family'] ) ? sanitize_text_field( (string) $args['family'] ) : '';
		if ( '' === $family ) {
			throw new \Exception( 'family is required (the font-family name, which becomes the post title).' );
		}
		$variations = self::normalize_variations( $args['variations'] ?? null );

		$post_id = wp_insert_post( array(
			'post_type'   => self::FONT_CPT,
			'post_title'  => $family,
			'post_status' => 'publish',
		), true );
		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create the font post: ' . esc_html( $post_id->get_error_message() ) );
		}

		wp_set_object_terms( $post_id, self::FONT_TERM, self::FONT_TAXONOMY );
		update_post_meta( $post_id, self::FONT_FILES_META, $variations );
		$derived = self::rebuild_font_derived_state( $post_id );

		return array(
			'success'         => true,
			'font_id'         => (int) $post_id,
			'family'          => $family,
			'variation_count' => count( $variations ),
			'font_face_rebuilt' => $derived['font_face_rebuilt'],
			'notes'           => $derived['notes'],
		);
	}

	public static function update_font( $args ) {
		self::require_cap();
		self::require_available();

		$font_id = isset( $args['font_id'] ) ? (int) $args['font_id'] : 0;
		$post    = $font_id > 0 ? get_post( $font_id ) : null;
		if ( ! $post || self::FONT_CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor custom font found for font_id ' . esc_html( (string) $font_id ) . '.' );
		}

		$prior = array(
			'post_title'  => (string) $post->post_title,
			'font_files'  => get_post_meta( $font_id, self::FONT_FILES_META, true ),
		);

		$changed    = array();
		$family     = null;
		$variations = null;

		

		
		if ( isset( $args['family'] ) ) {
			$family = sanitize_text_field( (string) $args['family'] );
			if ( '' === $family ) {
				throw new \Exception( 'family cannot be empty.' );
			}
		}
		if ( array_key_exists( 'variations', $args ) ) {
			$variations = self::normalize_variations( $args['variations'] );
		}

		if ( null !== $family ) {
			$res = wp_update_post( array( 'ID' => $font_id, 'post_title' => $family ), true );
			if ( is_wp_error( $res ) ) {
				throw new \Exception( 'Failed to rename the font: ' . esc_html( $res->get_error_message() ) );
			}
			$changed[] = 'family';
		}

		
		
		if ( null !== $variations ) {
			update_post_meta( $font_id, self::FONT_FILES_META, $variations );
			$changed[] = 'variations';
		}

		$derived = self::rebuild_font_derived_state( $font_id );

		
		
		$states = array();
		if ( null !== $family ) {
			$fresh    = get_post( $font_id );
			$states[] = Write_Verify::state_of(
				is_object( $fresh ) ? (string) $fresh->post_title : Write_Verify::UNREADABLE,
				$family,
				$prior['post_title']
			);
		}
		if ( null !== $variations ) {
			$states[] = Write_Verify::state_of(
				get_post_meta( $font_id, self::FONT_FILES_META, true ),
				$variations,
				$prior['font_files']
			);
		}
		$state = Write_Verify::combine( $states );

		Write_Verify::refuse_if_discarded( $state, __( 'custom font', 'more-mcp' ) );
		$verify = Write_Verify::report( $state, __( 'custom font', 'more-mcp' ) );

		
		$undo = \More_MCP\MCP\Undo_Store::store( array(
			'op'           => 'elementor_asset_write',
			'summary'      => sprintf( 'elementor_update_font on font %d (%s)', $font_id, $post->post_title ),
			'target'       => array( 'kind' => 'font', 'post_id' => $font_id ),
			'pre_op_state' => $prior,
		) );

		return array_merge(
			array(
				'success'           => true,
				'font_id'           => $font_id,
				'changed'           => $changed,
				'font_face_rebuilt' => $derived['font_face_rebuilt'],
				'notes'             => $derived['notes'],
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function delete_font( $args ) {
		self::require_cap();
		self::require_available();

		$font_id = isset( $args['font_id'] ) ? (int) $args['font_id'] : 0;
		$post    = $font_id > 0 ? get_post( $font_id ) : null;
		if ( ! $post || self::FONT_CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor custom font found for font_id ' . esc_html( (string) $font_id ) . '.' );
		}

		$references = self::count_font_references( $post->post_title );

		if ( ! empty( $args['dry_run'] ) ) {
			return array(
				'dry_run'    => true,
				'font_id'    => $font_id,
				'family'     => $post->post_title,
				'references' => $references,
				'message'    => sprintf(
					'Would delete the custom font "%s" (font_id %d). It is referenced by %d kit/page slot(s). Re-call without dry_run to delete; an undo token will be returned.',
					$post->post_title,
					$font_id,
					$references
				),
			);
		}

		$prior = array(
			'post_title' => (string) $post->post_title,
			'font_files' => get_post_meta( $font_id, self::FONT_FILES_META, true ),
			'font_face'  => get_post_meta( $font_id, self::FONT_FACE_META, true ),
		);

		wp_delete_post( $font_id, true );
		self::clear_fonts_option_cache();

		

		$state = Write_Verify::state_of_presence( ! get_post( $font_id ) );
		Write_Verify::refuse_if_discarded(
			$state,
			__( 'custom font deletion', 'more-mcp' ),
			__( 'The font post is still present.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, __( 'custom font deletion', 'more-mcp' ) );

		$undo = \More_MCP\MCP\Undo_Store::store( array(
			'op'           => 'elementor_asset_write',
			'summary'      => sprintf( 'elementor_delete_font removed font %d (%s)', $font_id, $post->post_title ),
			'target'       => array( 'kind' => 'font_delete', 'post_id' => $font_id ),
			'pre_op_state' => $prior,
		) );

		return array_merge(
			array(
				'success'    => true,
				'font_id'    => $font_id,
				'family'     => $post->post_title,
				'references' => $references,
				'message'    => 'Custom font deleted.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function list_icon_sets() {
		self::require_cap();
		self::require_available();

		$posts = get_posts( array(
			'post_type'   => self::ICON_CPT,
			'post_status' => 'any',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'suppress_filters' => false,
		) );

		$sets = array();
		foreach ( $posts as $post ) {
			$config = self::decode_icon_config( $post->ID );
			$sets[] = array(
				'set_id'     => (int) $post->ID,
				'label'      => $post->post_title,
				'prefix'     => isset( $config['name'] ) ? (string) $config['name'] : '',
				'icon_count' => isset( $config['icons'] ) && is_array( $config['icons'] ) ? count( $config['icons'] ) : 0,
			);
		}

		return array(
			'count'     => count( $sets ),
			'icon_sets' => $sets,
		);
	}

	public static function get_icon_set( $args ) {
		self::require_cap();
		self::require_available();

		$set_id = isset( $args['set_id'] ) ? (int) $args['set_id'] : 0;
		$post   = $set_id > 0 ? get_post( $set_id ) : null;
		if ( ! $post || self::ICON_CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor custom icon set found for set_id ' . esc_html( (string) $set_id ) . '.' );
		}

		$config = self::decode_icon_config( $set_id );
		$names  = array();
		if ( isset( $config['icons'] ) && is_array( $config['icons'] ) ) {
			foreach ( $config['icons'] as $icon ) {
				if ( is_array( $icon ) && isset( $icon['properties']['name'] ) ) {
					$names[] = (string) $icon['properties']['name'];
				} elseif ( is_array( $icon ) && isset( $icon['name'] ) ) {
					$names[] = (string) $icon['name'];
				} elseif ( is_string( $icon ) ) {
					$names[] = $icon;
				}
			}
		}

		return array(
			'set_id'     => $set_id,
			'label'      => $post->post_title,
			'prefix'     => isset( $config['name'] ) ? (string) $config['name'] : '',
			'icon_count' => count( $names ),
			'icons'      => $names,
		);
	}

	public static function delete_icon_set( $args ) {
		self::require_cap();
		self::require_available();

		$set_id = isset( $args['set_id'] ) ? (int) $args['set_id'] : 0;
		$post   = $set_id > 0 ? get_post( $set_id ) : null;
		if ( ! $post || self::ICON_CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor custom icon set found for set_id ' . esc_html( (string) $set_id ) . '.' );
		}

		$config = self::decode_icon_config( $set_id );
		$prefix = isset( $config['name'] ) ? (string) $config['name'] : '';

		if ( ! empty( $args['dry_run'] ) ) {
			return array(
				'dry_run' => true,
				'set_id'  => $set_id,
				'label'   => $post->post_title,
				'prefix'  => $prefix,
				'message' => sprintf(
					'Would delete the custom icon set "%s" (set_id %d, prefix "%s"). Re-call without dry_run to delete; an undo token will be returned.',
					$post->post_title,
					$set_id,
					$prefix
				),
			);
		}

		$prior = array(
			'post_title'  => (string) $post->post_title,
			'icon_config' => get_post_meta( $set_id, self::ICON_CONFIG_META, true ),
		);

		wp_delete_post( $set_id, true );
		delete_option( self::ICON_SETS_OPTION );

		$state = Write_Verify::state_of_presence( ! get_post( $set_id ) );
		Write_Verify::refuse_if_discarded(
			$state,
			__( 'custom icon set deletion', 'more-mcp' ),
			__( 'The icon-set post is still present.', 'more-mcp' )
		);
		$verify = Write_Verify::report( $state, __( 'custom icon set deletion', 'more-mcp' ) );

		$undo = \More_MCP\MCP\Undo_Store::store( array(
			'op'           => 'elementor_asset_write',
			'summary'      => sprintf( 'elementor_delete_icon_set removed set %d (%s)', $set_id, $post->post_title ),
			'target'       => array( 'kind' => 'icon_delete', 'post_id' => $set_id ),
			'pre_op_state' => $prior,
		) );

		return array_merge(
			array(
				'success' => true,
				'set_id'  => $set_id,
				'label'   => $post->post_title,
				'message' => 'Custom icon set deleted.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	
	public static function undo_asset_write( array $snapshot ) {
		$target = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();
		$kind   = isset( $target['kind'] ) ? (string) $target['kind'] : '';
		$pre    = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();
		$post_id = isset( $target['post_id'] ) ? (int) $target['post_id'] : 0;

		switch ( $kind ) {
			case 'font':
				
				if ( $post_id <= 0 || ! get_post( $post_id ) ) {
					throw new \Exception( 'The target font no longer exists.' );
				}
				if ( isset( $pre['post_title'] ) ) {
					wp_update_post( array( 'ID' => $post_id, 'post_title' => (string) $pre['post_title'] ) );
				}
				update_post_meta( $post_id, self::FONT_FILES_META, is_array( $pre['font_files'] ?? null ) ? $pre['font_files'] : array() );
				self::rebuild_font_derived_state( $post_id );
				return array(
					'success' => true,
					'op'      => 'elementor_asset_write',
					'font_id' => $post_id,
					'message' => 'Custom font restored to its pre-operation state.',
				);

			case 'font_delete':
				return self::restore_font_post( $pre );

			case 'icon_delete':
				return self::restore_icon_post( $pre );

			default:
				throw new \Exception( 'Unknown Elementor asset undo kind.' );
		}
	}

	private static function restore_font_post( array $pre ) {
		$title = isset( $pre['post_title'] ) ? (string) $pre['post_title'] : '';
		if ( '' === $title ) {
			throw new \Exception( 'Undo snapshot has no font family to restore.' );
		}
		$new_id = wp_insert_post( array(
			'post_type'   => self::FONT_CPT,
			'post_title'  => $title,
			'post_status' => 'publish',
		), true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( 'Failed to re-create the font: ' . esc_html( $new_id->get_error_message() ) );
		}
		wp_set_object_terms( $new_id, self::FONT_TERM, self::FONT_TAXONOMY );
		update_post_meta( $new_id, self::FONT_FILES_META, is_array( $pre['font_files'] ?? null ) ? $pre['font_files'] : array() );
		self::rebuild_font_derived_state( $new_id );
		return array(
			'success' => true,
			'op'      => 'elementor_asset_write',
			'font_id' => (int) $new_id,
			'message' => 'Custom font re-created from the undo snapshot. Note: its post ID changed.',
		);
	}

	private static function restore_icon_post( array $pre ) {
		$title = isset( $pre['post_title'] ) ? (string) $pre['post_title'] : '';
		if ( '' === $title ) {
			throw new \Exception( 'Undo snapshot has no icon set label to restore.' );
		}
		$new_id = wp_insert_post( array(
			'post_type'   => self::ICON_CPT,
			'post_title'  => $title,
			'post_status' => 'publish',
		), true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( 'Failed to re-create the icon set: ' . esc_html( $new_id->get_error_message() ) );
		}
		update_post_meta( $new_id, self::ICON_CONFIG_META, (string) ( $pre['icon_config'] ?? '' ) );
		delete_option( self::ICON_SETS_OPTION );
		return array(
			'success' => true,
			'op'      => 'elementor_asset_write',
			'set_id'  => (int) $new_id,
			'message' => 'Custom icon set re-created from the undo snapshot. Note: its post ID changed.',
		);
	}

	
	private static function normalize_variations( $input ) {
		if ( null === $input ) {
			return array();
		}
		if ( ! is_array( $input ) ) {
			throw new \Exception( 'variations must be an array of { weight, style, files } objects.' );
		}
		$rows = array();
		foreach ( $input as $v ) {
			if ( ! is_array( $v ) ) {
				continue;
			}
			$row = array(
				'font_weight' => isset( $v['weight'] ) ? sanitize_text_field( (string) $v['weight'] ) : '400',
				'font_style'  => isset( $v['style'] ) ? sanitize_text_field( (string) $v['style'] ) : 'normal',
				'font_type'   => isset( $v['type'] ) && 'variable' === $v['type'] ? 'variable' : '',
			);
			$files = isset( $v['files'] ) && is_array( $v['files'] ) ? $v['files'] : array();
			$has_file = false;
			foreach ( self::FILE_TYPES as $type ) {
				if ( empty( $files[ $type ] ) ) {
					continue;
				}
				$entry = $files[ $type ];
				
				if ( is_array( $entry ) ) {
					$url = isset( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '';
					$id  = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
				} elseif ( is_numeric( $entry ) ) {
					$id  = (int) $entry;
					$url = (string) wp_get_attachment_url( $id );
				} else {
					$url = esc_url_raw( (string) $entry );
					$id  = 0;
				}
				if ( '' === $url ) {
					continue;
				}
				$row[ $type ] = array( 'url' => $url, 'id' => $id );
				$has_file = true;
			}
			if ( $has_file ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	private static function rebuild_font_derived_state( $post_id ) {
		$notes = array();
		$rebuilt = false;

		$custom = self::pro_custom_fonts_object();
		if ( $custom && method_exists( $custom, 'generate_font_face' ) ) {
			try {
				update_post_meta( $post_id, self::FONT_FACE_META, $custom->generate_font_face( $post_id ) );
				$rebuilt = true;
			} catch ( \Throwable $e ) {
				$notes[] = 'Elementor generate_font_face() threw; used the local @font-face builder instead.';
			}
		}
		if ( ! $rebuilt ) {
			update_post_meta( $post_id, self::FONT_FACE_META, self::build_font_face_css( $post_id ) );
			$rebuilt = true;
			if ( ! $custom ) {
				$notes[] = 'Elementor Custom_Fonts API not reachable in this request; @font-face rebuilt locally.';
			}
		}

		self::clear_fonts_option_cache();

		return array( 'font_face_rebuilt' => $rebuilt, 'notes' => $notes );
	}

	private static function clear_fonts_option_cache() {
		$fonts_manager = self::pro_fonts_manager_object();
		if ( $fonts_manager && method_exists( $fonts_manager, 'clear_fonts_list' ) ) {
			$fonts_manager->clear_fonts_list();
			return;
		}
		delete_option( self::FONTS_OPTION );
		delete_option( self::FONTS_TYPE_OPTION );
	}

	private static function build_font_face_css( $post_id ) {
		$family     = get_the_title( $post_id );
		$variations = get_post_meta( $post_id, self::FONT_FILES_META, true );
		if ( ! is_array( $variations ) ) {
			return '';
		}
		$blocks = array();
		foreach ( $variations as $v ) {
			$src = array();
			foreach ( self::FILE_TYPES as $type ) {
				if ( empty( $v[ $type ]['url'] ) ) {
					continue;
				}
				$url = $v[ $type ]['url'];
				switch ( $type ) {
					case 'woff2':
					case 'woff':
					case 'svg':
						$src[] = "url('" . esc_attr( $url ) . "') format('" . $type . "')";
						break;
					case 'ttf':
						$src[] = "url('" . esc_attr( $url ) . "') format('truetype')";
						break;
					case 'eot':
						$src[] = "url('" . esc_attr( $url ) . "?#iefix') format('embedded-opentype')";
						break;
				}
			}
			$css  = "@font-face {\n\tfont-family: '" . $family . "';\n";
			if ( empty( $v['font_type'] ) || 'variable' !== $v['font_type'] ) {
				$css .= "\tfont-style: " . ( $v['font_style'] ?? 'normal' ) . ";\n";
				$css .= "\tfont-weight: " . ( $v['font_weight'] ?? '400' ) . ";\n";
			}
			$css .= "\tfont-display: auto;\n";
			$css .= "\tsrc: " . implode( ",\n\t\t", $src ) . ";\n}";
			$blocks[] = $css;
		}
		return implode( "\n", $blocks );
	}

	private static function decode_icon_config( $post_id ) {
		$raw = get_post_meta( $post_id, self::ICON_CONFIG_META, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function count_font_references( $family ) {
		if ( '' === $family ) {
			return 0;
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}
		$like = '%' . $wpdb->esc_like( $family ) . '%';
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			'_elementor_data',
			$like
		) );
		return (int) $count;
	}

	private static function pro_fonts_manager_object() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
			return null;
		}
		try {
			$plugin = \ElementorPro\Plugin::instance();
			if ( ! isset( $plugin->modules_manager ) ) {
				return null;
			}
			$module = $plugin->modules_manager->get_modules( 'assets-manager' );
			if ( ! $module || ! method_exists( $module, 'get_assets_manager' ) ) {
				return null;
			}
			return $module->get_assets_manager( 'font' );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function pro_custom_fonts_object() {
		$fonts_manager = self::pro_fonts_manager_object();
		if ( ! $fonts_manager || ! method_exists( $fonts_manager, 'get_font_type_object' ) ) {
			return null;
		}
		try {
			return $fonts_manager->get_font_type_object( 'custom' );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
