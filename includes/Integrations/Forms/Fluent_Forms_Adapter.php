<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fluent_Forms_Adapter {

	

	

	

	public static function ff_form_model() {
		$class = '\FluentForm\App\Models\Form';
		return class_exists( $class ) ? $class : null;
	}

	public static function ff_submission_model() {
		$class = '\FluentForm\App\Models\Submission';
		return class_exists( $class ) ? $class : null;
	}

	public static function ff_unavailable() {
		return new \Exception( 'Fluent Forms read API is unavailable in this version. Its model/query surface could not be resolved; verify against the installed plugin.' );
	}

	public static function ff_list_forms() {
		$model = self::ff_form_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$forms = $model::query()->get();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			$form_id = (int) ( $form->id ?? 0 );
			$out[]   = array(
				'provider'    => 'fluentforms',
				'id'          => $form_id,
				'title'       => (string) ( $form->title ?? '' ),
				'active'      => isset( $form->status ) ? ( 'published' === $form->status ) : true,
				'entry_count' => $form_id > 0 ? self::ff_count( $form_id ) : 0,
			);
		}
		return $out;
	}

	public static function ff_get_form( $form_id ) {
		$model = self::ff_form_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$form = $model::query()->find( $form_id );
		if ( ! $form ) {
			throw new \Exception( 'Fluent Forms form not found.' );
		}
		$fields  = array();
		$decoded = json_decode( (string) ( $form->form_fields ?? '' ), true );
		if ( is_array( $decoded ) && isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ) {
			$fields = self::ff_flatten_fields( $decoded['fields'] );
		}
		return array(
			'provider' => 'fluentforms',
			'id'       => (int) ( $form->id ?? $form_id ),
			'title'    => (string) ( $form->title ?? '' ),
			'active'   => isset( $form->status ) ? ( 'published' === $form->status ) : true,
			'fields'   => $fields,
		);
	}

	public static function ff_flatten_fields( array $nodes ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['fields'] ) && is_array( $node['fields'] ) ) {
				$out = array_merge( $out, self::ff_flatten_fields( $node['fields'] ) );
				continue;
			}
			if ( isset( $node['columns'] ) && is_array( $node['columns'] ) ) {
				foreach ( $node['columns'] as $column ) {
					if ( isset( $column['fields'] ) && is_array( $column['fields'] ) ) {
						$out = array_merge( $out, self::ff_flatten_fields( $column['fields'] ) );
					}
				}
				continue;
			}
			$attributes = isset( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : array();
			$settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$name       = (string) ( $attributes['name'] ?? ( $node['uniqElKey'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out[] = Normalizers::normalize_field(
				$name,
				(string) ( $settings['label'] ?? ( $settings['admin_field_label'] ?? '' ) ),
				(string) ( $node['element'] ?? '' ),
				! empty( $settings['validation_rules']['required']['value'] )
			);
		}
		return $out;
	}

	public static function ff_count( $form_id, $status = '' ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			return 0;
		}
		$query = $model::query()->where( 'form_id', $form_id );
		if ( '' !== $status ) {
			$query = $query->where( 'status', $status );
		}
		return (int) $query->count();
	}

	public static function ff_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$ff_status = self::ff_map_status_filter( $status );
		$query     = $model::query()->where( 'form_id', $form_id );
		if ( '' !== $ff_status ) {
			$query = $query->where( 'status', $ff_status );
		}
		if ( ! empty( $range['start_date'] ) ) {
			$query = $query->where( 'created_at', '>=', $range['start_date'] . ' 00:00:00' );
		}
		if ( ! empty( $range['end_date'] ) ) {
			$query = $query->where( 'created_at', '<=', $range['end_date'] . ' 23:59:59' );
		}
		$total   = (int) $query->count();
		$entries = $query->orderBy( 'id', 'DESC' )->skip( ( $page - 1 ) * $per_page )->take( $per_page )->get();
		$rows    = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'fluentforms',
				(int) ( $entry->id ?? 0 ),
				(string) ( $entry->created_at ?? '' ),
				(string) ( $entry->status ?? 'unread' ),
				isset( $entry->status ) ? ( 'unread' !== $entry->status ) : false
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function ff_get_entry( $entry_id ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}
		$values = json_decode( (string) ( $entry->response ?? '' ), true );
		return Normalizers::normalize_entry_detail(
			'fluentforms',
			array(
				'id'           => (int) ( $entry->id ?? 0 ),
				'form_id'      => (int) ( $entry->form_id ?? 0 ),
				'date_created' => (string) ( $entry->created_at ?? '' ),
				'status'       => (string) ( $entry->status ?? 'unread' ),
				'values'       => is_array( $values ) ? $values : array(),
				
			)
		);
	}

	public static function ff_get_stats( $form_id ) {
		$by_status = array();
		foreach ( array( 'unread', 'read', 'trashed' ) as $status ) {
			$by_status[ $status ] = self::ff_count( $form_id, $status );
		}
		return array(
			'provider'  => 'fluentforms',
			'form_id'   => (int) $form_id,
			'total'     => self::ff_count( $form_id ),
			'by_status' => $by_status,
		);
	}

	public static function ff_update_entry_status( $entry_id, $status ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}

		$ff_status = self::ff_map_status_write( $status );
		if ( null === $ff_status ) {
			throw new \Exception( 'Fluent Forms does not support the "' . esc_html( $status ) . '" status. Supported: read, unread.' );
		}
		$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'unread' ) );
		$undo  = Normalizers::store_undo( 'fluentforms', $entry_id, $prior, 'Restore Fluent Forms submission #' . $entry_id . ' status to ' . $prior['value'] );
		$entry->status = $ff_status;
		$entry->save();
		return array(
			'written'  => true,
			'provider' => 'fluentforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	public static function ff_trash_entry( $entry_id ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}
		$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'unread' ) );
		$undo  = Normalizers::store_undo( 'fluentforms', $entry_id, $prior, 'Restore Fluent Forms submission #' . $entry_id . ' from trash to ' . $prior['value'] );
		$entry->status = 'trashed';
		$entry->save();
		return array(
			'written'  => true,
			'provider' => 'fluentforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	public static function ff_map_status_filter( $status ) {
		
		if ( 'read' === $status ) {
			return 'read';
		}
		if ( 'unread' === $status ) {
			return 'unread';
		}
		if ( 'trash' === $status ) {
			return 'trashed';
		}
		return ''; 
	}

	public static function ff_map_status_write( $status ) {
		if ( 'read' === $status ) {
			return 'read';
		}
		if ( 'unread' === $status ) {
			return 'unread';
		}
		return null; 
	}
}
