<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SliceWP {

	public static function is_available() {

		return function_exists( 'slicewp' ) && function_exists( 'slicewp_get_affiliates' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'slicewp' ),
			'capabilities' => array( 'affiliate' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'slicewp_get_status',
				'description' => 'SliceWP affiliate-program health: affiliate counts total and by status (active/inactive/pending/rejected), commission counts total and by status (paid/unpaid/pending/rejected), the aggregate commission amount per status, and the total visit count. Reads through SliceWP\'s own public getter functions; status vocabularies come from the plugin\'s own published label functions. Aggregate counts and amounts only — never an affiliate record, user id, payment email, website, a commission\'s customer or order reference, or a payout. Read-only; no payout tools.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use affiliate tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'SliceWP is not active.' );
		}
		if ( 'slicewp_get_status' === $name ) {
			return self::get_status();
		}
		throw new \Exception( 'Unknown SliceWP tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
		$affiliate_statuses  = self::affiliate_statuses();
		$commission_statuses = self::commission_statuses();

		
		
		$affiliates_by_status = array();
		$affiliate_total      = 0;
		foreach ( $affiliate_statuses as $status => $label ) {
			$count                  = self::count_via( 'slicewp_get_affiliates', array( 'status' => $status ) );
			$affiliates_by_status[ $status ] = $count;
			$affiliate_total       += $count;
		}

		
		
		$commissions_by_status = array();
		$amounts_by_status     = array();
		$commission_total      = 0;
		foreach ( $commission_statuses as $status => $label ) {
			$count = self::count_via( 'slicewp_get_commissions', array( 'status' => $status ) );
			$commissions_by_status[ $status ] = $count;
			$commission_total += $count;
			$amounts_by_status[ $status ]     = self::sum_commissions( $status );
		}

		return array(
			'provider'            => 'slicewp',
			'affiliates'          => array(
				'total'      => $affiliate_total,
				'by_status'  => $affiliates_by_status,
			),
			'commissions'         => array(
				'total'          => $commission_total,
				'by_status'      => $commissions_by_status,
				'amount_by_status' => $amounts_by_status,
			),
			'visits'              => array(
				'total' => self::count_via( 'slicewp_get_visits', array() ),
			),
		);
	}

	private static function affiliate_statuses() {
		if ( function_exists( 'slicewp_get_affiliate_available_statuses' ) ) {
			$statuses = slicewp_get_affiliate_available_statuses();
			if ( is_array( $statuses ) && ! empty( $statuses ) ) {
				return $statuses;
			}
		}
		return array(
			'active'   => 'Active',
			'inactive' => 'Inactive',
			'pending'  => 'Pending',
			'rejected' => 'Rejected',
		);
	}

	private static function commission_statuses() {
		if ( function_exists( 'slicewp_get_commission_available_statuses' ) ) {
			$statuses = slicewp_get_commission_available_statuses();
			if ( is_array( $statuses ) && ! empty( $statuses ) ) {
				return $statuses;
			}
		}
		return array(
			'paid'     => 'Paid',
			'unpaid'   => 'Unpaid',
			'pending'  => 'Pending',
			'rejected' => 'Rejected',
		);
	}

	private static function count_via( $fn, array $args ) {
		try {
			$count = $fn( $args, true );
			return is_numeric( $count ) ? (int) $count : 0;
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	private static function sum_commissions( $status ) {
		if ( ! function_exists( 'slicewp_get_commissions' ) ) {
			return null;
		}
		$args = array( 'status' => $status, 'number' => 1000 );
		try {
			$commissions = slicewp_get_commissions( $args );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_array( $commissions ) ) {
			return null;
		}
		$sum = 0.0;
		$capped = count( $commissions ) >= 1000;
		foreach ( $commissions as $commission ) {
			if ( is_object( $commission ) && isset( $commission->amount ) && is_numeric( $commission->amount ) ) {
				$sum += (float) $commission->amount;
			}
		}
		return array(
			'amount' => round( $sum, 2 ),
			'capped' => $capped,
		);
	}
}
