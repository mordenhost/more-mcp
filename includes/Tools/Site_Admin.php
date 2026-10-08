<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Admin implements Handler {

	private const SETTINGS = array(
		'title'                  => 'blogname',
		'tagline'                => 'blogdescription',
		'timezone'               => 'timezone_string',
		'date_format'            => 'date_format',
		'time_format'            => 'time_format',
		'start_of_week'          => 'start_of_week',
		'posts_per_page'         => 'posts_per_page',
		'default_comment_status' => 'default_comment_status',
		'default_ping_status'    => 'default_ping_status',
		'search_engine_visible'  => 'blog_public',
	);

	private const ADDRESSES = array(
		'site_url' => 'siteurl',
		'home_url' => 'home',
	);

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'wp_get_site_settings',
				'description' => 'Read the general site settings: title, tagline, site and home URLs, timezone, date and time formats, week start, posts per page, default comment and ping status, and search-engine visibility. Needs manage_options.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'wp_update_site_settings',
				'description' => 'Update general site settings. Pass only what changes: title, tagline, timezone (e.g. "Asia/Jakarta"), date_format, time_format, start_of_week (0-6), posts_per_page (1-100), default_comment_status and default_ping_status (open|closed), search_engine_visible (boolean). site_url and home_url are accepted only with confirm_url_change=true because a wrong address locks the site out. Requires manage_options and the "Allow AI to write WordPress options" admin switch. Returns previous and new values.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'                  => array( 'type' => 'string' ),
						'tagline'                => array( 'type' => 'string' ),
						'timezone'               => array( 'type' => 'string' ),
						'date_format'            => array( 'type' => 'string' ),
						'time_format'            => array( 'type' => 'string' ),
						'start_of_week'          => array( 'type' => 'integer' ),
						'posts_per_page'         => array( 'type' => 'integer' ),
						'default_comment_status' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
						'default_ping_status'    => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
						'search_engine_visible'  => array( 'type' => 'boolean' ),
						'site_url'               => array( 'type' => 'string', 'description' => 'WordPress Address. Needs confirm_url_change.' ),
						'home_url'               => array( 'type' => 'string', 'description' => 'Site Address. Needs confirm_url_change.' ),
						'confirm_url_change'     => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'wp_get_site_health',
				'description' => 'Run WordPress core\'s Site Health checks and return each result (label, status good|recommended|critical, short description) with a count per status. Pass include_info=true to add the Info report (versions, constants, server, database, active theme and plugins) with private values left out. Needs view_site_health_checks.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'include_info' => array( 'type' => 'boolean', 'description' => 'Also return the Info report sections.' ) ),
				),
			),
			array(
				'name'        => 'wp_run_cron_event',
				'description' => 'Run a scheduled WP-Cron event now instead of waiting for it. hook must name an event that is currently scheduled (see wp_get_cron_schedule); arbitrary action names are refused. Pass the same args the event was scheduled with if it has any. Needs manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'hook' => array( 'type' => 'string' ),
						'args' => array( 'type' => 'array', 'description' => 'Arguments the event was scheduled with (default none).' ),
					),
					'required'   => array( 'hook' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array( 'wp_get_site_settings', 'wp_update_site_settings', 'wp_get_site_health', 'wp_run_cron_event' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_site_settings':
				return self::get_settings();
			case 'wp_update_site_settings':
				return self::update_settings( $args );
			case 'wp_get_site_health':
				return self::site_health( $args );
			case 'wp_run_cron_event':
				return self::run_cron( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function require_manage_options( string $what ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to ' . esc_html( $what ) . '.' );
		}
	}

	private static function get_settings(): array {
		self::require_manage_options( 'read site settings' );
		$out = array();
		foreach ( self::SETTINGS as $label => $option ) {
			$value         = get_option( $option );
			$out[ $label ] = 'blog_public' === $option ? (bool) (int) $value : $value;
		}
		$out['timezone_effective'] = wp_timezone_string();
		foreach ( self::ADDRESSES as $label => $option ) {
			$out[ $label ] = get_option( $option );
		}
		return $out;
	}

	private static function update_settings( array $args ): array {
		self::require_manage_options( 'change site settings' );

		$toggles = get_option( 'more_mcp_settings', array() );
		if ( empty( $toggles['allow_option_writes'] ) ) {
			throw new \Exception( 'Option writes are disabled. Enable "Allow AI to write WordPress options" under More MCP > Settings > General Settings.' );
		}

		$changes = array(); 
		foreach ( self::SETTINGS as $label => $option ) {
			if ( ! array_key_exists( $label, $args ) ) {
				continue;
			}
			$changes[ $option ] = self::sanitize_setting( $label, $args[ $label ] );
		}

		$wants_address = false;
		foreach ( self::ADDRESSES as $label => $option ) {
			if ( ! array_key_exists( $label, $args ) ) {
				continue;
			}
			$wants_address      = true;
			$changes[ $option ] = self::sanitize_address( $label, $args[ $label ] );
		}
		if ( $wants_address ) {
			if ( empty( $args['confirm_url_change'] ) ) {
				throw new \Exception( 'Changing site_url or home_url can lock everyone out of the site. Pass confirm_url_change=true if you are sure.' );
			}
			if ( ( isset( $changes['siteurl'] ) && defined( 'WP_SITEURL' ) ) || ( isset( $changes['home'] ) && defined( 'WP_HOME' ) ) ) {
				throw new \Exception( 'That address is fixed by WP_SITEURL / WP_HOME in wp-config.php and cannot be changed here.' );
			}
		}

		if ( ! $changes ) {
			throw new \Exception( 'Nothing to update. Pass at least one setting.' );
		}

		$labels = array_flip( array_merge( self::SETTINGS, self::ADDRESSES ) );
		$report = array();
		foreach ( $changes as $option => $value ) {
			$previous = get_option( $option );
			update_option( $option, $value );
			$report[ $labels[ $option ] ] = array(
				'previous' => 'blog_public' === $option ? (bool) (int) $previous : $previous,
				'value'    => 'blog_public' === $option ? (bool) (int) $value : $value,
			);
		}
		return array( 'updated' => $report );
	}

	private static function sanitize_setting( string $label, $value ) {
		switch ( $label ) {
			case 'title':
			case 'tagline':
				return sanitize_text_field( (string) $value );
			case 'timezone':
				$tz = (string) $value;
				if ( '' !== $tz && ! in_array( $tz, timezone_identifiers_list( \DateTimeZone::ALL_WITH_BC ), true ) && ! preg_match( '/^UTC[+-]\d{1,2}(\.5|\.25|\.75)?$/', $tz ) ) {
					throw new \Exception( 'timezone must be an IANA name such as "Asia/Jakarta" or a UTC offset such as "UTC+7".' );
				}
				return $tz;
			case 'date_format':
			case 'time_format':
				$format = sanitize_text_field( (string) $value );
				if ( '' === $format ) {
					throw new \Exception( esc_html( $label ) . ' cannot be empty.' );
				}
				return $format;
			case 'start_of_week':
				$day = (int) $value;
				if ( $day < 0 || $day > 6 ) {
					throw new \Exception( 'start_of_week must be 0 (Sunday) to 6 (Saturday).' );
				}
				return $day;
			case 'posts_per_page':
				$count = (int) $value;
				if ( $count < 1 || $count > 100 ) {
					throw new \Exception( 'posts_per_page must be between 1 and 100.' );
				}
				return $count;
			case 'default_comment_status':
			case 'default_ping_status':
				if ( ! in_array( $value, array( 'open', 'closed' ), true ) ) {
					throw new \Exception( esc_html( $label ) . ' must be "open" or "closed".' );
				}
				return $value;
			case 'search_engine_visible':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}
		throw new \Exception( 'Unknown setting.' );
	}

	private static function sanitize_address( string $label, $value ): string {
		$url    = esc_url_raw( (string) $value );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_parse_url( $url, PHP_URL_HOST ) ) {
			throw new \Exception( esc_html( $label ) . ' must be a full http(s) address such as https://example.com.' );
		}
		return untrailingslashit( $url );
	}

	private static function site_health( array $args ): array {
		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			throw new \Exception( 'You do not have permission to view Site Health.' );
		}
		foreach ( array( 'class-wp-site-health.php', 'class-wp-debug-data.php', 'update.php', 'misc.php', 'file.php', 'plugin.php', 'theme.php' ) as $file ) {
			$path = ABSPATH . 'wp-admin/includes/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
		if ( ! class_exists( '\WP_Site_Health' ) ) {
			throw new \Exception( 'Site Health is not available on this WordPress version.' );
		}

		$health  = \WP_Site_Health::get_instance();
		$tests   = \WP_Site_Health::get_tests();
		$results = array();
		$counts  = array( 'good' => 0, 'recommended' => 0, 'critical' => 0 );

		foreach ( (array) ( $tests['direct'] ?? array() ) as $id => $test ) {
			try {
				$callable = $test['test'] ?? '';
				if ( is_string( $callable ) && is_callable( array( $health, 'get_test_' . $callable ) ) ) {
					$result = call_user_func( array( $health, 'get_test_' . $callable ) );
				} elseif ( is_callable( $callable ) ) {
					$result = call_user_func( $callable );
				} else {
					continue;
				}
			} catch ( \Throwable $e ) {
				$results[] = array( 'id' => $id, 'label' => $test['label'] ?? $id, 'status' => 'error', 'description' => 'The check failed to run.' );
				continue;
			}
			$status = isset( $result['status'] ) ? (string) $result['status'] : 'recommended';
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
			$results[] = array(
				'id'          => $id,
				'label'       => wp_strip_all_tags( (string) ( $result['label'] ?? $test['label'] ?? $id ) ),
				'status'      => $status,
				'description' => mb_substr( trim( wp_strip_all_tags( (string) ( $result['description'] ?? '' ) ) ), 0, 400 ),
			);
		}

		$out = array(
			'counts'  => $counts,
			'checks'  => $results,
			'skipped' => 'Asynchronous checks (REST availability, loopback, page cache) need a browser round trip and are not run here.',
		);

		if ( ! empty( $args['include_info'] ) && class_exists( '\WP_Debug_Data' ) ) {
			$sections = array();
			foreach ( \WP_Debug_Data::debug_data() as $key => $section ) {
				$fields = array();
				foreach ( (array) ( $section['fields'] ?? array() ) as $field_key => $field ) {
					if ( ! empty( $field['private'] ) ) {
						continue;
					}
					$value = $field['debug'] ?? $field['value'] ?? '';
					$fields[ $field_key ] = array(
						'label' => $field['label'] ?? $field_key,
						'value' => is_scalar( $value ) ? $value : wp_json_encode( $value ),
					);
				}
				$sections[ $key ] = array(
					'label'  => $section['label'] ?? $key,
					'fields' => $fields,
				);
			}
			$out['info'] = $sections;
		}
		return $out;
	}

	private static function run_cron( array $args ): array {
		self::require_manage_options( 'run cron events' );
		$hook = isset( $args['hook'] ) ? (string) $args['hook'] : '';
		if ( '' === $hook ) {
			throw new \Exception( 'hook is required.' );
		}
		$event_args = isset( $args['args'] ) && is_array( $args['args'] ) ? array_values( $args['args'] ) : array();

		$event = false;
		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				$key = md5( serialize( $event_args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WP cron keys events by md5(serialize(args)).
				if ( isset( $hooks[ $hook ][ $key ] ) ) {
					$event = (object) array( 'hook' => $hook, 'timestamp' => (int) $timestamp );
					break;
				}
			}
		}
		if ( ! $event ) {
			throw new \Exception( 'No scheduled event with that hook and args. List them with wp_get_cron_schedule.' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- A slow cron job must not die at the default limit.
		}
		$started = microtime( true );
		ob_start();
		$error = '';
		try {
			do_action_ref_array( $hook, $event_args );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}
		ob_end_clean();

		if ( '' !== $error ) {
			throw new \Exception( 'The event ran but raised an error: ' . esc_html( $error ) );
		}
		return array(
			'hook'        => $hook,
			'ran'         => true,
			'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
			'note'        => 'The event was run in this request. Its schedule is unchanged.',
		);
	}
}
