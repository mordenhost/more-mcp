<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Variation_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_product_variations', 'wc_get_variation', 'wc_create_variation', 'wc_update_variation', 'wc_delete_variation', 'wc_batch_update_variations' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
		case 'wc_get_product_variations':
			$product = wc_get_product( intval( $args['product_id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			if ( ! $product->is_type( 'variable' ) ) {
				throw new \Exception( 'Product is not a variable product' );
			}
			$limit         = min( intval( $args['per_page'] ?? 100 ), 100 );
			$variation_ids = array_slice( $product->get_children(), 0, $limit );
			$variations    = array_filter( array_map( 'wc_get_product', $variation_ids ) );
			return array_values( array_map( [ __CLASS__, 'format_variation' ], $variations ) );

		case 'wc_get_variation':
			$variation = wc_get_product( intval( $args['variation_id'] ) );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				throw new \Exception( 'Variation not found' );
			}
			if ( $variation->get_parent_id() !== intval( $args['product_id'] ) ) {
				throw new \Exception( 'Variation does not belong to the specified product' );
			}
			return self::format_variation( $variation );

		case 'wc_create_variation':
			$product = wc_get_product( intval( $args['product_id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			if ( ! $product->is_type( 'variable' ) ) {
				throw new \Exception( 'Product is not a variable product' );
			}
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( intval( $args['product_id'] ) );
			self::apply_variation_fields( $variation, $args );
			$variation_id = $variation->save();
			if ( ! $variation_id ) {
				throw new \Exception( 'Failed to create variation' );
			}
			\WC_Product_Variable::sync( $product );
			return [ 'id' => $variation_id, 'message' => 'Variation created successfully' ];

		case 'wc_update_variation':
			$variation = wc_get_product( intval( $args['variation_id'] ) );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				throw new \Exception( 'Variation not found' );
			}
			if ( $variation->get_parent_id() !== intval( $args['product_id'] ) ) {
				throw new \Exception( 'Variation does not belong to the specified product' );
			}
			self::apply_variation_fields( $variation, $args );
			$variation->save();
			$parent = wc_get_product( $variation->get_parent_id() );
			if ( $parent ) {
				\WC_Product_Variable::sync( $parent );
			}
			return [ 'id' => intval( $args['variation_id'] ), 'message' => 'Variation updated successfully' ];

		case 'wc_delete_variation':
			$variation = wc_get_product( intval( $args['variation_id'] ) );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				throw new \Exception( 'Variation not found' );
			}
			if ( $variation->get_parent_id() !== intval( $args['product_id'] ) ) {
				throw new \Exception( 'Variation does not belong to the specified product' );
			}
			$force = isset( $args['force'] ) ? (bool) $args['force'] : true;
			$variation->delete( $force );
			$parent = wc_get_product( intval( $args['product_id'] ) );
			if ( $parent ) {
				\WC_Product_Variable::sync( $parent );
			}
			return [ 'id' => intval( $args['variation_id'] ), 'deleted' => true, 'force' => $force ];

		case 'wc_batch_update_variations':
			$product = wc_get_product( intval( $args['product_id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			if ( ! $product->is_type( 'variable' ) ) {
				throw new \Exception( 'Product is not a variable product' );
			}
			$result = [ 'create' => [], 'update' => [], 'delete' => [] ];
			foreach ( $args['create'] ?? [] as $data ) {
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id( intval( $args['product_id'] ) );
				self::apply_variation_fields( $variation, $data );
				$new_id            = $variation->save();
				$result['create'][] = [ 'id' => $new_id ];
			}
			foreach ( $args['update'] ?? [] as $data ) {
				$var_id    = intval( $data['variation_id'] ?? 0 );
				$variation = wc_get_product( $var_id );
				if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
					$result['update'][] = [ 'id' => $var_id, 'error' => 'Not found' ];
					continue;
				}
				if ( $variation->get_parent_id() !== intval( $args['product_id'] ) ) {
					$result['update'][] = [ 'id' => $var_id, 'error' => 'Variation does not belong to this product' ];
					continue;
				}
				self::apply_variation_fields( $variation, $data );
				$variation->save();
				$result['update'][] = [ 'id' => $var_id ];
			}
			foreach ( $args['delete'] ?? [] as $var_id ) {
				$variation = wc_get_product( intval( $var_id ) );
				if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
					$result['delete'][] = [ 'id' => $var_id, 'error' => 'Not found' ];
					continue;
				}
				if ( $variation->get_parent_id() !== intval( $args['product_id'] ) ) {
					$result['delete'][] = [ 'id' => $var_id, 'error' => 'Variation does not belong to this product' ];
					continue;
				}
				$variation->delete( true );
				$result['delete'][] = [ 'id' => $var_id, 'deleted' => true ];
			}
			\WC_Product_Variable::sync( $product );
			return $result;


			default:
				throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
		}
	}

	private static function format_variation( $variation ) {
		$attributes = [];
		foreach ( $variation->get_attributes() as $name => $value ) {
			$attributes[] = [ 'name' => $name, 'option' => $value ];
		}
		return [
			'id'             => $variation->get_id(),
			'parent_id'      => $variation->get_parent_id(),
			'status'         => $variation->get_status(),
			'sku'            => $variation->get_sku(),
			'price'          => $variation->get_price(),
			'regular_price'  => $variation->get_regular_price(),
			'sale_price'     => $variation->get_sale_price(),
			'stock_status'   => $variation->get_stock_status(),
			'stock_quantity' => $variation->get_stock_quantity(),
			'manage_stock'   => $variation->get_manage_stock(),
			'weight'         => $variation->get_weight(),
			'dimensions'     => [
				'length' => $variation->get_length(),
				'width'  => $variation->get_width(),
				'height' => $variation->get_height(),
			],
			'description'    => $variation->get_description(),
			'image_id'       => $variation->get_image_id(),
			'attributes'     => $attributes,
			'date_created'   => $variation->get_date_created() ? $variation->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'date_modified'  => $variation->get_date_modified() ? $variation->get_date_modified()->format( 'Y-m-d H:i:s' ) : null,
		];
	}

	private static function apply_variation_fields( \WC_Product_Variation $variation, array $args ) {
		if ( isset( $args['attributes'] ) ) {
			$variation->set_attributes( self::parse_variation_attributes( $args['attributes'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$variation->set_regular_price( sanitize_text_field( $args['regular_price'] ) );
		}
		if ( isset( $args['sale_price'] ) ) {
			$variation->set_sale_price( sanitize_text_field( $args['sale_price'] ) );
		}
		if ( isset( $args['sku'] ) ) {
			$variation->set_sku( sanitize_text_field( $args['sku'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$variation->set_status( in_array( $args['status'], [ 'publish', 'private' ], true ) ? $args['status'] : 'publish' );
		}
		if ( isset( $args['manage_stock'] ) ) {
			$variation->set_manage_stock( (bool) $args['manage_stock'] );
		}
		if ( isset( $args['stock_quantity'] ) ) {
			$variation->set_stock_quantity( intval( $args['stock_quantity'] ) );
		}
		if ( isset( $args['stock_status'] ) ) {
			$variation->set_stock_status( sanitize_text_field( $args['stock_status'] ) );
		}
		if ( isset( $args['weight'] ) ) {
			$variation->set_weight( sanitize_text_field( $args['weight'] ) );
		}
		if ( isset( $args['dimensions'] ) ) {
			if ( isset( $args['dimensions']['length'] ) ) {
				$variation->set_length( sanitize_text_field( $args['dimensions']['length'] ) );
			}
			if ( isset( $args['dimensions']['width'] ) ) {
				$variation->set_width( sanitize_text_field( $args['dimensions']['width'] ) );
			}
			if ( isset( $args['dimensions']['height'] ) ) {
				$variation->set_height( sanitize_text_field( $args['dimensions']['height'] ) );
			}
		}
		if ( isset( $args['description'] ) ) {
			$variation->set_description( wp_kses_post( $args['description'] ) );
		}
		if ( isset( $args['image_id'] ) ) {
			$variation->set_image_id( intval( $args['image_id'] ) );
		}
	}

	private static function parse_variation_attributes( array $attributes ) {
		$parsed = [];
		foreach ( $attributes as $attr ) {
			if ( empty( $attr['name'] ) || ! isset( $attr['option'] ) ) {
				continue;
			}
			
			$parsed[ sanitize_title( $attr['name'] ) ] = sanitize_text_field( $attr['option'] );
		}
		return $parsed;
	}

}
