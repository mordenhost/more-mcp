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
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use CRM tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'FluentCRM is not active.' );
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
}
