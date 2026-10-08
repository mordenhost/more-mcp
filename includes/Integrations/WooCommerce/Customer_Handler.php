<?php

namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Customer_Handler {

	const NAMES = array( 'wc_get_customer', 'wc_create_customer', 'wc_update_customer', 'wc_delete_customer' );

	const ADDRESS_KEYS = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		$address = array(
			'type'        => 'object',
			'description' => 'Address fields: first_name, last_name, company, address_1, address_2, city, state, postcode, country, and for billing also email and phone.',
		);
		return array(
			array(
				'name'        => 'wc_get_customer',
				'description' => 'Get one WooCommerce customer by user ID or email, with billing and shipping addresses, order count and total spent.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer', 'description' => 'Customer user ID' ),
						'email' => array( 'type' => 'string', 'description' => 'Customer email (used when id is not given)' ),
					),
				),
			),
			array(
				'name'        => 'wc_create_customer',
				'description' => 'Create a WooCommerce customer account. A password is generated when none is given; WooCommerce sends its new-account email as usual.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'email'      => array( 'type' => 'string', 'description' => 'Email address' ),
						'username'   => array( 'type' => 'string', 'description' => 'Username (generated from the email when omitted)' ),
						'password'   => array( 'type' => 'string', 'description' => 'Password (generated when omitted)' ),
						'first_name' => array( 'type' => 'string' ),
						'last_name'  => array( 'type' => 'string' ),
						'billing'    => $address,
						'shipping'   => $address,
					),
					'required'   => array( 'email' ),
				),
			),
			array(
				'name'        => 'wc_update_customer',
				'description' => 'Update a WooCommerce customer: email, names and billing/shipping address. Only the fields you pass change. Passwords and roles are not changed here.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'integer', 'description' => 'Customer user ID' ),
						'email'      => array( 'type' => 'string' ),
						'first_name' => array( 'type' => 'string' ),
						'last_name'  => array( 'type' => 'string' ),
						'billing'    => $address,
						'shipping'   => $address,
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wc_delete_customer',
				'description' => 'Permanently delete a WooCommerce customer account (customers only; other roles are refused). Orders stay on the store as guest orders. Optionally reassign the customer\'s posts to another user.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Customer user ID' ),
						'reassign' => array( 'type' => 'integer', 'description' => 'User ID to reassign the customer\'s posts to (default: none)' ),
					),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
			case 'wc_get_customer':
				if ( ! current_user_can( 'list_users' ) ) {
					throw new \Exception( 'list_users capability required.' );
				}
				if ( ! empty( $args['id'] ) ) {
					$user = get_userdata( (int) $args['id'] );
				} elseif ( ! empty( $args['email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( (string) $args['email'] ) );
				} else {
					throw new \Exception( 'id or email is required.' );
				}
				$user = self::require_customer( $user );
				return self::format_customer( new \WC_Customer( $user->ID ) );

			case 'wc_create_customer':
				if ( ! current_user_can( 'create_users' ) ) {
					throw new \Exception( 'create_users capability required.' );
				}
				$email = sanitize_email( (string) ( $args['email'] ?? '' ) );
				if ( '' === $email || ! is_email( $email ) ) {
					throw new \Exception( 'A valid email is required.' );
				}
				$username = isset( $args['username'] ) ? sanitize_user( (string) $args['username'], true ) : '';
				$password = isset( $args['password'] ) ? (string) $args['password'] : '';
				if ( '' === $password ) {
					$password = wp_generate_password( 20 );
				}
				$user_id = wc_create_new_customer(
					$email,
					$username,
					$password,
					array(
						'first_name' => sanitize_text_field( (string) ( $args['first_name'] ?? '' ) ),
						'last_name'  => sanitize_text_field( (string) ( $args['last_name'] ?? '' ) ),
					)
				);
				if ( is_wp_error( $user_id ) ) {
					throw new \Exception( esc_html( $user_id->get_error_message() ) );
				}
				$customer = new \WC_Customer( (int) $user_id );
				self::apply_addresses( $customer, $args );
				$customer->save();
				return array(
					'id'      => (int) $user_id,
					'email'   => $customer->get_email(),
					'message' => 'Customer created successfully',
				);

			case 'wc_update_customer':
				$user = self::require_customer( get_userdata( (int) ( $args['id'] ?? 0 ) ) );
				if ( ! current_user_can( 'edit_user', $user->ID ) ) {
					throw new \Exception( 'edit_user capability required on this customer.' );
				}
				$customer = new \WC_Customer( $user->ID );
				if ( isset( $args['email'] ) ) {
					$email = sanitize_email( (string) $args['email'] );
					if ( '' === $email || ! is_email( $email ) ) {
						throw new \Exception( 'A valid email is required.' );
					}
					$owner = email_exists( $email );
					if ( $owner && (int) $owner !== (int) $user->ID ) {
						throw new \Exception( 'That email address belongs to another account.' );
					}
					$customer->set_email( $email );
				}
				if ( isset( $args['first_name'] ) ) {
					$customer->set_first_name( sanitize_text_field( (string) $args['first_name'] ) );
				}
				if ( isset( $args['last_name'] ) ) {
					$customer->set_last_name( sanitize_text_field( (string) $args['last_name'] ) );
				}
				self::apply_addresses( $customer, $args );
				$customer->save();
				return array(
					'id'      => (int) $user->ID,
					'message' => 'Customer updated successfully',
				);

			case 'wc_delete_customer':
				$user = self::require_customer( get_userdata( (int) ( $args['id'] ?? 0 ) ) );
				if ( get_current_user_id() === (int) $user->ID ) {
					throw new \Exception( 'You cannot delete your own account.' );
				}
				if ( ! current_user_can( 'delete_user', $user->ID ) ) {
					throw new \Exception( 'delete_user capability required on this customer.' );
				}
				$reassign = ! empty( $args['reassign'] ) ? (int) $args['reassign'] : null;
				if ( null !== $reassign && ( $reassign === (int) $user->ID || ! get_userdata( $reassign ) ) ) {
					throw new \Exception( 'reassign must be another existing user.' );
				}
				require_once ABSPATH . 'wp-admin/includes/user.php';
				if ( ! wp_delete_user( $user->ID, $reassign ) ) {
					throw new \Exception( 'Failed to delete customer' );
				}
				return array(
					'id'      => (int) $user->ID,
					'message' => 'Customer permanently deleted',
				);
		}
		throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
	}

	private static function require_customer( $user ) {
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			throw new \Exception( 'Customer not found' );
		}
		$roles = (array) $user->roles;
		if ( array( 'customer' ) !== array_values( $roles ) ) {
			throw new \Exception( 'That user is not a customer-only account. Use the WordPress user tools for other roles.' );
		}
		return $user;
	}

	private static function apply_addresses( \WC_Customer $customer, array $args ) {
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( empty( $args[ $type ] ) || ! is_array( $args[ $type ] ) ) {
				continue;
			}
			foreach ( self::ADDRESS_KEYS as $key ) {
				if ( ! isset( $args[ $type ][ $key ] ) ) {
					continue;
				}
				if ( 'shipping' === $type && 'email' === $key ) {
					continue;
				}
				$value  = 'email' === $key ? sanitize_email( (string) $args[ $type ][ $key ] ) : sanitize_text_field( (string) $args[ $type ][ $key ] );
				$setter = 'set_' . $type . '_' . $key;
				if ( is_callable( array( $customer, $setter ) ) ) {
					$customer->$setter( $value );
				}
			}
		}
	}

	private static function format_customer( \WC_Customer $customer ) {
		$keys    = self::ADDRESS_KEYS;
		$address = static function ( string $type ) use ( $customer, $keys ) {
			$out = array();
			foreach ( $keys as $key ) {
				if ( 'shipping' === $type && 'email' === $key ) {
					continue;
				}
				$getter = 'get_' . $type . '_' . $key;
				if ( is_callable( array( $customer, $getter ) ) ) {
					$out[ $key ] = $customer->$getter();
				}
			}
			return $out;
		};
		$created = $customer->get_date_created();
		return array(
			'id'           => $customer->get_id(),
			'email'        => $customer->get_email(),
			'username'     => $customer->get_username(),
			'first_name'   => $customer->get_first_name(),
			'last_name'    => $customer->get_last_name(),
			'display_name' => $customer->get_display_name(),
			'is_paying'    => (bool) $customer->get_is_paying_customer(),
			'order_count'  => (int) $customer->get_order_count(),
			'total_spent'  => $customer->get_total_spent(),
			'date_created' => $created ? $created->date( 'Y-m-d H:i:s' ) : null,
			'billing'      => $address( 'billing' ),
			'shipping'     => $address( 'shipping' ),
		);
	}
}
