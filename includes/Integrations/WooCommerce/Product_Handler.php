<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_products', 'wc_get_product', 'wc_create_product', 'wc_update_product' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
		case 'wc_get_products':
			$query_args = [
				'limit'  => min( intval( $args['per_page'] ?? 10 ), 100 ),
				'status' => sanitize_text_field( $args['status'] ?? 'publish' ),
				'return' => 'objects',
			];
			if ( ! empty( $args['search'] ) ) {
				$query_args['s'] = sanitize_text_field( $args['search'] );
			}
			if ( ! empty( $args['category'] ) ) {
				$query_args['category'] = [ sanitize_text_field( $args['category'] ) ];
			}
			if ( ! empty( $args['type'] ) ) {
				$query_args['type'] = sanitize_text_field( $args['type'] );
			}
			$has_attr = ! empty( $args['attribute'] );
			$has_term = ! empty( $args['attribute_term'] );
			if ( $has_attr xor $has_term ) {
				throw new \Exception( 'attribute and attribute_term must be provided together.' );
			}
			if ( $has_attr && $has_term ) {
				$taxonomy = sanitize_text_field( $args['attribute'] );
				$term     = sanitize_text_field( $args['attribute_term'] );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					throw new \Exception( 'Unknown attribute taxonomy: ' . $taxonomy );
				}
				$query_args['tax_query'] = [
					[
						'taxonomy' => $taxonomy,
						'field'    => is_numeric( $term ) ? 'term_id' : 'slug',
						'terms'    => is_numeric( $term ) ? intval( $term ) : $term,
					],
				];
			}
			$products = wc_get_products( $query_args );
			return array_map( [ __CLASS__, 'format_product_summary' ], $products );

		case 'wc_get_product':
			$product = wc_get_product( intval( $args['id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			return self::format_product_detail( $product );

		case 'wc_create_product':
			$type              = sanitize_text_field( $args['type'] ?? 'simple' );
			$product_class_map = [
				'simple'   => '\WC_Product_Simple',
				'variable' => '\WC_Product_Variable',
				'grouped'  => '\WC_Product_Grouped',
				'external' => '\WC_Product_External',
			];
			if ( ! isset( $product_class_map[ $type ] ) ) {
				throw new \Exception( 'Unsupported product type: ' . $type . '. Supported types: simple, variable, grouped, external.' );
			}
			$class = $product_class_map[ $type ];
			if ( ! class_exists( $class ) ) {
				throw new \Exception( 'Product class not available: ' . $class . ' (WooCommerce may not be fully loaded)' );
			}
			$product = new $class();
			$product->set_name( sanitize_text_field( $args['name'] ) );
			if ( isset( $args['regular_price'] ) ) {
				$product->set_regular_price( sanitize_text_field( $args['regular_price'] ) );
			}
			if ( isset( $args['sale_price'] ) ) {
				$product->set_sale_price( sanitize_text_field( $args['sale_price'] ) );
			}
			if ( isset( $args['description'] ) ) {
				$product->set_description( wp_kses_post( $args['description'] ) );
			}
			if ( isset( $args['short_description'] ) ) {
				$product->set_short_description( wp_kses_post( $args['short_description'] ) );
			}
			if ( isset( $args['sku'] ) ) {
				$product->set_sku( sanitize_text_field( $args['sku'] ) );
			}
			if ( isset( $args['stock_quantity'] ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( intval( $args['stock_quantity'] ) );
			}
			if ( isset( $args['categories'] ) ) {
				$product->set_category_ids( array_map( 'intval', $args['categories'] ) );
			}
			$product->set_status( in_array( $args['status'] ?? 'draft', [ 'publish', 'draft' ] ) ? $args['status'] : 'draft' );
			$product_id = $product->save();
			if ( ! $product_id ) {
				throw new \Exception( 'Failed to create product' );
			}
			return [ 'id' => $product_id, 'message' => 'Product created successfully', 'url' => get_permalink( $product_id ) ];

		case 'wc_update_product':
			$product = wc_get_product( intval( $args['id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			if ( isset( $args['name'] ) ) {
				$product->set_name( sanitize_text_field( $args['name'] ) );
			}
			if ( isset( $args['regular_price'] ) ) {
				$product->set_regular_price( sanitize_text_field( $args['regular_price'] ) );
			}
			if ( isset( $args['sale_price'] ) ) {
				$product->set_sale_price( sanitize_text_field( $args['sale_price'] ) );
			}
			if ( isset( $args['description'] ) ) {
				$product->set_description( wp_kses_post( $args['description'] ) );
			}
			if ( isset( $args['short_description'] ) ) {
				$product->set_short_description( wp_kses_post( $args['short_description'] ) );
			}
			if ( isset( $args['sku'] ) ) {
				$product->set_sku( sanitize_text_field( $args['sku'] ) );
			}
			if ( isset( $args['status'] ) ) {
				$product->set_status( sanitize_text_field( $args['status'] ) );
			}
			if ( isset( $args['stock_quantity'] ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( intval( $args['stock_quantity'] ) );
			}
			$product->save();
			return [ 'id' => $args['id'], 'message' => 'Product updated successfully' ];


			default:
				throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
		}
	}

	private static function format_product_summary( $product ) {
		return [
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'type'          => $product->get_type(),
			'status'        => $product->get_status(),
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'sku'           => $product->get_sku(),
			'stock_status'  => $product->get_stock_status(),
			'url'           => get_permalink( $product->get_id() ),
		];
	}

	private static function format_product_detail( $product ) {
		return [
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'sku'               => $product->get_sku(),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'weight'            => $product->get_weight(),
			'categories'        => wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] ),
			'tags'              => wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'names' ] ),
			'url'               => get_permalink( $product->get_id() ),
			'date_created'      => $product->get_date_created() ? $product->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
		];
	}

	
}
