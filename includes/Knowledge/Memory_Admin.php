<?php

namespace More_MCP\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Memory_Admin {

	const PAGE = 'more-mcp-memory';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ), 20 );
		add_action( 'admin_post_more_mcp_memory_delete', array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_more_mcp_memory_delete_all', array( self::class, 'handle_delete_all' ) );
	}

	public static function add_page(): void {
		add_submenu_page(
			'more-mcp',
			__( 'Shared memory (beta)', 'mordenhost-mcp-server' ),
			__( 'Memory (beta)', 'mordenhost-mcp-server' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	public static function page_url( string $result = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		return '' === $result ? $url : add_query_arg( 'more_mcp_memory', $result, $url );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view shared memory.', 'mordenhost-mcp-server' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag set by our own redirect; the template matches it against a fixed list.
		$more_mcp_memory_result  = isset( $_GET['more_mcp_memory'] ) ? sanitize_key( wp_unslash( $_GET['more_mcp_memory'] ) ) : '';
		$more_mcp_memory_entries = Memory_Store::all();
		$more_mcp_memory_total   = Memory_Store::count();

		include MORE_MCP_PLUGIN_DIR . 'templates/admin/memory.php';
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete shared memory.', 'mordenhost-mcp-server' ), 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		check_admin_referer( 'more_mcp_memory_delete_' . $key );

		$ok = Memory_Store::is_valid_key( $key ) && Memory_Store::delete( $key );
		wp_safe_redirect( self::page_url( $ok ? 'deleted' : 'error' ) );
		exit;
	}

	public static function handle_delete_all(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete shared memory.', 'mordenhost-mcp-server' ), 403 );
		}
		check_admin_referer( 'more_mcp_memory_delete_all' );

		if ( empty( $_POST['confirm_delete_all'] ) ) {
			wp_safe_redirect( self::page_url( 'confirm_needed' ) );
			exit;
		}

		Memory_Store::delete_all();
		wp_safe_redirect( self::page_url( 'deleted_all' ) );
		exit;
	}
}
