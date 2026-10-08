<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Store_Handler {

	const NAMES = array( 'wc_get_product_categories', 'wc_get_payment_gateways', 'wc_get_shipping_methods', 'wc_get_shipping_zones', 'wc_get_tax_rates' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		return array(
			array(
				'name'        => 'wc_get_product_categories',
				'description' => 'List WooCommerce product categories with parent, product count and slug.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'     => array( 'type' => 'string', 'description' => 'Match on category name' ),
						'parent'     => array( 'type' => 'integer', 'description' => 'Only children of this category ID (0 for top level)' ),
						'hide_empty' => array( 'type' => 'boolean', 'description' => 'Skip categories with no products (default false)' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'Categories per page (max 100, default 50)' ),
						'page'       => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'wc_get_payment_gateways',
				'description' => 'List the store\'s payment gateways: id, title, whether each is enabled, and display order. Gateway credentials are not returned.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wc_get_shipping_methods',
				'description' => 'List the shipping method types WooCommerce offers (flat rate, free shipping, local pickup and any added by plugins).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wc_get_shipping_zones',
				'description' => 'List shipping zones with the regions each covers and the shipping methods set up in it, including the "Rest of the world" zone.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wc_get_tax_rates',
				'description' => 'List configured tax rates, optionally for one tax class. Also reports whether taxes are enabled and the available tax classes.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'class'    => array( 'type' => 'string', 'description' => 'Tax class slug; "standard" for the standard class. Omit for all classes.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Rates to return (max 500, default 100)' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
			case 'wc_get_product_categories':
				$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 50 ) ) );
				$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
				$query    = array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => ! empty( $args['hide_empty'] ),
					'number'     => $per_page,
					'offset'     => ( $page - 1 ) * $per_page,
					'orderby'    => 'name',
				);
				if ( ! empty( $args['search'] ) ) {
					$query['search'] = sanitize_text_field( (string) $args['search'] );
				}
				if ( isset( $args['parent'] ) ) {
					$query['parent'] = (int) $args['parent'];
				}
				$terms = get_terms( $query );
				if ( is_wp_error( $terms ) ) {
					throw new \Exception( esc_html( $terms->get_error_message() ) );
				}
				unset( $query['number'], $query['offset'] );
				$total = wp_count_terms( $query );
				return array(
					'categories' => array_map(
						static function ( $term ) {
							return array(
								'id'          => (int) $term->term_id,
								'name'        => $term->name,
								'slug'        => $term->slug,
								'parent'      => (int) $term->parent,
								'description' => $term->description,
								'count'       => (int) $term->count,
							);
						},
						$terms
					),
					'page'       => $page,
					'per_page'   => $per_page,
					'total'      => is_wp_error( $total ) ? count( $terms ) : (int) $total,
				);

			case 'wc_get_payment_gateways':
				$gateways = \WC()->payment_gateways()->payment_gateways();
				$out      = array();
				foreach ( $gateways as $id => $gateway ) {
					$out[] = array(
						'id'                 => (string) $id,
						'title'              => wp_strip_all_tags( (string) $gateway->get_title() ),
						'method_title'       => wp_strip_all_tags( (string) $gateway->get_method_title() ),
						'description'        => wp_strip_all_tags( (string) $gateway->get_description() ),
						'method_description' => wp_strip_all_tags( (string) $gateway->get_method_description() ),
						'enabled'            => 'yes' === $gateway->enabled,
						'order'              => isset( $gateway->order ) ? (int) $gateway->order : 0,
						'supports'           => array_values( (array) $gateway->supports ),
					);
				}
				return $out;

			case 'wc_get_shipping_methods':
				$out = array();
				foreach ( \WC()->shipping()->get_shipping_methods() as $id => $method ) {
					$out[] = array(
						'id'          => (string) $id,
						'title'       => wp_strip_all_tags( (string) $method->get_method_title() ),
						'description' => wp_strip_all_tags( (string) $method->get_method_description() ),
						'supports'    => array_values( (array) $method->supports ),
					);
				}
				return $out;

			case 'wc_get_shipping_zones':
				$zones = array();
				foreach ( \WC_Shipping_Zones::get_zones() as $zone_data ) {
					$zones[] = self::format_zone( new \WC_Shipping_Zone( (int) $zone_data['id'] ) );
				}
				$zones[] = self::format_zone( new \WC_Shipping_Zone( 0 ) );
				return $zones;

			case 'wc_get_tax_rates':
				$classes = \WC_Tax::get_tax_class_slugs();
				$wanted  = isset( $args['class'] ) ? sanitize_title( (string) $args['class'] ) : null;
				if ( 'standard' === $wanted ) {
					$wanted = '';
				}
				$rates = array();
				foreach ( array_merge( array( '' ), $classes ) as $slug ) {
					if ( null !== $wanted && $wanted !== $slug ) {
						continue;
					}
					foreach ( \WC_Tax::get_rates_for_tax_class( $slug ) as $rate ) {
						$rates[] = array(
							'id'       => (int) $rate->tax_rate_id,
							'country'  => $rate->tax_rate_country,
							'state'    => $rate->tax_rate_state,
							'rate'     => $rate->tax_rate,
							'name'     => $rate->tax_rate_name,
							'priority' => (int) $rate->tax_rate_priority,
							'compound' => (bool) (int) $rate->tax_rate_compound,
							'shipping' => (bool) (int) $rate->tax_rate_shipping,
							'order'    => (int) $rate->tax_rate_order,
							'class'    => '' === $slug ? 'standard' : $slug,
						);
					}
				}
				$per_page = max( 1, min( 500, (int) ( $args['per_page'] ?? 100 ) ) );
				$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
				return array(
					'taxes_enabled' => wc_tax_enabled(),
					'tax_classes'   => array_merge( array( 'standard' ), $classes ),
					'rates'         => array_slice( $rates, ( $page - 1 ) * $per_page, $per_page ),
					'page'          => $page,
					'per_page'      => $per_page,
					'total'         => count( $rates ),
				);
		}
		throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
	}

	private static function format_zone( \WC_Shipping_Zone $zone ) {
		$locations = array();
		foreach ( $zone->get_zone_locations() as $location ) {
			$locations[] = array(
				'code' => $location->code,
				'type' => $location->type,
			);
		}
		$methods = array();
		foreach ( $zone->get_shipping_methods( false ) as $method ) {
			$methods[] = array(
				'instance_id' => (int) $method->get_instance_id(),
				'method_id'   => (string) $method->id,
				'title'       => wp_strip_all_tags( (string) $method->get_title() ),
				'enabled'     => 'yes' === $method->enabled,
				'order'       => (int) $method->method_order,
			);
		}
		return array(
			'id'        => (int) $zone->get_id(),
			'name'      => $zone->get_zone_name(),
			'order'     => (int) $zone->get_zone_order(),
			'locations' => $locations,
			'methods'   => $methods,
		);
	}
}
