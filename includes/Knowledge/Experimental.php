<?php

namespace More_MCP\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Experimental {

	const CONSTANT = 'MORE_MCP_EXPERIMENTAL_MODE';

	public static function is_enabled(): bool {
		return defined( self::CONSTANT ) && (bool) constant( self::CONSTANT );
	}

	public static function register(): void {
		add_filter( 'map_meta_cap', array( Skills::class, 'deny_tool_writes' ), 10, 4 );
		if ( ! self::is_enabled() ) {
			return;
		}
		Skills::register();
		Memory_Admin::register();
	}
}
