<?php

namespace Mordenhost_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Llms_Txt {

	const OPTION = 'mordenhost_mcp_llms_txt';

	const MAX_BYTES = 524288;

	const DEFAULT_LIMIT = 50;
	const MAX_LIMIT     = 200;
	const MAX_SECTIONS  = 20;

	public static function register(): void {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	public static function url(): string {
		return home_url( '/llms.txt' );
	}

	

	
	public static function state(): array {
		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'content'    => isset( $raw['content'] ) && is_string( $raw['content'] ) ? $raw['content'] : '',
			'enabled'    => ! empty( $raw['enabled'] ),
			'source'     => isset( $raw['source'] ) && is_string( $raw['source'] ) ? $raw['source'] : '',
			'updated_at' => isset( $raw['updated_at'] ) && is_string( $raw['updated_at'] ) ? $raw['updated_at'] : '',
			'updated_by' => isset( $raw['updated_by'] ) ? (int) $raw['updated_by'] : 0,
		);
	}

	public static function save( string $content, bool $enabled, string $source ): bool {
		if ( '' === $content ) {
			$enabled = false;
		}
		$current = self::state();
		if ( $current['content'] === $content && $current['enabled'] === $enabled ) {
			return false;
		}
		update_option(
			self::OPTION,
			array(
				'content'    => $content,
				'enabled'    => $enabled,
				'source'     => '' === $content ? '' : $source,
				'updated_at' => gmdate( 'c' ),
				'updated_by' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			),
			false
		);
		return true;
	}

