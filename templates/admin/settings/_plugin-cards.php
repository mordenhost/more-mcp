<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_plugins = class_exists( '\More_MCP\Capabilities\Plugin_Catalog' )
	? \More_MCP\Capabilities\Plugin_Catalog::build( $more_mcp_settings )
	: [];

if ( empty( $more_mcp_plugins ) ) {
	echo '<p class="description">' . esc_html__( 'No supported plugins detected. When you activate a plugin More MCP can work with, it appears here.', 'more-mcp' ) . '</p>';
	return;
}

$more_mcp_docs_tools_url = add_query_arg(
	[ 'panel' => 'docs', 'doc' => 'tools' ],
	admin_url( 'admin.php?page=more-mcp' )
);

$more_mcp_importable_all = class_exists( '\More_MCP\Abilities\Importer' )
	? \More_MCP\Abilities\Importer::importable_abilities()
	: [];
$more_mcp_enabled_abilities = class_exists( '\More_MCP\Abilities\Importer' )
	? array_flip( \More_MCP\Abilities\Importer::enabled_ability_names() )
	: [];
?>

<ul class="mmcp-plugin-cards">
	<?php foreach ( $more_mcp_plugins as $more_mcp_pkey => $more_mcp_plugin ) : ?>
		<?php
		$more_mcp_active = ! empty( $more_mcp_plugin['active'] );
		
		$more_mcp_mark = strtoupper( mb_substr( preg_replace( '/[^A-Za-z0-9]/', '', $more_mcp_plugin['label'] ), 0, 2 ) );
		if ( ! empty( $more_mcp_plugin['via_abilities'] ) ) {
			$more_mcp_mark = '◇';
		}
		?>
		<li class="mmcp-plugin-card<?php echo $more_mcp_active ? '' : ' is-inactive'; ?>">
			<div class="mmcp-plugin-head">
				<span class="mmcp-plugin-mark" aria-hidden="true"><?php echo esc_html( $more_mcp_mark ); ?></span>
				<div class="mmcp-plugin-id">
					<h4><?php echo esc_html( $more_mcp_plugin['label'] ); ?></h4>
					<span class="mmcp-plugin-sub"<?php echo ! empty( $more_mcp_plugin['via_abilities'] ) ? ' title="' . esc_attr__( 'This plugin exposes its actions through the WordPress Abilities API (6.9+), not a built-in More MCP integration.', 'more-mcp' ) . '"' : ''; ?>>
						<?php
						if ( ! empty( $more_mcp_plugin['via_abilities'] ) ) {
							esc_html_e( 'via WP Abilities API', 'more-mcp' );
						} elseif ( $more_mcp_active ) {
							esc_html_e( 'active', 'more-mcp' );
						} else {
							esc_html_e( 'not installed', 'more-mcp' );
						}
						?>
					</span>
				</div>
			</div>

			<?php if ( $more_mcp_active ) : ?>
				<div class="mmcp-plugin-caps">

					<?php if ( is_array( $more_mcp_plugin['tools'] ?? null ) ) : ?>
						<div class="mmcp-cap-row">
							<label class="switch small">
								<input type="checkbox"
								       class="mmcp-integration-toggle"
								       data-slug="<?php echo esc_attr( $more_mcp_pkey ); ?>"
								       value="1"
								       <?php checked( ! empty( $more_mcp_plugin['tools']['enabled'] ) ); ?>>
								<span class="slider"></span>
							</label>
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Tools', 'more-mcp' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $more_mcp_plugin['tools']['count'] ) ); ?></span>
							</span>
							<a class="mmcp-cap-link" href="<?php echo esc_url( $more_mcp_docs_tools_url ); ?>">
								<?php esc_html_e( 'see list', 'more-mcp' ); ?> &rarr;
							</a>
						</div>
					<?php endif; ?>

					<?php if ( is_array( $more_mcp_plugin['settings'] ?? null ) ) : ?>
						<?php $more_mcp_set = $more_mcp_plugin['settings']; ?>
						<div class="mmcp-cap-row">
							<label class="switch small">
								<input type="checkbox"
								       class="mmcp-source-toggle"
								       data-slug="<?php echo esc_attr( $more_mcp_set['source_slug'] ); ?>"
								       <?php disabled( ! $more_mcp_perm_options ); ?>
								       value="1"
								       <?php checked( ! empty( $more_mcp_set['enabled'] ) ); ?>>
								<span class="slider"></span>
							</label>
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Settings', 'more-mcp' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $more_mcp_set['count'] ) ); ?></span>
							</span>
							<?php if ( ! $more_mcp_perm_options ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'enable Site options first', 'more-mcp' ); ?></span>
							<?php elseif ( ! empty( $more_mcp_set['has_array'] ) ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'bundled — replaced wholesale', 'more-mcp' ); ?></span>
							<?php endif; ?>
						</div>
						<?php foreach ( $more_mcp_set['cautions'] as $more_mcp_caution ) : ?>
							<p class="mmcp-preset-caution mmcp-cap-caution">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php echo esc_html( $more_mcp_caution ); ?>
							</p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( is_array( $more_mcp_plugin['abilities'] ?? null ) ) : ?>
						<?php
						$more_mcp_ab_ns    = (string) ( $more_mcp_plugin['abilities']['namespace'] ?? '' );
						$more_mcp_ab_names = $more_mcp_plugin['abilities']['names'];
						$more_mcp_ab_total = (int) $more_mcp_plugin['abilities']['count'];
						$more_mcp_ab_on_ct = (int) $more_mcp_plugin['abilities']['enabled_count'];
						$more_mcp_ab_all   = ! empty( $more_mcp_plugin['abilities']['enabled'] );
						$more_mcp_ab_list_id = 'mmcp-abn-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $more_mcp_ab_ns ) );
						?>
						<div class="mmcp-cap-row">
							<label class="switch small">
								<input type="checkbox"
								       class="mmcp-ability-ns-toggle"
								       data-namespace="<?php echo esc_attr( $more_mcp_ab_ns ); ?>"
								       value="1"
								       <?php checked( $more_mcp_ab_all ); ?>>
								<span class="slider"></span>
							</label>
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Abilities', 'more-mcp' ); ?></strong>
								<span class="mmcp-cap-count">
									<?php

									if ( $more_mcp_ab_on_ct > 0 && $more_mcp_ab_on_ct < $more_mcp_ab_total ) {
										printf(
											/* translators: 1: enabled count, 2: total count */
											esc_html__( '%1$s / %2$s on', 'more-mcp' ),
											esc_html( number_format_i18n( $more_mcp_ab_on_ct ) ),
											esc_html( number_format_i18n( $more_mcp_ab_total ) )
										);
									} else {
										echo esc_html( number_format_i18n( $more_mcp_ab_total ) );
									}
									?>
								</span>
							</span>
							<span class="mmcp-cap-note"><?php esc_html_e( 'third-party code, no dry-run or undo', 'more-mcp' ); ?></span>
						</div>

						<?php if ( $more_mcp_ab_total > 0 ) : ?>
							<button type="button" class="mmcp-ability-disclose" aria-expanded="false" aria-controls="<?php echo esc_attr( $more_mcp_ab_list_id ); ?>">
								<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								<?php
								printf(
									/* translators: %s: number of abilities */
									esc_html( _n( 'Show the %s ability', 'Show all %s abilities', $more_mcp_ab_total, 'more-mcp' ) ),
									esc_html( number_format_i18n( $more_mcp_ab_total ) )
								);
								?>
							</button>
							<ul class="mmcp-ability-picker" id="<?php echo esc_attr( $more_mcp_ab_list_id ); ?>" hidden>
								<?php foreach ( $more_mcp_ab_names as $more_mcp_ab_name ) : ?>
									<?php
									$more_mcp_ab      = $more_mcp_importable_all[ $more_mcp_ab_name ] ?? null;
									$more_mcp_ab_lbl  = is_object( $more_mcp_ab ) && method_exists( $more_mcp_ab, 'get_label' ) ? (string) $more_mcp_ab->get_label() : '';
									$more_mcp_ab_desc = is_object( $more_mcp_ab ) && method_exists( $more_mcp_ab, 'get_description' ) ? (string) $more_mcp_ab->get_description() : '';
									$more_mcp_ab_on   = isset( $more_mcp_enabled_abilities[ $more_mcp_ab_name ] );
									$more_mcp_ab_id   = 'mmcp-ab-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $more_mcp_ab_name ) );
									?>
									<li<?php echo '' !== $more_mcp_ab_desc ? ' title="' . esc_attr( $more_mcp_ab_desc ) . '"' : ''; ?>>
										<label class="switch small">
											<input type="checkbox"
											       class="mmcp-ability-toggle"
											       data-ability="<?php echo esc_attr( $more_mcp_ab_name ); ?>"
											       data-namespace="<?php echo esc_attr( $more_mcp_ab_ns ); ?>"
											       id="<?php echo esc_attr( $more_mcp_ab_id ); ?>"
											       value="1"
											       <?php checked( $more_mcp_ab_on ); ?>>
											<span class="slider"></span>
										</label>
										<code><?php echo esc_html( $more_mcp_ab_name ); ?></code>
										<?php if ( '' !== $more_mcp_ab_lbl ) : ?>
											<span class="mmcp-ability-label"><?php echo esc_html( $more_mcp_ab_lbl ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php endif; ?>

				</div>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>

<p class="description mmcp-plugin-hint" aria-live="polite"></p>
