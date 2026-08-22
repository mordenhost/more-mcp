<?php
namespace More_MCP\Admin;

use More_MCP\Platform\Registry;
use More_MCP\SEO_Data\Providers as SEODataProviders;

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
        
        
        
        add_filter('submenu_file', [$this, 'highlight_panel_submenu']);

        
        add_action('wp_ajax_more_mcp_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_more_mcp_reset_oauth_state', [$this, 'ajax_reset_oauth_state']);
        add_action('wp_ajax_more_mcp_clear_oauth_field', [$this, 'ajax_clear_oauth_field']);
        add_action('wp_ajax_more_mcp_revoke_all_sessions', [$this, 'ajax_revoke_all_sessions']);
        add_action('wp_ajax_more_mcp_revoke_grant', [$this, 'ajax_revoke_grant']);
        add_action('wp_ajax_more_mcp_delete_session', [$this, 'ajax_delete_session']);
        add_action('wp_ajax_more_mcp_clear_all_sessions', [$this, 'ajax_clear_all_sessions']);
        add_action('wp_ajax_more_mcp_toggle_integration', [$this, 'ajax_toggle_integration']);
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

        
        
        
        
        
        
        
        
        
        $more_mcp_panel_submenus = [
            'permissions' => __('Permissions', 'more-mcp'),
            'sessions'    => __('Sessions', 'more-mcp'),
            'services'    => __('External Services', 'more-mcp'),
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

    
    public function highlight_panel_submenu($submenu_file) {
        
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('more-mcp' !== $page) {
            return $submenu_file;
        }

        
        $panel = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';

        
        
        
        if ('providers' === $panel) {
            $panel = 'services';
        }

        $panel_submenus = ['permissions', 'sessions', 'services', 'docs'];
        if (in_array($panel, $panel_submenus, true)) {
            return 'more-mcp&panel=' . $panel;
        }

        return $submenu_file;
    }

    
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
        
        
        $sanitized['allow_plugin_management'] = isset($input['allow_plugin_management']) ? (bool) $input['allow_plugin_management'] : false;

        
        
        
        
        
        
        if (isset($input['enabled_integrations']) && is_array($input['enabled_integrations'])) {
            $catalog = class_exists('\More_MCP\Capabilities\Toggles') ? \More_MCP\Capabilities\Toggles::catalog() : [];
            $clean   = [];
            foreach ($input['enabled_integrations'] as $slug) {
                $slug = is_string($slug) ? sanitize_key($slug) : '';
                if ('' !== $slug && isset($catalog[$slug])) {
                    $clean[] = $slug;
                }
            }
            $sanitized['enabled_integrations'] = array_values(array_unique($clean));
        } else {
            $existing = isset($settings['enabled_integrations']) && is_array($settings['enabled_integrations'])
                ? $settings['enabled_integrations']
                : [];
            $sanitized['enabled_integrations'] = $existing;
        }

        
        
        
        
        $sanitized[\More_MCP\Abilities\Importer::TOGGLE_KEY] = isset($input[\More_MCP\Abilities\Importer::TOGGLE_KEY])
            ? (bool) $input[\More_MCP\Abilities\Importer::TOGGLE_KEY]
            : false;

        
        
        
        
        
        
        
        
        
        $enabled_abilities = [];
        if (isset($input[\More_MCP\Abilities\Importer::ENABLED_KEY]) && is_array($input[\More_MCP\Abilities\Importer::ENABLED_KEY])) {
            $importable = array_keys(\More_MCP\Abilities\Importer::importable_abilities());
            $importable = array_flip($importable);
            foreach ($input[\More_MCP\Abilities\Importer::ENABLED_KEY] as $posted_name) {
                if (!is_string($posted_name)) {
                    continue;
                }
                $posted_name = trim($posted_name);
                
                
                
                
                
                if (isset($importable[$posted_name])) {
                    $enabled_abilities[] = $posted_name;
                }
            }
        }
        $sanitized[\More_MCP\Abilities\Importer::ENABLED_KEY] = array_values(array_unique($enabled_abilities));

        
        $posted_ttl = isset($input['access_token_ttl_seconds']) ? (int) $input['access_token_ttl_seconds'] : 0;
        $sanitized['access_token_ttl_seconds'] = in_array($posted_ttl, \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL_CHOICES, true)
            ? $posted_ttl
            : \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL;

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $keys = [];

        if (isset($input['writable_options_source']) && is_array($input['writable_options_source'])) {
            $summaries = Option_Presets::source_summaries();
            foreach ($input['writable_options_source'] as $posted_slug) {
                if (!is_string($posted_slug) || !isset($summaries[$posted_slug])) {
                    continue;
                }
                foreach ($summaries[$posted_slug]['names'] as $name) {
                    $clean = sanitize_key(trim((string) $name));
                    if ($clean !== '') {
                        $keys[] = $clean;
                    }
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

        
        
        
        
        
        $sanitized['seo_data'] = [];
        if (isset($input['seo_data']) && is_array($input['seo_data'])) {
            foreach (SEODataProviders::all() as $seo_slug => $seo_provider) {
                if (!isset($input['seo_data'][$seo_slug]) || !is_array($input['seo_data'][$seo_slug])) {
                    continue;
                }
                $seo_posted   = $input['seo_data'][$seo_slug];
                $seo_existing = isset($settings['seo_data'][$seo_slug]) && is_array($settings['seo_data'][$seo_slug])
                    ? $settings['seo_data'][$seo_slug]
                    : [];
                $seo_row = [
                    'enabled' => !empty($seo_posted['enabled']),
                ];

                foreach ($seo_provider['fields'] as $seo_field_id => $seo_field) {
                    
                    
                    
                    
                    
                    
                    if ('textarea' === $seo_field['type'] && 'service_account_json' === $seo_field_id) {
                        $raw = isset($seo_posted[$seo_field_id]) ? trim((string) $seo_posted[$seo_field_id]) : '';
                        if ('' === $raw) {
                            foreach (['client_email', 'private_key', 'token_uri', 'project_id'] as $k) {
                                if (isset($seo_existing[$k])) {
                                    $seo_row[$k] = $seo_existing[$k];
                                }
                            }
                            continue;
                        }
                        $parsed = \More_MCP\SEO_Data\Google_Service_Account::parse_key_json($raw);
                        if (is_wp_error($parsed)) {
                            
                            
                            
                            add_settings_error('more_mcp_settings', 'seo_data_' . $seo_slug, sprintf(
                                /* translators: 1: provider label, 2: parse error */
                                __('%1$s service account key was not saved: %2$s', 'more-mcp'),
                                $seo_provider['label'],
                                $parsed->get_error_message()
                            ));
                            foreach (['client_email', 'private_key', 'token_uri', 'project_id'] as $k) {
                                if (isset($seo_existing[$k])) {
                                    $seo_row[$k] = $seo_existing[$k];
                                }
                            }
                            continue;
                        }
                        foreach ($parsed as $k => $v) {
                            $seo_row[$k] = $v;
                        }
                        continue;
                    }

                    if (!isset($seo_posted[$seo_field_id])) {
                        continue;
                    }
                    switch ($seo_field['type']) {
                        case 'url':
                            $seo_row[$seo_field_id] = esc_url_raw($seo_posted[$seo_field_id]);
                            break;
                        case 'password':
                        case 'text':
                        case 'select':
                        default:
                            $seo_row[$seo_field_id] = sanitize_text_field($seo_posted[$seo_field_id]);
                            break;
                    }
                }

                
                
                
                
                foreach (['access_token', 'token_expires'] as $seo_preserve_key) {
                    if (isset($seo_existing[$seo_preserve_key])) {
                        $seo_row[$seo_preserve_key] = $seo_existing[$seo_preserve_key];
                    }
                }

                
                
                
                if ($seo_row['enabled'] || count($seo_row) > 1) {
                    $sanitized['seo_data'][$seo_slug] = $seo_row;
                }
            }
        }

        return $sanitized;
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'more-mcp') === false) {
            return;
        }

        
        
        
        
        
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
            'api_key' => \More_MCP\Auth\Api_Key::generate(),
        ]);

        
        
        
        
        
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
        
        $table_name = esc_sql($wpdb->prefix . 'more_mcp_logs');

        
        $nonce_valid = isset($_GET['_wpnonce']) ? wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'more_mcp_logs_page') : true;
        $page = ($nonce_valid && isset($_GET['paged'])) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        

        include MORE_MCP_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    
    public function ajax_test_connection() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $platform_id = isset($_POST['platform']) ? sanitize_text_field(wp_unslash($_POST['platform'])) : '';
        $config = [];

        
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

    
    public function ajax_reset_oauth_state() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $counts = \More_MCP\OAuth\Token_Store::reset_all_oauth_state();

        
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

    
    public static function admin_writable_options($opts) {
        if (!is_array($opts)) { $opts = []; }
        $settings = get_option('more_mcp_settings', []);
        if (!is_array($settings) || empty($settings['writable_options_admin'])
            || !is_array($settings['writable_options_admin'])) {
            return $opts;
        }
        return array_values(array_unique(array_merge($opts, $settings['writable_options_admin'])));
    }

    
    public function ajax_revoke_all_sessions() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        
        $allowed = (bool) apply_filters('more_mcp_revoke_all_sessions_allowed', true, get_current_user_id());
        if (!$allowed) {
            wp_send_json_error(['message' => esc_html__('Session revocation is disabled by a filter on this site.', 'more-mcp')]);
        }

        $revoked = \More_MCP\OAuth\Token_Store::revoke_all_tokens();

        
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

    
    private function log_admin_action($action, array $request = [], array $response = []) {
        global $wpdb;
        $current_user = wp_get_current_user();

        
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
            wp_send_json_error(['message' => esc_html__('That session no longer exists. It may have expired or already been ended.', 'more-mcp')]);
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
                esc_html__('Ended %d transport session(s). Credentials are untouched: clients reconnect without re-authorizing.', 'more-mcp'),
                $deleted
            ),
        ]);
    }

    
    public function ajax_toggle_integration() {
        check_ajax_referer('more_mcp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'more-mcp')]);
        }

        $slug    = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $enabled = isset($_POST['enabled']) && ('1' === (string) $_POST['enabled'] || 'true' === $_POST['enabled']);

        $catalog = \More_MCP\Capabilities\Toggles::catalog();
        if ('' === $slug || !isset($catalog[$slug])) {
            wp_send_json_error(['message' => esc_html__('Unknown integration.', 'more-mcp')]);
        }

        
        $settings = get_option('more_mcp_settings', []);
        $current  = isset($settings[\More_MCP\Capabilities\Toggles::OPTION_KEY]) && is_array($settings[\More_MCP\Capabilities\Toggles::OPTION_KEY])
            ? $settings[\More_MCP\Capabilities\Toggles::OPTION_KEY]
            : [];

        
        $set = [];
        foreach ($current as $s) {
            $s = is_string($s) ? $s : '';
            if ('' !== $s && isset($catalog[$s])) {
                $set[$s] = true;
            }
        }
        if ($enabled) {
            $set[$slug] = true;
        } else {
            unset($set[$slug]);
        }
        $settings[\More_MCP\Capabilities\Toggles::OPTION_KEY] = array_values(array_keys($set));

        update_option('more_mcp_settings', $settings);

        $this->log_admin_action(
            'integration:toggle',
            ['slug' => $slug, 'enabled' => $enabled],
            ['enabled_count' => count($settings[\More_MCP\Capabilities\Toggles::OPTION_KEY])]
        );

        wp_send_json_success([
            'slug'    => $slug,
            'enabled' => $enabled,
            'message' => $enabled
                ? sprintf(
                    /* translators: %s: integration label */
                    esc_html__('%s enabled. Its tools are now available to connected MCP clients.', 'more-mcp'),
                    $catalog[$slug]['label']
                )
                : sprintf(
                    /* translators: %s: integration label */
                    esc_html__('%s disabled. Its tools are no longer exposed, even though the plugin is still active.', 'more-mcp'),
                    $catalog[$slug]['label']
                ),
        ]);
    }

    public function admin_footer_text($text) {
        
        
        return $text;
    }

}
