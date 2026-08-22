<?php

if (!defined('ABSPATH')) {
    exit;
}

use More_MCP\Platform\Registry;

$more_mcp_settings = isset($settings) ? $settings : get_option('more_mcp_settings', []);
$more_mcp_platforms = isset($platforms) ? $platforms : Registry::get_platforms();
$more_mcp_platform_groups = isset($more_mcp_platform_groups) ? $more_mcp_platform_groups : Registry::get_platform_groups();
$more_mcp_configured_platforms = $more_mcp_settings['platforms'] ?? [];

$more_mcp_url = rest_url('more-mcp/v1/mcp');
$more_mcp_url_https = preg_replace('/^http:/', 'https:', $more_mcp_url);
$more_mcp_is_localhost = strpos($more_mcp_url, 'localhost') !== false || strpos($more_mcp_url, '127.0.0.1') !== false;
$more_mcp_rest_base = rest_url('more-mcp/v1/');

$more_mcp_panels = [
    'connection'  => [
        'label'    => __('Connection', 'more-mcp'),
        'dashicon' => 'dashicons-admin-links',
        'summary'  => __('Status, server URL, API key, and how to connect a client.', 'more-mcp'),
    ],
    'permissions' => [
        'label'    => __('Permissions', 'more-mcp'),
        'dashicon' => 'dashicons-shield',
        'summary'  => __('What AI agents are allowed to change.', 'more-mcp'),
    ],
    'sessions'    => [
        'label'    => __('Sessions', 'more-mcp'),
        'dashicon' => 'dashicons-clock',
        'summary'  => __('Connected clients, session length, OAuth reset.', 'more-mcp'),
    ],
    'services'    => [
        'label'    => __('External Services', 'more-mcp'),
        'dashicon' => 'dashicons-cloud',
        'summary'  => __('Credentials this site uses to call out: AI models, SEO and analytics data.', 'more-mcp'),
    ],
    'capabilities' => [
        'label'    => __('Capabilities', 'more-mcp'),
        'dashicon' => 'dashicons-screenoptions',
        'summary'  => __('What this site can do and which active providers back each capability.', 'more-mcp'),
    ],
    'docs'        => [
        'label'    => __('Documentation', 'more-mcp'),
        'dashicon' => 'dashicons-book-alt',
        'summary'  => __('Client setup, API reference, troubleshooting.', 'more-mcp'),
    ],
];

$more_mcp_panel_aliases = [
    'overview'  => 'connection',
    'guides'    => 'docs',
    'endpoints' => 'docs',
    'providers' => 'services',
];

$more_mcp_requested = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';
if (isset($more_mcp_panel_aliases[$more_mcp_requested])) {
    $more_mcp_requested = $more_mcp_panel_aliases[$more_mcp_requested];
}
$more_mcp_active = isset($more_mcp_panels[$more_mcp_requested]) ? $more_mcp_requested : 'connection';

$more_mcp_session_requested = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$more_mcp_session_view = in_array($more_mcp_session_requested, ['clients', 'transport'], true)
    ? $more_mcp_session_requested
    : 'clients';

$more_mcp_service_requested = isset($_GET['svc']) ? sanitize_key(wp_unslash($_GET['svc'])) : '';
$more_mcp_service_view = in_array($more_mcp_service_requested, ['ai', 'seo'], true)
    ? $more_mcp_service_requested
    : 'ai';

$more_mcp_panel_owns = [
    'connection'         => ['api_key', 'oauth_client_id', 'oauth_client_secret'],
    'permissions'        => ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management', 'writable_options_admin', 'allow_discovered_tools', 'discovered_abilities'],
    'sessions:clients'   => ['access_token_ttl_seconds'],
    'sessions:transport' => [],
    'services:ai'        => ['platforms'],
    'services:seo'       => ['seo_data'],
    'capabilities'       => [],
    'docs'               => [],
];

if ('sessions' === $more_mcp_active) {
    $more_mcp_owner_key = 'sessions:' . $more_mcp_session_view;
} elseif ('services' === $more_mcp_active) {
    $more_mcp_owner_key = 'services:' . $more_mcp_service_view;
} else {
    $more_mcp_owner_key = $more_mcp_active;
}

$more_mcp_preserve = function () use ($more_mcp_settings, $more_mcp_panel_owns, $more_mcp_owner_key) {
    $owned = $more_mcp_panel_owns[$more_mcp_owner_key] ?? [];

    $bools   = ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management', 'allow_discovered_tools'];
    $scalars = ['api_key', 'oauth_client_id', 'oauth_client_secret', 'access_token_ttl_seconds'];

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
            foreach ($more_mcp_disc as $more_mcp_disc_name) {
                if (!is_string($more_mcp_disc_name) || '' === $more_mcp_disc_name) {
                    continue;
                }
                printf(
                    '<input type="hidden" name="more_mcp_settings[discovered_abilities][]" value="%s">' . "\n",
                    esc_attr($more_mcp_disc_name)
                );
            }
        }
    }

    
    
    if (!in_array('platforms', $owned, true)) {
        $more_mcp_configured = $more_mcp_settings['platforms'] ?? [];
        if (is_array($more_mcp_configured)) {
            foreach ($more_mcp_configured as $more_mcp_i => $more_mcp_row) {
                if (!is_array($more_mcp_row)) {
                    continue;
                }
                foreach ($more_mcp_row as $more_mcp_field => $more_mcp_value) {
                    if (is_array($more_mcp_value)) {
                        continue;
                    }
                    if (is_bool($more_mcp_value)) {
                        if ($more_mcp_value) {
                            printf(
                                '<input type="hidden" name="more_mcp_settings[platforms][%d][%s]" value="1">' . "\n",
                                (int) $more_mcp_i,
                                esc_attr($more_mcp_field)
                            );
                        }
                        continue;
                    }
                    printf(
                        '<input type="hidden" name="more_mcp_settings[platforms][%d][%s]" value="%s">' . "\n",
                        (int) $more_mcp_i,
                        esc_attr($more_mcp_field),
                        esc_attr((string) $more_mcp_value)
                    );
                }
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

$more_mcp_readonly_panel = in_array($more_mcp_active, ['docs', 'capabilities'], true);
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

        <nav class="mmcp-sidebar" aria-label="<?php esc_attr_e('Settings sections', 'more-mcp'); ?>">
            <div class="mmcp-brand">
                <img class="mmcp-brand-mark"
                     src="<?php echo esc_url( MORE_MCP_PLUGIN_URL . 'assets/images/menu-icon.svg' ); ?>"
                     width="28" height="28" alt="" aria-hidden="true">
                <span class="mmcp-brand-text">
                    <span class="mmcp-brand-name"><?php esc_html_e( 'More MCP', 'more-mcp' ); ?></span>
                    <span class="mmcp-brand-tag"><?php esc_html_e( 'Secure AI connector', 'more-mcp' ); ?></span>
                </span>
            </div>
            <ul>
                <?php foreach ($more_mcp_panels as $more_mcp_slug => $more_mcp_panel) : ?>
                    <?php
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
                                        <span class="mmcp-nav-badge" title="<?php esc_attr_e('Connected clients', 'more-mcp'); ?>">
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
                        ? esc_html__('MCP server enabled', 'more-mcp')
                        : esc_html__('MCP server disabled', 'more-mcp');
                    ?>
                </span>
                <?php if (!$more_mcp_enabled) : ?>
                    <a href="<?php echo esc_url(add_query_arg('panel', 'permissions', $more_mcp_base_url)); ?>">
                        <?php esc_html_e('Enable', 'more-mcp'); ?>
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

<?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/settings/platform-template.php'; ?>
