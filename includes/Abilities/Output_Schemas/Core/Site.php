<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Site {
	public static function map(): array {
		return array(

			'wp_get_site_info'   => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'url'         => array( 'type' => 'string' ),
					'language'    => array( 'type' => 'string' ),
					'timezone'    => array( 'type' => 'string' ),
					'wp_version'  => array( 'type' => 'string' ),
				),
			),
			'more_mcp_connection_health' => array(
				'type'       => 'object',
				'properties' => array(
					'route'          => array( 'type' => 'string' ),
					'auth_method'    => array( 'type' => 'string' ),
					'relay'          => array( 'type' => array( 'string', 'null' ) ),
					'token_ttl'      => array( 'type' => array( 'integer', 'null' ) ),
					'session_id'     => array( 'type' => array( 'string', 'null' ) ),
					'active_scopes'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'server_version' => array( 'type' => 'string' ),
					'wp_version'     => array( 'type' => 'string' ),
					'php_version'    => array( 'type' => 'string' ),
					'builders'       => array(
						'type'       => 'object',
						'properties' => array(
							'divi_version'      => array( 'type' => array( 'string', 'null' ) ),
							'elementor_version' => array( 'type' => array( 'string', 'null' ) ),
							'gutenberg_version' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'wp_get_site_status' => array(
				'type'       => 'object',
				'properties' => array(
					'wp_version'          => array( 'type' => 'string' ),
					'php_version'         => array( 'type' => 'string' ),
					'mysql_version'       => array( 'type' => array( 'string', 'null' ) ),
					'is_multisite'        => array( 'type' => 'boolean' ),
					'active_plugin_count' => array( 'type' => 'integer' ),
					'active_theme'        => array(
						'type'       => 'object',
						'properties' => array(
							'name'       => array( 'type' => 'string' ),
							'stylesheet' => array( 'type' => 'string' ),
							'template'   => array( 'type' => 'string' ),
							'version'    => array( 'type' => 'string' ),
						),
					),
					'memory_limit'        => array( 'type' => 'string' ),
					'max_upload_size'     => array( 'type' => 'string' ),
					'max_execution_time'  => array( 'type' => 'integer' ),
					'timezone'            => array( 'type' => 'string' ),
					'debug_log_enabled'   => array( 'type' => 'boolean' ),
					'disk_free_bytes'     => array( 'type' => array( 'integer', 'null' ) ),
					'disk_free_human'     => array( 'type' => array( 'string', 'null' ) ),
					'install_age_days'    => array( 'type' => array( 'integer', 'null' ) ),
					'site_url'            => array( 'type' => 'string' ),
					'home_url'            => array( 'type' => 'string' ),
				),
			),
			'wp_get_error_log_tail' => array(
				'type'       => 'object',
				'properties' => array(
					'status'         => array( 'type' => 'string' ),
					'message'        => array( 'type' => 'string' ),
					'path'           => array( 'type' => 'string' ),
					'filesize_bytes' => array( 'type' => 'integer' ),
					'window_bytes'   => array( 'type' => 'integer' ),
					'truncated'      => array( 'type' => 'boolean' ),
					'filter'         => array( 'type' => 'string' ),
					'total_returned' => array( 'type' => 'integer' ),
					'lines'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'wp_get_cron_schedule' => array(
				'type'       => 'object',
				'properties' => array(
					'events'      => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'hook'          => array( 'type' => 'string' ),
								'next_run_ts'   => array( 'type' => 'integer' ),
								'next_run_iso'  => array( 'type' => 'string' ),
								'seconds_until' => array( 'type' => 'integer' ),
								'is_overdue'    => array( 'type' => 'boolean' ),
								'recurrence'    => array( 'type' => array( 'string', 'null' ) ),
								'args'          => array( 'type' => 'array' ),
							),
						),
					),
					'total_count' => array( 'type' => 'integer' ),
					'now_ts'      => array( 'type' => 'integer' ),
					'now_iso'     => array( 'type' => 'string' ),
				),
			),
			'wp_search' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'type'    => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'snippet' => array( 'type' => 'string' ),
					),
				),
			),

					);
	}
}
