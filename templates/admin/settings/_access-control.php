<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_ac_read_only   = \Mordenhost_MCP\Access\Policy::read_only_mode();
$mordenhost_mcp_ac_default     = \Mordenhost_MCP\Access\Policy::default_oauth_access();
$mordenhost_mcp_ac_allowlist   = \Mordenhost_MCP\Access\Policy::ip_allowlist_raw();
$mordenhost_mcp_ac_proxies     = \Mordenhost_MCP\Access\Policy::trusted_proxies_raw();
$mordenhost_mcp_ac_address     = \Mordenhost_MCP\Admin\Access_Controls::current_address();
$mordenhost_mcp_ac_key_attrs   = \Mordenhost_MCP\Admin\Access_Controls::editor_attributes( \Mordenhost_MCP\Access\Policy::API_KEY );
$mordenhost_mcp_ac_list_active = \Mordenhost_MCP\Access\Policy::ip_allowlist_enabled();
$mordenhost_mcp_ac_list_locked = \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'ip_allowlist' );
$mordenhost_mcp_ac_mine_listed = $mordenhost_mcp_ac_list_active && \Mordenhost_MCP\Admin\Access_Controls::address_is_allowed( $mordenhost_mcp_ac_address );
$mordenhost_mcp_ac_app_on      = \Mordenhost_MCP\Access\Policy::app_passwords_enabled();
$mordenhost_mcp_ac_app_attrs   = \Mordenhost_MCP\Admin\Access_Controls::editor_attributes( \Mordenhost_MCP\Access\Policy::APP_PASSWORD );
$mordenhost_mcp_ac_app_core    = \Mordenhost_MCP\Auth\App_Passwords::available();
$mordenhost_mcp_ac_app_secure  = \Mordenhost_MCP\Auth\App_Passwords::transport_secure();

$mordenhost_mcp_ac_app_dead    = $mordenhost_mcp_ac_app_on && ( ! $mordenhost_mcp_ac_app_core || ! $mordenhost_mcp_ac_app_secure );
?>

