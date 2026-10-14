<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_plugins = class_exists( '\Mordenhost_MCP\Capabilities\Plugin_Catalog' )
	? \Mordenhost_MCP\Capabilities\Plugin_Catalog::build( $mordenhost_mcp_settings )
	: [];

if ( empty( $mordenhost_mcp_plugins ) ) {
	echo '<p class="description">' . esc_html__( 'No supported plugins detected. When you activate a plugin Mordenhost MCP can work with, it appears here.', 'mordenhost-mcp' ) . '</p>';
	return;
}

$mordenhost_mcp_docs_tools_url = add_query_arg(
	[ 'panel' => 'docs', 'doc' => 'tools' ],
	admin_url( 'admin.php?page=mordenhost-mcp' )
);

$mordenhost_mcp_importable_all = class_exists( '\Mordenhost_MCP\Abilities\Importer' )
	? \Mordenhost_MCP\Abilities\Importer::importable_abilities()
	: [];

$mordenhost_mcp_card_states = [];
foreach ( $mordenhost_mcp_plugins as $mordenhost_mcp_pkey => $mordenhost_mcp_plugin ) {
	$mordenhost_mcp_card_states[ $mordenhost_mcp_pkey ] = \Mordenhost_MCP\Capabilities\Plugin_Catalog::card_state( $mordenhost_mcp_plugin );
}
$mordenhost_mcp_all_on = ! in_array( 'off', $mordenhost_mcp_card_states, true ) && ! in_array( 'partial', $mordenhost_mcp_card_states, true );
?>

<div class="mmcp-plugins-bulk">
	<label class="switch small">
		<input type="checkbox"
		       class="mmcp-plugins-all-toggle"
		       value="1"
		       data-security-ack="<?php esc_attr_e( 'This switches on every plugin listed below for connected MCP clients: their tools, the plugin settings agents may write, and abilities imported from those plugins. Imported abilities run the plugin\'s own code with no dry-run or undo.', 'mordenhost-mcp' ); ?>"
		       <?php checked( $mordenhost_mcp_all_on ); ?>>
		<span class="slider"></span>
	</label>
	<span class="mmcp-plugins-bulk-label">
		<strong><?php esc_html_e( 'Enable all plugins', 'mordenhost-mcp' ); ?></strong>
		<span class="mmcp-plugins-bulk-count">
			<?php
			printf(
				/* translators: %s: number of detected plugins */
				esc_html( _n( '%s plugin detected', '%s plugins detected', count( $mordenhost_mcp_plugins ), 'mordenhost-mcp' ) ),
				esc_html( number_format_i18n( count( $mordenhost_mcp_plugins ) ) )
			);
			?>
		</span>
	</span>
</div>

