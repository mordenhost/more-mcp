<?php

namespace More_MCP\Tools;

use More_MCP\MCP\Log_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Feedback implements Handler {

	const TOOL = 'wp_send_feedback';

	const HOURLY_LIMIT = 20;

	public static function get_tools(): array {
		return array(
			array(
				'name'        => self::TOOL,
				'description' => 'Leave a note for the site owner about a problem, a missing capability or a suggestion. The note is saved in this site\'s Activity Log only; nothing is sent anywhere else. Keep it short and specific: what you tried, what happened, what you expected.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'message' => array( 'type' => 'string', 'description' => 'The note (5 to 2000 characters).' ),
						'type'    => array( 'type' => 'string', 'enum' => array( 'bug', 'missing_feature', 'suggestion', 'question', 'other' ) ),
						'tool'    => array( 'type' => 'string', 'description' => 'The tool the note is about, if any.' ),
					),
					'required'   => array( 'message' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return self::TOOL === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( self::TOOL !== $name ) {
			throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
		}

		$message = trim( sanitize_textarea_field( (string) ( $args['message'] ?? '' ) ) );
		if ( strlen( $message ) < 5 || strlen( $message ) > 2000 ) {
			throw new \Exception( 'message must be between 5 and 2000 characters.' );
		}
		$type = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : 'other';
		if ( ! in_array( $type, array( 'bug', 'missing_feature', 'suggestion', 'question', 'other' ), true ) ) {
			$type = 'other';
		}
		$about = isset( $args['tool'] ) ? substr( sanitize_key( (string) $args['tool'] ), 0, 100 ) : '';

		$bucket = 'more_mcp_feedback_' . md5( \More_MCP\MCP\Request_Context::key() . '|' . get_current_user_id() );
		$used   = (int) get_transient( $bucket );
		if ( $used >= self::HOURLY_LIMIT ) {
			throw new \Exception( 'Too many notes from this connection in the last hour. The site owner already has the earlier ones.' );
		}
		set_transient( $bucket, $used + 1, HOUR_IN_SECONDS );

		$id = Log_Store::write(
			'feedback:' . $type,
			array(
				'actor'   => Log_Store::actor(),
				'message' => $message,
				'type'    => $type,
				'tool'    => $about,
			),
			array( 'status' => 'success' ),
			'success'
		);

		return array(
			'recorded'         => $id > 0,
			'id'               => $id,
			'sent_externally'  => false,
			'where'            => 'More MCP > Activity Log, on this site only.',
		);
	}
}
