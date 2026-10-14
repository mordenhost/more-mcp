<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MORDENHOST_MCP_HISTORY_PER_PAGE' ) ) {
	define( 'MORDENHOST_MCP_HISTORY_PER_PAGE', 20 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters, validated against fixed lists below.
$mordenhost_mcp_hp_types = array(
	''           => __( 'Everything', 'mordenhost-mcp' ),
	'post'       => __( 'Posts and pages', 'mordenhost-mcp' ),
	'option'     => __( 'Settings (options)', 'mordenhost-mcp' ),
	'theme_mod'  => __( 'Theme settings', 'mordenhost-mcp' ),
	'custom_css' => __( 'Custom CSS', 'mordenhost-mcp' ),
	'term'       => __( 'Categories and tags', 'mordenhost-mcp' ),
	'comment'    => __( 'Comments', 'mordenhost-mcp' ),
	'user'       => __( 'Users', 'mordenhost-mcp' ),
);
$mordenhost_mcp_hp_type  = isset( $_GET['htype'] ) ? sanitize_key( wp_unslash( $_GET['htype'] ) ) : '';
$mordenhost_mcp_hp_type  = isset( $mordenhost_mcp_hp_types[ $mordenhost_mcp_hp_type ] ) ? $mordenhost_mcp_hp_type : '';
$mordenhost_mcp_hp_state = isset( $_GET['hstate'] ) ? sanitize_key( wp_unslash( $_GET['hstate'] ) ) : '';
$mordenhost_mcp_hp_state = in_array( $mordenhost_mcp_hp_state, array( 'applied', 'restored' ), true ) ? $mordenhost_mcp_hp_state : '';
$mordenhost_mcp_hp_page  = isset( $_GET['hpage'] ) ? max( 1, absint( $_GET['hpage'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$mordenhost_mcp_hp_result = \Mordenhost_MCP\MCP\Change_History::query(
	array(
		'object_type' => $mordenhost_mcp_hp_type,
		'state'       => $mordenhost_mcp_hp_state,
		'limit'       => MORDENHOST_MCP_HISTORY_PER_PAGE,
		'offset'      => ( $mordenhost_mcp_hp_page - 1 ) * MORDENHOST_MCP_HISTORY_PER_PAGE,
	)
);
$mordenhost_mcp_hp_pages  = max( 1, (int) ceil( $mordenhost_mcp_hp_result['total'] / MORDENHOST_MCP_HISTORY_PER_PAGE ) );
$mordenhost_mcp_hp_base   = add_query_arg(
	array(
		'panel'  => 'history',
		'htype'  => $mordenhost_mcp_hp_type,
		'hstate' => $mordenhost_mcp_hp_state,
	),
	admin_url( 'admin.php?page=mordenhost-mcp' )
);
$mordenhost_mcp_hp_now    = time();
?>

<div class="mmcp-perm-group mmcp-history">

	<p class="mmcp-subtab-summary">
		<?php
		printf(
			/* translators: %d: days history is kept */
			esc_html__( 'A before-and-after record of what AI clients changed, kept for %d days. Restore puts an item back the way it was. If it was edited again since, you are asked before those later edits are discarded.', 'mordenhost-mcp' ),
			90
		);
		?>
	</p>

	<span class="mmcp-safety-status" role="status" aria-live="polite"></span>

	<form method="get" class="mmcp-table-toolbar">
		<input type="hidden" name="page" value="mordenhost-mcp">
		<input type="hidden" name="panel" value="history">
		<label class="screen-reader-text" for="mmcp-hp-type"><?php esc_html_e( 'Type of item', 'mordenhost-mcp' ); ?></label>
		<select name="htype" id="mmcp-hp-type">
			<?php foreach ( $mordenhost_mcp_hp_types as $mordenhost_mcp_hp_key => $mordenhost_mcp_hp_label ) : ?>
				<option value="<?php echo esc_attr( $mordenhost_mcp_hp_key ); ?>" <?php selected( $mordenhost_mcp_hp_type, $mordenhost_mcp_hp_key ); ?>><?php echo esc_html( $mordenhost_mcp_hp_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<label class="screen-reader-text" for="mmcp-hp-state"><?php esc_html_e( 'State', 'mordenhost-mcp' ); ?></label>
		<select name="hstate" id="mmcp-hp-state">
			<option value=""><?php esc_html_e( 'Any state', 'mordenhost-mcp' ); ?></option>
			<option value="applied" <?php selected( $mordenhost_mcp_hp_state, 'applied' ); ?>><?php esc_html_e( 'Can be restored', 'mordenhost-mcp' ); ?></option>
			<option value="restored" <?php selected( $mordenhost_mcp_hp_state, 'restored' ); ?>><?php esc_html_e( 'Already restored', 'mordenhost-mcp' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mordenhost-mcp' ); ?></button>
		<span class="mmcp-table-count">
			<?php
			printf(
				/* translators: %s: number of history entries */
				esc_html( _n( '%s entry', '%s entries', $mordenhost_mcp_hp_result['total'], 'mordenhost-mcp' ) ),
				esc_html( number_format_i18n( $mordenhost_mcp_hp_result['total'] ) )
			);
			?>
		</span>
	</form>

	<?php if ( ! $mordenhost_mcp_hp_result['rows'] ) : ?>
		<p class="description"><?php esc_html_e( 'Nothing recorded yet. Changes appear here as soon as a connected client edits something.', 'mordenhost-mcp' ); ?></p>
	<?php else : ?>
		<table class="mmcp-table mmcp-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'mordenhost-mcp' ); ?></th>
					<th><?php esc_html_e( 'Who', 'mordenhost-mcp' ); ?></th>
					<th><?php esc_html_e( 'What', 'mordenhost-mcp' ); ?></th>
					<th><?php esc_html_e( 'State', 'mordenhost-mcp' ); ?></th>
					<th class="mmcp-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'mordenhost-mcp' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $mordenhost_mcp_hp_result['rows'] as $mordenhost_mcp_hp_row ) :
					$mordenhost_mcp_hp_ts   = (int) strtotime( $mordenhost_mcp_hp_row['created_at'] . ' UTC' );
					$mordenhost_mcp_hp_user = get_userdata( (int) $mordenhost_mcp_hp_row['user_id'] );
					$mordenhost_mcp_hp_can  = \Mordenhost_MCP\MCP\Change_History::can_access( $mordenhost_mcp_hp_row['object_type'], $mordenhost_mcp_hp_row['object_id'] );
					?>
					<tr data-entry="<?php echo esc_attr( $mordenhost_mcp_hp_row['id'] ); ?>">
						<td>
							<?php
							printf(
								/* translators: %s: human-readable time difference, e.g. "4 mins" */
								esc_html__( '%s ago', 'mordenhost-mcp' ),
								esc_html( human_time_diff( $mordenhost_mcp_hp_ts, $mordenhost_mcp_hp_now ) )
							);
							?>
							<br><span class="description"><?php echo esc_html( wp_date( 'Y-m-d H:i', $mordenhost_mcp_hp_ts ) ); ?></span>
						</td>
						<td>
							<?php echo esc_html( $mordenhost_mcp_hp_user ? $mordenhost_mcp_hp_user->user_login : '#' . (int) $mordenhost_mcp_hp_row['user_id'] ); ?>
							<?php if ( '' !== $mordenhost_mcp_hp_row['credential'] ) : ?>
								<br><span class="description"><?php echo esc_html( $mordenhost_mcp_hp_row['credential'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $mordenhost_mcp_hp_row['summary'] ); ?><br>
							<code><?php echo esc_html( $mordenhost_mcp_hp_row['tool'] ); ?></code>
						</td>
						<td>
							<span class="mmcp-pill mmcp-pill-<?php echo esc_attr( $mordenhost_mcp_hp_row['state'] ); ?>"><?php echo esc_html( 'applied' === $mordenhost_mcp_hp_row['state'] ? __( 'changed', 'mordenhost-mcp' ) : $mordenhost_mcp_hp_row['state'] ); ?></span>
						</td>
						<td class="mmcp-col-action">
							<?php if ( $mordenhost_mcp_hp_can ) : ?>
								<button type="button" class="button button-small mmcp-history-diff"><?php esc_html_e( 'What changed', 'mordenhost-mcp' ); ?></button>
								<?php if ( 'applied' === $mordenhost_mcp_hp_row['state'] ) : ?>
									<button type="button" class="button button-small mmcp-history-restore"><?php esc_html_e( 'Restore', 'mordenhost-mcp' ); ?></button>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $mordenhost_mcp_hp_pages > 1 ) : ?>
			<div class="mmcp-pager">
				<span class="mmcp-pager-links">
					<?php if ( $mordenhost_mcp_hp_page > 1 ) : ?>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'hpage', $mordenhost_mcp_hp_page - 1, $mordenhost_mcp_hp_base ) ); ?>"><?php esc_html_e( '‹ Previous', 'mordenhost-mcp' ); ?></a>
					<?php endif; ?>
					<span class="mmcp-pager-current">
						<?php
						printf(
							/* translators: 1: current page number, 2: total pages */
							esc_html__( 'Page %1$s of %2$s', 'mordenhost-mcp' ),
							esc_html( number_format_i18n( $mordenhost_mcp_hp_page ) ),
							esc_html( number_format_i18n( $mordenhost_mcp_hp_pages ) )
						);
						?>
					</span>
					<?php if ( $mordenhost_mcp_hp_page < $mordenhost_mcp_hp_pages ) : ?>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'hpage', $mordenhost_mcp_hp_page + 1, $mordenhost_mcp_hp_base ) ); ?>"><?php esc_html_e( 'Next ›', 'mordenhost-mcp' ); ?></a>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="mmcp-modal" id="mmcp-history-modal" hidden>
		<div class="mmcp-modal-card" role="dialog" aria-modal="true" aria-labelledby="mmcp-history-modal-title">
			<h3 id="mmcp-history-modal-title"><?php esc_html_e( 'What changed', 'mordenhost-mcp' ); ?></h3>
			<div class="mmcp-modal-body"></div>
			<p><button type="button" class="button" id="mmcp-history-modal-close"><?php esc_html_e( 'Close', 'mordenhost-mcp' ); ?></button></p>
		</div>
	</div>
</div>
