<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_logs_base = add_query_arg( 'panel', 'logs', admin_url( 'admin.php?page=mordenhost-mcp' ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination argument.
$mordenhost_mcp_lpage = isset( $_GET['lpage'] ) ? max( 1, absint( $_GET['lpage'] ) ) : 1;

$mordenhost_mcp_logs          = isset( $logs ) && is_array( $logs ) ? $logs : array();
$mordenhost_mcp_total_items   = isset( $total_items ) ? (int) $total_items : 0;
if ( isset( $GLOBALS['mordenhost_mcp_test_logs'] ) && is_array( $GLOBALS['mordenhost_mcp_test_logs'] ) ) {
	$mordenhost_mcp_logs        = $GLOBALS['mordenhost_mcp_test_logs'];
	$mordenhost_mcp_total_items = isset( $GLOBALS['mordenhost_mcp_test_log_total'] ) ? (int) $GLOBALS['mordenhost_mcp_test_log_total'] : count( $mordenhost_mcp_logs );
}
$mordenhost_mcp_per_page      = isset( $per_page ) && (int) $per_page > 0 ? (int) $per_page : 20;
$mordenhost_mcp_total_pages   = max( 1, (int) ceil( $mordenhost_mcp_total_items / $mordenhost_mcp_per_page ) );
$mordenhost_mcp_undo_snaps    = isset( $undo_snapshots ) && is_array( $undo_snapshots ) ? $undo_snapshots : array();

if ( $mordenhost_mcp_lpage > $mordenhost_mcp_total_pages && $mordenhost_mcp_total_items > 0 ) {
	$mordenhost_mcp_lpage = $mordenhost_mcp_total_pages;
}
$mordenhost_mcp_loffset = ( $mordenhost_mcp_lpage - 1 ) * $mordenhost_mcp_per_page;
?>

<p class="mmcp-subtab-summary">
	<?php
	echo esc_html(
		__( 'Every authenticated request leaves a row: which tool ran, when, and whether it succeeded. Only argument names are stored, never values, so post content and credentials never land in this table.', 'mordenhost-mcp' )
	);
	?>
</p>

<?php

if ( class_exists( '\Mordenhost_MCP\MCP\Log_Store' ) ) {
	$mordenhost_mcp_retention_days = \Mordenhost_MCP\MCP\Log_Store::retention_days();
	$mordenhost_mcp_row_cap        = \Mordenhost_MCP\MCP\Log_Store::row_cap();
	if ( $mordenhost_mcp_retention_days > 0 ) {
		$mordenhost_mcp_days_label = sprintf(
			/* translators: %s: number of days */
			esc_html( _n( '%s day', '%s days', $mordenhost_mcp_retention_days, 'mordenhost-mcp' ) ),
			esc_html( number_format_i18n( $mordenhost_mcp_retention_days ) )
		);
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
	foreach ( array( 'log_retention_days', 'log_row_cap' ) as $mordenhost_mcp_log_pinned ) {
		echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_settings', $mordenhost_mcp_log_pinned ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note().
	}
}
?>

<?php if ( empty( $mordenhost_mcp_logs ) ) : ?>

	<div class="mmcp-empty">
		<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
		<h4><?php esc_html_e( 'No activity yet', 'mordenhost-mcp' ); ?></h4>
		<p>
			<?php esc_html_e( 'A row appears here as soon as any client sends its first request. Connect a client from the Connection panel to get started.', 'mordenhost-mcp' ); ?>
		</p>
	</div>

<?php else : ?>

	<table class="mmcp-table widefat striped" id="mordenhost-mcp-logs-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Timestamp', 'mordenhost-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'MCP Server', 'mordenhost-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Action', 'mordenhost-mcp' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'mordenhost-mcp' ); ?></th>
				<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Details', 'mordenhost-mcp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $mordenhost_mcp_logs as $mordenhost_mcp_log ) : ?>
			<tr>
				<td>
					<?php

					
					$mordenhost_mcp_log_df = get_option( 'date_format', '' );
					$mordenhost_mcp_log_tf = get_option( 'time_format', '' );
					$mordenhost_mcp_log_fmt = ( is_string( $mordenhost_mcp_log_df ) ? $mordenhost_mcp_log_df : '' ) . ' ' . ( is_string( $mordenhost_mcp_log_tf ) ? $mordenhost_mcp_log_tf : '' );
					
					echo esc_html( get_date_from_gmt( (string) $mordenhost_mcp_log->timestamp, trim( $mordenhost_mcp_log_fmt ) !== '' ? $mordenhost_mcp_log_fmt : 'Y-m-d H:i:s' ) );
					?>
				</td>
				<td>
					<strong><?php echo esc_html( $mordenhost_mcp_log->mcp_server ); ?></strong>
				</td>
				<td>
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
				<td>
					<?php
					if ( 'refused' === $mordenhost_mcp_log->status ) {
						$mordenhost_mcp_status_class = 'refused';
						$mordenhost_mcp_status_label = esc_html__( 'Refused', 'mordenhost-mcp' );
					} else {
						$mordenhost_mcp_status_class = $mordenhost_mcp_log->status === 'success' ? 'success' : 'error';
						$mordenhost_mcp_status_label = $mordenhost_mcp_log->status === 'success' ? esc_html__( 'Success', 'mordenhost-mcp' ) : esc_html__( 'Error', 'mordenhost-mcp' );
					}
					?>
					<span class="status-badge status-<?php echo esc_attr( $mordenhost_mcp_status_class ); ?>">
						<?php echo esc_html( $mordenhost_mcp_status_label ); ?>
					</span>
				</td>
				<td class="mmcp-col-action">
					<button type="button"
					        class="button button-small view-log-details"
					        data-request="<?php echo esc_attr( $mordenhost_mcp_log->request_data ); ?>"
					        data-response="<?php echo esc_attr( $mordenhost_mcp_log->response_data ); ?>">
						<?php esc_html_e( 'View Details', 'mordenhost-mcp' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $mordenhost_mcp_total_pages > 1 ) : ?>
	<div class="mmcp-pager">
		<span class="mmcp-pager-range">
			<?php
			printf(
				/* translators: 1: first row on this page, 2: last row on this page, 3: total rows */
				esc_html__( '%1$s–%2$s of %3$s', 'mordenhost-mcp' ),
				esc_html( number_format_i18n( $mordenhost_mcp_loffset + 1 ) ),
				esc_html( number_format_i18n( min( $mordenhost_mcp_loffset + $mordenhost_mcp_per_page, $mordenhost_mcp_total_items ) ) ),
				esc_html( number_format_i18n( $mordenhost_mcp_total_items ) )
			);
			?>
		</span>
		<span class="mmcp-pager-links">
			<?php if ( $mordenhost_mcp_lpage > 1 ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'lpage', $mordenhost_mcp_lpage - 1, $mordenhost_mcp_logs_base ) ); ?>">
					<?php esc_html_e( '‹ Previous', 'mordenhost-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( '‹ Previous', 'mordenhost-mcp' ); ?></span>
			<?php endif; ?>

			<span class="mmcp-pager-current">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$s of %2$s', 'mordenhost-mcp' ),
					esc_html( number_format_i18n( $mordenhost_mcp_lpage ) ),
					esc_html( number_format_i18n( $mordenhost_mcp_total_pages ) )
				);
				?>
			</span>

			<?php if ( $mordenhost_mcp_lpage < $mordenhost_mcp_total_pages ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'lpage', $mordenhost_mcp_lpage + 1, $mordenhost_mcp_logs_base ) ); ?>">
					<?php esc_html_e( 'Next ›', 'mordenhost-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( 'Next ›', 'mordenhost-mcp' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php endif; ?>

<?php endif; ?>

<?php

?>
<div class="mmcp-section">
	<h3><?php esc_html_e( 'Reversible operations', 'mordenhost-mcp' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Some MCP tools save a snapshot before a destructive write, so the change can be undone. These are the snapshots still available. Each expires 72 hours after it was created, and undoing one consumes it.', 'mordenhost-mcp' ); ?>
	</p>

	<?php if ( empty( $mordenhost_mcp_undo_snaps ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'No reversible operations are currently stored.', 'mordenhost-mcp' ); ?>
		</p>
	<?php else : ?>
		<table class="mmcp-table widefat striped mordenhost-mcp-undo-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Operation', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What it would restore', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'mordenhost-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'mordenhost-mcp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php

				$mordenhost_mcp_undo_df = get_option( 'date_format', '' );
				$mordenhost_mcp_undo_tf = get_option( 'time_format', '' );
				$mordenhost_mcp_datetime_fmt = ( is_string( $mordenhost_mcp_undo_df ) ? $mordenhost_mcp_undo_df : '' ) . ' ' . ( is_string( $mordenhost_mcp_undo_tf ) ? $mordenhost_mcp_undo_tf : '' );
				if ( '' === trim( $mordenhost_mcp_datetime_fmt ) ) {
					$mordenhost_mcp_datetime_fmt = 'Y-m-d H:i:s';
				}
				foreach ( $mordenhost_mcp_undo_snaps as $mordenhost_mcp_snap ) :

					
					$mordenhost_mcp_op         = (string) ( $mordenhost_mcp_snap['op'] ?? '' );
					$mordenhost_mcp_summary    = (string) ( $mordenhost_mcp_snap['summary'] ?? '' );
					$mordenhost_mcp_token      = (string) ( $mordenhost_mcp_snap['token'] ?? '' );
					$mordenhost_mcp_created_at = (int) ( $mordenhost_mcp_snap['created_at'] ?? 0 );
					$mordenhost_mcp_expires_at = (int) ( $mordenhost_mcp_snap['expires_at'] ?? 0 );
					?>
				<tr>
					<td><code><?php echo esc_html( $mordenhost_mcp_op ); ?></code></td>
					<td><?php echo esc_html( $mordenhost_mcp_summary !== '' ? $mordenhost_mcp_summary : esc_html__( '(no description recorded)', 'mordenhost-mcp' ) ); ?></td>
					<td>
						<?php echo $mordenhost_mcp_created_at > 0 ? esc_html( wp_date( $mordenhost_mcp_datetime_fmt, $mordenhost_mcp_created_at ) ) : '&mdash;'; ?>
					</td>
					<td>
						<?php
						if ( $mordenhost_mcp_expires_at > 0 ) {
							$mordenhost_mcp_remaining = $mordenhost_mcp_expires_at - time();
							echo esc_html( wp_date( $mordenhost_mcp_datetime_fmt, $mordenhost_mcp_expires_at ) );
							if ( $mordenhost_mcp_remaining > 0 ) {
								echo ' <span class="description">(' . esc_html( human_time_diff( time(), $mordenhost_mcp_expires_at ) ) . ')</span>';
							}
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td class="mmcp-col-action">
						<button type="button"
						        class="button button-small mordenhost-mcp-undo-run"
						        data-token="<?php echo esc_attr( $mordenhost_mcp_token ); ?>">
							<?php esc_html_e( 'Undo', 'mordenhost-mcp' ); ?>
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
		<h2><?php esc_html_e( 'Log Details', 'mordenhost-mcp' ); ?></h2>
		<div class="log-details-container">
			<h3><?php esc_html_e( 'Request Data', 'mordenhost-mcp' ); ?></h3>
			<pre id="log-request-data"></pre>
			<h3><?php esc_html_e( 'Response Data', 'mordenhost-mcp' ); ?></h3>
			<pre id="log-response-data"></pre>
		</div>
	</div>
</div>
