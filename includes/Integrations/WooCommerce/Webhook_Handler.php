<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook_Handler {

	const NAMES = array( 'wc_get_webhooks', 'wc_create_webhook', 'wc_update_webhook', 'wc_delete_webhook' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		$props = array(
			'name'         => array( 'type' => 'string', 'description' => 'Webhook name' ),
			'topic'        => array( 'type' => 'string', 'description' => 'Topic such as order.created, order.updated, product.deleted, customer.created, coupon.updated, or action.woocommerce_* for a custom action' ),
			'delivery_url' => array( 'type' => 'string', 'description' => 'Public http(s) URL that receives the POST' ),
			'secret'       => array( 'type' => 'string', 'description' => 'Signing secret for the X-WC-Webhook-Signature header (generated when omitted)' ),
			'status'       => array( 'type' => 'string', 'enum' => array( 'active', 'paused', 'disabled' ) ),
		);
		return array(
			array(
				'name'        => 'wc_get_webhooks',
				'description' => 'List WooCommerce webhooks with topic, delivery URL, status and failure count. Secrets are not returned.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'enum' => array( 'active', 'paused', 'disabled' ), 'description' => 'Only webhooks with this status' ),
						'search'   => array( 'type' => 'string', 'description' => 'Match on webhook name' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Webhooks per page (max 100, default 20)' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'wc_create_webhook',
				'description' => 'Create a WooCommerce webhook. When created active, WooCommerce sends a ping to the delivery URL.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $props,
					'required'   => array( 'topic', 'delivery_url' ),
				),
			),
			array(
				'name'        => 'wc_update_webhook',
				'description' => 'Update a WooCommerce webhook: name, topic, delivery URL, secret or status. Only the fields you pass change.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge( array( 'id' => array( 'type' => 'integer', 'description' => 'Webhook ID' ) ), $props ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wc_delete_webhook',
				'description' => 'Permanently delete a WooCommerce webhook (webhooks have no trash).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'Webhook ID' ),
					),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
			case 'wc_get_webhooks':
				$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
				$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
				$query    = array(
					'limit'  => $per_page,
					'offset' => ( $page - 1 ) * $per_page,
				);
				if ( ! empty( $args['status'] ) ) {
					$query['status'] = self::status( (string) $args['status'] );
				}
				if ( ! empty( $args['search'] ) ) {
					$query['search'] = sanitize_text_field( (string) $args['search'] );
				}
				$ids   = \WC_Data_Store::load( 'webhook' )->search_webhooks( $query );
				$hooks = array();
				foreach ( (array) $ids as $id ) {
					$webhook = wc_get_webhook( (int) $id );
					if ( $webhook ) {
						$hooks[] = self::format( $webhook );
					}
				}
				return array(
					'webhooks' => $hooks,
					'page'     => $page,
					'per_page' => $per_page,
				);

			case 'wc_create_webhook':
				$topic = self::topic( (string) ( $args['topic'] ?? '' ) );
				$url   = self::delivery_url( (string) ( $args['delivery_url'] ?? '' ) );

				$generated = false;
				$secret    = isset( $args['secret'] ) ? (string) $args['secret'] : '';
				if ( '' === $secret ) {
					$secret    = wp_generate_password( 50, true, true );
					$generated = true;
				}

				$webhook = new \WC_Webhook();
				$webhook->set_name( ! empty( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : 'Webhook ' . $topic );
				$webhook->set_user_id( get_current_user_id() );
				$webhook->set_topic( $topic );
				$webhook->set_delivery_url( $url );
				$webhook->set_secret( $secret );
				$webhook->set_status( self::status( (string) ( $args['status'] ?? 'active' ) ) );
				$id = $webhook->save();
				if ( ! $id ) {
					throw new \Exception( 'Failed to create webhook' );
				}
				$out = array(
					'id'      => (int) $id,
					'message' => 'Webhook created successfully',
				);
				if ( $generated ) {
					$out['secret'] = $secret;
					$out['note']   = 'Store this secret now; it is not shown again.';
				}
				return $out;

			case 'wc_update_webhook':
				$webhook = self::find( (int) ( $args['id'] ?? 0 ) );
				if ( isset( $args['name'] ) ) {
					$webhook->set_name( sanitize_text_field( (string) $args['name'] ) );
				}
				if ( isset( $args['topic'] ) ) {
					$webhook->set_topic( self::topic( (string) $args['topic'] ) );
				}
				if ( isset( $args['delivery_url'] ) ) {
					$webhook->set_delivery_url( self::delivery_url( (string) $args['delivery_url'] ) );
				}
				if ( isset( $args['secret'] ) && '' !== (string) $args['secret'] ) {
					$webhook->set_secret( (string) $args['secret'] );
				}
				if ( isset( $args['status'] ) ) {
					$webhook->set_status( self::status( (string) $args['status'] ) );
				}
				$webhook->save();
				return array(
					'id'      => $webhook->get_id(),
					'message' => 'Webhook updated successfully',
				);

			case 'wc_delete_webhook':
				$webhook = self::find( (int) ( $args['id'] ?? 0 ) );
				$id      = $webhook->get_id();
				$webhook->delete( true );
				return array(
					'id'      => $id,
					'message' => 'Webhook permanently deleted',
				);
		}
		throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
	}

	private static function find( int $id ) {
		$webhook = $id > 0 ? wc_get_webhook( $id ) : null;
		if ( ! $webhook ) {
			throw new \Exception( 'Webhook not found' );
		}
		return $webhook;
	}

	private static function topic( string $topic ): string {
		$topic = sanitize_text_field( $topic );
		if ( '' === $topic || ! wc_is_webhook_valid_topic( $topic ) ) {
			throw new \Exception( 'Invalid webhook topic. Use resource.event (order.created, product.updated, customer.deleted, coupon.created) or action.woocommerce_*.' );
		}
		return $topic;
	}

	private static function delivery_url( string $url ): string {
		$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			throw new \Exception( 'delivery_url must be a public http(s) URL. Private and loopback addresses are refused.' );
		}
		return $url;
	}

	private static function status( string $status ): string {
		$status = sanitize_key( $status );
		if ( ! array_key_exists( $status, wc_get_webhook_statuses() ) ) {
			throw new \Exception( 'Invalid webhook status. Use active, paused or disabled.' );
		}
		return $status;
	}

	private static function format( $webhook ) {
		$created  = $webhook->get_date_created();
		$modified = $webhook->get_date_modified();
		return array(
			'id'               => $webhook->get_id(),
			'name'             => $webhook->get_name(),
			'status'           => $webhook->get_status(),
			'topic'            => $webhook->get_topic(),
			'resource'         => $webhook->get_resource(),
			'event'            => $webhook->get_event(),
			'delivery_url'     => $webhook->get_delivery_url(),
			'has_secret'       => '' !== (string) $webhook->get_secret(),
			'failure_count'    => (int) $webhook->get_failure_count(),
			'pending_delivery' => (bool) $webhook->get_pending_delivery(),
			'date_created'     => $created ? $created->date( 'Y-m-d H:i:s' ) : null,
			'date_modified'    => $modified ? $modified->date( 'Y-m-d H:i:s' ) : null,
		);
	}
}
