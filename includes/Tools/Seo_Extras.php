<?php

namespace More_MCP\Tools;

use More_MCP\SEO\Detector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo_Extras implements Handler {

	const TOOLS = array( 'seo_get_head', 'seo_get_content_analysis' );

	const MAX_HTML = 60000;

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'seo_get_head',
				'description' => 'Get the SEO <head> output for a post as search engines and social networks see it: title, meta description, robots, canonical, Open Graph and Twitter tags, and the JSON-LD schema. Built by the active SEO plugin (Yoast SEO and Rank Math directly; any other plugin through its wp_head output). Give post_id, or a url on this site.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post, page or custom post type entry ID' ),
						'url'     => array( 'type' => 'string', 'description' => 'URL on this site (used when post_id is not given)' ),
					),
				),
			),
			array(
				'name'        => 'seo_get_content_analysis',
				'description' => 'Get the content-analysis scores the active SEO plugin has stored for a post: SEO score, readability score, focus keyword and the plugin\'s own rating. Plugins compute these in the post editor, so a post that has never been opened there has none and the result says so. Supports Yoast SEO, Rank Math, SEOPress, SiteSEO, SureRank and All in One SEO.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post, page or custom post type entry ID' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read SEO data.' );
		}
		switch ( $name ) {
			case 'seo_get_head':
				return self::get_head( $args );
			case 'seo_get_content_analysis':
				return self::get_content_analysis( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function post_for( array $args ): \WP_Post {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 && ! empty( $args['url'] ) ) {
			$post_id = (int) url_to_postid( esc_url_raw( (string) $args['url'] ) );
			if ( $post_id <= 0 ) {
				throw new \Exception( 'That URL does not match a post on this site.' );
			}
		}
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id (or url) is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post not found: ' . esc_html( (string) $post_id ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to read SEO data on this post.' );
		}
		return $post;
	}

	

	private static function get_head( array $args ): array {
		$post     = self::post_for( $args );
		$provider = Detector::primary();
		$source   = 'wp_head';
		$html     = '';
		$json     = null;

		try {
			if ( 'yoast' === $provider && function_exists( 'YoastSEO' ) ) {
				$meta = YoastSEO()->meta->for_post( $post->ID );
				if ( $meta ) {
					$head   = $meta->get_head();
					$html   = (string) ( $head->html ?? '' );
					$json   = $head->json ?? null;
					$source = 'yoast';
				}
			} elseif ( 'rankmath' === $provider && function_exists( 'rest_do_request' ) ) {
				$request = new \WP_REST_Request( 'GET', '/rankmath/v1/getHead' );
				$request->set_param( 'url', get_permalink( $post ) );
				$response = rest_do_request( $request );
				$data     = $response->get_data();
				if ( 200 === $response->get_status() && is_array( $data ) && ! empty( $data['head'] ) ) {
					$html   = (string) $data['head'];
					$source = 'rankmath';
				}
			}
		} catch ( \Throwable $e ) {
			$html = '';
		}

		if ( '' === $html ) {
			$html   = self::capture_wp_head( $post, $provider );
			$source = 'wp_head';
		}

		$tags = self::seo_tags( $html );
		$out  = array(
			'post_id'   => (int) $post->ID,
			'permalink' => get_permalink( $post ),
			'plugin'    => $provider,
			'source'    => $source,
			'detection' => Detector::report(),
		);
		$out = array_merge( $out, self::summarise( $tags ) );

		$joined = implode( "\n", $tags );
		if ( strlen( $joined ) > self::MAX_HTML ) {
			$out['html']      = substr( $joined, 0, self::MAX_HTML );
			$out['truncated'] = true;
		} else {
			$out['html'] = $joined;
		}
		return $out;
	}

	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- the main query is swapped for one wp_head run and restored in finally.
	
	private static function capture_wp_head( \WP_Post $post, string $provider = '' ): string {
		global $wp_query, $wp_the_query;
		$saved = array( $wp_query, $wp_the_query, $GLOBALS['post'] ?? null );

		$level = ob_get_level();
		try {
			$query = new \WP_Query(
				array(
					'p'           => $post->ID,
					'post_type'   => $post->post_type,
					'post_status' => 'any',
				)
			);
			if ( 'page' === $post->post_type ) {
				$query = new \WP_Query( array( 'page_id' => $post->ID, 'post_status' => 'any' ) );
			}
			$wp_query     = $query;
			$wp_the_query = $query;
			$GLOBALS['post'] = $post;
			setup_postdata( $post );

			self::prime_provider( $provider );

			ob_start();
			do_action( 'wp_head' );
			$head = (string) ob_get_clean();

			
			
			if ( ! preg_match( '#<title\b#i', $head ) ) {
				$title = wp_get_document_title();
				if ( '' !== $title ) {
					$head = '<title>' . esc_html( $title ) . "</title>\n" . $head;
				}
			}
			return $head;
		} catch ( \Throwable $e ) {
			return '';
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			$wp_query        = $saved[0];
			$wp_the_query    = $saved[1];
			$GLOBALS['post'] = $saved[2];
			if ( $saved[2] instanceof \WP_Post ) {
				setup_postdata( $saved[2] );
			} else {
				wp_reset_postdata();
			}
		}
	}

	// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

	private static function prime_provider( string $provider ): void {
		if ( 'surerank' === $provider ) {

			try {
				if ( class_exists( '\\SureRank\\Inc\\Frontend\\Meta_Data' ) ) {
					$meta_data = \SureRank\Inc\Frontend\Meta_Data::get_instance();
					if ( method_exists( $meta_data, 'reset_meta_data' ) ) {
						$meta_data->reset_meta_data();
					}
					$meta_data->set_meta_data();
				}
			} catch ( \Throwable $e ) {
				return;
			}
			return;
		}
		if ( 'rankmath' !== $provider || ! function_exists( 'rank_math' ) ) {
			return;
		}
		try {
			$rank_math = rank_math();
			if ( isset( $rank_math->head ) || ! isset( $rank_math->frontend ) || ! method_exists( $rank_math->frontend, 'integrations' ) ) {
				return;
			}
			$rank_math->frontend->integrations();
		} catch ( \Throwable $e ) {
			return;
		}
	}

	public static function seo_tags( string $html ): array {
		$tags = array();
		if ( preg_match_all( '#<title\b[^>]*>.*?</title>#is', $html, $found ) ) {
			$tags = array_merge( $tags, $found[0] );
		}
		if ( preg_match_all( '#<meta\b[^>]*>#i', $html, $found ) ) {
			foreach ( $found[0] as $tag ) {
				if ( preg_match( '#\bcharset\b|\bviewport\b|http-equiv#i', $tag ) ) {
					continue;
				}
				$tags[] = $tag;
			}
		}
		if ( preg_match_all( '#<link\b[^>]*>#i', $html, $found ) ) {
			foreach ( $found[0] as $tag ) {
				if ( preg_match( '#\brel=["\']?(canonical|alternate|prev|next|shortlink|amphtml)\b#i', $tag ) ) {
					$tags[] = $tag;
				}
			}
		}
		if ( preg_match_all( '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>#is', $html, $found ) ) {
			$tags = array_merge( $tags, $found[0] );
		}
		return array_values( array_map( 'trim', $tags ) );
	}

	public static function summarise( array $tags ): array {
		$title = null;
		$canonical = null;
		$meta   = array();
		$schema = array();

		foreach ( $tags as $tag ) {
			if ( 0 === stripos( $tag, '<title' ) ) {
				$title = trim( html_entity_decode( wp_strip_all_tags( $tag ), ENT_QUOTES, 'UTF-8' ) );
			} elseif ( 0 === stripos( $tag, '<meta' ) ) {
				$key = null;
				if ( preg_match( '#\b(?:name|property)=("|\')(.*?)\1#i', $tag, $k ) ) {
					$key = strtolower( html_entity_decode( $k[2] ) );
				}
				if ( null !== $key && preg_match( '#\bcontent=("|\')(.*?)\1#is', $tag, $v ) ) {
					$meta[ $key ] = html_entity_decode( $v[2], ENT_QUOTES );
				}
			} elseif ( 0 === stripos( $tag, '<link' ) ) {
				if ( preg_match( '#\brel=["\']?canonical\b#i', $tag ) && preg_match( '#\bhref=("|\')(.*?)\1#i', $tag, $h ) ) {
					$canonical = html_entity_decode( $h[2] );
				}
			} elseif ( 0 === stripos( $tag, '<script' ) ) {
				$body    = trim( (string) preg_replace( '#^<script\b[^>]*>|</script>$#i', '', $tag ) );
				$decoded = json_decode( $body, true );
				if ( is_array( $decoded ) ) {
					$schema[] = $decoded;
				}
			}
		}

		$og      = array();
		$twitter = array();
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, 'og:' ) || 0 === strpos( $key, 'article:' ) ) {
				$og[ $key ] = $value;
			} elseif ( 0 === strpos( $key, 'twitter:' ) ) {
				$twitter[ $key ] = $value;
			}
		}
		return array(
			'title'       => $title,
			'description' => $meta['description'] ?? null,
			'robots'      => $meta['robots'] ?? null,
			'canonical'   => $canonical,
			'open_graph'  => (object) $og,
			'twitter'     => (object) $twitter,
			'schema'      => $schema,
		);
	}

	

	private static function get_content_analysis( array $args ): array {
		$post     = self::post_for( $args );
		$id       = (int) $post->ID;
		$provider = Detector::primary();

		$out = array(
			'post_id'     => $id,
			'plugin'      => $provider,
			'detection'   => Detector::report(),
			'available'   => false,
			'seo'         => null,
			'readability' => null,
		);

		switch ( $provider ) {
			case 'yoast':
				$seo  = get_post_meta( $id, '_yoast_wpseo_linkdex', true );
				$read = get_post_meta( $id, '_yoast_wpseo_content_score', true );
				$out['focus_keyword']  = (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true );
				$out['cornerstone']    = '1' === (string) get_post_meta( $id, '_yoast_wpseo_is_cornerstone', true );
				$out['seo']            = self::score( $seo, array( 40, 70 ) );
				$out['readability']    = self::score( $read, array( 40, 70 ) );
				break;

			case 'rankmath':
				$out['focus_keyword'] = (string) get_post_meta( $id, 'rank_math_focus_keyword', true );
				$out['pillar']        = 'on' === (string) get_post_meta( $id, 'rank_math_pillar_content', true );
				$out['seo']           = self::score( get_post_meta( $id, 'rank_math_seo_score', true ), array( 50, 80 ) );
				$out['note']          = 'Rank Math has no readability score.';
				break;

			case 'seopress':
				$out = array_merge( $out, self::seopress( $id ) );
				break;

			case 'siteseo':
				$out['focus_keyword'] = (string) get_post_meta( $id, '_siteseo_analysis_target_kw', true );
				
				$out['seo']           = self::score( get_post_meta( $id, '_siteseo_score', true ), array( 49, 79 ) );
				$out['note']          = 'SiteSEO has no separate readability score; its overall score is computed in the post editor.';
				break;

			case 'surerank':
				$out = array_merge( $out, self::surerank( $id ) );
				break;

			case 'aioseo':
				$out = array_merge( $out, self::aioseo( $id ) );
				break;

			default:
				$out['note'] = 'none' === $provider
					? 'No SEO plugin is active, so there is no content analysis.'
					: 'This SEO plugin does not store a content analysis. Supported: Yoast SEO, Rank Math, SEOPress, SiteSEO, SureRank and All in One SEO.';
		}

		$out['available'] = null !== $out['seo'] || null !== $out['readability'];
		if ( ! $out['available'] && empty( $out['note'] ) ) {
			$out['note'] = 'No analysis is stored for this post yet. The plugin computes it in the post editor; open the post there once and save.';
		}
		return $out;
	}

	public static function score( $raw, array $bounds ): ?array {
		if ( '' === $raw || null === $raw || false === $raw || ! is_numeric( $raw ) || (int) $raw <= 0 ) {
			return null;
		}
		$score = (int) $raw;
		if ( $score <= $bounds[0] ) {
			$rating = 'bad';
		} elseif ( $score <= $bounds[1] ) {
			$rating = 'ok';
		} else {
			$rating = 'good';
		}
		return array(
			'score'  => $score,
			'rating' => $rating,
		);
	}

	private static function seopress( int $id ): array {
		$out = array(
			'focus_keyword' => (string) get_post_meta( $id, '_seopress_analysis_target_kw', true ),
		);

		
		$checks = null;
		$when   = null;
		if ( function_exists( 'seopress_get_service' ) ) {
			try {
				$rows = seopress_get_service( 'ContentAnalysisDatabase' )->getData( $id, array( 'score', 'analysis_date' ) );
				if ( is_array( $rows ) && ! empty( $rows ) ) {
					$row    = reset( $rows );
					$checks = is_array( $row ) ? ( $row['score'] ?? null ) : null;
					$when   = is_array( $row ) ? ( $row['analysis_date'] ?? null ) : null;
				}
			} catch ( \Throwable $e ) {
				$checks = null;
			}
		}
		if ( ! is_array( $checks ) ) {
			$legacy = get_post_meta( $id, '_seopress_analysis_data', true );
			if ( is_array( $legacy ) && isset( $legacy['score'] ) && is_array( $legacy['score'] ) ) {
				$checks = $legacy['score'];
			}
		}
		if ( ! is_array( $checks ) || empty( $checks ) ) {
			return $out;
		}

		$counts = array( 'good' => 0, 'low' => 0, 'medium' => 0, 'high' => 0 );
		foreach ( $checks as $impact ) {
			if ( is_string( $impact ) && isset( $counts[ $impact ] ) ) {
				++$counts[ $impact ];
			}
		}
		$total = array_sum( $counts );
		if ( 0 === $total ) {
			return $out;
		}
		$out['seo'] = array(
			'rating' => ( $counts['medium'] > 0 || $counts['high'] > 0 ) ? 'needs_improvement' : 'good',
			'checks' => $counts,
		);
		if ( $when ) {
			$out['analysed_at'] = is_object( $when ) && method_exists( $when, 'format' ) ? $when->format( 'Y-m-d H:i:s' ) : (string) $when;
		}
		$out['note'] = 'SEOPress rates each check good, low, medium or high impact; it has no numeric score and no readability score.';
		return $out;
	}

	private static function surerank( int $id ): array {
		$general = get_post_meta( $id, 'surerank_settings_general', true );
		$out     = array(
			'focus_keyword' => is_array( $general ) ? (string) ( $general['focus_keyword'] ?? '' ) : '',
		);

		
		$checks = get_post_meta( $id, 'surerank_seo_checks', true );
		if ( ! is_array( $checks ) || empty( $checks ) ) {
			return $out;
		}
		$ignored = get_post_meta( $id, 'surerank_ignored_post_checks', true );
		$ignored = is_array( $ignored ) ? array_map( 'strval', $ignored ) : array();

		$counts = array( 'success' => 0, 'warning' => 0, 'error' => 0 );
		foreach ( $checks as $check_id => $check ) {
			if ( in_array( (string) $check_id, $ignored, true ) || ! is_array( $check ) || ! empty( $check['ignore'] ) ) {
				continue;
			}
			$status = isset( $check['status'] ) ? (string) $check['status'] : '';
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}
		if ( 0 === array_sum( $counts ) ) {
			return $out;
		}
		if ( $counts['error'] > 0 ) {
			$rating = 'needs_improvement';
		} elseif ( $counts['warning'] > 0 ) {
			$rating = 'ok';
		} else {
			$rating = 'good';
		}
		$out['seo']  = array(
			'rating' => $rating,
			'checks' => $counts,
		);
		$when = get_post_meta( $id, 'surerank_seo_checks_last_updated', true );
		if ( is_numeric( $when ) && (int) $when > 0 ) {
			$out['analysed_at'] = gmdate( 'Y-m-d H:i:s', (int) $when );
		}
		$out['note'] = 'SureRank rates each check success, warning or error; it has no numeric score and no readability score.';
		return $out;
	}

	private static function aioseo( int $id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- All in One SEO keeps its post data in its own table.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $exists !== $table ) {
			return array( 'note' => 'The All in One SEO post table was not found.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is $wpdb->prefix plus a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT seo_score, keyphrases FROM {$table} WHERE post_id = %d", $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return array();
		}
		$out = array();
		$out['seo'] = self::score( $row['seo_score'] ?? null, array( 40, 70 ) );
		$keyphrases = json_decode( (string) ( $row['keyphrases'] ?? '' ), true );
		if ( is_array( $keyphrases ) && ! empty( $keyphrases['focus']['keyphrase'] ) ) {
			$out['focus_keyword'] = (string) $keyphrases['focus']['keyphrase'];
		}
		$out['note'] = 'All in One SEO has no readability score.';
		return $out;
	}
}
