<?php
/**
 * Plugin Name: Mordenhost MCP Server with OAuth 2.0 for Claude, ChatGPT and Gemini
 * Plugin URI: https://github.com/mordenhost/more-mcp
 * Description: A security-first Model Context Protocol (MCP) server for WordPress with OAuth 2.0 and API-key auth, rate limiting, audit logs, and typed tools for content, media, SEO, WooCommerce, and page builders.
 * Version: 0.18.0
 * Author: Sadewadee
 * Author URI: https://github.com/sadewadee
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: mordenhost-mcp-server
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */


if (!defined('ABSPATH')) {
    exit;
}





if (defined('MORE_MCP_VERSION')) {
    
    
    
    register_activation_hook(__FILE__, function () {
        if (!defined('MORE_MCP_PLUGIN_BASENAME') || MORE_MCP_PLUGIN_BASENAME === plugin_basename(__FILE__)) {
            return;
        }
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins(MORE_MCP_PLUGIN_BASENAME);
        if (!wp_next_scheduled('more_mcp_token_cleanup')) {
            wp_schedule_event(time(), 'daily', 'more_mcp_token_cleanup');
        }
    });
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html__('A second copy of this plugin is installed. Only the first copy is running; deactivate and delete the duplicate.', 'mordenhost-mcp-server')
        );
    });
    return;
}


define('MORE_MCP_VERSION', '0.18.0');
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


