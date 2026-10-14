<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Akismet {

	const STATS_INTERVALS = array( '60-days', '6-months', 'all' );

	const STATS_MAX_PERIODS = 120;

	public static function is_available() {
		return class_exists( 'Akismet' ) || function_exists( 'akismet_http_post' ) || defined( 'AKISMET_VERSION' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'akismet' ),
			'capabilities' => array( 'spam' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'akismet_get_status',
				'description' => 'Read Akismet anti-spam status: whether an API key is configured (never the key itself), the lifetime count of spam caught, and how many spam comments are in the moderation queue right now. Read-only diagnostic; returns no credentials and cannot change any setting. To moderate individual comments, use the core comment tools.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'akismet_check_comment',
				'description' => 'Ask Akismet to classify an EXISTING comment (by comment ID) as spam or not spam, by re-sending it to the Akismet service. Returns is_spam plus Akismet\'s pro-tip / discard hint when present. This is a read-only classification: it does NOT change the comment\'s status, move it to spam, or delete it — use the core comment tools (wp_spam_comment / wp_trash_comment / wp_approve_comment) to act on the verdict. Only a comment already stored on this site can be checked; no ad-hoc text is sent. Requires moderate_comments and a configured Akismet key.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of an existing comment on this site to re-check against Akismet.',
						),
					),
					'required'   => array( 'comment_id' ),
				),
			),
			array(
				'name'        => 'akismet_get_key_status',
				'description' => 'Check the Akismet API key without revealing it: whether one is set and where it comes from (wp-config.php constant, the saved option, or a filter), whether the Akismet service accepts it (valid, invalid, or failed when the service is unreachable), the account status and plan (active, no-sub, missing, cancelled, suspended), any usage-limit alert Akismet has raised, and the non-secret settings (discard worst spam, approved-comment counts, privacy notice, test mode). Calls the Akismet service the way its settings screen does. The key itself is never returned, not even masked. Requires manage_options. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'akismet_get_stats',
				'description' => 'Read Akismet\'s own service statistics for this site over an interval: spam caught, legitimate comments, missed spam, false positives, accuracy percentage, estimated time saved, and the per-period breakdown, plus the local lifetime and queue counts. Needs a configured key. Requires manage_options. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'interval' => array(
							'type'        => 'string',
							'enum'        => self::STATS_INTERVALS,
							'description' => 'Time window: 60-days, 6-months (default), or all.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		

		if ( 'akismet_check_comment' === $name ) {
			if ( ! current_user_can( 'moderate_comments' ) ) {
				throw new \Exception( 'You do not have permission to moderate comments.' );
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use spam tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Akismet is not active.' );
		}

		if ( 'akismet_check_comment' === $name ) {
			return self::check_comment( $args );
		}
		if ( 'akismet_get_key_status' === $name ) {
			return self::get_key_status();
		}
		if ( 'akismet_get_stats' === $name ) {
			return self::get_stats( is_array( $args ) ? $args : array() );
		}
		if ( 'akismet_get_status' !== $name ) {
			throw new \Exception( 'Unknown spam tool: ' . esc_html( $name ) );
		}

		
		$configured = false;
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_api_key' ) ) {
			$key        = \Akismet::get_api_key();
			$configured = ! empty( $key );
		}
		if ( ! $configured && defined( 'WPCOM_API_KEY' ) && '' !== (string) constant( 'WPCOM_API_KEY' ) ) {
			$configured = true;
		}

		$lifetime = get_option( 'akismet_spam_count', null );

		
		
		$queue = self::queue_count();

		return array(
			'provider'         => 'akismet',
			'configured'       => $configured,
			'version'          => defined( 'AKISMET_VERSION' ) ? (string) AKISMET_VERSION : null,
			'lifetime_spam'    => null === $lifetime ? null : (int) $lifetime,
			'spam_in_queue'    => $queue,
		);
	}

	private static function api_key() {
		$key = '';
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_api_key' ) ) {
			$key = (string) \Akismet::get_api_key();
		} elseif ( defined( 'WPCOM_API_KEY' ) ) {
			$key = (string) constant( 'WPCOM_API_KEY' );
		} else {
			$key = (string) get_option( 'wordpress_api_key', '' );
		}
		if ( '' === $key ) {
			return array( '', 'none' );
		}

		if ( defined( 'WPCOM_API_KEY' ) && (string) constant( 'WPCOM_API_KEY' ) === $key ) {
			$source = 'constant';
		} elseif ( (string) get_option( 'wordpress_api_key', '' ) === $key ) {
			$source = 'option';
		} else {
			$source = 'filter';
		}
		return array( $key, $source );
	}

	private static function get_key_status() {
		list( $key, $source ) = self::api_key();

		$verification = 'missing';
		$account      = null;
		if ( '' !== $key ) {
			$verification = 'failed';
			if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'verify_key' ) ) {
				$verdict      = (string) \Akismet::verify_key( $key );
				$verification = in_array( $verdict, array( 'valid', 'invalid' ), true ) ? $verdict : 'failed';
			}
			if ( 'valid' === $verification && class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'get_akismet_user' ) ) {
				$user = \Akismet_Admin::get_akismet_user( $key );
				if ( is_object( $user ) ) {
					$status  = isset( $user->status ) && is_string( $user->status ) ? sanitize_key( $user->status ) : '';
					$account = array(
						'status'       => '' === $status ? null : $status,
						'account_type' => isset( $user->account_type ) && is_string( $user->account_type ) ? sanitize_text_field( $user->account_type ) : null,
						'plan'         => isset( $user->account_name ) && is_string( $user->account_name ) ? sanitize_text_field( $user->account_name ) : null,
						'note'         => self::account_note( $status ),
					);
				}
			}
		}

		$alert      = null;
		$alert_code = get_option( 'akismet_alert_code', '' );
		if ( '' !== (string) $alert_code && false !== $alert_code ) {
			$alert = array(
				'code'             => (int) $alert_code,
				'message'          => sanitize_text_field( (string) get_option( 'akismet_alert_msg', '' ) ),
				'api_calls'        => is_numeric( get_option( 'akismet_alert_api_calls', '' ) ) ? (int) get_option( 'akismet_alert_api_calls' ) : null,
				'usage_limit'      => is_numeric( get_option( 'akismet_alert_usage_limit', '' ) ) ? (int) get_option( 'akismet_alert_usage_limit' ) : null,
				'recommended_plan' => sanitize_text_field( (string) get_option( 'akismet_alert_recommended_plan_name', '' ) ),
			);
		}

		$notes = array(
			'missing' => 'No API key is set, so Akismet checks nothing. Add one in Settings > Akismet Anti-spam.',
			'valid'   => 'Akismet accepts the key.',
			'invalid' => 'Akismet rejects this key. Replace it in Settings > Akismet Anti-spam.',
			'failed'  => 'Akismet could not be reached to verify the key; try again later.',
		);

		return array(
			'provider'          => 'akismet',
			'version'           => defined( 'AKISMET_VERSION' ) ? (string) constant( 'AKISMET_VERSION' ) : null,
			'key_configured'    => '' !== $key,
			'key_source'        => $source,
			'key_verification'  => $verification,
			'verification_note' => $notes[ $verification ],
			'account'           => $account,
			'usage_alert'       => $alert,
			'settings'          => array(
				'discard_worst_spam'          => '1' === get_option( 'akismet_strictness', '0' ),
				'show_comment_approved_count' => '1' === (string) get_option( 'akismet_show_user_comments_approved', '' ),
				'comment_form_privacy_notice' => 'display' === get_option( 'akismet_comment_form_privacy_notice', 'hide' ),
				'abilities_mcp_access'        => '1' === get_option( 'akismet_enable_mcp_access', '' ),
				'test_mode'                   => class_exists( 'Akismet' ) && method_exists( 'Akismet', 'is_test_mode' ) ? (bool) \Akismet::is_test_mode() : ( defined( 'AKISMET_TEST_MODE' ) && constant( 'AKISMET_TEST_MODE' ) ),
			),
			'settings_url'      => admin_url( 'options-general.php?page=akismet-key-config' ),
		);
	}

	private static function account_note( $status ) {
		$notes = array(
			'active'    => 'Subscription active.',
			'no-sub'    => 'The key has no subscription; Akismet is not protecting this site.',
			'missing'   => 'No Akismet account was found for this key.',
			'cancelled' => 'The subscription was cancelled.',
			'suspended' => 'The subscription is suspended.',
		);
		return $notes[ $status ] ?? null;
	}

	private static function get_stats( $args ) {
		$interval = isset( $args['interval'] ) && is_string( $args['interval'] ) ? $args['interval'] : '6-months';
		if ( ! in_array( $interval, self::STATS_INTERVALS, true ) ) {
			throw new \Exception( 'interval must be one of: ' . esc_html( implode( ', ', self::STATS_INTERVALS ) ) . '.' );
		}
		list( $key ) = self::api_key();
		if ( '' === $key ) {
			throw new \Exception( 'Akismet has no API key, so the service has no stats for this site. akismet_get_status has the local counters.' );
		}

		$data = null;
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_stats' ) ) {
			$data = \Akismet::get_stats( $interval );
		} elseif ( class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'get_stats' ) && '60-days' !== $interval ) {
			$totals = \Akismet_Admin::get_stats( $key );
			$data   = is_array( $totals ) && isset( $totals[ $interval ] ) ? $totals[ $interval ] : null;
		} else {
			throw new \Exception( 'This Akismet version has no stats call for that interval. Update Akismet to 5.7 or later.' );
		}
		if ( ! is_object( $data ) ) {
			throw new \Exception( 'Akismet returned no stats (the service could not be reached or rejected the key). akismet_get_key_status says which.' );
		}

		$int = static function ( $object, $field ) {
			return isset( $object->$field ) && is_numeric( $object->$field ) ? (int) $object->$field : null;
		};

		$breakdown = array();
		if ( isset( $data->breakdown ) && ( is_object( $data->breakdown ) || is_array( $data->breakdown ) ) ) {
			foreach ( (array) $data->breakdown as $period => $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$breakdown[] = array(
					'period'          => sanitize_text_field( (string) $period ),
					'date'            => isset( $row->da ) && is_string( $row->da ) ? sanitize_text_field( $row->da ) : null,
					'spam'            => $int( $row, 'spam' ),
					'ham'             => $int( $row, 'ham' ),
					'missed_spam'     => $int( $row, 'missed_spam' ),
					'false_positives' => $int( $row, 'false_positives' ),
				);
			}
		}
		$total_periods = count( $breakdown );
		if ( $total_periods > self::STATS_MAX_PERIODS ) {
			$breakdown = array_slice( $breakdown, -self::STATS_MAX_PERIODS );
		}

		$lifetime = get_option( 'akismet_spam_count', null );
		return array(
			'provider'          => 'akismet',
			'interval'          => $interval,
			'spam'              => $int( $data, 'spam' ),
			'ham'               => $int( $data, 'ham' ),
			'missed_spam'       => $int( $data, 'missed_spam' ),
			'false_positives'   => $int( $data, 'false_positives' ),
			'accuracy_percent'  => isset( $data->accuracy ) && is_numeric( $data->accuracy ) ? (float) $data->accuracy : null,
			'time_saved_seconds' => $int( $data, 'time_saved' ),
			'breakdown'         => $breakdown,
			'breakdown_note'    => $total_periods > self::STATS_MAX_PERIODS ? 'Only the last ' . self::STATS_MAX_PERIODS . ' of the ' . $total_periods . ' periods Akismet returned are listed.' : null,
			'local'             => array(
				'lifetime_spam' => null === $lifetime ? null : (int) $lifetime,
				'spam_in_queue' => self::queue_count(),
			),
		);
	}

	private static function queue_count() {
		if ( class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'get_spam_count' ) ) {
			return (int) \Akismet_Admin::get_spam_count();
		}
		if ( function_exists( 'wp_count_comments' ) ) {
			$counts = wp_count_comments();
			return isset( $counts->spam ) ? (int) $counts->spam : null;
		}
		return null;
	}

	private static function check_comment( $args ) {
		$comment_id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
		if ( $comment_id <= 0 ) {
			throw new \Exception( 'comment_id (a positive integer) is required.' );
		}
		if ( function_exists( 'get_comment' ) && null === get_comment( $comment_id ) ) {
			throw new \Exception( 'Comment ' . (int) $comment_id . ' was not found.' );
		}
		if ( ! class_exists( 'Akismet' ) || ! method_exists( 'Akismet', 'check_db_comment' ) ) {
			throw new \Exception( 'Akismet comment-check is unavailable on this version.' );
		}

		$result = \Akismet::check_db_comment( $comment_id, 'recheck_queue' );

		
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}

		
		
		if ( 'true' === $result || 'false' === $result ) {
			return array(
				'provider'   => 'akismet',
				'comment_id' => $comment_id,
				'checked'    => true,
				'is_spam'    => ( 'true' === $result ),
				'note'       => 'Verdict only; the comment status was not changed. Use wp_spam_comment / wp_trash_comment / wp_approve_comment to act on it.',
			);
		}

		return array(
			'provider'   => 'akismet',
			'comment_id' => $comment_id,
			'checked'    => false,
			'is_spam'    => null,
			'note'       => 'Akismet returned no verdict (service unreachable or key unset). The comment status was not changed.',
		);
	}
}
