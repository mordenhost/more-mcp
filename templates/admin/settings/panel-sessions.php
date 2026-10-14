<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MORDENHOST_MCP_SESSIONS_PER_PAGE' ) ) {
	define( 'MORDENHOST_MCP_SESSIONS_PER_PAGE', 20 );
}

$mordenhost_mcp_sessions_base = add_query_arg( 'panel', 'sessions', admin_url( 'admin.php?page=mordenhost-mcp' ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pager, cast to a bounded int below.
$mordenhost_mcp_page   = isset( $_GET['spage'] ) ? max( 1, absint( $_GET['spage'] ) ) : 1;
$mordenhost_mcp_offset = ( $mordenhost_mcp_page - 1 ) * MORDENHOST_MCP_SESSIONS_PER_PAGE;

$mordenhost_mcp_has_token_store   = class_exists( '\Mordenhost_MCP\OAuth\Token_Store' ) && method_exists( '\Mordenhost_MCP\OAuth\Token_Store', 'list_active_grants' );
$mordenhost_mcp_has_session_store = class_exists( '\Mordenhost_MCP\MCP\Session_Store' ) && method_exists( '\Mordenhost_MCP\MCP\Session_Store', 'list_sessions' );

$mordenhost_mcp_grant_total   = $mordenhost_mcp_grant_count;
$mordenhost_mcp_session_total = $mordenhost_mcp_session_count;

$mordenhost_mcp_grants   = [];
$mordenhost_mcp_sessions = [];

if ( 'clients' === $mordenhost_mcp_session_view && $mordenhost_mcp_has_token_store ) {
	$mordenhost_mcp_grants = \Mordenhost_MCP\OAuth\Token_Store::list_active_grants( MORDENHOST_MCP_SESSIONS_PER_PAGE, $mordenhost_mcp_offset );
} elseif ( 'transport' === $mordenhost_mcp_session_view && $mordenhost_mcp_has_session_store ) {
	$mordenhost_mcp_sessions = \Mordenhost_MCP\MCP\Session_Store::list_sessions( MORDENHOST_MCP_SESSIONS_PER_PAGE, $mordenhost_mcp_offset );
}

$mordenhost_mcp_active_total = ( 'clients' === $mordenhost_mcp_session_view ) ? $mordenhost_mcp_grant_total : $mordenhost_mcp_session_total;
$mordenhost_mcp_total_pages  = max( 1, (int) ceil( $mordenhost_mcp_active_total / MORDENHOST_MCP_SESSIONS_PER_PAGE ) );

if ( $mordenhost_mcp_page > $mordenhost_mcp_total_pages && $mordenhost_mcp_active_total > 0 ) {
	$mordenhost_mcp_page   = $mordenhost_mcp_total_pages;
	$mordenhost_mcp_offset = ( $mordenhost_mcp_page - 1 ) * MORDENHOST_MCP_SESSIONS_PER_PAGE;
	if ( 'clients' === $mordenhost_mcp_session_view && $mordenhost_mcp_has_token_store ) {
		$mordenhost_mcp_grants = \Mordenhost_MCP\OAuth\Token_Store::list_active_grants( MORDENHOST_MCP_SESSIONS_PER_PAGE, $mordenhost_mcp_offset );
	} elseif ( 'transport' === $mordenhost_mcp_session_view && $mordenhost_mcp_has_session_store ) {
		$mordenhost_mcp_sessions = \Mordenhost_MCP\MCP\Session_Store::list_sessions( MORDENHOST_MCP_SESSIONS_PER_PAGE, $mordenhost_mcp_offset );
	}
}

$mordenhost_mcp_when = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'mordenhost-mcp' ),
			'absolute' => '',
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "3 mins" */
			__( '%s ago', 'mordenhost-mcp' ),
			human_time_diff( $ts, time() )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

$mordenhost_mcp_until = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'mordenhost-mcp' ),
			'absolute' => '',
		];
	}
	if ( $ts <= time() ) {
		return [
			'relative' => __( 'expired', 'mordenhost-mcp' ),
			'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "4 hours" */
			__( 'in %s', 'mordenhost-mcp' ),
			human_time_diff( time(), $ts )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

$mordenhost_mcp_pager = function () use ( $mordenhost_mcp_page, $mordenhost_mcp_total_pages, $mordenhost_mcp_active_total, $mordenhost_mcp_session_view, $mordenhost_mcp_sessions_base, $mordenhost_mcp_offset ) {
	if ( $mordenhost_mcp_total_pages < 2 ) {
		return;
	}

	$tab_url = add_query_arg( 'view', $mordenhost_mcp_session_view, $mordenhost_mcp_sessions_base );
	$first   = $mordenhost_mcp_offset + 1;
	$last    = min( $mordenhost_mcp_offset + MORDENHOST_MCP_SESSIONS_PER_PAGE, $mordenhost_mcp_active_total );
	?>
	<div class="mmcp-pager">
		<span class="mmcp-pager-range">
			<?php
			printf(
				/* translators: 1: first row on this page, 2: last row on this page, 3: total rows */
				esc_html__( '%1$s–%2$s of %3$s', 'mordenhost-mcp' ),
				esc_html( number_format_i18n( $first ) ),
				esc_html( number_format_i18n( $last ) ),
				esc_html( number_format_i18n( $mordenhost_mcp_active_total ) )
			);
			?>
		</span>
		<span class="mmcp-pager-links">
			<?php if ( $mordenhost_mcp_page > 1 ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $mordenhost_mcp_page - 1, $tab_url ) ); ?>">
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
					esc_html( number_format_i18n( $mordenhost_mcp_page ) ),
					esc_html( number_format_i18n( $mordenhost_mcp_total_pages ) )
				);
				?>
			</span>

			<?php if ( $mordenhost_mcp_page < $mordenhost_mcp_total_pages ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $mordenhost_mcp_page + 1, $tab_url ) ); ?>">
					<?php esc_html_e( 'Next ›', 'mordenhost-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( 'Next ›', 'mordenhost-mcp' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php
};

