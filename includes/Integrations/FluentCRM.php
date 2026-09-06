<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCRM {

	public static function is_available() {
		return defined( 'FLUENTCRM' ) || function_exists( 'FluentCrmApi' ) || class_exists( '\FluentCrm\App\Models\Subscriber' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'fluentcrm' ),
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
				'name'        => 'crm_get_status',
				'description' => 'Read FluentCRM contact-list health: the total number of contacts and a breakdown by subscription status (subscribed, pending, unsubscribed, bounced, complained). Returns aggregate counts only, never contact records or any personal data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'crm_get_campaigns',
				'description' => 'Read FluentCRM email campaign health: a breakdown of campaigns by status (draft, scheduled, working, archived, etc.) and, optionally, a list of recent campaigns with title, type, status, recipient count, and scheduled/created dates. Returns campaign metadata only — never the email subject, pre-header, or body content, and never recipient identities or any personal data. Read-only diagnostic; cannot send, schedule, edit, or delete a campaign.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include a list of the most recent campaigns (metadata only). Default false (counts by status only).',
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
			throw new \Exception( 'FluentCRM is not active.' );
		}

		if ( 'crm_get_campaigns' === $name ) {
			return self::get_campaigns( $args );
		}
		if ( 'crm_get_status' !== $name ) {
			throw new \Exception( 'Unknown CRM tool: ' . esc_html( $name ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fc_subscribers';

		
		
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM contact table was not found; cannot read counts on this version.',
			);
		}

		
		
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );

		$by_status = array();
		$total     = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status            = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
				$count             = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$by_status[ $status ] = $count;
				$total            += $count;
			}
		}

		return array(
			'provider'   => 'fluentcrm',
			'available'  => true,
			'total'      => $total,
			'by_status'  => $by_status,
		);
	}

	private static function get_campaigns( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_campaigns';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM campaigns table was not found; cannot read campaigns on this version.',
			);
		}

		
		
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );

		$by_status = array();
		$total     = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status               = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
				$count                = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$by_status[ $status ] = $count;
				$total               += $count;
			}
		}

		$result = array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'total'     => $total,
			'by_status' => $by_status,
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

		
		$campaign_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, type, status, recipients_count, scheduled_at, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$campaigns = array();
		if ( is_array( $campaign_rows ) ) {
			foreach ( $campaign_rows as $row ) {
				$campaigns[] = array(
					'id'               => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'title'            => isset( $row['title'] ) ? (string) $row['title'] : '',
					'type'             => isset( $row['type'] ) ? (string) $row['type'] : '',
					'status'           => isset( $row['status'] ) ? (string) $row['status'] : '',
					'recipients_count' => isset( $row['recipients_count'] ) ? (int) $row['recipients_count'] : 0,
					'scheduled_at'     => isset( $row['scheduled_at'] ) ? $row['scheduled_at'] : null,
					'created_at'       => isset( $row['created_at'] ) ? $row['created_at'] : null,
				);
			}
		}

		$result['campaigns'] = $campaigns;
		return $result;
	}
}
