<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $more_mcp_integration_catalog ) ) {
	echo '<p class="description">' . esc_html__( 'Integration catalogue unavailable on this build.', 'more-mcp' ) . '</p>';
	return;
}
?>

<ul class="mmcp-integration-list">
	<?php foreach ( $more_mcp_integration_catalog as $more_mcp_int_slug => $more_mcp_int_meta ) : ?>
		<?php
		$more_mcp_int_active  = ! empty( $more_mcp_integration_available[ $more_mcp_int_slug ] );
		$more_mcp_int_on      = isset( $more_mcp_integration_enabled[ $more_mcp_int_slug ] );
		$more_mcp_int_id      = 'mmcp-int-' . $more_mcp_int_slug;
		?>
		<li class="mmcp-integration-row<?php echo $more_mcp_int_active ? '' : ' is-inactive'; ?>">
			<label class="switch small">
				<input type="checkbox"
				       class="mmcp-integration-toggle"
				       id="<?php echo esc_attr( $more_mcp_int_id ); ?>"
				       data-slug="<?php echo esc_attr( $more_mcp_int_slug ); ?>"
				       value="1"
				       <?php checked( $more_mcp_int_on ); ?>
				       <?php disabled( ! $more_mcp_int_active ); ?>>
				<span class="slider"></span>
			</label>
			<div class="mmcp-integration-meta">
				<label for="<?php echo esc_attr( $more_mcp_int_id ); ?>" class="mmcp-integration-name">
					<?php echo esc_html( $more_mcp_int_meta['label'] ); ?>
				</label>
				<span class="mmcp-integration-status">
					<?php
					if ( ! $more_mcp_int_active ) {
						esc_html_e( 'Plugin not active', 'more-mcp' );
					} elseif ( $more_mcp_int_on ) {
						esc_html_e( 'Enabled — tools exposed', 'more-mcp' );
					} else {
						esc_html_e( 'Installed — tools hidden', 'more-mcp' );
					}
					?>
				</span>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
<p class="description mmcp-integration-hint" aria-live="polite"></p>
