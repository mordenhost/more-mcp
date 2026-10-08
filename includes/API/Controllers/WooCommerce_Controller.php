<?php
namespace More_MCP\API\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerce_Controller extends Base_Controller {
    public function register_routes() {
        $this->register( '/products/attributes', [
            [ 'methods' => 'GET', 'callback' => 'get_product_attributes', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'POST', 'callback' => 'create_product_attribute', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/products/attributes/(?P<attribute_id>\d+)/terms', [
            [ 'methods' => 'GET', 'callback' => 'get_attribute_terms', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/products/(?P<id>\d+)/variations', [
            [ 'methods' => 'GET', 'callback' => 'get_product_variations', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'POST', 'callback' => 'create_variation', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/products/(?P<id>\d+)/variations/(?P<variation_id>\d+)', [
            [ 'methods' => 'GET', 'callback' => 'get_variation', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'PUT', 'callback' => 'update_variation', 'permission_callback' => 'check_permission' ],
            [ 'methods' => 'DELETE', 'callback' => 'delete_variation', 'permission_callback' => 'check_permission' ],
        ] );
        $this->register( '/products/(?P<id>\d+)/attributes', [
            [ 'methods' => 'POST', 'callback' => 'set_product_attributes', 'permission_callback' => 'check_permission' ],
        ] );
    }
}
