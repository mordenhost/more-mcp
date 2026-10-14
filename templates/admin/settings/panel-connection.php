<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_base_url_conn = admin_url( 'admin.php?page=mordenhost-mcp' );

$mordenhost_mcp_has_manual_oauth = ! empty( $mordenhost_mcp_settings['oauth_client_id'] )
	|| ! empty( $mordenhost_mcp_settings['oauth_client_secret'] );

$mordenhost_mcp_checks = [];

$mordenhost_mcp_checks[] = $mordenhost_mcp_enabled
	? [ 'state' => 'good', 'label' => __( 'MCP server is on', 'mordenhost-mcp' ) ]
	: [
		'state'    => 'bad',
		'label'    => __( 'MCP server is off', 'mordenhost-mcp' ),
		'detail'   => __( 'Clients cannot connect until you enable it.', 'mordenhost-mcp' ),
		'fix_url'  => add_query_arg( 'panel', 'permissions', $mordenhost_mcp_base_url_conn ),
		'fix_text' => __( 'Enable', 'mordenhost-mcp' ),
	];

$mordenhost_mcp_permalinks_ok = '' !== (string) get_option( 'permalink_structure', '' );
$mordenhost_mcp_checks[]      = $mordenhost_mcp_permalinks_ok
	? [ 'state' => 'good', 'label' => __( 'Permalinks support OAuth', 'mordenhost-mcp' ) ]
	: [
		'state'    => 'warn',
		'label'    => __( 'Plain permalinks block OAuth', 'mordenhost-mcp' ),
		'detail'   => __( 'Claude.ai and ChatGPT cannot complete the handshake. API-key clients still work.', 'mordenhost-mcp' ),
		'fix_url'  => admin_url( 'options-permalink.php' ),
		'fix_text' => __( 'Change permalinks', 'mordenhost-mcp' ),
	];

$mordenhost_mcp_checks[] = $mordenhost_mcp_is_localhost
	? [
		'state'  => 'warn',
		'label'  => __( 'Site is on localhost', 'mordenhost-mcp' ),
		'detail' => __( 'Hosted clients need a public HTTPS address. Fine for local testing only.', 'mordenhost-mcp' ),
	]
	: [ 'state' => 'good', 'label' => __( 'Public HTTPS address', 'mordenhost-mcp' ) ];

$mordenhost_mcp_worst = 'good';
foreach ( $mordenhost_mcp_checks as $mordenhost_mcp_c ) {
	if ( 'bad' === $mordenhost_mcp_c['state'] ) { $mordenhost_mcp_worst = 'bad'; break; }
	if ( 'warn' === $mordenhost_mcp_c['state'] ) { $mordenhost_mcp_worst = 'warn'; }
}

if ( ! $mordenhost_mcp_enabled ) {
	$mordenhost_mcp_banner_state = 'off';
	$mordenhost_mcp_banner_title = __( 'Your MCP server is off', 'mordenhost-mcp' );
	$mordenhost_mcp_banner_sub   = __( 'AI clients cannot connect. Turn it on from Access when you are ready.', 'mordenhost-mcp' );
} elseif ( 'good' === $mordenhost_mcp_worst ) {
	$mordenhost_mcp_banner_state = 'on';
	$mordenhost_mcp_banner_title = __( 'Your MCP server is ready', 'mordenhost-mcp' );
	$mordenhost_mcp_banner_sub   = __( 'Everything checks out. Connect a client using the URL below.', 'mordenhost-mcp' );
} else {
	$mordenhost_mcp_banner_state = 'attention';
	$mordenhost_mcp_banner_title = __( 'Your MCP server is on, with one thing to check', 'mordenhost-mcp' );
	$mordenhost_mcp_banner_sub   = __( 'The server is running, but a setup check below needs your attention.', 'mordenhost-mcp' );
}

$mordenhost_mcp_conn_btn = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
$mordenhost_mcp_conn_svg = 'width:14px;height:14px;flex-shrink:0;';
?>

<!-- ============================================================
     Status banner — the one prominent element on this panel
     ============================================================ -->
