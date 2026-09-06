<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pods {

	public static function is_available() {
		return function_exists( 'pods_api' ) || defined( 'PODS_VERSION' ) || class_exists( 'PodsInit' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'pods' ),
			'capabilities' => array( 'data_models' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'pods_list_models',
				'description' => 'List every content model registered through Pods: its name, label, the WordPress type it maps to (post_type, taxonomy, user, comment, media, or Pods\' own table storage), whether it extends an existing core type, and how many fields and groups it defines. Returns model DEFINITIONS only — never the posts/terms or field values, which are read through the core post/term/meta tools. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => 'Optional filter by model type: post_type, taxonomy, user, comment, media, pod (Pods table storage), or settings. Omit to list all models.',
						),
					),
				),
			),
			array(
				'name'        => 'pods_get_model',
				'description' => 'Read one Pods model\'s field schema by its name: the model\'s type and storage, plus every field with its name, label, field type, required and repeatable flags, and — for relationship fields — the related object TYPE and NAME (never the related rows themselves). Returns the schema DEFINITION only, not field values or content. Use the core post/term tools to read the model\'s content and the meta tools to read its field values. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'The Pod (model) name, e.g. "book" or "event". Get it from pods_list_models.',
						),
					),
					'required'   => array( 'name' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use data-model tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Pods is not active.' );
		}

		if ( 'pods_list_models' === $name ) {
			return self::list_models( $args );
		}
		if ( 'pods_get_model' === $name ) {
			return self::get_model( $args );
		}
		throw new \Exception( 'Unknown data-model tool: ' . esc_html( $name ) );
	}

	private static function list_models( $args ) {
		if ( ! function_exists( 'pods_api' ) ) {
			throw new \Exception( 'Pods API is unavailable on this version.' );
		}

		$api = pods_api();
		if ( ! is_object( $api ) || ! method_exists( $api, 'load_pods' ) ) {
			throw new \Exception( 'Pods API is unavailable on this version.' );
		}

		$type   = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : '';
		$params = array();
		if ( '' !== $type ) {
			$params['type'] = $type;
		}

		$pods = $api->load_pods( $params );
		if ( ! is_array( $pods ) ) {
			$pods = array();
		}

		$models = array();
		foreach ( $pods as $pod ) {
			if ( ! is_object( $pod ) ) {
				continue;
			}
			$models[] = self::summarize_pod( $pod );
		}

		usort(
			$models,
			static function ( $a, $b ) {
				return strcmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return array(
			'provider'    => 'pods',
			'available'   => true,
			'total'       => count( $models ),
			'filter_type' => '' !== $type ? $type : null,
			'models'      => $models,
		);
	}

	private static function get_model( $args ) {
		$name = isset( $args['name'] ) ? trim( (string) $args['name'] ) : '';
		if ( '' === $name ) {
			throw new \Exception( 'name (the Pod/model name) is required.' );
		}
		if ( ! function_exists( 'pods_api' ) ) {
			throw new \Exception( 'Pods API is unavailable on this version.' );
		}

		$api = pods_api();
		if ( ! is_object( $api ) || ! method_exists( $api, 'load_pod' ) ) {
			throw new \Exception( 'Pods API is unavailable on this version.' );
		}

		$pod = $api->load_pod( array( 'name' => $name ) );
		if ( ! is_object( $pod ) ) {
			throw new \Exception( 'Pods model "' . esc_html( $name ) . '" was not found.' );
		}

		$summary = self::summarize_pod( $pod );

		$fields     = self::pod_fields( $pod );
		$field_list = array();
		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}
			$field_list[] = self::describe_field( $field );
		}

		$summary['fields'] = $field_list;
		return array(
			'provider'  => 'pods',
			'available' => true,
			'model'     => $summary,
		);
	}

	private static function summarize_pod( $pod ) {
		$name    = method_exists( $pod, 'get_name' ) ? (string) $pod->get_name() : '';
		$label   = method_exists( $pod, 'get_label' ) ? (string) $pod->get_label() : '';
		$type    = method_exists( $pod, 'get_type' ) ? (string) $pod->get_type() : '';
		$storage = method_exists( $pod, 'get_storage' ) ? (string) $pod->get_storage() : '';

		$extended = null;
		if ( method_exists( $pod, 'is_extended' ) ) {
			$extended = (bool) $pod->is_extended();
		}

		$field_count = null;
		$fields      = self::pod_fields( $pod );
		if ( is_array( $fields ) ) {
			$field_count = count( $fields );
		}

		$group_count = null;
		if ( method_exists( $pod, 'get_groups' ) ) {
			$groups      = $pod->get_groups();
			$group_count = is_array( $groups ) ? count( $groups ) : 0;
		}

		return array(
			'name'        => $name,
			'label'       => $label,
			'type'        => $type,
			'storage'     => $storage,
			'extended'    => $extended,
			'field_count' => $field_count,
			'group_count' => $group_count,
		);
	}

	private static function pod_fields( $pod ) {
		if ( ! method_exists( $pod, 'get_fields' ) ) {
			return array();
		}
		$fields = $pod->get_fields();
		return is_array( $fields ) ? $fields : array();
	}

	private static function describe_field( $field ) {
		$name  = method_exists( $field, 'get_name' ) ? (string) $field->get_name() : '';
		$label = method_exists( $field, 'get_label' ) ? (string) $field->get_label() : '';
		$type  = method_exists( $field, 'get_type' ) ? (string) $field->get_type() : '';

		$required = null;
		if ( method_exists( $field, 'is_required' ) ) {
			$required = (bool) $field->is_required();
		}
		$repeatable = null;
		if ( method_exists( $field, 'is_repeatable' ) ) {
			$repeatable = (bool) $field->is_repeatable();
		}

		$default = null;
		if ( method_exists( $field, 'get_arg' ) ) {
			$raw     = $field->get_arg( 'default_value' );
			$default = ( '' === $raw || null === $raw ) ? null : $raw;
		}

		$out = array(
			'name'       => $name,
			'label'      => $label,
			'type'       => $type,
			'required'   => $required,
			'repeatable' => $repeatable,
			'default'    => $default,
		);

		$is_rel = method_exists( $field, 'is_relationship' ) ? (bool) $field->is_relationship() : false;
		if ( $is_rel ) {
			$related_type = method_exists( $field, 'get_related_object_type' ) ? $field->get_related_object_type() : null;
			$related_name = method_exists( $field, 'get_related_object_name' ) ? $field->get_related_object_name() : null;
			$out['relationship'] = array(
				'is_relationship' => true,
				'related_type'    => ( null === $related_type || '' === $related_type ) ? null : (string) $related_type,
				'related_name'    => ( null === $related_name || '' === $related_name ) ? null : (string) $related_name,
			);
		}

		return $out;
	}
}
