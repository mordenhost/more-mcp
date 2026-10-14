<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Handler {

	public static function get_tools(): array;

	public static function supports( string $name ): bool;

	public static function execute_tool( string $name, array $args );
}
