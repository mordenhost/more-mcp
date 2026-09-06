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
