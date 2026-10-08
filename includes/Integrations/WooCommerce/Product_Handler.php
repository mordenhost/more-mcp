<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_products', 'wc_get_product', 'wc_create_product', 'wc_update_product', 'wc_delete_product', 'wc_batch_update_products' );
		return in_array( $name, $names, true );
	}

	const BATCH_LIMIT = 50;

	public static function get_tools() {
		return [
			[
				'name'        => 'wc_delete_product',
				'description' => 'Delete a WooCommerce product; moves it to the trash by default, set force=true to delete it permanently (a variable product takes its variations with it).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer', 'description' => 'Product ID' ],
						'force' => [ 'type' => 'boolean', 'description' => 'Permanently delete instead of trashing (default: false)' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_batch_update_products',
				'description' => 'Create, update and trash several products in one call (up to ' . self::BATCH_LIMIT . ' items in all). create items take the wc_create_product fields, update items the wc_update_product fields plus id, delete is a list of product IDs to move to the trash. Each item succeeds or fails on its own.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'create' => [ 'type' => 'array', 'description' => 'Products to create', 'items' => [ 'type' => 'object' ] ],
						'update' => [ 'type' => 'array', 'description' => 'Products to update: each must include id', 'items' => [ 'type' => 'object' ] ],
						'delete' => [ 'type' => 'array', 'description' => 'Product IDs to move to the trash', 'items' => [ 'type' => 'integer' ] ],
					],
				],
			],
		];
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
					throw new \Exception( 'Unknown attribute taxonomy: ' . esc_html( $taxonomy ) );
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
				throw new \Exception( 'Unsupported product type: ' . esc_html( $type ) . '. Supported types: simple, variable, grouped, external.' );
			}
			$class = $product_class_map[ $type ];
			if ( ! class_exists( $class ) ) {
				throw new \Exception( 'Product class not available: ' . esc_html( $class ) . ' (WooCommerce may not be fully loaded)' );
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
			if ( isset( $args['stock_status'] ) ) {
				$stock_status = sanitize_text_field( $args['stock_status'] );
				if ( ! in_array( $stock_status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
					throw new \Exception( 'stock_status must be instock, outofstock or onbackorder.' );
				}
				$product->set_stock_status( $stock_status );
			}
			if ( isset( $args['categories'] ) && is_array( $args['categories'] ) ) {
				$product->set_category_ids( array_map( 'intval', $args['categories'] ) );
			}
			if ( isset( $args['featured'] ) ) {
				$product->set_featured( (bool) $args['featured'] );
			}
			if ( isset( $args['weight'] ) ) {
				$product->set_weight( sanitize_text_field( (string) $args['weight'] ) );
			}
			$product->save();
			return [ 'id' => $args['id'], 'message' => 'Product updated successfully' ];

		case 'wc_delete_product':
			$id      = intval( $args['id'] ?? 0 );
			$product = $id > 0 ? wc_get_product( $id ) : false;
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			if ( $product->is_type( 'variation' ) ) {
				throw new \Exception( 'That ID is a variation. Use wc_delete_variation.' );
			}
			if ( ! current_user_can( 'delete_product', $id ) ) {
				throw new \Exception( 'delete_product capability required on this product.' );
			}
			$force = ! empty( $args['force'] );
			if ( ! $force && 'trash' === $product->get_status() ) {
				return [ 'id' => $id, 'message' => 'Product is already in trash' ];
			}
			if ( ! $product->delete( $force ) ) {
				throw new \Exception( 'Failed to delete product' );
			}
			return [ 'id' => $id, 'message' => $force ? 'Product permanently deleted' : 'Product moved to trash' ];

		case 'wc_batch_update_products':
			$create = isset( $args['create'] ) && is_array( $args['create'] ) ? array_values( $args['create'] ) : [];
			$update = isset( $args['update'] ) && is_array( $args['update'] ) ? array_values( $args['update'] ) : [];
			$delete = isset( $args['delete'] ) && is_array( $args['delete'] ) ? array_values( $args['delete'] ) : [];
			if ( empty( $create ) && empty( $update ) && empty( $delete ) ) {
				throw new \Exception( 'Nothing to do: pass create, update and/or delete.' );
			}
			if ( count( $create ) + count( $update ) + count( $delete ) > self::BATCH_LIMIT ) {
				throw new \Exception( esc_html( 'Too many items: a batch takes at most ' . self::BATCH_LIMIT . ' in total.' ) );
			}
			$result = [ 'create' => [], 'update' => [], 'delete' => [] ];
			foreach ( $create as $data ) {
				try {
					$result['create'][] = self::execute_tool( 'wc_create_product', is_array( $data ) ? $data : [] );
				} catch ( \Exception $e ) {
					$result['create'][] = [ 'error' => $e->getMessage() ];
				}
			}
			foreach ( $update as $data ) {
				$data = is_array( $data ) ? $data : [];
				try {
					$result['update'][] = self::execute_tool( 'wc_update_product', $data );
				} catch ( \Exception $e ) {
					$result['update'][] = [ 'id' => intval( $data['id'] ?? 0 ), 'error' => $e->getMessage() ];
				}
			}
			foreach ( $delete as $product_id ) {
				try {
					$result['delete'][] = self::execute_tool( 'wc_delete_product', [ 'id' => $product_id, 'force' => false ] );
				} catch ( \Exception $e ) {
					$result['delete'][] = [ 'id' => intval( $product_id ), 'error' => $e->getMessage() ];
				}
			}
			return $result;

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
