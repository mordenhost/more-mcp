<?php
/**
 * Plugin Name: More MCP – Secure AI Connector for Claude, ChatGPT & Gemini
 * Plugin URI: https://mordenhost.com/more-mcp/
 * Description: Integrate Model Context Protocol (MCP) servers with WordPress to enable LLM interactions with your site
 * Version: 0.3.1
 * Author: Sadewadee
 * Author URI: https://mordenhost.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: more-mcp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MORE_MCP_VERSION', '0.3.1');
define('MORE_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MORE_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MORE_MCP_PLUGIN_FILE', __FILE__);
define('MORE_MCP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Documentation and support base URL.
 *
 * Placeholder — point this at your own documentation before distributing,
 * or filter it per-site. Every in-plugin "Docs" / troubleshooting link is
 * derived from this constant so there is exactly one place to change.
 */
if ( ! defined( 'MORE_MCP_DOCS_URL' ) ) {
    define('MORE_MCP_DOCS_URL', 'https://mordenhost.com/more-mcp/');
}

// Autoloader
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

/**
 * Main plugin class
 */
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

        // Cache-Control: no-store on EVERY response under our REST namespace.
        // 1.4.13 added this to OAuth endpoints. 1.4.15 audit found the MCP
        // endpoint missing it (Server::json_response) and the REST_Controller
        // routes (/posts, /pages, /site, etc.) also missing it — both got
        // poisoned by URL-keyed edge caches when CF cached an early response
        // and served it back to differently-authenticated requests. The
        // global filter below covers the whole namespace defensively;
        // per-response edits in MCP/Server.php are kept as belt-and-suspenders.
        add_filter('rest_post_dispatch', [$this, 'force_no_store_on_namespace'], 10, 3);

        // OAuth 2.0 endpoints (served at domain root, not under /wp-json/).
        add_action('init', [$this, 'register_oauth_rewrites']);
        add_filter('query_vars', [$this, 'register_oauth_query_vars']);
        add_action('parse_request', [$this, 'handle_oauth_request']);

        // Scheduled token cleanup.
        add_action('more_mcp_token_cleanup', [\More_MCP\OAuth\Token_Store::class, 'cleanup_expired']);
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Undo_Store::class, 'cleanup_expired']);

        // sessions cleanup rides on the same daily cron action.
        add_action('more_mcp_token_cleanup', [\More_MCP\MCP\Session_Store::class, 'cleanup_expired']);

        // Add plugin action links (Settings, Docs)
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);

        // Elementor MCP module coexistence: admin notice + dismiss handler.
        // Safe to register unconditionally; the render callback checks native
        // detection before drawing anything.
        \More_MCP\Integrations\Elementor_Coexistence::register_hooks();

        // WordPress Abilities API registration (WP 6.9+). function_exists() guard makes this a
        // silent no-op on older WP. The option flag lets an admin flip the feature off in one
        // call for rollback without a plugin update.
        //
        // WP core exposes two separate hooks: wp_abilities_api_categories_init runs first
        // (categories registry init), wp_abilities_api_init runs after (ability registry init,
        // by which point categories must already exist). Registering an ability against a
        // non-registered category throws.
        if ( function_exists( 'wp_register_ability_category' ) && (bool) get_option( 'more_mcp_abilities_registration_enabled', true ) ) {
            add_action( 'wp_abilities_api_categories_init', array( \More_MCP\Abilities\Categories::class, 'register' ) );
            add_action( 'wp_abilities_api_init', array( \More_MCP\Abilities\Registrar::class, 'register' ) );

            // MCP Adapter server registration (Option C — own named server, explicit ability
            // list, our abilities do NOT auto-enroll on the adapter's default server). Guarded
            // on adapter presence; silent no-op when MCP Adapter isn't installed.
            if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
                add_action( 'mcp_adapter_init', array( \More_MCP\Abilities\MCP_Adapter_Server::class, 'register' ) );
            }
        }
    }

    /**
     * Force no-store cache headers on every response under more-mcp/* namespace.
     *
     * Hooked late on rest_post_dispatch so it overrides any cache headers a
     * route callback may have set. Prevents edge/host caches from URL-keying
     * responses and serving them back to subsequent requests with different
     * auth state.
     *
     * @param \WP_REST_Response $response The dispatch result.
     * @param \WP_REST_Server   $server   The REST server instance.
     * @param \WP_REST_Request  $request  The original request.
     * @return \WP_REST_Response
     */
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

    /**
     * Add action links to plugins page
     */
    public function add_action_links($links) {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=more-mcp') . '">' . __('Settings', 'more-mcp') . '</a>',
            '<a href="' . esc_url( MORE_MCP_DOCS_URL ) . '" target="_blank">' . __('Docs', 'more-mcp') . '</a>',
        ];
        return array_merge($plugin_links, $links);
    }

    public function activate() {
        // Create necessary database tables and options
        $this->create_tables();

        // Create OAuth tables.
        if ( class_exists( '\More_MCP\OAuth\Token_Store' ) ) {
            \More_MCP\OAuth\Token_Store::create_tables();
        } else {
            // Force-load if autoloader hasn't fired yet (WP 7.0+ activation flow)
            $token_store_file = MORE_MCP_PLUGIN_DIR . 'includes/OAuth/Token_Store.php';
            if ( file_exists( $token_store_file ) ) {
                require_once $token_store_file;
                \More_MCP\OAuth\Token_Store::create_tables();
            }
        }

        // Create sessions table. Same force-load pattern as Token_Store
        // because register_activation_hook fires before the autoloader on some
        // WP versions, so class_exists() returns false on a fresh activation.
        if ( class_exists( '\More_MCP\MCP\Session_Store' ) ) {
            \More_MCP\MCP\Session_Store::create_tables();
        } else {
            $session_store_file = MORE_MCP_PLUGIN_DIR . 'includes/MCP/Session_Store.php';
            if ( file_exists( $session_store_file ) ) {
                require_once $session_store_file;
                \More_MCP\MCP\Session_Store::create_tables();
            }
        }

        // Set default options.
        // API key format lives in Auth\Api_Key — see that class for why the
        // prefix and base58 alphabet were chosen over the previous bare hex.
        //
        // Same force-load guard as the stores above: register_activation_hook
        // can fire before the autoloader, and a fatal here would abort
        // activation after the tables were already created.
        if ( ! class_exists( '\More_MCP\Auth\Api_Key' ) ) {
            $api_key_file = MORE_MCP_PLUGIN_DIR . 'includes/Auth/Api_Key.php';
            if ( file_exists( $api_key_file ) ) {
                require_once $api_key_file;
            }
        }
        add_option('more_mcp_settings', [
            'enabled' => false,
            'platforms' => [],
            'mcp_servers' => [],
            'api_key' => \More_MCP\Auth\Api_Key::generate(),
        ]);

        // Register OAuth rewrite rules before flushing.
        $this->register_oauth_rewrites();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Schedule daily token cleanup.
        if ( ! wp_next_scheduled( 'more_mcp_token_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'more_mcp_token_cleanup' );
        }

        // Mark schema as current so the runtime migration check is a no-op for fresh installs.
        update_option('more_mcp_db_version', MORE_MCP_VERSION);
    }

    /**
     * Runtime schema check. register_activation_hook only fires on activation, so plugins
     * that ship new tables via an update never run create_tables() on existing installs.
     * This heals any install where the DB version doesn't match the plugin version.
     *
     * INVARIANT: db_version must only advance when EVERY required migration actually ran.
     * If class_exists() returns false (autoloader transiently failed during auto-update,
     * opcache stale, file-deploy race) AND the force-load fallback can't find the file,
     * we leave db_version alone so the next request retries.
     *
     * INVARIANT: db_version matching the plugin version is necessary but NOT sufficient —
     * we also verify required tables physically exist before short-circuiting. Stuck states
     * like "uninstall dropped tables but left db_version intact, then reinstall ran" cannot
     * latch the healer into a permanent no-op.
     */
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
        // If either failed: db_version stays at the old value, next request retries.
    }

    /**
     * Verify the two core tables required for OAuth client registration and MCP session
     * persistence physically exist. Used by maybe_upgrade_db() as a backstop against the
     * db_version option lying.
     *
     * Two SHOW TABLES LIKE queries per pageload — negligible cost, and the safe-by-default
     * payoff is that no external or accidental state mismatch can latch the healer.
     */
    private function required_tables_exist() {
        global $wpdb;
        $required = [
            $wpdb->prefix . 'more_mcp_oauth_clients',
            $wpdb->prefix . 'more_mcp_sessions',
        ];
        foreach ($required as $table) {
            // esc_like the pattern: `_` is a single-character wildcard in LIKE and these
            // table names are full of them, so an unescaped probe can match a differently
            // named table and report a table that exists as missing.
            $like = $wpdb->esc_like($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, no caching layer involved.
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)) !== $table) {
                return false;
            }
        }
        return true;
    }

    public function deactivate() {
        // Clear scheduled events.
        wp_clear_scheduled_hook( 'more_mcp_token_cleanup' );

        // Flush rewrite rules
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

    /* ------------------------------------------------------------------
     *  OAuth 2.0 rewrite rules & request handling
     * ----------------------------------------------------------------*/

    /**
     * Register rewrite rules for OAuth endpoints at domain root.
     */
    public function register_oauth_rewrites() {
        add_rewrite_rule( '\.well-known/oauth-protected-resource(/.*)?$', 'index.php?more_mcp_oauth=protected_resource', 'top' );
        add_rewrite_rule( '\.well-known/oauth-authorization-server/?$', 'index.php?more_mcp_oauth=metadata', 'top' );
        add_rewrite_rule( 'authorize/?$', 'index.php?more_mcp_oauth=authorize', 'top' );
        add_rewrite_rule( 'token/?$', 'index.php?more_mcp_oauth=token', 'top' );
        add_rewrite_rule( 'register/?$', 'index.php?more_mcp_oauth=register', 'top' );
    }

    /**
     * Register the query variable used by OAuth rewrite rules.
     */
    public function register_oauth_query_vars( $vars ) {
        $vars[] = 'more_mcp_oauth';
        return $vars;
    }

    /**
     * Intercept requests that match OAuth rewrite rules and dispatch to OAuth\Server.
     */
    public function handle_oauth_request( $wp ) {
        if ( empty( $wp->query_vars['more_mcp_oauth'] ) ) {
            return;
        }

        // Only handle OAuth if plugin is enabled (allow metadata always for discovery).
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

        // Short-circuit self-check probes from Well_Known_Notice::check_register_301().
        // Reaching this point means the rewrite resolved (so there's no host-side 301 to
        // detect); return 204 No Content without invoking OAuth\Server so we don't pollute
        // the Activity Log with a synthetic "register failed" entry every 12 hours.
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( 'More MCP Self-Check' === $ua && in_array( $action, [ 'register', 'authorize', 'token' ], true ) ) {
            status_header( 204 );
            header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
            exit;
        }

        $oauth_server = new More_MCP\OAuth\Server();
        $oauth_server->dispatch( $action );
        // dispatch() calls exit, but just in case:
        exit;
    }

    public function init() {
        // Text domain is automatically loaded by WordPress 4.6+ for plugins hosted on WordPress.org
        // No need to call load_plugin_textdomain() manually

        // Endpoint tool-profile filter — trims tools/list by ?tools=<profile>.
        More_MCP\MCP\Tool_Profiles::register();

        // Initialize components
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

        // Streamable HTTP endpoint.
        // Single endpoint for all MCP communication - no SSE connection needed
        // MCP protocol requires public REST endpoints — auth enforced inside
        // Server::validate_auth() on every request (API key or Bearer token).
        // @security-ignore WP-AUTH-001 — verified: auth on all code paths in Server.php
        register_rest_route('more-mcp/v1', '/mcp', [
            'methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
            'callback' => [$server, 'handle_mcp'],
            'permission_callback' => '__return_true', // @security-ignore — auth in validate_auth()
        ]);

        // Also register at namespace root path — Claude Desktop may post to /wp-json/more-mcp/v1
        // when it strips the last path segment from the configured MCP URL.
        // @security-ignore WP-AUTH-001 — same handler as above
        register_rest_route('more-mcp', '/v1', [
            'methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
            'callback' => [$server, 'handle_mcp'],
            'permission_callback' => '__return_true', // @security-ignore — auth in validate_auth()
        ]);

        // LEGACY: SSE endpoint (deprecated, returns redirect info)
        // @security-ignore WP-AUTH-001 — deprecated, returns error message only
        register_rest_route('more-mcp/v1', '/sse', [
            'methods' => 'GET',
            'callback' => [$server, 'handle_sse'],
            'permission_callback' => '__return_true', // @security-ignore — deprecated endpoint
        ]);

        // LEGACY: Messages endpoint (forwards to new handler with full auth)
        // @security-ignore WP-AUTH-001 — forwards to handle_mcp() which has validate_auth()
        register_rest_route('more-mcp/v1', '/messages', [
            'methods' => 'POST',
            'callback' => [$server, 'handle_message'],
            'permission_callback' => '__return_true', // @security-ignore — auth in validate_auth()
        ]);
    }
}

// Initialize the plugin
function more_mcp_init() {
    return More_MCP_Plugin::get_instance();
}

// Start the plugin
more_mcp_init();
