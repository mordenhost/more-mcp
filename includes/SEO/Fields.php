<?php
/**
 * Where each SEO plugin actually stores each field, per object type.
 *
 * This file exists because of a specific class of bug: writing an SEO value to
 * a plausible-looking location the plugin never reads. The caller gets a
 * success response, the value round-trips correctly on read, and the rendered
 * page is unchanged. GitHub #6 is exactly that — `_yoast_wpseo_noindex` written
 * to term meta while Yoast reads taxonomy noindex out of the
 * `wpseo_taxonomy_meta` *option*.
 *
 * So the catalogue below is deliberately explicit rather than pattern-based. A
 * field/plugin/level combination is supported only if it appears here with a
 * storage strategy we have implemented. Everything else is refused by name,
 * with a message saying which plugin and which field — never written to a
 * best-guess key.
 *
 * Storage strategies, all implemented in {@see Meta}:
 *
 *   post_meta          one meta key per field on the post
 *   term_meta          one meta key per field on the term
 *   meta_array         one meta key holding an associative array of fields
 *   yoast_term_option  the nested `wpseo_taxonomy_meta` option, keyed
 *                      [taxonomy][term_id][field]
 *   aioseo_table       AIOSEO 4.x custom tables (aioseo_posts / aioseo_terms)
 *
 * `noindex` gets its own per-plugin spec because every plugin encodes it
 * differently: a '1'/'0' string, a member of a robots array, a 'yes' flag, a
 * tri-state 'default'/'noindex'/'index' string, or a boolean column.
 *
 * @package More_MCP
 */

namespace More_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fields {

	/**
	 * Every logical field name this subsystem understands, in the order a
	 * response presents them.
	 *
	 * `slug` is absent on purpose: it is the WordPress-native post_name /
	 * term slug column, handled outside the per-plugin catalogue because it
	 * works with no SEO plugin at all.
	 */
	const LOGICAL = array(
		'title',
		'description',
		'focus_keyword',
		'noindex',
		'canonical',
		'og_title',
		'og_description',
		'twitter_title',
		'twitter_description',
		'schema_page_type',
	);

