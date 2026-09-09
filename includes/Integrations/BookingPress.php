<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingPress {

	const CATEGORIES_TABLE   = 'bookingpress_categories';
	const SERVICES_TABLE     = 'bookingpress_services';
	const APPOINTMENTS_TABLE = 'bookingpress_appointment_bookings';
	const CUSTOMERS_TABLE    = 'bookingpress_customers';
	const STAFF_TABLE        = 'bookingpress_staffmembers';
	const SETTINGS_TABLE     = 'bookingpress_settings';

	const STATUS_LABELS = array(
		1 => 'Approved',
		2 => 'Pending',
		3 => 'Cancelled',
		4 => 'Rejected',
	);

	public static function is_available() {

		
		
		if ( defined( 'BOOKINGPRESS_VERSION' ) || class_exists( 'BookingPress' ) ) {
			return true;
		}
		return self::table_exists( self::SERVICES_TABLE );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'bookingpress' ),
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
				'name'        => 'bookingpress_get_status',
				'description' => 'Read BookingPress booking scale: counts of service categories, services, staff members (when the staff module is present), appointment bookings total and by status (Approved/Pending/Cancelled/Rejected), total customers, and the configured currency. Aggregate counts only — never a customer name, email, phone, appointment participant, time slot, note, or payment record — and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bookingpress_get_service_catalog',
				'description' => 'Read the BookingPress bookable catalogue: service categories, each with the services inside it (name, price, duration value + unit [minutes/hours/days] and a derived whole-minute figure). Returns the DEFINITIONS of what can be booked only — never appointments, customers, staff schedules, availability slots, or service descriptions. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'bookingpress_get_staff_catalog',
				'description' => 'Read the BookingPress staff catalogue: staff members (id, first name, last name, status), ordered by id, at most 100 per call. BookingPress keeps staff in the customers table distinguished by a user-type column, so the read is filtered to the staff type and selects name/status columns only — the table\'s email/phone columns are never selected and customer rows (user type 2) are never returned. Customer and booking data is never returned. The table exists on a free install but holds staff rows only when the staff module adds them; the read is safe either way. Read-only; cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum staff members returned (1-100). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use booking tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'BookingPress is not active.' );
		}

		if ( 'bookingpress_get_service_catalog' === $name ) {
			return self::get_service_catalog();
		}
		if ( 'bookingpress_get_staff_catalog' === $name ) {
			return self::get_staff_catalog( $args );
		}
		if ( 'bookingpress_get_status' !== $name ) {
			throw new \Exception( 'Unknown booking tool: ' . esc_html( $name ) );
		}

		$out = array(
			'provider'           => 'bookingpress',
			'categories_total'   => self::count_rows( self::CATEGORIES_TABLE ),
			'services_total'     => self::count_rows( self::SERVICES_TABLE ),
			'appointments_total' => self::count_rows( self::APPOINTMENTS_TABLE ),
			'bookings_by_status' => self::count_by_status(),
			'customers_total'    => self::count_rows( self::CUSTOMERS_TABLE ),
		);

		
		$staff = self::count_rows( self::STAFF_TABLE );
		if ( null !== $staff ) {
			$out['staff_total'] = $staff;
		}

		$currency = self::currency();
		if ( '' !== $currency ) {
			$out['currency'] = $currency;
		}

		return $out;
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
			'provider'           => 'bookingpress',
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

		$rows = $wpdb->get_results( "SELECT bookingpress_category_id, bookingpress_category_name, bookingpress_category_position FROM {$table} ORDER BY bookingpress_category_position ASC, bookingpress_category_id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$id = (int) $row['bookingpress_category_id'];
			$out[ $id ] = array(
				'name'     => (string) $row['bookingpress_category_name'],
				'position' => (int) $row['bookingpress_category_position'],
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
			"SELECT bookingpress_service_id, bookingpress_category_id, bookingpress_service_name, bookingpress_service_price, bookingpress_service_duration_val, bookingpress_service_duration_unit, bookingpress_service_position FROM {$table} ORDER BY bookingpress_service_position ASC, bookingpress_service_id ASC",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$duration_val  = (int) $row['bookingpress_service_duration_val'];
			$duration_unit = (string) $row['bookingpress_service_duration_unit'];
			$out[] = array(
				'service_id'       => (int) $row['bookingpress_service_id'],
				'category_id'      => (int) $row['bookingpress_category_id'],
				'name'             => (string) $row['bookingpress_service_name'],
				'price'            => isset( $row['bookingpress_service_price'] ) ? (float) $row['bookingpress_service_price'] : 0.0,
				'duration_value'   => $duration_val,
				'duration_unit'    => $duration_unit,
				'duration_minutes' => self::duration_to_minutes( $duration_val, $duration_unit ),
				'position'         => (int) $row['bookingpress_service_position'],
			);
		}
		return $out;
	}

	private static function duration_to_minutes( $value, $unit ) {
		switch ( $unit ) {
			case 'h':
				return $value * 60;
			case 'd':
				return $value * 60 * 24;
			case 'm':
			default:
				return $value;
		}
	}

	private static function count_by_status() {
		global $wpdb;
		if ( ! self::table_exists( self::APPOINTMENTS_TABLE ) ) {
			return null;
		}
		$out = array();
		foreach ( self::STATUS_LABELS as $label ) {
			$out[ sanitize_key( $label ) ] = 0;
		}
		$table = self::table_name( self::APPOINTMENTS_TABLE );

		$rows = $wpdb->get_results( "SELECT bookingpress_appointment_status AS v, COUNT(*) AS n FROM {$table} GROUP BY bookingpress_appointment_status", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$code = (int) $row['v'];
				if ( isset( self::STATUS_LABELS[ $code ] ) ) {
					$key         = sanitize_key( self::STATUS_LABELS[ $code ] );
					$out[ $key ] = (int) $row['n'];
				}
			}
		}
		return $out;
	}

	private static function currency() {
		global $wpdb;
		if ( ! self::table_exists( self::SETTINGS_TABLE ) ) {
			return '';
		}
		$table = self::table_name( self::SETTINGS_TABLE );
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_name = %s AND setting_type = %s LIMIT 1",
				'payment_default_currency',
				'payment_setting'
			)
		);
		return null === $value ? '' : (string) $value;
	}

	private static function get_staff_catalog( $args ) {
		global $wpdb;
		$out = array();
		if ( ! self::table_exists( self::CUSTOMERS_TABLE ) ) {
			return array(
				'provider'  => 'bookingpress',
				'available' => false,
				'total'     => 0,
				'staff'     => $out,
			);
		}
		$limit = self::clamp_limit( $args );
		$table = self::table_name( self::CUSTOMERS_TABLE );

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT bookingpress_customer_id, bookingpress_user_firstname, bookingpress_user_lastname, bookingpress_user_status FROM {$table} WHERE bookingpress_user_type = %d ORDER BY bookingpress_customer_id ASC LIMIT %d", 1, $limit ),
			ARRAY_A
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'staff_id'   => (int) $row['bookingpress_customer_id'],
					'first_name' => (string) $row['bookingpress_user_firstname'],
					'last_name'  => (string) $row['bookingpress_user_lastname'],
					'status'     => (int) $row['bookingpress_user_status'],
				);
			}
		}
		return array(
			'provider'  => 'bookingpress',
			'available' => true,
			'total'     => count( $out ),
			'staff'     => $out,
		);
	}

	private static function clamp_limit( $args ) {
		$limit = isset( $args['limit'] ) && is_numeric( $args['limit'] ) ? (int) $args['limit'] : 50;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}
		return $limit;
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
