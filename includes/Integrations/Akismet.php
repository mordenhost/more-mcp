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
			array(
				'name'        => 'akismet_check_comment',
				'description' => 'Ask Akismet to classify an EXISTING comment (by comment ID) as spam or not spam, by re-sending it to the Akismet service. Returns is_spam plus Akismet\'s pro-tip / discard hint when present. This is a read-only classification: it does NOT change the comment\'s status, move it to spam, or delete it — use the core comment tools (wp_spam_comment / wp_trash_comment / wp_approve_comment) to act on the verdict. Only a comment already stored on this site can be checked; no ad-hoc text is sent. Requires moderate_comments and a configured Akismet key.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of an existing comment on this site to re-check against Akismet.',
						),
					),
					'required'   => array( 'comment_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		

		if ( 'akismet_check_comment' === $name ) {
			if ( ! current_user_can( 'moderate_comments' ) ) {
				throw new \Exception( 'You do not have permission to moderate comments.' );
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use spam tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Akismet is not active.' );
		}

		if ( 'akismet_check_comment' === $name ) {
			return self::check_comment( $args );
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

	private static function check_comment( $args ) {
		$comment_id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
		if ( $comment_id <= 0 ) {
			throw new \Exception( 'comment_id (a positive integer) is required.' );
		}
		if ( function_exists( 'get_comment' ) && null === get_comment( $comment_id ) ) {
			throw new \Exception( 'Comment ' . (int) $comment_id . ' was not found.' );
		}
		if ( ! class_exists( 'Akismet' ) || ! method_exists( 'Akismet', 'check_db_comment' ) ) {
			throw new \Exception( 'Akismet comment-check is unavailable on this version.' );
		}

		$result = \Akismet::check_db_comment( $comment_id, 'recheck_queue' );

		
		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result->get_error_message() ) );
		}

		
		
		if ( 'true' === $result || 'false' === $result ) {
			return array(
				'provider'   => 'akismet',
				'comment_id' => $comment_id,
				'checked'    => true,
				'is_spam'    => ( 'true' === $result ),
				'note'       => 'Verdict only; the comment status was not changed. Use wp_spam_comment / wp_trash_comment / wp_approve_comment to act on it.',
			);
		}

		return array(
			'provider'   => 'akismet',
			'comment_id' => $comment_id,
			'checked'    => false,
			'is_spam'    => null,
			'note'       => 'Akismet returned no verdict (service unreachable or key unset). The comment status was not changed.',
		);
	}
}
