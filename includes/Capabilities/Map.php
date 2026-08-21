<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use More_MCP\Capabilities\Integrations;

final class Map {

	private static $cache = null;

	public static function build(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}

		$map = array();

		foreach ( Integrations::all() as $class ) {

			if ( ! class_exists( $class ) || ! method_exists( $class, 'is_available' ) ) {
				continue;
			}
			if ( ! $class::is_available() ) {
				continue; 
			}
			if ( ! method_exists( $class, 'get_manifest' ) ) {
				continue; 
			}

			$manifest = $class::get_manifest();

			$providers    = isset( $manifest['providers'] ) && is_array( $manifest['providers'] ) ? $manifest['providers'] : array();
			$capabilities = isset( $manifest['capabilities'] ) && is_array( $manifest['capabilities'] ) ? $manifest['capabilities'] : array();

			foreach ( $capabilities as $cap ) {
				foreach ( $providers as $provider ) {
					$map[ $cap ][ $provider ] = $class;
				}
			}
		}

		return self::$cache = $map;
	}

	public static function for_display(): array {
		$map  = self::build();
		$kind = self::kinds_by_class();

		$out = array();
		foreach ( $map as $capability => $providers ) {
			$rows = array();
			foreach ( $providers as $provider => $class ) {
				$rows[] = array(
					'provider' => $provider,
					'kind'     => isset( $kind[ $class ] ) ? $kind[ $class ] : 'plugin',
				);
			}
			$described = self::describe( $capability );
			$out[]     = array(
				'capability' => $capability,
				'label'      => $described['label'],
				'summary'    => $described['summary'],
				'providers'  => $rows,
			);
		}

		return $out;
	}

	private static function kinds_by_class(): array {
		$kinds = array();
		foreach ( Integrations::all() as $class ) {
			if ( ! class_exists( $class ) || ! method_exists( $class, 'is_available' ) || ! method_exists( $class, 'get_manifest' ) ) {
				continue;
			}
			if ( ! $class::is_available() ) {
				continue;
			}
			$manifest        = $class::get_manifest();
			$kinds[ $class ] = isset( $manifest['kind'] ) ? (string) $manifest['kind'] : 'plugin';
		}
		return $kinds;
	}

	public static function reset(): void {
		self::$cache = null;
	}

	public static function describe( string $capability ): array {
		$labels = array(
			'page_building' => array(
				'label'   => 'Page building',
				'summary' => 'Visual layout of pages and posts through a builder.',
			),
			'site_settings' => array(
				'label'   => 'Site-wide design settings',
				'summary' => 'Global colours, typography, and theme-level settings a builder controls.',
			),
			'commerce'      => array(
				'label'   => 'Commerce',
				'summary' => 'Products, orders, and store data.',
			),
			'forms'         => array(
				'label'   => 'Forms',
				'summary' => 'Form definitions and submitted entries.',
			),
			'analytics'     => array(
				'label'   => 'Analytics',
				'summary' => 'Traffic and content-performance reporting.',
			),
			'custom_fields' => array(
				'label'   => 'Custom fields',
				'summary' => 'Structured meta beyond the core post fields.',
			),
			'redirects'     => array(
				'label'   => 'Redirects',
				'summary' => 'URL redirection rules.',
			),
			'caching'       => array(
				'label'   => 'Caching',
				'summary' => 'Page cache and its purge controls.',
			),
			'security'      => array(
				'label'   => 'Security',
				'summary' => 'Firewall, scanning, and hardening controls.',
			),
			'backup'        => array(
				'label'   => 'Backup',
				'summary' => 'Site backup and restore.',
			),
		);

		if ( isset( $labels[ $capability ] ) ) {
			return $labels[ $capability ];
		}

		return array(
			'label'   => ucwords( str_replace( '_', ' ', $capability ) ),
			'summary' => '',
		);
	}
}
