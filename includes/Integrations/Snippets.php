<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippets {

	const PROVIDERS = array( 'code-snippets', 'wpcode' );

	const WPCODE_POST_TYPE = 'wpcode';

	const LIST_LIMIT = 500;

	public static function is_available() {
		return array() !== self::available_providers();
	}

	public static function available_providers() {
		$out = array();
		if ( self::code_snippets_available() ) {
			$out[] = 'code-snippets';
		}
		if ( self::wpcode_available() ) {
			$out[] = 'wpcode';
		}
		return $out;
	}

	public static function code_snippets_available() {

		

		return function_exists( 'get_snippets' );
	}

	public static function wpcode_available() {

		
		return function_exists( 'post_type_exists' )
			&& post_type_exists( self::WPCODE_POST_TYPE )
			&& ( function_exists( 'wpcode' ) || defined( 'WPCODE_VERSION' ) );
	}

	public static function get_manifest() {
		$providers = self::available_providers();
		return array(
			'providers'    => array() === $providers ? self::PROVIDERS : $providers,
			'capabilities' => array( 'snippets' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		$provider = array(
			'type'        => 'string',
			'enum'        => self::PROVIDERS,
			'description' => 'Which snippet plugin to read. Optional while only one is active; required when both Code Snippets and WPCode are.',
		);
		return array(
			array(
				'name'        => 'snippet_list',
				'description' => 'List code snippets managed by Code Snippets or WPCode. Returns id, name, type (php/css/js/html, plus text/universal/scss on WPCode) and active state for each, plus scope and locked state (Code Snippets) or insert location, auto-insert flag, priority and tags (WPCode) — not the code bodies. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'provider' => $provider ),
				),
			),
			array(
				'name'        => 'snippet_get',
				'description' => 'Get one code snippet by id, including its code body, type, description / note and active state, plus scope and locked state (Code Snippets) or insert location, auto-insert flag, priority, device type, tags, shortcode and last error (WPCode). Read-only. Seeing a PHP snippet\'s body does not run it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => $provider,
						'id'       => array( 'type' => 'integer', 'description' => 'Snippet id.' ),
					),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		if ( ! self::is_available() ) {
			throw new \Exception( 'A supported code-snippets plugin is not active.' );
		}
		switch ( $name ) {
			case 'snippet_list':
				return self::list_snippets( $args );
			case 'snippet_get':
				return self::get_one( $args );
		}
		throw new \Exception( 'Unknown snippets tool: ' . esc_html( $name ) );
	}

	private static function resolve_provider( $args ) {
		$available = self::available_providers();
		$wanted    = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '';
		if ( '' !== $wanted ) {
			if ( ! in_array( $wanted, self::PROVIDERS, true ) ) {
				throw new \Exception( 'provider must be one of: ' . esc_html( implode( ', ', self::PROVIDERS ) ) . '.' );
			}
			if ( ! in_array( $wanted, $available, true ) ) {
				throw new \Exception( 'The ' . esc_html( $wanted ) . ' plugin is not active.' );
			}
			return $wanted;
		}
		if ( count( $available ) > 1 ) {
			throw new \Exception( 'Both Code Snippets and WPCode are active; pass provider (' . esc_html( implode( ' or ', $available ) ) . ').' );
		}
		return $available[0];
	}

	private static function require_read_cap() {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to read code snippets.' );
		}
	}

	private static function list_snippets( $args = array() ) {
		self::require_read_cap();
		$provider = self::resolve_provider( is_array( $args ) ? $args : array() );
		if ( 'wpcode' === $provider ) {
			return self::wpcode_list();
		}
		$out = array();
		if ( function_exists( 'get_snippets' ) ) {
			$snippets = \get_snippets();
			if ( is_array( $snippets ) ) {
				foreach ( $snippets as $s ) {
					$out[] = array(
						'id'     => (int) ( $s->id ?? 0 ),
						'name'   => (string) ( $s->name ?? '' ),
						'scope'  => (string) ( $s->scope ?? '' ),
						'type'   => (string) ( $s->type ?? '' ),
						'active' => (bool) ( $s->active ?? false ),

						'locked' => (bool) ( $s->locked ?? false ),
					);
				}
			}
		}
		return array( 'provider' => 'code-snippets', 'count' => count( $out ), 'snippets' => $out );
	}

	private static function get_one( $args ) {
		self::require_read_cap();
		$args     = is_array( $args ) ? $args : array();
		$provider = self::resolve_provider( $args );
		$id       = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( 'wpcode' === $provider ) {
			return self::wpcode_get( $id );
		}
		if ( $id <= 0 || ! function_exists( 'get_snippet' ) ) {
			throw new \Exception( 'A valid snippet id is required.' );
		}
		$s = \get_snippet( $id );
		if ( ! is_object( $s ) || (int) ( $s->id ?? 0 ) !== $id ) {
			throw new \Exception( 'Snippet not found: ' . intval( $id ) );
		}
		return array(
			'id'          => (int) $s->id,
			'name'        => (string) ( $s->name ?? '' ),
			'scope'       => (string) ( $s->scope ?? '' ),
			'type'        => (string) ( $s->type ?? '' ),
			'description' => (string) ( $s->desc ?? '' ),
			'code'        => (string) ( $s->code ?? '' ),
			'active'      => (bool) ( $s->active ?? false ),
			
			'locked'      => (bool) ( $s->locked ?? false ),
		);
	}

	private static function wpcode_list() {
		$posts = get_posts(
			array(
				'post_type'        => self::WPCODE_POST_TYPE,

				'post_status'      => array( 'publish', 'draft' ),
				'posts_per_page'   => self::LIST_LIMIT + 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$truncated = count( $posts ) > self::LIST_LIMIT;
		$posts     = array_slice( $posts, 0, self::LIST_LIMIT );

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = self::wpcode_row( $post );
		}
		$result = array( 'provider' => 'wpcode', 'count' => count( $out ), 'snippets' => $out );
		if ( $truncated ) {
			$result['truncated'] = true;
			$result['note']      = 'Only the first ' . self::LIST_LIMIT . ' snippets are listed.';
		}
		return $result;
	}

	private static function wpcode_get( $id ) {
		if ( $id <= 0 ) {
			throw new \Exception( 'A valid snippet id is required.' );
		}
		$post = get_post( $id );
		if ( ! $post || self::WPCODE_POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			throw new \Exception( 'Snippet not found: ' . intval( $id ) );
		}

		$row = self::wpcode_row( $post );
		$pid = (int) $post->ID;

		$insert_number = absint( get_post_meta( $pid, '_wpcode_auto_insert_number', true ) );
		$device        = (string) get_post_meta( $pid, '_wpcode_device_type', true );
		$custom        = (string) get_post_meta( $pid, '_wpcode_custom_shortcode', true );

		return array_merge(
			$row,
			array(
				
				'description'               => (string) get_post_meta( $pid, '_wpcode_note', true ),
				'code'                      => (string) $post->post_content,
				'insert_number'             => 0 === $insert_number ? 1 : $insert_number,
				'device_type'               => '' === $device ? 'any' : $device,
				'conditional_logic_enabled' => (bool) get_post_meta( $pid, '_wpcode_conditional_logic_enabled', true ),
				'shortcode'                 => '[wpcode id="' . $pid . '"]',
				'custom_shortcode'          => $custom,
				'last_error'                => self::wpcode_last_error( $pid ),
			)
		);
	}

	private static function wpcode_row( $post ) {
		$pid      = (int) $post->ID;
		$priority = get_post_meta( $pid, '_wpcode_priority', true );
		return array(
			'id'          => $pid,
			'name'        => (string) $post->post_title,
			'type'        => self::wpcode_term( $pid, 'wpcode_type' ),
			'active'      => 'publish' === $post->post_status,
			'location'    => self::wpcode_term( $pid, 'wpcode_location' ),
			
			'auto_insert' => 1 === absint( get_post_meta( $pid, '_wpcode_auto_insert', true ) ),
			
			'priority'    => '' === $priority ? 10 : (int) $priority,
			'tags'        => self::wpcode_tags( $pid ),
		);
	}

	private static function wpcode_term( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return (string) $terms[0]->slug;
	}

	private static function wpcode_tags( $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'wpcode_tags' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return array_values( array_map( static function ( $term ) {
			return (string) $term->name;
		}, $terms ) );
	}

	private static function wpcode_last_error( $post_id ) {
		$error = get_post_meta( $post_id, '_wpcode_last_error', true );
		if ( ! is_array( $error ) || ! isset( $error['message'] ) ) {
			return null;
		}
		$out = array(
			'message' => substr( (string) $error['message'], 0, 500 ),
			'type'    => isset( $error['wpc_type'] ) ? (string) $error['wpc_type'] : 'error',
		);
		if ( isset( $error['line'] ) ) {
			$out['line'] = (int) $error['line'];
		}
		if ( isset( $error['time'] ) ) {
			$out['time'] = (int) $error['time'];
		}
		return $out;
	}
}
