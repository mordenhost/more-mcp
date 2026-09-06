<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPML {

	public static function is_available() {
		return defined( 'ICL_SITEPRESS_VERSION' ) && class_exists( 'SitePress' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'wpml' ),
			'capabilities' => array( 'multilingual' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wpml_get_languages',
				'description' => 'List WPML\'s active languages: code, native name, English/translated name, locale, and which one is the default. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'wpml_get_object_language',
				'description' => 'Resolve the WPML language code assigned to a specific post or taxonomy term. element_type follows WPML\'s own convention: "post_{post_type}" (e.g. post_post, post_page, post_product) or "tax_{taxonomy}" (e.g. tax_category, tax_post_tag). For a taxonomy element, element_id must be the term_taxonomy_id, not the term_id.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'element_id'   => [ 'type' => 'integer', 'description' => 'Post ID, or term_taxonomy_id for a taxonomy element (not term_id).' ],
						'element_type' => [ 'type' => 'string', 'description' => 'e.g. post_post, post_page, post_product, tax_category, tax_post_tag.' ],
					],
					'required'   => [ 'element_id', 'element_type' ],
				],
			],
			[
				'name'        => 'wpml_get_translations',
				'description' => 'List a post\'s or taxonomy term\'s translation siblings: language code, translated element ID, and publish status. Uses the same element_type convention as wpml_get_object_language. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'element_id'   => [ 'type' => 'integer', 'description' => 'Post ID, or term_taxonomy_id for a taxonomy element (not term_id).' ],
						'element_type' => [ 'type' => 'string', 'description' => 'e.g. post_post, post_page, post_product, tax_category, tax_post_tag.' ],
					],
					'required'   => [ 'element_id', 'element_type' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use multilingual tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'WPML is not active.' );
		}

		switch ( $name ) {
			case 'wpml_get_languages':
				return self::get_languages();
			case 'wpml_get_object_language':
				return self::get_object_language( $args );
			case 'wpml_get_translations':
				return self::get_translations( $args );
			default:
				throw new \Exception( 'Unknown WPML tool: ' . esc_html( $name ) );
		}
	}

	private static function get_languages() {
		$default = apply_filters( 'wpml_default_language', null );
		$active  = apply_filters( 'wpml_active_languages', null, [] );
		$active  = is_array( $active ) ? $active : [];

		$out = [];
		foreach ( $active as $code => $lang ) {
			$out[] = [
				'code'          => sanitize_text_field( (string) ( $lang['language_code'] ?? $code ) ),
				'native_name'   => sanitize_text_field( (string) ( $lang['native_name'] ?? '' ) ),
				'name'          => sanitize_text_field( (string) ( $lang['translated_name'] ?? '' ) ),
				'locale'        => sanitize_text_field( (string) ( $lang['default_locale'] ?? '' ) ),
				'is_default'    => ( $lang['language_code'] ?? $code ) === $default,
			];
		}

		return [
			'provider'  => 'wpml',
			'default'   => $default ?: null,
			'languages' => $out,
		];
	}

	private static function get_object_language( $args ) {
		[ $element_id, $element_type ] = self::resolve_element_args( $args );

		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $element_id, 'element_type' => $element_type ]
		);

		return [
			'provider'      => 'wpml',
			'element_id'    => $element_id,
			'element_type'  => $element_type,
			'has_language'  => ! empty( $code ),
			'language_code' => $code ? sanitize_text_field( (string) $code ) : null,
		];
	}

	private static function get_translations( $args ) {
		[ $element_id, $element_type ] = self::resolve_element_args( $args );

		$trid = apply_filters( 'wpml_element_trid', null, $element_id, $element_type );
		if ( empty( $trid ) ) {
			return [
				'provider'     => 'wpml',
				'element_id'   => $element_id,
				'element_type' => $element_type,
				'count'        => 0,
				'translations' => [],
			];
		}

		$translations = apply_filters( 'wpml_get_element_translations', null, $trid, $element_type );
		$translations = is_array( $translations ) ? $translations : [];

		$out = [];
		foreach ( $translations as $code => $entry ) {
			$entry = (array) $entry;
			$out[] = [
				'language' => sanitize_text_field( (string) ( $entry['language_code'] ?? $code ) ),
				'id'       => isset( $entry['element_id'] ) ? (int) $entry['element_id'] : null,
				'status'   => isset( $entry['post_status'] ) ? sanitize_text_field( (string) $entry['post_status'] ) : null,
			];
		}

		return [
			'provider'     => 'wpml',
			'element_id'   => $element_id,
			'element_type' => $element_type,
			'count'        => count( $out ),
			'translations' => $out,
		];
	}

	private static function resolve_element_args( $args ) {
		$element_id   = absint( $args['element_id'] ?? 0 );
		$element_type = is_string( $args['element_type'] ?? null ) ? $args['element_type'] : '';

		if ( $element_id <= 0 ) {
			throw new \Exception( 'element_id is required.' );
		}
		if ( ! preg_match( '/^(post|tax)_[a-z0-9_]+$/', $element_type ) ) {
			throw new \Exception( 'element_type must look like "post_{post_type}" or "tax_{taxonomy}" (e.g. post_post, post_page, tax_category).' );
		}

		return [ $element_id, $element_type ];
	}
}
