<?php
/**
 * Sessions panel — connected clients, transport sessions, session length, and
 * OAuth reset.
 *
 * Split into two sub-tabs because the two lists answer different questions and
 * behave differently under revocation:
 *
 *   - A GRANT is a credential: one client_id + user_id pair that has been
 *     authorized. Revoking it stops that client from authenticating at all.
 *     API-key clients have no grant, so they never appear in that list.
 *
 *   - A SESSION is transport state: one MCP conversation, opened after
 *     authentication succeeded. Ending it makes the client re-initialize; it does
 *     nothing about whether the client may still connect.
 *
 * Flattening them into one list would mean either action silently did less than
 * its label implied. Stacking them on one screen was the earlier compromise, and
 * it does not survive real volume — a busy site accumulates far more transport
 * sessions than clients, so the grants table got pushed off the top of the screen
 * by rows an admin rarely needs to look at.
 *
 * IMPORTANT: the active sub-tab is resolved by templates/admin/settings.php, not
 * here, because it determines whether the TTL select posts on save. See the
 * $more_mcp_session_view block there for why.
 *
 * Rendered by templates/admin/settings.php, which owns the $more_mcp_*
 * variables used below. PHP `use` statements are file-scoped and are NOT
 * inherited across require(), so any namespaced class this partial touches
 * must be imported or fully qualified here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rows per page. Deliberately modest: these tables are wide.
 *
 * Guarded because this file is a template, not a class — anything rendering the
 * panel twice in one process (the render tests do) would otherwise re-declare it.
 */
if ( ! defined( 'MORE_MCP_SESSIONS_PER_PAGE' ) ) {
	define( 'MORE_MCP_SESSIONS_PER_PAGE', 20 );
}

$more_mcp_sessions_base = add_query_arg( 'panel', 'sessions', admin_url( 'admin.php?page=more-mcp' ) );

// Current page, from the `spage` query arg. Named to avoid colliding with the
// `paged` arg the Activity Log screen uses.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pager, cast to a bounded int below.
$more_mcp_page   = isset( $_GET['spage'] ) ? max( 1, absint( $_GET['spage'] ) ) : 1;
$more_mcp_offset = ( $more_mcp_page - 1 ) * MORE_MCP_SESSIONS_PER_PAGE;

/**
 * Read whichever list the active sub-tab needs — and only that one.
 *
 * The inactive sub-tab's rows are never queried. Its total still is, because the
 * sub-tab labels carry counts, and a count is one aggregate query rather than a
 * page of rows.
 *
 * Guarded on method_exists for the same reason the parent template guards the
 * counts: tests render this panel against stubs with no database, and a missing
 * store should degrade to an empty list rather than fatal the settings screen.
 */
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

// Total for the ACTIVE tab, which is what the pager pages through.
$more_mcp_active_total = ( 'clients' === $more_mcp_session_view ) ? $more_mcp_grant_total : $more_mcp_session_total;
$more_mcp_total_pages  = max( 1, (int) ceil( $more_mcp_active_total / MORE_MCP_SESSIONS_PER_PAGE ) );

// A page number past the end (a stale bookmark, or rows expiring between the
// count and the read) would render an empty table under a non-zero total, which
// reads as data loss. Send it back to the last real page instead.
if ( $more_mcp_page > $more_mcp_total_pages && $more_mcp_active_total > 0 ) {
	$more_mcp_page   = $more_mcp_total_pages;
	$more_mcp_offset = ( $more_mcp_page - 1 ) * MORE_MCP_SESSIONS_PER_PAGE;
	if ( 'clients' === $more_mcp_session_view && $more_mcp_has_token_store ) {
		$more_mcp_grants = \More_MCP\OAuth\Token_Store::list_active_grants( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
	} elseif ( 'transport' === $more_mcp_session_view && $more_mcp_has_session_store ) {
		$more_mcp_sessions = \More_MCP\MCP\Session_Store::list_sessions( MORE_MCP_SESSIONS_PER_PAGE, $more_mcp_offset );
	}
}

/**
 * Format a stored UTC datetime as a human-relative string plus an absolute
 * tooltip.
 *
 * Everything in these tables is stored via gmdate(), so the value is UTC. It is
 * rendered relative ("3 minutes ago") because that is the form an admin can act
 * on, with the absolute local time in the title attribute for when the exact
 * moment matters.
 */
$more_mcp_when = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'more-mcp' ),
			'absolute' => '',
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "3 mins" */
			__( '%s ago', 'more-mcp' ),
			human_time_diff( $ts, time() )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

/**
 * Same, forward-looking: "in 4 hours" rather than "4 hours ago".
 *
 * An expiry already in the past is reported as expired instead of as a negative
 * duration. It can happen legitimately — the list is read at the top of the
 * request and a short TTL can lapse before the page finishes rendering.
 */
