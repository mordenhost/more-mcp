<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use More_MCP\Platform\Registry;

$more_mcp_provider_count = is_array( $more_mcp_configured_platforms ) ? count( $more_mcp_configured_platforms ) : 0;

$more_mcp_provider_active = 0;
if ( is_array( $more_mcp_configured_platforms ) ) {
	foreach ( $more_mcp_configured_platforms as $more_mcp_count_row ) {
		if ( is_array( $more_mcp_count_row ) && ! empty( $more_mcp_count_row['enabled'] ) ) {
			$more_mcp_provider_active++;
		}
	}
}

$more_mcp_providers_base = admin_url( 'admin.php?page=more-mcp' );
?>

<p class="description mmcp-service-note">
	<?php
	echo wp_kses(
		sprintf(
			/* translators: %s: URL of the Connection panel */
			__( 'Only for WordPress calling out: drafting copy, summarizing comments. Connecting Claude or ChatGPT <em>to</em> this site needs nothing here, just the URL on <a href="%s">Connection</a>.', 'more-mcp' ),
			esc_url( add_query_arg( 'panel', 'connection', $more_mcp_providers_base ) )
		),
		[ 'a' => [ 'href' => [] ], 'em' => [] ]
	);
	?>
</p>

<?php if ( $more_mcp_provider_count > 0 ) : ?>
	<div class="mmcp-section-head mmcp-providers-head">
		<h3>
			<?php esc_html_e( 'Configured providers', 'more-mcp' ); ?>
			<span class="mmcp-section-count">
				<?php
				if ( $more_mcp_provider_active === $more_mcp_provider_count ) {
					printf(
						/* translators: %s: number of providers */
						esc_html( _n( '%s provider, active', '%s providers, all active', $more_mcp_provider_count, 'more-mcp' ) ),
						esc_html( number_format_i18n( $more_mcp_provider_count ) )
					);
				} else {
					printf(
						/* translators: 1: number of active providers, 2: total number of providers */
						esc_html__( '%1$s of %2$s active', 'more-mcp' ),
						esc_html( number_format_i18n( $more_mcp_provider_active ) ),
						esc_html( number_format_i18n( $more_mcp_provider_count ) )
					);
				}
				?>
			</span>
		</h3>
		<p class="description">
			<?php esc_html_e( 'Switching one off keeps its key but stops outbound calls. Removing it discards the key. Keys live in the options table: treat this screen as holding live secrets.', 'more-mcp' ); ?>
		</p>
	</div>
<?php endif; ?>

