<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use More_MCP\Platform\Registry;
use More_MCP\SEO_Data\Providers as SEODataProviders;
use More_MCP\SEO_Data\Credentials as SEODataCredentials;

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

<!-- ============================================================
     Direction-of-travel framing
     ============================================================ -->
<div class="mmcp-direction-note">
	<div class="mmcp-direction-col">
		<span class="mmcp-direction-icon dashicons dashicons-migrate" aria-hidden="true"></span>
		<h4><?php esc_html_e( 'This panel: WordPress calls out', 'more-mcp' ); ?></h4>
		<p>
			<?php esc_html_e( 'Credentials stored here let this site send requests to an AI provider, such as drafting post copy with ChatGPT or summarizing comments with Claude. Entirely optional, and only useful if something on the site actually makes those calls.', 'more-mcp' ); ?>
		</p>
	</div>
	<div class="mmcp-direction-col is-muted">
		<span class="mmcp-direction-icon dashicons dashicons-admin-links" aria-hidden="true"></span>
		<h4><?php esc_html_e( 'Not this panel: AI clients call in', 'more-mcp' ); ?></h4>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Connection panel */
					__( 'Connecting Claude, ChatGPT, or Cursor <em>to</em> this site needs nothing here, just the URL on the <a href="%s">Connection</a> panel. Adding a provider key does not help with that and is not a prerequisite for it.', 'more-mcp' ),
					esc_url( add_query_arg( 'panel', 'connection', $more_mcp_providers_base ) )
				),
				[ 'a' => [ 'href' => [] ], 'em' => [] ]
			);
			?>
		</p>
	</div>
</div>

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
			<?php esc_html_e( 'Switching a provider off keeps its credentials but stops outbound calls to it. Removing it discards them. Keys are stored in the options table like any other plugin setting, so treat this screen as holding live secrets.', 'more-mcp' ); ?>
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
			<h3><?php esc_html_e( 'No outbound providers, which is the right setting for most sites', 'more-mcp' ); ?></h3>
			<p>
				<?php esc_html_e( 'Nothing here needs configuring for AI clients to connect to this site. Add a provider only if you want WordPress itself to call an AI service, and you have code or a plugin that does so.', 'more-mcp' ); ?>
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
						<?php esc_html_e( 'Test Connection uses the values currently in the fields above, not the saved ones, so you can verify a key before committing it. Save afterwards to keep it.', 'more-mcp' ); ?>
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
		<?php esc_html_e( 'Add an outbound provider', 'more-mcp' ); ?>
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
		<?php esc_html_e( 'Adding a provider only creates the card. Nothing is stored until you fill in its fields and save.', 'more-mcp' ); ?>
	</p>
</div>

<!-- ============================================================
     SEO & analytics data sources
     ============================================================
     A FIXED set of named providers (unlike the add/remove LLM list above),
     rendered from the SEO_Data\Providers catalogue. Each card shows a status
     label and an optional trial badge, matching the mockup this was built to.
     Credentials store under settings[seo_data][<slug>], sanitized by
     Settings_Page::sanitize_settings() against the same catalogue.
     ============================================================ -->
