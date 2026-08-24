<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gravity_Forms_Adapter {

	public static function gf_list_forms() {
		$forms = \GFAPI::get_forms();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			$out[]   = array(
				'provider'    => 'gravityforms',
				'id'          => $form_id,
				'title'       => isset( $form['title'] ) ? (string) $form['title'] : '',
				'active'      => empty( $form['is_active'] ) ? false : (bool) $form['is_active'],
				'entry_count' => $form_id > 0 ? (int) \GFAPI::count_entries( $form_id, array() ) : 0,
			);
		}
		return $out;
	}

	public static function gf_get_form( $form_id ) {
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			throw new \Exception( 'Gravity Forms form not found.' );
		}
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			
			$fields[] = Normalizers::normalize_field(
				(string) ( is_object( $field ) ? ( $field->id ?? '' ) : ( $field['id'] ?? '' ) ),
				(string) ( is_object( $field ) ? ( $field->label ?? '' ) : ( $field['label'] ?? '' ) ),
				(string) ( is_object( $field ) ? ( $field->type ?? '' ) : ( $field['type'] ?? '' ) ),
				(bool) ( is_object( $field ) ? ( $field->isRequired ?? false ) : ( $field['isRequired'] ?? false ) )
			);
		}
		return array(
			'provider' => 'gravityforms',
			'id'       => (int) ( $form['id'] ?? $form_id ),
			'title'    => (string) ( $form['title'] ?? '' ),
			'active'   => empty( $form['is_active'] ) ? false : (bool) $form['is_active'],
			'fields'   => $fields,
		);
	}

	public static function gf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$search = array();

		if ( in_array( $status, array( 'active', 'spam', 'trash' ), true ) ) {
			$search['status'] = $status;
		}
		if ( ! empty( $range['start_date'] ) ) {
			$search['start_date'] = $range['start_date'];
		}
		if ( ! empty( $range['end_date'] ) ) {
			$search['end_date'] = $range['end_date'];
		}
		$sorting     = array( 'key' => 'id', 'direction' => 'DESC' );
		$paging      = array( 'offset' => ( $page - 1 ) * $per_page, 'page_size' => $per_page );
		$total_count = 0;
		$entries     = \GFAPI::get_entries( $form_id, $search, $sorting, $paging, $total_count );
		if ( is_wp_error( $entries ) ) {
			throw new \Exception( 'Gravity Forms entry query failed: ' . esc_html( $entries->get_error_message() ) );
		}
		$rows = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'gravityforms',
				(int) ( $entry['id'] ?? 0 ),
				(string) ( $entry['date_created'] ?? '' ),
				(string) ( $entry['status'] ?? 'active' ),
				! empty( $entry['is_read'] )
			);
		}
		return Normalizers::paginate( (int) $total_count, $page, $per_page, $rows );
	}

	public static function gf_get_entry( $entry_id ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		return Normalizers::normalize_entry_detail( 'gravityforms', self::gf_redact( (array) $entry ) );
	}

	public static function gf_get_stats( $form_id ) {
		$by_status = array();
		foreach ( array( 'active', 'spam', 'trash' ) as $status ) {
			$by_status[ $status ] = (int) \GFAPI::count_entries( $form_id, array( 'status' => $status ) );
		}
		return array(
			'provider'  => 'gravityforms',
			'form_id'   => (int) $form_id,
			'total'     => (int) \GFAPI::count_entries( $form_id, array() ),
			'by_status' => $by_status,
		);
	}

	public static function gf_update_entry_status( $entry_id, $status ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		
		if ( in_array( $status, array( 'read', 'unread' ), true ) ) {
			$property = 'is_read';
			$value    = 'read' === $status ? '1' : '0';
			$prior    = array( 'property' => 'is_read', 'value' => empty( $entry['is_read'] ) ? '0' : '1' );
		} else {
			$property = 'status';
			$value    = $status;
			$prior    = array( 'property' => 'status', 'value' => (string) ( $entry['status'] ?? 'active' ) );
		}
		$undo   = Normalizers::store_undo( 'gravityforms', $entry_id, $prior, 'Restore Gravity Forms entry #' . $entry_id . ' ' . $prior['property'] . ' to ' . $prior['value'] );
		$result = \GFAPI::update_entry_property( $entry_id, $property, $value );
		if ( is_wp_error( $result ) || false === $result ) {
			throw new \Exception( 'Failed to update Gravity Forms entry status.' );
		}
		return array(
			'written'  => true,
			'provider' => 'gravityforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	public static function gf_trash_entry( $entry_id ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		$prior  = array( 'property' => 'status', 'value' => (string) ( $entry['status'] ?? 'active' ) );
		$undo   = Normalizers::store_undo( 'gravityforms', $entry_id, $prior, 'Restore Gravity Forms entry #' . $entry_id . ' from trash to ' . $prior['value'] );
		
		$result = \GFAPI::update_entry_property( $entry_id, 'status', 'trash' );
		if ( is_wp_error( $result ) || false === $result ) {
			throw new \Exception( 'Failed to trash Gravity Forms entry.' );
		}
		return array(
			'written'  => true,
			'provider' => 'gravityforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	public static function gf_redact( array $entry ) {
		foreach ( array( 'ip', 'user_agent', 'payment_status', 'payment_amount', 'payment_date', 'payment_method', 'transaction_id', 'transaction_type' ) as $key ) {
			unset( $entry[ $key ] );
		}
		return $entry;
	}
}
