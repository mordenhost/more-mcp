<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor MCP module coexistence layer.
 *
 * When Elementor's own `modules/mcp` ships (currently under active daily
 * development in the elementor/elementor repo, tracked in
 * _internal/more-mcp/monitoring-log.md), More MCP's Elementor tools and
 * Elementor's own abilities will both be advertised to MCP clients on any
 * site with both plugins active.
 *
 * This class handles the coexistence surface:
 *   - Detects when Elementor's MCP module is present on this site
 *   - Shows a one-time admin notice on More MCP settings pages
 *   - Prefixes More MCP's elementor_* tool descriptions with a routing hint
 *     pointing at Elementor's canonical primitives (`build-composition`,
 *     `get-page-structure`) so agents pick the right tool for the task
 *
 * Does NOT:
 *   - Deregister More MCP's elementor_* tools (they keep working; downstream
 *     clients that already know about them continue functioning)
 *   - Auto-proxy calls to Elementor's abilities (that's the WP MCP Adapter's
 *     job — see SCOPE_1.4.38.md for the abilities registration ship)
 *   - Modify capability gates or per-tool behavior
 */
class Elementor_Coexistence {

	/**
	 * Version-scoped user-meta dismissal key. When plugin bumps to a new
	 * version, the notice re-appears once so users see the coexistence
	 * message even if they'd dismissed it in an earlier version.
	 */
	const NOTICE_DISMISS_KEY = 'more_mcp_elementor_coexistence_dismissed';

	/**
	 * admin-post action for the dismiss link. Must match the nonce in
	 * render_notice().
	 */
	const NOTICE_ACTION = 'more_mcp_dismiss_elementor_coexistence';

	/**
	 * True when Elementor's core MCP module is actually registering abilities
	 * on this site — i.e. an MCP client that discovered the site would see
	 * Elementor's tools.
	 *
	 * Delegates to Elementor's own `Module::is_active()` which returns true
	 * only when BOTH conditions hold: the `WP\MCP\Core\McpAdapter` class is
	 * present (the WP MCP Adapter Composer package is installed) AND the
	 * WordPress Abilities API is available (`wp_register_ability` exists).
	 *
	 * Class-exists alone is NOT sufficient: Elementor 4.x ships the module
	 * class in the plugin bundle on every install, so class_exists would fire
	 * this coexistence layer on millions of sites where the MCP module is
	 * actually dormant. Their own is_active() gate is the correct signal.
	 *
	 * A filter (`more_mcp_elementor_native_mcp_active`, default null) allows
	 * downstream code / tests to force the answer without touching Elementor.
	 * Returning bool from the filter overrides detection; null preserves it.
	 */
	public static function is_native_mcp_active() {
		$override = apply_filters( 'more_mcp_elementor_native_mcp_active', null );
		if ( is_bool( $override ) ) {
			return $override;
		}

		if ( ! class_exists( '\Elementor\Modules\Mcp\Module' ) ) {
			return false;
		}
		if ( ! method_exists( '\Elementor\Modules\Mcp\Module', 'is_active' ) ) {
			return false;
		}

		return (bool) \Elementor\Modules\Mcp\Module::is_active();
	}

	/**
	 * Wire up admin_notices + admin_post handlers. Called from the main
	 * plugin bootstrap. Safe to call regardless of Elementor presence —
	 * the render callback is the only one that checks native detection.
	 */
	public static function register_hooks() {
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_notice' ] );
		add_action( 'admin_post_' . self::NOTICE_ACTION, [ __CLASS__, 'handle_dismiss' ] );
	}

	/**
	 * Render the coexistence notice on More MCP admin pages when both
	 * conditions hold: Elementor's MCP module is present + this user hasn't
	 * dismissed it for the current plugin version.
	 */
	public static function maybe_show_notice() {
		if ( ! self::is_native_mcp_active() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'more-mcp' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$dismissed_version = (string) get_user_meta( $user_id, self::NOTICE_DISMISS_KEY, true );
		if ( defined( 'MORE_MCP_VERSION' ) && $dismissed_version === (string) MORE_MCP_VERSION ) {
			return;
		}

		self::render_notice();
	}

	/**
	 * The actual notice markup. Kept single-tone and factual — Elementor
	 * shipping their own MCP module is not a problem for our customers, and
	 * the copy shouldn't imply we're competing or defensive.
	 */
	private static function render_notice() {
		$dismiss_url = wp_nonce_url(
			add_query_arg(
				[ 'action' => self::NOTICE_ACTION ],
				admin_url( 'admin-post.php' )
			),
			self::NOTICE_ACTION
		);

		// Intentionally NOT using `is-dismissible` class here — that adds WP core's
		// X button which only dismisses for the current page load (no server-side
		// persistence hook exists for it unless we ship the AJAX handler). Having
		// two dismiss paths (transient X vs. our persistent link) with only one
		// actually persisting is a confusing UX. The explicit "Dismiss for this
		// version" link below is the single source of dismissal truth.
		?>
		<div class="notice notice-info more-mcp-elementor-coexistence-notice">
			<p>
				<strong><?php esc_html_e( 'Elementor MCP module detected on this site.', 'more-mcp' ); ?></strong>
				<?php esc_html_e( "Both MCP servers are active. More MCP's Elementor tools continue to work. Agents will see both tool surfaces and can pick per task — for structural page writes, Elementor's own primitives are typically the better choice.", 'more-mcp' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link">
					<?php esc_html_e( 'Dismiss for this version', 'more-mcp' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the dismiss link. Stores the current plugin version so the
	 * notice reappears on the next version bump.
	 */
	public static function handle_dismiss() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'more-mcp' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::NOTICE_ACTION );

		$user_id = get_current_user_id();
		if ( $user_id ) {
			$version = defined( 'MORE_MCP_VERSION' ) ? MORE_MCP_VERSION : '1.0.0';
			update_user_meta( $user_id, self::NOTICE_DISMISS_KEY, (string) $version );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=more-mcp' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * When Elementor's MCP module is active, prefix the description of every
	 * elementor_* tool More MCP registers with a short routing hint pointing
	 * at Elementor's canonical primitives. Agents reading the tool list see
	 * both options and can pick per task — no behavior change to our tools.
	 *
	 * Called from Server::get_tools() right before return so it applies
	 * uniformly across every code path that reads the tool list.
	 *
	 * @param array $tools Full tool list from Server::get_tools() (each entry
	 *                     has at minimum 'name' and 'description' keys).
	 * @return array The same list with elementor_* descriptions prefixed.
	 */
	public static function filter_elementor_tool_descriptions( $tools ) {
		if ( ! self::is_native_mcp_active() ) {
			return $tools;
		}

		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$prefix = '[Also available: elementor/build-composition, elementor/get-page-structure via Elementor\'s native MCP.] ';

		foreach ( $tools as $idx => $tool ) {
			$name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
			if ( 0 !== strpos( $name, 'elementor_' ) ) {
				continue;
			}
			$existing = isset( $tool['description'] ) ? (string) $tool['description'] : '';
			// Idempotent: don't double-prefix if already applied by an
			// earlier pass (defensive against filters that iterate).
			if ( 0 === strpos( $existing, $prefix ) ) {
				continue;
			}
			$tools[ $idx ]['description'] = $prefix . $existing;
		}

		return $tools;
	}
}
