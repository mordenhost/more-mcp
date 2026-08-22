<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attribute_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_product_attributes', 'wc_get_attribute_terms', 'wc_create_product_attribute', 'wc_set_product_attributes' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
		case 'wc_get_product_attributes':
			$attributes = wc_get_attribute_taxonomies();
			return array_values( array_map( function( $attr ) {
				return [
					'id'           => (int) $attr->attribute_id,
					'name'         => $attr->attribute_label,
					'slug'         => wc_attribute_taxonomy_name( $attr->attribute_name ),
					'type'         => $attr->attribute_type,
					'order_by'     => $attr->attribute_orderby,
					'has_archives' => (bool) $attr->attribute_public,
				];
			}, $attributes ) );

		case 'wc_get_attribute_terms':
			if ( ! empty( $args['attribute_id'] ) ) {
				$attr_obj = wc_get_attribute( intval( $args['attribute_id'] ) );
				if ( ! $attr_obj || is_wp_error( $attr_obj ) ) {
					throw new \Exception( 'Attribute not found' );
				}
				
				$taxonomy = $attr_obj->slug;
			} elseif ( ! empty( $args['taxonomy'] ) ) {
				$taxonomy = sanitize_text_field( $args['taxonomy'] );
			} else {
				throw new \Exception( 'Either taxonomy or attribute_id is required' );
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				throw new \Exception( 'Taxonomy does not exist: ' . esc_html( $taxonomy ) );
			}
			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => (bool) ( $args['hide_empty'] ?? false ),
			] );
			if ( is_wp_error( $terms ) ) {
				throw new \Exception( esc_html( $terms->get_error_message() ) );
			}
			return array_values( array_map( function( $term ) {
				return [
					'id'    => $term->term_id,
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count,
				];
			}, $terms ) );

		case 'wc_create_product_attribute':
			$attr_data = [
				'name'         => sanitize_text_field( $args['name'] ),
				'slug'         => sanitize_title( $args['slug'] ?? $args['name'] ),
				'type'         => in_array( $args['type'] ?? 'select', [ 'select', 'text', 'color', 'image', 'button' ], true ) ? ( $args['type'] ?? 'select' ) : 'select',
				'order_by'     => in_array( $args['order_by'] ?? 'menu_order', [ 'menu_order', 'name', 'name_num', 'id' ], true ) ? ( $args['order_by'] ?? 'menu_order' ) : 'menu_order',
				'has_archives' => (bool) ( $args['has_archives'] ?? false ),
			];
			$new_id = wc_create_attribute( $attr_data );
			if ( is_wp_error( $new_id ) ) {
				throw new \Exception( esc_html( $new_id->get_error_message() ) );
			}
			$new_taxonomy = wc_attribute_taxonomy_name( $attr_data['slug'] );
			return [
				'id'      => $new_id,
				'slug'    => $new_taxonomy,
				'message' => 'Attribute created successfully',
			];

		case 'wc_set_product_attributes':
			$product = wc_get_product( intval( $args['product_id'] ) );
			if ( ! $product ) {
				throw new \Exception( 'Product not found' );
			}
			$existing_attribute_count = count( $product->get_attributes() );
			$product_attributes = [];
			$auto_position      = 0;
			foreach ( $args['attributes'] as $attr_data ) {
				$attribute = new \WC_Product_Attribute();
				$attr_id   = intval( $attr_data['id'] ?? 0 );
				$attribute->set_id( $attr_id );
				$attribute->set_position( isset( $attr_data['position'] ) ? intval( $attr_data['position'] ) : $auto_position );
				$attribute->set_visible( (bool) ( $attr_data['visible'] ?? true ) );
				$attribute->set_variation( (bool) ( $attr_data['variation'] ?? false ) );
				if ( $attr_id > 0 ) {
					$global_attr = wc_get_attribute( $attr_id );
					if ( ! $global_attr || is_wp_error( $global_attr ) ) {
						throw new \Exception( 'Attribute ID not found: ' . esc_html( $attr_id ) );
					}
					
					$taxonomy = $global_attr->slug;
					$attribute->set_name( $taxonomy );
					$term_ids = [];
					foreach ( $attr_data['options'] ?? [] as $option ) {
						$term = get_term_by( 'slug', sanitize_title( $option ), $taxonomy );
						if ( ! $term ) {
							$term = get_term_by( 'name', sanitize_text_field( $option ), $taxonomy );
						}
						if ( $term ) {
							$term_ids[] = $term->term_id;
						}
					}
					$attribute->set_options( $term_ids );
				} else {
					$attribute->set_name( sanitize_text_field( $attr_data['name'] ?? '' ) );
					$attribute->set_options( array_map( 'sanitize_text_field', $attr_data['options'] ?? [] ) );
				}
				$product_attributes[] = $attribute;
				++$auto_position;
			}
			$product->set_attributes( $product_attributes );
			$product->save();
			$response = [
				'id'              => intval( $args['product_id'] ),
				'attribute_count' => count( $product_attributes ),
				'message'         => 'Product attributes updated successfully',
			];
			if ( $existing_attribute_count > 0 ) {
				$response['warning'] = sprintf(
					'This operation replaced %d existing attribute(s). Any variations using removed attributes may be affected.',
					$existing_attribute_count
				);
			}
			return $response;


			default:
				throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
		}
	}
}
