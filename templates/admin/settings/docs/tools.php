<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_tool_groups = [
	'more_mcp_'    => [
		'label'   => __( 'Diagnostics and undo', 'mordenhost-mcp-server' ),
		'summary' => __( 'Connection health checks and the undo token redeemer.', 'mordenhost-mcp-server' ),
	],
	'blocks_'      => [
		'label'   => __( 'Gutenberg blocks', 'mordenhost-mcp-server' ),
		'summary' => __( 'Read and mutate the block tree of a post, page, reusable block, or site template.', 'mordenhost-mcp-server' ),
	],
	'wc_'          => [
		'label'   => __( 'WooCommerce', 'mordenhost-mcp-server' ),
		'summary' => __( 'Orders, products, and customers. Present only while WooCommerce is active.', 'mordenhost-mcp-server' ),
	],
	'elementor_'   => [
		'label'   => __( 'Elementor', 'mordenhost-mcp-server' ),
		'summary' => __( 'Page structure, widgets, and templates. Writes are validated against Elementor\'s live widget registry, and Theme Builder dynamic widgets (theme-*) are checked for their required dynamic bindings before saving. Present only while Elementor is active.', 'mordenhost-mcp-server' ),
	],
	'divi_'        => [
		'label'   => __( 'Divi', 'mordenhost-mcp-server' ),
		'summary' => __( 'Read-only Divi 4 shortcode and Divi 5 block structure. Present only while Divi is active.', 'mordenhost-mcp-server' ),
	],
	'acf_'         => [
		'label'   => __( 'Advanced Custom Fields', 'mordenhost-mcp-server' ),
		'summary' => __( 'Field groups and field values. Present only while ACF is active.', 'mordenhost-mcp-server' ),
	],
	'redirection_' => [
		'label'   => __( 'Redirection', 'mordenhost-mcp-server' ),
		'summary' => __( 'Redirect rules and groups. Present only while Redirection is active.', 'mordenhost-mcp-server' ),
	],
	'analytics_'   => [
		'label'   => __( 'Analytics', 'mordenhost-mcp-server' ),
		'summary' => __( 'Site Kit, Jetpack Stats, and MonsterInsights status and reports. Present only while a supported provider is active.', 'mordenhost-mcp-server' ),
	],
	'seo_'         => [
		'label'   => __( 'SEO auditing', 'mordenhost-mcp-server' ),
		'summary' => __( 'Reads the rendered head of a page rather than the database, so it works whichever SEO plugin is active. Post and term SEO metadata itself lives under WordPress core below.', 'mordenhost-mcp-server' ),
	],
	
	'gsc_'         => [
		'label'   => __( 'Google Search Console', 'mordenhost-mcp-server' ),
		'summary' => __( 'Search analytics, sitemaps, and URL inspection. Present once a service account key is stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'ga4_'         => [
		'label'   => __( 'Google Analytics 4', 'mordenhost-mcp-server' ),
		'summary' => __( 'Reports, realtime, and property metadata. Present once a service account key is stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'semrush_'     => [
		'label'   => __( 'Semrush', 'mordenhost-mcp-server' ),
		'summary' => __( 'Domain and keyword research, competitors, and backlinks. Present once an API key is stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'dataforseo_'  => [
		'label'   => __( 'DataForSEO', 'mordenhost-mcp-server' ),
		'summary' => __( 'Live SERPs, keyword volume, ranked keywords, and backlink data. Present once API credentials are stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'seranking_'   => [
		'label'   => __( 'SE Ranking', 'mordenhost-mcp-server' ),
		'summary' => __( 'Domain overview, keyword and competitor research. Present once an API token is stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'ahrefs_'      => [
		'label'   => __( 'Ahrefs', 'mordenhost-mcp-server' ),
		'summary' => __( 'Domain Rating. Present once an Ahrefs APIv3 key is stored on External Services.', 'mordenhost-mcp-server' ),
	],
	'wpr_'         => [
		'label'   => __( 'WP Rocket', 'mordenhost-mcp-server' ),
		'summary' => __( 'Cache purges: full cache, single URL, and minified assets. Present only while WP Rocket is active.', 'mordenhost-mcp-server' ),
	],
	'up_'          => [
		'label'   => __( 'UpdraftPlus', 'mordenhost-mcp-server' ),
		'summary' => __( 'Backup set listing, last-run and running-state reads, and a confirmation-gated start-backup. No restore or deletion. Present only while UpdraftPlus is active.', 'mordenhost-mcp-server' ),
	],
	'wf_'          => [
		'label'   => __( 'Wordfence', 'mordenhost-mcp-server' ),
		'summary' => __( 'Security reads: firewall/scan status, scan findings, blocked IPs, failed-login summaries, plus a confirmation-gated start-scan. Present only while Wordfence is active.', 'mordenhost-mcp-server' ),
	],
	'def_'         => [
		'label'   => __( 'WP Defender', 'mordenhost-mcp-server' ),
		'summary' => __( 'Read-only security state: scan results and status, blocked IPs, lockout statistics, and hardening status. Present only while WP Defender is active.', 'mordenhost-mcp-server' ),
	],
	'discovered_'  => [
		'label'   => __( 'Discovered (imported abilities)', 'mordenhost-mcp-server' ),
		'summary' => __( 'Abilities other plugins registered with the WordPress Abilities API, wrapped as tools. Present only for abilities you explicitly enabled on the Access panel; they run the other plugin\'s own code and carry none of More MCP\'s dry-run / undo guards.', 'mordenhost-mcp-server' ),
	],
	'wp_'          => [
		'label'   => __( 'WordPress core', 'mordenhost-mcp-server' ),
		'summary' => __( 'Posts, pages, media, taxonomies, menus, comments, users, options, widgets, theme settings, and SEO metadata on posts and terms (routed to whichever of six SEO plugins is active).', 'mordenhost-mcp-server' ),
	],
];

$more_mcp_all_tools = [];
if ( class_exists( '\More_MCP\MCP\Server' ) ) {
	$more_mcp_tools_server = new \More_MCP\MCP\Server();
	if ( method_exists( $more_mcp_tools_server, 'get_all_tools' ) ) {
		$more_mcp_all_tools = $more_mcp_tools_server->get_all_tools();
	}
}

$more_mcp_bucketed = [];
$more_mcp_other    = [];

foreach ( $more_mcp_all_tools as $more_mcp_tool ) {
	if ( ! is_array( $more_mcp_tool ) || empty( $more_mcp_tool['name'] ) ) {
		continue;
	}
	$more_mcp_name    = (string) $more_mcp_tool['name'];
	$more_mcp_matched = false;

	foreach ( $more_mcp_tool_groups as $more_mcp_prefix => $more_mcp_group ) {
		if ( 0 === strpos( $more_mcp_name, $more_mcp_prefix ) ) {
			$more_mcp_bucketed[ $more_mcp_prefix ][] = $more_mcp_tool;
			$more_mcp_matched                        = true;
			break;
		}
	}

	if ( ! $more_mcp_matched ) {
		$more_mcp_other[] = $more_mcp_tool;
	}
}

$more_mcp_total_tools = count( $more_mcp_all_tools );

$more_mcp_lifecycle_on = class_exists( '\More_MCP\Lifecycle\Manager' )
	&& \More_MCP\Lifecycle\Manager::is_enabled();
?>

<div class="mmcp-doc-lead">
	<p>
		<?php
		printf(
			/* translators: %s: number of abilities, already formatted for locale */
			esc_html__( 'This site currently exposes %s abilities. The list is built from the live registry, so it reflects which optional integrations are active right now. It is what an AI client actually receives from tools/list.', 'mordenhost-mcp-server' ),
			'<strong>' . esc_html( number_format_i18n( $more_mcp_total_tools ) ) . '</strong>'
		);
		?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Being listed is not the same as being permitted. Every write still runs the calling user\'s WordPress capability check, and the option, theme, and plugin-management toggles on the Access panel gate whole categories on top of that.', 'mordenhost-mcp-server' ); ?>
	</p>
</div>

<?php if ( 0 === $more_mcp_total_tools ) : ?>

	<div class="cloudflare-warning warning-error">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'Tool registry unavailable.', 'mordenhost-mcp-server' ); ?></strong>
			<?php esc_html_e( 'The registry could not be read on this request. This is unexpected. Check the Activity Log and your PHP error log.', 'mordenhost-mcp-server' ); ?>
		</p>
	</div>

<?php else : ?>

	<div class="mmcp-tool-groups">
		<?php
		foreach ( $more_mcp_tool_groups as $more_mcp_prefix => $more_mcp_group ) :
			$more_mcp_group_tools = $more_mcp_bucketed[ $more_mcp_prefix ] ?? [];
			if ( empty( $more_mcp_group_tools ) ) {

				
				continue;
			}
			?>
			<div class="mmcp-tool-group">
				<button type="button" class="mmcp-tool-group-header" aria-expanded="false">
					<span class="dashicons dashicons-arrow-down-alt2 mmcp-tool-group-chevron" aria-hidden="true"></span>
					<span class="mmcp-tool-group-title">
						<?php echo esc_html( $more_mcp_group['label'] ); ?>
						<small><?php echo esc_html( $more_mcp_group['summary'] ); ?></small>
					</span>
					<span class="mmcp-tool-group-count">
						<?php
						printf(
							/* translators: %s: tool count */
							esc_html( _n( '%s tool', '%s tools', count( $more_mcp_group_tools ), 'mordenhost-mcp-server' ) ),
							esc_html( number_format_i18n( count( $more_mcp_group_tools ) ) )
						);
						?>
					</span>
				</button>
				<div class="mmcp-tool-group-body">
					<?php if ( 'wp_' === $more_mcp_prefix ) : ?>
						<p class="mmcp-tool-group-note">
							<?php
							echo $more_mcp_lifecycle_on
								? esc_html__( 'Plugin and theme lifecycle tools are included in this group because installing or activating a plugin is a core WordPress operation. They are listed because you enabled plugin management on the Access panel.', 'mordenhost-mcp-server' )
								: esc_html__( 'Plugin and theme lifecycle tools would also appear in this group, but plugin management is off on the Access panel, so they are not listed to clients at all.', 'mordenhost-mcp-server' );
							?>
						</p>
					<?php endif; ?>
					<ul class="mmcp-tool-list">
						<?php foreach ( $more_mcp_group_tools as $more_mcp_group_tool ) : ?>
							<?php

							

							
							
							$more_mcp_desc = isset( $more_mcp_group_tool['description'] )
								? (string) $more_mcp_group_tool['description']
								: '';
							?>
							<li<?php echo '' !== $more_mcp_desc ? ' title="' . esc_attr( $more_mcp_desc ) . '"' : ''; ?>>
								<code><?php echo esc_html( $more_mcp_group_tool['name'] ); ?></code>
								<?php if ( '' !== $more_mcp_desc ) : ?>
									<span class="mmcp-tool-info" aria-hidden="true">i</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( ! empty( $more_mcp_other ) ) : ?>
			<div class="mmcp-tool-group">
				<button type="button" class="mmcp-tool-group-header" aria-expanded="false">
					<span class="dashicons dashicons-arrow-down-alt2 mmcp-tool-group-chevron" aria-hidden="true"></span>
					<span class="mmcp-tool-group-title">
						<?php esc_html_e( 'Other', 'mordenhost-mcp-server' ); ?>
						<small><?php esc_html_e( 'Tools registered by a filter under a prefix this page does not recognize.', 'mordenhost-mcp-server' ); ?></small>
					</span>
					<span class="mmcp-tool-group-count">
						<?php
						printf(
							/* translators: %s: tool count */
							esc_html( _n( '%s tool', '%s tools', count( $more_mcp_other ), 'mordenhost-mcp-server' ) ),
							esc_html( number_format_i18n( count( $more_mcp_other ) ) )
						);
						?>
					</span>
				</button>
				<div class="mmcp-tool-group-body">
					<ul class="mmcp-tool-list">
						<?php foreach ( $more_mcp_other as $more_mcp_other_tool ) : ?>
							<?php $more_mcp_other_desc = (string) ( $more_mcp_other_tool['description'] ?? '' ); ?>
							<li<?php echo '' !== $more_mcp_other_desc ? ' title="' . esc_attr( $more_mcp_other_desc ) . '"' : ''; ?>>
								<code><?php echo esc_html( $more_mcp_other_tool['name'] ); ?></code>
								<?php if ( '' !== $more_mcp_other_desc ) : ?>
									<span class="mmcp-tool-info" aria-hidden="true">i</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<h3><?php esc_html_e( 'Trimming the list for a specific client', 'mordenhost-mcp-server' ); ?></h3>
	<p class="description">
		<?php echo wp_kses( __( 'Some clients degrade or fail outright on large tool sets. Append <code>?tools=&lt;profile&gt;</code> to the MCP Server URL to send a subset instead. One profile ships built in:', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
	</p>
	<ul class="mmcp-doc-facts">
		<li>
			<strong><code>?tools=core</code></strong>
			<?php esc_html_e( 'WordPress core, protocol meta-tools, and SEO auditing. Drops every integration.', 'mordenhost-mcp-server' ); ?>
		</li>
	</ul>
	<p class="description">
		<?php echo wp_kses( __( 'An unrecognized profile name is ignored and the full list is sent, so a typo will not silently leave a client with no tools. Developers can register additional profiles with the <code>more_mcp_tool_profile_prefixes</code> filter.', 'mordenhost-mcp-server' ), [ 'code' => [] ] ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'A profile trims what is listed, not what is permitted. It is chosen by the client and applied only to the tool list, so a client can still call a tool the profile omitted. Treat it as a compatibility setting for clients that choke on long lists, not as an access control. That is what the capability checks on every tool are for.', 'mordenhost-mcp-server' ); ?>
	</p>

<?php endif; ?>
