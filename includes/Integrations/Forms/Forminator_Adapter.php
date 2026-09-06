<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forminator_Adapter {

	const FORM_POST_TYPE = 'forminator_forms';

	const INTERNAL_META = array( '_forminator_user_ip', '_forminator_nonce', '_forminator_choice_values' );

	public static function forminator_available() {
		return class_exists( '\Forminator_Base_Form_Model' )
			&& class_exists( '\Forminator_Form_Model' )
			&& class_exists( '\Forminator_Form_Entry_Model' );
	}

	public static function forminator_unavailable() {
		return new \Exception( 'Forminator is unavailable. Verify the plugin is active and up to date.' );
	}

	public static function forminator_list_forms() {
		if ( ! self::forminator_available() ) {
			throw self::forminator_unavailable();
		}
		$model_class = '\Forminator_Form_Model';
		if ( ! method_exists( $model_class, 'model' ) ) {
			throw self::forminator_unavailable();
		}
		$out  = array();
		$page = 1;

		do {
			$batch = $model_class::model()->get_all_paged( $page, 50, 'publish' );
			$models = ( is_array( $batch ) && isset( $batch['models'] ) && is_array( $batch['models'] ) ) ? $batch['models'] : array();
			foreach ( $models as $model ) {
				if ( ! is_object( $model ) ) {
					continue;
				}
				$form_id = (int) ( $model->id ?? 0 );
				if ( $form_id <= 0 ) {
					continue;
				}
				$out[] = array(
					'provider'    => 'forminator',
					'id'          => $form_id,
					'title'       => self::forminator_form_title( $model, $form_id ),
					'active'      => true,
					'entry_count' => self::forminator_count( $form_id ),
				);
			}
			$total_pages = ( is_array( $batch ) && isset( $batch['totalPages'] ) ) ? (int) $batch['totalPages'] : 1;
			++$page;
		} while ( $page <= $total_pages && $page <= 100 );
		return $out;
	}

	public static function forminator_get_form( $form_id ) {
		if ( ! self::forminator_available() ) {
			throw self::forminator_unavailable();
		}
		$model = \Forminator_Base_Form_Model::get_model( (int) $form_id );
		if ( ! is_object( $model ) || self::FORM_POST_TYPE !== ( get_post_type( (int) $form_id ) ?: '' ) ) {
			throw new \Exception( 'Forminator form not found.' );
		}
		$fields = array();
		
		$real = method_exists( $model, 'get_real_fields' ) ? $model->get_real_fields() : array();
		foreach ( (array) $real as $field ) {
			if ( ! is_object( $field ) || ! method_exists( $field, 'to_formatted_array' ) ) {
				continue;
			}
			$fa   = (array) $field->to_formatted_array();
			$slug = (string) ( $fa['element_id'] ?? $fa['id'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$fields[] = Normalizers::normalize_field(
				$slug,
				(string) ( $fa['field_label'] ?? '' ),
				(string) ( $fa['type'] ?? '' ),
				! empty( $fa['required'] )
			);
		}
		return array(
			'provider' => 'forminator',
			'id'       => (int) $form_id,
			'title'    => self::forminator_form_title( $model, (int) $form_id ),
			'active'   => true,
			'fields'   => $fields,
		);
	}

	private static function forminator_form_title( $model, $form_id ) {
		if ( is_object( $model ) ) {
			if ( isset( $model->settings ) && is_array( $model->settings ) && ! empty( $model->settings['formName'] ) ) {
				return (string) $model->settings['formName'];
			}
			if ( ! empty( $model->name ) ) {
				return (string) $model->name;
			}
		}
		return 'Form #' . (int) $form_id;
	}

	public static function forminator_count( $form_id, $all_types = false ) {
		if ( ! class_exists( '\Forminator_Form_Entry_Model' ) ) {
			return 0;
		}
		
		return (int) \Forminator_Form_Entry_Model::count_entries( (int) $form_id, false, (bool) $all_types );
	}

	public static function forminator_list_entries( $form_id, $status, $range, $page, $per_page ) {
		if ( ! self::forminator_available() ) {
			throw self::forminator_unavailable();
		}

		
		$unsupported = in_array( $status, array( 'read', 'unread', 'trash' ), true );
		if ( $unsupported ) {
			$empty        = Normalizers::paginate( 0, $page, $per_page, array() );
			$empty['note'] = 'Forminator has no "' . $status . '" state (it stores active / draft / abandoned / spam only), so this filter matches no entries.';
			return $empty;
		}

		$args = array(
			'form_id'  => (int) $form_id,
			'per_page' => (int) $per_page,
			'offset'   => ( max( 1, (int) $page ) - 1 ) * (int) $per_page,
			'order_by' => 'entries.entry_id',
			'order'    => 'DESC',
		);
		if ( 'spam' === $status ) {
			$args['status'] = 'spam';
		} elseif ( 'active' === $status ) {
			$args['status'] = 'active';
		} else {
			
			$args['is_spam'] = 0;
		}
		if ( ! empty( $range['start_date'] ) && ! empty( $range['end_date'] ) ) {

			$args['date_created'] = array( $range['start_date'], $range['end_date'] );
		}

		$result = \Forminator_Form_Entry_Model::query_entries( $args, true );
		$total  = ( is_array( $result ) && isset( $result['count'] ) ) ? (int) $result['count'] : 0;
		$models = ( is_array( $result ) && isset( $result['data'] ) && is_array( $result['data'] ) ) ? $result['data'] : array();

		$rows = array();
		foreach ( $models as $entry ) {
			if ( ! is_object( $entry ) || (int) ( $entry->entry_id ?? 0 ) <= 0 ) {
				continue;
			}
			$rows[] = Normalizers::normalize_entry_summary(
				'forminator',
				(int) $entry->entry_id,
				(string) ( $entry->date_created_sql ?? $entry->date_created ?? '' ),
				self::forminator_entry_status( $entry ),
				
				false
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function forminator_get_entry( $entry_id ) {
		if ( ! self::forminator_available() ) {
			throw self::forminator_unavailable();
		}
		$entry = new \Forminator_Form_Entry_Model( (int) $entry_id );
		if ( ! is_object( $entry ) || (int) ( $entry->entry_id ?? 0 ) <= 0 ) {
			throw new \Exception( 'Forminator submission not found.' );
		}

		$values = array();
		$meta   = isset( $entry->meta_data ) && is_array( $entry->meta_data ) ? $entry->meta_data : array();
		foreach ( $meta as $key => $row ) {
			$key = (string) $key;

			
			if ( in_array( $key, self::INTERNAL_META, true ) ) {
				continue;
			}
			if ( self::forminator_is_payment_key( $key ) ) {
				continue;
			}
			$values[ $key ] = is_array( $row ) && array_key_exists( 'value', $row ) ? $row['value'] : $row;
		}

		return Normalizers::normalize_entry_detail(
			'forminator',
			array(
				'id'           => (int) $entry->entry_id,
				'form_id'      => (int) ( $entry->form_id ?? 0 ),
				'date_created' => (string) ( $entry->date_created_sql ?? $entry->date_created ?? '' ),
				'status'       => self::forminator_entry_status( $entry ),
				'values'       => $values,
				
			)
		);
	}

	public static function forminator_get_stats( $form_id ) {
		if ( ! self::forminator_available() ) {
			throw self::forminator_unavailable();
		}
		$active    = self::forminator_count( $form_id, false );
		$all_non_spam = self::forminator_count( $form_id, true );
		return array(
			'provider'  => 'forminator',
			'form_id'   => (int) $form_id,

			'total'     => $all_non_spam,
			'by_status' => array(
				'active'       => $active,
				'non_spam_all' => $all_non_spam,
			),
		);
	}

	public static function forminator_update_entry_status( $entry_id, $status ) {
		throw new \Exception(
			'Forminator exposes no supported API to change an existing submission\'s status. '
			. 'Its status (active / draft / abandoned / spam) is set only at submission time and cannot be '
			. 'safely re-written afterward through the plugin, so forms_update_entry_status is not supported for Forminator. '
			. 'Reading submissions (forms_list_entries, forms_get_entry, forms_get_stats) is fully supported.'
		);
	}

	public static function forminator_trash_entry( $entry_id ) {
		throw new \Exception(
			'Forminator has no trash: its only deletion path removes the submission permanently and irreversibly. '
			. 'forms_trash_entry promises a recoverable trash, so it is not supported for Forminator (this tool will not '
			. 'perform a permanent delete). Reading submissions is fully supported.'
		);
	}

	
	private static function forminator_entry_status( $entry ) {
		if ( is_object( $entry ) && ! empty( $entry->status ) ) {
			return (string) $entry->status;
		}
		if ( is_object( $entry ) && ! empty( $entry->is_spam ) ) {
			return 'spam';
		}
		return 'active';
	}

	private static function forminator_is_payment_key( $key ) {
		$key = (string) $key;
		return 0 === strpos( $key, 'stripe' ) || 0 === strpos( $key, 'paypal' );
	}
}
