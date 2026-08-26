<?php

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(
			
			'wc_get_products'   => array( 'type' => 'array', 'items' => self::product_summary_schema() ),
			'wc_get_product'    => self::product_full_schema(),
			'wc_create_product' => self::id_message_schema( array( 'url' => array( 'type' => 'string' ) ) ),
			'wc_update_product' => self::id_message_schema(),

			'wc_get_orders'            => array(
				'type'       => 'object',
				'properties' => array(
					'orders'      => array( 'type' => 'array', 'items' => self::order_summary_schema() ),
					'total_count' => array( 'type' => 'integer' ),
				),
			),
			'wc_get_order'             => self::order_full_schema(),
			'wc_update_order_status'   => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'status'  => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
				),
			),

			'wc_get_customers'     => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'email'       => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'order_count' => array( 'type' => 'integer' ),
						'total_spent' => array( 'type' => array( 'number', 'string' ) ),
					),
				),
			),
			'wc_get_store_stats'   => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'period'         => array( 'type' => 'string' ),
					'total_revenue'  => array( 'type' => array( 'number', 'string' ) ),
					'total_orders'   => array( 'type' => 'integer' ),
					'average_order'  => array( 'type' => array( 'number', 'string' ) ),
				),
			),

			'wc_get_product_variations'   => array( 'type' => 'array', 'items' => self::variation_schema() ),
			'wc_get_variation'            => self::variation_schema(),
			'wc_create_variation'         => self::id_message_schema(),
			'wc_update_variation'         => self::id_message_schema(),
			'wc_delete_variation'         => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'deleted' => array( 'type' => 'boolean' ),
					'force'   => array( 'type' => 'boolean' ),
				),
			),
			'wc_batch_update_variations'  => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),

			'wc_get_product_attributes'   => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'type'        => array( 'type' => 'string' ),
						'order_by'    => array( 'type' => 'string' ),
					),
				),
			),
			'wc_get_attribute_terms'      => array(
				'type'  => 'array',
				'items' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'count'       => array( 'type' => 'integer' ),
					),
				),
			),
			'wc_create_product_attribute' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'id'   => array( 'type' => 'integer' ),
					'name' => array( 'type' => 'string' ),
					'slug' => array( 'type' => 'string' ),
				),
			),
			'wc_set_product_attributes'   => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),

			'wc_get_coupons'         => array( 'type' => 'array', 'items' => self::coupon_schema() ),
			'wc_get_coupon'          => self::coupon_schema(),
			'wc_get_coupon_count'    => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'integer' ),
			),
			'wc_create_coupon'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'code'    => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'wc_update_coupon'       => self::id_message_schema(),
			'wc_delete_coupon'       => self::id_message_schema(),
			'wc_empty_coupon_trash'  => array(
				'type'       => 'object',
				'properties' => array(
					'deleted' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
		);
	}

	private static function product_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'    => array( 'type' => 'integer' ),
				'name'  => array( 'type' => 'string' ),
				'price' => array( 'type' => array( 'string', 'number' ) ),
				'type'  => array( 'type' => 'string' ),
			),
		);
	}

	private static function product_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'   => array( 'type' => 'integer' ),
				'name' => array( 'type' => 'string' ),
			),
		);
	}

	private static function order_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string' ),
				'total'  => array( 'type' => array( 'string', 'number' ) ),
			),
		);
	}

	private static function order_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string' ),
			),
		);
	}

	private static function variation_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'    => array( 'type' => 'integer' ),
				'price' => array( 'type' => array( 'string', 'number' ) ),
			),
		);
	}

	private static function coupon_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'   => array( 'type' => 'integer' ),
				'code' => array( 'type' => 'string' ),
			),
		);
	}

	private static function id_message_schema( array $extras = array() ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array_merge(
				array(
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
				$extras
			),
		);
	}
}
