<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_tm_presets = \More_MCP\Auth\Api_Tokens::presets();
$more_mcp_tm_now     = time();
?>
<div class="mmcp-token-manager" data-admin="<?php echo $more_mcp_tm_is_admin ? '1' : '0'; ?>">

	<h3 class="mmcp-token-heading"><?php esc_html_e( 'Create a token', 'mordenhost-mcp-server' ); ?></h3>

	<div class="mmcp-token-form">
		<div class="mmcp-token-field">
			<label for="mmcp-token-label"><strong><?php esc_html_e( 'Label', 'mordenhost-mcp-server' ); ?></strong></label>
			<input type="text" id="mmcp-token-label" class="regular-text" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Claude Desktop on my laptop', 'mordenhost-mcp-server' ); ?>">
		</div>

		<div class="mmcp-token-field">
			<label for="mmcp-token-preset"><strong><?php esc_html_e( 'Access level', 'mordenhost-mcp-server' ); ?></strong></label>
			<select id="mmcp-token-preset">
				<?php foreach ( $more_mcp_tm_presets as $more_mcp_tm_key => $more_mcp_tm_preset ) : ?>
					<option value="<?php echo esc_attr( $more_mcp_tm_key ); ?>" <?php selected( \More_MCP\Auth\Api_Tokens::PRESET_READ_ONLY, $more_mcp_tm_key ); ?>>
						<?php echo esc_html( $more_mcp_tm_preset['label'] . ' — ' . $more_mcp_tm_preset['description'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="mmcp-token-field mmcp-token-custom" hidden>
			<strong><?php esc_html_e( 'Per-area access', 'mordenhost-mcp-server' ); ?></strong>
			<div class="mmcp-token-matrix"></div>
		</div>

		<?php if ( $more_mcp_tm_is_admin ) : ?>
			<div class="mmcp-token-field">
				<label for="mmcp-token-user"><strong><?php esc_html_e( 'Acts as WordPress user', 'mordenhost-mcp-server' ); ?></strong></label>
				<?php
				wp_dropdown_users(
					array(
						'name'     => 'mmcp-token-user',
						'id'       => 'mmcp-token-user',
						'selected' => get_current_user_id(),
						'who'      => 'authors',
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'The token can never do more than this user could do in wp-admin.', 'mordenhost-mcp-server' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="mmcp-token-field mmcp-token-inline">
			<div>
				<label for="mmcp-token-expires"><strong><?php esc_html_e( 'Expires', 'mordenhost-mcp-server' ); ?></strong></label>
				<select id="mmcp-token-expires">
					<option value="0"><?php esc_html_e( 'Never', 'mordenhost-mcp-server' ); ?></option>
					<option value="7"><?php esc_html_e( 'In 7 days', 'mordenhost-mcp-server' ); ?></option>
					<option value="30" selected><?php esc_html_e( 'In 30 days', 'mordenhost-mcp-server' ); ?></option>
					<option value="90"><?php esc_html_e( 'In 90 days', 'mordenhost-mcp-server' ); ?></option>
					<option value="365"><?php esc_html_e( 'In a year', 'mordenhost-mcp-server' ); ?></option>
				</select>
			</div>
			<div>
				<label for="mmcp-token-rate"><strong><?php esc_html_e( 'Requests per minute', 'mordenhost-mcp-server' ); ?></strong></label>
				<input type="number" id="mmcp-token-rate" min="0" max="6000" value="0" class="small-text">
				<span class="description"><?php esc_html_e( '0 uses the site-wide limit only.', 'mordenhost-mcp-server' ); ?></span>
			</div>
		</div>

		<p>
			<button type="button" class="button button-primary" id="mmcp-token-create"><?php esc_html_e( 'Create token', 'mordenhost-mcp-server' ); ?></button>
			<span class="mmcp-token-status" role="status" aria-live="polite"></span>
		</p>
	</div>

	<div class="mmcp-token-secret" hidden role="alert">
		<p><strong><?php esc_html_e( 'Copy your new token now. It is shown once and cannot be recovered.', 'mordenhost-mcp-server' ); ?></strong></p>
		<div class="mmcp-token-secret-row">
			<input type="text" readonly class="large-text code" id="mmcp-token-secret-value" aria-label="<?php esc_attr_e( 'New token', 'mordenhost-mcp-server' ); ?>">
			<button type="button" class="button" id="mmcp-token-copy"><?php esc_html_e( 'Copy', 'mordenhost-mcp-server' ); ?></button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Send it as an Authorization: Bearer header, or in the MMCP-Key header, to the MCP endpoint of this site.', 'mordenhost-mcp-server' ); ?>
		</p>
		<p><button type="button" class="button button-primary" id="mmcp-token-done"><?php esc_html_e( 'I have saved it', 'mordenhost-mcp-server' ); ?></button></p>
	</div>

	<h3 class="mmcp-token-heading"><?php esc_html_e( 'Tokens', 'mordenhost-mcp-server' ); ?></h3>

	<?php if ( ! $more_mcp_tm_tokens ) : ?>
		<p class="description"><?php esc_html_e( 'No tokens yet.', 'mordenhost-mcp-server' ); ?></p>
	<?php else : ?>
		<table class="mmcp-table mmcp-token-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Label', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'Access', 'mordenhost-mcp-server' ); ?></th>
					<?php if ( $more_mcp_tm_is_admin ) : ?>
						<th><?php esc_html_e( 'User', 'mordenhost-mcp-server' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Last used', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'mordenhost-mcp-server' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mordenhost-mcp-server' ); ?></th>
					<th class="mmcp-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'mordenhost-mcp-server' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $more_mcp_tm_tokens as $more_mcp_tm_row ) :
					$more_mcp_tm_expires = ! empty( $more_mcp_tm_row['expires_at'] ) ? (int) strtotime( $more_mcp_tm_row['expires_at'] . ' UTC' ) : 0;
					if ( ! empty( $more_mcp_tm_row['revoked_at'] ) ) {
						$more_mcp_tm_status = 'revoked';
					} elseif ( $more_mcp_tm_expires && $more_mcp_tm_expires < $more_mcp_tm_now ) {
						$more_mcp_tm_status = 'expired';
					} else {
						$more_mcp_tm_status = 'active';
					}
					$more_mcp_tm_user = get_userdata( (int) $more_mcp_tm_row['user_id'] );
					$more_mcp_tm_last = ! empty( $more_mcp_tm_row['last_used_at'] ) ? (int) strtotime( $more_mcp_tm_row['last_used_at'] . ' UTC' ) : 0;
					?>
					<tr class="<?php echo 'active' === $more_mcp_tm_status ? '' : 'is-revoked'; ?>" data-token="<?php echo esc_attr( $more_mcp_tm_row['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( $more_mcp_tm_row['label'] ); ?></strong><br>
							<code>…<?php echo esc_html( $more_mcp_tm_row['token_hint'] ); ?></code>
						</td>
						<td>
							<?php echo esc_html( $more_mcp_tm_presets[ $more_mcp_tm_row['preset'] ]['label'] ?? $more_mcp_tm_row['preset'] ); ?>
							<?php if ( (int) $more_mcp_tm_row['rate_limit'] > 0 ) : ?>
								<br><span class="description">
									<?php
									printf(
										/* translators: %d: requests per minute */
										esc_html__( '%d / min', 'mordenhost-mcp-server' ),
										(int) $more_mcp_tm_row['rate_limit']
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<?php if ( $more_mcp_tm_is_admin ) : ?>
							<td><?php echo esc_html( $more_mcp_tm_user ? $more_mcp_tm_user->user_login : '#' . (int) $more_mcp_tm_row['user_id'] ); ?></td>
						<?php endif; ?>
						<td>
							<?php
							if ( $more_mcp_tm_last ) {
								printf(
									/* translators: %s: human-readable time difference, e.g. "4 mins" */
									esc_html__( '%s ago', 'mordenhost-mcp-server' ),
									esc_html( human_time_diff( $more_mcp_tm_last, $more_mcp_tm_now ) )
								);
								if ( '' !== $more_mcp_tm_row['last_ip'] ) {
									echo '<br><span class="description">' . esc_html( $more_mcp_tm_row['last_ip'] ) . '</span>';
								}
							} else {
								esc_html_e( 'Never', 'mordenhost-mcp-server' );
							}
							?>
						</td>
						<td><?php echo $more_mcp_tm_expires ? esc_html( wp_date( get_option( 'date_format' ), $more_mcp_tm_expires ) ) : esc_html__( 'Never', 'mordenhost-mcp-server' ); ?></td>
						<td><span class="mmcp-pill mmcp-pill-<?php echo esc_attr( $more_mcp_tm_status ); ?>"><?php echo esc_html( $more_mcp_tm_status ); ?></span></td>
						<td class="mmcp-col-action">
							<?php if ( 'revoked' !== $more_mcp_tm_status ) : ?>
								<button type="button" class="button button-small mmcp-token-revoke"><?php esc_html_e( 'Revoke', 'mordenhost-mcp-server' ); ?></button>
							<?php else : ?>
								<button type="button" class="button button-small mmcp-token-delete"><?php esc_html_e( 'Remove', 'mordenhost-mcp-server' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
