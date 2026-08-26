<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_cap_rows = array();
if ( class_exists( '\More_MCP\Capabilities\Map' ) ) {
	$more_mcp_cap_rows = \More_MCP\Capabilities\Map::for_display();
}

if ( empty( $more_mcp_cap_rows ) ) {
	return;
}

$more_mcp_cap_kind_labels = array(
	'builder'        => __( 'Page builder', 'more-mcp' ),
	'block_editor'   => __( 'Block editor', 'more-mcp' ),
	'theme_template' => __( 'Theme template', 'more-mcp' ),
	'plugin'         => __( 'Plugin', 'more-mcp' ),
);

$more_mcp_cap_count = count( $more_mcp_cap_rows );
?>

<div class="mmcp-cap-summary">
	<button type="button"
	        class="mmcp-cap-summary-toggle advanced-toggle"
	        aria-expanded="false"
	        aria-controls="mmcp-cap-summary-body">
		<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
		<span class="mmcp-cap-summary-title">
			<?php esc_html_e( 'What this site can do', 'more-mcp' ); ?>
			<span class="mmcp-cap-summary-count">
				<?php
				printf(
					/* translators: %s: number of detected capabilities */
					esc_html( _n( '%s capability', '%s capabilities', $more_mcp_cap_count, 'more-mcp' ) ),
					esc_html( number_format_i18n( $more_mcp_cap_count ) )
				);
				?>
			</span>
		</span>
	</button>

	<div class="mmcp-cap-summary-body advanced-content" id="mmcp-cap-summary-body" hidden>
		<p class="description">
			<?php esc_html_e( 'Detected from the plugins active on this site right now. This is what is possible, not what is allowed: every write still runs the calling user\'s WordPress capability check and the scopes below. Turning a capability on here is not a thing you can do — enable the matching access scope or plugin card instead.', 'more-mcp' ); ?>
		</p>

		<ul class="mmcp-cap-summary-list">
			<?php foreach ( $more_mcp_cap_rows as $more_mcp_cap ) : ?>
				<?php
				$more_mcp_cap_providers = isset( $more_mcp_cap['providers'] ) && is_array( $more_mcp_cap['providers'] )
					? $more_mcp_cap['providers']
					: array();
				?>
				<li class="mmcp-cap-summary-row">
					<span class="mmcp-cap-summary-name">
						<strong><?php echo esc_html( $more_mcp_cap['label'] ); ?></strong>
						<?php if ( ! empty( $more_mcp_cap['summary'] ) ) : ?>
							<span class="mmcp-cap-summary-desc"><?php echo esc_html( $more_mcp_cap['summary'] ); ?></span>
						<?php endif; ?>
					</span>
					<span class="mmcp-cap-summary-providers">
						<?php foreach ( $more_mcp_cap_providers as $more_mcp_cap_provider ) : ?>
							<?php
							$more_mcp_cap_kind = isset( $more_mcp_cap_provider['kind'] ) ? (string) $more_mcp_cap_provider['kind'] : 'plugin';
							$more_mcp_cap_kind_label = isset( $more_mcp_cap_kind_labels[ $more_mcp_cap_kind ] )
								? $more_mcp_cap_kind_labels[ $more_mcp_cap_kind ]
								: ucwords( str_replace( '_', ' ', $more_mcp_cap_kind ) );
							?>
							<span class="mmcp-cap-summary-provider" title="<?php echo esc_attr( $more_mcp_cap_kind_label ); ?>">
								<code><?php echo esc_html( $more_mcp_cap_provider['provider'] ); ?></code>
							</span>
						<?php endforeach; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
