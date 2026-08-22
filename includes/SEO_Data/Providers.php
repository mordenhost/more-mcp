<?php

namespace More_MCP\SEO_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Providers {

	public static function slugs(): array {
		return array( 'semrush', 'se_ranking', 'dataforseo', 'ahrefs', 'search_console', 'analytics4' );
	}

	public static function all(): array {
		return array(
			'semrush'        => array(
				'label'       => 'Semrush',
				'status_kind' => 'keyed',
				'trial_badge' => __( '14-day free trial available', 'more-mcp' ),
				'signup_url'  => 'https://www.semrush.com/api-documentation/',
				'docs_url'    => 'https://developer.semrush.com/api/',
				'endpoint'    => 'https://api.semrush.com',
				'auth'        => array( 'type' => 'query', 'param' => 'key' ),
				'fields'      => array(
					'api_key' => array(
						'type'        => 'password',
						'label'       => __( 'API Key', 'more-mcp' ),
						'required'    => true,
						'placeholder' => '',
						'help'        => __( 'Semrush API key from your subscription. Standard/Business plan with API units required.', 'more-mcp' ),
					),
				),
			),

			'se_ranking'     => array(
				'label'       => 'SE Ranking',
				'status_kind' => 'keyed',
				'trial_badge' => __( '14-day free trial · 100,000 API credits', 'more-mcp' ),
				'signup_url'  => 'https://seranking.com/api.html',
				'docs_url'    => 'https://seranking.com/api-documentation.html',
				'endpoint'    => 'https://api.seranking.com',
				'auth'        => array( 'type' => 'bearer' ),
				'fields'      => array(
					'api_key' => array(
						'type'        => 'password',
						'label'       => __( 'API Token', 'more-mcp' ),
						'required'    => true,
						'placeholder' => '',
						'help'        => __( 'SE Ranking API token from Account → API. Sent as a Bearer token.', 'more-mcp' ),
					),
				),
			),

			'dataforseo'     => array(
				'label'       => 'DataforSEO',
				'status_kind' => 'keyed',
				'trial_badge' => __( '$1 trial credit available', 'more-mcp' ),
				'signup_url'  => 'https://app.dataforseo.com/register',
				'docs_url'    => 'https://docs.dataforseo.com/v3/',
				'endpoint'    => 'https://api.dataforseo.com',
				
				'auth'        => array( 'type' => 'basic' ),
				'fields'      => array(
					'login'    => array(
						'type'        => 'text',
						'label'       => __( 'API Login', 'more-mcp' ),
						'required'    => true,
						'placeholder' => '',
						'help'        => __( 'DataForSEO account login (the email you registered with).', 'more-mcp' ),
					),
					'password' => array(
						'type'        => 'password',
						'label'       => __( 'API Password', 'more-mcp' ),
						'required'    => true,
						'placeholder' => '',
						'help'        => __( 'DataForSEO API password from your dashboard, NOT your login password unless you set them the same.', 'more-mcp' ),
					),
				),
			),

			'ahrefs'         => array(
				'label'       => 'Ahrefs (DR)',
				'status_kind' => 'keyed',
				'trial_badge' => '',
				'signup_url'  => 'https://ahrefs.com/api',
				'docs_url'    => 'https://docs.ahrefs.com/en/api/reference/public/get-domain-rating-free',

				

				'endpoint'    => 'https://api.ahrefs.com',
				'auth'        => array( 'type' => 'bearer' ),
				'fields'      => array(
					'api_key' => array(
						'type'        => 'password',
						'label'       => __( 'API Key', 'more-mcp' ),
						'required'    => true,
						'placeholder' => '',
						'help'        => __( 'Ahrefs APIv3 key from Account → API. Sent as a Bearer token.', 'more-mcp' ),
					),
				),
			),

			'search_console' => array(
				'label'       => 'Google Search Console',
				'status_kind' => 'service_account',
				'trial_badge' => '',
				'signup_url'  => 'https://console.cloud.google.com/iam-admin/serviceaccounts',
				'docs_url'    => 'https://developers.google.com/webmaster-tools/v1/api_reference_index',
				'endpoint'    => 'https://searchconsole.googleapis.com',
				'auth'        => array( 'type' => 'bearer' ),
				'fields'      => self::google_service_account_fields(),
			),

			'analytics4'     => array(
				'label'       => 'Google Analytics 4',
				'status_kind' => 'service_account',
				'trial_badge' => '',
				'signup_url'  => 'https://console.cloud.google.com/iam-admin/serviceaccounts',
				'docs_url'    => 'https://developers.google.com/analytics/devguides/reporting/data/v1',
				'endpoint'    => 'https://analyticsdata.googleapis.com',
				'auth'        => array( 'type' => 'bearer' ),
				'fields'      => self::google_service_account_fields(),
			),
		);
	}

	private static function google_service_account_fields(): array {
		return array(
			'service_account_json' => array(
				'type'        => 'textarea',
				'label'       => __( 'Service Account key (JSON)', 'more-mcp' ),
				'required'    => true,
				'placeholder' => '{ "type": "service_account", ... }',
				'help'        => __( 'Paste the full service account key JSON from Google Cloud → IAM & Admin → Service Accounts → Keys. Then share the property with the service account email. GA4: add it as a property Viewer; Search Console: add it as a user on the property. Stored encrypted-at-rest is not provided by WordPress, so treat this as a live secret.', 'more-mcp' ),
			),
		);
	}

	public static function get( string $slug ) {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}
}
