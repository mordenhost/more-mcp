<?php
/**
 * Plugin Name: More MCP – Secure AI Connector for Claude, ChatGPT & Gemini
 * Plugin URI: https://github.com/mordenhost/more-mcp
 * Description: Integrate Model Context Protocol (MCP) servers with WordPress to enable LLM interactions with your site
 * Version: 0.6.0
 * Author: Sadewadee
 * Author URI: https://github.com/mordenhost/more-mcp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: more-mcp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */


if (!defined('ABSPATH')) {
    exit;
}


define('MORE_MCP_VERSION', '0.6.0');
define('MORE_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MORE_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MORE_MCP_PLUGIN_FILE', __FILE__);
define('MORE_MCP_PLUGIN_BASENAME', plugin_basename(__FILE__));


if ( ! defined( 'MORE_MCP_DOCS_URL' ) ) {
    define('MORE_MCP_DOCS_URL', 'https://github.com/mordenhost/more-mcp');
}


spl_autoload_register(function ($class) {
    $prefix = 'More_MCP\\';
    $base_dir = MORE_MCP_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});


class More_MCP_Plugin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'maybe_upgrade_db'], 5);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('rest_api_init', [$this, 'register_mcp_endpoint']);

        
        
        
        
        
        
        
        
        add_filter('rest_post_dispatch', [$this, 'force_no_store_on_namespace'], 10, 3);

        
        add_action('init', [$this, 'register_oauth_rewrites']);
        add_filter('query_vars', [$this, 'register_oauth_query_vars']);
        add_action('parse_request', [$this, 'handle_oauth_request']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\OAuth\Token_Store::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Undo_Store::class, 'cleanup_expired']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Session_Store::class, 'cleanup_expired']);

        
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);

        
        
        
        \More_MCP\Integrations\Elementor_Coexistence::register_hooks();

        
        
        
        
        
        
        
        
        if ( function_exists( 'wp_register_ability_category' ) && (bool) get_option( 'more_mcp_abilities_registration_enabled', true ) ) {
            add_action( 'wp_abilities_api_categories_init', array( \More_MCP\Abilities\Categories::class, 'register' ) );
            add_action( 'wp_abilities_api_init', array( \More_MCP\Abilities\Registrar::class, 'register' ) );

            
            
            
            if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
                add_action( 'mcp_adapter_init', array( \More_MCP\Abilities\MCP_Adapter_Server::class, 'register' ) );
            }
        }
    }

    
    public function force_no_store_on_namespace( $response, $server, $request ) {
        if ( ! $response instanceof \WP_REST_Response ) {
            return $response;
        }
        $route = $request->get_route();
        if ( is_string( $route ) && 0 === strpos( $route, '/more-mcp/' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
            $response->header( 'Pragma', 'no-cache' );
        }
        return $response;
    }

    
    public function add_action_links($links) {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=more-mcp') . '">' . __('Settings', 'more-mcp') . '</a>',
            '<a href="' . esc_url( MORE_MCP_DOCS_URL ) . '" target="_blank">' . __('Docs', 'more-mcp') . '</a>',
        ];
        return array_merge($plugin_links, $links);
    }

    public function activate() {
        
        $this->create_tables();

        
        if ( class_exists( '\More_MCP\OAuth\Token_Store' ) ) {
            \More_MCP\OAuth\Token_Store::create_tables();
        } else {
            
            $token_store_file = MORE_MCP_PLUGIN_DIR . 'includes/OAuth/Token_Store.php';
            if ( file_exists( $token_store_file ) ) {
                require_once $token_store_file;
                \More_MCP\OAuth\Token_Store::create_tables();
            }
        }

        
        
        
        if ( class_exists( '\More_MCP\MCP\Session_Store' ) ) {
            \More_MCP\MCP\Session_Store::create_tables();
        } else {
            $session_store_file = MORE_MCP_PLUGIN_DIR . 'includes/MCP/Session_Store.php';
            if ( file_exists( $session_store_file ) ) {
                require_once $session_store_file;
                \More_MCP\MCP\Session_Store::create_tables();
            }
        }

        
        
        
        
        
        
        
        if ( ! class_exists( '\More_MCP\Auth\Api_Key' ) ) {
            $api_key_file = MORE_MCP_PLUGIN_DIR . 'includes/Auth/Api_Key.php';
            if ( file_exists( $api_key_file ) ) {
                require_once $api_key_file;
            }
        }
        add_option('more_mcp_settings', [
            'enabled' => false,
            'platforms' => [],
            'api_key' => \More_MCP\Auth\Api_Key::generate(),
        ]);

        
        $this->register_oauth_rewrites();

        
        flush_rewrite_rules();

        
        if ( ! wp_next_scheduled( 'more_mcp_token_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'more_mcp_token_cleanup' );
        }

        
        update_option('more_mcp_db_version', MORE_MCP_VERSION);
    }

    
    public function maybe_upgrade_db() {
        if (get_option('more_mcp_db_version') === MORE_MCP_VERSION
            && $this->required_tables_exist()) {
            return;
        }

        $token_store_ok = false;
        if (class_exists('\More_MCP\OAuth\Token_Store')) {
            \More_MCP\OAuth\Token_Store::create_tables();
            $token_store_ok = true;
        } else {
            $f = MORE_MCP_PLUGIN_DIR . 'includes/OAuth/Token_Store.php';
            if (file_exists($f)) {
                require_once $f;
                \More_MCP\OAuth\Token_Store::create_tables();
                $token_store_ok = true;
            }
        }

        $session_store_ok = false;
        if (class_exists('\More_MCP\MCP\Session_Store')) {
            \More_MCP\MCP\Session_Store::create_tables();
            $session_store_ok = true;
        } else {
            $f = MORE_MCP_PLUGIN_DIR . 'includes/MCP/Session_Store.php';
            if (file_exists($f)) {
                require_once $f;
                \More_MCP\MCP\Session_Store::create_tables();
                $session_store_ok = true;
            }
        }

        if ($token_store_ok && $session_store_ok) {
            update_option('more_mcp_db_version', MORE_MCP_VERSION);
        }
        
    }

    
    private function required_tables_exist() {
        global $wpdb;
        $required = [
            $wpdb->prefix . 'more_mcp_oauth_clients',
            $wpdb->prefix . 'more_mcp_sessions',
        ];
        foreach ($required as $table) {
            
            
            
            $like = $wpdb->esc_like($table);
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)) !== $table) {
                return false;
            }
        }
        return true;
    }

    public function deactivate() {
        
        wp_clear_scheduled_hook( 'more_mcp_token_cleanup' );

        
        flush_rewrite_rules();
    }

    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'more_mcp_logs';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            mcp_server varchar(255) NOT NULL,
            action varchar(100) NOT NULL,
            request_data longtext,
            response_data longtext,
            status varchar(50) NOT NULL,
            PRIMARY KEY  (id),
            KEY timestamp (timestamp),
            KEY mcp_server (mcp_server)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    

    
    public function register_oauth_rewrites() {
        add_rewrite_rule( '\.well-known/oauth-protected-resource(/.*)?$', 'index.php?more_mcp_oauth=protected_resource', 'top' );
        add_rewrite_rule( '\.well-known/oauth-authorization-server/?$', 'index.php?more_mcp_oauth=metadata', 'top' );
        add_rewrite_rule( 'authorize/?$', 'index.php?more_mcp_oauth=authorize', 'top' );
        add_rewrite_rule( 'token/?$', 'index.php?more_mcp_oauth=token', 'top' );
        add_rewrite_rule( 'register/?$', 'index.php?more_mcp_oauth=register', 'top' );
    }

    
    public function register_oauth_query_vars( $vars ) {
        $vars[] = 'more_mcp_oauth';
        return $vars;
    }

    
    public function handle_oauth_request( $wp ) {
        if ( empty( $wp->query_vars['more_mcp_oauth'] ) ) {
            return;
        }

        
        $action = sanitize_text_field( $wp->query_vars['more_mcp_oauth'] );
        if ( 'metadata' !== $action ) {
            $settings = get_option( 'more_mcp_settings', [] );
            if ( empty( $settings['enabled'] ) ) {
                status_header( 503 );
                header( 'Content-Type: application/json' );
                echo wp_json_encode( [ 'error' => 'server_error', 'error_description' => 'More MCP is currently disabled.' ] );
                exit;
            }
        }

        
        
        
        
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( 'More MCP Self-Check' === $ua && in_array( $action, [ 'register', 'authorize', 'token' ], true ) ) {
            status_header( 204 );
            header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
            exit;
        }

        $oauth_server = new More_MCP\OAuth\Server();
        $oauth_server->dispatch( $action );
        
        exit;
    }

    public function init() {
        
        

        
        More_MCP\MCP\Tool_Profiles::register();

        
        if (is_admin()) {
            new More_MCP\Admin\Settings_Page();
            new More_MCP\Admin\Well_Known_Notice();
        }
    }

    public function register_rest_routes() {
        $api = new More_MCP\API\REST_Controller();
        $api->register_routes();
    }

    public function register_mcp_endpoint() {
        $server = new More_MCP\MCP\Server();

        
        
        
        
        
        register_rest_route('more-mcp/v1', '/mcp', [
            'methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
            'callback' => [$server, 'handle_mcp'],
            'permission_callback' => '__return_true', 
        ]);

        
        
        
        register_rest_route('more-mcp', '/v1', [
            'methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
            'callback' => [$server, 'handle_mcp'],
            'permission_callback' => '__return_true', 
        ]);

        
        
        register_rest_route('more-mcp/v1', '/sse', [
            'methods' => 'GET',
            'callback' => [$server, 'handle_sse'],
            'permission_callback' => '__return_true', 
        ]);

        
        
        register_rest_route('more-mcp/v1', '/messages', [
            'methods' => 'POST',
            'callback' => [$server, 'handle_message'],
            'permission_callback' => '__return_true', 
        ]);
    }
}


function more_mcp_init() {
    return More_MCP_Plugin::get_instance();
}


more_mcp_init();
