<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Polylang {

	public static function is_available() {
		return defined( 'POLYLANG_VERSION' ) && function_exists( 'pll_languages_list' ) && function_exists( 'pll_get_post_language' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'polylang' ),
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
				'name'        => 'pll_get_languages',
				'description' => 'List Polylang\'s configured languages: code (slug), display name, locale, right-to-left flag, flag code, and which one is the default. Read-only.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			],
			[
				'name'        => 'pll_get_object_language',
				'description' => 'Resolve the Polylang language assigned to a specific post or term: code, name, locale. Returns has_language=false if the object has no language assigned yet.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'enum' => [ 'post', 'term' ], 'description' => 'Whether object_id is a post ID or a term ID.' ],
						'object_id'   => [ 'type' => 'integer', 'description' => 'Post ID or term ID, per object_type.' ],
					],
					'required'   => [ 'object_type', 'object_id' ],
				],
			],
			[
				'name'        => 'pll_get_translations',
				'description' => 'List a post\'s or term\'s translation siblings: language code mapped to the sibling object\'s ID. Does not include a language the object has no translation into. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'enum' => [ 'post', 'term' ], 'description' => 'Whether object_id is a post ID or a term ID.' ],
						'object_id'   => [ 'type' => 'integer', 'description' => 'Post ID or term ID, per object_type.' ],
					],
					'required'   => [ 'object_type', 'object_id' ],
				],
			],
		];
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use multilingual tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Polylang is not active.' );
		}

		switch ( $name ) {
			case 'pll_get_languages':
				return self::get_languages();
			case 'pll_get_object_language':
				return self::get_object_language( $args );
			case 'pll_get_translations':
				return self::get_translations( $args );
			default:
				throw new \Exception( 'Unknown Polylang tool: ' . esc_html( $name ) );
		}
	}

	private static function get_languages() {
		$default = pll_default_language( 'slug' );
		$list    = pll_languages_list( [ 'fields' => '' ] );
		$list    = is_array( $list ) ? $list : [];

		$out = [];
		foreach ( $list as $lang ) {
			$out[] = self::language_to_array( $lang, $default );
		}

		return [
			'provider'  => 'polylang',
			'default'   => $default ?: null,
			'languages' => $out,
		];
	}

	private static function get_object_language( $args ) {
		[ $type, $id ] = self::resolve_object_args( $args );

		$lang = 'post' === $type ? pll_get_post_language( $id, \OBJECT ) : pll_get_term_language( $id, \OBJECT );

		if ( empty( $lang ) ) {
			return [
				'provider'     => 'polylang',
				'object_type'  => $type,
				'object_id'    => $id,
				'has_language' => false,
				'language'     => null,
			];
		}

		return [
			'provider'     => 'polylang',
			'object_type'  => $type,
			'object_id'    => $id,
			'has_language' => true,
			'language'     => self::language_to_array( $lang, pll_default_language( 'slug' ) ),
		];
	}

	private static function get_translations( $args ) {
		[ $type, $id ] = self::resolve_object_args( $args );

		$translations = 'post' === $type ? pll_get_post_translations( $id ) : pll_get_term_translations( $id );
		$translations = is_array( $translations ) ? $translations : [];

		$out = [];
		foreach ( $translations as $code => $translated_id ) {
			$out[] = [
				'language' => sanitize_text_field( (string) $code ),
				'id'       => (int) $translated_id,
			];
		}

		return [
			'provider'     => 'polylang',
			'object_type'  => $type,
			'object_id'    => $id,
			'count'        => count( $out ),
			'translations' => $out,
		];
	}

	private static function resolve_object_args( $args ) {
		$type = $args['object_type'] ?? '';
		$id   = absint( $args['object_id'] ?? 0 );
		if ( ! in_array( $type, [ 'post', 'term' ], true ) ) {
			throw new \Exception( 'object_type must be "post" or "term".' );
		}
		if ( $id <= 0 ) {
			throw new \Exception( 'object_id is required.' );
		}
		return [ $type, $id ];
	}

	private static function language_to_array( $lang, $default_slug ) {
		$slug = (string) ( $lang->slug ?? '' );
		return [
			'code'       => $slug,
			'name'       => (string) ( $lang->name ?? '' ),
			'locale'     => (string) ( $lang->locale ?? '' ),
			'is_rtl'     => ! empty( $lang->is_rtl ),
			'is_default' => '' !== $slug && $slug === $default_slug,
			'flag_code'  => (string) ( $lang->flag_code ?? '' ),
		];
	}
}
