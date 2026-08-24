<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pages_Controller extends Base_Controller {
    public function register_routes() {
        $this->register( '/pages', [
            [ 'methods' => 'GET', 'callback' => 'get_pages', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'POST', 'callback' => 'create_page', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/pages/(?P<id>\d+)', [
            [ 'methods' => 'GET', 'callback' => 'get_page', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'PUT', 'callback' => 'update_page', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'DELETE', 'callback' => 'delete_page', 'permission_callback' => 'check_permission' ],
        ] );
    }
}
