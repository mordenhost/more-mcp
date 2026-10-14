<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mordenhost_mcp_doc_tabs = [
	'setup'           => [
		'label'    => __( 'Client setup', 'mordenhost-mcp' ),
		'dashicon' => 'dashicons-admin-plugins',
		'summary'  => __( 'Step-by-step connection walkthroughs for each MCP host.', 'mordenhost-mcp' ),
	],
	'tools'           => [
		'label'    => __( 'What agents can do', 'mordenhost-mcp' ),
		'dashicon' => 'dashicons-list-view',
		'summary'  => __( 'The abilities an AI client sees, grouped by area.', 'mordenhost-mcp' ),
	],
	'api'             => [
		'label'    => __( 'REST API reference', 'mordenhost-mcp' ),
		'dashicon' => 'dashicons-rest-api',
		'summary'  => __( 'The legacy per-endpoint REST surface, for integrations that predate MCP.', 'mordenhost-mcp' ),
	],
	'troubleshooting' => [
		'label'    => __( 'Troubleshooting', 'mordenhost-mcp' ),
		'dashicon' => 'dashicons-sos',
		'summary'  => __( 'What to check when a client will not connect.', 'mordenhost-mcp' ),
	],
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, validated against a fixed allowlist.
$mordenhost_mcp_doc_requested = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';

if ( '' === $mordenhost_mcp_doc_requested ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only selector; see above.
	$mordenhost_mcp_legacy_panel = isset( $_GET['panel'] ) ? sanitize_key( wp_unslash( $_GET['panel'] ) ) : '';
	if ( 'endpoints' === $mordenhost_mcp_legacy_panel ) {
		$mordenhost_mcp_doc_requested = 'api';
	}
}

$mordenhost_mcp_doc_active = isset( $mordenhost_mcp_doc_tabs[ $mordenhost_mcp_doc_requested ] ) ? $mordenhost_mcp_doc_requested : 'setup';

$mordenhost_mcp_docs_base = add_query_arg( 'panel', 'docs', admin_url( 'admin.php?page=mordenhost-mcp' ) );
?>

<div class="mmcp-docs">

	<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Documentation sections', 'mordenhost-mcp' ); ?>">
		<?php foreach ( $mordenhost_mcp_doc_tabs as $mordenhost_mcp_doc_slug => $mordenhost_mcp_doc_tab ) : ?>
			<?php $mordenhost_mcp_doc_is_active = ( $mordenhost_mcp_doc_slug === $mordenhost_mcp_doc_active ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'doc', $mordenhost_mcp_doc_slug, $mordenhost_mcp_docs_base ) ); ?>"
			   class="mmcp-subtab<?php echo $mordenhost_mcp_doc_is_active ? ' is-active' : ''; ?>"
			   <?php echo $mordenhost_mcp_doc_is_active ? 'aria-current="page"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $mordenhost_mcp_doc_tab['dashicon'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $mordenhost_mcp_doc_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<p class="mmcp-subtab-summary">
		<?php echo esc_html( $mordenhost_mcp_doc_tabs[ $mordenhost_mcp_doc_active ]['summary'] ); ?>
	</p>

	<div class="mmcp-docs-body">
		<?php require MORDENHOST_MCP_PLUGIN_DIR . 'templates/admin/settings/docs/' . $mordenhost_mcp_doc_active . '.php'; ?>
	</div>

	<div class="mmcp-docs-footer">
		<a href="<?php echo esc_url( MORDENHOST_MCP_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="dashicons dashicons-external" aria-hidden="true"></span>
			<?php esc_html_e( 'Full documentation site', 'mordenhost-mcp' ); ?>
		</a>
	</div>

</div>
