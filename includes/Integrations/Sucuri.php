<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sucuri {

	public static function is_available() {
		return defined( 'SUCURISCAN_VERSION' ) || class_exists( 'SucuriScanOption' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'sucuri' ),
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
				'name'        => 'sucuri_get_status',
				'description' => 'Read Sucuri Security\'s local configuration status: plugin version, whether a Sucuri.net account is linked (presence only, never the account identifier), whether the monitoring API service is enabled, whether alert email is set up, and per-directory hardening lockdown state (uploads, wp-content, wp-includes). Audit logs, malware-scan results, and CloudProxy WAF connection state live behind Sucuri\'s own remote dashboard/secret storage and are not read here. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'sucuri_get_scan_status',
				'description' => 'Read the verdict of Sucuri Security\'s most recent SiteCheck malware scan from its LOCAL cache: whether the site is flagged for malware, whether it appears on any blocklist, and how fresh the cached scan is. Never triggers a scan and never makes a network request — if no scan result is cached (or it has expired) the tool says so. Verdicts only: infected URLs, payload excerpts, and blocklist service links in the cached report are never returned. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Sucuri Security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Sucuri Security is not active' );
		}

		if ( 'sucuri_get_status' === $name ) {
			return self::get_status();
		}

		if ( 'sucuri_get_scan_status' === $name ) {
			return self::get_scan_status();
		}

		throw new \Exception( 'Unknown Sucuri Security tool: ' . esc_html( $name ) );
	}

	private static function get_status() {
		$version = defined( 'SUCURISCAN_VERSION' ) ? SUCURISCAN_VERSION : '';

		$monitoring_api_service = 'disabled';
		$account_configured     = false;
		$notify_email_set       = false;
		if ( class_exists( 'SucuriScanOption' ) && method_exists( 'SucuriScanOption', 'getOption' ) ) {

			
			
			$monitoring_api_service = (string) \SucuriScanOption::getOption( ':api_service' );
			$account                = (string) \SucuriScanOption::getOption( ':account' );
			$account_configured     = '' !== $account;
			$notify_to              = (string) \SucuriScanOption::getOption( ':notify_to' );
			$notify_email_set       = '' !== $notify_to;
		}

		$hardening = [];
		if ( class_exists( 'SucuriScanHardening' ) && method_exists( 'SucuriScanHardening', 'isHardened' ) ) {
			foreach ( [ 'uploads', 'wp-content', 'wp-includes' ] as $dir ) {
				try {
					$hardening[ $dir ] = (bool) \SucuriScanHardening::isHardened( $dir );
				} catch ( \Throwable $e ) {
					$hardening[ $dir ] = null;
				}
			}
		}

		return [
			'provider'               => 'sucuri',
			'plugin_version'         => $version,
			'account_configured'     => $account_configured,
			'monitoring_api_service' => '' !== $monitoring_api_service ? $monitoring_api_service : 'disabled',
			'notify_email_set'       => $notify_email_set,
			'hardening'              => $hardening,
		];
	}

	private static function get_scan_status() {
		$cache_available = class_exists( 'SucuriScanCache' ) && defined( 'SUCURISCAN_SITECHECK_LIFETIME' );

		if ( ! $cache_available ) {
			return [
				'provider'       => 'sucuri',
				'available'      => false,
				'message'        => 'The Sucuri SiteCheck cache API is not available in this version.',
				'cached'         => false,
			];
		}

		try {
			$cache   = new \SucuriScanCache( 'sitecheck' );
			$results = $cache->get( 'scan_results', SUCURISCAN_SITECHECK_LIFETIME, 'array' );
		} catch ( \Throwable $e ) {
			return [
				'provider'       => 'sucuri',
				'available'      => false,
				'message'        => 'The Sucuri SiteCheck cache could not be read.',
				'cached'         => false,
			];
		}

		if ( ! is_array( $results ) || empty( $results ) ) {
			return [
				'provider'       => 'sucuri',
				'available'      => true,
				'cached'         => false,
				'message'        => 'No SiteCheck scan result is cached (or the cache has expired). Open the Sucuri dashboard to run a scan; this tool never triggers one.',
			];
		}

		$malware_warn  = isset( $results['MALWARE']['WARN'] ) && is_array( $results['MALWARE']['WARN'] )
			? count( $results['MALWARE']['WARN'] )
			: 0;
		$blacklist_warn = isset( $results['BLACKLIST']['WARN'] ) && is_array( $results['BLACKLIST']['WARN'] )
			? count( $results['BLACKLIST']['WARN'] )
			: 0;

		return [
			'provider'           => 'sucuri',
			'available'          => true,
			'cached'             => true,

			'malware'            => [
				'flagged'     => $malware_warn > 0,
				'warn_count'  => $malware_warn,
			],
			'blocklist'          => [
				'flagged'     => $blacklist_warn > 0,
				'warn_count'  => $blacklist_warn,
			],
		];
	}
}
