<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_capabilities = array();
if ( class_exists( '\More_MCP\Capabilities\Map' ) ) {
	$more_mcp_capabilities = \More_MCP\Capabilities\Map::for_display();
}

$more_mcp_kind_labels = array(
	'builder'        => __( 'Page builder', 'more-mcp' ),
	'block_editor'   => __( 'Block editor', 'more-mcp' ),
	'theme_template' => __( 'Theme template', 'more-mcp' ),
	'plugin'         => __( 'Plugin', 'more-mcp' ),
);
?>

<div class="mmcp-doc-lead">
	<p>
		<?php esc_html_e( 'This is what the site can do right now, derived from the integrations whose host plugin is active. Each capability lists the active providers that back it. It is the same information the capability resource exposes to an AI client for discovery.', 'more-mcp' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Discovery is not permission. Appearing here means a provider is installed and detected, not that any given change is allowed: every write still runs the calling user\'s WordPress capability check and the Permissions panel toggles on top of that. Page builders share the "page building" capability for discovery but keep their own native tools; the map never merges them into one generic editor.', 'more-mcp' ); ?>
	</p>
</div>

<?php if ( empty( $more_mcp_capabilities ) ) : ?>

	<div class="mmcp-empty">
		<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
		<h4><?php esc_html_e( 'No integrated capabilities detected', 'more-mcp' ); ?></h4>
		<p>
			<?php esc_html_e( 'None of the plugins or themes More MCP integrates with (WooCommerce, Elementor, Divi, ACF, Redirection, an analytics plugin, a forms plugin, WP Rocket or LiteSpeed, Wordfence or Defender, UpdraftPlus) are active on this site. The always-registered content, media, taxonomy, menu, and block tools are unaffected and still available; this panel only reports the optional, plugin-backed capabilities.', 'more-mcp' ); ?>
		</p>
	</div>

<?php else : ?>

	<div class="mmcp-tool-groups">
		<?php foreach ( $more_mcp_capabilities as $more_mcp_cap ) : ?>
			<?php
			$more_mcp_providers = isset( $more_mcp_cap['providers'] ) && is_array( $more_mcp_cap['providers'] )
				? $more_mcp_cap['providers']
				: array();
			$more_mcp_provider_count = count( $more_mcp_providers );
			?>
			<div class="mmcp-tool-group">
				<button type="button" class="mmcp-tool-group-header" aria-expanded="false">
					<span class="dashicons dashicons-arrow-down-alt2 mmcp-tool-group-chevron" aria-hidden="true"></span>
					<span class="mmcp-tool-group-title">
						<?php echo esc_html( $more_mcp_cap['label'] ); ?>
						<?php if ( ! empty( $more_mcp_cap['summary'] ) ) : ?>
							<small><?php echo esc_html( $more_mcp_cap['summary'] ); ?></small>
						<?php endif; ?>
					</span>
					<span class="mmcp-tool-group-count">
						<?php
						printf(
							/* translators: %s: number of active providers */
							esc_html( _n( '%s provider', '%s providers', $more_mcp_provider_count, 'more-mcp' ) ),
							esc_html( number_format_i18n( $more_mcp_provider_count ) )
						);
						?>
					</span>
				</button>
				<div class="mmcp-tool-group-body">
					<ul class="mmcp-tool-list">
						<?php foreach ( $more_mcp_providers as $more_mcp_provider ) : ?>
							<?php
							$more_mcp_kind = isset( $more_mcp_provider['kind'] ) ? (string) $more_mcp_provider['kind'] : 'plugin';
							$more_mcp_kind_label = isset( $more_mcp_kind_labels[ $more_mcp_kind ] )
								? $more_mcp_kind_labels[ $more_mcp_kind ]
								: ucwords( str_replace( '_', ' ', $more_mcp_kind ) );
							?>
							<li>
								<code><?php echo esc_html( $more_mcp_provider['provider'] ); ?></code>
								<span class="mmcp-provider-status is-configured"><?php echo esc_html( $more_mcp_kind_label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

<?php endif; ?>
