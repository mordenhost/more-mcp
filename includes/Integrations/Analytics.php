<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	private const PROVIDERS = array( 'sitekit', 'jetpack', 'monsterinsights' );

	public static function is_available() {
		return self::provider_available( 'sitekit' ) || self::provider_available( 'jetpack' ) || self::provider_available( 'monsterinsights' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'analytics_get_status',
				'description' => 'Read analytics provider status and safe configuration identifiers for Site Kit by Google, Jetpack Stats, and MonsterInsights. Credentials and tokens are never returned.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => array( 'all', 'sitekit', 'jetpack', 'monsterinsights' ), 'description' => 'Provider to inspect. Defaults to all active providers.' ),
					),
				),
			),
			array(
				'name'        => 'analytics_get_summary',
				'description' => 'Read a normalized traffic summary through an installed analytics plugin. Uses the plugin-mediated report path; if a provider cannot safely expose reports, returns report_unavailable instead of guessing private storage or calling a vendor API directly.',
				'inputSchema' => self::report_schema(),
			),
			array(
				'name'        => 'analytics_get_top_content',
				'description' => 'Read top content by views through an installed analytics plugin. Results are normalized and provider report failures are returned explicitly.',
				'inputSchema' => self::report_schema( true ),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use analytics tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'No supported analytics plugin is active.' );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'all';
		if ( 'all' !== $provider && ! in_array( $provider, self::PROVIDERS, true ) ) {
			throw new \Exception( 'Unknown analytics provider: ' . esc_html( $provider ) );
		}
		if ( 'analytics_get_status' === $name ) {
			return self::status( self::requested_providers( $provider ) );
		}
		if ( ! in_array( $name, array( 'analytics_get_summary', 'analytics_get_top_content' ), true ) ) {
			throw new \Exception( 'Unknown analytics tool: ' . esc_html( $name ) );
		}

		$range   = self::date_range( $args );
		$limit   = isset( $args['limit'] ) ? max( 1, min( 100, absint( $args['limit'] ) ) ) : 10;
		$results = array();
		foreach ( self::requested_providers( $provider ) as $requested ) {
			if ( ! self::provider_available( $requested ) ) {
				continue;
			}
			$results[ $requested ] = 'analytics_get_summary' === $name
				? self::summary( $requested, $range )
				: self::top_content( $requested, $range, $limit );
		}
		return array( 'results' => $results );
	}

	private static function report_schema( $with_limit = false ) {
		$properties = array(
			'provider'   => array( 'type' => 'string', 'enum' => array( 'all', 'sitekit', 'jetpack', 'monsterinsights' ) ),
			'start_date' => array( 'type' => 'string', 'description' => 'Start date in YYYY-MM-DD format. Defaults to 30 days ago.' ),
			'end_date'   => array( 'type' => 'string', 'description' => 'End date in YYYY-MM-DD format. Defaults to yesterday.' ),
		);
		if ( $with_limit ) {
			$properties['limit'] = array( 'type' => 'integer', 'description' => 'Maximum rows per provider, from 1 to 100. Defaults to 10.' );
		}
		return array( 'type' => 'object', 'properties' => $properties );
	}

	private static function requested_providers( $provider ) {
		return 'all' === $provider ? self::PROVIDERS : array( $provider );
	}

	private static function provider_available( $provider ) {
		if ( 'sitekit' === $provider ) {
			return class_exists( '\Google\Site_Kit\Plugin' ) || defined( 'GOOGLESITEKIT_VERSION' );
		}
		if ( 'jetpack' === $provider ) {
			return class_exists( '\Automattic\Jetpack\Stats\WPCOM_Stats' ) && class_exists( '\Jetpack_Options' );
		}
		if ( 'monsterinsights' === $provider ) {
			return class_exists( 'MonsterInsights_Lite' ) || defined( 'MONSTERINSIGHTS_VERSION' );
		}
		return false;
	}

	private static function status( $providers ) {
		$results = array();
		foreach ( $providers as $provider ) {
			if ( ! self::provider_available( $provider ) ) {
				continue;
			}
			$results[ $provider ] = 'sitekit' === $provider ? self::sitekit_status() : ( 'jetpack' === $provider ? self::jetpack_status() : self::monsterinsights_status() );
		}
		return array( 'providers' => $results );
	}

	private static function sitekit_status() {
		$active   = get_option( 'googlesitekit_active_modules', get_option( 'googlesitekit-active-modules', array() ) );
		$settings = get_option( 'googlesitekit_analytics-4_settings', array() );
		$safe     = array();
		foreach ( array( 'propertyID', 'webDataStreamID', 'measurementID', 'googleTagID', 'trackingDisabled', 'useSnippet' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$safe[ $key ] = $settings[ $key ];
			}
		}
		return array(
			'provider'           => 'sitekit',
			'connected'          => is_array( $settings ) && ! empty( $settings['propertyID'] ),
			'active_modules'     => is_array( $active ) ? array_values( array_map( 'sanitize_key', $active ) ) : array(),
			'safe_settings'      => $safe,
			'report_capable'     => false,
			'report_unavailable' => 'Site Kit report data requires its authenticated REST flow; no stable public PHP report accessor is used by More MCP.',
		);
	}

	private static function jetpack_status() {
		$site_id = class_exists( '\Jetpack_Options' ) && method_exists( '\Jetpack_Options', 'get_option' ) ? \Jetpack_Options::get_option( 'id' ) : 0;
		return array(
			'provider'       => 'jetpack',
			'connected'      => (bool) $site_id,
			'site_id'        => $site_id ? (int) $site_id : null,
			'report_capable' => class_exists( '\Automattic\Jetpack\Stats\WPCOM_Stats' ),
		);
	}

	private static function monsterinsights_status() {
		$measurement_id = function_exists( 'monsterinsights_get_v4_id' ) ? monsterinsights_get_v4_id() : '';
		return array(
			'provider'       => 'monsterinsights',
			'connected'      => (bool) $measurement_id,
			'version'        => defined( 'MONSTERINSIGHTS_VERSION' ) ? (string) MONSTERINSIGHTS_VERSION : null,
			'measurement_id' => $measurement_id ? (string) $measurement_id : null,
			'report_capable' => class_exists( 'MonsterInsights_Reporting' ) && function_exists( 'MonsterInsights' ),
		);
	}

	private static function summary( $provider, $range ) {
		if ( 'jetpack' === $provider ) {
			return self::jetpack_report( 'get_stats_summary', $range );
		}
		if ( 'monsterinsights' === $provider ) {
			return self::monsterinsights_report( $range );
		}
		return self::unavailable( $provider, 'Traffic summary is not exposed through a stable plugin-mediated PHP accessor.' );
	}

	private static function top_content( $provider, $range, $limit ) {
		if ( 'jetpack' === $provider ) {
			$range['limit'] = $limit;
			return self::jetpack_report( 'get_top_posts', $range );
		}
		if ( 'monsterinsights' === $provider ) {
			$result = self::monsterinsights_report( $range );
			if ( 'ok' === ( $result['status'] ?? '' ) && isset( $result['data']['toppages'] ) ) {
				$result['data']['toppages'] = array_slice( (array) $result['data']['toppages'], 0, $limit );
			}
			return $result;
		}
		return self::unavailable( $provider, 'Top content is not exposed through a stable plugin-mediated PHP accessor.' );
	}

	private static function jetpack_report( $method, $args ) {
		$class = '\\Automattic\\Jetpack\\Stats\\WPCOM_Stats';
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return self::unavailable( 'jetpack', 'Jetpack Stats report method is unavailable in this version.' );
		}
		$report = new $class();
		$result = $report->$method( $args );
		if ( is_wp_error( $result ) ) {
			return self::error( 'jetpack', $result );
		}
		return array( 'status' => 'ok', 'provider' => 'jetpack', 'source' => 'jetpack_stats', 'date_range' => $args, 'data' => is_array( $result ) ? $result : array() );
	}

	private static function monsterinsights_report( $range ) {
		if ( ! function_exists( 'MonsterInsights' ) || ! class_exists( 'MonsterInsights_Reporting' ) ) {
			return self::unavailable( 'monsterinsights', 'MonsterInsights reporting service is unavailable in this version.' );
		}
		$plugin = MonsterInsights();
		if ( ! is_object( $plugin ) || ! isset( $plugin->reporting ) || ! is_object( $plugin->reporting ) || ! method_exists( $plugin->reporting, 'get_report' ) ) {
			return self::unavailable( 'monsterinsights', 'MonsterInsights reporting service is unavailable in this version.' );
		}
		$report = $plugin->reporting->get_report( 'overview' );
		if ( ! is_object( $report ) || ! method_exists( $report, 'get_data' ) ) {
			return self::unavailable( 'monsterinsights', 'MonsterInsights overview report is unavailable in this version.' );
		}
		$result = $report->get_data( array( 'start' => $range['start_date'], 'end' => $range['end_date'] ) );
		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			return self::unavailable( 'monsterinsights', isset( $result['error'] ) ? sanitize_text_field( $result['error'] ) : 'MonsterInsights returned no report data.' );
		}
		return array( 'status' => 'ok', 'provider' => 'monsterinsights', 'source' => 'monsterinsights_overview', 'date_range' => $range, 'data' => isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array() );
	}

	private static function date_range( $args ) {
		$start = isset( $args['start_date'] ) ? sanitize_text_field( $args['start_date'] ) : wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		$end   = isset( $args['end_date'] ) ? sanitize_text_field( $args['end_date'] ) : wp_date( 'Y-m-d', strtotime( '-1 day' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) || $start > $end ) {
			throw new \Exception( 'start_date and end_date must be valid YYYY-MM-DD dates with start_date no later than end_date' );
		}
		if ( ( strtotime( $end ) - strtotime( $start ) ) > 366 * DAY_IN_SECONDS ) {
			throw new \Exception( 'Analytics date range cannot exceed 366 days' );
		}
		return array( 'start_date' => $start, 'end_date' => $end );
	}

	private static function unavailable( $provider, $message ) {
		return array( 'status' => 'report_unavailable', 'provider' => $provider, 'message' => $message );
	}

	private static function error( $provider, $error ) {
		return array( 'status' => 'error', 'provider' => $provider, 'error' => sanitize_text_field( $error->get_error_message() ) );
	}
}
