<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Groundhogg {

	private const OPTIN_LABELS = array(
		1 => 'unconfirmed',
		2 => 'confirmed',
		3 => 'unsubscribed',
		4 => 'weekly',
		5 => 'monthly',
		6 => 'hard_bounce',
		7 => 'spam',
		8 => 'complained',
		9 => 'blocked',
	);

	public static function is_available() {
		return defined( 'GROUNDHOGG_VERSION' ) || function_exists( 'Groundhogg\get_db' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'groundhogg' ),
			'capabilities' => array( 'crm' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'groundhogg_get_status',
				'description' => 'Read Groundhogg audience health: the total number of contacts with a breakdown by opt-in status (unconfirmed, confirmed, unsubscribed, weekly, monthly, hard_bounce, spam, complained, blocked), and the total number of tags. Returns aggregate counts only, never contact records, email addresses, or any personal data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'groundhogg_get_campaigns',
				'description' => 'Read Groundhogg marketing-automation health: funnel (automation) counts by status (active, inactive, archived) and broadcast (campaign) counts by status (pending, scheduled, sending, sent, cancelled), and optionally a bounded list of recent broadcasts carrying only id, object type (email/sms), status, and scheduled date. Returns counts and state metadata only — never the email subject, body, or any content, and never recipient identities or any personal data. Read-only diagnostic; cannot send, schedule, edit, or delete a funnel or broadcast.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include a list of the most recent broadcasts (id/object_type/status/scheduled date only, no content). Default false (counts by status only).',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'Maximum broadcasts to list when include_list is true (1-50). Default 20.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use CRM tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Groundhogg is not active.' );
		}

		if ( 'groundhogg_get_campaigns' === $name ) {
			return self::get_campaigns( $args );
		}
		if ( 'groundhogg_get_status' !== $name ) {
			throw new \Exception( 'Unknown Groundhogg tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'gh_' . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function grouped_counts( $table, $column, $labels = null ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$table} GROUP BY {$column}", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$raw   = isset( $row['k'] ) ? $row['k'] : '';
				$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
				if ( null !== $labels ) {
					$key = isset( $labels[ (int) $raw ] ) ? $labels[ (int) $raw ] : (string) (int) $raw;
				} else {
					$key = sanitize_key( $raw );
				}
				$by[ $key ] = $count;
				$total     += $count;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function table_total( $table ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function get_status() {
		$contacts = self::grouped_counts( self::table( 'contacts' ), 'optin_status', self::OPTIN_LABELS );
		$tags     = self::table_total( self::table( 'tags' ) );

		if ( null === $contacts && null === $tags ) {
			return array(
				'provider'  => 'groundhogg',
				'available' => false,
				'message'   => 'Groundhogg tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'groundhogg',
			'available' => true,
		);

		if ( null !== $contacts ) {
			$result['contacts'] = array(
				'total'     => $contacts['total'],
				'by_status' => $contacts['by'],
			);
		}
		if ( null !== $tags ) {
			$result['tags'] = array( 'total' => $tags );
		}

		return $result;
	}

	private static function get_campaigns( $args ) {
		global $wpdb;
		$funnels_table    = self::table( 'funnels' );
		$broadcasts_table = self::table( 'broadcasts' );

		$funnels    = self::grouped_counts( $funnels_table, 'status' );
		$broadcasts = self::grouped_counts( $broadcasts_table, 'status' );

		if ( null === $funnels && null === $broadcasts ) {
			return array(
				'provider'  => 'groundhogg',
				'available' => false,
				'message'   => 'Groundhogg funnel/broadcast tables were not found; cannot read campaigns on this version.',
			);
		}

		$result = array(
			'provider'  => 'groundhogg',
			'available' => true,
		);

		if ( null !== $funnels ) {
			$result['funnels'] = array(
				'total'     => $funnels['total'],
				'by_status' => $funnels['by'],
			);
		}
		if ( null !== $broadcasts ) {
			$result['broadcasts'] = array(
				'total'     => $broadcasts['total'],
				'by_status' => $broadcasts['by'],
			);
		}

		if ( empty( $args['include_list'] ) || null === $broadcasts ) {
			return $result;
		}

		
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 50 ) {
			$limit = 50;
		}

		
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, object_type, status, date_scheduled FROM {$broadcasts_table} ORDER BY ID DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$list = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$list[] = array(
					'id'             => isset( $row['ID'] ) ? (int) $row['ID'] : 0,
					'object_type'    => isset( $row['object_type'] ) ? (string) $row['object_type'] : '',
					'status'         => isset( $row['status'] ) ? (string) $row['status'] : '',
					'date_scheduled' => isset( $row['date_scheduled'] ) ? $row['date_scheduled'] : null,
				);
			}
		}

		$result['recent_broadcasts'] = $list;
		return $result;
	}
}
