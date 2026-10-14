<?php
if (!defined('ABSPATH')) {
    exit;
}

$mordenhost_mcp_logs = isset($logs) ? $logs : [];
$mordenhost_mcp_total_items = isset($total_items) ? $total_items : 0;
$mordenhost_mcp_per_page = isset($per_page) ? $per_page : 20;
$mordenhost_mcp_page = isset($page) ? $page : 1;
$mordenhost_mcp_total_pages = ceil($mordenhost_mcp_total_items / $mordenhost_mcp_per_page);
?>

<div class="wrap mordenhost-mcp-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php

    
    if ( class_exists( '\Mordenhost_MCP\MCP\Log_Store' ) ) {
        $mordenhost_mcp_retention_days = \Mordenhost_MCP\MCP\Log_Store::retention_days();
        $mordenhost_mcp_row_cap        = \Mordenhost_MCP\MCP\Log_Store::row_cap();
        if ( $mordenhost_mcp_retention_days > 0 ) {
            /* translators: %s: number of days */
            $mordenhost_mcp_days_label = sprintf( esc_html( _n( '%s day', '%s days', $mordenhost_mcp_retention_days, 'mordenhost-mcp' ) ), esc_html( number_format_i18n( $mordenhost_mcp_retention_days ) ) );
            $mordenhost_mcp_retention_msg = sprintf(
                /* translators: %s: number of days */
                esc_html__( 'Entries older than %s are removed automatically by the daily cleanup.', 'mordenhost-mcp' ),
                $mordenhost_mcp_days_label
            );
        } else {
            $mordenhost_mcp_retention_msg = esc_html__( 'Log retention is unlimited: this table grows without bound until you set a retention period.', 'mordenhost-mcp' );
        }
        if ( $mordenhost_mcp_row_cap > 0 ) {
            $mordenhost_mcp_retention_msg .= ' ' . sprintf(
                /* translators: %s: maximum row count */
                esc_html__( 'A maximum of %s rows is kept, oldest removed first.', 'mordenhost-mcp' ),
                esc_html( number_format_i18n( $mordenhost_mcp_row_cap ) )
            );
        }
        echo '<p class="description mordenhost-mcp-log-retention">' . esc_html( $mordenhost_mcp_retention_msg ) . '</p>';

        

        
        
        $mordenhost_mcp_ret_choices = \Mordenhost_MCP\MCP\Log_Store::RETENTION_DAY_CHOICES;
        $mordenhost_mcp_cap_choices = \Mordenhost_MCP\MCP\Log_Store::ROW_CAP_CHOICES;
        if ( ! in_array( $mordenhost_mcp_retention_days, $mordenhost_mcp_ret_choices, true ) ) {
            $mordenhost_mcp_retention_days = \Mordenhost_MCP\MCP\Log_Store::DEFAULT_RETENTION_DAYS;
        }
        if ( ! in_array( $mordenhost_mcp_row_cap, $mordenhost_mcp_cap_choices, true ) ) {
            $mordenhost_mcp_row_cap = \Mordenhost_MCP\MCP\Log_Store::DEFAULT_ROW_CAP;
        }
        ?>
        <table class="form-table mordenhost-mcp-log-retention-controls">
            <tr>
                <th scope="row">
                    <label for="log_retention_days"><?php esc_html_e( 'Keep entries for', 'mordenhost-mcp' ); ?></label>
                </th>
                <td>
                    <select class="mmcp-log-retention-select" id="log_retention_days" data-current="<?php echo esc_attr( $mordenhost_mcp_retention_days ); ?>">
                        <?php foreach ( $mordenhost_mcp_ret_choices as $mordenhost_mcp_days ) : ?>
                            <?php if ( 0 === $mordenhost_mcp_days ) : ?>
                                <option value="0" <?php selected( $mordenhost_mcp_retention_days, 0 ); ?>>
                                    <?php esc_html_e( 'Unlimited (never delete by age)', 'mordenhost-mcp' ); ?>
                                </option>
                            <?php else : ?>
                                <option value="<?php echo esc_attr( $mordenhost_mcp_days ); ?>" <?php selected( $mordenhost_mcp_retention_days, $mordenhost_mcp_days ); ?>>
                                    <?php
                                    printf(
                                        /* translators: %s: number of days */
                                        esc_html( _n( '%s day', '%s days', $mordenhost_mcp_days, 'mordenhost-mcp' ) ),
                                        esc_html( number_format_i18n( $mordenhost_mcp_days ) )
                                    );
                                    ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="log_row_cap"><?php esc_html_e( 'Maximum rows kept', 'mordenhost-mcp' ); ?></label>
                </th>
                <td>
                    <select class="mmcp-log-retention-select" id="log_row_cap" data-current="<?php echo esc_attr( $mordenhost_mcp_row_cap ); ?>">
                        <?php foreach ( $mordenhost_mcp_cap_choices as $mordenhost_mcp_cap ) : ?>
                            <?php if ( 0 === $mordenhost_mcp_cap ) : ?>
                                <option value="0" <?php selected( $mordenhost_mcp_row_cap, 0 ); ?>>
                                    <?php esc_html_e( 'No cap', 'mordenhost-mcp' ); ?>
                                </option>
                            <?php else : ?>
                                <option value="<?php echo esc_attr( $mordenhost_mcp_cap ); ?>" <?php selected( $mordenhost_mcp_row_cap, $mordenhost_mcp_cap ); ?>>
                                    <?php echo esc_html( number_format_i18n( $mordenhost_mcp_cap ) ); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Enforced by the daily cleanup alongside the age limit: when both are set, whichever bound is hit first wins. Applies to future cleanups only; nothing is deleted the moment you save.', 'mordenhost-mcp' ); ?>
                    </p>
                </td>
            </tr>
        </table>
    <?php
    }
    ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="mordenhost-mcp-logs">
                <button type="submit" class="button"><?php esc_html_e('Refresh', 'mordenhost-mcp'); ?></button>
            </form>
        </div>
        <?php if ($mordenhost_mcp_total_pages > 1) : ?>
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php
                /* translators: %s: number of items */
                printf(esc_html(_n('%s item', '%s items', $mordenhost_mcp_total_items, 'mordenhost-mcp')), esc_html(number_format_i18n($mordenhost_mcp_total_items)));
                ?>
            </span>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $mordenhost_mcp_total_pages,
                'current' => $mordenhost_mcp_page,
            ]));
            ?>
        </div>
        <?php endif; ?>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-timestamp">
                    <?php esc_html_e('Timestamp', 'mordenhost-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-server">
                    <?php esc_html_e('MCP Server', 'mordenhost-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-action">
                    <?php esc_html_e('Action', 'mordenhost-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php esc_html_e('Status', 'mordenhost-mcp'); ?>
                </th>
                <th scope="col" class="manage-column column-details">
                    <?php esc_html_e('Details', 'mordenhost-mcp'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mordenhost_mcp_logs)) : ?>
            <tr>
                <td colspan="5" class="no-items">
                    <?php esc_html_e('No activity logs found.', 'mordenhost-mcp'); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php foreach ($mordenhost_mcp_logs as $mordenhost_mcp_log) : ?>
                <tr>
                    <td class="column-timestamp">
                        <?php echo esc_html(get_date_from_gmt((string) $mordenhost_mcp_log->timestamp, get_option('date_format') . ' ' . get_option('time_format'))); ?>
                    </td>
                    <td class="column-server">
                        <strong><?php echo esc_html($mordenhost_mcp_log->mcp_server); ?></strong>
                    </td>
                    <td class="column-action">
                        <?php

                        
                        
                        $mordenhost_mcp_action_label = class_exists( '\Mordenhost_MCP\MCP\Action_Labels' )
                            ? \Mordenhost_MCP\MCP\Action_Labels::label( (string) $mordenhost_mcp_log->action )
                            : '';
                        ?>
                        <?php if ( '' !== $mordenhost_mcp_action_label ) : ?>
                            <?php echo esc_html( $mordenhost_mcp_action_label ); ?>
                            <br><code class="mmcp-action-raw"><?php echo esc_html( $mordenhost_mcp_log->action ); ?></code>
                        <?php else : ?>
                            <code><?php echo esc_html( $mordenhost_mcp_log->action ); ?></code>
                        <?php endif; ?>
                    </td>
                    <td class="column-status">
                        <?php
                        if ($mordenhost_mcp_log->status === 'refused') {
                            $mordenhost_mcp_status_class = 'refused';
                            $mordenhost_mcp_status_label = esc_html__('Refused', 'mordenhost-mcp');
                        } else {
                            $mordenhost_mcp_status_class = $mordenhost_mcp_log->status === 'success' ? 'success' : 'error';
                            $mordenhost_mcp_status_label = $mordenhost_mcp_log->status === 'success' ? esc_html__('Success', 'mordenhost-mcp') : esc_html__('Error', 'mordenhost-mcp');
                        }
                        ?>
                        <span class="status-badge status-<?php echo esc_attr($mordenhost_mcp_status_class); ?>">
                            <?php echo esc_html($mordenhost_mcp_status_label); ?>
                        </span>
                    </td>
                    <td class="column-details">
                        <button type="button"
                                class="button button-small view-log-details"
                                data-request="<?php echo esc_attr($mordenhost_mcp_log->request_data); ?>"
                                data-response="<?php echo esc_attr($mordenhost_mcp_log->response_data); ?>">
                            <?php esc_html_e('View Details', 'mordenhost-mcp'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($mordenhost_mcp_total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php
                /* translators: %s: number of items */
                printf(esc_html(_n('%s item', '%s items', $mordenhost_mcp_total_items, 'mordenhost-mcp')), esc_html(number_format_i18n($mordenhost_mcp_total_items)));
                ?>
            </span>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $mordenhost_mcp_total_pages,
                'current' => $mordenhost_mcp_page,
            ]));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php

$mordenhost_mcp_undo_snapshots = isset($undo_snapshots) && is_array($undo_snapshots) ? $undo_snapshots : [];
?>
<div class="wrap mordenhost-mcp-undo-panel">
    <h2><?php esc_html_e('Reversible operations', 'mordenhost-mcp'); ?></h2>
    <p class="description">
        <?php esc_html_e('Some MCP tools save a snapshot before a destructive write, so the change can be undone. These are the snapshots still available. Each expires 72 hours after it was created, and undoing one consumes it.', 'mordenhost-mcp'); ?>
    </p>

    <table class="wp-list-table widefat fixed striped mordenhost-mcp-undo-table">
        <thead>
            <tr>
                <th scope="col" class="manage-column"><?php esc_html_e('Operation', 'mordenhost-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('What it would restore', 'mordenhost-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Created', 'mordenhost-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Expires', 'mordenhost-mcp'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Action', 'mordenhost-mcp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mordenhost_mcp_undo_snapshots)) : ?>
            <tr>
                <td colspan="5" class="no-items">
                    <?php esc_html_e('No reversible operations are currently stored.', 'mordenhost-mcp'); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php
                $mordenhost_mcp_datetime_fmt = get_option('date_format') . ' ' . get_option('time_format');
                foreach ($mordenhost_mcp_undo_snapshots as $mordenhost_mcp_snap) :

                    
                    $mordenhost_mcp_op         = (string) ($mordenhost_mcp_snap['op'] ?? '');
                    $mordenhost_mcp_summary    = (string) ($mordenhost_mcp_snap['summary'] ?? '');
                    $mordenhost_mcp_token      = (string) ($mordenhost_mcp_snap['token'] ?? '');
                    $mordenhost_mcp_created_at = (int) ($mordenhost_mcp_snap['created_at'] ?? 0);
                    $mordenhost_mcp_expires_at = (int) ($mordenhost_mcp_snap['expires_at'] ?? 0);
                    ?>
                <tr>
                    <td><code><?php echo esc_html($mordenhost_mcp_op); ?></code></td>
                    <td><?php echo esc_html($mordenhost_mcp_summary !== '' ? $mordenhost_mcp_summary : esc_html__('(no description recorded)', 'mordenhost-mcp')); ?></td>
                    <td>
                        <?php echo $mordenhost_mcp_created_at > 0 ? esc_html(wp_date($mordenhost_mcp_datetime_fmt, $mordenhost_mcp_created_at)) : '&mdash;'; ?>
                    </td>
                    <td>
                        <?php
                        if ($mordenhost_mcp_expires_at > 0) {
                            $mordenhost_mcp_remaining = $mordenhost_mcp_expires_at - time();
                            echo esc_html(wp_date($mordenhost_mcp_datetime_fmt, $mordenhost_mcp_expires_at));
                            if ($mordenhost_mcp_remaining > 0) {
                                echo ' <span class="description">(' . esc_html(human_time_diff(time(), $mordenhost_mcp_expires_at)) . ')</span>';
                            }
                        } else {
                            echo '&mdash;';
                        }
                        ?>
                    </td>
                    <td>
                        <button type="button"
                                class="button button-small mordenhost-mcp-undo-run"
                                data-token="<?php echo esc_attr($mordenhost_mcp_token); ?>">
                            <?php esc_html_e('Undo', 'mordenhost-mcp'); ?>
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
        <h2><?php esc_html_e('Log Details', 'mordenhost-mcp'); ?></h2>
        <div class="log-details-container">
            <h3><?php esc_html_e('Request Data', 'mordenhost-mcp'); ?></h3>
            <pre id="log-request-data"></pre>
            <h3><?php esc_html_e('Response Data', 'mordenhost-mcp'); ?></h3>
            <pre id="log-response-data"></pre>
        </div>
    </div>
</div>