$more_mcp_until = function ( $gmt_datetime ) {
	$ts = $gmt_datetime ? strtotime( $gmt_datetime . ' UTC' ) : false;
	if ( ! $ts ) {
		return [
			'relative' => __( 'unknown', 'more-mcp' ),
			'absolute' => '',
		];
	}
	if ( $ts <= time() ) {
		return [
			'relative' => __( 'expired', 'more-mcp' ),
			'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
		];
	}
	return [
		'relative' => sprintf(
			/* translators: %s: human-readable time difference, e.g. "4 hours" */
			__( 'in %s', 'more-mcp' ),
			human_time_diff( time(), $ts )
		),
		'absolute' => wp_date( 'Y-m-d H:i:s', $ts ),
	];
};

/**
 * Render the pager for the active sub-tab.
 *
 * Emits nothing when everything fits on one page — a pager reading "Page 1 of 1"
 * is pure noise. Links preserve the sub-tab so paging does not bounce the admin
 * back to the other list.
 */
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
				esc_html__( '%1$s–%2$s of %3$s', 'more-mcp' ),
				esc_html( number_format_i18n( $first ) ),
				esc_html( number_format_i18n( $last ) ),
				esc_html( number_format_i18n( $more_mcp_active_total ) )
			);
			?>
		</span>
		<span class="mmcp-pager-links">
			<?php if ( $more_mcp_page > 1 ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $more_mcp_page - 1, $tab_url ) ); ?>">
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
					esc_html( number_format_i18n( $more_mcp_page ) ),
					esc_html( number_format_i18n( $more_mcp_total_pages ) )
				);
				?>
			</span>

			<?php if ( $more_mcp_page < $more_mcp_total_pages ) : ?>
				<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'spage', $more_mcp_page + 1, $tab_url ) ); ?>">
					<?php esc_html_e( 'Next ›', 'more-mcp' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-small disabled" aria-disabled="true"><?php esc_html_e( 'Next ›', 'more-mcp' ); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php
};

// Sub-tab definitions, each carrying its live total.
$more_mcp_session_tabs = [
	'clients'   => [
		'label' => __( 'Connected clients', 'more-mcp' ),
		'count' => $more_mcp_grant_total,
	],
	'transport' => [
		'label' => __( 'Transport sessions', 'more-mcp' ),
		'count' => $more_mcp_session_total,
	],
];
?>

