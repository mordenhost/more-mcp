<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MailPoet {

	public static function is_available() {
		return defined( 'MAILPOET_VERSION' ) || class_exists( '\MailPoet\Config\Env' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'mailpoet' ),
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
				'name'        => 'mailpoet_get_status',
				'description' => 'Read MailPoet audience health: the total number of subscribers with a breakdown by subscription status (subscribed, unconfirmed, unsubscribed, bounced, inactive), and the total number of lists/segments with a breakdown by type (default, dynamic, wp_users, woocommerce_users, woocommerce_memberships, without-list). Soft-deleted rows are excluded. Returns aggregate counts only, never subscriber records, email addresses, or any personal data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'mailpoet_get_campaigns',
				'description' => 'Read MailPoet email-campaign health: newsletters broken down by status (draft, scheduled, sending, sent, active, corrupt) and by type (standard, notification, automation, re_engagement, etc.), and optionally a bounded list of recent campaigns carrying only id, type, status, and sent/created dates. Returns campaign counts and state metadata only — never the email subject line, pre-header, or body content, and never recipient identities or any personal data. Read-only diagnostic; cannot send, schedule, edit, or delete a campaign.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include a list of the most recent campaigns (id/type/status/dates only, no content). Default false (counts by status and type only).',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'Maximum campaigns to list when include_list is true (1-50). Default 20.',
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
			throw new \Exception( 'MailPoet is not active.' );
		}

		if ( 'mailpoet_get_campaigns' === $name ) {
			return self::get_campaigns( $args );
		}
		if ( 'mailpoet_get_status' !== $name ) {
			throw new \Exception( 'Unknown MailPoet tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'mailpoet_' . $name;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function grouped_counts( $table, $column ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		
		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$table} WHERE deleted_at IS NULL GROUP BY {$column}", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$key        = isset( $row['k'] ) ? sanitize_key( $row['k'] ) : 'unknown';
				$count      = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$by[ $key ] = $count;
				$total     += $count;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function get_status() {
		$subscribers = self::grouped_counts( self::table( 'subscribers' ), 'status' );
		$segments    = self::grouped_counts( self::table( 'segments' ), 'type' );

		if ( null === $subscribers && null === $segments ) {
			return array(
				'provider'  => 'mailpoet',
				'available' => false,
				'message'   => 'MailPoet tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'mailpoet',
			'available' => true,
		);

		if ( null !== $subscribers ) {
			$result['subscribers'] = array(
				'total'     => $subscribers['total'],
				'by_status' => $subscribers['by'],
			);
		}
		if ( null !== $segments ) {
			$result['lists'] = array(
				'total'   => $segments['total'],
				'by_type' => $segments['by'],
			);
		}

		return $result;
	}

	private static function get_campaigns( $args ) {
		global $wpdb;
		$table = self::table( 'newsletters' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'mailpoet',
				'available' => false,
				'message'   => 'MailPoet newsletters table was not found; cannot read campaigns on this version.',
			);
		}

		$by_status = self::grouped_counts( $table, 'status' );
		$by_type   = self::grouped_counts( $table, 'type' );

		$result = array(
			'provider'  => 'mailpoet',
			'available' => true,
			'total'     => null !== $by_status ? $by_status['total'] : 0,
			'by_status' => null !== $by_status ? $by_status['by'] : array(),
			'by_type'   => null !== $by_type ? $by_type['by'] : array(),
		);

		if ( empty( $args['include_list'] ) ) {
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
				"SELECT id, type, status, sent_at, created_at FROM {$table} WHERE deleted_at IS NULL ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$campaigns = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$campaigns[] = array(
					'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'type'       => isset( $row['type'] ) ? (string) $row['type'] : '',
					'status'     => isset( $row['status'] ) ? (string) $row['status'] : '',
					'sent_at'    => isset( $row['sent_at'] ) ? $row['sent_at'] : null,
					'created_at' => isset( $row['created_at'] ) ? $row['created_at'] : null,
				);
			}
		}

		$result['campaigns'] = $campaigns;
		return $result;
	}
}
