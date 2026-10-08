<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_api_mcp_url  = isset( $more_mcp_url_https ) ? $more_mcp_url_https : '';
$more_mcp_api_rest_url = isset( $more_mcp_rest_base ) ? $more_mcp_rest_base : rest_url( 'more-mcp/v1/' );
$more_mcp_api_root     = home_url( '/' );

$more_mcp_api_sections = [
	[
		'title'   => __( 'Posts', 'mordenhost-mcp-server' ),
		'routes'  => [
			[ 'GET', '/posts', __( 'List posts', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/posts/{id}', __( 'Get a specific post', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/posts', __( 'Create a new post', 'mordenhost-mcp-server' ) ],
			[ 'PUT', '/posts/{id}', __( 'Update a post', 'mordenhost-mcp-server' ) ],
			[ 'DELETE', '/posts/{id}', __( 'Delete a post', 'mordenhost-mcp-server' ) ],
		],
	],
	[
		'title'   => __( 'Pages', 'mordenhost-mcp-server' ),
		'routes'  => [
			[ 'GET', '/pages', __( 'List pages', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/pages/{id}', __( 'Get a specific page', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/pages', __( 'Create a new page', 'mordenhost-mcp-server' ) ],
			[ 'PUT', '/pages/{id}', __( 'Update a page', 'mordenhost-mcp-server' ) ],
			[ 'DELETE', '/pages/{id}', __( 'Delete a page', 'mordenhost-mcp-server' ) ],
		],
	],
	[
		'title'   => __( 'Media', 'mordenhost-mcp-server' ),
		'routes'  => [
			[ 'GET', '/media', __( 'List media files', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/media/{id}', __( 'Get a specific media file', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/media', __( 'Upload media', 'mordenhost-mcp-server' ) ],
			[ 'DELETE', '/media/{id}', __( 'Delete media', 'mordenhost-mcp-server' ) ],
		],
	],
	[
		'title'   => __( 'Site and search', 'mordenhost-mcp-server' ),
		'routes'  => [
			[ 'GET', '/site', __( 'Get site information', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/search', __( 'Search content', 'mordenhost-mcp-server' ) ],
		],
	],
	[
		'title'   => __( 'WooCommerce products', 'mordenhost-mcp-server' ),
		'note'    => __( 'Present only while WooCommerce is active.', 'mordenhost-mcp-server' ),
		'routes'  => [
			[ 'GET', '/products/attributes', __( 'List product attributes', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/products/attributes', __( 'Create a product attribute', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/products/attributes/{id}/terms', __( 'List terms of an attribute', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/products/{id}/variations', __( 'List variations of a variable product', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/products/{id}/variations', __( 'Create a variation', 'mordenhost-mcp-server' ) ],
			[ 'GET', '/products/{id}/variations/{variation_id}', __( 'Get a variation', 'mordenhost-mcp-server' ) ],
			[ 'PUT', '/products/{id}/variations/{variation_id}', __( 'Update a variation', 'mordenhost-mcp-server' ) ],
			[ 'DELETE', '/products/{id}/variations/{variation_id}', __( 'Delete a variation', 'mordenhost-mcp-server' ) ],
			[ 'POST', '/products/{id}/attributes', __( 'Attach attributes to a product', 'mordenhost-mcp-server' ) ],
		],
	],
];
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'More MCP answers on three separate HTTP surfaces. Almost every integration should use the first one; the others exist for discovery and for backward compatibility.', 'mordenhost-mcp-server' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '1. MCP endpoint, the current surface', 'mordenhost-mcp-server' ); ?></h3>

<div class="mmcp-endpoint-card">
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_mcp_url ); ?></code>
	</div>
	<p class="description">
		<?php esc_html_e( 'JSON-RPC 2.0 over Streamable HTTP, protocol version 2025-11-25. One endpoint carries every method: initialize, tools/list, tools/call, and the rest. GET is used by clients probing for support, DELETE terminates a session, OPTIONS answers CORS preflight.', 'mordenhost-mcp-server' ); ?>
	</p>
	<ul class="mmcp-doc-facts">
		<li>
			<strong><?php esc_html_e( 'Auth', 'mordenhost-mcp-server' ); ?></strong>
			<?php echo wp_kses( __( 'An <code>Authorization: Bearer &lt;token&gt;</code> OAuth token, or the API key in an <code>MMCP-Key</code> header. Both are accepted on the same endpoint.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sessions', 'mordenhost-mcp-server' ); ?></strong>
			<?php echo wp_kses( __( 'The response to <code>initialize</code> carries an <code>Mcp-Session-Id</code> header. Send it back on every subsequent request. A session is bound to the credentials that opened it, so it cannot be reused under different auth.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Rate limit', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( '60 requests per 60 seconds per IP address. Exceeding it returns HTTP 429.', 'mordenhost-mcp-server' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Caching', 'mordenhost-mcp-server' ); ?></strong>
			<?php echo wp_kses( __( 'Every response under this namespace is sent <code>Cache-Control: no-store</code>. Do not put a caching layer in front of it that ignores that header.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
		</li>
	</ul>
</div>

<h3><?php esc_html_e( '2. OAuth endpoints, served at the domain root', 'mordenhost-mcp-server' ); ?></h3>

<p class="description">
	<?php echo wp_kses( __( 'These sit at the site root, not under <code>/wp-json/</code>, because MCP clients discover them via RFC 9728 well-known paths that must resolve at the domain apex. Clients find and call them on their own; nothing here needs to be entered anywhere.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
</p>

<div class="mmcp-endpoint-card">
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>.well-known/oauth-authorization-server</code>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>.well-known/oauth-protected-resource</code>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>register</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Dynamic Client Registration', 'mordenhost-mcp-server' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>authorize</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Authorization code + PKCE', 'mordenhost-mcp-server' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>token</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Token exchange and refresh', 'mordenhost-mcp-server' ); ?></span>
	</div>
	<p class="description">
		<?php esc_html_e( 'The two well-known documents stay reachable even when More MCP is disabled, so discovery still answers correctly instead of timing out. The other three return HTTP 503 while the plugin is off.', 'mordenhost-mcp-server' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '3. Legacy REST routes', 'mordenhost-mcp-server' ); ?></h3>

<div class="cloudflare-warning">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<p>
		<strong><?php esc_html_e( 'Use the MCP endpoint instead unless you have a reason not to.', 'mordenhost-mcp-server' ); ?></strong>
		<?php esc_html_e( 'These conventional REST routes predate the MCP surface and cover a small fraction of it. The roughly 170 MCP tools have no REST equivalent. They remain supported for integrations written against them.', 'mordenhost-mcp-server' ); ?>
	</p>
</div>

<p class="mmcp-doc-lead-url">
	<code><?php echo esc_html( $more_mcp_api_rest_url ); ?></code>
	<button type="button" class="button button-small copy-btn" data-copy-text="<?php echo esc_attr( $more_mcp_api_rest_url ); ?>">
		<?php esc_html_e( 'Copy', 'mordenhost-mcp-server' ); ?>
	</button>
</p>
<p class="description">
	<?php echo wp_kses( __( 'Paths below are relative to that base. Every request must carry the API key in an <code>MMCP-Key</code> header; these routes do not accept OAuth tokens.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
</p>

<div class="mmcp-rest-reference">
	<?php foreach ( $more_mcp_api_sections as $more_mcp_api_section ) : ?>
		<div class="mmcp-rest-section">
			<h4><?php echo esc_html( $more_mcp_api_section['title'] ); ?></h4>
			<?php if ( ! empty( $more_mcp_api_section['note'] ) ) : ?>
				<p class="description mmcp-rest-section-note"><?php echo esc_html( $more_mcp_api_section['note'] ); ?></p>
			<?php endif; ?>
			<ul class="mmcp-rest-routes">
				<?php foreach ( $more_mcp_api_section['routes'] as $more_mcp_api_route ) : ?>
					<?php
					list( $more_mcp_api_method, $more_mcp_api_path, $more_mcp_api_desc ) = $more_mcp_api_route;
					?>
					<li>
						<span class="mmcp-method mmcp-method-<?php echo esc_attr( strtolower( $more_mcp_api_method ) ); ?>">
							<?php echo esc_html( $more_mcp_api_method ); ?>
						</span>
						<code><?php echo esc_html( $more_mcp_api_path ); ?></code>
						<span class="mmcp-endpoint-note"><?php echo esc_html( $more_mcp_api_desc ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</div>

<h3><?php esc_html_e( 'WordPress Abilities API', 'mordenhost-mcp-server' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'On WordPress 6.9 and later, every MCP tool is also registered as a WordPress ability under the more-mcp/ namespace, so other plugins can invoke them in-process without an HTTP round trip. This is registration, not a fourth endpoint: the abilities route through the same handlers and the same capability checks as everything above.', 'mordenhost-mcp-server' ); ?>
</p>
