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
			array(
				'name'        => 'crm_get_lists',
				'description' => 'Read the FluentCRM list catalogue: for each list its id, title, slug, and whether it is public, plus a per-list subscriber count as a pure aggregate. Returns list definitions and counts only — never a contact record, email address, or any personal data. Read-only diagnostic; cannot create, edit, or delete a list.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'crm_get_tags',
				'description' => 'Read the FluentCRM tag catalogue: for each tag its id, title, and slug. Returns tag definitions only — never a contact record, email address, tag membership, or any personal data. Read-only diagnostic; cannot create, edit, or delete a tag.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
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
		if ( 'crm_get_lists' === $name ) {
			return self::get_lists();
		}
		if ( 'crm_get_tags' === $name ) {
			return self::get_tags();
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

	private static function get_lists() {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_lists';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM lists table was not found; cannot read lists on this version.',
			);
		}

		
		$pivot       = $wpdb->prefix . 'fc_subscribers';
		$pivot_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pivot ) ) === $pivot;

		$counts = array();
		if ( $pivot_exist ) {

			$rows = $wpdb->get_results( "SELECT list_id, COUNT(*) AS n FROM {$pivot} GROUP BY list_id", ARRAY_A );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$counts[ (int) $row['list_id'] ] = (int) $row['n'];
				}
			}
		}

		
		$list_rows = $wpdb->get_results( "SELECT id, title, slug, is_public FROM {$table} ORDER BY id", ARRAY_A );

		$lists = array();
		if ( is_array( $list_rows ) ) {
			foreach ( $list_rows as $row ) {
				$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$lists[] = array(
					'id'               => $id,
					'title'            => isset( $row['title'] ) ? (string) $row['title'] : '',
					'slug'             => isset( $row['slug'] ) ? (string) $row['slug'] : '',
					'is_public'        => ! empty( $row['is_public'] ),
					'subscriber_count' => $pivot_exist && isset( $counts[ $id ] ) ? $counts[ $id ] : 0,
				);
			}
		}

		$result = array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'lists'     => $lists,
			'count'     => count( $lists ),
		);
		if ( ! $pivot_exist ) {
			$result['subscriber_count_available'] = false;
		}
		return $result;
	}

	private static function get_tags() {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_tags';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM tags table was not found; cannot read tags on this version.',
			);
		}

		
		$rows = $wpdb->get_results( "SELECT id, title, slug FROM {$table} ORDER BY id", ARRAY_A );

		$tags = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$tags[] = array(
					'id'    => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
					'slug'  => isset( $row['slug'] ) ? (string) $row['slug'] : '',
				);
			}
		}

		return array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'tags'      => $tags,
			'count'     => count( $tags ),
		);
	}
}
