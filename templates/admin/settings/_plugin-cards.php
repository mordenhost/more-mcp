<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_plugins = class_exists( '\More_MCP\Capabilities\Plugin_Catalog' )
	? \More_MCP\Capabilities\Plugin_Catalog::build( $more_mcp_settings )
	: [];

if ( empty( $more_mcp_plugins ) ) {
	echo '<p class="description">' . esc_html__( 'No supported plugins detected. When you activate a plugin More MCP can work with, it appears here.', 'mordenhost-mcp-server' ) . '</p>';
	return;
}

$more_mcp_docs_tools_url = add_query_arg(
	[ 'panel' => 'docs', 'doc' => 'tools' ],
	admin_url( 'admin.php?page=more-mcp' )
);

$more_mcp_importable_all = class_exists( '\More_MCP\Abilities\Importer' )
	? \More_MCP\Abilities\Importer::importable_abilities()
	: [];

$more_mcp_card_states = [];
foreach ( $more_mcp_plugins as $more_mcp_pkey => $more_mcp_plugin ) {
	$more_mcp_card_states[ $more_mcp_pkey ] = \More_MCP\Capabilities\Plugin_Catalog::card_state( $more_mcp_plugin );
}
$more_mcp_all_on = ! in_array( 'off', $more_mcp_card_states, true ) && ! in_array( 'partial', $more_mcp_card_states, true );
?>

<div class="mmcp-plugins-bulk">
	<label class="switch small">
		<input type="checkbox"
		       class="mmcp-plugins-all-toggle"
		       value="1"
		       data-security-ack="<?php esc_attr_e( 'This switches on every plugin listed below for connected MCP clients: their tools, the plugin settings agents may write, and abilities imported from those plugins. Imported abilities run the plugin\'s own code with no dry-run or undo.', 'mordenhost-mcp-server' ); ?>"
		       <?php checked( $more_mcp_all_on ); ?>>
		<span class="slider"></span>
	</label>
	<span class="mmcp-plugins-bulk-label">
		<strong><?php esc_html_e( 'Enable all plugins', 'mordenhost-mcp-server' ); ?></strong>
		<span class="mmcp-plugins-bulk-count">
			<?php
			printf(
				/* translators: %s: number of detected plugins */
				esc_html( _n( '%s plugin detected', '%s plugins detected', count( $more_mcp_plugins ), 'mordenhost-mcp-server' ) ),
				esc_html( number_format_i18n( count( $more_mcp_plugins ) ) )
			);
			?>
		</span>
	</span>
</div>

