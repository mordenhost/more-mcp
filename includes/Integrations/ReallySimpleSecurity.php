<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReallySimpleSecurity {

	public static function is_available() {
		return defined( 'rsssl_version' ) && function_exists( 'rsssl_get_option' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'really-simple-security' ),
			'capabilities' => array( 'security' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'rsssl_get_status',
				'description' => 'Get a Really Simple Security overview: plugin version, whether SSL enforcement is enabled, whether the firewall module is enabled, and an aggregate summary of known vulnerabilities affecting installed plugins/themes/core (count of affected components, total vulnerability count, and the highest severity found). Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'rsssl_get_vulnerabilities',
				'description' => 'List components (plugins, themes, or WordPress core) with known vulnerabilities, as detected by Really Simple Security\'s vulnerability database: component name, slug, type, latest known version, and each affecting vulnerability\'s severity, published date, and affected version range. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of components to return (default 25, max 100).' ],
					],
				],
			],
			[
				'name'        => 'rsssl_get_security_headers',
				'description' => 'Read Really Simple Security\'s security-header configuration: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer Policy, HTTP Strict Transport Security (enabled, max-age, preload, subdomains), and the Cross-Origin policies (Opener/Resource/Embedder). Values are the configured option keys, exactly as the plugin\'s own settings screen stores them. Configuration only — this reads what is configured, not whether headers actually render on responses. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Really Simple Security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Really Simple Security is not active' );
		}

		switch ( $name ) {
			case 'rsssl_get_status':
				return self::get_status();

			case 'rsssl_get_vulnerabilities':
				return self::get_vulnerabilities( $args );

			case 'rsssl_get_security_headers':
				return self::get_security_headers();

			default:
				throw new \Exception( 'Unknown Really Simple Security tool: ' . esc_html( $name ) );
		}
	}

	private static function get_status() {
		$components = self::vulnerability_map();

		$severity_rank = [ 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4 ];
		$total_vulns    = 0;
		$highest        = '';
		$highest_rank   = 0;

		foreach ( $components as $component ) {
			$vulns = is_array( $component ) && isset( $component['vulnerabilities'] ) && is_array( $component['vulnerabilities'] )
				? $component['vulnerabilities']
				: [];
			$total_vulns += count( $vulns );
			foreach ( $vulns as $vuln ) {
				$severity = isset( $vuln['severity'] ) ? strtolower( (string) $vuln['severity'] ) : '';
				$rank     = $severity_rank[ $severity ] ?? 0;
				if ( $rank > $highest_rank ) {
					$highest_rank = $rank;
					$highest      = $severity;
				}
			}
		}

		return [
			'provider'                     => 'really-simple-security',
			'plugin_version'               => defined( 'rsssl_version' ) ? rsssl_version : '',
			'ssl_enabled'                  => (bool) rsssl_get_option( 'ssl_enabled', false ),
			'firewall_enabled'             => (bool) rsssl_get_option( 'enable_firewall', false ),
			'vulnerable_components_count'  => count( $components ),
			'total_vulnerabilities'        => $total_vulns,
			'highest_severity'             => '' !== $highest ? $highest : null,
		];
	}

	private static function get_vulnerabilities( $args ) {
		$components = self::vulnerability_map();
		$limit      = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 25;

		$out = [];
		foreach ( $components as $key => $component ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( ! is_array( $component ) ) {
				continue;
			}
			$vulns = isset( $component['vulnerabilities'] ) && is_array( $component['vulnerabilities'] ) ? $component['vulnerabilities'] : [];
			$out[] = [
				'storage_key'     => (string) $key,
				'name'            => (string) ( $component['name'] ?? '' ),
				'slug'            => (string) ( $component['slug'] ?? '' ),
				'type'            => (string) ( $component['type'] ?? '' ),
				'latest_version'  => $component['latestVersion'] ?? null,
				'vulnerabilities' => array_map(
					function ( $v ) {
						return [
							'severity'       => $v['severity'] ?? '',
							'published_at'   => $v['published_at'] ?? '',
							'fixed_in'       => isset( $v['fixed_in'] ) ? (bool) $v['fixed_in'] : null,
							'version_from'   => $v['version_from'] ?? '',
							'version_to'     => $v['version_to'] ?? '',
						];
					},
					$vulns
				),
			];
		}

		return [
			'count'      => count( $out ),
			'total'      => count( $components ),
			'components' => $out,
		];
	}

	private static function get_security_headers() {
		$select_fields = [
			'x_xss_protection',
			'x_frame_options',
			'referrer_policy',
			'cross_origin_opener_policy',
			'cross_origin_resource_policy',
			'cross_origin_embedder_policy',
		];

		$headers = [];
		foreach ( $select_fields as $field ) {
			$headers[ $field ] = (string) rsssl_get_option( $field, '' );
		}

		return [
			'provider'                      => 'really-simple-security',
			'x_content_type_options'        => (bool) rsssl_get_option( 'x_content_type_options', false ),
			'x_xss_protection'              => $headers['x_xss_protection'],
			'x_frame_options'               => $headers['x_frame_options'],
			'referrer_policy'               => $headers['referrer_policy'],
			'cross_origin_opener_policy'    => $headers['cross_origin_opener_policy'],
			'cross_origin_resource_policy'  => $headers['cross_origin_resource_policy'],
			'cross_origin_embedder_policy'  => $headers['cross_origin_embedder_policy'],
			'hsts'                          => [
				'enabled'    => (bool) rsssl_get_option( 'hsts', false ),
				'max_age'    => (string) rsssl_get_option( 'hsts_max_age', '' ),
				'preload'    => (bool) rsssl_get_option( 'hsts_preload', false ),
				'subdomains' => (bool) rsssl_get_option( 'hsts_subdomains', false ),
			],
		];
	}

	private static function vulnerability_map() {
		$stored = get_option( 'rsssl_vulnerabilities', [] );
		return is_array( $stored ) ? $stored : [];
	}
}
