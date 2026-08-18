<?php
/**
 * Connection panel — the settings landing screen.
 *
 * This is the first panel an admin sees. It absorbed the former standalone
 * Overview panel, which duplicated the server URL and API key: there is now one
 * destination that answers "is it working, how do I connect, what is connected"
 * and also carries the connection-level settings (API key, manual OAuth).
 *
 * The panel renders INSIDE the Settings API form (unlike the old read-only
 * Overview). Everything above the API key — the status banner, setup-check chips,
 * the readonly URL, and the live counts — is display-only markup with no `name`
 * attribute, so it never posts. The only inputs that post are the API key field,
 * the manual OAuth credentials, and the Regenerate submit.
 *
 * Content order, worst-first then most-used-first:
 *   1. Status banner (enabled / attention / off)
 *   2. Setup checks (server on, permalinks, public HTTPS)
 *   3. Connect a client (URL + API key + client cues)
 *   4. Current state (live counts + environment facts)
 *   5. Advanced: manual OAuth credentials (collapsed unless in use)
 *
 * Rendered by templates/admin/settings.php, which owns the $more_mcp_*
 * variables used below ($more_mcp_enabled, the grant/session/tool counts, the
 * URL and localhost flag, the protocol version). PHP `use` statements are
 * file-scoped and are NOT inherited across require(), so any namespaced class
 * this partial touches must be imported or fully qualified here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_base_url_conn = admin_url( 'admin.php?page=more-mcp' );

// Whether either manual OAuth credential is set. When one is, the Advanced
// section is expanded on load: a static credential changes how the handshake
// behaves, and hiding that behind a collapsed toggle makes it invisible to
// whoever inherits the site.
$more_mcp_has_manual_oauth = ! empty( $more_mcp_settings['oauth_client_id'] )
	|| ! empty( $more_mcp_settings['oauth_client_secret'] );

/**
 * Setup checks. Each is a plain assertion about the environment an admin can act
 * on, ordered worst-first by how commonly it blocks a first connection, matching
 * the ordering in docs/troubleshooting.php. `state` is good | warn | bad; a
 * `fix_url` is shown only when there is something on this site to click.
 */
$more_mcp_checks = [];

$more_mcp_checks[] = $more_mcp_enabled
	? [ 'state' => 'good', 'label' => __( 'MCP server is on', 'more-mcp' ) ]
	: [
		'state'    => 'bad',
		'label'    => __( 'MCP server is off', 'more-mcp' ),
		'detail'   => __( 'Clients cannot connect until you enable it.', 'more-mcp' ),
		'fix_url'  => add_query_arg( 'panel', 'permissions', $more_mcp_base_url_conn ),
		'fix_text' => __( 'Enable', 'more-mcp' ),
	];

$more_mcp_permalinks_ok = '' !== (string) get_option( 'permalink_structure', '' );
$more_mcp_checks[]      = $more_mcp_permalinks_ok
	? [ 'state' => 'good', 'label' => __( 'Permalinks support OAuth', 'more-mcp' ) ]
	: [
		'state'    => 'warn',
		'label'    => __( 'Plain permalinks block OAuth', 'more-mcp' ),
		'detail'   => __( 'Claude.ai and ChatGPT cannot complete the handshake. API-key clients still work.', 'more-mcp' ),
		'fix_url'  => admin_url( 'options-permalink.php' ),
		'fix_text' => __( 'Change permalinks', 'more-mcp' ),
	];

$more_mcp_checks[] = $more_mcp_is_localhost
	? [
		'state'  => 'warn',
		'label'  => __( 'Site is on localhost', 'more-mcp' ),
		'detail' => __( 'Hosted clients need a public HTTPS address. Fine for local testing only.', 'more-mcp' ),
	]
	: [ 'state' => 'good', 'label' => __( 'Public HTTPS address', 'more-mcp' ) ];

