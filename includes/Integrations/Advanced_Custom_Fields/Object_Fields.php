<?php

namespace More_MCP\Integrations\Advanced_Custom_Fields;

use More_MCP\Integrations\Advanced_Custom_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Object_Fields {

	const NAMES = array( 'acf_get_term_fields', 'acf_get_user_fields', 'acf_update_user_fields' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		return array(
			array(
				'name'        => 'acf_get_term_fields',
				'description' => 'Get every ACF field set on a term (category, tag or custom taxonomy term) with each field\'s name, label, type and formatted value, as the ACF term screen would show it. Use instead of wp_get_term_meta, which returns raw stored values.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'  => array( 'type' => 'integer', 'description' => 'Term ID' ),
						'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy slug; optional, used to make sure the term belongs to it' ),
					),
					'required'   => array( 'term_id' ),
				),
			),
			array(
				'name'        => 'acf_get_user_fields',
				'description' => 'Get every ACF field set on a user profile with each field\'s name, label, type and formatted value. Needs the user\'s own account or the edit_user capability.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
					),
					'required'   => array( 'user_id' ),
				),
			),
			array(
				'name'        => 'acf_update_user_fields',
				'description' => 'Update ACF fields on a user profile. Pass fields as an object of field name (or key) to new value; every name must resolve to a field registered for that user, otherwise nothing is written. Needs the edit_user capability. Does not change the user\'s role, password or email.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
						'fields'  => array( 'type' => 'object', 'description' => 'Map of ACF field name or key to the new value, e.g. {"job_title": "Editor"}' ),
					),
					'required'   => array( 'user_id', 'fields' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
			case 'acf_get_term_fields':
				$term = self::term( $args );
				return array(
					'term_id'  => (int) $term->term_id,
					'taxonomy' => $term->taxonomy,
					'fields'   => self::read( 'term_' . (int) $term->term_id, array( 'taxonomy' => $term->taxonomy ) ),
				);

			case 'acf_get_user_fields':
				$user_id = self::user( $args, false );
				return array(
					'user_id' => $user_id,
					'fields'  => self::read( 'user_' . $user_id, self::user_screen( $user_id ) ),
				);

			case 'acf_update_user_fields':
				$user_id = self::user( $args, true );
				$fields  = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : null;
				if ( null === $fields || array() === $fields ) {
					throw new \Exception( 'fields must be a non-empty object of field name to value.' );
				}
				$object_id  = 'user_' . $user_id;
				$screen     = self::user_screen( $user_id );
				$registered = self::registered_fields( $screen );

				
				$targets = array();
				foreach ( $fields as $field_name => $value ) {
					$field_name = sanitize_text_field( (string) $field_name );
					$target     = '' === $field_name ? null : self::resolve( $registered, $field_name, $object_id );
					if ( null === $target ) {
						throw new \Exception( 'No ACF field "' . esc_html( $field_name ) . '" is registered for user ' . esc_html( (string) $user_id ) . '. Nothing was written. Check that the field group exists and its location rules include this user.' );
					}
					$targets[] = array( $field_name, $target['key'], $value );
				}

				$written = array();
				foreach ( $targets as list( $field_name, $field_key, $value ) ) {
					if ( false === update_field( $field_key, $value, $object_id ) ) {
						throw new \Exception( 'Failed to update ACF field "' . esc_html( $field_name ) . '" on user ' . esc_html( (string) $user_id ) . '. Fields written before it: ' . esc_html( implode( ', ', $written ) ?: 'none' ) . '.' );
					}
					$written[] = $field_name;
				}
				return array(
					'user_id' => $user_id,
					'updated' => $written,
					'fields'  => self::read( $object_id, $screen ),
					'success' => true,
				);
		}
		throw new \Exception( 'Unknown ACF tool: ' . esc_html( $name ) );
	}

	private static function term( array $args ) {
		$term_id = (int) ( $args['term_id'] ?? 0 );
		if ( $term_id <= 0 ) {
			throw new \Exception( 'term_id is required' );
		}
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : '';
		$term     = '' !== $taxonomy ? get_term( $term_id, $taxonomy ) : get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			throw new \Exception( 'Term not found for ID ' . esc_html( (string) $term_id ) );
		}
		return $term;
	}

	private static function user( array $args, bool $write ): int {
		$user_id = (int) ( $args['user_id'] ?? 0 );
		if ( $user_id <= 0 ) {
			throw new \Exception( 'user_id is required' );
		}
		if ( ! get_userdata( $user_id ) ) {
			throw new \Exception( 'User not found for ID ' . esc_html( (string) $user_id ) );
		}
		$own = get_current_user_id() === $user_id;
		if ( $write || ! $own ) {
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				throw new \Exception( 'You do not have permission to ' . ( $write ? 'edit' : 'read' ) . ' ACF fields on this user.' );
			}
		}
		return $user_id;
	}

	private static function user_screen( int $user_id ): array {
		$user = get_userdata( $user_id );
		$role = $user && ! empty( $user->roles ) ? (string) reset( $user->roles ) : '';
		return array(
			'user_id'   => $user_id,
			'user_form' => 'edit',
			'user_role' => $role,
		);
	}

	private static function registered_fields( array $screen ): array {
		$out = array();
		foreach ( (array) acf_get_field_groups( $screen ) as $group ) {
			foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
				if ( empty( $field['key'] ) || empty( $field['name'] ) || in_array( $field['type'] ?? '', array( 'tab', 'message', 'accordion' ), true ) ) {
					continue;
				}
				$out[ $field['key'] ] = $field;
			}
		}
		return $out;
	}

	private static function resolve( array $registered, string $selector, string $object_id ) {
		if ( isset( $registered[ $selector ] ) ) {
			return $registered[ $selector ];
		}
		foreach ( $registered as $field ) {
			if ( $field['name'] === $selector ) {
				return $field;
			}
		}
		$saved = get_field_object( $selector, $object_id, false, false );
		return is_array( $saved ) && ! empty( $saved['key'] ) ? $saved : null;
	}

	private static function read( string $object_id, array $screen ): array {
		$rows = array();
		foreach ( self::registered_fields( $screen ) as $key => $field ) {
			$rows[ $key ] = self::row( $field, get_field( $key, $object_id, true ) );
		}
		
		$objects = get_field_objects( $object_id, true, true );
		foreach ( is_array( $objects ) ? $objects : array() as $object ) {
			if ( is_array( $object ) && ! empty( $object['key'] ) && ! isset( $rows[ $object['key'] ] ) ) {
				$rows[ $object['key'] ] = self::row( $object, $object['value'] ?? null );
			}
		}
		return array_values( $rows );
	}

	private static function row( array $field, $value ): array {
		return array(
			'name'  => $field['name'] ?? '',
			'key'   => $field['key'] ?? '',
			'label' => $field['label'] ?? '',
			'type'  => $field['type'] ?? '',
			'value' => Advanced_Custom_Fields::flatten_value( $value ),
		);
	}
}
