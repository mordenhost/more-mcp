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
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Sucuri Security tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Sucuri Security is not active' );
		}

		if ( 'sucuri_get_status' !== $name ) {
			throw new \Exception( 'Unknown Sucuri Security tool: ' . esc_html( $name ) );
		}

		return self::get_status();
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
}
