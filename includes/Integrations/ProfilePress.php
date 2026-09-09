<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProfilePress {

	const SUBSCRIPTION_STATUSES = array(
		'active'    => 'Active',
		'pending'   => 'Pending',
		'expired'   => 'Expired',
		'completed' => 'Completed',
		'trialling' => 'Trialling',
		'cancelled' => 'Cancelled',
	);

	const ORDER_STATUSES = array(
		'pending'   => 'Pending',
		'completed' => 'Completed',
		'refunded'  => 'Refunded',
		'failed'    => 'Failed',
	);

	public static function is_available() {
		return defined( 'PPRESS_VERSION_NUMBER' ) || class_exists( 'ProfilePress\Core\Base' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'profilepress' ),
			'capabilities' => array( 'membership' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'ppress_get_status',
				'description' => 'Read ProfilePress membership health: the number of plans, subscription counts with a breakdown by status (active, pending, expired, completed, trialling, cancelled), order counts with a breakdown by status (pending, completed, refunded, failed), the total customer count, and the store currency. Returns aggregate counts only, never a subscription, order, or customer record, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'ppress_get_plans',
				'description' => 'Read the ProfilePress plan catalogue: for each plan its id, name, price, billing frequency, subscription length, signup fee, free-trial terms, and whether it is enabled. Returns plan definitions (access/pricing configuration) only — never a subscription, order, or customer, and never a plan\'s description or internal metadata. Read-only diagnostic; cannot create, edit, or delete a plan.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'ppress_get_coupons',
				'description' => 'List the ProfilePress discount-coupon catalogue: each coupon\'s code, description-free type (flat or percentage discount, and whether it applies to recurring payments), application scope (new purchases only or also upgrades), amount, unit, the plan ids it applies to, its usage limit, and its enabled state and validity window. Read as the coupon definitions ProfilePress stores — the per-coupon usage count is intentionally not computed (it costs one orders-table query per coupon; the usage_limit column is reported instead). Coupons are pricing configuration, not customer data. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum coupons to return (1-50, default 25).',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use membership tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'ProfilePress is not active.' );
		}

		if ( 'ppress_get_plans' === $name ) {
			return self::get_plans();
		}
		if ( 'ppress_get_coupons' === $name ) {
			return self::get_coupons( $args );
		}
		if ( 'ppress_get_status' !== $name ) {
			throw new \Exception( 'Unknown ProfilePress tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function table_total( $suffix ) {
		global $wpdb;
		$table = self::table( $suffix );
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function status_counts( $suffix, $vocabulary ) {
		global $wpdb;
		$table = self::table( $suffix );
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		
		$rows = $wpdb->get_results( "SELECT status AS k, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$raw   = isset( $row['k'] ) ? (string) $row['k'] : '';
				$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$key   = '' === $raw ? 'unspecified' : sanitize_key( $raw );
				$by[ $key ] = array(
					'count' => $count,
					'label' => $vocabulary[ $key ] ?? 'unknown',
				);
				$total += $count;
			}
		}
		return array(
			'total'     => $total,
			'by_status' => $by,
		);
	}

	private static function currency() {
		if ( function_exists( 'ppress_get_currency' ) ) {
			$code = ppress_get_currency();
			if ( is_string( $code ) && '' !== $code ) {
				return $code;
			}
		}
		return null;
	}

	private static function get_status() {
		$plans         = self::table_total( 'ppress_plans' );
		$subscriptions = self::status_counts( 'ppress_subscriptions', self::SUBSCRIPTION_STATUSES );
		$orders        = self::status_counts( 'ppress_orders', self::ORDER_STATUSES );
		$customers     = self::table_total( 'ppress_customers' );

		if ( null === $plans && null === $subscriptions && null === $orders && null === $customers ) {
			return array(
				'provider'  => 'profilepress',
				'available' => false,
				'message'   => 'ProfilePress tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'profilepress',
			'available' => true,
		);

		if ( null !== $plans ) {
			$result['plans'] = array( 'total' => $plans );
		}
		if ( null !== $subscriptions ) {
			$result['subscriptions'] = $subscriptions;
		}
		if ( null !== $orders ) {
			$result['orders'] = $orders;
		}
		if ( null !== $customers ) {
			$result['customers'] = array( 'total' => $customers );
		}

		$currency = self::currency();
		if ( null !== $currency ) {
			$result['currency'] = $currency;
		}

		return $result;
	}

	private static function get_plans() {
		global $wpdb;
		$table = self::table( 'ppress_plans' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'profilepress',
				'available' => false,
				'message'   => 'The ProfilePress plans table was not found.',
			);
		}

		
		
		$rows = $wpdb->get_results(
			"SELECT id, name, price, billing_frequency, subscription_length, signup_fee, free_trial, status FROM {$table} ORDER BY id",
			ARRAY_A
		);

		$plans = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				
				$enabled = isset( $row['status'] ) && 'true' === (string) $row['status'];
				$plans[] = array(
					'id'                  => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'                => isset( $row['name'] ) ? (string) $row['name'] : '',
					'price'               => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
					'billing_frequency'   => isset( $row['billing_frequency'] ) ? (string) $row['billing_frequency'] : '',
					'subscription_length' => isset( $row['subscription_length'] ) ? (string) $row['subscription_length'] : '',
					'signup_fee'          => isset( $row['signup_fee'] ) ? (float) $row['signup_fee'] : 0.0,
					'free_trial'          => isset( $row['free_trial'] ) ? (string) $row['free_trial'] : '',
					'enabled'             => $enabled,
				);
			}
		}

		return array(
			'provider'  => 'profilepress',
			'available' => true,
			'plans'     => $plans,
			'count'     => count( $plans ),
		);
	}

	private static function get_coupons( $args ) {
		global $wpdb;
		$table = self::table( 'ppress_coupons' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'profilepress',
				'available' => false,
				'message'   => 'The ProfilePress coupons table was not found (membership add-on not active or no coupons created yet).',
			);
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 25;
		$limit = max( 1, min( 50, $limit ) );

		
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, code, coupon_application, type, amount, unit, plan_ids, usage_limit, status, start_date, end_date FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$coupons = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				
				$plan_ids = array();
				if ( ! empty( $row['plan_ids'] ) ) {
					$decoded = json_decode( (string) $row['plan_ids'], true );
					if ( is_array( $decoded ) ) {
						$plan_ids = array_map( 'intval', $decoded );
					}
				}
				$coupons[] = array(
					'id'                 => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'code'               => isset( $row['code'] ) ? (string) $row['code'] : '',
					'type'               => isset( $row['type'] ) ? (string) $row['type'] : '',
					'application'        => isset( $row['coupon_application'] ) ? (string) $row['coupon_application'] : '',
					'amount'             => isset( $row['amount'] ) ? (float) $row['amount'] : 0.0,
					'unit'               => isset( $row['unit'] ) ? (string) $row['unit'] : '',
					'plan_ids'           => $plan_ids,
					'usage_limit'        => isset( $row['usage_limit'] ) && '' !== $row['usage_limit'] ? (int) $row['usage_limit'] : null,
					'enabled'            => isset( $row['status'] ) && 'true' === (string) $row['status'],
					'start_date'         => ! empty( $row['start_date'] ) ? (string) $row['start_date'] : null,
					'end_date'           => ! empty( $row['end_date'] ) ? (string) $row['end_date'] : null,
				);
			}
		}

		return array(
			'provider'  => 'profilepress',
			'available' => true,
			'count'     => count( $coupons ),
			'coupons'   => $coupons,
		);
	}
}
