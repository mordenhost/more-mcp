<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MORE_MCP_SESSIONS_PER_PAGE' ) ) {
	define( 'MORE_MCP_SESSIONS_PER_PAGE', 20 );
}

$more_mcp_sessions_base = add_query_arg( 'panel', 'sessions', admin_url( 'admin.php?page=more-mcp' ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pager, cast to a bounded int below.
$more_mcp_page   = isset( $_GET['spage'] ) ? max( 1, absint( $_GET['spage'] ) ) : 1;
$more_mcp_offset = ( $more_mcp_page - 1 ) * MORE_MCP_SESSIONS_PER_PAGE;

$more_mcp_has_token_store   = class_exists( '\More_MCP\OAuth\Token_Store' ) && method_exists( '\More_MCP\OAuth\Token_Store', 'list_active_grants' );
$more_mcp_has_session_store = class_exists( '\More_MCP\MCP\Session_Store' ) && method_exists( '\More_MCP\MCP\Session_Store', 'list_sessions' );

$more_mcp_grant_total   = $more_mcp_grant_count;
$more_mcp_session_total = $more_mcp_session_count;

$more_mcp_grants   = [];
$more_mcp_sessions = [];

if ( 'clients' === $more_mcp_session_view && $more_mcp_has_token_store ) {
	$more_mcp_grants = \More_MCP\OAuth\Token_Store::list_active_grants( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
} elseif ( 'transport' === $more_mcp_session_view && $more_mcp_has_session_store ) {
	$more_mcp_sessions = \More_MCP\MCP\Session_Store::list_sessions( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
}

$more_mcp_active_total = ( 'clients' === $more_mcp_session_view ) ? $more_mcp_grant_total : $more_mcp_session_total;
$more_mcp_total_pages  = max( 1, (int) ceil( $more_mcp_active_total / MORE_MCP_SESSIONS_PER_PAGE ) );

if ( $more_mcp_page > $more_mcp_total_pages && $more_mcp_active_total > 0 ) {
	$more_mcp_page   = $more_mcp_total_pages;
	$more_mcp_offset = ( $more_mcp_page - 1 ) * MORE_MCP_SESSIONS_PER_PAGE;
	if ( 'clients' === $more_mcp_session_view && $more_mcp_has_token_store ) {
		$more_mcp_grants = \More_MCP\OAuth\Token_Store::list_active_grants( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
	} elseif ( 'transport' === $more_mcp_session_view && $more_mcp_has_session_store ) {
		$more_mcp_sessions = \More_MCP\MCP\Session_Store::list_sessions( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
	}
}

$more_mcp_when = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'mordenhost-mcp-server' ),
			'absolute' => '',
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "3 mins" */
			__( '%s ago', 'mordenhost-mcp-server' ),
			human_time_diff( $ts, time() )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

$more_mcp_until = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'mordenhost-mcp-server' ),
			'absolute' => '',
		];
	}
	if ( $ts <= time() ) {
		return [
			'relative' => __( 'expired', 'mordenhost-mcp-server' ),
			'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "4 hours" */
			__( 'in %s', 'mordenhost-mcp-server' ),
			human_time_diff( time(), $ts )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

$more_mcp_pager = function () use ( $more_mcp_page, $more_mcp_total_pages, $more_mcp_active_total, $more_mcp_session_view, $more_mcp_sessions_base, $more_mcp_offset ) {
	if ( $more_mcp_total_pages < 2 ) {
		return;
	}

	$tab_url = add_query_arg( 'view', $more_mcp_session_view, $more_mcp_sessions_base );
	$first   = $more_mcp_offset + 1;
	$last    = min( $more_mcp_offset + MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_active_total );
	?>
	<div class="mmcp-pager">
		<span class="mmcp-pager-range">
			<?php
			printf(
				/* translators: 1: first row on this page, 2: last row on this page, 3: total rows */
				esc_html__( '%1$s–%2$s of %3$s', 'mordenhost-mcp-server' ),
				esc_html( number_format_i18n( $first ) ),
				esc_html( number_format_i18n( $last ) ),
				esc_html( number_format_i18n( $more_mcp_active_total ) )
			);
			?>
		</span>
		<span class="mmcp-pager-links">
			<?php if ( $more_mcp_page > 1 ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $more_mcp_page - 1, $tab_url ) ); ?>">
					<?php esc_html_e( '‹ Previous', 'mordenhost-mcp-server' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( '‹ Previous', 'mordenhost-mcp-server' ); ?></span>
			<?php endif; ?>

			<span class="mmcp-pager-current">
				<?php
				printf(
					/* translators: 1: current page number, 2: total pages */
					esc_html__( 'Page %1$s of %2$s', 'mordenhost-mcp-server' ),
					esc_html( number_format_i18n( $more_mcp_page ) ),
					esc_html( number_format_i18n( $more_mcp_total_pages ) )
				);
				?>
			</span>

			<?php if ( $more_mcp_page < $more_mcp_total_pages ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $more_mcp_page + 1, $tab_url ) ); ?>">
					<?php esc_html_e( 'Next ›', 'mordenhost-mcp-server' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( 'Next ›', 'mordenhost-mcp-server' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php
};

$more_mcp_session_tabs = [
	'clients'   => [
		'label' => __( 'Connected clients', 'mordenhost-mcp-server' ),
		'count' => $more_mcp_grant_total,
	],
	'transport' => [
		'label' => __( 'Transport sessions', 'mordenhost-mcp-server' ),
		'count' => $more_mcp_session_total,
	],
];
?>

<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Session views', 'mordenhost-mcp-server' ); ?>">
	<?php foreach ( $more_mcp_session_tabs as $more_mcp_tab_slug => $more_mcp_tab ) : ?>
		<?php $more_mcp_tab_active = ( $more_mcp_tab_slug === $more_mcp_session_view ); ?>
		<a href="<?php echo esc_url( add_query_arg( 'view', $more_mcp_tab_slug, $more_mcp_sessions_base ) ); ?>"
		   class="mmcp-subtab<?php echo $more_mcp_tab_active ? ' is-active' : ''; ?>"
		   <?php echo $more_mcp_tab_active ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( $more_mcp_tab['label'] ); ?>
			<span class="mmcp-subtab-count"><?php echo esc_html( number_format_i18n( $more_mcp_tab['count'] ) ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( 'clients' === $more_mcp_session_view ) : ?>

	<!-- ============================================================
	     Sub-tab: Connected clients (OAuth grants)
	     ============================================================ -->
	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'One row per AI client that has authorized through OAuth. Each acts as the WordPress user shown, with exactly that user\'s capabilities. Disconnecting a client revokes its tokens, so it must authorize again before it can call this site.', 'mordenhost-mcp-server' ); ?>
	</p>

	<?php if ( empty( $more_mcp_grants ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No clients are connected via OAuth', 'mordenhost-mcp-server' ); ?></h4>
			<p>
				<?php esc_html_e( 'Claude.ai and ChatGPT appear here once they complete the OAuth handshake. Clients that authenticate with the API key instead, such as Claude Desktop, Cursor, and raw REST calls, hold no OAuth grant and never appear in this list. Look under Transport sessions for those.', 'mordenhost-mcp-server' ); ?>
			</p>
		</div>

	<?php else : ?>

		<table class="mmcp-table widefat striped" id="more-mcp-grants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Client', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Acting as', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Connected', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last activity', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Access', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'mordenhost-mcp-server' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $more_mcp_grants as $more_mcp_grant ) :
					$more_mcp_user      = get_userdata( (int) $more_mcp_grant['user_id'] );
					$more_mcp_first     = $more_mcp_when( $more_mcp_grant['first_seen'] );
					$more_mcp_refreshed = $more_mcp_when( $more_mcp_grant['last_issued'] );
					$more_mcp_expiry    = $more_mcp_until( $more_mcp_grant['expires_at'] );
					$more_mcp_access    = \More_MCP\Admin\Access_Controls::editor_attributes(
						\More_MCP\Access\Policy::key_for_grant( (string) $more_mcp_grant['client_id'], (int) $more_mcp_grant['user_id'] )
					);
					?>
					<tr data-client-id="<?php echo esc_attr( $more_mcp_grant['client_id'] ); ?>"
					    data-user-id="<?php echo esc_attr( $more_mcp_grant['user_id'] ); ?>">
						<td>
							<strong>
								<?php
								echo $more_mcp_grant['client_name'] !== ''
									? esc_html( $more_mcp_grant['client_name'] )
									: esc_html__( 'Unnamed client', 'mordenhost-mcp-server' );
								?>
							</strong>
							<?php  ?>
							<code class="mmcp-client-id" title="<?php echo esc_attr( $more_mcp_grant['client_id'] ); ?>"><?php echo esc_html( substr( (string) $more_mcp_grant['client_id'], 0, 12 ) ); ?>…</code>
							<?php if ( ! empty( $more_mcp_grant['client_missing'] ) ) : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The client registration for these tokens no longer exists. This is normal after an OAuth reset; the tokens are cleaned up by the daily cron. Revoke them here to take effect immediately.', 'mordenhost-mcp-server' ); ?>">
									<?php esc_html_e( 'orphaned', 'mordenhost-mcp-server' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $more_mcp_user ) : ?>
								<?php echo esc_html( $more_mcp_user->display_name ); ?>
								<span class="mmcp-subtle">
									<?php

									$more_mcp_roles = ! empty( $more_mcp_user->roles ) ? implode( ', ', $more_mcp_user->roles ) : __( 'no role', 'mordenhost-mcp-server' );
									echo esc_html( $more_mcp_roles );
									?>
								</span>
							<?php else : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The WordPress user this grant was issued to has been deleted. The tokens cannot authenticate anyone, so revoke them to clear the row.', 'mordenhost-mcp-server' ); ?>">
									<?php
									printf(
										/* translators: %d: WordPress user ID */
										esc_html__( 'deleted user #%d', 'mordenhost-mcp-server' ),
										(int) $more_mcp_grant['user_id']
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_first['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_first['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_refreshed['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_refreshed['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_expiry['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_expiry['relative'] ); ?>
						</td>
						<td class="mmcp-col-access">
							<div class="mmcp-access-editor"
							     data-target="grant"
							     data-access="<?php echo esc_attr( $more_mcp_access['data-access'] ); ?>"
							     data-groups="<?php echo esc_attr( $more_mcp_access['data-groups'] ); ?>"></div>
						</td>
						<td class="mmcp-col-action">
							<button type="button" class="button button-small mmcp-revoke-grant">
								<?php esc_html_e( 'Disconnect', 'mordenhost-mcp-server' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $more_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( '"Last activity" is when this client last exchanged its refresh token for a new access token, which well-behaved clients do quietly in the background. A recent value means the client is alive; a value older than the session length means it has stopped calling.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="description mmcp-table-note">
			<?php
			printf(
				/* translators: %s: link to the Access control sub-tab */
				esc_html__( '"Access" limits what that client can do on top of its user\'s capabilities, and takes effect on its next request. Read-only mode and the default for new clients are on the %s screen.', 'mordenhost-mcp-server' ),
				'<a href="' . esc_url( add_query_arg( [ 'panel' => 'permissions', 'sub' => 'access' ], admin_url( 'admin.php?page=more-mcp' ) ) ) . '">' . esc_html__( 'Access control', 'mordenhost-mcp-server' ) . '</a>'
			);
			?>
		</p>

	<?php endif; ?>

<?php else : ?>

	<!-- ============================================================
	     Sub-tab: Transport sessions
	     ============================================================ -->
	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'One row per open MCP conversation. Sessions are opened after authentication, so both OAuth and API-key clients appear here, which is why this count can differ from the client count. Ending a session does not revoke credentials: the client simply starts a new one on its next request.', 'mordenhost-mcp-server' ); ?>
	</p>

	<?php if ( empty( $more_mcp_sessions ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No open sessions', 'mordenhost-mcp-server' ); ?></h4>
			<p>
				<?php esc_html_e( 'A session appears here as soon as any client sends its first request, and expires 24 hours after its last activity.', 'mordenhost-mcp-server' ); ?>
			</p>
		</div>

	<?php else : ?>

		<?php  ?>
		<div class="mmcp-table-toolbar">
			<span class="mmcp-table-count">
				<?php
				printf(
					/* translators: %s: number of open sessions */
					esc_html( _n( '%s open session', '%s open sessions', $more_mcp_session_total, 'mordenhost-mcp-server' ) ),
					esc_html( number_format_i18n( $more_mcp_session_total ) )
				);
				?>
			</span>
			<button type="button" class="button" id="more-mcp-clear-all-sessions">
				<?php esc_html_e( 'End all sessions', 'mordenhost-mcp-server' ); ?>
			</button>
			<span id="more-mcp-clear-all-sessions-status" class="mmcp-inline-status"></span>
		</div>

		<table class="mmcp-table widefat striped" id="more-mcp-sessions-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Session', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Started', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last activity', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'mordenhost-mcp-server' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'mordenhost-mcp-server' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $more_mcp_sessions as $more_mcp_session ) :
					$more_mcp_started = $more_mcp_when( $more_mcp_session['created_at'] );
					$more_mcp_seen    = $more_mcp_when( $more_mcp_session['last_seen_at'] );
					$more_mcp_sexp    = $more_mcp_until( $more_mcp_session['expires_at'] );
					?>
					<tr data-session-row-id="<?php echo esc_attr( $more_mcp_session['id'] ); ?>">
						<td>
							<code><?php echo esc_html( $more_mcp_session['hash_prefix'] ); ?>…</code>
							<span class="mmcp-subtle">
								<?php
								printf(
									/* translators: %s: truncated credential fingerprint */
									esc_html__( 'credential %s…', 'mordenhost-mcp-server' ),
									esc_html( $more_mcp_session['auth_fingerprint_prefix'] )
								);
								?>
							</span>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_started['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_started['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_seen['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_seen['relative'] ); ?>
						</td>
						<td title="<?php echo esc_attr( $more_mcp_sexp['absolute'] ); ?>">
							<?php echo esc_html( $more_mcp_sexp['relative'] ); ?>
						</td>
						<td class="mmcp-col-action">
							<button type="button" class="button button-small mmcp-end-session">
								<?php esc_html_e( 'End', 'mordenhost-mcp-server' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $more_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( 'Session IDs are stored only as a hash, so the values above are truncated prefixes: enough to tell two rows apart, never enough to reuse. The credential fingerprint is what binds a session to the client that opened it: two rows sharing a fingerprint are the same client on two conversations.', 'mordenhost-mcp-server' ); ?>
		</p>

		<p class="description mmcp-table-note">
			<?php esc_html_e( 'Ending sessions clears transport state without touching credentials, so nothing has to re-authorize afterwards. To actually cut off access, disconnect the client on the Connected clients tab.', 'mordenhost-mcp-server' ); ?>
		</p>

	<?php endif; ?>

<?php endif; ?>
