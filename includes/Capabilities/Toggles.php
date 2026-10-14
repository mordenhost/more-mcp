<?php
namespace Mordenhost_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Toggles {

	const OPTION_KEY = 'enabled_integrations';

	const ENABLE_LOCATION = 'Mordenhost MCP > Access > Plugins';

	const ENABLE_ADMIN_PATH = 'admin.php?page=mordenhost-mcp&panel=permissions&sub=plugins';

	public static function catalog(): array {
		return array(
			'woocommerce'    => array( 'label' => 'WooCommerce', 'prefixes' => array( 'wc_' ) ),
			'litespeed'      => array( 'label' => 'LiteSpeed Cache', 'prefixes' => array( 'ls_' ) ),
			'elementor'      => array( 'label' => 'Elementor', 'prefixes' => array( 'elementor_' ) ),
			'divi'           => array( 'label' => 'Divi', 'prefixes' => array( 'divi_' ) ),
			'beaver-builder' => array( 'label' => 'Beaver Builder', 'prefixes' => array( 'beaver_' ) ),
			'siteorigin-panels' => array( 'label' => 'SiteOrigin Page Builder', 'prefixes' => array( 'siteorigin_' ) ),
			'acf'            => array( 'label' => 'Advanced Custom Fields', 'prefixes' => array( 'acf_' ) ),
			'metabox'        => array( 'label' => 'Meta Box', 'prefixes' => array( 'mb_' ) ),
			'redirection'    => array( 'label' => 'Redirection', 'prefixes' => array( 'redirection_' ) ),
			'rank_math'      => array( 'label' => 'Rank Math (SEO scores, audit, links, schema, keywords)', 'prefixes' => array( 'rank_math_' ) ),
			'analytics'      => array( 'label' => 'Analytics (Site Kit, Jetpack, MonsterInsights, ExactMetrics, Burst, Matomo, Independent Analytics, WP Statistics)', 'prefixes' => array( 'analytics_' ) ),
			'email'          => array( 'label' => 'Email / SMTP (WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP, GoSMTP, SureMail)', 'prefixes' => array( 'email_' ) ),
			'forms'          => array( 'label' => 'Forms (Gravity, Fluent, CF7, WPForms, Ninja, Formidable, Forminator, SureForms, Everest Forms)', 'prefixes' => array( 'forms_' ) ),
			'wp-rocket'      => array( 'label' => 'WP Rocket', 'prefixes' => array( 'wpr_' ) ),
			'w3tc'           => array( 'label' => 'W3 Total Cache', 'prefixes' => array( 'w3tc_' ) ),
			'redis-cache'    => array( 'label' => 'Redis Object Cache', 'prefixes' => array( 'redis_' ) ),
			'autoptimize'    => array( 'label' => 'Autoptimize', 'prefixes' => array( 'ao_' ) ),
			'wp-optimize'    => array( 'label' => 'WP-Optimize (page cache)', 'prefixes' => array( 'wpo_' ) ),
			'wp-super-cache' => array( 'label' => 'WP Super Cache', 'prefixes' => array( 'wpsc_' ) ),
			'wp-fastest-cache' => array( 'label' => 'WP Fastest Cache', 'prefixes' => array( 'wpfc_' ) ),
			'updraftplus'    => array( 'label' => 'UpdraftPlus', 'prefixes' => array( 'up_' ) ),
			'backwpup'       => array( 'label' => 'BackWPup', 'prefixes' => array( 'bwu_' ) ),
			'duplicator'     => array( 'label' => 'Duplicator', 'prefixes' => array( 'dup_' ) ),
			'wpvivid'        => array( 'label' => 'WPvivid', 'prefixes' => array( 'wpvivid_' ) ),
			'wordfence'      => array( 'label' => 'Wordfence', 'prefixes' => array( 'wf_' ) ),
			'defender'       => array( 'label' => 'WP Defender', 'prefixes' => array( 'def_' ) ),
			'solid-security' => array( 'label' => 'Solid Security', 'prefixes' => array( 'solid_' ) ),
			'sucuri'         => array( 'label' => 'Sucuri Security', 'prefixes' => array( 'sucuri_' ) ),
			'really-simple-security' => array( 'label' => 'Really Simple Security', 'prefixes' => array( 'rsssl_' ) ),
			'aios'           => array( 'label' => 'All-In-One Security (AIOS)', 'prefixes' => array( 'aios_' ) ),
			'llar'           => array( 'label' => 'Limit Login Attempts Reloaded', 'prefixes' => array( 'llar_' ) ),
			'akismet'        => array( 'label' => 'Akismet', 'prefixes' => array( 'akismet_' ) ),
			'imagify'        => array( 'label' => 'Imagify', 'prefixes' => array( 'imagify_' ) ),
			'ewww'           => array( 'label' => 'EWWW Image Optimizer', 'prefixes' => array( 'ewww_' ) ),
			'smush'          => array( 'label' => 'Smush', 'prefixes' => array( 'smush_' ) ),
			'shortpixel'     => array( 'label' => 'ShortPixel', 'prefixes' => array( 'shortpixel_' ) ),
			'instant-images' => array( 'label' => 'Instant Images (stock photo search)', 'prefixes' => array( 'stock_' ) ),
			'events-calendar' => array( 'label' => 'The Events Calendar', 'prefixes' => array( 'tec_' ) ),
			'sugar-calendar' => array( 'label' => 'Sugar Calendar', 'prefixes' => array( 'sugarcal_' ) ),
			'events-manager' => array( 'label' => 'Events Manager', 'prefixes' => array( 'em_' ) ),
			'modern-events-calendar' => array( 'label' => 'Modern Events Calendar', 'prefixes' => array( 'mec_' ) ),
			'translatepress' => array( 'label' => 'TranslatePress', 'prefixes' => array( 'trp_' ) ),
			'polylang'       => array( 'label' => 'Polylang', 'prefixes' => array( 'pll_' ) ),
			'wpml'           => array( 'label' => 'WPML', 'prefixes' => array( 'wpml_' ) ),
			'weglot'         => array( 'label' => 'Weglot', 'prefixes' => array( 'weglot_' ) ),
			'geodirectory'   => array( 'label' => 'GeoDirectory (directories)', 'prefixes' => array( 'geodir_' ) ),
			'business-directory' => array( 'label' => 'Business Directory Plugin', 'prefixes' => array( 'bdp_' ) ),
			'ivory-search'   => array( 'label' => 'Ivory Search', 'prefixes' => array( 'ivory_' ) ),
			'uncanny-automator' => array( 'label' => 'Uncanny Automator (automation)', 'prefixes' => array( 'uncanny_automator_' ) ),
			'blog2social'    => array( 'label' => 'Blog2Social (social publishing)', 'prefixes' => array( 'blog2social_' ) ),
			'estatik'        => array( 'label' => 'Estatik (real estate)', 'prefixes' => array( 'estatik_' ) ),
			'toolset-types'  => array( 'label' => 'Toolset Types (custom content models)', 'prefixes' => array( 'toolset_' ) ),
			'fluentcrm'      => array( 'label' => 'FluentCRM', 'prefixes' => array( 'crm_' ) ),
			'mailpoet'       => array( 'label' => 'MailPoet', 'prefixes' => array( 'mailpoet_' ) ),
			'groundhogg'     => array( 'label' => 'Groundhogg', 'prefixes' => array( 'groundhogg_' ) ),
			'givewp'         => array( 'label' => 'GiveWP', 'prefixes' => array( 'givewp_' ) ),
			'pmpro'          => array( 'label' => 'Paid Memberships Pro', 'prefixes' => array( 'pmpro_' ) ),
			'swpm'           => array( 'label' => 'Simple Membership', 'prefixes' => array( 'swpm_' ) ),
			'profilepress'   => array( 'label' => 'ProfilePress', 'prefixes' => array( 'ppress_' ) ),
			'restrictcontent' => array( 'label' => 'Restrict Content', 'prefixes' => array( 'rcp_' ) ),
			'ultimate-member' => array( 'label' => 'Ultimate Member', 'prefixes' => array( 'um_' ) ),
			'charitable'     => array( 'label' => 'Charitable', 'prefixes' => array( 'charitable_' ) ),
			'bbpress'        => array( 'label' => 'bbPress', 'prefixes' => array( 'bbp_' ) ),
			'buddypress'     => array( 'label' => 'BuddyPress', 'prefixes' => array( 'bp_' ) ),
			'fluentsupport'  => array( 'label' => 'Fluent Support (helpdesk)', 'prefixes' => array( 'fluentsupport_' ) ),
			'learnpress'     => array( 'label' => 'LearnPress', 'prefixes' => array( 'lms_' ) ),
			'sensei'         => array( 'label' => 'Sensei LMS', 'prefixes' => array( 'sensei_' ) ),
			'masteriyo'      => array( 'label' => 'Masteriyo LMS', 'prefixes' => array( 'mto_' ) ),
			'tutor'          => array( 'label' => 'Tutor LMS', 'prefixes' => array( 'tutor_' ) ),
			'lifterlms'      => array( 'label' => 'LifterLMS', 'prefixes' => array( 'llms_' ) ),
			'amelia'         => array( 'label' => 'Amelia (booking)', 'prefixes' => array( 'amelia_' ) ),
			'latepoint'      => array( 'label' => 'LatePoint (booking)', 'prefixes' => array( 'latepoint_' ) ),
			'bookly'         => array( 'label' => 'Bookly (booking)', 'prefixes' => array( 'bookly_' ) ),
			'bookingpress'   => array( 'label' => 'BookingPress (booking)', 'prefixes' => array( 'bookingpress_' ) ),
			'pods'           => array( 'label' => 'Pods (custom content models)', 'prefixes' => array( 'pods_' ) ),
			'cptui'          => array( 'label' => 'Custom Post Type UI', 'prefixes' => array( 'cptui_' ) ),
			'mbcpt'          => array( 'label' => 'MB Custom Post Types', 'prefixes' => array( 'mbcpt_' ) ),
			'edd'            => array( 'label' => 'Easy Digital Downloads', 'prefixes' => array( 'edd_' ) ),
			'fluentcart'     => array( 'label' => 'FluentCart', 'prefixes' => array( 'fluentcart_' ) ),
			'ai1wm'          => array( 'label' => 'All-in-One WP Migration (backups)', 'prefixes' => array( 'ai1wm_' ) ),
			'patchstack'     => array( 'label' => 'Patchstack (security)', 'prefixes' => array( 'patchstack_' ) ),
			'relevanssi'     => array( 'label' => 'Relevanssi (search)', 'prefixes' => array( 'relevanssi_' ) ),
			'slicewp'        => array( 'label' => 'SliceWP (affiliate)', 'prefixes' => array( 'slicewp_' ) ),
			'automatorwp'    => array( 'label' => 'AutomatorWP (automation)', 'prefixes' => array( 'automatorwp_' ) ),
			'wpjm'           => array( 'label' => 'WP Job Manager (job board)', 'prefixes' => array( 'wpjm_' ) ),
			'wp-go-maps'     => array( 'label' => 'WP Go Maps (mapping)', 'prefixes' => array( 'wpgm_' ) ),
			'jetpack'        => array( 'label' => 'Jetpack (connection, modules, Stats, VaultPress Backup, Monitor, Newsletter, Sitemaps, Shortlinks)', 'prefixes' => array( 'jetpack_' ) ),
		);
	}

