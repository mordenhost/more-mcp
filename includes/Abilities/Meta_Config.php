<?php

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Config {

	
	const PUBLIC_META_KEY = 'public';

	const SHOW_IN_REST_META_KEY = 'show_in_rest';

	

	
	public static function ability_meta(): array {
		return array(
			self::PUBLIC_META_KEY       => true,
			self::SHOW_IN_REST_META_KEY => true,
		);
	}
}
