<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UltimateMember {

	const ACCOUNT_STATUSES = array(
		'approved'                    => 'Approved',
		'awaiting_admin_review'       => 'Pending administrator review',
		'awaiting_email_confirmation' => 'Waiting email confirmation',
		'inactive'                    => 'Membership inactive',
		'rejected'                    => 'Membership rejected',
	);

	const PENDING_STATUSES = array( 'awaiting_admin_review', 'awaiting_email_confirmation' );

	const UNASSIGNED = 'unassigned';

	const REGISTRATION_STATUSES = array(
		'approved'  => 'Auto Approve',
		'checkmail' => 'Require Email Activation',
		'pending'   => 'Require Admin Review',
	);

	const FORM_MODES = array( 'register', 'login', 'profile' );

	const ROLE_PERMISSIONS = array(
		'can_access_wpadmin',
		'can_not_see_adminbar',
		'can_edit_everyone',
		'can_delete_everyone',
		'can_edit_profile',
		'can_delete_profile',
		'can_view_all',
		'can_make_private_profile',
		'can_access_private_profile',
	);

	const MAX_FIELDS = 100;

	public static function is_available() {
		return defined( 'UM_VERSION' ) && function_exists( 'UM' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'ultimate-member' ),
			'capabilities' => array( 'membership' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'um_get_status',
				'description' => 'Read Ultimate Member health: the plugin version, member counts with a breakdown by account status (approved, awaiting_admin_review, awaiting_email_confirmation, inactive, rejected, and unassigned for users with no status yet) plus the pending-queue total (admin review + email confirmation), the number of roles and how many Ultimate Member created, published form counts by mode (register/login/profile), and the published member-directory count. Returns aggregate counts only, never a user record, id, name, or email, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'um_get_roles',
				'description' => 'Read the Ultimate Member role catalogue (UM\'s membership levels): for each role its id, name, whether Ultimate Member created it, its UM priority, its registration status (approved = auto approve, checkmail = email activation, pending = admin review), and the UM permission flags (wp-admin access, admin bar, edit/delete everyone, edit/delete own profile, view profiles, private profiles), plus the site-wide default registration role. Role definitions only — never a member, and never a role\'s redirect URLs or custom messages. Read-only diagnostic; cannot create, edit, or delete a role.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'um_get_members_by_role',
				'description' => 'Read Ultimate Member member counts per role, each split by account status (approved, awaiting_admin_review, awaiting_email_confirmation, inactive, rejected, unassigned) with a pending total, plus the site-wide totals and the count of users with no role. A user holding several roles is counted under each. Aggregate counts only — never a user record, id, login, name, or email. Read-only diagnostic; cannot approve, reject, or change any member.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'Optional role id (for example subscriber or um_member) to report only that role. Must be an existing role.',
						),
					),
				),
			),
			array(
				'name'        => 'um_get_forms',
				'description' => 'Read the Ultimate Member form catalogue (published um_form posts): for each form its id, title, mode (register/login/profile), whether it is the default form for its mode, its field definitions (meta key, field type, required flag), and for registration forms the role it assigns and that role\'s registration status. Form definitions only — never a submission, user, or field value. Read-only diagnostic; cannot create, edit, or delete a form.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'mode'  => array(
							'type'        => 'string',
							'enum'        => self::FORM_MODES,
							'description' => 'Optional: only list forms of this mode.',
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum forms to list (1-100). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use membership tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Ultimate Member is not active.' );
		}
		$args = is_array( $args ) ? $args : array();

		if ( 'um_get_roles' === $name ) {
			return self::get_roles();
		}
		if ( 'um_get_members_by_role' === $name ) {
			return self::get_members_by_role( $args );
		}
		if ( 'um_get_forms' === $name ) {
			return self::get_forms( $args );
		}
		if ( 'um_get_status' !== $name ) {
			throw new \Exception( 'Unknown Ultimate Member tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function um() {
		$um = UM();
		if ( ! is_object( $um ) || ! method_exists( $um, 'roles' ) ) {
			return null;
		}
		return $um;
	}

	private static function api_missing() {
		return array(
			'provider'  => 'ultimate-member',
			'available' => false,
			'message'   => 'The Ultimate Member roles API was not found; cannot read on this version.',
		);
	}

	private static function roles( $um ) {
		$roles = $um->roles()->get_roles();
		if ( ! is_array( $roles ) ) {
			return array();
		}
		$out = array();
		foreach ( $roles as $id => $role_name ) {
			if ( ! is_string( $id ) || '' === $id ) {
				continue;
			}
			$out[ $id ] = (string) $role_name;
		}
		return $out;
	}

	private static function status_labels( $um ) {
		$labels = self::ACCOUNT_STATUSES;
		if ( method_exists( $um, 'common' ) ) {
			$common = $um->common();
			if ( is_object( $common ) && method_exists( $common, 'users' ) ) {
				$users = $common->users();
				if ( is_object( $users ) && method_exists( $users, 'statuses_list' ) ) {
					$list = $users->statuses_list();
					if ( is_array( $list ) ) {
						foreach ( $list as $key => $label ) {
							$key = sanitize_key( (string) $key );
							if ( '' !== $key && is_scalar( $label ) ) {
								$labels[ $key ] = (string) $label;
							}
						}
					}
				}
			}
		}
		$labels[ self::UNASSIGNED ] = 'No status set';
		return $labels;
	}

	private static function member_rows( array $role_ids ) {
		global $wpdb;
		$columns = '';
		$params  = array();
		foreach ( array_values( $role_ids ) as $i => $role_id ) {
			$columns .= "COUNT(NULLIF(c.meta_value LIKE %s, false)) AS r{$i}, ";
			$params[] = '%' . $wpdb->esc_like( '"' . $role_id . '"' ) . '%';
		}
		$params[] = $wpdb->get_blog_prefix() . 'capabilities';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Core table names; one %s per role pattern plus the meta key, all bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.meta_value AS k, {$columns}COUNT(NULLIF(c.meta_value = 'a:0:{}', false)) AS r_none, COUNT(*) AS n FROM {$wpdb->usermeta} c INNER JOIN {$wpdb->users} u ON u.ID = c.user_id LEFT JOIN {$wpdb->usermeta} s ON s.user_id = c.user_id AND s.meta_key = 'account_status' WHERE c.meta_key = %s GROUP BY s.meta_value", $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	private static function status_key( $raw ) {
		$raw = null === $raw ? '' : (string) $raw;
		$key = '' === $raw ? '' : sanitize_key( $raw );
		return '' === $key ? self::UNASSIGNED : $key;
	}

	private static function pending_total( array $by_status ) {
		$pending = 0;
		foreach ( self::PENDING_STATUSES as $status ) {
			$pending += isset( $by_status[ $status ] ) ? (int) $by_status[ $status ] : 0;
		}
		return $pending;
	}

	private static function fold_members( array $rows, array $role_ids ) {
		$role_ids = array_values( $role_ids );
		$all      = array();
		$total    = 0;
		$per_role = array_fill_keys( $role_ids, array() );
		$no_role  = array();

		foreach ( $rows as $row ) {
			$key = self::status_key( isset( $row['k'] ) ? $row['k'] : null );
			$n   = isset( $row['n'] ) ? (int) $row['n'] : 0;

			$all[ $key ] = ( isset( $all[ $key ] ) ? $all[ $key ] : 0 ) + $n;
			$total      += $n;

			foreach ( $role_ids as $i => $role_id ) {
				$count = isset( $row[ 'r' . $i ] ) ? (int) $row[ 'r' . $i ] : 0;
				if ( $count > 0 ) {
					$per_role[ $role_id ][ $key ] = ( isset( $per_role[ $role_id ][ $key ] ) ? $per_role[ $role_id ][ $key ] : 0 ) + $count;
				}
			}

			$none = isset( $row['r_none'] ) ? (int) $row['r_none'] : 0;
			if ( $none > 0 ) {
				$no_role[ $key ] = ( isset( $no_role[ $key ] ) ? $no_role[ $key ] : 0 ) + $none;
			}
		}

		return array(
			'total'     => $total,
			'by_status' => $all,
			'per_role'  => $per_role,
			'no_role'   => $no_role,
		);
	}

	private static function count_block( array $by_status ) {
		return array(
			'total'     => array_sum( $by_status ),
			'by_status' => $by_status,
			'pending'   => self::pending_total( $by_status ),
		);
	}

	private static function form_mode_counts() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table names; the post type is bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value AS k, COUNT(*) AS n FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_um_mode' WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY m.meta_value", 'um_form' ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return null;
		}
		$by    = array();
		$total = 0;
		foreach ( $rows as $row ) {
			$raw  = isset( $row['k'] ) ? (string) $row['k'] : '';
			$key  = '' === $raw ? 'unspecified' : sanitize_key( $raw );
			$key  = '' === $key ? 'unspecified' : $key;
			$n    = isset( $row['n'] ) ? (int) $row['n'] : 0;
			$by[ $key ] = ( isset( $by[ $key ] ) ? $by[ $key ] : 0 ) + $n;
			$total     += $n;
		}
		return array(
			'total'   => $total,
			'by_mode' => $by,
		);
	}

	private static function published_count( $post_type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name; the post type is bound by prepare().
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type ) );
	}

	private static function default_registration_role( $um, array $roles ) {
		$role = (string) get_option( 'default_role' );
		if ( method_exists( $um, 'options' ) ) {
			$um_role = $um->options()->get( 'register_role' );
			if ( is_string( $um_role ) && '' !== $um_role && isset( $roles[ $um_role ] ) ) {
				$role = $um_role;
			}
		}
		return $role;
	}

	private static function registration_status( $um, $role_id ) {
		$data = $um->roles()->role_data( $role_id );
		if ( is_array( $data ) && isset( $data['status'] ) && is_string( $data['status'] ) && isset( self::REGISTRATION_STATUSES[ $data['status'] ] ) ) {
			return $data['status'];
		}
		return null;
	}

	private static function get_status() {
		$um = self::um();
		if ( null === $um ) {
			return self::api_missing();
		}

		$roles   = self::roles( $um );
		$members = self::fold_members( self::member_rows( array() ), array() );

		$custom = 0;
		foreach ( array_keys( $roles ) as $role_id ) {
			if ( $um->roles()->is_role_custom( $role_id ) ) {
				$custom++;
			}
		}

		$result = array(
			'provider'      => 'ultimate-member',
			'available'     => true,
			'version'       => (string) UM_VERSION,
			'members'       => self::count_block( $members['by_status'] ),
			'status_labels' => self::status_labels( $um ),
			'roles'         => array(
				'total'  => count( $roles ),
				'custom' => $custom,
			),
		);

		$forms = self::form_mode_counts();
		if ( null !== $forms ) {
			$result['forms'] = $forms;
		}
		$result['member_directories'] = array( 'total' => self::published_count( 'um_directory' ) );

		return $result;
	}

	private static function get_roles() {
		$um = self::um();
		if ( null === $um ) {
			return self::api_missing();
		}

		$roles = self::roles( $um );
		$list  = array();
		foreach ( $roles as $role_id => $role_name ) {
			$data   = $um->roles()->role_data( $role_id );
			$data   = is_array( $data ) ? $data : array();
			$status = self::registration_status( $um, $role_id );

			$permissions = array();
			foreach ( self::ROLE_PERMISSIONS as $flag ) {
				$permissions[ $flag ] = ! empty( $data[ $flag ] );
			}

			$list[] = array(
				'id'                        => $role_id,
				'name'                      => $role_name,
				'um_custom'                 => (bool) $um->roles()->is_role_custom( $role_id ),
				'um_configured'             => ! empty( $data ),
				'priority'                  => isset( $data['priority'] ) ? (int) $data['priority'] : 0,
				'registration_status'       => $status,
				'registration_status_label' => null !== $status ? self::REGISTRATION_STATUSES[ $status ] : null,
				'permissions'               => $permissions,
			);
		}

		return array(
			'provider'                  => 'ultimate-member',
			'available'                 => true,
			'default_registration_role' => self::default_registration_role( $um, $roles ),
			'roles'                     => $list,
			'count'                     => count( $list ),
		);
	}

	private static function get_members_by_role( array $args ) {
		$um = self::um();
		if ( null === $um ) {
			return self::api_missing();
		}

		$roles  = self::roles( $um );
		$filter = isset( $args['role'] ) && is_string( $args['role'] ) ? trim( $args['role'] ) : '';
		if ( '' !== $filter && ! isset( $roles[ $filter ] ) ) {
			throw new \Exception( 'Unknown role: ' . esc_html( $filter ) . '. Use um_get_roles to list the role ids.' );
		}
		$role_ids = '' !== $filter ? array( $filter ) : array_keys( $roles );

		$members = self::fold_members( self::member_rows( $role_ids ), $role_ids );

		$by_role = array();
		foreach ( $role_ids as $role_id ) {
			$block     = self::count_block( $members['per_role'][ $role_id ] );
			$by_role[] = array(
				'role'      => $role_id,
				'name'      => $roles[ $role_id ],
				'total'     => $block['total'],
				'by_status' => $block['by_status'],
				'pending'   => $block['pending'],
			);
		}

		$result = array(
			'provider'      => 'ultimate-member',
			'available'     => true,
			'roles'         => $by_role,
			'status_labels' => self::status_labels( $um ),
			'note'          => 'A user holding several roles is counted under each of them, as WordPress core count_users() does, so per-role totals can add up to more than the member total.',
		);
		if ( '' === $filter ) {
			$result['members'] = self::count_block( $members['by_status'] );
			$result['no_role'] = self::count_block( $members['no_role'] );
		}

		return $result;
	}

	private static function get_forms( array $args ) {
		$um = self::um();
		if ( null === $um ) {
			return self::api_missing();
		}

		$mode = isset( $args['mode'] ) && is_string( $args['mode'] ) ? sanitize_key( $args['mode'] ) : '';
		if ( '' !== $mode && ! in_array( $mode, self::FORM_MODES, true ) ) {
			throw new \Exception( 'Unknown form mode: ' . esc_html( $mode ) . '. Expected register, login, or profile.' );
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}

		$query = array(
			'post_type'        => 'um_form',
			'post_status'      => 'publish',
			'numberposts'      => $limit,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);
		if ( '' !== $mode ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded list of a private post type; _um_mode is how UM itself filters forms.
			$query['meta_key']   = '_um_mode';
			$query['meta_value'] = $mode; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$posts = get_posts( $query );

		$core_forms = get_option( 'um_core_forms', array() );
		$core_forms = is_array( $core_forms ) ? $core_forms : array();
		$can_assign = method_exists( $um, 'form' ) && method_exists( $um->form(), 'assigned_role' );

		$forms = array();
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( ! is_object( $post ) || empty( $post->ID ) ) {
					continue;
				}
				$id        = (int) $post->ID;
				$form_mode = sanitize_key( (string) get_post_meta( $id, '_um_mode', true ) );

				$entry = array(
					'id'               => $id,
					'title'            => isset( $post->post_title ) ? (string) $post->post_title : '',
					'mode'             => $form_mode,
					'default_for_mode' => '' !== $form_mode && isset( $core_forms[ $form_mode ] ) && (int) $core_forms[ $form_mode ] === $id,
				);

				$fields          = self::form_fields( get_post_meta( $id, '_um_custom_fields', true ) );
				$entry['fields'] = $fields['list'];
				$entry['field_count'] = $fields['count'];

				if ( 'register' === $form_mode && $can_assign ) {
					$role                         = (string) $um->form()->assigned_role( $id );
					$entry['custom_settings']     = (bool) get_post_meta( $id, '_um_register_use_custom_settings', true );
					$entry['assigned_role']       = $role;
					$entry['registration_status'] = '' !== $role ? self::registration_status( $um, $role ) : null;
				}

				$forms[] = $entry;
			}
		}

		return array(
			'provider'  => 'ultimate-member',
			'available' => true,
			'forms'     => $forms,
			'count'     => count( $forms ),
		);
	}

	private static function form_fields( $raw ) {
		$list  = array();
		$count = 0;
		if ( is_array( $raw ) ) {
			foreach ( $raw as $key => $field ) {
				$type = is_array( $field ) && isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : '';
				if ( 'row' === $type ) {
					continue;
				}
				$count++;
				if ( count( $list ) >= self::MAX_FIELDS ) {
					continue;
				}
				$list[] = array(
					'key'      => (string) $key,
					'type'     => $type,
					'required' => is_array( $field ) && ! empty( $field['required'] ),
				);
			}
		}
		return array(
			'list'  => $list,
			'count' => $count,
		);
	}
}
