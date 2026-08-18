<?php
/**
 * Settings screen — sidebar navigation plus one visible panel.
 *
 * Replaces the previous single 874-line scroll. Sections now live in
 * templates/admin/settings/panel-*.php and only the active one renders.
 *
 * Panel state travels in the `panel` query arg, so a reload or a bookmark
 * lands on the same panel. No JavaScript is involved in switching: each
 * sidebar item is a real link and the server decides what to include, which
 * also means the browser's back button behaves normally.
 *
 * One consequence worth stating plainly: because only the active panel is
 * rendered, only its fields post on save. WordPress's Settings API hands
 * sanitize_settings() whatever the form submitted, and a missing key reads as
 * "unset" — so every panel re-submits the values owned by the other panels as
 * hidden inputs. Without that, saving Permissions would silently wipe the API
 * key. See $more_mcp_preserve below.
 */

if (!defined('ABSPATH')) {
    exit;
}

use More_MCP\Platform\Registry;

$more_mcp_settings = isset($settings) ? $settings : get_option('more_mcp_settings', []);
$more_mcp_platforms = isset($platforms) ? $platforms : Registry::get_platforms();
$more_mcp_platform_groups = isset($more_mcp_platform_groups) ? $more_mcp_platform_groups : Registry::get_platform_groups();
$more_mcp_configured_platforms = $more_mcp_settings['platforms'] ?? [];

// The MCP endpoint — same URL for every MCP client (Claude.ai, ChatGPT,
// Claude Desktop, Cursor, Gemini, etc.). Forced HTTPS because MCP backends
// reject plain HTTP origins.
$more_mcp_url = rest_url('more-mcp/v1/mcp');
$more_mcp_url_https = preg_replace('/^http:/', 'https:', $more_mcp_url);
$more_mcp_is_localhost = strpos($more_mcp_url, 'localhost') !== false || strpos($more_mcp_url, '127.0.0.1') !== false;
$more_mcp_rest_base = rest_url('more-mcp/v1/');

/**
 * Panel definitions. Order here is the order in the sidebar.
 *
 * `summary` gives each panel a one-line purpose so an admin can pick the right
 * one without clicking through all of them.
 *
 * Connection is the landing panel: an admin opening the plugin first sees
 * whether it is working, the URL and key to connect a client, and what is
 * connected right now, followed by the connection-level settings. It absorbed
 * the former standalone Overview panel, which duplicated the URL and key.
 *
 * Setup Guides and API Reference used to be two separate sidebar entries. They
 * are now sub-tabs of a single Documentation panel: both are read-only reference
 * material, neither is something an admin visits repeatedly, and giving each its
 * own top-level slot made the sidebar read as if half the plugin were docs.
 */
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
    'providers'   => [
        'label'    => __('AI Providers', 'more-mcp'),
        'dashicon' => 'dashicons-cloud',
        'summary'  => __('Optional outbound provider credentials.', 'more-mcp'),
    ],
    'docs'        => [
        'label'    => __('Documentation', 'more-mcp'),
        'dashicon' => 'dashicons-book-alt',
        'summary'  => __('Client setup, API reference, troubleshooting.', 'more-mcp'),
    ],
];

