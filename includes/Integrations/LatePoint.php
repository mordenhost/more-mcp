<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePoint {

	const CATEGORIES_TABLE = 'service_categories';
	const SERVICES_TABLE   = 'services';
	const BOOKINGS_TABLE   = 'bookings';
	const AGENTS_TABLE     = 'agents';
	const LOCATIONS_TABLE  = 'locations';
	const CUSTOMERS_TABLE  = 'customers';

	public static function is_available() {
		if ( defined( 'LATEPOINT_VERSION' ) ) {
			return true;
		}

		
		return self::table_exists( self::SERVICES_TABLE );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'latepoint' ),
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
				'name'        => 'latepoint_get_status',
				'description' => 'Read LatePoint booking scale: counts of service categories, services (by status: active/disabled), agents (by status), and bookings (by status: approved/pending/payment_pending/cancelled/no_show/completed). Aggregate counts only — never a customer name, email, booking participant, time slot, or payment record — and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'latepoint_get_service_catalog',
				'description' => 'Read the LatePoint bookable catalogue: service categories, each with the services inside it (name, price range, duration in minutes, min/max capacity, and status). Returns the DEFINITIONS of what can be booked only — never bookings, customers, availability slots, or agents\' schedules. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'latepoint_get_agent_catalog',
				'description' => 'Read the LatePoint agent catalogue: booking agents (id, first name, last name, status: active/disabled), ordered by id, at most 100 per call. Returns the DEFINITIONS of who can take bookings only — the agents table\'s email/phone/password columns are never selected. Customer and booking data is never returned. Read-only; cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum agents returned (1-100). Default 50.',
						),
					),
				),
			),
			array(
				'name'        => 'latepoint_get_location_catalog',
				'description' => 'Read the LatePoint location catalogue: booking locations (id, name, full address, status, order number), ordered by order number, at most 100 per call. Returns the DEFINITIONS of where bookings happen only — an address of a business location, never a person. Customer and booking data is never returned. Read-only; cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum locations returned (1-100). Default 50.',
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
			throw new \Exception( 'LatePoint is not active.' );
		}

		if ( 'latepoint_get_service_catalog' === $name ) {
			return self::get_service_catalog();
		}
		if ( 'latepoint_get_agent_catalog' === $name ) {
			return self::get_agent_catalog( $args );
		}
		if ( 'latepoint_get_location_catalog' === $name ) {
			return self::get_location_catalog( $args );
		}
		if ( 'latepoint_get_status' !== $name ) {
			throw new \Exception( 'Unknown booking tool: ' . esc_html( $name ) );
		}

		return array(
			'provider'            => 'latepoint',
			'categories_total'    => self::count_rows( self::CATEGORIES_TABLE ),
			'services_by_status'  => self::count_by_status( self::SERVICES_TABLE, array( 'active', 'disabled' ) ),
			'services_total'      => self::count_rows( self::SERVICES_TABLE ),
			'agents_by_status'    => self::count_by_status( self::AGENTS_TABLE, array( 'active', 'disabled' ) ),
			'agents_total'        => self::count_rows( self::AGENTS_TABLE ),
			'bookings_by_status'  => self::count_by_status( self::BOOKINGS_TABLE, array( 'approved', 'pending', 'payment_pending', 'cancelled', 'no_show', 'completed' ) ),
			'bookings_total'      => self::count_rows( self::BOOKINGS_TABLE ),
			'customers_total'     => self::count_rows( self::CUSTOMERS_TABLE ),
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
				'parent_id'     => $cat['parent_id'],
				'order_number'  => $cat['order_number'],
				'service_count' => count( $items ),
				'services'      => $items,
			);
		}

		return array(
			'provider'           => 'latepoint',
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

		$rows = $wpdb->get_results( "SELECT id, name, parent_id, order_number FROM {$table} ORDER BY order_number ASC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$out[ $id ] = array(
				'name'         => (string) $row['name'],
				'parent_id'    => (int) $row['parent_id'],
				'order_number' => (int) $row['order_number'],
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
			"SELECT id, name, price_min, price_max, charge_amount, duration, category_id, capacity_min, capacity_max, status, visibility, order_number FROM {$table} ORDER BY order_number ASC, id ASC",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$out[] = array(
				'service_id'       => (int) $row['id'],
				'name'             => (string) $row['name'],
				'price_min'        => isset( $row['price_min'] ) ? (float) $row['price_min'] : 0.0,
				'price_max'        => isset( $row['price_max'] ) ? (float) $row['price_max'] : 0.0,
				'charge_amount'    => isset( $row['charge_amount'] ) ? (float) $row['charge_amount'] : 0.0,
				'duration_minutes' => (int) $row['duration'],
				'category_id'      => (int) $row['category_id'],
				'capacity_min'     => (int) $row['capacity_min'],
				'capacity_max'     => (int) $row['capacity_max'],
				'status'           => (string) $row['status'],
				'visibility'       => (string) $row['visibility'],
				'order_number'     => (int) $row['order_number'],
			);
		}
		return $out;
	}

	private static function get_agent_catalog( $args ) {
		global $wpdb;
		$out = array();
		if ( ! self::table_exists( self::AGENTS_TABLE ) ) {
			return array(
				'provider'  => 'latepoint',
				'available' => false,
				'total'     => 0,
				'agents'    => $out,
			);
		}
		$limit = self::clamp_limit( $args );
		$table = self::table_name( self::AGENTS_TABLE );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, first_name, last_name, status FROM {$table} ORDER BY id ASC LIMIT %d", $limit ),
			ARRAY_A
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'agent_id'   => (int) $row['id'],
					'first_name' => (string) $row['first_name'],
					'last_name'  => (string) $row['last_name'],
					'status'     => (string) $row['status'],
				);
			}
		}
		return array(
			'provider'  => 'latepoint',
			'available' => true,
			'total'     => count( $out ),
			'agents'    => $out,
		);
	}

	private static function get_location_catalog( $args ) {
		global $wpdb;
		$out = array();
		if ( ! self::table_exists( self::LOCATIONS_TABLE ) ) {
			return array(
				'provider'   => 'latepoint',
				'available'  => false,
				'total'      => 0,
				'locations'  => $out,
			);
		}
		$limit = self::clamp_limit( $args );
		$table = self::table_name( self::LOCATIONS_TABLE );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, name, full_address, status, order_number FROM {$table} ORDER BY order_number ASC, id ASC LIMIT %d", $limit ),
			ARRAY_A
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = array(
					'location_id'  => (int) $row['id'],
					'name'         => (string) $row['name'],
					'full_address' => (string) $row['full_address'],
					'status'       => (string) $row['status'],
					'order_number' => (int) $row['order_number'],
				);
			}
		}
		return array(
			'provider'   => 'latepoint',
			'available'  => true,
			'total'      => count( $out ),
			'locations'  => $out,
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

	private static function table_name( $table_key ) {
		global $wpdb;
		return $wpdb->prefix . 'latepoint_' . $table_key;
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
