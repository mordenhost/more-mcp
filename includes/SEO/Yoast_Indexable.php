<?php

namespace Mordenhost_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yoast_Indexable {

	const TERM_WATCHER = 'Yoast\WP\SEO\Integrations\Watchers\Indexable_Term_Watcher';

	const POST_WATCHER = 'Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher';

	public static function refresh( string $type, int $id ): ?array {
		if ( ! in_array( 'yoast', Detector::all(), true ) ) {
			return null;
		}
		$is_term = 'term' === $type;
		$reason  = self::rebuild( $is_term ? self::TERM_WATCHER : self::POST_WATCHER, $id );
		if ( null === $reason ) {
			return array( 'refreshed' => true );
		}
		return array(
			'refreshed' => false,
			'reason'    => $reason,
			'warning'   => $is_term
				? sprintf(
					'The values are stored, but Yoast SEO renders term archives from its indexable table and Mordenhost MCP could not rebuild the indexable for term %d (%s). The live archive keeps its old title and meta tags until Yoast rebuilds it: save the term again, for example wp_update_term with its current name, then check the archive with seo_audit_meta_tags.',
					$id,
					$reason
				)
				: sprintf(
					'The change is saved, but Yoast SEO renders the page head, og:image included, from its indexable table and Mordenhost MCP could not rebuild the indexable for post %d (%s). The live page keeps its old meta tags and image until Yoast rebuilds it: save the post again, for example wp_update_post with its current status, then check with seo_audit_meta_tags.',
					$id,
					$reason
				),
		);
	}

	public static function annotate( array $response, ?array $report ): array {
		if ( null === $report ) {
			return $response;
		}
		$warning = $report['warning'] ?? null;
		unset( $report['warning'] );
		$response['yoast_indexable'] = $report;
		if ( empty( $report['refreshed'] ) && null !== $warning ) {
			$response['warnings']   = isset( $response['warnings'] ) && is_array( $response['warnings'] ) ? $response['warnings'] : array();
			$response['warnings'][] = $warning;
			if ( isset( $response['message'] ) && is_string( $response['message'] ) ) {
				$response['message'] = rtrim( $response['message'], '.' ) . ' in WordPress, but Yoast SEO was not refreshed, so the live page has not changed yet. See warnings.';
			}
		}
		return $response;
	}

	private static function rebuild( string $watcher_class, int $id ): ?string {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return 'YoastSEO() is not available';
		}
		$short = substr( (string) strrchr( $watcher_class, '\\' ), 1 );
		try {
			$main    = \YoastSEO();

			$classes = ( is_object( $main ) && isset( $main->classes ) ) ? $main->classes : null;
			if ( ! is_object( $classes ) || ! method_exists( $classes, 'get' ) ) {
				return 'YoastSEO()->classes is not available';
			}
			$watcher = $classes->get( $watcher_class );
			if ( ! is_object( $watcher ) || ! method_exists( $watcher, 'build_indexable' ) ) {
				return $short . '::build_indexable() is not available in this Yoast SEO version';
			}
			$watcher->build_indexable( $id );
		} catch ( \Throwable $e ) {
			return $short . ' failed: ' . $e->getMessage();
		}
		return null;
	}
}