// Resolve the active panel. An unknown or missing value falls back to the
// first panel rather than rendering an empty page.
//
// `guides` and `endpoints` were separate panels before the Documentation merge.
// `overview` was a separate landing panel before it was merged into Connection.
// All are aliased rather than dropped: those URLs were linked from the plugin
// row, from support replies, and from admins' own bookmarks, and a stale link
// silently landing on the wrong panel would look like the page had lost content.
$more_mcp_panel_aliases = [
    'overview'  => 'connection',
    'guides'    => 'docs',
    'endpoints' => 'docs',
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$more_mcp_requested = isset($_GET['panel']) ? sanitize_key(wp_unslash($_GET['panel'])) : '';
if (isset($more_mcp_panel_aliases[$more_mcp_requested])) {
    $more_mcp_requested = $more_mcp_panel_aliases[$more_mcp_requested];
}
$more_mcp_active = isset($more_mcp_panels[$more_mcp_requested]) ? $more_mcp_requested : 'connection';

/**
 * Resolve the Sessions panel's sub-tab.
 *
 * This has to happen HERE, in the parent, rather than inside the panel the way
 * panel-docs.php resolves its own — and the asymmetry is load-bearing rather than
 * an oversight.
 *
 * Sessions is the only panel that OWNS a setting (access_token_ttl_seconds), and
 * $more_mcp_preserve() runs before the panel is required. So the parent must know
 * which sub-tab will render in order to decide whether the TTL select is about to
 * post. Get this wrong and saving from the Transport sub-tab silently resets the
 * session length, which is exactly the class of bug the preserve mechanism exists
 * to prevent. Documentation owns nothing, so it can resolve its own sub-tab.
 */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$more_mcp_session_requested = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$more_mcp_session_view = in_array($more_mcp_session_requested, ['clients', 'transport'], true)
    ? $more_mcp_session_requested
    : 'clients';

/**
 * Which settings keys each panel's own fields post.
 *
 * Anything not owned by the active panel is re-emitted as a hidden input.
 *
 * Sessions is keyed per sub-tab because its two sub-tabs post different fields:
 * the TTL select lives on Clients, so from Transport the TTL is not "owned" and
 * must be preserved as a hidden input like any other panel's settings.
 */
$more_mcp_panel_owns = [
    'connection'         => ['api_key', 'oauth_client_id', 'oauth_client_secret'],
    'permissions'        => ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management', 'writable_options_admin'],
    'sessions:clients'   => ['access_token_ttl_seconds'],
    'sessions:transport' => [],
    'providers'          => ['platforms'],
    'docs'               => [],
];

// The ownership key for the current view. Only Sessions splits by sub-tab.
$more_mcp_owner_key = ('sessions' === $more_mcp_active)
    ? 'sessions:' . $more_mcp_session_view
    : $more_mcp_active;

/**
 * Emit hidden inputs for every setting the active panel does not own.
 *
 * Checkboxes need care: an unchecked box posts nothing and sanitize_settings
 * reads a missing key as false. A hidden "1" is therefore emitted only when
 * the stored value is truthy — mirroring what the real checkbox would send.
 */
$more_mcp_preserve = function () use ($more_mcp_settings, $more_mcp_panel_owns, $more_mcp_owner_key) {
    $owned = $more_mcp_panel_owns[$more_mcp_owner_key] ?? [];

    $bools   = ['enabled', 'allow_option_writes', 'allow_theme_writes', 'allow_plugin_management'];
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

    // The option allowlist. On the Permissions panel this posts as two inputs —
    // preset checkboxes plus a textarea for hand-typed names — but sanitize_settings()
    // merges them back into one flat list, so from any OTHER panel the whole list
    // can be preserved through the textarea alone. Preset-covered names survive
    // that round trip because the stored shape never distinguished them.
    if (!in_array('writable_options_admin', $owned, true)) {
        $more_mcp_wo = $more_mcp_settings['writable_options_admin'] ?? [];
        if (is_array($more_mcp_wo) && !empty($more_mcp_wo)) {
            printf(
                '<textarea name="more_mcp_settings[writable_options_admin]" style="display:none" aria-hidden="true">%s</textarea>' . "\n",
                esc_textarea(implode("\n", $more_mcp_wo))
            );
        }
    }

    // Configured outbound providers are a nested array, so each leaf is
    // re-emitted individually to keep the structure intact across a save from
    // a panel that does not own it.
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
};

$more_mcp_base_url = admin_url('admin.php?page=more-mcp');

// Documentation is read-only reference material, so it renders outside the form:
// no submit button, nothing to save. Connection absorbed the former Overview, so
// it is a form panel like the rest.
$more_mcp_readonly_panel = ('docs' === $more_mcp_active);
$more_mcp_enabled = !empty($more_mcp_settings['enabled']);

// Live counts for the sidebar. Both stores are queried here rather than inside
// the Sessions panel so the sidebar badge is accurate on every panel — an admin
// on Permissions should still see that four clients are connected.
//
// Guarded on class_exists because this template is also rendered by
// tests/settings-panels-test.php, which stubs only what the form needs and has
// no database at all. A missing store degrades to "no badge" rather than fataling.
$more_mcp_grant_count   = class_exists('\More_MCP\OAuth\Token_Store') && method_exists('\More_MCP\OAuth\Token_Store', 'count_active_grants')
    ? (int) \More_MCP\OAuth\Token_Store::count_active_grants()
    : 0;
$more_mcp_session_count = class_exists('\More_MCP\MCP\Session_Store') && method_exists('\More_MCP\MCP\Session_Store', 'count_active')
    ? (int) \More_MCP\MCP\Session_Store::count_active()
    : 0;

// Badge counts are keyed by panel slug so the nav loop stays generic.
$more_mcp_panel_badges = [
    'sessions' => $more_mcp_grant_count,
];

// Live tool count for the Connection panel's "Right now" tiles. Read from the
// same registry the tools/list response is built from, so it reflects which
// optional integrations are active on this specific site. Guarded like the
// counts above: the render tests stub no MCP server, and a missing registry
// should degrade to zero rather than fatal the settings screen.
$more_mcp_tool_count = 0;
if (class_exists('\More_MCP\MCP\Server')) {
    $more_mcp_tools_srv = new \More_MCP\MCP\Server();
    if (method_exists($more_mcp_tools_srv, 'get_all_tools')) {
        $more_mcp_tool_count = count($more_mcp_tools_srv->get_all_tools());
    }
}

// The negotiated MCP protocol version, surfaced on the Connection panel so an
// admin can see it without asking a connected client to call the health tool.
// Hardcoded to the value MCP\Server advertises in its initialize response; kept
// as a constant here rather than reaching into the server, since it is a single
// literal either side.
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
