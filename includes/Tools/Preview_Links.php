<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Preview_Links {

	const OPTION      = 'more_mcp_preview_links';
	const PARAM       = 'mmcp_preview';
	const DEFAULT_TTL = 172800; 
	const MAX_TTL     = 2592000; 
	const MAX_LINKS   = 200;

	public static function register(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'maybe_allow' ) );

		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) {
				if ( 'publish' === $new_status && $post instanceof \WP_Post ) {
					self::revoke_post( (int) $post->ID );
				}
			},
			10,
			3
		);
		add_action( 'before_delete_post', array( __CLASS__, 'revoke_post' ) );
	}

	public static function create( int $post_id, int $ttl, int $user_id ): array {
		$ttl   = max( 300, min( $ttl, self::MAX_TTL ) );
		$token = bin2hex( random_bytes( 20 ) );
		$links = self::prune( self::load() );

		if ( count( $links ) >= self::MAX_LINKS ) {
			uasort(
				$links,
				static function ( $a, $b ) {
					return $a['exp'] <=> $b['exp'];
				}
			);
			$links = array_slice( $links, count( $links ) - self::MAX_LINKS + 1, null, true );
		}

		$links[ self::digest( $token ) ] = array(
			'post' => $post_id,
			'exp'  => time() + $ttl,
			'by'   => $user_id,
		);
		update_option( self::OPTION, $links, false );

		return array(
			'url'              => add_query_arg(
				array(
					'p'          => $post_id,
					'preview'    => 'true',
					self::PARAM  => $token,
				),
				home_url( '/' )
			),
			'expires_at'       => time() + $ttl,
			'expires_in_hours' => (int) round( $ttl / HOUR_IN_SECONDS ),
		);
	}

	public static function resolve( string $token ): int {
		if ( ! preg_match( '/^[a-f0-9]{40}$/', $token ) ) {
			return 0;
		}
		$links = self::load();
		$row   = $links[ self::digest( $token ) ] ?? null;
		if ( ! is_array( $row ) || (int) $row['exp'] < time() ) {
			return 0;
		}
		return (int) $row['post'];
	}

	public static function revoke_post( int $post_id ): int {
		$links   = self::load();
		$removed = 0;
		foreach ( $links as $hash => $row ) {
			if ( (int) ( $row['post'] ?? 0 ) === $post_id ) {
				unset( $links[ $hash ] );
				++$removed;
			}
		}
		if ( $removed ) {
			update_option( self::OPTION, $links, false );
		}
		return $removed;
	}

	public static function maybe_allow( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : '';
		if ( '' === $token || ! $query->is_preview() || ! $query->is_singular() ) {
			return;
		}
		$post_id = self::resolve( $token );
		if ( $post_id <= 0 || (int) $query->get( 'p' ) !== $post_id ) {
			return;
		}

		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'posts_results', array( __CLASS__, 'present_as_published' ), 10, 2 );
	}

	public static function present_as_published( $posts, $query ) {
		remove_filter( 'posts_results', array( __CLASS__, 'present_as_published' ), 10 );
		if ( empty( $posts ) || ! $query->is_main_query() ) {
			return $posts;
		}
		$posts[0]->post_status = 'publish';
		add_filter( 'comments_open', '__return_false' );
		add_filter( 'pings_open', '__return_false' );
		return $posts;
	}

	private static function digest( string $token ): string {
		return hash( 'sha256', $token );
	}

	private static function load(): array {
		$links = get_option( self::OPTION, array() );
		return is_array( $links ) ? $links : array();
	}

	private static function prune( array $links ): array {
		$now = time();
		foreach ( $links as $hash => $row ) {
			if ( ! is_array( $row ) || (int) ( $row['exp'] ?? 0 ) < $now ) {
				unset( $links[ $hash ] );
			}
		}
		return $links;
	}
}
