<?php
/**
 * Documentation → Troubleshooting.
 *
 * New in the Documentation consolidation. Previously this knowledge lived in
 * three disconnected places: warning callouts on the Connection panel, the
 * admin notices raised by Admin\Well_Known_Notice, and support replies. Only the
 * first two were discoverable, and only while the specific condition was
 * detected — an admin whose connection failed for an undetected reason had
 * nothing on the screen to work from.
 *
 * The order below is roughly the order in which these actually cause failures in
 * the field, not the order in which they are easy to explain.
 *
 * Rendered by templates/admin/settings/panel-docs.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_ts_permalinks = get_option( 'permalink_structure' );
$more_mcp_ts_plain      = empty( $more_mcp_ts_permalinks );
$more_mcp_ts_base       = admin_url( 'admin.php?page=more-mcp' );
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'Nearly every failed connection comes down to something between the client and WordPress, such as a CDN rule, a security layer, or a permalink setting, rather than to More MCP itself. Work down this list in order; the first three account for most reports.', 'more-mcp' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'More MCP also probes its own OAuth endpoints on a schedule and raises an admin notice when it detects a specific known cause. An absent notice is not proof that nothing is wrong; the probe only recognizes conditions it knows how to name.', 'more-mcp' ); ?>
	</p>
</div>

<?php if ( $more_mcp_ts_plain ) : ?>
	<div class="cloudflare-warning warning-error">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'This site is using Plain permalinks right now.', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the WordPress permalink settings screen */
					__( 'OAuth cannot work until that is changed, because the OAuth endpoints are served from the domain root by rewrite rules and rewrite rules do not run on Plain. Choose any other option under <a href="%s">Settings → Permalinks</a>.', 'more-mcp' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>
<?php endif; ?>

<div class="mmcp-troubleshoot-list">

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'The site is not on a public HTTPS address', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'Hosted MCP backends reach into your site over the public internet, so a localhost URL, a LAN IP, or a plain-HTTP address will never complete a connection from Claude.ai or ChatGPT. A local bridge client (Claude Desktop, Cursor) can still reach a local site, but the hosted connectors cannot.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Deploy the site to a public domain with a valid SSL certificate before connecting a hosted client. There is no plugin setting that can substitute for a reachable HTTPS address; the Connection panel flags this when it detects a localhost URL.', 'more-mcp' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Cloudflare is blocking AI bots', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'Cloudflare enables "Block AI Bots" by default on new domains, and it blocks every MCP backend, including Claude and ChatGPT, from completing the handshake. The connection usually fails with a generic error that gives no hint the CDN is involved.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'In the Cloudflare dashboard, turn off "Block AI Bots" under Security → Bots. If you would rather keep it on, create an exception for your MCP endpoint path instead of disabling it site-wide.', 'more-mcp' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Plain permalinks', 'more-mcp' ); ?></h3>
		<p>
			<?php echo wp_kses( __( 'The OAuth endpoints (<code>/authorize</code>, <code>/token</code>, <code>/register</code>, and the two <code>.well-known</code> documents) are served from the domain root through WordPress rewrite rules. On Plain permalinks those rules never fire, so the client\'s discovery request 404s and the handshake stops there.', 'more-mcp' ), [ 'code' => [] ] ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the WordPress permalink settings screen */
					__( 'Switch to any non-Plain structure under <a href="%s">Settings → Permalinks</a>. Saving that screen also flushes the rewrite rules.', 'more-mcp' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'The host intercepts .well-known or /register', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'Several hosting layers claim these paths before WordPress sees the request. Imunify360 on shared cPanel hosts and BitNinja WebShield both do it; SiteGround reserves .well-known for its own use; and some servers 301-redirect /register to /register/, which OAuth clients do not follow on POST.', 'more-mcp' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'A membership or maintenance-mode plugin can cause the same symptom from inside WordPress by serving an HTML page where the client expects JSON.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Open the two .well-known URLs listed on the REST API reference tab in a private browser window. If either returns HTML, a 404, or a challenge page instead of JSON, the cause is above WordPress and your host has to allowlist those paths. No plugin setting can work around it.', 'more-mcp' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'A stuck connector that will not finish authorizing', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'A handshake interrupted partway through can leave a registered client and a pending authorization code that no longer match what the client believes it holds. Retrying from the client side then fails the same way every time, because the stale server-side state is what is wrong.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Sessions panel */
					__( 'Run <a href="%s">Reset OAuth State</a> on the Sessions panel, then remove and re-add the connector in the client. Your settings, API key, and Activity Log are not affected, but every other connected client will need to re-authorize.', 'more-mcp' ),
					esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Clients report "Session not found" repeatedly', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'Sessions are stored in a database table rather than in transients, specifically so that an object-cache drop-in evicting keys between requests cannot break them. If clients still loop on session errors, the session rows themselves are the thing to clear.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Sessions panel */
					__( 'Use <a href="%s">End all sessions</a> on the Sessions panel. That clears transport state without revoking any credentials, so clients reconnect on their own without re-authorizing.', 'more-mcp' ),
					esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'The client connects but shows no tools', 'more-mcp' ); ?></h3>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Permissions panel */
					__( 'First check that the master switch is on. <a href="%s">Permissions</a> shows the current state, and while it is off the server answers discovery but refuses everything else. If it is on, the next suspect is tool-list size: some clients silently drop a list they consider too large.', 'more-mcp' ),
					esc_url( add_query_arg( 'panel', 'permissions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, What agents can do tab */
					__( 'Request a trimmed tool profile with a URL parameter. See <a href="%s">What agents can do</a> for the available profiles and what each one sends.', 'more-mcp' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'tools' ], $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'A write fails with a permission error', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'Authorization has two layers, and they fail with similar-looking messages. A connector authorized through OAuth acts as the WordPress user who authorized it, so it cannot exceed that user\'s capabilities. Separately, option writes, theme changes, and plugin management are each gated by their own toggle regardless of capability.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Permissions panel */
					__( 'Check the relevant toggle on <a href="%s">Permissions</a> first; it is the more common cause. If the toggle is already on, re-authorize the connector as a user who holds the capability the operation needs.', 'more-mcp' ),
					esc_url( add_query_arg( 'panel', 'permissions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Requests fail intermittently under load', 'more-mcp' ); ?></h3>
		<p>
			<?php esc_html_e( 'The MCP endpoint allows 60 requests per 60 seconds per IP address and returns HTTP 429 beyond that. An agent working through a long batch can hit this, and because the limit is per-IP, several clients behind one office network share the same budget.', 'more-mcp' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Check the Activity Log for the failing window. If the pattern is a genuine burst rather than a runaway loop, spread the work out; the limit is deliberate protection for the site.', 'more-mcp' ); ?>
		</p>
	</div>

</div>

<h3><?php esc_html_e( 'Where to look next', 'more-mcp' ); ?></h3>
<ul class="mmcp-doc-facts">
	<li>
		<strong><?php esc_html_e( 'Activity Log', 'more-mcp' ); ?></strong>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Activity Log screen */
				__( '<a href="%s">Every tool call and OAuth event</a> is recorded with its outcome. Tool names and argument keys are logged; argument values never are.', 'more-mcp' ),
				esc_url( admin_url( 'admin.php?page=more-mcp-logs' ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Connection health tool', 'more-mcp' ); ?></strong>
		<?php echo wp_kses( __( 'Ask the connected client to call <code>more_mcp_connection_health</code>. It reports which auth method the request used, the token lifetime, the session ID, and the negotiated capabilities, answered from inside the request the client actually made.', 'more-mcp' ), [ 'code' => [] ] ); ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Sessions panel', 'more-mcp' ); ?></strong>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Sessions panel */
				__( '<a href="%s">See which clients are connected</a> right now, and disconnect one without disturbing the others.', 'more-mcp' ),
				esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</li>
</ul>
