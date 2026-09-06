<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookly {

	const CATEGORIES_TABLE    = 'bookly_categories';
	const SERVICES_TABLE      = 'bookly_services';
	const STAFF_TABLE         = 'bookly_staff';
	const APPOINTMENTS_TABLE  = 'bookly_appointments';
	const CUSTOMER_APPTS_TABLE = 'bookly_customer_appointments';
	const CUSTOMERS_TABLE     = 'bookly_customers';

	public static function is_available() {

		
		if ( class_exists( '\Bookly\Lib\Plugin' ) ) {
			return true;
		}
		return self::table_exists( self::SERVICES_TABLE );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'bookly' ),
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
				'name'        => 'bookly_get_status',
				'description' => 'Read Bookly booking scale: counts of service categories, services (total and by visibility: public/private/group), staff members (total and by visibility), scheduled appointment slots, per-customer bookings by status (pending/approved/cancelled/rejected/waitlisted/done), and total customers. Aggregate counts only — never a customer name, email, phone, appointment participant, time slot, or payment record — and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bookly_get_service_catalog',
				'description' => 'Read the Bookly bookable catalogue: service categories, each with the services inside it (title, type, price, duration in seconds and whole minutes, min/max capacity, and visibility). Returns the DEFINITIONS of what can be booked only — never appointments, customers, staff schedules, or availability slots. Read-only; cannot modify the catalogue.',
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
			throw new \Exception( 'Bookly is not active.' );
		}

		if ( 'bookly_get_service_catalog' === $name ) {
			return self::get_service_catalog();
		}
		if ( 'bookly_get_status' !== $name ) {
			throw new \Exception( 'Unknown booking tool: ' . esc_html( $name ) );
		}

		return array(
			'provider'                    => 'bookly',
			'categories_total'            => self::count_rows( self::CATEGORIES_TABLE ),
			'services_total'              => self::count_rows( self::SERVICES_TABLE ),
			'services_by_visibility'      => self::count_by_column( self::SERVICES_TABLE, 'visibility', array( 'public', 'private', 'group' ) ),
			'staff_total'                 => self::count_rows( self::STAFF_TABLE ),
			'staff_by_visibility'         => self::count_by_column( self::STAFF_TABLE, 'visibility', array( 'public', 'private' ) ),
			'appointments_total'          => self::count_rows( self::APPOINTMENTS_TABLE ),
			'bookings_by_status'          => self::count_by_column( self::CUSTOMER_APPTS_TABLE, 'status', array( 'pending', 'approved', 'cancelled', 'rejected', 'waitlisted', 'done' ) ),
			'bookings_total'              => self::count_rows( self::CUSTOMER_APPTS_TABLE ),
			'customers_total'             => self::count_rows( self::CUSTOMERS_TABLE ),
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
				'position'      => $cat['position'],
				'service_count' => count( $items ),
				'services'      => $items,
			);
		}

		return array(
			'provider'           => 'bookly',
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

		$rows = $wpdb->get_results( "SELECT id, name, position FROM {$table} ORDER BY position ASC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$out[ $id ] = array(
				'name'     => (string) $row['name'],
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
			"SELECT id, category_id, type, title, duration, price, capacity_min, capacity_max, visibility, position FROM {$table} ORDER BY position ASC, id ASC",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$duration_seconds = (int) $row['duration'];
			$out[] = array(
				'service_id'       => (int) $row['id'],
				'category_id'      => (int) $row['category_id'],
				'type'             => (string) $row['type'],
				'title'            => (string) $row['title'],
				'price'            => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
				'duration_seconds' => $duration_seconds,
				'duration_minutes' => (int) floor( $duration_seconds / 60 ),
				'capacity_min'     => (int) $row['capacity_min'],
				'capacity_max'     => (int) $row['capacity_max'],
				'visibility'       => (string) $row['visibility'],
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

	private static function count_by_column( $table_key, $column, array $expected ) {
		global $wpdb;
		if ( ! self::table_exists( $table_key ) ) {
			return null;
		}
		$out = array();
		foreach ( $expected as $value ) {
			$out[ $value ] = 0;
		}
		$table = self::table_name( $table_key );

		$rows = $wpdb->get_results( "SELECT {$column} AS v, COUNT(*) AS n FROM {$table} GROUP BY {$column}", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$value = (string) $row['v'];
				if ( '' === $value ) {
					continue;
				}
				$out[ $value ] = (int) $row['n'];
			}
		}
		return $out;
	}

	private static function table_name( $table_key ) {
		global $wpdb;
		return $wpdb->prefix . $table_key;
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
