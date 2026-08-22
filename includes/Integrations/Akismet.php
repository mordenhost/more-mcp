<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Akismet {

	public static function is_available() {
		return class_exists( 'Akismet' ) || function_exists( 'akismet_http_post' ) || defined( 'AKISMET_VERSION' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'akismet' ),
			'capabilities' => array( 'spam' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'akismet_get_status',
				'description' => 'Read Akismet anti-spam status: whether an API key is configured (never the key itself), the lifetime count of spam caught, and how many spam comments are in the moderation queue right now. Read-only diagnostic; returns no credentials and cannot change any setting. To moderate individual comments, use the core comment tools.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use spam tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Akismet is not active.' );
		}
		if ( 'akismet_get_status' !== $name ) {
			throw new \Exception( 'Unknown spam tool: ' . esc_html( $name ) );
		}

		
		$configured = false;
		if ( class_exists( 'Akismet' ) && method_exists( 'Akismet', 'get_api_key' ) ) {
			$key        = \Akismet::get_api_key();
			$configured = ! empty( $key );
		}
		if ( ! $configured && defined( 'WPCOM_API_KEY' ) && '' !== (string) constant( 'WPCOM_API_KEY' ) ) {
			$configured = true;
		}

		$lifetime = get_option( 'akismet_spam_count', null );

		
		
		$queue = null;
		if ( class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'get_spam_count' ) ) {
			$queue = (int) \Akismet_Admin::get_spam_count();
		} elseif ( function_exists( 'wp_count_comments' ) ) {
			$counts = wp_count_comments();
			$queue  = isset( $counts->spam ) ? (int) $counts->spam : null;
		}

		return array(
			'provider'         => 'akismet',
			'configured'       => $configured,
			'version'          => defined( 'AKISMET_VERSION' ) ? (string) AKISMET_VERSION : null,
			'lifetime_spam'    => null === $lifetime ? null : (int) $lifetime,
			'spam_in_queue'    => $queue,
		);
	}
}
