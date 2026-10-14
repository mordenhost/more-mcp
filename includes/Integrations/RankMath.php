<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankMath {

	private const POST_TOOLS = array(
		'rank_math_get_seo_scores',
		'rank_math_analyze_post_content',
		'rank_math_get_post_schema',
		'rank_math_get_post_links',
	);

	private const RANK_MATH_CAPS = array(
		'rank_math_get_seo_scores'       => 'rank_math_onpage_analysis',
		'rank_math_analyze_post_content' => 'rank_math_onpage_analysis',
		'rank_math_get_post_schema'      => 'rank_math_onpage_snippet',
		'rank_math_get_post_links'       => 'rank_math_link_builder',
		'rank_math_get_link_report'      => 'rank_math_link_builder',
		'rank_math_audit_site_seo'       => 'rank_math_site_analysis',
		'rank_math_fix_site_seo'         => 'rank_math_site_analysis',
		'rank_math_get_top_keywords'     => 'rank_math_analytics',
	);

	private const SEO_FILTERS = array( 'bad', 'good', 'great', 'empty-fk', 'unscored', 'noindexed' );

	private const LIST_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future', 'any' );

	private const ANALYZE_STATUSES = array( 'publish', 'draft', 'private', 'pending' );

	private const DEFAULT_TESTS = array(
		'contentHasTOC'             => true,
		'contentHasShortParagraphs' => true,
		'contentHasAssets'          => true,
		'keywordInTitle'            => true,
		'keywordInMetaDescription'  => true,
		'keywordInPermalink'        => true,
		'keywordIn10Percent'        => true,
		'keywordInContent'          => true,
		'keywordInSubheadings'      => true,
		'keywordInImageAlt'         => true,
		'keywordDensity'            => true,
		'keywordNotUsed'            => true,
		'lengthContent'             => true,
		'lengthPermalink'           => true,
		'linksHasInternal'          => true,
		'linksHasExternals'         => true,
		'linksNotAllExternals'      => true,
		'titleStartWithKeyword'     => true,
		'titleSentiment'            => true,
		'titleHasPowerWords'        => true,
		'titleHasNumber'            => true,
		'hasContentAI'              => true,
	);

	private const KB_LINK = 'https://rankmath.com/kb/score-100-in-tests/';

	private const FIXABLE_TESTS = array(
		'blog_public',
		'permalink_structure',
		'site_description',
		'sitemaps',
		'schema',
		'noindex',
		'opengraph',
		'robots_txt',
		'focus_keywords',
		'post_titles',
	);

	private const AUDIT_WEIGHTS = array(
		'h1_heading'          => 5,
		'h2_headings'         => 2,
		'img_alt'             => 4,
		'keywords_meta'       => 5,
		'links_ratio'         => 3,
		'title_length'        => 4,
		'permalink_structure' => 7,
		'focus_keywords'      => 3,
		'post_titles'         => 4,
		'canonical'           => 5,
		'noindex'             => 7,
		'non_www'             => 4,
		'opengraph'           => 2,
		'robots_txt'          => 3,
		'schema'              => 3,
		'sitemaps'            => 3,
		'search_console'      => 1,
		'image_header'        => 3,
		'minify_css'          => 2,
		'minify_js'           => 1,
		'page_objects'        => 2,
		'page_size'           => 3,
		'response_time'       => 3,
		'directory_listing'   => 1,
		'safe_browsing'       => 8,
		'ssl'                 => 7,
	);

	private const AUDIT_ALWAYS_HIDDEN = array( 'serp_preview', 'mobile_serp_preview' );

	private const DATE_RANGES = array(
		'last_7_days'    => '-7 days',
		'last_30_days'   => '-30 days',
		'last_3_months'  => '-3 months',
		'last_6_months'  => '-6 months',
		'last_12_months' => '-1 year',
	);

	private const ROBOTS_TXT_DEFAULT = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php";

	private const FIX_POST_LIMIT = 100;
	private const FIX_POST_MAX   = 500;

	private const DEFAULT_WRITABLE_OPTIONS = array( 'blogname', 'blogdescription', 'posts_per_page', 'date_format', 'time_format' );

	public static function is_available() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'rankmath' ),
			'capabilities' => array( 'seo_analysis' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'rank_math_get_seo_scores',
				'description' => 'List posts with the SEO score Rank Math stored for them, its band (great above 80, good 51-80, bad 50 or below, as in Rank Math\'s post list), the focus keywords, and the noindex and pillar flags. Filter like Rank Math\'s own post-list filter: seo_filter bad, good or great (indexed posts with a focus keyword, worst score first), empty-fk (no focus keyword), unscored (never analyzed), or noindexed. Without a filter, the most recently modified posts come first. Only posts you can edit are returned. Scores are computed in the post editor, so this reads what is stored and never rescans. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'seo_filter'  => array(
							'type'        => 'string',
							'enum'        => self::SEO_FILTERS,
							'description' => 'bad, good, great, empty-fk (no focus keyword), unscored, or noindexed. Omit for the most recently modified posts.',
						),
						'post_type'   => array(
							'type'        => 'string',
							'description' => 'One post type. Defaults to the post types Rank Math shows its meta box on.',
						),
						'post_status' => array(
							'type'        => 'string',
							'enum'        => self::LIST_STATUSES,
							'description' => 'Default publish.',
						),
						'limit'       => array(
							'type'        => 'integer',
							'description' => 'Posts per page (1-100). Default 20.',
						),
						'page'        => array(
							'type'        => 'integer',
							'description' => 'Page number, from 1. Default 1.',
						),
					),
				),
			),
			array(
				'name'        => 'rank_math_analyze_post_content',
				'description' => 'Get the inputs Rank Math\'s on-page SEO tests read for one post: the list of tests that apply to its post type, the SEO title and meta description, permalink, focus keywords, plain-text body, word count, first 10% of the body, headings, image alt texts and links (internal or external), plus the score Rank Math last stored. Rank Math runs its tests in the browser editor, so this gives you the same material to judge against the test list; it does not compute a new score. Requires permission to edit the post. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer', 'description' => 'Post ID.' ),
						'keyword'   => array( 'type' => 'string', 'description' => 'Analyze against this keyword instead of the stored primary focus keyword.' ),
						'max_chars' => array( 'type' => 'integer', 'description' => 'Longest body text returned (1000-100000). Default 20000. Word count always covers the whole body.' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'rank_math_get_post_schema',
				'description' => 'Get the schema Rank Math stores for one post: every rank_math_schema_* entry with its id, @type, title, whether it is the primary schema and whether it came from a template, plus the default schema type for the post type, schema added by Rank Math\'s FAQ and HowTo blocks, and the schema types Rank Math offers. These are the editable templates (they may contain %variables%); seo_get_head returns the rendered JSON-LD. Requires permission to edit the post. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'rank_math_get_post_links',
				'description' => 'Get the links Rank Math\'s link counter recorded for one post: outgoing links (URL, internal or external, target post), the internal, external and incoming counts, and the internal links pointing at this post from other posts you can read. Rank Math records links when a post is saved; the free plugin stores no anchor text or nofollow flag. Requires permission to edit the post. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array( 'type' => 'integer', 'description' => 'Post ID.' ),
						'type'             => array(
							'type'        => 'string',
							'enum'        => array( 'internal', 'external' ),
							'description' => 'Only outgoing links of this type. Omit for both.',
						),
						'include_incoming' => array( 'type' => 'boolean', 'description' => 'Include internal links from other posts to this one. Default true.' ),
						'limit'            => array( 'type' => 'integer', 'description' => 'Most links returned per list (1-500). Default 100.' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'rank_math_get_link_report',
				'description' => 'Get the site-wide internal-linking report from Rank Math\'s link counter: total, internal and external links, scanned published posts, orphan posts (no incoming internal links), and posts with no internal or no external links. Set include_posts to list posts with their link counts, optionally only orphans, posts with no internal links, or posts with no external links. Set include_audit to add Rank Math PRO\'s link audit when PRO provides one; without PRO the report says the audit is unavailable. Read-only. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_posts' => array( 'type' => 'boolean', 'description' => 'List posts with their link counts. Default false.' ),
						'filter'        => array(
							'type'        => 'string',
							'enum'        => array( 'orphans', 'no_internal', 'no_external' ),
							'description' => 'With include_posts: only these posts.',
						),
						'post_type'     => array( 'type' => 'string', 'description' => 'With include_posts: one post type. Default post and page, as Rank Math\'s report uses.' ),
						'orderby'       => array(
							'type' => 'string',
							'enum' => array( 'post_title', 'internal_link_count', 'external_link_count', 'incoming_link_count', 'post_modified' ),
						),
						'order'         => array(
							'type' => 'string',
							'enum' => array( 'asc', 'desc' ),
						),
						'limit'         => array( 'type' => 'integer', 'description' => 'With include_posts: posts per page (1-200). Default 50.' ),
						'page'          => array( 'type' => 'integer', 'description' => 'With include_posts: page number, from 1.' ),
						'include_audit' => array( 'type' => 'boolean', 'description' => 'Ask Rank Math PRO for its link audit (broken links, redirect chains). Default false.' ),
					),
				),
			),
			array(
				'name'        => 'rank_math_audit_site_seo',
				'description' => 'Get the result of Rank Math\'s last SEO Analyzer run: overall score and grade, counts by status, and every test with its category, status (ok, fail, warning, info), weight, message, how-to-fix text, knowledge-base link, a fix hint, and whether rank_math_fix_site_seo can fix it automatically. Reads the stored result only; a fresh run calls rankmath.com and is started from wp-admin (Rank Math > SEO Analyzer). Read-only. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'fail', 'warning', 'info', 'ok' ),
							'description' => 'Only tests with this status. Omit for all.',
						),
						'include_data' => array( 'type' => 'boolean', 'description' => 'Include each test\'s raw data (for example the posts a test flagged). Default false.' ),
					),
				),
			),
			array(
				'name'        => 'rank_math_fix_site_seo',
				'description' => 'Apply Rank Math\'s automatic fix for one SEO Analyzer test. Two-part confirmation: call with dry_run=true (or without confirm) to get a preview of exactly what would change, then call with confirm=true and confirm_test_id equal to test_id to apply it. Where the site owner requires approval for high-impact tools, a dry_run preview needs none; only the applying call does. Fixes: blog_public (let search engines index the site), permalink_structure (/%postname%/ when post names are not in permalinks), site_description (needs value), sitemaps and schema (turn on the Rank Math module), noindex (remove noindex from Rank Math\'s global robots), robots_txt (default rules when none are set), focus_keywords (set a keyword from the title on published posts without one), post_titles (replace focus keywords that are not in the title). opengraph cannot be fixed automatically. Option changes need "Allow AI to write WordPress options"; Rank Math option rows (rank-math-options-titles, rank-math-options-general, rank_math_modules) must also be in the writable-options allowlist. Requires manage_options; keyword changes also need permission to edit each post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'test_id'         => array(
							'type' => 'string',
							'enum' => self::FIXABLE_TESTS,
						),
						'value'           => array( 'type' => 'string', 'description' => 'site_description only: the new tagline.' ),
						'post_limit'      => array( 'type' => 'integer', 'description' => 'focus_keywords and post_titles only: most posts changed in one call (1-500). Default 100.' ),
						'dry_run'         => array( 'type' => 'boolean', 'description' => 'true returns the preview and changes nothing.' ),
						'confirm'         => array( 'type' => 'boolean', 'description' => 'Must be true to apply. Omit for the preview, which is the intended first call.' ),
						'confirm_test_id' => array( 'type' => 'string', 'description' => 'Repeat test_id. Required with confirm=true.' ),
					),
					'required'   => array( 'test_id' ),
				),
			),
			array(
				'name'        => 'rank_math_get_top_keywords',
				'description' => 'Get the top search queries from Rank Math\'s own Google Search Console connection: clicks, impressions, CTR and average position for each query, with the change against the previous period, sorted by clicks. Needs the Rank Math Analytics module and a connected Search Console property; otherwise the result says which is missing. Data comes from what Rank Math has already synced, ending three days ago. Read-only. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'date_range' => array(
							'type'        => 'string',
							'enum'        => array_keys( self::DATE_RANGES ),
							'description' => 'Default last_30_days.',
						),
						'limit'      => array( 'type' => 'integer', 'description' => 'Most queries returned (1-100). Default 25.' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		$args = is_array( $args ) ? $args : array();

		$is_post_tool = in_array( $name, self::POST_TOOLS, true );
		if ( ! current_user_can( $is_post_tool ? 'edit_posts' : 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Rank Math tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Rank Math is not active.' );
		}
		if ( ! isset( self::RANK_MATH_CAPS[ $name ] ) ) {
			throw new \Exception( 'Unknown Rank Math tool: ' . esc_html( $name ) );
		}
		
		if ( ! current_user_can( self::RANK_MATH_CAPS[ $name ] ) ) {
			throw new \Exception( 'Rank Math\'s role settings do not give you the ' . esc_html( self::RANK_MATH_CAPS[ $name ] ) . ' capability this tool needs.' );
		}

		switch ( $name ) {
			case 'rank_math_get_seo_scores':
				return self::get_seo_scores( $args );
			case 'rank_math_analyze_post_content':
				return self::analyze_post_content( $args );
			case 'rank_math_get_post_schema':
				return self::get_post_schema( $args );
			case 'rank_math_get_post_links':
				return self::get_post_links( $args );
			case 'rank_math_get_link_report':
				return self::get_link_report( $args );
			case 'rank_math_audit_site_seo':
				return self::audit_site_seo( $args );
			case 'rank_math_fix_site_seo':
				return self::fix_site_seo( $args );
			case 'rank_math_get_top_keywords':
				return self::get_top_keywords( $args );
		}
		throw new \Exception( 'Unknown Rank Math tool: ' . esc_html( $name ) );
	}

	

	private static function int_arg( array $args, $key, $default, $min, $max ) {
		$value = isset( $args[ $key ] ) && is_numeric( $args[ $key ] ) ? (int) $args[ $key ] : $default;
		return max( $min, min( $max, $value ) );
	}

	private static function bool_arg( array $args, $key, $default = false ) {
		if ( ! array_key_exists( $key, $args ) ) {
			return $default;
		}
		$value = $args[ $key ];
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return false;
	}

	private static function post_for_edit( array $args ) {
		$post_id = isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'Post ' . (int) $post_id . ' was not found, or you do not have permission to edit it.' );
		}
		return $post;
	}

	private static function rm_helper( $method, array $params = array() ) {
		$helper = '\RankMath\Helper';
		if ( ! class_exists( $helper ) || ! method_exists( $helper, $method ) ) {
			return null;
		}
		try {
			return call_user_func_array( array( $helper, $method ), $params );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function module_active( $module ) {
		$modules = get_option( 'rank_math_modules', array() );
		return is_array( $modules ) && in_array( $module, $modules, true );
	}

	private static function focus_keywords( $post_id ) {
		$raw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		$all = array();
		if ( is_string( $raw ) && '' !== $raw ) {
			foreach ( explode( ',', $raw ) as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$all[] = $part;
				}
			}
		}
		return array( isset( $all[0] ) ? $all[0] : '', $all );
	}

	private static function score_band( $raw ) {
		if ( null === $raw || false === $raw || '' === $raw || ! is_numeric( $raw ) ) {
			return array( null, null );
		}
		$score = (int) $raw;
		if ( $score > 80 ) {
			return array( $score, 'great' );
		}
		return array( $score, $score > 50 ? 'good' : 'bad' );
	}

	private static function is_noindex( $post_id ) {
		$robots = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $robots ) ) {
			return in_array( 'noindex', $robots, true );
		}
		return is_string( $robots ) && false !== strpos( $robots, 'noindex' );
	}

	private static function allowed_post_types() {
		$types = self::rm_helper( 'get_allowed_post_types' );
		if ( ! is_array( $types ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
	}

	private static function default_post_types() {
		$types = self::allowed_post_types();
		if ( ! empty( $types ) ) {
			return $types;
		}
		$public = get_post_types( array( 'public' => true ) );
		unset( $public['attachment'] );
		return array_values( $public );
	}

	private static function post_type_arg( array $args ) {
		if ( empty( $args['post_type'] ) || ! is_string( $args['post_type'] ) ) {
			return null;
		}
		$type = sanitize_key( $args['post_type'] );
		if ( '' === $type || ! post_type_exists( $type ) ) {
			throw new \Exception( 'Unknown post type: ' . esc_html( $type ) );
		}
		return $type;
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table;
	}

	private static function home_host() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	private static function is_external_url( $url, $home_host ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		$home = preg_replace( '/^www\./', '', $home_host );
		return preg_replace( '/^www\./', '', $host ) !== $home;
	}

	

	
	private static function score_meta_query( $filter ) {
		$hash = array(
			'empty-fk'  => array(
				'key'     => 'rank_math_focus_keyword',
				'compare' => 'NOT EXISTS',
			),
			'unscored'  => array(
				'key'     => 'rank_math_seo_score',
				'compare' => 'NOT EXISTS',
			),
			'noindexed' => array(
				'key'     => 'rank_math_robots',
				'value'   => 'noindex',
				'compare' => 'LIKE',
			),
			'bad'       => array(
				'key'     => 'rank_math_seo_score',
				'value'   => 50,
				'compare' => '<=',
				'type'    => 'numeric',
			),
			'good'      => array(
				'key'     => 'rank_math_seo_score',
				'value'   => array( 51, 80 ),
				'compare' => 'BETWEEN',
				'type'    => 'numeric',
			),
			'great'     => array(
				'key'     => 'rank_math_seo_score',
				'value'   => 80,
				'compare' => '>',
				'type'    => 'numeric',
			),
		);
		if ( ! isset( $hash[ $filter ] ) ) {
			return array();
		}
		$meta_query = array();
		if ( in_array( $filter, array( 'bad', 'good', 'great' ), true ) ) {
			$meta_query['relation'] = 'AND';
			$meta_query[]           = array(
				'relation' => 'OR',
				array(
					'key'     => 'rank_math_robots',
					'value'   => 'noindex',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'rank_math_robots',
					'compare' => 'NOT EXISTS',
				),
			);
			$meta_query[]           = array(
				'key'     => 'rank_math_focus_keyword',
				'compare' => 'EXISTS',
			);
			$meta_query[]           = array(
				'key'     => 'rank_math_focus_keyword',
				'value'   => '',
				'compare' => '!=',
			);
		}
		$meta_query[] = $hash[ $filter ];
		return $meta_query;
	}

	private static function get_seo_scores( array $args ) {
		$limit  = self::int_arg( $args, 'limit', 20, 1, 100 );
		$page   = self::int_arg( $args, 'page', 1, 1, 100000 );
		$filter = isset( $args['seo_filter'] ) && is_string( $args['seo_filter'] ) ? sanitize_key( $args['seo_filter'] ) : '';
		if ( '' !== $filter && ! in_array( $filter, self::SEO_FILTERS, true ) ) {
			throw new \Exception( 'Unknown seo_filter. Use one of: ' . esc_html( implode( ', ', self::SEO_FILTERS ) ) . '.' );
		}
		$status = isset( $args['post_status'] ) && is_string( $args['post_status'] ) ? sanitize_key( $args['post_status'] ) : 'publish';
		if ( ! in_array( $status, self::LIST_STATUSES, true ) ) {
			throw new \Exception( 'Unknown post_status. Use one of: ' . esc_html( implode( ', ', self::LIST_STATUSES ) ) . '.' );
		}
		$type  = self::post_type_arg( $args );
		$types = null === $type ? self::default_post_types() : array( $type );

		$query = array(
			'post_type'        => $types,
			'post_status'      => $status,
			'posts_per_page'   => $limit,
			'paged'            => $page,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => false,
		);
		$meta_query = self::score_meta_query( $filter );
		if ( ! empty( $meta_query ) ) {
			$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Rank Math's own filter, mirrored.
			if ( in_array( $filter, array( 'bad', 'good', 'great' ), true ) ) {
				
				$query['orderby']  = 'meta_value_num';
				$query['meta_key'] = 'rank_math_seo_score'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by the stored score.
				$query['order']    = 'ASC';
			}
		}

		$ids     = get_posts( $query );
		$posts   = array();
		$skipped = 0;
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$id   = (int) ( is_object( $id ) ? $id->ID : $id );
			$post = get_post( $id );
			if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
				++$skipped;
				continue;
			}
			list( $score, $band )      = self::score_band( get_post_meta( $id, 'rank_math_seo_score', true ) );
			list( $primary, $keywords ) = self::focus_keywords( $id );
			$posts[]                    = array(
				'post_id'        => $id,
				'title'          => (string) $post->post_title,
				'post_type'      => (string) $post->post_type,
				'status'         => (string) $post->post_status,
				'score'          => $score,
				'band'           => $band,
				'focus_keyword'  => '' === $primary ? null : $primary,
				'focus_keywords' => $keywords,
				'noindex'        => self::is_noindex( $id ),
				'pillar'         => 'on' === (string) get_post_meta( $id, 'rank_math_pillar_content', true ),
				'modified_gmt'   => isset( $post->post_modified_gmt ) ? (string) $post->post_modified_gmt : null,
			);
		}

		return array(
			'seo_filter'            => '' === $filter ? null : $filter,
			'post_types'            => array_values( $types ),
			'post_status'           => $status,
			'page'                  => $page,
			'limit'                 => $limit,
			'count'                 => count( $posts ),
			'has_more'              => is_array( $ids ) && count( $ids ) >= $limit,
			'skipped_no_permission' => $skipped,
			'bands'                 => array(
				'great' => 'above 80',
				'good'  => '51-80',
				'bad'   => '50 or below',
			),
			'posts'                 => $posts,
			'note'                  => 'Rank Math computes scores in the post editor and stores them when the post is saved. A post with score null has never been analyzed; open it in the editor to score it.',
		);
	}

	

	private static function analyze_post_content( array $args ) {
		$post = self::post_for_edit( $args );
		$id   = (int) $post->ID;
		if ( ! in_array( (string) $post->post_status, self::ANALYZE_STATUSES, true ) ) {
			throw new \Exception( 'Rank Math analyzes published, draft, pending and private posts only; this post is ' . esc_html( (string) $post->post_status ) . '.' );
		}
		$max_chars = self::int_arg( $args, 'max_chars', 20000, 1000, 100000 );

		$content = (string) $post->post_content;
		$body    = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $content ) ) ) );
		$length  = mb_strlen( $body );
		$tenth   = (int) ceil( $length * 0.1 );

		$tests = apply_filters( 'rank_math/researches/tests', self::DEFAULT_TESTS, (string) $post->post_type );
		$tests = is_array( $tests ) ? array_keys( array_filter( $tests ) ) : array_keys( self::DEFAULT_TESTS );

		list( $primary, $keywords ) = self::focus_keywords( $id );
		$keyword                    = isset( $args['keyword'] ) && is_string( $args['keyword'] ) ? trim( sanitize_text_field( $args['keyword'] ) ) : '';

		$title = self::rm_helper( 'get_post_meta', array( 'title', $id ) );
		if ( ! is_string( $title ) || '' === $title ) {
			$title = (string) get_post_meta( $id, 'rank_math_title', true );
		}
		$description = self::rm_helper( 'get_post_meta', array( 'description', $id ) );
		if ( ! is_string( $description ) || '' === $description ) {
			$description = (string) get_post_meta( $id, 'rank_math_description', true );
		}

		$headings = array();
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$headings[] = array(
					'level' => 'h' . $match[1],
					'text'  => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}
		$images = array();
		if ( preg_match_all( '/<img[^>]+>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $tag ) {
				$images[] = array( 'alt' => preg_match( '/alt=["\']([^"\']*)["\']/i', $tag, $alt ) ? $alt[1] : '' );
			}
		}
		$links = array();
		$home  = self::home_host();
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				if ( 0 === strpos( $href, '#' ) ) {
					continue;
				}
				$links[] = array(
					'url'      => $href,
					'external' => self::is_external_url( $href, $home ),
				);
			}
		}

		list( $score, $band ) = self::score_band( get_post_meta( $id, 'rank_math_seo_score', true ) );

		return array(
			'post_id'      => $id,
			'post_type'    => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'tests_to_run' => $tests,
			'content'      => array(
				'title'          => '' !== $title ? $title : (string) $post->post_title,
				'description'    => $description,
				'permalink'      => str_replace( home_url(), '', (string) get_permalink( $id ) ),
				'focus_keyword'  => '' !== $keyword ? $keyword : $primary,
				'focus_keywords' => $keywords,
				'body'           => mb_substr( $body, 0, $max_chars ),
				'body_truncated' => $length > $max_chars,
				'word_count'     => str_word_count( $body ),
				'excerpt_10pct'  => mb_substr( $body, 0, min( $tenth, $max_chars ) ),
				'headings'       => $headings,
				'images'         => $images,
				'links'          => $links,
			),
			'stored'       => array(
				'score'  => $score,
				'band'   => $band,
				'pillar' => 'on' === (string) get_post_meta( $id, 'rank_math_pillar_content', true ),
			),
			'kb_link'      => self::KB_LINK,
			'note'         => 'Rank Math runs these tests in the browser editor. This returns the material they read and the score last stored; it does not compute a score.',
		);
	}

	

	private static function get_post_schema( array $args ) {
		global $wpdb;
		$post = self::post_for_edit( $args );
		$id   = (int) $post->ID;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_id ASC",
				$id,
				$wpdb->esc_like( 'rank_math_schema' ) . '%'
			),
			ARRAY_A
		);

		$schemas = array();
		$types   = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$value = maybe_unserialize( isset( $row['meta_value'] ) ? $row['meta_value'] : '' );
			if ( empty( $value ) || ! is_array( $value ) ) {
				continue;
			}
			$type     = isset( $value['@type'] ) ? $value['@type'] : null;
			$metadata = isset( $value['metadata'] ) && is_array( $value['metadata'] ) ? $value['metadata'] : array();
			foreach ( (array) $type as $t ) {
				if ( is_string( $t ) && '' !== $t ) {
					$types[] = $t;
				}
			}
			$schemas[] = array(
				'id'         => 'schema-' . (int) $row['meta_id'],
				'meta_key'   => (string) $row['meta_key'],
				'type'       => $type,
				'title'      => isset( $metadata['title'] ) ? (string) $metadata['title'] : null,
				'is_primary' => ! empty( $metadata['isPrimary'] ),
				'source'     => isset( $metadata['type'] ) ? (string) $metadata['type'] : null,
				'shortcode'  => isset( $metadata['shortcode'] ) ? (string) $metadata['shortcode'] : null,
				'schema'     => $value,
			);
		}

		$default = empty( $schemas ) ? self::rm_helper( 'get_default_schema_type', array( $id ) ) : null;
		$default = is_string( $default ) && '' !== $default ? ucfirst( $default ) : null;
		if ( null !== $default ) {
			$types[] = $default;
		}
		$blocks = array();
		if ( has_block( 'rank-math/faq-block', $id ) ) {
			$blocks[] = 'FAQPage';
		}
		if ( has_block( 'rank-math/howto-block', $id ) ) {
			$blocks[] = 'HowTo';
		}

		$available = self::rm_helper( 'choices_rich_snippet_types', array( false, (string) $post->post_type ) );

		return array(
			'post_id'         => $id,
			'module_active'   => self::module_active( 'rich-snippet' ),
			'schemas'         => $schemas,
			'types'           => array_values( array_unique( array_merge( $types, $blocks ) ) ),
			'default_type'    => $default,
			'block_schemas'   => $blocks,
			'available_types' => is_array( $available ) ? $available : null,
			'pro_active'      => defined( 'RANK_MATH_PRO_VERSION' ),
			'note'            => 'Stored schema templates; values may contain %variables% that Rank Math fills in when the page renders. Use seo_get_head for the rendered JSON-LD.',
		);
	}

	

	private static function get_post_links( array $args ) {
		global $wpdb;
		$post  = self::post_for_edit( $args );
		$id    = (int) $post->ID;
		$links = self::table( 'rank_math_internal_links' );
		$meta  = self::table( 'rank_math_internal_meta' );

		$out = array(
			'post_id'       => $id,
			'module_active' => self::module_active( 'link-counter' ),
			'available'     => false,
		);
		if ( ! self::table_exists( $links ) || ! self::table_exists( $meta ) ) {
			$out['note'] = 'Rank Math\'s link-counter tables do not exist. Turn on the Link Counter module (Rank Math > Dashboard) and save posts to record their links.';
			return $out;
		}

		$limit = self::int_arg( $args, 'limit', 100, 1, 500 );
		$type  = isset( $args['type'] ) && in_array( $args['type'], array( 'internal', 'external' ), true ) ? $args['type'] : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are class-built from $wpdb->prefix; every value is a placeholder.
		$counts = $wpdb->get_row(
			$wpdb->prepare( "SELECT internal_link_count, external_link_count, incoming_link_count FROM {$meta} WHERE object_id = %d", $id ),
			ARRAY_A
		);
		if ( '' === $type ) {
			$outgoing = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, url, target_post_id, type FROM {$links} WHERE post_id = %d ORDER BY id ASC LIMIT %d", $id, $limit + 1 ),
				ARRAY_A
			);
		} else {
			$outgoing = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, url, target_post_id, type FROM {$links} WHERE post_id = %d AND type = %s ORDER BY id ASC LIMIT %d", $id, $type, $limit + 1 ),
				ARRAY_A
			);
		}
		$incoming = array();
		if ( self::bool_arg( $args, 'include_incoming', true ) ) {
			$incoming = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, post_id, url FROM {$links} WHERE target_post_id = %d AND type = %s ORDER BY id ASC LIMIT %d", $id, 'internal', $limit + 1 ),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$outgoing = is_array( $outgoing ) ? $outgoing : array();
		$incoming = is_array( $incoming ) ? $incoming : array();

		$out_rows = array();
		foreach ( array_slice( $outgoing, 0, $limit ) as $row ) {
			$target     = (int) $row['target_post_id'];
			$readable   = $target > 0 && current_user_can( 'read_post', $target );
			$out_rows[] = array(
				'id'             => (int) $row['id'],
				'url'            => (string) $row['url'],
				'type'           => (string) $row['type'],
				'target_post_id' => $target > 0 ? $target : null,
				'target_title'   => $readable ? get_the_title( $target ) : null,
			);
		}

		$in_rows = array();
		$hidden  = 0;
		foreach ( array_slice( $incoming, 0, $limit ) as $row ) {
			$source = (int) $row['post_id'];
			if ( ! current_user_can( 'read_post', $source ) ) {
				++$hidden;
				continue;
			}
			$in_rows[] = array(
				'id'             => (int) $row['id'],
				'source_post_id' => $source,
				'source_title'   => get_the_title( $source ),
				'url'            => (string) $row['url'],
			);
		}

		$out['available']          = true;
		$out['counts']             = is_array( $counts ) ? array(
			'internal' => (int) $counts['internal_link_count'],
			'external' => (int) $counts['external_link_count'],
			'incoming' => (int) $counts['incoming_link_count'],
		) : null;
		$out['outgoing']           = $out_rows;
		$out['outgoing_truncated'] = count( $outgoing ) > $limit;
		$out['incoming']           = $in_rows;
		$out['incoming_truncated'] = count( $incoming ) > $limit;
		$out['incoming_hidden']    = $hidden;
		$out['note']               = 'Rank Math records links when a post is saved (drafts included; auto-drafts and trash are skipped). The free plugin stores URL, source, target and type only: no anchor text, no nofollow flag.' . ( null === $out['counts'] ? ' This post has not been scanned yet.' : '' );
		return $out;
	}

	

	private static function get_link_report( array $args ) {
		global $wpdb;
		$links = self::table( 'rank_math_internal_links' );
		$meta  = self::table( 'rank_math_internal_meta' );

		$out = array(
			'module_active' => self::module_active( 'link-counter' ),
			'available'     => false,
			'pro_active'    => defined( 'RANK_MATH_PRO_VERSION' ),
		);
		if ( ! self::table_exists( $links ) || ! self::table_exists( $meta ) ) {
			$out['note'] = 'Rank Math\'s link-counter tables do not exist. Turn on the Link Counter module (Rank Math > Dashboard).';
			return $out;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names are class-built from $wpdb->prefix; no user input.
		$link_stats = $wpdb->get_row(
			"SELECT COUNT(*) AS total, SUM(CASE WHEN type = 'internal' THEN 1 ELSE 0 END) AS internal, SUM(CASE WHEN type = 'external' THEN 1 ELSE 0 END) AS external FROM {$links}",
			ARRAY_A
		);
		$post_stats = $wpdb->get_row(
			"SELECT COUNT(DISTINCT m.object_id) AS total_posts,
				SUM(CASE WHEN m.incoming_link_count IS NULL OR m.incoming_link_count = 0 THEN 1 ELSE 0 END) AS orphan_posts,
				SUM(CASE WHEN m.internal_link_count > 0 THEN 1 ELSE 0 END) AS posts_with_internal,
				SUM(CASE WHEN m.external_link_count > 0 THEN 1 ELSE 0 END) AS posts_with_external
			FROM {$meta} m
			INNER JOIN {$wpdb->posts} p ON m.object_id = p.ID
			WHERE p.post_status = 'publish'",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$link_stats  = is_array( $link_stats ) ? $link_stats : array();
		$post_stats  = is_array( $post_stats ) ? $post_stats : array();
		$total_posts = (int) ( isset( $post_stats['total_posts'] ) ? $post_stats['total_posts'] : 0 );
		$with_int    = (int) ( isset( $post_stats['posts_with_internal'] ) ? $post_stats['posts_with_internal'] : 0 );
		$with_ext    = (int) ( isset( $post_stats['posts_with_external'] ) ? $post_stats['posts_with_external'] : 0 );

		$stats = array(
			'total_links'         => (int) ( isset( $link_stats['total'] ) ? $link_stats['total'] : 0 ),
			'total_internal'      => (int) ( isset( $link_stats['internal'] ) ? $link_stats['internal'] : 0 ),
			'total_external'      => (int) ( isset( $link_stats['external'] ) ? $link_stats['external'] : 0 ),
			'total_posts'         => $total_posts,
			'orphan_posts'        => (int) ( isset( $post_stats['orphan_posts'] ) ? $post_stats['orphan_posts'] : 0 ),
			'posts_with_internal' => $with_int,
			'posts_with_external' => $with_ext,
			'posts_no_internal'   => $total_posts - $with_int,
			'posts_no_external'   => $total_posts - $with_ext,
		);

		$out['available'] = true;
		$out['stats']     = $stats;

		if ( self::bool_arg( $args, 'include_posts' ) ) {
			$out['posts'] = self::link_report_posts( $args, $meta );
		}

		if ( self::bool_arg( $args, 'include_audit' ) ) {
			
			$seed   = array(
				'stats' => array(
					'total_internal'    => $stats['total_internal'],
					'total_external'    => $stats['total_external'],
					'posts_no_internal' => $stats['posts_no_internal'],
					'posts_no_external' => $stats['posts_no_external'],
				),
			);
			$result = apply_filters( 'rank_math/abilities/link_report_result', $seed, array( 'include_posts' => false ) );
			if ( is_array( $result ) && isset( $result['audit'] ) ) {
				$out['audit_available'] = true;
				$out['audit']           = $result['audit'];
			} else {
				$out['audit_available'] = false;
				$out['audit_note']      = 'No link audit was provided. Broken-link, redirect-chain and HTTP-status data come from Rank Math PRO\'s link audit.';
			}
		}

		$out['note'] = 'Counts cover published posts Rank Math has scanned. A post is scanned when it is saved, so posts never saved since the Link Counter module was turned on are not counted.';
		return $out;
	}

	private static function link_report_posts( array $args, $meta ) {
		global $wpdb;
		$limit  = self::int_arg( $args, 'limit', 50, 1, 200 );
		$page   = self::int_arg( $args, 'page', 1, 1, 100000 );
		$type   = self::post_type_arg( $args );
		$types  = null === $type ? array( 'post', 'page' ) : array( $type );
		$orders = array(
			'post_title'          => 'p.post_title',
			'internal_link_count' => 'm.internal_link_count',
			'external_link_count' => 'm.external_link_count',
			'incoming_link_count' => 'm.incoming_link_count',
			'post_modified'       => 'p.post_modified',
		);
		$orderby = isset( $args['orderby'] ) && is_string( $args['orderby'] ) && isset( $orders[ $args['orderby'] ] ) ? $orders[ $args['orderby'] ] : 'p.post_title';
		$order   = isset( $args['order'] ) && is_string( $args['order'] ) && 'desc' === strtolower( $args['order'] ) ? 'DESC' : 'ASC';
		$filters = array(
			'orphans'     => ' AND (m.incoming_link_count IS NULL OR m.incoming_link_count = 0)',
			'no_internal' => ' AND (m.internal_link_count IS NULL OR m.internal_link_count = 0)',
			'no_external' => ' AND (m.external_link_count IS NULL OR m.external_link_count = 0)',
		);
		$filter = isset( $args['filter'] ) && is_string( $args['filter'] ) && isset( $filters[ $args['filter'] ] ) ? $args['filter'] : '';
		$where  = '' === $filter ? '' : $filters[ $filter ];

		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$params       = array_merge( $types, array( $limit, ( $page - 1 ) * $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table, filter clause and ORDER BY come from fixed maps above; values are placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS post_id, p.post_title, p.post_type, p.post_modified_gmt, m.internal_link_count, m.external_link_count, m.incoming_link_count
				FROM {$meta} m
				INNER JOIN {$wpdb->posts} p ON m.object_id = p.ID
				WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}){$where}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$list = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$incoming = (int) $row['incoming_link_count'];
			$list[]   = array(
				'post_id'      => (int) $row['post_id'],
				'title'        => (string) $row['post_title'],
				'post_type'    => (string) $row['post_type'],
				'modified_gmt' => (string) $row['post_modified_gmt'],
				'internal'     => (int) $row['internal_link_count'],
				'external'     => (int) $row['external_link_count'],
				'incoming'     => $incoming,
				'orphan'       => 0 === $incoming,
			);
		}
		return array(
			'filter'     => '' === $filter ? null : $filter,
			'post_types' => $types,
			'page'       => $page,
			'limit'      => $limit,
			'has_more'   => count( $list ) >= $limit,
			'items'      => $list,
		);
	}

	

	
	private static function audit_entry( $id, $result ) {
		$class = '\RankMath\SEO_Analysis\Result';
		if ( class_exists( $class ) ) {
			try {
				$entry = new $class( $id, $result, false );
				$data  = $entry->get_result();
				return array( (bool) $entry->is_hidden(), (int) $entry->get_score(), is_array( $data ) ? $data : null );
			} catch ( \Throwable $e ) {
				return array( true, 0, null );
			}
		}
		if ( ! is_array( $result ) ) {
			return array( true, 0, null );
		}
		$weight = isset( self::AUDIT_WEIGHTS[ $id ] ) ? self::AUDIT_WEIGHTS[ $id ] : 0;
		return array( in_array( $id, self::AUDIT_ALWAYS_HIDDEN, true ), $weight, $result );
	}

	private static function audit_site_seo( array $args ) {
		$results = get_option( 'rank_math_seo_analysis_results' );
		$date    = (int) get_option( 'rank_math_seo_analysis_date', 0 );
		$url     = get_option( 'rank_math_seo_analysis_url' );

		$out = array(
			'available'     => false,
			'module_active' => self::module_active( 'seo-analysis' ),
			'url'           => is_string( $url ) && '' !== $url ? $url : home_url(),
			'last_run_at'   => $date > 0 ? $date : null,
			'last_run_iso'  => $date > 0 ? gmdate( 'c', $date ) : null,
		);
		if ( ! is_array( $results ) || empty( $results ) ) {
			$out['note'] = 'No stored SEO Analyzer result. Run Rank Math > SEO Analyzer in wp-admin once (it sends the site URL to rankmath.com), then call this again.';
			return $out;
		}

		$status_filter = isset( $args['status'] ) && in_array( $args['status'], array( 'fail', 'warning', 'info', 'ok' ), true ) ? $args['status'] : '';
		$include_data  = self::bool_arg( $args, 'include_data' );
		$fix_hints     = '\RankMath\Abilities\SEO_Analysis\Fix_Hint_Map';
		$has_hints     = class_exists( $fix_hints ) && method_exists( $fix_hints, 'get' );

		$priority = array();
		$rest     = array();
		foreach ( $results as $id => $result ) {
			if ( is_array( $result ) && isset( $result['category'] ) && 'priority' === $result['category'] ) {
				$priority[ $id ] = $result;
			} else {
				$rest[ $id ] = $result;
			}
		}

		$statuses   = array(
			'ok'      => 0,
			'fail'    => 0,
			'warning' => 0,
			'info'    => 0,
		);
		$total      = 0;
		$score_sum  = 0;
		$score_pass = 0;
		$findings   = array();
		foreach ( array_replace( $priority, $rest ) as $id => $result ) {
			$id                             = (string) $id;
			list( $hidden, $weight, $data ) = self::audit_entry( $id, $result );
			if ( $hidden || null === $data ) {
				continue;
			}
			$status              = isset( $data['status'] ) ? (string) $data['status'] : '';
			$statuses[ $status ] = isset( $statuses[ $status ] ) ? $statuses[ $status ] + 1 : 1;
			++$total;
			$score_sum += $weight;
			if ( 'ok' === $status ) {
				$score_pass += $weight;
			}
			if ( '' !== $status_filter && $status !== $status_filter ) {
				continue;
			}
			
			$message = isset( $data['message'] ) ? $data['message'] : ( isset( $data['description'] ) ? $data['description'] : '' );
			$fix     = isset( $data['fix'] ) ? (string) $data['fix'] : '';
			$finding = array(
				'test_id'  => $id,
				'category' => isset( $data['category'] ) ? (string) $data['category'] : '',
				'status'   => $status,
				'weight'   => $weight,
				'title'    => isset( $data['title'] ) ? wp_strip_all_tags( (string) $data['title'] ) : '',
				'message'  => wp_strip_all_tags( is_scalar( $message ) ? (string) $message : '' ),
				'fix_text' => html_entity_decode( wp_strip_all_tags( $fix ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'kb_link'  => isset( $data['kb_link'] ) ? (string) $data['kb_link'] : '',
				'fix_hint' => $has_hints ? call_user_func( array( $fix_hints, 'get' ), $id ) : null,
				'auto_fix' => in_array( $id, self::FIXABLE_TESTS, true ) && 'opengraph' !== $id,
			);
			if ( $include_data ) {
				$finding['data'] = isset( $data['data'] ) ? $data['data'] : null;
			}
			$findings[] = $finding;
		}

		$percent = $score_sum > 0 ? (int) round( ( $score_pass / $score_sum ) * 100 ) : 0;
		if ( $percent < 50 ) {
			$grade = 'bad';
		} elseif ( $percent < 70 ) {
			$grade = 'average';
		} else {
			$grade = 'good';
		}

		$out['available']   = true;
		$out['score']       = $percent;
		$out['grade']       = $grade;
		$out['statuses']    = $statuses;
		$out['total_tests'] = $total;
		$out['filter']      = '' === $status_filter ? null : $status_filter;
		$out['findings']    = $findings;
		$out['note']        = 'Stored result of Rank Math\'s last SEO Analyzer run. Score is the weighted share of passing tests, as Rank Math computes it. Run a new analysis from wp-admin to refresh it.';
		return $out;
	}

	

	private static function option_writes_enabled() {
		$settings = get_option( 'mordenhost_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings['allow_option_writes'] );
	}

	private static function option_refusal( $option, $use_allowlist ) {
		if ( ! self::option_writes_enabled() ) {
			return 'Option writes are disabled. Enable "Allow AI to write WordPress options" under Mordenhost MCP > Settings > General Settings.';
		}
		if ( ! $use_allowlist ) {
			return null;
		}
		$support = '\Mordenhost_MCP\Tools\Options_Support';
		if ( ! class_exists( $support ) || call_user_func( array( $support, 'is_denylisted_option' ), $option ) ) {
			return 'Option is permanently denylisted: ' . $option;
		}
		$writable = apply_filters( 'mordenhost_mcp_writable_options', self::DEFAULT_WRITABLE_OPTIONS );
		if ( ! is_array( $writable ) ) {
			$writable = self::DEFAULT_WRITABLE_OPTIONS;
		}
		if ( ! in_array( $option, $writable, true ) ) {
			return 'Option not in allowlist: ' . $option . '. An administrator can allow it under Mordenhost MCP > Settings (writable options), or a plugin can add it through the mordenhost_mcp_writable_options filter.';
		}
		return null;
	}

	private static function keyword_from_title( $title ) {
		$words = preg_split( '/\s+/', strtolower( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$keep  = array();
		foreach ( is_array( $words ) ? $words : array() as $word ) {
			if ( '' !== preg_replace( '/[^a-z0-9]/i', '', $word ) ) {
				$keep[] = $word;
			}
		}
		return implode( ' ', array_slice( $keep, 0, 3 ) );
	}

	private static function plan_fix( $test_id, array $args ) {
		switch ( $test_id ) {
			case 'blog_public':
				$from = (string) get_option( 'blog_public' );
				return '1' === $from
					? array( 'action' => 'none', 'summary' => 'Search engines are already allowed to index the site.' )
					: array(
						'action'      => 'change',
						'summary'     => 'Set "Search engine visibility" to allow indexing (blog_public = 1).',
						'option'      => 'blog_public',
						'allowlisted' => false,
						'from'        => $from,
						'to'          => 1,
					);

			case 'permalink_structure':
				$from = (string) get_option( 'permalink_structure' );

				if ( '' !== $from && false !== strpos( $from, '%postname%' ) ) {
					return array( 'action' => 'none', 'summary' => 'The permalink structure already contains %postname% (' . $from . ').' );
				}
				return array(
					'action'      => 'change',
					'summary'     => 'Set the permalink structure to /%postname%/ and flush rewrite rules. This changes the URL of every post and page.',
					'option'      => 'permalink_structure',
					'allowlisted' => false,
					'from'        => $from,
					'to'          => '/%postname%/',
				);

			case 'site_description':
				$value = isset( $args['value'] ) && is_string( $args['value'] ) ? trim( sanitize_text_field( $args['value'] ) ) : '';
				if ( '' === $value ) {
					return array( 'action' => 'cannot_fix', 'summary' => 'A "value" is required: a short, unique tagline for the site.' );
				}
				if ( 'just another wordpress site' === strtolower( $value ) ) {
					return array( 'action' => 'cannot_fix', 'summary' => 'That is the default WordPress tagline. Supply a unique one.' );
				}
				$from = (string) get_option( 'blogdescription' );
				return $from === $value
					? array( 'action' => 'none', 'summary' => 'The tagline is already set to that value.' )
					: array(
						'action'      => 'change',
						'summary'     => 'Set the site tagline.',
						'option'      => 'blogdescription',
						'allowlisted' => false,
						'from'        => $from,
						'to'          => $value,
					);

			case 'sitemaps':
			case 'schema':
				$module = 'sitemaps' === $test_id ? 'sitemap' : 'rich-snippet';
				$from   = get_option( 'rank_math_modules', array() );
				$from   = is_array( $from ) ? array_values( $from ) : array();
				if ( in_array( $module, $from, true ) ) {
					return array( 'action' => 'none', 'summary' => 'The Rank Math ' . $module . ' module is already on.' );
				}
				return array(
					'action'      => 'change',
					'summary'     => 'Turn on the Rank Math ' . $module . ' module (through Rank Math\'s own module API, which also creates its tables).',
					'option'      => 'rank_math_modules',
					'allowlisted' => true,
					'module'      => $module,
					'from'        => $from,
					'to'          => array_values( array_unique( array_merge( $from, array( $module ) ) ) ),
				);

			case 'noindex':
				$titles = get_option( 'rank-math-options-titles', array() );
				$titles = is_array( $titles ) ? $titles : array();
				$from   = isset( $titles['robots_global'] ) ? (array) $titles['robots_global'] : null;
				$robots = array_values( array_diff( null === $from ? array( 'index', 'follow' ) : $from, array( 'noindex' ) ) );
				if ( ! in_array( 'index', $robots, true ) ) {
					$robots[] = 'index';
				}
				if ( null !== $from && array_values( $from ) === $robots ) {
					return array( 'action' => 'none', 'summary' => 'Rank Math\'s global robots setting already allows indexing.' );
				}
				return array(
					'action'      => 'change',
					'summary'     => 'Remove noindex from Rank Math\'s global robots meta (Titles & Meta > Global Meta).',
					'option'      => 'rank-math-options-titles',
					'allowlisted' => true,
					'key'         => 'robots_global',
					'from'        => $from,
					'to'          => $robots,
				);

			case 'opengraph':
				return array( 'action' => 'cannot_fix', 'summary' => 'Rank Math always outputs Open Graph tags. If this test fails, set a fallback Open Graph image at Rank Math > Titles & Meta > Global Meta > Open Graph Thumbnail.' );

			case 'robots_txt':
				$general = get_option( 'rank-math-options-general', array() );
				$general = is_array( $general ) ? $general : array();
				$from    = isset( $general['robots_txt_content'] ) ? (string) $general['robots_txt_content'] : '';
				if ( '' !== trim( $from ) ) {
					return array( 'action' => 'none', 'summary' => 'Rank Math already has robots.txt content configured.' );
				}
				return array(
					'action'      => 'change',
					'summary'     => 'Write default robots.txt rules into Rank Math\'s settings (used only when no physical robots.txt file exists).',
					'option'      => 'rank-math-options-general',
					'allowlisted' => true,
					'key'         => 'robots_txt_content',
					'from'        => $from,
					'to'          => self::ROBOTS_TXT_DEFAULT,
				);

			case 'focus_keywords':
			case 'post_titles':
				return self::plan_keyword_fix( $test_id, $args );
		}
		return array( 'action' => 'cannot_fix', 'summary' => 'No automatic fix is available for this test.' );
	}

	private static function plan_keyword_fix( $test_id, array $args ) {
		global $wpdb;
		$limit = self::int_arg( $args, 'post_limit', self::FIX_POST_LIMIT, 1, self::FIX_POST_MAX );
		$types = self::allowed_post_types();
		if ( empty( $types ) ) {
			return array( 'action' => 'cannot_fix', 'summary' => 'No post types are enabled for Rank Math\'s meta box.' );
		}

		$candidates = array(); 
		if ( 'focus_keywords' === $test_id ) {
			$ids = get_posts(
				array(
					'post_type'        => $types,
					'post_status'      => 'publish',
					'posts_per_page'   => $limit,
					'fields'           => 'ids',
					'suppress_filters' => false,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Rank Math's own query, mirrored.
						array(
							'key'     => 'rank_math_focus_keyword',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				$candidates[ (int) $id ] = null;
			}
		} else {

			$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
			$params       = array_merge(
				array( '%' . $wpdb->esc_like( 'noindex' ) . '%' ),
				$types,
				array( '&amp;', '&', '%', ',', '%', $limit )
			);
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- only $wpdb table names and a placeholder list are interpolated; values are placeholders.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} fk ON fk.post_id = p.ID AND fk.meta_key = 'rank_math_focus_keyword'
					LEFT JOIN {$wpdb->postmeta} rb ON rb.post_id = p.ID AND rb.meta_key = 'rank_math_robots'
					WHERE (rb.meta_id IS NULL OR rb.meta_value NOT LIKE %s)
					AND p.post_type IN ({$placeholders})
					AND p.post_status = 'publish'
					AND REPLACE( p.post_title, %s, %s ) NOT LIKE CONCAT( %s, SUBSTRING_INDEX( fk.meta_value, %s, 1 ), %s )
					GROUP BY p.ID
					LIMIT %d",
					$params
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$id                = (int) $row['ID'];
				$candidates[ $id ] = (string) get_post_meta( $id, 'rank_math_focus_keyword', true );
			}
		}

		$posts = array();
		foreach ( $candidates as $id => $from ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$to = self::keyword_from_title( $post->post_title );
			if ( '' === $to || $to === $from ) {
				continue;
			}
			$posts[] = array(
				'post_id'  => $id,
				'title'    => (string) $post->post_title,
				'from'     => $from,
				'to'       => $to,
				'editable' => current_user_can( 'edit_post', $id ),
			);
		}
		if ( empty( $posts ) ) {
			if ( ! empty( $candidates ) ) {
				$summary = 'Nothing to change: the posts found either already have the keyword their title gives, or their title gives none.';
			} elseif ( 'focus_keywords' === $test_id ) {
				$summary = 'Every published post already has a focus keyword.';
			} else {
				$summary = 'Every indexable post with a focus keyword already has it in the title.';
			}
			return array(
				'action'  => 'none',
				'summary' => $summary,
			);
		}
		return array(
			'action'    => 'change',
			'summary'   => 'focus_keywords' === $test_id
				? 'Set a focus keyword (the first three words of the title) on published posts that have none.'
				: 'Replace the focus keyword (all of it, secondary keywords included) with the first three words of the title on posts whose keyword is not in the title.',
			'option'    => null,
			'posts'     => $posts,
			'truncated' => count( $candidates ) >= $limit,
			'limit'     => $limit,
		);
	}

	private static function fix_site_seo( array $args ) {
		$test_id = isset( $args['test_id'] ) && is_string( $args['test_id'] ) ? sanitize_key( $args['test_id'] ) : '';
		if ( ! in_array( $test_id, self::FIXABLE_TESTS, true ) ) {
			throw new \Exception( 'test_id must be one of: ' . esc_html( implode( ', ', self::FIXABLE_TESTS ) ) . '.' );
		}

		$plan    = self::plan_fix( $test_id, $args );
		$refusal = null;
		if ( 'change' === $plan['action'] && null !== $plan['option'] ) {
			$refusal = self::option_refusal( $plan['option'], ! empty( $plan['allowlisted'] ) );
			if ( null === $refusal && 'rank_math_modules' === $plan['option'] && null === self::rm_helper_callable( 'update_modules' ) ) {
				$refusal = 'Rank Math\'s module API (Helper::update_modules) is not available.';
			}
		}

		$confirmed = ! self::bool_arg( $args, 'dry_run' )
			&& self::bool_arg( $args, 'confirm' )
			&& isset( $args['confirm_test_id'] )
			&& is_string( $args['confirm_test_id'] )
			&& sanitize_key( $args['confirm_test_id'] ) === $test_id;

		if ( ! $confirmed || 'change' !== $plan['action'] ) {
			$out = array(
				'preview' => ! $confirmed,
				'test_id' => $test_id,
				'action'  => $plan['action'],
				'summary' => $plan['summary'],
			);
			foreach ( array( 'option', 'key', 'from', 'to', 'posts', 'truncated', 'limit' ) as $field ) {
				if ( array_key_exists( $field, $plan ) ) {
					$out[ $field ] = $plan[ $field ];
				}
			}
			if ( null !== $refusal ) {
				$out['blocked'] = $refusal;
			}
			if ( 'change' === $plan['action'] && ! $confirmed ) {
				$out['message'] = 'Preview only; nothing changed. To apply, call again with confirm=true and confirm_test_id=' . $test_id . '.';
			} else {
				$out['fixed']   = 'none' === $plan['action'];
				$out['changed'] = false;
			}
			return $out;
		}

		if ( null !== $refusal ) {
			throw new \Exception( esc_html( $refusal ) );
		}
		return self::apply_fix( $test_id, $plan );
	}

	private static function rm_helper_callable( $method ) {
		$helper = '\RankMath\Helper';
		return class_exists( $helper ) && method_exists( $helper, $method ) ? array( $helper, $method ) : null;
	}

	private static function apply_fix( $test_id, array $plan ) {
		$done = array(
			'test_id' => $test_id,
			'fixed'   => true,
			'changed' => true,
			'summary' => $plan['summary'],
		);

		switch ( $test_id ) {
			case 'blog_public':
				update_option( 'blog_public', 1 );
				break;

			case 'permalink_structure':
				global $wp_rewrite;
				if ( $wp_rewrite && method_exists( $wp_rewrite, 'set_permalink_structure' ) ) {
					$wp_rewrite->set_permalink_structure( '/%postname%/' );
					$wp_rewrite->flush_rules();
				} else {
					update_option( 'permalink_structure', '/%postname%/' );
				}
				break;

			case 'site_description':
				update_option( 'blogdescription', $plan['to'] );
				break;

			case 'sitemaps':
			case 'schema':
				call_user_func( self::rm_helper_callable( 'update_modules' ), array( $plan['module'] => 'on' ) );
				break;

			case 'noindex':
			case 'robots_txt':
				$value = get_option( $plan['option'], array() );
				$value = is_array( $value ) ? $value : array();

				$value[ $plan['key'] ] = $plan['to'];
				update_option( $plan['option'], $value );
				break;

			case 'focus_keywords':
			case 'post_titles':
				$updated = 0;
				$skipped = array();
				foreach ( $plan['posts'] as $row ) {
					
					if ( ! current_user_can( 'edit_post', $row['post_id'] ) ) {
						$skipped[] = $row['post_id'];
						continue;
					}
					update_post_meta( $row['post_id'], 'rank_math_focus_keyword', $row['to'] );
					++$updated;
				}
				
				$done['fixed']   = empty( $skipped ) && empty( $plan['truncated'] );
				$done['changed'] = $updated > 0;
				$done['details'] = array(
					'updated'               => $updated,
					'skipped_no_permission' => $skipped,
					'truncated'             => ! empty( $plan['truncated'] ),
				);
				if ( ! empty( $plan['truncated'] ) ) {
					$done['summary'] .= ' Limited to ' . (int) $plan['limit'] . ' posts; run again to continue.';
				}
				break;
		}

		if ( array_key_exists( 'from', $plan ) ) {
			$done['from'] = $plan['from'];
			$done['to']   = $plan['to'];
		}
		$done['note'] = 'Run a new SEO Analyzer pass in wp-admin to refresh the stored audit.';
		return $done;
	}

	

	
	private static function console_connected() {
		$profile = get_option( 'rank_math_google_analytic_profile' );
		return is_array( $profile ) && ! empty( $profile['profile'] );
	}

	private static function metric( array $row, $key ) {
		if ( isset( $row[ $key ] ) && is_array( $row[ $key ] ) ) {
			return array(
				isset( $row[ $key ]['total'] ) ? $row[ $key ]['total'] : 0,
				isset( $row[ $key ]['difference'] ) ? $row[ $key ]['difference'] : 0,
			);
		}
		return array( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? $row[ $key ] : 0, 0 );
	}

	private static function get_top_keywords( array $args ) {
		$range = isset( $args['date_range'] ) && is_string( $args['date_range'] ) && isset( self::DATE_RANGES[ $args['date_range'] ] ) ? $args['date_range'] : 'last_30_days';
		$limit = self::int_arg( $args, 'limit', 25, 1, 100 );

		$out = array(
			'date_range'    => $range,
			'module_active' => self::module_active( 'analytics' ),
			'connected'     => self::console_connected(),
			'available'     => false,
			'keywords'      => array(),
		);
		if ( ! $out['module_active'] ) {
			$out['note'] = 'The Rank Math Analytics module is off. Turn it on under Rank Math > Dashboard.';
			return $out;
		}
		if ( ! $out['connected'] ) {
			$out['note'] = 'Rank Math is not connected to Google Search Console. Connect it under Rank Math > General Settings > Analytics.';
			return $out;
		}
		if ( ! self::table_exists( self::table( 'rank_math_analytics_gsc' ) ) ) {
			$out['note'] = 'Rank Math has not stored any Search Console data yet (no rank_math_analytics_gsc table).';
			return $out;
		}
		$stats_class = '\RankMath\Analytics\Stats';
		if ( ! class_exists( $stats_class ) || ! method_exists( $stats_class, 'get' ) ) {
			$out['note'] = 'Rank Math\'s analytics reader is not loaded.';
			return $out;
		}
		$stats = call_user_func( array( $stats_class, 'get' ) );
		if ( ! is_object( $stats ) || ! method_exists( $stats, 'set_date_range' ) || ! method_exists( $stats, 'get_analytics_data' ) ) {
			$out['note'] = 'Rank Math\'s analytics reader is not loaded.';
			return $out;
		}

		$stats->set_date_range( self::DATE_RANGES[ $range ] );
		$rows = $stats->get_analytics_data(
			array(
				'dimension' => 'query',
				'orderBy'   => 'clicks',
				'order'     => 'DESC',
				'offset'    => 0,
				'perpage'   => $limit,
				'objects'   => false,
				'pageview'  => false,
			)
		);

		$keywords = array();
		foreach ( is_array( $rows ) ? $rows : array() as $key => $row ) {
			$row = (array) $row;
			
			$query                     = isset( $row['query'] ) && is_scalar( $row['query'] ) ? (string) $row['query'] : (string) $key;
			list( $clicks, $d_clicks ) = self::metric( $row, 'clicks' );
			list( $impr, $d_impr )     = self::metric( $row, 'impressions' );
			list( $ctr, $d_ctr )       = self::metric( $row, 'ctr' );
			list( $pos, $d_pos )       = self::metric( $row, 'position' );
			$keywords[]                = array(
				'keyword'     => $query,
				'clicks'      => (int) $clicks,
				'impressions' => (int) $impr,
				'ctr'         => (float) $ctr,
				'position'    => (float) $pos,
				'change'      => array(
					'clicks'      => (int) $d_clicks,
					'impressions' => (int) $d_impr,
					'ctr'         => (float) $d_ctr,
					'position'    => (float) $d_pos,
				),
			);
		}
		
		$filtered = apply_filters( 'rank_math/analytics/ability/top_keywords', $keywords, $range );

		$out['available'] = true;
		$out['keywords']  = array_slice( is_array( $filtered ) ? array_values( $filtered ) : $keywords, 0, $limit );
		$out['period']    = array(
			'start' => isset( $stats->start_date ) ? (string) $stats->start_date : null,
			'end'   => isset( $stats->end_date ) ? (string) $stats->end_date : null,
		);
		$out['note']      = 'From Search Console data Rank Math has already synced; the period ends three days ago, as in Rank Math\'s Analytics screen.';
		return $out;
	}
}
