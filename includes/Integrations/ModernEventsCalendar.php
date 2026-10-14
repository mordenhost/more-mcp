<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ModernEventsCalendar {

	const EVENT_CPT = 'mec-events';

	const CATEGORY_TAX = 'mec_category';

	const LOCATION_TAX = 'mec_location';

	const ORGANIZER_TAX = 'mec_organizer';

	const EVENTS_TABLE = 'mec_events';

	const DATES_TABLE = 'mec_dates';

	const HIDDEN_TERM_ID = 1;

	public static function is_available() {
		if ( defined( 'MEC_VERSION' ) ) {
			return true;
		}

		if ( function_exists( 'post_type_exists' ) && function_exists( 'taxonomy_exists' ) ) {
			return post_type_exists( self::event_post_type() ) && taxonomy_exists( self::CATEGORY_TAX );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'modern-events-calendar' ),
			'capabilities' => array( 'events' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		$paging = array(
			'per_page' => array( 'type' => 'integer', 'description' => 'How many rows (1-100, default 50).' ),
			'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
		);
		return array(
			array(
				'name'        => 'mec_list_events',
				'description' => 'List published events from Modern Events Calendar. Returns post id, title, status, permalink, first start/end datetime (local wall-clock time), all-day and recurring flags, repeat type, time zone, shortcode visibility, primary location and organizer names, and category names. A date range keeps the events with at least one scheduled occurrence in it, including recurring events that started earlier. Filter by category and keyword; order and paginate. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array( 'type' => 'string', 'description' => 'Only events with an occurrence ending on/after this date (Y-m-d).' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'Only events with an occurrence starting on/before this date (Y-m-d).' ),
						'category'   => array( 'type' => 'string', 'description' => 'Filter by an event category slug or numeric term id.' ),
						'search'     => array( 'type' => 'string', 'description' => 'Optional keyword search on event title/content.' ),
						'orderby'    => array( 'type' => 'string', 'description' => 'One of: start_date, title, date, id. Default start_date (the first occurrence).' ),
						'order'      => array( 'type' => 'string', 'description' => 'ASC or DESC. Default ASC.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'How many events (1-100, default 20).' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'mec_get_event',
				'description' => 'Get one Modern Events Calendar event by post id with full detail: everything mec_list_events returns plus content, cost, the repeat rule (type, interval as MEC stores it, which is in days for daily and weekly repeats, and the computed last date or null for never), the location (name, address), additional location names, all organizer names, created/modified timestamps, and the upcoming scheduled occurrences from MEC\'s own date table. Read-only; never organizer contact details or bookings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array( 'type' => 'integer', 'description' => 'Event post id.' ),
						'occurrences_from'  => array( 'type' => 'string', 'description' => 'List occurrences ending on/after this date (Y-m-d). Default today in the site time zone.' ),
						'occurrences_limit' => array( 'type' => 'integer', 'description' => 'How many occurrences (1-50, default 10).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'mec_list_occurrences',
				'description' => 'List scheduled occurrences of published Modern Events Calendar events in date order, read from MEC\'s own date table, so each date of a recurring event is its own row. Returns event post id, title, start/end date, and start/end datetime (local wall-clock time). Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'start_date' => array( 'type' => 'string', 'description' => 'Only occurrences ending on/after this date (Y-m-d). Default today in the site time zone.' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'Only occurrences starting on/before this date (Y-m-d). Default: no upper bound.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'How many occurrences (1-100, default 50).' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed.' ),
					),
				),
			),
			array(
				'name'        => 'mec_list_locations',
				'description' => 'List location definitions from Modern Events Calendar (mec_location terms). Returns term id, name, slug, address, latitude, longitude, and event count for each. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $paging,
				),
			),
			array(
				'name'        => 'mec_list_organizers',
				'description' => 'List organizer definitions from Modern Events Calendar (mec_organizer terms). Returns term id, name, slug, and event count only. Read-only; never an organizer phone, email, or website.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $paging,
				),
			),
			array(
				'name'        => 'mec_list_event_categories',
				'description' => 'List event-category terms from Modern Events Calendar (mec_category taxonomy). Returns term id, name, slug, event count, and parent term id; the taxonomy is hierarchical. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $paging,
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read events.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Modern Events Calendar is not active.' );
		}
		$args = is_array( $args ) ? $args : array();
		if ( 'mec_list_events' === $name ) {
			return self::list_events( $args );
		}
		if ( 'mec_get_event' === $name ) {
			return self::get_event( $args );
		}
		if ( 'mec_list_occurrences' === $name ) {
			return self::list_occurrences( $args );
		}
		if ( 'mec_list_locations' === $name ) {
			return self::list_terms( $args, self::LOCATION_TAX, 'locations' );
		}
		if ( 'mec_list_organizers' === $name ) {
			return self::list_terms( $args, self::ORGANIZER_TAX, 'organizers' );
		}
		if ( 'mec_list_event_categories' === $name ) {
			return self::list_terms( $args, self::CATEGORY_TAX, 'categories' );
		}
		throw new \Exception( 'Unknown events tool: ' . esc_html( $name ) );
	}

	private static function event_post_type() {
		$type = self::EVENT_CPT;
		if ( function_exists( 'apply_filters' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- MEC's own post-type-name filter (MEC_main::get_main_post_type()).
			$filtered = apply_filters( 'mec_post_type_name', $type );
			if ( is_string( $filtered ) && '' !== $filtered ) {
				$type = $filtered;
			}
		}
		return $type;
	}

	private static function list_events( $args ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$start    = self::date_arg( $args, 'start_date' );
		$end      = self::date_arg( $args, 'end_date' );

		$result = array(
			'provider' => 'modern-events-calendar',
			'page'     => $page,
			'per_page' => $per_page,
			'count'    => 0,
			'events'   => array(),
		);

		$order = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';
		$query = array(
			'post_type'        => self::event_post_type(),
			'post_status'      => 'publish', 
			'posts_per_page'   => $per_page,
			'offset'           => ( $page - 1 ) * $per_page,
			'order'            => $order,
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);

		
		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'start_date';
		if ( 'title' === $orderby ) {
			$query['orderby'] = 'title';
		} elseif ( 'date' === $orderby ) {
			$query['orderby'] = 'date';
		} elseif ( 'id' === $orderby ) {
			$query['orderby'] = 'ID';
		} else {
			$query['orderby']  = 'meta_value';
			$query['meta_key'] = 'mec_start_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- MEC's own first-start key, needed to sort by event date.
		}

		if ( ! empty( $args['search'] ) ) {
			$query['s'] = sanitize_text_field( (string) $args['search'] );
		}
		if ( isset( $args['category'] ) && '' !== (string) $args['category'] ) {
			$category = sanitize_text_field( (string) $args['category'] );
			$numeric  = 1 === preg_match( '/^\d+$/', $category );
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the category filter the tool advertises.
			$query['tax_query'] = array(
				array(
					'taxonomy' => self::CATEGORY_TAX,
					'field'    => $numeric ? 'term_id' : 'slug',
					'terms'    => $numeric ? (int) $category : $category,
				),
			);
		}

		
		if ( '' !== $start || '' !== $end ) {
			$ids = self::event_ids_in_range( $start, $end );
			if ( empty( $ids ) ) {
				return $result;
			}
			$query['post__in'] = $ids;
		}

		$posts = function_exists( 'get_posts' ) ? get_posts( $query ) : array();
		foreach ( (array) $posts as $post ) {
			if ( is_object( $post ) && ! empty( $post->ID ) ) {
				$result['events'][] = self::event_summary( $post );
			}
		}
		$result['count'] = count( $result['events'] );
		return $result;
	}

	private static function get_event( $args ) {
		$id = isset( $args['id'] ) ? (int) $args['id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'A valid event id is required.' );
		}
		$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || self::event_post_type() !== $post->post_type ) {
			throw new \Exception( 'Event not found: ' . intval( $id ) );
		}

		$is_public = ( 'publish' === $post->post_status ) && empty( $post->post_password );
		if ( ! $is_public && ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( 'You do not have permission to read this event.' );
		}

		$limit = isset( $args['occurrences_limit'] ) ? max( 1, min( 50, (int) $args['occurrences_limit'] ) ) : 10;
		$from  = self::date_arg( $args, 'occurrences_from' );
		if ( '' === $from ) {
			$from = self::today();
		}

		$detail            = self::event_summary( $post );
		$detail['content'] = isset( $post->post_content ) ? (string) $post->post_content : '';
		$cost              = self::meta( $id, 'mec_cost' );
		$detail['cost']    = ( '' === $cost ) ? null : $cost;

		$interval         = self::meta( $id, 'mec_repeat_interval' );
		$detail['repeat'] = array(
			'type'     => $detail['repeat_type'],
			'interval' => ( $detail['recurring'] && is_numeric( $interval ) ) ? (int) $interval : null,
			'until'    => $detail['recurring'] ? self::repeat_until( $id ) : null,
		);

		$location_id        = (int) self::meta( $id, 'mec_location_id' );
		$detail['location'] = null;
		if ( $location_id > self::HIDDEN_TERM_ID ) {
			$name = self::term_name( $location_id, self::LOCATION_TAX );
			if ( null !== $name ) {
				$address            = self::term_meta( $location_id, 'address' );
				$detail['location'] = array(
					'id'      => $location_id,
					'name'    => $name,
					'address' => ( '' === $address ) ? null : $address,
				);
			}
		}
		$detail['additional_locations'] = self::term_names( self::meta_ids( $id, 'mec_additional_location_ids' ), self::LOCATION_TAX );

		$organizer_ids        = self::meta_ids( $id, 'mec_additional_organizer_ids' );
		$primary_organizer_id = (int) self::meta( $id, 'mec_organizer_id' );
		if ( $primary_organizer_id > self::HIDDEN_TERM_ID ) {
			array_unshift( $organizer_ids, $primary_organizer_id );
		}
		$detail['organizers'] = self::term_names( array_values( array_unique( $organizer_ids ) ), self::ORGANIZER_TAX );

		$detail['date_created']  = isset( $post->post_date ) ? (string) $post->post_date : '';
		$detail['date_modified'] = isset( $post->post_modified ) ? (string) $post->post_modified : '';
		$detail['occurrences']   = self::event_occurrences( $id, $from, $limit );
		return $detail;
	}

	private static function event_summary( $post ) {
		$id          = (int) $post->ID;
		$recurring   = '1' === self::meta( $id, 'mec_repeat_status' );
		$repeat_type = self::meta( $id, 'mec_repeat_type' );
		$public      = self::meta( $id, 'mec_public' );

		$location_id  = (int) self::meta( $id, 'mec_location_id' );
		$organizer_id = (int) self::meta( $id, 'mec_organizer_id' );

		return array(
			'id'          => $id,
			'title'       => isset( $post->post_title ) ? (string) $post->post_title : '',
			'status'      => isset( $post->post_status ) ? (string) $post->post_status : '',
			'permalink'   => function_exists( 'get_permalink' ) ? (string) get_permalink( $id ) : null,
			'start'       => self::meta_datetime( $id, 'start' ),
			'end'         => self::meta_datetime( $id, 'end' ),
			'all_day'     => '1' === self::meta( $id, 'mec_allday' ),
			'recurring'   => $recurring,
			'repeat_type' => ( $recurring && '' !== $repeat_type ) ? $repeat_type : null,
			'timezone'    => self::event_timezone( $id ),
			'public'      => '0' !== $public, 
			'location'    => $location_id > self::HIDDEN_TERM_ID ? self::term_name( $location_id, self::LOCATION_TAX ) : null,
			'organizer'   => $organizer_id > self::HIDDEN_TERM_ID ? self::term_name( $organizer_id, self::ORGANIZER_TAX ) : null,
			'categories'  => self::category_names( $id ),
		);
	}

	private static function meta_datetime( $id, $which ) {
		$date = self::meta( $id, 'mec_' . $which . '_date' );
		if ( '' === $date ) {
			return null;
		}
		$seconds = self::meta( $id, 'mec_' . $which . '_day_seconds' );
		if ( ! is_numeric( $seconds ) ) {
			return $date;
		}
		$seconds = max( 0, (int) $seconds ) % 86400;
		return sprintf( '%s %02d:%02d:%02d', $date, intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
	}

	private static function event_timezone( $id ) {
		$tz = self::meta( $id, 'mec_timezone' );
		if ( '' !== $tz && 'global' !== $tz ) {
			return $tz;
		}
		if ( function_exists( 'wp_timezone_string' ) ) {
			$site = (string) wp_timezone_string();
			return '' === $site ? null : $site;
		}
		return null;
	}

	private static function meta( $id, $key ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$value = get_post_meta( $id, $key, true );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	private static function term_meta( $term_id, $key ) {
		if ( ! function_exists( 'get_term_meta' ) ) {
			return '';
		}
		$value = get_term_meta( (int) $term_id, $key, true );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	private static function meta_ids( $id, $key ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$value = get_post_meta( $id, $key, true );
		$ids   = array();
		foreach ( is_array( $value ) ? $value : array() as $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id > self::HIDDEN_TERM_ID ) {
				$ids[] = $term_id;
			}
		}
		return $ids;
	}

	private static function term_name( $term_id, $taxonomy ) {
		if ( ! function_exists( 'get_term' ) ) {
			return null;
		}
		$term = get_term( (int) $term_id, $taxonomy );
		if ( ! is_object( $term ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) || ! isset( $term->name ) ) {
			return null;
		}
		return (string) $term->name;
	}

	private static function term_names( array $term_ids, $taxonomy ) {
		$names = array();
		foreach ( $term_ids as $term_id ) {
			$name = self::term_name( $term_id, $taxonomy );
			if ( null !== $name ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	private static function category_names( $id ) {
		if ( ! function_exists( 'wp_get_object_terms' ) ) {
			return array();
		}
		$names = wp_get_object_terms( (int) $id, self::CATEGORY_TAX, array( 'fields' => 'names' ) );
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $names ) ) || ! is_array( $names ) ) {
			return array();
		}
		return array_values( array_map( 'strval', $names ) );
	}

	private static function event_ids_in_range( $start, $end ) {
		global $wpdb;
		if ( ! self::table_exists( self::DATES_TABLE ) ) {
			return array();
		}
		$table  = self::table_name( self::DATES_TABLE );
		$where  = array();
		$params = array();
		if ( '' !== $start ) {
			$where[]  = 'dend >= %s';
			$params[] = $start;
		}
		if ( '' !== $end ) {
			$where[]  = 'dstart <= %s';
			$params[] = $end;
		}
		if ( empty( $where ) ) {
			return array();
		}

		$sql = "SELECT DISTINCT post_id FROM {$table} WHERE " . implode( ' AND ', $where );
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$out = array();
		foreach ( (array) $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 ) {
				$out[] = $post_id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function repeat_until( $id ) {
		global $wpdb;
		if ( ! self::table_exists( self::EVENTS_TABLE ) ) {
			return null;
		}
		$table = self::table_name( self::EVENTS_TABLE );
		$until = $wpdb->get_var( $wpdb->prepare( "SELECT `end` FROM {$table} WHERE post_id = %d LIMIT 1", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$until = is_scalar( $until ) ? (string) $until : '';
		return ( '' === $until || '0000-00-00' === $until ) ? null : $until;
	}

	private static function event_occurrences( $id, $from, $limit ) {
		global $wpdb;
		if ( ! self::table_exists( self::DATES_TABLE ) ) {
			return array();
		}
		$table = self::table_name( self::DATES_TABLE );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT dstart, dend, tstart, tend FROM {$table} WHERE post_id = %d AND dend >= %s ORDER BY tstart ASC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id,
				$from,
				(int) $limit
			),
			ARRAY_A
		);
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = self::occurrence_row( $row );
		}
		return $out;
	}

	private static function list_occurrences( $args ) {
		global $wpdb;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$start    = self::date_arg( $args, 'start_date' );
		$end      = self::date_arg( $args, 'end_date' );
		if ( '' === $start ) {
			$start = self::today();
		}

		$result = array(
			'provider'    => 'modern-events-calendar',
			'page'        => $page,
			'per_page'    => $per_page,
			'count'       => 0,
			'occurrences' => array(),
		);
		if ( ! self::table_exists( self::DATES_TABLE ) ) {
			return $result;
		}

		
		$table  = self::table_name( self::DATES_TABLE );
		$posts  = $wpdb->posts;
		$sql    = "SELECT d.post_id, p.post_title, d.dstart, d.dend, d.tstart, d.tend FROM {$table} d INNER JOIN {$posts} p ON p.ID = d.post_id WHERE p.post_type = %s AND p.post_status = 'publish' AND d.dend >= %s";
		$params = array( self::event_post_type(), $start );
		if ( '' !== $end ) {
			$sql     .= ' AND d.dstart <= %s';
			$params[] = $end;
		}
		$sql     .= ' ORDER BY d.tstart ASC, d.id ASC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result['occurrences'][] = array_merge(
				array(
					'event_id' => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
					'title'    => isset( $row['post_title'] ) ? (string) $row['post_title'] : '',
				),
				self::occurrence_row( $row )
			);
		}
		$result['count'] = count( $result['occurrences'] );
		return $result;
	}

	private static function occurrence_row( $row ) {
		$tstart = isset( $row['tstart'] ) ? (int) $row['tstart'] : 0;
		$tend   = isset( $row['tend'] ) ? (int) $row['tend'] : 0;
		return array(
			'start_date' => isset( $row['dstart'] ) ? (string) $row['dstart'] : '',
			'end_date'   => isset( $row['dend'] ) ? (string) $row['dend'] : '',
			'start'      => $tstart > 0 ? gmdate( 'Y-m-d H:i:s', $tstart ) : null,
			'end'        => $tend > 0 ? gmdate( 'Y-m-d H:i:s', $tend ) : null,
		);
	}

	private static function list_terms( $args, $taxonomy, $key ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$result = array(
			'provider' => 'modern-events-calendar',
			'page'     => $page,
			'per_page' => $per_page,
			'count'    => 0,
			$key       => array(),
		);
		if ( ! function_exists( 'get_terms' ) || ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
			return $result;
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

		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
				continue;
			}
			$term_id = (int) $term->term_id;
			$row     = array(
				'id'    => $term_id,
				'name'  => isset( $term->name ) ? (string) $term->name : '',
				'slug'  => isset( $term->slug ) ? (string) $term->slug : '',
				'count' => isset( $term->count ) ? (int) $term->count : 0,
			);
			if ( self::CATEGORY_TAX === $taxonomy ) {
				$row['parent'] = isset( $term->parent ) ? (int) $term->parent : 0;
			} elseif ( self::LOCATION_TAX === $taxonomy ) {
				foreach ( array( 'address', 'latitude', 'longitude' ) as $meta_key ) {
					$value            = self::term_meta( $term_id, $meta_key );
					$row[ $meta_key ] = ( '' === $value ) ? null : $value;
				}
			}
			$result[ $key ][] = $row;
		}
		$result['count'] = count( $result[ $key ] );
		return $result;
	}

	private static function date_arg( $args, $key ) {
		if ( ! isset( $args[ $key ] ) || '' === $args[ $key ] ) {
			return '';
		}
		$value = sanitize_text_field( (string) $args[ $key ] );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			throw new \Exception( esc_html( $key ) . ' must be a date in Y-m-d format.' );
		}
		return $value;
	}

	private static function today() {
		return function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
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
		$like  = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : $table;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $found === $table;
	}
}
