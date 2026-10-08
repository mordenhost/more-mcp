<?php
namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Divi {

	public static function get( string $tool_name ): ?array {
		$schemas = [
			'divi_get_page_outline' => [
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => [
					'post_id'      => [ 'type' => 'integer' ],
					'divi_enabled' => [ 'type' => 'boolean' ],
					'format'       => [ 'type' => 'string' ],
					'outline'      => [ 'type' => 'array' ],
				],
			],
			'divi_get_module' => [
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => [
					'post_id' => [ 'type' => 'integer' ],
					'path'    => [ 'type' => 'string' ],
					'found'   => [ 'type' => 'boolean' ],
					'format'  => [ 'type' => 'string' ],
				],
			],
			'divi_replace_module' => self::write_schema(),
			'divi_insert_module'  => self::write_schema(),
			'divi_delete_module'  => self::write_schema(),
		];

		return $schemas[ $tool_name ] ?? null;
	}

	private static function write_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => [
				'post_id'          => [ 'type' => 'integer' ],
				'path'             => [ 'type' => 'string' ],
				'format'           => [ 'type' => 'string' ],
				'written'          => [ 'type' => 'boolean' ],
				'dry_run'          => [ 'type' => 'boolean' ],
				'verified'         => [ 'type' => 'boolean' ],
				'resulting_outline' => [ 'type' => 'array' ],
				'cache_invalidation' => [ 'type' => 'object' ],
				'undo'             => [ 'type' => 'object' ],
			],
		];
	}
}
