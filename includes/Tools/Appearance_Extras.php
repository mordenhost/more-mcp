<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appearance_Extras implements Handler {

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'wp_get_widget',
				'description' => 'Get one widget by ID (as returned by wp_get_widgets): its settings, the sidebar it sits in and its rendered HTML. Needs edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'string', 'description' => 'Widget ID, e.g. text-2 or block-3.' ) ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_get_widget_types',
				'description' => 'List every registered widget type (id, name, description, whether more than one instance can exist). Use the id as id_base in wp_create_widget. Needs edit_theme_options.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'wp_create_widget',
				'description' => 'Add a widget to a widget area. id_base is a widget type (wp_get_widget_types); sidebar is a widget area (wp_get_sidebars). For a block widget use id_base "block" and instance {"raw":{"content":"<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->"}}; for a classic widget pass its fields as instance {"raw":{...}}. Requires the "Allow AI to modify theme appearance" admin switch and edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id_base'  => array( 'type' => 'string' ),
						'sidebar'  => array( 'type' => 'string' ),
						'instance' => array( 'type' => 'object', 'description' => 'Widget settings, usually {"raw":{...}}.' ),
					),
					'required'   => array( 'id_base', 'sidebar' ),
				),
			),
			array(
				'name'        => 'wp_delete_widget',
				'description' => 'Remove a widget. By default it moves to the inactive widgets area so its settings are kept; pass force=true to delete it permanently. Requires the "Allow AI to modify theme appearance" admin switch and edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'string' ),
						'force' => array( 'type' => 'boolean', 'description' => 'Delete permanently instead of deactivating.' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_delete_theme_mod',
				'description' => 'Reset one Customizer setting of the active theme to the theme\'s default. Same gates as wp_update_theme_mod: the "Allow AI to modify theme appearance" admin switch and the mod must be in the writable allowlist (more_mcp_writable_theme_mods filter).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'mod_name' => array( 'type' => 'string' ) ),
					'required'   => array( 'mod_name' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array( 'wp_get_widget', 'wp_get_widget_types', 'wp_create_widget', 'wp_delete_widget', 'wp_delete_theme_mod' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}

		switch ( $name ) {
			case 'wp_get_widget':
				return self::rest( 'GET', '/wp/v2/widgets/' . self::widget_id( $args ) );

			case 'wp_get_widget_types':
				$types = self::rest( 'GET', '/wp/v2/widget-types' );
				return array_values(
					array_map(
						static function ( $t ) {
							return array(
								'id'          => $t['id'] ?? '',
								'name'        => $t['name'] ?? '',
								'description' => $t['description'] ?? '',
								'is_multi'    => ! empty( $t['is_multi'] ),
							);
						},
						(array) $types
					)
				);

			case 'wp_create_widget':
				self::require_theme_writes();
				$params = array(
					'id_base' => sanitize_key( (string) ( $args['id_base'] ?? '' ) ),
					'sidebar' => sanitize_key( (string) ( $args['sidebar'] ?? '' ) ),
				);
				if ( '' === $params['id_base'] || '' === $params['sidebar'] ) {
					throw new \Exception( 'id_base and sidebar are required.' );
				}
				if ( isset( $args['instance'] ) ) {
					if ( ! is_array( $args['instance'] ) ) {
						throw new \Exception( 'instance must be an object.' );
					}
					$params['instance'] = $args['instance'];
				}
				return self::rest( 'POST', '/wp/v2/widgets', $params );

			case 'wp_delete_widget':
				self::require_theme_writes();
				return self::rest( 'DELETE', '/wp/v2/widgets/' . self::widget_id( $args ), array( 'force' => ! empty( $args['force'] ) ) );

			case 'wp_delete_theme_mod':
				self::require_theme_writes();
				$mod = sanitize_text_field( (string) ( $args['mod_name'] ?? '' ) );
				if ( '' === $mod ) {
					throw new \Exception( 'mod_name is required.' );
				}
				$writable = apply_filters( 'more_mcp_writable_theme_mods', array() );
				if ( ! is_array( $writable ) || ! in_array( $mod, $writable, true ) ) {
					throw new \Exception( 'Theme mod not in allowlist: ' . esc_html( $mod ) . '. Theme or plugin authors can opt their mods in via add_filter("more_mcp_writable_theme_mods", ...).' );
				}
				$previous = get_theme_mod( $mod );
				remove_theme_mod( $mod );
				return array(
					'mod_name'       => $mod,
					'previous_value' => $previous,
					'reset'          => true,
				);
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function widget_id( array $args ): string {
		$id = isset( $args['id'] ) ? sanitize_text_field( (string) $args['id'] ) : '';
		if ( '' === $id ) {
			throw new \Exception( 'Widget id is required.' );
		}
		return rawurlencode( $id );
	}

	private static function require_theme_writes(): void {
		$settings = get_option( 'more_mcp_settings', array() );
		if ( empty( $settings['allow_theme_writes'] ) ) {
			throw new \Exception( 'Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.' );
		}
	}

	private static function rest( string $method, string $route, array $params = array() ) {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			throw new \Exception( esc_html( $response->as_error()->get_error_message() ) );
		}
		return $response->get_data();
	}
}
