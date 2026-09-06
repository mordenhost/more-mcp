<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaidMembershipsPro {

	public static function is_available() {
		return defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_getAllLevels' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'pmpro' ),
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
				'name'        => 'pmpro_get_status',
				'description' => 'Read Paid Memberships Pro health: the number of membership levels, member counts with a breakdown by status (active, inactive, cancelled, expired, changed, admin_changed, admin_cancelled, and similar), payment-subscription counts by status (active, cancelled, and similar), and the configured currency. Membership state and payment-subscription state are reported separately, as PMPro keeps them distinct. Returns aggregate counts only, never a member record, user id, email, or any per-member detail, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'pmpro_get_levels',
				'description' => 'Read the Paid Memberships Pro membership-level catalogue: for each level its id, name, initial payment, recurring billing amount and cycle, whether signups are allowed, and expiration terms. Returns level definitions (pricing and access configuration) only — never a level\'s marketing description/confirmation copy, and never any member or subscription data. Read-only diagnostic; cannot create, edit, or delete a level.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_hidden' => array(
							'type'        => 'boolean',
							'description' => 'When true, include levels with signups disallowed (allow_signups = 0), i.e. hidden/legacy levels. Default false (only levels open for signup).',
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
			throw new \Exception( 'Paid Memberships Pro is not active.' );
		}

		if ( 'pmpro_get_levels' === $name ) {
			return self::get_levels( $args );
		}
		if ( 'pmpro_get_status' !== $name ) {
			throw new \Exception( 'Unknown Paid Memberships Pro tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'pmpro_' . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function grouped_counts( $table, $column ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$table} GROUP BY {$column}", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$raw   = isset( $row['k'] ) ? (string) $row['k'] : '';
				$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$key   = '' === $raw ? 'unspecified' : sanitize_key( $raw );
				$by[ $key ] = $count;
				$total     += $count;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function table_total( $table ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function get_status() {
		$levels        = self::table_total( self::table( 'membership_levels' ) );
		$members       = self::grouped_counts( self::table( 'memberships_users' ), 'status' );
		$subscriptions = self::grouped_counts( self::table( 'subscriptions' ), 'status' );

		if ( null === $levels && null === $members && null === $subscriptions ) {
			return array(
				'provider'  => 'pmpro',
				'available' => false,
				'message'   => 'Paid Memberships Pro tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'pmpro',
			'available' => true,
		);

		if ( null !== $levels ) {
			$result['levels'] = array( 'total' => $levels );
		}
		if ( null !== $members ) {
			$result['members'] = array(
				'total'     => $members['total'],
				'by_status' => $members['by'],
			);
		}
		if ( null !== $subscriptions ) {
			$result['subscriptions'] = array(
				'total'     => $subscriptions['total'],
				'by_status' => $subscriptions['by'],
			);
		}

		$currency = self::currency();
		if ( null !== $currency ) {
			$result['currency'] = $currency;
		}

		return $result;
	}

	private static function currency() {
		$options = get_option( 'pmpro_options' );
		if ( is_array( $options ) && ! empty( $options['currency'] ) ) {
			return (string) $options['currency'];
		}
		return null;
	}

	private static function get_levels( $args ) {
		global $wpdb;
		$table = self::table( 'membership_levels' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'pmpro',
				'available' => false,
				'message'   => 'The Paid Memberships Pro levels table was not found.',
			);
		}

		$include_hidden = ! empty( $args['include_hidden'] );
		$where          = $include_hidden ? '' : 'WHERE allow_signups = 1';

		

		$rows = $wpdb->get_results(
			"SELECT id, name, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, allow_signups, expiration_number, expiration_period FROM {$table} {$where} ORDER BY id",
			ARRAY_A
		);

		$levels = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$levels[] = array(
					'id'                => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'              => isset( $row['name'] ) ? (string) $row['name'] : '',
					'initial_payment'   => isset( $row['initial_payment'] ) ? (float) $row['initial_payment'] : 0.0,
					'billing_amount'    => isset( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 0.0,
					'cycle_number'      => isset( $row['cycle_number'] ) ? (int) $row['cycle_number'] : 0,
					'cycle_period'      => isset( $row['cycle_period'] ) ? (string) $row['cycle_period'] : '',
					'billing_limit'     => isset( $row['billing_limit'] ) ? (int) $row['billing_limit'] : 0,
					'trial_amount'      => isset( $row['trial_amount'] ) ? (float) $row['trial_amount'] : 0.0,
					'trial_limit'       => isset( $row['trial_limit'] ) ? (int) $row['trial_limit'] : 0,
					'allow_signups'     => ! empty( $row['allow_signups'] ),
					'expiration_number' => isset( $row['expiration_number'] ) ? (int) $row['expiration_number'] : 0,
					'expiration_period' => isset( $row['expiration_period'] ) ? (string) $row['expiration_period'] : '',
				);
			}
		}

		$result = array(
			'provider'  => 'pmpro',
			'available' => true,
			'levels'    => $levels,
			'count'     => count( $levels ),
		);

		$currency = self::currency();
		if ( null !== $currency ) {
			$result['currency'] = $currency;
		}

		return $result;
	}
}