<div class="mmcp-ov-banner mmcp-ov-banner-<?php echo esc_attr( $mordenhost_mcp_banner_state ); ?>">
	<span class="mmcp-ov-banner-dot" aria-hidden="true"></span>
	<div class="mmcp-ov-banner-copy">
		<h3><?php echo esc_html( $mordenhost_mcp_banner_title ); ?></h3>
		<p><?php echo esc_html( $mordenhost_mcp_banner_sub ); ?></p>
	</div>
	<?php if ( ! $mordenhost_mcp_enabled ) : ?>
		<a class="button button-primary mmcp-ov-banner-cta"
		   href="<?php echo esc_url( add_query_arg( 'panel', 'permissions', $mordenhost_mcp_base_url_conn ) ); ?>">
			<?php esc_html_e( 'Turn it on', 'mordenhost-mcp' ); ?>
		</a>
	<?php endif; ?>
</div>

<!-- ============================================================
     Setup checks — one-time diagnostics, worst-first (audit #11)

     These keep the green-check style deliberately: they are point-in-time
     diagnostics ("is the server reachable, are permalinks right"), not live
     state. The blue toggle switches on Access mean something else ("this
     scope is on"), so the two signals must NOT converge — the caption below
     says which is which.
     ============================================================ -->
<div class="mmcp-ov-checks" role="group" aria-label="<?php esc_attr_e( 'Setup diagnostics', 'mordenhost-mcp' ); ?>">
	<p class="description mmcp-ov-checks-note"><?php esc_html_e( 'One-time checks, not live switches. A green check means that item passed when this page loaded.', 'mordenhost-mcp' ); ?></p>
	<?php foreach ( $mordenhost_mcp_checks as $mordenhost_mcp_c ) : ?>
		<div class="mmcp-ov-check mmcp-ov-check-<?php echo esc_attr( $mordenhost_mcp_c['state'] ); ?>">
			<span class="mmcp-ov-check-icon dashicons <?php
				echo esc_attr(
					'good' === $mordenhost_mcp_c['state'] ? 'dashicons-yes-alt'
					: ( 'warn' === $mordenhost_mcp_c['state'] ? 'dashicons-warning' : 'dashicons-dismiss' )
				);
			?>" aria-hidden="true"></span>
			<div class="mmcp-ov-check-copy">
				<span class="mmcp-ov-check-label"><?php echo esc_html( $mordenhost_mcp_c['label'] ); ?></span>
				<?php if ( ! empty( $mordenhost_mcp_c['detail'] ) ) : ?>
					<span class="mmcp-ov-check-detail"><?php echo esc_html( $mordenhost_mcp_c['detail'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $mordenhost_mcp_c['fix_url'] ) ) : ?>
				<a class="mmcp-ov-check-fix" href="<?php echo esc_url( $mordenhost_mcp_c['fix_url'] ); ?>">
					<?php echo esc_html( $mordenhost_mcp_c['fix_text'] ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<!-- ============================================================
     Connect a client — URL and API key in ONE column
     ============================================================

     These were two blocks: a bordered, accent-railed card for the URL, then a
     separate `.mmcp-section` for the API key below it. The border made them read
     as unrelated settings, when in fact they are the two halves of a single task —
     Claude.ai needs the URL alone, Claude Desktop needs the URL and the key. One
     admin's first action on this screen is to copy both.

     So they are now one field group under one heading, with the shared caveats
     stated once beneath. Nothing about what posts changed: the URL is still
     display-only with no `name`, and the key field and Regenerate submit are
     unchanged.
     ============================================================ -->
<div class="mmcp-connect-block">
	<h3 class="mmcp-connect-title"><?php esc_html_e( 'Connect a client', 'mordenhost-mcp' ); ?></h3>

	<div class="mmcp-connect-field">
		<label for="mcp-server-url"><?php esc_html_e( 'Server URL', 'mordenhost-mcp' ); ?></label>
		<div class="mcp-url-input-group">
			<input type="text"
			       id="mcp-server-url"
			       value="<?php echo esc_attr( $mordenhost_mcp_url_https ); ?>"
			       class="large-text code"
			       readonly>
			<button type="button" class="button button-primary copy-btn" data-target="mcp-server-url"
			        style="<?php echo esc_attr( $mordenhost_mcp_conn_btn ); ?>">
				<svg style="<?php echo esc_attr( $mordenhost_mcp_conn_svg ); ?>" viewBox="0 0 24 24" fill="none"
				     stroke="currentColor" stroke-width="2" stroke-linecap="round"
				     stroke-linejoin="round" aria-hidden="true">
					<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
					<rect x="8" y="2" width="8" height="4" rx="1"/>
				</svg>
				<?php esc_html_e( 'Copy', 'mordenhost-mcp' ); ?>
			</button>
		</div>
	</div>

	<div class="mmcp-connect-field">
		<label for="api_key"><?php esc_html_e( 'API key', 'mordenhost-mcp' ); ?></label>
		<div class="mmcp-key-row" data-live="masked">
			<?php  ?>
			<input type="password"
			       id="api_key"
			       value="<?php echo esc_attr( $mordenhost_mcp_api_preview ?? '' ); ?>"
			       class="regular-text code"
			       autocomplete="off"
			       readonly>
			<button type="button" class="button toggle-password" aria-label="<?php esc_attr_e( 'Show or hide the API key', 'mordenhost-mcp' ); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			</button>
			<button type="button" class="button" id="copy-api-key">
				<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				<?php esc_html_e( 'Copy', 'mordenhost-mcp' ); ?>
			</button>
			<button type="submit"
			        name="mordenhost_mcp_settings[regenerate_api_key]"
			        value="1"
			        class="button"
			        id="rmcp-regenerate-key">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Regenerate', 'mordenhost-mcp' ); ?>
			</button>
		</div>
	</div>

	<p class="mcp-url-hint">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Documentation panel, client setup tab */
				__( 'Claude.ai and ChatGPT need only the URL. Claude Desktop, Cursor, and REST calls also send the key. <a href="%s">Setup walkthroughs</a> show where each goes.', 'mordenhost-mcp' ),
				esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'setup' ], $mordenhost_mcp_base_url_conn ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</p>

	<?php if ( $mordenhost_mcp_is_localhost ) : ?>
		<div class="cloudflare-warning warning-error">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p>
				<strong><?php esc_html_e( 'This is a localhost URL.', 'mordenhost-mcp' ); ?></strong>
				<?php esc_html_e( 'Hosted clients need a public HTTPS address. Local testing only.', 'mordenhost-mcp' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="cloudflare-warning">
		<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'Behind Cloudflare?', 'mordenhost-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, troubleshooting tab */
					__( 'Its "Block AI Bots" rule stops the handshake. <a href="%s">Troubleshooting</a> has the fix.', 'mordenhost-mcp' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'troubleshooting' ], $mordenhost_mcp_base_url_conn ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<button type="button" class="advanced-toggle mmcp-inline-help-toggle"
	        id="mmcp-key-facts-toggle"
	        aria-expanded="false"
	        aria-controls="mmcp-key-facts-help">
		<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
		<?php esc_html_e( 'How the API key works', 'mordenhost-mcp' ); ?>
	</button>
	<div class="advanced-content" id="mmcp-key-facts-help" hidden>
		<ul class="mmcp-doc-facts mmcp-key-facts is-compact">
			<li>
				<strong><?php esc_html_e( 'How to send it', 'mordenhost-mcp' ); ?></strong>
				<?php echo wp_kses( __( 'As an <code>MMCP-Key</code> HTTP header on every request.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'What it can do', 'mordenhost-mcp' ); ?></strong>
				<?php esc_html_e( 'Acts as an administrator: unlike an OAuth grant it is not role-scoped. Treat it as an admin password.', 'mordenhost-mcp' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Regenerating', 'mordenhost-mcp' ); ?></strong>
				<?php esc_html_e( 'Immediate, no grace period. Every client on the old key breaks, and the old value is unrecoverable.', 'mordenhost-mcp' ); ?>
			</li>
		</ul>
	</div>
</div>

<!-- ============================================================
     Current state — live counts and environment, read-only
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'Right now', 'mordenhost-mcp' ); ?></h3>
	</div>

	<div class="mmcp-ov-stats">
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'sessions', 'view' => 'clients' ], $mordenhost_mcp_base_url_conn ) ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $mordenhost_mcp_grant_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Connected clients', 'mordenhost-mcp' ); ?></span>
		</a>
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'sessions', 'view' => 'transport' ], $mordenhost_mcp_base_url_conn ) ); ?>"
		   title="<?php esc_attr_e( 'Normal: one client opens a new session per conversation, so this is usually larger than the client count.', 'mordenhost-mcp' ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $mordenhost_mcp_session_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Open sessions', 'mordenhost-mcp' ); ?> <span class="dashicons dashicons-info-outline mmcp-ov-stat-hint" aria-hidden="true"></span></span>
		</a>
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'tools' ], $mordenhost_mcp_base_url_conn ) ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $mordenhost_mcp_tool_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Tools available', 'mordenhost-mcp' ); ?></span>
		</a>
	</div>

	<?php  ?>
	<p class="description mmcp-ov-version">
		<?php
		printf(
			/* translators: %s: plugin version number */
			esc_html__( 'Mordenhost MCP version %s', 'mordenhost-mcp' ),
			esc_html( MORDENHOST_MCP_VERSION )
		);
		?>
	</p>

	<ul class="mmcp-ov-facts">
		<li>
			<span class="mmcp-ov-fact-key"><?php esc_html_e( 'Protocol', 'mordenhost-mcp' ); ?></span>
			<code><?php echo esc_html( $mordenhost_mcp_protocol_version ); ?></code>
		</li>
		<li>
			<span class="mmcp-ov-fact-key"><?php esc_html_e( 'Environment', 'mordenhost-mcp' ); ?></span>
			<span><?php
				printf(
					/* translators: 1: WordPress version, 2: PHP version */
					esc_html__( 'WordPress %1$s · PHP %2$s', 'mordenhost-mcp' ),
					esc_html( get_bloginfo( 'version' ) ),
					esc_html( PHP_VERSION )
				);
			?></span>
		</li>
	</ul>

	<a class="mmcp-ov-card-link"
	   href="<?php echo esc_url( add_query_arg( 'panel', 'logs', admin_url( 'admin.php?page=mordenhost-mcp' ) ) ); ?>">
		<?php esc_html_e( 'View the Activity Log', 'mordenhost-mcp' ); ?>
		<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
	</a>
