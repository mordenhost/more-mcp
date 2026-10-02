<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IvorySearch {

	const FORM_POST_TYPE = 'is_search_form';

	public static function is_available() {

		return defined( 'IS_VERSION' ) && class_exists( '\IS_Index_Model' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'ivory-search' ),
			'capabilities' => array( 'search' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'ivory_get_index_status',
				'description' => 'Ivory Search index health: whether the inverted index exists, the number of indexed posts (distinct) and distinct terms, the total index size (row count), and the number of configured search forms. Read through Ivory Search\'s own count helpers over its {prefix}is_inverted_index table. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'ivory_get_forms',
				'description' => 'List the search forms Ivory Search has configured (the is_search_form custom post type): id, title, and status. Ivory Search lets an admin define multiple named search forms; this is their inventory. Form definitions only, no submitted queries. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'ivory_get_search_forms',
				'description' => 'List Ivory Search forms through the plugin\'s own IS_Search_Form::find(), including which of the five config groups (includes, excludes, settings, ajax, customize) each form has populated. Form definitions only — the config values themselves may hold query scoping and are reported as presence flags, not their contents. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Maximum forms to return (default 50, max 200 — find() defaults to posts_per_page=-1, so this caps it server-side).' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use search tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Ivory Search is not active.' );
		}
		if ( 'ivory_get_index_status' === $name ) {
			return self::get_index_status();
		}
		if ( 'ivory_get_forms' === $name ) {
			return self::get_forms();
		}
		if ( 'ivory_get_search_forms' === $name ) {
			return self::get_search_forms( $args );
		}
		throw new \Exception( 'Unknown Ivory Search tool: ' . esc_html( $name ) );
	}

	private static function get_index_status() {

		$posts = self::safe_count( 'count_indexed_posts' );
		$terms = self::safe_count( 'count_indexed_terms' );
		$size  = self::safe_count( 'count_index_size' );

		$exists = null;
		if ( method_exists( '\IS_Index_Model', 'verify_is_index_table_exists' ) ) {
			$exists = (bool) \IS_Index_Model::verify_is_index_table_exists();
		}

		return array(
			'provider'     => 'ivory-search',
			'index_exists' => $exists,
			'indexed_posts' => $posts,
			'indexed_terms' => $terms,
			'index_size'   => $size,
			'search_forms' => self::form_count(),
		);
	}

	private static function get_forms() {
		if ( ! post_type_exists( self::FORM_POST_TYPE ) ) {
			return array( 'provider' => 'ivory-search', 'forms' => array() );
		}
		$query = new \WP_Query(
			array(
				'post_type'      => self::FORM_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$forms = array();
		foreach ( (array) $query->posts as $post ) {
			$forms[] = array(
				'id'     => (int) $post->ID,
				'title'  => (string) get_the_title( $post ),
				'status' => (string) $post->post_status,
			);
		}
		return array( 'provider' => 'ivory-search', 'forms' => $forms );
	}

	private static function safe_count( $method ) {
		if ( ! method_exists( '\IS_Index_Model', $method ) ) {
			return null;
		}
		try {
			return (int) \IS_Index_Model::$method();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function get_search_forms( $args ) {
		if ( ! class_exists( 'IS_Search_Form' ) || ! method_exists( 'IS_Search_Form', 'find' ) ) {
			return array( 'provider' => 'ivory-search', 'available' => false, 'message' => 'IS_Search_Form::find() is not available.', 'forms' => array() );
		}

		$limit = max( 1, min( 200, isset( $args['limit'] ) ? (int) $args['limit'] : 50 ) );
		
		$objs = \IS_Search_Form::find( array( 'posts_per_page' => $limit ) );

		$groups = array( '_is_includes', '_is_excludes', '_is_settings', '_is_ajax', '_is_customize' );

		$forms = array();
		foreach ( (array) $objs as $form ) {
			if ( ! is_object( $form ) ) {
				continue;
			}
			$id = method_exists( $form, 'id' ) ? (int) $form->id() : 0;
			$config = array();
			foreach ( $groups as $g ) {
				$v = method_exists( $form, 'prop' ) ? $form->prop( $g ) : null;
				$config[ $g ] = is_array( $v ) ? ( count( $v ) > 0 ) : ! empty( $v );
			}
			$forms[] = array(
				'id'      => $id,
				'title'   => method_exists( $form, 'title' ) ? (string) $form->title() : '',
				'status'  => $id ? (string) get_post_status( $id ) : '',
				'config'  => $config,
			);
		}

		return array(
			'provider'   => 'ivory-search',
			'total'      => count( $forms ),
			'limited_to' => $limit,
			'forms'      => $forms,
		);
	}

	private static function form_count() {
		if ( ! post_type_exists( self::FORM_POST_TYPE ) ) {
			return 0;
		}
		$counts = wp_count_posts( self::FORM_POST_TYPE );
		$total  = 0;
		foreach ( array( 'publish', 'draft' ) as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}
		return $total;
	}
}
