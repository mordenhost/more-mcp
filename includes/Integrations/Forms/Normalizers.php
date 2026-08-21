<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Normalizers {

	public static function normalize_field( $id, $label, $type, $required ) {
		return array(
			'id'       => (string) $id,
			'label'    => (string) $label,
			'type'     => (string) $type,
			'required' => (bool) $required,
		);
	}

	public static function normalize_entry_summary( $provider, $id, $date, $status, $is_read ) {

		return array(
			'provider' => (string) $provider,
			'id'       => (int) $id,
			'date'     => (string) $date,
			'status'   => (string) $status,
			'is_read'  => (bool) $is_read,
		);
	}

	public static function normalize_entry_detail( $provider, array $entry ) {
		$entry['provider'] = (string) $provider;
		return $entry;
	}

	public static function paginate( $total, $page, $per_page, array $rows ) {
		return array(
			'total'    => (int) $total,
			'page'     => (int) $page,
			'per_page' => (int) $per_page,
			'pages'    => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'returned' => count( $rows ),
			'has_more' => ( ( $page - 1 ) * $per_page + count( $rows ) ) < $total,
			'entries'  => $rows,
		);
	}

	public static function date_range( $args ) {
		$range = array( 'start_date' => '', 'end_date' => '' );
		foreach ( array( 'start_date', 'end_date' ) as $key ) {
			if ( empty( $args[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $args[ $key ] );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				throw new \Exception( $key . ' must be a valid YYYY-MM-DD date.' );
			}
			$range[ $key ] = $value;
		}
		if ( '' !== $range['start_date'] && '' !== $range['end_date'] && $range['start_date'] > $range['end_date'] ) {
			throw new \Exception( 'start_date must not be later than end_date.' );
		}
		return $range;
	}

	public static function store_undo( $provider, $entry_id, array $prior, $summary ) {
		return \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'forms_entry_write',
				'summary'      => (string) $summary,
				'target'       => array( 'provider' => (string) $provider, 'entry_id' => (int) $entry_id ),
				'pre_op_state' => $prior, 
			)
		);
	}
}
