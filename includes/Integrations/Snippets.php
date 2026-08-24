<?php
namespace More_MCP\Integrations;

use More_MCP\Lifecycle\Guard;
use More_MCP\MCP\Undo_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippets {

	const TOGGLE_KEY = 'allow_code_snippets';

	public static function is_available() {
		return defined( 'CODE_SNIPPETS_VERSION' ) || function_exists( 'get_snippets' ) && function_exists( 'save_snippet' );
	}

	private static function toggle_on() {
		$settings = get_option( 'more_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'code-snippets' ),
			'capabilities' => array( 'snippets' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		
		$tools = array(
			array(
				'name'        => 'snippet_list',
				'description' => 'List code snippets managed by the Code Snippets plugin. Returns id, name, scope, type (php/css/js/html), and active state for each — not the code bodies. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'snippet_get',
				'description' => 'Get one code snippet by id, including its code body, scope, type, description, and active state. Read-only. Seeing a PHP snippet\'s body does not run it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Snippet id.' ) ),
					'required'   => array( 'id' ),
				),
			),
		);
		
		if ( self::toggle_on() ) {
			$tools[] = array(
				'name'        => 'snippet_create',
				'description' => 'Create a CSS or JS code snippet. PHP snippets cannot be created (that is remote code execution) and are refused. The snippet is always saved DISABLED: a human must activate it in the Code Snippets UI before it runs — this tool never activates code. Requires the "Allow code snippets" admin toggle, manage_options, and two-part confirmation (confirm=true plus confirm_slug echoing the returned slug). Emits an undo token. Markdown code fences in the body are stripped.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'name'         => array( 'type' => 'string', 'description' => 'Snippet name.' ),
						'type'         => array( 'type' => 'string', 'enum' => array( 'css', 'js' ), 'description' => 'css or js. PHP is not permitted.' ),
						'code'         => array( 'type' => 'string', 'description' => 'The CSS or JS body. Markdown fences are stripped.' ),
						'description'  => array( 'type' => 'string', 'description' => 'Optional description.' ),
						'confirm'      => array( 'type' => 'boolean', 'description' => 'Set true (with confirm_slug) to write. Without it, a preview is returned and nothing is saved.' ),
						'confirm_slug' => array( 'type' => 'string', 'description' => 'Echo the slug from the preview response to confirm.' ),
					),
					'required'   => array( 'name', 'type', 'code' ),
				),
			);
			$tools[] = array(
				'name'        => 'snippet_update',
				'description' => 'Update the code and/or name of an existing CSS or JS snippet. A PHP snippet cannot be updated through this tool and is refused. The snippet\'s active state is never changed — an active snippet stays active, a disabled one stays disabled. Requires the "Allow code snippets" toggle, manage_options, and two-part confirmation. Emits an undo token.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer', 'description' => 'Snippet id to update.' ),
						'code'         => array( 'type' => 'string', 'description' => 'New CSS/JS body. Markdown fences stripped.' ),
						'name'         => array( 'type' => 'string', 'description' => 'New name (optional).' ),
						'confirm'      => array( 'type' => 'boolean' ),
						'confirm_slug' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			);
		}
		return $tools;
	}

	public static function execute_tool( $name, $args ) {
		
		if ( ! self::is_available() ) {
			throw new \Exception( 'A supported code-snippets plugin is not active.' );
		}
		switch ( $name ) {
			case 'snippet_list':
				return self::list_snippets();
			case 'snippet_get':
				return self::get_one( $args );
			case 'snippet_create':
				return self::create( $args );
			case 'snippet_update':
				return self::update( $args );
		}
		throw new \Exception( 'Unknown snippets tool: ' . esc_html( $name ) );
	}

	private static function require_read_cap() {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to read code snippets.' );
		}
	}

	private static function list_snippets() {
		self::require_read_cap();
		$out = array();
		if ( function_exists( 'get_snippets' ) ) {
			$snippets = get_snippets();
			if ( is_array( $snippets ) ) {
				foreach ( $snippets as $s ) {
					$out[] = array(
						'id'     => (int) ( $s->id ?? 0 ),
						'name'   => (string) ( $s->name ?? '' ),
						'scope'  => (string) ( $s->scope ?? '' ),
						'type'   => (string) ( $s->type ?? '' ),
						'active' => (bool) ( $s->active ?? false ),
					);
				}
			}
		}
		return array( 'provider' => 'code-snippets', 'count' => count( $out ), 'snippets' => $out );
	}

	private static function get_one( $args ) {
		self::require_read_cap();
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( $id <= 0 || ! function_exists( 'get_snippet' ) ) {
			throw new \Exception( 'A valid snippet id is required.' );
		}
		$s = get_snippet( $id );
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
		);
	}

	private static function require_write_gates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to write code snippets.' );
		}
		if ( ! self::toggle_on() ) {
			throw new \Exception( 'Code-snippet writes are disabled. Enable "Allow code snippets" under More MCP settings first.' );
		}
	}

	private static function scope_for_type( $type ) {
		if ( 'css' === $type ) {
			return 'site-css';
		}
		if ( 'js' === $type ) {
			return 'site-footer-js';
		}
		return '';
	}

	private static function clean_code( $code ) {
		$code = (string) $code;
		$code = trim( $code );
		
		$code = preg_replace( '/^```[a-zA-Z0-9]*\s*\n?/', '', $code );
		
		$code = preg_replace( '/\n?```\s*$/', '', $code );
		return trim( $code );
	}

	private static function create( $args ) {
		self::require_write_gates();
		$type = isset( $args['type'] ) ? strtolower( sanitize_key( $args['type'] ) ) : '';
		if ( 'php' === $type ) {
			throw new \Exception( 'PHP snippet writes are not supported: creating executable PHP is remote code execution. Only css and js snippets can be created.' );
		}
		if ( ! in_array( $type, array( 'css', 'js' ), true ) ) {
			throw new \Exception( 'type must be "css" or "js".' );
		}
		$name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		if ( '' === $name ) {
			throw new \Exception( 'name is required.' );
		}
		$code = self::clean_code( $args['code'] ?? '' );
		if ( '' === $code ) {
			throw new \Exception( 'code is required.' );
		}
		
		if ( false !== stripos( $code, '<?php' ) ) {
			throw new \Exception( 'The code contains a PHP open tag; only css/js is permitted here.' );
		}

		$slug = Guard::slug_of( sanitize_title( $name ) ?: 'snippet' );
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'snippet_create',
				$slug,
				sprintf( 'Create a %s snippet "%s", saved DISABLED (you activate it in Code Snippets). Re-call with confirm=true and confirm_slug="%s".', $type, $name, $slug )
			);
		}

		$desc = isset( $args['description'] ) ? sanitize_text_field( $args['description'] ) : '';
		$id   = self::save_new( $name, $desc, $code, self::scope_for_type( $type ) );

		$undo = Undo_Store::store( array(
			'op'      => 'snippet_create',
			'summary' => sprintf( 'Created %s snippet "%s" (id %d, disabled)', $type, $name, $id ),
			'snippet_id' => $id,
		) );

		return array(
			'success'  => true,
			'id'       => $id,
			'type'     => $type,
			'active'   => false,
			'message'  => 'Snippet created and saved DISABLED. Activate it in the Code Snippets admin to make it run.',
			'undo'     => $undo,
		);
	}

	private static function update( $args ) {
		self::require_write_gates();
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( $id <= 0 || ! function_exists( 'get_snippet' ) ) {
			throw new \Exception( 'A valid snippet id is required.' );
		}
		$s = get_snippet( $id );
		if ( ! is_object( $s ) || (int) ( $s->id ?? 0 ) !== $id ) {
			throw new \Exception( 'Snippet not found: ' . intval( $id ) );
		}
		$type = (string) ( $s->type ?? '' );
		if ( 'php' === $type ) {
			throw new \Exception( 'This is a PHP snippet; PHP snippets cannot be updated through this tool. Only css/js snippets are writable.' );
		}
		if ( ! in_array( $type, array( 'css', 'js' ), true ) ) {
			throw new \Exception( 'Only css and js snippets can be updated (this one is type "' . esc_html( $type ) . '").' );
		}

		$new_code = array_key_exists( 'code', $args ) ? self::clean_code( $args['code'] ) : null;
		$new_name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : null;
		if ( null === $new_code && null === $new_name ) {
			throw new \Exception( 'Provide code and/or name to update.' );
		}
		if ( null !== $new_code && false !== stripos( $new_code, '<?php' ) ) {
			throw new \Exception( 'The code contains a PHP open tag; only css/js is permitted here.' );
		}

		$slug = Guard::slug_of( 'snippet-' . $id );
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'snippet_update',
				$slug,
				sprintf( 'Update %s snippet id %d. Its active state is unchanged. Re-call with confirm=true and confirm_slug="%s".', $type, $id, $slug )
			);
		}

		$undo = Undo_Store::store( array(
			'op'         => 'snippet_update',
			'summary'    => sprintf( 'Updated %s snippet id %d', $type, $id ),
			'snippet_id' => $id,
			'prev_code'  => (string) ( $s->code ?? '' ),
			'prev_name'  => (string) ( $s->name ?? '' ),
		) );

		$data = array(
			'id'     => $id,
			'name'   => null !== $new_name ? $new_name : (string) ( $s->name ?? '' ),
			'desc'   => (string) ( $s->desc ?? '' ),
			'code'   => null !== $new_code ? $new_code : (string) ( $s->code ?? '' ),
			'scope'  => (string) ( $s->scope ?? self::scope_for_type( $type ) ),
			'active' => (bool) ( $s->active ?? false ), 
		);
		$saved = save_snippet( $data );
		if ( ! is_object( $saved ) ) {
			throw new \Exception( 'Failed to update the snippet.' );
		}
		return array(
			'success' => true,
			'id'      => $id,
			'type'    => $type,
			'message' => 'Snippet updated. Active state left unchanged.',
			'undo'    => $undo,
		);
	}

	private static function save_new( $name, $desc, $code, $scope ) {
		$data = array(
			'name'   => $name,
			'desc'   => $desc,
			'code'   => $code,
			'scope'  => $scope,
			'active' => false, 
		);
		$saved = save_snippet( $data );
		if ( ! is_object( $saved ) || (int) ( $saved->id ?? 0 ) <= 0 ) {
			throw new \Exception( 'Failed to create the snippet.' );
		}
		return (int) $saved->id;
	}
}
