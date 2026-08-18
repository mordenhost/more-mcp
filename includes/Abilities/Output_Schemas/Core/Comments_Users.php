<?php
namespace More_MCP\Abilities\Output_Schemas\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Comments_Users {
	public static function map(): array {
		return array(

			'wp_get_comments'         => array(
				'type'  => 'array',
				'items' => Shared::comment_summary_schema(),
			),
			'wp_create_comment'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'wp_delete_comment'       => Shared::message_schema(),
			'wp_get_pending_comments' => array(
				'type'  => 'array',
				'items' => Shared::comment_summary_schema(),
			),
			'wp_approve_comment' => Shared::comment_status_change_schema(),
			'wp_spam_comment'    => Shared::comment_status_change_schema(),
			'wp_trash_comment'   => Shared::comment_status_change_schema(),

			'wp_get_users' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer' ),
						'display_name' => array( 'type' => 'string' ),
						'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
			),
			'wp_get_user'  => array(
				'type'       => 'object',
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'display_name' => array( 'type' => 'string' ),
					'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'registered'   => array( 'type' => 'string' ),
				),
			),

					);
	}
}
