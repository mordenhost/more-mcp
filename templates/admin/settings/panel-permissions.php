<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_perm_enabled   = ! empty( $more_mcp_settings['enabled'] );
$more_mcp_perm_options   = ! empty( $more_mcp_settings['allow_option_writes'] );
$more_mcp_perm_theme     = ! empty( $more_mcp_settings['allow_theme_writes'] );
$more_mcp_perm_lifecycle = ! empty( $more_mcp_settings['allow_plugin_management'] );

$more_mcp_wo_admin_arr = isset( $more_mcp_settings['writable_options_admin'] ) && is_array( $more_mcp_settings['writable_options_admin'] )
	? $more_mcp_settings['writable_options_admin']
	: [];

$more_mcp_wo_split   = class_exists( '\More_MCP\Admin\Option_Presets' )
	? \More_MCP\Admin\Option_Presets::split_stored( $more_mcp_wo_admin_arr )
	: [ 'sources' => [], 'custom' => $more_mcp_wo_admin_arr ];
$more_mcp_wo_sources = $more_mcp_wo_split['sources'];
$more_mcp_wo_custom  = $more_mcp_wo_split['custom'];

$more_mcp_preset_sources = class_exists( '\More_MCP\Admin\Option_Presets' )
	? \More_MCP\Admin\Option_Presets::source_summaries()
	: [];

$more_mcp_open_scopes = (int) $more_mcp_perm_options + (int) $more_mcp_perm_theme + (int) $more_mcp_perm_lifecycle;
?>

<!-- ============================================================
     Master switch
     ============================================================ -->
<div class="mmcp-master-switch <?php echo $more_mcp_perm_enabled ? 'is-on' : 'is-off'; ?>">
	<label class="switch">
		<input type="checkbox"
		       name="more_mcp_settings[enabled]"
		       id="enabled"
		       value="1"
		       <?php checked( $more_mcp_perm_enabled ); ?>>
		<span class="slider"></span>
	</label>
	<div class="mmcp-master-copy">
		<h3>
			<label for="enabled"><?php esc_html_e( 'MCP server', 'more-mcp' ); ?></label>
			<span class="mmcp-flag <?php echo $more_mcp_perm_enabled ? 'mmcp-flag-on' : 'mmcp-flag-off'; ?>">
				<?php
				echo $more_mcp_perm_enabled
					? esc_html__( 'enabled', 'more-mcp' )
					: esc_html__( 'disabled', 'more-mcp' );
				?>
			</span>
		</h3>
		<p class="description">
			<?php
			echo $more_mcp_perm_enabled
				? esc_html__( 'AI clients can connect and call tools. What succeeds still depends on the authorizing user\'s capabilities and the scopes below.', 'more-mcp' )
				: esc_html__( 'AI clients cannot connect. Discovery still answers so a client reports a clear error rather than timing out; every request is refused.', 'more-mcp' );
			?>
		</p>
		<?php if ( $more_mcp_perm_enabled ) : ?>
			<p class="description">
				<?php
				if ( 0 === $more_mcp_open_scopes ) {
					esc_html_e( 'Content only: agents can read the site and edit posts, pages, media, and taxonomies. No settings, appearance, or code.', 'more-mcp' );
				} else {
					printf(
						/* translators: %s: number of additional write scopes enabled */
						esc_html( _n( '%s additional write scope is open beyond content editing.', '%s additional write scopes are open beyond content editing.', $more_mcp_open_scopes, 'more-mcp' ) ),
						esc_html( number_format_i18n( $more_mcp_open_scopes ) )
					);
				}
				?>
			</p>
		<?php endif; ?>
	</div>
</div>

