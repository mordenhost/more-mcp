<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaBox {

	public static function is_available() {
		return function_exists( 'rwmb_meta' ) && class_exists( '\RWMB_Loader' ) || defined( 'RWMB_VER' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'metabox' ),
			'capabilities' => array( 'custom_fields' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'mb_get_field',
				'description' => 'Get a single Meta Box field value from a post, hydrated per field type (image fields as attachment data, group/cloneable fields as nested structures, etc.). Use this instead of wp_get_post_meta when Meta Box is active: the raw meta API returns the serialized storage, this returns the value the Meta Box editor shows.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer', 'description' => 'Post the field belongs to.' ),
						'field_id' => array( 'type' => 'string', 'description' => 'Meta Box field id.' ),
					),
					'required'   => array( 'post_id', 'field_id' ),
				),
			),
			array(
				'name'        => 'mb_get_fields',
				'description' => 'Read every Meta Box field registered for a post\'s post type, hydrated. Discovery + read in one call. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post to read fields from.' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'mb_update_field',
				'description' => 'Write one Meta Box field value on a post through Meta Box\'s own setter (rwmb_set_meta), so the value is stored the way Meta Box expects for that field type. Requires edit permission on the post. Emits an undo token restoring the prior value.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'field_id' => array( 'type' => 'string' ),
						'value'    => array( 'description' => 'New value. Shape depends on the field type; for simple fields a scalar, for others the structure Meta Box stores.' ),
					),
					'required'   => array( 'post_id', 'field_id', 'value' ),
				),
			),
			array(
				'name'        => 'mb_get_field_groups',
				'description' => 'Enumerate registered Meta Box fields grouped by the post type they attach to: field id, name/label, and type. Optionally filter to one post type. Read-only discovery.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'Optional post-type slug to filter to.' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Meta Box tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Meta Box is not active.' );
		}

		switch ( $name ) {
			case 'mb_get_field':
				return self::get_field( intval( $args['post_id'] ?? 0 ), sanitize_text_field( $args['field_id'] ?? '' ) );
			case 'mb_get_fields':
				return self::get_fields( intval( $args['post_id'] ?? 0 ) );
			case 'mb_update_field':
				return self::update_field( intval( $args['post_id'] ?? 0 ), sanitize_text_field( $args['field_id'] ?? '' ), $args['value'] ?? null );
			case 'mb_get_field_groups':
				return self::get_field_groups( isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : '' );
			default:
				throw new \Exception( 'Unknown Meta Box tool: ' . esc_html( $name ) );
		}
	}

	private static function get_field( $post_id, $field_id ) {
		if ( $post_id <= 0 || '' === $field_id ) {
			throw new \Exception( 'post_id and field_id are required.' );
		}
		if ( ! get_post( $post_id ) ) {
			throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to read fields on this post.' );
		}
		$value = rwmb_meta( $field_id, array(), $post_id );
		return array(
			'provider' => 'metabox',
			'post_id'  => $post_id,
			'field_id' => $field_id,
			'value'    => self::flatten_value( $value ),
		);
	}

	private static function get_fields( $post_id ) {
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to read fields on this post.' );
		}

		$defs   = self::registered_fields( $post->post_type );
		$fields = array();
		foreach ( $defs as $def ) {
			$field_id = isset( $def['id'] ) ? (string) $def['id'] : '';
			if ( '' === $field_id ) {
				continue;
			}
			$fields[] = array(
				'field_id' => $field_id,
				'name'     => (string) ( $def['name'] ?? '' ),
				'type'     => (string) ( $def['type'] ?? '' ),
				'value'    => self::flatten_value( rwmb_meta( $field_id, array(), $post_id ) ),
			);
		}
		return array(
			'provider' => 'metabox',
			'post_id'  => $post_id,
			'fields'   => $fields,
		);
	}

	private static function update_field( $post_id, $field_id, $value ) {
		if ( $post_id <= 0 || '' === $field_id ) {
			throw new \Exception( 'post_id and field_id are required.' );
		}
		if ( ! get_post( $post_id ) ) {
			throw new \Exception( 'Post not found for ID ' . esc_html( (string) $post_id ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'You do not have permission to edit fields on this post.' );
		}
		if ( ! function_exists( 'rwmb_set_meta' ) ) {
			throw new \Exception( 'Meta Box write helper (rwmb_set_meta) is unavailable in this version.' );
		}

		$prior = rwmb_meta( $field_id, array(), $post_id );
		$undo  = \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'metabox_field_write',
				'summary'      => 'Restore Meta Box field ' . $field_id . ' on post #' . $post_id,
				'target'       => array( 'post_id' => (int) $post_id, 'field_id' => (string) $field_id ),
				'pre_op_state' => array( 'value' => $prior ),
			)
		);

		rwmb_set_meta( $post_id, $field_id, $value );

		return array(
			'written'  => true,
			'provider' => 'metabox',
			'post_id'  => (int) $post_id,
			'field_id' => (string) $field_id,
			'value'    => self::flatten_value( rwmb_meta( $field_id, array(), $post_id ) ),
			'undo'     => $undo,
		);
	}

	private static function get_field_groups( $post_type ) {
		$grouped = array();
		foreach ( self::fields_by_object_type() as $type => $defs ) {
			if ( '' !== $post_type && $type !== $post_type ) {
				continue;
			}
			$rows = array();
			foreach ( (array) $defs as $def ) {
				if ( empty( $def['id'] ) ) {
					continue;
				}
				$rows[] = array(
					'field_id' => (string) $def['id'],
					'name'     => (string) ( $def['name'] ?? '' ),
					'type'     => (string) ( $def['type'] ?? '' ),
				);
			}
			$grouped[] = array( 'post_type' => (string) $type, 'fields' => $rows );
		}
		return array( 'provider' => 'metabox', 'groups' => $grouped );
	}

	private static function fields_by_object_type() {
		if ( ! function_exists( 'rwmb_get_registry' ) ) {
			return array();
		}
		$registry = rwmb_get_registry( 'field' );
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_by_object_type' ) ) {
			return array();
		}
		$by_type = $registry->get_by_object_type( 'post' );
		return is_array( $by_type ) ? $by_type : array();
	}

	private static function registered_fields( $post_type ) {
		$by_type = self::fields_by_object_type();
		if ( ! isset( $by_type[ $post_type ] ) || ! is_array( $by_type[ $post_type ] ) ) {
			return array();
		}
		return $by_type[ $post_type ];
	}

	private static function flatten_value( $value ) {
		if ( $value instanceof \WP_Post ) {
			return array( 'post_id' => $value->ID, 'title' => $value->post_title );
		}
		if ( $value instanceof \WP_Term ) {
			return array( 'term_id' => $value->term_id, 'name' => $value->name );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::flatten_value( $v );
			}
			return $out;
		}
		if ( is_object( $value ) ) {
			return get_object_vars( $value );
		}
		return $value;
	}

	public static function undo_field_write( array $snapshot ) {
		$target   = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();
		$post_id  = isset( $target['post_id'] ) ? (int) $target['post_id'] : 0;
		$field_id = isset( $target['field_id'] ) ? (string) $target['field_id'] : '';
		$prior    = isset( $snapshot['pre_op_state']['value'] ) ? $snapshot['pre_op_state']['value'] : null;

		if ( $post_id <= 0 || '' === $field_id ) {
			throw new \Exception( 'Meta Box undo snapshot is incomplete.' );
		}
		if ( ! function_exists( 'rwmb_set_meta' ) ) {
			throw new \Exception( 'Meta Box is no longer active; cannot undo.' );
		}
		rwmb_set_meta( $post_id, $field_id, $prior );

		return array(
			'success'  => true,
			'op'       => 'metabox_field_write',
			'provider' => 'metabox',
			'post_id'  => $post_id,
			'field_id' => $field_id,
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}
}
