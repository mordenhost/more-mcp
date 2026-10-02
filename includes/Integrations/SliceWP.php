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
			array(
				'name'        => 'slicewp_get_creatives',
				'description' => 'List the SliceWP creative catalogue: each promo asset\'s name, type (image or text), image URL with its alt text, landing URL, and status (active/inactive). Read through SliceWP\'s own creative getter. Creative definitions only — never an affiliate, a commission, a payout, or the creative\'s description text. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum creatives to return (1-50, default 25).',
						),
					),
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
		if ( 'slicewp_get_creatives' === $name ) {
			return self::get_creatives( $args );
		}
		if ( 'slicewp_get_status' !== $name ) {
			throw new \Exception( 'Unknown SliceWP tool: ' . esc_html( $name ) );
		}

		return self::get_status();
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

	private static function get_creatives( $args ) {
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 25;
		$limit = max( 1, min( 50, $limit ) );

		
		
		if ( ! function_exists( 'slicewp_get_creatives' ) ) {
			return array(
				'provider'  => 'slicewp',
				'available' => false,
				'message'   => 'The SliceWP creative API is not available in this version.',
				'creatives' => array(),
			);
		}

		try {
			$objects = slicewp_get_creatives( array( 'number' => $limit ) );
		} catch ( \Throwable $e ) {
			return array(
				'provider'  => 'slicewp',
				'available' => false,
				'message'   => 'SliceWP returned an error while reading creatives.',
				'creatives' => array(),
			);
		}
		if ( ! is_array( $objects ) ) {
			$objects = array();
		}

		$statuses = function_exists( 'slicewp_get_creative_available_statuses' )
			? slicewp_get_creative_available_statuses()
			: array( 'active' => 'Active', 'inactive' => 'Inactive' );
		$types = function_exists( 'slicewp_get_creative_available_types' )
			? slicewp_get_creative_available_types()
			: array( 'image' => 'Image', 'text' => 'Text Link', 'long_text' => 'Long Text' );

		$creatives = array();
		foreach ( $objects as $object ) {
			if ( ! is_object( $object ) ) {
				continue;
			}
			$type = isset( $object->type ) ? (string) $object->type : '';
			$creatives[] = array(
				'id'          => isset( $object->id ) ? (int) $object->id : 0,
				'name'        => isset( $object->name ) ? (string) $object->name : '',
				'type'        => $type,
				'type_label'  => isset( $types[ $type ] ) ? $types[ $type ] : null,
				'image_url'   => isset( $object->image_url ) && '' !== (string) $object->image_url ? (string) $object->image_url : null,
				'alt_text'    => isset( $object->alt_text ) && '' !== (string) $object->alt_text ? (string) $object->alt_text : null,
				'landing_url' => isset( $object->landing_url ) ? (string) $object->landing_url : '',
				'status'      => isset( $object->status ) ? (string) $object->status : '',
				'status_label' => isset( $object->status, $statuses[ (string) $object->status ] )
					? (string) $statuses[ (string) $object->status ]
					: null,
			);
		}

		return array(
			'provider'  => 'slicewp',
			'available' => true,
			'count'     => count( $creatives ),
			'creatives' => $creatives,
		);
	}
}