<div class="mmcp-perm-group">

	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'These controls narrow what a connected AI client can do. They sit on top of WordPress capability checks: they can take access away from a connection, never add any. A blocked call comes back to the client as a plain error, and is recorded in the Activity Log.', 'mordenhost-mcp' ); ?>
	</p>

	<!-- Read-only mode -->
	<div class="mmcp-scope <?php echo $mordenhost_mcp_ac_read_only ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       class="mmcp-access-readonly"
				       id="mmcp-access-readonly"
				       value="1"
				       <?php checked( $mordenhost_mcp_ac_read_only ); ?> <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'read_only' ) ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-access-readonly"><?php esc_html_e( 'Read-only mode', 'mordenhost-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Applies to every connection', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_access', 'read_only' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			<p class="description">
				<?php esc_html_e( 'Every tool that would change the site is refused and hidden from the tool list; reading, searching and reporting keep working. Use it while you audit a site, hand a connection to someone you do not fully trust, or want a guaranteed look-but-do-not-touch session. A tool whose name does not clearly say it only reads counts as a change, so an unfamiliar tool is blocked rather than let through.', 'mordenhost-mcp' ); ?>
			</p>
		</div>
	</div>

	<!-- Per-connection access -->
	<div class="mmcp-scope is-on">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><?php esc_html_e( 'Connection access', 'mordenhost-mcp' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Per connection', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'Give each connection only what it needs. "Custom" picks Read, or Read and write, for each area of the site. OAuth clients are set one by one under Sessions, in Connected clients.', 'mordenhost-mcp' ); ?>
			</p>

			<div class="mmcp-scope-field">
				<label for="mmcp-access-default"><strong><?php esc_html_e( 'New OAuth clients start with', 'mordenhost-mcp' ); ?></strong></label>
				<select id="mmcp-access-default" class="mmcp-access-default" <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'default_oauth_access' ) ); ?>>
					<option value="full" <?php selected( 'full', $mordenhost_mcp_ac_default ); ?>><?php esc_html_e( 'Full access', 'mordenhost-mcp' ); ?></option>
					<option value="read_only" <?php selected( 'read_only', $mordenhost_mcp_ac_default ); ?>><?php esc_html_e( 'Read-only', 'mordenhost-mcp' ); ?></option>
				</select>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_access', 'default_oauth_access' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
				<p class="description">
					<?php esc_html_e( 'Applies to a client the first time it connects. Clients that registered themselves get a new identity on each connection, so a stricter default is the only way to keep them restricted without visiting this screen every time.', 'mordenhost-mcp' ); ?>
				</p>
			</div>

			<div class="mmcp-scope-field">
				<strong><?php esc_html_e( 'API key', 'mordenhost-mcp' ); ?></strong>
				<div class="mmcp-access-editor"
				     data-target="api-key"
				     data-access="<?php echo esc_attr( $mordenhost_mcp_ac_key_attrs['data-access'] ); ?>"
				     data-groups="<?php echo esc_attr( $mordenhost_mcp_ac_key_attrs['data-groups'] ); ?>"></div>
				<p class="description">
					<?php esc_html_e( 'Clients that send the API key (Claude Desktop, Cursor, scripts) all share this one setting. It also applies to the REST routes under /wp-json/mordenhost-mcp/v1/ that use the same key.', 'mordenhost-mcp' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Application Passwords (GitHub #10) -->
	<div class="mmcp-scope <?php echo $mordenhost_mcp_ac_app_on ? 'is-on' : ''; ?><?php echo $mordenhost_mcp_ac_app_dead ? ' is-ineffective' : ''; ?>" id="mmcp-app-passwords-scope">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       class="mmcp-access-app-passwords"
				       id="mmcp-access-app-passwords"
				       value="1"
				       data-unavailable="<?php echo ( ! $mordenhost_mcp_ac_app_core || ! $mordenhost_mcp_ac_app_secure ) ? '1' : '0'; ?>"
				       <?php checked( $mordenhost_mcp_ac_app_on ); ?> <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'app_passwords' ) ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-access-app-passwords"><?php esc_html_e( 'Application Passwords', 'mordenhost-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Off by default. A fallback when a firewall blocks OAuth', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_access', 'app_passwords' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			<p class="description">
				<?php echo wp_kses( __( 'Lets a client sign in with a WordPress Application Password, sent as <code>Authorization: Basic</code> with the username and the password. Each user creates and revokes their own under Users → Profile → Application Passwords; revoking one ends that client\'s access on its next request. The connection acts as that user, so their WordPress capabilities still apply. Prefer OAuth where it works: turn this on only when a firewall, bot filter or proxy stops the OAuth handshake.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The trade-off: Basic authentication sends the password with every request instead of a short-lived token, so it is only accepted over HTTPS. Over plain HTTP it is refused, unless the site\'s environment type is "local", the same rule WordPress applies to Application Passwords.', 'mordenhost-mcp' ); ?>
			</p>
			<?php if ( ! $mordenhost_mcp_ac_app_core || ! $mordenhost_mcp_ac_app_secure ) : ?>
				<p class="description mmcp-dep-note">
					<?php if ( $mordenhost_mcp_ac_app_on ) : ?>
						<span class="mmcp-flag mmcp-flag-warn"><?php esc_html_e( 'On, but not available', 'mordenhost-mcp' ); ?></span>
					<?php endif; ?>
					<?php esc_html_e( 'WordPress does not offer Application Passwords on this site right now: it needs HTTPS, and a plugin or the site configuration can switch them off. Until that changes no client can sign in this way.', 'mordenhost-mcp' ); ?>
				</p>
			<?php endif; ?>

			<div class="mmcp-scope-field">
				<strong><?php esc_html_e( 'Access for Application Password connections', 'mordenhost-mcp' ); ?></strong>
				<div class="mmcp-access-editor"
				     data-target="app-password"
				     data-access="<?php echo esc_attr( $mordenhost_mcp_ac_app_attrs['data-access'] ); ?>"
				     data-groups="<?php echo esc_attr( $mordenhost_mcp_ac_app_attrs['data-groups'] ); ?>"></div>
				<p class="description">
					<?php esc_html_e( 'Applies to every Application Password connection, on top of what its user can do. It starts as Read-only, like a new named token, because users create these passwords themselves; widen it here if those clients need to make changes.', 'mordenhost-mcp' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- IP allowlist -->
	<div class="mmcp-scope <?php echo $mordenhost_mcp_ac_list_active ? 'is-on' : ''; ?>" id="mmcp-ip-scope">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-ip-allowlist"><?php esc_html_e( 'IP allowlist', 'mordenhost-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Off while empty', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'When this list has entries, only requests from those addresses can reach the MCP endpoint. Everyone else is refused before the key or token is even checked. One address or range per line, IPv4 or IPv6, for example 203.0.113.7 or 198.51.100.0/24.', 'mordenhost-mcp' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The list also covers the REST routes under /wp-json/mordenhost-mcp/v1/ and the OAuth addresses a client calls itself: /token, /register, /mcp-device/code and the two .well-known discovery documents. The approval pages, /authorize and /mcp-device, stay open because your browser opens them, often from another address; they need you signed in, and what they hand out only works through /token. Refused requests appear in the Activity Log with their address.', 'mordenhost-mcp' ); ?>
			</p>
			<textarea id="mmcp-ip-allowlist"
			          class="large-text code mmcp-ip-field"
			          data-field="ip_allowlist"
			          rows="4"
			          <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'ip_allowlist' ) ); ?>
			          placeholder="203.0.113.7&#10;198.51.100.0/24"><?php echo esc_textarea( $mordenhost_mcp_ac_allowlist ); ?></textarea>
			<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_access', 'ip_allowlist' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>

			<?php if ( '' !== $mordenhost_mcp_ac_address ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: IP address as seen by this site for the current request */
						esc_html__( 'This screen sees your address as %s. An AI client running somewhere else has a different address, and that is the one to list.', 'mordenhost-mcp' ),
						'<code>' . esc_html( $mordenhost_mcp_ac_address ) . '</code>'
					);
					?>
					<?php if ( ! $mordenhost_mcp_ac_list_locked && ! $mordenhost_mcp_ac_mine_listed ) : ?>
						<button type="button" class="button button-small mmcp-ip-add-mine"><?php esc_html_e( 'Add my address', 'mordenhost-mcp' ); ?></button>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="button button-primary mmcp-ip-save" <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'ip_allowlist' ) && \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'trusted_proxies' ) ); ?>><?php esc_html_e( 'Save allowlist', 'mordenhost-mcp' ); ?></button>
				<span class="mmcp-access-status" id="mmcp-ip-status" role="status" aria-live="polite"></span>
			</p>

			<button type="button" class="advanced-toggle" aria-expanded="<?php echo '' !== $mordenhost_mcp_ac_proxies ? 'true' : 'false'; ?>" aria-controls="mmcp-proxy-box">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Behind a proxy or CDN?', 'mordenhost-mcp' ); ?>
			</button>
			<div class="advanced-content" id="mmcp-proxy-box" <?php echo '' !== $mordenhost_mcp_ac_proxies ? '' : 'hidden'; ?>>
				<p class="description">
					<?php esc_html_e( 'Behind Cloudflare or a load balancer, every request appears to come from the proxy, so the allowlist would judge the proxy instead of your client. List the proxy\'s own addresses here and the client address is read from X-Forwarded-For, but only for requests that really came from one of these. Leave empty if the site is reached directly.', 'mordenhost-mcp' ); ?>
				</p>
				<label for="mmcp-trusted-proxies" class="screen-reader-text"><?php esc_html_e( 'Trusted proxy addresses, one per line', 'mordenhost-mcp' ); ?></label>
				<textarea id="mmcp-trusted-proxies"
				          class="large-text code mmcp-ip-field"
				          data-field="trusted_proxies"
				          rows="3"
				          <?php disabled( \Mordenhost_MCP\Platform\Locked_Settings::is_locked( 'mordenhost_mcp_access', 'trusted_proxies' ) ); ?>
				          placeholder="173.245.48.0/20"><?php echo esc_textarea( $mordenhost_mcp_ac_proxies ); ?></textarea>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_access', 'trusted_proxies' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>
		</div>
	</div>

</div>
