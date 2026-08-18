<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users implements Handler {

	public static function get_tools(): array {
		return array(
			array( 'name' => 'wp_get_users', 'description' => 'List site users. Returns id, display_name, role, and post_count. Emails and usernames are NOT exposed. Filter by role slug (administrator / editor / author / contributor / subscriber, or any custom role).', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer', 'description' => 'Number of users (default 10, max 100)' ), 'role' => array( 'type' => 'string', 'description' => 'Filter by role slug — use wp_get_user on a specific ID for full profile data.' ) ) ) ),
			array( 'name' => 'wp_get_user', 'description' => 'Get user by ID', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ) ),
		);
	}

	public static function supports( string $name ): bool {
		return 'wp_get_users' === $name || 'wp_get_user' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_users':

				
				
				if ( ! current_user_can( 'list_users' ) ) {
					throw new \Exception( 'You do not have permission to list users.' );
				}
				$user_args = array( 'number' => min( intval( $args['per_page'] ?? 10 ), 100 ) );
				if ( ! empty( $args['role'] ) ) {
					$user_args['role'] = sanitize_text_field( $args['role'] );
				}
				$users = get_users( $user_args );
				return array_map( function ( $u ) {
					return array(
						'id'           => $u->ID,
						'display_name' => $u->display_name,
						'roles'        => $u->roles,
					);
				}, $users );

			case 'wp_get_user':
				if ( ! current_user_can( 'list_users' ) ) {
					throw new \Exception( 'You do not have permission to view user accounts.' );
				}
				$user = get_user_by( 'ID', intval( $args['id'] ) );
				if ( ! $user ) {
					throw new \Exception( 'User not found' );
				}
				return array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'roles'        => $user->roles,
					'registered'   => $user->user_registered,
				);
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
