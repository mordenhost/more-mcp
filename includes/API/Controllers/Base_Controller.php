<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Base_Controller {
    protected $controller;
    protected $namespace = 'more-mcp/v1';

    public function __construct( \More_MCP\API\REST_Controller $controller ) {
        $this->controller = $controller;
    }

    protected function register( $route, $definitions ) {
        register_rest_route( $this->namespace, $route, $this->bind( $definitions ) );
    }

    private function bind( $definitions ) {
        $definitions = is_array( $definitions ) && isset( $definitions['callback'] )
            ? [ $definitions ]
            : $definitions;
        foreach ( $definitions as &$definition ) {
            if ( isset( $definition['callback'] ) && is_string( $definition['callback'] ) ) {
                $definition['callback'] = [ $this->controller, $definition['callback'] ];
            }
            if ( isset( $definition['permission_callback'] ) && 'check_permission' === $definition['permission_callback'] ) {
                $definition['permission_callback'] = [ $this->controller, 'check_permission' ];
            }
        }
        return $definitions;
    }
}
