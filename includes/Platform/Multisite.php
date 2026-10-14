<?php

namespace Mordenhost_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Multisite {

	const NETWORK_OPTION = 'mordenhost_mcp_network';

	const NETWORK_OPTIONS = array( self::NETWORK_OPTION );

	public static function is_active(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	public static function is_network_option( string $name ): bool {
		return in_array( $name, self::NETWORK_OPTIONS, true );
	}

	public static function network_settings(): array {
		if ( ! self::is_active() ) {
			return array();
		}
		$stored = get_site_option( self::NETWORK_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function network_paused(): bool {
		if ( defined( 'MORDENHOST_MCP_NETWORK_PAUSED' ) ) {
			return (bool) constant( 'MORDENHOST_MCP_NETWORK_PAUSED' );
		}
		$s = self::network_settings();
		return ! empty( $s['paused'] );
	}

	public static function set_network_paused( bool $paused ): void {
		$s           = self::network_settings();
		$s['paused'] = $paused ? 1 : 0;
		update_site_option( self::NETWORK_OPTION, $s );
	}

	public static function for_each_site( callable $callback, array $blog_ids = array() ): void {
		if ( ! self::is_active() ) {
			$callback( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
			return;
		}
		if ( ! $blog_ids ) {
			$blog_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
		}
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try {
				$callback( (int) $blog_id );
			} finally {
				restore_current_blog();
			}
		}
	}

	public static function register(): void {
		if ( ! self::is_active() ) {
			return;
		}
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_new_site' ), 20 );
		add_action( 'network_admin_menu', array( __CLASS__, 'add_network_menu' ) );
		add_action( 'admin_post_mordenhost_mcp_network_save', array( __CLASS__, 'handle_network_save' ) );
	}

	public static function on_new_site( $site ): void {
		if ( ! is_object( $site ) || empty( $site->blog_id ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( MORDENHOST_MCP_PLUGIN_BASENAME ) ) {
			return;
		}
		self::for_each_site(
			static function () {
				\Mordenhost_MCP_Plugin::get_instance()->activate_single();
			},
			array( (int) $site->blog_id )
		);
	}

	

	public static function add_network_menu(): void {
		add_menu_page(
			__( 'Mordenhost MCP', 'mordenhost-mcp' ),
			__( 'Mordenhost MCP', 'mordenhost-mcp' ),
			'manage_network_options',
			'mordenhost-mcp-network',
			array( __CLASS__, 'render_network_page' ),
			'dashicons-rest-api',
			81
		);
	}

	public static function render_network_page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$paused = self::network_paused();
		$locked = defined( 'MORDENHOST_MCP_NETWORK_PAUSED' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mordenhost MCP: network', 'mordenhost-mcp' ); ?></h1>
			<p><?php esc_html_e( 'Every site has its own Mordenhost MCP settings, API key, tokens, history and activity log. Open a site\'s dashboard to manage it. This screen holds the one control that applies to the whole network.', 'mordenhost-mcp' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mordenhost_mcp_network_save">
				<?php wp_nonce_field( 'mordenhost_mcp_network_save' ); ?>
				<label>
					<input type="checkbox" name="paused" value="1" <?php checked( $paused ); ?> <?php disabled( $locked ); ?>>
					<?php esc_html_e( 'Pause AI access on every site in the network', 'mordenhost-mcp' ); ?>
				</label>
				<?php if ( $locked ) : ?>
					<p class="description"><?php esc_html_e( 'Fixed by MORDENHOST_MCP_NETWORK_PAUSED in wp-config.php.', 'mordenhost-mcp' ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'While paused, every tool call on every site is refused. Individual sites can also be paused from their own Safety screen.', 'mordenhost-mcp' ); ?></p>
				<?php submit_button( __( 'Save', 'mordenhost-mcp' ) ); ?>
			</form>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag. ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'mordenhost-mcp' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_network_save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'mordenhost-mcp' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mordenhost_mcp_network_save' );
		if ( ! defined( 'MORDENHOST_MCP_NETWORK_PAUSED' ) ) {
			self::set_network_paused( ! empty( $_POST['paused'] ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'mordenhost-mcp-network', 'saved' => 1 ), network_admin_url( 'admin.php' ) ) );
		exit;
	}

	

	
	public static function delete_network_data(): void {
		if ( self::is_active() ) {
			delete_site_option( self::NETWORK_OPTION );
		}
	}
}
