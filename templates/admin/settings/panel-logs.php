<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_logs_base = add_query_arg( 'panel', 'logs', admin_url( 'admin.php?page=more-mcp' ) );

$more_mcp_lpage = isset( $_GET['lpage'] ) ? max( 1, absint( $_GET['lpage'] ) ) : 1;

if ( isset( $GLOBALS['mm_test_logs'] ) && is_array( $GLOBALS['mm_test_logs'] ) ) {
	$logs        = $GLOBALS['mm_test_logs'];
	$total_items = isset( $GLOBALS['mm_test_log_total'] ) ? (int) $GLOBALS['mm_test_log_total'] : count( $logs );
}
$more_mcp_logs          = isset( $logs ) && is_array( $logs ) ? $logs : array();
$more_mcp_total_items   = isset( $total_items ) ? (int) $total_items : 0;
$more_mcp_per_page      = isset( $per_page ) && (int) $per_page > 0 ? (int) $per_page : 20;
$more_mcp_total_pages   = max( 1, (int) ceil( $more_mcp_total_items / $more_mcp_per_page ) );
$more_mcp_undo_snaps    = isset( $undo_snapshots ) && is_array( $undo_snapshots ) ? $undo_snapshots : array();

if ( $more_mcp_lpage > $more_mcp_total_pages && $more_mcp_total_items > 0 ) {
	$more_mcp_lpage = $more_mcp_total_pages;
}
$more_mcp_loffset = ( $more_mcp_lpage - 1 ) * $more_mcp_per_page;
?>

<p class="mmcp-subtab-summary">
	<?php
	echo esc_html(
		__( 'Every authenticated request leaves a row: which tool ran, when, and whether it succeeded. Only argument names are stored, never values, so post content and credentials never land in this table.', 'more-mcp' )
	);
	?>
</p>

<?php

