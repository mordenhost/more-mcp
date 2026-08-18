<?php
/**
 * Detects which SEO plugin is active, and reports every one it finds.
 *
 * Before this class the detection lived inline in Server::detect_seo_plugin()
 * and knew about two plugins. Two things were wrong with that:
 *
 *   1. A site running AIOSEO, SEOPress, Slim SEO, or The SEO Framework got
 *      'none', so wp_get_seo_meta returned nothing and wp_update_seo_meta
 *      refused outright.
 *   2. It returned a single answer. Two SEO plugins active at once is a
 *      misconfiguration that produces duplicate meta tags, and it is exactly
 *      the kind of thing an agent auditing a site should be told about rather
 *      than have silently resolved by declaration order.
 *
 * This class answers both: {@see primary()} keeps the single-value contract the
 * existing tools depend on, and {@see all()} exposes the full picture.
 *
 * Detection deliberately checks constants and classes rather than
 * is_plugin_active(). A plugin can be active under a directory name we do not
 * expect (renamed folder, must-use install, bundled by a host), and the
 * constant is what the plugin itself guarantees.
 *
 * @package More_MCP
 */

namespace More_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Detector {

	/**
	 * Plugin slug → detection signals, in the order they are checked.
	 *
	 * Order matters only for {@see primary()} when more than one is active. It
	 * follows install base, so the plugin most likely to be the intended one
	 * wins a tie — but the tie itself is surfaced rather than hidden.
	 */
	private static function signals(): array {
		return array(
			'yoast'     => array(
				'label'     => 'Yoast SEO',
				'constants' => array( 'WPSEO_VERSION' ),
				'classes'   => array( 'WPSEO_Options' ),
				'functions' => array(),
			),
			'rankmath'  => array(
				'label'     => 'Rank Math',
				'constants' => array( 'RANK_MATH_VERSION' ),
				'classes'   => array( 'RankMath' ),
				'functions' => array(),
			),
			'aioseo'    => array(
				'label'     => 'All in One SEO',
				'constants' => array( 'AIOSEO_VERSION' ),
				'classes'   => array(),
				'functions' => array( 'aioseo' ),
			),
			'seopress'  => array(
				'label'     => 'SEOPress',
				'constants' => array( 'SEOPRESS_VERSION' ),
				'classes'   => array(),
				'functions' => array(),
			),
			'slimseo'   => array(
				'label'     => 'Slim SEO',
				'constants' => array( 'SLIM_SEO_VER' ),
				'classes'   => array( 'SlimSEO\Loader' ),
				'functions' => array(),
			),
			'tsf'       => array(
				'label'     => 'The SEO Framework',
				'constants' => array( 'THE_SEO_FRAMEWORK_VERSION' ),
				'classes'   => array(),
				'functions' => array( 'tsf', 'the_seo_framework' ),
			),
		);
	}

	/**
	 * Human-readable name for a slug. Returns the slug unchanged when unknown,
	 * so a caller composing an error message never renders an empty string.
	 */
	public static function label( string $slug ): string {
		$signals = self::signals();
		return isset( $signals[ $slug ] ) ? $signals[ $slug ]['label'] : $slug;
	}

	/**
	 * Every SEO plugin detected, in signals() order.
	 *
	 * @return string[] Slugs. Empty when no SEO plugin is present.
	 */
	public static function all(): array {
		$found = array();
		foreach ( self::signals() as $slug => $spec ) {
			if ( self::matches( $spec ) ) {
				$found[] = $slug;
			}
		}
		return $found;
	}

	/**
	 * The plugin the SEO tools will read and write.
	 *
	 * Returns 'none' rather than null or an empty string: the existing tool
	 * responses put this value in a `plugin` field that callers compare against
	 * string literals, and 'none' is already the documented value there.
	 */
	public static function primary(): string {
		$all = self::all();
		return empty( $all ) ? 'none' : $all[0];
	}

	/**
	 * True when more than one SEO plugin is active.
	 *
	 * Worth surfacing on every read: two plugins both emitting a title tag is a
	 * live SEO defect, and it explains why a write through one of them does not
	 * change what a crawler sees.
	 */
	public static function has_conflict(): bool {
		return count( self::all() ) > 1;
	}

	/**
	 * A `detection` block suitable for merging into any tool response.
	 *
	 * Read tools include this unconditionally so an agent does not have to make
	 * a second call to learn which plugin its values came from, or that a
	 * second plugin is fighting the first.
	 */
	public static function report(): array {
		$all     = self::all();
		$primary = empty( $all ) ? 'none' : $all[0];
		$report  = array(
			'plugin'     => $primary,
			'plugin_name' => 'none' === $primary ? 'none' : self::label( $primary ),
			'all_active' => $all,
		);
		if ( count( $all ) > 1 ) {
			$labels               = array_map( array( __CLASS__, 'label' ), $all );
			$report['conflict']   = true;
			$report['conflict_note'] = sprintf(
				'More than one SEO plugin is active (%s). They will each emit their own title and meta description, so the rendered head contains duplicates and a write through %s may not be what a crawler sees. Use seo_audit_meta_tags to check the served output. Reads and writes here use %s.',
				implode( ', ', $labels ),
				self::label( $primary ),
				self::label( $primary )
			);
		}
		return $report;
	}

	/**
	 * Whether any signal for a plugin is present.
	 */
	private static function matches( array $spec ): bool {
		foreach ( $spec['constants'] as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		foreach ( $spec['classes'] as $class ) {
			if ( class_exists( $class ) ) {
				return true;
			}
		}
		foreach ( $spec['functions'] as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}
		return false;
	}
}