$mordenhost_mcp_session_tabs = [
	'clients'   => [
		'label' => __( 'Connected clients', 'mordenhost-mcp' ),
		'count' => $mordenhost_mcp_grant_total,
	],
	'transport' => [
		'label' => __( 'Transport sessions', 'mordenhost-mcp' ),
		'count' => $mordenhost_mcp_session_total,
	],
];
?>

<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Session views', 'mordenhost-mcp' ); ?>">
	<?php foreach ( $mordenhost_mcp_session_tabs as $mordenhost_mcp_tab_slug => $mordenhost_mcp_tab ) : ?>
		<?php $mordenhost_mcp_tab_active = ( $mordenhost_mcp_tab_slug === $mordenhost_mcp_session_view ); ?>
		<a href="<?php echo esc_url( add_query_arg( 'view', $mordenhost_mcp_tab_slug, $mordenhost_mcp_sessions_base ) ); ?>"
		   class="mmcp-subtab<?php echo $mordenhost_mcp_tab_active ? ' is-active' : ''; ?>"
		   <?php echo $mordenhost_mcp_tab_active ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( $mordenhost_mcp_tab['label'] ); ?>
			<span class="mmcp-subtab-count"><?php echo esc_html( number_format_i18n( $mordenhost_mcp_tab['count'] ) ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( 'clients' === $mordenhost_mcp_session_view ) : ?>

	<!-- ============================================================
	     Sub-tab: Connected clients (OAuth grants)
	     ============================================================ -->
	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'One row per AI client that has authorized through OAuth. Each acts as the WordPress user shown, with exactly that user\'s capabilities. Disconnecting a client revokes its tokens, so it must authorize again before it can call this site.', 'mordenhost-mcp' ); ?>
	</p>

	<?php if ( empty( $mordenhost_mcp_grants ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No clients are connected via OAuth', 'mordenhost-mcp' ); ?></h4>
			<p>
				<?php esc_html_e( 'Claude.ai and ChatGPT appear here once they complete the OAuth handshake. Clients that authenticate with the API key instead, such as Claude Desktop, Cursor, and raw REST calls, hold no OAuth grant and never appear in this list. Look under Transport sessions for those.', 'mordenhost-mcp' ); ?>
			</p>
		</div>

	<?php else : ?>

		<table class="mmcp-table widefat striped" id="mordenhost-mcp-grants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Client', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Acting as', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Connected', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last activity', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Access', 'mordenhost-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'mordenhost-mcp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $mordenhost_mcp_grants as $mordenhost_mcp_grant ) :
					$mordenhost_mcp_user      = get_userdata( (int) $mordenhost_mcp_grant['user_id'] );
					$mordenhost_mcp_first     = $mordenhost_mcp_when( $mordenhost_mcp_grant['first_seen'] );
					$mordenhost_mcp_refreshed = $mordenhost_mcp_when( $mordenhost_mcp_grant['last_issued'] );
					$mordenhost_mcp_expiry    = $mordenhost_mcp_until( $mordenhost_mcp_grant['expires_at'] );
					$mordenhost_mcp_access    = \Mordenhost_MCP\Admin\Access_Controls::editor_attributes(
						\Mordenhost_MCP\Access\Policy::key_for_grant( (string) $mordenhost_mcp_grant['client_id'], (int) $mordenhost_mcp_grant['user_id'] )
					);
					?>
					<tr data-client-id="<?php echo esc_attr( $mordenhost_mcp_grant['client_id'] ); ?>"
					    data-user-id="<?php echo esc_attr( $mordenhost_mcp_grant['user_id'] ); ?>">
						<td>
							<strong>
								<?php
								echo $mordenhost_mcp_grant['client_name'] !== ''
									? esc_html( $mordenhost_mcp_grant['client_name'] )
									: esc_html__( 'Unnamed client', 'mordenhost-mcp' );
								?>
							</strong>
							<?php  ?>
							<code class="mmcp-client-id" title="<?php echo esc_attr( $mordenhost_mcp_grant['client_id'] ); ?>"><?php echo esc_html( substr( (string) $mordenhost_mcp_grant['client_id'], 0, 12 ) ); ?>…</code>
							<?php if ( ! empty( $mordenhost_mcp_grant['client_missing'] ) ) : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The client registration for these tokens no longer exists. This is normal after an OAuth reset; the tokens are cleaned up by the daily cron. Revoke them here to take effect immediately.', 'mordenhost-mcp' ); ?>">
									<?php esc_html_e( 'orphaned', 'mordenhost-mcp' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $mordenhost_mcp_user ) : ?>
								<?php echo esc_html( $mordenhost_mcp_user->display_name ); ?>
								<span class="mmcp-subtle">
									<?php

									$mordenhost_mcp_roles = ! empty( $mordenhost_mcp_user->roles ) ? implode( ', ', $mordenhost_mcp_user->roles ) : __( 'no role', 'mordenhost-mcp' );
									echo esc_html( $mordenhost_mcp_roles );
									?>
								</span>
							<?php else : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The WordPress user this grant was issued to has been deleted. The tokens cannot authenticate anyone, so revoke them to clear the row.', 'mordenhost-mcp' ); ?>">
									<?php
									printf(
										/* translators: %d: WordPress user ID */
										esc_html__( 'deleted user #%d', 'mordenhost-mcp' ),
										(int) $mordenhost_mcp_grant['user_id']
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_first['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_first['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_refreshed['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_refreshed['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_expiry['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_expiry['relative'] ); ?>
						</td>
						<td class="mmcp-col-access">
							<div class="mmcp-access-editor"
							     data-target="grant"
							     data-access="<?php echo esc_attr( $mordenhost_mcp_access['data-access'] ); ?>"
							     data-groups="<?php echo esc_attr( $mordenhost_mcp_access['data-groups'] ); ?>"></div>
						</td>
						<td class="mmcp-col-action">
							<button type="button" class="button button-small mmcp-revoke-grant">
								<?php esc_html_e( 'Disconnect', 'mordenhost-mcp' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $mordenhost_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( '"Last activity" is when this client last exchanged its refresh token for a new access token, which well-behaved clients do quietly in the background. A recent value means the client is alive; a value older than the session length means it has stopped calling.', 'mordenhost-mcp' ); ?>
		</p>
		<p class="description mmcp-table-note">
			<?php
			printf(
				/* translators: %s: link to the Access control sub-tab */
				esc_html__( '"Access" limits what that client can do on top of its user\'s capabilities, and takes effect on its next request. Read-only mode and the default for new clients are on the %s screen.', 'mordenhost-mcp' ),
				'<a href="' . esc_url( add_query_arg( [ 'panel' => 'permissions', 'sub' => 'access' ], admin_url( 'admin.php?page=mordenhost-mcp' ) ) ) . '">' . esc_html__( 'Access control', 'mordenhost-mcp' ) . '</a>'
			);
			?>
		</p>

	<?php endif; ?>

<?php else : ?>

	<!-- ============================================================
	     Sub-tab: Transport sessions
	     ============================================================ -->
	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'One row per open MCP conversation. Sessions are opened after authentication, so both OAuth and API-key clients appear here, which is why this count can differ from the client count. Ending a session does not revoke credentials: the client simply starts a new one on its next request.', 'mordenhost-mcp' ); ?>
	</p>

	<?php if ( empty( $mordenhost_mcp_sessions ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No open sessions', 'mordenhost-mcp' ); ?></h4>
			<p>
				<?php esc_html_e( 'A session appears here as soon as any client sends its first request, and expires 24 hours after its last activity.', 'mordenhost-mcp' ); ?>
			</p>
		</div>

	<?php else : ?>

		<?php  ?>
		<div class="mmcp-table-toolbar">
			<span class="mmcp-table-count">
				<?php
				printf(
					/* translators: %s: number of open sessions */
					esc_html( _n( '%s open session', '%s open sessions', $mordenhost_mcp_session_total, 'mordenhost-mcp' ) ),
					esc_html( number_format_i18n( $mordenhost_mcp_session_total ) )
				);
				?>
			</span>
			<button type="button" class="button" id="mordenhost-mcp-clear-all-sessions">
				<?php esc_html_e( 'End all sessions', 'mordenhost-mcp' ); ?>
			</button>
			<span id="mordenhost-mcp-clear-all-sessions-status" class="mmcp-inline-status"></span>
		</div>

		<table class="mmcp-table widefat striped" id="mordenhost-mcp-sessions-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Session', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Started', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last activity', 'mordenhost-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'mordenhost-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'mordenhost-mcp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $mordenhost_mcp_sessions as $mordenhost_mcp_session ) :
					$mordenhost_mcp_started = $mordenhost_mcp_when( $mordenhost_mcp_session['created_at'] );
					$mordenhost_mcp_seen    = $mordenhost_mcp_when( $mordenhost_mcp_session['last_seen_at'] );
					$mordenhost_mcp_sexp    = $mordenhost_mcp_until( $mordenhost_mcp_session['expires_at'] );
					?>
					<tr data-session-row-id="<?php echo esc_attr( $mordenhost_mcp_session['id'] ); ?>">
						<td>
							<code><?php echo esc_html( $mordenhost_mcp_session['hash_prefix'] ); ?>…</code>
							<span class="mmcp-subtle">
								<?php
								printf(
									/* translators: %s: truncated credential fingerprint */
									esc_html__( 'credential %s…', 'mordenhost-mcp' ),
									esc_html( $mordenhost_mcp_session['auth_fingerprint_prefix'] )
								);
								?>
							</span>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_started['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_started['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_seen['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_seen['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $mordenhost_mcp_sexp['absolute'] ); ?>">
							<?php echo esc_html( $mordenhost_mcp_sexp['relative'] ); ?>
						</td>
						<td class="mmcp-col-action">
							<button type="button" class="button button-small mmcp-end-session">
								<?php esc_html_e( 'End', 'mordenhost-mcp' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $mordenhost_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( 'Session IDs are stored only as a hash, so the values above are truncated prefixes: enough to tell two rows apart, never enough to reuse. The credential fingerprint is what binds a session to the client that opened it: two rows sharing a fingerprint are the same client on two conversations.', 'mordenhost-mcp' ); ?>
		</p>

		<p class="description mmcp-table-note">
			<?php esc_html_e( 'Ending sessions clears transport state without touching credentials, so nothing has to re-authorize afterwards. To actually cut off access, disconnect the client on the Connected clients tab.', 'mordenhost-mcp' ); ?>
		</p>

	<?php endif; ?>

<?php endif; ?>
