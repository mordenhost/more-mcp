<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Formidable_Adapter {

	public static function frm_available() {
		return class_exists( '\FrmEntry' ) && class_exists( '\FrmForm' ) && class_exists( '\FrmField' );
	}

	public static function frm_unavailable() {
		return new \Exception( 'Formidable Forms is unavailable. Verify the plugin is active and up to date.' );
	}

	public static function frm_list_forms() {
		if ( ! self::frm_available() || ! method_exists( '\FrmForm', 'get_published_forms' ) ) {
			throw self::frm_unavailable();
		}
		$forms = \FrmForm::get_published_forms();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			if ( ! is_object( $form ) || empty( $form->id ) ) {
				continue;
			}
			$form_id = (int) $form->id;
			$out[]   = array(
				'provider'    => 'formidable',
				'id'          => $form_id,
				'title'       => (string) ( $form->name ?? '' ),
				'active'      => true,
				'entry_count' => self::frm_count( $form_id ),
			);
		}
		return $out;
	}

	public static function frm_get_form( $form_id ) {
		if ( ! self::frm_available() ) {
			throw self::frm_unavailable();
		}
		$form = \FrmForm::getOne( (int) $form_id );
		if ( ! is_object( $form ) || empty( $form->id ) ) {
			throw new \Exception( 'Formidable form not found.' );
		}

		$fields = array();
		if ( method_exists( '\FrmField', 'get_all_for_form' ) ) {
			foreach ( (array) \FrmField::get_all_for_form( (int) $form_id ) as $field ) {
				$type = (string) ( $field->type ?? '' );

				
				
				if ( in_array( $type, self::frm_no_save_fields(), true ) ) {
					continue;
				}
				$fields[] = Normalizers::normalize_field(
					(string) $field->id,
					(string) ( $field->name ?? '' ),
					$type,
					self::frm_field_required( $field )
				);
			}
		}

		return array(
			'provider' => 'formidable',
			'id'       => (int) $form->id,
			'title'    => (string) ( $form->name ?? '' ),
			'active'   => isset( $form->status ) ? in_array( $form->status, array( null, '', 'published' ), true ) : true,
			'fields'   => $fields,
		);
	}

	public static function frm_count( $form_id ) {
		if ( ! self::frm_available() || ! method_exists( '\FrmEntry', 'getRecordCount' ) ) {
			return 0;
		}
		
		return (int) \FrmEntry::getRecordCount( (int) $form_id );
	}

	public static function frm_map_status_filter( $status ) {

		if ( 'active' === $status ) {
			return 0;
		}
		if ( 'trash' === $status ) {
			return -1; 
		}
		return null; 
	}

	public static function frm_list_entries( $form_id, $status, $range, $page, $per_page ) {
		if ( ! self::frm_available() || ! method_exists( '\FrmEntry', 'getAll' ) ) {
			throw self::frm_unavailable();
		}
		$is_draft = self::frm_map_status_filter( $status );
		if ( -1 === $is_draft ) {

			return Normalizers::paginate( 0, $page, $per_page, array() );
		}

		$where = array( 'it.form_id' => (int) $form_id );
		if ( null !== $is_draft ) {
			$where['it.is_draft'] = $is_draft;
		}

		$entries = \FrmEntry::getAll( $where, ' ORDER BY it.created_at DESC', self::frm_limit( $page, $per_page ) );
		$total   = self::frm_count( $form_id );
		$rows    = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = Normalizers::normalize_entry_summary(
				'formidable',
				(int) $entry->id,
				(string) ( $entry->created_at ?? '' ),

				(int) ( $entry->is_draft ?? 0 ) === 1 ? 'draft' : 'active',
				
				false
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $rows );
	}

	public static function frm_get_entry( $entry_id ) {
		if ( ! self::frm_available() || ! method_exists( '\FrmEntry', 'getOne' ) ) {
			throw self::frm_unavailable();
		}
		$entry = \FrmEntry::getOne( (int) $entry_id );
		if ( ! is_object( $entry ) || empty( $entry->id ) ) {
			throw new \Exception( 'Formidable submission not found.' );
		}

		
		$values = array();
		if ( class_exists( '\FrmEntryMeta' ) && method_exists( '\FrmEntryMeta', 'get_entry_meta_info' ) ) {
			foreach ( (array) \FrmEntryMeta::get_entry_meta_info( (int) $entry_id ) as $meta ) {
				if ( ! empty( $meta->field_id ) ) {
					$values[ (string) $meta->field_id ] = $meta->meta_value;
				}
			}
		}

		return Normalizers::normalize_entry_detail(
			'formidable',
			array(
				'id'           => (int) $entry->id,
				'form_id'      => (int) ( $entry->form_id ?? 0 ),
				'date_created' => (string) ( $entry->created_at ?? '' ),
				'status'       => (int) ( $entry->is_draft ?? 0 ) === 1 ? 'draft' : 'active',
				'values'       => $values,
				
			)
		);
	}

	public static function frm_get_stats( $form_id ) {
		$total = self::frm_count( $form_id );

		$by_status = array(
			'active' => self::frm_count_draft( $form_id, 0 ),
			'draft'  => self::frm_count_draft( $form_id, 1 ),
		);
		return array(
			'provider'  => 'formidable',
			'form_id'   => (int) $form_id,
			'total'     => $total,
			'by_status' => $by_status,
		);
	}

	public static function frm_update_entry_status( $entry_id, $status ) {

		

		throw new \Exception(
			'Formidable submissions have no "' . esc_html( $status ) . '" state. Formidable (free) stores only submitted / draft entries '
			. 'and deletes permanently; it has no trash, spam, or read/unread state. spam/read/unread/trash are not supported.'
		);
	}

	public static function frm_trash_entry( $entry_id ) {

		
		throw new \Exception(
			'Formidable has no trash for submissions: its own delete path (FrmEntry::destroy) is permanent. '
			. 'This tool never permanently deletes, so Formidable entries cannot be trashed. Delete from Formidable\'s own UI if needed.'
		);
	}

	private static function frm_count_draft( $form_id, $is_draft ) {
		if ( ! self::frm_available() || ! method_exists( '\FrmEntry', 'getRecordCount' ) ) {
			return 0;
		}
		return (int) \FrmEntry::getRecordCount( array( 'form_id' => (int) $form_id, 'is_draft' => $is_draft ) );
	}

	private static function frm_limit( $page, $per_page ) {
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;
		return ' LIMIT ' . (int) $per_page . ' OFFSET ' . $offset;
	}

	private static function frm_field_required( $field ) {
		if ( method_exists( '\FrmField', 'is_required' ) ) {
			return (bool) \FrmField::is_required( $field );
		}
		return ! empty( $field->required );
	}

	private static function frm_no_save_fields() {
		if ( method_exists( '\FrmField', 'no_save_fields' ) ) {
			return (array) \FrmField::no_save_fields();
		}
		return array( 'divider', 'end_divider', 'captcha', 'break', 'html', 'form', 'summary', 'submit' );
	}
}
