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
			'acf'            => array( 'label' => 'Advanced Custom Fields', 'prefixes' => array( 'acf_' ) ),
			'metabox'        => array( 'label' => 'Meta Box', 'prefixes' => array( 'mb_' ) ),
			'redirection'    => array( 'label' => 'Redirection', 'prefixes' => array( 'redirection_' ) ),
			'analytics'      => array( 'label' => 'Analytics (Site Kit, Jetpack, MonsterInsights)', 'prefixes' => array( 'analytics_' ) ),
			'email'          => array( 'label' => 'Email / SMTP (WP Mail SMTP, Easy WP SMTP)', 'prefixes' => array( 'email_' ) ),
			'forms'          => array( 'label' => 'Forms (Gravity, Fluent, CF7, WPForms, Ninja)', 'prefixes' => array( 'forms_' ) ),
			'wp-rocket'      => array( 'label' => 'WP Rocket', 'prefixes' => array( 'wpr_' ) ),
			'updraftplus'    => array( 'label' => 'UpdraftPlus', 'prefixes' => array( 'up_' ) ),
			'backwpup'       => array( 'label' => 'BackWPup', 'prefixes' => array( 'bwu_' ) ),
			'wordfence'      => array( 'label' => 'Wordfence', 'prefixes' => array( 'wf_' ) ),
			'defender'       => array( 'label' => 'WP Defender', 'prefixes' => array( 'def_' ) ),
			'akismet'        => array( 'label' => 'Akismet', 'prefixes' => array( 'akismet_' ) ),
			'imagify'        => array( 'label' => 'Imagify', 'prefixes' => array( 'imagify_' ) ),
			'translatepress' => array( 'label' => 'TranslatePress', 'prefixes' => array( 'trp_' ) ),
			'fluentcrm'      => array( 'label' => 'FluentCRM', 'prefixes' => array( 'crm_' ) ),
			'learnpress'     => array( 'label' => 'LearnPress', 'prefixes' => array( 'lms_' ) ),
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
			'acf'            => \More_MCP\Integrations\ACF::class,
			'metabox'        => \More_MCP\Integrations\MetaBox::class,
			'redirection'    => \More_MCP\Integrations\Redirection::class,
			'analytics'      => \More_MCP\Integrations\Analytics::class,
			'email'          => \More_MCP\Integrations\Email::class,
			'forms'          => \More_MCP\Integrations\Forms::class,
			'wp-rocket'      => \More_MCP\Integrations\WPRocket::class,
			'updraftplus'    => \More_MCP\Integrations\UpdraftPlus::class,
			'backwpup'       => \More_MCP\Integrations\BackWPup::class,
			'wordfence'      => \More_MCP\Integrations\Wordfence::class,
			'defender'       => \More_MCP\Integrations\Defender::class,
			'akismet'        => \More_MCP\Integrations\Akismet::class,
			'imagify'        => \More_MCP\Integrations\Imagify::class,
			'translatepress' => \More_MCP\Integrations\TranslatePress::class,
			'fluentcrm'      => \More_MCP\Integrations\FluentCRM::class,
			'learnpress'     => \More_MCP\Integrations\LearnPress::class,
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
