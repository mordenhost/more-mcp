<?php

if (!defined('ABSPATH')) {
    exit;
}

$mordenhost_mcp_settings = isset($settings) ? $settings : get_option('mordenhost_mcp_settings', []);

$mordenhost_mcp_url = rest_url('mordenhost-mcp/v1/mcp');
$mordenhost_mcp_url_https = preg_replace('/^http:/', 'https:', $mordenhost_mcp_url);
$mordenhost_mcp_is_localhost = strpos($mordenhost_mcp_url, 'localhost') !== false || strpos($mordenhost_mcp_url, '127.0.0.1') !== false;
$mordenhost_mcp_rest_base = rest_url('mordenhost-mcp/v1/');

$mordenhost_mcp_panels = [
    'connection'  => [
        'label'    => __('Connection', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-admin-links',
        'summary'  => __('Status, connect a client, API key, session length, and OAuth setup.', 'mordenhost-mcp'),
        'section'  => 'setup',
    ],
    'permissions' => [
        'label'    => __('Access', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-shield',
        'summary'  => __('What connected agents may change, plus which plugins back each ability.', 'mordenhost-mcp'),
        'section'  => 'setup',
    ],

    'tokens'      => [
        'label'    => __('API Tokens', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-admin-network',
        'summary'  => __('Named, expiring, individually revocable credentials.', 'mordenhost-mcp'),
        'section'  => 'setup',
    ],
    'safety'      => [
        'label'    => __('Safety', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-lock',
        'summary'  => __('Pause everything, hold content as drafts, require approvals.', 'mordenhost-mcp'),
        'section'  => 'setup',
    ],
    'sessions'    => [
        'label'    => __('Sessions', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-clock',
        'summary'  => __('Who is connected right now: OAuth clients and transport sessions.', 'mordenhost-mcp'),
        'section'  => 'operate',
    ],
    'services'    => [
        'label'    => __('External Services', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-cloud',
        'summary'  => __('Credentials this site uses to call out: SEO and analytics data sources.', 'mordenhost-mcp'),
        'section'  => 'operate',
    ],

    
    'logs'        => [
        'label'    => __('Activity Log', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-list-view',
        'summary'  => __('What AI clients did on this site, most recent first.', 'mordenhost-mcp'),
        'section'  => 'operate',
    ],
    'history'     => [
        'label'    => __('Change History', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-backup',
        'summary'  => __('What AI clients changed, with a one-click restore.', 'mordenhost-mcp'),
        'section'  => 'operate',
    ],
    'docs'        => [
        'label'    => __('Documentation', 'mordenhost-mcp'),
        'dashicon' => 'dashicons-book-alt',
        'summary'  => __('Client setup, API reference, troubleshooting.', 'mordenhost-mcp'),
        'section'  => 'help',
    ],
];

$mordenhost_mcp_nav_sections = [
    'setup'   => __('Set up', 'mordenhost-mcp'),
    'operate' => __('Operate', 'mordenhost-mcp'),
    'help'    => __('Help', 'mordenhost-mcp'),
];

$mordenhost_mcp_panel_aliases = [
    'overview'     => 'connection',
    'guides'       => 'docs',
    'endpoints'    => 'docs',
    'providers'    => 'services',

    
    
    'capabilities' => 'permissions',
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$mordenhost_mcp_requested = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';
if (isset($mordenhost_mcp_panel_aliases[$mordenhost_mcp_requested])) {
    $mordenhost_mcp_requested = $mordenhost_mcp_panel_aliases[$mordenhost_mcp_requested];
}
$mordenhost_mcp_active = isset($mordenhost_mcp_panels[$mordenhost_mcp_requested]) ? $mordenhost_mcp_requested : 'connection';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$mordenhost_mcp_session_requested = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$mordenhost_mcp_session_view = in_array($mordenhost_mcp_session_requested, ['clients', 'transport'], true)
    ? $mordenhost_mcp_session_requested
    : 'clients';

$mordenhost_mcp_panel_owns = [

    
    
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

if ('sessions' === $mordenhost_mcp_active) {
    $mordenhost_mcp_owner_key = 'sessions:' . $mordenhost_mcp_session_view;
} else {
    $mordenhost_mcp_owner_key = $mordenhost_mcp_active;
}

$mordenhost_mcp_preserve = function () use ($mordenhost_mcp_settings, $mordenhost_mcp_panel_owns, $mordenhost_mcp_owner_key) {
    $owned = $mordenhost_mcp_panel_owns[$mordenhost_mcp_owner_key] ?? [];

    $bools   = ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management', 'allow_ai_media', 'allow_code_writes', 'allow_discovered_tools'];

    
    
    $scalars = ['oauth_client_id', 'oauth_client_secret', 'access_token_ttl_seconds', 'log_retention_days', 'log_row_cap'];

    foreach ($bools as $key) {
        if (in_array($key, $owned, true)) {
            continue;
        }
        if (!empty($mordenhost_mcp_settings[$key])) {
            printf(
                '<input type="hidden" name="mordenhost_mcp_settings[%s]" value="1">' . "\n",
                esc_attr($key)
            );
        }
    }

    foreach ($scalars as $key) {
        if (in_array($key, $owned, true)) {
            continue;
        }
        if (isset($mordenhost_mcp_settings[$key]) && $mordenhost_mcp_settings[$key] !== '') {
            printf(
                '<input type="hidden" name="mordenhost_mcp_settings[%s]" value="%s">' . "\n",
                esc_attr($key),
                esc_attr((string) $mordenhost_mcp_settings[$key])
            );
        }
    }

    

    
    if (!in_array('writable_options_admin', $owned, true)) {
        $mordenhost_mcp_wo = $mordenhost_mcp_settings['writable_options_admin'] ?? [];
        if (is_array($mordenhost_mcp_wo) && !empty($mordenhost_mcp_wo)) {
            printf(
                '<textarea name="mordenhost_mcp_settings[writable_options_admin]" style="display:none" aria-hidden="true">%s</textarea>' . "\n",
                esc_textarea(implode("\n", $mordenhost_mcp_wo))
            );
        }
    }

    

    

    if (!in_array('discovered_abilities', $owned, true)) {
        $mordenhost_mcp_disc = $mordenhost_mcp_settings['discovered_abilities'] ?? [];
        if (is_array($mordenhost_mcp_disc)) {
            foreach ($mordenhost_mcp_disc as $mordenhost_mcp_disc_ns) {
                if (!is_string($mordenhost_mcp_disc_ns) || '' === $mordenhost_mcp_disc_ns) {
                    continue;
                }
                printf(
                    '<input type="hidden" name="mordenhost_mcp_settings[discovered_abilities][]" value="%s">' . "\n",
                    esc_attr($mordenhost_mcp_disc_ns)
                );
            }
        }
    }

    
    
    if (!in_array('seo_data', $owned, true)) {
        $mordenhost_mcp_seo_data = $mordenhost_mcp_settings['seo_data'] ?? [];
        if (is_array($mordenhost_mcp_seo_data)) {
            foreach ($mordenhost_mcp_seo_data as $mordenhost_mcp_slug => $mordenhost_mcp_row) {
                if (!is_array($mordenhost_mcp_row)) {
                    continue;
                }
                foreach ($mordenhost_mcp_row as $mordenhost_mcp_field => $mordenhost_mcp_value) {
                    if (is_array($mordenhost_mcp_value)) {
                        continue;
                    }

                    

                    

                    

                    
                    
                    if ('private_key' === $mordenhost_mcp_field || 'access_token' === $mordenhost_mcp_field) {
                        continue;
                    }
                    if (is_bool($mordenhost_mcp_value)) {
                        if ($mordenhost_mcp_value) {
                            printf(
                                '<input type="hidden" name="mordenhost_mcp_settings[seo_data][%s][%s]" value="1">' . "\n",
                                esc_attr($mordenhost_mcp_slug),
                                esc_attr($mordenhost_mcp_field)
                            );
                        }
                        continue;
                    }
                    printf(
                        '<input type="hidden" name="mordenhost_mcp_settings[seo_data][%s][%s]" value="%s">' . "\n",
                        esc_attr($mordenhost_mcp_slug),
                        esc_attr($mordenhost_mcp_field),
                        esc_attr((string) $mordenhost_mcp_value)
                    );
                }
            }
        }
    }
};

$mordenhost_mcp_base_url = admin_url('admin.php?page=mordenhost-mcp');

$mordenhost_mcp_readonly_panel = in_array($mordenhost_mcp_active, ['docs', 'permissions', 'logs', 'sessions', 'safety', 'tokens', 'history'], true);
$mordenhost_mcp_enabled = !empty($mordenhost_mcp_settings['enabled']);

$mordenhost_mcp_grant_count   = class_exists('\Mordenhost_MCP\OAuth\Token_Store') && method_exists('\Mordenhost_MCP\OAuth\Token_Store', 'count_active_grants')
    ? (int) \Mordenhost_MCP\OAuth\Token_Store::count_active_grants()
    : 0;
$mordenhost_mcp_session_count = class_exists('\Mordenhost_MCP\MCP\Session_Store') && method_exists('\Mordenhost_MCP\MCP\Session_Store', 'count_active')
    ? (int) \Mordenhost_MCP\MCP\Session_Store::count_active()
    : 0;

$mordenhost_mcp_panel_badges = [
    'sessions' => $mordenhost_mcp_grant_count,
];
$mordenhost_mcp_panel_badge_titles = [
    'sessions' => __('Connected clients', 'mordenhost-mcp'),
    'safety'   => __('Approval requests waiting', 'mordenhost-mcp'),
];

if (class_exists('\Mordenhost_MCP\Access\Approvals') && method_exists('\Mordenhost_MCP\Access\Approvals', 'pending_count') && isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && !empty($GLOBALS['wpdb']->prefix)) {
    $mordenhost_mcp_panel_badges['safety'] = (int) \Mordenhost_MCP\Access\Approvals::pending_count();
}

$mordenhost_mcp_tool_count = 0;
if (class_exists('\Mordenhost_MCP\MCP\Server')) {
    $mordenhost_mcp_tools_srv = new \Mordenhost_MCP\MCP\Server();
    if (method_exists($mordenhost_mcp_tools_srv, 'get_all_tools')) {
        $mordenhost_mcp_tool_count = count($mordenhost_mcp_tools_srv->get_all_tools());
    }
}

$mordenhost_mcp_protocol_version = '2025-11-25';
?>

<div class="wrap mordenhost-mcp-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <div class="mmcp-layout">

        <nav class="mmcp-sidebar" aria-label="<?php esc_attr_e('Settings sections', 'mordenhost-mcp'); ?>">
            <div class="mmcp-brand">
                <img class="mmcp-brand-mark"
                     src="<?php echo esc_url( MORDENHOST_MCP_PLUGIN_URL . 'assets/images/menu-icon.svg' ); ?>"
                     width="28" height="28" alt="" aria-hidden="true">
                <span class="mmcp-brand-text">
                    <span class="mmcp-brand-name"><?php esc_html_e( 'Mordenhost MCP', 'mordenhost-mcp' ); ?></span>
                    <span class="mmcp-brand-tag"><?php esc_html_e( 'Secure AI connector', 'mordenhost-mcp' ); ?></span>
                </span>
            </div>
            <ul>
                <?php

                

                $mordenhost_mcp_current_section = null;
                ?>
                <?php foreach ($mordenhost_mcp_panels as $mordenhost_mcp_slug => $mordenhost_mcp_panel) : ?>
                    <?php
                    $mordenhost_mcp_section = $mordenhost_mcp_panel['section'] ?? '';
                    if ($mordenhost_mcp_section !== $mordenhost_mcp_current_section) {
                        $mordenhost_mcp_current_section = $mordenhost_mcp_section;
                        $mordenhost_mcp_section_label   = $mordenhost_mcp_nav_sections[$mordenhost_mcp_section] ?? '';
                        if ('' !== $mordenhost_mcp_section_label) {
                            printf(
                                '<li class="mmcp-nav-section" aria-hidden="true">%s</li>',
                                esc_html($mordenhost_mcp_section_label)
                            );
                        }
                    }
                    $mordenhost_mcp_is_active = ($mordenhost_mcp_slug === $mordenhost_mcp_active);
                    $mordenhost_mcp_badge     = $mordenhost_mcp_panel_badges[$mordenhost_mcp_slug] ?? 0;
                    ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('panel', $mordenhost_mcp_slug, $mordenhost_mcp_base_url)); ?>"
                           class="mmcp-nav-item<?php echo $mordenhost_mcp_is_active ? ' is-active' : ''; ?>"
                           <?php echo $mordenhost_mcp_is_active ? 'aria-current="page"' : ''; ?>>
                            <span class="dashicons <?php echo esc_attr($mordenhost_mcp_panel['dashicon']); ?>" aria-hidden="true"></span>
                            <span class="mmcp-nav-text">
                                <span class="mmcp-nav-label">
                                    <?php echo esc_html($mordenhost_mcp_panel['label']); ?>
                                    <?php if ($mordenhost_mcp_badge > 0) : ?>
                                        <span class="mmcp-nav-badge" title="<?php echo esc_attr($mordenhost_mcp_panel_badge_titles[$mordenhost_mcp_slug] ?? ''); ?>">
                                            <?php echo esc_html(number_format_i18n($mordenhost_mcp_badge)); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="mmcp-nav-summary"><?php echo esc_html($mordenhost_mcp_panel['summary']); ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="mmcp-status <?php echo $mordenhost_mcp_enabled ? 'is-on' : 'is-off'; ?>">
                <span class="mmcp-status-dot" aria-hidden="true"></span>
                <span class="mmcp-status-text">
                    <?php
                    echo $mordenhost_mcp_enabled
                        ? esc_html__('MCP server enabled', 'mordenhost-mcp')
                        : esc_html__('MCP server disabled', 'mordenhost-mcp');
                    ?>
                </span>
                <?php if (!$mordenhost_mcp_enabled) : ?>
                    <a href="<?php echo esc_url(add_query_arg('panel', 'permissions', $mordenhost_mcp_base_url)); ?>">
                        <?php esc_html_e('Enable', 'mordenhost-mcp'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="mmcp-panel">
            <div class="mmcp-panel-header">
                <h2>
                    <span class="dashicons <?php echo esc_attr($mordenhost_mcp_panels[$mordenhost_mcp_active]['dashicon']); ?>" aria-hidden="true"></span>
                    <?php echo esc_html($mordenhost_mcp_panels[$mordenhost_mcp_active]['label']); ?>
                </h2>
                <p class="description"><?php echo esc_html($mordenhost_mcp_panels[$mordenhost_mcp_active]['summary']); ?></p>
            </div>

            <?php if ($mordenhost_mcp_readonly_panel) : ?>

                <div class="mmcp-panel-body">
                    <?php require MORDENHOST_MCP_PLUGIN_DIR . 'templates/admin/settings/panel-' . $mordenhost_mcp_active . '.php'; ?>
                </div>

            <?php else : ?>

                <form method="post" action="options.php" id="mordenhost-mcp-settings-form">
                    <?php settings_fields('mordenhost_mcp_settings_group'); ?>
                    <?php $mordenhost_mcp_preserve(); ?>

                    <div class="mmcp-panel-body">
                        <?php require MORDENHOST_MCP_PLUGIN_DIR . 'templates/admin/settings/panel-' . $mordenhost_mcp_active . '.php'; ?>
                    </div>

                    <?php submit_button(); ?>
                </form>

            <?php endif; ?>
        </div>

    </div>
</div>