<?php
$more_mcp_seo_providers = SEODataProviders::all();
$more_mcp_seo_index     = 0;
?>
<div class="mmcp-section-head mmcp-providers-head mmcp-seo-data-head">
	<h3><?php esc_html_e( 'SEO & analytics data sources', 'more-mcp' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Connect ranking, traffic, keyword, and backlink data so an agent can see how content is performing, not just edit it. Each source is optional and off until you add its credentials (or, for the free Ahrefs metric, switch it on). Keys are stored in the options table like any other setting — treat this screen as holding live secrets.', 'more-mcp' ); ?>
	</p>
</div>

<div id="seo-data-list">
	<?php
	foreach ( $more_mcp_seo_providers as $more_mcp_seo_slug => $more_mcp_seo_provider ) :
		$more_mcp_seo_status  = SEODataCredentials::status( $more_mcp_seo_slug );
		$more_mcp_seo_config  = SEODataCredentials::config( $more_mcp_seo_slug );
		$more_mcp_seo_enabled = SEODataCredentials::is_enabled( $more_mcp_seo_slug );
		$more_mcp_seo_kind    = $more_mcp_seo_provider['status_kind'];

		

		$more_mcp_seo_sa      = ( 'service_account' === $more_mcp_seo_kind );
		$more_mcp_seo_freeopt = ( 'free-optin' === $more_mcp_seo_kind );

		if ( 'not_configured' === $more_mcp_seo_status ) {
			$more_mcp_seo_status_text  = __( 'Not configured', 'more-mcp' );
			$more_mcp_seo_status_class = 'is-not-configured';
		} elseif ( 'off' === $more_mcp_seo_status ) {
			$more_mcp_seo_status_text  = $more_mcp_seo_freeopt
				? __( 'Off — free, no API key (opt-in)', 'more-mcp' )
				: __( 'Off', 'more-mcp' );
			$more_mcp_seo_status_class = 'is-off';
		} else {
			$more_mcp_seo_status_text  = __( 'Active', 'more-mcp' );
			$more_mcp_seo_status_class = 'is-configured';
		}
		?>
		<div class="platform-item mmcp-seo-data-item<?php echo $more_mcp_seo_enabled ? '' : ' is-disabled'; ?>"
		     data-seo-provider="<?php echo esc_attr( $more_mcp_seo_slug ); ?>">
			<div class="platform-header">
				<div class="platform-info">
					<div class="platform-details">
						<h3 class="platform-name">
							<?php echo esc_html( $more_mcp_seo_provider['label'] ); ?>
							<span class="mmcp-provider-status <?php echo esc_attr( $more_mcp_seo_status_class ); ?>">
								<?php echo esc_html( $more_mcp_seo_status_text ); ?>
							</span>
							<?php if ( ! empty( $more_mcp_seo_provider['trial_badge'] ) ) : ?>
								<span class="mmcp-trial-badge"><?php echo esc_html( $more_mcp_seo_provider['trial_badge'] ); ?></span>
							<?php endif; ?>
						</h3>
					</div>
				</div>
				<div class="platform-actions">
					<label class="switch small" title="<?php esc_attr_e( 'Enable or disable this data source', 'more-mcp' ); ?>">
						<input type="checkbox"
						       name="more_mcp_settings[seo_data][<?php echo esc_attr( $more_mcp_seo_slug ); ?>][enabled]"
						       value="1"
						       <?php checked( $more_mcp_seo_enabled ); ?>>
						<span class="slider"></span>
					</label>
					<?php if ( ! $more_mcp_seo_freeopt ) : ?>
						<button type="button" class="button platform-toggle" aria-label="<?php esc_attr_e( 'Expand / collapse', 'more-mcp' ); ?>">
							<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php

			
			
			if ( ! $more_mcp_seo_freeopt ) :
				?>
				<div class="platform-config" style="display: none;">
					<table class="form-table platform-fields">
						<?php
						foreach ( $more_mcp_seo_provider['fields'] as $more_mcp_seo_field_id => $more_mcp_seo_field ) :
							$more_mcp_seo_field_name  = "more_mcp_settings[seo_data][{$more_mcp_seo_slug}][{$more_mcp_seo_field_id}]";
							$more_mcp_seo_field_value = $more_mcp_seo_config[ $more_mcp_seo_field_id ] ?? '';
							$more_mcp_seo_field_dom   = 'seo-' . $more_mcp_seo_slug . '-' . $more_mcp_seo_field_id;

							
							
							$more_mcp_seo_sa_stored = ( 'service_account_json' === $more_mcp_seo_field_id && ! empty( $more_mcp_seo_config['client_email'] ) );
							?>
							<tr class="platform-field">
								<th scope="row">
									<label for="<?php echo esc_attr( $more_mcp_seo_field_dom ); ?>">
										<?php echo esc_html( $more_mcp_seo_field['label'] ); ?>
										<?php if ( ! empty( $more_mcp_seo_field['required'] ) ) : ?>
											<span class="required" aria-hidden="true">*</span>
										<?php endif; ?>
									</label>
								</th>
								<td>
									<?php if ( 'textarea' === $more_mcp_seo_field['type'] ) : ?>
										<?php if ( $more_mcp_seo_sa_stored ) : ?>
											<p class="mmcp-provider-status is-configured" style="display:inline-block;margin:0 0 8px;">
												<?php
												printf(
													/* translators: %s: service account email */
													esc_html__( 'Key stored for %s', 'more-mcp' ),
													esc_html( $more_mcp_seo_config['client_email'] )
												);
												?>
											</p>
										<?php endif; ?>
										<textarea
											name="<?php echo esc_attr( $more_mcp_seo_field_name ); ?>"
											id="<?php echo esc_attr( $more_mcp_seo_field_dom ); ?>"
											class="large-text code"
											rows="6"
											spellcheck="false"
											autocomplete="off"
											placeholder="<?php echo esc_attr( $more_mcp_seo_field['placeholder'] ?? '' ); ?>"></textarea>
										<?php if ( $more_mcp_seo_sa_stored ) : ?>
											<p class="description"><?php esc_html_e( 'A key is already stored. Leave this blank to keep it, or paste a new key JSON to replace it.', 'more-mcp' ); ?></p>
										<?php endif; ?>
									<?php elseif ( 'password' === $more_mcp_seo_field['type'] ) : ?>
										<input type="password"
										       name="<?php echo esc_attr( $more_mcp_seo_field_name ); ?>"
										       id="<?php echo esc_attr( $more_mcp_seo_field_dom ); ?>"
										       value="<?php echo esc_attr( $more_mcp_seo_field_value ); ?>"
										       class="regular-text"
										       placeholder="<?php echo esc_attr( $more_mcp_seo_field['placeholder'] ?? '' ); ?>"
										       autocomplete="new-password">
										<button type="button" class="button toggle-password" title="<?php esc_attr_e( 'Show/Hide', 'more-mcp' ); ?>">
											<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										</button>
									<?php else : ?>
										<input type="text"
										       name="<?php echo esc_attr( $more_mcp_seo_field_name ); ?>"
										       id="<?php echo esc_attr( $more_mcp_seo_field_dom ); ?>"
										       value="<?php echo esc_attr( $more_mcp_seo_field_value ); ?>"
										       class="regular-text"
										       placeholder="<?php echo esc_attr( $more_mcp_seo_field['placeholder'] ?? '' ); ?>">
									<?php endif; ?>
									<?php if ( ! empty( $more_mcp_seo_field['help'] ) ) : ?>
										<p class="description"><?php echo esc_html( $more_mcp_seo_field['help'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<div class="platform-footer">
						<div class="platform-links">
							<?php if ( ! empty( $more_mcp_seo_provider['signup_url'] ) ) : ?>
								<a href="<?php echo esc_url( $more_mcp_seo_provider['signup_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<?php echo $more_mcp_seo_sa ? esc_html__( 'Create service account', 'more-mcp' ) : esc_html__( 'Get API Key', 'more-mcp' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $more_mcp_seo_provider['docs_url'] ) ) : ?>
								<a href="<?php echo esc_url( $more_mcp_seo_provider['docs_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-book" aria-hidden="true"></span>
									<?php esc_html_e( 'Documentation', 'more-mcp' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
