<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_doc_tabs = [
	'setup'           => [
		'label'    => __( 'Client setup', 'mordenhost-mcp-server' ),
		'dashicon' => 'dashicons-admin-plugins',
		'summary'  => __( 'Step-by-step connection walkthroughs for each MCP host.', 'mordenhost-mcp-server' ),
	],
	'tools'           => [
		'label'    => __( 'What agents can do', 'mordenhost-mcp-server' ),
		'dashicon' => 'dashicons-list-view',
		'summary'  => __( 'The abilities an AI client sees, grouped by area.', 'mordenhost-mcp-server' ),
	],
	'api'             => [
		'label'    => __( 'REST API reference', 'mordenhost-mcp-server' ),
		'dashicon' => 'dashicons-rest-api',
		'summary'  => __( 'The legacy per-endpoint REST surface, for integrations that predate MCP.', 'mordenhost-mcp-server' ),
	],
	'troubleshooting' => [
		'label'    => __( 'Troubleshooting', 'mordenhost-mcp-server' ),
		'dashicon' => 'dashicons-sos',
		'summary'  => __( 'What to check when a client will not connect.', 'mordenhost-mcp-server' ),
	],
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$more_mcp_doc_requested = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';

if ( '' === $more_mcp_doc_requested ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only selector; see above.
	$more_mcp_legacy_panel = isset( $_GET['panel'] ) ? sanitize_key( wp_unslash( $_GET['panel'] ) ) : '';
	if ( 'endpoints' === $more_mcp_legacy_panel ) {
		$more_mcp_doc_requested = 'api';
	}
}

$more_mcp_doc_active = isset( $more_mcp_doc_tabs[ $more_mcp_doc_requested ] ) ? $more_mcp_doc_requested : 'setup';

$more_mcp_docs_base = add_query_arg( 'panel', 'docs', admin_url( 'admin.php?page=more-mcp' ) );
?>

<div class="mmcp-docs">

	<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Documentation sections', 'mordenhost-mcp-server' ); ?>">
		<?php foreach ( $more_mcp_doc_tabs as $more_mcp_doc_slug => $more_mcp_doc_tab ) : ?>
			<?php $more_mcp_doc_is_active = ( $more_mcp_doc_slug === $more_mcp_doc_active ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'doc', $more_mcp_doc_slug, $more_mcp_docs_base ) ); ?>"
			   class="mmcp-subtab<?php echo $more_mcp_doc_is_active ? ' is-active' : ''; ?>"
			   <?php echo $more_mcp_doc_is_active ? 'aria-current="page"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $more_mcp_doc_tab['dashicon'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $more_mcp_doc_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<p class="mmcp-subtab-summary">
		<?php echo esc_html( $more_mcp_doc_tabs[ $more_mcp_doc_active ]['summary'] ); ?>
	</p>

	<div class="mmcp-docs-body">
		<?php require MORE_MCP_PLUGIN_DIR . 'templates/admin/settings/docs/' . $more_mcp_doc_active . '.php'; ?>
	</div>

	<div class="mmcp-docs-footer">
		<a href="<?php echo esc_url( MORE_MCP_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
			<?php esc_html_e( 'Full documentation site', 'mordenhost-mcp-server' ); ?>
		</a>
	</div>

</div>
