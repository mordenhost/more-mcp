<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Controller extends Base_Controller {
    public function register_routes() {
        $this->register( '/media', [
            [ 'methods' => 'GET', 'callback' => 'get_media', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'POST', 'callback' => 'upload_media', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/media/(?P<id>\d+)', [
            [ 'methods' => 'GET', 'callback' => 'get_media_item', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'DELETE', 'callback' => 'delete_media', 'permission_callback' => 'check_permission' ],
        ] );
    }
}
