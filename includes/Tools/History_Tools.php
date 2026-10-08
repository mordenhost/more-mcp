<?php

namespace More_MCP\Tools;

use More_MCP\MCP\Change_History;
use More_MCP\MCP\Log_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class History_Tools implements Handler {

	const TOOLS = array( 'wp_history_list', 'wp_history_get', 'wp_history_diff', 'wp_history_apply', 'wp_audit_list' );

	public static function get_tools(): array {
		$entry = array( 'type' => 'integer', 'description' => 'History entry ID (from wp_history_list).' );
		return array(
			array(
				'name'        => 'wp_history_list',
				'description' => 'List recorded changes made through this plugin, newest first: what changed, which tool, which user and credential, and when. Covers posts and pages (with their meta, terms and builder data), options, theme mods, custom CSS, terms, comments and users. Filter by object to see the history of one post. Only objects you can edit are shown.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'object_type' => array( 'type' => 'string', 'enum' => array( 'post', 'option', 'theme_mod', 'custom_css', 'term', 'comment', 'user' ) ),
						'object_id'   => array( 'type' => 'string', 'description' => 'ID of the object (a post ID, an option name, a term ID...).' ),
						'tool'        => array( 'type' => 'string', 'description' => 'Only changes made by this tool.' ),
						'user_id'     => array( 'type' => 'integer', 'description' => 'Only changes made as this WordPress user.' ),
						'state'       => array( 'type' => 'string', 'enum' => array( 'applied', 'restored' ), 'description' => 'applied = still in effect, restored = already reverted.' ),
						'since'       => array( 'type' => 'string', 'description' => 'Only changes at or after this date/time (ISO 8601 or any strtotime format, UTC).' ),
						'limit'       => array( 'type' => 'integer', 'description' => 'Rows to return (default 25, max 100).' ),
						'offset'      => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'wp_history_get',
				'description' => 'Get one history entry with its full before and after snapshots. Long values are shortened to max_chars; pass full:true for the complete text.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => $entry,
						'full'      => array( 'type' => 'boolean', 'description' => 'Return values untruncated (can be very large for page-builder data).' ),
						'max_chars' => array( 'type' => 'integer', 'description' => 'Shorten longer strings to this many characters (default 2000).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_history_diff',
				'description' => 'Show what a history entry changed, field by field. Post content and CSS come back as line diffs with a little context; meta and other fields as before/after values.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'id' => $entry ), 'required' => array( 'id' ) ),
			),
			array(
				'name'        => 'wp_history_apply',
				'description' => 'Restore an object to its state before a recorded change. If the object was created by that change it is moved to the trash (posts) or removed. Refuses when the object has been changed again since, because restoring would discard those edits: restore the newest entry first, or pass force:true. The restore is itself recorded, so it can be undone the same way.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => $entry,
						'force' => array( 'type' => 'boolean', 'description' => 'Restore even though the object has changed since this entry.' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_audit_list',
				'description' => 'Read the activity log: every tool call with the user, credential (API key, named token or OAuth client), source address, a redacted argument summary and duration, plus calls that were refused and failed sign-ins. Newest first. Needs manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'     => array( 'type' => 'string', 'enum' => array( 'success', 'error', 'refused' ) ),
						'tool'       => array( 'type' => 'string', 'description' => 'Tool name or part of it.' ),
						'user_id'    => array( 'type' => 'integer' ),
						'credential' => array( 'type' => 'string', 'description' => 'Part of the credential label, e.g. a token name.' ),
						'since'      => array( 'type' => 'string', 'description' => 'Only entries at or after this date/time (UTC).' ),
						'limit'      => array( 'type' => 'integer', 'description' => 'Rows to return (default 25, max 100).' ),
						'offset'     => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_history_list':
				return self::history_list( $args );
			case 'wp_history_get':
				return self::history_get( $args );
			case 'wp_history_diff':
				return Change_History::diff( self::entry( $args ) );
			case 'wp_history_apply':
				return Change_History::apply( self::entry( $args ), ! empty( $args['force'] ) );
			case 'wp_audit_list':
				return self::audit_list( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function entry( array $args ): array {
		$row = Change_History::get_row( (int) ( $args['id'] ?? 0 ) );
		if ( ! $row || ! Change_History::can_access( $row['object_type'], $row['object_id'] ) ) {
			
			throw new \Exception( 'History entry not found.' );
		}
		return $row;
	}

	private static function history_list( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read change history.' );
		}
		$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 25 ) ) );
		$result = Change_History::query( $args );

		$rows = array();
		foreach ( $result['rows'] as $row ) {
			if ( ! Change_History::can_access( $row['object_type'], $row['object_id'] ) ) {
				continue;
			}
			$rows[] = Change_History::present( $row );
		}
		return array(
			'total'   => $result['total'],
			'count'   => count( $rows ),
			'limit'   => $limit,
			'entries' => $rows,
			'note'    => count( $rows ) < count( $result['rows'] ) ? 'Some entries were left out because you cannot edit the objects they refer to.' : '',
		);
	}

	private static function history_get( array $args ): array {
		$row   = self::entry( $args );
		$full  = ! empty( $args['full'] );
		$chars = isset( $args['max_chars'] ) ? max( 100, min( 100000, (int) $args['max_chars'] ) ) : 2000;
		return Change_History::present( $row, true, $full ? 0 : $chars );
	}

	private static function audit_list( array $args ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'manage_options capability required to read the audit log.' );
		}
		$result  = Log_Store::query( $args );
		$entries = array();
		foreach ( $result['rows'] as $row ) {
			$request  = json_decode( (string) $row['request_data'], true );
			$response = json_decode( (string) $row['response_data'], true );
			$request  = is_array( $request ) ? $request : array();
			$response = is_array( $response ) ? $response : array();

			$entry = array(
				'id'     => (int) $row['id'],
				'time'   => gmdate( 'c', (int) strtotime( $row['timestamp'] . ' UTC' ) ),
				'action' => $row['action'],
				'status' => $row['status'],
			);
			foreach ( array( 'actor', 'args', 'arg_keys' ) as $key ) {
				if ( isset( $request[ $key ] ) ) {
					$entry[ $key ] = $request[ $key ];
				}
			}
			foreach ( array( 'duration_ms', 'error', 'reason' ) as $key ) {
				if ( isset( $response[ $key ] ) ) {
					$entry[ $key ] = $response[ $key ];
				}
			}
			$entries[] = $entry;
		}
		return array(
			'total'   => $result['total'],
			'count'   => count( $entries ),
			'entries' => $entries,
		);
	}
}
