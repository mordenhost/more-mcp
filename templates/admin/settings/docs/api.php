<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_api_mcp_url  = isset( $more_mcp_url_https ) ? $more_mcp_url_https : '';
$more_mcp_api_rest_url = isset( $more_mcp_rest_base ) ? $more_mcp_rest_base : rest_url( 'more-mcp/v1/' );
$more_mcp_api_root     = home_url( '/' );

$more_mcp_api_sections = [
	[
		'title'   => __( 'Posts', 'more-mcp' ),
		'routes'  => [
			[ 'GET', '/posts', __( 'List posts', 'more-mcp' ) ],
			[ 'GET', '/posts/{id}', __( 'Get a specific post', 'more-mcp' ) ],
			[ 'POST', '/posts', __( 'Create a new post', 'more-mcp' ) ],
			[ 'PUT', '/posts/{id}', __( 'Update a post', 'more-mcp' ) ],
			[ 'DELETE', '/posts/{id}', __( 'Delete a post', 'more-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Pages', 'more-mcp' ),
		'routes'  => [
			[ 'GET', '/pages', __( 'List pages', 'more-mcp' ) ],
			[ 'GET', '/pages/{id}', __( 'Get a specific page', 'more-mcp' ) ],
			[ 'POST', '/pages', __( 'Create a new page', 'more-mcp' ) ],
			[ 'PUT', '/pages/{id}', __( 'Update a page', 'more-mcp' ) ],
			[ 'DELETE', '/pages/{id}', __( 'Delete a page', 'more-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Media', 'more-mcp' ),
		'routes'  => [
			[ 'GET', '/media', __( 'List media files', 'more-mcp' ) ],
			[ 'GET', '/media/{id}', __( 'Get a specific media file', 'more-mcp' ) ],
			[ 'POST', '/media', __( 'Upload media', 'more-mcp' ) ],
			[ 'DELETE', '/media/{id}', __( 'Delete media', 'more-mcp' ) ],
		],
	],
	[
		'title'   => __( 'Site and search', 'more-mcp' ),
		'routes'  => [
			[ 'GET', '/site', __( 'Get site information', 'more-mcp' ) ],
			[ 'GET', '/search', __( 'Search content', 'more-mcp' ) ],
		],
	],
	[
		'title'   => __( 'WooCommerce products', 'more-mcp' ),
		'note'    => __( 'Present only while WooCommerce is active.', 'more-mcp' ),
		'routes'  => [
			[ 'GET', '/products/attributes', __( 'List product attributes', 'more-mcp' ) ],
			[ 'POST', '/products/attributes', __( 'Create a product attribute', 'more-mcp' ) ],
			[ 'GET', '/products/attributes/{id}/terms', __( 'List terms of an attribute', 'more-mcp' ) ],
			[ 'GET', '/products/{id}/variations', __( 'List variations of a variable product', 'more-mcp' ) ],
			[ 'POST', '/products/{id}/variations', __( 'Create a variation', 'more-mcp' ) ],
			[ 'GET', '/products/{id}/variations/{variation_id}', __( 'Get a variation', 'more-mcp' ) ],
			[ 'PUT', '/products/{id}/variations/{variation_id}', __( 'Update a variation', 'more-mcp' ) ],
			[ 'DELETE', '/products/{id}/variations/{variation_id}', __( 'Delete a variation', 'more-mcp' ) ],
			[ 'POST', '/products/{id}/attributes', __( 'Attach attributes to a product', 'more-mcp' ) ],
		],
	],
];
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'More MCP answers on three separate HTTP surfaces. Almost every integration should use the first one; the others exist for discovery and for backward compatibility.', 'more-mcp' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '1. MCP endpoint, the current surface', 'more-mcp' ); ?></h3>

<div class="mmcp-endpoint-card">
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_mcp_url ); ?></code>
	</div>
	<p class="description">
		<?php esc_html_e( 'JSON-RPC 2.0 over Streamable HTTP, protocol version 2025-11-25. One endpoint carries every method: initialize, tools/list, tools/call, and the rest. GET is used by clients probing for support, DELETE terminates a session, OPTIONS answers CORS preflight.', 'more-mcp' ); ?>
	</p>
	<ul class="mmcp-doc-facts">
		<li>
			<strong><?php esc_html_e( 'Auth', 'more-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'An <code>Authorization: Bearer &lt;token&gt;</code> OAuth token, or the API key in an <code>MMCP-Key</code> header. Both are accepted on the same endpoint.', 'more-mcp' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sessions', 'more-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'The response to <code>initialize</code> carries an <code>Mcp-Session-Id</code> header. Send it back on every subsequent request. A session is bound to the credentials that opened it, so it cannot be reused under different auth.', 'more-mcp' ), [ 'code' => [] ] ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Rate limit', 'more-mcp' ); ?></strong>
			<?php esc_html_e( '60 requests per 60 seconds per IP address. Exceeding it returns HTTP 429.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Caching', 'more-mcp' ); ?></strong>
			<?php echo wp_kses( __( 'Every response under this namespace is sent <code>Cache-Control: no-store</code>. Do not put a caching layer in front of it that ignores that header.', 'more-mcp' ), [ 'code' => [] ] ); ?>
		</li>
	</ul>
</div>

<h3><?php esc_html_e( '2. OAuth endpoints, served at the domain root', 'more-mcp' ); ?></h3>

<p class="description">
	<?php echo wp_kses( __( 'These sit at the site root, not under <code>/wp-json/</code>, because MCP clients discover them via RFC 9728 well-known paths that must resolve at the domain apex. Clients find and call them on their own; nothing here needs to be entered anywhere.', 'more-mcp' ), [ 'code' => [] ] ); ?>
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
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Dynamic Client Registration', 'more-mcp' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-get">GET</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>authorize</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Authorization code + PKCE', 'more-mcp' ); ?></span>
	</div>
	<div class="mmcp-endpoint-row">
		<span class="mmcp-method mmcp-method-post">POST</span>
		<code class="mmcp-endpoint-path"><?php echo esc_html( $more_mcp_api_root ); ?>token</code>
		<span class="mmcp-endpoint-note"><?php esc_html_e( 'Token exchange and refresh', 'more-mcp' ); ?></span>
	</div>
	<p class="description">
		<?php esc_html_e( 'The two well-known documents stay reachable even when More MCP is disabled, so discovery still answers correctly instead of timing out. The other three return HTTP 503 while the plugin is off.', 'more-mcp' ); ?>
	</p>
</div>

<h3><?php esc_html_e( '3. Legacy REST routes', 'more-mcp' ); ?></h3>

<div class="cloudflare-warning">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<p>
		<strong><?php esc_html_e( 'Use the MCP endpoint instead unless you have a reason not to.', 'more-mcp' ); ?></strong>
		<?php esc_html_e( 'These conventional REST routes predate the MCP surface and cover a small fraction of it. The roughly 170 MCP tools have no REST equivalent. They remain supported for integrations written against them.', 'more-mcp' ); ?>
	</p>
</div>

<p class="mmcp-doc-lead-url">
	<code><?php echo esc_html( $more_mcp_api_rest_url ); ?></code>
	<button type="button" class="button button-small copy-btn" data-copy-text="<?php echo esc_attr( $more_mcp_api_rest_url ); ?>">
		<?php esc_html_e( 'Copy', 'more-mcp' ); ?>
	</button>
</p>
<p class="description">
	<?php echo wp_kses( __( 'Paths below are relative to that base. Every request must carry the API key in an <code>MMCP-Key</code> header; these routes do not accept OAuth tokens.', 'more-mcp' ), [ 'code' => [] ] ); ?>
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

<h3><?php esc_html_e( 'WordPress Abilities API', 'more-mcp' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'On WordPress 6.9 and later, every MCP tool is also registered as a WordPress ability under the more-mcp/ namespace, so other plugins can invoke them in-process without an HTTP round trip. This is registration, not a fourth endpoint: the abilities route through the same handlers and the same capability checks as everything above.', 'more-mcp' ); ?>
</p>
