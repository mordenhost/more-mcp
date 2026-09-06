<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBPress {

	const STAT_KEYS = array(
		'forum_count_int'           => 'forums',
		'topic_count_int'           => 'topics',
		'topic_count_hidden_int'    => 'topics_non_public',
		'reply_count_int'           => 'replies',
		'reply_count_hidden_int'    => 'replies_non_public',
		'topic_tag_count_int'       => 'topic_tags',
		'empty_topic_tag_count_int' => 'topic_tags_empty',
		'user_count_int'            => 'users',
	);

	public static function is_available() {

		
		return class_exists( 'bbPress' ) && function_exists( 'bbp_get_statistics' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'bbpress' ),
			'capabilities' => array( 'community' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'bbp_get_status',
				'description' => 'Read bbPress community health: the number of forums, topics (published and non-public), replies (published and non-public), topic tags, and registered users, read through bbPress\'s own statistics aggregate. Returns site-wide counts only, never a topic or reply, a post body, an author identity, or a subscriber list, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bbp_get_forums',
				'description' => 'Read the bbPress forum structure: for each forum its id, title, status (open/closed), visibility (public/private/hidden), parent forum id, topic count, and reply count. Returns forum structure and counts only — never a topic, reply, post body, author identity, or moderator assignment. Read-only diagnostic; cannot create, edit, move, or close a forum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum forums to list (1-200). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use community tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'bbPress is not active.' );
		}

		if ( 'bbp_get_forums' === $name ) {
			return self::get_forums( $args );
		}
		if ( 'bbp_get_status' !== $name ) {
			throw new \Exception( 'Unknown bbPress tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function get_status() {
		$stats = bbp_get_statistics();
		if ( ! is_array( $stats ) ) {
			return array(
				'provider'  => 'bbpress',
				'available' => false,
				'message'   => 'bbPress returned no statistics; cannot read counts on this version.',
			);
		}

		$counts = array();
		foreach ( self::STAT_KEYS as $stat_key => $field ) {
			if ( isset( $stats[ $stat_key ] ) ) {
				$counts[ $field ] = (int) $stats[ $stat_key ];
			}
		}

		if ( empty( $counts ) ) {
			return array(
				'provider'  => 'bbpress',
				'available' => false,
				'message'   => 'bbPress statistics carried none of the expected integer tallies on this version.',
			);
		}

		return array(
			'provider'  => 'bbpress',
			'available' => true,
			'counts'    => $counts,
		);
	}

	private static function forum_row( $post ) {
		$forum_id = (int) $post->ID;

		$row = array(
			'id'    => $forum_id,
			'title' => (string) $post->post_title,
		);

		if ( function_exists( 'bbp_get_forum_status' ) ) {
			$row['status'] = (string) bbp_get_forum_status( $forum_id );
		}
		if ( function_exists( 'bbp_get_forum_visibility' ) ) {
			$row['visibility'] = (string) bbp_get_forum_visibility( $forum_id );
		}
		if ( function_exists( 'bbp_get_forum_parent_id' ) ) {
			$row['parent_id'] = (int) bbp_get_forum_parent_id( $forum_id );
		}

		if ( function_exists( 'bbp_get_forum_topic_count' ) ) {
			$row['topic_count'] = (int) bbp_get_forum_topic_count( $forum_id, true, true );
		}
		if ( function_exists( 'bbp_get_forum_reply_count' ) ) {
			$row['reply_count'] = (int) bbp_get_forum_reply_count( $forum_id, true, true );
		}

		return $row;
	}

	private static function get_forums( $args ) {
		if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
			return array(
				'provider'  => 'bbpress',
				'available' => false,
				'message'   => 'The bbPress forum post type accessor was not found.',
			);
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}

		$posts = get_posts(
			array(
				'post_type'        => bbp_get_forum_post_type(),
				'post_status'      => 'any',
				'numberposts'      => $limit,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'suppress_filters' => false,
			)
		);

		$forums = array();
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$forums[] = self::forum_row( $post );
			}
		}

		return array(
			'provider'  => 'bbpress',
			'available' => true,
			'forums'    => $forums,
			'count'     => count( $forums ),
		);
	}
}
