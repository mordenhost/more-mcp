<?php

namespace More_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fields {

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

					'on'       => 'yes',
					'off'      => '',
				),
			),
			'slimseo' => array(

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
					
					'on'       => 1,
					'off'      => -1,
					'default'  => 0,
				),
			),
		);
	}

	private static function term_catalog(): array {
		return array(
			'yoast' => array(

				
				
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

			
			
		);
	}

	public static function catalog( string $level ): array {
		return 'term' === $level ? self::term_catalog() : self::post_catalog();
	}

	public static function spec( string $plugin, string $level ): ?array {
		$catalog = self::catalog( $level );
		return isset( $catalog[ $plugin ] ) ? $catalog[ $plugin ] : null;
	}

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

	public static function supports( string $plugin, string $level, string $field ): bool {
		return in_array( $field, self::supported( $plugin, $level ), true );
	}

	public static function plugins_with_support( string $level ): array {
		return array_keys( self::catalog( $level ) );
	}
}
