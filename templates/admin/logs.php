<?php
if (!defined('ABSPATH')) {
    exit;
}

$more_mcp_logs = isset($logs) ? $logs : [];
$more_mcp_total_items = isset($total_items) ? $total_items : 0;
$more_mcp_per_page = isset($per_page) ? $per_page : 20;
$more_mcp_page = isset($page) ? $page : 1;
$more_mcp_total_pages = ceil($more_mcp_total_items / $more_mcp_per_page);
?>

<div class="wrap more-mcp-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="more-mcp-logs">
                <button type="submit" class="button"><?php esc_html_e('Refresh', 'more-mcp'); ?></button>
            </form>
        </div>
        <?php if ($more_mcp_total_pages > 1) : ?>
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php
                /* translators: %s: number of items */
                printf(esc_html(_n('%s item', '%s items', $more_mcp_total_items, 'more-mcp')), esc_html(number_format_i18n($more_mcp_total_items)));
                ?>
            </span>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $more_mcp_total_pages,
                'current' => $more_mcp_page,
            ]));
            ?>
        </div>
        <?php endif; ?>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-timestamp">
                    <?php esc_html_e('Timestamp', 'more-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-server">
                    <?php esc_html_e('MCP Server', 'more-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-action">
                    <?php esc_html_e('Action', 'more-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php esc_html_e('Status', 'more-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-details">
                    <?php esc_html_e('Details', 'more-mcp'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($more_mcp_logs)) : ?>
            <tr>
                <td colspan="5" class="no-items">
                    <?php esc_html_e('No activity logs found.', 'more-mcp'); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php foreach ($more_mcp_logs as $more_mcp_log) : ?>
                <tr>
                    <td class="column-timestamp">
                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $more_mcp_log->timestamp)); ?>
                    </td>
                    <td class="column-server">
                        <strong><?php echo esc_html($more_mcp_log->mcp_server); ?></strong>
                    </td>
                    <td class="column-action">
                        <code><?php echo esc_html($more_mcp_log->action); ?></code>
                    </td>
                    <td class="column-status">
                        <?php
                        $more_mcp_status_class = $more_mcp_log->status === 'success' ? 'success' : 'error';
                        $more_mcp_status_label = $more_mcp_log->status === 'success' ? esc_html__('Success', 'more-mcp') : esc_html__('Error', 'more-mcp');
                        ?>
                        <span class="status-badge status-<?php echo esc_attr($more_mcp_status_class); ?>">
                            <?php echo esc_html($more_mcp_status_label); ?>
                        </span>
                    </td>
                    <td class="column-details">
                        <button type="button"
                                class="button button-small view-log-details"
                                data-request="<?php echo esc_attr($more_mcp_log->request_data); ?>"
                                data-response="<?php echo esc_attr($more_mcp_log->response_data); ?>">
                            <?php esc_html_e('View Details', 'more-mcp'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($more_mcp_total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php
                /* translators: %s: number of items */
                printf(esc_html(_n('%s item', '%s items', $more_mcp_total_items, 'more-mcp')), esc_html(number_format_i18n($more_mcp_total_items)));
                ?>
            </span>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $more_mcp_total_pages,
                'current' => $more_mcp_page,
            ]));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal for log details -->
<div id="log-details-modal" class="log-modal">
    <div class="log-modal-content">
        <span class="log-modal-close">&times;</span>
        <h2><?php esc_html_e('Log Details', 'more-mcp'); ?></h2>
        <div class="log-details-container">
            <h3><?php esc_html_e('Request Data', 'more-mcp'); ?></h3>
            <pre id="log-request-data"></pre>
            <h3><?php esc_html_e('Response Data', 'more-mcp'); ?></h3>
            <pre id="log-response-data"></pre>
        </div>
    </div>
</div>