	/**
	 * Post-level storage, keyed plugin → logical field → spec.
	 *
	 * A spec is either a plain meta key (string) or an array carrying the
	 * strategy and whatever that strategy needs.
	 */
	private static function post_catalog(): array {
		return array(
			'yoast' => array(
				'strategy'            => 'post_meta',
				'title'               => '_yoast_wpseo_title',
				'description'         => '_yoast_wpseo_metadesc',
				'focus_keyword'       => '_yoast_wpseo_focuskw',
				'canonical'           => '_yoast_wpseo_canonical',
				'og_title'            => '_yoast_wpseo_opengraph-title',
				'og_description'      => '_yoast_wpseo_opengraph-description',
				'twitter_title'       => '_yoast_wpseo_twitter-title',
				'twitter_description' => '_yoast_wpseo_twitter-description',
				// Verified rendering into the @graph on Yoast 27.2 — see #6.
				'schema_page_type'    => '_yoast_wpseo_schema_page_type',
				'noindex'             => array(
					'strategy' => 'flag_string',
					'key'      => '_yoast_wpseo_meta-robots-noindex',
					'on'       => '1',
					'off'      => '0',
				),
			),
			'rankmath' => array(
				'strategy'            => 'post_meta',
				'title'               => 'rank_math_title',
				'description'         => 'rank_math_description',
				'focus_keyword'       => 'rank_math_focus_keyword',
				'canonical'           => 'rank_math_canonical_url',
				'og_title'            => 'rank_math_facebook_title',
				'og_description'      => 'rank_math_facebook_description',
				'twitter_title'       => 'rank_math_twitter_title',
				'twitter_description' => 'rank_math_twitter_description',
				'noindex'             => array(
					'strategy' => 'robots_array',
					'key'      => 'rank_math_robots',
				),
			),
			'aioseo' => array(
				// AIOSEO 4.x moved off post meta into its own table. Writing
				// `_aioseo_title` post meta (the 3.x location) is the #6 failure
				// mode again: it stores cleanly and renders nothing.
				'strategy'            => 'aioseo_table',
				'table'               => 'aioseo_posts',
				'id_column'           => 'post_id',
				'title'               => 'title',
				'description'         => 'description',
				'canonical'           => 'canonical_url',
				'og_title'            => 'og_title',
				'og_description'      => 'og_description',
				'twitter_title'       => 'twitter_title',
				'twitter_description' => 'twitter_description',
				'focus_keyword'       => null,
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'aioseo_robots',
					'column'   => 'robots_noindex',
				),
			),
			'seopress' => array(
				'strategy'            => 'post_meta',
				'title'               => '_seopress_titles_title',
				'description'         => '_seopress_titles_desc',
				'focus_keyword'       => '_seopress_analysis_target_kw',
				'canonical'           => '_seopress_robots_canonical',
				'og_title'            => '_seopress_social_fb_title',
				'og_description'      => '_seopress_social_fb_desc',
				'twitter_title'       => '_seopress_social_twitter_title',
				'twitter_description' => '_seopress_social_twitter_desc',
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'flag_string',
					'key'      => '_seopress_robots_index',
					// SEOPress inverts this one: the key means "no index",
					// 'yes' = noindex. Storing '0' would read as indexable.
					'on'       => 'yes',
					'off'      => '',
				),
			),
			'slimseo' => array(
				// Slim SEO keeps one serialized array under a single key rather
				// than a key per field.
				'strategy'            => 'meta_array',
				'key'                 => 'slim_seo',
				'title'               => 'title',
				'description'         => 'description',
				'canonical'           => 'canonical',
				'og_title'            => 'facebook_title',
				'og_description'      => 'facebook_description',
				'twitter_title'       => 'twitter_title',
				'twitter_description' => 'twitter_description',
				'focus_keyword'       => null,
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'array_flag',
					'field'    => 'noindex',
					'on'       => '1',
					'off'      => '',
				),
			),
			'tsf' => array(
				// The SEO Framework 4.1+ consolidated its per-post fields into
				// one `_genesis_*`-successor array under `tsf`. 4.0 and earlier
				// used discrete _genesis_ keys; we target current.
				'strategy'            => 'meta_array',
				'key'                 => 'tsf',
				'title'               => 'doctitle',
				'description'         => 'description',
				'canonical'           => 'canonical',
				'og_title'            => 'og_title',
				'og_description'      => 'og_description',
				'twitter_title'       => 'tw_title',
				'twitter_description' => 'tw_description',
				'focus_keyword'       => null,
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'array_tristate',
					'field'    => 'noindex',
					// TSF stores -1 index / 0 default / 1 noindex.
					'on'       => 1,
					'off'      => -1,
					'default'  => 0,
				),
			),
		);
	}

	/**
	 * Term-level storage, keyed plugin → logical field → spec.
	 *
	 * Yoast is the one that motivated this whole file: its term data lives in an
	 * option, not in term meta, so it needs its own strategy. The others do use
	 * term meta, with their own key prefixes.
	 */
	private static function term_catalog(): array {
		return array(
			'yoast' => array(
				// The wpseo_taxonomy_meta option, shaped
				// [ taxonomy => [ term_id => [ field => value ] ] ].
				// Writing this option means read-modify-write: a blind overwrite
				// discards every other term's settings on the site.
				'strategy'            => 'yoast_term_option',
				'title'               => 'wpseo_title',
				'description'         => 'wpseo_desc',
				'focus_keyword'       => 'wpseo_focuskw',
				'canonical'           => 'wpseo_canonical',
				'og_title'            => 'wpseo_opengraph-title',
				'og_description'      => 'wpseo_opengraph-description',
				'twitter_title'       => 'wpseo_twitter-title',
				'twitter_description' => 'wpseo_twitter-description',
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'yoast_term_noindex',
					'field'    => 'wpseo_noindex',
					// Yoast term noindex is tri-state: 'default' inherits the
					// taxonomy-wide setting, which is not the same as 'index'.
					'on'       => 'noindex',
					'off'      => 'index',
					'default'  => 'default',
				),
			),
			'rankmath' => array(
				'strategy'            => 'term_meta',
				'title'               => 'rank_math_title',
				'description'         => 'rank_math_description',
				'focus_keyword'       => 'rank_math_focus_keyword',
				'canonical'           => 'rank_math_canonical_url',
				'og_title'            => 'rank_math_facebook_title',
				'og_description'      => 'rank_math_facebook_description',
				'twitter_title'       => 'rank_math_twitter_title',
				'twitter_description' => 'rank_math_twitter_description',
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'robots_array',
					'key'      => 'rank_math_robots',
				),
			),
			'aioseo' => array(
				'strategy'            => 'aioseo_table',
				'table'               => 'aioseo_terms',
				'id_column'           => 'term_id',
				'title'               => 'title',
				'description'         => 'description',
				'canonical'           => 'canonical_url',
				'og_title'            => 'og_title',
				'og_description'      => 'og_description',
				'twitter_title'       => 'twitter_title',
				'twitter_description' => 'twitter_description',
				'focus_keyword'       => null,
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'aioseo_robots',
					'column'   => 'robots_noindex',
				),
			),
			'seopress' => array(
				'strategy'            => 'term_meta',
				'title'               => '_seopress_titles_title',
				'description'         => '_seopress_titles_desc',
				'canonical'           => '_seopress_robots_canonical',
				'og_title'            => '_seopress_social_fb_title',
				'og_description'      => '_seopress_social_fb_desc',
				'twitter_title'       => '_seopress_social_twitter_title',
				'twitter_description' => '_seopress_social_twitter_desc',
				'focus_keyword'       => null,
				'schema_page_type'    => null,
				'noindex'             => array(
					'strategy' => 'flag_string',
					'key'      => '_seopress_robots_index',
					'on'       => 'yes',
					'off'      => '',
				),
			),
			// Slim SEO and The SEO Framework: term-level support is not
			// implemented. Both store term data differently again, and shipping
			// a guess here would reintroduce the exact bug this file exists to
			// prevent. Absent from the catalogue means refused by name.
		);
	}

	/**
	 * Catalogue for a level. Level is 'post' or 'term'.
	 */
	public static function catalog( string $level ): array {
		return 'term' === $level ? self::term_catalog() : self::post_catalog();
	}

	/**
	 * The plugin's storage spec for a level, or null when that plugin has no
	 * support at that level at all.
	 */
	public static function spec( string $plugin, string $level ): ?array {
		$catalog = self::catalog( $level );
		return isset( $catalog[ $plugin ] ) ? $catalog[ $plugin ] : null;
	}

	/**
	 * Logical fields this plugin supports at this level, in LOGICAL order.
	 *
	 * A field mapped to null is declared-but-unsupported: it exists in the spec
	 * so the refusal message can say "Yoast supports this, SEOPress does not"
	 * rather than falling through to a generic unknown-field error.
	 */
	public static function supported( string $plugin, string $level ): array {
		$spec = self::spec( $plugin, $level );
		if ( null === $spec ) {
			return array();
		}
		$out = array();
		foreach ( self::LOGICAL as $field ) {
			if ( array_key_exists( $field, $spec ) && null !== $spec[ $field ] ) {
				$out[] = $field;
			}
		}
		return $out;
	}

	/**
	 * Whether a plugin supports a field at a level.
	 */
	public static function supports( string $plugin, string $level, string $field ): bool {
		return in_array( $field, self::supported( $plugin, $level ), true );
	}

	/**
	 * Plugins with any support at a level. Used to build refusal messages that
	 * name a working alternative instead of just saying no.
	 */
	public static function plugins_with_support( string $level ): array {
		return array_keys( self::catalog( $level ) );
	}
}
