<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleMembership {

	const DURATION_TYPES = array(
		0 => 'No Expiry',
		1 => 'Days',
		2 => 'Weeks',
		3 => 'Months',
		4 => 'Years',
		5 => 'Fixed Date',
		6 => 'Annual Fixed Date',
	);

	public static function is_available() {
		return defined( 'SIMPLE_WP_MEMBERSHIP_VER' ) || class_exists( 'SimpleWpMembership' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'swpm' ),
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
				'name'        => 'swpm_get_status',
				'description' => 'Read Simple Membership health: the number of membership levels and member counts with a breakdown by account state (active, inactive, activation_required, expired, pending, unsubscribed). Returns aggregate counts only, never a member record, name, email, or any per-member detail, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'swpm_get_levels',
				'description' => 'Read the Simple Membership level catalogue: for each level its id, name, the WordPress role it grants, and its duration terms (No Expiry / Days / Weeks / Months / Years / Fixed Date / Annual Fixed Date, plus the period). Returns level definitions (access configuration) only — never a member, and never the level\'s content-protection lists or options. The reserved system level (id 1, "Content Protection") is excluded unless include_reserved is true. Read-only diagnostic; cannot create, edit, or delete a level.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_reserved' => array(
							'type'        => 'boolean',
							'description' => 'When true, include the reserved system level (id 1, "Content Protection") that the plugin\'s own levels screen hides. Default false.',
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
			throw new \Exception( 'Simple Membership is not active.' );
		}

		if ( 'swpm_get_levels' === $name ) {
			return self::get_levels( $args );
		}
		if ( 'swpm_get_status' !== $name ) {
			throw new \Exception( 'Unknown Simple Membership tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function grouped_counts( $table, $column ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$table} GROUP BY {$column}", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$raw   = isset( $row['k'] ) ? (string) $row['k'] : '';
				$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$key   = '' === $raw ? 'unspecified' : sanitize_key( $raw );
				$by[ $key ] = $count;
				$total     += $count;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function level_total( $include_reserved ) {
		global $wpdb;
		$table = self::table( 'swpm_membership_tbl' );
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		$where = $include_reserved ? '' : 'WHERE id != 1';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
	}

	private static function get_status() {
		$levels  = self::level_total( false );
		$members = self::grouped_counts( self::table( 'swpm_members_tbl' ), 'account_state' );

		if ( null === $levels && null === $members ) {
			return array(
				'provider'  => 'swpm',
				'available' => false,
				'message'   => 'Simple Membership tables were not found; cannot read counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'swpm',
			'available' => true,
		);

		if ( null !== $levels ) {
			$result['levels'] = array( 'total' => $levels );
		}
		if ( null !== $members ) {
			$result['members'] = array(
				'total'     => $members['total'],
				'by_status' => $members['by'],
			);
		}

		return $result;
	}

	private static function get_levels( $args ) {
		global $wpdb;
		$table = self::table( 'swpm_membership_tbl' );

		if ( ! self::table_exists( $table ) ) {
			return array(
				'provider'  => 'swpm',
				'available' => false,
				'message'   => 'The Simple Membership levels table was not found.',
			);
		}

		$include_reserved = ! empty( $args['include_reserved'] );
		$where            = $include_reserved ? '' : 'WHERE id != 1';

		
		
		$rows = $wpdb->get_results(
			"SELECT id, alias, role, subscription_period, subscription_duration_type, subscription_unit FROM {$table} {$where} ORDER BY id",
			ARRAY_A
		);

		$levels = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$type_code = isset( $row['subscription_duration_type'] ) ? (int) $row['subscription_duration_type'] : 0;
				$levels[]  = array(
					'id'                  => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'                => isset( $row['alias'] ) ? (string) $row['alias'] : '',
					'role'                => isset( $row['role'] ) ? (string) $row['role'] : '',
					'duration_type'       => self::DURATION_TYPES[ $type_code ] ?? 'unknown',
					'subscription_period' => isset( $row['subscription_period'] ) ? (string) $row['subscription_period'] : '',
				);
			}
		}

		return array(
			'provider'  => 'swpm',
			'available' => true,
			'levels'    => $levels,
			'count'     => count( $levels ),
		);
	}
}
