<?php

namespace More_MCP\Integrations\Forms;

use More_MCP\Integrations\Write_Verify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Everest_Forms_Adapter {

	const LIST_LIMIT = 500;

	const FORM_POST_TYPE = 'everest_form';

	const HIDDEN_STATUSES = array( 'trash', 'spam', 'draft' );

	public static function evf_available() {
		return function_exists( 'evf' ) && defined( 'EVF_VERSION' );
	}

	public static function evf_unavailable() {
		return new \Exception( 'Everest Forms is unavailable. Verify the plugin is active and up to date.' );
	}

	public static function evf_list_forms() {
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
				'provider'    => 'everestforms',
				'id'          => (int) $post->ID,
				'title'       => (string) $post->post_title,
				'active'      => 'publish' === $post->post_status,
				'entry_count' => self::evf_count( (int) $post->ID ),
			);
		}
		return $out;
	}

	public static function evf_get_form( $form_id ) {
		$post = get_post( (int) $form_id );
		if ( ! $post || self::FORM_POST_TYPE !== $post->post_type ) {
			throw new \Exception( 'Everest Forms form not found.' );
		}
		$content = json_decode( (string) $post->post_content, true );
		$defs    = is_array( $content ) && isset( $content['form_fields'] ) && is_array( $content['form_fields'] ) ? $content['form_fields'] : array();

		$fields = array();
		foreach ( $defs as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			
			if ( in_array( $type, self::non_value_types(), true ) ) {
				continue;
			}
			$fields[] = Normalizers::normalize_field(
				(string) ( $field['id'] ?? $key ),
				(string) ( $field['label'] ?? '' ),
				$type,
				isset( $field['required'] ) && in_array( (string) $field['required'], array( '1', 'true', 'yes' ), true )
			);
		}

		return array(
			'provider' => 'everestforms',
			'id'       => (int) $post->ID,
			'title'    => (string) $post->post_title,
			'active'   => 'publish' === $post->post_status,
			'fields'   => $fields,
		);
	}

	
	public static function evf_count( $form_id ) {
		return self::count_where( (int) $form_id, self::status_sql( 'active' ) );
	}

	public static function evf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		global $wpdb;
		if ( ! self::evf_available() ) {
			throw self::evf_unavailable();
		}
		$table = $wpdb->prefix . 'evf_entries';

		$where  = 'form_id = %d AND ' . self::status_sql( $status );
		$params = array( (int) $form_id );
		if ( '' !== $range['start_date'] ) {
			$where   .= ' AND date_created >= %s';
			$params[] = $range['start_date'] . ' 00:00:00';
		}
		if ( '' !== $range['end_date'] ) {
			$where   .= ' AND date_created <= %s';
			$params[] = $range['end_date'] . ' 23:59:59';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Everest Forms keeps submissions in its own table; $table is $wpdb->prefix plus a constant and $where is built from literals above with every value bound.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entry_id, status, viewed, date_created FROM {$table} WHERE {$where} ORDER BY entry_id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( (int) $per_page, ( max( 1, (int) $page ) - 1 ) * (int) $per_page ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = Normalizers::normalize_entry_summary(
				'everestforms',
				(int) $row['entry_id'],
				(string) $row['date_created'],
				(string) $row['status'],
				! empty( $row['viewed'] )
			);
		}
		return Normalizers::paginate( $total, $page, $per_page, $out );
	}

	public static function evf_get_entry( $entry_id ) {
		$row = self::evf_row( $entry_id );
		if ( null === $row ) {
			throw new \Exception( 'Everest Forms submission not found.' );
		}

		
		$values  = array();
		$decoded = json_decode( (string) $row['fields'], true );
		foreach ( is_array( $decoded ) ? $decoded : array() as $field ) {
			if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( in_array( $type, self::non_value_types(), true ) || self::is_payment_card( $type ) ) {
				continue;
			}
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name ) {
				$name = (string) ( $field['meta_key'] ?? ( $field['id'] ?? '' ) );
			}
			if ( isset( $values[ $name ] ) ) {
				$name .= ' (' . (string) ( $field['id'] ?? count( $values ) ) . ')';
			}
			$values[ $name ] = $field['value'];
		}

		return Normalizers::normalize_entry_detail(
			'everestforms',
			array(
				'id'           => (int) $row['entry_id'],
				'form_id'      => (int) $row['form_id'],
				'date_created' => (string) $row['date_created'],
				'status'       => (string) $row['status'],
				'is_read'      => ! empty( $row['viewed'] ),
				'starred'      => ! empty( $row['starred'] ),
				'values'       => $values,
				
			)
		);
	}

	public static function evf_get_stats( $form_id ) {
		global $wpdb;
		if ( ! self::evf_available() ) {
			throw self::evf_unavailable();
		}
		$table = $wpdb->prefix . 'evf_entries';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Everest Forms keeps submissions in its own table; $table is $wpdb->prefix plus a constant.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT status, COUNT(*) AS n, SUM(CASE WHEN viewed = 0 THEN 1 ELSE 0 END) AS unread FROM {$table} WHERE form_id = %d AND status <> %s GROUP BY status", (int) $form_id, 'draft' ),
			ARRAY_A
		);
		// phpcs:enable

		$by_status = array();
		$total     = 0;
		$unread    = 0;
		foreach ( (array) $rows as $row ) {
			$by_status[ (string) $row['status'] ] = (int) $row['n'];

			$total += (int) $row['n'];
			if ( ! in_array( (string) $row['status'], self::HIDDEN_STATUSES, true ) ) {
				$unread += (int) $row['unread'];
			}
		}
		foreach ( array( 'publish', 'trash', 'spam' ) as $known ) {
			$by_status += array( $known => 0 );
		}
		$by_status['unread'] = $unread;

		return array(
			'provider'  => 'everestforms',
			'form_id'   => (int) $form_id,
			'total'     => $total,
			'by_status' => $by_status,
		);
	}

	public static function evf_update_entry_status( $entry_id, $status ) {
		$row = self::evf_row( $entry_id );
		if ( null === $row ) {
			throw new \Exception( 'Everest Forms submission not found.' );
		}

		if ( 'read' === $status || 'unread' === $status ) {
			$prior  = array( 'property' => 'viewed', 'value' => (string) (int) $row['viewed'] );
			$target = 'read' === $status ? 1 : 0;
			if ( (int) $prior['value'] === $target ) {
				return array(
					'written'  => false,
					'provider' => 'everestforms',
					'entry_id' => (int) $entry_id,
					'status'   => $status,
					'note'     => 'The entry already has that status.',
				);
			}
			return self::evf_write( $entry_id, 'viewed', $target, $prior, array( 'status' => $status ), 'Restore Everest Forms submission #' . (int) $entry_id . ' viewed flag to ' . $prior['value'] );
		}

		if ( 'active' === $status || 'spam' === $status ) {
			$target_status = 'active' === $status ? 'publish' : 'spam';
			if ( $target_status === (string) $row['status'] ) {
				return array(
					'written'  => false,
					'provider' => 'everestforms',
					'entry_id' => (int) $entry_id,
					'status'   => $status,
					'note'     => 'The entry already has that status.',
				);
			}
			$prior = array( 'property' => 'status', 'value' => (string) $row['status'] );
			return self::evf_write( $entry_id, 'status', $target_status, $prior, array( 'status' => $status ), 'Restore Everest Forms submission #' . (int) $entry_id . ' status to ' . $prior['value'] );
		}

		throw new \Exception( 'status must be one of: active, spam, read, unread.' );
	}

	public static function evf_trash_entry( $entry_id ) {
		$row = self::evf_row( $entry_id );
		if ( null === $row ) {
			throw new \Exception( 'Everest Forms submission not found.' );
		}

		$prior = array( 'property' => 'status', 'value' => (string) $row['status'] );
		return self::evf_write( $entry_id, 'status', 'trash', $prior, array( 'action' => 'trash' ), 'Restore Everest Forms submission #' . (int) $entry_id . ' from trash to ' . $prior['value'] );
	}

	private static function evf_write( $entry_id, $column, $target, array $prior, array $echo, $summary ) {
		if ( ! self::evf_available() ) {
			throw self::evf_unavailable();
		}
		self::update_column( (int) $entry_id, $column, $target );

		$fresh  = self::evf_row( $entry_id );
		$verify = Normalizers::verify_write(
			null !== $fresh ? (string) $fresh[ $column ] : Write_Verify::UNREADABLE,
			(string) $target,
			$column,
			$prior['value']
		);

		$undo = Normalizers::store_undo( 'everestforms', $entry_id, $prior, $summary );

		return array_merge(
			array(
				'written'  => true,
				'provider' => 'everestforms',
				'entry_id' => (int) $entry_id,
			),
			$echo,
			$verify,
			array( 'undo' => $undo )
		);
	}

	public static function evf_undo( $entry_id, $property, $value ) {
		if ( ! in_array( $property, array( 'status', 'viewed' ), true ) ) {
			throw new \Exception( 'Unexpected Everest Forms undo property: ' . esc_html( (string) $property ) );
		}
		if ( ! self::evf_available() ) {
			throw self::evf_unavailable();
		}
		if ( null === self::evf_row( $entry_id ) ) {
			throw new \Exception( 'Everest Forms submission no longer exists; cannot undo.' );
		}
		self::update_column( (int) $entry_id, (string) $property, 'viewed' === $property ? (int) $value : (string) $value );
	}

	
	private static function evf_row( $entry_id ) {
		global $wpdb;
		if ( ! self::evf_available() || (int) $entry_id <= 0 ) {
			return null;
		}
		$table = $wpdb->prefix . 'evf_entries';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the entry cache would hand back the pre-write row; $table is $wpdb->prefix plus a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE entry_id = %d LIMIT 1", (int) $entry_id ), ARRAY_A );
		// phpcs:enable
		return is_array( $row ) ? $row : null;
	}

	private static function update_column( $entry_id, $column, $value ) {
		global $wpdb;
		$format = 'viewed' === $column ? '%d' : '%s';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Everest Forms' own admin action does exactly this single-column update on its own table.
		$result = $wpdb->update( $wpdb->prefix . 'evf_entries', array( $column => $value ), array( 'entry_id' => $entry_id ), array( $format ), array( '%d' ) );
		if ( false === $result ) {
			throw new \Exception( 'Failed to update the Everest Forms submission.' );
		}
		
		wp_cache_delete( $entry_id, 'evf-entry' );
		wp_cache_delete( $entry_id, 'evf-entrymeta' );
	}

	private static function status_sql( $status ) {
		switch ( $status ) {
			case 'trash':
				return "status = 'trash'";
			case 'spam':
				return "status = 'spam'";
			case 'read':
				return "viewed = 1 AND status NOT IN ('trash','spam','draft')";
			case 'unread':
				return "viewed = 0 AND status NOT IN ('trash','spam','draft')";
			default:
				return "status NOT IN ('trash','spam','draft')";
		}
	}

	private static function count_where( $form_id, $predicate ) {
		global $wpdb;
		if ( ! self::evf_available() ) {
			return 0;
		}
		$table = $wpdb->prefix . 'evf_entries';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $table is $wpdb->prefix plus a constant and $predicate is a literal from status_sql().
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND {$predicate}", (int) $form_id ) );
		// phpcs:enable
		return $count;
	}

	private static function non_value_types() {
		return array( 'title', 'html', 'divider', 'captcha', 'recaptcha', 'hcaptcha', 'turnstile', 'honeypot' );
	}

	private static function is_payment_card( $type ) {
		return (bool) preg_match( '/credit-card|stripe|square|authorize|paypal/i', (string) $type );
	}
}
