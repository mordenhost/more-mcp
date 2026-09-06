<?php

namespace More_MCP\Integrations\Forms;

use More_MCP\Integrations\Write_Verify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Adapter {

	const QUERY_CLASS = '\ElementorPro\Modules\Forms\Submissions\Database\Query';

	public static function el_available() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! class_exists( self::QUERY_CLASS ) ) {
			return false;
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$table = $wpdb->prefix . 'e_submissions';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	public static function el_unavailable() {
		return new \Exception( 'Elementor Pro Forms submissions are unavailable. Verify Elementor Pro is active and its Submissions feature is enabled.' );
	}

	private static function query() {
		if ( ! self::el_available() ) {
			throw self::el_unavailable();
		}
		$cls = self::QUERY_CLASS;
		return $cls::get_instance();
	}

	
	public static function el_list_forms() {
		if ( ! self::el_available() ) {
			throw self::el_unavailable();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		
		$rows = $wpdb->get_results(
			"SELECT post_id, element_id, COUNT(*) AS cnt, MAX(form_name) AS form_name
			 FROM `{$table}`
			 GROUP BY post_id, element_id
			 ORDER BY cnt DESC" 
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'provider'    => 'elementor',
				'id'          => (string) $r->post_id . '_' . (string) $r->element_id,
				'title'       => (string) ( $r->form_name ?: ( 'Form ' . $r->element_id ) ),
				'active'      => true,
				'entry_count' => (int) $r->cnt,
			);
		}
		return $out;
	}

	public static function el_get_form( $form_id ) {
		
		list( $post_id, $element_id ) = self::split_form_id( $form_id );
		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		$name  = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(form_name) FROM `{$table}` WHERE post_id = %d AND element_id = %s",
			$post_id,
			$element_id
		) );

		
		$fields = array();
		$latest = self::query()->get_submissions( array(
			'page'     => 1,
			'per_page' => 1,
			'filters'  => array( 'form' => array( 'value' => $post_id . '_' . $element_id ) ),
			'order'    => array(),
		) );
		$data = isset( $latest['data'] ) && is_array( $latest['data'] ) ? $latest['data'] : array();
		if ( ! empty( $data[0]['values'] ) && is_array( $data[0]['values'] ) ) {
			foreach ( $data[0]['values'] as $v ) {
				$key = isset( $v['key'] ) ? (string) $v['key'] : '';
				if ( '' === $key ) {
					continue;
				}
				$fields[] = Normalizers::normalize_field( $key, $key, 'text', false );
			}
		}
		return array(
			'provider' => 'elementor',
			'id'       => (string) $form_id,
			'title'    => (string) ( $name ?: ( 'Form ' . $element_id ) ),
			'active'   => true,
			'fields'   => $fields,
		);
	}

	public static function el_list_entries( $form_id, $status, $range, $page, $per_page ) {
		list( $post_id, $element_id ) = self::split_form_id( $form_id );
		$filters = array( 'form' => array( 'value' => $post_id . '_' . $element_id ) );

		$status_filter = self::el_map_status_filter( $status );
		if ( 'spam' === $status ) {

			
			return array_merge(
				Normalizers::paginate( 0, $page, $per_page, array() ),
				array( 'note' => 'Elementor Pro Forms has no spam state; no submissions match a spam filter.' )
			);
		}
		if ( '' !== $status_filter ) {
			$filters['status'] = array( 'value' => $status_filter );
		}

		if ( ! empty( $range['start_date'] ) || ! empty( $range['end_date'] ) ) {
			
			$after  = ! empty( $range['start_date'] ) ? $range['start_date'] . ' 00:00:00' : '';
			$before = ! empty( $range['end_date'] ) ? $range['end_date'] . ' 23:59:59' : '';
			if ( '' !== $after || '' !== $before ) {
				$filters['after']  = $after;
				$filters['before'] = $before;
			}
		}

		$result = self::query()->get_submissions( array(
			'page'     => (int) $page,
			'per_page' => (int) $per_page,
			'filters'  => $filters,
			'order'    => array( 'by' => 'id', 'direction' => 'desc' ),
		) );

		$data  = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		$total = isset( $result['meta']['pagination']['total'] ) ? (int) $result['meta']['pagination']['total'] : count( $data );

		$rows = array();
		foreach ( $data as $row ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'elementor',
				(int) ( $row['id'] ?? 0 ),
				(string) ( $row['created_at'] ?? '' ),
				(string) ( $row['status'] ?? '' ),
				! empty( $row['is_read'] )
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function el_get_entry( $entry_id ) {
		$result = self::query()->get_submission( (int) $entry_id );
		if ( ! $result || empty( $result['data'] ) ) {
			throw new \Exception( 'Elementor Pro Forms submission not found.' );
		}
		$data = $result['data'];

		
		
		$values = array();
		if ( ! empty( $data['values'] ) && is_array( $data['values'] ) ) {
			foreach ( $data['values'] as $v ) {
				$key = isset( $v['key'] ) ? (string) $v['key'] : '';
				if ( '' === $key ) {
					continue;
				}
				$values[ $key ] = isset( $v['value'] ) ? $v['value'] : '';
			}
		}

		$form_element = isset( $data['form']['element_id'] ) ? (string) $data['form']['element_id'] : '';
		$form_post    = isset( $data['post']['post_id'] ) ? (int) $data['post']['post_id'] : 0;

		return Normalizers::normalize_entry_detail(
			'elementor',
			array(
				'id'           => (int) ( $data['id'] ?? $entry_id ),
				'form_id'      => ( $form_post ? $form_post . '_' . $form_element : $form_element ),
				'date_created' => (string) ( $data['created_at'] ?? '' ),
				'status'       => (string) ( $data['status'] ?? '' ),
				'is_read'      => ! empty( $data['is_read'] ),
				'values'       => $values,
				
			)
		);
	}

	public static function el_get_stats( $form_id ) {
		list( $post_id, $element_id ) = self::split_form_id( $form_id );
		$counts = self::query()->count_submissions_by_status( array(
			'form' => array( 'value' => $post_id . '_' . $element_id ),
		) );

		$by = self::collection_to_array( $counts );
		return array(
			'provider'  => 'elementor',
			'form_id'   => (string) $form_id,
			'total'     => (int) ( $by['all'] ?? 0 ),
			'by_status' => array(
				'new'    => (int) ( $by['all'] ?? 0 ),
				'trash'  => (int) ( $by['trash'] ?? 0 ),
				'read'   => (int) ( $by['read'] ?? 0 ),
				'unread' => (int) ( $by['unread'] ?? 0 ),
			),
		);
	}

	public static function el_update_entry_status( $entry_id, $status ) {
		$entry_id = (int) $entry_id;
		$existing = self::query()->get_submission( $entry_id );
		if ( ! $existing || empty( $existing['data'] ) ) {
			throw new \Exception( 'Elementor Pro Forms submission not found.' );
		}
		$prior_status  = (string) ( $existing['data']['status'] ?? '' );
		$prior_is_read = ! empty( $existing['data']['is_read'] );

		if ( 'spam' === $status ) {
			throw new \Exception(
				'Elementor Pro Forms has no spam state. Supported: "active" (restore from trash), "read", "unread". Use forms_trash_entry to trash.'
			);
		}

		if ( 'active' === $status ) {
			
			$prior = array( 'property' => 'status', 'value' => $prior_status );
			self::query()->restore( $entry_id );

			

			
			$fresh  = self::query()->get_submission( $entry_id );
			$verify = Normalizers::verify_write(
				( is_array( $fresh ) && ! empty( $fresh['data'] ) )
					? ( 'trash' !== (string) ( $fresh['data']['status'] ?? '' ) )
					: Write_Verify::UNREADABLE,
				true,
				'status',
				'trash' !== $prior_status
			);

			$undo = Normalizers::store_undo( 'elementor', $entry_id, $prior, 'Restore Elementor submission #' . $entry_id . ' status to ' . $prior_status );

			return array_merge(
				array(
					'written'  => true,
					'provider' => 'elementor',
					'entry_id' => $entry_id,
					'status'   => $status,
				),
				$verify,
				array( 'undo' => $undo )
			);
		}

		if ( 'read' === $status || 'unread' === $status ) {
			$prior  = array( 'property' => 'is_read', 'value' => $prior_is_read ? '1' : '0' );
			$target = ( 'read' === $status ) ? 1 : 0;
			self::query()->update_submission( $entry_id, array( 'is_read' => $target ) );

			

			
			$fresh  = self::query()->get_submission( $entry_id );
			$verify = Normalizers::verify_write(
				( is_array( $fresh ) && ! empty( $fresh['data'] ) )
					? ( empty( $fresh['data']['is_read'] ) ? 0 : 1 )
					: Write_Verify::UNREADABLE,
				$target,
				'is_read',
				$prior_is_read ? 1 : 0
			);

			$undo = Normalizers::store_undo( 'elementor', $entry_id, $prior, 'Restore Elementor submission #' . $entry_id . ' is_read to ' . $prior['value'] );

			return array_merge(
				array(
					'written'  => true,
					'provider' => 'elementor',
					'entry_id' => $entry_id,
					'status'   => $status,
				),
				$verify,
				array( 'undo' => $undo )
			);
		}

		throw new \Exception( 'status must be one of: active, read, unread (Elementor Pro Forms does not support spam).' );
	}

	public static function el_trash_entry( $entry_id ) {
		$entry_id = (int) $entry_id;
		$existing = self::query()->get_submission( $entry_id );
		if ( ! $existing || empty( $existing['data'] ) ) {
			throw new \Exception( 'Elementor Pro Forms submission not found.' );
		}
		$prior = array( 'property' => 'status', 'value' => (string) ( $existing['data']['status'] ?? 'new' ) );
		
		self::query()->move_to_trash_submission( $entry_id );

		$fresh  = self::query()->get_submission( $entry_id );
		$verify = Normalizers::verify_write(
			( is_array( $fresh ) && ! empty( $fresh['data'] ) )
				? (string) ( $fresh['data']['status'] ?? '' )
				: Write_Verify::UNREADABLE,
			'trash',
			'status',
			$prior['value']
		);

		$undo = Normalizers::store_undo( 'elementor', $entry_id, $prior, 'Restore Elementor submission #' . $entry_id . ' from trash to ' . $prior['value'] );

		return array_merge(
			array(
				'written'  => true,
				'provider' => 'elementor',
				'entry_id' => $entry_id,
				'action'   => 'trash',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function el_undo( $entry_id, $property, $value ) {
		$entry_id = (int) $entry_id;
		if ( 'status' === $property ) {
			if ( 'trash' === $value ) {
				self::query()->move_to_trash_submission( $entry_id );
			} else {

				self::query()->restore( $entry_id );
			}
			return;
		}
		if ( 'is_read' === $property ) {
			self::query()->update_submission( $entry_id, array( 'is_read' => ( '1' === (string) $value ) ? 1 : 0 ) );
			return;
		}
		throw new \Exception( 'Unexpected Elementor Forms undo property: ' . esc_html( $property ) );
	}

	public static function el_map_status_filter( $status ) {
		
		if ( 'active' === $status ) {
			return 'all';   
		}
		if ( 'unread' === $status ) {
			return 'unread';
		}
		if ( 'read' === $status ) {
			return 'read';
		}
		if ( 'trash' === $status ) {
			return 'trash';
		}
		
		return '';
	}

	private static function split_form_id( $form_id ) {
		$form_id = (string) $form_id;
		if ( false !== strpos( $form_id, '_' ) ) {
			$parts      = explode( '_', $form_id, 2 );
			$post_id    = (int) $parts[0];
			$element_id = (string) $parts[1];
			return array( $post_id, $element_id );
		}

		
		throw new \Exception( 'Elementor form_id must be the composite "<post_id>_<element_id>" from forms_list.' );
	}

	private static function collection_to_array( $counts ) {
		if ( is_array( $counts ) ) {
			return $counts;
		}
		if ( is_object( $counts ) && method_exists( $counts, 'all' ) ) {
			$all = $counts->all();
			return is_array( $all ) ? $all : array();
		}
		if ( is_object( $counts ) ) {
			return get_object_vars( $counts );
		}
		return array();
	}
}
