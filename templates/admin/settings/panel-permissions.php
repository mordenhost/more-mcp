<?php
/**
 * Permissions panel — master enable switch and the write-scope toggles.
 *
 * Restructured around risk rather than around the order the settings were added.
 * The master switch stands alone at the top because it is a different kind of
 * control from the rest — everything below it is irrelevant while it is off — and
 * the write scopes are then presented in ascending order of what they let an agent
 * break, each stating plainly what is still enforced regardless of the toggle.
 *
 * That last part is the point of the copy here. Every one of these toggles is a
 * gate that sits ON TOP of WordPress capability checks, never instead of them, and
 * an admin who believes otherwise will either refuse to enable anything or enable
 * everything. Both are worse than an accurate mental model.
 *
 * Rendered by templates/admin/settings.php, which owns the $more_mcp_*
 * variables used below. PHP `use` statements are file-scoped and are NOT
 * inherited across require(), so any namespaced class this partial touches
 * must be imported or fully qualified here.
 */

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

// The stored allowlist is one flat list. Split it for display: names a preset
// covers become ticked checkboxes, everything else stays in the textarea. The
// split is derived on every render rather than stored, so a name that later
// becomes a preset simply moves from the textarea to a ticked box on its own.
$more_mcp_wo_split  = class_exists( '\More_MCP\Admin\Option_Presets' )
	? \More_MCP\Admin\Option_Presets::split_stored( $more_mcp_wo_admin_arr )
	: [ 'preset' => [], 'custom' => $more_mcp_wo_admin_arr ];
$more_mcp_wo_preset = $more_mcp_wo_split['preset'];
$more_mcp_wo_custom = $more_mcp_wo_split['custom'];

$more_mcp_preset_groups = class_exists( '\More_MCP\Admin\Option_Presets' )
	? \More_MCP\Admin\Option_Presets::groups()
	: [];

