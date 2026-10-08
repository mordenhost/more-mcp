<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_perm_enabled    = ! empty( $more_mcp_settings['enabled'] );
$more_mcp_perm_options    = ! empty( $more_mcp_settings['allow_option_writes'] );
$more_mcp_perm_theme      = ! empty( $more_mcp_settings['allow_theme_writes'] );
$more_mcp_perm_lifecycle  = ! empty( $more_mcp_settings['allow_plugin_management'] );
$more_mcp_perm_ai_media   = ! empty( $more_mcp_settings['allow_ai_media'] );

$more_mcp_dep_ai_providers = class_exists( '\\More_MCP\\Integrations\\AIMedia' )
	? \More_MCP\Integrations\AIMedia::has_provider_configured()
	: false;

$more_mcp_dep_ai_link = function_exists( 'wp_ai_client_prompt' )
	? admin_url( 'options-connectors.php' )
	: add_query_arg( 'panel', 'permissions', admin_url( 'admin.php?page=more-mcp' ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.
$more_mcp_perm_view = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : '';
$more_mcp_perm_view = in_array( $more_mcp_perm_view, [ 'wordpress', 'plugins', 'access' ], true ) ? $more_mcp_perm_view : 'wordpress';

$more_mcp_perm_base = add_query_arg( 'panel', 'permissions', admin_url( 'admin.php?page=more-mcp' ) );

$more_mcp_wo_admin_arr = isset( $more_mcp_settings['writable_options_admin'] ) && is_array( $more_mcp_settings['writable_options_admin'] )
	? $more_mcp_settings['writable_options_admin']
	: [];
$more_mcp_have_presets = class_exists( '\More_MCP\Admin\Option_Presets' );
$more_mcp_core_source_key = $more_mcp_have_presets ? \More_MCP\Admin\Option_Presets::SOURCE_CORE : 'wordpress';
$more_mcp_wo_split = $more_mcp_have_presets
	? \More_MCP\Admin\Option_Presets::split_stored( $more_mcp_wo_admin_arr )
	: [ 'sources' => [], 'custom' => $more_mcp_wo_admin_arr ];
$more_mcp_summaries = $more_mcp_have_presets
	? \More_MCP\Admin\Option_Presets::source_summaries()
	: [];
$more_mcp_core_summary = $more_mcp_summaries[ $more_mcp_core_source_key ] ?? null;
$more_mcp_core_on      = ! empty( $more_mcp_wo_split['sources'][ $more_mcp_core_source_key ] );
$more_mcp_wo_custom    = $more_mcp_wo_split['custom'];
?>

<!-- Master switch — compact single line, saves on change -->
<div class="mmcp-master-switch <?php echo $more_mcp_perm_enabled ? 'is-on' : 'is-off'; ?>">
	<label class="switch">
		<input type="checkbox"
		       class="mmcp-scope-toggle"
		       data-scope="enabled"
		       id="enabled"
		       value="1"
		       <?php checked( $more_mcp_perm_enabled ); ?> <?php disabled( \More_MCP\Platform\Locked_Settings::is_locked( 'more_mcp_settings', 'enabled' ) ); ?>>
		<span class="slider"></span>
	</label>
	<div class="mmcp-master-copy">
		<h3>
			<label for="enabled"><?php esc_html_e( 'MCP server', 'mordenhost-mcp-server' ); ?></label>
			<span class="mmcp-flag <?php echo $more_mcp_perm_enabled ? 'mmcp-flag-on' : 'mmcp-flag-off'; ?>">
				<?php echo $more_mcp_perm_enabled ? esc_html__( 'enabled', 'mordenhost-mcp-server' ) : esc_html__( 'disabled', 'mordenhost-mcp-server' ); ?>
			</span>
		</h3>
		<p class="description">
			<?php esc_html_e( 'AI clients can connect and call tools. Content editing is always available; the scopes below are extra. Every gate sits on top of WordPress capability checks, never instead of them.', 'mordenhost-mcp-server' ); ?>
		</p>
		<?php echo \More_MCP\Platform\Locked_Settings::lock_note( 'more_mcp_settings', 'enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside lock_note(). ?>
	</div>
</div>

<?php
?>

<!-- Sub-tabs: WordPress / Plugins -->
<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Permission groups', 'mordenhost-mcp-server' ); ?>">
	<a href="<?php echo esc_url( add_query_arg( 'sub', 'wordpress', $more_mcp_perm_base ) ); ?>"
	   class="mmcp-subtab<?php echo 'wordpress' === $more_mcp_perm_view ? ' is-active' : ''; ?>"
	   <?php echo 'wordpress' === $more_mcp_perm_view ? 'aria-current="page"' : ''; ?>>
		<?php esc_html_e( 'WordPress', 'mordenhost-mcp-server' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'sub', 'plugins', $more_mcp_perm_base ) ); ?>"
	   class="mmcp-subtab<?php echo 'plugins' === $more_mcp_perm_view ? ' is-active' : ''; ?>"
	   <?php echo 'plugins' === $more_mcp_perm_view ? 'aria-current="page"' : ''; ?>>
		<?php esc_html_e( 'Plugins', 'mordenhost-mcp-server' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'sub', 'access', $more_mcp_perm_base ) ); ?>"
	   class="mmcp-subtab<?php echo 'access' === $more_mcp_perm_view ? ' is-active' : ''; ?>"
	   <?php echo 'access' === $more_mcp_perm_view ? 'aria-current="page"' : ''; ?>>
		<?php esc_html_e( 'Access control', 'mordenhost-mcp-server' ); ?>
	</a>
</nav>

<?php if ( 'wordpress' === $more_mcp_perm_view ) : ?>

	<div class="mmcp-perm-group">

		<!-- Reversible band: scopes whose damage undoes cleanly — a wrong value
		     is edited back, nothing is destroyed. Grouped so the severity of the
		     High-impact band below reads by contrast. -->
		<div class="mmcp-scope-band mmcp-scope-band-reversible">
			<div class="mmcp-scope-band-head">
				<h3 class="mmcp-scope-band-title"><?php esc_html_e( 'Reversible', 'mordenhost-mcp-server' ); ?></h3>
				<p class="mmcp-scope-band-note"><?php esc_html_e( 'A wrong value here is edited back. Nothing is deleted and no money is spent.', 'mordenhost-mcp-server' ); ?></p>
			</div>

			<!-- Site options -->
		<div class="mmcp-scope <?php echo $more_mcp_perm_options ? 'is-on' : ''; ?>">
			<div class="mmcp-scope-head">
				<label class="switch small">
					<input type="checkbox"
					       class="mmcp-scope-toggle"
					       data-scope="allow_option_writes"
					       id="allow_option_writes"
					       value="1"
					       <?php checked( $more_mcp_perm_options ); ?>>
					<span class="slider"></span>
				</label>
				<div class="mmcp-scope-title">
					<h4><label for="allow_option_writes"><?php esc_html_e( 'Site options', 'mordenhost-mcp-server' ); ?></label></h4>
					<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Reversible', 'mordenhost-mcp-server' ); ?></span>
				</div>
			</div>
			<div class="mmcp-scope-body">
				<p class="description">
					<?php echo wp_kses( __( 'Lets agents change core WordPress settings and the permalink structure through <code>wp_update_option</code>. Only allowlisted names are writable, out of the box just five harmless site settings.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
				</p>

				<?php if ( $more_mcp_core_summary ) : ?>
					<div class="mmcp-scope-field">
						<div class="mmcp-preset-toggle<?php echo $more_mcp_core_on ? ' is-on' : ''; ?>">
							<div class="mmcp-preset-toggle-head">
								<label class="switch small">
									<input type="checkbox"
									       class="mmcp-source-toggle"
									       data-slug="<?php echo esc_attr( $more_mcp_core_source_key ); ?>"
									       id="mmcp-src-wordpress"
									       value="1"
									       <?php checked( $more_mcp_core_on ); ?>>
									<span class="slider"></span>
								</label>
								<div class="mmcp-preset-toggle-title">
									<label for="mmcp-src-wordpress"><?php echo esc_html( $more_mcp_core_summary['label'] ); ?></label>
									<span class="mmcp-preset-toggle-count">
										<?php
										$more_mcp_core_count = count( $more_mcp_core_summary['names'] );
										printf(
											/* translators: %s: number of settings */
											esc_html( _n( '%s setting', '%s settings', $more_mcp_core_count, 'mordenhost-mcp-server' ) ),
											esc_html( number_format_i18n( $more_mcp_core_count ) )
										);
										?>
									</span>
								</div>
							</div>
							<?php foreach ( $more_mcp_core_summary['cautions'] as $more_mcp_caution ) : ?>
								<p class="mmcp-preset-caution">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php echo esc_html( $more_mcp_caution ); ?>
								</p>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<button type="button" class="advanced-toggle" id="mmcp-allowlist-manual-toggle"
				        aria-expanded="<?php echo ! empty( $more_mcp_wo_custom ) ? 'true' : 'false'; ?>"
				        aria-controls="mmcp-allowlist-manual">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add an option name manually', 'mordenhost-mcp-server' ); ?>
				</button>
				<div class="advanced-content" id="mmcp-allowlist-manual" <?php echo ! empty( $more_mcp_wo_custom ) ? '' : 'hidden'; ?>>
					<label for="writable_options_admin" class="screen-reader-text">
						<?php esc_html_e( 'Additional option names, one per line', 'mordenhost-mcp-server' ); ?>
					</label>
					<textarea id="writable_options_admin"
					          class="large-text code mmcp-custom-options"
					          rows="4"
					          placeholder="my_plugin_settings&#10;another_option_key"><?php echo esc_textarea( implode( "\n", $more_mcp_wo_custom ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One database option name per line, for anything the toggles do not cover. These are option names, not the labels on a settings screen. Saves when you click away from the field.', 'mordenhost-mcp-server' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Theme appearance -->
		<div class="mmcp-scope <?php echo $more_mcp_perm_theme ? 'is-on' : ''; ?>">
			<div class="mmcp-scope-head">
				<label class="switch small">
					<input type="checkbox"
					       class="mmcp-scope-toggle"
					       data-scope="allow_theme_writes"
					       id="allow_theme_writes"
					       value="1"
					       <?php checked( $more_mcp_perm_theme ); ?>>
					<span class="slider"></span>
				</label>
				<div class="mmcp-scope-title">
					<h4><label for="allow_theme_writes"><?php esc_html_e( 'Theme appearance', 'mordenhost-mcp-server' ); ?></label></h4>
					<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Visible site-wide', 'mordenhost-mcp-server' ); ?></span>
				</div>
			</div>
			<div class="mmcp-scope-body">
				<p class="description">
					<?php esc_html_e( 'Lets agents change customizer settings and the theme\'s custom CSS. Nothing is destroyed, but every visitor sees a mistake immediately.', 'mordenhost-mcp-server' ); ?>
				</p>
			</div>
		</div>
		</div><!-- /.mmcp-scope-band-reversible -->

		<!-- High-impact band: scopes that change running code, spend real money,
		     or execute third-party code. Each carries a security-ack gate; the
		     band heading makes that shared severity legible before the first
		     switch. -->
		<div class="mmcp-scope-band mmcp-scope-band-critical">
			<div class="mmcp-scope-band-head">
				<h3 class="mmcp-scope-band-title"><?php esc_html_e( 'High impact', 'mordenhost-mcp-server' ); ?></h3>
				<p class="mmcp-scope-band-note"><?php esc_html_e( 'These change the code that runs your site, spend provider credits, or run code More MCP has not reviewed. Each asks you to confirm before it turns on. Keep a working backup.', 'mordenhost-mcp-server' ); ?></p>
			</div>

			<!-- Plugin and theme management -->
		<div class="mmcp-scope mmcp-scope-critical <?php echo $more_mcp_perm_lifecycle ? 'is-on' : ''; ?>">
			<div class="mmcp-scope-head">
				<label class="switch small">
					<input type="checkbox"
					       class="mmcp-scope-toggle"
					       data-scope="allow_plugin_management"
					       data-security-ack="<?php esc_attr_e( 'This lets an agent install, update, activate, deactivate, and delete plugins and themes — the code that runs your site, not just its content. A deleted plugin is not recoverable from a revision.', 'mordenhost-mcp-server' ); ?>"
					       id="allow_plugin_management"
					       value="1"
					       <?php checked( $more_mcp_perm_lifecycle ); ?>>
					<span class="slider"></span>
				</label>
				<div class="mmcp-scope-title">
					<h4><label for="allow_plugin_management"><?php esc_html_e( 'Plugin and theme management', 'mordenhost-mcp-server' ); ?></label></h4>
					<span class="mmcp-scope-risk mmcp-risk-high"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Changes running code', 'mordenhost-mcp-server' ); ?></span>
				</div>
			</div>
			<div class="mmcp-scope-body">
				<p class="description">
					<strong><?php esc_html_e( 'The highest-risk scope on this page.', 'mordenhost-mcp-server' ); ?></strong>
					<?php esc_html_e( 'Agents can install, update, activate, deactivate, and delete plugins and themes. A bad content edit is recoverable from a revision; a deleted plugin is not.', 'mordenhost-mcp-server' ); ?>
				</p>
				<button type="button" class="advanced-toggle mmcp-inline-help-toggle mmcp-disclosure"
				        aria-expanded="false"
				        aria-controls="mmcp-scope-help-plugins">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<?php esc_html_e( 'How this works', 'mordenhost-mcp-server' ); ?>
				</button>
				<div class="advanced-content" id="mmcp-scope-help-plugins" hidden>
					<p class="description">
						<?php esc_html_e( 'Invisible to clients while off. Two-part confirmation on every action, installs are WordPress.org slugs only, and More MCP cannot delete itself. Keep a working backup either way.', 'mordenhost-mcp-server' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- AI media generation (WordPress-level scope, save-on-change) -->
		<?php  ?>
		<?php $more_mcp_ai_ineffective = $more_mcp_perm_ai_media && ! $more_mcp_dep_ai_providers; ?>
		<div class="mmcp-scope mmcp-scope-critical <?php echo $more_mcp_perm_ai_media ? 'is-on' : ''; ?><?php echo $more_mcp_ai_ineffective ? ' is-ineffective' : ''; ?>">
			<div class="mmcp-scope-head">
				<label class="switch small"<?php echo $more_mcp_ai_ineffective ? ' title="' . esc_attr__( 'On, but no AI provider is connected to WordPress, so no tools are exposed yet.', 'mordenhost-mcp-server' ) . '"' : ''; ?>>
					<input type="checkbox"
					       class="mmcp-scope-toggle"
					       data-scope="allow_ai_media"
					       data-security-ack="<?php esc_attr_e( 'This lets an agent generate images and alt text through the AI provider connected to WordPress. Every call spends real money against that account.', 'mordenhost-mcp-server' ); ?>"
					       id="allow_ai_media"
					       value="1"
					       <?php checked( $more_mcp_perm_ai_media ); ?>>
					<span class="slider"></span>
				</label>
				<div class="mmcp-scope-title">
					<h4><label for="allow_ai_media"><?php esc_html_e( 'AI media generation', 'mordenhost-mcp-server' ); ?></label></h4>
					<span class="mmcp-scope-risk mmcp-risk-high"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Spends provider credits', 'mordenhost-mcp-server' ); ?></span>
				</div>
			</div>
			<div class="mmcp-scope-body">
				<p class="description">
					<strong><?php esc_html_e( 'Lets an agent generate images and alt text via the AI provider connected to WordPress.', 'mordenhost-mcp-server' ); ?></strong>
					<?php esc_html_e( 'Uses the provider you connected under Settings → Connectors (WordPress 7.0 or later). Every generation call spends real money against that account, so nothing is exposed until you turn this on.', 'mordenhost-mcp-server' ); ?>
				</p>
				<?php if ( ! $more_mcp_dep_ai_providers ) : ?>
					<?php  ?>
					<?php if ( $more_mcp_perm_ai_media ) : ?>
						<p class="description mmcp-dep-note">
							<span class="mmcp-flag mmcp-flag-warn"><?php esc_html_e( 'On with no provider', 'mordenhost-mcp-server' ); ?></span>
							<?php echo wp_kses(
								sprintf(
									/* translators: %s: URL of the Connectors screen */
									__( 'This switch is on, but WordPress has no AI provider connected, so the ai_generate_* tools are still not exposed. <a href="%s">Connect a provider</a> to make it effective.', 'mordenhost-mcp-server' ),
									esc_url( $more_mcp_dep_ai_link )
								),
								array( 'a' => array( 'href' => array() ) )
							); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<em><?php echo wp_kses(
								sprintf(
									/* translators: %s: URL of the Connectors screen */
									__( 'Requires a connected AI provider first; none is connected yet. <a href="%s">Connect one under Connectors.</a>', 'mordenhost-mcp-server' ),
									esc_url( $more_mcp_dep_ai_link )
								),
								array( 'a' => array( 'href' => array() ) )
							); ?></em>
						</p>
					<?php endif; ?>
				<?php endif; ?>
				<button type="button" class="advanced-toggle mmcp-inline-help-toggle mmcp-disclosure"
				        aria-expanded="false"
				        aria-controls="mmcp-scope-help-aimedia">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<?php esc_html_e( 'How this works', 'mordenhost-mcp-server' ); ?>
				</button>
				<div class="advanced-content" id="mmcp-scope-help-aimedia" hidden>
					<ul class="mmcp-doc-facts is-compact">
						<li>
							<strong><?php esc_html_e( 'Invisible while off', 'mordenhost-mcp-server' ); ?></strong>
							<?php esc_html_e( 'The ai_generate_* tools are never sent to clients, and the toggle is re-checked on execution.', 'mordenhost-mcp-server' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Uses WordPress credentials', 'mordenhost-mcp-server' ); ?></strong>
							<?php esc_html_e( 'No key is stored by this plugin. The provider is connected once under Settings → Connectors and WordPress holds the credentials.', 'mordenhost-mcp-server' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Synchronous images only', 'mordenhost-mcp-server' ); ?></strong>
							<?php esc_html_e( 'Image generation and alt text run in-request. Video is out of scope.', 'mordenhost-mcp-server' ); ?>
						</li>
					</ul>
					<p class="description">
						<?php esc_html_e( 'Leave off unless you want an agent producing images, and watch your provider spend.', 'mordenhost-mcp-server' ); ?>
					</p>
				</div>
			</div>
		</div>

		</div>
		</div><!-- /.mmcp-scope-band-critical -->

	</div>

<?php elseif ( 'access' === $more_mcp_perm_view ) : ?>

	<?php require __DIR__ . '/_access-control.php'; ?>

<?php else : ?>

	<div class="mmcp-perm-group">
		<?php require __DIR__ . '/_plugin-cards.php'; ?>
	</div>

<?php endif; ?>
