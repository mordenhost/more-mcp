<?php

namespace More_MCP\Integrations\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contact_Form_7_Adapter {

	public static function cf7_no_entry_store() {
		return new \Exception(
			'Contact Form 7 does not store form submissions; it only emails them. '
			. 'There are no entries to read or modify. Install a submissions add-on '
			. '(for example Flamingo or Contact Form CFDB7) to persist entries, and '
			. 'read them through that plugin.'
		);
	}

	public static function cf7_list_forms() {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			throw new \Exception( 'Contact Form 7 is not active.' );
		}
		$out = array();
		foreach ( (array) \WPCF7_ContactForm::find( array() ) as $form ) {
			if ( ! is_object( $form ) || ! method_exists( $form, 'id' ) ) {
				continue;
			}
			$out[] = array(
				'provider'    => 'contactform7',
				'id'          => (int) $form->id(),
				'title'       => (string) $form->title(),

				
				
				'active'      => true,
				'entry_count' => 0,
			);
		}
		return $out;
	}

	public static function cf7_get_form( $form_id ) {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			throw new \Exception( 'Contact Form 7 is not active.' );
		}
		$form = \WPCF7_ContactForm::get_instance( (int) $form_id );
		if ( ! $form || ! method_exists( $form, 'scan_form_tags' ) ) {
			throw new \Exception( 'Contact Form 7 form not found.' );
		}

		$fields = array();
		foreach ( (array) $form->scan_form_tags() as $tag ) {

			
			
			$name = is_object( $tag ) ? (string) ( $tag->name ?? '' ) : '';
			if ( '' === $name ) {
				continue;
			}

			
			
			$basetype = is_object( $tag ) ? (string) ( $tag->basetype ?? ( $tag->type ?? '' ) ) : '';
			$required = is_object( $tag ) && method_exists( $tag, 'is_required' )
				? (bool) $tag->is_required()
				: ( '' !== $name && is_object( $tag ) && isset( $tag->type ) && substr( (string) $tag->type, -1 ) === '*' );

			$fields[] = Normalizers::normalize_field( $name, $name, $basetype, $required );
		}

		return array(
			'provider' => 'contactform7',
			'id'       => (int) $form->id(),
			'title'    => (string) $form->title(),
			'active'   => true,
			'fields'   => $fields,
		);
	}

	public static function cf7_list_entries( $form_id, $status, $range, $page, $per_page ) {
		throw self::cf7_no_entry_store();
	}

	public static function cf7_get_entry( $entry_id ) {
		throw self::cf7_no_entry_store();
	}

	public static function cf7_get_stats( $form_id ) {
		throw self::cf7_no_entry_store();
	}

	public static function cf7_update_entry_status( $entry_id, $status ) {
		throw self::cf7_no_entry_store();
	}

	public static function cf7_trash_entry( $entry_id ) {
		throw self::cf7_no_entry_store();
	}
}
