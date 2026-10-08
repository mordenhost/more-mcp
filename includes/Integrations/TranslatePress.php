<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TranslatePress {

	public static function is_available() {
		return class_exists( '\TRP_Translate_Press' ) || defined( 'TRP_PLUGIN_VERSION' ) || false !== get_option( 'trp_settings', false );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'translatepress' ),
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
				'name'        => 'trp_get_languages',
				'description' => 'Read TranslatePress multilingual configuration: the default language, the full set of translation languages, which of those are published (live), each language\'s URL slug, and whether the default language uses a subdirectory. Read-only; translation content and the language set are edited in the TranslatePress editor, not here.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'trp_get_machine_translation_status',
				'description' => 'Read TranslatePress automatic (machine) translation status: whether it is enabled, which translation engine is selected, whether a character limit is set and what it is, and the character count already used on the counter\'s current date. Configuration and counters only — never an API key, never translated content. Read-only; engine selection is made in TranslatePress\'s own settings.',
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
			throw new \Exception( 'TranslatePress is not active.' );
		}
		if ( 'trp_get_machine_translation_status' === $name ) {
			return self::get_machine_translation_status();
		}
		if ( 'trp_get_languages' !== $name ) {
			throw new \Exception( 'Unknown multilingual tool: ' . esc_html( $name ) );
		}

		$settings = get_option( 'trp_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$all       = self::string_list( $settings['translation-languages'] ?? array() );
		$published = self::string_list( $settings['publish-languages'] ?? array() );
		$default   = isset( $settings['default-language'] ) ? (string) $settings['default-language'] : '';

		$slugs = array();
		if ( isset( $settings['url-slugs'] ) && is_array( $settings['url-slugs'] ) ) {
			foreach ( $settings['url-slugs'] as $code => $slug ) {
				$slugs[ (string) $code ] = (string) $slug;
			}
		}

		
		$languages = array();
		foreach ( $all as $code ) {
			$languages[] = array(
				'code'      => $code,
				'slug'      => $slugs[ $code ] ?? null,
				'default'   => $code === $default,
				'published' => in_array( $code, $published, true ),
			);
		}

		return array(
			'provider'                => 'translatepress',
			'default_language'        => '' === $default ? null : $default,
			'languages'               => $languages,
			'default_in_subdirectory' => isset( $settings['add-subdirectory-to-default-language'] )
				? ( 'yes' === $settings['add-subdirectory-to-default-language'] )
				: null,
		);
	}

	private static function get_machine_translation_status() {
		$settings = get_option( 'trp_machine_translation_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$counter_date = isset( $settings['machine_translation_counter_date'] )
			? (string) $settings['machine_translation_counter_date']
			: null;
		$counter = null;
		if ( null !== $counter_date ) {

			$raw    = get_option( 'trp_machine_translation_counter', null );
			$counter = is_numeric( $raw ) ? (int) $raw : null;
		}

		return array(
			'provider'      => 'translatepress',
			'enabled'       => 'yes' === ( isset( $settings['machine-translation'] ) ? (string) $settings['machine-translation'] : '' ),
			'engine'        => isset( $settings['translation-engine'] ) ? (string) $settings['translation-engine'] : null,
			'limit_enabled' => 'yes' === ( isset( $settings['machine_translation_limit_enabled'] ) ? (string) $settings['machine_translation_limit_enabled'] : '' ),
			'limit'         => isset( $settings['machine_translation_limit'] ) && is_numeric( $settings['machine_translation_limit'] )
				? (int) $settings['machine_translation_limit']
				: null,
			'characters_used' => $counter,
			'counter_date'    => $counter_date,
		);
	}

	private static function string_list( $value ) {
		if ( ! is_array( $value ) ) {
			return '' === (string) $value ? array() : array( (string) $value );
		}
		$out = array();
		foreach ( $value as $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$out[] = (string) $v;
			}
		}
		return $out;
	}
}
