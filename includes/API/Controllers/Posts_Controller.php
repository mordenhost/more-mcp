<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Posts_Controller extends Base_Controller {
    public function register_routes() {
        $this->register( '/posts', [
            [ 'methods' => 'GET', 'callback' => 'get_posts', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'POST', 'callback' => 'create_post', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/posts/(?P<id>\d+)', [
            [ 'methods' => 'GET', 'callback' => 'get_post', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'PUT', 'callback' => 'update_post', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'DELETE', 'callback' => 'delete_post', 'permission_callback' => 'check_permission' ],
        ] );
    }
}
