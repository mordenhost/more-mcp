<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_sf        = \More_MCP\Access\Safety::current();
$more_mcp_sf_locked = static function ( $setting ) {
	return \More_MCP\Access\Safety::locked( $setting );
};
$more_mcp_sf_net    = \More_MCP\Platform\Multisite::network_paused();
$more_mcp_sf_rows   = \More_MCP\Access\Approvals::recent( 20 );
$more_mcp_sf_now    = time();

$more_mcp_sf_switch = static function ( $key, $title, $risk, $risk_class, $description, $extra = '' ) use ( $more_mcp_sf, $more_mcp_sf_locked ) {
	$on     = ! empty( $more_mcp_sf[ $key ] );
	$locked = $more_mcp_sf_locked( $key );
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
			<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_safety', $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller. ?>
		</div>
	</div>
	<?php
};
?>

<div class="mmcp-perm-group mmcp-safety">

	<p class="mmcp-subtab-summary">
		<?php esc_html_e( 'Emergency brakes and guard rails for connected AI clients. They apply to every connection (API key, OAuth clients and named tokens) and every change here is recorded in the Activity Log. Pausing and approvals never touch what you can do in wp-admin yourself.', 'mordenhost-mcp-server' ); ?>
	</p>

	<span class="mmcp-safety-status" role="status" aria-live="polite"></span>

	<?php if ( $more_mcp_sf_net ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'The network administrator has paused MCP access for every site on this network. Switching the setting below on or off here does not lift that.', 'mordenhost-mcp-server' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$more_mcp_sf_switch(
		'paused',
		__( 'Pause all MCP access', 'mordenhost-mcp-server' ),
		__( 'Kill switch', 'mordenhost-mcp-server' ),
		'mmcp-risk-high',
		__( 'Every tool call is refused until you switch this off. Clients stay connected and see a clear "paused" message, so nothing needs to be reconnected afterwards. Use it the moment something looks wrong.', 'mordenhost-mcp-server' )
	);

	$more_mcp_sf_switch(
		'force_draft',
		__( 'Save new content as drafts', 'mordenhost-mcp-server' ),
		__( 'Content stays unpublished', 'mordenhost-mcp-server' ),
		'mmcp-risk-low',
		__( 'Posts, pages and other public content an agent creates or edits are saved as drafts instead of going live, so a person reviews them first. Content that is already published is left as it is, and the response tells the agent what was held back.', 'mordenhost-mcp-server' )
	);

	$more_mcp_sf_switch(
		'allow_privileged',
		__( 'Allow privileged tools', 'mordenhost-mcp-server' ),
		__( 'Off by default', 'mordenhost-mcp-server' ),
		'mmcp-risk-high',
		__( 'Switches on the tools that reach past posts, pages and settings: database search-and-replace (always previewed first), reading files under wp-content, and a bridge to any REST route. They need an administrator account, keys and wp-config are never shown, and the REST bridge refuses plugins, themes, users, site settings and application passwords. Only turn this on for connections you trust, and give named tokens the Advanced area only when they need it.', 'mordenhost-mcp-server' )
	);

	$more_mcp_sf_switch(
		'disable_destructive',
		__( 'Disable high-impact tools', 'mordenhost-mcp-server' ),
		__( 'Blocks irreversible actions', 'mordenhost-mcp-server' ),
		'mmcp-risk-medium',
		__( 'Tools that delete content, install or remove plugins and themes, change users or roles, or run site-wide replacements are refused and hidden from the tool list. Undo and change-history restores stay available.', 'mordenhost-mcp-server' ),
		'<details class="mmcp-tool-list"><summary>' . esc_html(
			sprintf(
				/* translators: %d: number of tools */
				_n( 'Show the %d tool this covers', 'Show the %d tools this covers', count( \More_MCP\Access\Destructive::high_impact_list() ), 'mordenhost-mcp-server' ),
				count( \More_MCP\Access\Destructive::high_impact_list() )
			)
		) . '</summary><p><code>' . esc_html( implode( '</code> <code>', \More_MCP\Access\Destructive::high_impact_list() ) ) . '</code></p></details>'
	);
	?>

	<!-- Approvals -->
	<?php
	$more_mcp_sf_mode_html  = '<div class="mmcp-scope-field"><label for="mmcp-safety-approval-mode"><strong>' . esc_html__( 'Who approves', 'mordenhost-mcp-server' ) . '</strong></label> ';
	$more_mcp_sf_mode_html .= '<select id="mmcp-safety-approval-mode" class="mmcp-safety-select" data-safety="approval_mode"' . disabled( \More_MCP\Access\Safety::locked( 'approval_mode' ), true, false ) . '>';
	$more_mcp_sf_mode_html .= '<option value="admin"' . selected( 'admin', $more_mcp_sf['approval_mode'], false ) . '>' . esc_html__( 'A person in wp-admin (below)', 'mordenhost-mcp-server' ) . '</option>';
	$more_mcp_sf_mode_html .= '<option value="chat"' . selected( 'chat', $more_mcp_sf['approval_mode'], false ) . '>' . esc_html__( 'The user, in the AI chat', 'mordenhost-mcp-server' ) . '</option>';
	$more_mcp_sf_mode_html .= '</select>';
	$more_mcp_sf_mode_html .= '<p class="description">' . esc_html__( 'In chat mode the agent asks you in the conversation and confirms with the approval tool. That is quicker, but it trusts the agent to relay your answer honestly. With a person in wp-admin, nothing runs until someone clicks Approve below.', 'mordenhost-mcp-server' ) . '</p>' . \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_safety', 'approval_mode' ) . '</div>';

	$more_mcp_sf_switch(
		'require_approval',
		__( 'Require approval for high-impact actions', 'mordenhost-mcp-server' ),
		__( 'A person confirms first', 'mordenhost-mcp-server' ),
		'mmcp-risk-medium',
		__( 'The same set of tools is held until the exact call is approved. An approval is good for 15 minutes, for one run, and only for the connection that asked.', 'mordenhost-mcp-server' ),
		$more_mcp_sf_mode_html
	);
	?>

	<div class="mmcp-scope is-on" id="mmcp-safety-approvals">
		<div class="mmcp-scope-head">
			<div class="mmcp-scope-title">
				<h4><?php esc_html_e( 'Approval requests', 'mordenhost-mcp-server' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Open and recent', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<?php if ( ! $more_mcp_sf_rows ) : ?>
				<p class="description"><?php esc_html_e( 'No approval requests yet. They appear here when "Require approval" is on and an agent attempts a high-impact action.', 'mordenhost-mcp-server' ); ?></p>
			<?php else : ?>
				<table class="mmcp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'mordenhost-mcp-server' ); ?></th>
							<th><?php esc_html_e( 'What it would do', 'mordenhost-mcp-server' ); ?></th>
							<th><?php esc_html_e( 'Requested', 'mordenhost-mcp-server' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mordenhost-mcp-server' ); ?></th>
							<th class="mmcp-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'mordenhost-mcp-server' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $more_mcp_sf_rows as $more_mcp_sf_row ) :
							$more_mcp_sf_expires = (int) strtotime( $more_mcp_sf_row['expires_at'] . ' UTC' );
							$more_mcp_sf_status  = $more_mcp_sf_row['status'];
							if ( 'pending' === $more_mcp_sf_status && $more_mcp_sf_expires <= $more_mcp_sf_now ) {
								$more_mcp_sf_status = 'expired';
							}
							$more_mcp_sf_open = 'pending' === $more_mcp_sf_status;
							?>
							<tr data-approval="<?php echo esc_attr( $more_mcp_sf_row['id'] ); ?>">
								<td><code><?php echo esc_html( $more_mcp_sf_row['tool'] ); ?></code></td>
								<td><?php echo esc_html( $more_mcp_sf_row['summary'] ); ?></td>
								<td>
									<?php
									printf(
										/* translators: %s: human-readable time difference, e.g. "4 mins" */
										esc_html__( '%s ago', 'mordenhost-mcp-server' ),
										esc_html( human_time_diff( (int) strtotime( $more_mcp_sf_row['created_at'] . ' UTC' ), $more_mcp_sf_now ) )
									);
									?>
								</td>
								<td><span class="mmcp-pill mmcp-pill-<?php echo esc_attr( $more_mcp_sf_status ); ?>"><?php echo esc_html( $more_mcp_sf_status ); ?></span></td>
								<td class="mmcp-col-action">
									<?php if ( $more_mcp_sf_open ) : ?>
										<button type="button" class="button button-small button-primary mmcp-approval-btn" data-decision="approve"><?php esc_html_e( 'Approve', 'mordenhost-mcp-server' ); ?></button>
										<button type="button" class="button button-small mmcp-approval-btn" data-decision="deny"><?php esc_html_e( 'Deny', 'mordenhost-mcp-server' ); ?></button>
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
				<h4><?php esc_html_e( 'Tool lists', 'mordenhost-mcp-server' ); ?></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Fine-grained', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">

			<div class="mmcp-scope-field">
				<label for="mmcp-safety-whitelist"><strong><?php esc_html_e( 'Only allow these tools', 'mordenhost-mcp-server' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'One name or pattern per line. When the list has entries, only matching tools work; everything else is refused and hidden. * matches any run of characters, ? one character, and [a-z] a range. Examples: wp_get_*, wc_get_orders, elementor_*. Leave empty to allow every tool.', 'mordenhost-mcp-server' ); ?>
				</p>
				<textarea id="mmcp-safety-whitelist" class="large-text code" rows="4" data-safety="whitelist"
				          <?php disabled( $more_mcp_sf_locked( 'whitelist' ) ); ?>
				          placeholder="wp_get_*&#10;wc_get_orders"><?php echo esc_textarea( implode( "\n", $more_mcp_sf['whitelist'] ) ); ?></textarea>
				<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_safety', 'whitelist' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>

			<div class="mmcp-scope-field">
				<label for="mmcp-safety-disabled"><strong><?php esc_html_e( 'Never allow these tools', 'mordenhost-mcp-server' ); ?></strong></label>
				<p class="description">
					<?php esc_html_e( 'Exact tool names, one per line. These are refused and hidden no matter what else is allowed. The connection-health tool cannot be switched off.', 'mordenhost-mcp-server' ); ?>
				</p>
				<textarea id="mmcp-safety-disabled" class="large-text code" rows="4" data-safety="disabled_tools"
				          <?php disabled( $more_mcp_sf_locked( 'disabled_tools' ) ); ?>
				          placeholder="wp_delete_plugin&#10;wp_delete_user"><?php echo esc_textarea( implode( "\n", $more_mcp_sf['disabled_tools'] ) ); ?></textarea>
				<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_safety', 'disabled_tools' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="mmcp-safety-save-lists"><?php esc_html_e( 'Save tool lists', 'mordenhost-mcp-server' ); ?></button>
			</p>
		</div>
	</div>

	<p class="description mmcp-safety-config">
		<?php
		printf(
			/* translators: %s: list of wp-config.php constants */
			esc_html__( 'Operators can also fix these in wp-config.php or the server environment, where they cannot be changed from the admin: %s.', 'mordenhost-mcp-server' ),
			'<code>MORE_MCP_PAUSED</code>, <code>MORE_MCP_FORCE_DRAFT</code>, <code>MORE_MCP_REQUIRE_APPROVAL</code>, <code>MORE_MCP_ALLOW_PRIVILEGED</code>, <code>MORE_MCP_DISABLE_DESTRUCTIVE</code>, <code>MORE_MCP_APPROVAL_MODE</code>, <code>MORE_MCP_DISABLED_TOOLS</code>, <code>MORE_MCP_TOOL_WHITELIST</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
		);
		?>
	</p>
</div>