// How many write scopes are currently open, for the summary line. Content writes
// are always available when the server is on, so they are not counted here —
// this is specifically the count of scopes beyond content.
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
				? esc_html__( 'AI clients can connect and call tools. Which tools succeed still depends on the authorizing user\'s WordPress capabilities and on the scopes below.', 'more-mcp' )
				: esc_html__( 'AI clients cannot connect. Discovery endpoints still answer so a client reports a clear error instead of timing out, but every request is refused and the OAuth endpoints return 503.', 'more-mcp' );
			?>
		</p>
		<?php if ( $more_mcp_perm_enabled ) : ?>
			<p class="description">
				<?php
				if ( 0 === $more_mcp_open_scopes ) {
					esc_html_e( 'No write scopes beyond content are open. Agents can read the site and edit posts, pages, media, and taxonomies, but cannot change settings, appearance, or installed code.', 'more-mcp' );
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
     ============================================================ -->
<div class="mmcp-section">
	<div class="mmcp-section-head">
		<h3><?php esc_html_e( 'What is always enforced', 'more-mcp' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'These are not settings. They hold regardless of every toggle on this page, and they are why the scopes below are narrower than they sound.', 'more-mcp' ); ?>
		</p>
	</div>

	<ul class="mmcp-doc-facts">
		<li>
			<strong><?php esc_html_e( 'WordPress capabilities', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Every tool re-checks the capability its operation requires. An OAuth connector acts as the user who authorized it, so authorizing as an editor produces an editor-scoped agent. Nothing on this page can grant more than the underlying user holds.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sensitive options are permanently blocked', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Site URL, security keys, credentials, and license keys are on a denylist that runs after every allowlist check. No setting or filter on this site can open them for writing.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Content mutations are verified', 'more-mcp' ); ?></strong>
			<?php esc_html_e( 'Writes are read back from the database and the stored value is compared with what was sent, so a value silently altered by a sanitizer or another plugin is reported rather than returned as a clean success. Block edits additionally round-trip through the parser and are abandoned if the tree does not match.', 'more-mcp' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Everything is logged', 'more-mcp' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL of the Activity Log screen */
					__( 'Every call lands in the <a href="%s">Activity Log</a> with its outcome. Tool names and argument keys are recorded; argument values never are, because they carry arbitrary site content.', 'more-mcp' ),
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
			<?php esc_html_e( 'Each scope is off by default and opens a category of writes that content editing does not cover. They are listed in order of how hard the damage is to undo.', 'more-mcp' ); ?>
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
				<?php echo wp_kses( __( 'Lets agents change stored settings through <code>wp_update_option</code>, for example adjusting a plugin\'s configuration on request. Only option names on an allowlist are writable. Out of the box that list is five harmless site settings: <code>blogname</code>, <code>blogdescription</code>, <code>posts_per_page</code>, <code>date_format</code>, and <code>time_format</code>.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</p>
			<p class="description">
				<?php echo wp_kses( __( 'Anything beyond those five has to be added deliberately, either by a plugin author calling the <code>more_mcp_writable_options</code> filter, or by you, in the box below.', 'more-mcp' ), [ 'code' => [] ] ); ?>
			</p>

			<div class="mmcp-scope-field">
				<span class="mmcp-scope-field-label"><?php esc_html_e( 'What agents may change', 'more-mcp' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Tick the settings you want writable. These are grouped by what they affect, so you never have to look up a database option name. The groups offered depend on which plugins are active on this site.', 'more-mcp' ); ?>
				</p>

				<?php if ( empty( $more_mcp_preset_groups ) ) : ?>
					<p class="description warning-text">
						<?php esc_html_e( 'Preset groups are unavailable on this install. Use the manual field below.', 'more-mcp' ); ?>
					</p>
				<?php endif; ?>

				<div class="mmcp-presets">
					<?php
					foreach ( $more_mcp_preset_groups as $more_mcp_pg_slug => $more_mcp_pg ) :
						if ( empty( $more_mcp_pg['options'] ) || ! is_array( $more_mcp_pg['options'] ) ) {
							continue;
						}

						// Count ticked members so the group header can report state
						// without the group having to be expanded.
						$more_mcp_pg_names  = array_keys( $more_mcp_pg['options'] );
						$more_mcp_pg_on     = count( array_intersect( $more_mcp_pg_names, $more_mcp_wo_preset ) );
						$more_mcp_pg_total  = count( $more_mcp_pg_names );
						$more_mcp_pg_is_arr = ( ( $more_mcp_pg['shape'] ?? '' ) === \More_MCP\Admin\Option_Presets::SHAPE_ARRAY );
						// Expand a group that already has selections, so a saved
						// choice is never hidden behind a collapsed header.
						$more_mcp_pg_open = ( $more_mcp_pg_on > 0 );
						?>
						<div class="mmcp-preset-group<?php echo $more_mcp_pg_open ? ' open' : ''; ?>">
							<button type="button" class="mmcp-preset-header" aria-expanded="<?php echo $more_mcp_pg_open ? 'true' : 'false'; ?>">
								<span class="dashicons dashicons-arrow-down-alt2 mmcp-preset-chevron" aria-hidden="true"></span>
								<span class="mmcp-preset-title">
									<?php echo esc_html( $more_mcp_pg['label'] ); ?>
									<?php if ( $more_mcp_pg_is_arr ) : ?>
										<span class="mmcp-flag mmcp-flag-warn"
										      title="<?php esc_attr_e( 'Each of these options holds many settings at once and is replaced wholesale on write.', 'more-mcp' ); ?>">
											<?php esc_html_e( 'bundled', 'more-mcp' ); ?>
										</span>
									<?php endif; ?>
									<small><?php echo esc_html( $more_mcp_pg['description'] ); ?></small>
								</span>
								<span class="mmcp-preset-count<?php echo $more_mcp_pg_on > 0 ? ' is-on' : ''; ?>">
									<?php
									printf(
										/* translators: 1: number selected, 2: number available */
										esc_html__( '%1$s of %2$s', 'more-mcp' ),
										esc_html( number_format_i18n( $more_mcp_pg_on ) ),
										esc_html( number_format_i18n( $more_mcp_pg_total ) )
									);
									?>
								</span>
							</button>

							<div class="mmcp-preset-body">
								<?php if ( ! empty( $more_mcp_pg['caution'] ) ) : ?>
									<p class="mmcp-preset-caution">
										<span class="dashicons dashicons-warning" aria-hidden="true"></span>
										<?php echo esc_html( $more_mcp_pg['caution'] ); ?>
									</p>
								<?php endif; ?>

								<ul class="mmcp-preset-options">
									<?php foreach ( $more_mcp_pg['options'] as $more_mcp_opt_name => $more_mcp_opt_label ) : ?>
										<?php
										$more_mcp_opt_id = 'mmcp-preset-' . sanitize_html_class( $more_mcp_pg_slug . '-' . $more_mcp_opt_name );
										?>
										<li>
											<label for="<?php echo esc_attr( $more_mcp_opt_id ); ?>">
												<input type="checkbox"
												       id="<?php echo esc_attr( $more_mcp_opt_id ); ?>"
												       name="more_mcp_settings[writable_options_preset][]"
												       value="<?php echo esc_attr( $more_mcp_opt_name ); ?>"
												       <?php checked( in_array( (string) $more_mcp_opt_name, $more_mcp_wo_preset, true ) ); ?>>
												<span class="mmcp-preset-option-label"><?php echo esc_html( $more_mcp_opt_label ); ?></span>
												<code><?php echo esc_html( $more_mcp_opt_name ); ?></code>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
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
						<?php esc_html_e( 'One option name per line, for anything the groups above do not cover. These are database option names, not the labels shown on a settings screen.', 'more-mcp' ); ?>
					</p>
					<p class="description">
						<?php echo wp_kses( __( 'To find the real name, ask a connected AI client to call <code>wp_get_plugin_settings</code> with the plugin\'s slug. It returns every option that plugin stores, so you can copy the exact name. Guessing is the main reason a hand-typed entry silently does nothing.', 'more-mcp' ), [ 'code' => [] ] ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'A name that later gains a preset moves up into its group automatically on the next page load; nothing is lost either way.', 'more-mcp' ); ?>
					</p>
				</div>

				<?php if ( ! empty( $more_mcp_wo_admin_arr ) && ! $more_mcp_perm_options ) : ?>
					<p class="description warning-text">
						<?php
						printf(
							/* translators: %s: number of selected option names */
							esc_html( _n( '%s option is selected, but the scope above is off — so it is not writable.', '%s options are selected, but the scope above is off — so none of them are writable.', count( $more_mcp_wo_admin_arr ), 'more-mcp' ) ),
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
				<?php esc_html_e( 'Lets agents change customizer settings and the active theme\'s custom CSS. Mistakes here are visible to every visitor immediately, which is the reason it sits above options: nothing is destroyed, but everyone sees it.', 'more-mcp' ); ?>
			</p>
			<p class="description">
				<?php echo wp_kses( __( 'Individual customizer settings need to be allowlisted through the <code>more_mcp_writable_theme_mods</code> filter, and that allowlist ships empty. So with this scope on and no filter registered, custom CSS is writable and theme mods are not. Custom CSS is filtered so script tags cannot be injected.', 'more-mcp' ), [ 'code' => [] ] ); ?>
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
				<strong><?php esc_html_e( 'This is the highest-risk scope on the page.', 'more-mcp' ); ?></strong>
				<?php esc_html_e( 'It lets agents install, update, activate, deactivate, and delete plugins and themes, changing the code that runs on this site, not just its content. A bad content edit is recoverable from a revision. A deleted plugin is not.', 'more-mcp' ); ?>
			</p>
			<ul class="mmcp-doc-facts">
				<li>
					<strong><?php esc_html_e( 'Invisible while off', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'The lifecycle tools are not sent to clients at all, so an agent cannot call what it never saw listed. The toggle is re-checked on execution too, in case a client cached an older tool list.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Two-part confirmation', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'Every mutation needs both an explicit confirmation flag and the exact target slug echoed back. A call without them returns a preview and writes nothing. That preview is the intended first call, not an error.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Installs are slug-only', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'Only WordPress.org slugs are accepted. Arbitrary package URLs are refused by design, because accepting them would turn this into download-and-execute. Filesystem credentials are never accepted or stored.', 'more-mcp' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Self-protection', 'more-mcp' ); ?></strong>
					<?php esc_html_e( 'More MCP cannot deactivate or delete itself. The request is being served by this plugin, so doing so mid-request would cut the connection and leave the caller unable to distinguish success from a crash.', 'more-mcp' ); ?>
				</li>
			</ul>
			<p class="description">
				<?php esc_html_e( 'Leave this off unless you specifically want an agent maintaining updates for you, and keep a working backup either way.', 'more-mcp' ); ?>
			</p>
		</div>
	</div>

</div>
