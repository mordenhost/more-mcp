<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class ACF {

	
	public static function is_available() {
		return function_exists( 'get_field' ) && function_exists( 'acf_get_field_groups' );
	}

	
	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'acf_get_field',
				'description' => 'Get a single ACF (Advanced Custom Fields) field value from a post, formatted per the field\'s Return Format setting (image arrays, post objects, parsed repeater rows, etc.). Use this instead of wp_get_post_meta when ACF is active — wp_get_post_meta returns the raw serialized value, this returns the hydrated value the ACF UI would show.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'field_name' => [ 'type' => 'string', 'description' => 'ACF field name or field key (e.g. "subtitle" or "field_abc123")' ],
						'post_id'    => [ 'type' => 'integer', 'description' => 'Post or page ID' ],
					],
					'required'   => [ 'field_name', 'post_id' ],
				],
			],
			[
				'name'        => 'acf_get_fields',
				'description' => 'Get every ACF field defined for a post in one call, with each field\'s name, label, type, and formatted value. The most efficient way for an AI agent to discover what ACF fields exist on a post and read them all without round-trips.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'acf_update_field',
				'description' => 'Update an ACF field value on a post. Accepts the field name (or field key) and a value matching the field type — scalar for text/number/select-single, array for repeater rows / flexible content / select-multi, post ID for post_object/relationship, attachment ID for image/file. ACF stores it correctly per field type.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'field_name' => [ 'type' => 'string', 'description' => 'ACF field name or field key' ],
						'post_id'    => [ 'type' => 'integer', 'description' => 'Post or page ID' ],
						'value'      => [ 'description' => 'New value. Type depends on the field type — see ACF documentation.' ],
					],
					'required'   => [ 'field_name', 'post_id', 'value' ],
				],
			],
			[
				'name'        => 'acf_get_field_groups',
				'description' => 'Enumerate ACF field groups on this site with the fields they contain. Optionally filter by post type to discover which field groups apply to a given content type. Use this to discover what custom fields are available before reading or writing them.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'description' => 'Optional post type slug (e.g. "post", "page", "product") — returns only field groups attached to that post type. Omit to return all field groups.' ],
					],
				],
			],
		];
	}

	
	public static function execute_tool( $name, $args ) {
		
		
		
		
		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use ACF tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Advanced Custom Fields is not active' );
		}

		switch ( $name ) {
			case 'acf_get_field':
				$field_name = sanitize_text_field( $args['field_name'] ?? '' );
				$post_id    = intval( $args['post_id'] ?? 0 );
				if ( '' === $field_name || $post_id <= 0 ) {
					throw new \Exception( 'field_name and post_id are required' );
				}
				if ( ! get_post( $post_id ) ) {
					throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
				}
				
				
				if ( ! current_user_can( 'read_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to read ACF fields on this post.' );
				}
				$field_object = get_field_object( $field_name, $post_id, true, true );
				if ( ! $field_object ) {
					return [
						'post_id'    => $post_id,
						'field_name' => $field_name,
						'value'      => null,
						'exists'     => false,
						'message'    => 'No ACF field with that name/key is registered for this post.',
					];
				}
				return [
					'post_id'    => $post_id,
					'field_name' => $field_object['name'] ?? $field_name,
					'field_key'  => $field_object['key'] ?? '',
					'label'      => $field_object['label'] ?? '',
					'type'       => $field_object['type'] ?? '',
					'value'      => self::flatten_value( $field_object['value'] ?? null ),
					'exists'     => true,
				];

			case 'acf_get_fields':
				$post_id = intval( $args['post_id'] ?? 0 );
				if ( $post_id <= 0 ) {
					throw new \Exception( 'post_id is required' );
				}
				if ( ! get_post( $post_id ) ) {
					throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
				}
				if ( ! current_user_can( 'read_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to read ACF fields on this post.' );
				}
				$objects = get_field_objects( $post_id, true, true );
				if ( ! $objects ) {
					return [
						'post_id' => $post_id,
						'fields'  => [],
						'message' => 'No ACF fields are attached to this post.',
					];
				}
				$fields = [];
				foreach ( $objects as $field_name => $object ) {
					$fields[] = [
						'name'  => $field_name,
						'key'   => $object['key'] ?? '',
						'label' => $object['label'] ?? '',
						'type'  => $object['type'] ?? '',
						'value' => self::flatten_value( $object['value'] ?? null ),
					];
				}
				return [
					'post_id' => $post_id,
					'fields'  => $fields,
				];

			case 'acf_update_field':
				$field_name = sanitize_text_field( $args['field_name'] ?? '' );
				$post_id    = intval( $args['post_id'] ?? 0 );
				if ( '' === $field_name || $post_id <= 0 ) {
					throw new \Exception( 'field_name and post_id are required' );
				}
				if ( ! get_post( $post_id ) ) {
					throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
				}
				
				
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					throw new \Exception( 'You do not have permission to edit ACF fields on this post.' );
				}
				if ( ! array_key_exists( 'value', $args ) ) {
					throw new \Exception( 'value is required (pass null to clear the field)' );
				}
				$result = update_field( $field_name, $args['value'], $post_id );
				if ( false === $result ) {
					throw new \Exception( 'Failed to update ACF field "' . esc_html( $field_name ) . '" on post ' . esc_html( (string) $post_id ) );
				}
				$updated = get_field_object( $field_name, $post_id, true, true );
				return [
					'post_id'    => $post_id,
					'field_name' => $updated['name'] ?? $field_name,
					'field_key'  => $updated['key'] ?? '',
					'type'       => $updated['type'] ?? '',
					'value'      => self::flatten_value( $updated['value'] ?? null ),
					'success'    => true,
				];

			case 'acf_get_field_groups':
				
				
				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'You do not have permission to discover ACF field groups.' );
				}
				$filter = [];
				if ( ! empty( $args['post_type'] ) ) {
					$filter['post_type'] = sanitize_key( $args['post_type'] );
				}
				$groups  = acf_get_field_groups( $filter );
				$results = [];
				foreach ( $groups as $group ) {
					$group_key = $group['key'] ?? '';
					$fields    = function_exists( 'acf_get_fields' ) ? acf_get_fields( $group_key ) : [];
					$field_summary = [];
					if ( is_array( $fields ) ) {
						foreach ( $fields as $field ) {
							$field_summary[] = [
								'name'  => $field['name'] ?? '',
								'key'   => $field['key'] ?? '',
								'label' => $field['label'] ?? '',
								'type'  => $field['type'] ?? '',
							];
						}
					}
					$results[] = [
						'key'      => $group_key,
						'title'    => $group['title'] ?? '',
						'location' => $group['location'] ?? [],
						'fields'   => $field_summary,
					];
				}
				return [
					'count'        => count( $results ),
					'field_groups' => $results,
				];

			default:
				throw new \Exception( 'Unknown ACF tool: ' . esc_html( $name ) );
		}
	}

	
	private static function flatten_value( $value ) {
		if ( is_null( $value ) || is_scalar( $value ) ) {
			return $value;
		}
		if ( $value instanceof \WP_Post ) {
			return [
				'id'         => (int) $value->ID,
				'title'      => $value->post_title,
				'post_type'  => $value->post_type,
				'status'     => $value->post_status,
				'permalink'  => get_permalink( $value ),
			];
		}
		if ( $value instanceof \WP_User ) {
			return [
				'id'           => (int) $value->ID,
				'display_name' => $value->display_name,
				'user_email'   => $value->user_email,
			];
		}
		if ( $value instanceof \WP_Term ) {
			return [
				'id'       => (int) $value->term_id,
				'name'     => $value->name,
				'slug'     => $value->slug,
				'taxonomy' => $value->taxonomy,
			];
		}
		if ( is_array( $value ) ) {
			$flat = [];
			foreach ( $value as $k => $v ) {
				$flat[ $k ] = self::flatten_value( $v );
			}
			return $flat;
		}
		
		return is_object( $value ) ? get_object_vars( $value ) : null;
	}
}
