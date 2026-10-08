<?php

if (!defined('ABSPATH')) {
    exit;
}

$more_mcp_memory_entries = isset($more_mcp_memory_entries) ? (array) $more_mcp_memory_entries : [];
$more_mcp_memory_total   = isset($more_mcp_memory_total) ? (int) $more_mcp_memory_total : 0;
$more_mcp_memory_result  = isset($more_mcp_memory_result) ? (string) $more_mcp_memory_result : '';
$more_mcp_memory_max     = \More_MCP\Knowledge\Memory_Store::MAX_ENTRIES;

$more_mcp_memory_notices = [
    'deleted'        => ['success', __('Entry deleted.', 'mordenhost-mcp-server')],
    'deleted_all'    => ['success', __('All memory entries deleted.', 'mordenhost-mcp-server')],
    'confirm_needed' => ['warning', __('Nothing was deleted. Tick the confirmation box to delete every entry.', 'mordenhost-mcp-server')],
    'error'          => ['error', __('That entry could not be deleted. It may already be gone.', 'mordenhost-mcp-server')],
];
?>

<div class="wrap more-mcp-memory">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($more_mcp_memory_notices[$more_mcp_memory_result])) : ?>
        <div class="notice notice-<?php echo esc_attr($more_mcp_memory_notices[$more_mcp_memory_result][0]); ?> is-dismissible">
            <p><?php echo esc_html($more_mcp_memory_notices[$more_mcp_memory_result][1]); ?></p>
        </div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('Facts that AI agents connected to this site chose to remember: conventions, decisions, pitfalls. Every connected agent can read and change them through the memory tools. Review them here and delete anything that is wrong, outdated, or should not be shared.', 'mordenhost-mcp-server'); ?>
    </p>
    <p class="description">
        <?php
        printf(
            /* translators: 1: number of entries, 2: maximum number of entries */
            esc_html__('%1$s of %2$s entries used. Experimental: on because wp-config.php defines MORE_MCP_EXPERIMENTAL_MODE.', 'mordenhost-mcp-server'),
            esc_html(number_format_i18n($more_mcp_memory_total)),
            esc_html(number_format_i18n($more_mcp_memory_max))
        );
        ?>
    </p>

    <?php if (empty($more_mcp_memory_entries)) : ?>
        <p><em><?php esc_html_e('Memory is empty. Entries appear here when a connected agent saves one with memory_save.', 'mordenhost-mcp-server'); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Key', 'mordenhost-mcp-server'); ?></th>
                    <th scope="col"><?php esc_html_e('Entry', 'mordenhost-mcp-server'); ?></th>
                    <th scope="col"><?php esc_html_e('Type', 'mordenhost-mcp-server'); ?></th>
                    <th scope="col"><?php esc_html_e('Last saved', 'mordenhost-mcp-server'); ?></th>
                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'mordenhost-mcp-server'); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($more_mcp_memory_entries as $more_mcp_memory_entry) :
                    $more_mcp_memory_user = get_userdata((int) $more_mcp_memory_entry['updated_by']);
                    $more_mcp_memory_by   = $more_mcp_memory_user ? $more_mcp_memory_user->display_name : __('unknown user', 'mordenhost-mcp-server');
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($more_mcp_memory_entry['key']); ?></code></td>
                        <td>
                            <strong><?php echo esc_html($more_mcp_memory_entry['title']); ?></strong>
                            <div><?php echo esc_html($more_mcp_memory_entry['summary']); ?></div>
                            <details>
                                <summary><?php esc_html_e('Full text', 'mordenhost-mcp-server'); ?></summary>
                                <pre style="white-space:pre-wrap;max-width:70ch;"><?php echo esc_html($more_mcp_memory_entry['body']); ?></pre>
                            </details>
                        </td>
                        <td><?php echo esc_html($more_mcp_memory_entry['type']); ?></td>
                        <td>
                            <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) strtotime($more_mcp_memory_entry['updated_at']))); ?>
                            <div class="description">
                                <?php
                                /* translators: %s: user display name */
                                printf(esc_html__('by %s', 'mordenhost-mcp-server'), esc_html($more_mcp_memory_by));
                                ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="more_mcp_memory_delete">
                                <input type="hidden" name="key" value="<?php echo esc_attr($more_mcp_memory_entry['key']); ?>">
                                <?php wp_nonce_field('more_mcp_memory_delete_' . $more_mcp_memory_entry['key']); ?>
                                <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'mordenhost-mcp-server'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Delete all memory', 'mordenhost-mcp-server'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="more_mcp_memory_delete_all">
            <?php wp_nonce_field('more_mcp_memory_delete_all'); ?>
            <p>
                <label>
                    <input type="checkbox" name="confirm_delete_all" value="1">
                    <?php esc_html_e('I understand every entry will be deleted for good.', 'mordenhost-mcp-server'); ?>
                </label>
            </p>
            <p><button type="submit" class="button button-secondary"><?php esc_html_e('Delete all memory', 'mordenhost-mcp-server'); ?></button></p>
        </form>
    <?php endif; ?>
</div>