</div>

<!-- ============================================================
     Session policy — how long a connection stays valid
     ============================================================

     Migrated here from the Sessions panel. Session length is a CONNECTION
     policy, not a live-session view: it governs every token this server will
     issue, so it belongs with the other connection config rather than beside the
     table of who happens to be connected right now. Sessions is now a pure
     monitor. This is the same save-on-change `.mmcp-ttl-select` (id
     access_token_ttl_seconds); it is written by the mordenhost_mcp_set_ttl AJAX
     handler and is never form-owned, so moving the markup changes no wiring.
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'Session length', 'mordenhost-mcp' ); ?></h3>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="access_token_ttl_seconds"><?php esc_html_e( 'Stay connected for', 'mordenhost-mcp' ); ?></label>
			</th>
			<td>
				<?php
				$mordenhost_mcp_ttl_choices = [
					3600   => __( '1 hour', 'mordenhost-mcp' ),
					28800  => __( '8 hours', 'mordenhost-mcp' ),
					86400  => __( '24 hours (default)', 'mordenhost-mcp' ),
					604800 => __( '7 days', 'mordenhost-mcp' ),
				];
				$mordenhost_mcp_ttl_current = (int) ( $mordenhost_mcp_settings['access_token_ttl_seconds'] ?? \Mordenhost_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL );
				$mordenhost_mcp_ttl_locked = \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_settings', 'access_token_ttl_seconds' );
				if ( $mordenhost_mcp_ttl_locked ) {
					
					$mordenhost_mcp_ttl_current = \Mordenhost_MCP\OAuth\Token_Store::get_access_token_ttl();
					if ( ! array_key_exists( $mordenhost_mcp_ttl_current, $mordenhost_mcp_ttl_choices ) ) {
						$mordenhost_mcp_ttl_choices[ $mordenhost_mcp_ttl_current ] = sprintf(
							/* translators: %s: number of seconds */
							_n( '%s second', '%s seconds', $mordenhost_mcp_ttl_current, 'mordenhost-mcp' ),
							number_format_i18n( $mordenhost_mcp_ttl_current )
						);
					}
				} elseif ( ! array_key_exists( $mordenhost_mcp_ttl_current, $mordenhost_mcp_ttl_choices ) ) {
					$mordenhost_mcp_ttl_current = \Mordenhost_MCP\OAuth\Token_Store::ACCESS_TOKEN_TTL;
				}
				?>
				<select class="mmcp-ttl-select" id="access_token_ttl_seconds" data-current="<?php echo esc_attr( $mordenhost_mcp_ttl_current ); ?>" <?php disabled( $mordenhost_mcp_ttl_locked ); ?>>
					<?php foreach ( $mordenhost_mcp_ttl_choices as $mordenhost_mcp_seconds => $mordenhost_mcp_label ) : ?>
						<option value="<?php echo esc_attr( $mordenhost_mcp_seconds ); ?>" <?php selected( $mordenhost_mcp_ttl_current, $mordenhost_mcp_seconds ); ?>>
							<?php echo esc_html( $mordenhost_mcp_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_settings', 'access_token_ttl_seconds' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
				<p class="description">
					<?php esc_html_e( 'How long an access token stays valid before the client has to refresh it. Shorter is tighter but means more refresh traffic; clients handle refreshes on their own either way, so this is not a re-authorization interval.', 'mordenhost-mcp' ); ?>
				</p>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: URL of the Sessions panel, connected-clients view */
							__( 'Applies to newly issued tokens only. Tokens already issued keep their original expiry; disconnect a client from <a href="%s">Sessions</a> to have it re-issued at the new length immediately.', 'mordenhost-mcp' ),
							esc_url( add_query_arg( [ 'panel' => 'sessions', 'view' => 'clients' ], $mordenhost_mcp_base_url_conn ) )
						),
						[ 'a' => [ 'href' => [] ] ]
					);
					?>
				</p>
			</td>
		</tr>
	</table>