<div id="platforms-list">
	<?php
	if ( empty( $more_mcp_configured_platforms ) ) {
		?>
		<div class="platform-empty-state">
			<div class="empty-icon">
				<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
			</div>
			<h3><?php esc_html_e( 'No providers, which is correct for most sites', 'more-mcp' ); ?></h3>
			<p>
				<?php esc_html_e( 'Add one only if you want WordPress itself to call an AI service, and you have code or a plugin that does so.', 'more-mcp' ); ?>
			</p>
		</div>
		<?php
	} else {
		foreach ( $more_mcp_configured_platforms as $more_mcp_index => $more_mcp_platform_config ) :
			$more_mcp_platform_id = $more_mcp_platform_config['platform'] ?? '';
			$more_mcp_platform    = Registry::get_platform( $more_mcp_platform_id );
			if ( ! $more_mcp_platform ) {
				continue;
			}
			$more_mcp_row_enabled = ! empty( $more_mcp_platform_config['enabled'] );

			
			
			$more_mcp_row_incomplete = false;
			foreach ( $more_mcp_platform['fields'] as $more_mcp_check_id => $more_mcp_check_field ) {
				if ( ! empty( $more_mcp_check_field['required'] ) && empty( $more_mcp_platform_config[ $more_mcp_check_id ] ) ) {
					$more_mcp_row_incomplete = true;
					break;
				}
			}
			?>
			<div class="platform-item<?php echo $more_mcp_row_enabled ? '' : ' is-disabled'; ?>"
			     data-index="<?php echo esc_attr( $more_mcp_index ); ?>"
			     data-platform="<?php echo esc_attr( $more_mcp_platform_id ); ?>">
				<div class="platform-header">
					<div class="platform-info">
						<span class="platform-icon" style="background-color: <?php echo esc_attr( $more_mcp_platform['color'] ); ?>">
							<?php echo esc_html( substr( $more_mcp_platform['label'], 0, 1 ) ); ?>
						</span>
						<div class="platform-details">
							<h3 class="platform-name">
								<?php echo esc_html( $more_mcp_platform['label'] ); ?>
								<?php if ( $more_mcp_row_incomplete ) : ?>
									<span class="mmcp-flag mmcp-flag-warn"
									      title="<?php esc_attr_e( 'A required field is empty. Outbound calls to this provider will fail until it is filled in.', 'more-mcp' ); ?>">
										<?php esc_html_e( 'incomplete', 'more-mcp' ); ?>
									</span>
								<?php elseif ( ! $more_mcp_row_enabled ) : ?>
									<span class="mmcp-flag mmcp-flag-off"><?php esc_html_e( 'off', 'more-mcp' ); ?></span>
								<?php endif; ?>
							</h3>
							<span class="platform-description"><?php echo esc_html( $more_mcp_platform['description'] ); ?></span>
						</div>
					</div>
					<div class="platform-actions">
						<label class="switch small" title="<?php esc_attr_e( 'Enable or disable outbound calls to this provider', 'more-mcp' ); ?>">
							<input type="checkbox"
							       name="more_mcp_settings[platforms][<?php echo esc_attr( $more_mcp_index ); ?>][enabled]"
							       value="1"
							       <?php checked( $more_mcp_row_enabled ); ?>>
							<span class="slider"></span>
						</label>
						<button type="button" class="button platform-toggle" aria-label="<?php esc_attr_e( 'Expand / collapse', 'more-mcp' ); ?>">
							<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
						<button type="button" class="button remove-platform" aria-label="<?php esc_attr_e( 'Remove provider', 'more-mcp' ); ?>">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<div class="platform-config" style="display: none;">
					<input type="hidden"
					       name="more_mcp_settings[platforms][<?php echo esc_attr( $more_mcp_index ); ?>][platform]"
					       value="<?php echo esc_attr( $more_mcp_platform_id ); ?>">

					<table class="form-table platform-fields">
						<?php
						foreach ( $more_mcp_platform['fields'] as $more_mcp_field_id => $more_mcp_field ) :
							$more_mcp_field_name  = "more_mcp_settings[platforms][{$more_mcp_index}][{$more_mcp_field_id}]";
							$more_mcp_field_value = $more_mcp_platform_config[ $more_mcp_field_id ] ?? ( $more_mcp_field['default'] ?? '' );
							?>
							<tr class="platform-field platform-field-<?php echo esc_attr( $more_mcp_field_id ); ?>">
								<th scope="row">
									<label for="platform-<?php echo esc_attr( $more_mcp_index ); ?>-<?php echo esc_attr( $more_mcp_field_id ); ?>">
										<?php echo esc_html( $more_mcp_field['label'] ); ?>
										<?php if ( ! empty( $more_mcp_field['required'] ) ) : ?>
											<span class="required" aria-hidden="true">*</span>
										<?php endif; ?>
									</label>
								</th>
								<td>
									<?php
									switch ( $more_mcp_field['type'] ) {
										case 'select':
											?>
											<select
												name="<?php echo esc_attr( $more_mcp_field_name ); ?>"
												id="platform-<?php echo esc_attr( $more_mcp_index ); ?>-<?php echo esc_attr( $more_mcp_field_id ); ?>"
												class="regular-text"
												data-field="<?php echo esc_attr( $more_mcp_field_id ); ?>"
											>
												<?php foreach ( $more_mcp_field['options'] as $more_mcp_value => $more_mcp_label ) : ?>
													<option value="<?php echo esc_attr( $more_mcp_value ); ?>" <?php selected( $more_mcp_field_value, $more_mcp_value ); ?>>
														<?php echo esc_html( $more_mcp_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<?php
											break;

										case 'password':
											?>
											<input
												type="password"
												name="<?php echo esc_attr( $more_mcp_field_name ); ?>"
												id="platform-<?php echo esc_attr( $more_mcp_index ); ?>-<?php echo esc_attr( $more_mcp_field_id ); ?>"
												value="<?php echo esc_attr( $more_mcp_field_value ); ?>"
												class="regular-text"
												placeholder="<?php echo esc_attr( $more_mcp_field['placeholder'] ?? '' ); ?>"
												data-field="<?php echo esc_attr( $more_mcp_field_id ); ?>"
												autocomplete="new-password"
											>
											<button type="button" class="button toggle-password" title="<?php esc_attr_e( 'Show/Hide', 'more-mcp' ); ?>">
												<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
											</button>
											<?php
											break;

										case 'url':
											?>
											<input
												type="url"
												name="<?php echo esc_attr( $more_mcp_field_name ); ?>"
												id="platform-<?php echo esc_attr( $more_mcp_index ); ?>-<?php echo esc_attr( $more_mcp_field_id ); ?>"
												value="<?php echo esc_attr( $more_mcp_field_value ); ?>"
												class="regular-text"
												placeholder="<?php echo esc_attr( $more_mcp_field['placeholder'] ?? '' ); ?>"
												data-field="<?php echo esc_attr( $more_mcp_field_id ); ?>"
											>
											<?php
											break;

										case 'text':
										default:
											?>
											<input
												type="text"
												name="<?php echo esc_attr( $more_mcp_field_name ); ?>"
												id="platform-<?php echo esc_attr( $more_mcp_index ); ?>-<?php echo esc_attr( $more_mcp_field_id ); ?>"
												value="<?php echo esc_attr( $more_mcp_field_value ); ?>"
												class="regular-text"
												placeholder="<?php echo esc_attr( $more_mcp_field['placeholder'] ?? '' ); ?>"
												data-field="<?php echo esc_attr( $more_mcp_field_id ); ?>"
											>
											<?php
											break;
									}

									if ( ! empty( $more_mcp_field['help'] ) ) :
										?>
										<p class="description"><?php echo esc_html( $more_mcp_field['help'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<div class="platform-footer">
						<div class="platform-links">
							<?php if ( ! empty( $more_mcp_platform['api_key_url'] ) ) : ?>
								<a href="<?php echo esc_url( $more_mcp_platform['api_key_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<?php esc_html_e( 'Get API Key', 'more-mcp' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $more_mcp_platform['docs_url'] ) ) : ?>
								<a href="<?php echo esc_url( $more_mcp_platform['docs_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-book" aria-hidden="true"></span>
									<?php esc_html_e( 'Documentation', 'more-mcp' ); ?>
								</a>
							<?php endif; ?>
						</div>
						<div class="platform-test">
							<button type="button" class="button test-connection">
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php esc_html_e( 'Test Connection', 'more-mcp' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>
					<p class="description platform-test-note">
						<?php esc_html_e( 'Test Connection uses the values in the fields above, not the saved ones. Save afterwards to keep them.', 'more-mcp' ); ?>
					</p>

				</div>

			</div>
			<?php
		endforeach;
	}
	?>
</div>

<div class="add-platform-section">
	<label for="add-platform-select" class="mmcp-add-label">
		<?php esc_html_e( 'Add a provider', 'more-mcp' ); ?>
	</label>
	<div class="add-platform-dropdown">
		<select id="add-platform-select">
			<option value=""><?php esc_html_e( 'Select a provider…', 'more-mcp' ); ?></option>
			<?php foreach ( $more_mcp_platform_groups as $more_mcp_group_id => $more_mcp_group ) : ?>
				<optgroup label="<?php echo esc_attr( $more_mcp_group['label'] ); ?>">
					<?php
					foreach ( $more_mcp_group['platforms'] as $more_mcp_pid ) :
						$more_mcp_p = $more_mcp_platforms[ $more_mcp_pid ] ?? null;
						if ( ! $more_mcp_p ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( $more_mcp_pid ); ?>" data-color="<?php echo esc_attr( $more_mcp_p['color'] ); ?>">
							<?php echo esc_html( $more_mcp_p['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
		<?php
		
		$more_mcp_btn_style_add = 'display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;';
		$more_mcp_svg_style_add = 'width:14px;height:14px;flex-shrink:0;';
		?>
		<button type="button" class="button button-primary" id="add-platform-btn"
		        style="<?php echo esc_attr( $more_mcp_btn_style_add ); ?>">
			<svg style="<?php echo esc_attr( $more_mcp_svg_style_add ); ?>" viewBox="0 0 24 24" fill="none"
			     stroke="currentColor" stroke-width="2" stroke-linecap="round"
			     stroke-linejoin="round" aria-hidden="true">
				<line x1="12" y1="5" x2="12" y2="19"/>
				<line x1="5" y1="12" x2="19" y2="12"/>
			</svg>
			<?php esc_html_e( 'Add Provider', 'more-mcp' ); ?>
		</button>
	</div>
	<p class="description">
		<?php esc_html_e( 'This only creates the card. Nothing is stored until you fill in its fields and save.', 'more-mcp' ); ?>
	</p>
</div>
