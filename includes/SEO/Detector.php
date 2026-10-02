<?php

namespace More_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Detector {

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

	public static function label( string $slug ): string {
		$signals = self::signals();
		return isset( $signals[ $slug ] ) ? $signals[ $slug ]['label'] : $slug;
	}

	public static function all(): array {
		$found = array();
		foreach ( self::signals() as $slug => $spec ) {
			if ( self::matches( $spec ) ) {
				$found[] = $slug;
			}
		}
		return $found;
	}

	public static function primary(): string {
		$all = self::all();
		return empty( $all ) ? 'none' : $all[0];
	}

	public static function has_conflict(): bool {
		return count( self::all() ) > 1;
	}

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
