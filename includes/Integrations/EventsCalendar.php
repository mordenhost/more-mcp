<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventsCalendar {

	public static function is_available() {
		return class_exists( '\Tribe__Events__Main' ) || function_exists( 'tribe_get_events' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'the-events-calendar' ),
			'capabilities' => array( 'events' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'tec_list_events',
				'description' => 'List events from The Events Calendar. Returns id, title, permalink, start/end datetime, all-day flag, and venue/organizer names for each. Filter by a date range and paginate. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array( 'type' => 'string', 'description' => 'Only events starting on/after this date (Y-m-d or a strtotime-parseable string).' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'Only events starting on/before this date.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'How many events (1-100, default 20).' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
						'search'     => array( 'type' => 'string', 'description' => 'Optional keyword search on event title/content.' ),
					),
				),
			),
			array(
				'name'        => 'tec_get_event',
				'description' => 'Get one event by ID with full detail: title, content, permalink, start/end datetime, all-day flag, cost, and hydrated venue (name, address, city) and organizer (names). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Event post ID.' ) ),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read events.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'The Events Calendar is not active.' );
		}
		if ( 'tec_list_events' === $name ) {
			return self::list_events( $args );
		}
		if ( 'tec_get_event' === $name ) {
			return self::get_event( $args );
		}
		throw new \Exception( 'Unknown events tool: ' . esc_html( $name ) );
	}

	private static function list_events( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$query = array(
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);
		if ( ! empty( $args['start_date'] ) ) {
			$query['start_date'] = sanitize_text_field( $args['start_date'] );
		}
		if ( ! empty( $args['end_date'] ) ) {
			$query['end_date'] = sanitize_text_field( $args['end_date'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = sanitize_text_field( $args['search'] );
		}

		$events = function_exists( 'tribe_get_events' ) ? tribe_get_events( $query ) : array();
		$rows   = array();
		if ( is_array( $events ) ) {
			foreach ( $events as $e ) {
				$id = is_object( $e ) ? (int) ( $e->ID ?? 0 ) : (int) $e;
				if ( $id > 0 ) {
					$rows[] = self::event_summary( $id );
				}
			}
		}
		return array(
			'provider' => 'the-events-calendar',
			'page'     => $page,
			'per_page' => $per_page,
			'count'    => count( $rows ),
			'events'   => $rows,
		);
	}

	private static function get_event( $args ) {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'A valid event id is required.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			throw new \Exception( 'Event not found: ' . intval( $id ) );
		}
		$summary = self::event_summary( $id );
		$summary['content'] = (string) ( $post->post_content ?? '' );
		if ( function_exists( 'tribe_get_cost' ) ) {
			$summary['cost'] = (string) tribe_get_cost( $id );
		}
		$summary['venue']      = self::venue_detail( $id );
		$summary['organizers'] = self::organizer_names( $id );
		return $summary;
	}

	private static function event_summary( $id ) {
		return array(
			'id'         => $id,
			'title'      => get_the_title( $id ),
			'permalink'  => get_permalink( $id ),
			'start_date' => function_exists( 'tribe_get_start_date' ) ? tribe_get_start_date( $id, false, 'Y-m-d H:i:s' ) : null,
			'end_date'   => function_exists( 'tribe_get_end_date' ) ? tribe_get_end_date( $id, false, 'Y-m-d H:i:s' ) : null,
			'all_day'    => function_exists( 'tribe_event_is_all_day' ) ? (bool) tribe_event_is_all_day( $id ) : null,
			'venue'      => function_exists( 'tribe_get_venue' ) ? ( tribe_get_venue( $id ) ?: null ) : null,
		);
	}

	private static function venue_detail( $id ) {
		if ( ! function_exists( 'tribe_get_venue_id' ) ) {
			return null;
		}
		$venue_id = (int) tribe_get_venue_id( $id );
		if ( $venue_id <= 0 ) {
			return null;
		}
		return array(
			'id'      => $venue_id,
			'name'    => function_exists( 'tribe_get_venue' ) ? tribe_get_venue( $id ) : get_the_title( $venue_id ),
			'address' => function_exists( 'tribe_get_address' ) ? ( tribe_get_address( $venue_id ) ?: null ) : null,
			'city'    => function_exists( 'tribe_get_city' ) ? ( tribe_get_city( $venue_id ) ?: null ) : null,
		);
	}

	private static function organizer_names( $id ) {
		if ( ! function_exists( 'tribe_get_organizer_ids' ) ) {
			return array();
		}
		$ids = tribe_get_organizer_ids( $id );
		$out = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $oid ) {
				$oid = (int) $oid;
				if ( $oid > 0 ) {
					$out[] = array( 'id' => $oid, 'name' => get_the_title( $oid ) );
				}
			}
		}
		return $out;
	}
}
