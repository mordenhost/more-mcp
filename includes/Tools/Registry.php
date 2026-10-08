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
			PageTemplates::class,
			Seo::class,
			Permalink::class,
			Revisions::class,
		);
	}

	public static function extended_handlers(): array {
		return array(
			Users_Manage::class,
			Comment_Moderation::class,
			Content_Extras::class,
			Menu_Extras::class,
			Appearance_Extras::class,
			Site_Admin::class,
			History_Tools::class,
			Safety_Tools::class,
			Privileged_Db::class,
			Privileged_Files::class,
			Rest_Bridge::class,
			Feedback::class,
			Seo_Extras::class,
		);
	}

	public static function extended_tools(): array {
		$tools = array();
		foreach ( self::extended_handlers() as $handler ) {
			foreach ( $handler::get_tools() as $tool ) {
				$tools[] = $tool;
			}
		}
		return $tools;
	}

	public static function find_handler( string $name ): ?string {
		foreach ( array_merge( self::core_handlers(), self::extended_handlers() ) as $handler ) {
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