	public static function slugs(): array {
		return array_keys( self::catalog() );
	}

	public static function classes(): array {
		return array(
			'woocommerce'    => \Mordenhost_MCP\Integrations\WooCommerce::class,
			'litespeed'      => \Mordenhost_MCP\Integrations\LiteSpeed::class,
			'elementor'      => \Mordenhost_MCP\Integrations\Elementor::class,
			'divi'           => \Mordenhost_MCP\Integrations\Divi::class,
			'beaver-builder' => \Mordenhost_MCP\Integrations\BeaverBuilder::class,
			'siteorigin-panels' => \Mordenhost_MCP\Integrations\SiteOriginPanels::class,
			'acf'            => \Mordenhost_MCP\Integrations\Advanced_Custom_Fields::class,
			'metabox'        => \Mordenhost_MCP\Integrations\MetaBox::class,
			'redirection'    => \Mordenhost_MCP\Integrations\Redirection::class,
			'rank_math'      => \Mordenhost_MCP\Integrations\RankMath::class,
			'analytics'      => \Mordenhost_MCP\Integrations\Analytics::class,
			'email'          => \Mordenhost_MCP\Integrations\Email::class,
			'forms'          => \Mordenhost_MCP\Integrations\Forms::class,
			'wp-rocket'      => \Mordenhost_MCP\Integrations\WPRocket::class,
			'w3tc'           => \Mordenhost_MCP\Integrations\W3TotalCache::class,
			'redis-cache'    => \Mordenhost_MCP\Integrations\RedisObjectCache::class,
			'autoptimize'    => \Mordenhost_MCP\Integrations\Autoptimize::class,
			'wp-optimize'    => \Mordenhost_MCP\Integrations\WPOptimize::class,
			'wp-super-cache' => \Mordenhost_MCP\Integrations\WPSuperCache::class,
			'wp-fastest-cache' => \Mordenhost_MCP\Integrations\WPFastestCache::class,
			'updraftplus'    => \Mordenhost_MCP\Integrations\UpdraftPlus::class,
			'backwpup'       => \Mordenhost_MCP\Integrations\BackWPup::class,
			'duplicator'     => \Mordenhost_MCP\Integrations\Duplicator::class,
			'wpvivid'        => \Mordenhost_MCP\Integrations\WPvivid::class,
			'wordfence'      => \Mordenhost_MCP\Integrations\Wordfence::class,
			'defender'       => \Mordenhost_MCP\Integrations\Defender::class,
			'solid-security' => \Mordenhost_MCP\Integrations\SolidSecurity::class,
			'sucuri'         => \Mordenhost_MCP\Integrations\Sucuri::class,
			'really-simple-security' => \Mordenhost_MCP\Integrations\ReallySimpleSecurity::class,
			'aios'           => \Mordenhost_MCP\Integrations\AIOS::class,
			'llar'           => \Mordenhost_MCP\Integrations\LimitLoginAttemptsReloaded::class,
			'akismet'        => \Mordenhost_MCP\Integrations\Akismet::class,
			'imagify'        => \Mordenhost_MCP\Integrations\Imagify::class,
			'ewww'           => \Mordenhost_MCP\Integrations\EWWW::class,
			'smush'          => \Mordenhost_MCP\Integrations\Smush::class,
			'shortpixel'     => \Mordenhost_MCP\Integrations\ShortPixel::class,
			'instant-images' => \Mordenhost_MCP\Integrations\InstantImages::class,
			'events-calendar' => \Mordenhost_MCP\Integrations\EventsCalendar::class,
			'sugar-calendar' => \Mordenhost_MCP\Integrations\SugarCalendar::class,
			'events-manager' => \Mordenhost_MCP\Integrations\EventsManager::class,
			'modern-events-calendar' => \Mordenhost_MCP\Integrations\ModernEventsCalendar::class,
			'translatepress' => \Mordenhost_MCP\Integrations\TranslatePress::class,
			'polylang'       => \Mordenhost_MCP\Integrations\Polylang::class,
			'wpml'           => \Mordenhost_MCP\Integrations\WPML::class,
			'weglot'         => \Mordenhost_MCP\Integrations\Weglot::class,
			'geodirectory'   => \Mordenhost_MCP\Integrations\GeoDirectory::class,
			'business-directory' => \Mordenhost_MCP\Integrations\BusinessDirectory::class,
			'ivory-search'   => \Mordenhost_MCP\Integrations\IvorySearch::class,
			'uncanny-automator' => \Mordenhost_MCP\Integrations\UncannyAutomator::class,
			'blog2social'    => \Mordenhost_MCP\Integrations\Blog2Social::class,
			'estatik'        => \Mordenhost_MCP\Integrations\Estatik::class,
			'toolset-types'  => \Mordenhost_MCP\Integrations\ToolsetTypes::class,
			'fluentcrm'      => \Mordenhost_MCP\Integrations\FluentCRM::class,
			'mailpoet'       => \Mordenhost_MCP\Integrations\MailPoet::class,
			'groundhogg'     => \Mordenhost_MCP\Integrations\Groundhogg::class,
			'givewp'         => \Mordenhost_MCP\Integrations\GiveWP::class,
			'pmpro'          => \Mordenhost_MCP\Integrations\PaidMembershipsPro::class,
			'swpm'           => \Mordenhost_MCP\Integrations\SimpleMembership::class,
			'profilepress'   => \Mordenhost_MCP\Integrations\ProfilePress::class,
			'restrictcontent' => \Mordenhost_MCP\Integrations\RestrictContent::class,
			'ultimate-member' => \Mordenhost_MCP\Integrations\UltimateMember::class,
			'charitable'     => \Mordenhost_MCP\Integrations\Charitable::class,
			'bbpress'        => \Mordenhost_MCP\Integrations\BBPress::class,
			'buddypress'     => \Mordenhost_MCP\Integrations\BuddyPress::class,
			'fluentsupport'  => \Mordenhost_MCP\Integrations\FluentSupport::class,
			'learnpress'     => \Mordenhost_MCP\Integrations\LearnPress::class,
			'sensei'         => \Mordenhost_MCP\Integrations\Sensei::class,
			'masteriyo'      => \Mordenhost_MCP\Integrations\Masteriyo::class,
			'tutor'          => \Mordenhost_MCP\Integrations\Tutor::class,
			'lifterlms'      => \Mordenhost_MCP\Integrations\LifterLMS::class,
			'amelia'         => \Mordenhost_MCP\Integrations\Amelia::class,
			'latepoint'      => \Mordenhost_MCP\Integrations\LatePoint::class,
			'bookly'         => \Mordenhost_MCP\Integrations\Bookly::class,
			'bookingpress'   => \Mordenhost_MCP\Integrations\BookingPress::class,
			'pods'           => \Mordenhost_MCP\Integrations\Pods::class,
			'cptui'          => \Mordenhost_MCP\Integrations\CptUi::class,
			'mbcpt'          => \Mordenhost_MCP\Integrations\MbCpt::class,
			'edd'            => \Mordenhost_MCP\Integrations\EDD::class,
			'fluentcart'     => \Mordenhost_MCP\Integrations\FluentCart::class,
			'ai1wm'          => \Mordenhost_MCP\Integrations\Ai1wm::class,
			'patchstack'     => \Mordenhost_MCP\Integrations\Patchstack::class,
			'relevanssi'     => \Mordenhost_MCP\Integrations\Relevanssi::class,
			'slicewp'        => \Mordenhost_MCP\Integrations\SliceWP::class,
			'automatorwp'    => \Mordenhost_MCP\Integrations\AutomatorWP::class,
			'wpjm'           => \Mordenhost_MCP\Integrations\WPJobManager::class,
			'wp-go-maps'     => \Mordenhost_MCP\Integrations\WpGoMaps::class,
			'jetpack'        => \Mordenhost_MCP\Integrations\Jetpack::class,
		);
	}

