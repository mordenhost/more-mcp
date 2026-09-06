<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amelia {

	const CATEGORIES_TABLE   = 'categories';
	const SERVICES_TABLE     = 'services';
	const APPOINTMENTS_TABLE = 'appointments';
	const EVENTS_TABLE       = 'events';
	const USERS_TABLE        = 'users';

	public static function is_available() {
		if ( defined( 'AMELIA_VERSION' ) || class_exists( '\AmeliaBooking\Plugin' ) ) {
			return true;
		}

		
		return self::table_exists( self::SERVICES_TABLE );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'amelia' ),
			'capabilities' => array( 'booking' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'amelia_get_status',
				'description' => 'Read Amelia booking scale: counts of service categories, services (by status), employees, appointments (by status: approved/pending/canceled/rejected/no-show), and group events (by status). Aggregate counts only — never a customer name, email, appointment participant, time slot, or payment record — and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'amelia_get_service_catalog',
				'description' => 'Read the Amelia bookable catalogue: service categories, each with the services inside it (name, price, duration in seconds, min/max capacity, and status). Returns the DEFINITIONS of what can be booked only — never appointments, customers, availability slots, or bookings. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use booking tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Amelia is not active.' );
		}

		if ( 'amelia_get_service_catalog' === $name ) {
			return self::get_service_catalog();
		}
		if ( 'amelia_get_status' !== $name ) {
			throw new \Exception( 'Unknown booking tool: ' . esc_html( $name ) );
		}

		return array(
			'provider'               => 'amelia',
			'categories_total'       => self::count_rows( self::CATEGORIES_TABLE ),
			'services_by_status'     => self::count_by_status( self::SERVICES_TABLE, array( 'visible', 'hidden', 'disabled' ) ),
			'services_total'         => self::count_rows( self::SERVICES_TABLE ),
			'employees_total'        => self::count_employees(),
			'appointments_by_status' => self::count_by_status( self::APPOINTMENTS_TABLE, array( 'approved', 'pending', 'canceled', 'rejected', 'no-show' ) ),
			'appointments_total'     => self::count_rows( self::APPOINTMENTS_TABLE ),
			'events_by_status'       => self::count_by_status( self::EVENTS_TABLE, array( 'approved', 'pending', 'canceled', 'rejected' ) ),
			'events_total'           => self::count_rows( self::EVENTS_TABLE ),
		);
	}

	private static function get_service_catalog() {
		$categories = self::read_categories();
		$services   = self::read_services();

		$by_category = array();
		$ungrouped   = array();
		foreach ( $services as $svc ) {
			$cid = (int) $svc['category_id'];
			if ( $cid > 0 && isset( $categories[ $cid ] ) ) {
				$by_category[ $cid ][] = $svc;
			} else {
				$ungrouped[] = $svc;
			}
		}

		$out = array();
		foreach ( $categories as $cid => $cat ) {
			$items = isset( $by_category[ $cid ] ) ? $by_category[ $cid ] : array();
			$out[] = array(
				'category_id'   => $cid,
				'name'          => $cat['name'],
				'status'        => $cat['status'],
				'position'      => $cat['position'],
				'service_count' => count( $items ),
				'services'      => $items,
			);
		}

		return array(
			'provider'           => 'amelia',
			'available'          => true,
			'category_count'     => count( $out ),
			'categories'         => $out,
			'ungrouped_services' => $ungrouped,
			'services_total'     => count( $services ),
		);
	}

	private static function read_categories() {
		global $wpdb;
		$out = array();
		if ( ! self::table_exists( self::CATEGORIES_TABLE ) ) {
			return $out;
		}
		$table = self::table_name( self::CATEGORIES_TABLE );

		$rows = $wpdb->get_results( "SELECT id, name, status, position FROM {$table} ORDER BY position ASC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$out[ $id ] = array(
				'name'     => (string) $row['name'],
				'status'   => (string) $row['status'],
				'position' => (int) $row['position'],
			);
		}
		return $out;
	}

	private static function read_services() {
		global $wpdb;
		$out = array();
		if ( ! self::table_exists( self::SERVICES_TABLE ) ) {
			return $out;
		}
		$table = self::table_name( self::SERVICES_TABLE );
		$rows  = $wpdb->get_results(
			"SELECT id, name, price, status, categoryId, minCapacity, maxCapacity, duration, position FROM {$table} ORDER BY position ASC, id ASC",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$out[] = array(
				'service_id'       => (int) $row['id'],
				'name'             => (string) $row['name'],
				'price'            => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
				'status'           => (string) $row['status'],
				'category_id'      => (int) $row['categoryId'],
				'min_capacity'     => (int) $row['minCapacity'],
				'max_capacity'     => (int) $row['maxCapacity'],
				'duration_seconds' => (int) $row['duration'],
				'position'         => (int) $row['position'],
			);
		}
		return $out;
	}

	private static function count_rows( $table_key ) {
		global $wpdb;
		if ( ! self::table_exists( $table_key ) ) {
			return null;
		}
		$table = self::table_name( $table_key );
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return null === $count ? null : (int) $count;
	}

	private static function count_by_status( $table_key, array $expected ) {
		global $wpdb;
		if ( ! self::table_exists( $table_key ) ) {
			return null;
		}
		$out = array();
		foreach ( $expected as $status ) {
			$out[ $status ] = 0;
		}
		$table = self::table_name( $table_key );
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = (string) $row['status'];
				if ( '' === $status ) {
					continue;
				}
				$out[ $status ] = (int) $row['n'];
			}
		}
		return $out;
	}

	private static function count_employees() {
		global $wpdb;
		if ( ! self::table_exists( self::USERS_TABLE ) ) {
			return null;
		}
		$table = self::table_name( self::USERS_TABLE );
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE type IN ( %s, %s, %s )",
				'provider',
				'manager',
				'admin'
			)
		);
		return null === $count ? null : (int) $count;
	}

	private static function table_name( $table_key ) {
		global $wpdb;
		return $wpdb->prefix . 'amelia_' . $table_key;
	}

	private static function table_exists( $table_key ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$table = self::table_name( $table_key );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