<!-- ============================================================
     Always-on baseline — stated, not a toggle
     ============================================================

     Deliberately terse. This section is context an admin reads once to calibrate
     how much the toggles below actually grant; it is not reference material. The
     long-form version of each point lives in Documentation → Troubleshooting and
     in the per-scope help below, so the facts stay available without four
     paragraphs sitting between the master switch and the first real control.
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'Always enforced', 'more-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Not settings: these hold whatever you switch on below.', 'more-mcp' ); ?>
		</p>
	</div>

	<ul class="mmcp-doc-facts is-compact">
		<li>
			<strong><?php esc_html_e( 'WordPress capabilities', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Every tool re-checks its own capability. An agent never exceeds the user that authorized it.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sensitive options blocked', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Site URL, security keys, and credentials are denylisted. No setting here can open them.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Writes are verified', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Every write is read back and compared, so a silently altered value is reported, not passed off as success.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Everything is logged', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Activity Log screen */
					__( 'Tool names and argument keys land in the <a href="%s">Activity Log</a>. Values never do.', 'more-mcp' ),
					esc_url( admin_url( 'admin.php?page=more-mcp-logs' ) )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</li>
	</ul>
</div>

<!-- ============================================================
     Write scopes, ascending risk
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'Write scopes', 'more-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'All off by default, ordered by how hard the damage is to undo.', 'more-mcp' ); ?>
		</p>
	</div>

	<!-- Options -->
	<div class="mmcp-scope <?php echo $more_mcp_perm_options ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       name="more_mcp_settings[allow_option_writes]"
				       id="allow_option_writes"
				       value="1"
				       <?php checked( $more_mcp_perm_options ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="allow_option_writes"><?php esc_html_e( 'WordPress and plugin options', 'more-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-low"><?php esc_html_e( 'Reversible', 'more-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php echo wp_kses( __( 'Lets agents change stored settings through <code>wp_update_option</code>. Only allowlisted option names are writable, out of the box just five harmless site settings.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</p>

			<div class="mmcp-scope-field">
				<span class="mmcp-scope-field-label"><?php esc_html_e( 'What agents may change', 'more-mcp' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'One switch per product. Turn a product on and an agent may change its settings; leave it off and it cannot. No database option names to look up.', 'more-mcp' ); ?>
				</p>

				<?php if ( empty( $more_mcp_preset_sources ) ) : ?>
					<p class="description warning-text">
						<?php esc_html_e( 'Presets are unavailable on this install. Use the manual field below.', 'more-mcp' ); ?>
					</p>
				<?php endif; ?>

				<div class="mmcp-preset-toggles">
					<?php
					
					foreach ( $more_mcp_preset_sources as $more_mcp_src_slug => $more_mcp_src ) :
						if ( empty( $more_mcp_src['names'] ) ) {
							continue;
						}
						$more_mcp_src_on    = ! empty( $more_mcp_wo_sources[ $more_mcp_src_slug ] );
						$more_mcp_src_count = count( $more_mcp_src['names'] );
						$more_mcp_src_id    = 'mmcp-src-' . sanitize_html_class( $more_mcp_src_slug );
						$more_mcp_src_incl  = 'mmcp-src-incl-' . sanitize_html_class( $more_mcp_src_slug );
						?>
						<div class="mmcp-preset-toggle<?php echo $more_mcp_src_on ? ' is-on' : ''; ?>">
							<div class="mmcp-preset-toggle-head">
								<label class="switch small">
									<input type="checkbox"
									       id="<?php echo esc_attr( $more_mcp_src_id ); ?>"
									       name="more_mcp_settings[writable_options_source][]"
									       value="<?php echo esc_attr( $more_mcp_src_slug ); ?>"
									       <?php checked( $more_mcp_src_on ); ?>>
									<span class="slider"></span>
								</label>
								<div class="mmcp-preset-toggle-title">
									<label for="<?php echo esc_attr( $more_mcp_src_id ); ?>">
										<?php echo esc_html( $more_mcp_src['label'] ); ?>
									</label>
									<span class="mmcp-preset-toggle-count">
										<?php
										printf(
											/* translators: %s: number of settings the product contributes */
											esc_html( _n( '%s setting', '%s settings', $more_mcp_src_count, 'more-mcp' ) ),
											esc_html( number_format_i18n( $more_mcp_src_count ) )
										);
										?>
									</span>
									<?php if ( ! empty( $more_mcp_src['has_array'] ) ) : ?>
										<span class="mmcp-flag mmcp-flag-warn"
										      title="<?php esc_attr_e( 'Some of these options hold many settings at once and are replaced wholesale on write.', 'more-mcp' ); ?>">
											<?php esc_html_e( 'bundled', 'more-mcp' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<?php foreach ( $more_mcp_src['cautions'] as $more_mcp_caution ) : ?>
								<p class="mmcp-preset-caution">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php echo esc_html( $more_mcp_caution ); ?>
								</p>
							<?php endforeach; ?>

							<?php if ( ! empty( $more_mcp_src['breakdown'] ) ) : ?>
								<button type="button" class="mmcp-preset-incl-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $more_mcp_src_incl ); ?>">
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
									<?php esc_html_e( 'What this includes', 'more-mcp' ); ?>
								</button>
								<div class="mmcp-preset-incl" id="<?php echo esc_attr( $more_mcp_src_incl ); ?>" hidden>
									<?php foreach ( $more_mcp_src['breakdown'] as $more_mcp_grp_label => $more_mcp_grp_opts ) : ?>
										<div class="mmcp-preset-incl-group">
											<h5 class="mmcp-preset-incl-title"><?php echo esc_html( $more_mcp_grp_label ); ?></h5>
											<ul class="mmcp-preset-incl-list">
												<?php foreach ( $more_mcp_grp_opts as $more_mcp_grp_opt_label ) : ?>
													<li><?php echo esc_html( $more_mcp_grp_opt_label ); ?></li>
												<?php endforeach; ?>
											</ul>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" class="advanced-toggle" id="mmcp-allowlist-manual-toggle"
				        aria-expanded="<?php echo ! empty( $more_mcp_wo_custom ) ? 'true' : 'false'; ?>"
				        aria-controls="mmcp-allowlist-manual">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add an option name manually', 'more-mcp' ); ?>
					<?php if ( ! empty( $more_mcp_wo_custom ) ) : ?>
						<span class="mmcp-flag mmcp-flag-info">
							<?php
							printf(
								/* translators: %s: number of manually added option names */
								esc_html( _n( '%s added', '%s added', count( $more_mcp_wo_custom ), 'more-mcp' ) ),
								esc_html( number_format_i18n( count( $more_mcp_wo_custom ) ) )
							);
							?>
						</span>
					<?php endif; ?>
				</button>
				<div class="advanced-content" id="mmcp-allowlist-manual" <?php echo ! empty( $more_mcp_wo_custom ) ? '' : 'hidden'; ?>>
					<label for="writable_options_admin" class="screen-reader-text">
						<?php esc_html_e( 'Additional option names, one per line', 'more-mcp' ); ?>
					</label>
					<textarea name="more_mcp_settings[writable_options_admin]"
					          id="writable_options_admin"
					          rows="4"
					          class="large-text code"
					          placeholder="my_plugin_settings&#10;another_option_key"><?php echo esc_textarea( implode( "\n", $more_mcp_wo_custom ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One option name per line, for anything the switches above do not cover, or a single setting you want without turning on its whole product. These are database option names, not the labels shown on a settings screen.', 'more-mcp' ); ?>
					</p>
					<p class="description">
						<?php echo wp_kses( __( 'To find the real name, ask a connected AI client to call <code>wp_get_plugin_settings</code> with the plugin\'s slug. It returns every option that plugin stores, so you can copy the exact name. Guessing is the main reason a hand-typed entry silently does nothing.', 'more-mcp' ), [ 'code' => [] ] ); ?>
					</p>
				</div>

				<?php if ( ! empty( $more_mcp_wo_admin_arr ) && ! $more_mcp_perm_options ) : ?>
					<p class="description warning-text">
						<?php
						printf(
							/* translators: %s: number of selected option names */
							esc_html( _n( '%s option is selected, but the scope above is off, so it is not writable.', '%s options are selected, but the scope above is off, so none of them are writable.', count( $more_mcp_wo_admin_arr ), 'more-mcp' ) ),
							esc_html( number_format_i18n( count( $more_mcp_wo_admin_arr ) ) )
						);
						?>
					</p>
				<?php endif; ?>

				<button type="button" class="advanced-toggle" id="mmcp-allowlist-help-toggle" aria-expanded="false" aria-controls="mmcp-allowlist-help">
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'What an option write actually does', 'more-mcp' ); ?>
				</button>
				<div class="advanced-content" id="mmcp-allowlist-help" hidden>
					<ul class="mmcp-doc-facts">
						<li>
							<strong><?php esc_html_e( 'A write replaces the whole option', 'more-mcp' ); ?></strong>
							<?php esc_html_e( 'Groups marked "bundled" store many settings inside a single option. Writing to one overwrites the entire bundle, so an agent that sends only the key it wants to change discards every other setting in that option. A careful agent reads the current value and writes the merged result back, but nothing forces it to.', 'more-mcp' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'The permanent denylist always wins', 'more-mcp' ); ?></strong>
							<?php echo wp_kses( __( 'The denylist is checked <em>before</em> this list, so adding a blocked name has no effect. Permanently blocked: the site URL and home URL, user roles, active plugins, the active theme, rewrite rules, the cron array, More MCP\'s own settings, and any name containing <code>secret</code>, <code>salt</code>, <code>api_key</code>, <code>license_key</code>, <code>auth_token</code>, <code>private_key</code>, <code>session_token</code>, or a WordPress security key.', 'more-mcp' ), [ 'code' => [], 'em' => [] ] ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Matching is exact', 'more-mcp' ); ?></strong>
							<?php echo wp_kses( __( 'There are no wildcards. <code>my_plugin</code> does not authorize <code>my_plugin_advanced</code>. Every option name must be listed on its own.', 'more-mcp' ), [ 'code' => [] ] ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Every write is logged, and there is no undo', 'more-mcp' ); ?></strong>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: URL of the Activity Log screen */
									__( 'The <a href="%s">Activity Log</a> records each option write, and the previous value is returned to the caller, so a change can be traced and reversed by hand. Unlike content edits, option writes have no undo token.', 'more-mcp' ),
									esc_url( admin_url( 'admin.php?page=more-mcp-logs' ) )
								),
								[ 'a' => [ 'href' => [] ] ]
							);
							?>
						</li>
					</ul>
					<p class="description">
						<?php esc_html_e( 'A practical rule: allow the settings whose wrong value would be obvious and easy to fix. Anything whose failure mode is silent, such as payment configuration, access rules, or search visibility, is worth leaving off even though nothing here prevents it.', 'more-mcp' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Theme -->
	<div class="mmcp-scope <?php echo $more_mcp_perm_theme ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       name="more_mcp_settings[allow_theme_writes]"
				       id="allow_theme_writes"
				       value="1"
				       <?php checked( $more_mcp_perm_theme ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="allow_theme_writes"><?php esc_html_e( 'Theme appearance', 'more-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-medium"><?php esc_html_e( 'Visible site-wide', 'more-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<?php esc_html_e( 'Lets agents change customizer settings and the theme\'s custom CSS. Nothing is destroyed, but every visitor sees a mistake immediately.', 'more-mcp' ); ?>
			</p>
			<p class="description">
				<?php echo wp_kses( __( 'Theme mods need their own allowlist via <code>more_mcp_writable_theme_mods</code>, which ships empty, so with this on and no filter registered, only custom CSS is writable.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</p>
		</div>
	</div>

	<!-- Lifecycle -->
	<div class="mmcp-scope mmcp-scope-critical <?php echo $more_mcp_perm_lifecycle ? 'is-on' : ''; ?>">
		<div class="mmcp-scope-head">
			<label class="switch small">
				<input type="checkbox"
				       name="more_mcp_settings[allow_plugin_management]"
				       id="allow_plugin_management"
				       value="1"
				       <?php checked( $more_mcp_perm_lifecycle ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-scope-title">
				<h4><label for="allow_plugin_management"><?php esc_html_e( 'Plugin and theme management', 'more-mcp' ); ?></label></h4>
				<span class="mmcp-scope-risk mmcp-risk-high"><?php esc_html_e( 'Changes running code', 'more-mcp' ); ?></span>
			</div>
		</div>
		<div class="mmcp-scope-body">
			<p class="description">
				<strong><?php esc_html_e( 'The highest-risk scope on this page.', 'more-mcp' ); ?></strong>
				<?php esc_html_e( 'Agents can install, update, activate, deactivate, and delete plugins and themes: the code that runs the site, not just its content. A bad content edit is recoverable from a revision. A deleted plugin is not.', 'more-mcp' ); ?>
			</p>
			<ul class="mmcp-doc-facts is-compact">
				<li>
					<strong><?php esc_html_e( 'Invisible while off', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'The tools are never sent to clients, and the toggle is re-checked on execution.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Two-part confirmation', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'A confirmation flag plus the exact target slug. Without both, the call returns a preview and writes nothing.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Installs are slug-only', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'WordPress.org slugs only. Package URLs and filesystem credentials are refused.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Self-protection', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'More MCP cannot deactivate or delete itself mid-request.', 'more-mcp' ); ?>
				</li>
			</ul>
			<p class="description">
				<?php esc_html_e( 'Leave off unless you want an agent maintaining updates, and keep a working backup either way.', 'more-mcp' ); ?>
			</p>
		</div>
	</div>

</div>
