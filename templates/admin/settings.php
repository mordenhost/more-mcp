<?php

if (!defined('ABSPATH')) {
    exit;
}

$more_mcp_settings = isset($settings) ? $settings : get_option('more_mcp_settings', []);

$more_mcp_url = rest_url('more-mcp/v1/mcp');
$more_mcp_url_https = preg_replace('/^http:/', 'https:', $more_mcp_url);
$more_mcp_is_localhost = strpos($more_mcp_url, 'localhost') !== false || strpos($more_mcp_url, '127.0.0.1') !== false;
$more_mcp_rest_base = rest_url('more-mcp/v1/');

$more_mcp_panels = [
    'connection'  => [
        'label'    => __('Connection', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-admin-links',
        'summary'  => __('Status, connect a client, API key, session length, and OAuth setup.', 'mordenhost-mcp-server'),
        'section'  => 'setup',
    ],
    'permissions' => [
        'label'    => __('Access', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-shield',
        'summary'  => __('What connected agents may change, plus which plugins back each ability.', 'mordenhost-mcp-server'),
        'section'  => 'setup',
    ],

    'tokens'      => [
        'label'    => __('API Tokens', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-admin-network',
        'summary'  => __('Named, expiring, individually revocable credentials.', 'mordenhost-mcp-server'),
        'section'  => 'setup',
    ],
    'safety'      => [
        'label'    => __('Safety', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-lock',
        'summary'  => __('Pause everything, hold content as drafts, require approvals.', 'mordenhost-mcp-server'),
        'section'  => 'setup',
    ],
    'sessions'    => [
        'label'    => __('Sessions', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-clock',
        'summary'  => __('Who is connected right now: OAuth clients and transport sessions.', 'mordenhost-mcp-server'),
        'section'  => 'operate',
    ],
    'services'    => [
        'label'    => __('External Services', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-cloud',
        'summary'  => __('Credentials this site uses to call out: SEO and analytics data sources.', 'mordenhost-mcp-server'),
        'section'  => 'operate',
    ],

    
    'logs'        => [
        'label'    => __('Activity Log', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-list-view',
        'summary'  => __('What AI clients did on this site, most recent first.', 'mordenhost-mcp-server'),
        'section'  => 'operate',
    ],
    'history'     => [
        'label'    => __('Change History', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-backup',
        'summary'  => __('What AI clients changed, with a one-click restore.', 'mordenhost-mcp-server'),
        'section'  => 'operate',
    ],
    'docs'        => [
        'label'    => __('Documentation', 'mordenhost-mcp-server'),
        'dashicon' => 'dashicons-book-alt',
        'summary'  => __('Client setup, API reference, troubleshooting.', 'mordenhost-mcp-server'),
        'section'  => 'help',
    ],
];

$more_mcp_nav_sections = [
    'setup'   => __('Set up', 'mordenhost-mcp-server'),
    'operate' => __('Operate', 'mordenhost-mcp-server'),
    'help'    => __('Help', 'mordenhost-mcp-server'),
];

$more_mcp_panel_aliases = [
    'overview'     => 'connection',
    'guides'       => 'docs',
    'endpoints'    => 'docs',
    'providers'    => 'services',

    
    
    'capabilities' => 'permissions',
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$more_mcp_requested = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';
if (isset($more_mcp_panel_aliases[$more_mcp_requested])) {
    $more_mcp_requested = $more_mcp_panel_aliases[$more_mcp_requested];
}
$more_mcp_active = isset($more_mcp_panels[$more_mcp_requested]) ? $more_mcp_requested : 'connection';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$more_mcp_session_requested = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$more_mcp_session_view = in_array($more_mcp_session_requested, ['clients', 'transport'], true)
    ? $more_mcp_session_requested
    : 'clients';

$more_mcp_panel_owns = [

    
    
    'connection'         => ['oauth_client_id', 'oauth_client_secret'],

    

    

    'permissions'        => ['writable_options_admin'],

    'sessions:clients'   => [],
    'sessions:transport' => [],
    'services'           => ['seo_data'],
    'docs'               => [],
    
    'logs'               => [],

    'safety'             => [],
    'tokens'             => [],
    'history'            => [],
];

if ('sessions' === $more_mcp_active) {
    $more_mcp_owner_key = 'sessions:' . $more_mcp_session_view;
} else {
    $more_mcp_owner_key = $more_mcp_active;
}

$more_mcp_preserve = function () use ($more_mcp_settings, $more_mcp_panel_owns, $more_mcp_owner_key) {
    $owned = $more_mcp_panel_owns[$more_mcp_owner_key] ?? [];

    $bools   = ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management', 'allow_ai_media', 'allow_discovered_tools'];

    
    
    $scalars = ['oauth_client_id', 'oauth_client_secret', 'access_token_ttl_seconds', 'log_retention_days', 'log_row_cap'];

    foreach ($bools as $key) {
        if (in_array($key, $owned, true)) {
            continue;
        }
        if (!empty($more_mcp_settings[$key])) {
            printf(
                '<input type="hidden" name="more_mcp_settings[%s]" value="1">' . "\n",
                esc_attr($key)
            );
        }
    }

    foreach ($scalars as $key) {
        if (in_array($key, $owned, true)) {
            continue;
        }
        if (isset($more_mcp_settings[$key]) && $more_mcp_settings[$key] !== '') {
            printf(
                '<input type="hidden" name="more_mcp_settings[%s]" value="%s">' . "\n",
                esc_attr($key),
                esc_attr((string) $more_mcp_settings[$key])
            );
        }
    }

    

    
    if (!in_array('writable_options_admin', $owned, true)) {
        $more_mcp_wo = $more_mcp_settings['writable_options_admin'] ?? [];
        if (is_array($more_mcp_wo) && !empty($more_mcp_wo)) {
            printf(
                '<textarea name="more_mcp_settings[writable_options_admin]" style="display:none" aria-hidden="true">%s</textarea>' . "\n",
                esc_textarea(implode("\n", $more_mcp_wo))
            );
        }
    }

    

    

    if (!in_array('discovered_abilities', $owned, true)) {
        $more_mcp_disc = $more_mcp_settings['discovered_abilities'] ?? [];
        if (is_array($more_mcp_disc)) {
            foreach ($more_mcp_disc as $more_mcp_disc_ns) {
                if (!is_string($more_mcp_disc_ns) || '' === $more_mcp_disc_ns) {
                    continue;
                }
                printf(
                    '<input type="hidden" name="more_mcp_settings[discovered_abilities][]" value="%s">' . "\n",
                    esc_attr($more_mcp_disc_ns)
                );
            }
        }
    }

    
    
    if (!in_array('seo_data', $owned, true)) {
        $more_mcp_seo_data = $more_mcp_settings['seo_data'] ?? [];
        if (is_array($more_mcp_seo_data)) {
            foreach ($more_mcp_seo_data as $more_mcp_slug => $more_mcp_row) {
                if (!is_array($more_mcp_row)) {
                    continue;
                }
                foreach ($more_mcp_row as $more_mcp_field => $more_mcp_value) {
                    if (is_array($more_mcp_value)) {
                        continue;
                    }

                    

                    

                    

                    
                    
                    if ('private_key' === $more_mcp_field || 'access_token' === $more_mcp_field) {
                        continue;
                    }
                    if (is_bool($more_mcp_value)) {
                        if ($more_mcp_value) {
                            printf(
                                '<input type="hidden" name="more_mcp_settings[seo_data][%s][%s]" value="1">' . "\n",
                                esc_attr($more_mcp_slug),
                                esc_attr($more_mcp_field)
                            );
                        }
                        continue;
                    }
                    printf(
                        '<input type="hidden" name="more_mcp_settings[seo_data][%s][%s]" value="%s">' . "\n",
                        esc_attr($more_mcp_slug),
                        esc_attr($more_mcp_field),
                        esc_attr((string) $more_mcp_value)
                    );
                }
            }
        }
    }
};

$more_mcp_base_url = admin_url('admin.php?page=more-mcp');

$more_mcp_readonly_panel = in_array($more_mcp_active, ['docs', 'permissions', 'logs', 'sessions', 'safety', 'tokens', 'history'], true);
$more_mcp_enabled = !empty($more_mcp_settings['enabled']);

$more_mcp_grant_count   = class_exists('\More_MCP\OAuth\Token_Store') && method_exists('\More_MCP\OAuth\Token_Store', 'count_active_grants')
    ? (int) \More_MCP\OAuth\Token_Store::count_active_grants()
    : 0;
$more_mcp_session_count = class_exists('\More_MCP\MCP\Session_Store') && method_exists('\More_MCP\MCP\Session_Store', 'count_active')
    ? (int) \More_MCP\MCP\Session_Store::count_active()
    : 0;

$more_mcp_panel_badges = [
    'sessions' => $more_mcp_grant_count,
];
$more_mcp_panel_badge_titles = [
    'sessions' => __('Connected clients', 'mordenhost-mcp-server'),
    'safety'   => __('Approval requests waiting', 'mordenhost-mcp-server'),
];

if (class_exists('\More_MCP\Access\Approvals') && method_exists('\More_MCP\Access\Approvals', 'pending_count') && isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && !empty($GLOBALS['wpdb']->prefix)) {
    $more_mcp_panel_badges['safety'] = (int) \More_MCP\Access\Approvals::pending_count();
}

$more_mcp_tool_count = 0;
if (class_exists('\More_MCP\MCP\Server')) {
    $more_mcp_tools_srv = new \More_MCP\MCP\Server();
    if (method_exists($more_mcp_tools_srv, 'get_all_tools')) {
        $more_mcp_tool_count = count($more_mcp_tools_srv->get_all_tools());
    }
}

$more_mcp_protocol_version = '2025-11-25';
?>

<div class="wrap more-mcp-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <div class="mmcp-layout">

        <nav class="mmcp-sidebar" aria-label="<?php esc_attr_e('Settings sections', 'mordenhost-mcp-server'); ?>">
            <div class="mmcp-brand">
                <img class="mmcp-brand-mark"
                     src="<?php echo esc_url( MORE_MCP_PLUGIN_URL . 'assets/images/menu-icon.svg' ); ?>"
                     width="28" height="28" alt="" aria-hidden="true">
                <span class="mmcp-brand-text">
                    <span class="mmcp-brand-name"><?php esc_html_e( 'More MCP', 'mordenhost-mcp-server' ); ?></span>
                    <span class="mmcp-brand-tag"><?php esc_html_e( 'Secure AI connector', 'mordenhost-mcp-server' ); ?></span>
                </span>
            </div>
            <ul>
                <?php

                

                $more_mcp_current_section = null;
                ?>
                <?php foreach ($more_mcp_panels as $more_mcp_slug => $more_mcp_panel) : ?>
                    <?php
                    $more_mcp_section = $more_mcp_panel['section'] ?? '';
                    if ($more_mcp_section !== $more_mcp_current_section) {
                        $more_mcp_current_section = $more_mcp_section;
                        $more_mcp_section_label   = $more_mcp_nav_sections[$more_mcp_section] ?? '';
                        if ('' !== $more_mcp_section_label) {
                            printf(
                                '<li class="mmcp-nav-section" aria-hidden="true">%s</li>',
                                esc_html($more_mcp_section_label)
                            );
                        }
                    }
                    $more_mcp_is_active = ($more_mcp_slug === $more_mcp_active);
                    $more_mcp_badge     = $more_mcp_panel_badges[$more_mcp_slug] ?? 0;
                    ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('panel', $more_mcp_slug, $more_mcp_base_url)); ?>"
                           class="mmcp-nav-item<?php echo $more_mcp_is_active ? ' is-active' : ''; ?>"
                           <?php echo $more_mcp_is_active ? 'aria-current="page"' : ''; ?>>
                            <span class="dashicons <?php echo esc_attr($more_mcp_panel['dashicon']); ?>" aria-hidden="true"></span>
                            <span class="mmcp-nav-text">
                                <span class="mmcp-nav-label">
                                    <?php echo esc_html($more_mcp_panel['label']); ?>
                                    <?php if ($more_mcp_badge > 0) : ?>
                                        <span class="mmcp-nav-badge" title="<?php echo esc_attr($more_mcp_panel_badge_titles[$more_mcp_slug] ?? ''); ?>">
                                            <?php echo esc_html(number_format_i18n($more_mcp_badge)); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="mmcp-nav-summary"><?php echo esc_html($more_mcp_panel['summary']); ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="mmcp-status <?php echo $more_mcp_enabled ? 'is-on' : 'is-off'; ?>">
                <span class="mmcp-status-dot" aria-hidden="true"></span>
                <span class="mmcp-status-text">
                    <?php
                    echo $more_mcp_enabled
                        ? esc_html__('MCP server enabled', 'mordenhost-mcp-server')
                        : esc_html__('MCP server disabled', 'mordenhost-mcp-server');
                    ?>
                </span>
                <?php if (!$more_mcp_enabled) : ?>
                    <a href="<?php echo esc_url(add_query_arg('panel', 'permissions', $more_mcp_base_url)); ?>">
                        <?php esc_html_e('Enable', 'mordenhost-mcp-server'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="mmcp-panel">
            <div class="mmcp-panel-header">
                <h2>
                    <span class="dashicons <?php echo esc_attr($more_mcp_panels[$more_mcp_active]['dashicon']); ?>" aria-hidden="true"></span>
                    <?php echo esc_html($more_mcp_panels[$more_mcp_active]['label']); ?>
                </h2>
                <p class="description"><?php echo esc_html($more_mcp_panels[$more_mcp_active]['summary']); ?></p>
            </div>

            <?php if ($more_mcp_readonly_panel) : ?>

                <div class="mmcp-panel-body">
                    <?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/settings/panel-' . $more_mcp_active . '.php'; ?>
                </div>

            <?php else : ?>

                <form method="post" action="options.php" id="more-mcp-settings-form">
                    <?php settings_fields('more_mcp_settings_group'); ?>
                    <?php $more_mcp_preserve(); ?>

                    <div class="mmcp-panel-body">
                        <?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/settings/panel-' . $more_mcp_active . '.php'; ?>
                    </div>

                    <?php submit_button(); ?>
                </form>

            <?php endif; ?>
        </div>

    </div>
</div>

