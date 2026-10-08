<?php
namespace More_MCP\Admin;

use More_MCP\Access\Approvals;
use More_MCP\Access\Classifier;
use More_MCP\Access\Safety;
use More_MCP\Auth\Api_Tokens;
use More_MCP\MCP\Change_History;
use More_MCP\Platform\Locked_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Safety_Controls {

	public static function register(): void {
		foreach ( array(
			'set_safety',
			'decide_approval',
			'create_token',
			'revoke_token',
			'delete_token',
			'token_settings',
			'history_diff',
			'history_restore',
		) as $action ) {
			add_action( 'wp_ajax_more_mcp_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_profile_assets' ) );
	}

	

	
	public static function enqueue(): void {
		self::enqueue_assets();
	}

	public static function enqueue_profile_assets( $hook ): void {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) || ! self::profile_section_visible() ) {
			return;
		}
		self::enqueue_assets();
	}

	private static function enqueue_assets(): void {
		$js  = MORE_MCP_PLUGIN_DIR . 'assets/js/admin-safety.js';
		$css = MORE_MCP_PLUGIN_DIR . 'assets/css/admin-safety.css';
		$ver = MORE_MCP_VERSION . '.' . ( file_exists( $js ) ? filemtime( $js ) : '0' );

		wp_enqueue_style( 'more-mcp-admin-safety', MORE_MCP_PLUGIN_URL . 'assets/css/admin-safety.css', array(), MORE_MCP_VERSION . '.' . ( file_exists( $css ) ? filemtime( $css ) : '0' ) );
		wp_enqueue_script( 'more-mcp-admin-safety', MORE_MCP_PLUGIN_URL . 'assets/js/admin-safety.js', array( 'jquery' ), $ver, true );
		wp_localize_script(
			'more-mcp-admin-safety',
			'moreMcpSafety',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'more_mcp_nonce' ),
				'groups'  => Classifier::groups(),
				'strings' => array(
					'saving'        => __( 'Saving…', 'mordenhost-mcp-server' ),
					'saved'         => __( 'Saved', 'mordenhost-mcp-server' ),
					'failed'        => __( 'Could not save. Reload the page and try again.', 'mordenhost-mcp-server' ),
					'copied'        => __( 'Copied', 'mordenhost-mcp-server' ),
					'copy'          => __( 'Copy', 'mordenhost-mcp-server' ),
					'confirmRevoke' => __( 'Revoke this token? Anything using it stops working immediately.', 'mordenhost-mcp-server' ),
					'confirmDelete' => __( 'Remove this token from the list?', 'mordenhost-mcp-server' ),
					'confirmPause'  => __( 'Pause every MCP connection to this site? Connected clients get an error until you switch it back on.', 'mordenhost-mcp-server' ),
					'confirmForce'  => __( 'The current version of this item differs from the one this entry recorded. Restore anyway and discard the later changes?', 'mordenhost-mcp-server' ),
					'confirmRestore' => __( 'Put this item back the way it was before the change?', 'mordenhost-mcp-server' ),
					'restored'      => __( 'Restored', 'mordenhost-mcp-server' ),
					'close'         => __( 'Close', 'mordenhost-mcp-server' ),
					'read'          => __( 'Read', 'mordenhost-mcp-server' ),
					'write'         => __( 'Read and write', 'mordenhost-mcp-server' ),
				),
			)
		);
	}

	

	public static function ajax_set_safety(): void {
		self::guard_admin();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by guard_admin().
		$input   = array();
		$details = array();
		foreach ( array( 'paused', 'force_draft', 'require_approval', 'disable_destructive', 'allow_privileged' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ]   = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$details[ $key ] = in_array( $input[ $key ], array( '1', 'true', 'on' ), true );
			}
		}
		if ( isset( $_POST['approval_mode'] ) ) {
			$input['approval_mode']   = sanitize_key( wp_unslash( $_POST['approval_mode'] ) );
			$details['approval_mode'] = $input['approval_mode'];
		}
		foreach ( array( 'disabled_tools', 'whitelist' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
				$details[ $key ] = 'changed';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		
		list( $free, $pinned ) = Locked_Settings::strip( Safety::OPTION, $input );
		if ( $pinned && ! $free ) {
			Locked_Settings::refuse( Safety::OPTION, $pinned );
		}

		$result = Safety::save( $input );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => esc_html( implode( ' ', $result['errors'] ) ) ) );
		}

		Access_Controls::log( 'safety:set', $details );
		wp_send_json_success(
			array(
				'state'   => Safety::current(),
				'message' => esc_html__( 'Saved', 'mordenhost-mcp-server' ),
			)
		);
	}

	public static function ajax_decide_approval(): void {
		self::guard_admin();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by guard_admin().
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $id || ! Approvals::decide( $id, 'approve' === $decision ? 'approved' : 'denied', get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'That request is no longer waiting (it may have expired or already been decided).', 'mordenhost-mcp-server' ) ) );
		}

		Access_Controls::log( 'approval:' . ( 'approve' === $decision ? 'approved' : 'denied' ), array( 'id' => $id ) );
		wp_send_json_success(
			array(
				'pending' => Approvals::pending_count(),
				'message' => esc_html__( 'Saved', 'mordenhost-mcp-server' ),
			)
		);
	}

	

	public static function ajax_create_token(): void {
		self::guard_tokens();

		$is_admin = current_user_can( 'manage_options' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by guard_tokens().
		$user_id = $is_admin && isset( $_POST['user_id'] ) && absint( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();
		$groups  = array();
		if ( isset( $_POST['groups'] ) && is_array( $_POST['groups'] ) ) {
			$posted = map_deep( wp_unslash( $_POST['groups'] ), 'sanitize_key' );
			foreach ( $posted as $group => $level ) {
				if ( is_string( $group ) && is_string( $level ) ) {
					$groups[ sanitize_key( $group ) ] = $level;
				}
			}
		}
		$args = array(
			'label'           => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
			'preset'          => isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : Api_Tokens::PRESET_READ_ONLY,
			'user_id'         => $user_id,
			'expires_in_days' => isset( $_POST['expires_in_days'] ) ? absint( $_POST['expires_in_days'] ) : 0,
			'rate_limit'      => isset( $_POST['rate_limit'] ) ? absint( $_POST['rate_limit'] ) : 0,
			'groups'          => $groups,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $is_admin && Api_Tokens::active_count( $user_id ) >= Api_Tokens::MAX_PER_USER ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum number of active tokens per user */
						esc_html__( 'You can hold up to %d active tokens. Revoke one first.', 'mordenhost-mcp-server' ),
						Api_Tokens::MAX_PER_USER
					),
				)
			);
		}

		try {
			$token = Api_Tokens::create( $args );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}

		Access_Controls::log(
			'token:create',
			array(
				'id'      => $token['id'],
				'label'   => $token['label'],
				'preset'  => $token['preset'],
				'user_id' => $token['user_id'],
			)
		);

		wp_send_json_success(
			array(
				'id'      => $token['id'],
				'token'   => $token['token'],
				'message' => esc_html__( 'Token created. Copy it now: it is not shown again.', 'mordenhost-mcp-server' ),
			)
		);
	}

	public static function ajax_revoke_token(): void {
		$row = self::guard_token_row();
		Api_Tokens::revoke( (int) $row['id'] );
		Access_Controls::log( 'token:revoke', array( 'id' => (int) $row['id'], 'label' => $row['label'] ) );
		wp_send_json_success( array( 'message' => esc_html__( 'Revoked', 'mordenhost-mcp-server' ) ) );
	}

	public static function ajax_delete_token(): void {
		$row = self::guard_token_row();
		Api_Tokens::delete( (int) $row['id'] );
		Access_Controls::log( 'token:delete', array( 'id' => (int) $row['id'], 'label' => $row['label'] ) );
		wp_send_json_success( array( 'message' => esc_html__( 'Removed', 'mordenhost-mcp-server' ) ) );
	}

	public static function ajax_token_settings(): void {
		self::guard_admin();
		Locked_Settings::refuse( Api_Tokens::OPTION, array( 'self_service' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by guard_admin().
		$value = isset( $_POST['self_service'] ) ? sanitize_text_field( wp_unslash( $_POST['self_service'] ) ) : '0';
		Api_Tokens::save_settings( array( 'self_service' => $value ) );
		Access_Controls::log( 'token:settings', array( 'self_service' => Api_Tokens::self_service_enabled() ) );
		wp_send_json_success( array( 'message' => esc_html__( 'Saved', 'mordenhost-mcp-server' ) ) );
	}

	private static function guard_tokens(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) || ! Api_Tokens::self_service_enabled() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mordenhost-mcp-server' ) ) );
		}
	}

	private static function guard_token_row(): array {
		self::guard_tokens();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by guard_tokens().
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$row = $id ? Api_Tokens::get( $id ) : null;
		if ( ! $row || (int) $row['site_id'] !== get_current_blog_id() || ( ! current_user_can( 'manage_options' ) && (int) $row['user_id'] !== get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Token not found.', 'mordenhost-mcp-server' ) ) );
		}
		return $row;
	}

	

	public static function ajax_history_diff(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );
		$row = self::history_row();
		wp_send_json_success( array( 'html' => self::diff_html( Change_History::diff( $row ) ) ) );
	}

	public static function ajax_history_restore(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );
		$row = self::history_row();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$force = ! empty( $_POST['force'] );

		try {
			$result = Change_History::apply( $row, $force );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => esc_html( $e->getMessage() ),
					'changed' => false !== strpos( $e->getMessage(), 'has changed since' ),
				)
			);
		}

		Access_Controls::log( 'history:restore', array( 'entry' => (int) $row['id'], 'object' => $row['object_type'] . ':' . $row['object_id'], 'forced' => $force ) );
		wp_send_json_success(
			array(
				'verified' => ! empty( $result['verified'] ),
				'message'  => esc_html__( 'Restored', 'mordenhost-mcp-server' ),
			)
		);
	}

	private static function history_row(): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mordenhost-mcp-server' ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller.
		$row = Change_History::get_row( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $row || ! Change_History::can_access( $row['object_type'], $row['object_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'History entry not found.', 'mordenhost-mcp-server' ) ) );
		}
		return $row;
	}

	public static function diff_html( array $diff ): string {
		$out  = '<p class="mmcp-hist-summary"><strong>' . esc_html( (string) $diff['summary'] ) . '</strong> ';
		$out .= '<span class="mmcp-pill">' . esc_html( (string) $diff['change'] ) . '</span></p>';

		$sections = array();
		foreach ( array( 'fields', 'meta' ) as $group ) {
			foreach ( $diff[ $group ] ?? array() as $name => $change ) {
				$sections[] = array( $group . ' · ' . $name, $change );
			}
		}
		foreach ( array( 'terms', 'roles', 'value', 'css' ) as $single ) {
			if ( isset( $diff[ $single ] ) ) {
				$sections[] = array( $single, $diff[ $single ] );
			}
		}
		if ( ! $sections ) {
			return $out . '<p>' . esc_html__( 'No field-level difference was recorded.', 'mordenhost-mcp-server' ) . '</p>';
		}

		foreach ( $sections as $section ) {
			$out .= '<h4>' . esc_html( (string) $section[0] ) . '</h4>' . self::change_html( (array) $section[1] );
		}
		return $out;
	}

	private static function change_html( array $change ): string {
		if ( isset( $change['diff'] ) ) {
			$d = $change['diff'];
			if ( ! empty( $d['truncated'] ) ) {
				return '<p>' . esc_html(
					sprintf(
						/* translators: 1: lines added, 2: lines removed */
						__( 'Large change: about %1$d lines added, %2$d removed.', 'mordenhost-mcp-server' ),
						(int) $d['added'],
						(int) $d['removed']
					)
				) . '</p>';
			}
			$html = '<pre class="mmcp-diff">';
			foreach ( $d['hunks'] as $hunk ) {
				$html .= '<span class="mmcp-diff-hunk">@@ -' . (int) $hunk['old_start'] . ' +' . (int) $hunk['new_start'] . " @@</span>\n";
				foreach ( $hunk['lines'] as $line ) {
					$class = '+' === $line[0] ? 'mmcp-diff-add' : ( '-' === $line[0] ? 'mmcp-diff-del' : '' );
					$html .= '<span class="' . esc_attr( $class ) . '">' . esc_html( $line ) . "</span>\n";
				}
			}
			return $html . '</pre>';
		}

		$html = '';
		foreach ( array( 'before' => 'mmcp-diff-del', 'removed' => 'mmcp-diff-del', 'after' => 'mmcp-diff-add', 'added' => 'mmcp-diff-add' ) as $key => $class ) {
			if ( array_key_exists( $key, $change ) ) {
				$value = is_scalar( $change[ $key ] ) || null === $change[ $key ] ? (string) $change[ $key ] : (string) wp_json_encode( $change[ $key ] );
				$html .= '<pre class="mmcp-diff"><span class="' . esc_attr( $class ) . '">' . esc_html( ( 'after' === $key || 'added' === $key ? '+ ' : '- ' ) . $value ) . '</span></pre>';
			}
		}
		if ( ! empty( $change['changed'] ) ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: 1: size before, 2: size after (characters) */
					__( 'Changed (%1$d → %2$d characters).', 'mordenhost-mcp-server' ),
					(int) $change['length_before'],
					(int) $change['length_after']
				)
			) . '</p>';
		}
		return $html;
	}

	

	private static function profile_section_visible(): bool {
		return Api_Tokens::self_service_enabled() && current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' );
	}

	public static function render_profile_section( $user ): void {
		if ( ! $user instanceof \WP_User || (int) $user->ID !== get_current_user_id() || ! self::profile_section_visible() ) {
			return;
		}
		$tokens = Api_Tokens::all( (int) $user->ID );
		require MORE_MCP_PLUGIN_DIR . 'templates/admin/profile-tokens.php';
	}

	

	private static function guard_admin(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mordenhost-mcp-server' ) ) );
		}
	}
}
