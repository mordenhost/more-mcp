<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BuddyPress {

	const COMPONENT_KEYS = array(
		'members'       => 'members',
		'groups'        => 'groups',
		'activity'      => 'activity',
		'friends'       => 'friends',
		'messages'      => 'messages',
		'notifications' => 'notifications',
		'xprofile'      => 'xprofile',
	);

	public static function is_available() {

		
		return class_exists( 'BuddyPress' ) && function_exists( 'bp_core_get_total_member_count' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'buddypress' ),
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
				'name'        => 'bp_get_status',
				'description' => 'Read BuddyPress community health: the total registered member count, the active member count, the total group count, and which BuddyPress components (members, groups, activity, friends, messages, notifications, profiles) are active, read through BuddyPress\'s own count accessors. Returns site-wide counts and component flags only, never a member profile, a member identity, an activity item, a message, or a group description, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bp_get_groups',
				'description' => 'Read the BuddyPress group structure: for each group its id, name, slug, status (public/private/hidden), parent group id, creation date, and member count. Returns group structure and counts only — never a group description, a group\'s creator or admins/moderators, a member profile, or an activity item. Read-only diagnostic; cannot create, edit, move, or change the privacy of a group.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum groups to list (1-200). Default 50.',
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
			throw new \Exception( 'BuddyPress is not active.' );
		}

		if ( 'bp_get_groups' === $name ) {
			return self::get_groups( $args );
		}
		if ( 'bp_get_status' !== $name ) {
			throw new \Exception( 'Unknown BuddyPress tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function get_status() {
		$counts = array(
			'members_total' => (int) bp_core_get_total_member_count(),
		);

		if ( function_exists( 'bp_core_get_active_member_count' ) ) {
			$counts['members_active'] = (int) bp_core_get_active_member_count();
		}

		$groups_active = function_exists( 'bp_is_active' ) && bp_is_active( 'groups' );
		if ( $groups_active && function_exists( 'groups_get_total_group_count' ) ) {
			$counts['groups_total'] = (int) groups_get_total_group_count();
		}

		$components = array();
		if ( function_exists( 'bp_is_active' ) ) {
			foreach ( self::COMPONENT_KEYS as $component => $field ) {
				$components[ $field ] = (bool) bp_is_active( $component );
			}
		}

		return array(
			'provider'   => 'buddypress',
			'available'  => true,
			'counts'     => $counts,
			'components' => $components,
		);
	}

	private static function group_row( $group ) {
		$row = array(
			'id'   => (int) ( $group->id ?? 0 ),
			'name' => isset( $group->name ) ? (string) $group->name : '',
		);

		if ( isset( $group->slug ) ) {
			$row['slug'] = (string) $group->slug;
		}
		if ( isset( $group->status ) ) {
			$row['status'] = (string) $group->status;
		}
		if ( isset( $group->parent_id ) ) {
			$row['parent_id'] = (int) $group->parent_id;
		}
		if ( isset( $group->date_created ) ) {
			$row['date_created'] = (string) $group->date_created;
		}

		
		if ( is_object( $group ) && isset( $group->total_member_count ) ) {
			$row['member_count'] = (int) $group->total_member_count;
		}

		return $row;
	}

	private static function get_groups( $args ) {
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'groups' ) ) {
			return array(
				'provider'  => 'buddypress',
				'available' => false,
				'message'   => 'The BuddyPress Groups component is not active on this site.',
			);
		}
		if ( ! function_exists( 'groups_get_groups' ) ) {
			return array(
				'provider'  => 'buddypress',
				'available' => false,
				'message'   => 'The BuddyPress groups_get_groups() accessor was not found.',
			);
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}

		$result = groups_get_groups(
			array(
				'per_page'          => $limit,
				'page'              => 1,
				'show_hidden'       => true,
				'orderby'           => 'date_created',
				'order'             => 'DESC',
				'update_meta_cache' => true,
			)
		);

		$rows  = array();
		$total = 0;
		if ( is_array( $result ) ) {
			$total = isset( $result['total'] ) ? (int) $result['total'] : 0;
			$list  = ( isset( $result['groups'] ) && is_array( $result['groups'] ) ) ? $result['groups'] : array();
			foreach ( $list as $group ) {
				if ( ! is_object( $group ) ) {
					continue;
				}
				$rows[] = self::group_row( $group );
			}
		}

		return array(
			'provider'  => 'buddypress',
			'available' => true,
			'groups'    => $rows,
			'count'     => count( $rows ),
			'total'     => $total,
		);
	}
}
