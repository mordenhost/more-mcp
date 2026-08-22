<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ninja_Forms_Adapter {

	const SUB_POST_TYPE = 'nf_sub';

	public static function nf() {
		if ( ! function_exists( 'Ninja_Forms' ) ) {
			return null;
		}
		$nf = \Ninja_Forms();
		return is_object( $nf ) ? $nf : null;
	}

	public static function nf_unavailable() {
		return new \Exception( 'Ninja Forms service container is unavailable. Verify the plugin is active and up to date.' );
	}

	public static function nf_list_forms() {
		$nf = self::nf();
		if ( ! $nf || ! method_exists( $nf, 'form' ) ) {
			throw self::nf_unavailable();
		}
		$forms = $nf->form()->get_forms();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			if ( ! is_object( $form ) || ! method_exists( $form, 'get_id' ) ) {
				continue;
			}
			$form_id = (int) $form->get_id();
			$out[]   = array(
				'provider'    => 'ninjaforms',
				'id'          => $form_id,
				'title'       => (string) ( method_exists( $form, 'get_setting' ) ? $form->get_setting( 'title' ) : '' ),
				'active'      => true,
				'entry_count' => self::nf_count( $form_id ),
			);
		}
		return $out;
	}

	public static function nf_get_form( $form_id ) {
		$nf = self::nf();
		if ( ! $nf || ! method_exists( $nf, 'form' ) ) {
			throw self::nf_unavailable();
		}
		$form = $nf->form( (int) $form_id );
		if ( ! is_object( $form ) || ! method_exists( $form, 'get_fields' ) ) {
			throw new \Exception( 'Ninja Forms form not found.' );
		}
		$fields = array();
		foreach ( (array) $form->get_fields() as $field ) {
			if ( ! is_object( $field ) || ! method_exists( $field, 'get_id' ) ) {
				continue;
			}
			$get = static function ( $key, $default = '' ) use ( $field ) {
				return method_exists( $field, 'get_setting' ) ? $field->get_setting( $key ) : $default;
			};
			$type = (string) $get( 'type' );

			
			if ( in_array( $type, array( 'submit', 'html', 'hr', 'heading' ), true ) ) {
				continue;
			}
			$fields[] = Normalizers::normalize_field(
				(string) $field->get_id(),
				(string) $get( 'label' ),
				$type,
				(bool) $get( 'required' )
			);
		}
		return array(
			'provider' => 'ninjaforms',
			'id'       => (int) ( method_exists( $form, 'get_id' ) ? $form->get_id() : $form_id ),
			'title'    => (string) ( method_exists( $form, 'get_setting' ) ? $form->get_setting( 'title' ) : '' ),
			'active'   => true,
			'fields'   => $fields,
		);
	}

	public static function nf_count( $form_id, $status = '' ) {

		$args = array(
			'post_type'      => self::SUB_POST_TYPE,
			'post_status'    => '' !== $status ? $status : array( 'publish', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_form_id',
			'meta_value'     => (int) $form_id,
		);
		$q = new \WP_Query( $args );
		return (int) $q->post_count;
	}

	public static function nf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$nf_status = self::nf_map_status_filter( $status );
		$args      = array(
			'post_type'      => self::SUB_POST_TYPE,
			'post_status'    => '' !== $nf_status ? $nf_status : array( 'publish', 'trash' ),
			'posts_per_page' => (int) $per_page,
			'paged'          => (int) $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'meta_key'       => '_form_id',
			'meta_value'     => (int) $form_id,
		);
		if ( ! empty( $range['start_date'] ) || ! empty( $range['end_date'] ) ) {
			$date_query = array();
			if ( ! empty( $range['start_date'] ) ) {
				$date_query['after'] = $range['start_date'] . ' 00:00:00';
			}
			if ( ! empty( $range['end_date'] ) ) {
				$date_query['before'] = $range['end_date'] . ' 23:59:59';
			}
			$date_query['inclusive'] = true;
			$args['date_query']      = array( $date_query );
		}
		$q     = new \WP_Query( $args );
		$total = (int) $q->found_posts;
		$rows  = array();
		foreach ( (array) $q->posts as $post ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'ninjaforms',
				(int) $post->ID,
				(string) $post->post_date,
				(string) $post->post_status,
				
				false
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function nf_get_entry( $entry_id ) {
		$post = get_post( (int) $entry_id );
		if ( ! $post || self::SUB_POST_TYPE !== $post->post_type ) {
			throw new \Exception( 'Ninja Forms submission not found.' );
		}
		$form_id = (int) get_post_meta( (int) $entry_id, '_form_id', true );
		$values  = array();

		
		$nf = self::nf();
		if ( $nf && method_exists( $nf, 'form' ) && $form_id > 0 ) {
			$sub = self::nf_find_sub( $form_id, (int) $entry_id );
			if ( $sub && method_exists( $sub, 'get_field_values' ) ) {
				$raw = $sub->get_field_values();
				if ( is_array( $raw ) ) {
					$values = $raw;
				}
			}
		}

		if ( empty( $values ) ) {
			foreach ( (array) get_post_meta( (int) $entry_id ) as $key => $val ) {
				if ( 0 === strpos( (string) $key, '_field_' ) ) {
					$values[ substr( (string) $key, strlen( '_field_' ) ) ] = is_array( $val ) ? reset( $val ) : $val;
				}
			}
		}

		return Normalizers::normalize_entry_detail(
			'ninjaforms',
			array(
				'id'           => (int) $entry_id,
				'form_id'      => $form_id,
				'date_created' => (string) $post->post_date,
				'status'       => (string) $post->post_status,
				'values'       => $values,
				
			)
		);
	}

	public static function nf_get_stats( $form_id ) {
		$by_status = array();
		foreach ( array( 'publish', 'trash' ) as $status ) {
			$by_status[ $status ] = self::nf_count( $form_id, $status );
		}
		return array(
			'provider'  => 'ninjaforms',
			'form_id'   => (int) $form_id,
			'total'     => self::nf_count( $form_id ),
			'by_status' => $by_status,
		);
	}

	public static function nf_update_entry_status( $entry_id, $status ) {

		
		
		$post = get_post( (int) $entry_id );
		if ( ! $post || self::SUB_POST_TYPE !== $post->post_type ) {
			throw new \Exception( 'Ninja Forms submission not found.' );
		}
		if ( 'active' === $status ) {
			$target = 'publish';
		} else {
			throw new \Exception(
				'Ninja Forms submissions have no "' . esc_html( $status ) . '" state. Ninja Forms stores only published / trashed; '
				. 'use forms_trash_entry to trash, or status "active" to restore. spam/read/unread are not supported.'
			);
		}
		$prior  = array( 'property' => 'post_status', 'value' => (string) $post->post_status );
		$undo   = Normalizers::store_undo( 'ninjaforms', $entry_id, $prior, 'Restore Ninja Forms submission #' . $entry_id . ' post_status to ' . $prior['value'] );
		$result = wp_update_post( array( 'ID' => (int) $entry_id, 'post_status' => $target ), true );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to update Ninja Forms submission: ' . esc_html( $result->get_error_message() ) );
		}
		return array(
			'written'  => true,
			'provider' => 'ninjaforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	public static function nf_trash_entry( $entry_id ) {
		$post = get_post( (int) $entry_id );
		if ( ! $post || self::SUB_POST_TYPE !== $post->post_type ) {
			throw new \Exception( 'Ninja Forms submission not found.' );
		}
		$prior = array( 'property' => 'post_status', 'value' => (string) $post->post_status );
		$undo  = Normalizers::store_undo( 'ninjaforms', $entry_id, $prior, 'Restore Ninja Forms submission #' . $entry_id . ' from trash to ' . $prior['value'] );
		
		$result = wp_trash_post( (int) $entry_id );
		if ( ! $result ) {
			throw new \Exception( 'Failed to trash Ninja Forms submission.' );
		}
		return array(
			'written'  => true,
			'provider' => 'ninjaforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	public static function nf_undo( $entry_id, $property, $value ) {
		if ( 'post_status' !== $property ) {
			throw new \Exception( 'Unexpected Ninja Forms undo property: ' . esc_html( $property ) );
		}
		$result = wp_update_post( array( 'ID' => (int) $entry_id, 'post_status' => (string) $value ), true );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to restore Ninja Forms submission.' );
		}
	}

	private static function nf_find_sub( $form_id, $entry_id ) {
		$nf = self::nf();
		if ( ! $nf || ! method_exists( $nf, 'form' ) ) {
			return null;
		}
		$form = $nf->form( (int) $form_id );
		if ( ! is_object( $form ) || ! method_exists( $form, 'get_sub' ) ) {

			return null;
		}
		$sub = $form->get_sub( (int) $entry_id );
		return is_object( $sub ) ? $sub : null;
	}

	public static function nf_map_status_filter( $status ) {
		
		if ( 'active' === $status ) {
			return 'publish';
		}
		if ( 'trash' === $status ) {
			return 'trash';
		}
		
		return '';
	}
}
