<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_guide_url = isset( $more_mcp_url_https ) ? $more_mcp_url_https : '';
$more_mcp_guide_key = $more_mcp_settings['api_key'] ?? 'YOUR_API_KEY';
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'Every client below connects to the same MCP Server URL. There is no per-client endpoint. Copy it from the Connection panel, then follow the steps for whichever host you are using.', 'more-mcp' ); ?>
	</p>
	<p class="mmcp-doc-lead-url">
		<code><?php echo esc_html( $more_mcp_guide_url ); ?></code>
		<button type="button" class="button button-small copy-btn" data-copy-text="<?php echo esc_attr( $more_mcp_guide_url ); ?>">
			<?php esc_html_e( 'Copy', 'more-mcp' ); ?>
		</button>
	</p>
	<p class="description">
		<?php esc_html_e( 'Clients split into two groups. Claude.ai and ChatGPT run the OAuth handshake themselves and never need the API key. Claude Desktop and Cursor send the API key as a header instead, because they connect through a local bridge rather than a browser.', 'more-mcp' ); ?>
	</p>
</div>

<div class="setup-guides-list">

	<!-- Claude.ai (web) -->
	<div class="setup-guide-item" data-guide="claude-web">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-claude">C</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Claude.ai (web)', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'Custom connector in Claude.ai Settings. OAuth, no API key needed', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-oauth"><?php esc_html_e( 'OAuth', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<ol>
				<li><?php echo wp_kses( __( 'Go to <a href="https://claude.ai" target="_blank" rel="noopener noreferrer">claude.ai</a> and open <strong>Settings</strong>', 'more-mcp' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Click <strong>Connectors</strong> in the sidebar', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Click <strong>Add custom connector</strong>', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Enter a name (e.g., "My WordPress Site")', 'more-mcp' ); ?></li>
				<li><?php echo wp_kses( __( 'Paste the <strong>MCP Server URL</strong> shown above', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Click <strong>Add</strong>. Claude runs the OAuth handshake automatically and asks you to authorize as a WordPress user', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
			</ol>
			<p class="setup-guide-note">
				<?php esc_html_e( 'The connector acts as whichever WordPress user authorizes it, so its capabilities are that user\'s capabilities. Authorize as an editor rather than an administrator if you want a narrower surface.', 'more-mcp' ); ?>
			</p>
		</div>
	</div>

	<!-- ChatGPT -->
	<div class="setup-guide-item" data-guide="chatgpt">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-chatgpt">O</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'ChatGPT (Connectors)', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'Custom connector in ChatGPT Settings. OAuth, no API key needed', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-oauth"><?php esc_html_e( 'OAuth', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<ol>
				<li><?php echo wp_kses( __( 'Open <a href="https://chatgpt.com" target="_blank" rel="noopener noreferrer">chatgpt.com</a> → <strong>Settings</strong> → <strong>Connectors</strong>', 'more-mcp' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Click <strong>+ Add</strong> and choose <strong>Custom connector</strong> (or "MCP Server")', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Enter a name (e.g., "My WordPress Site")', 'more-mcp' ); ?></li>
				<li><?php echo wp_kses( __( 'Paste the <strong>MCP Server URL</strong> shown above', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Authorize when prompted. ChatGPT runs the OAuth handshake against your WordPress site', 'more-mcp' ); ?></li>
				<li><?php esc_html_e( 'The connector becomes available across new ChatGPT conversations', 'more-mcp' ); ?></li>
			</ol>
		</div>
	</div>

	<!-- Claude Desktop & Cowork -->
	<div class="setup-guide-item" data-guide="claude-desktop">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-claude-desktop">CD</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Claude Desktop & Cowork', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'stdio bridge via mcp-remote. Requires Node.js and the API key', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-key"><?php esc_html_e( 'API key', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<p>
				<?php esc_html_e( 'Claude Desktop and Cowork talk to HTTPS MCP servers through a stdio bridge. The bridge is a small Node.js package called mcp-remote that wraps the connection; npx downloads it on first run. The same config entry works in both apps.', 'more-mcp' ); ?>
			</p>
			<ol>
				<li><?php echo wp_kses( __( 'Install <a href="https://nodejs.org" target="_blank" rel="noopener noreferrer">Node.js</a> if not already installed', 'more-mcp' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Open Claude Desktop → <strong>Settings</strong> → <strong>Developer</strong> → <strong>Edit Config</strong>', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Add a server entry with your URL and API key already filled in below:', 'more-mcp' ), [ 'strong' => [] ] ); ?>
					<pre class="setup-guide-code-block"><code>{
  "mcpServers": {
    "more-mcp": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote",
        "<?php echo esc_html( $more_mcp_guide_url ); ?>",
        "--header",
        "MMCP-Key:<?php echo esc_html( $more_mcp_guide_key ); ?>"
      ]
    }
  }
}</code></pre>
				</li>
				<li><?php esc_html_e( 'Save the config file and restart the app', 'more-mcp' ); ?></li>
				<li><?php esc_html_e( 'More MCP tools appear in the tool list', 'more-mcp' ); ?></li>
			</ol>
			<p class="setup-guide-note">
				<?php esc_html_e( 'The API key carries administrator-level trust, and this config file stores it in plain text on your machine. Regenerating the key on the Connection panel invalidates this config immediately.', 'more-mcp' ); ?>
			</p>
		</div>
	</div>

	<!-- Cursor -->
	<div class="setup-guide-item" data-guide="cursor">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-cursor">CR</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Cursor', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'MCP server entry in Cursor Settings. Uses the API key header', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-key"><?php esc_html_e( 'API key', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<ol>
				<li><?php echo wp_kses( __( 'Open Cursor → <strong>Settings</strong> → <strong>MCP</strong>', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Click <strong>+ Add new MCP server</strong>', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Enter a name (e.g., "More MCP, My Site")', 'more-mcp' ); ?></li>
				<li><?php echo wp_kses( __( 'Paste the <strong>MCP Server URL</strong> shown above', 'more-mcp' ), [ 'strong' => [] ] ); ?></li>
				<li><?php echo wp_kses( __( 'Add an HTTP header: <code>MMCP-Key</code> with your <strong>API Key</strong> as the value', 'more-mcp' ), [ 'strong' => [], 'code' => [] ] ); ?></li>
				<li><?php esc_html_e( 'Save. Cursor connects automatically and More MCP tools become available', 'more-mcp' ); ?></li>
			</ol>
		</div>
	</div>

	<!-- Claude Code -->
	<div class="setup-guide-item" data-guide="claude-code">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-claude-code">CC</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Claude Code', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'One CLI command. HTTP transport with the API key header', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-key"><?php esc_html_e( 'API key', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<p>
				<?php esc_html_e( 'Claude Code connects to remote MCP servers directly over HTTP — no bridge package. Run one command in your terminal with the URL and API key already filled in below:', 'more-mcp' ); ?>
			</p>
			<pre class="setup-guide-code-block"><code>claude mcp add --transport http more-mcp \
  <?php echo esc_html( $more_mcp_guide_url ); ?> \
  --header "MMCP-Key: <?php echo esc_html( $more_mcp_guide_key ); ?>"</code></pre>
			<p>
				<?php echo wp_kses( __( 'The server is added at <strong>local</strong> scope by default (this project, your machine only). Add <code>--scope user</code> to make it available across all your projects, or <code>--scope project</code> to share it with your team through a checked-in <code>.mcp.json</code>. Do not use <code>--scope project</code> with the API key inline: it commits an administrator-level secret to the repository.', 'more-mcp' ), [ 'strong' => [], 'code' => [] ] ); ?>
			</p>
			<p class="setup-guide-note">
				<?php echo wp_kses( __( 'Verify with <code>claude mcp list</code>. The API key carries administrator-level trust; regenerating it on the Connection panel invalidates this server entry immediately.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</p>
		</div>
	</div>

	<!-- Google Antigravity -->
	<div class="setup-guide-item" data-guide="antigravity">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-antigravity">GA</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Google Antigravity', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'MCP server entry in the agent settings. Uses the API key header', 'more-mcp' ); ?></small>
			</span>
			<span class="setup-guide-badge badge-key"><?php esc_html_e( 'API key', 'more-mcp' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<p>
				<?php echo wp_kses( __( 'Antigravity connects to remote MCP servers over HTTP. Exact menu wording changes between releases, so check <a href="https://antigravity.google" target="_blank" rel="noopener noreferrer">the Antigravity documentation</a> for where MCP servers are configured in your version. Whatever the wording, it needs the same three things:', 'more-mcp' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?>
			</p>
			<ul class="mmcp-doc-facts">
				<li>
					<strong><?php esc_html_e( 'Endpoint', 'more-mcp' ); ?></strong>
					<code><?php echo esc_html( $more_mcp_guide_url ); ?></code>
				</li>
				<li>
					<strong><?php esc_html_e( 'Transport', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'Streamable HTTP', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Header', 'more-mcp' ); ?></strong>
					<?php echo wp_kses( __( '<code>MMCP-Key</code> with your API key as the value.', 'more-mcp' ), [ 'code' => [] ] ); ?>
				</li>
			</ul>
			<p class="setup-guide-note">
				<?php esc_html_e( 'If Antigravity discovers OAuth automatically, you can connect with the URL alone and skip the header. The API key carries administrator-level trust; regenerating it on the Connection panel invalidates this entry immediately.', 'more-mcp' ); ?>
			</p>
		</div>
	</div>

	<!-- Any other MCP client -->
	<div class="setup-guide-item" data-guide="generic">
		<button type="button" class="setup-guide-header" aria-expanded="false">
			<span class="setup-guide-icon icon-generic">?</span>
			<span class="setup-guide-name">
				<?php esc_html_e( 'Any other MCP client', 'more-mcp' ); ?>
				<small><?php esc_html_e( 'What to enter when the client is not listed above', 'more-mcp' ); ?></small>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2 setup-guide-chevron"></span>
		</button>
		<div class="setup-guide-body">
			<p>
				<?php esc_html_e( 'More MCP implements MCP over Streamable HTTP, so any spec-compliant client works. Whatever the client\'s wording, it needs three things:', 'more-mcp' ); ?>
			</p>
			<ul class="mmcp-doc-facts">
				<li>
					<strong><?php esc_html_e( 'Endpoint', 'more-mcp' ); ?></strong>
					<code><?php echo esc_html( $more_mcp_guide_url ); ?></code>
				</li>
				<li>
					<strong><?php esc_html_e( 'Transport', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'Streamable HTTP (POST for JSON-RPC). The deprecated HTTP+SSE transport is not used.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Auth', 'more-mcp' ); ?></strong>
					<?php echo wp_kses( __( 'Either OAuth 2.0 (discovered automatically, so the client needs no configuration) or the <code>MMCP-Key</code> header carrying your API key.', 'more-mcp' ), [ 'code' => [] ] ); ?>
				</li>
			</ul>
			<p class="setup-guide-note">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: URL of the Documentation panel, What agents can do tab */
						__( 'If a client fails on the size of the tool list, a trimmed profile can be requested with a URL parameter. See <a href="%s">What agents can do</a> for the profiles and what each includes.', 'more-mcp' ),
						esc_url( add_query_arg( [ 'panel' => 'docs', 'doc' => 'tools' ], admin_url( 'admin.php?page=more-mcp' ) ) )
					),
					[ 'a' => [ 'href' => [] ] ]
				);
				?>
			</p>
		</div>
	</div>

</div>
