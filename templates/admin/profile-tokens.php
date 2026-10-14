<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_tm_tokens   = $tokens;
$mordenhost_mcp_tm_is_admin = false;
?>
<h2><?php esc_html_e( 'Mordenhost MCP API tokens', 'mordenhost-mcp' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Tokens let an AI client work on this site as you, with at most the access you choose here. Revoke one and it stops working at once.', 'mordenhost-mcp' ); ?>
</p>
<div class="mordenhost-mcp-settings mmcp-profile-tokens">
	<?php require MORDENHOST_MCP_PLUGIN_DIR . 'templates/admin/_token-manager.php'; ?>
</div>
