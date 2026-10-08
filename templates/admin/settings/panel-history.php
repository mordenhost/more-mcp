<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MORE_MCP_HISTORY_PER_PAGE' ) ) {
	define( 'MORE_MCP_HISTORY_PER_PAGE', 20 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters, validated against fixed lists below.
$more_mcp_hp_types = array(
	''           => __( 'Everything', 'mordenhost-mcp-server' ),
	'post'       => __( 'Posts and pages', 'mordenhost-mcp-server' ),
	'option'     => __( 'Settings (options)', 'mordenhost-mcp-server' ),
	'theme_mod'  => __( 'Theme settings', 'mordenhost-mcp-server' ),
	'custom_css' => __( 'Custom CSS', 'mordenhost-mcp-server' ),
	'term'       => __( 'Categories and tags', 'mordenhost-mcp-server' ),
	'comment'    => __( 'Comments', 'mordenhost-mcp-server' ),
	'user'       => __( 'Users', 'mordenhost-mcp-server' ),
);
$more_mcp_hp_type  = isset( $_GET['htype'] ) ? sanitize_key( wp_unslash( $_GET['htype'] ) ) : '';
$more_mcp_hp_type  = isset( $more_mcp_hp_types[ $more_mcp_hp_type ] ) ? $more_mcp_hp_type : '';
$more_mcp_hp_state = isset( $_GET['hstate'] ) ? sanitize_key( wp_unslash( $_GET['hstate'] ) ) : '';
$more_mcp_hp_state = in_array( $more_mcp_hp_state, array( 'applied', 'restored' ), true ) ? $more_mcp_hp_state : '';
$more_mcp_hp_page  = isset( $_GET['hpage'] ) ? max( 1, absint( $_GET['hpage'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$more_mcp_hp_result = \More_MCP\MCP\Change_History::query(
	array(
		'object_type' => $more_mcp_hp_type,
		'state'       => $more_mcp_hp_state,
		'limit'       => MORE_MCP_HISTORY_PER_PAGE,
		'offset'      => ( $more_mcp_hp_page - 1 ) * MORE_MCP_HISTORY_PER_PAGE,
	)
);
$more_mcp_hp_pages  = max( 1, (int) ceil( $more_mcp_hp_result['total'] / MORE_MCP_HISTORY_PER_PAGE ) );
$more_mcp_hp_base   = add_query_arg(
	array(
		'panel'  => 'history',
		'htype'  => $more_mcp_hp_type,
		'hstate' => $more_mcp_hp_state,
	),
	admin_url( 'admin.php?page=more-mcp' )
);
$more_mcp_hp_now    = time();
?>

<div class="mmcp-perm-group mmcp-history">

	<p class="mmcp-subtab-summary">
		<?php
		printf(
			/* translators: %d: days history is kept */
			esc_html__( 'A before-and-after record of what AI clients changed, kept for %d days. Restore puts an item back the way it was. If it was edited again since, you are asked before those later edits are discarded.', 'mordenhost-mcp-server' ),
			90
		);
		?>
	</p>

	<span class="mmcp-safety-status" role="status" aria-live="polite"></span>

	<form method="get" class="mmcp-table-toolbar">
		<input type="hidden" name="page" value="more-mcp">
		<input type="hidden" name="panel" value="history">
		<label class="screen-reader-text" for="mmcp-hp-type"><?php esc_html_e( 'Type of item', 'mordenhost-mcp-server' ); ?></label>
		<select name="htype" id="mmcp-hp-type">
			<?php foreach ( $more_mcp_hp_types as $more_mcp_hp_key => $more_mcp_hp_label ) : ?>
				<option value="<?php echo esc_attr( $more_mcp_hp_key ); ?>" <?php selected( $more_mcp_hp_type, $more_mcp_hp_key ); ?>><?php echo esc_html( $more_mcp_hp_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="screen-reader-text" for="mmcp-hp-state"><?php esc_html_e( 'State', 'mordenhost-mcp-server' ); ?></label>
		<select name="hstate" id="mmcp-hp-state">
			<option value=""><?php esc_html_e( 'Any state', 'mordenhost-mcp-server' ); ?></option>
			<option value="applied" <?php selected( $more_mcp_hp_state, 'applied' ); ?>><?php esc_html_e( 'Can be restored', 'mordenhost-mcp-server' ); ?></option>
			<option value="restored" <?php selected( $more_mcp_hp_state, 'restored' ); ?>><?php esc_html_e( 'Already restored', 'mordenhost-mcp-server' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mordenhost-mcp-server' ); ?></button>
		<span class="mmcp-table-count">
			<?php
			printf(
				/* translators: %s: number of history entries */
				esc_html( _n( '%s entry', '%s entries', $more_mcp_hp_result['total'], 'mordenhost-mcp-server' ) ),
				esc_html( number_format_i18n( $more_mcp_hp_result['total'] ) )
			);
			?>
		</span>
	</form>

	<?php if ( ! $more_mcp_hp_result['rows'] ) : ?>
		<p class="description"><?php esc_html_e( 'Nothing recorded yet. Changes appear here as soon as a connected client edits something.', 'mordenhost-mcp-server' ); ?></p>
	<?php else : ?>
		<table class="mmcp-table mmcp-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'Who', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'What', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'State', 'mordenhost-mcp-server' ); ?></th>
					<th class="mmcp-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'mordenhost-mcp-server' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $more_mcp_hp_result['rows'] as $more_mcp_hp_row ) :
					$more_mcp_hp_ts   = (int) strtotime( $more_mcp_hp_row['created_at'] . ' UTC' );
					$more_mcp_hp_user = get_userdata( (int) $more_mcp_hp_row['user_id'] );
					$more_mcp_hp_can  = \More_MCP\MCP\Change_History::can_access( $more_mcp_hp_row['object_type'], $more_mcp_hp_row['object_id'] );
					?>
					<tr data-entry="<?php echo esc_attr( $more_mcp_hp_row['id'] ); ?>">
						<td>
							<?php
							printf(
								/* translators: %s: human-readable time difference, e.g. "4 mins" */
								esc_html__( '%s ago', 'mordenhost-mcp-server' ),
								esc_html( human_time_diff( $more_mcp_hp_ts, $more_mcp_hp_now ) )
							);
							?>
							<br><span class="description"><?php echo esc_html( wp_date( 'Y-m-d H:i', $more_mcp_hp_ts ) ); ?></span>
						</td>
						<td>
							<?php echo esc_html( $more_mcp_hp_user ? $more_mcp_hp_user->user_login : '#' . (int) $more_mcp_hp_row['user_id'] ); ?>
							<?php if ( '' !== $more_mcp_hp_row['credential'] ) : ?>
								<br><span class="description"><?php echo esc_html( $more_mcp_hp_row['credential'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $more_mcp_hp_row['summary'] ); ?><br>
							<code><?php echo esc_html( $more_mcp_hp_row['tool'] ); ?></code>
						</td>
						<td>
							<span class="mmcp-pill mmcp-pill-<?php echo esc_attr( $more_mcp_hp_row['state'] ); ?>"><?php echo esc_html( 'applied' === $more_mcp_hp_row['state'] ? __( 'changed', 'mordenhost-mcp-server' ) : $more_mcp_hp_row['state'] ); ?></span>
						</td>
						<td class="mmcp-col-action">
							<?php if ( $more_mcp_hp_can ) : ?>
								<button type="button" class="button button-small mmcp-history-diff"><?php esc_html_e( 'What changed', 'mordenhost-mcp-server' ); ?></button>
								<?php if ( 'applied' === $more_mcp_hp_row['state'] ) : ?>
									<button type="button" class="button button-small mmcp-history-restore"><?php esc_html_e( 'Restore', 'mordenhost-mcp-server' ); ?></button>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $more_mcp_hp_pages > 1 ) : ?>
			<div class="mmcp-pager">
				<span class="mmcp-pager-links">
					<?php if ( $more_mcp_hp_page > 1 ) : ?>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'hpage', $more_mcp_hp_page - 1, $more_mcp_hp_base ) ); ?>"><?php esc_html_e( '‹ Previous', 'mordenhost-mcp-server' ); ?></a>
					<?php endif; ?>
					<span class="mmcp-pager-current">
						<?php
						printf(
							/* translators: 1: current page number, 2: total pages */
							esc_html__( 'Page %1$s of %2$s', 'mordenhost-mcp-server' ),
							esc_html( number_format_i18n( $more_mcp_hp_page ) ),
							esc_html( number_format_i18n( $more_mcp_hp_pages ) )
						);
						?>
					</span>
					<?php if ( $more_mcp_hp_page < $more_mcp_hp_pages ) : ?>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'hpage', $more_mcp_hp_page + 1, $more_mcp_hp_base ) ); ?>"><?php esc_html_e( 'Next ›', 'mordenhost-mcp-server' ); ?></a>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="mmcp-modal" id="mmcp-history-modal" hidden>
		<div class="mmcp-modal-card" role="dialog" aria-modal="true" aria-labelledby="mmcp-history-modal-title">
			<h3 id="mmcp-history-modal-title"><?php esc_html_e( 'What changed', 'mordenhost-mcp-server' ); ?></h3>
			<div class="mmcp-modal-body"></div>
			<p><button type="button" class="button" id="mmcp-history-modal-close"><?php esc_html_e( 'Close', 'mordenhost-mcp-server' ); ?></button></p>
		</div>
	</div>
</div>
