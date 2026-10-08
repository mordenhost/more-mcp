<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_ts_permalinks = get_option( 'permalink_structure' );
$more_mcp_ts_plain      = empty( $more_mcp_ts_permalinks );
$more_mcp_ts_base       = admin_url( 'admin.php?page=more-mcp' );
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'Nearly every failed connection comes down to something between the client and WordPress, such as a CDN rule, a security layer, or a permalink setting, rather than to More MCP itself. Work down this list in order; the first three account for most reports.', 'mordenhost-mcp-server' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'More MCP also probes its own OAuth endpoints on a schedule and raises an admin notice when it detects a specific known cause. An absent notice is not proof that nothing is wrong; the probe only recognizes conditions it knows how to name.', 'mordenhost-mcp-server' ); ?>
	</p>
</div>

<?php if ( $more_mcp_ts_plain ) : ?>
	<div class="cloudflare-warning warning-error">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'This site is using Plain permalinks right now.', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the WordPress permalink settings screen */
					__( 'OAuth cannot work until that is changed, because the OAuth endpoints are served from the domain root by rewrite rules and rewrite rules do not run on Plain. Choose any other option under <a href="%s">Settings → Permalinks</a>.', 'mordenhost-mcp-server' ),
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
		<h3><?php esc_html_e( 'The site is not on a public HTTPS address', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'Hosted MCP backends reach into your site over the public internet, so a localhost URL, a LAN IP, or a plain-HTTP address will never complete a connection from Claude.ai or ChatGPT. A local bridge client (Claude Desktop, Cursor) can still reach a local site, but the hosted connectors cannot.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( 'Deploy the site to a public domain with a valid SSL certificate before connecting a hosted client. There is no plugin setting that can substitute for a reachable HTTPS address; the Connection panel flags this when it detects a localhost URL.', 'mordenhost-mcp-server' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Cloudflare is blocking AI bots', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'Cloudflare enables "Block AI Bots" by default on new domains, and it blocks every MCP backend, including Claude and ChatGPT, from completing the handshake. The connection usually fails with a generic error that gives no hint the CDN is involved.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( 'In the Cloudflare dashboard, turn off "Block AI Bots" under Security → Bots. If you would rather keep it on, create an exception for your MCP endpoint path instead of disabling it site-wide.', 'mordenhost-mcp-server' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Plain permalinks', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php echo wp_kses( __( 'The OAuth endpoints (<code>/authorize</code>, <code>/token</code>, <code>/register</code>, and the two <code>.well-known</code> documents) are served from the domain root through WordPress rewrite rules. On Plain permalinks those rules never fire, so the client\'s discovery request 404s and the handshake stops there.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the WordPress permalink settings screen */
					__( 'Switch to any non-Plain structure under <a href="%s">Settings → Permalinks</a>. Saving that screen also flushes the rewrite rules.', 'mordenhost-mcp-server' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'The host intercepts .well-known or /register', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'Several hosting layers claim these paths before WordPress sees the request. Imunify360 on shared cPanel hosts and BitNinja WebShield both do it; SiteGround reserves .well-known for its own use; and some servers 301-redirect /register to /register/, which OAuth clients do not follow on POST.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'A membership or maintenance-mode plugin can cause the same symptom from inside WordPress by serving an HTML page where the client expects JSON.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( 'Open the two .well-known URLs listed on the REST API reference tab in a private browser window. If either returns HTML, a 404, or a challenge page instead of JSON, the cause is above WordPress and your host has to allowlist those paths. No plugin setting can work around it.', 'mordenhost-mcp-server' ); ?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'A stuck connector that will not finish authorizing', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'A handshake interrupted partway through can leave a registered client and a pending authorization code that no longer match what the client believes it holds. Retrying from the client side then fails the same way every time, because the stale server-side state is what is wrong.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Sessions panel */
					__( 'Run <a href="%s">Reset OAuth State</a> on the Sessions panel, then remove and re-add the connector in the client. Your settings, API key, and Activity Log are not affected, but every other connected client will need to re-authorize.', 'mordenhost-mcp-server' ),
					esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Clients report "Session not found" repeatedly', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'Sessions are stored in a database table rather than in transients, specifically so that an object-cache drop-in evicting keys between requests cannot break them. If clients still loop on session errors, the session rows themselves are the thing to clear.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Sessions panel */
					__( 'Use <a href="%s">End all sessions</a> on the Sessions panel. That clears transport state without revoking any credentials, so clients reconnect on their own without re-authorizing.', 'mordenhost-mcp-server' ),
					esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'The client connects but shows no tools', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Access panel */
					__( 'First check that the master switch is on. <a href="%s">Access</a> shows the current state, and while it is off the server answers discovery but refuses everything else. If it is on, the next suspect is tool-list size: some clients silently drop a list they consider too large.', 'mordenhost-mcp-server' ),
					esc_url( add_query_arg( 'panel', 'permissions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Documentation panel, What agents can do tab */
					__( 'Request a trimmed tool profile with a URL parameter. See <a href="%s">What agents can do</a> for the available profiles and what each one sends.', 'mordenhost-mcp-server' ),
					esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'tools' ], $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'A write fails with a permission error', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'Authorization has two layers, and they fail with similar-looking messages. A connector authorized through OAuth acts as the WordPress user who authorized it, so it cannot exceed that user\'s capabilities. Separately, option writes, theme changes, and plugin management are each gated by their own toggle regardless of capability.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Access panel */
					__( 'Check the relevant toggle on <a href="%s">Access</a> first; it is the more common cause. If the toggle is already on, re-authorize the connector as a user who holds the capability the operation needs.', 'mordenhost-mcp-server' ),
					esc_url( add_query_arg( 'panel', 'permissions', $more_mcp_ts_base ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>

	<div class="mmcp-troubleshoot-item">
		<h3><?php esc_html_e( 'Requests fail intermittently under load', 'mordenhost-mcp-server' ); ?></h3>
		<p>
			<?php esc_html_e( 'The MCP endpoint allows 60 requests per 60 seconds per IP address and returns HTTP 429 beyond that. An agent working through a long batch can hit this, and because the limit is per-IP, several clients behind one office network share the same budget.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p>
			<?php

			

			

			esc_html_e( 'On a site behind Cloudflare, a load balancer, or another reverse proxy, every client can land in this same bucket, because the limiter reads the direct connection address rather than a proxy-forwarded one. One busy automation can then make the limit trigger for every other client on the site.', 'mordenhost-mcp-server' );
			?>
		</p>
		<p class="mmcp-troubleshoot-fix">
			<strong><?php esc_html_e( 'Fix:', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( 'Check the Activity Log for the failing window. If the pattern is a genuine burst rather than a runaway loop, spread the work out; the limit is deliberate protection for the site.', 'mordenhost-mcp-server' ); ?>
		</p>
	</div>

</div>

<h3><?php esc_html_e( 'Where to look next', 'mordenhost-mcp-server' ); ?></h3>
<ul class="mmcp-doc-facts">
	<li>
		<strong><?php esc_html_e( 'Activity Log', 'mordenhost-mcp-server' ); ?></strong>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Activity Log screen */
				__( '<a href="%s">Every tool call and OAuth event</a> is recorded with its outcome. Tool names and argument keys are logged; argument values never are.', 'mordenhost-mcp-server' ),
				esc_url( add_query_arg( 'panel', 'logs', admin_url( 'admin.php?page=more-mcp' ) ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Connection health tool', 'mordenhost-mcp-server' ); ?></strong>
		<?php echo wp_kses( __( 'Ask the connected client to call <code>more_mcp_connection_health</code>. It reports which auth method the request used, the token lifetime, the session ID, and the negotiated capabilities, answered from inside the request the client actually made.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Sessions panel', 'mordenhost-mcp-server' ); ?></strong>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL of the Sessions panel */
				__( '<a href="%s">See which clients are connected</a> right now, and disconnect one without disturbing the others.', 'mordenhost-mcp-server' ),
				esc_url( add_query_arg( 'panel', 'sessions', $more_mcp_ts_base ) )
			),
			[ 'a' => [ 'href' => [] ] ]
		);
		?>
	</li>
</ul>
