<?php

namespace More_MCP\Tools;

use More_MCP\Access\Approvals;
use More_MCP\Access\Safety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Safety_Tools implements Handler {

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'more_mcp_approve_request',
				'description' => 'Confirm a pending approval request after the user has clearly agreed, in this conversation, to the exact action it names. Only works when the site is set to chat approval; otherwise the site owner approves in wp-admin. Never call this without the user\'s explicit yes.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'approval_id' => array( 'type' => 'integer', 'description' => 'The request number from the "Approval required" message.' ),
					),
					'required'   => array( 'approval_id' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return 'more_mcp_approve_request' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( 'chat' !== Safety::approval_mode() || ! Safety::require_approval() ) {
			throw new \Exception( 'This site does not use chat approval. The site owner approves requests in wp-admin (More MCP > Safety); tell the user to do that.' );
		}
		$id = (int) ( $args['approval_id'] ?? 0 );
		if ( $id <= 0 || ! Approvals::approve_from_chat( $id ) ) {
			throw new \Exception( 'No open approval request with that number for this connection. It may have expired (they last 15 minutes); repeat the original call to open a new one.' );
		}
		return array(
			'approved' => true,
			'id'       => $id,
			'note'     => 'Approved once. Repeat the original call now with exactly the same arguments; it will run and the approval is then used up.',
		);
	}
}
