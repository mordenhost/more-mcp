<?php
/**
 * Documentation panel — client setup guides, API reference, and troubleshooting.
 * Read-only.
 *
 * This panel replaces the former "Setup Guides" and "API Reference" sidebar
 * entries. Both were reference material an admin reads once during setup, and
 * neither justified a permanent top-level slot; splitting them also meant an
 * admin looking for "how do I connect X" had to guess which of the two held it.
 *
 * Sub-tab state travels in the `doc` query arg, matching how the outer panel
 * uses `panel` — real links, server-side selection, working back button, and a
 * bookmarkable URL for a specific section.
 *
 * Rendered by templates/admin/settings.php, which owns the $more_mcp_*
 * variables used below. PHP `use` statements are file-scoped and are NOT
 * inherited across require(), so any namespaced class this partial touches
 * must be imported or fully qualified here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sub-tab definitions. Each maps to templates/admin/settings/docs/<slug>.php.
 */
$more_mcp_doc_tabs = [
	'setup'           => [
		'label'    => __( 'Client setup', 'more-mcp' ),
		'dashicon' => 'dashicons-admin-plugins',
		'summary'  => __( 'Step-by-step connection walkthroughs for each MCP host.', 'more-mcp' ),
	],
	'tools'           => [
		'label'    => __( 'What agents can do', 'more-mcp' ),
		'dashicon' => 'dashicons-list-view',
		'summary'  => __( 'The tool surface an AI client sees, grouped by area.', 'more-mcp' ),
	],
	'api'             => [
		'label'    => __( 'REST API reference', 'more-mcp' ),
		'dashicon' => 'dashicons-rest-api',
		'summary'  => __( 'The legacy per-endpoint REST surface, for integrations that predate MCP.', 'more-mcp' ),
	],
	'troubleshooting' => [
		'label'    => __( 'Troubleshooting', 'more-mcp' ),
		'dashicon' => 'dashicons-sos',
		'summary'  => __( 'What to check when a client will not connect.', 'more-mcp' ),
	],
];

/**
 * Resolve the active sub-tab.
 *
 * The legacy `panel=endpoints` URL is aliased to `panel=docs` by the parent
 * template. That alias alone would land such a visitor on Client setup, which is
 * not the content they asked for — so the API tab is also selected when the
 * request arrived under the old endpoints slug.
 */
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

	<nav class="mmcp-subtabs" aria-label="<?php esc_attr_e( 'Documentation sections', 'more-mcp' ); ?>">
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
			<?php esc_html_e( 'Full documentation site', 'more-mcp' ); ?>
		</a>
	</div>

</div>