if ( class_exists( '\More_MCP\MCP\Log_Store' ) ) {
	$more_mcp_retention_days = \More_MCP\MCP\Log_Store::retention_days();
	$more_mcp_row_cap        = \More_MCP\MCP\Log_Store::row_cap();
	if ( $more_mcp_retention_days > 0 ) {
		$more_mcp_days_label = sprintf(
			/* translators: %s: number of days */
			esc_html( _n( '%s day', '%s days', $more_mcp_retention_days, 'more-mcp' ) ),
			esc_html( number_format_i18n( $more_mcp_retention_days ) )
		);
		$more_mcp_retention_msg = sprintf(
			/* translators: %s: number of days */
			esc_html__( 'Entries older than %s are removed automatically by the daily cleanup.', 'more-mcp' ),
			$more_mcp_days_label
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

<?php if ( empty( $more_mcp_logs ) ) : ?>

	<div class="mmcp-empty">
		<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
		<h4><?php esc_html_e( 'No activity yet', 'more-mcp' ); ?></h4>
		<p>
			<?php esc_html_e( 'A row appears here as soon as any client sends its first request. Connect a client from the Connection panel to get started.', 'more-mcp' ); ?>
		</p>
	</div>

<?php else : ?>

	<table class="mmcp-table widefat striped" id="more-mcp-logs-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Timestamp', 'more-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'MCP Server', 'more-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Action', 'more-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'more-mcp' ); ?></th>
				<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Details', 'more-mcp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $more_mcp_logs as $more_mcp_log ) : ?>
			<tr>
				<td>
					<?php

					
					$more_mcp_log_df = get_option( 'date_format', '' );
					$more_mcp_log_tf = get_option( 'time_format', '' );
					$more_mcp_log_fmt = ( is_string( $more_mcp_log_df ) ? $more_mcp_log_df : '' ) . ' ' . ( is_string( $more_mcp_log_tf ) ? $more_mcp_log_tf : '' );
					echo esc_html( mysql2date( trim( $more_mcp_log_fmt ) !== '' ? $more_mcp_log_fmt : 'Y-m-d H:i:s', $more_mcp_log->timestamp ) );
					?>
				</td>
				<td>
					<strong><?php echo esc_html( $more_mcp_log->mcp_server ); ?></strong>
				</td>
				<td>
					<?php

					
					
					$more_mcp_action_label = class_exists( '\More_MCP\MCP\Action_Labels' )
						? \More_MCP\MCP\Action_Labels::label( (string) $more_mcp_log->action )
						: '';
					?>
					<?php if ( '' !== $more_mcp_action_label ) : ?>
						<?php echo esc_html( $more_mcp_action_label ); ?>
						<br><code class="mmcp-action-raw"><?php echo esc_html( $more_mcp_log->action ); ?></code>
					<?php else : ?>
						<code><?php echo esc_html( $more_mcp_log->action ); ?></code>
					<?php endif; ?>
				</td>
				<td>
					<?php
					$more_mcp_status_class = $more_mcp_log->status === 'success' ? 'success' : 'error';
					$more_mcp_status_label = $more_mcp_log->status === 'success' ? esc_html__( 'Success', 'more-mcp' ) : esc_html__( 'Error', 'more-mcp' );
					?>
					<span class="status-badge status-<?php echo esc_attr( $more_mcp_status_class ); ?>">
						<?php echo esc_html( $more_mcp_status_label ); ?>
					</span>
				</td>
				<td class="mmcp-col-action">
					<button type="button"
					        class="button button-small view-log-details"
					        data-request="<?php echo esc_attr( $more_mcp_log->request_data ); ?>"
					        data-response="<?php echo esc_attr( $more_mcp_log->response_data ); ?>">
						<?php esc_html_e( 'View Details', 'more-mcp' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $more_mcp_total_pages > 1 ) : ?>
	<div class="mmcp-pager">
		<span class="mmcp-pager-range">
			<?php
			printf(
				/* translators: 1: first row on this page, 2: last row on this page, 3: total rows */
				esc_html__( '%1$s–%2$s of %3$s', 'more-mcp' ),
				esc_html( number_format_i18n( $more_mcp_loffset + 1 ) ),
				esc_html( number_format_i18n( min( $more_mcp_loffset + $more_mcp_per_page, $more_mcp_total_items ) ) ),
				esc_html( number_format_i18n( $more_mcp_total_items ) )
			);
			?>
		</span>
		<span class="mmcp-pager-links">
			<?php if ( $more_mcp_lpage > 1 ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'lpage', $more_mcp_lpage - 1, $more_mcp_logs_base ) ); ?>">
					<?php esc_html_e( '‹ Previous', 'more-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( '‹ Previous', 'more-mcp' ); ?></span>
			<?php endif; ?>

			<span class="mmcp-pager-current">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$s of %2$s', 'more-mcp' ),
					esc_html( number_format_i18n( $more_mcp_lpage ) ),
					esc_html( number_format_i18n( $more_mcp_total_pages ) )
				);
				?>
			</span>

			<?php if ( $more_mcp_lpage < $more_mcp_total_pages ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'lpage', $more_mcp_lpage + 1, $more_mcp_logs_base ) ); ?>">
					<?php esc_html_e( 'Next ›', 'more-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( 'Next ›', 'more-mcp' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php endif; ?>

<?php endif; ?>

<?php

?>
<div class="mmcp-section">
	<h3><?php esc_html_e( 'Reversible operations', 'more-mcp' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Some MCP tools save a snapshot before a destructive write, so the change can be undone. These are the snapshots still available. Each expires 72 hours after it was created, and undoing one consumes it.', 'more-mcp' ); ?>
	</p>

	<?php if ( empty( $more_mcp_undo_snaps ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'No reversible operations are currently stored.', 'more-mcp' ); ?>
		</p>
	<?php else : ?>
		<table class="mmcp-table widefat striped more-mcp-undo-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Operation', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What it would restore', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'more-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'more-mcp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php

				$more_mcp_undo_df = get_option( 'date_format', '' );
				$more_mcp_undo_tf = get_option( 'time_format', '' );
				$more_mcp_datetime_fmt = ( is_string( $more_mcp_undo_df ) ? $more_mcp_undo_df : '' ) . ' ' . ( is_string( $more_mcp_undo_tf ) ? $more_mcp_undo_tf : '' );
				if ( '' === trim( $more_mcp_datetime_fmt ) ) {
					$more_mcp_datetime_fmt = 'Y-m-d H:i:s';
				}
				foreach ( $more_mcp_undo_snaps as $more_mcp_snap ) :

					
					$more_mcp_op         = (string) ( $more_mcp_snap['op'] ?? '' );
					$more_mcp_summary    = (string) ( $more_mcp_snap['summary'] ?? '' );
					$more_mcp_token      = (string) ( $more_mcp_snap['token'] ?? '' );
					$more_mcp_created_at = (int) ( $more_mcp_snap['created_at'] ?? 0 );
					$more_mcp_expires_at = (int) ( $more_mcp_snap['expires_at'] ?? 0 );
					?>
				<tr>
					<td><code><?php echo esc_html( $more_mcp_op ); ?></code></td>
					<td><?php echo esc_html( $more_mcp_summary !== '' ? $more_mcp_summary : esc_html__( '(no description recorded)', 'more-mcp' ) ); ?></td>
					<td>
						<?php echo $more_mcp_created_at > 0 ? esc_html( wp_date( $more_mcp_datetime_fmt, $more_mcp_created_at ) ) : '&mdash;'; ?>
					</td>
					<td>
						<?php
						if ( $more_mcp_expires_at > 0 ) {
							$more_mcp_remaining = $more_mcp_expires_at - time();
							echo esc_html( wp_date( $more_mcp_datetime_fmt, $more_mcp_expires_at ) );
							if ( $more_mcp_remaining > 0 ) {
								echo ' <span class="description">(' . esc_html( human_time_diff( time(), $more_mcp_expires_at ) ) . ')</span>';
							}
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td class="mmcp-col-action">
						<button type="button"
						        class="button button-small more-mcp-undo-run"
						        data-token="<?php echo esc_attr( $more_mcp_token ); ?>">
							<?php esc_html_e( 'Undo', 'more-mcp' ); ?>
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Modal for log details -->
<div id="log-details-modal" class="log-modal">
	<div class="log-modal-content">
		<span class="log-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Log Details', 'more-mcp' ); ?></h2>
		<div class="log-details-container">
			<h3><?php esc_html_e( 'Request Data', 'more-mcp' ); ?></h3>
			<pre id="log-request-data"></pre>
			<h3><?php esc_html_e( 'Response Data', 'more-mcp' ); ?></h3>
			<pre id="log-response-data"></pre>
		</div>
	</div>
</div>