// Worst state drives the banner tone: bad > warn > good.
$more_mcp_worst = 'good';
foreach ( $more_mcp_checks as $more_mcp_c ) {
	if ( 'bad' === $more_mcp_c['state'] ) { $more_mcp_worst = 'bad'; break; }
	if ( 'warn' === $more_mcp_c['state'] ) { $more_mcp_worst = 'warn'; }
}

if ( ! $more_mcp_enabled ) {
	$more_mcp_banner_state = 'off';
	$more_mcp_banner_title = __( 'Your MCP server is off', 'more-mcp' );
	$more_mcp_banner_sub   = __( 'AI clients cannot connect. Turn it on from Permissions when you are ready.', 'more-mcp' );
} elseif ( 'good' === $more_mcp_worst ) {
	$more_mcp_banner_state = 'on';
	$more_mcp_banner_title = __( 'Your MCP server is ready', 'more-mcp' );
	$more_mcp_banner_sub   = __( 'Everything checks out. Connect a client using the URL below.', 'more-mcp' );
} else {
	$more_mcp_banner_state = 'attention';
	$more_mcp_banner_title = __( 'Your MCP server is on, with one thing to check', 'more-mcp' );
	$more_mcp_banner_sub   = __( 'The server is running, but a setup check below needs your attention.', 'more-mcp' );
}

// Inline SVG style shared by the copy buttons — per CLAUDE.md rule 8.
$more_mcp_conn_btn = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
$more_mcp_conn_svg = 'width:14px;height:14px;flex-shrink:0;';
?>

<!-- ============================================================
     Status banner — the one prominent element on this panel
     ============================================================ -->
<div class="mmcp-ov-banner mmcp-ov-banner-<?php echo esc_attr( $more_mcp_banner_state ); ?>">
	<span class="mmcp-ov-banner-dot" aria-hidden="true"></span>
	<div class="mmcp-ov-banner-copy">
		<h3><?php echo esc_html( $more_mcp_banner_title ); ?></h3>
		<p><?php echo esc_html( $more_mcp_banner_sub ); ?></p>
	</div>
	<?php if ( ! $more_mcp_enabled ) : ?>
		<a class="button button-primary mmcp-ov-banner-cta"
		   href="<?php echo esc_url( add_query_arg( 'panel', 'permissions', $more_mcp_base_url_conn ) ); ?>">
			<?php esc_html_e( 'Turn it on', 'more-mcp' ); ?>
		</a>
	<?php endif; ?>
</div>

<!-- ============================================================
     Setup checks — actionable chips, worst-first
     ============================================================ -->