<ul class="mmcp-plugin-cards">
	<?php foreach ( $more_mcp_plugins as $more_mcp_pkey => $more_mcp_plugin ) : ?>
		<?php

		
		$more_mcp_mark = strtoupper( mb_substr( preg_replace( '/[^A-Za-z0-9]/', '', $more_mcp_plugin['label'] ), 0, 2 ) );
		if ( ! empty( $more_mcp_plugin['via_abilities'] ) ) {
			$more_mcp_mark = '◇';
		}

		
		$more_mcp_has_tools = is_array( $more_mcp_plugin['tools'] ?? null );
		$more_mcp_state     = $more_mcp_card_states[ $more_mcp_pkey ];
		?>
		<li class="mmcp-plugin-card">
			<div class="mmcp-plugin-head">
				<span class="mmcp-plugin-mark" aria-hidden="true"><?php echo esc_html( $more_mcp_mark ); ?></span>
				<div class="mmcp-plugin-id">
					<h4><?php echo esc_html( $more_mcp_plugin['label'] ); ?></h4>
					<span class="mmcp-plugin-sub"<?php echo ! empty( $more_mcp_plugin['via_abilities'] ) ? ' title="' . esc_attr__( 'This plugin exposes its actions through the WordPress Abilities API (6.9+), not a built-in More MCP integration.', 'mordenhost-mcp-server' ) . '"' : ''; ?>>
						<?php
						if ( ! empty( $more_mcp_plugin['via_abilities'] ) ) {
							esc_html_e( 'imported via WP Abilities API', 'mordenhost-mcp-server' );
						} else {
							esc_html_e( 'active', 'mordenhost-mcp-server' );
						}
						?>
					</span>
				</div>
				<label class="switch small mmcp-plugin-master">
					<input type="checkbox"
					       class="mmcp-plugin-toggle"
					       data-plugin="<?php echo esc_attr( (string) $more_mcp_pkey ); ?>"
					       data-state="<?php echo esc_attr( $more_mcp_state ); ?>"
					       aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name */ __( 'Enable %s for MCP clients', 'mordenhost-mcp-server' ), $more_mcp_plugin['label'] ) ); ?>"
					       value="1"
					       <?php checked( 'on' === $more_mcp_state ); ?>>
					<span class="slider"></span>
				</label>
			</div>

			<div class="mmcp-plugin-caps">

					<?php if ( $more_mcp_has_tools ) : ?>
						<div class="mmcp-cap-row">
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Abilities', 'mordenhost-mcp-server' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $more_mcp_plugin['tools']['count'] ) ); ?></span>
							</span>
							<a class="mmcp-cap-link" href="<?php echo esc_url( $more_mcp_docs_tools_url ); ?>">
								<?php esc_html_e( 'see list', 'mordenhost-mcp-server' ); ?> &rarr;
							</a>
						</div>
					<?php endif; ?>

					<?php if ( is_array( $more_mcp_plugin['settings'] ?? null ) ) : ?>
						<?php $more_mcp_set = $more_mcp_plugin['settings']; ?>
						<div class="mmcp-cap-row">
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Settings', 'mordenhost-mcp-server' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $more_mcp_set['count'] ) ); ?></span>
							</span>
							<?php if ( ! $more_mcp_perm_options ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'enable Site options first', 'mordenhost-mcp-server' ); ?></span>
							<?php elseif ( ! empty( $more_mcp_set['has_array'] ) ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'bundled — replaced wholesale', 'mordenhost-mcp-server' ); ?></span>
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
						$more_mcp_ab_list_id = 'mmcp-abn-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $more_mcp_ab_ns ) );
						?>
						<?php if ( ! $more_mcp_has_tools ) : ?>
							<div class="mmcp-cap-row">
								<span class="mmcp-cap-label">
									<strong><?php esc_html_e( 'Abilities', 'mordenhost-mcp-server' ); ?></strong>
									<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( $more_mcp_ab_total ) ); ?></span>
								</span>
								<span class="mmcp-cap-note"><?php esc_html_e( 'imported — third-party code, no dry-run or undo', 'mordenhost-mcp-server' ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $more_mcp_ab_total > 0 ) : ?>
							<button type="button" class="mmcp-ability-disclose" aria-expanded="false" aria-controls="<?php echo esc_attr( $more_mcp_ab_list_id ); ?>">
								<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								<?php
								printf(
									/* translators: %s: number of abilities */
									esc_html( _n( 'Show the %s imported ability', 'Show all %s imported abilities', $more_mcp_ab_total, 'mordenhost-mcp-server' ) ),
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
									$more_mcp_ab_id   = 'mmcp-ab-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $more_mcp_ab_name ) );
									?>
									<li<?php echo '' !== $more_mcp_ab_desc ? ' title="' . esc_attr( $more_mcp_ab_desc ) . '"' : ''; ?>>
										<code><?php echo esc_html( $more_mcp_ab_name ); ?></code>
										<?php if ( '' !== $more_mcp_ab_lbl ) : ?>
											<span class="mmcp-ability-label"><?php echo esc_html( $more_mcp_ab_lbl ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $more_mcp_has_tools ) : ?>
								<p class="mmcp-preset-caution mmcp-cap-caution">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php esc_html_e( 'imported abilities run third-party code with no dry-run or undo; they join tools/list when this card is on', 'mordenhost-mcp-server' ); ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>

				</div>
		</li>
	<?php endforeach; ?>
</ul>

<p class="description mmcp-plugin-hint" aria-live="polite"></p>
