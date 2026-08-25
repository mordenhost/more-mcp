<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPForms_Adapter {

	public static function wpf_entry_handler() {
		if ( ! function_exists( 'wpforms' ) ) {
			return null;
		}
		$app = wpforms();
		if ( ! is_object( $app ) ) {
			return null;
		}

		if ( method_exists( $app, 'obj' ) ) {
			$handler = $app->obj( 'entry' );
			if ( is_object( $handler ) && method_exists( $handler, 'get_entries' ) ) {
				return $handler;
			}
		}
		if ( isset( $app->entry ) && is_object( $app->entry ) && method_exists( $app->entry, 'get_entries' ) ) {
			return $app->entry;
		}
		return null;
	}

	public static function wpf_entries_unavailable() {
		return new \Exception(
			'WPForms entry storage is not available. WPForms Lite emails submissions '
			. 'without storing them; entries exist only in WPForms Pro. Form reads '
			. '(forms_list, forms_get) work regardless. Install WPForms Pro to read entries.'
		);
	}

	public static function wpf_list_forms() {
		$posts = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( (array) $posts as $post ) {
			$form_id = (int) $post->ID;
			$out[]   = array(
				'provider'    => 'wpforms',
				'id'          => $form_id,
				'title'       => (string) $post->post_title,
				'active'      => 'publish' === $post->post_status,
				'entry_count' => self::wpf_count( $form_id ),
			);
		}
		return $out;
	}

	public static function wpf_get_form( $form_id ) {
		$post = get_post( (int) $form_id );
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			throw new \Exception( 'WPForms form not found.' );
		}
		$config = json_decode( (string) $post->post_content, true );
		$fields = array();
		if ( is_array( $config ) && isset( $config['fields'] ) && is_array( $config['fields'] ) ) {
			foreach ( $config['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$id = isset( $field['id'] ) ? (string) $field['id'] : '';
				if ( '' === $id ) {
					continue;
				}
				$fields[] = Normalizers::normalize_field(
					$id,
					(string) ( $field['label'] ?? '' ),
					(string) ( $field['type'] ?? '' ),
					! empty( $field['required'] )
				);
			}
		}
		return array(
			'provider' => 'wpforms',
			'id'       => (int) $post->ID,
			'title'    => (string) $post->post_title,
			'active'   => 'publish' === $post->post_status,
			'fields'   => $fields,
		);
	}

	public static function wpf_count( $form_id, $status = '' ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler || ! method_exists( $handler, 'get_entries' ) ) {

			return 0;
		}
		$args = array( 'form_id' => (int) $form_id, 'number' => 0 );
		if ( '' !== $status ) {
			$args['status'] = $status;
		}
		
		if ( method_exists( $handler, 'get_entries' ) ) {
			$args['count'] = true;
			$count         = $handler->get_entries( $args, true );
			if ( is_numeric( $count ) ) {
				return (int) $count;
			}
		}
		return 0;
	}

	public static function wpf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler ) {
			throw self::wpf_entries_unavailable();
		}
		$args = array(
			'form_id' => (int) $form_id,
			'number'  => (int) $per_page,
			'offset'  => (int) ( ( $page - 1 ) * $per_page ),
			'orderby' => 'entry_id',
			'order'   => 'DESC',
		);
		$wpf_status = self::wpf_map_status_filter( $status );
		if ( '' !== $wpf_status ) {
			$args['status'] = $wpf_status;
		}
		if ( ! empty( $range['start_date'] ) && ! empty( $range['end_date'] ) ) {
			$args['date'] = array( $range['start_date'], $range['end_date'] );
		}

		$total   = self::wpf_count( $form_id, $wpf_status );
		$entries = $handler->get_entries( $args );
		$rows    = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'wpforms',
				(int) ( $entry->entry_id ?? 0 ),
				(string) ( $entry->date ?? '' ),
				(string) ( $entry->status ?? 'published' ),
				! empty( $entry->viewed )
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function wpf_get_entry( $entry_id ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler || ! method_exists( $handler, 'get' ) ) {
			throw self::wpf_entries_unavailable();
		}
		$entry = $handler->get( (int) $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'WPForms entry not found.' );
		}
		
		$values = json_decode( (string) ( $entry->fields ?? '' ), true );
		return Normalizers::normalize_entry_detail(
			'wpforms',
			array(
				'id'           => (int) ( $entry->entry_id ?? 0 ),
				'form_id'      => (int) ( $entry->form_id ?? 0 ),
				'date_created' => (string) ( $entry->date ?? '' ),
				'status'       => (string) ( $entry->status ?? 'published' ),
				'values'       => is_array( $values ) ? $values : array(),
				
			)
		);
	}

	public static function wpf_get_stats( $form_id ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler ) {
			throw self::wpf_entries_unavailable();
		}
		$by_status = array();
		foreach ( array( 'published', 'spam', 'trash' ) as $status ) {
			$by_status[ $status ] = self::wpf_count( $form_id, $status );
		}
		return array(
			'provider'  => 'wpforms',
			'form_id'   => (int) $form_id,
			'total'     => self::wpf_count( $form_id ),
			'by_status' => $by_status,
		);
	}

	public static function wpf_update_entry_status( $entry_id, $status ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler || ! method_exists( $handler, 'get' ) || ! method_exists( $handler, 'update' ) ) {
			throw self::wpf_entries_unavailable();
		}
		$entry = $handler->get( (int) $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'WPForms entry not found.' );
		}
		
		if ( in_array( $status, array( 'read', 'unread' ), true ) ) {
			$prior = array( 'property' => 'viewed', 'value' => empty( $entry->viewed ) ? '0' : '1' );
			$data  = array( 'viewed' => 'read' === $status ? 1 : 0 );
		} elseif ( 'spam' === $status ) {
			$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'published' ) );
			$data  = array( 'status' => 'spam' );
		} elseif ( 'active' === $status ) {
			$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'published' ) );
			$data  = array( 'status' => 'published' );
		} else {
			throw new \Exception( 'WPForms does not support the "' . esc_html( $status ) . '" status. Supported: active, spam, read, unread.' );
		}
		$undo   = Normalizers::store_undo( 'wpforms', $entry_id, $prior, 'Restore WPForms entry #' . $entry_id . ' ' . $prior['property'] . ' to ' . $prior['value'] );
		$result = $handler->update( (int) $entry_id, $data );
		if ( false === $result ) {
			throw new \Exception( 'Failed to update WPForms entry status.' );
		}
		return array(
			'written'  => true,
			'provider' => 'wpforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	public static function wpf_trash_entry( $entry_id ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler || ! method_exists( $handler, 'get' ) || ! method_exists( $handler, 'update' ) ) {
			throw self::wpf_entries_unavailable();
		}
		$entry = $handler->get( (int) $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'WPForms entry not found.' );
		}
		$prior  = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'published' ) );
		$undo   = Normalizers::store_undo( 'wpforms', $entry_id, $prior, 'Restore WPForms entry #' . $entry_id . ' from trash to ' . $prior['value'] );
		
		$result = $handler->update( (int) $entry_id, array( 'status' => 'trash' ) );
		if ( false === $result ) {
			throw new \Exception( 'Failed to trash WPForms entry.' );
		}
		return array(
			'written'  => true,
			'provider' => 'wpforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	public static function wpf_undo( $entry_id, $property, $value ) {
		$handler = self::wpf_entry_handler();
		if ( ! $handler || ! method_exists( $handler, 'update' ) ) {
			throw self::wpf_entries_unavailable();
		}
		$data   = array( $property => ( 'viewed' === $property ? (int) $value : $value ) );
		$result = $handler->update( (int) $entry_id, $data );
		if ( false === $result ) {
			throw new \Exception( 'Failed to restore WPForms entry.' );
		}
	}

	public static function wpf_map_status_filter( $status ) {
		
		if ( 'spam' === $status ) {
			return 'spam';
		}
		if ( 'trash' === $status ) {
			return 'trash';
		}
		if ( 'active' === $status ) {
			return 'published';
		}
		
		return '';
	}
}
