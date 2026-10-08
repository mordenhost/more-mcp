<?php
namespace More_MCP\Admin;

use More_MCP\Access\Classifier;
use More_MCP\Access\Ip_Matcher;
use More_MCP\Access\Policy;
use More_MCP\Platform\Locked_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Access_Controls {

	public static function register(): void {
		add_action( 'wp_ajax_more_mcp_set_access', array( __CLASS__, 'ajax_set_access' ) );
		add_action( 'wp_ajax_more_mcp_set_connection_access', array( __CLASS__, 'ajax_set_connection_access' ) );
	}

	public static function localize( string $handle ): void {
		wp_localize_script(
			$handle,
			'moreMcpAccess',
			array(
				'groups'  => Classifier::groups(),
				'strings' => array(
					'full'       => __( 'Full access', 'mordenhost-mcp-server' ),
					'readOnly'   => __( 'Read-only', 'mordenhost-mcp-server' ),
					'custom'     => __( 'Custom', 'mordenhost-mcp-server' ),
					'none'       => __( 'No access', 'mordenhost-mcp-server' ),
					'read'       => __( 'Read', 'mordenhost-mcp-server' ),
					'write'      => __( 'Read and write', 'mordenhost-mcp-server' ),
					'saving'     => __( 'Saving…', 'mordenhost-mcp-server' ),
					'saved'      => __( 'Saved', 'mordenhost-mcp-server' ),
					'saveFailed' => __( 'Could not save. Reload the page and try again.', 'mordenhost-mcp-server' ),
					'undoNote'   => __( 'Undo needs write access to every group, so it stays off for custom access until all of them are set to Read and write.', 'mordenhost-mcp-server' ),
				),
			)
		);
	}

	public static function ajax_set_access(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mordenhost-mcp-server' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() above.
		$input = array();
		if ( isset( $_POST['read_only'] ) ) {
			$input['read_only'] = sanitize_text_field( wp_unslash( $_POST['read_only'] ) );
		}
		if ( isset( $_POST['default_oauth_access'] ) ) {
			$input['default_oauth_access'] = sanitize_key( wp_unslash( $_POST['default_oauth_access'] ) );
		}
		foreach ( array( 'ip_allowlist', 'trusted_proxies' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		
		list( $input, $pinned ) = Locked_Settings::strip( Policy::OPTION_GLOBAL, $input );
		if ( $pinned && ! $input ) {
			Locked_Settings::refuse( Policy::OPTION_GLOBAL, $pinned );
		}

		$result = Policy::save_global( $input );

		if ( ! $result['ok'] ) {
			$bad = array();
			foreach ( $result['errors'] as $entries ) {
				$bad = array_merge( $bad, $entries );
			}
			wp_send_json_error(
				array(
					'invalid' => $bad,
					'message' => sprintf(
						/* translators: %s: comma-separated list of entries that are not valid IP addresses or ranges */
						esc_html__( 'Not saved. These are not valid IP addresses or ranges: %s', 'mordenhost-mcp-server' ),
						esc_html( implode( ', ', $bad ) )
					),
				)
			);
		}

		self::log( 'access:set', array_keys( $input ) );

		$response = array(
			'read_only'    => Policy::read_only_mode(),
			'ip_allowlist' => Policy::ip_allowlist_raw(),
			'message'      => esc_html__( 'Saved', 'mordenhost-mcp-server' ),
		);

		
		if ( isset( $input['ip_allowlist'] ) && Policy::ip_allowlist_enabled() ) {
			$mine = Policy::client_ip();
			if ( '' !== $mine && ! Policy::ip_allowed( $mine ) ) {
				$response['warning'] = sprintf(
					/* translators: %s: the administrator's current IP address */
					esc_html__( 'Your current address (%s) is not in this list. That only matters if you run an AI client from this same address.', 'mordenhost-mcp-server' ),
					esc_html( $mine )
				);
			}
		}

		wp_send_json_success( $response );
	}

	public static function ajax_set_connection_access(): void {
		check_ajax_referer( 'more_mcp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mordenhost-mcp-server' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() above.
		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '';

		if ( 'api-key' === $target ) {
			$key = Policy::API_KEY;
		} else {
			$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
			$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
			if ( '' === $client_id || 0 === $user_id ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Missing client or user identifier.', 'mordenhost-mcp-server' ) ) );
			}
			$key = Policy::key_for_grant( $client_id, $user_id );
		}

		$raw = array(
			'access' => isset( $_POST['access'] ) ? sanitize_key( wp_unslash( $_POST['access'] ) ) : '',
			'groups' => array(),
		);
		if ( isset( $_POST['groups'] ) && is_array( $_POST['groups'] ) ) {
			$posted_groups = map_deep( wp_unslash( $_POST['groups'] ), 'sanitize_key' );
			foreach ( $posted_groups as $group => $level ) {
				if ( is_string( $group ) && is_string( $level ) ) {
					$raw['groups'][ sanitize_key( $group ) ] = $level;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$policy = Policy::save( $key, $raw );

		self::log(
			'access:set_connection',
			array(
				'target' => 'api-key' === $key ? 'api-key' : $key,
				'access' => $policy['access'],
				'groups' => $policy['groups'],
			)
		);

		wp_send_json_success(
			array(
				'access'  => $policy['access'],
				'groups'  => (object) $policy['groups'],
				'message' => esc_html__( 'Saved', 'mordenhost-mcp-server' ),
			)
		);
	}

	public static function editor_attributes( ?string $key ): array {
		$policy = Policy::for_key( $key );
		return array(
			'data-access' => $policy ? $policy['access'] : Policy::FULL,
			'data-groups' => (string) wp_json_encode( (object) ( $policy ? $policy['groups'] : array() ) ),
		);
	}

	public static function current_address(): string {
		return Policy::client_ip();
	}

	public static function address_is_allowed( string $address ): bool {
		return '' !== $address && Policy::ip_allowed( $address );
	}

	public static function trusted_proxy_entries(): array {
		return Ip_Matcher::parse( Policy::trusted_proxies_raw() )['entries'];
	}

	public static function log( string $action, array $details ): void {
		global $wpdb;
		$user = wp_get_current_user();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct insert to the logs table.
		$wpdb->insert(
			$wpdb->prefix . 'more_mcp_logs',
			array(
				'mcp_server'    => 'Access Controls',
				'action'        => $action,
				'request_data'  => wp_json_encode(
					array(
						'user_id'    => (int) $user->ID,
						'user_login' => $user->user_login,
						'details'    => $details,
					)
				),
				'response_data' => wp_json_encode( array( 'status' => 'success' ) ),
				'status'        => 'success',
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