<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Session views', 'more-mcp' ); ?>">
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
		<?php esc_html_e( 'One row per AI client that has authorized through OAuth. Each acts as the WordPress user shown, with exactly that user\'s capabilities. Disconnecting a client revokes its tokens, so it must authorize again before it can call this site.', 'more-mcp' ); ?>
	</p>

	<?php if ( empty( $more_mcp_grants ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No clients are connected via OAuth', 'more-mcp' ); ?></h4>
			<p>
				<?php esc_html_e( 'Claude.ai and ChatGPT appear here once they complete the OAuth handshake. Clients that authenticate with the API key instead, such as Claude Desktop, Cursor, and raw REST calls, hold no OAuth grant and never appear in this list. Look under Transport sessions for those.', 'more-mcp' ); ?>
			</p>
		</div>

	<?php else : ?>

		<table class="mmcp-table widefat striped" id="more-mcp-grants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Client', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Acting as', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Connected', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Token refreshed', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'more-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'more-mcp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $more_mcp_grants as $more_mcp_grant ) :
					$more_mcp_user      = get_userdata( (int) $more_mcp_grant['user_id'] );
					$more_mcp_first     = $more_mcp_when( $more_mcp_grant['first_seen'] );
					$more_mcp_refreshed = $more_mcp_when( $more_mcp_grant['last_issued'] );
					$more_mcp_expiry    = $more_mcp_until( $more_mcp_grant['expires_at'] );
					?>
					<tr data-client-id="<?php echo esc_attr( $more_mcp_grant['client_id'] ); ?>"
					    data-user-id="<?php echo esc_attr( $more_mcp_grant['user_id'] ); ?>">
						<td>
							<strong>
								<?php
								echo $more_mcp_grant['client_name'] !== ''
									? esc_html( $more_mcp_grant['client_name'] )
									: esc_html__( 'Unnamed client', 'more-mcp' );
								?>
							</strong>
							<code class="mmcp-client-id"><?php echo esc_html( $more_mcp_grant['client_id'] ); ?></code>
							<?php if ( ! empty( $more_mcp_grant['client_missing'] ) ) : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The client registration for these tokens no longer exists. This is normal after an OAuth reset; the tokens are cleaned up by the daily cron. Revoke them here to take effect immediately.', 'more-mcp' ); ?>">
									<?php esc_html_e( 'orphaned', 'more-mcp' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $more_mcp_user ) : ?>
								<?php echo esc_html( $more_mcp_user->display_name ); ?>
								<span class="mmcp-subtle">
									<?php
									// The role matters more than the name here: it is what
									// bounds everything the client is able to do.
									$more_mcp_roles = ! empty( $more_mcp_user->roles ) ? implode( ', ', $more_mcp_user->roles ) : __( 'no role', 'more-mcp' );
									echo esc_html( $more_mcp_roles );
									?>
								</span>
							<?php else : ?>
								<span class="mmcp-flag mmcp-flag-warn"
								      title="<?php esc_attr_e( 'The WordPress user this grant was issued to has been deleted. The tokens cannot authenticate anyone, so revoke them to clear the row.', 'more-mcp' ); ?>">
									<?php
									printf(
										/* translators: %d: WordPress user ID */
										esc_html__( 'deleted user #%d', 'more-mcp' ),
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
						<td class="mmcp-col-action">
							<button type="button" class="button button-small mmcp-revoke-grant">
								<?php esc_html_e( 'Disconnect', 'more-mcp' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $more_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( '"Token refreshed" is when this client last exchanged its refresh token for a new access token, which well-behaved clients do quietly in the background. A recent value means the client is alive; a value older than the session length means it has stopped calling.', 'more-mcp' ); ?>
		</p>

	<?php endif; ?>

	<!-- ============================================================
	     Session length — lives on this sub-tab, and templates/admin/settings.php
	     keys TTL ownership to it. Moving this field to the other sub-tab means
	     updating $more_mcp_panel_owns there too.
	     ============================================================ -->
	<div class="mmcp-section">
		<div class="mmcp-section-head">
			<h3><?php esc_html_e( 'Session length', 'more-mcp' ); ?></h3>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="access_token_ttl_seconds"><?php esc_html_e( 'Stay connected for', 'more-mcp' ); ?></label>
				</th>
				<td>
					<?php
					$more_mcp_ttl_choices = [
						3600   => __( '1 hour', 'more-mcp' ),
						28800  => __( '8 hours', 'more-mcp' ),
						86400  => __( '24 hours (default)', 'more-mcp' ),
						604800 => __( '7 days', 'more-mcp' ),
					];
					$more_mcp_ttl_current = (int) ( $more_mcp_settings['access_token_ttl_seconds'] ?? \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL );
					if ( ! array_key_exists( $more_mcp_ttl_current, $more_mcp_ttl_choices ) ) {
						$more_mcp_ttl_current = \More_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL;
					}
					?>
					<select name="more_mcp_settings[access_token_ttl_seconds]" id="access_token_ttl_seconds">
						<?php foreach ( $more_mcp_ttl_choices as $more_mcp_seconds => $more_mcp_label ) : ?>
							<option value="<?php echo esc_attr( $more_mcp_seconds ); ?>" <?php selected( $more_mcp_ttl_current, $more_mcp_seconds ); ?>>
								<?php echo esc_html( $more_mcp_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'How long an access token stays valid before the client has to refresh it. Shorter is tighter but means more refresh traffic; clients handle refreshes on their own either way, so this is not a re-authorization interval.', 'more-mcp' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Applies to newly issued tokens only. Tokens already issued keep their original expiry; disconnect a client above to have it re-issued at the new length immediately.', 'more-mcp' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- ============================================================
	     Danger zone — credential-scoped actions belong with the credentials
	     ============================================================ -->
	<div class="mmcp-section mmcp-section-danger">
		<div class="mmcp-section-head">
			<h3><?php esc_html_e( 'Bulk and recovery actions', 'more-mcp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Both actions below affect every connected client at once. Prefer the per-row Disconnect button above unless you actually intend that.', 'more-mcp' ); ?>
			</p>
		</div>

		<div class="mmcp-danger-action">
			<div class="mmcp-danger-copy">
				<h4><?php esc_html_e( 'Revoke all active sessions', 'more-mcp' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Revokes every issued token, so every OAuth client must authorize again. Registered clients and all settings are preserved; only the tokens are invalidated. Use it during incident response, or to force every client onto a newly shortened session length at once.', 'more-mcp' ); ?>
				</p>
			</div>
			<div class="mmcp-danger-control">
				<?php
				// Inline SVG with currentColor + inline flex — per CLAUDE.md rule 8.
				// Dashicons inside .button rot across WP admin CSS releases; inline SVG
				// inherits button text color and survives cascade churn.
				$more_mcp_btn_style = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
				$more_mcp_svg_style = 'width:14px;height:14px;flex-shrink:0;';
				?>
				<button type="button"
				        class="button button-secondary"
				        id="more-mcp-revoke-all-sessions"
				        style="<?php echo esc_attr( $more_mcp_btn_style ); ?>">
					<svg style="<?php echo esc_attr( $more_mcp_svg_style ); ?>" viewBox="0 0 24 24" fill="none"
					     stroke="currentColor" stroke-width="2" stroke-linecap="round"
					     stroke-linejoin="round" aria-hidden="true">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					</svg>
					<?php esc_html_e( 'Revoke all', 'more-mcp' ); ?>
				</button>
				<span id="more-mcp-revoke-all-sessions-status" class="mmcp-inline-status"></span>
			</div>
		</div>

		<div class="mmcp-danger-action">
			<div class="mmcp-danger-copy">
				<h4><?php esc_html_e( 'Reset OAuth state', 'more-mcp' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Deletes all registered OAuth clients, issued tokens, and pending authorization codes. Your settings, API key, and Activity Log are not affected.', 'more-mcp' ); ?>
				</p>
				<p class="description warning-text">
					<strong><?php esc_html_e( 'Note:', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'Every connected client must be removed and re-added afterwards, and any manually configured OAuth client ID and secret are cleared so the connector falls back to automatic registration. Use it for a connector stuck mid-handshake; Troubleshooting explains when.', 'more-mcp' ); ?>
				</p>
			</div>
			<div class="mmcp-danger-control">
				<?php
				// Inline SVG with currentColor + inline flex — per CLAUDE.md rule 8.
				$more_mcp_btn_style_reset = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
				$more_mcp_svg_style_reset = 'width:14px;height:14px;flex-shrink:0;';
				?>
				<button type="button"
				        class="button button-secondary"
				        id="more-mcp-reset-oauth-state"
				        style="<?php echo esc_attr( $more_mcp_btn_style_reset ); ?>">
					<svg style="<?php echo esc_attr( $more_mcp_svg_style_reset ); ?>" viewBox="0 0 24 24" fill="none"
					     stroke="currentColor" stroke-width="2" stroke-linecap="round"
					     stroke-linejoin="round" aria-hidden="true">
						<polyline points="3 6 5 6 21 6"/>
						<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
						<path d="M10 11v6"/>
						<path d="M14 11v6"/>
						<path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
					</svg>
					<?php esc_html_e( 'Reset OAuth state', 'more-mcp' ); ?>
				</button>
				<span id="more-mcp-reset-oauth-state-status" class="mmcp-inline-status"></span>
			</div>
		</div>
	</div>

<?php else : ?>

	<!-- ============================================================
	     Sub-tab: Transport sessions
	     ============================================================ -->
	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'One row per open MCP conversation. Sessions are opened after authentication, so both OAuth and API-key clients appear here, which is why this count can differ from the client count. Ending a session does not revoke credentials: the client simply starts a new one on its next request.', 'more-mcp' ); ?>
	</p>

	<?php if ( empty( $more_mcp_sessions ) ) : ?>

		<div class="mmcp-empty">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<h4><?php esc_html_e( 'No open sessions', 'more-mcp' ); ?></h4>
			<p>
				<?php esc_html_e( 'A session appears here as soon as any client sends its first request, and expires 24 hours after its last activity.', 'more-mcp' ); ?>
			</p>
		</div>

	<?php else : ?>

		<table class="mmcp-table widefat striped" id="more-mcp-sessions-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Session', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Started', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last request', 'more-mcp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'more-mcp' ); ?></th>
					<th scope="col" class="mmcp-col-action"><?php esc_html_e( 'Action', 'more-mcp' ); ?></th>
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
									esc_html__( 'credential %s…', 'more-mcp' ),
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
								<?php esc_html_e( 'End', 'more-mcp' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $more_mcp_pager(); ?>

		<p class="description mmcp-table-note">
			<?php esc_html_e( 'Session IDs are stored only as a hash, so the values above are truncated prefixes: enough to tell two rows apart, never enough to reuse. The credential fingerprint is what binds a session to the client that opened it: two rows sharing a fingerprint are the same client on two conversations.', 'more-mcp' ); ?>
		</p>

		<div class="mmcp-section">
			<p>
				<button type="button" class="button" id="more-mcp-clear-all-sessions">
					<?php esc_html_e( 'End all sessions', 'more-mcp' ); ?>
				</button>
				<span id="more-mcp-clear-all-sessions-status" class="mmcp-inline-status"></span>
			</p>
			<p class="description">
				<?php esc_html_e( 'Clears transport state for every client without touching credentials, so nothing has to re-authorize afterwards. To actually cut off access, use Revoke all on the Connected clients tab.', 'more-mcp' ); ?>
			</p>
		</div>

	<?php endif; ?>

<?php endif; ?>
