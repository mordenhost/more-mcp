<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_orders', 'wc_get_order', 'wc_update_order_status', 'wc_create_order', 'wc_update_order', 'wc_add_order_note', 'wc_get_customers', 'wc_get_store_stats' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
		case 'wc_get_orders':
			$per_page = max( 1, min( intval( $args['per_page'] ?? 10 ), 100 ) );
			$page     = max( 1, intval( $args['page'] ?? 1 ) );
			$status   = ! empty( $args['status'] ) ? sanitize_text_field( $args['status'] ) : 'any';
			$result   = wc_get_orders( [
				'limit'    => $per_page,
				'paged'    => $page,
				'status'   => $status,
				'type'     => 'shop_order',
				'orderby'  => 'date',
				'order'    => 'DESC',
				'paginate' => true,
			] );
			return [
				'orders'      => array_map( [ __CLASS__, 'format_order_summary' ], $result->orders ),
				'page'        => $page,
				'per_page'    => $per_page,
				
				'total'       => intval( $result->total ),
				'total_count' => intval( $result->total ),
				'total_pages' => intval( $result->max_num_pages ),
			];

		case 'wc_get_order':
			$order = wc_get_order( intval( $args['id'] ) );
			if ( ! $order || ! $order instanceof \WC_Order ) {
				throw new \Exception( 'Order not found' );
			}
			return self::format_order_detail( $order );

		case 'wc_update_order_status':
			$order = wc_get_order( intval( $args['id'] ) );
			if ( ! $order || ! $order instanceof \WC_Order ) {
				throw new \Exception( 'Order not found' );
			}
			$allowed_statuses = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
			$new_status = sanitize_text_field( $args['status'] );
			if ( ! in_array( $new_status, $allowed_statuses ) ) {
				throw new \Exception( 'Invalid order status' );
			}
			
			
			
			
			$note = ! empty( $args['note'] ) ? wp_kses_post( $args['note'] ) : '';
			$order->update_status( $new_status, $note );
			return [ 'id' => $args['id'], 'status' => $new_status, 'message' => 'Order status updated' ];

		case 'wc_create_order':
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				throw new \Exception( 'edit_shop_orders capability required.' );
			}
			$line_items = isset( $args['line_items'] ) && is_array( $args['line_items'] ) ? $args['line_items'] : [];
			if ( empty( $line_items ) ) {
				throw new \Exception( 'line_items is required and must be a non-empty array.' );
			}
			$new_order = wc_create_order();
			if ( is_wp_error( $new_order ) ) {
				throw new \Exception( 'wc_create_order failed: ' . esc_html( $new_order->get_error_message() ) );
			}
			
			foreach ( $line_items as $item ) {
				$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
				$quantity     = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
				$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
				if ( $product_id <= 0 ) {
					throw new \Exception( 'line_items entry missing product_id.' );
				}
				$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
				if ( ! $product ) {
					throw new \Exception( 'Product not found: ' . esc_html( (string) ( $variation_id > 0 ? $variation_id : $product_id ) ) );
				}
				if ( $variation_id > 0 && (int) $product->get_parent_id() !== $product_id ) {
					throw new \Exception( 'variation_id ' . esc_html( (string) $variation_id ) . ' does not belong to product_id ' . esc_html( (string) $product_id ) . '.' );
				}
				$new_order->add_product( $product, $quantity );
			}
			
			if ( ! empty( $args['shipping_lines'] ) && is_array( $args['shipping_lines'] ) ) {
				foreach ( $args['shipping_lines'] as $sl ) {
					$shipping_item = new \WC_Order_Item_Shipping();
					if ( ! empty( $sl['method_id'] ) )    { $shipping_item->set_method_id( sanitize_text_field( (string) $sl['method_id'] ) ); }
					if ( ! empty( $sl['method_title'] ) ) { $shipping_item->set_method_title( sanitize_text_field( (string) $sl['method_title'] ) ); }
					if ( isset( $sl['total'] ) )          { $shipping_item->set_total( (string) wc_format_decimal( $sl['total'] ) ); }
					$new_order->add_item( $shipping_item );
				}
			}
			if ( ! empty( $args['fee_lines'] ) && is_array( $args['fee_lines'] ) ) {
				foreach ( $args['fee_lines'] as $fee ) {
					$fee_item = new \WC_Order_Item_Fee();
					if ( ! empty( $fee['name'] ) ) { $fee_item->set_name( sanitize_text_field( (string) $fee['name'] ) ); }
					if ( isset( $fee['total'] ) )  { $fee_item->set_total( (string) wc_format_decimal( $fee['total'] ) ); }
					$new_order->add_item( $fee_item );
				}
			}
			
			if ( ! empty( $args['billing'] )  && is_array( $args['billing'] ) )  { $new_order->set_address( self::sanitize_address_fields( $args['billing'] ),  'billing' ); }
			if ( ! empty( $args['shipping'] ) && is_array( $args['shipping'] ) ) { $new_order->set_address( self::sanitize_address_fields( $args['shipping'] ), 'shipping' ); }
			if ( ! empty( $args['customer_id'] ) ) {
				$new_order->set_customer_id( (int) $args['customer_id'] );
			}
			if ( ! empty( $args['payment_method'] ) ) {
				$new_order->set_payment_method( sanitize_text_field( (string) $args['payment_method'] ) );
			}
			if ( ! empty( $args['customer_note'] ) ) {
				$new_order->set_customer_note( wp_kses_post( (string) $args['customer_note'] ) );
			}
			
			if ( ! empty( $args['meta_data'] ) && is_array( $args['meta_data'] ) ) {
				foreach ( $args['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) ) {
						$new_order->update_meta_data( sanitize_text_field( (string) $meta['key'] ), $meta['value'] ?? '' );
					}
				}
			}
			$new_order->calculate_totals();
			$initial_status = ! empty( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : 'pending';
			$allowed_initial = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled' ];
			if ( ! in_array( $initial_status, $allowed_initial, true ) ) {
				$initial_status = 'pending';
			}
			$new_order->set_status( $initial_status );
			$new_order->save();
			if ( ! empty( $args['send_emails'] ) ) {
				$mailer = \WC()->mailer();
				if ( $mailer && isset( $mailer->emails['WC_Email_New_Order'] ) ) {
					$mailer->emails['WC_Email_New_Order']->trigger( $new_order->get_id() );
				}
			}
			return [
				'order_id'  => $new_order->get_id(),
				'order_key' => $new_order->get_order_key(),
				'total'     => $new_order->get_total(),
				'status'    => $new_order->get_status(),
			];

		case 'wc_update_order':
			$upd_order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
			$upd_order    = wc_get_order( $upd_order_id );
			if ( ! $upd_order || ! $upd_order instanceof \WC_Order ) {
				throw new \Exception( 'Order not found: ' . esc_html( (string) $upd_order_id ) );
			}
			if ( ! current_user_can( 'edit_shop_order', $upd_order_id ) ) {
				throw new \Exception( 'edit_shop_order capability required on this order.' );
			}
			if ( ! empty( $args['billing'] ) && is_array( $args['billing'] ) ) {
				$upd_order->set_address( array_merge( self::current_address( $upd_order, 'billing' ), self::sanitize_address_fields( $args['billing'] ) ), 'billing' );
			}
			if ( ! empty( $args['shipping'] ) && is_array( $args['shipping'] ) ) {
				$upd_order->set_address( array_merge( self::current_address( $upd_order, 'shipping' ), self::sanitize_address_fields( $args['shipping'] ) ), 'shipping' );
			}
			if ( isset( $args['customer_note'] ) ) {
				$upd_order->set_customer_note( wp_kses_post( (string) $args['customer_note'] ) );
			}
			if ( ! empty( $args['meta_data'] ) && is_array( $args['meta_data'] ) ) {
				foreach ( $args['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) ) {
						$upd_order->update_meta_data( sanitize_text_field( (string) $meta['key'] ), $meta['value'] ?? '' );
					}
				}
			}
			if ( ! empty( $args['line_items'] ) && is_array( $args['line_items'] ) ) {
				foreach ( $args['line_items'] as $item ) {
					$item_id     = isset( $item['id'] ) ? (int) $item['id'] : 0;
					$product_id  = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
					$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
					$quantity    = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
					if ( $item_id > 0 && $quantity === 0 ) {
						
						$upd_order->remove_item( $item_id );
						continue;
					}
					if ( $item_id > 0 ) {
						
						$existing = $upd_order->get_item( $item_id );
						if ( $existing ) {
							$existing->set_quantity( max( 1, $quantity ) );
							$existing->save();
						}
						continue;
					}
					
					if ( $product_id <= 0 ) {
						throw new \Exception( 'line_items entry without id must include product_id.' );
					}
					$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
					if ( ! $product ) {
						throw new \Exception( 'Product not found: ' . esc_html( (string) ( $variation_id > 0 ? $variation_id : $product_id ) ) );
					}
					if ( $variation_id > 0 && (int) $product->get_parent_id() !== $product_id ) {
						throw new \Exception( 'variation_id ' . esc_html( (string) $variation_id ) . ' does not belong to product_id ' . esc_html( (string) $product_id ) . '.' );
					}
					$upd_order->add_product( $product, max( 1, $quantity ) );
				}
			}
			if ( ! empty( $args['status'] ) ) {
				$new_status_upd = sanitize_text_field( (string) $args['status'] );
				$allowed_upd = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];
				if ( ! in_array( $new_status_upd, $allowed_upd, true ) ) {
					throw new \Exception( 'Invalid order status: ' . esc_html( $new_status_upd ) );
				}
				$upd_order->set_status( $new_status_upd );
			}
			$upd_order->calculate_totals();
			$upd_order->save();
			return [
				'updated' => true,
				'total'   => $upd_order->get_total(),
				'status'  => $upd_order->get_status(),
			];

		case 'wc_add_order_note':
			$note_order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
			$note_order    = wc_get_order( $note_order_id );
			if ( ! $note_order || ! $note_order instanceof \WC_Order ) {
				throw new \Exception( 'Order not found: ' . esc_html( (string) $note_order_id ) );
			}
			if ( ! current_user_can( 'edit_shop_order', $note_order_id ) ) {
				throw new \Exception( 'edit_shop_order capability required on this order.' );
			}
			$note_text = isset( $args['note'] ) ? wp_kses_post( (string) $args['note'] ) : '';
			if ( $note_text === '' ) {
				throw new \Exception( 'note is required.' );
			}
			$is_customer_note = ! empty( $args['customer_note'] );
			$note_id = $note_order->add_order_note( $note_text, $is_customer_note ? 1 : 0 );
			return [ 'note_id' => (int) $note_id ];

		case 'wc_get_customers':
			$limit = min( intval( $args['per_page'] ?? 10 ), 100 );
			$customer_args = [
				'number' => $limit,
				'role'   => 'customer',
			];
			if ( ! empty( $args['search'] ) ) {
				$customer_args['search']         = '*' . sanitize_text_field( $args['search'] ) . '*';
				$customer_args['search_columns']  = [ 'user_login', 'user_email', 'display_name' ];
			}
			$customers = get_users( $customer_args );
			return array_map( function( $user ) {
				$customer = new \WC_Customer( $user->ID );
				return [
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'order_count'  => $customer->get_order_count(),
					'total_spent'  => $customer->get_total_spent(),
					'city'         => $customer->get_billing_city(),
					'country'      => $customer->get_billing_country(),
				];
			}, $customers );

		case 'wc_get_store_stats':
			return self::get_store_stats( $args['period'] ?? 'month' );



			default:
				throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
		}
	}

	private static function sanitize_address_fields( array $address ): array {
		$allowed = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' ];
		$out = [];
		foreach ( $allowed as $key ) {
			if ( isset( $address[ $key ] ) ) {
				$val = (string) $address[ $key ];
				$out[ $key ] = ( $key === 'email' ) ? sanitize_email( $val ) : sanitize_text_field( $val );
			}
		}
		return $out;
	}

	
	private static function current_address( \WC_Order $order, string $type ): array {
		$getter_prefix = 'get_' . $type . '_';
		return [
			'first_name' => $order->{$getter_prefix . 'first_name'}(),
			'last_name'  => $order->{$getter_prefix . 'last_name'}(),
			'company'    => $order->{$getter_prefix . 'company'}(),
			'address_1'  => $order->{$getter_prefix . 'address_1'}(),
			'address_2'  => $order->{$getter_prefix . 'address_2'}(),
			'city'       => $order->{$getter_prefix . 'city'}(),
			'state'      => $order->{$getter_prefix . 'state'}(),
			'postcode'   => $order->{$getter_prefix . 'postcode'}(),
			'country'    => $order->{$getter_prefix . 'country'}(),
			'email'      => ( $type === 'billing' ) ? $order->get_billing_email() : '',
			'phone'      => ( $type === 'billing' ) ? $order->get_billing_phone() : '',
		];
	}

	private static function format_order_summary( $order ) {
		return [
			'id'         => $order->get_id(),
			'status'     => $order->get_status(),
			'total'      => $order->get_total(),
			'currency'   => $order->get_currency(),
			'items'      => $order->get_item_count(),
			'customer'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'date'       => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
		];
	}

	private static function format_order_detail( $order ) {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
				'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
			];
		}
		return [
			'id'              => $order->get_id(),
			'status'          => $order->get_status(),
			'total'           => $order->get_total(),
			'subtotal'        => $order->get_subtotal(),
			'tax'             => $order->get_total_tax(),
			'shipping'        => $order->get_shipping_total(),
			'currency'        => $order->get_currency(),
			'payment_method'  => $order->get_payment_method_title(),
			'customer_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'billing_city'    => $order->get_billing_city(),
			'billing_country' => $order->get_billing_country(),
			'items'           => $items,
			'date_created'    => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'date_paid'       => $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : null,
		];
	}

	private static function get_store_stats( $period ) {
		$periods = [
			'today' => '-1 day',
			'week'  => '-7 days',
			'month' => '-30 days',
			'year'  => '-365 days',
		];
		$after = gmdate( 'Y-m-d', strtotime( $periods[ $period ] ?? $periods['month'] ) );

		$orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => [ 'completed', 'processing' ],
			'type'       => 'shop_order',
			'date_after' => $after,
			'return'     => 'objects',
		] );

		$revenue     = 0;
		$order_count = count( $orders );
		foreach ( $orders as $order ) {
			$revenue += (float) $order->get_total();
		}

		$product_count = wp_count_posts( 'product' );

		return [
			'period'         => $period,
			'revenue'        => round( $revenue, 2 ),
			'order_count'    => $order_count,
			'average_order'  => $order_count > 0 ? round( $revenue / $order_count, 2 ) : 0,
			'total_products' => (int) $product_count->publish,
			'currency'       => get_woocommerce_currency(),
		];
	}

}
