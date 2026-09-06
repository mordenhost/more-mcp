<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestrictContent {

	const MEMBERSHIP_STATUSES = array(
		'active'    => 'Active',
		'inactive'  => 'Inactive',
		'pending'   => 'Pending',
		'cancelled' => 'Cancelled',
		'expired'   => 'Expired',
		'free'      => 'Free',
	);

	const PAYMENT_STATUSES = array(
		'pending'   => 'Pending',
		'complete'  => 'Complete',
		'failed'    => 'Failed',
		'refunded'  => 'Refunded',
		'abandoned' => 'Abandoned',
	);

	public static function is_available() {
		return defined( 'RCP_PLUGIN_VERSION' ) || function_exists( 'rcp_get_status_label' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'restrictcontent' ),
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
				'name'        => 'rcp_get_status',
				'description' => 'Read Restrict Content (Kadence Memberships) health: the number of membership levels, membership counts with a breakdown by status (active, inactive, pending, cancelled, expired), the customer count, payment counts with a breakdown by status (pending, complete, failed, refunded, abandoned), and the store currency. Returns aggregate counts only, never a membership, customer, or payment record, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'rcp_get_levels',
				'description' => 'Read the Restrict Content membership-level catalogue: for each level its id, name, price, duration terms (duration + unit), trial terms, the WordPress role it grants, and whether it is enabled. Returns level definitions (access/pricing configuration) only — never a membership, customer, or payment, and never a level\'s description text. Read-only diagnostic; cannot create, edit, or delete a level.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use membership tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Restrict Content is not active.' );
		}

		if ( 'rcp_get_levels' === $name ) {
			return self::get_levels();
		}
		if ( 'rcp_get_status' !== $name ) {
			throw new \Exception( 'Unknown Restrict Content tool: ' . esc_html( $name ) );
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
		if ( function_exists( 'rcp_get_currency' ) ) {
			$code = rcp_get_currency();
			if ( is_string( $code ) && '' !== $code ) {
				return $code;
			}
		}
		return null;
	}

	private static function get_status() {
		$levels      = self::table_total( 'restrict_content_pro' );
		$memberships = self::status_counts( 'rcp_memberships', self::MEMBERSHIP_STATUSES );
		$customers   = self::table_total( 'rcp_customers' );
		$payments    = self::status_counts( 'rcp_payments', self::PAYMENT_STATUSES );

		if ( null === $levels && null === $memberships && null === $customers && null === $payments ) {
			return array(
				'provider'  => 'restrictcontent',
				'available' => false,
				'message'   => 'Restrict Content tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'restrictcontent',
			'available' => true,
		);

		if ( null !== $levels ) {
			$result['levels'] = array( 'total' => $levels );
		}
		if ( null !== $memberships ) {
			$result['memberships'] = $memberships;
		}
		if ( null !== $customers ) {
			$result['customers'] = array( 'total' => $customers );
		}
		if ( null !== $payments ) {
			$result['payments'] = $payments;
		}

		$currency = self::currency();
		if ( null !== $currency ) {
			$result['currency'] = $currency;
		}

		return $result;
	}

	private static function get_levels() {
		global $wpdb;
		$table = self::table( 'restrict_content_pro' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'restrictcontent',
				'available' => false,
				'message'   => 'The Restrict Content levels table was not found.',
			);
		}

		
		
		$rows = $wpdb->get_results(
			"SELECT id, name, price, duration, duration_unit, trial_duration, trial_duration_unit, role, status, list_order FROM {$table} ORDER BY list_order, id",
			ARRAY_A
		);

		$levels = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				
				$enabled = isset( $row['status'] ) && 'active' === (string) $row['status'];
				$levels[] = array(
					'id'                  => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'                => isset( $row['name'] ) ? (string) $row['name'] : '',
					'price'               => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
					'duration'            => isset( $row['duration'] ) ? (int) $row['duration'] : 0,
					'duration_unit'       => isset( $row['duration_unit'] ) ? (string) $row['duration_unit'] : '',
					'trial_duration'      => isset( $row['trial_duration'] ) ? (int) $row['trial_duration'] : 0,
					'trial_duration_unit' => isset( $row['trial_duration_unit'] ) ? (string) $row['trial_duration_unit'] : '',
					'role'                => isset( $row['role'] ) ? (string) $row['role'] : '',
					'enabled'             => $enabled,
				);
			}
		}

		return array(
			'provider'  => 'restrictcontent',
			'available' => true,
			'levels'    => $levels,
			'count'     => count( $levels ),
		);
	}
}
