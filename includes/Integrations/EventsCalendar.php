<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventsCalendar {

	const VENUE_CPT = 'tribe_venue';

	const ORGANIZER_CPT = 'tribe_organizer';

	const CATEGORY_TAX = 'tribe_events_cat';

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
			array(
				'name'        => 'tec_list_venues',
				'description' => 'List venue definitions from The Events Calendar. Returns id, title, address, and city for each venue. Read-only; never a venue phone number or any contact field.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'How many venues (1-100, default 50).' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'tec_list_organizers',
				'description' => 'List organizer definitions from The Events Calendar. Returns id and title only. Read-only; never an organizer email, phone, or website.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'How many organizers (1-100, default 50).' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'tec_list_event_categories',
				'description' => 'List event-category terms from The Events Calendar (tribe_events_cat taxonomy). Returns term id, name, slug, and event count for each. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'How many categories (1-100, default 50).' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
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
		if ( 'tec_list_venues' === $name ) {
			return self::list_venues( $args );
		}
		if ( 'tec_list_organizers' === $name ) {
			return self::list_organizers( $args );
		}
		if ( 'tec_list_event_categories' === $name ) {
			return self::list_event_categories( $args );
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

	const VENUE_META_ADDRESS = '_VenueAddress';
	const VENUE_META_CITY    = '_VenueCity';

	private static function list_venues( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$venues = array();
		if ( function_exists( 'tribe_get_venues' ) ) {

			
			
			$result = tribe_get_venues( null, $per_page * $page, true );
			if ( is_array( $result ) ) {
				
				$venues = array_slice( $result, ( $page - 1 ) * $per_page, $per_page );
			}
		} elseif ( function_exists( 'get_posts' ) ) {
			$posts  = get_posts( array(
				'post_type'      => self::VENUE_CPT,
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
			$venues = is_array( $posts ) ? $posts : array();
		}

		$rows = array();
		foreach ( $venues as $v ) {
			$id = is_object( $v ) ? (int) ( $v->ID ?? 0 ) : (int) $v;
			if ( $id <= 0 ) {
				continue;
			}
			$address = function_exists( 'get_post_meta' ) ? get_post_meta( $id, self::VENUE_META_ADDRESS, true ) : '';
			$city    = function_exists( 'get_post_meta' ) ? get_post_meta( $id, self::VENUE_META_CITY, true ) : '';
			$rows[]  = array(
				'id'      => $id,
				'title'   => function_exists( 'get_the_title' ) ? get_the_title( $id ) : '',
				'address' => ( '' === $address || null === $address ) ? null : (string) $address,
				'city'    => ( '' === $city || null === $city ) ? null : (string) $city,
			);
		}
		return array(
			'provider' => 'the-events-calendar',
			'page'     => $page,
			'per_page' => $per_page,
			'count'    => count( $rows ),
			'venues'   => $rows,
		);
	}

	private static function list_organizers( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$organizers = array();
		if ( function_exists( 'tribe_get_organizers' ) ) {
			$result = tribe_get_organizers( null, $per_page * $page, true );
			if ( is_array( $result ) ) {
				$organizers = array_slice( $result, ( $page - 1 ) * $per_page, $per_page );
			}
		}

		$rows = array();
		foreach ( $organizers as $o ) {
			$id = is_object( $o ) ? (int) ( $o->ID ?? 0 ) : (int) $o;
			if ( $id > 0 ) {
				$rows[] = array(
					'id'    => $id,
					'title' => function_exists( 'get_the_title' ) ? get_the_title( $id ) : '',
				);
			}
		}
		return array(
			'provider'  => 'the-events-calendar',
			'page'      => $page,
			'per_page'  => $per_page,
			'count'     => count( $rows ),
			'organizers' => $rows,
		);
	}

	private static function list_event_categories( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array(
				'provider'   => 'the-events-calendar',
				'page'       => $page,
				'per_page'   => $per_page,
				'count'      => 0,
				'categories' => array(),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::CATEGORY_TAX,
				'hide_empty' => false,
				'number'     => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
			)
		);
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) || ! is_array( $terms ) ) {
			$terms = array();
		}

		$rows = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
				continue;
			}
			$rows[] = array(
				'id'    => (int) $term->term_id,
				'name'  => isset( $term->name ) ? (string) $term->name : '',
				'slug'  => isset( $term->slug ) ? (string) $term->slug : '',
				'count' => isset( $term->count ) ? (int) $term->count : 0,
			);
		}
		return array(
			'provider'   => 'the-events-calendar',
			'page'       => $page,
			'per_page'   => $per_page,
			'count'      => count( $rows ),
			'categories' => $rows,
		);
	}
}
