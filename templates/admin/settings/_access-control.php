<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_ac_read_only   = \More_MCP\Access\Policy::read_only_mode();
$more_mcp_ac_default     = \More_MCP\Access\Policy::default_oauth_access();
$more_mcp_ac_allowlist   = \More_MCP\Access\Policy::ip_allowlist_raw();
$more_mcp_ac_proxies     = \More_MCP\Access\Policy::trusted_proxies_raw();
$more_mcp_ac_address     = \More_MCP\Admin\Access_Controls::current_address();
$more_mcp_ac_key_attrs   = \More_MCP\Admin\Access_Controls::editor_attributes( \More_MCP\Access\Policy::API_KEY );
$more_mcp_ac_list_active = \More_MCP\Access\Policy::ip_allowlist_enabled();
?>

<div class="mmcp-perm-group">

	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'These controls narrow what a connected AI client can do. They sit on top of WordPress capability checks: they can take access away from a connection, never add any. A blocked call comes back to the client as a plain error, and is recorded in the Activity Log.', 'mordenhost-mcp-server' ); ?>
	</p>

	<!-- Read-only mode -->
	<div class="mmcp-scope <?php echo $more_mcp_ac_read_only ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       class="mmcp-access-readonly"
				       id="mmcp-access-readonly"
				       value="1"
				       <?php checked( $more_mcp_ac_read_only ); ?> <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'read_only' ) ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-access-readonly"><?php esc_html_e( 'Read-only mode', 'mordenhost-mcp-server' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Applies to every connection', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_access', 'read_only' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			<p class="description">
				<?php esc_html_e( 'Every tool that would change the site is refused and hidden from the tool list; reading, searching and reporting keep working. Use it while you audit a site, hand a connection to someone you do not fully trust, or want a guaranteed look-but-do-not-touch session. A tool whose name does not clearly say it only reads counts as a change, so an unfamiliar tool is blocked rather than let through.', 'mordenhost-mcp-server' ); ?>
			</p>
		</div>
	</div>

	<!-- Per-connection access -->
	<div class="mmcp-scope is-on">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><?php esc_html_e( 'Connection access', 'mordenhost-mcp-server' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Per connection', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'Give each connection only what it needs. "Custom" picks Read, or Read and write, for each area of the site. OAuth clients are set one by one under Sessions, in Connected clients.', 'mordenhost-mcp-server' ); ?>
			</p>

			<div class="mmcp-scope-field">
				<label for="mmcp-access-default"><strong><?php esc_html_e( 'New OAuth clients start with', 'mordenhost-mcp-server' ); ?></strong></label>
				<select id="mmcp-access-default" class="mmcp-access-default" <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'default_oauth_access' ) ); ?>>
					<option value="full" <?php selected( 'full', $more_mcp_ac_default ); ?>><?php esc_html_e( 'Full access', 'mordenhost-mcp-server' ); ?></option>
					<option value="read_only" <?php selected( 'read_only', $more_mcp_ac_default ); ?>><?php esc_html_e( 'Read-only', 'mordenhost-mcp-server' ); ?></option>
				</select>
				<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_access', 'default_oauth_access' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
				<p class="description">
					<?php esc_html_e( 'Applies to a client the first time it connects. Clients that registered themselves get a new identity on each connection, so a stricter default is the only way to keep them restricted without visiting this screen every time.', 'mordenhost-mcp-server' ); ?>
				</p>
			</div>

			<div class="mmcp-scope-field">
				<strong><?php esc_html_e( 'API key', 'mordenhost-mcp-server' ); ?></strong>
				<div class="mmcp-access-editor"
				     data-target="api-key"
				     data-access="<?php echo esc_attr( $more_mcp_ac_key_attrs['data-access'] ); ?>"
				     data-groups="<?php echo esc_attr( $more_mcp_ac_key_attrs['data-groups'] ); ?>"></div>
				<p class="description">
					<?php esc_html_e( 'Clients that send the API key (Claude Desktop, Cursor, scripts) all share this one setting. It also applies to the REST routes under /wp-json/more-mcp/v1/ that use the same key.', 'mordenhost-mcp-server' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- IP allowlist -->
	<div class="mmcp-scope <?php echo $more_mcp_ac_list_active ? 'is-on' : ''; ?>" id="mmcp-ip-scope">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-ip-allowlist"><?php esc_html_e( 'IP allowlist', 'mordenhost-mcp-server' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Off while empty', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'When this list has entries, only requests from those addresses can reach the MCP endpoint. Everyone else is refused before the key or token is even checked. One address or range per line, IPv4 or IPv6, for example 203.0.113.7 or 198.51.100.0/24.', 'mordenhost-mcp-server' ); ?>
			</p>
			<textarea id="mmcp-ip-allowlist"
			          class="large-text code mmcp-ip-field"
			          data-field="ip_allowlist"
			          rows="4"
			          <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'ip_allowlist' ) ); ?>
			          placeholder="203.0.113.7&#10;198.51.100.0/24"><?php echo esc_textarea( $more_mcp_ac_allowlist ); ?></textarea>
			<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_access', 'ip_allowlist' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>

			<?php if ( '' !== $more_mcp_ac_address ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: IP address as seen by this site for the current request */
						esc_html__( 'This screen sees your address as %s. An AI client running somewhere else has a different address, and that is the one to list.', 'mordenhost-mcp-server' ),
						'<code>' . esc_html( $more_mcp_ac_address ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>

			<p>
				<button type="button" class="button button-primary mmcp-ip-save" <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'ip_allowlist' ) && \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'trusted_proxies' ) ); ?>><?php esc_html_e( 'Save allowlist', 'mordenhost-mcp-server' ); ?></button>
				<span class="mmcp-access-status" id="mmcp-ip-status" role="status" aria-live="polite"></span>
			</p>

			<button type="button" class="advanced-toggle" aria-expanded="<?php echo '' !== $more_mcp_ac_proxies ? 'true' : 'false'; ?>" aria-controls="mmcp-proxy-box">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Behind a proxy or CDN?', 'mordenhost-mcp-server' ); ?>
			</button>
			<div class="advanced-content" id="mmcp-proxy-box" <?php echo '' !== $more_mcp_ac_proxies ? '' : 'hidden'; ?>>
				<p class="description">
					<?php esc_html_e( 'Behind Cloudflare or a load balancer, every request appears to come from the proxy, so the allowlist would judge the proxy instead of your client. List the proxy\'s own addresses here and the client address is read from X-Forwarded-For, but only for requests that really came from one of these. Leave empty if the site is reached directly.', 'mordenhost-mcp-server' ); ?>
				</p>
				<label for="mmcp-trusted-proxies" class="screen-reader-text"><?php esc_html_e( 'Trusted proxy addresses, one per line', 'mordenhost-mcp-server' ); ?></label>
				<textarea id="mmcp-trusted-proxies"
				          class="large-text code mmcp-ip-field"
				          data-field="trusted_proxies"
				          rows="3"
				          <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_access', 'trusted_proxies' ) ); ?>
				          placeholder="173.245.48.0/20"><?php echo esc_textarea( $more_mcp_ac_proxies ); ?></textarea>
				<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_access', 'trusted_proxies' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>
		</div>
	</div>

</div>
