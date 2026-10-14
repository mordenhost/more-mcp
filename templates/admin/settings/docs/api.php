<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_api_mcp_url  = isset( $mordenhost_mcp_url_https ) ? $mordenhost_mcp_url_https : '';
$mordenhost_mcp_api_rest_url = isset( $mordenhost_mcp_rest_base ) ? $mordenhost_mcp_rest_base : rest_url( 'mordenhost-mcp/v1/' );
$mordenhost_mcp_api_root     = home_url( '/' );

$mordenhost_mcp_api_sections = [
	[
		'title'   => __( 'Posts', 'mordenhost-mcp' ),
		'routes'  => [
			[ 'GET', '/posts', __( 'List posts', 'mordenhost-mcp' ) ],
			[ 'GET', '/posts/{id}', __( 'Get a specific post', 'mordenhost-mcp' ) ],
			[ 'POST', '/posts', __( 'Create a new post', 'mordenhost-mcp' ) ],
			[ 'PUT', '/posts/{id}', __( 'Update a post', 'mordenhost-mcp' ) ],
			[ 'DELETE', '/posts/{id}', __( 'Delete a post', 'mordenhost-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Pages', 'mordenhost-mcp' ),
		'routes'  => [
			[ 'GET', '/pages', __( 'List pages', 'mordenhost-mcp' ) ],
			[ 'GET', '/pages/{id}', __( 'Get a specific page', 'mordenhost-mcp' ) ],
			[ 'POST', '/pages', __( 'Create a new page', 'mordenhost-mcp' ) ],
			[ 'PUT', '/pages/{id}', __( 'Update a page', 'mordenhost-mcp' ) ],
			[ 'DELETE', '/pages/{id}', __( 'Delete a page', 'mordenhost-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Media', 'mordenhost-mcp' ),
		'routes'  => [
			[ 'GET', '/media', __( 'List media files', 'mordenhost-mcp' ) ],
			[ 'GET', '/media/{id}', __( 'Get a specific media file', 'mordenhost-mcp' ) ],
			[ 'POST', '/media', __( 'Upload media', 'mordenhost-mcp' ) ],
			[ 'DELETE', '/media/{id}', __( 'Delete media', 'mordenhost-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Site and search', 'mordenhost-mcp' ),
		'routes'  => [
			[ 'GET', '/site', __( 'Get site information', 'mordenhost-mcp' ) ],
			[ 'GET', '/search', __( 'Search content', 'mordenhost-mcp' ) ],
		],
	],
	[
		'title'   => __( 'WooCommerce products', 'mordenhost-mcp' ),
		'note'    => __( 'Present only while WooCommerce is active.', 'mordenhost-mcp' ),
		'routes'  => [
			[ 'GET', '/products/attributes', __( 'List product attributes', 'mordenhost-mcp' ) ],
			[ 'POST', '/products/attributes', __( 'Create a product attribute', 'mordenhost-mcp' ) ],
			[ 'GET', '/products/attributes/{id}/terms', __( 'List terms of an attribute', 'mordenhost-mcp' ) ],
			[ 'GET', '/products/{id}/variations', __( 'List variations of a variable product', 'mordenhost-mcp' ) ],
			[ 'POST', '/products/{id}/variations', __( 'Create a variation', 'mordenhost-mcp' ) ],
			[ 'GET', '/products/{id}/variations/{variation_id}', __( 'Get a variation', 'mordenhost-mcp' ) ],
			[ 'PUT', '/products/{id}/variations/{variation_id}', __( 'Update a variation', 'mordenhost-mcp' ) ],
			[ 'DELETE', '/products/{id}/variations/{variation_id}', __( 'Delete a variation', 'mordenhost-mcp' ) ],
			[ 'POST', '/products/{id}/attributes', __( 'Attach attributes to a product', 'mordenhost-mcp' ) ],
		],
	],
];
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'Mordenhost MCP answers on three separate HTTP surfaces. Almost every integration should use the first one; the others exist for discovery and for backward compatibility.', 'mordenhost-mcp' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '1. MCP endpoint, the current surface', 'mordenhost-mcp' ); ?></h3>

<div class="mmcp-endpoint-card">
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_mcp_url ); ?></code>
	</div>
	<p class="description">
		<?php esc_html_e( 'JSON-RPC 2.0 over Streamable HTTP, protocol version 2025-11-25. One endpoint carries every method: initialize, tools/list, tools/call, and the rest. GET is used by clients probing for support, DELETE terminates a session, OPTIONS answers CORS preflight.', 'mordenhost-mcp' ); ?>
	</p>
	<ul class="mmcp-doc-facts">
		<li>
			<strong><?php esc_html_e( 'Auth', 'mordenhost-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'An <code>Authorization: Bearer &lt;token&gt;</code> OAuth token, or the API key in an <code>MMCP-Key</code> header. Both are accepted on the same endpoint.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Application Passwords', 'mordenhost-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'Off unless turned on under Access. Then a WordPress Application Password in <code>Authorization: Basic</code> is accepted too, over HTTPS only, acting as the password\'s user. It is the fallback for sites where a firewall blocks OAuth.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sessions', 'mordenhost-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'The response to <code>initialize</code> carries an <code>Mcp-Session-Id</code> header. Send it back on every subsequent request. A session is bound to the credentials that opened it, so it cannot be reused under different auth.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Rate limit', 'mordenhost-mcp' ); ?></strong>
			<?php esc_html_e( '60 requests per 60 seconds per IP address. Exceeding it returns HTTP 429.', 'mordenhost-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Caching', 'mordenhost-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'Every response under this namespace is sent <code>Cache-Control: no-store</code>. Do not put a caching layer in front of it that ignores that header.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
		</li>
	</ul>
</div>

<h3><?php esc_html_e( '2. OAuth endpoints, served at the domain root', 'mordenhost-mcp' ); ?></h3>

<p class="description">
	<?php echo wp_kses( __( 'These sit at the site root, not under <code>/wp-json/</code>, because MCP clients discover them via RFC 9728 well-known paths that must resolve at the domain apex. Clients find and call them on their own; nothing here needs to be entered anywhere.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
</p>

<div class="mmcp-endpoint-card">
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_root ); ?>.well-known/oauth-authorization-server</code>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_root ); ?>.well-known/oauth-protected-resource</code>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_root ); ?>register</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Dynamic Client Registration', 'mordenhost-mcp' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_root ); ?>authorize</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Authorization code + PKCE', 'mordenhost-mcp' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $mordenhost_mcp_api_root ); ?>token</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Token exchange and refresh', 'mordenhost-mcp' ); ?></span>
	</div>
	<p class="description">
		<?php esc_html_e( 'The two well-known documents stay reachable even when Mordenhost MCP is disabled, so discovery still answers correctly instead of timing out. The other three return HTTP 503 while the plugin is off.', 'mordenhost-mcp' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '3. Legacy REST routes', 'mordenhost-mcp' ); ?></h3>

<div class="cloudflare-warning">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<p>
		<strong><?php esc_html_e( 'Use the MCP endpoint instead unless you have a reason not to.', 'mordenhost-mcp' ); ?></strong>
		<?php esc_html_e( 'These conventional REST routes predate the MCP surface and cover a small fraction of it. The roughly 170 MCP tools have no REST equivalent. They remain supported for integrations written against them.', 'mordenhost-mcp' ); ?>
	</p>
</div>

<p class="mmcp-doc-lead-url">
	<code><?php echo esc_html( $mordenhost_mcp_api_rest_url ); ?></code>
	<button type="button" class="button button-small copy-btn" data-copy-text="<?php echo esc_attr( $mordenhost_mcp_api_rest_url ); ?>">
		<?php esc_html_e( 'Copy', 'mordenhost-mcp' ); ?>
	</button>
</p>
<p class="description">
	<?php echo wp_kses( __( 'Paths below are relative to that base. Every request must carry the API key in an <code>MMCP-Key</code> header; these routes do not accept OAuth tokens.', 'mordenhost-mcp' ), [ 'code' => [] ] ); ?>
</p>

<div class="mmcp-rest-reference">
	<?php foreach ( $mordenhost_mcp_api_sections as $mordenhost_mcp_api_section ) : ?>
		<div class="mmcp-rest-section">
			<h4><?php echo esc_html( $mordenhost_mcp_api_section['title'] ); ?></h4>
			<?php if ( ! empty( $mordenhost_mcp_api_section['note'] ) ) : ?>
				<p class="description mmcp-rest-section-note"><?php echo esc_html( $mordenhost_mcp_api_section['note'] ); ?></p>
			<?php endif; ?>
			<ul class="mmcp-rest-routes">
				<?php foreach ( $mordenhost_mcp_api_section['routes'] as $mordenhost_mcp_api_route ) : ?>
					<?php
					list( $mordenhost_mcp_api_method, $mordenhost_mcp_api_path, $mordenhost_mcp_api_desc ) = $mordenhost_mcp_api_route;
					?>
					<li>
						<span class="mmcp-method mmcp-method-<?php echo esc_attr( strtolower( $mordenhost_mcp_api_method ) ); ?>">
							<?php echo esc_html( $mordenhost_mcp_api_method ); ?>
						</span>
						<code><?php echo esc_html( $mordenhost_mcp_api_path ); ?></code>
						<span class="mmcp-endpoint-note"><?php echo esc_html( $mordenhost_mcp_api_desc ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</div>

<h3><?php esc_html_e( 'WordPress Abilities API', 'mordenhost-mcp' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'On WordPress 6.9 and later, every MCP tool is also registered as a WordPress ability under the mordenhost-mcp/ namespace, so other plugins can invoke them in-process without an HTTP round trip. This is registration, not a fourth endpoint: the abilities route through the same handlers and the same capability checks as everything above.', 'mordenhost-mcp' ); ?>
</p>