<ul class="mmcp-plugin-cards">
	<?php foreach ( $mordenhost_mcp_plugins as $mordenhost_mcp_pkey => $mordenhost_mcp_plugin ) : ?>
		<?php

		
		$mordenhost_mcp_mark = strtoupper( mb_substr( preg_replace( '/[^A-Za-z0-9]/', '', $mordenhost_mcp_plugin['label'] ), 0, 2 ) );
		if ( ! empty( $mordenhost_mcp_plugin['via_abilities'] ) ) {
			$mordenhost_mcp_mark = '◇';
		}

		
		$mordenhost_mcp_has_tools = is_array( $mordenhost_mcp_plugin['tools'] ?? null );
		$mordenhost_mcp_state     = $mordenhost_mcp_card_states[ $mordenhost_mcp_pkey ];
		?>
		<li class="mmcp-plugin-card">
			<div class="mmcp-plugin-head">
				<span class="mmcp-plugin-mark" aria-hidden="true"><?php echo esc_html( $mordenhost_mcp_mark ); ?></span>
				<div class="mmcp-plugin-id">
					<h4><?php echo esc_html( $mordenhost_mcp_plugin['label'] ); ?></h4>
					<span class="mmcp-plugin-sub"<?php echo ! empty( $mordenhost_mcp_plugin['via_abilities'] ) ? ' title="' . esc_attr__( 'This plugin exposes its actions through the WordPress Abilities API (6.9+), not a built-in Mordenhost MCP integration.', 'mordenhost-mcp' ) . '"' : ''; ?>>
						<?php
						if ( ! empty( $mordenhost_mcp_plugin['via_abilities'] ) ) {
							esc_html_e( 'imported via WP Abilities API', 'mordenhost-mcp' );
						} else {
							esc_html_e( 'active', 'mordenhost-mcp' );
						}
						?>
					</span>
				</div>
				<label class="switch small mmcp-plugin-master">
					<input type="checkbox"
					       class="mmcp-plugin-toggle"
					       data-plugin="<?php echo esc_attr( (string) $mordenhost_mcp_pkey ); ?>"
					       data-state="<?php echo esc_attr( $mordenhost_mcp_state ); ?>"
					       aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name */ __( 'Enable %s for MCP clients', 'mordenhost-mcp' ), $mordenhost_mcp_plugin['label'] ) ); ?>"
					       value="1"
					       <?php checked( 'on' === $mordenhost_mcp_state ); ?>>
					<span class="slider"></span>
				</label>
			</div>

			<div class="mmcp-plugin-caps">

					<?php if ( $mordenhost_mcp_has_tools ) : ?>
						<div class="mmcp-cap-row">
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Abilities', 'mordenhost-mcp' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $mordenhost_mcp_plugin['tools']['count'] ) ); ?></span>
							</span>
							<a class="mmcp-cap-link" href="<?php echo esc_url( $mordenhost_mcp_docs_tools_url ); ?>">
								<?php esc_html_e( 'see list', 'mordenhost-mcp' ); ?> &rarr;
							</a>
						</div>
					<?php endif; ?>

					<?php if ( is_array( $mordenhost_mcp_plugin['settings'] ?? null ) ) : ?>
						<?php $mordenhost_mcp_set = $mordenhost_mcp_plugin['settings']; ?>
						<div class="mmcp-cap-row">
							<span class="mmcp-cap-label">
								<strong><?php esc_html_e( 'Settings', 'mordenhost-mcp' ); ?></strong>
								<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( (int) $mordenhost_mcp_set['count'] ) ); ?></span>
							</span>
							<?php if ( ! $mordenhost_mcp_perm_options ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'enable Site options first', 'mordenhost-mcp' ); ?></span>
							<?php elseif ( ! empty( $mordenhost_mcp_set['has_array'] ) ) : ?>
								<span class="mmcp-cap-note"><?php esc_html_e( 'bundled — replaced wholesale', 'mordenhost-mcp' ); ?></span>
							<?php endif; ?>
						</div>
						<?php foreach ( $mordenhost_mcp_set['cautions'] as $mordenhost_mcp_caution ) : ?>
							<p class="mmcp-preset-caution mmcp-cap-caution">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php echo esc_html( $mordenhost_mcp_caution ); ?>
							</p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( is_array( $mordenhost_mcp_plugin['abilities'] ?? null ) ) : ?>
						<?php
						$mordenhost_mcp_ab_ns    = (string) ( $mordenhost_mcp_plugin['abilities']['namespace'] ?? '' );
						$mordenhost_mcp_ab_names = $mordenhost_mcp_plugin['abilities']['names'];
						$mordenhost_mcp_ab_total = (int) $mordenhost_mcp_plugin['abilities']['count'];
						$mordenhost_mcp_ab_list_id = 'mmcp-abn-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $mordenhost_mcp_ab_ns ) );
						?>
						<?php if ( ! $mordenhost_mcp_has_tools ) : ?>
							<div class="mmcp-cap-row">
								<span class="mmcp-cap-label">
									<strong><?php esc_html_e( 'Abilities', 'mordenhost-mcp' ); ?></strong>
									<span class="mmcp-cap-count"><?php echo esc_html( number_format_i18n( $mordenhost_mcp_ab_total ) ); ?></span>
								</span>
								<span class="mmcp-cap-note"><?php esc_html_e( 'imported — third-party code, no dry-run or undo', 'mordenhost-mcp' ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $mordenhost_mcp_ab_total > 0 ) : ?>
							<button type="button" class="mmcp-ability-disclose" aria-expanded="false" aria-controls="<?php echo esc_attr( $mordenhost_mcp_ab_list_id ); ?>">
								<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								<?php
								printf(
									/* translators: %s: number of abilities */
									esc_html( _n( 'Show the %s imported ability', 'Show all %s imported abilities', $mordenhost_mcp_ab_total, 'mordenhost-mcp' ) ),
									esc_html( number_format_i18n( $mordenhost_mcp_ab_total ) )
								);
								?>
							</button>
							<ul class="mmcp-ability-picker" id="<?php echo esc_attr( $mordenhost_mcp_ab_list_id ); ?>" hidden>
								<?php foreach ( $mordenhost_mcp_ab_names as $mordenhost_mcp_ab_name ) : ?>
									<?php
									$mordenhost_mcp_ab      = $mordenhost_mcp_importable_all[ $mordenhost_mcp_ab_name ] ?? null;
									$mordenhost_mcp_ab_lbl  = is_object( $mordenhost_mcp_ab ) && method_exists( $mordenhost_mcp_ab, 'get_label' ) ? (string) $mordenhost_mcp_ab->get_label() : '';
									$mordenhost_mcp_ab_desc = is_object( $mordenhost_mcp_ab ) && method_exists( $mordenhost_mcp_ab, 'get_description' ) ? (string) $mordenhost_mcp_ab->get_description() : '';
									$mordenhost_mcp_ab_id   = 'mmcp-ab-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $mordenhost_mcp_ab_name ) );
									?>
									<li<?php echo '' !== $mordenhost_mcp_ab_desc ? ' title="' . esc_attr( $mordenhost_mcp_ab_desc ) . '"' : ''; ?>>
										<code><?php echo esc_html( $mordenhost_mcp_ab_name ); ?></code>
										<?php if ( '' !== $mordenhost_mcp_ab_lbl ) : ?>
											<span class="mmcp-ability-label"><?php echo esc_html( $mordenhost_mcp_ab_lbl ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $mordenhost_mcp_has_tools ) : ?>
								<p class="mmcp-preset-caution mmcp-cap-caution">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php esc_html_e( 'imported abilities run third-party code with no dry-run or undo; they join tools/list when this card is on', 'mordenhost-mcp' ); ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>

				</div>
		</li>
	<?php endforeach; ?>
</ul>

<p class="description mmcp-plugin-hint" aria-live="polite"></p>
