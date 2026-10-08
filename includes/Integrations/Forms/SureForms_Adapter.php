<?php

namespace More_MCP\Integrations\Forms;

use More_MCP\Integrations\Write_Verify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SureForms_Adapter {

	const LIST_LIMIT = 500;

	const FORM_POST_TYPE = 'sureforms_form';

	public static function sf_available() {
		return class_exists( '\SRFM\Inc\Entries' ) && class_exists( '\SRFM\Inc\Database\Tables\Entries' );
	}

	public static function sf_unavailable() {
		return new \Exception( 'SureForms is unavailable. Verify the plugin is active and up to date.' );
	}

	public static function sf_list_forms() {
		$posts = get_posts(
			array(
				'post_type'        => self::FORM_POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => self::LIST_LIMIT,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'provider'    => 'sureforms',
				'id'          => (int) $post->ID,
				'title'       => (string) $post->post_title,
				'active'      => 'publish' === $post->post_status,
				'entry_count' => self::sf_count( (int) $post->ID ),
			);
		}
		return $out;
	}

	public static function sf_get_form( $form_id ) {
		$post = get_post( (int) $form_id );
		if ( ! $post || self::FORM_POST_TYPE !== $post->post_type ) {
			throw new \Exception( 'SureForms form not found.' );
		}
		return array(
			'provider' => 'sureforms',
			'id'       => (int) $post->ID,
			'title'    => (string) $post->post_title,
			'active'   => 'publish' === $post->post_status,
			'fields'   => self::sf_fields( parse_blocks( (string) $post->post_content ) ),
		);
	}

	private static function sf_fields( array $blocks ) {
		$fields = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
				continue;
			}
			$name  = (string) $block['blockName'];
			$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
			if ( 0 !== strpos( $name, 'srfm/' ) || 'srfm/form' === $name ) {
				if ( $inner ) {
					$fields = array_merge( $fields, self::sf_fields( $inner ) );
				}
				continue;
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( empty( $attrs['slug'] ) ) {
				continue;
			}
			$fields[] = Normalizers::normalize_field(
				(string) $attrs['slug'],
				(string) ( $attrs['label'] ?? '' ),
				substr( $name, strlen( 'srfm/' ) ),
				! empty( $attrs['required'] )
			);
			if ( $inner ) {
				$fields = array_merge( $fields, self::sf_fields( $inner ) );
			}
		}
		return $fields;
	}

	
	public static function sf_count( $form_id ) {
		return self::sf_count_status( $form_id, 'all' );
	}

	private static function sf_count_status( $form_id, $status ) {
		if ( ! self::sf_available() ) {
			return 0;
		}
		$result = \SRFM\Inc\Entries::get_entries(
			array(
				'form_id'  => (int) $form_id,
				'status'   => $status,
				'per_page' => 1,
				'page'     => 1,
			)
		);
		return (int) ( $result['total'] ?? 0 );
	}

	public static function sf_map_status_filter( $status ) {
		
		if ( 'active' === $status ) {
			return 'all';
		}
		if ( in_array( $status, array( 'read', 'unread', 'trash' ), true ) ) {
			return $status;
		}
		return 'all';
	}

	public static function sf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		if ( ! self::sf_available() ) {
			throw self::sf_unavailable();
		}
		if ( 'spam' === $status ) {

			return Normalizers::paginate( 0, $page, $per_page, array() );
		}

		$result = \SRFM\Inc\Entries::get_entries(
			array(
				'form_id'   => (int) $form_id,
				'status'    => self::sf_map_status_filter( $status ),

				'date_from' => '' !== $range['start_date'] ? $range['start_date'] . ' 00:00:00' : '',
				'date_to'   => '' !== $range['end_date'] ? $range['end_date'] . ' 23:59:59' : '',
				'per_page'  => (int) $per_page,
				'page'      => (int) $page,
			)
		);

		$rows = array();
		foreach ( (array) ( $result['entries'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entry_status = (string) ( $entry['status'] ?? '' );
			$rows[]       = Normalizers::normalize_entry_summary(
				'sureforms',
				(int) ( $entry['ID'] ?? 0 ),
				(string) ( $entry['created_at'] ?? '' ),
				$entry_status,
				'read' === $entry_status
			);
		}
		return Normalizers::paginate( (int) ( $result['total'] ?? 0 ), $page, $per_page, $rows );
	}

	public static function sf_get_entry( $entry_id ) {
		$entry = self::sf_row( $entry_id );
		if ( null === $entry ) {
			throw new \Exception( 'SureForms submission not found.' );
		}

		
		$excluded = array( 'srfm-honeypot-field', 'g-recaptcha-response', 'srfm-sender-email-field', 'form-id' );
		$values   = array();
		foreach ( (array) ( $entry['form_data'] ?? array() ) as $key => $value ) {
			if ( ! is_string( $key ) || in_array( $key, $excluded, true ) || false === strpos( $key, '-lbl-' ) ) {
				continue;
			}
			$label = self::sf_label( $key );
			$name  = '' !== $label ? $label : $key;
			if ( isset( $values[ $name ] ) ) {
				$name = $key; 
			}
			$values[ $name ] = $value;
		}

		return Normalizers::normalize_entry_detail(
			'sureforms',
			array(
				'id'           => (int) $entry_id,
				'form_id'      => (int) ( $entry['form_id'] ?? 0 ),
				'date_created' => (string) ( $entry['created_at'] ?? '' ),
				'status'       => (string) ( $entry['status'] ?? '' ),
				'is_read'      => 'read' === (string) ( $entry['status'] ?? '' ),
				'values'       => $values,
				
			)
		);
	}

	public static function sf_get_stats( $form_id ) {
		if ( ! self::sf_available() ) {
			throw self::sf_unavailable();
		}
		$by_status = array();
		foreach ( array( 'unread', 'read', 'trash' ) as $status ) {
			$by_status[ $status ] = self::sf_count_status( $form_id, $status );
		}
		return array(
			'provider'  => 'sureforms',
			'form_id'   => (int) $form_id,
			'total'     => array_sum( $by_status ),
			'by_status' => $by_status,
		);
	}

	public static function sf_update_entry_status( $entry_id, $status ) {
		$entry = self::sf_row( $entry_id );
		if ( null === $entry ) {
			throw new \Exception( 'SureForms submission not found.' );
		}
		if ( 'spam' === $status ) {
			throw new \Exception( 'SureForms submissions have no "spam" state: an entry is read, unread or trash. Use read, unread, active (restores a trashed entry) or forms_trash_entry.' );
		}

		$prior_status = (string) ( $entry['status'] ?? '' );
		if ( 'active' === $status ) {
			if ( 'trash' !== $prior_status ) {
				return array(
					'written'  => false,
					'provider' => 'sureforms',
					'entry_id' => (int) $entry_id,
					'status'   => $status,
					'note'     => 'The entry is not in the trash, so there is nothing to restore.',
				);
			}
			$call   = 'restore';
			$target = 'unread'; 
		} else {
			$call   = $status; 
			$target = $status;
		}
		if ( $target === $prior_status ) {
			return array(
				'written'  => false,
				'provider' => 'sureforms',
				'entry_id' => (int) $entry_id,
				'status'   => $status,
				'note'     => 'The entry already has that status.',
			);
		}

		return self::sf_write( $entry_id, $call, $target, $prior_status, array( 'status' => $status ), 'Restore SureForms submission #' . (int) $entry_id . ' status to ' . $prior_status );
	}

	public static function sf_trash_entry( $entry_id ) {
		$entry = self::sf_row( $entry_id );
		if ( null === $entry ) {
			throw new \Exception( 'SureForms submission not found.' );
		}
		$prior_status = (string) ( $entry['status'] ?? '' );

		return self::sf_write( $entry_id, 'trash', 'trash', $prior_status, array( 'action' => 'trash' ), 'Restore SureForms submission #' . (int) $entry_id . ' from trash to ' . $prior_status );
	}

	private static function sf_write( $entry_id, $call, $target, $prior_status, array $echo, $summary ) {
		if ( ! self::sf_available() ) {
			throw self::sf_unavailable();
		}
		$result = \SRFM\Inc\Entries::update_status( array( (int) $entry_id ), $call );
		if ( empty( $result['success'] ) ) {
			$errors = isset( $result['errors'] ) && is_array( $result['errors'] ) ? implode( ' ', array_map( 'strval', $result['errors'] ) ) : '';
			throw new \Exception( 'Failed to update the SureForms submission. ' . esc_html( $errors ) );
		}

		$fresh  = self::sf_row( $entry_id );
		$verify = Normalizers::verify_write(
			null !== $fresh ? (string) ( $fresh['status'] ?? '' ) : Write_Verify::UNREADABLE,
			$target,
			'status',
			$prior_status
		);

		$prior = array( 'property' => 'status', 'value' => $prior_status );
		$undo  = Normalizers::store_undo( 'sureforms', $entry_id, $prior, $summary );

		return array_merge(
			array(
				'written'  => true,
				'provider' => 'sureforms',
				'entry_id' => (int) $entry_id,
			),
			$echo,
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function sf_undo( $entry_id, $property, $value ) {
		if ( 'status' !== $property || ! in_array( (string) $value, array( 'read', 'unread', 'trash' ), true ) ) {
			throw new \Exception( 'Unexpected SureForms undo property: ' . esc_html( (string) $property ) );
		}
		if ( ! self::sf_available() ) {
			throw self::sf_unavailable();
		}
		if ( null === self::sf_row( $entry_id ) ) {
			throw new \Exception( 'SureForms submission no longer exists; cannot undo.' );
		}
		$result = \SRFM\Inc\Database\Tables\Entries::update( (int) $entry_id, array( 'status' => (string) $value ) );
		if ( false === $result ) {
			throw new \Exception( 'Failed to restore the SureForms submission.' );
		}
	}

	
	private static function sf_row( $entry_id ) {
		if ( ! self::sf_available() || (int) $entry_id <= 0 ) {
			return null;
		}
		$row = \SRFM\Inc\Database\Tables\Entries::get( (int) $entry_id );
		return ( is_array( $row ) && ! empty( $row ) ) ? $row : null;
	}

	private static function sf_label( $key ) {
		$parts = explode( '-lbl-', (string) $key, 2 );
		if ( ! isset( $parts[1] ) ) {
			return '';
		}
		$encoded = explode( '-', $parts[1] )[0];
		if ( '' === $encoded ) {
			return '';
		}
		if ( class_exists( '\SRFM\Inc\Helper' ) && method_exists( '\SRFM\Inc\Helper', 'decode' ) ) {
			return (string) \SRFM\Inc\Helper::decode( $encoded );
		}
		$decoded = base64_decode( $encoded . str_repeat( '=', strlen( $encoded ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- SureForms encodes field labels in its stored keys.
		return false === $decoded ? '' : (string) $decoded;
	}
}
