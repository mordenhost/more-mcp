<?php

namespace More_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class White_Label {

	const DOMAIN = 'mordenhost-mcp-server';

	const BRAND = 'More MCP';

	public static function register(): void {
		if ( '' !== self::name() ) {
			add_filter( 'gettext', array( __CLASS__, 'rename_text' ), 10, 3 );
			add_filter( 'gettext_with_context', array( __CLASS__, 'rename_text_with_context' ), 10, 4 );
		}
		add_filter( 'all_plugins', array( __CLASS__, 'filter_plugins' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'maybe_hide_menu' ), 999 );
	}

	public static function name(): string {
		return trim( wp_strip_all_tags( Config::string( 'white_label_name' ) ) );
	}

	public static function active(): bool {
		return '' !== self::name()
			|| '' !== Config::string( 'white_label_author' )
			|| '' !== Config::string( 'white_label_description' )
			|| Config::bool( 'hide_menu' )
			|| Config::bool( 'hide_from_plugins' );
	}

	public static function rename_text( $translated, $text = '', $domain = '' ) {
		if ( self::DOMAIN !== $domain || ! is_string( $translated ) || false === strpos( $translated, self::BRAND ) ) {
			return $translated;
		}
		return str_replace( self::BRAND, self::name(), $translated );
	}

	public static function rename_text_with_context( $translated, $text = '', $context = '', $domain = '' ) {
		return self::rename_text( $translated, $text, $domain );
	}

	public static function filter_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}
		$ours = defined( 'MORE_MCP_PLUGIN_FILE' ) ? plugin_basename( MORE_MCP_PLUGIN_FILE ) : '';
		if ( '' === $ours || ! isset( $plugins[ $ours ] ) ) {
			return $plugins;
		}
		if ( Config::bool( 'hide_from_plugins' ) ) {
			unset( $plugins[ $ours ] );
			return $plugins;
		}

		$name   = self::name();
		$author = trim( wp_strip_all_tags( Config::string( 'white_label_author' ) ) );
		$desc   = trim( wp_strip_all_tags( Config::string( 'white_label_description' ) ) );

		if ( '' !== $name ) {
			$plugins[ $ours ]['Name']  = $name;
			$plugins[ $ours ]['Title'] = $name;
			$plugins[ $ours ]['PluginURI'] = '';
		}
		if ( '' !== $author ) {
			$plugins[ $ours ]['Author']     = $author;
			$plugins[ $ours ]['AuthorName'] = $author;
			$plugins[ $ours ]['AuthorURI']  = '';
		}
		if ( '' !== $desc ) {
			$plugins[ $ours ]['Description'] = $desc;
		}
		return $plugins;
	}

	public static function maybe_hide_menu(): void {
		if ( Config::bool( 'hide_menu' ) ) {
			remove_menu_page( 'more-mcp' );
		}
	}
}
