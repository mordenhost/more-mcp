<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users_Manage implements Handler {

	private const BLOCKED_META = array(
		'capabilities', 'user_level', 'session_tokens', 'activation_key',
		'application_passwords', 'user_pass', 'password', 'secret', 'token',
	);

	public static function get_tools(): array {
		$meta_key = array( 'type' => 'string', 'description' => 'Meta key. Credential, role and session keys are refused.' );
		return array(
			array(
				'name'        => 'wp_create_user',
				'description' => 'Create a user. Requires the create_users capability, and the role must be one the acting user is allowed to assign. When no password is given a strong one is generated and the new user is emailed a link to set their own (the password is never returned). Returns id, display_name and roles; the email address is not echoed back.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'username'          => array( 'type' => 'string' ),
						'email'             => array( 'type' => 'string' ),
						'password'          => array( 'type' => 'string', 'description' => 'Optional. Omit to have the user set their own through the emailed link.' ),
						'role'              => array( 'type' => 'string', 'description' => 'Role slug (default: the site default role).' ),
						'display_name'      => array( 'type' => 'string' ),
						'first_name'        => array( 'type' => 'string' ),
						'last_name'         => array( 'type' => 'string' ),
						'send_notification' => array( 'type' => 'boolean', 'description' => 'Email the new user (default true).' ),
					),
					'required'   => array( 'username', 'email' ),
				),
			),
			array(
				'name'        => 'wp_update_user',
				'description' => 'Update a user\'s email, display name, name fields, website, bio, role or password. Requires edit_user on that account; changing the role also requires promote_user, and you cannot change your own role. Pass only the fields to change.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer' ),
						'email'        => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
						'url'          => array( 'type' => 'string' ),
						'description'  => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'password'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_delete_user',
				'description' => 'Delete a user. Requires delete_user on that account (never your own). If the user owns content, pass reassign (a user ID to hand it to) or delete_content=true to remove it with the account; without either the call is refused so content is never lost by accident. On multisite the user is removed from this site rather than deleted network-wide.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'             => array( 'type' => 'integer' ),
						'reassign'       => array( 'type' => 'integer', 'description' => 'User ID that inherits the content.' ),
						'delete_content' => array( 'type' => 'boolean', 'description' => 'Delete the user\'s posts and links with the account.' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_get_user_meta',
				'description' => 'Read user meta. Anyone may read their own; reading another user\'s needs edit_user. Omit key to list every non-sensitive key. Credential, role and session keys are never returned.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer' ),
						'key'     => $meta_key,
					),
					'required'   => array( 'user_id' ),
				),
			),
			array(
				'name'        => 'wp_update_user_meta',
				'description' => 'Set one user meta key. Requires edit_user. Credential, role and session keys and protected (underscore-prefixed) keys are refused.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer' ),
						'key'     => $meta_key,
						'value'   => array( 'description' => 'Any JSON value.' ),
					),
					'required'   => array( 'user_id', 'key', 'value' ),
				),
			),
			array(
				'name'        => 'wp_delete_user_meta',
				'description' => 'Delete a user meta key (all rows, or only rows whose value matches). Requires edit_user. Same key restrictions as wp_update_user_meta.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer' ),
						'key'     => $meta_key,
						'value'   => array( 'description' => 'Optional. Delete only rows holding this value.' ),
					),
					'required'   => array( 'user_id', 'key' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array(
			'wp_create_user', 'wp_update_user', 'wp_delete_user',
			'wp_get_user_meta', 'wp_update_user_meta', 'wp_delete_user_meta',
		);
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_create_user':
				return self::create_user( $args );
			case 'wp_update_user':
				return self::update_user( $args );
			case 'wp_delete_user':
				return self::delete_user( $args );
			case 'wp_get_user_meta':
				return self::get_user_meta_tool( $args );
			case 'wp_update_user_meta':
				return self::write_user_meta( $args, false );
			case 'wp_delete_user_meta':
				return self::write_user_meta( $args, true );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	public static function is_blocked_meta_key( string $key ): bool {
		$lower = strtolower( $key );
		foreach ( self::BLOCKED_META as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function summary( \WP_User $user ): array {
		return array(
			'id'           => (int) $user->ID,
			'display_name' => $user->display_name,
			'roles'        => array_values( $user->roles ),
		);
	}

	private static function assignable_role( string $role ): string {
		$role = sanitize_key( $role );
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$editable = get_editable_roles();
		if ( ! isset( $editable[ $role ] ) ) {
			throw new \Exception( 'You cannot assign the role "' . esc_html( $role ) . '". Available: ' . esc_html( implode( ', ', array_keys( $editable ) ) ) . '.' );
		}
		return $role;
	}

	private static function create_user( array $args ): array {
		if ( ! current_user_can( 'create_users' ) ) {
			throw new \Exception( 'You do not have permission to create users.' );
		}
		$login = isset( $args['username'] ) ? sanitize_user( (string) $args['username'], true ) : '';
		$email = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : '';
		if ( '' === $login || ! validate_username( $login ) ) {
			throw new \Exception( 'username is missing or not a valid username.' );
		}
		if ( ! is_email( $email ) ) {
			throw new \Exception( 'email is missing or not a valid address.' );
		}
		if ( username_exists( $login ) ) {
			throw new \Exception( 'That username is already taken.' );
		}
		if ( email_exists( $email ) ) {
			throw new \Exception( 'That email address is already registered.' );
		}

		$role = isset( $args['role'] ) && '' !== (string) $args['role']
			? self::assignable_role( (string) $args['role'] )
			: self::assignable_role( (string) get_option( 'default_role', 'subscriber' ) );

		$supplied = isset( $args['password'] ) && '' !== (string) $args['password'];
		$notify   = array_key_exists( 'send_notification', $args ) ? ! empty( $args['send_notification'] ) : true;

		$data = array(
			'user_login' => $login,
			'user_email' => $email,
			'user_pass'  => $supplied ? (string) $args['password'] : wp_generate_password( 24, true, true ),
			'role'       => $role,
		);
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $args[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( (string) $args[ $field ] );
			}
		}

		$id = wp_insert_user( $data );
		if ( is_wp_error( $id ) ) {
			throw new \Exception( esc_html( $id->get_error_message() ) );
		}

		if ( $notify ) {
			
			wp_new_user_notification( $id, null, 'user' );
		}

		$user = get_userdata( $id );
		return array_merge(
			self::summary( $user ),
			array(
				'message'      => 'User created.',
				'notification' => $notify ? 'sent' : 'not sent',
			)
		);
	}

	private static function update_user( array $args ): array {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$user = $id > 0 ? get_userdata( $id ) : false;
		if ( ! $user ) {
			throw new \Exception( 'User not found.' );
		}
		if ( ! current_user_can( 'edit_user', $id ) ) {
			throw new \Exception( 'You do not have permission to edit this user.' );
		}

		$data = array( 'ID' => $id );

		if ( isset( $args['email'] ) ) {
			$email = sanitize_email( (string) $args['email'] );
			if ( ! is_email( $email ) ) {
				throw new \Exception( 'email is not a valid address.' );
			}
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== $id ) {
				throw new \Exception( 'That email address belongs to another account.' );
			}
			$data['user_email'] = $email;
		}
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $args[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( (string) $args[ $field ] );
			}
		}
		if ( isset( $args['url'] ) ) {
			$data['user_url'] = esc_url_raw( (string) $args['url'] );
		}
		if ( isset( $args['description'] ) ) {
			$data['description'] = wp_kses_post( (string) $args['description'] );
		}
		if ( isset( $args['password'] ) && '' !== (string) $args['password'] ) {
			$data['user_pass'] = (string) $args['password'];
		}
		if ( isset( $args['role'] ) && '' !== (string) $args['role'] ) {
			if ( get_current_user_id() === $id ) {
				throw new \Exception( 'You cannot change your own role.' );
			}
			if ( ! current_user_can( 'promote_user', $id ) ) {
				throw new \Exception( 'You do not have permission to change this user\'s role.' );
			}
			$data['role'] = self::assignable_role( (string) $args['role'] );
		}

		if ( 1 === count( $data ) ) {
			throw new \Exception( 'Nothing to update. Pass at least one field besides id.' );
		}

		$result = wp_update_user( $data );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}
		return array_merge( self::summary( get_userdata( $id ) ), array( 'message' => 'User updated.' ) );
	}

	private static function delete_user( array $args ): array {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$user = $id > 0 ? get_userdata( $id ) : false;
		if ( ! $user ) {
			throw new \Exception( 'User not found.' );
		}
		if ( ! current_user_can( 'delete_user', $id ) ) {
			throw new \Exception( 'You do not have permission to delete this user.' );
		}

		
		if ( get_current_user_id() === $id ) {
			throw new \Exception( 'You cannot delete the account this connection is signed in as.' );
		}
		
		if ( in_array( 'administrator', (array) $user->roles, true ) && ! is_multisite() ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 2 ) );
			if ( count( $admins ) <= 1 ) {
				throw new \Exception( 'This is the only administrator. Create or promote another administrator first.' );
			}
		}

		$reassign       = isset( $args['reassign'] ) ? (int) $args['reassign'] : 0;
		$delete_content = ! empty( $args['delete_content'] );
		if ( $reassign > 0 ) {
			if ( $reassign === $id || ! get_userdata( $reassign ) ) {
				throw new \Exception( 'reassign must be the ID of a different, existing user.' );
			}
		} elseif ( ! $delete_content && self::owns_content( $id ) ) {
			throw new \Exception( 'This user owns content. Pass reassign=<user id> to hand it over, or delete_content=true to delete it with the account.' );
		}

		if ( is_multisite() ) {
			if ( ! function_exists( 'remove_user_from_blog' ) ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
			}
			remove_user_from_blog( $id, get_current_blog_id(), $reassign > 0 ? $reassign : '' );
			return array( 'id' => $id, 'message' => 'User removed from this site.' );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		if ( ! wp_delete_user( $id, $reassign > 0 ? $reassign : null ) ) {
			throw new \Exception( 'WordPress could not delete that user.' );
		}
		return array( 'id' => $id, 'message' => 'User deleted.' );
	}

	private static function owns_content( int $user_id ): bool {
		$owned = get_posts(
			array(
				'author'                 => $user_id,
				'post_type'              => array_values( get_post_types() ),
				'post_status'            => 'any',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				
				'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- the ownership guard has to see every post.
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return ! empty( $owned );
	}

	private static function meta_target( array $args, bool $write ): int {
		$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			throw new \Exception( 'User not found.' );
		}
		$own = get_current_user_id() === $user_id;
		if ( $write || ! $own ) {
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				throw new \Exception( 'You do not have permission to ' . ( $write ? 'change' : 'read' ) . ' this user\'s meta.' );
			}
		} elseif ( ! current_user_can( 'read' ) ) {
			throw new \Exception( 'You do not have permission to read user meta.' );
		}
		return $user_id;
	}

	private static function get_user_meta_tool( array $args ): array {
		$user_id = self::meta_target( $args, false );

		if ( isset( $args['key'] ) && '' !== (string) $args['key'] ) {
			$key = (string) $args['key'];
			if ( self::is_blocked_meta_key( $key ) ) {
				throw new \Exception( 'That meta key holds credentials or privileges and is never exposed.' );
			}
			return array(
				'user_id' => $user_id,
				'key'     => $key,
				'value'   => get_user_meta( $user_id, $key, true ),
			);
		}

		$out = array();
		foreach ( (array) get_user_meta( $user_id ) as $key => $values ) {
			if ( self::is_blocked_meta_key( (string) $key ) ) {
				continue;
			}
			$decoded = array_map( 'maybe_unserialize', (array) $values );
			$out[ $key ] = 1 === count( $decoded ) ? $decoded[0] : $decoded;
		}
		return array( 'user_id' => $user_id, 'meta' => $out );
	}

	private static function write_user_meta( array $args, bool $delete ): array {
		$user_id = self::meta_target( $args, true );
		$key     = isset( $args['key'] ) ? (string) $args['key'] : '';
		if ( '' === $key ) {
			throw new \Exception( 'key is required.' );
		}
		if ( self::is_blocked_meta_key( $key ) ) {
			throw new \Exception( 'That meta key holds credentials or privileges and cannot be changed here.' );
		}
		if ( is_protected_meta( $key, 'user' ) ) {
			throw new \Exception( 'Protected meta keys (leading underscore) cannot be changed here.' );
		}

		if ( $delete ) {
			$ok = array_key_exists( 'value', $args )
				? delete_user_meta( $user_id, $key, $args['value'] )
				: delete_user_meta( $user_id, $key );
			if ( ! $ok ) {
				throw new \Exception( 'Nothing deleted: no matching meta row.' );
			}
			return array( 'user_id' => $user_id, 'key' => $key, 'message' => 'Meta deleted.' );
		}

		if ( ! array_key_exists( 'value', $args ) ) {
			throw new \Exception( 'value is required.' );
		}
		$previous = get_user_meta( $user_id, $key, true );
		update_user_meta( $user_id, $key, $args['value'] );
		return array(
			'user_id'        => $user_id,
			'key'            => $key,
			'previous_value' => $previous,
			'value'          => get_user_meta( $user_id, $key, true ),
		);
	}
}
