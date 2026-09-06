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

    <?php

    
    if ( class_exists( '\More_MCP\MCP\Log_Store' ) ) {
        $more_mcp_retention_days = \More_MCP\MCP\Log_Store::retention_days();
        $more_mcp_row_cap        = \More_MCP\MCP\Log_Store::row_cap();
        if ( $more_mcp_retention_days > 0 ) {
            $more_mcp_retention_msg = sprintf(
                /* translators: %s: number of days */
                esc_html__( 'Entries older than %s are removed automatically by the daily cleanup.', 'more-mcp' ),
                sprintf( esc_html( _n( '%s day', '%s days', $more_mcp_retention_days, 'more-mcp' ) ), esc_html( number_format_i18n( $more_mcp_retention_days ) ) )
            );
        } else {
            $more_mcp_retention_msg = esc_html__( 'Log retention is unlimited: this table grows without bound until you set a retention period.', 'more-mcp' );
        }
        if ( $more_mcp_row_cap > 0 ) {
            $more_mcp_retention_msg .= ' ' . sprintf(
                /* translators: %s: maximum row count */
                esc_html__( 'A maximum of %s rows is kept, oldest removed first.', 'more-mcp' ),
                esc_html( number_format_i18n( $more_mcp_row_cap ) )
            );
        }
        echo '<p class="description more-mcp-log-retention">' . esc_html( $more_mcp_retention_msg ) . '</p>';
    }
    ?>

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

<?php

$more_mcp_undo_snapshots = isset($undo_snapshots) && is_array($undo_snapshots) ? $undo_snapshots : [];
?>
<div class="wrap more-mcp-undo-panel">
    <h2><?php esc_html_e('Reversible operations', 'more-mcp'); ?></h2>
    <p class="description">
        <?php esc_html_e('Some MCP tools save a snapshot before a destructive write, so the change can be undone. These are the snapshots still available. Each expires 72 hours after it was created, and undoing one consumes it.', 'more-mcp'); ?>
    </p>

    <table class="wp-list-table widefat fixed striped more-mcp-undo-table">
        <thead>
            <tr>
                <th scope="col" class="manage-column"><?php esc_html_e('Operation', 'more-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('What it would restore', 'more-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Created', 'more-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Expires', 'more-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Action', 'more-mcp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($more_mcp_undo_snapshots)) : ?>
            <tr>
                <td colspan="5" class="no-items">
                    <?php esc_html_e('No reversible operations are currently stored.', 'more-mcp'); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php
                $more_mcp_datetime_fmt = get_option('date_format') . ' ' . get_option('time_format');
                foreach ($more_mcp_undo_snapshots as $more_mcp_snap) :

                    
                    $more_mcp_op         = (string) ($more_mcp_snap['op'] ?? '');
                    $more_mcp_summary    = (string) ($more_mcp_snap['summary'] ?? '');
                    $more_mcp_token      = (string) ($more_mcp_snap['token'] ?? '');
                    $more_mcp_created_at = (int) ($more_mcp_snap['created_at'] ?? 0);
                    $more_mcp_expires_at = (int) ($more_mcp_snap['expires_at'] ?? 0);
                    ?>
                <tr>
                    <td><code><?php echo esc_html($more_mcp_op); ?></code></td>
                    <td><?php echo esc_html($more_mcp_summary !== '' ? $more_mcp_summary : esc_html__('(no description recorded)', 'more-mcp')); ?></td>
                    <td>
                        <?php echo $more_mcp_created_at > 0 ? esc_html(wp_date($more_mcp_datetime_fmt, $more_mcp_created_at)) : '&mdash;'; ?>
                    </td>
                    <td>
                        <?php
                        if ($more_mcp_expires_at > 0) {
                            $more_mcp_remaining = $more_mcp_expires_at - time();
                            echo esc_html(wp_date($more_mcp_datetime_fmt, $more_mcp_expires_at));
                            if ($more_mcp_remaining > 0) {
                                echo ' <span class="description">(' . esc_html(human_time_diff(time(), $more_mcp_expires_at)) . ')</span>';
                            }
                        } else {
                            echo '&mdash;';
                        }
                        ?>
                    </td>
                    <td>
                        <button type="button"
                                class="button button-small more-mcp-undo-run"
                                data-token="<?php echo esc_attr($more_mcp_token); ?>">
                            <?php esc_html_e('Undo', 'more-mcp'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
