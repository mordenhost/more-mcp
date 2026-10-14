<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Mordenhost_MCP\SEO_Data\Providers as SEODataProviders;
use Mordenhost_MCP\SEO_Data\Credentials as SEODataCredentials;

$mordenhost_mcp_seo_providers = SEODataProviders::all();
?>

<p class="description mmcp-service-note">
	<?php esc_html_e( 'Each source is off until you add its credentials. Keys live in the options table, so treat this screen as holding live secrets.', 'mordenhost-mcp' ); ?>
</p>

<div id="seo-data-list">
	<?php
	foreach ( $mordenhost_mcp_seo_providers as $mordenhost_mcp_seo_slug => $mordenhost_mcp_seo_provider ) :
		$mordenhost_mcp_seo_status  = SEODataCredentials::status( $mordenhost_mcp_seo_slug );
		$mordenhost_mcp_seo_config  = SEODataCredentials::config( $mordenhost_mcp_seo_slug );
		$mordenhost_mcp_seo_enabled = SEODataCredentials::is_enabled( $mordenhost_mcp_seo_slug );
		$mordenhost_mcp_seo_kind    = $mordenhost_mcp_seo_provider['status_kind'];

		

		$mordenhost_mcp_seo_sa      = ( 'service_account' === $mordenhost_mcp_seo_kind );

		if ( 'not_configured' === $mordenhost_mcp_seo_status ) {
			$mordenhost_mcp_seo_status_text  = __( 'Not configured', 'mordenhost-mcp' );
			$mordenhost_mcp_seo_status_class = 'is-not-configured';
		} elseif ( 'off' === $mordenhost_mcp_seo_status ) {
			$mordenhost_mcp_seo_status_text  = __( 'Off', 'mordenhost-mcp' );
			$mordenhost_mcp_seo_status_class = 'is-off';
		} else {
			$mordenhost_mcp_seo_status_text  = __( 'Active', 'mordenhost-mcp' );
			$mordenhost_mcp_seo_status_class = 'is-configured';
		}
		?>
		<div class="platform-item mmcp-seo-data-item<?php echo $mordenhost_mcp_seo_enabled ? '' : ' is-disabled'; ?>"
		     data-seo-provider="<?php echo esc_attr( $mordenhost_mcp_seo_slug ); ?>">
			<div class="platform-header">
				<div class="platform-info">
					<div class="platform-details">
						<h3 class="platform-name">
							<?php echo esc_html( $mordenhost_mcp_seo_provider['label'] ); ?>
							<span class="mmcp-provider-status <?php echo esc_attr( $mordenhost_mcp_seo_status_class ); ?>">
								<?php echo esc_html( $mordenhost_mcp_seo_status_text ); ?>
							</span>
							<?php if ( ! empty( $mordenhost_mcp_seo_provider['trial_badge'] ) ) : ?>
								<span class="mmcp-trial-badge"><?php echo esc_html( $mordenhost_mcp_seo_provider['trial_badge'] ); ?></span>
							<?php endif; ?>
						</h3>
					</div>
				</div>
				<div class="platform-actions">
					<label class="switch small" title="<?php esc_attr_e( 'Enable or disable this data source', 'mordenhost-mcp' ); ?>">
						<input type="checkbox"
						       name="mordenhost_mcp_settings[seo_data][<?php echo esc_attr( $mordenhost_mcp_seo_slug ); ?>][enabled]"
						       value="1"
						       <?php checked( $mordenhost_mcp_seo_enabled ); ?>>
						<span class="slider"></span>
					</label>
					<button type="button" class="button platform-toggle" aria-label="<?php esc_attr_e( 'Expand / collapse', 'mordenhost-mcp' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<?php

			
			?>
				<div class="platform-config" style="display: none;">
					<table class="form-table platform-fields">
						<?php
						foreach ( $mordenhost_mcp_seo_provider['fields'] as $mordenhost_mcp_seo_field_id => $mordenhost_mcp_seo_field ) :
							$mordenhost_mcp_seo_field_name  = "mordenhost_mcp_settings[seo_data][{$mordenhost_mcp_seo_slug}][{$mordenhost_mcp_seo_field_id}]";
							$mordenhost_mcp_seo_field_value = $mordenhost_mcp_seo_config[ $mordenhost_mcp_seo_field_id ] ?? '';
							$mordenhost_mcp_seo_field_dom   = 'seo-' . $mordenhost_mcp_seo_slug . '-' . $mordenhost_mcp_seo_field_id;

							
							
							$mordenhost_mcp_seo_sa_stored = ( 'service_account_json' === $mordenhost_mcp_seo_field_id && ! empty( $mordenhost_mcp_seo_config['client_email'] ) );
							?>
							<tr class="platform-field">
								<th scope="row">
									<label for="<?php echo esc_attr( $mordenhost_mcp_seo_field_dom ); ?>">
										<?php echo esc_html( $mordenhost_mcp_seo_field['label'] ); ?>
										<?php if ( ! empty( $mordenhost_mcp_seo_field['required'] ) ) : ?>
											<span class="required" aria-hidden="true">*</span>
										<?php endif; ?>
									</label>
								</th>
								<td>
									<?php if ( 'textarea' === $mordenhost_mcp_seo_field['type'] ) : ?>
										<?php if ( $mordenhost_mcp_seo_sa_stored ) : ?>
											<p class="mmcp-provider-status is-configured" style="display:inline-block;margin:0 0 8px;">
												<?php
												printf(
													/* translators: %s: service account email */
													esc_html__( 'Key stored for %s', 'mordenhost-mcp' ),
													esc_html( $mordenhost_mcp_seo_config['client_email'] )
												);
												?>
											</p>
										<?php endif; ?>
										<textarea
											name="<?php echo esc_attr( $mordenhost_mcp_seo_field_name ); ?>"
											id="<?php echo esc_attr( $mordenhost_mcp_seo_field_dom ); ?>"
											class="large-text code"
											rows="6"
											spellcheck="false"
											autocomplete="off"
											placeholder="<?php echo esc_attr( $mordenhost_mcp_seo_field['placeholder'] ?? '' ); ?>"></textarea>
										<?php if ( $mordenhost_mcp_seo_sa_stored ) : ?>
											<p class="description"><?php esc_html_e( 'A key is already stored. Leave this blank to keep it, or paste a new key JSON to replace it.', 'mordenhost-mcp' ); ?></p>
										<?php endif; ?>
									<?php elseif ( 'password' === $mordenhost_mcp_seo_field['type'] ) : ?>
										<input type="password"
										       name="<?php echo esc_attr( $mordenhost_mcp_seo_field_name ); ?>"
										       id="<?php echo esc_attr( $mordenhost_mcp_seo_field_dom ); ?>"
										       value="<?php echo esc_attr( $mordenhost_mcp_seo_field_value ); ?>"
										       class="regular-text"
										       placeholder="<?php echo esc_attr( $mordenhost_mcp_seo_field['placeholder'] ?? '' ); ?>"
										       autocomplete="new-password">
										<button type="button" class="button toggle-password" title="<?php esc_attr_e( 'Show/Hide', 'mordenhost-mcp' ); ?>">
											<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										</button>
									<?php else : ?>
										<input type="text"
										       name="<?php echo esc_attr( $mordenhost_mcp_seo_field_name ); ?>"
										       id="<?php echo esc_attr( $mordenhost_mcp_seo_field_dom ); ?>"
										       value="<?php echo esc_attr( $mordenhost_mcp_seo_field_value ); ?>"
										       class="regular-text"
										       placeholder="<?php echo esc_attr( $mordenhost_mcp_seo_field['placeholder'] ?? '' ); ?>">
									<?php endif; ?>
									<?php if ( ! empty( $mordenhost_mcp_seo_field['help'] ) ) : ?>
										<p class="description"><?php echo esc_html( $mordenhost_mcp_seo_field['help'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<div class="platform-footer">
						<div class="platform-links">
							<?php if ( ! empty( $mordenhost_mcp_seo_provider['signup_url'] ) ) : ?>
								<a href="<?php echo esc_url( $mordenhost_mcp_seo_provider['signup_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<?php echo $mordenhost_mcp_seo_sa ? esc_html__( 'Create service account', 'mordenhost-mcp' ) : esc_html__( 'Get API Key', 'mordenhost-mcp' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $mordenhost_mcp_seo_provider['docs_url'] ) ) : ?>
								<a href="<?php echo esc_url( $mordenhost_mcp_seo_provider['docs_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
									<span class="dashicons dashicons-book" aria-hidden="true"></span>
									<?php esc_html_e( 'Documentation', 'mordenhost-mcp' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>

		</div>
	<?php endforeach; ?>
</div>
