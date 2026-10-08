<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comment_Moderation implements Handler {

	private const BULK_LIMIT = 100;

	private const ACTIONS = array( 'approve', 'unapprove', 'spam', 'unspam', 'trash', 'untrash' );

	public static function get_tools(): array {
		$id = array( 'type' => 'integer', 'description' => 'Comment ID.' );
		return array(
			array(
				'name'        => 'wp_get_comment',
				'description' => 'Get one comment by ID: content, author, post, parent, date and status. Pending, spam and trashed comments need moderate_comments. The author email is partly masked.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'id' => $id ), 'required' => array( 'id' ) ),
			),
			array(
				'name'        => 'wp_update_comment',
				'description' => 'Edit a comment: content, status (approve, hold, spam, trash), author name, email or URL. Requires edit_comment on that comment. Content is filtered to the tags the comment form allows. Pass only the fields to change.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => $id,
						'content'      => array( 'type' => 'string' ),
						'status'       => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash' ) ),
						'author'       => array( 'type' => 'string' ),
						'author_email' => array( 'type' => 'string' ),
						'author_url'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_reply_to_comment',
				'description' => 'Post a threaded reply to a comment as the authenticated user. The reply follows the site\'s moderation rules unless approve=true and you hold moderate_comments, in which case it is published immediately.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'comment_id' => $id,
						'content'    => array( 'type' => 'string' ),
						'approve'    => array( 'type' => 'boolean', 'description' => 'Publish the reply at once (needs moderate_comments).' ),
					),
					'required'   => array( 'comment_id', 'content' ),
				),
			),
			array(
				'name'        => 'wp_unapprove_comment',
				'description' => 'Return an approved comment to the moderation queue (status hold). Requires moderate_comments.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'comment_id' => $id ), 'required' => array( 'comment_id' ) ),
			),
			array(
				'name'        => 'wp_unspam_comment',
				'description' => 'Take a comment out of spam and restore the status it had before. Requires moderate_comments.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'comment_id' => $id ), 'required' => array( 'comment_id' ) ),
			),
			array(
				'name'        => 'wp_untrash_comment',
				'description' => 'Restore a trashed comment to the status it had before. Requires moderate_comments.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'comment_id' => $id ), 'required' => array( 'comment_id' ) ),
			),
			array(
				'name'        => 'wp_bulk_moderate_comments',
				'description' => 'Apply one moderation action (approve, unapprove, spam, unspam, trash, untrash) to up to ' . self::BULK_LIMIT . ' comments. Each comment is checked individually; the result lists what changed and what was refused and why.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ids'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Comment IDs (max ' . self::BULK_LIMIT . ').' ),
						'action' => array( 'type' => 'string', 'enum' => self::ACTIONS ),
					),
					'required'   => array( 'ids', 'action' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array(
			'wp_get_comment', 'wp_update_comment', 'wp_reply_to_comment',
			'wp_unapprove_comment', 'wp_unspam_comment', 'wp_untrash_comment',
			'wp_bulk_moderate_comments',
		);
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_comment':
				return self::get_comment_tool( $args );
			case 'wp_update_comment':
				return self::update_comment_tool( $args );
			case 'wp_reply_to_comment':
				return self::reply( $args );
			case 'wp_unapprove_comment':
				return self::single( $args, 'unapprove' );
			case 'wp_unspam_comment':
				return self::single( $args, 'unspam' );
			case 'wp_untrash_comment':
				return self::single( $args, 'untrash' );
			case 'wp_bulk_moderate_comments':
				return self::bulk( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function status_of( \WP_Comment $comment ): string {
		$status = wp_get_comment_status( $comment );
		return false === $status ? 'unknown' : (string) $status;
	}

	private static function load( int $id ): \WP_Comment {
		$comment = $id > 0 ? get_comment( $id ) : null;
		if ( ! $comment ) {
			throw new \Exception( 'Comment not found.' );
		}
		return $comment;
	}

	private static function mask_email( string $email ): string {
		return '' === $email ? '' : substr( $email, 0, 2 ) . '***@***';
	}

	private static function get_comment_tool( array $args ): array {
		$comment = self::load( isset( $args['id'] ) ? (int) $args['id'] : 0 );
		$status  = self::status_of( $comment );

		if ( 'approved' !== $status && ! current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
			throw new \Exception( 'You do not have permission to view this comment.' );
		}
		
		if ( ! current_user_can( 'read_post', (int) $comment->comment_post_ID ) ) {
			throw new \Exception( 'You do not have permission to view this comment.' );
		}

		return array(
			'id'                    => (int) $comment->comment_ID,
			'post_id'               => (int) $comment->comment_post_ID,
			'post_title'            => get_the_title( (int) $comment->comment_post_ID ),
			'parent'                => (int) $comment->comment_parent,
			'author'                => $comment->comment_author,
			'author_email_redacted' => self::mask_email( (string) $comment->comment_author_email ),
			'author_url'            => $comment->comment_author_url,
			'user_id'               => (int) $comment->user_id,
			'content'               => $comment->comment_content,
			'date'                  => $comment->comment_date,
			'status'                => $status,
			'type'                  => $comment->comment_type ?: 'comment',
		);
	}

	private static function update_comment_tool( array $args ): array {
		$comment = self::load( isset( $args['id'] ) ? (int) $args['id'] : 0 );
		$id      = (int) $comment->comment_ID;
		if ( ! current_user_can( 'edit_comment', $id ) ) {
			throw new \Exception( 'You do not have permission to edit this comment.' );
		}

		$data = array( 'comment_ID' => $id );
		if ( isset( $args['content'] ) ) {
			$content = wp_filter_kses( (string) $args['content'] );
			if ( '' === trim( $content ) ) {
				throw new \Exception( 'content cannot be empty.' );
			}
			$data['comment_content'] = $content;
		}
		if ( isset( $args['author'] ) ) {
			$data['comment_author'] = sanitize_text_field( (string) $args['author'] );
		}
		if ( isset( $args['author_email'] ) ) {
			$email = sanitize_email( (string) $args['author_email'] );
			if ( '' !== $email && ! is_email( $email ) ) {
				throw new \Exception( 'author_email is not a valid address.' );
			}
			$data['comment_author_email'] = $email;
		}
		if ( isset( $args['author_url'] ) ) {
			$data['comment_author_url'] = esc_url_raw( (string) $args['author_url'] );
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && ! in_array( $status, array( 'approve', 'hold', 'spam', 'trash' ), true ) ) {
			throw new \Exception( 'status must be approve, hold, spam or trash.' );
		}
		if ( 1 === count( $data ) && '' === $status ) {
			throw new \Exception( 'Nothing to update. Pass at least one field besides id.' );
		}

		if ( count( $data ) > 1 ) {
			$result = wp_update_comment( $data, true );
			if ( is_wp_error( $result ) ) {
				throw new \Exception( esc_html( $result->get_error_message() ) );
			}
		}
		if ( '' !== $status ) {
			if ( ! wp_set_comment_status( $id, $status ) ) {
				throw new \Exception( 'Could not change the comment status.' );
			}
		}

		return array(
			'id'      => $id,
			'status'  => self::status_of( self::load( $id ) ),
			'message' => 'Comment updated.',
		);
	}

	private static function reply( array $args ): array {
		if ( ! current_user_can( 'read' ) ) {
			throw new \Exception( 'You do not have permission to post comments.' );
		}
		$parent = self::load( isset( $args['comment_id'] ) ? (int) $args['comment_id'] : 0 );
		$post   = get_post( (int) $parent->comment_post_ID );
		if ( ! $post ) {
			throw new \Exception( 'The comment\'s post no longer exists.' );
		}
		if ( 'open' !== $post->comment_status && ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new \Exception( 'Comments are closed on this post.' );
		}
		$content = wp_filter_kses( (string) ( $args['content'] ?? '' ) );
		if ( '' === trim( $content ) ) {
			throw new \Exception( 'content is required.' );
		}

		$user = wp_get_current_user();
		$data = array(
			'comment_post_ID'      => (int) $post->ID,
			'comment_parent'       => (int) $parent->comment_ID,
			'comment_content'      => $content,
			'user_id'              => (int) $user->ID,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => $user->user_url,
		);

		$approved = wp_allow_comment( $data, true );
		if ( is_wp_error( $approved ) ) {
			throw new \Exception( esc_html( $approved->get_error_message() ) );
		}
		$data['comment_approved'] = $approved;
		if ( ! empty( $args['approve'] ) && current_user_can( 'moderate_comments' ) && 'spam' !== $approved ) {
			$data['comment_approved'] = 1;
		}

		$id = wp_insert_comment( wp_slash( $data ) );
		if ( ! $id ) {
			throw new \Exception( 'Failed to post the reply.' );
		}
		return array(
			'id'      => (int) $id,
			'parent'  => (int) $parent->comment_ID,
			'status'  => self::status_of( self::load( (int) $id ) ),
			'message' => 'Reply posted.',
		);
	}

	private static function transition( int $id, string $action ): string {
		$comment = self::load( $id );
		if ( ! current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
			throw new \Exception( 'No permission to moderate this comment.' );
		}
		switch ( $action ) {
			case 'approve':
				$ok = wp_set_comment_status( $id, 'approve' );
				break;
			case 'unapprove':
				$ok = wp_set_comment_status( $id, 'hold' );
				break;
			case 'spam':
				$ok = wp_spam_comment( $id );
				break;
			case 'unspam':
				$ok = wp_unspam_comment( $id );
				break;
			case 'trash':
				$ok = wp_trash_comment( $id );
				break;
			case 'untrash':
				$ok = wp_untrash_comment( $id );
				break;
			default:
				throw new \Exception( 'Unknown moderation action.' );
		}
		if ( ! $ok ) {
			throw new \Exception( 'WordPress did not change this comment (it may already be in that state).' );
		}
		return self::status_of( self::load( $id ) );
	}

	private static function single( array $args, string $action ): array {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			throw new \Exception( 'moderate_comments capability required.' );
		}
		$id = isset( $args['comment_id'] ) ? (int) $args['comment_id'] : 0;
		return array(
			'comment_id' => $id,
			'new_status' => self::transition( $id, $action ),
		);
	}

	private static function bulk( array $args ): array {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			throw new \Exception( 'moderate_comments capability required.' );
		}
		$action = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			throw new \Exception( 'action must be one of: ' . esc_html( implode( ', ', self::ACTIONS ) ) . '.' );
		}
		$ids = isset( $args['ids'] ) && is_array( $args['ids'] ) ? array_values( array_unique( array_filter( array_map( 'intval', $args['ids'] ) ) ) ) : array();
		if ( ! $ids ) {
			throw new \Exception( 'ids must list at least one comment ID.' );
		}
		if ( count( $ids ) > self::BULK_LIMIT ) {
			throw new \Exception( 'At most ' . (int) self::BULK_LIMIT . ' comments per call.' );
		}

		$changed = array();
		$refused = array();
		foreach ( $ids as $id ) {
			try {
				$changed[ $id ] = self::transition( $id, $action );
			} catch ( \Exception $e ) {
				$refused[ $id ] = $e->getMessage();
			}
		}
		return array(
			'action'  => $action,
			'changed' => (object) $changed,
			'refused' => (object) $refused,
			'summary' => count( $changed ) . ' changed, ' . count( $refused ) . ' refused.',
		);
	}
}
