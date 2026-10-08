<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Bridge implements Handler {

	const TOOLS = array( 'wp_rest_routes', 'wp_rest_read', 'wp_rest_write', 'wp_rest_delete' );

	const MAX_RESPONSE_BYTES = 200000;

	const DENY_ALL = array(
		'#^/more-mcp(/|$)#i',
		'#^/batch/v1(/|$)#i',
		'#/application-passwords(/|$)#i',
	);

	const DENY_WRITE = array(
		'#^/wp/v2/plugins(/|$)#i',
		'#^/wp/v2/themes(/|$)#i',
		'#^/wp/v2/users(/|$)#i',
		'#^/wp/v2/settings(/|$)#i',

		
		'#^/code-snippets(/|$)#i',
		'#^/wpcode(/|$)#i',
		'#^/insert-headers-and-footers(/|$)#i',
		'#^/wp/v2/(elementor_snippet|wpcode|wpcode_snippets|code_snippets)(/|$)#i',
	);

	public static function get_tools(): array {
		$route  = array( 'type' => 'string', 'description' => 'Route such as /wp/v2/posts or /wp/v2/posts/12 (a leading /wp-json is accepted and dropped). Use wp_rest_routes to discover routes.' );
		return array(
			array(
				'name'        => 'wp_rest_routes',
				'description' => 'List the REST routes registered on this site, with the HTTP methods each accepts. Filter by namespace (for example wp/v2) or search text. Use it to find the route for something no dedicated tool covers. Needs privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'namespace' => array( 'type' => 'string', 'description' => 'Only routes in this namespace, e.g. wp/v2 or wc/v3. Omit to list the namespaces and route counts.' ),
						'search'    => array( 'type' => 'string', 'description' => 'Only routes whose path contains this text.' ),
						'limit'     => array( 'type' => 'integer', 'description' => 'Routes to return (default 100, max 300).' ),
						'offset'    => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'wp_rest_read',
				'description' => 'GET a REST route as the connected user and return its JSON. Pass query parameters in params (per_page, search, _fields, context, _embed...). Large responses are cut at about 200 KB: narrow them with _fields or per_page. Needs privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => $route,
						'params' => array( 'type' => 'object', 'description' => 'Query parameters.' ),
					),
					'required'   => array( 'route' ),
				),
			),
			array(
				'name'        => 'wp_rest_write',
				'description' => 'POST, PUT or PATCH a REST route as the connected user (create or update through any registered endpoint). Plugins, themes, users, site settings, application passwords, code-snippet plugins and this plugin\'s own routes are refused here; use their dedicated tools. Needs privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => $route,
						'method' => array( 'type' => 'string', 'enum' => array( 'POST', 'PUT', 'PATCH' ), 'description' => 'Default POST.' ),
						'body'   => array( 'type' => 'object', 'description' => 'JSON body.' ),
						'params' => array( 'type' => 'object', 'description' => 'Query parameters.' ),
					),
					'required'   => array( 'route' ),
				),
			),
			array(
				'name'        => 'wp_rest_delete',
				'description' => 'DELETE through a REST route as the connected user. Many core routes move items to the trash unless force:true is passed in params. Needs privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => $route,
						'params' => array( 'type' => 'object', 'description' => 'Query parameters, e.g. {"force": true}.' ),
					),
					'required'   => array( 'route' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_rest_routes':
				return self::routes( $args );
			case 'wp_rest_read':
				return self::dispatch( 'GET', $args );
			case 'wp_rest_write':
				$method = strtoupper( (string) ( $args['method'] ?? 'POST' ) );
				if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
					throw new \Exception( 'method must be POST, PUT or PATCH.' );
				}
				return self::dispatch( $method, $args );
			case 'wp_rest_delete':
				return self::dispatch( 'DELETE', $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	

	private static function denied( string $route, bool $write ): bool {

		$route = (string) preg_replace( '#/{2,}#', '/', $route );
		foreach ( self::DENY_ALL as $pattern ) {
			if ( preg_match( $pattern, $route ) ) {
				return true;
			}
		}
		if ( $write ) {
			foreach ( self::DENY_WRITE as $pattern ) {
				if ( preg_match( $pattern, $route ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function routes( array $args ): array {
		$server = rest_get_server();
		$all    = $server->get_routes();

		$namespace = isset( $args['namespace'] ) ? trim( (string) $args['namespace'], '/' ) : '';
		$search    = isset( $args['search'] ) ? (string) $args['search'] : '';

		if ( '' === $namespace && '' === $search ) {
			
			$namespaces = array();
			foreach ( $server->get_namespaces() as $ns ) {
				$namespaces[ $ns ] = 0;
			}
			foreach ( array_keys( $all ) as $route ) {
				if ( self::denied( $route, false ) ) {
					continue;
				}
				foreach ( array_keys( $namespaces ) as $ns ) {
					if ( 0 === strpos( $route, '/' . $ns . '/' ) || '/' . $ns === $route ) {
						++$namespaces[ $ns ];
						break;
					}
				}
			}
			ksort( $namespaces );
			return array(
				'namespaces' => $namespaces,
				'hint'       => 'Pass namespace (for example wp/v2) to list its routes.',
			);
		}

		$rows = array();
		foreach ( $all as $route => $handlers ) {
			if ( '' !== $namespace && 0 !== strpos( $route, '/' . $namespace . '/' ) && '/' . $namespace !== $route ) {
				continue;
			}
			if ( '' !== $search && false === stripos( $route, $search ) ) {
				continue;
			}
			if ( self::denied( $route, false ) ) {
				continue;
			}
			$methods = array();
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( (array) ( $handler['methods'] ?? array() ) ) as $m ) {
					$methods[ $m ] = true;
				}
			}
			$rows[] = array(
				'route'   => $route,
				'methods' => array_keys( $methods ),
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['route'], $b['route'] );
			}
		);

		$limit  = max( 1, min( 300, (int) ( $args['limit'] ?? 100 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		return array(
			'total'  => count( $rows ),
			'offset' => $offset,
			'routes' => array_slice( $rows, $offset, $limit ),
		);
	}

	

	private static function normalize( $route ): string {
		$route = (string) $route;
		if ( false !== strpos( $route, "\0" ) ) {
			throw new \Exception( 'route must be a REST route such as /wp/v2/posts.' );
		}
		$route = trim( $route );
		$route = (string) preg_replace( '#^https?://[^/]+#i', '', $route );
		$route = (string) preg_replace( '#/{2,}#', '/', $route );
		$route = (string) preg_replace( '#^/?wp-json#i', '', $route );
		$query = strpos( $route, '?' );
		if ( false !== $query ) {
			$route = substr( $route, 0, $query );
		}
		$route = '/' . ltrim( $route, '/' );
		if ( '/' === $route || false !== strpos( $route, '..' ) || false !== strpos( $route, "\0" ) ) {
			throw new \Exception( 'route must be a REST route such as /wp/v2/posts.' );
		}
		return rtrim( $route, '/' );
	}

	private static function dispatch( string $method, array $args ): array {
		$route = self::normalize( $args['route'] ?? '' );
		$write = 'GET' !== $method;

		if ( self::denied( $route, $write ) ) {
			throw new \Exception( 'That route is not available through the REST bridge (' . esc_html( $route ) . '). Plugins, themes, users, site settings, application passwords and this plugin\'s own routes have dedicated tools or are closed on purpose.' );
		}

		$request = new \WP_REST_Request( $method, $route );
		$params  = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
		foreach ( $params as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}
		if ( 'GET' !== $method && 'DELETE' !== $method && isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $args['body'] ) );
			foreach ( $args['body'] as $key => $value ) {
				$request->set_param( (string) $key, $value );
			}
		}

		$response = rest_do_request( $request );
		$server   = rest_get_server();
		$status   = (int) $response->get_status();
		$embed    = isset( $params['_embed'] ) ? rest_parse_embed_param( $params['_embed'] ) : false;
		$data     = $server->response_to_data( $response, $embed );

		$headers = array();
		foreach ( $response->get_headers() as $name => $value ) {
			if ( in_array( strtolower( $name ), array( 'x-wp-total', 'x-wp-totalpages', 'allow', 'location' ), true ) ) {
				$headers[ $name ] = $value;
			}
		}

		$out = array(
			'route'   => $route,
			'method'  => $method,
			'status'  => $status,
			'ok'      => $status >= 200 && $status < 300,
			'headers' => (object) $headers,
		);

		$json = wp_json_encode( $data );
		if ( is_string( $json ) && strlen( $json ) > self::MAX_RESPONSE_BYTES ) {
			$out['truncated'] = true;
			$out['bytes']     = strlen( $json );
			$out['note']      = 'Response larger than the limit. Narrow it with _fields, per_page or a more specific route.';
			if ( is_array( $data ) && array_values( $data ) === $data ) {
				$kept = array();
				$size = 2;
				foreach ( $data as $item ) {
					$piece = (string) wp_json_encode( $item );
					if ( $size + strlen( $piece ) > self::MAX_RESPONSE_BYTES ) {
						break;
					}
					$size  += strlen( $piece ) + 1;
					$kept[] = $item;
				}
				$out['data']          = $kept;
				$out['items_shown']   = count( $kept );
				$out['items_in_page'] = count( $data );
			} else {
				$out['data'] = null;
			}
		} else {
			$out['data'] = $data;
		}

		if ( ! $out['ok'] ) {
			$out['error'] = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : 'The route returned an error.';
		}
		return $out;
	}
}
