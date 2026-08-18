<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Elementor_Coexistence {

	
	const NOTICE_DISMISS_KEY = 'more_mcp_elementor_coexistence_dismissed';

	
	const NOTICE_ACTION = 'more_mcp_dismiss_elementor_coexistence';

	
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

	
	public static function register_hooks() {
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_notice' ] );
		add_action( 'admin_post_' . self::NOTICE_ACTION, [ __CLASS__, 'handle_dismiss' ] );
	}

	
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

	
	private static function render_notice() {
		$dismiss_url = wp_nonce_url(
			add_query_arg(
				[ 'action' => self::NOTICE_ACTION ],
				admin_url( 'admin-post.php' )
			),
			self::NOTICE_ACTION
		);

		
		
		
		
		
		
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
			
			
			if ( 0 === strpos( $existing, $prefix ) ) {
				continue;
			}
			$tools[ $idx ]['description'] = $prefix . $existing;
		}

		return $tools;
	}
}
