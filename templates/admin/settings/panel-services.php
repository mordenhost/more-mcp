<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_services_base = add_query_arg( 'panel', 'services', admin_url( 'admin.php?page=more-mcp' ) );

$more_mcp_service_tabs = [
	'ai'  => [
		'label'    => __( 'AI models', 'more-mcp' ),
		'dashicon' => 'dashicons-cloud',
		'summary'  => __( 'Keys for WordPress to call an AI model. Optional, not needed to connect Claude or ChatGPT.', 'more-mcp' ),
	],
	'seo' => [
		'label'    => __( 'SEO & analytics', 'more-mcp' ),
		'dashicon' => 'dashicons-chart-line',
		'summary'  => __( 'Ranking, traffic, keyword, and backlink data, so an agent can see how content performs.', 'more-mcp' ),
	],
];

$more_mcp_service_view = isset( $more_mcp_service_view ) && isset( $more_mcp_service_tabs[ $more_mcp_service_view ] )
	? $more_mcp_service_view
	: 'ai';

$more_mcp_svc_ai_count = 0;
if ( is_array( $more_mcp_configured_platforms ) ) {
	foreach ( $more_mcp_configured_platforms as $more_mcp_svc_row ) {
		if ( is_array( $more_mcp_svc_row ) && ! empty( $more_mcp_svc_row['enabled'] ) ) {
			$more_mcp_svc_ai_count++;
		}
	}
}

$more_mcp_svc_seo_count = 0;
if ( class_exists( '\More_MCP\SEO_Data\Providers' ) && class_exists( '\More_MCP\SEO_Data\Credentials' ) ) {
	foreach ( array_keys( \More_MCP\SEO_Data\Providers::all() ) as $more_mcp_svc_slug ) {
		if ( \More_MCP\SEO_Data\Credentials::is_enabled( $more_mcp_svc_slug ) ) {
			$more_mcp_svc_seo_count++;
		}
	}
}

$more_mcp_service_counts = [
	'ai'  => $more_mcp_svc_ai_count,
	'seo' => $more_mcp_svc_seo_count,
];
?>

<div class="mmcp-docs">

	<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Service categories', 'more-mcp' ); ?>">
		<?php foreach ( $more_mcp_service_tabs as $more_mcp_svc_tab_slug => $more_mcp_svc_tab ) : ?>
			<?php
			$more_mcp_svc_is_active = ( $more_mcp_svc_tab_slug === $more_mcp_service_view );
			$more_mcp_svc_count     = $more_mcp_service_counts[ $more_mcp_svc_tab_slug ] ?? 0;
			?>
			<a href="<?php echo esc_url( add_query_arg( 'svc', $more_mcp_svc_tab_slug, $more_mcp_services_base ) ); ?>"
			   class="mmcp-subtab<?php echo $more_mcp_svc_is_active ? ' is-active' : ''; ?>"
			   <?php echo $more_mcp_svc_is_active ? 'aria-current="page"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $more_mcp_svc_tab['dashicon'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $more_mcp_svc_tab['label'] ); ?>
				<?php if ( $more_mcp_svc_count > 0 ) : ?>
					<span class="mmcp-subtab-count"><?php echo esc_html( number_format_i18n( $more_mcp_svc_count ) ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<p class="mmcp-subtab-summary">
		<?php echo esc_html( $more_mcp_service_tabs[ $more_mcp_service_view ]['summary'] ); ?>
	</p>

	<div class="mmcp-docs-body">
		<?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/settings/services/' . $more_mcp_service_view . '.php'; ?>
	</div>

</div>
