<?php
namespace More_MCP\Admin;

use More_MCP\Platform\Registry;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('admin_footer_text', [$this, 'admin_footer_text']);
        add_filter('more_mcp_writable_options', [__CLASS__, 'admin_writable_options']);
        // Highlight the correct settings submenu for the active ?panel=. WP keys
        // the highlight off the base page slug alone, so without this every
        // panel's submenu row would highlight Connection.
        add_filter('submenu_file', [$this, 'highlight_panel_submenu']);

        // AJAX handlers
        add_action('wp_ajax_more_mcp_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_more_mcp_reset_oauth_state', [$this, 'ajax_reset_oauth_state']);
        add_action('wp_ajax_more_mcp_clear_oauth_field', [$this, 'ajax_clear_oauth_field']);
        add_action('wp_ajax_more_mcp_revoke_all_sessions', [$this, 'ajax_revoke_all_sessions']);
        add_action('wp_ajax_more_mcp_revoke_grant', [$this, 'ajax_revoke_grant']);
        add_action('wp_ajax_more_mcp_delete_session', [$this, 'ajax_delete_session']);
        add_action('wp_ajax_more_mcp_clear_all_sessions', [$this, 'ajax_clear_all_sessions']);
    }


    public function add_menu_page() {
        add_menu_page(
            __('More MCP Settings', 'more-mcp'),
            __('More MCP', 'more-mcp'),
            'manage_options',
            'more-mcp',
            [$this, 'render_settings_page'],
            $this->menu_icon(),
            80
        );

        add_submenu_page(
            'more-mcp',
            __('Settings', 'more-mcp'),
            __('Connection', 'more-mcp'),
            'manage_options',
            'more-mcp',
            [$this, 'render_settings_page']
        );

        // Each settings section is also a real WordPress submenu that deep-links
        // into the same tabbed shell via ?panel=. The in-page sidebar remains the
        // primary navigation; these give the plugin the familiar per-section
        // submenu list (the WooCommerce pattern) without a second render path.
        // All point at render_settings_page(); the panel arg selects the content.
        //
        // The composite `more-mcp&panel=<slug>` menu slugs are what
        // highlight_panel_submenu() below matches against so the right row shows
        // as active — WP alone would highlight only the base `more-mcp` row.
        $more_mcp_panel_submenus = [
            'permissions' => __('Permissions', 'more-mcp'),
            'sessions'    => __('Sessions', 'more-mcp'),
            'providers'   => __('AI Providers', 'more-mcp'),
            'docs'        => __('Documentation', 'more-mcp'),
        ];
        foreach ($more_mcp_panel_submenus as $more_mcp_slug => $more_mcp_label) {
            add_submenu_page(
                'more-mcp',
                $more_mcp_label,
                $more_mcp_label,
                'manage_options',
                'more-mcp&panel=' . $more_mcp_slug,
                [$this, 'render_settings_page']
            );
        }

        add_submenu_page(
            'more-mcp',
            __('Activity Log', 'more-mcp'),
            __('Activity Log', 'more-mcp'),
            'manage_options',
            'more-mcp-logs',
            [$this, 'render_logs_page']
        );

    }

    /**
     * Highlight the submenu row matching the active ?panel=.
     *
     * The per-section submenu entries registered above use composite slugs of the
     * form `more-mcp&panel=<slug>`. WordPress computes the highlighted submenu
     * from the base `page` query var only, so on `?page=more-mcp&panel=sessions`
     * it would light up the first `more-mcp` row (Connection) rather than
     * Sessions. This filter rewrites the active submenu file to the composite slug
     * when a known panel is requested.
     *
     * Only fires on this plugin's own settings page, and only for panel slugs that
     * were actually registered as submenus, so it cannot mis-highlight another
     * plugin's menu or invent a row that does not exist.
     *
     * @param string|null $submenu_file The submenu file WordPress resolved.
     * @return string|null The (possibly rewritten) submenu file.
     */
    public function highlight_panel_submenu($submenu_file) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only menu-highlight hint, validated against a fixed allowlist below.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('more-mcp' !== $page) {
            return $submenu_file;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only hint; validated against the allowlist.
        $panel = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';
        $panel_submenus = ['permissions', 'sessions', 'providers', 'docs'];
        if (in_array($panel, $panel_submenus, true)) {
            return 'more-mcp&panel=' . $panel;
        }

        return $submenu_file;
    }

    /**
     * Menu icon for the top-level admin menu entry.
     *
     * Returned as a base64 data URI rather than a dashicon slug on purpose:
     * WordPress renders a data-URI icon inside an <img>, which keeps the brand's
     * full colour (ink + #ffd21e), whereas a dashicon slug is painted as a CSS
     * mask and forced to the admin colour scheme's monochrome tint. Branded
     * product icons — WooCommerce, Elementor, Yoast — all take the data-URI route
     * for exactly this reason.
     *
     * Falls back to the closest core dashicon if the SVG is missing from the
     * build, so a stripped or partial install still gets a sensible menu mark
     * rather than a broken image.
     *
     * @return string A `data:image/svg+xml;base64,…` URI, or a dashicon slug.
     */
    private function menu_icon() {
        $svg_path = MORE_MCP_PLUGIN_DIR . 'assets/images/menu-icon.svg';

        if ( is_readable( $svg_path ) ) {
            $svg = file_get_contents( $svg_path );
            if ( false !== $svg && '' !== $svg ) {
                return 'data:image/svg+xml;base64,' . base64_encode( $svg );
            }
        }

        return 'dashicons-networking';
    }

    public function register_settings() {
        register_setting('more_mcp_settings_group', 'more_mcp_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        $settings = get_option('more_mcp_settings', []);

        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['allow_option_writes'] = isset($input['allow_option_writes']) ? (bool) $input['allow_option_writes'] : false;
        $sanitized['allow_theme_writes'] = isset($input['allow_theme_writes']) ? (bool) $input['allow_theme_writes'] : false;
        // Plugin/theme lifecycle. Default off and never inferred — while this is
        // unset the lifecycle tools are not even listed to MCP clients.
        $sanitized['allow_plugin_management'] = isset($input['allow_plugin_management']) ? (bool) $input['allow_plugin_management'] : false;

        // Access token TTL — whitelist against the 4 UI choices; anything else falls back to the default.
        $posted_ttl = isset($input['access_token_ttl_seconds']) ? (int) $input['access_token_ttl_seconds'] : 0;
        $sanitized['access_token_ttl_seconds'] = in_array($posted_ttl, \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL_CHOICES, true)
            ? $posted_ttl
            : \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL;

        // Third-party option allowlist.
        //
        // Arrives as TWO inputs that merge into one stored list:
        //
        //   writable_options_preset[]  — checkboxes from the curated presets
        //   writable_options_admin     — textarea, for names no preset covers
        //
        // The stored value stays a single flat list of option names, which is what
        // the wp_update_option gate reads. Keeping the storage shape unchanged
        // means the presets are purely an input affordance: an install that
        // predates them, or one whose settings were written by another panel's
        // hidden field, needs no migration.
        //
        // Every key runs through sanitize_key() so a hostile / malformed input can't smuggle
        // an option name shape the wp_update_option gate wouldn't recognize. The denylist
        // in MCP\Server.php runs AFTER the allowlist check, so entries here CANNOT escape
        // the sensitive-option denylist regardless of what admins add.
        //
        // Preset checkboxes are additionally validated against the catalogue rather
        // than trusted as posted. Without that check the checkbox array would be an
        // unvalidated second path into the allowlist — a forged POST could add any
        // name through it, bypassing the deliberate act of typing one.
        $keys = [];

        if (isset($input['writable_options_preset']) && is_array($input['writable_options_preset'])) {
            $known = Option_Presets::all_preset_option_names();
            foreach ($input['writable_options_preset'] as $posted_name) {
                if (!is_string($posted_name)) {
                    continue;
                }
                $clean = sanitize_key(trim($posted_name));
                if ($clean !== '' && in_array($clean, $known, true)) {
                    $keys[] = $clean;
                }
            }
        }

        if (isset($input['writable_options_admin']) && is_string($input['writable_options_admin'])) {
            $lines = preg_split('/\r?\n/', (string) $input['writable_options_admin']);
            foreach ($lines as $line) {
                $clean = sanitize_key(trim($line));
                if ($clean !== '') { $keys[] = $clean; }
            }
        }

        $sanitized['writable_options_admin'] = array_values(array_unique($keys));

        // Sanitize API key.
        //
        // Order matters: the readonly `api_key` field in the settings form posts
        // the current value on every submit, so we must check `regenerate_api_key`
        // FIRST. With the order reversed, the current-value POST silently
        // overrides the regenerate signal and clicking Regenerate becomes a no-op.
        //
        // A submitted key is only kept if it is a well-formed current-format key.
        // Anything else — a legacy 32-hex key carried over from before 0.1.5, or a
        // hand-edited value — is replaced with a freshly minted one rather than
        // stored, because storing an unusable key would leave the site with auth
        // that silently never succeeds.
        if (isset($input['regenerate_api_key'])) {
            $sanitized['api_key'] = \More_MCP\Auth\Api_Key::generate();
        } else {
            $submitted = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
            $existing  = isset($settings['api_key']) ? (string) $settings['api_key'] : '';

            if (\More_MCP\Auth\Api_Key::is_valid_format($submitted)) {
                $sanitized['api_key'] = $submitted;
            } elseif (\More_MCP\Auth\Api_Key::is_valid_format($existing)) {
                $sanitized['api_key'] = $existing;
            } else {
                $sanitized['api_key'] = \More_MCP\Auth\Api_Key::generate();
            }
        }

        // Sanitize OAuth settings
        if (isset($input['oauth_client_id']) && !empty($input['oauth_client_id'])) {
            $sanitized['oauth_client_id'] = sanitize_text_field($input['oauth_client_id']);
        } else {
            $sanitized['oauth_client_id'] = $settings['oauth_client_id'] ?? '';
        }

        if (isset($input['oauth_client_secret']) && !empty($input['oauth_client_secret'])) {
            $sanitized['oauth_client_secret'] = sanitize_text_field($input['oauth_client_secret']);
        } else {
            $sanitized['oauth_client_secret'] = $settings['oauth_client_secret'] ?? '';
        }

        // Sanitize AI Platforms (new structure)
        $sanitized['platforms'] = [];
        if (isset($input['platforms']) && is_array($input['platforms'])) {
            foreach ($input['platforms'] as $index => $platform_config) {
                if (empty($platform_config['platform'])) {
                    continue;
                }

                $platform_id = sanitize_text_field($platform_config['platform']);
                $platform = Registry::get_platform($platform_id);

                if (!$platform) {
                    continue;
                }

                $sanitized_platform = [
                    'platform' => $platform_id,
                    'enabled' => isset($platform_config['enabled']) ? (bool) $platform_config['enabled'] : true,
                ];

                // Sanitize each field based on platform configuration
                foreach ($platform['fields'] as $field_id => $field_config) {
                    if (isset($platform_config[$field_id])) {
                        switch ($field_config['type']) {
                            case 'url':
                                $sanitized_platform[$field_id] = esc_url_raw($platform_config[$field_id]);
                                break;
                            case 'password':
                            case 'text':
                            case 'select':
                            default:
                                $sanitized_platform[$field_id] = sanitize_text_field($platform_config[$field_id]);
                                break;
                        }
                    } elseif (isset($field_config['default'])) {
                        $sanitized_platform[$field_id] = $field_config['default'];
                    }
                }

                $sanitized['platforms'][] = $sanitized_platform;
            }
        }

        // Legacy: Also keep mcp_servers for backward compatibility
        $sanitized['mcp_servers'] = [];
        if (isset($input['mcp_servers']) && is_array($input['mcp_servers'])) {
            foreach ($input['mcp_servers'] as $server) {
                if (!empty($server['name']) && !empty($server['url'])) {
                    $sanitized['mcp_servers'][] = [
                        'name' => sanitize_text_field($server['name']),
                        'url' => esc_url_raw($server['url']),
                        'api_key' => sanitize_text_field($server['api_key'] ?? ''),
                        'enabled' => isset($server['enabled']) ? (bool) $server['enabled'] : true,
                    ];
                }
            }
        }

        return $sanitized;
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'more-mcp') === false) {
            return;
        }

        // Use filemtime() appended to the version string so intra-version patches
        // bust CDN / immutable browser caches. Plain MORE_MCP_VERSION alone is
        // not enough on Cloudflare-fronted sites where plugin JS/CSS gets cached
        // with `Cache-Control: immutable, max-age=2592000` — the same ?ver= URL
        // serves the stale asset for 30 days regardless of hard-refresh.
        $css_path = MORE_MCP_PLUGIN_DIR . 'assets/css/admin.css';
        $js_path  = MORE_MCP_PLUGIN_DIR . 'assets/js/admin.js';
        $css_ver  = MORE_MCP_VERSION . '.' . (file_exists($css_path) ? filemtime($css_path) : '0');
        $js_ver   = MORE_MCP_VERSION . '.' . (file_exists($js_path)  ? filemtime($js_path)  : '0');

        wp_enqueue_style(
            'more-mcp-admin',
            MORE_MCP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'more-mcp-admin',
            MORE_MCP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            $js_ver,
            true
        );

        // Get platform data for JavaScript
        $platforms = Registry::get_platforms();
        $platform_groups = Registry::get_platform_groups();

        wp_localize_script('more-mcp-admin', 'moreMcp', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('more_mcp_nonce'),
            'restUrl' => rest_url('more-mcp/v1/'),
            'platforms' => $platforms,
            'platformGroups' => $platform_groups,
            'strings' => [
                'selectPlatform' => esc_html__('Select a platform...', 'more-mcp'),
                'testConnection' => esc_html__('Test Connection', 'more-mcp'),
                'testing' => esc_html__('Testing...', 'more-mcp'),
                'connectionSuccess' => esc_html__('Connection successful!', 'more-mcp'),
                'connectionFailed' => esc_html__('Connection failed', 'more-mcp'),
                'removePlatform' => esc_html__('Remove', 'more-mcp'),
                'getApiKey' => esc_html__('Get API Key', 'more-mcp'),
                'documentation' => esc_html__('Documentation', 'more-mcp'),
                'confirmRemove' => esc_html__('Are you sure you want to remove this platform?', 'more-mcp'),
                'confirmRegenerate' => esc_html__('Are you sure? This will invalidate the current API key.', 'more-mcp'),
            ],
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('more_mcp_settings', [
            'enabled' => false,
            'platforms' => [],
            'mcp_servers' => [],
            'api_key' => \More_MCP\Auth\Api_Key::generate(),
        ]);

        // Heal a missing or legacy-format key on view rather than waiting for the
        // next save. An install that upgraded from before 0.1.5 still holds a
        // 32-hex key, which validate_api_key_value() now rejects — so without
        // this the settings page would keep displaying a credential that cannot
        // authenticate, with no indication anything is wrong.
        if ( ! \More_MCP\Auth\Api_Key::is_valid_format( $settings['api_key'] ?? '' ) ) {
            $settings['api_key'] = \More_MCP\Auth\Api_Key::generate();
            $stored              = get_option('more_mcp_settings', []);
            $stored['api_key']   = $settings['api_key'];
            update_option('more_mcp_settings', $stored);
        }

        $platforms = Registry::get_platforms();
        $platform_groups = Registry::get_platform_groups();

        include MORE_MCP_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        // Table name constructed safely from prefix + hardcoded string, then escaped
        $table_name = esc_sql($wpdb->prefix . 'more_mcp_logs');

        // Verify nonce for page navigation
        $nonce_valid = isset($_GET['_wpnonce']) ? wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'more_mcp_logs_page') : true;
        $page = ($nonce_valid && isset($_GET['paged'])) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin logs table, table name escaped via esc_sql()
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Custom plugin logs table - table name escaped via esc_sql()
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        // phpcs:enable

        include MORE_MCP_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    /**
     * AJAX handler for testing platform connections
     */
    public function ajax_test_connection() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $platform_id = isset($_POST['platform']) ? sanitize_text_field(wp_unslash($_POST['platform'])) : '';
        $config = [];

        // Get config from POST data
        if (isset($_POST['config']) && is_array($_POST['config'])) {
            $posted_config = map_deep(wp_unslash($_POST['config']), 'sanitize_text_field');
            foreach ($posted_config as $key => $value) {
                $config[sanitize_text_field($key)] = sanitize_text_field($value);
            }
        }

        if (empty($platform_id)) {
            wp_send_json_error(['message' => esc_html__('No platform selected', 'more-mcp')]);
        }

        $result = Registry::test_connection($platform_id, $config);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler — wipe all OAuth state (clients, tokens, in-flight auth codes).
     *
     * Used by the "Reset OAuth State" button on the settings page. Replaces the
     * wp-cli SQL recipe customers previously had to paste from support emails
     * when a Claude connector got stuck mid-handshake. All connected MCP clients
     * will need to re-authorize after this runs — that's the point.
     */
    public function ajax_reset_oauth_state() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $counts = \More_MCP\OAuth\Token_Store::reset_all_oauth_state();

        // Audit trail in the Activity Log so we can see who reset and when.
        $this->log_admin_action('oauth:reset', [], $counts);

        $base_message = sprintf(
            /* translators: 1: clients deleted, 2: tokens deleted, 3: auth codes deleted */
            esc_html__('OAuth state reset. Deleted %1$d clients, %2$d tokens, %3$d auth codes. Connected MCP clients will need to re-authorize.', 'more-mcp'),
            (int) $counts['clients'],
            (int) $counts['tokens'],
            (int) $counts['auth_codes']
        );

        if ( ! empty( $counts['static_creds_cleared'] ) ) {
            $base_message .= ' ' . esc_html__('Manually-configured OAuth client credentials were also cleared; the connector will fall back to Dynamic Client Registration.', 'more-mcp');
        }

        wp_send_json_success([
            'message' => $base_message,
            'counts'  => $counts,
        ]);
    }

    /**
     * AJAX handler — clear a single OAuth manual-credential field (oauth_client_id
     * or oauth_client_secret) from the more_mcp_settings option.
     *
     * The settings sanitize callback treats an empty form submission as
     * "preserve previous value" (defense against accidental blanking), which
     * would otherwise leave admins with no UI path to switch from manual-
     * credential mode back to Dynamic Client Registration once they had
     * generated a static client. This AJAX handler is that UI path.
     */
    public function ajax_clear_oauth_field() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $field = isset($_POST['field']) ? sanitize_text_field(wp_unslash($_POST['field'])) : '';
        $allowed_fields = ['oauth_client_id', 'oauth_client_secret'];
        if (!in_array($field, $allowed_fields, true)) {
            wp_send_json_error(['message' => esc_html__('Invalid field name.', 'more-mcp')]);
        }

        $settings = get_option('more_mcp_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings[$field] = '';
        update_option('more_mcp_settings', $settings);

        wp_send_json_success([
            'field'   => $field,
            'message' => esc_html__('Field cleared. Save your settings to confirm.', 'more-mcp'),
        ]);
    }

    /**
     * Merge admin-picked writable option keys into the more_mcp_writable_options
     * filter chain. Runs at default priority; developer-registered callbacks at
     * higher priority still get final say. The Server::is_denylisted_option check
     * runs AFTER this filter, so entries here can never escape the denylist.
     *
     * @param array $opts Options passed through from earlier filter callbacks.
     * @return array Merged option-name list.
     */
    public static function admin_writable_options($opts) {
        if (!is_array($opts)) { $opts = []; }
        $settings = get_option('more_mcp_settings', []);
        if (!is_array($settings) || empty($settings['writable_options_admin'])
            || !is_array($settings['writable_options_admin'])) {
            return $opts;
        }
        return array_values(array_unique(array_merge($opts, $settings['writable_options_admin'])));
    }

    /**
     * AJAX handler — soft-revoke every active OAuth session in one call.
     *
     * Kicks all connected MCP clients so they must re-authorize on their next
     * request. Complements the Session length setting: an admin who lengthens
     * the TTL to 7 days but still holds a token minted at 1h can use this to
     * force an immediate re-mint on the new TTL. Also useful outside that flow
     * for incident response.
     */
    public function ajax_revoke_all_sessions() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        // Filter escape hatch — security plugins can veto revocation per acting user.
        $allowed = (bool) apply_filters('more_mcp_revoke_all_sessions_allowed', true, get_current_user_id());
        if (!$allowed) {
            wp_send_json_error(['message' => esc_html__('Session revocation is disabled by a filter on this site.', 'more-mcp')]);
        }

        $revoked = \More_MCP\OAuth\Token_Store::revoke_all_tokens();

        // Audit trail — mirrors the reset_oauth_state pattern so both actions land in Activity Log.
        $this->log_admin_action('oauth:revoke_all_sessions', [], ['revoked_count' => $revoked]);

        wp_send_json_success([
            'revoked_count' => $revoked,
            'message'       => sprintf(
                /* translators: %d: number of sessions revoked */
                esc_html__('Revoked %d active session(s). All connected AI clients must re-authorize.', 'more-mcp'),
                $revoked
            ),
        ]);
    }

    /**
     * Write one row to the Activity Log.
     *
     * Extracted from the OAuth reset / revoke handlers once a third and fourth
     * caller appeared. The four session-management handlers all need the same
     * audit trail, and the acting user has to be recorded for every one of them:
     * these actions disconnect other people's clients, so "who did this" is the
     * first question asked afterwards.
     *
     * @param string $action   Log action slug, e.g. 'oauth:revoke_grant'.
     * @param array  $request  Request-side detail. The acting user is merged in here.
     * @param array  $response Response-side detail (counts, IDs).
     */
    private function log_admin_action($action, array $request = [], array $response = []) {
        global $wpdb;
        $current_user = wp_get_current_user();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'more_mcp_logs',
            [
                'mcp_server'    => 'OAuth Server',
                'action'        => $action,
                'request_data'  => wp_json_encode(array_merge([
                    'user_id'    => (int) $current_user->ID,
                    'user_login' => $current_user->user_login,
                ], $request)),
                'response_data' => wp_json_encode($response),
                'status'        => 'success',
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * AJAX handler — revoke one OAuth grant (a single client_id + user_id pair).
     *
     * The per-client counterpart to ajax_revoke_all_sessions. An admin who sees
     * one stale or unexpected connector in the list can disconnect exactly that
     * one without kicking every other client off the site, which was the only
     * option before this handler existed.
     *
     * Honors the same more_mcp_revoke_all_sessions_allowed filter as the bulk
     * action: a security plugin that vetoes revocation should not be bypassable
     * by choosing the narrower button.
     */
    public function ajax_revoke_grant() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $allowed = (bool) apply_filters('more_mcp_revoke_all_sessions_allowed', true, get_current_user_id());
        if (!$allowed) {
            wp_send_json_error(['message' => esc_html__('Session revocation is disabled by a filter on this site.', 'more-mcp')]);
        }

        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $user_id   = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if ('' === $client_id || 0 === $user_id) {
            wp_send_json_error(['message' => esc_html__('Missing client or user identifier.', 'more-mcp')]);
        }

        $revoked = \More_MCP\OAuth\Token_Store::revoke_grant($client_id, $user_id);

        $this->log_admin_action(
            'oauth:revoke_grant',
            ['client_id' => $client_id, 'target_user_id' => $user_id],
            ['revoked_count' => $revoked]
        );

        wp_send_json_success([
            'revoked_count' => $revoked,
            'message'       => sprintf(
                /* translators: %d: number of token rows revoked */
                esc_html__('Revoked %d token(s) for this client. It must re-authorize before it can call this site again.', 'more-mcp'),
                $revoked
            ),
        ]);
    }

    /**
     * AJAX handler — delete a single MCP transport session row.
     *
     * Addressed by table row ID, not by session ID: only the sha256 hash of a
     * session ID is ever stored (see Session_Store::create_session), so the admin
     * screen has no plaintext session ID to send back. The affected client gets a
     * 404 on its next request and re-initializes.
     *
     * Deleting a session does NOT revoke the credentials behind it — a client
     * holding a valid token will simply open a new session. Revoking the grant is
     * the action that actually cuts access, which is why the UI presents them as
     * two separate lists.
     */
    public function ajax_delete_session() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $row_id = isset($_POST['session_row_id']) ? absint($_POST['session_row_id']) : 0;
        if (0 === $row_id) {
            wp_send_json_error(['message' => esc_html__('Missing session identifier.', 'more-mcp')]);
        }

        $deleted = \More_MCP\MCP\Session_Store::delete_by_id($row_id);

        if (!$deleted) {
            wp_send_json_error(['message' => esc_html__('That session no longer exists — it may have expired or already been ended.', 'more-mcp')]);
        }

        $this->log_admin_action(
            'session:delete',
            ['session_row_id' => $row_id],
            ['deleted' => (int) $deleted]
        );

        wp_send_json_success([
            'message' => esc_html__('Session ended. The client will start a new session on its next request if its credentials are still valid.', 'more-mcp'),
        ]);
    }

    /**
     * AJAX handler — delete every MCP transport session row.
     *
     * Separate from "revoke all sessions" on purpose. This one ends transport
     * state without touching credentials, so every client reconnects but nothing
     * has to re-authorize. It is the right tool for a stuck-session symptom
     * (clients getting "Session not found" loops) where revoking OAuth grants
     * would be an unnecessarily large hammer.
     */
    public function ajax_clear_all_sessions() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $deleted = \More_MCP\MCP\Session_Store::delete_all();

        $this->log_admin_action('session:delete_all', [], ['deleted' => $deleted]);

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => sprintf(
                /* translators: %d: number of sessions ended */
                esc_html__('Ended %d transport session(s). Credentials are untouched — clients reconnect without re-authorizing.', 'more-mcp'),
                $deleted
            ),
        ]);
    }

    public function admin_footer_text($text) {
        // No-op passthrough. The filter stays wired in __construct so there is
        // a single obvious place to add footer branding if it is ever wanted.
        return $text;
    }

}
