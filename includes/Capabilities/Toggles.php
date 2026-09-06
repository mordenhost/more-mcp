<?php
namespace More_MCP\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Toggles {

	const OPTION_KEY = 'enabled_integrations';

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
			'analytics'      => array( 'label' => 'Analytics (Site Kit, Jetpack, MonsterInsights, Burst, Matomo, Independent Analytics)', 'prefixes' => array( 'analytics_' ) ),
			'email'          => array( 'label' => 'Email / SMTP (WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP)', 'prefixes' => array( 'email_' ) ),
			'forms'          => array( 'label' => 'Forms (Gravity, Fluent, CF7, WPForms, Ninja, Formidable)', 'prefixes' => array( 'forms_' ) ),
			'wp-rocket'      => array( 'label' => 'WP Rocket', 'prefixes' => array( 'wpr_' ) ),
			'w3tc'           => array( 'label' => 'W3 Total Cache', 'prefixes' => array( 'w3tc_' ) ),
			'redis-cache'    => array( 'label' => 'Redis Object Cache', 'prefixes' => array( 'redis_' ) ),
			'autoptimize'    => array( 'label' => 'Autoptimize', 'prefixes' => array( 'ao_' ) ),
			'wp-optimize'    => array( 'label' => 'WP-Optimize (page cache)', 'prefixes' => array( 'wpo_' ) ),
			'updraftplus'    => array( 'label' => 'UpdraftPlus', 'prefixes' => array( 'up_' ) ),
			'backwpup'       => array( 'label' => 'BackWPup', 'prefixes' => array( 'bwu_' ) ),
			'duplicator'     => array( 'label' => 'Duplicator', 'prefixes' => array( 'dup_' ) ),
			'wpvivid'        => array( 'label' => 'WPvivid', 'prefixes' => array( 'wpvivid_' ) ),
			'wordfence'      => array( 'label' => 'Wordfence', 'prefixes' => array( 'wf_' ) ),
			'defender'       => array( 'label' => 'WP Defender', 'prefixes' => array( 'def_' ) ),
			'solid-security' => array( 'label' => 'Solid Security', 'prefixes' => array( 'solid_' ) ),
			'sucuri'         => array( 'label' => 'Sucuri Security', 'prefixes' => array( 'sucuri_' ) ),
			'really-simple-security' => array( 'label' => 'Really Simple Security', 'prefixes' => array( 'rsssl_' ) ),
			'akismet'        => array( 'label' => 'Akismet', 'prefixes' => array( 'akismet_' ) ),
			'imagify'        => array( 'label' => 'Imagify', 'prefixes' => array( 'imagify_' ) ),
			'ewww'           => array( 'label' => 'EWWW Image Optimizer', 'prefixes' => array( 'ewww_' ) ),
			'smush'          => array( 'label' => 'Smush', 'prefixes' => array( 'smush_' ) ),
			'shortpixel'     => array( 'label' => 'ShortPixel', 'prefixes' => array( 'shortpixel_' ) ),
			'instant-images' => array( 'label' => 'Instant Images (stock photo search)', 'prefixes' => array( 'stock_' ) ),
			'events-calendar' => array( 'label' => 'The Events Calendar', 'prefixes' => array( 'tec_' ) ),
			'sugar-calendar' => array( 'label' => 'Sugar Calendar', 'prefixes' => array( 'sugarcal_' ) ),
			'events-manager' => array( 'label' => 'Events Manager', 'prefixes' => array( 'em_' ) ),
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
		);
	}

	public static function slugs(): array {
		return array_keys( self::catalog() );
	}

	public static function classes(): array {
		return array(
			'woocommerce'    => \More_MCP\Integrations\WooCommerce::class,
			'litespeed'      => \More_MCP\Integrations\LiteSpeed::class,
			'elementor'      => \More_MCP\Integrations\Elementor::class,
			'divi'           => \More_MCP\Integrations\Divi::class,
			'beaver-builder' => \More_MCP\Integrations\BeaverBuilder::class,
			'siteorigin-panels' => \More_MCP\Integrations\SiteOriginPanels::class,
			'acf'            => \More_MCP\Integrations\ACF::class,
			'metabox'        => \More_MCP\Integrations\MetaBox::class,
			'redirection'    => \More_MCP\Integrations\Redirection::class,
			'analytics'      => \More_MCP\Integrations\Analytics::class,
			'email'          => \More_MCP\Integrations\Email::class,
			'forms'          => \More_MCP\Integrations\Forms::class,
			'wp-rocket'      => \More_MCP\Integrations\WPRocket::class,
			'w3tc'           => \More_MCP\Integrations\W3TotalCache::class,
			'redis-cache'    => \More_MCP\Integrations\RedisObjectCache::class,
			'autoptimize'    => \More_MCP\Integrations\Autoptimize::class,
			'wp-optimize'    => \More_MCP\Integrations\WPOptimize::class,
			'updraftplus'    => \More_MCP\Integrations\UpdraftPlus::class,
			'backwpup'       => \More_MCP\Integrations\BackWPup::class,
			'duplicator'     => \More_MCP\Integrations\Duplicator::class,
			'wpvivid'        => \More_MCP\Integrations\WPvivid::class,
			'wordfence'      => \More_MCP\Integrations\Wordfence::class,
			'defender'       => \More_MCP\Integrations\Defender::class,
			'solid-security' => \More_MCP\Integrations\SolidSecurity::class,
			'sucuri'         => \More_MCP\Integrations\Sucuri::class,
			'really-simple-security' => \More_MCP\Integrations\ReallySimpleSecurity::class,
			'akismet'        => \More_MCP\Integrations\Akismet::class,
			'imagify'        => \More_MCP\Integrations\Imagify::class,
			'ewww'           => \More_MCP\Integrations\EWWW::class,
			'smush'          => \More_MCP\Integrations\Smush::class,
			'shortpixel'     => \More_MCP\Integrations\ShortPixel::class,
			'instant-images' => \More_MCP\Integrations\InstantImages::class,
			'events-calendar' => \More_MCP\Integrations\EventsCalendar::class,
			'sugar-calendar' => \More_MCP\Integrations\SugarCalendar::class,
			'events-manager' => \More_MCP\Integrations\EventsManager::class,
			'translatepress' => \More_MCP\Integrations\TranslatePress::class,
			'polylang'       => \More_MCP\Integrations\Polylang::class,
			'wpml'           => \More_MCP\Integrations\WPML::class,
			'weglot'         => \More_MCP\Integrations\Weglot::class,
			'geodirectory'   => \More_MCP\Integrations\GeoDirectory::class,
			'business-directory' => \More_MCP\Integrations\BusinessDirectory::class,
			'ivory-search'   => \More_MCP\Integrations\IvorySearch::class,
			'uncanny-automator' => \More_MCP\Integrations\UncannyAutomator::class,
			'blog2social'    => \More_MCP\Integrations\Blog2Social::class,
			'estatik'        => \More_MCP\Integrations\Estatik::class,
			'toolset-types'  => \More_MCP\Integrations\ToolsetTypes::class,
			'fluentcrm'      => \More_MCP\Integrations\FluentCRM::class,
			'mailpoet'       => \More_MCP\Integrations\MailPoet::class,
			'groundhogg'     => \More_MCP\Integrations\Groundhogg::class,
			'givewp'         => \More_MCP\Integrations\GiveWP::class,
			'pmpro'          => \More_MCP\Integrations\PaidMembershipsPro::class,
			'swpm'           => \More_MCP\Integrations\SimpleMembership::class,
			'profilepress'   => \More_MCP\Integrations\ProfilePress::class,
			'restrictcontent' => \More_MCP\Integrations\RestrictContent::class,
			'charitable'     => \More_MCP\Integrations\Charitable::class,
			'bbpress'        => \More_MCP\Integrations\BBPress::class,
			'buddypress'     => \More_MCP\Integrations\BuddyPress::class,
			'fluentsupport'  => \More_MCP\Integrations\FluentSupport::class,
			'learnpress'     => \More_MCP\Integrations\LearnPress::class,
			'sensei'         => \More_MCP\Integrations\Sensei::class,
			'masteriyo'      => \More_MCP\Integrations\Masteriyo::class,
			'tutor'          => \More_MCP\Integrations\Tutor::class,
			'lifterlms'      => \More_MCP\Integrations\LifterLMS::class,
			'amelia'         => \More_MCP\Integrations\Amelia::class,
			'latepoint'      => \More_MCP\Integrations\LatePoint::class,
			'bookly'         => \More_MCP\Integrations\Bookly::class,
			'bookingpress'   => \More_MCP\Integrations\BookingPress::class,
			'pods'           => \More_MCP\Integrations\Pods::class,
			'cptui'          => \More_MCP\Integrations\CptUi::class,
			'mbcpt'          => \More_MCP\Integrations\MbCpt::class,
			'edd'            => \More_MCP\Integrations\EDD::class,
			'fluentcart'     => \More_MCP\Integrations\FluentCart::class,
			'ai1wm'          => \More_MCP\Integrations\Ai1wm::class,
			'patchstack'     => \More_MCP\Integrations\Patchstack::class,
			'relevanssi'     => \More_MCP\Integrations\Relevanssi::class,
			'slicewp'        => \More_MCP\Integrations\SliceWP::class,
			'automatorwp'    => \More_MCP\Integrations\AutomatorWP::class,
			'wpjm'           => \More_MCP\Integrations\WPJobManager::class,
			'wp-go-maps'     => \More_MCP\Integrations\WpGoMaps::class,
		);
	}

	public static function availability(): array {
		$out = array();
		foreach ( self::classes() as $slug => $class ) {
			$out[ $slug ] = class_exists( $class )
				&& method_exists( $class, 'is_available' )
				&& $class::is_available();
		}
		return $out;
	}

	public static function enabled_slugs(): array {
		$settings = get_option( 'more_mcp_settings', array() );
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
}
