<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LimitLoginAttemptsReloaded {

	const DIGEST_POST_TYPE     = 'llar_digest_day';
	const META_DAY_TS          = '_llar_digest_day_ts';
	const META_LOCKOUTS        = '_llar_digest_lockouts_count';
	const META_FAILED_ATTEMPTS = '_llar_digest_failed_attempts_count';
	const META_USERNAME_STATS  = '_llar_digest_username_stats';

	const DEFAULT_RETENTION_DAYS = 60;

	public static function is_available() {
		return defined( 'LLA_PLUGIN_FILE' ) && class_exists( 'LLAR\\Core\\Config' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'llar' ),
			'capabilities' => array( 'security' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'llar_get_status',
				'description' => 'Limit Login Attempts Reloaded overview: plugin version, mode (local, or cloud where lockouts live in the LLAR cloud service) and whether the local data is authoritative, number of currently locked-out IPs, lockouts since the counter was last reset, failed login attempts in the last 24 hours, the lockout policy (allowed retries, lockout and long-lockout durations, retry reset window, IP source, notification settings, GDPR mode, MFA), and allow/deny list entry counts. Never returns cloud credentials, rescue codes, or list contents. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'llar_get_lockouts',
				'description' => 'List IP addresses currently locked out by Limit Login Attempts Reloaded (local mode), newest first: the IP, when the lockout expires, seconds remaining, and the failed attempts recorded for it in the current window. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum number of lockouts to return (default 50, max 200). The total count is always returned.' ),
					),
				),
			),
			array(
				'name'        => 'llar_get_events',
				'description' => 'Read Limit Login Attempts Reloaded\'s recent activity: the lockout log, newest first (time, IP, username tried, lockout count, login gateway such as wp_login or xmlrpc, whether it was manually unlocked, whether the IP is locked now), plus the daily history for the last N days (lockouts and failed attempts per day, totals, and the most-targeted usernames). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum number of lockout-log entries to return (default 25, max 100). The total count is always returned.' ),
						'days'  => array( 'type' => 'integer', 'description' => 'Days of daily history to return, counting today (default 7, max 60: the plugin keeps 60 days).' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Limit Login Attempts Reloaded tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Limit Login Attempts Reloaded is not active.' );
		}

		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'llar_get_status':
				return self::get_status();

			case 'llar_get_lockouts':
				return self::get_lockouts( $args );

			case 'llar_get_events':
				return self::get_events( $args );

			default:
				throw new \Exception( 'Unknown Limit Login Attempts Reloaded tool: ' . esc_html( $name ) );
		}
	}

	private static function get_status() {
		$now     = time();
		$cloud   = self::is_cloud_mode();
		$notify  = array_values( array_intersect( array( 'log', 'email' ), explode( ',', (string) self::option( 'lockout_notify' ) ) ) );
		$version = self::option( 'plugin_version' );

		return array(
			'provider'                 => 'llar',
			'plugin_version'           => is_string( $version ) && '' !== $version ? $version : null,
			'mode'                     => $cloud ? 'cloud' : 'local',
			
			'cloud_app_configured'     => $cloud && ! empty( self::option( 'app_config' ) ),
			'local_data_authoritative' => ! $cloud,
			'active_lockouts'          => count( self::active_lockouts( $now ) ),
			'lockouts_since_reset'     => self::int_option( 'lockouts_total', 0 ),
			'failed_attempts_24h'      => self::failed_attempts_last_day( $now ),
			'policy'                   => array(
				'allowed_retries'               => self::int_option( 'allowed_retries' ),
				'lockout_duration_seconds'      => self::int_option( 'lockout_duration' ),
				'lockouts_before_long_lockout'  => self::int_option( 'allowed_lockouts' ),
				'long_lockout_duration_seconds' => self::int_option( 'long_duration' ),
				'retries_reset_after_seconds'   => self::int_option( 'valid_duration' ),
				
				'ip_source'                     => 'HTTP_X_FORWARDED_FOR' === self::option( 'client_type' ) ? 'proxy' : 'direct',
				'lockout_notify'                => $notify,
				'notify_email_after_lockouts'   => self::int_option( 'notify_email_after' ),
				'gdpr_mode'                     => ! empty( self::option( 'gdpr' ) ),
				'mfa_enabled'                   => ! empty( self::option( 'mfa_enabled' ) ),
			),
			
			'lists'                    => array(
				'allowlist_ips'       => self::count_list( 'whitelist' ),
				'allowlist_usernames' => self::count_list( 'whitelist_usernames' ),
				'denylist_ips'        => self::count_list( 'blacklist' ),
				'denylist_usernames'  => self::count_list( 'blacklist_usernames' ),
			),
		);
	}

	private static function get_lockouts( $args ) {
		$now     = time();
		$limit   = self::limit( $args, 'limit', 50, 200 );
		$active  = self::active_lockouts( $now );
		$retries = self::option( 'retries' );
		$retries = is_array( $retries ) ? $retries : array();

		$out = array();
		foreach ( $active as $ip => $expires ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$out[] = array(
				'ip'                => (string) $ip,
				'expires_at'        => gmdate( 'c', $expires ),
				'seconds_remaining' => $expires - $now,
				'retries'           => isset( $retries[ $ip ] ) ? (int) $retries[ $ip ] : null,
			);
		}

		return array(
			'mode'                     => self::is_cloud_mode() ? 'cloud' : 'local',
			'local_data_authoritative' => ! self::is_cloud_mode(),
			'total'                    => count( $active ),
			'limit'                    => $limit,
			'lockouts'                 => $out,
		);
	}

	private static function get_events( $args ) {
		$now    = time();
		$limit  = self::limit( $args, 'limit', 25, 100 );
		$days   = self::limit( $args, 'days', 7, self::retention_days() );
		$locked = self::active_lockouts( $now );

		$log     = self::option( 'logged' );
		$entries = array();
		foreach ( is_array( $log ) ? $log : array() as $ip => $users ) {
			if ( ! is_array( $users ) ) {
				continue;
			}
			foreach ( $users as $username => $info ) {
				if ( is_array( $info ) ) {

					if ( empty( $info['date'] ) || empty( $info['counter'] ) ) {
						continue;
					}
					$date     = (int) $info['date'];
					$counter  = (int) $info['counter'];
					$gateway  = isset( $info['gateway'] ) ? sanitize_key( (string) $info['gateway'] ) : '';
					$unlocked = ! empty( $info['unlocked'] );
				} else {
					
					$date     = 0;
					$counter  = (int) $info;
					$gateway  = '';
					$unlocked = false;
				}
				$entries[] = array(
					'date'             => $date > 0 ? gmdate( 'c', $date ) : null,
					'ip'               => (string) $ip,
					'username'         => (string) $username,
					'lockouts'         => $counter,
					'gateway'          => '' !== $gateway ? $gateway : null,
					'unlocked'         => $unlocked,
					'currently_locked' => isset( $locked[ $ip ] ),
					'_ts'              => $date,
				);
			}
		}

		usort(
			$entries,
			function ( $a, $b ) {
				return $b['_ts'] <=> $a['_ts'];
			}
		);
		$total   = count( $entries );
		$entries = array_map(
			function ( $entry ) {
				unset( $entry['_ts'] );
				return $entry;
			},
			array_slice( $entries, 0, $limit )
		);

		return array(
			'provider'                 => 'llar',
			'mode'                     => self::is_cloud_mode() ? 'cloud' : 'local',
			'local_data_authoritative' => ! self::is_cloud_mode(),
			'lockout_log'              => array(
				'total'   => $total,
				'count'   => count( $entries ),
				'entries' => $entries,
			),
			'daily'                    => self::daily_history( $days ),
		);
	}

	private static function daily_history( $days ) {
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_meta' ) ) {
			return array( 'available' => false, 'days' => $days, 'by_day' => array() );
		}

		$now_local = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
		$today     = gmmktime( 0, 0, 0, (int) gmdate( 'n', $now_local ), (int) gmdate( 'j', $now_local ), (int) gmdate( 'Y', $now_local ) );
		$start     = $today - ( $days - 1 ) * 86400;

		$post_ids = get_posts(
			array(
				'post_type'        => self::DIGEST_POST_TYPE,
				'post_status'      => 'private',
				'fields'           => 'ids',

				'posts_per_page'   => 2 * $days,
				'no_found_rows'    => true,
				
				'suppress_filters' => false,
				'orderby'          => 'meta_value_num',
				'order'            => 'DESC',
				'meta_key'         => self::META_DAY_TS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the plugin's own index key, bounded to $days rows.
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- same query the plugin's digest runs.
					array(
						'key'     => self::META_DAY_TS,
						'value'   => $start,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$post_ids = is_array( $post_ids ) ? $post_ids : array();
		if ( $post_ids && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $post_ids );
		}

		$by_day    = array();
		$usernames = array();
		foreach ( $post_ids as $post_id ) {
			$day_ts = (int) get_post_meta( $post_id, self::META_DAY_TS, true );
			if ( $day_ts < $start ) {
				continue;
			}
			$date = gmdate( 'Y-m-d', $day_ts );
			if ( ! isset( $by_day[ $date ] ) ) {
				$by_day[ $date ] = array( 'date' => $date, 'lockouts' => 0, 'failed_attempts' => 0 );
			}
			$by_day[ $date ]['lockouts']        += (int) get_post_meta( $post_id, self::META_LOCKOUTS, true );
			$by_day[ $date ]['failed_attempts'] += (int) get_post_meta( $post_id, self::META_FAILED_ATTEMPTS, true );

			$stats = get_post_meta( $post_id, self::META_USERNAME_STATS, true );
			foreach ( is_array( $stats ) ? $stats : array() as $username => $count ) {
				$username = (string) $username;
				if ( '' !== $username ) {
					$usernames[ $username ] = ( $usernames[ $username ] ?? 0 ) + (int) $count;
				}
			}
		}
		krsort( $by_day );
		arsort( $usernames );

		$top = array();
		foreach ( array_slice( $usernames, 0, 10, true ) as $username => $count ) {
			$top[] = array( 'username' => (string) $username, 'failed_attempts' => (int) $count );
		}

		return array(
			'available'             => true,
			'days'                  => $days,
			'since'                 => gmdate( 'Y-m-d', $start ),
			'lockouts_total'        => array_sum( array_column( $by_day, 'lockouts' ) ),
			'failed_attempts_total' => array_sum( array_column( $by_day, 'failed_attempts' ) ),
			'top_usernames'         => $top,
			'by_day'                => array_values( $by_day ),
		);
	}

	

	
	private static function option( $key ) {
		if ( method_exists( 'LLAR\\Core\\Config', 'get' ) ) {
			return \LLAR\Core\Config::get( $key );
		}
		return get_option( 'limit_login_' . $key, null );
	}

	private static function int_option( $key, $default = null ) {
		$value = self::option( $key );
		return is_numeric( $value ) ? (int) $value : $default;
	}

	private static function is_cloud_mode() {
		return 'custom' === self::option( 'active_app' );
	}

	private static function active_lockouts( $now ) {
		$lockouts = self::option( 'lockouts' );
		$active   = array();
		foreach ( is_array( $lockouts ) ? $lockouts : array() as $ip => $expires ) {
			if ( is_numeric( $expires ) && (int) $expires > $now ) {
				$active[ (string) $ip ] = (int) $expires;
			}
		}
		arsort( $active );
		return $active;
	}

	private static function failed_attempts_last_day( $now ) {
		$stats = self::option( 'retries_stats' );
		if ( ! is_array( $stats ) ) {
			return 0;
		}
		$cutoff = $now - 86400;
		$today  = function_exists( 'date_i18n' ) ? date_i18n( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$count  = 0;
		foreach ( $stats as $key => $n ) {
			if ( is_numeric( $key ) && (int) $key > $cutoff ) {
				$count += (int) $n;
			} elseif ( ! is_numeric( $key ) && $today === $key ) {
				$count += (int) $n;
			}
		}
		return $count;
	}

	private static function count_list( $key ) {
		$list = self::option( $key );
		return is_array( $list ) ? count( array_filter( array_map( 'trim', array_map( 'strval', $list ) ), 'strlen' ) ) : 0;
	}

	private static function retention_days() {
		return defined( 'LLA_LOCKOUT_HISTORY_RETENTION_DAYS' ) ? max( 1, (int) constant( 'LLA_LOCKOUT_HISTORY_RETENTION_DAYS' ) ) : self::DEFAULT_RETENTION_DAYS;
	}

	private static function limit( $args, $key, $default, $max ) {
		return isset( $args[ $key ] ) ? max( 1, min( $max, (int) $args[ $key ] ) ) : min( $default, $max );
	}
}
