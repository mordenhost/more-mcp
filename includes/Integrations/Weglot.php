<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Weglot {

	public static function is_available() {

		
		return function_exists( 'weglot_get_original_language' ) && function_exists( 'weglot_get_destination_languages' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'weglot' ),
			'capabilities' => array( 'multilingual' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'weglot_get_languages',
				'description' => 'Read Weglot multilingual configuration: the original (source) language, the configured destination languages with each one\'s language code, custom code/slug, and whether it is public, whether Weglot is connected (an API key is configured), whether language auto-redirect is on, and the number of URL exclusion rules. Weglot translates on the fly through its cloud service rather than storing one WordPress post per language, so it offers site-wide configuration only — no per-object language resolution or translation-sibling lookup. The API key and the exclusion patterns themselves are never returned. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use multilingual tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Weglot is not active.' );
		}
		if ( 'weglot_get_languages' !== $name ) {
			throw new \Exception( 'Unknown multilingual tool: ' . esc_html( $name ) );
		}

		$original = self::safe_call( 'weglot_get_original_language' );
		$original = is_string( $original ) ? $original : '';

		$destinations = self::safe_call( 'weglot_get_destination_languages' );
		$languages    = array();
		if ( is_array( $destinations ) ) {
			foreach ( $destinations as $lang ) {
				if ( is_string( $lang ) ) {
					
					$languages[] = array( 'code' => $lang, 'custom_code' => null, 'public' => null );
					continue;
				}
				if ( ! is_array( $lang ) ) {
					continue;
				}
				$code = isset( $lang['language_to'] ) ? (string) $lang['language_to'] : ( isset( $lang['code'] ) ? (string) $lang['code'] : '' );
				if ( '' === $code ) {
					continue;
				}

				$public = null;
				if ( array_key_exists( 'public', $lang ) ) {
					$public = (bool) $lang['public'];
				} elseif ( array_key_exists( 'enabled', $lang ) ) {
					$public = (bool) $lang['enabled'];
				}
				$languages[] = array(
					'code'        => $code,

					'custom_code' => isset( $lang['custom_code'] ) && is_scalar( $lang['custom_code'] ) ? (string) $lang['custom_code'] : null,
					'public'      => $public,
				);
			}
		}

		return array(
			'provider'          => 'weglot',
			'original_language' => '' === $original ? null : $original,
			'languages'         => $languages,
			
			'connected'         => self::has_api_key(),

			'auto_redirect'     => self::safe_call( 'weglot_has_auto_redirect' ),

			'excluded_url_count' => self::exclude_url_count(),
		);
	}

	private static function exclude_url_count() {
		$excluded = self::safe_call( 'weglot_get_exclude_urls' );
		if ( ! is_array( $excluded ) ) {
			return null;
		}
		return count( $excluded );
	}

	private static function has_api_key() {
		if ( ! function_exists( 'weglot_get_api_key' ) ) {
			return null;
		}
		$key = self::safe_call( 'weglot_get_api_key' );
		return is_string( $key ) && '' !== trim( $key );
	}

	private static function safe_call( $fn ) {
		if ( ! function_exists( $fn ) ) {
			return null;
		}
		try {
			return $fn();
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
