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

			
			
			if ( class_exists( '\More_MCP\Capabilities\Toggles' ) && ! Toggles::is_class_enabled( $class ) ) {
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
			'spam'          => array(
				'label'   => 'Spam protection',
				'summary' => 'Comment/anti-spam service status and counts. Read-only diagnostic.',
			),
			'images'        => array(
				'label'   => 'Image optimization',
				'summary' => 'Media-image optimization status and savings. Read-only diagnostic.',
			),
			'stock_images'  => array(
				'label'   => 'Stock image search',
				'summary' => 'Search stock photos (Unsplash, Pexels) and return candidates with license and attribution. Uses keys stored by a host plugin; no key is returned.',
			),
			'events'        => array(
				'label'   => 'Events',
				'summary' => 'Read-only The Events Calendar access: list/read events with hydrated venue and organizer detail. No write tools.',
			),
			'multilingual'  => array(
				'label'   => 'Multilingual',
				'summary' => 'Translation languages and routing configuration. Read-only diagnostic.',
			),
			'crm'           => array(
				'label'   => 'CRM',
				'summary' => 'Contact-list scale and status breakdown. Read-only diagnostic, counts only.',
			),
			'lms'           => array(
				'label'   => 'Learning management',
				'summary' => 'Course counts and enrolment totals. Read-only diagnostic, counts only.',
			),
			'booking'       => array(
				'label'   => 'Booking',
				'summary' => 'Appointment/booking scale and the bookable service catalogue. Read-only diagnostic, counts and definitions only.',
			),
			'donations'     => array(
				'label'   => 'Donations',
				'summary' => 'Fundraising scale: donation-form and donation counts by status, donor total, site-wide amount raised, and campaign state. Read-only diagnostic, aggregate counts and campaign definitions only.',
			),
			'membership'    => array(
				'label'   => 'Membership',
				'summary' => 'Membership base scale and the level catalogue: member counts by status, payment-subscription counts by status (kept separate), and per-level pricing/access definitions. Read-only diagnostic, aggregate counts and level definitions only.',
			),
			'community'     => array(
				'label'   => 'Community',
				'summary' => 'Forum/community scale and the forum structure: forum, topic, reply, and topic-tag counts, plus each forum\'s status, visibility, parent, and its own topic/reply counts. Read-only diagnostic, aggregate counts and forum structure only, never discussion content or participant identity.',
			),
			'helpdesk'      => array(
				'label'   => 'Helpdesk',
				'summary' => 'Support-desk scale and configuration: ticket counts by status, priority, and mailbox, service-timing aggregates, and the mailbox and product definitions tickets are filed against. Read-only diagnostic, aggregate counts and definitions only, never a ticket, its conversation, or the customer or agent on it.',
			),
			'backup'        => array(
				'label'   => 'Backup',
				'summary' => 'Site backup and restore.',
			),
			'email'         => array(
				'label'   => 'Email delivery',
				'summary' => 'Outgoing-email (SMTP) configuration status. Read-only diagnostic.',
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