if ( ! class_exists( 'More_MCP_Plugin', false ) ) :


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
        
        
        More_MCP\Platform\Locked_Settings::register();

        
        More_MCP\Platform\White_Label::register();

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'maybe_upgrade_db'], 5);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('rest_api_init', [$this, 'register_mcp_endpoint']);

        
        
        
        
        
        
        
        
        add_filter('rest_post_dispatch', [$this, 'force_no_store_on_namespace'], 10, 3);

        
        add_action('init', [$this, 'register_oauth_rewrites']);
        add_action('init', [$this, 'maybe_flush_rewrites'], 99);
        add_filter('query_vars', [$this, 'register_oauth_query_vars']);
        add_action('parse_request', [$this, 'handle_oauth_request']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\OAuth\Token_Store::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\OAuth\Device_Flow::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Undo_Store::class, 'cleanup_expired']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Session_Store::class, 'cleanup_expired']);

        
        
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Log_Store::class, 'cleanup_expired']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Rate_Limiter::class, 'cleanup_expired']);

        
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Change_History::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\Access\Approvals::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\Auth\Api_Tokens::class, 'cleanup_expired']);

        
        
        
        
        
        add_action('before_delete_post', [\More_MCP\MCP\Edit_Trail_Store::class, 'delete_for_post']);

        
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);

        
        
        
        \More_MCP\Webhooks\Manager::register_listeners();

        
        
        \More_MCP\Platform\Multisite::register();

        
        
        \More_MCP\Access\Safety::register();

        
        
        \More_MCP\Tools\Preview_Links::register();

        
        
        
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
            '<a href="' . admin_url('admin.php?page=more-mcp') . '">' . __('Settings', 'mordenhost-mcp-server') . '</a>',
            '<a href="' . esc_url( MORE_MCP_DOCS_URL ) . '" target="_blank">' . __('Docs', 'mordenhost-mcp-server') . '</a>',
        ];
        return array_merge($plugin_links, $links);
    }

    
    public function activate($network_wide = false) {
        if ($network_wide && is_multisite()) {
            if (!class_exists('\More_MCP\Platform\Multisite')) {
                require_once MORE_MCP_PLUGIN_DIR . 'includes/Platform/Multisite.php';
            }
            \More_MCP\Platform\Multisite::for_each_site(function () {
                $this->activate_single();
            });
            return;
        }
        $this->activate_single();
    }

    
    public function activate_single() {
        
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

        
        
        if ( class_exists( '\More_MCP\MCP\Edit_Trail_Store' ) ) {
            \More_MCP\MCP\Edit_Trail_Store::create_tables();
        } else {
            $edit_trail_store_file = MORE_MCP_PLUGIN_DIR . 'includes/MCP/Edit_Trail_Store.php';
            if ( file_exists( $edit_trail_store_file ) ) {
                require_once $edit_trail_store_file;
                \More_MCP\MCP\Edit_Trail_Store::create_tables();
            }
        }

        
        
        if ( ! class_exists( '\More_MCP\Platform\Schema' ) ) {
            $schema_file = MORE_MCP_PLUGIN_DIR . 'includes/Platform/Schema.php';
            if ( file_exists( $schema_file ) ) {
                require_once $schema_file;
            }
        }
        if ( class_exists( '\More_MCP\Platform\Schema' ) ) {
            \More_MCP\Platform\Schema::create_all();
        }

        
        
        
        
        
        
        
        if ( ! class_exists( '\More_MCP\Auth\Api_Key' ) ) {
            $api_key_file = MORE_MCP_PLUGIN_DIR . 'includes/Auth/Api_Key.php';
            if ( file_exists( $api_key_file ) ) {
                require_once $api_key_file;
            }
        }
        add_option('more_mcp_settings', [
            'enabled' => false,
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

        $edit_trail_store_ok = false;
        if (class_exists('\More_MCP\MCP\Edit_Trail_Store')) {
            \More_MCP\MCP\Edit_Trail_Store::create_tables();
            $edit_trail_store_ok = true;
        } else {
            $f = MORE_MCP_PLUGIN_DIR . 'includes/MCP/Edit_Trail_Store.php';
            if (file_exists($f)) {
                require_once $f;
                \More_MCP\MCP\Edit_Trail_Store::create_tables();
                $edit_trail_store_ok = true;
            }
        }

        $schema_ok = false;
        if (!class_exists('\More_MCP\Platform\Schema')) {
            $f = MORE_MCP_PLUGIN_DIR . 'includes/Platform/Schema.php';
            if (file_exists($f)) {
                require_once $f;
            }
        }
        if (class_exists('\More_MCP\Platform\Schema')) {
            \More_MCP\Platform\Schema::create_all();
            $schema_ok = true;
        }

        if ($token_store_ok && $session_store_ok && $edit_trail_store_ok && $schema_ok) {
            $this->drop_retired_settings();
            \More_MCP\SEO_Data\Credentials::migrate_plaintext();
            
            update_option('more_mcp_flush_rewrites', 1);
            update_option('more_mcp_db_version', MORE_MCP_VERSION);
        }
        
    }

    
    private function drop_retired_settings() {
        $settings = get_option('more_mcp_settings', []);
        if (!is_array($settings)) {
            return;
        }
        $retired = ['platforms', 'allow_code_snippets'];
        $kept    = array_diff_key($settings, array_flip($retired));
        if (count($kept) !== count($settings)) {
            update_option('more_mcp_settings', $kept);
        }
    }

    
    private function required_tables_exist() {
        global $wpdb;
        $required = [
            $wpdb->prefix . 'more_mcp_oauth_clients',
            $wpdb->prefix . 'more_mcp_sessions',
            $wpdb->prefix . 'more_mcp_edit_trail',
            $wpdb->prefix . 'more_mcp_history',
            $wpdb->prefix . 'more_mcp_api_tokens',
            $wpdb->prefix . 'more_mcp_approvals',
        ];
        foreach ($required as $table) {
            
            
            
            $like = $wpdb->esc_like($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, no caching layer involved.
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)) !== $table) {
                return false;
            }
        }
        return true;
    }

    public function deactivate($network_wide = false) {
        if ($network_wide && is_multisite()) {
            if (!class_exists('\More_MCP\Platform\Multisite')) {
                require_once MORE_MCP_PLUGIN_DIR . 'includes/Platform/Multisite.php';
            }
            \More_MCP\Platform\Multisite::for_each_site(function () {
                wp_clear_scheduled_hook( 'more_mcp_token_cleanup' );
            });
        }

        
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
        
        add_rewrite_rule( 'mcp-device/code/?$', 'index.php?more_mcp_oauth=device_authorization', 'top' );
        add_rewrite_rule( 'mcp-device/?$', 'index.php?more_mcp_oauth=device', 'top' );
    }

    
    public function maybe_flush_rewrites() {
        if ( get_option( 'more_mcp_flush_rewrites' ) ) {
            delete_option( 'more_mcp_flush_rewrites' );
            flush_rewrite_rules();
        }
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

        
        
        if ( ! More_MCP\OAuth\Token_Store::oauth_enabled() ) {
            status_header( 404 );
            header( 'Content-Type: application/json' );
            header( 'Cache-Control: no-store' );
            echo wp_json_encode( [ 'error' => 'not_found', 'error_description' => 'OAuth is not available on this site.' ] );
            exit;
        }

        
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

        
        
        
        
        add_filter('more_mcp_writable_options', [More_MCP\Admin\Settings_Page::class, 'admin_writable_options']);

        
        
        
        
        More_MCP\Knowledge\Experimental::register();

        
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
    }
}


function more_mcp_init() {
    return More_MCP_Plugin::get_instance();
}


more_mcp_init();

endif;
