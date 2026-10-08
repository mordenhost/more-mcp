<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_tm_tokens   = \More_MCP\Auth\Api_Tokens::all();
$more_mcp_tm_is_admin = true;
$more_mcp_tm_self     = \More_MCP\Auth\Api_Tokens::self_service_enabled();
?>

<div class="mmcp-perm-group mmcp-tokens">

	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'Give each person, client or script its own token instead of sharing the site API key. A token can be limited to reading, to content work, or to areas you pick; it expires on its own and can be revoked on its own. The secret is shown once when you create it and only a hash is stored.', 'mordenhost-mcp-server' ); ?>
	</p>

	<div class="mmcp-scope <?php echo $more_mcp_tm_self ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox" class="mmcp-token-setting" id="mmcp-token-self-service" data-setting="self_service" value="1" <?php checked( $more_mcp_tm_self ); ?> <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_token_settings', 'self_service' ) ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-token-self-service"><?php esc_html_e( 'Let editors and authors create their own tokens', 'mordenhost-mcp-server' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Off by default', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'Anyone who can edit posts gets an "API tokens" section on their profile page, where they can make up to ten tokens that act as themselves, with no more rights than their account has. Administrators keep using this screen.', 'mordenhost-mcp-server' ); ?>
			</p>
			<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_token_settings', 'self_service' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
		</div>
	</div>

	<?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/_token-manager.php'; ?>
</div>
