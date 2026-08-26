<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	public static function core_handlers(): array {
		return array(
			
			Posts::class,
			Pages::class,
			Terms::class,
			TermMeta::class,
			Comments::class,
			Users::class,
			PostMeta::class,
			Media::class,
			Site::class,
			Search::class,
			Options::class,
			Menus::class,
			Appearance::class,
			Seo::class,
			Permalink::class,
			Revisions::class,
		);
	}

	public static function find_handler( string $name ): ?string {
		foreach ( self::core_handlers() as $handler ) {
			if ( $handler::supports( $name ) ) {
				return $handler;
			}
		}
		return null;
	}

	public static function tools(): array {
		$tools = array();
		foreach ( self::core_handlers() as $handler ) {
			foreach ( $handler::get_tools() as $tool ) {
				$tools[] = $tool;
			}
		}
		return $tools;
	}
}
