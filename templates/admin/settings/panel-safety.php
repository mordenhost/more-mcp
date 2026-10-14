<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_sf        = \Mordenhost_MCP\Access\Safety::current();
$mordenhost_mcp_sf_locked = static function ( $setting ) {
	return \Mordenhost_MCP\Access\Safety::locked( $setting );
};
$mordenhost_mcp_sf_net    = \Mordenhost_MCP\Platform\Multisite::network_paused();
$mordenhost_mcp_sf_rows   = \Mordenhost_MCP\Access\Approvals::recent( 20 );
$mordenhost_mcp_sf_now    = time();

$mordenhost_mcp_sf_switch = static function ( $key, $title, $risk, $risk_class, $description, $extra = '' ) use ( $mordenhost_mcp_sf, $mordenhost_mcp_sf_locked ) {
	$on     = ! empty( $mordenhost_mcp_sf[ $key ] );
	$locked = $mordenhost_mcp_sf_locked( $key );
	?>
	<div class="mmcp-scope <?php echo $on ? 'is-on' : ''; ?>" data-safety-scope="<?php echo esc_attr( $key ); ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox" class="mmcp-safety-switch" id="mmcp-safety-<?php echo esc_attr( $key ); ?>"
				       data-safety="<?php echo esc_attr( $key ); ?>" value="1"
				       <?php checked( $on ); ?> <?php disabled( $locked ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="mmcp-safety-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></label></h4>
				<span class="mmcp-scope-risk <?php echo esc_attr( $risk_class ); ?>"><?php echo esc_html( $risk ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_safety', $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller. ?>
		</div>
	</div>
	<?php
};
?>

<div class="mmcp-perm-group mmcp-safety">

	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'Emergency brakes and guard rails for connected AI clients. They apply to every connection (API key, OAuth clients and named tokens) and every change here is recorded in the Activity Log. Pausing and approvals never touch what you can do in wp-admin yourself.', 'mordenhost-mcp' ); ?>
	</p>

	<span class="mmcp-safety-status" role="status" aria-live="polite"></span>

	<?php if ( $mordenhost_mcp_sf_net ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'The network administrator has paused MCP access for every site on this network. Switching the setting below on or off here does not lift that.', 'mordenhost-mcp' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$mordenhost_mcp_sf_switch(
		'paused',
		__( 'Pause all MCP access', 'mordenhost-mcp' ),
		__( 'Kill switch', 'mordenhost-mcp' ),
		'mmcp-risk-high',
		__( 'Every tool call is refused until you switch this off. Clients stay connected and see a clear "paused" message, so nothing needs to be reconnected afterwards. Use it the moment something looks wrong.', 'mordenhost-mcp' )
	);

	$mordenhost_mcp_sf_switch(
		'force_draft',
		__( 'Save new content as drafts', 'mordenhost-mcp' ),
		__( 'Content stays unpublished', 'mordenhost-mcp' ),
		'mmcp-risk-low',
		__( 'Posts, pages and other public content an agent creates or edits are saved as drafts instead of going live, so a person reviews them first. Content that is already published is left as it is, and the response tells the agent what was held back.', 'mordenhost-mcp' )
	);

	$mordenhost_mcp_sf_switch(
		'allow_privileged',
		__( 'Allow privileged tools', 'mordenhost-mcp' ),
		__( 'Off by default', 'mordenhost-mcp' ),
		'mmcp-risk-high',
		__( 'Switches on the tools that reach past posts, pages and settings: database search-and-replace (always previewed first), reading files under wp-content, and a bridge to any REST route. They need an administrator account, keys and wp-config are never shown, and the REST bridge refuses plugins, themes, users, site settings and application passwords. Only turn this on for connections you trust, and give named tokens the Advanced area only when they need it.', 'mordenhost-mcp' )
	);

	$mordenhost_mcp_sf_switch(
		'disable_destructive',
		__( 'Disable high-impact tools', 'mordenhost-mcp' ),
		__( 'Blocks irreversible actions', 'mordenhost-mcp' ),
		'mmcp-risk-medium',
		__( 'Tools that delete content, install or remove plugins and themes, change users or roles, or run site-wide replacements are refused and hidden from the tool list. Undo and change-history restores stay available.', 'mordenhost-mcp' ),
		'<details class="mmcp-tool-list"><summary>' . esc_html(
			sprintf(
				/* translators: %d: number of tools */
				_n( 'Show the %d tool this covers', 'Show the %d tools this covers', count( \Mordenhost_MCP\Access\Destructive::high_impact_list() ), 'mordenhost-mcp' ),
				count( \Mordenhost_MCP\Access\Destructive::high_impact_list() )
			)
		) . '</summary><p><code>' . esc_html( implode( '</code> <code>', \Mordenhost_MCP\Access\Destructive::high_impact_list() ) ) . '</code></p></details>'
	);
	?>

	<!-- Approvals -->
	<?php
	$mordenhost_mcp_sf_mode_html  = '<div class="mmcp-scope-field"><label for="mmcp-safety-approval-mode"><strong>' . esc_html__( 'Who approves', 'mordenhost-mcp' ) . '</strong></label> ';
	$mordenhost_mcp_sf_mode_html .= '<select id="mmcp-safety-approval-mode" class="mmcp-safety-select" data-safety="approval_mode"' . disabled( \Mordenhost_MCP\Access\Safety::locked( 'approval_mode' ), true, false ) . '>';
	$mordenhost_mcp_sf_mode_html .= '<option value="admin"' . selected( 'admin', $mordenhost_mcp_sf['approval_mode'], false ) . '>' . esc_html__( 'A person in wp-admin (below)', 'mordenhost-mcp' ) . '</option>';
	$mordenhost_mcp_sf_mode_html .= '<option value="chat"' . selected( 'chat', $mordenhost_mcp_sf['approval_mode'], false ) . '>' . esc_html__( 'The user, in the AI chat', 'mordenhost-mcp' ) . '</option>';
	$mordenhost_mcp_sf_mode_html .= '</select>';
	$mordenhost_mcp_sf_mode_html .= '<p class="description">' . esc_html__( 'In chat mode the agent asks you in the conversation and confirms with the approval tool. That is quicker, but it trusts the agent to relay your answer honestly. With a person in wp-admin, nothing runs until someone clicks Approve below.', 'mordenhost-mcp' ) . '</p>' . \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_safety', 'approval_mode' ) . '</div>';

	$mordenhost_mcp_sf_switch(
		'require_approval',
		__( 'Require approval for high-impact actions', 'mordenhost-mcp' ),
		__( 'A person confirms first', 'mordenhost-mcp' ),
		'mmcp-risk-medium',
		__( 'The same set of tools, plus any you list under "Also require approval for these tools" below, is held until the exact call is approved. The agent is told the call is waiting, not that it failed. An approval is good for 15 minutes, for one run, and only for the connection that asked.', 'mordenhost-mcp' ),
		$mordenhost_mcp_sf_mode_html
	);
	?>

	<div class="mmcp-scope is-on" id="mmcp-safety-approvals">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><?php esc_html_e( 'Approval requests', 'mordenhost-mcp' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Open and recent', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<?php if ( ! $mordenhost_mcp_sf_rows ) : ?>
				<p class="description"><?php esc_html_e( 'No approval requests yet. They appear here when "Require approval" is on and an agent attempts a high-impact action or a tool on your approval list.', 'mordenhost-mcp' ); ?></p>
			<?php else : ?>
				<table class="mmcp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'mordenhost-mcp' ); ?></th>
							<th><?php esc_html_e( 'What it would do', 'mordenhost-mcp' ); ?></th>
							<th><?php esc_html_e( 'Requested', 'mordenhost-mcp' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mordenhost-mcp' ); ?></th>
							<th class="mmcp-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'mordenhost-mcp' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $mordenhost_mcp_sf_rows as $mordenhost_mcp_sf_row ) :
							$mordenhost_mcp_sf_expires = (int) strtotime( $mordenhost_mcp_sf_row['expires_at'] . ' UTC' );
							$mordenhost_mcp_sf_status  = $mordenhost_mcp_sf_row['status'];
							if ( 'pending' === $mordenhost_mcp_sf_status && $mordenhost_mcp_sf_expires <= $mordenhost_mcp_sf_now ) {
								$mordenhost_mcp_sf_status = 'expired';
							}
							$mordenhost_mcp_sf_open = 'pending' === $mordenhost_mcp_sf_status;
							?>
							<tr data-approval="<?php echo esc_attr( $mordenhost_mcp_sf_row['id'] ); ?>">
								<td><code><?php echo esc_html( $mordenhost_mcp_sf_row['tool'] ); ?></code></td>
								<td><?php echo esc_html( $mordenhost_mcp_sf_row['summary'] ); ?></td>
								<td>
									<?php
									printf(
										/* translators: %s: human-readable time difference, e.g. "4 mins" */
										esc_html__( '%s ago', 'mordenhost-mcp' ),
										esc_html( human_time_diff( (int) strtotime( $mordenhost_mcp_sf_row['created_at'] . ' UTC' ), $mordenhost_mcp_sf_now ) )
									);
									?>
								</td>
								<td><span class="mmcp-pill mmcp-pill-<?php echo esc_attr( $mordenhost_mcp_sf_status ); ?>"><?php echo esc_html( $mordenhost_mcp_sf_status ); ?></span></td>
								<td class="mmcp-col-action">
									<?php if ( $mordenhost_mcp_sf_open ) : ?>
										<button type="button" class="button button-small button-primary mmcp-approval-btn" data-decision="approve"><?php esc_html_e( 'Approve', 'mordenhost-mcp' ); ?></button>
										<button type="button" class="button button-small mmcp-approval-btn" data-decision="deny"><?php esc_html_e( 'Deny', 'mordenhost-mcp' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Tool lists -->
	<div class="mmcp-scope is-on">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><?php esc_html_e( 'Tool lists', 'mordenhost-mcp' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Fine-grained', 'mordenhost-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">

			<div class="mmcp-scope-field">
				<label for="mmcp-safety-whitelist"><strong><?php esc_html_e( 'Only allow these tools', 'mordenhost-mcp' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'One name or pattern per line. When the list has entries, only matching tools work; everything else is refused and hidden. * matches any run of characters, ? one character, and [a-z] a range. Examples: wp_get_*, wc_get_orders, elementor_*. Leave empty to allow every tool.', 'mordenhost-mcp' ); ?>
				</p>
				<textarea id="mmcp-safety-whitelist" class="large-text code" rows="4" data-safety="whitelist"
				          <?php disabled( $mordenhost_mcp_sf_locked( 'whitelist' ) ); ?>
				          placeholder="wp_get_*&#10;wc_get_orders"><?php echo esc_textarea( implode( "\n", $mordenhost_mcp_sf['whitelist'] ) ); ?></textarea>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_safety', 'whitelist' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>

			<div class="mmcp-scope-field">
				<label for="mmcp-safety-disabled"><strong><?php esc_html_e( 'Never allow these tools', 'mordenhost-mcp' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'Exact tool names, one per line. These are refused and hidden no matter what else is allowed. The connection-health tool cannot be switched off.', 'mordenhost-mcp' ); ?>
				</p>
				<textarea id="mmcp-safety-disabled" class="large-text code" rows="4" data-safety="disabled_tools"
				          <?php disabled( $mordenhost_mcp_sf_locked( 'disabled_tools' ) ); ?>
				          placeholder="wp_delete_plugin&#10;wp_delete_user"><?php echo esc_textarea( implode( "\n", $mordenhost_mcp_sf['disabled_tools'] ) ); ?></textarea>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_safety', 'disabled_tools' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>

			<div class="mmcp-scope-field">
				<label for="mmcp-safety-approval-tools"><strong><?php esc_html_e( 'Also require approval for these tools', 'mordenhost-mcp' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'Exact tool names, one per line, held for approval on top of the high-impact set while "Require approval" is on. For example wp_update_post or wp_create_post, so content changes wait for you too. Tools that only read cannot be listed. Empty by default.', 'mordenhost-mcp' ); ?>
				</p>
				<textarea id="mmcp-safety-approval-tools" class="large-text code" rows="3" data-safety="approval_tools"
				          <?php disabled( $mordenhost_mcp_sf_locked( 'approval_tools' ) ); ?>
				          placeholder="wp_update_post&#10;wp_create_post"><?php echo esc_textarea( implode( "\n", $mordenhost_mcp_sf['approval_tools'] ) ); ?></textarea>
				<?php echo \Mordenhost_MCP\Platform\Locked_Settings::lock_note( 'mordenhost_mcp_safety', 'approval_tools' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="mmcp-safety-save-lists"><?php esc_html_e( 'Save tool lists', 'mordenhost-mcp' ); ?></button>
			</p>
		</div>
	</div>

	<p class="description mmcp-safety-config">
		<?php
		printf(
			/* translators: %s: list of wp-config.php constants */
			esc_html__( 'Operators can also fix these in wp-config.php or the server environment, where they cannot be changed from the admin: %s.', 'mordenhost-mcp' ),
			'<code>MORDENHOST_MCP_PAUSED</code>, <code>MORDENHOST_MCP_FORCE_DRAFT</code>, <code>MORDENHOST_MCP_REQUIRE_APPROVAL</code>, <code>MORDENHOST_MCP_ALLOW_PRIVILEGED</code>, <code>MORDENHOST_MCP_DISABLE_DESTRUCTIVE</code>, <code>MORDENHOST_MCP_APPROVAL_MODE</code>, <code>MORDENHOST_MCP_DISABLED_TOOLS</code>, <code>MORDENHOST_MCP_TOOL_WHITELIST</code>, <code>MORDENHOST_MCP_APPROVAL_TOOLS</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		);
		?>
	</p>
</div>
