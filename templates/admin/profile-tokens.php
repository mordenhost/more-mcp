<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_tm_tokens   = $tokens;
$more_mcp_tm_is_admin = false;
?>
<h2><?php esc_html_e( 'More MCP API tokens', 'mordenhost-mcp-server' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Tokens let an AI client work on this site as you, with at most the access you choose here. Revoke one and it stops working at once.', 'mordenhost-mcp-server' ); ?>
</p>
<div class="more-mcp-settings mmcp-profile-tokens">
	<?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/_token-manager.php'; ?>
</div>
