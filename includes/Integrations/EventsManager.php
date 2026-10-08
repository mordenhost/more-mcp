<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventsManager {

	const EVENT_CPT = 'event';

	const CATEGORY_TAX = 'event-categories';

	public static function is_available() {
		if ( class_exists( '\EM_Events' ) || function_exists( 'em_get_events' ) || defined( 'EM_VERSION' ) ) {
			return true;
		}

		if ( function_exists( 'post_type_exists' ) && function_exists( 'taxonomy_exists' ) ) {
			return post_type_exists( self::EVENT_CPT ) && taxonomy_exists( self::CATEGORY_TAX );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'events-manager' ),
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
				'name'        => 'em_list_events',
				'description' => 'List events from Events Manager. Returns event id, linked post id, title, slug, status, start/end datetime, all-day flag, timezone, recurring flag, category names, and permalink for each. Filter by a date range, category, and keyword; paginate; and choose ordering. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array( 'type' => 'string', 'description' => 'Only events on/after this date (Y-m-d). Combined with end_date to form a date-range scope.' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'Only events on/before this date (Y-m-d).' ),
						'category'   => array( 'type' => 'string', 'description' => 'Filter by an event category slug or numeric term id.' ),
						'search'     => array( 'type' => 'string', 'description' => 'Optional keyword search on event name.' ),
						'orderby'    => array( 'type' => 'string', 'description' => 'One of: event_start_date, event_start_time, event_name, event_id. Default event_start_date.' ),
						'order'      => array( 'type' => 'string', 'description' => 'ASC or DESC. Default ASC.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'How many events (1-100, default 20).' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'em_get_event',
				'description' => 'Get one Events Manager event by its EM event id, with full detail: title, slug, content, status, start/end datetime, all-day flag, timezone, recurring flag, category names, permalink, and created/modified timestamps. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'Events Manager event id (event_id, not the WordPress post id).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'em_list_locations',
				'description' => 'List location definitions from Events Manager. Returns location id, name, address, town, and country for each. Read-only; never the location owner or any booking/attendee data.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'How many locations (1-100, default 50).' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'em_list_event_categories',
				'description' => 'List event-category terms from Events Manager (event-categories taxonomy). Returns term id, name, slug, and event count for each. Read-only.',
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
			throw new \Exception( 'Events Manager is not active.' );
		}
		if ( 'em_list_events' === $name ) {
			return self::list_events( $args );
		}
		if ( 'em_get_event' === $name ) {
			return self::get_event( $args );
		}
		if ( 'em_list_locations' === $name ) {
			return self::list_locations( $args );
		}
		if ( 'em_list_event_categories' === $name ) {
			return self::list_event_categories( $args );
		}
		throw new \Exception( 'Unknown events tool: ' . esc_html( $name ) );
	}

	private static function allowed_orderby(): array {
		return array( 'event_start_date', 'event_start_time', 'event_name', 'event_id' );
	}

	private static function list_events( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], self::allowed_orderby(), true )
			? $args['orderby']
			: 'event_start_date';
		$order = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';

		$query = array(
			'limit'      => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'pagination' => false,
			'array'      => false,
			'orderby'    => $orderby,
			'order'      => $order,
			'status'     => 1, 
		);

		
		
		$start = isset( $args['start_date'] ) ? sanitize_text_field( (string) $args['start_date'] ) : '';
		$end   = isset( $args['end_date'] ) ? sanitize_text_field( (string) $args['end_date'] ) : '';
		if ( '' !== $start || '' !== $end ) {
			$query['scope'] = $start . ',' . $end;
		} else {
			$query['scope'] = 'all';
		}

		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field( (string) $args['search'] );
		}
		if ( isset( $args['category'] ) && '' !== $args['category'] ) {
			
			$query['category'] = sanitize_text_field( (string) $args['category'] );
		}

		$events = array();
		if ( class_exists( '\EM_Events' ) && method_exists( '\EM_Events', 'get' ) ) {
			$result = \EM_Events::get( $query );
			if ( is_array( $result ) ) {
				$events = $result;
			}
		}

		$rows = array();
		foreach ( $events as $event ) {
			if ( is_object( $event ) ) {
				$rows[] = self::event_summary( $event );
			}
		}

		return array(
			'provider' => 'events-manager',
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
		if ( ! function_exists( 'em_get_event' ) ) {
			throw new \Exception( 'Events Manager event API is unavailable.' );
		}

		$event = em_get_event( $id, 'event_id' );
		$resolved_id = ( is_object( $event ) && method_exists( $event, 'get_event_id' ) ) ? (int) $event->get_event_id() : 0;
		if ( ! is_object( $event ) || $resolved_id <= 0 ) {
			throw new \Exception( 'Event not found: ' . intval( $id ) );
		}

		$summary = self::event_summary( $event );

		
		$summary['content'] = isset( $event->post_content ) ? (string) $event->post_content : '';

		
		
		$created = self::safe_prop( $event, 'event_date_created' );
		if ( null !== $created ) {
			$summary['date_created'] = (string) $created;
		}
		$modified = self::safe_prop( $event, 'event_date_modified' );
		if ( null !== $modified ) {
			$summary['date_modified'] = (string) $modified;
		}

		return $summary;
	}

	private static function event_summary( $event ) {
		$event_id = ( method_exists( $event, 'get_event_id' ) ) ? (int) $event->get_event_id() : (int) self::safe_prop( $event, 'event_id' );
		$post_id  = (int) self::safe_prop( $event, 'post_id' );

		$title = self::safe_prop( $event, 'event_name' );
		if ( ( null === $title || '' === $title ) && $post_id > 0 ) {
			$title = get_the_title( $post_id );
		}

		return array(
			'id'         => $event_id,
			'post_id'    => $post_id > 0 ? $post_id : null,
			'title'      => (string) $title,
			'slug'       => (string) self::safe_prop( $event, 'event_slug' ),
			'status'     => self::event_status( $event ),
			'start'      => self::event_datetime( $event, 'start' ),
			'end'        => self::event_datetime( $event, 'end' ),
			'all_day'    => (bool) self::safe_prop( $event, 'event_all_day' ),
			'timezone'   => self::event_timezone( $event ),
			'recurring'  => method_exists( $event, 'is_recurring' ) ? (bool) $event->is_recurring() : null,
			'permalink'  => method_exists( $event, 'get_permalink' ) ? (string) $event->get_permalink() : ( $post_id > 0 ? (string) get_permalink( $post_id ) : null ),
			'categories' => $post_id > 0 ? self::category_names( $post_id ) : array(),
		);
	}

	private static function event_status( $event ) {
		if ( method_exists( $event, 'get_status' ) ) {
			$status = $event->get_status();
			return ( null === $status ) ? null : (int) $status;
		}
		$raw = self::safe_prop( $event, 'event_status' );
		return ( null === $raw ) ? null : (int) $raw;
	}

	private static function event_datetime( $event, $which ) {
		if ( ! method_exists( $event, $which ) ) {
			return null;
		}
		$dt = $event->{$which}();
		if ( is_object( $dt ) && method_exists( $dt, 'format' ) ) {
			$formatted = $dt->format( 'Y-m-d H:i:s' );
			return ( '' === $formatted ) ? null : (string) $formatted;
		}
		return null;
	}

	private static function event_timezone( $event ) {
		if ( method_exists( $event, 'get_timezone' ) ) {
			$tz = $event->get_timezone();
			if ( is_object( $tz ) && method_exists( $tz, 'getName' ) ) {
				return (string) $tz->getName();
			}
		}
		$raw = self::safe_prop( $event, 'event_timezone' );
		return ( null === $raw ) ? null : (string) $raw;
	}

	private static function category_names( $post_id ) {
		if ( ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array();
		}
		$names = wp_get_object_terms( (int) $post_id, self::CATEGORY_TAX, array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! is_array( $names ) ) {
			return array();
		}
		return array_values( array_map( 'strval', $names ) );
	}

	private static function safe_prop( $event, $prop ) {
		if ( ! is_object( $event ) ) {
			return null;
		}
		$value = @$event->{$prop};
		return ( null === $value ) ? null : $value;
	}

	private static function list_locations( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$locations = array();
		if ( class_exists( '\EM_Locations' ) && method_exists( '\EM_Locations', 'get' ) ) {
			$result = \EM_Locations::get( array(
				'limit'      => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
				'pagination' => false,
				'array'      => true,
			) );
			if ( is_array( $result ) ) {
				$locations = $result;
			}
		}

		$rows = array();
		foreach ( $locations as $loc ) {
			if ( ! is_object( $loc ) ) {
				continue;
			}
			$id = (int) self::safe_prop( $loc, 'location_id' );
			if ( $id <= 0 ) {
				continue;
			}
			$rows[] = array(
				'id'      => $id,
				'name'    => (string) self::safe_prop( $loc, 'location_name' ),
				'address' => (string) self::safe_prop( $loc, 'location_address' ),
				'town'    => (string) self::safe_prop( $loc, 'location_town' ),
				'country' => (string) self::safe_prop( $loc, 'location_country' ),
				'status'  => (int) self::safe_prop( $loc, 'location_status' ),
			);
		}
		return array(
			'provider'  => 'events-manager',
			'page'      => $page,
			'per_page'  => $per_page,
			'count'     => count( $rows ),
			'locations' => $rows,
		);
	}

	private static function list_event_categories( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array(
				'provider'   => 'events-manager',
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
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
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
			'provider'   => 'events-manager',
			'page'       => $page,
			'per_page'   => $per_page,
			'count'      => count( $rows ),
			'categories' => $rows,
		);
	}
}
