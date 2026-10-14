<?php

namespace Mordenhost_MCP\Abilities\Output_Schemas;

use Mordenhost_MCP\Abilities\Output_Schemas\Core\Appearance;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Comments_Users;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Media;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Options_Menus;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Permalink_Revisions;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\PostMeta;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Posts_Pages;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Seo;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Site;
use Mordenhost_MCP\Abilities\Output_Schemas\Core\Terms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( static function ( $class ) {
	$prefix = __NAMESPACE__ . '\\Core\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}
	$file = __DIR__ . '/Core/' . substr( $class, strlen( $prefix ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

class Core {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array_merge(
			Posts_Pages::map(),
			Media::map(),
			Terms::map(),
			Comments_Users::map(),
			PostMeta::map(),
			Site::map(),
			Options_Menus::map(),
			Appearance::map(),
			Seo::map(),
			Permalink_Revisions::map()
		);
	}
}
