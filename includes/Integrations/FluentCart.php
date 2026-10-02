<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCart {

	const PRODUCT_CPT = 'fluent-products';

	const PAYMENT_STATUSES = array(
		'pending',
		'paid',
		'partially_paid',
		'failed',
		'refunded',
		'partially_refunded',
		'authorized',
		'payment_scheduled',
	);

	const SUBSCRIPTION_STATUSES = array(
		'pending',
		'intended',
		'trialing',
		'active',
		'canceled',
		'paused',
		'past_due',
		'expired',
		'failing',
		'expiring',
		'completed',
	);

	public static function is_available() {
		if ( defined( 'FLUENTCART_VERSION' ) || class_exists( '\FluentCart\App\Helpers\Status' ) ) {
			return true;
		}

		if ( function_exists( 'post_type_exists' ) && post_type_exists( self::PRODUCT_CPT ) ) {
			return null !== self::table( 'fct_orders' );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'fluentcart' ),
			'capabilities' => array( 'commerce' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'fluentcart_get_status',
				'description' => 'Read FluentCart store scale: product counts by post status, order counts by payment status (pending, paid, refunded, failed, etc.), total customers, subscription counts by status, and the store currency. Aggregate counts and totals only, never an order record, a purchase, a subscriber, or a customer identity, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'fluentcart_get_product_catalog',
				'description' => 'List the published FluentCart products (by page): each product\'s id, title, and its variations (variation title, SKU, price, fulfillment type single/subscription, stock status, and active flag). Product and variation DEFINITIONS only — no order or purchase data, no customer data, no stock quantities beyond the coarse in/out status. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page of products to return (1-indexed). Default 1.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Products per page (1-100). Default 50.',
						),
					),
				),
			),
			array(
				'name'        => 'fluentcart_get_coupon_catalog',
				'description' => 'List the store\'s coupon codes (by page): each coupon\'s title, code, type (percent or flat), amount, status, use count, and start/end dates. Coupon DEFINITIONS only — never a redemption, an order, a purchase, or a customer identity. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page of coupons to return (1-indexed). Default 1.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Coupons per page (1-100). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use commerce tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'FluentCart is not active.' );
		}

		if ( 'fluentcart_get_product_catalog' === $name ) {
			return self::get_product_catalog( $args );
		}
		if ( 'fluentcart_get_coupon_catalog' === $name ) {
			return self::get_coupon_catalog( $args );
		}
		if ( 'fluentcart_get_status' !== $name ) {
			throw new \Exception( 'Unknown commerce tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function get_status() {
		
		$products_by_status = array();
		$products_total     = 0;
		if ( function_exists( 'wp_count_posts' ) ) {
			$counts = wp_count_posts( self::PRODUCT_CPT );
			foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
				$n                             = isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
				$products_by_status[ $status ] = $n;
				$products_total               += $n;
			}
		}

		return array(
			'provider'                => 'fluentcart',
			'products_total'          => $products_total,
			'products_by_status'      => $products_by_status,
			'orders_by_status'        => self::counts_by_column( 'fct_orders', 'payment_status', self::PAYMENT_STATUSES ),
			'orders_total'            => self::table_total( 'fct_orders' ),
			'customers_total'         => self::table_total( 'fct_customers' ),
			'subscriptions_by_status' => self::counts_by_column( 'fct_subscriptions', 'status', self::SUBSCRIPTION_STATUSES ),
			'subscriptions_total'     => self::table_total( 'fct_subscriptions' ),
			'currency'                => self::currency(),
		);
	}

	private static function get_product_catalog( $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		if ( $per_page < 1 ) {
			$per_page = 50;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider'       => 'fluentcart',
				'page'           => $page,
				'per_page'       => $per_page,
				'products_total' => 0,
				'product_count'  => 0,
				'products'       => array(),
			);
		}

		$q = new \WP_Query(
			array(
				'post_type'      => self::PRODUCT_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$ids   = is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : array();
		$total = isset( $q->found_posts ) ? (int) $q->found_posts : count( $ids );

		$products = array();
		foreach ( $ids as $id ) {
			$products[] = array(
				'product_id' => (int) $id,
				'title'      => (string) get_the_title( $id ),
				'variations' => self::variations_for( $id ),
			);
		}

		return array(
			'provider'       => 'fluentcart',
			'page'           => $page,
			'per_page'       => $per_page,
			'products_total' => $total,
			'product_count'  => count( $products ),
			'products'       => $products,
		);
	}

	private static function get_coupon_catalog( $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		if ( $per_page < 1 ) {
			$per_page = 50;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		global $wpdb;
		$table = self::table( 'fct_coupons' );
		if ( null === $table || ! is_object( $wpdb ) ) {
			return array(
				'provider'      => 'fluentcart',
				'page'          => $page,
				'per_page'      => $per_page,
				'coupons_total' => 0,
				'coupon_count'  => 0,
				'coupons'       => array(),
			);
		}

		
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, code, priority, type, amount, use_count, status, start_date, end_date FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);

		$coupons = array();
		foreach ( $rows as $row ) {
			$get = function ( $field ) use ( $row ) {
				if ( is_object( $row ) ) {
					return isset( $row->{$field} ) ? $row->{$field} : null;
				}
				return is_array( $row ) && isset( $row[ $field ] ) ? $row[ $field ] : null;
			};
			$coupons[] = array(
				'title'      => (string) $get( 'title' ),
				'code'       => (string) $get( 'code' ),
				'type'       => (string) $get( 'type' ),
				'amount'     => null === $get( 'amount' ) ? null : (float) $get( 'amount' ),
				'status'     => (string) $get( 'status' ),
				'use_count'  => (int) $get( 'use_count' ),
				'start_date' => self::nullable_string( $get( 'start_date' ) ),
				'end_date'   => self::nullable_string( $get( 'end_date' ) ),
			);
		}

		return array(
			'provider'      => 'fluentcart',
			'page'          => $page,
			'per_page'      => $per_page,
			'coupons_total' => count( $coupons ),
			'coupon_count'  => count( $coupons ),
			'coupons'       => $coupons,
		);
	}

	private static function nullable_string( $value ) {
		return ( null === $value || '' === $value ) ? null : (string) $value;
	}

	private static function variations_for( $product_id ) {
		global $wpdb;
		$table = self::table( 'fct_product_variations' );
		if ( null === $table || ! is_object( $wpdb ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT variation_title, sku, item_price, fulfillment_type, stock_status, item_status FROM {$table} WHERE post_id = %d ORDER BY serial_index ASC, id ASC",
				(int) $product_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'variation_title'  => isset( $row['variation_title'] ) ? (string) $row['variation_title'] : '',
				'sku'              => isset( $row['sku'] ) ? (string) $row['sku'] : '',
				'price'            => isset( $row['item_price'] ) ? (float) $row['item_price'] : null,
				'fulfillment_type' => isset( $row['fulfillment_type'] ) ? (string) $row['fulfillment_type'] : '',
				'stock_status'     => isset( $row['stock_status'] ) ? (string) $row['stock_status'] : '',
				'item_status'      => isset( $row['item_status'] ) ? (string) $row['item_status'] : '',
			);
		}
		return $out;
	}

	private static function counts_by_column( $suffix, $column, array $statuses ) {
		global $wpdb;
		$table = self::table( $suffix );
		if ( null === $table || ! is_object( $wpdb ) ) {
			return null;
		}

		$out = array();
		foreach ( $statuses as $status ) {
			$out[ $status ] = 0;
		}

		
		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS c FROM {$table} GROUP BY {$column}", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$key = isset( $row['k'] ) ? (string) $row['k'] : '';
				if ( '' === $key ) {
					continue;
				}
				$out[ $key ] = isset( $row['c'] ) ? (int) $row['c'] : 0;
			}
		}
		return $out;
	}

	private static function table_total( $suffix ) {
		global $wpdb;
		$table = self::table( $suffix );
		if ( null === $table || ! is_object( $wpdb ) ) {
			return null;
		}
		
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function currency() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		$settings = get_option( 'fluent_cart_store_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['currency'] ) ) {
			return (string) $settings['currency'];
		}
		return null;
	}

	private static function table( $suffix ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return null;
		}
		$full = $wpdb->prefix . $suffix;
		
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
		return ( $found === $full ) ? $full : null;
	}
}
