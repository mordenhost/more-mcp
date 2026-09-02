<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Controller extends Base_Controller {
    public function register_routes() {
        $this->register( '/site', [
            'methods' => 'GET',
            'callback' => 'get_site_info',
            'permission_callback' => 'check_permission',
        ] );
        $this->register( '/search', [
            'methods' => 'GET',
            'callback' => 'search_content',
            'permission_callback' => 'check_permission',
        ] );
    }
}
