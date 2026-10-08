<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Report_Handler {

	const NAMES = array( 'wc_report_orders', 'wc_report_customers', 'wc_report_products', 'wc_report_top_sellers' );

	const ORDER_SCAN_CAP = 2000;

	const SALES_STATUSES = array( 'completed', 'processing' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		$range = array(
			'period'   => array( 'type' => 'string', 'enum' => array( 'today', 'week', 'month', 'year' ), 'description' => 'Relative period ending now (default month). Ignored when date_min is given.' ),
			'date_min' => array( 'type' => 'string', 'description' => 'Start date, YYYY-MM-DD (inclusive)' ),
			'date_max' => array( 'type' => 'string', 'description' => 'End date, YYYY-MM-DD (inclusive, default today)' ),
		);
		return array(
			array(
				'name'        => 'wc_report_orders',
				'description' => 'Order report for a period: order count by status, revenue (completed and processing), refunds, items sold and average order value.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $range,
				),
			),
			array(
				'name'        => 'wc_report_customers',
				'description' => 'Customer report for a period: total customers, new sign-ups, orders from registered customers versus guests, and the top customers by spend.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge( $range, array( 'limit' => array( 'type' => 'integer', 'description' => 'Top customers to list (max 50, default 10)' ) ) ),
				),
			),
			array(
				'name'        => 'wc_report_products',
				'description' => 'Catalog report: products by status and type, stock status counts, and products at or below their low-stock threshold.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Low-stock products to list (max 100, default 20)' ),
					),
				),
			),
			array(
				'name'        => 'wc_report_top_sellers',
				'description' => 'Best-selling products for a period by units sold, with revenue. Variations roll up to their parent product.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge( $range, array( 'limit' => array( 'type' => 'integer', 'description' => 'Products to list (max 50, default 10)' ) ) ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
			case 'wc_report_orders':
				return self::orders_report( $args );
			case 'wc_report_customers':
				return self::customers_report( $args );
			case 'wc_report_products':
				return self::products_report( $args );
			case 'wc_report_top_sellers':
				return self::top_sellers_report( $args );
		}
		throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
	}

	

	
	public static function range( array $args ): array {
		$now = time();
		if ( ! empty( $args['date_min'] ) ) {
			$min = self::parse_date( (string) $args['date_min'], 'date_min' );
			$max = ! empty( $args['date_max'] ) ? self::parse_date( (string) $args['date_max'], 'date_max' ) : strtotime( gmdate( 'Y-m-d', $now ) . ' 00:00:00 UTC' );
			if ( $max < $min ) {
				throw new \Exception( 'date_max must not be before date_min.' );
			}
			$from = $min;
			$to   = $max + DAY_IN_SECONDS - 1;
		} else {
			$periods = array(
				'today' => '-1 day',
				'week'  => '-7 days',
				'month' => '-30 days',
				'year'  => '-365 days',
			);
			$period  = isset( $args['period'] ) ? (string) $args['period'] : 'month';
			if ( ! isset( $periods[ $period ] ) ) {
				throw new \Exception( 'period must be today, week, month or year.' );
			}
			$from = strtotime( $periods[ $period ], $now );
			$to   = $now;
		}
		return array(
			'from'     => (int) $from,
			'to'       => (int) $to,
			'date_min' => gmdate( 'Y-m-d', (int) $from ),
			'date_max' => gmdate( 'Y-m-d', (int) $to ),
		);
	}

	private static function parse_date( string $value, string $field ): int {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			throw new \Exception( esc_html( $field ) . ' must be a date in YYYY-MM-DD format.' );
		}
		$ts = strtotime( $value . ' 00:00:00 UTC' );
		if ( false === $ts ) {
			throw new \Exception( esc_html( $field ) . ' is not a valid date.' );
		}
		return (int) $ts;
	}

	private static function fetch_orders( array $range, array $status ): array {
		$query = array(
			'type'         => 'shop_order',
			'limit'        => self::ORDER_SCAN_CAP,
			'paginate'     => true,
			'return'       => 'objects',
			'orderby'      => 'date',
			'order'        => 'DESC',
			'date_created' => $range['from'] . '...' . $range['to'],
		);
		if ( ! empty( $status ) ) {
			$query['status'] = $status;
		}
		$result = wc_get_orders( $query );
		$orders = array();
		foreach ( (array) $result->orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$orders[] = $order;
			}
		}
		return array(
			'orders'    => $orders,
			'total'     => (int) $result->total,
			'truncated' => (int) $result->total > count( $orders ),
		);
	}

	private static function window( array $range ): array {
		return array(
			'date_min' => $range['date_min'],
			'date_max' => $range['date_max'],
		);
	}

	

	private static function orders_report( array $args ): array {
		$range = self::range( $args );

		$by_status = array();
		$total     = 0;
		foreach ( array_keys( wc_get_order_statuses() ) as $key ) {
			$status               = 0 === strpos( $key, 'wc-' ) ? substr( $key, 3 ) : $key;
			$count                = wc_get_orders(
				array(
					'type'         => 'shop_order',
					'status'       => $status,
					'limit'        => 1,
					'paginate'     => true,
					'return'       => 'ids',
					'date_created' => $range['from'] . '...' . $range['to'],
				)
			);
			$by_status[ $status ] = (int) $count->total;
			$total               += (int) $count->total;
		}

		$sales   = self::fetch_orders( $range, self::SALES_STATUSES );
		$revenue = 0.0;
		$refunds = 0.0;
		$items   = 0;
		foreach ( $sales['orders'] as $order ) {
			$revenue += (float) $order->get_total();
			$refunds += (float) $order->get_total_refunded();
			$items   += (int) $order->get_item_count();
		}
		$sale_count = count( $sales['orders'] );

		return array_merge(
			self::window( $range ),
			array(
				'currency'      => get_woocommerce_currency(),
				'total_orders'  => $total,
				'by_status'     => $by_status,
				'sales_orders'  => $sale_count,
				'revenue'       => round( $revenue, 2 ),
				'refunded'      => round( $refunds, 2 ),
				'net_revenue'   => round( $revenue - $refunds, 2 ),
				'items_sold'    => $items,
				'average_order' => $sale_count > 0 ? round( $revenue / $sale_count, 2 ) : 0,
				'truncated'     => $sales['truncated'],
			)
		);
	}

	private static function customers_report( array $args ): array {
		$range = self::range( $args );
		$limit = max( 1, min( 50, (int) ( $args['limit'] ?? 10 ) ) );

		$counts = count_users();
		$total  = (int) ( $counts['avail_roles']['customer'] ?? 0 );

		$new_query = new \WP_User_Query(
			array(
				'role'        => 'customer',
				'number'      => 1,
				'count_total' => true,
				'fields'      => 'ID',
				'date_query'  => array(
					array(
						'column'    => 'user_registered',
						'after'     => gmdate( 'Y-m-d H:i:s', $range['from'] ),
						'before'    => gmdate( 'Y-m-d H:i:s', $range['to'] ),
						'inclusive' => true,
					),
				),
			)
		);

		$sales      = self::fetch_orders( $range, self::SALES_STATUSES );
		$registered = 0;
		$guest      = 0;
		$buyers     = array();
		foreach ( $sales['orders'] as $order ) {
			$customer_id = (int) $order->get_customer_id();
			if ( $customer_id > 0 ) {
				++$registered;
				$key = 'u' . $customer_id;
			} else {
				++$guest;
				$key = 'g' . strtolower( (string) $order->get_billing_email() );
			}
			if ( ! isset( $buyers[ $key ] ) ) {
				$buyers[ $key ] = array(
					'customer_id' => $customer_id,
					'name'        => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email'       => $order->get_billing_email(),
					'orders'      => 0,
					'total_spent' => 0.0,
				);
			}
			++$buyers[ $key ]['orders'];
			$buyers[ $key ]['total_spent'] += (float) $order->get_total();
		}
		uasort(
			$buyers,
			static function ( $a, $b ) {
				return $b['total_spent'] <=> $a['total_spent'];
			}
		);
		$top = array();
		foreach ( array_slice( array_values( $buyers ), 0, $limit ) as $buyer ) {
			$buyer['total_spent'] = round( $buyer['total_spent'], 2 );
			$top[]                = $buyer;
		}

		return array_merge(
			self::window( $range ),
			array(
				'currency'          => get_woocommerce_currency(),
				'total_customers'   => $total,
				'new_customers'     => (int) $new_query->get_total(),
				'orders_registered' => $registered,
				'orders_guest'      => $guest,
				'unique_buyers'     => count( $buyers ),
				'top_customers'     => $top,
				'truncated'         => $sales['truncated'],
			)
		);
	}

	private static function products_report( array $args ): array {
		$limit = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );

		$counts = (array) wp_count_posts( 'product' );
		$status = array();
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'trash' ) as $key ) {
			$status[ $key ] = (int) ( $counts[ $key ] ?? 0 );
		}

		$count = static function ( array $query ): int {
			$result = wc_get_products(
				array_merge(
					array(
						'limit'    => 1,
						'paginate' => true,
						'return'   => 'ids',
						'status'   => 'publish',
					),
					$query
				)
			);
			return (int) $result->total;
		};

		$types = array();
		foreach ( array( 'simple', 'variable', 'grouped', 'external' ) as $type ) {
			$types[ $type ] = $count( array( 'type' => $type ) );
		}
		$stock = array();
		foreach ( array( 'instock', 'outofstock', 'onbackorder' ) as $key ) {
			$stock[ $key ] = $count( array( 'stock_status' => $key ) );
		}

		$low     = array();
		$ids     = wc_get_products(
			array(
				'limit'        => self::ORDER_SCAN_CAP,
				'return'       => 'ids',
				'status'       => 'publish',
				'manage_stock' => true,
				'stock_status' => 'instock',
			)
		);
		$scanned = 0;
		foreach ( $ids as $id ) {
			++$scanned;
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$quantity = $product->get_stock_quantity();
			if ( null === $quantity ) {
				continue;
			}
			if ( (int) $quantity <= (int) wc_get_low_stock_amount( $product ) ) {
				$low[] = array(
					'id'             => $product->get_id(),
					'name'           => $product->get_name(),
					'sku'            => $product->get_sku(),
					'stock_quantity' => (int) $quantity,
					'threshold'      => (int) wc_get_low_stock_amount( $product ),
				);
			}
		}
		usort(
			$low,
			static function ( $a, $b ) {
				return $a['stock_quantity'] <=> $b['stock_quantity'];
			}
		);

		return array(
			'by_status'       => $status,
			'by_type'         => $types,
			'stock_status'    => $stock,
			'low_stock_count' => count( $low ),
			'low_stock'       => array_slice( $low, 0, $limit ),
			'low_stock_scan'  => $scanned,
		);
	}

	private static function top_sellers_report( array $args ): array {
		$range = self::range( $args );
		$limit = max( 1, min( 50, (int) ( $args['limit'] ?? 10 ) ) );

		$sales    = self::fetch_orders( $range, self::SALES_STATUSES );
		$products = array();
		foreach ( $sales['orders'] as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof \WC_Order_Item_Product ) {
					continue;
				}
				$product_id = (int) $item->get_product_id();
				if ( $product_id <= 0 ) {
					continue;
				}
				if ( ! isset( $products[ $product_id ] ) ) {
					$products[ $product_id ] = array(
						'product_id' => $product_id,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'revenue'    => 0.0,
					);
				}
				$products[ $product_id ]['quantity'] += (int) $item->get_quantity();
				$products[ $product_id ]['revenue']  += (float) $item->get_total();
			}
		}
		uasort(
			$products,
			static function ( $a, $b ) {
				$by_units = $b['quantity'] <=> $a['quantity'];
				return 0 !== $by_units ? $by_units : $b['revenue'] <=> $a['revenue'];
			}
		);
		$top = array();
		foreach ( array_slice( array_values( $products ), 0, $limit ) as $row ) {
			$product        = wc_get_product( $row['product_id'] );
			$row['name']    = $product ? $product->get_name() : $row['name'];
			$row['sku']     = $product ? $product->get_sku() : '';
			$row['revenue'] = round( $row['revenue'], 2 );
			$top[]          = $row;
		}

		return array_merge(
			self::window( $range ),
			array(
				'currency'       => get_woocommerce_currency(),
				'orders_scanned' => count( $sales['orders'] ),
				'top_sellers'    => $top,
				'truncated'      => $sales['truncated'],
			)
		);
	}
}
