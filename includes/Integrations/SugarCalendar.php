<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SugarCalendar {

	const EVENT_CPT   = 'sc_event';
	const CALENDAR_TAX = 'sc_event_category';

	public static function is_available() {
		if ( function_exists( 'sugar_calendar_get_events' ) || class_exists( '\Sugar_Calendar\Event_Query' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) ) {
			return post_type_exists( self::EVENT_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'sugar-calendar' ),
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
				'name'        => 'sugarcal_list_events',
				'description' => 'List events from Sugar Calendar. Returns id, linked post id, title, status, start/end datetime (with time zone), all-day flag, recurrence type, and calendar (category) names for each. Filter by a start-date range, status, and keyword; order and paginate. Read-only; cannot create or modify events.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'start_after'  => array( 'type' => 'string', 'description' => 'Only events starting on/after this datetime (Y-m-d or Y-m-d H:i:s).' ),
						'start_before' => array( 'type' => 'string', 'description' => 'Only events starting on/before this datetime (Y-m-d or Y-m-d H:i:s).' ),
						'status'       => array( 'type' => 'string', 'description' => 'Optional event status filter (e.g. "publish", "draft").' ),
						'search'       => array( 'type' => 'string', 'description' => 'Optional keyword search on event title/content.' ),
						'orderby'      => array( 'type' => 'string', 'description' => 'One of: id, title, start_date, end_date, date_created. Default start_date.' ),
						'order'        => array( 'type' => 'string', 'description' => 'ASC or DESC. Default ASC.' ),
						'per_page'     => array( 'type' => 'integer', 'description' => 'How many events (1-100, default 20).' ),
						'page'         => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'sugarcal_get_event',
				'description' => 'Get one Sugar Calendar event by its event ID with full detail: title, content, status, start/end datetime and time zones, all-day flag, recurrence (type, interval, end), created/modified timestamps, the linked post id, and the calendar (category) names it belongs to. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'Sugar Calendar event ID (not the linked post ID).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'sugarcal_list_calendars',
				'description' => 'List Sugar Calendar calendars (sc_event_category taxonomy terms). Returns term id, name, slug, event count, and parent term id — the taxonomy is hierarchical. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'How many calendars (1-100, default 50).' ),
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
			throw new \Exception( 'Sugar Calendar is not active.' );
		}
		if ( 'sugarcal_list_events' === $name ) {
			return self::list_events( $args );
		}
		if ( 'sugarcal_get_event' === $name ) {
			return self::get_event( $args );
		}
		if ( 'sugarcal_list_calendars' === $name ) {
			return self::list_calendars( $args );
		}
		throw new \Exception( 'Unknown events tool: ' . esc_html( $name ) );
	}

	private static function list_events( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$allowed_orderby = array( 'id', 'title', 'start_date', 'end_date', 'date_created' );
		$orderby         = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'start_date';
		$order = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';

		$query = array(
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => $orderby,
			'order'   => $order,
		);
		if ( ! empty( $args['status'] ) ) {
			$query['status'] = sanitize_text_field( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field( $args['search'] );
		}

		$start_clause = array();
		if ( ! empty( $args['start_after'] ) ) {
			$start_clause['after'] = sanitize_text_field( $args['start_after'] );
		}
		if ( ! empty( $args['start_before'] ) ) {
			$start_clause['before'] = sanitize_text_field( $args['start_before'] );
		}
		if ( ! empty( $start_clause ) ) {
			$start_clause['inclusive'] = true;
			$query['start_query']      = array( $start_clause );
		}

		$events = function_exists( 'sugar_calendar_get_events' ) ? sugar_calendar_get_events( $query ) : array();
		$rows   = array();
		if ( is_array( $events ) ) {
			foreach ( $events as $e ) {
				if ( is_object( $e ) ) {
					$rows[] = self::event_summary( $e );
				}
			}
		}
		return array(
			'provider' => 'sugar-calendar',
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
		if ( ! function_exists( 'sugar_calendar_get_event' ) ) {
			throw new \Exception( 'Sugar Calendar event API is unavailable.' );
		}
		$event = sugar_calendar_get_event( $id );
		
		if ( ! is_object( $event ) || empty( $event->id ) ) {
			throw new \Exception( 'Event not found: ' . intval( $id ) );
		}

		$detail                     = self::event_summary( $event );
		$detail['content']          = isset( $event->content ) ? (string) $event->content : '';
		$detail['end_tz']           = isset( $event->end_tz ) ? (string) $event->end_tz : '';
		$detail['recurrence_interval'] = isset( $event->recurrence_interval ) ? (int) $event->recurrence_interval : 0;
		$detail['recurrence_end']   = isset( $event->recurrence_end ) ? (string) $event->recurrence_end : '';
		$detail['date_created']     = isset( $event->date_created ) ? (string) $event->date_created : '';
		$detail['date_modified']    = isset( $event->date_modified ) ? (string) $event->date_modified : '';
		return $detail;
	}

	private static function event_summary( $event ) {
		$object_id = isset( $event->object_id ) ? (int) $event->object_id : 0;
		return array(
			'id'         => isset( $event->id ) ? (int) $event->id : 0,
			'post_id'    => $object_id,
			'title'      => isset( $event->title ) ? (string) $event->title : '',
			'status'     => isset( $event->status ) ? (string) $event->status : '',
			'start'      => isset( $event->start ) ? (string) $event->start : '',
			'start_tz'   => isset( $event->start_tz ) ? (string) $event->start_tz : '',
			'end'        => isset( $event->end ) ? (string) $event->end : '',
			'all_day'    => isset( $event->all_day ) ? (bool) $event->all_day : false,
			'recurrence' => isset( $event->recurrence ) ? (string) $event->recurrence : '',
			'calendars'  => self::calendar_names( $object_id ),
		);
	}

	private static function calendar_names( $post_id ) {
		if ( $post_id <= 0 || ! function_exists( 'wp_get_object_terms' ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $post_id, self::CALENDAR_TAX, array( 'fields' => 'names' ) );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			return array();
		}
		return is_array( $terms ) ? array_values( array_map( 'strval', $terms ) ) : array();
	}

	private static function list_calendars( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$taxonomy = function_exists( 'sugar_calendar_get_calendar_taxonomy_id' )
			? (string) sugar_calendar_get_calendar_taxonomy_id()
			: self::CALENDAR_TAX;
		if ( '' === $taxonomy ) {
			$taxonomy = self::CALENDAR_TAX;
		}

		if ( ! function_exists( 'get_terms' ) || ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'provider'  => 'sugar-calendar',
				'page'      => $page,
				'per_page'  => $per_page,
				'count'     => 0,
				'calendars' => array(),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
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
				'id'     => (int) $term->term_id,
				'name'   => isset( $term->name ) ? (string) $term->name : '',
				'slug'   => isset( $term->slug ) ? (string) $term->slug : '',
				'count'  => isset( $term->count ) ? (int) $term->count : 0,
				'parent' => isset( $term->parent ) ? (int) $term->parent : 0,
			);
		}
		return array(
			'provider'  => 'sugar-calendar',
			'page'      => $page,
			'per_page'  => $per_page,
			'count'     => count( $rows ),
			'calendars' => $rows,
		);
	}
}