<div class="mmcp-ov-checks">
	<?php foreach ( $more_mcp_checks as $more_mcp_c ) : ?>
		<div class="mmcp-ov-check mmcp-ov-check-<?php echo esc_attr( $more_mcp_c['state'] ); ?>">
			<span class="mmcp-ov-check-icon dashicons <?php
				echo esc_attr(
					'good' === $more_mcp_c['state'] ? 'dashicons-yes-alt'
					: ( 'warn' === $more_mcp_c['state'] ? 'dashicons-warning' : 'dashicons-dismiss' )
				);
			?>" aria-hidden="true"></span>
			<div class="mmcp-ov-check-copy">
				<span class="mmcp-ov-check-label"><?php echo esc_html( $more_mcp_c['label'] ); ?></span>
				<?php if ( ! empty( $more_mcp_c['detail'] ) ) : ?>
					<span class="mmcp-ov-check-detail"><?php echo esc_html( $more_mcp_c['detail'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $more_mcp_c['fix_url'] ) ) : ?>
				<a class="mmcp-ov-check-fix" href="<?php echo esc_url( $more_mcp_c['fix_url'] ); ?>">
					<?php echo esc_html( $more_mcp_c['fix_text'] ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<!-- ============================================================
     Connect a client — the URL and the API key, together
     ============================================================ -->
<div class="mcp-url-block">
	<label for="mcp-server-url" class="mcp-url-label">
		<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
		<?php esc_html_e( 'MCP Server URL', 'more-mcp' ); ?>
	</label>
	<div class="mcp-url-input-group">
		<input type="text"
		       id="mcp-server-url"
		       value="<?php echo esc_attr( $more_mcp_url_https ); ?>"
		       class="large-text code"
		       readonly>
		<button type="button" class="button button-primary copy-btn" data-target="mcp-server-url"
		        style="<?php echo esc_attr( $more_mcp_conn_btn ); ?>">
			<svg style="<?php echo esc_attr( $more_mcp_conn_svg ); ?>" viewBox="0 0 24 24" fill="none"
			     stroke="currentColor" stroke-width="2" stroke-linecap="round"
			     stroke-linejoin="round" aria-hidden="true">
				<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
				<rect x="8" y="2" width="8" height="4" rx="1"/>
			</svg>
			<?php esc_html_e( 'Copy', 'more-mcp' ); ?>
		</button>
	</div>
	<p class="mcp-url-hint">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Documentation panel, client setup tab */
				__( 'One URL for every client: Claude.ai and ChatGPT connect with just this, Claude Desktop and Cursor also send the API key below. <a href="%s">Setup walkthroughs</a> cover where to paste it in each one.', 'more-mcp' ),
				esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'setup' ], $more_mcp_base_url_conn ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</p>

	<?php if ( $more_mcp_is_localhost ) : ?>
		<div class="cloudflare-warning warning-error">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p>
				<strong><?php esc_html_e( 'This is a localhost URL.', 'more-mcp' ); ?></strong>
				<?php esc_html_e( 'MCP clients need a publicly reachable HTTPS address, so this only works for local testing. Deploy with SSL before connecting a hosted client.', 'more-mcp' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="cloudflare-warning">
		<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'Behind Cloudflare?', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, troubleshooting tab */
					__( 'Its "Block AI Bots" rule stops the handshake. <a href="%s">Troubleshooting</a> has the fix.', 'more-mcp' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'troubleshooting' ], $more_mcp_base_url_conn ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>
</div>

<!-- ============================================================
     API key — the one form field most connect flows need
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'API key', 'more-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Only needed by clients that cannot run an OAuth handshake, such as Claude Desktop, Cursor, Postman, and direct REST calls. Claude.ai and ChatGPT negotiate their own credentials and never use this value.', 'more-mcp' ); ?>
		</p>
	</div>

	<div class="mmcp-key-row">
		<input type="password"
		       name="more_mcp_settings[api_key]"
		       id="api_key"
		       value="<?php echo esc_attr( $more_mcp_settings['api_key'] ?? '' ); ?>"
		       class="regular-text code"
		       autocomplete="off"
		       readonly>
		<button type="button" class="button toggle-password" aria-label="<?php esc_attr_e( 'Show or hide the API key', 'more-mcp' ); ?>">
			<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
		</button>
		<button type="button" class="button" id="copy-api-key">
			<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
			<?php esc_html_e( 'Copy', 'more-mcp' ); ?>
		</button>
		<button type="submit"
		        name="more_mcp_settings[regenerate_api_key]"
		        value="1"
		        class="button"
		        id="rmcp-regenerate-key">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Regenerate', 'more-mcp' ); ?>
		</button>
	</div>

	<button type="button" class="advanced-toggle mmcp-inline-help-toggle"
	        id="mmcp-key-facts-toggle"
	        aria-expanded="false"
	        aria-controls="mmcp-key-facts-help">
		<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
		<?php esc_html_e( 'How this key works', 'more-mcp' ); ?>
	</button>
	<div class="advanced-content" id="mmcp-key-facts-help" hidden>
		<ul class="mmcp-doc-facts mmcp-key-facts">
			<li>
				<strong><?php esc_html_e( 'How to send it', 'more-mcp' ); ?></strong>
				<?php echo wp_kses( __( 'As an <code>MMCP-Key</code> HTTP header on every request.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'What it can do', 'more-mcp' ); ?></strong>
				<?php esc_html_e( 'A request authenticated with this key acts as an administrator. It is not scoped to a role the way an OAuth grant is, so treat it as an admin password.', 'more-mcp' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Regenerating', 'more-mcp' ); ?></strong>
				<?php esc_html_e( 'Takes effect immediately and breaks every client still configured with the old key. There is no grace period and the previous value is not recoverable.', 'more-mcp' ); ?>
			</li>
		</ul>
	</div>
</div>

<!-- ============================================================
     Current state — live counts and environment, read-only
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'Right now', 'more-mcp' ); ?></h3>
	</div>

	<div class="mmcp-ov-stats">
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'sessions', 'view' => 'clients' ], $more_mcp_base_url_conn ) ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $more_mcp_grant_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Connected clients', 'more-mcp' ); ?></span>
		</a>
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'sessions', 'view' => 'transport' ], $more_mcp_base_url_conn ) ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $more_mcp_session_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Open sessions', 'more-mcp' ); ?></span>
		</a>
		<a class="mmcp-ov-stat" href="<?php echo esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'tools' ], $more_mcp_base_url_conn ) ); ?>">
			<span class="mmcp-ov-stat-num"><?php echo esc_html( number_format_i18n( $more_mcp_tool_count ) ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Tools available', 'more-mcp' ); ?></span>
		</a>
		<div class="mmcp-ov-stat is-static">
			<span class="mmcp-ov-stat-num mmcp-ov-stat-version"><?php echo esc_html( MORE_MCP_VERSION ); ?></span>
			<span class="mmcp-ov-stat-label"><?php esc_html_e( 'Plugin version', 'more-mcp' ); ?></span>
		</div>
	</div>

	<ul class="mmcp-ov-facts">
		<li>
			<span class="mmcp-ov-fact-key"><?php esc_html_e( 'Protocol', 'more-mcp' ); ?></span>
			<code><?php echo esc_html( $more_mcp_protocol_version ); ?></code>
		</li>
		<li>
			<span class="mmcp-ov-fact-key"><?php esc_html_e( 'Environment', 'more-mcp' ); ?></span>
			<span><?php
				printf(
					/* translators: 1: WordPress version, 2: PHP version */
					esc_html__( 'WordPress %1$s · PHP %2$s', 'more-mcp' ),
					esc_html( get_bloginfo( 'version' ) ),
					esc_html( PHP_VERSION )
				);
			?></span>
		</li>
	</ul>

	<a class="mmcp-ov-card-link"
	   href="<?php echo esc_url( admin_url( 'admin.php?page=more-mcp-logs' ) ); ?>">
		<?php esc_html_e( 'View the Activity Log', 'more-mcp' ); ?>
		<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
	</a>
</div>

<!-- ============================================================
     Advanced — manual OAuth credentials, titled and explained
     ============================================================ -->
<div class="mmcp-advanced-section<?php echo $more_mcp_has_manual_oauth ? ' is-open' : ''; ?>">
	<button type="button"
	        class="mmcp-advanced-header advanced-toggle<?php echo $more_mcp_has_manual_oauth ? ' open' : ''; ?>"
	        aria-expanded="<?php echo $more_mcp_has_manual_oauth ? 'true' : 'false'; ?>"
	        aria-controls="mmcp-advanced-connection">
		<span class="dashicons dashicons-arrow-down-alt2 mmcp-advanced-chevron" aria-hidden="true"></span>
		<span class="mmcp-advanced-title">
			<?php esc_html_e( 'Advanced', 'more-mcp' ); ?>
			<small>
				<?php esc_html_e( 'Manual OAuth credentials. Nothing here is required. Skip this section unless a specific client has told you it needs one of these values.', 'more-mcp' ); ?>
			</small>
		</span>
		<?php if ( $more_mcp_has_manual_oauth ) : ?>
			<span class="mmcp-flag mmcp-flag-info"><?php esc_html_e( 'in use', 'more-mcp' ); ?></span>
		<?php endif; ?>
	</button>

	<div class="mmcp-advanced-body advanced-content"
	     id="mmcp-advanced-connection"
	     <?php echo $more_mcp_has_manual_oauth ? '' : 'hidden'; ?>>

		<h4><?php esc_html_e( 'Manual OAuth credentials', 'more-mcp' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Leave both fields empty in almost every case. MCP clients register themselves through Dynamic Client Registration, which is why connecting Claude.ai takes nothing but the URL. Setting a static client ID here switches that off and every client then has to be configured with these exact values.', 'more-mcp' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="oauth_client_id">
						<?php esc_html_e( 'OAuth Client ID', 'more-mcp' ); ?>
						<span class="optional">(<?php esc_html_e( 'optional', 'more-mcp' ); ?>)</span>
					</label>
				</th>
				<td>
					<input type="text"
					       name="more_mcp_settings[oauth_client_id]"
					       id="oauth_client_id"
					       value="<?php echo esc_attr( $more_mcp_settings['oauth_client_id'] ?? '' ); ?>"
					       class="regular-text code"
					       placeholder="<?php esc_attr_e( 'Empty. Clients register automatically', 'more-mcp' ); ?>">
					<button type="button" class="button copy-btn" data-target="oauth_client_id"
					        aria-label="<?php esc_attr_e( 'Copy client ID', 'more-mcp' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					</button>
					<?php if ( empty( $more_mcp_settings['oauth_client_id'] ) ) : ?>
						<button type="button" class="button generate-oauth" data-field="oauth_client_id">
							<?php esc_html_e( 'Generate', 'more-mcp' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button clear-oauth" data-field="oauth_client_id">
							<?php esc_html_e( 'Clear', 'more-mcp' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="oauth_client_secret">
						<?php esc_html_e( 'OAuth Client Secret', 'more-mcp' ); ?>
						<span class="optional">(<?php esc_html_e( 'optional', 'more-mcp' ); ?>)</span>
					</label>
				</th>
				<td>
					<input type="password"
					       name="more_mcp_settings[oauth_client_secret]"
					       id="oauth_client_secret"
					       value="<?php echo esc_attr( $more_mcp_settings['oauth_client_secret'] ?? '' ); ?>"
					       class="regular-text code"
					       autocomplete="new-password"
					       placeholder="<?php esc_attr_e( 'Empty. Clients register automatically', 'more-mcp' ); ?>">
					<button type="button" class="button toggle-password"
					        aria-label="<?php esc_attr_e( 'Show or hide the client secret', 'more-mcp' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
					<button type="button" class="button copy-btn" data-target="oauth_client_secret"
					        aria-label="<?php esc_attr_e( 'Copy client secret', 'more-mcp' ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					</button>
					<?php if ( empty( $more_mcp_settings['oauth_client_secret'] ) ) : ?>
						<button type="button" class="button generate-oauth" data-field="oauth_client_secret">
							<?php esc_html_e( 'Generate', 'more-mcp' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button clear-oauth" data-field="oauth_client_secret">
							<?php esc_html_e( 'Clear', 'more-mcp' ); ?>
						</button>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Only meaningful alongside a client ID. Leave blank unless the client requires a confidential client.', 'more-mcp' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="description">
			<?php esc_html_e( 'Clearing a field writes the change immediately rather than waiting for Save, because an empty submission on this form is treated as "keep the current value". That guard protects against accidental blanking, and the Clear button is the deliberate way around it.', 'more-mcp' ); ?>
		</p>

		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, REST API tab */
					__( 'Looking for the legacy per-endpoint REST base URL? It moved to the <a href="%s">REST API reference</a>, alongside the framing for when to use it.', 'more-mcp' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'api' ], $more_mcp_base_url_conn ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>

	</div>
</div>

