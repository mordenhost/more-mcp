<?php

if (!defined('ABSPATH')) {
    exit;
}

$mordenhost_mcp_memory_entries = isset($mordenhost_mcp_memory_entries) ? (array) $mordenhost_mcp_memory_entries : [];
$mordenhost_mcp_memory_total   = isset($mordenhost_mcp_memory_total) ? (int) $mordenhost_mcp_memory_total : 0;
$mordenhost_mcp_memory_result  = isset($mordenhost_mcp_memory_result) ? (string) $mordenhost_mcp_memory_result : '';
$mordenhost_mcp_memory_max     = \Mordenhost_MCP\Knowledge\Memory_Store::MAX_ENTRIES;

$mordenhost_mcp_memory_notices = [
    'deleted'        => ['success', __('Entry deleted.', 'mordenhost-mcp')],
    'deleted_all'    => ['success', __('All memory entries deleted.', 'mordenhost-mcp')],
    'confirm_needed' => ['warning', __('Nothing was deleted. Tick the confirmation box to delete every entry.', 'mordenhost-mcp')],
    'error'          => ['error', __('That entry could not be deleted. It may already be gone.', 'mordenhost-mcp')],
];
?>

<div class="wrap mordenhost-mcp-memory">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($mordenhost_mcp_memory_notices[$mordenhost_mcp_memory_result])) : ?>
        <div class="notice notice-<?php echo esc_attr($mordenhost_mcp_memory_notices[$mordenhost_mcp_memory_result][0]); ?> is-dismissible">
            <p><?php echo esc_html($mordenhost_mcp_memory_notices[$mordenhost_mcp_memory_result][1]); ?></p>
        </div>
    <?php endif; ?>

    <p>
        <?php esc_html_e('Facts that AI agents connected to this site chose to remember: conventions, decisions, pitfalls. Every connected agent can read and change them through the memory tools. Review them here and delete anything that is wrong, outdated, or should not be shared.', 'mordenhost-mcp'); ?>
    </p>
    <p class="description">
        <?php
        printf(
            /* translators: 1: number of entries, 2: maximum number of entries */
            esc_html__('%1$s of %2$s entries used. Experimental: on because wp-config.php defines MORDENHOST_MCP_EXPERIMENTAL_MODE.', 'mordenhost-mcp'),
            esc_html(number_format_i18n($mordenhost_mcp_memory_total)),
            esc_html(number_format_i18n($mordenhost_mcp_memory_max))
        );
        ?>
    </p>

    <?php if (empty($mordenhost_mcp_memory_entries)) : ?>
        <p><em><?php esc_html_e('Memory is empty. Entries appear here when a connected agent saves one with memory_save.', 'mordenhost-mcp'); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Key', 'mordenhost-mcp'); ?></th>
                    <th scope="col"><?php esc_html_e('Entry', 'mordenhost-mcp'); ?></th>
                    <th scope="col"><?php esc_html_e('Type', 'mordenhost-mcp'); ?></th>
                    <th scope="col"><?php esc_html_e('Last saved', 'mordenhost-mcp'); ?></th>
                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'mordenhost-mcp'); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mordenhost_mcp_memory_entries as $mordenhost_mcp_memory_entry) :
                    $mordenhost_mcp_memory_user = get_userdata((int) $mordenhost_mcp_memory_entry['updated_by']);
                    $mordenhost_mcp_memory_by   = $mordenhost_mcp_memory_user ? $mordenhost_mcp_memory_user->display_name : __('unknown user', 'mordenhost-mcp');
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($mordenhost_mcp_memory_entry['key']); ?></code></td>
                        <td>
                            <strong><?php echo esc_html($mordenhost_mcp_memory_entry['title']); ?></strong>
                            <div><?php echo esc_html($mordenhost_mcp_memory_entry['summary']); ?></div>
                            <details>
                                <summary><?php esc_html_e('Full text', 'mordenhost-mcp'); ?></summary>
                                <pre style="white-space:pre-wrap;max-width:70ch;"><?php echo esc_html($mordenhost_mcp_memory_entry['body']); ?></pre>
                            </details>
                        </td>
                        <td><?php echo esc_html($mordenhost_mcp_memory_entry['type']); ?></td>
                        <td>
                            <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) strtotime($mordenhost_mcp_memory_entry['updated_at']))); ?>
                            <div class="description">
                                <?php
                                /* translators: %s: user display name */
                                printf(esc_html__('by %s', 'mordenhost-mcp'), esc_html($mordenhost_mcp_memory_by));
                                ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mordenhost_mcp_memory_delete">
                                <input type="hidden" name="key" value="<?php echo esc_attr($mordenhost_mcp_memory_entry['key']); ?>">
                                <?php wp_nonce_field('mordenhost_mcp_memory_delete_' . $mordenhost_mcp_memory_entry['key']); ?>
                                <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'mordenhost-mcp'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Delete all memory', 'mordenhost-mcp'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mordenhost_mcp_memory_delete_all">
            <?php wp_nonce_field('mordenhost_mcp_memory_delete_all'); ?>
            <p>
                <label>
                    <input type="checkbox" name="confirm_delete_all" value="1">
                    <?php esc_html_e('I understand every entry will be deleted for good.', 'mordenhost-mcp'); ?>
                </label>
            </p>
            <p><button type="submit" class="button button-secondary"><?php esc_html_e('Delete all memory', 'mordenhost-mcp'); ?></button></p>
        </form>
    <?php endif; ?>
</div>
