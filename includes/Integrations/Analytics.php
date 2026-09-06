<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	private const PROVIDERS = array( 'sitekit', 'jetpack', 'monsterinsights', 'burst', 'matomo', 'iawp' );

	public static function is_available() {
		foreach ( self::PROVIDERS as $provider ) {
			if ( self::provider_available( $provider ) ) {
				return true;
			}
		}
		return false;
	}

	public static function get_manifest() {
		$providers = array();
		foreach ( self::PROVIDERS as $provider ) {
			if ( self::provider_available( $provider ) ) {
				$providers[] = $provider;
			}
		}
		return array(
			'providers'    => $providers,
			'capabilities' => array( 'analytics' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'analytics_get_status',
				'description' => 'Read analytics provider status and safe configuration identifiers for Site Kit by Google, Jetpack Stats, MonsterInsights, Burst Statistics, Matomo, and Independent Analytics. Credentials and tokens are never returned.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => array( 'all', 'sitekit', 'jetpack', 'monsterinsights', 'burst', 'matomo', 'iawp' ), 'description' => 'Provider to inspect. Defaults to all active providers.' ),
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
			'provider'   => array( 'type' => 'string', 'enum' => array( 'all', 'sitekit', 'jetpack', 'monsterinsights', 'burst', 'matomo', 'iawp' ) ),
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
		if ( 'burst' === $provider ) {

			
			return class_exists( '\Burst\Admin\Statistics\Statistics_Data' ) || defined( 'BURST_VERSION' );
		}
		if ( 'matomo' === $provider ) {

			return class_exists( '\WpMatomo' );
		}
		if ( 'iawp' === $provider ) {

			
			return defined( 'IAWP_VERSION' ) && class_exists( '\IAWP\Public_API\Analytics' );
		}
		return false;
	}

	private static function status( $providers ) {
		$results = array();
		foreach ( $providers as $provider ) {
			if ( ! self::provider_available( $provider ) ) {
				continue;
			}
			if ( 'sitekit' === $provider ) {
				$results[ $provider ] = self::sitekit_status();
			} elseif ( 'jetpack' === $provider ) {
				$results[ $provider ] = self::jetpack_status();
			} elseif ( 'burst' === $provider ) {
				$results[ $provider ] = self::burst_status();
			} elseif ( 'matomo' === $provider ) {
				$results[ $provider ] = self::matomo_status();
			} elseif ( 'iawp' === $provider ) {
				$results[ $provider ] = self::iawp_status();
			} else {
				$results[ $provider ] = self::monsterinsights_status();
			}
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

	private static function burst_status() {
		$tracking = null;
		if ( class_exists( '\Burst\Frontend\Endpoint' ) && method_exists( '\Burst\Frontend\Endpoint', 'get_tracking_status' ) ) {
			$tracking = (string) \Burst\Frontend\Endpoint::get_tracking_status();
		} else {
			$stored = get_option( 'burst_tracking_status' );
			if ( is_string( $stored ) && '' !== $stored ) {
				$tracking = $stored;
			}
		}
		return array(
			'provider'        => 'burst',
			'connected'       => class_exists( '\Burst\Admin\Statistics\Statistics_Data' ),
			'version'         => defined( 'BURST_VERSION' ) ? (string) BURST_VERSION : null,
			'tracking_status' => $tracking,
			'report_capable'  => class_exists( '\Burst\Admin\Statistics\Statistics_Data' ),
			'source'          => 'local_database',
		);
	}

	private static function summary( $provider, $range ) {
		if ( 'jetpack' === $provider ) {
			return self::jetpack_report( 'get_stats_summary', $range );
		}
		if ( 'monsterinsights' === $provider ) {
			return self::monsterinsights_report( $range );
		}
		if ( 'burst' === $provider ) {
			return self::burst_summary( $range );
		}
		if ( 'matomo' === $provider ) {
			return self::matomo_summary( $range );
		}
		if ( 'iawp' === $provider ) {
			return self::iawp_summary( $range );
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
		if ( 'burst' === $provider ) {
			return self::burst_top_content( $range, $limit );
		}
		if ( 'matomo' === $provider ) {
			return self::matomo_top_content( $range, $limit );
		}
		if ( 'iawp' === $provider ) {
			return self::iawp_top_content( $range, $limit );
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

	private static function burst_summary( $range ) {
		$data = self::burst_data_instance();
		if ( null === $data ) {
			return self::unavailable( 'burst', 'Burst Statistics data layer is unavailable in this version.' );
		}
		list( $start, $end ) = self::burst_unix_range( $range );
		try {
			$totals = $data->get_data(
				array( 'pageviews', 'visitors', 'sessions', 'bounce_rate', 'avg_time_on_page' ),
				$start,
				$end,
				array()
			);
		} catch ( \Throwable $e ) {
			return self::unavailable( 'burst', 'Burst report query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}
		if ( ! is_array( $totals ) ) {
			$totals = array();
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'burst',
			'source'     => 'burst_local_database',
			'date_range' => $range,
			'data'       => array(
				'pageviews'        => isset( $totals['pageviews'] ) ? (int) $totals['pageviews'] : 0,
				'visitors'         => isset( $totals['visitors'] ) ? (int) $totals['visitors'] : 0,
				'sessions'         => isset( $totals['sessions'] ) ? (int) $totals['sessions'] : 0,
				'bounce_rate'      => isset( $totals['bounce_rate'] ) ? (float) $totals['bounce_rate'] : 0.0,
				'avg_time_on_page' => isset( $totals['avg_time_on_page'] ) ? (int) $totals['avg_time_on_page'] : 0,
			),
		);
	}

	private static function burst_top_content( $range, $limit ) {
		$data = self::burst_data_instance();
		if ( null === $data ) {
			return self::unavailable( 'burst', 'Burst Statistics data layer is unavailable in this version.' );
		}
		if ( ! method_exists( $data, 'get_datatables_data' ) ) {
			return self::unavailable( 'burst', 'Burst datatable accessor is unavailable in this version.' );
		}
		list( $start, $end ) = self::burst_unix_range( $range );
		try {
			$result = $data->get_datatables_data(
				array(
					'date_start' => $start,
					'date_end'   => $end,
					'metrics'    => array( 'pageviews' ),
					'group_by'   => array( 'page_url' ),
					'filters'    => array(),
					'limit'      => $limit,
				)
			);
		} catch ( \Throwable $e ) {
			return self::unavailable( 'burst', 'Burst report query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}
		$rows = array();
		if ( is_array( $result ) && isset( $result['data'] ) && is_array( $result['data'] ) ) {
			foreach ( array_slice( $result['data'], 0, $limit ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rows[] = array(
					'page_url'  => isset( $row['page_url'] ) ? (string) $row['page_url'] : '',
					'pageviews' => isset( $row['pageviews'] ) ? (int) $row['pageviews'] : 0,
				);
			}
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'burst',
			'source'     => 'burst_local_database',
			'date_range' => $range,
			'data'       => array( 'toppages' => $rows ),
		);
	}

	private static function burst_data_instance() {
		$class = '\\Burst\\Admin\\Statistics\\Statistics_Data';
		if ( ! class_exists( $class ) ) {
			return null;
		}
		return new $class();
	}

	private static function burst_unix_range( $range ) {
		$start = strtotime( $range['start_date'] . ' 00:00:00' );
		$end   = strtotime( $range['end_date'] . ' 23:59:59' );
		return array( (int) $start, (int) $end );
	}

	
	private static function matomo_status() {
		$track_mode = null;
		$site_id    = null;
		if ( class_exists( '\WpMatomo\Settings' ) ) {
			$settings = new \WpMatomo\Settings();
			if ( method_exists( $settings, 'get_global_option' ) ) {
				$track_mode = (string) $settings->get_global_option( 'track_mode' );
			}
		}
		if ( class_exists( '\WpMatomo\Site' ) && method_exists( '\WpMatomo\Site', 'get_current_matomo_site_id' ) ) {
			$site_id = (int) ( new \WpMatomo\Site() )->get_current_matomo_site_id();
		}
		$report_capable = class_exists( '\WpMatomo\Report\Data' ) && class_exists( '\WpMatomo\Bootstrap' );
		return array(
			'provider'       => 'matomo',
			'connected'      => $site_id > 0,
			'site_id'        => $site_id ? $site_id : null,
			'track_mode'     => $track_mode,
			'tracking'       => null !== $track_mode && 'disabled' !== $track_mode,
			'report_capable' => $report_capable,
			'source'         => 'local_database',
		);
	}

	private static function matomo_summary( $range ) {
		if ( ! class_exists( '\WpMatomo\Report\Data' ) || ! method_exists( '\WpMatomo\Report\Data', 'fetch_raw_report' ) ) {
			return self::unavailable( 'matomo', 'Matomo report layer is unavailable in this version.' );
		}
		try {
			$data = new \WpMatomo\Report\Data();
			$report = $data->fetch_raw_report(
				'VisitsSummary.get',
				'day',
				$range['start_date'] . ',' . $range['end_date'],
				'',
				1
			);
		} catch ( \Throwable $e ) {
			return self::unavailable( 'matomo', 'Matomo report query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}

		$totals = array( 'pageviews' => 0, 'visitors' => 0, 'visits' => 0, 'bounce_rate' => 0.0, 'avg_time_on_site' => 0 );
		if ( is_array( $report ) ) {
			if ( isset( $report['nb_pageviews'] ) ) {
				
				$totals['pageviews'] = (int) $report['nb_pageviews'];
				$totals['visitors']  = isset( $report['nb_uniq_visitors'] ) ? (int) $report['nb_uniq_visitors'] : 0;
				$totals['visits']    = isset( $report['nb_visits'] ) ? (int) $report['nb_visits'] : 0;
				$totals['bounce_rate'] = isset( $report['bounce_rate'] ) ? (float) $report['bounce_rate'] : 0.0;
				$totals['avg_time_on_site'] = isset( $report['avg_time_on_site'] ) ? (int) $report['avg_time_on_site'] : 0;
			} elseif ( ! empty( $report ) && is_array( reset( $report ) ) ) {
				
				$days = 0;
				foreach ( $report as $day ) {
					if ( ! is_array( $day ) ) { continue; }
					$days++;
					$totals['pageviews'] += (int) ( $day['nb_pageviews'] ?? 0 );
					$totals['visitors']  += (int) ( $day['nb_uniq_visitors'] ?? 0 );
					$totals['visits']    += (int) ( $day['nb_visits'] ?? 0 );
					$totals['bounce_rate'] += (float) ( $day['bounce_rate'] ?? 0 );
					$totals['avg_time_on_site'] += (int) ( $day['avg_time_on_site'] ?? 0 );
				}
				if ( $days > 1 ) {
					$totals['bounce_rate'] = round( $totals['bounce_rate'] / $days, 2 );
					$totals['avg_time_on_site'] = (int) round( $totals['avg_time_on_site'] / $days );
				}
			}
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'matomo',
			'source'     => 'matomo_local_database',
			'date_range' => $range,
			'data'       => array(
				'pageviews'        => $totals['pageviews'],
				'visitors'         => $totals['visitors'],
				'sessions'         => $totals['visits'],
				'bounce_rate'      => $totals['bounce_rate'],
				'avg_time_on_site' => $totals['avg_time_on_site'],
			),
		);
	}

	private static function matomo_top_content( $range, $limit ) {
		if ( ! class_exists( '\WpMatomo\Report\Data' ) || ! method_exists( '\WpMatomo\Report\Data', 'fetch_raw_report' ) ) {
			return self::unavailable( 'matomo', 'Matomo report layer is unavailable in this version.' );
		}
		try {
			$data = new \WpMatomo\Report\Data();
			$report = $data->fetch_raw_report(
				'Actions.getPageUrls',
				'range',
				$range['start_date'] . ',' . $range['end_date'],
				'nb_pageviews',
				$limit
			);
		} catch ( \Throwable $e ) {
			return self::unavailable( 'matomo', 'Matomo report query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}
		$rows = array();
		if ( is_array( $report ) ) {
			foreach ( array_slice( $report, 0, $limit ) as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$url = isset( $row['url'] ) ? (string) $row['url'] : '';
				if ( '' === $url ) { continue; }
				$rows[] = array(
					'page_url'  => $url,
					'pageviews' => isset( $row['nb_pageviews'] ) ? (int) $row['nb_pageviews'] : 0,
				);
			}
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'matomo',
			'source'     => 'matomo_local_database',
			'date_range' => $range,
			'data'       => array( 'toppages' => $rows ),
		);
	}

	
	private static function iawp_status() {
		$capable = class_exists( '\IAWP\Public_API\Analytics' ) && class_exists( '\IAWP\Date_Range\Exact_Date_Range' );
		return array(
			'provider'       => 'iawp',
			'connected'      => $capable,
			'version'        => defined( 'IAWP_VERSION' ) ? (string) IAWP_VERSION : null,
			'report_capable' => $capable,
			'source'         => 'local_database',
		);
	}

	private static function iawp_date_range( $range ) {
		return new \IAWP\Date_Range\Exact_Date_Range(
			new \DateTime( $range['start_date'] . ' 00:00:00', new \DateTimeZone( 'UTC' ) ),
			new \DateTime( $range['end_date'] . ' 23:59:59', new \DateTimeZone( 'UTC' ) )
		);
	}

	private static function iawp_summary( $range ) {
		if ( ! class_exists( '\IAWP\Public_API\Analytics' ) || ! method_exists( '\IAWP\Public_API\Analytics', 'for' ) ) {
			return self::unavailable( 'iawp', 'Independent Analytics public API is unavailable in this version.' );
		}
		try {
			$analytics = \IAWP\Public_API\Analytics::for( self::iawp_date_range( $range ) );
		} catch ( \Throwable $e ) {
			return self::unavailable( 'iawp', 'Independent Analytics query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'iawp',
			'source'     => 'iawp_local_database',
			'date_range' => $range,
			'data'       => array(
				'pageviews' => isset( $analytics->views ) ? (int) $analytics->views : 0,
				'visitors'  => isset( $analytics->visitors ) ? (int) $analytics->visitors : 0,
				'sessions'  => isset( $analytics->sessions ) ? (int) $analytics->sessions : 0,
			),
		);
	}

	private static function iawp_top_content( $range, $limit ) {
		if ( ! class_exists( '\IAWP\Public_API\Top_Posts' ) ) {
			return self::unavailable( 'iawp', 'Independent Analytics Top_Posts API is unavailable in this version.' );
		}
		try {
			$top_posts = new \IAWP\Public_API\Top_Posts( array(
				'from'    => new \DateTime( $range['start_date'] . ' 00:00:00', new \DateTimeZone( 'UTC' ) ),
				'to'      => new \DateTime( $range['end_date'] . ' 23:59:59', new \DateTimeZone( 'UTC' ) ),
				'limit'   => $limit,
				'sort_by' => 'views',
				'post_type' => 'post',
			) );
			$results = $top_posts->get();
		} catch ( \Throwable $e ) {
			return self::unavailable( 'iawp', 'Independent Analytics query failed: ' . sanitize_text_field( $e->getMessage() ) );
		}
		$rows = array();
		foreach ( (array) $results as $row ) {
			if ( ! is_object( $row ) && ! is_array( $row ) ) { continue; }
			$id    = is_object( $row ) ? ( $row->id ?? null ) : ( $row['id'] ?? null );
			$title = is_object( $row ) ? ( $row->title ?? '' ) : ( $row['title'] ?? '' );
			$views = is_object( $row ) ? ( $row->views ?? 0 ) : ( $row['views'] ?? 0 );
			$rows[] = array(
				'id'        => $id ? (int) $id : null,
				'title'     => (string) $title,
				'pageviews' => (int) $views,
			);
		}
		return array(
			'status'     => 'ok',
			'provider'   => 'iawp',
			'source'     => 'iawp_local_database',
			'date_range' => $range,
			'data'       => array( 'toppages' => array_slice( $rows, 0, $limit ) ),
		);
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
