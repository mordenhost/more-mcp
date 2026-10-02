<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GiveWP {

	public static function is_available() {
		return defined( 'GIVE_VERSION' ) || function_exists( 'give_get_payment_statuses' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'givewp' ),
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
				'name'        => 'givewp_get_status',
				'description' => 'Read GiveWP fundraising health: the number of donation forms (total and by status), the number of donations with a breakdown by status (pending, complete, refunded, failed, cancelled, abandoned, processing, and similar), the number of donors, and the site-wide total amount raised with its currency. Returns aggregate counts and a single total figure only — never donation records, donor identities, per-donation amounts, or payment details, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'givewp_get_campaigns',
				'description' => 'Read GiveWP campaign health: campaign counts by status and by goal type, and optionally a bounded list of campaigns carrying only id, name, status, goal type, goal amount, and start/end dates. Returns counts and campaign definitions only — never a campaign\'s marketing description, image, or URL, and never donor or donation data. Read-only diagnostic; cannot create, edit, or delete a campaign.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include a list of campaigns (id/name/status/goal_type/goal/dates only, no description or media). Default false (counts only).',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'Maximum campaigns to list when include_list is true (1-50). Default 20.',
						),
					),
				),
			),
			array(
				'name'        => 'givewp_get_forms',
				'description' => 'List the GiveWP donation-form catalogue through the plugin\'s own Give_Donate_Form model: each form\'s id, title, status, price mode (set / multi-level / custom-amount), its single price or price-level range with the level count, its goal amount and goal format, and its lifetime donation count and earnings total (the aggregate figures GiveWP keeps per form). The form title is an operator-facing label. Never donor identities, never per-donation records, never a form\'s marketing content. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum forms to return (1-100, default 50).',
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
			throw new \Exception( 'GiveWP is not active.' );
		}

		if ( 'givewp_get_campaigns' === $name ) {
			return self::get_campaigns( $args );
		}
		if ( 'givewp_get_forms' === $name ) {
			return self::get_forms( $args );
		}
		if ( 'givewp_get_status' !== $name ) {
			throw new \Exception( 'Unknown GiveWP tool: ' . esc_html( $name ) );
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

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function total_raised() {
		return (float) get_option( 'give_earnings_total', 0 );
	}

	private static function donor_total() {
		if ( function_exists( 'give_count_total_donors' ) ) {
			return (int) give_count_total_donors();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'give_donors';
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function get_status() {
		$labels = function_exists( 'give_get_payment_statuses' ) ? give_get_payment_statuses() : null;

		$forms     = self::post_counts( 'give_forms' );
		$donations = self::post_counts( 'give_payment', $labels );
		$donors    = self::donor_total();

		$result = array(
			'provider'  => 'givewp',
			'available' => true,
			'forms'     => array(
				'total'     => $forms['total'],
				'by_status' => $forms['by'],
			),
			'donations' => array(
				'total'     => $donations['total'],
				'by_status' => $donations['by'],
			),
			'total_raised' => self::total_raised(),
		);

		if ( function_exists( 'give_get_currency' ) ) {
			$result['currency'] = (string) give_get_currency();
		}
		if ( null !== $donors ) {
			$result['donors'] = array( 'total' => $donors );
		}

		return $result;
	}

	private static function get_campaigns( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'give_campaigns';

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'givewp',
				'available' => false,
				'message'   => 'The GiveWP campaigns table was not found; campaigns are available only on GiveWP 4.0+.',
			);
		}

		
		
		$by_status = $wpdb->get_results( "SELECT status AS k, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		
		$by_goal_type = $wpdb->get_results( "SELECT goal_type AS k, COUNT(*) AS n FROM {$table} GROUP BY goal_type", ARRAY_A );

		$status_map = self::tally( $by_status );
		$goal_map   = self::tally( $by_goal_type );

		$result = array(
			'provider'     => 'givewp',
			'available'    => true,
			'campaigns'    => array(
				'total'        => array_sum( $status_map ),
				'by_status'    => $status_map,
				'by_goal_type' => $goal_map,
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

		
		
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, campaign_title, status, goal_type, campaign_goal, start_date, end_date FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$list = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$list[] = array(
					'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'       => isset( $row['campaign_title'] ) ? (string) $row['campaign_title'] : '',
					'status'     => isset( $row['status'] ) ? (string) $row['status'] : '',
					'goal_type'  => isset( $row['goal_type'] ) ? (string) $row['goal_type'] : '',
					'goal'       => isset( $row['campaign_goal'] ) ? (int) $row['campaign_goal'] : 0,
					'start_date' => isset( $row['start_date'] ) ? $row['start_date'] : null,
					'end_date'   => isset( $row['end_date'] ) ? $row['end_date'] : null,
				);
			}
		}

		$result['campaign_list'] = $list;
		return $result;
	}

	private static function tally( $rows ) {
		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$key = isset( $row['k'] ) ? sanitize_key( $row['k'] ) : '';
				if ( '' === $key ) {
					$key = 'unspecified';
				}
				$out[ $key ] = isset( $row['n'] ) ? (int) $row['n'] : 0;
			}
		}
		return $out;
	}

	private static function get_forms( $args ) {
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		$limit = max( 1, min( 100, $limit ) );

		$forms = array();
		if ( ! post_type_exists( 'give_forms' ) || ! class_exists( '\Give_Donate_Form' ) ) {
			return array(
				'provider'  => 'givewp',
				'available' => false,
				'message'   => 'The Give_Donate_Form model is not available in this GiveWP version.',
				'forms'     => array(),
			);
		}

		$posts = get_posts(
			array(
				'post_type'        => 'give_forms',
				'post_status'      => 'any',
				'numberposts'      => $limit,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		foreach ( (array) $posts as $post ) {
			$form = new \Give_Donate_Form( $post->ID );

			$row = array(
				'id'     => (int) $post->ID,
				'title'  => (string) $post->post_title,
				'status' => (string) $post->post_status,
			);

			$single = is_callable( array( $form, 'is_single_price_mode' ) ) ? (bool) $form->is_single_price_mode() : false;
			$custom = is_callable( array( $form, 'is_custom_price_mode' ) ) ? (bool) $form->is_custom_price_mode() : false;
			$row['price_mode'] = $custom ? 'custom' : ( $single ? 'set' : 'multi_level' );

			if ( method_exists( $form, 'get_goal' ) ) {
				$row['goal'] = (float) $form->get_goal();
			}
			if ( function_exists( 'give_get_form_goal_format' ) ) {
				$row['goal_format'] = (string) give_get_form_goal_format( $post->ID );
			}

			$levels = function_exists( 'give_get_variable_prices' ) ? give_get_variable_prices( $post->ID ) : null;
			if ( is_array( $levels ) && $levels ) {
				$amounts = array();
				foreach ( $levels as $level ) {
					if ( isset( $level['_give_amount'] ) && '' !== $level['_give_amount'] ) {
						$amounts[] = (float) $level['_give_amount'];
					}
				}
				if ( $amounts ) {
					$row['price_levels'] = count( $amounts );
					$row['price_min']    = min( $amounts );
					$row['price_max']    = max( $amounts );
				}
			}
			if ( $single && ! isset( $row['price_levels'] ) ) {
				$row['price'] = (float) give_get_form_price( $post->ID );
			}

			if ( method_exists( $form, 'get_sales' ) ) {
				$row['sales'] = (int) $form->get_sales();
			}
			if ( method_exists( $form, 'get_earnings' ) ) {
				$row['earnings'] = (float) $form->get_earnings();
			}

			$forms[] = $row;
		}

		return array(
			'provider' => 'givewp',
			'total'    => count( $forms ),
			'forms'    => $forms,
		);
	}
}