</div>

<!-- ============================================================
     Advanced — manual OAuth credentials, titled and explained
     ============================================================ -->
<div class="mmcp-advanced-section<?php echo $mordenhost_mcp_has_manual_oauth ? ' is-open' : ''; ?>">
	<button type="button"
	        class="mmcp-advanced-header advanced-toggle<?php echo $mordenhost_mcp_has_manual_oauth ? ' open' : ''; ?>"
	        aria-expanded="<?php echo $mordenhost_mcp_has_manual_oauth ? 'true' : 'false'; ?>"
	        aria-controls="mmcp-advanced-connection">
		<span class="dashicons dashicons-arrow-down-alt2 mmcp-advanced-chevron" aria-hidden="true"></span>
		<span class="mmcp-advanced-title">
			<?php esc_html_e( 'Advanced', 'mordenhost-mcp' ); ?>
			<small>
				<?php esc_html_e( 'Manual OAuth credentials. Nothing here is required. Skip this section unless a specific client has told you it needs one of these values.', 'mordenhost-mcp' ); ?>
			</small>
		</span>
		<?php if ( $mordenhost_mcp_has_manual_oauth ) : ?>
			<span class="mmcp-flag mmcp-flag-info"><?php esc_html_e( 'in use', 'mordenhost-mcp' ); ?></span>
		<?php endif; ?>
	</button>

	<div class="mmcp-advanced-body advanced-content"
	     id="mmcp-advanced-connection"
	     <?php echo $mordenhost_mcp_has_manual_oauth ? '' : 'hidden'; ?>>

		<h4><?php esc_html_e( 'Manual OAuth credentials', 'mordenhost-mcp' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Leave both fields empty in almost every case. MCP clients register themselves through Dynamic Client Registration, which is why connecting Claude.ai takes nothing but the URL. Setting a static client ID here switches that off and every client then has to be configured with these exact values.', 'mordenhost-mcp' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="oauth_client_id">
						<?php esc_html_e( 'OAuth Client ID', 'mordenhost-mcp' ); ?>
						<span class="optional">(<?php esc_html_e( 'optional', 'mordenhost-mcp' ); ?>)</span>
					</label>
				</th>
				<td>
					<input type="text"
					       name="mordenhost_mcp_settings[oauth_client_id]"
					       id="oauth_client_id"
					       value="<?php echo esc_attr( $mordenhost_mcp_settings['oauth_client_id'] ?? '' ); ?>"
					       class="regular-text code"
					       placeholder="<?php esc_attr_e( 'Empty. Clients register automatically', 'mordenhost-mcp' ); ?>">
					<button type="button" class="button copy-btn" data-target="oauth_client_id"
					        aria-label="<?php esc_attr_e( 'Copy client ID', 'mordenhost-mcp' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					</button>
					<?php if ( empty( $mordenhost_mcp_settings['oauth_client_id'] ) ) : ?>
						<button type="button" class="button generate-oauth" data-field="oauth_client_id">
							<?php esc_html_e( 'Generate', 'mordenhost-mcp' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button clear-oauth" data-field="oauth_client_id">
							<?php esc_html_e( 'Clear', 'mordenhost-mcp' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="oauth_client_secret">
						<?php esc_html_e( 'OAuth Client Secret', 'mordenhost-mcp' ); ?>
						<span class="optional">(<?php esc_html_e( 'optional', 'mordenhost-mcp' ); ?>)</span>
					</label>
				</th>
				<td>
					<input type="password"
					       name="mordenhost_mcp_settings[oauth_client_secret]"
					       id="oauth_client_secret"
					       value="<?php echo esc_attr( $mordenhost_mcp_settings['oauth_client_secret'] ?? '' ); ?>"
					       class="regular-text code"
					       autocomplete="new-password"
					       placeholder="<?php esc_attr_e( 'Empty. Clients register automatically', 'mordenhost-mcp' ); ?>">
					<button type="button" class="button toggle-password"
					        aria-label="<?php esc_attr_e( 'Show or hide the client secret', 'mordenhost-mcp' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
					<button type="button" class="button copy-btn" data-target="oauth_client_secret"
					        aria-label="<?php esc_attr_e( 'Copy client secret', 'mordenhost-mcp' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					</button>
					<?php if ( empty( $mordenhost_mcp_settings['oauth_client_secret'] ) ) : ?>
						<button type="button" class="button generate-oauth" data-field="oauth_client_secret">
							<?php esc_html_e( 'Generate', 'mordenhost-mcp' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button clear-oauth" data-field="oauth_client_secret">
							<?php esc_html_e( 'Clear', 'mordenhost-mcp' ); ?>
						</button>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Only meaningful alongside a client ID. Leave blank unless the client requires a confidential client.', 'mordenhost-mcp' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="description">
			<?php esc_html_e( 'Clearing a field writes the change immediately rather than waiting for Save, because an empty submission on this form is treated as "keep the current value". That guard protects against accidental blanking, and the Clear button is the deliberate way around it.', 'mordenhost-mcp' ); ?>
		</p>

		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, REST API tab */
					__( 'Looking for the legacy per-endpoint REST base URL? It moved to the <a href="%s">REST API reference</a>, alongside the framing for when to use it.', 'mordenhost-mcp' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'api' ], $mordenhost_mcp_base_url_conn ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>

		<!-- ============================================================
		     Recovery — bulk, credential-scoped actions
		     ============================================================

		     Migrated from the Sessions panel. Both actions reset auth state, so
		     they belong beside the OAuth credentials they act on rather than in
		     the live-session monitor. Kept inside the collapsed Advanced region so
		     routine users never trip over them, but present for incident response.
		     The button IDs (mordenhost-mcp-revoke-all-sessions, mordenhost-mcp-reset-oauth-state)
		     and their AJAX handlers are unchanged; only the markup location moved.
		     ============================================================ -->
		<div class="mmcp-section mmcp-section-danger">
			<div class="mmcp-section-head">
				<h4><?php esc_html_e( 'Recovery actions', 'mordenhost-mcp' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Both actions below affect every connected client at once. To disconnect a single client, use the per-row control on the Sessions panel instead.', 'mordenhost-mcp' ); ?>
				</p>
			</div>

			<div class="mmcp-danger-action">
				<div class="mmcp-danger-copy">
					<h4><?php esc_html_e( 'Revoke all active sessions', 'mordenhost-mcp' ); ?></h4>
					<p class="description">
						<?php esc_html_e( 'Revokes every issued token, so every OAuth client must authorize again. Registered clients and all settings are preserved; only the tokens are invalidated. Use it during incident response, or to force every client onto a newly shortened session length at once.', 'mordenhost-mcp' ); ?>
					</p>
				</div>
				<div class="mmcp-danger-control">
					<?php

					
					$mordenhost_mcp_btn_style = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
					$mordenhost_mcp_svg_style = 'width:14px;height:14px;flex-shrink:0;';
					?>
					<button type="button"
					        class="button button-secondary"
					        id="mordenhost-mcp-revoke-all-sessions"
					        style="<?php echo esc_attr( $mordenhost_mcp_btn_style ); ?>">
						<svg style="<?php echo esc_attr( $mordenhost_mcp_svg_style ); ?>" viewBox="0 0 24 24" fill="none"
						     stroke="currentColor" stroke-width="2" stroke-linecap="round"
						     stroke-linejoin="round" aria-hidden="true">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
						</svg>
						<?php esc_html_e( 'Revoke all', 'mordenhost-mcp' ); ?>
					</button>
					<span id="mordenhost-mcp-revoke-all-sessions-status" class="mmcp-inline-status"></span>
				</div>
			</div>

			<div class="mmcp-danger-action">
				<div class="mmcp-danger-copy">
					<h4><?php esc_html_e( 'Reset OAuth state', 'mordenhost-mcp' ); ?></h4>
					<p class="description">
						<?php esc_html_e( 'Deletes all registered OAuth clients, issued tokens, and pending authorization codes. Your settings, API key, and Activity Log are not affected.', 'mordenhost-mcp' ); ?>
					</p>
					<p class="description warning-text">
						<strong><?php esc_html_e( 'Note:', 'mordenhost-mcp' ); ?></strong>
						<?php esc_html_e( 'Every connected client must be removed and re-added afterwards, and any manually configured OAuth client ID and secret above are cleared so the connector falls back to automatic registration. Use it for a connector stuck mid-handshake; Troubleshooting explains when.', 'mordenhost-mcp' ); ?>
					</p>
				</div>
				<div class="mmcp-danger-control">
					<?php
					
					$mordenhost_mcp_btn_style_reset = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
					$mordenhost_mcp_svg_style_reset = 'width:14px;height:14px;flex-shrink:0;';
					?>
					<button type="button"
					        class="button button-secondary"
					        id="mordenhost-mcp-reset-oauth-state"
					        style="<?php echo esc_attr( $mordenhost_mcp_btn_style_reset ); ?>">
						<svg style="<?php echo esc_attr( $mordenhost_mcp_svg_style_reset ); ?>" viewBox="0 0 24 24" fill="none"
						     stroke="currentColor" stroke-width="2" stroke-linecap="round"
						     stroke-linejoin="round" aria-hidden="true">
							<polyline points="3 6 5 6 21 6"/>
							<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
							<path d="M10 11v6"/>
							<path d="M14 11v6"/>
							<path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
						</svg>
						<?php esc_html_e( 'Reset OAuth state', 'mordenhost-mcp' ); ?>
					</button>
					<span id="mordenhost-mcp-reset-oauth-state-status" class="mmcp-inline-status"></span>
				</div>
			</div>
		</div>

	</div>
</div>