	public static function sanitize_content( string $content ): string {
		if ( '' !== $content && ! preg_match( '//u', $content ) ) {
			throw new \Exception( 'The llms.txt content is not valid UTF-8.' );
		}
		$content = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), $content );
		$content = rtrim( $content );
		if ( '' === $content ) {
			return '';
		}
		$content .= "\n";
		if ( strlen( $content ) > self::MAX_BYTES ) {
			throw new \Exception( 'The llms.txt content is larger than ' . (int) ( self::MAX_BYTES / 1024 ) . ' KB.' );
		}
		return $content;
	}

	public static function format_warnings( string $content ): array {
		if ( '' === $content ) {
			return array();
		}
		$warnings = array();
		$first    = '';
		foreach ( explode( "\n", $content ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$first = $line;
				break;
			}
		}
		if ( 0 !== strpos( $first, '# ' ) ) {
			$warnings[] = 'The llms.txt format starts with an H1 line ("# Site name"); this text does not.';
		}
		return $warnings;
	}

	

	
	public static function conflicts(): array {
		$found = array();

		foreach ( self::static_files() as $where ) {
			$found[] = array(
				'source' => 'static_file',
				'label'  => 'Static llms.txt file',
				'detail' => 'A file named llms.txt exists in ' . $where . '. The web server serves it before WordPress runs. If a plugin wrote it, turn that plugin\'s llms.txt feature off (it removes its file); otherwise delete or rename the file.',
			);
		}

		if ( defined( 'WEBSITE_LLMS_TXT_VERSION' ) ) {
			$found[] = array(
				'source' => 'website-llms-txt',
				'label'  => 'Website LLMs.txt',
				'detail' => 'The Website LLMs.txt plugin is active and serves /llms.txt. Deactivate it to manage llms.txt here, or keep editing it in Settings > LLMs.txt.',
			);
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			$wpseo = get_option( 'wpseo', array() );
			if ( is_array( $wpseo ) && ! empty( $wpseo['enable_llms_txt'] ) ) {
				$found[] = array(
					'source' => 'yoast',
					'label'  => 'Yoast SEO llms.txt',
					'detail' => 'Yoast SEO\'s llms.txt feature is on (Yoast SEO > Settings > Site features). Turn it off to manage llms.txt here.',
				);
			}
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$modules = get_option( 'rank_math_modules', array() );
			if ( is_array( $modules ) && in_array( 'llms-txt', $modules, true ) ) {
				$found[] = array(
					'source' => 'rankmath',
					'label'  => 'Rank Math LLMS Txt module',
					'detail' => 'Rank Math\'s LLMS Txt module is active (Rank Math SEO > Dashboard). Turn the module off to manage llms.txt here, or edit it in Rank Math SEO > General Settings > Edit llms.txt.',
				);
			}
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			$aioseo = get_option( 'aioseo_options', '' );
			$aioseo = is_string( $aioseo ) ? json_decode( $aioseo, true ) : $aioseo;
			if ( is_array( $aioseo ) && ! empty( $aioseo['sitemap']['llms']['enable'] ) ) {
				$found[] = array(
					'source' => 'aioseo',
					'label'  => 'All in One SEO llms.txt',
					'detail' => 'All in One SEO\'s llms.txt is enabled (All in One SEO > Sitemaps > LLMs.txt). Disable it to manage llms.txt here.',
				);
			}
		}

		$found = apply_filters( 'mordenhost_mcp_llms_txt_conflicts', $found );
		return is_array( $found ) ? array_values( array_filter( $found, 'is_array' ) ) : array();
	}

	public static function static_files(): array {
		$dirs = array( 'the WordPress directory' => ABSPATH );
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$dirs['the web root (the parent of wp-content)'] = dirname( WP_CONTENT_DIR ) . '/';
		}
		if ( function_exists( 'get_home_path' ) ) {
			$dirs['the site\'s home directory'] = (string) get_home_path();
		}

		$found = array();
		$seen  = array();
		foreach ( $dirs as $label => $dir ) {
			$file = rtrim( (string) $dir, '/\\' ) . '/llms.txt';
			if ( isset( $seen[ $file ] ) || ! is_file( $file ) ) {
				continue;
			}
			$seen[ $file ] = true;
			$found[]       = $label;
		}
		return $found;
	}

	public static function status(): array {
		$state     = self::state();
		$conflicts = self::conflicts();
		if ( '' === $state['content'] ) {
			$reason = 'Nothing is stored. Build a draft with wp_generate_llms_txt.';
		} elseif ( ! $state['enabled'] ) {
			$reason = 'Serving is turned off. Turn it on with wp_update_llms_txt (enabled: true).';
		} elseif ( $conflicts ) {
			$reason = 'Another source serves /llms.txt (' . implode( ', ', array_column( $conflicts, 'label' ) ) . '), so this copy is not served.';
		} else {
			$reason = 'Served at ' . self::url() . '.';
		}
		return array(
			'served'    => '' !== $state['content'] && $state['enabled'] && ! $conflicts,
			'reason'    => $reason,
			'url'       => self::url(),
			'conflicts' => $conflicts,
		);
	}

	public static function refuse_if_conflicting( string $tool ): void {
		$conflicts = self::conflicts();
		if ( ! $conflicts ) {
			return;
		}
		$lines = array();
		foreach ( $conflicts as $conflict ) {
			$lines[] = ( $conflict['label'] ?? 'Another source' ) . ': ' . ( $conflict['detail'] ?? '' );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
		throw new \Exception(
			'Not saved: /llms.txt is already served by another source, and serving a second copy would put two answers on one URL. '
			. implode( ' ', $lines )
			. ( 'wp_generate_llms_txt' === $tool ? ' Pass dry_run: true to see the draft without saving it.' : '' )
		);
	}

	

	
	public static function is_llms_request( string $uri ): bool {
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = is_string( $home ) ? rtrim( $home, '/' ) : '';
		return $home . '/llms.txt' === $path;
	}

	public static function body_for_request( string $uri, string $method ): ?string {
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) || ! self::is_llms_request( $uri ) ) {
			return null;
		}
		$state = self::state();
		if ( ! $state['enabled'] || '' === $state['content'] || self::conflicts() ) {
			return null;
		}
		return $state['content'];
	}

	public static function maybe_serve( $wp = null, bool $terminate = true ): void {
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( '' === $uri || ! self::is_llms_request( $uri ) ) {
			return;
		}
		$body = self::body_for_request( $uri, $method );
		if ( null === $body ) {
			return;
		}
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 200 );
			}
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: public, max-age=300' );
		}
		if ( 'HEAD' !== strtoupper( $method ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Served as text/plain with nosniff; HTML-escaping would corrupt the Markdown.
			echo $body;
		}
		if ( $terminate ) {
			exit;
		}
	}

	public static function purge_cache(): array {
		$url  = self::url();
		$done = array();
		if ( function_exists( 'has_action' ) && has_action( 'litespeed_purge_url' ) ) {
			do_action( 'litespeed_purge_url', $url );
			$done[] = 'litespeed';
		}
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( array( $url ) );
			$done[] = 'wp-rocket';
		}
		
		do_action( 'mordenhost_mcp_llms_txt_updated', $url );
		return $done;
	}

	

	
	public static function generate( array $args ): array {
		$types = isset( $args['post_types'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) $args['post_types'] ) ) ) : array( 'page', 'post' );
		if ( ! $types ) {
			throw new \Exception( 'post_types must name at least one post type.' );
		}
		foreach ( $types as $type ) {
			if ( 'attachment' === $type || ! post_type_exists( $type ) || ! is_post_type_viewable( $type ) ) {
				throw new \Exception( 'Post type "' . esc_html( $type ) . '" does not exist or has no public pages.' );
			}
		}
		$exclude = array_values( array_filter( array_map( 'absint', (array) ( $args['exclude_ids'] ?? array() ) ) ) );
		$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : self::DEFAULT_LIMIT;
		$limit   = max( 1, min( self::MAX_LIMIT, $limit ) );

		$title       = self::plain( (string) get_bloginfo( 'name' ) );
		$description = array_key_exists( 'description', $args ) ? (string) $args['description'] : (string) get_bloginfo( 'description' );
		$description = self::plain( $description );
		$custom      = self::custom_sections( $args['custom_sections'] ?? array() );

		$lines   = array( '# ' . ( '' !== $title ? $title : (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ), '' );
		if ( '' !== $description ) {
			$lines[] = '> ' . $description;
			$lines[] = '';
		}

		$noindex  = 'none' !== Detector::primary();
		$sections = array();
		$skipped  = array(
			'excluded' => count( $exclude ),
			'noindex'  => 0,
			'untitled' => 0,
		);
		foreach ( $types as $type ) {
			$items = self::items_for_type( $type, $limit, $exclude, $noindex, $skipped );
			if ( ! $items ) {
				continue;
			}
			$object = get_post_type_object( $type );
			$label  = $object && isset( $object->labels->name ) ? self::plain( (string) $object->labels->name ) : ucfirst( $type );
			$lines[] = '## ' . $label;
			$lines[] = '';
			foreach ( $items as $item ) {
				$lines[] = $item;
			}
			$lines[]    = '';
			$sections[] = array(
				'title'     => $label,
				'post_type' => $type,
				'count'     => count( $items ),
			);
		}

		foreach ( $custom as $section ) {
			$lines[] = '## ' . $section['title'];
			$lines[] = '';
			$lines[] = $section['content'];
			$lines[] = '';
			$sections[] = array(
				'title'  => $section['title'],
				'custom' => true,
			);
		}

		return array(
			'content'  => self::sanitize_content( implode( "\n", $lines ) ),
			'sections' => $sections,
			'skipped'  => $skipped,
		);
	}

	private static function items_for_type( string $type, int $limit, array $exclude, bool $check_noindex, array &$skipped ): array {
		$hierarchical = is_post_type_hierarchical( $type );
		$posts        = get_posts(
			array(
				'post_type'           => $type,
				'post_status'         => 'publish',
				'posts_per_page'      => min( $limit * 2, self::MAX_LIMIT * 2 ),
				'orderby'             => $hierarchical ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : 'date',
				'order'               => $hierarchical ? 'ASC' : 'DESC',
				'post__not_in'        => $exclude,
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			)
		);
		$posts = is_array( $posts ) ? $posts : array();

		if ( 'page' === $type && 'page' === get_option( 'show_on_front' ) ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front > 0 && ! in_array( $front, $exclude, true ) ) {
				$ordered = array();
				$found   = null;
				foreach ( $posts as $post ) {
					if ( (int) $post->ID === $front ) {
						$found = $post;
					} else {
						$ordered[] = $post;
					}
				}
				if ( null === $found ) {
					$candidate = get_post( $front );
					if ( $candidate && 'publish' === $candidate->post_status && 'page' === $candidate->post_type && '' === (string) $candidate->post_password ) {
						$found = $candidate;
					}
				}
				$posts = null === $found ? $ordered : array_merge( array( $found ), $ordered );
			}
		}

		$items = array();
		foreach ( $posts as $post ) {
			if ( count( $items ) >= $limit ) {
				break;
			}
			if ( ! empty( $post->post_password ) ) {
				continue;
			}
			if ( $check_noindex ) {
				$meta = Meta::read( 'post', (int) $post->ID );
				if ( ! empty( $meta['noindex'] ) ) {
					++$skipped['noindex'];
					continue;
				}
			}
			$title = self::plain( (string) get_the_title( $post ) );
			if ( '' === $title ) {
				++$skipped['untitled'];
				continue;
			}
			$url = (string) get_permalink( $post );
			if ( '' === $url ) {
				continue;
			}
			$line    = '- [' . self::link_text( $title ) . '](' . strtr( $url, array( ' ' => '%20', '(' => '%28', ')' => '%29' ) ) . ')';
			$summary = self::summary( $post );
			if ( '' !== $summary ) {
				$line .= ': ' . $summary;
			}
			$items[] = $line;
		}
		return $items;
	}

	private static function summary( $post ): string {
		$text = (string) $post->post_excerpt;
		if ( '' === trim( $text ) ) {
			$text = (string) $post->post_content;
			if ( function_exists( 'excerpt_remove_blocks' ) ) {
				$text = excerpt_remove_blocks( $text );
			}
			$text = strip_shortcodes( $text );
		}
		$text = self::plain( $text );
		if ( '' === $text ) {
			return '';
		}
		return self::plain( wp_trim_words( $text, 30, '…' ) );
	}

	private static function custom_sections( $sections ): array {
		if ( ! is_array( $sections ) ) {
			throw new \Exception( 'custom_sections must be a list of {title, content} objects.' );
		}
		if ( count( $sections ) > self::MAX_SECTIONS ) {
			throw new \Exception( 'custom_sections holds more than ' . (int) self::MAX_SECTIONS . ' sections.' );
		}
		$out = array();
		foreach ( array_values( $sections ) as $i => $section ) {
			if ( ! is_array( $section ) ) {
				throw new \Exception( 'custom_sections[' . (int) $i . '] must be an object with title and content.' );
			}
			$title   = self::plain( ltrim( (string) ( $section['title'] ?? '' ), "# \t" ) );
			$content = self::sanitize_content( (string) ( $section['content'] ?? '' ) );
			if ( '' === $title || '' === $content ) {
				throw new \Exception( 'custom_sections[' . (int) $i . '] needs both a title and content.' );
			}
			$out[] = array(
				'title'   => function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 120 ) : substr( $title, 0, 120 ),
				'content' => rtrim( $content, "\n" ),
			);
		}
		return $out;
	}

	private static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function link_text( string $text ): string {
		return strtr(
			$text,
			array(
				'\\' => '\\\\',
				'['  => '\\[',
				']'  => '\\]',
			)
		);
	}
}
