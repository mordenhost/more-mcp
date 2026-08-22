<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics4 {

	const SLUG = 'analytics4';

	
	
	const ADMIN_BASE = 'https://analyticsadmin.googleapis.com';

	public static function get_tools(): array {
		if ( ! Credentials::is_active( self::SLUG ) ) {
			return array();
		}

		$property = array( 'type' => 'string', 'description' => 'GA4 property ID (numeric), e.g. "123456789". Do not include the "properties/" prefix.' );
		$date     = array( 'type' => 'string', 'description' => 'YYYY-MM-DD (or a relative token like "7daysAgo", "today").' );

		return array(
			array(
				'name'        => 'ga4_list_accounts',
				'description' => 'List Google Analytics accounts the connected account can access. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'ga4_list_properties',
				'description' => 'List GA4 properties under an account. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'account_id' => array( 'type' => 'string', 'description' => 'Account ID (numeric), e.g. "123456".' ) ),
					'required'   => array( 'account_id' ),
				),
			),
			array(
				'name'        => 'ga4_get_property',
				'description' => 'Get one GA4 property\'s details: display name, time zone, currency, and create time. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_metadata',
				'description' => 'List the dimensions and metrics available for a property, including custom ones. Use before ga4_run_report to pick valid field names. Source: Google Analytics 4 Data API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_run_report',
				'description' => 'Run a standard GA4 report: metrics by dimensions over a date range. Source: Google Analytics 4 Data API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'property_id' => $property,
						'start_date'  => $date,
						'end_date'    => $date,
						'metrics'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Metric names, e.g. ["sessions","totalUsers"]. Defaults to ["sessions"].' ),
						'dimensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Dimension names, e.g. ["date","pagePath"]. Optional.' ),
						'limit'       => array( 'type' => 'integer', 'description' => 'Max rows (1–100000). Default 100.' ),
					),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_run_pivot_report',
				'description' => 'Run a GA4 pivot report: metrics pivoted across one or more dimensions. Source: Google Analytics 4 Data API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'property_id' => $property,
						'start_date'  => $date,
						'end_date'    => $date,
						'metrics'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Metric names.' ),
						'dimensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Dimension names to pivot on.' ),
					),
					'required'   => array( 'property_id', 'metrics', 'dimensions' ),
				),
			),
			array(
				'name'        => 'ga4_realtime_report',
				'description' => 'Run a GA4 realtime report: active users and events in the last 30 minutes, by dimension. Source: Google Analytics 4 Data API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'property_id' => $property,
						'metrics'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Metric names, e.g. ["activeUsers"]. Defaults to ["activeUsers"].' ),
						'dimensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Dimension names, e.g. ["country"]. Optional.' ),
					),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_list_data_streams',
				'description' => 'List data streams (web/app) configured for a property, with their measurement IDs. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_list_conversion_events',
				'description' => 'List conversion (key) events configured for a property. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_list_custom_dimensions',
				'description' => 'List custom dimensions defined for a property. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
			array(
				'name'        => 'ga4_list_custom_metrics',
				'description' => 'List custom metrics defined for a property. Source: Google Analytics 4 Admin API. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'property_id' => $property ),
					'required'   => array( 'property_id' ),
				),
			),
		);
	}

	public static function tool_names(): array {
		return array(
			'ga4_list_accounts', 'ga4_list_properties', 'ga4_get_property', 'ga4_metadata',
			'ga4_run_report', 'ga4_run_pivot_report', 'ga4_realtime_report', 'ga4_list_data_streams',
			'ga4_list_conversion_events', 'ga4_list_custom_dimensions', 'ga4_list_custom_metrics',
		);
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use SEO data tools.' );
		}
		if ( ! Credentials::is_active( self::SLUG ) ) {
			throw new \Exception( 'Google Analytics 4 is not connected. Add a service account key in Settings → External Services → SEO & analytics, then grant the service account email Viewer access to the GA4 property.' );
		}

		$token = Google_Service_Account::ensure_fresh_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			throw new \Exception( $token->get_error_message() );
		}

		$prop = isset( $args['property_id'] ) ? preg_replace( '/[^0-9]/', '', (string) $args['property_id'] ) : '';
		$metrics    = self::names( $args['metrics'] ?? array( 'sessions' ) );
		$dimensions = self::names( $args['dimensions'] ?? array() );

		switch ( $name ) {
			case 'ga4_list_accounts':
				return self::result( self::admin( '/v1beta/accounts', array() ) );

			case 'ga4_list_properties':
				$acct = preg_replace( '/[^0-9]/', '', (string) ( $args['account_id'] ?? '' ) );
				if ( '' === $acct ) {
					throw new \Exception( 'account_id is required.' );
				}
				return self::result( self::admin( '/v1beta/properties', array( 'query' => array( 'filter' => 'parent:accounts/' . $acct ) ) ) );

			case 'ga4_get_property':
				self::need_prop( $prop );
				return self::result( self::admin( '/v1beta/properties/' . $prop, array() ) );

			case 'ga4_metadata':
				self::need_prop( $prop );
				return self::result( Http::request( self::SLUG, '/v1beta/properties/' . $prop . '/metadata', array() ) );

			case 'ga4_run_report':
				self::need_prop( $prop );
				$body = array(
					'dateRanges' => array( array(
						'startDate' => isset( $args['start_date'] ) ? (string) $args['start_date'] : '28daysAgo',
						'endDate'   => isset( $args['end_date'] ) ? (string) $args['end_date'] : 'today',
					) ),
					'metrics'    => self::metric_objs( $metrics ),
					'limit'      => isset( $args['limit'] ) ? (string) max( 1, min( 100000, (int) $args['limit'] ) ) : '100',
				);
				if ( $dimensions ) {
					$body['dimensions'] = self::dim_objs( $dimensions );
				}
				return self::result( Http::request( self::SLUG, '/v1beta/properties/' . $prop . ':runReport', array( 'method' => 'POST', 'body' => $body ) ) );

			case 'ga4_run_pivot_report':
				self::need_prop( $prop );
				if ( ! $dimensions ) {
					throw new \Exception( 'dimensions is required for a pivot report.' );
				}
				$body = array(
					'dateRanges' => array( array(
						'startDate' => isset( $args['start_date'] ) ? (string) $args['start_date'] : '28daysAgo',
						'endDate'   => isset( $args['end_date'] ) ? (string) $args['end_date'] : 'today',
					) ),
					'metrics'    => self::metric_objs( $metrics ),
					'dimensions' => self::dim_objs( $dimensions ),
					'pivots'     => array( array( 'fieldNames' => $dimensions, 'limit' => 100 ) ),
				);
				return self::result( Http::request( self::SLUG, '/v1beta/properties/' . $prop . ':runPivotReport', array( 'method' => 'POST', 'body' => $body ) ) );

			case 'ga4_realtime_report':
				self::need_prop( $prop );
				$rt_metrics = self::names( $args['metrics'] ?? array( 'activeUsers' ) );
				$body = array( 'metrics' => self::metric_objs( $rt_metrics ) );
				if ( $dimensions ) {
					$body['dimensions'] = self::dim_objs( $dimensions );
				}
				return self::result( Http::request( self::SLUG, '/v1beta/properties/' . $prop . ':runRealtimeReport', array( 'method' => 'POST', 'body' => $body ) ) );

			case 'ga4_list_data_streams':
				self::need_prop( $prop );
				return self::result( self::admin( '/v1beta/properties/' . $prop . '/dataStreams', array() ) );

			case 'ga4_list_conversion_events':
				self::need_prop( $prop );
				return self::result( self::admin( '/v1beta/properties/' . $prop . '/keyEvents', array() ) );

			case 'ga4_list_custom_dimensions':
				self::need_prop( $prop );
				return self::result( self::admin( '/v1beta/properties/' . $prop . '/customDimensions', array() ) );

			case 'ga4_list_custom_metrics':
				self::need_prop( $prop );
				return self::result( self::admin( '/v1beta/properties/' . $prop . '/customMetrics', array() ) );
		}

		throw new \Exception( 'Unknown Analytics 4 tool: ' . $name );
	}

	private static function admin( string $path, array $args ) {
		return Http::request( self::SLUG, self::ADMIN_BASE . $path, $args );
	}

	private static function need_prop( string $prop ): void {
		if ( '' === $prop ) {
			throw new \Exception( 'property_id is required (numeric).' );
		}
	}

	private static function names( $val ): array {
		if ( ! is_array( $val ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $val ), static fn( $s ) => '' !== $s ) );
	}

	private static function metric_objs( array $names ): array {
		return array_map( static fn( $n ) => array( 'name' => $n ), $names );
	}

	private static function dim_objs( array $names ): array {
		return array_map( static fn( $n ) => array( 'name' => $n ), $names );
	}

	private static function result( $res ) {
		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}
		return array( 'source' => 'analytics4', 'data' => $res['data'] );
	}
}
