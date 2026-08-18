<?php
/**
 * Plugin and theme lifecycle output schemas.
 *
 * Every mutation here returns one of two shapes: a preview (confirmed=false,
 * written=false, plus what would change) or an applied result (written=true,
 * verified, plus the state read back). Rather than declaring two variants, each
 * schema is the union with additionalProperties open — a caller branches on
 * `written`, which is present in both.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lifecycle {

	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	/**
	 * Fields shared by every mutation response, in both the preview and the
	 * applied shape.
	 */
	private static function common(): array {
		return array(
			'written'      => array( 'type' => 'boolean' ),
			'confirmed'    => array( 'type' => 'boolean' ),
			'verified'     => array( 'type' => 'boolean' ),
			'already'      => array( 'type' => 'boolean' ),
			'message'      => array( 'type' => 'string' ),
			'summary'      => array( 'type' => 'string' ),
			'target'       => array( 'type' => 'string' ),
			'tool'         => array( 'type' => 'string' ),
			'confirm_note' => array( 'type' => 'string' ),
			'caution'      => array( 'type' => array( 'string', 'null' ) ),
			'to_apply'     => array(
				'type'       => 'object',
				'properties' => array(
					'confirm'      => array( 'type' => 'boolean' ),
					'confirm_slug' => array( 'type' => 'string' ),
				),
			),
		);
	}

	private static function plugin_fields(): array {
		return array(
			'plugin'  => array( 'type' => 'string' ),
			'slug'    => array( 'type' => 'string' ),
			'name'    => array( 'type' => 'string' ),
			'version' => array( 'type' => 'string' ),
			'active'  => array( 'type' => 'boolean' ),
		);
	}

	private static function theme_fields(): array {
		return array(
			'stylesheet'     => array( 'type' => 'string' ),
			'name'           => array( 'type' => 'string' ),
			'version'        => array( 'type' => 'string' ),
			'active'         => array( 'type' => 'boolean' ),
			'parent'         => array( 'type' => array( 'string', 'null' ) ),
			'is_block_theme' => array( 'type' => array( 'boolean', 'null' ) ),
		);
	}

	private static function plugin_mutation(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array_merge(
				self::common(),
				self::plugin_fields(),
				array(
					'previous_version' => array( 'type' => 'string' ),
					'skin_messages'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				)
			),
		);
	}

	private static function theme_mutation(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array_merge(
				self::common(),
				self::theme_fields(),
				array(
					'previous_version' => array( 'type' => 'string' ),
					'previous_theme'   => array( 'type' => 'string' ),
					'skin_messages'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				)
			),
		);
	}

	private static function map(): array {
		return array(
			'wp_get_plugin_updates' => array(
				'type'       => 'object',
				'properties' => array(
					'count'   => array( 'type' => 'integer' ),
					'note'    => array( 'type' => 'string' ),
					'updates' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'plugin'          => array( 'type' => 'string' ),
								'slug'            => array( 'type' => 'string' ),
								'name'            => array( 'type' => 'string' ),
								'current_version' => array( 'type' => 'string' ),
								'new_version'     => array( 'type' => 'string' ),
								'active'          => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			),

			'wp_get_themes_status' => array(
				'type'       => 'object',
				'properties' => array(
					'count'           => array( 'type' => 'integer' ),
					'active'          => array( 'type' => 'string' ),
					'updates_pending' => array( 'type' => 'integer' ),
					'themes'          => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array_merge(
								self::theme_fields(),
								array(
									'update_available' => array( 'type' => 'boolean' ),
									'new_version'      => array( 'type' => array( 'string', 'null' ) ),
								)
							),
						),
					),
				),
			),

			'wp_activate_plugin'   => self::plugin_mutation(),
			'wp_deactivate_plugin' => self::plugin_mutation(),
			'wp_update_plugin'     => self::plugin_mutation(),
			'wp_delete_plugin'     => self::plugin_mutation(),

			// Install carries the wp.org lookup fields the preview shows, so a
			// caller can verify the target before confirming.
			'wp_install_plugin'    => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array_merge(
					self::common(),
					self::plugin_fields(),
					array(
						'source'            => array( 'type' => 'string' ),
						'author'            => array( 'type' => 'string' ),
						'active_installs'   => array( 'type' => array( 'integer', 'null' ) ),
						'last_updated'      => array( 'type' => 'string' ),
						'requires_php'      => array( 'type' => array( 'string', 'null' ) ),
						'tested_up_to'      => array( 'type' => array( 'string', 'null' ) ),
						'short_description' => array( 'type' => 'string' ),
						'will_activate'     => array( 'type' => 'boolean' ),
						'activation_error'  => array( 'type' => array( 'string', 'null' ) ),
						'skin_messages'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					)
				),
			),

			'wp_activate_theme' => self::theme_mutation(),
			'wp_update_theme'   => self::theme_mutation(),
			'wp_delete_theme'   => self::theme_mutation(),
		);
	}
}
