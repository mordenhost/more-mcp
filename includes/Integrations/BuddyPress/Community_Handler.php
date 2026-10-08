<?php

namespace More_MCP\Integrations\BuddyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Community_Handler {

	const NAMES = array(
		'bp_list_members', 'bp_get_member', 'bp_get_group', 'bp_list_group_members',
		'bp_list_activity', 'bp_create_activity', 'bp_delete_activity',
		'bp_list_message_threads', 'bp_get_message_thread',
	);

	const MAX_CONTENT = 10000;

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		$paging = array(
			'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (1-100, default 20)' ),
			'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed' ),
		);
		return array(
			array(
				'name'        => 'bp_list_members',
				'description' => 'List BuddyPress members: id, username, display name, registration date, last activity and profile URL. Never returns email addresses. Needs list_users.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						$paging,
						array(
							'search' => array( 'type' => 'string', 'description' => 'Match on username, display name or profile fields' ),
							'type'   => array( 'type' => 'string', 'enum' => array( 'alphabetical', 'newest', 'active', 'random', 'popular' ), 'description' => 'Sort order (default alphabetical, which lists every member; the other orders only include members BuddyPress has recorded activity for)' ),
						)
					),
				),
			),
			array(
				'name'        => 'bp_get_member',
				'description' => 'Get one BuddyPress member by user ID or username: account basics, profile (xprofile) fields with their visibility level, and group and friend counts. No email address. Needs list_users, except for your own profile.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'User ID' ),
						'username' => array( 'type' => 'string', 'description' => 'Username (used when id is not given)' ),
					),
				),
			),
			array(
				'name'        => 'bp_get_group',
				'description' => 'Get one BuddyPress group by ID or slug: name, description, status, parent, creator, creation date, member count, and its administrators and moderators. Private and hidden groups are visible to their members and to moderators only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer', 'description' => 'Group ID' ),
						'slug' => array( 'type' => 'string', 'description' => 'Group slug (used when id is not given)' ),
					),
				),
			),
			array(
				'name'        => 'bp_list_group_members',
				'description' => 'List the members of a BuddyPress group with their role in it (admin, mod, member). Banned members are left out unless include_banned is set. Private and hidden groups are visible to their members and to moderators only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						array( 'group_id' => array( 'type' => 'integer', 'description' => 'Group ID' ) ),
						$paging,
						array(
							'role'           => array( 'type' => 'string', 'enum' => array( 'admin', 'mod', 'member' ), 'description' => 'Only members with this role in the group' ),
							'search'         => array( 'type' => 'string' ),
							'include_banned' => array( 'type' => 'boolean', 'description' => 'Include banned members (moderators only)' ),
						)
					),
					'required'   => array( 'group_id' ),
				),
			),
			array(
				'name'        => 'bp_list_activity',
				'description' => 'List BuddyPress activity-stream items, newest first: author, type, text, date and the group or object it belongs to. Activity in private and hidden groups is included for moderators only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						$paging,
						array(
							'component' => array( 'type' => 'string', 'description' => 'Only this component: activity, groups, friends, xprofile, blogs...' ),
							'type'      => array( 'type' => 'string', 'description' => 'Only this activity type, e.g. activity_update, activity_comment, new_member, joined_group' ),
							'user_id'   => array( 'type' => 'integer', 'description' => 'Only items by this user' ),
							'group_id'  => array( 'type' => 'integer', 'description' => 'Only items in this group' ),
							'search'    => array( 'type' => 'string', 'description' => 'Match on the item text' ),
							'order'     => array( 'type' => 'string', 'enum' => array( 'DESC', 'ASC' ), 'description' => 'Default DESC (newest first)' ),
						)
					),
				),
			),
			array(
				'name'        => 'bp_create_activity',
				'description' => 'Post to the BuddyPress activity stream as the connected user: a status update, a post in a group (group_id), or a reply to an activity item (parent_id). Posting as another user needs moderator rights, and whoever posts into a group must be a member of it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'content'   => array( 'type' => 'string', 'description' => 'Text of the post' ),
						'group_id'  => array( 'type' => 'integer', 'description' => 'Post into this group instead of the site-wide stream' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'Reply to this activity item' ),
						'user_id'   => array( 'type' => 'integer', 'description' => 'Post as this user (moderators only; default the connected user)' ),
					),
					'required'   => array( 'content' ),
				),
			),
			array(
				'name'        => 'bp_delete_activity',
				'description' => 'Delete a BuddyPress activity item (and the replies under it). You can delete your own items; deleting someone else\'s needs moderator rights.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'Activity item ID' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'bp_list_message_threads',
				'description' => 'List the connected user\'s own private-message threads: subject, last message, participants and unread count. Other members\' messages are never readable through this tool.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge(
						$paging,
						array(
							'box'    => array( 'type' => 'string', 'enum' => array( 'inbox', 'sentbox' ), 'description' => 'Default inbox' ),
							'unread' => array( 'type' => 'boolean', 'description' => 'Only threads with unread messages' ),
							'search' => array( 'type' => 'string', 'description' => 'Match on subject or message text' ),
						)
					),
				),
			),
			array(
				'name'        => 'bp_get_message_thread',
				'description' => 'Read one of the connected user\'s own private-message threads with every message in it. A thread the user is not part of is refused. Reading does not mark the thread read.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
					),
					'required'   => array( 'thread_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		if ( ! is_user_logged_in() ) {
			throw new \Exception( 'You do not have permission to use community tools.' );
		}
		if ( ! function_exists( 'buddypress' ) || ! class_exists( 'BuddyPress' ) ) {
			throw new \Exception( 'BuddyPress is not active.' );
		}

		switch ( $name ) {
			case 'bp_list_members':
				return self::list_members( $args );
			case 'bp_get_member':
				return self::get_member( $args );
			case 'bp_get_group':
				return self::get_group( $args );
			case 'bp_list_group_members':
				return self::list_group_members( $args );
			case 'bp_list_activity':
				return self::list_activity( $args );
			case 'bp_create_activity':
				return self::create_activity( $args );
			case 'bp_delete_activity':
				return self::delete_activity( $args );
			case 'bp_list_message_threads':
				return self::list_message_threads( $args );
			case 'bp_get_message_thread':
				return self::get_message_thread( $args );
		}
		throw new \Exception( 'Unknown BuddyPress tool: ' . esc_html( $name ) );
	}

	

	private static function is_moderator(): bool {
		return current_user_can( 'bp_moderate' ) || current_user_can( 'manage_options' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- bp_moderate is a BuddyPress capability.
	}

	private static function require_component( string $component ): void {
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( $component ) ) {
			throw new \Exception( 'The BuddyPress ' . esc_html( ucfirst( $component ) ) . ' component is not active on this site.' );
		}
	}

	private static function paging( array $args, int $default = 20 ): array {
		return array(
			max( 1, min( 100, (int) ( $args['per_page'] ?? $default ) ) ),
			max( 1, (int) ( $args['page'] ?? 1 ) ),
		);
	}

	private static function user_row( $user_id, $user = null ): array {
		$user_id = (int) $user_id;
		$user    = $user ? $user : get_userdata( $user_id );
		return array(
			'id'           => $user_id,
			'username'     => $user ? (string) $user->user_login : '',
			'display_name' => $user ? (string) $user->display_name : '',
		);
	}

	private static function profile_url( int $user_id ): string {
		return function_exists( 'bp_core_get_user_domain' ) ? (string) bp_core_get_user_domain( $user_id ) : '';
	}

	private static function last_activity( int $user_id ) {
		if ( ! function_exists( 'bp_get_user_last_activity' ) ) {
			return null;
		}
		$value = bp_get_user_last_activity( $user_id );
		return $value ? (string) $value : null;
	}

	private static function visible_group( array $args ) {
		self::require_component( 'groups' );
		$id = (int) ( $args['id'] ?? $args['group_id'] ?? 0 );
		if ( $id <= 0 && ! empty( $args['slug'] ) ) {
			$id = (int) groups_get_id( sanitize_title( (string) $args['slug'] ) );
		}
		if ( $id <= 0 ) {
			throw new \Exception( 'id or slug is required.' );
		}
		$group = groups_get_group( $id );
		if ( ! $group || empty( $group->id ) ) {
			throw new \Exception( 'Group not found' );
		}
		if ( 'public' !== $group->status && ! self::is_moderator() && ! groups_is_user_member( get_current_user_id(), (int) $group->id ) ) {
			
			throw new \Exception( 'Group not found' );
		}
		return $group;
	}

	private static function member_group_ids( int $user_id ): array {
		if ( $user_id <= 0 || ! function_exists( 'groups_get_user_groups' ) || ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			return array();
		}
		$found = groups_get_user_groups( $user_id );
		return array_values( array_filter( array_map( 'intval', (array) ( $found['groups'] ?? array() ) ) ) );
	}

	

	private static function list_members( array $args ): array {
		if ( ! current_user_can( 'list_users' ) ) {
			throw new \Exception( 'list_users capability required.' );
		}
		list( $per_page, $page ) = self::paging( $args );
		$type = (string) ( $args['type'] ?? 'alphabetical' );
		if ( ! in_array( $type, array( 'active', 'newest', 'alphabetical', 'random', 'popular' ), true ) ) {
			throw new \Exception( 'type must be active, newest, alphabetical, random or popular.' );
		}
		$query = new \BP_User_Query(
			array(
				'type'            => $type,
				'per_page'        => $per_page,
				'page'            => $page,
				'search_terms'    => ! empty( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : false,
				'populate_extras' => false,
			)
		);
		$rows = array();
		foreach ( (array) $query->results as $user ) {
			$id     = (int) ( $user->ID ?? 0 );
			$rows[] = array_merge(
				self::user_row( $id, $user ),
				array(
					'registered'    => isset( $user->user_registered ) ? (string) $user->user_registered : null,
					'last_activity' => self::last_activity( $id ),
					'profile_url'   => self::profile_url( $id ),
				)
			);
		}
		return array(
			'members'  => $rows,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->total_users,
		);
	}

	private static function get_member( array $args ): array {
		if ( ! empty( $args['id'] ) ) {
			$user = get_userdata( (int) $args['id'] );
		} elseif ( ! empty( $args['username'] ) ) {
			$user = get_user_by( 'login', sanitize_user( (string) $args['username'] ) );
		} else {
			throw new \Exception( 'id or username is required.' );
		}
		if ( ! $user ) {
			throw new \Exception( 'Member not found' );
		}
		$id = (int) $user->ID;
		if ( get_current_user_id() !== $id && ! current_user_can( 'list_users' ) ) {
			throw new \Exception( 'list_users capability required.' );
		}

		$out = array_merge(
			self::user_row( $id, $user ),
			array(
				'registered'    => (string) $user->user_registered,
				'last_activity' => self::last_activity( $id ),
				'profile_url'   => self::profile_url( $id ),
				'profile'       => array(),
			)
		);

		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'xprofile' ) && function_exists( 'bp_xprofile_get_groups' ) ) {
			foreach ( (array) bp_xprofile_get_groups( array( 'user_id' => $id, 'fetch_fields' => true, 'fetch_field_data' => true ) ) as $group ) {
				foreach ( (array) ( $group->fields ?? array() ) as $field ) {
					$value = isset( $field->data->value ) ? maybe_unserialize( $field->data->value ) : null;
					if ( is_array( $value ) ) {
						$value = array_map( 'wp_strip_all_tags', array_map( 'strval', $value ) );
					} elseif ( null !== $value ) {
						$value = wp_strip_all_tags( (string) $value );
					}
					$out['profile'][] = array(
						'field'      => (string) $field->name,
						'type'       => (string) $field->type,
						'value'      => $value,
						'visibility' => function_exists( 'xprofile_get_field_visibility_level' ) ? (string) xprofile_get_field_visibility_level( (int) $field->id, $id ) : null,
					);
				}
			}
		}
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'groups' ) && function_exists( 'groups_total_groups_for_user' ) ) {
			$out['group_count'] = (int) groups_total_groups_for_user( $id );
		}
		if ( function_exists( 'bp_is_active' ) && bp_is_active( 'friends' ) && function_exists( 'friends_get_total_friend_count' ) ) {
			$out['friend_count'] = (int) friends_get_total_friend_count( $id );
		}
		return $out;
	}

	

	private static function get_group( array $args ): array {
		$group = self::visible_group( $args );
		$gid   = (int) $group->id;

		$people = static function ( $members ) {
			$rows = array();
			foreach ( (array) $members as $member ) {
				$uid = (int) ( $member->user_id ?? $member->ID ?? 0 );
				if ( $uid > 0 ) {
					$rows[] = self::user_row( $uid );
				}
			}
			return $rows;
		};

		return array(
			'id'           => $gid,
			'name'         => (string) $group->name,
			'slug'         => (string) $group->slug,
			'description'  => wp_strip_all_tags( (string) $group->description ),
			'status'       => (string) $group->status,
			'parent_id'    => (int) $group->parent_id,
			'creator'      => self::user_row( (int) $group->creator_id ),
			'date_created' => (string) $group->date_created,
			'member_count' => (int) groups_get_total_member_count( $gid ),
			'admins'       => $people( groups_get_group_admins( $gid ) ),
			'mods'         => $people( groups_get_group_mods( $gid ) ),
		);
	}

	private static function list_group_members( array $args ): array {
		$group = self::visible_group( $args );
		list( $per_page, $page ) = self::paging( $args );

		$roles = array( 'admin', 'mod', 'member' );
		$role  = isset( $args['role'] ) ? (string) $args['role'] : '';
		if ( '' !== $role && ! in_array( $role, $roles, true ) ) {
			throw new \Exception( 'role must be admin, mod or member.' );
		}
		$banned = ! empty( $args['include_banned'] ) && self::is_moderator();

		$result = groups_get_group_members(
			array(
				'group_id'            => (int) $group->id,
				'per_page'            => $per_page,
				'page'                => $page,
				'exclude_admins_mods' => false,
				'exclude_banned'      => ! $banned,
				'group_role'          => '' === $role ? array() : array( $role ),
				'search_terms'        => ! empty( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : false,
			)
		);

		$rows = array();
		foreach ( (array) ( $result['members'] ?? array() ) as $member ) {
			$uid = (int) ( $member->ID ?? $member->user_id ?? 0 );
			if ( $uid <= 0 ) {
				continue;
			}
			$member_role = 'member';
			if ( ! empty( $member->is_admin ) ) {
				$member_role = 'admin';
			} elseif ( ! empty( $member->is_mod ) ) {
				$member_role = 'mod';
			}
			$rows[] = array_merge(
				self::user_row( $uid ),
				array(
					'role'      => $member_role,
					'is_banned' => ! empty( $member->is_banned ),
				)
			);
		}
		return array(
			'group_id' => (int) $group->id,
			'members'  => $rows,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) ( $result['count'] ?? count( $rows ) ),
		);
	}

	

	private static function activity_row( $item ): array {
		return array(
			'id'                => (int) $item->id,
			'component'         => (string) $item->component,
			'type'              => (string) $item->type,
			'user'              => self::user_row( (int) $item->user_id ),
			'action'            => wp_strip_all_tags( (string) $item->action ),
			'content'           => wp_strip_all_tags( (string) $item->content ),
			'date_recorded'     => (string) $item->date_recorded,
			'item_id'           => (int) $item->item_id,
			'secondary_item_id' => (int) $item->secondary_item_id,
			'link'              => (string) $item->primary_link,
			'hide_sitewide'     => (bool) $item->hide_sitewide,
			'is_spam'           => (bool) $item->is_spam,
		);
	}

	private static function list_activity( array $args ): array {
		self::require_component( 'activity' );
		list( $per_page, $page ) = self::paging( $args );

		$filter = array();
		if ( ! empty( $args['component'] ) ) {
			$filter['object'] = sanitize_key( (string) $args['component'] );
		}
		if ( ! empty( $args['type'] ) ) {
			$filter['action'] = sanitize_key( (string) $args['type'] );
		}
		if ( ! empty( $args['user_id'] ) ) {
			$filter['user_id'] = (int) $args['user_id'];
		}
		if ( ! empty( $args['group_id'] ) ) {
			$group = self::visible_group( array( 'id' => (int) $args['group_id'] ) );
			$filter['object']     = 'groups';
			$filter['primary_id'] = (int) $group->id;
		}
		$order = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			throw new \Exception( 'order must be ASC or DESC.' );
		}

		$moderator = self::is_moderator();
		$query     = array(
			'per_page'         => $per_page,
			'page'             => $page,
			'sort'             => $order,
			'filter'           => $filter,
			'search_terms'     => ! empty( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : false,
			'display_comments' => false,
			'show_hidden'      => $moderator,
			'spam'             => $moderator ? 'all' : 'ham_only',
			'count_total'      => true,
		);
		if ( ! $moderator ) {

			
			$mine = self::member_group_ids( get_current_user_id() );
			if ( $mine ) {
				$query['show_hidden']  = true;
				$query['filter_query'] = array(
					'relation' => 'OR',
					array(
						'column' => 'hide_sitewide',
						'value'  => 0,
					),
					array(
						'relation' => 'AND',
						array(
							'column' => 'component',
							'value'  => 'groups',
						),
						array(
							'column'  => 'item_id',
							'compare' => 'IN',
							'value'   => $mine,
						),
					),
				);
			}
		}
		$result = bp_activity_get( $query );

		$rows = array();
		foreach ( (array) ( $result['activities'] ?? array() ) as $item ) {
			$rows[] = self::activity_row( $item );
		}
		return array(
			'activity' => $rows,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) ( $result['total'] ?? count( $rows ) ),
		);
	}

	private static function create_activity( array $args ): array {
		self::require_component( 'activity' );
		$content = trim( (string) ( $args['content'] ?? '' ) );
		if ( '' === $content ) {
			throw new \Exception( 'content is required.' );
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $content ) > self::MAX_CONTENT : strlen( $content ) > self::MAX_CONTENT ) {
			throw new \Exception( esc_html( 'content is too long (limit ' . self::MAX_CONTENT . ' characters).' ) );
		}

		$me      = get_current_user_id();
		$user_id = ! empty( $args['user_id'] ) ? (int) $args['user_id'] : $me;
		if ( $user_id !== $me ) {
			if ( ! self::is_moderator() ) {
				throw new \Exception( 'Posting as another user needs moderator rights.' );
			}
			if ( ! get_userdata( $user_id ) ) {
				throw new \Exception( 'user_id does not match a user.' );
			}
		}

		$parent_id = (int) ( $args['parent_id'] ?? 0 );
		$group_id  = (int) ( $args['group_id'] ?? 0 );

		if ( $parent_id > 0 ) {
			$parent = bp_activity_get_specific( array( 'activity_ids' => $parent_id, 'show_hidden' => true ) );
			$item   = ! empty( $parent['activities'][0] ) ? $parent['activities'][0] : null;
			if ( ! $item ) {
				throw new \Exception( 'Parent activity item not found' );
			}
			if ( 'groups' === $item->component ) {
				if ( ! self::is_moderator() && ! groups_is_user_member( $me, (int) $item->item_id ) ) {
					throw new \Exception( 'Parent activity item not found' );
				}
				if ( ! groups_is_user_member( $user_id, (int) $item->item_id ) ) {
					throw new \Exception( 'The posting user must be a member of this group to reply in it.' );
				}
			}
			$new_id = bp_activity_new_comment(
				array(
					'content'     => $content,
					'user_id'     => $user_id,
					'activity_id' => $parent_id,
					'parent_id'   => $parent_id,
					'skip_notification' => false,
				)
			);
		} elseif ( $group_id > 0 ) {
			self::require_component( 'groups' );
			$group = groups_get_group( $group_id );
			if ( ! $group || empty( $group->id ) ) {
				throw new \Exception( 'Group not found' );
			}
			if ( ! groups_is_user_member( $user_id, $group_id ) ) {
				throw new \Exception( 'The posting user must be a member of this group. A moderator can post as a member by passing that member\'s user_id.' );
			}
			$new_id = groups_post_update(
				array(
					'content'    => $content,
					'user_id'    => $user_id,
					'group_id'   => $group_id,
					'error_type' => 'wp_error',
				)
			);
		} else {
			$new_id = bp_activity_post_update(
				array(
					'content'    => $content,
					'user_id'    => $user_id,
					'error_type' => 'wp_error',
				)
			);
		}

		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( esc_html( $new_id->get_error_message() ) );
		}
		if ( ! $new_id ) {
			throw new \Exception( 'Failed to create the activity item' );
		}
		return array(
			'id'      => (int) $new_id,
			'message' => 'Activity posted successfully',
		);
	}

	private static function delete_activity( array $args ): array {
		self::require_component( 'activity' );
		$id = (int) ( $args['id'] ?? 0 );
		if ( $id <= 0 ) {
			throw new \Exception( 'id is required.' );
		}
		$found = bp_activity_get_specific( array( 'activity_ids' => $id, 'show_hidden' => true, 'spam' => 'all' ) );
		$item  = ! empty( $found['activities'][0] ) ? $found['activities'][0] : null;
		if ( ! $item ) {
			throw new \Exception( 'Activity item not found' );
		}
		if ( get_current_user_id() !== (int) $item->user_id && ! self::is_moderator() ) {
			throw new \Exception( 'You can only delete your own activity items.' );
		}
		if ( 'activity_comment' === $item->type ) {
			$deleted = bp_activity_delete_comment( (int) $item->item_id, $id );
		} else {
			$deleted = bp_activity_delete( array( 'id' => $id ) );
		}
		if ( ! $deleted ) {
			throw new \Exception( 'Failed to delete the activity item' );
		}
		return array(
			'id'      => $id,
			'message' => 'Activity item deleted',
		);
	}

	

	private static function list_message_threads( array $args ): array {
		self::require_component( 'messages' );
		list( $per_page, $page ) = self::paging( $args );
		$box = (string) ( $args['box'] ?? 'inbox' );
		if ( ! in_array( $box, array( 'inbox', 'sentbox' ), true ) ) {
			throw new \Exception( 'box must be inbox or sentbox.' );
		}
		$me = get_current_user_id();

		$result = \BP_Messages_Thread::get_current_threads_for_user(
			array(
				'user_id'           => $me,
				'box'               => $box,
				'type'              => ! empty( $args['unread'] ) ? 'unread' : 'all',
				'limit'             => $per_page,
				'page'              => $page,
				'search_terms'      => ! empty( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
				'messages_per_page' => 1,
				'messages_page'     => 1,
			)
		);

		$rows = array();
		foreach ( (array) ( $result['threads'] ?? array() ) as $thread ) {
			$last = ! empty( $thread->messages ) ? reset( $thread->messages ) : null;
			$rows[] = array(
				'thread_id'      => (int) $thread->thread_id,
				'subject'        => wp_strip_all_tags( (string) ( $last->subject ?? '' ) ),
				'last_message'   => wp_strip_all_tags( (string) ( $last->message ?? '' ) ),
				'last_sender'    => $last ? self::user_row( (int) $last->sender_id ) : null,
				'last_date'      => $last ? (string) $last->date_sent : null,
				'unread_count'   => (int) ( $thread->unread_count ?? 0 ),
				'participants'   => array_values(
					array_map(
						static function ( $uid ) {
							return self::user_row( (int) $uid );
						},
						array_keys( (array) ( $thread->recipients ?? array() ) )
					)
				),
			);
		}
		return array(
			'threads'  => $rows,
			'box'      => $box,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) ( $result['total'] ?? count( $rows ) ),
		);
	}

	private static function get_message_thread( array $args ): array {
		self::require_component( 'messages' );
		$thread_id = (int) ( $args['thread_id'] ?? 0 );
		$me        = get_current_user_id();
		if ( $thread_id <= 0 || ! messages_check_thread_access( $thread_id, $me ) ) {
			
			throw new \Exception( 'Thread not found' );
		}
		$thread = new \BP_Messages_Thread( $thread_id, 'ASC', array( 'update_meta_cache' => false ) );

		$messages = array();
		foreach ( (array) $thread->messages as $message ) {
			$messages[] = array(
				'id'      => (int) $message->id,
				'sender'  => self::user_row( (int) $message->sender_id ),
				'subject' => wp_strip_all_tags( (string) $message->subject ),
				'message' => wp_strip_all_tags( (string) $message->message ),
				'date'    => (string) $message->date_sent,
			);
		}
		$participants = array();
		foreach ( array_keys( (array) $thread->recipients ) as $uid ) {
			$participants[] = self::user_row( (int) $uid );
		}
		return array(
			'thread_id'    => $thread_id,
			'subject'      => $messages ? $messages[0]['subject'] : '',
			'participants' => $participants,
			'messages'     => $messages,
		);
	}
}