	public static function availability(): array {
		$out = array();
		foreach ( self::classes() as $slug => $class ) {

			
			try {
				$out[ $slug ] = class_exists( $class )
					&& method_exists( $class, 'is_available' )
					&& $class::is_available();
			} catch ( \Throwable $e ) {
				$out[ $slug ] = false;
			}
		}
		return $out;
	}

	public static function enabled_slugs(): array {
		$settings = get_option( 'mordenhost_mcp_settings', array() );
		$stored   = isset( $settings[ self::OPTION_KEY ] ) && is_array( $settings[ self::OPTION_KEY ] )
			? $settings[ self::OPTION_KEY ]
			: array();
		$catalog  = self::catalog();
		$out      = array();
		foreach ( $stored as $slug ) {
			$slug = is_string( $slug ) ? $slug : '';
			if ( '' !== $slug && isset( $catalog[ $slug ] ) ) {
				$out[] = $slug;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function is_enabled( string $slug ): bool {
		return in_array( $slug, self::enabled_slugs(), true );
	}

	public static function is_class_enabled( string $class ): bool {
		$slug = array_search( $class, self::classes(), true );
		if ( false === $slug ) {
			return true;
		}
		return self::is_enabled( (string) $slug );
	}

	public static function slug_for_tool( string $tool_name ): string {
		foreach ( self::catalog() as $slug => $meta ) {
			foreach ( $meta['prefixes'] as $prefix ) {
				if ( 0 === strpos( $tool_name, $prefix ) ) {
					return $slug;
				}
			}
		}
		return '';
	}

	public static function tool_is_allowed( string $tool_name ): bool {
		$slug = self::slug_for_tool( $tool_name );
		if ( '' === $slug ) {
			return true;
		}
		return self::is_enabled( $slug );
	}

	public static function disabled_message( string $slug ): string {
		$catalog = self::catalog();
		$label   = isset( $catalog[ $slug ] ) ? $catalog[ $slug ]['label'] : $slug;
		return sprintf(
			'The %1$s integration (%2$s) is turned off for MCP on this site, so its tools cannot be used. A site administrator turns it on in WordPress admin under %3$s: switch on the %1$s card (the card shows once that plugin is active). Integrations cannot be enabled over MCP. Once it is on, list the tools again.',
			$label,
			$slug,
			self::ENABLE_LOCATION
		);
	}

	public static function client_report(): array {
		$availability = self::availability();
		$enabled      = array_flip( self::enabled_slugs() );

		$active   = array();
		$disabled = array();
		$missing  = array();
		foreach ( self::catalog() as $slug => $meta ) {
			$detected = ! empty( $availability[ $slug ] );
			$on       = isset( $enabled[ $slug ] );
			if ( $detected && $on ) {
				$active[] = $slug;
			} elseif ( $detected ) {
				$disabled[] = array(
					'slug'          => $slug,
					'label'         => $meta['label'],
					'tool_prefixes' => $meta['prefixes'],
				);
			} elseif ( $on ) {
				$missing[] = $slug;
			}
		}

		return array(
			'active'                   => $active,
			'detected_but_disabled'    => $disabled,
			'enabled_but_not_detected' => $missing,
			'how_to_enable'            => 'A site administrator turns an integration on in WordPress admin under ' . self::ENABLE_LOCATION . ' by switching on the plugin card there. Integrations cannot be enabled over MCP. Its tools appear the next time the client lists tools.',
			'settings_url'             => admin_url( self::ENABLE_ADMIN_PATH ),
		);
	}
}
