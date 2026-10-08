<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Charitable {

	public static function is_available() {

		return class_exists( 'Charitable' ) && function_exists( 'charitable_get_valid_donation_statuses' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'charitable' ),
			'capabilities' => array( 'donations' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'charitable_get_status',
				'description' => 'Read Charitable fundraising health: the number of campaigns (total and by status), the number of donations with a breakdown by status label (Paid/Pending/Failed/Cancelled/Refunded), the number of donors, and the site-wide total amount raised with its currency. Returns aggregate counts and a single total figure only — never donation records, donor identities, per-donation amounts, or payment details, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'charitable_get_campaigns',
				'description' => 'Read Charitable campaign health: campaign counts by status, and optionally a bounded list of campaigns carrying id, title, status, publish date, plus each campaign\'s configuration and progress read through the plugin\'s own Charitable_Campaign model: goal amount, end date, suggested donation amounts, the amount raised, and the distinct donor count (both read from Charitable\'s own aggregate helpers over its campaign-donations table, never from donation rows). The campaign body content is never read; no donor identity or per-donation record is returned. Read-only diagnostic; cannot create, edit, or delete a campaign.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include the campaign list with goal/progress fields. Default false (counts only).',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'Maximum campaigns to list when include_list is true (1-50). Default 20.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use donation tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Charitable is not active.' );
		}

		if ( 'charitable_get_campaigns' === $name ) {
			return self::get_campaigns( $args );
		}
		if ( 'charitable_get_status' !== $name ) {
			throw new \Exception( 'Unknown Charitable tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function post_counts( $post_type, $labels = null ) {
		$counts = wp_count_posts( $post_type );
		$by     = array();
		$total  = 0;
		if ( is_object( $counts ) ) {
			foreach ( get_object_vars( $counts ) as $status => $n ) {
				$n = (int) $n;
				if ( 0 === $n ) {
					continue;
				}
				if ( null !== $labels && isset( $labels[ $status ] ) ) {
					$key = sanitize_key( $labels[ $status ] );
				} else {
					$key = sanitize_key( $status );
				}
				$by[ $key ] = isset( $by[ $key ] ) ? $by[ $key ] + $n : $n;
				$total     += $n;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function db_table( $table ) {
		if ( ! function_exists( 'charitable_get_table' ) ) {
			return null;
		}
		$obj = charitable_get_table( $table );
		return is_object( $obj ) ? $obj : null;
	}

	private static function total_raised() {
		$cd = self::db_table( 'campaign_donations' );
		if ( null === $cd || ! method_exists( $cd, 'get_total' ) ) {
			return null;
		}
		return (float) $cd->get_total();
	}

	private static function donor_total() {
		$donors = self::db_table( 'donors' );
		if ( null === $donors || ! method_exists( $donors, 'count_donors_with_donations' ) ) {
			return null;
		}
		return (int) $donors->count_donors_with_donations();
	}

	private static function get_status() {
		$labels = charitable_get_valid_donation_statuses();

		$campaigns = self::post_counts( 'campaign' );
		$donations = self::post_counts( 'donation', $labels );
		$donors    = self::donor_total();
		$raised    = self::total_raised();

		$result = array(
			'provider'  => 'charitable',
			'available' => true,
			'campaigns' => array(
				'total'     => $campaigns['total'],
				'by_status' => $campaigns['by'],
			),
			'donations' => array(
				'total'     => $donations['total'],
				'by_status' => $donations['by'],
			),
		);

		if ( null !== $raised ) {
			$result['total_raised'] = $raised;
		}
		if ( function_exists( 'charitable_get_currency' ) ) {
			$result['currency'] = (string) charitable_get_currency();
		}
		if ( null !== $donors ) {
			$result['donors'] = array( 'total' => $donors );
		}

		return $result;
	}

	private static function get_campaigns( $args ) {
		$counts = self::post_counts( 'campaign' );

		$result = array(
			'provider'  => 'charitable',
			'available' => true,
			'campaigns' => array(
				'total'     => $counts['total'],
				'by_status' => $counts['by'],
			),
		);

		if ( empty( $args['include_list'] ) ) {
			return $result;
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 50 ) {
			$limit = 50;
		}

		
		$posts = get_posts(
			array(
				'post_type'        => 'campaign',
				'post_status'      => 'any',
				'numberposts'      => $limit,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$list = array();
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$row = array(
					'id'     => (int) $post->ID,
					'title'  => (string) $post->post_title,
					'status' => (string) $post->post_status,
					'date'   => (string) $post->post_date,
				);
				$row = array_merge( $row, self::campaign_detail( $post->ID ) );
				$list[] = $row;
			}
		}

		$result['campaign_list'] = $list;
		return $result;
	}

	private static function campaign_detail( $campaign_id ) {
		$detail = array();

		if ( ! class_exists( '\Charitable_Campaign' ) ) {
			return $detail;
		}

		$campaign = new \Charitable_Campaign( $campaign_id );

		if ( ! is_callable( array( $campaign, 'get_goal' ) ) ) {
			return $detail;
		}

		$goal = $campaign->get_goal();
		$detail['goal'] = is_wp_error( $goal ) ? null : (float) $goal;

		if ( is_callable( array( $campaign, 'get_end_date' ) ) ) {
			$detail['end_date'] = (string) $campaign->get_end_date( 'Y-m-d' );
		}
		if ( is_callable( array( $campaign, 'get_suggested_donations' ) ) ) {
			$suggested = $campaign->get_suggested_donations();
			$detail['suggested_amounts'] = is_array( $suggested )
				? array_map( 'floatval', array_values( $suggested ) )
				: array();
		}

		

		if ( function_exists( 'charitable_get_table' ) ) {
			$table = charitable_get_table( 'campaign_donations' );
			if ( is_object( $table ) && is_callable( array( $table, 'get_campaign_donated_amount' ) ) ) {
				$amount = $table->get_campaign_donated_amount( $campaign_id, false, false );
				$detail['donated_amount'] = is_wp_error( $amount ) ? null : (float) $amount;
			}
			if ( is_object( $table ) && is_callable( array( $table, 'count_campaign_donors' ) ) ) {
				$donors = $table->count_campaign_donors( $campaign_id );
				$detail['donors'] = is_wp_error( $donors ) ? null : (int) $donors;
			}
		}

		return $detail;
	}
}
