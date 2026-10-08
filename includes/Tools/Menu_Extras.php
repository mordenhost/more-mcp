<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu_Extras implements Handler {

	const UNDO_OP = 'wp_delete_menu';

	public static function get_tools(): array {
		$menu = array( 'type' => 'integer', 'description' => 'Menu ID (from wp_get_menus).' );
		return array(
			array(
				'name'        => 'wp_get_menu',
				'description' => 'Get one navigation menu with all of its items (id, title, url, type, parent, position) and the theme locations it is assigned to. Items are flat; use parent to rebuild the tree. Needs edit_theme_options.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'id' => $menu ), 'required' => array( 'id' ) ),
			),
			array(
				'name'        => 'wp_update_menu',
				'description' => 'Rename a menu, change its slug, or set which theme locations it is assigned to. locations is the full list of location slugs for this menu; other menus keep theirs. Needs edit_theme_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => $menu,
						'name'      => array( 'type' => 'string' ),
						'slug'      => array( 'type' => 'string' ),
						'locations' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Theme location slugs (see registered locations in wp_get_menu output).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_delete_menu',
				'description' => 'Delete a navigation menu and every item in it. Returns an undo token; redeem it with more_mcp_undo_last_operation to recreate the menu, its items and its locations. Needs edit_theme_options.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'id' => $menu ), 'required' => array( 'id' ) ),
			),
		);
	}

	public static function supports( string $name ): bool {
		return 'wp_get_menu' === $name || 'wp_update_menu' === $name || 'wp_delete_menu' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new \Exception( 'edit_theme_options capability required.' );
		}
		$menu = wp_get_nav_menu_object( (int) ( $args['id'] ?? 0 ) );
		if ( ! $menu ) {
			throw new \Exception( 'Menu not found.' );
		}

		switch ( $name ) {
			case 'wp_get_menu':
				return self::describe( $menu );
			case 'wp_update_menu':
				return self::update( $menu, $args );
			case 'wp_delete_menu':
				return self::delete( $menu );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function locations_of( int $menu_id ): array {
		$out = array();
		foreach ( (array) get_nav_menu_locations() as $location => $assigned ) {
			if ( (int) $assigned === $menu_id ) {
				$out[] = $location;
			}
		}
		return $out;
	}

	private static function items_of( int $menu_id ): array {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( ! is_array( $items ) ) {
			return array();
		}
		return array_map(
			static function ( $item ) {
				return array(
					'id'          => (int) $item->ID,
					'title'       => $item->title,
					'url'         => $item->url,
					'type'        => $item->type,
					'object'      => $item->object,
					'object_id'   => (int) $item->object_id,
					'parent'      => (int) $item->menu_item_parent,
					'position'    => (int) $item->menu_order,
					'target'      => $item->target,
					'classes'     => array_values( array_filter( (array) $item->classes ) ),
					'xfn'         => $item->xfn,
					'description' => $item->description,
					'attr_title'  => $item->attr_title,
					'status'      => $item->post_status,
				);
			},
			$items
		);
	}

	private static function describe( \WP_Term $menu ): array {
		return array(
			'id'                   => (int) $menu->term_id,
			'name'                 => $menu->name,
			'slug'                 => $menu->slug,
			'count'                => (int) $menu->count,
			'locations'            => self::locations_of( (int) $menu->term_id ),
			'registered_locations' => get_registered_nav_menus(),
			'items'                => self::items_of( (int) $menu->term_id ),
		);
	}

	private static function update( \WP_Term $menu, array $args ): array {
		$menu_id = (int) $menu->term_id;
		$data    = array();

		if ( isset( $args['name'] ) ) {
			$name = sanitize_text_field( (string) $args['name'] );
			if ( '' === $name ) {
				throw new \Exception( 'name cannot be empty.' );
			}
			$other = wp_get_nav_menu_object( $name );
			if ( $other && (int) $other->term_id !== $menu_id ) {
				throw new \Exception( 'A menu named "' . esc_html( $name ) . '" already exists. Menu names must be unique.' );
			}
			$data['menu-name'] = $name;
		}
		if ( isset( $args['slug'] ) ) {
			$data['slug'] = sanitize_title( (string) $args['slug'] );
		}
		if ( ! isset( $args['locations'] ) && ! $data ) {
			throw new \Exception( 'Nothing to update. Pass name, slug or locations.' );
		}

		if ( $data ) {
			$result = wp_update_nav_menu_object( $menu_id, $data );
			if ( is_wp_error( $result ) ) {
				throw new \Exception( esc_html( $result->get_error_message() ) );
			}
		}

		if ( isset( $args['locations'] ) ) {
			if ( ! is_array( $args['locations'] ) ) {
				throw new \Exception( 'locations must be an array of location slugs.' );
			}
			$registered = get_registered_nav_menus();
			$want       = array_map( 'sanitize_key', array_map( 'strval', $args['locations'] ) );
			foreach ( $want as $location ) {
				if ( ! isset( $registered[ $location ] ) ) {
					throw new \Exception( 'Unknown theme location "' . esc_html( $location ) . '". Registered: ' . esc_html( implode( ', ', array_keys( $registered ) ) ) . '.' );
				}
			}
			$assigned = (array) get_nav_menu_locations();
			foreach ( $assigned as $location => $assigned_id ) {
				if ( (int) $assigned_id === $menu_id && ! in_array( $location, $want, true ) ) {
					unset( $assigned[ $location ] );
				}
			}
			foreach ( $want as $location ) {
				$assigned[ $location ] = $menu_id;
			}
			set_theme_mod( 'nav_menu_locations', $assigned );
		}

		return self::describe( wp_get_nav_menu_object( $menu_id ) );
	}

	private static function delete( \WP_Term $menu ): array {
		$menu_id  = (int) $menu->term_id;
		$snapshot = array(
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => self::locations_of( $menu_id ),
			'items'     => self::items_of( $menu_id ),
		);

		$deleted = wp_delete_nav_menu( $menu_id );
		if ( ! $deleted || is_wp_error( $deleted ) ) {
			throw new \Exception( 'WordPress could not delete that menu.' );
		}

		
		
		if ( wp_get_nav_menu_object( $menu_id ) ) {
			throw new \Exception( 'WordPress reported the menu deleted but it still exists, so nothing was changed.' );
		}

		$undo = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => self::UNDO_OP,
				'summary'      => 'Delete menu "' . $snapshot['name'] . '" (' . count( $snapshot['items'] ) . ' items)',
				'target'       => array( 'menu_id' => $menu_id ),
				'pre_op_state' => $snapshot,
			)
		);
		return array(
			'deleted' => true,
			'id'      => $menu_id,
			'name'    => $snapshot['name'],
			'items'   => count( $snapshot['items'] ),
			'verified'    => true,
			'verify_note' => 'Re-read after the delete: the menu no longer exists.',
			'undo'    => $undo,
		);
	}

	public static function restore( array $pre_op_state ): array {
		$name = (string) ( $pre_op_state['name'] ?? '' );
		if ( '' === $name ) {
			throw new \Exception( 'Undo snapshot has no menu name.' );
		}
		if ( wp_get_nav_menu_object( $name ) ) {
			throw new \Exception( 'A menu named "' . esc_html( $name ) . '" exists again, so the deleted one cannot be recreated under that name.' );
		}
		$menu_id = wp_update_nav_menu_object(
			0,
			array(
				'menu-name' => $name,
				'slug'      => (string) ( $pre_op_state['slug'] ?? '' ),
			)
		);
		if ( is_wp_error( $menu_id ) ) {
			throw new \Exception( 'Could not recreate the menu: ' . esc_html( $menu_id->get_error_message() ) );
		}

		$items = isset( $pre_op_state['items'] ) && is_array( $pre_op_state['items'] ) ? $pre_op_state['items'] : array();
		usort(
			$items,
			static function ( $a, $b ) {
				return (int) ( $a['position'] ?? 0 ) <=> (int) ( $b['position'] ?? 0 );
			}
		);

		$id_map  = array();
		$created = 0;
		$pending = $items;
		
		do {
			$progress = false;
			foreach ( $pending as $index => $item ) {
				$old_parent = (int) ( $item['parent'] ?? 0 );
				if ( $old_parent && ! isset( $id_map[ $old_parent ] ) ) {
					continue;
				}
				$new_id = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'       => (string) ( $item['title'] ?? '' ),
						'menu-item-url'         => (string) ( $item['url'] ?? '' ),
						'menu-item-type'        => (string) ( $item['type'] ?? 'custom' ),
						'menu-item-object'      => (string) ( $item['object'] ?? 'custom' ),
						'menu-item-object-id'   => (int) ( $item['object_id'] ?? 0 ),
						'menu-item-parent-id'   => $old_parent ? (int) $id_map[ $old_parent ] : 0,
						'menu-item-position'    => (int) ( $item['position'] ?? 0 ),
						'menu-item-target'      => (string) ( $item['target'] ?? '' ),
						'menu-item-classes'     => implode( ' ', (array) ( $item['classes'] ?? array() ) ),
						'menu-item-xfn'         => (string) ( $item['xfn'] ?? '' ),
						'menu-item-description' => (string) ( $item['description'] ?? '' ),
						'menu-item-attr-title'  => (string) ( $item['attr_title'] ?? '' ),
						'menu-item-status'      => (string) ( $item['status'] ?? 'publish' ),
					)
				);
				if ( ! is_wp_error( $new_id ) ) {
					$id_map[ (int) ( $item['id'] ?? 0 ) ] = (int) $new_id;
					++$created;
				}
				unset( $pending[ $index ] );
				$progress = true;
			}
		} while ( $pending && $progress );

		$locations = isset( $pre_op_state['locations'] ) && is_array( $pre_op_state['locations'] ) ? $pre_op_state['locations'] : array();
		if ( $locations ) {
			$registered = get_registered_nav_menus();
			$assigned   = (array) get_nav_menu_locations();
			foreach ( $locations as $location ) {
				if ( isset( $registered[ $location ] ) && empty( $assigned[ $location ] ) ) {
					$assigned[ $location ] = (int) $menu_id;
				}
			}
			set_theme_mod( 'nav_menu_locations', $assigned );
		}

		return array(
			'menu_id' => (int) $menu_id,
			'items'   => $created,
		);
	}
}
