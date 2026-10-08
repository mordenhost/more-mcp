<?php

namespace More_MCP\Integrations\EventsCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Handler {

	const NAMES = array( 'tec_create_event', 'tec_update_event', 'tec_delete_event', 'tec_create_venue', 'tec_create_organizer', 'tec_get_venue' );

	const EVENT_CPT     = 'tribe_events';
	const VENUE_CPT     = 'tribe_venue';
	const ORGANIZER_CPT = 'tribe_organizer';
	const CATEGORY_TAX  = 'tribe_events_cat';

	const STATUSES = array( 'draft', 'pending', 'publish', 'future', 'private' );

	public static function supports( $name ) {
		return in_array( $name, self::NAMES, true );
	}

	public static function get_tools() {
		$event_fields = array(
			'title'         => array( 'type' => 'string', 'description' => 'Event title' ),
			'content'       => array( 'type' => 'string', 'description' => 'Event description (HTML allowed as in the editor)' ),
			'excerpt'       => array( 'type' => 'string' ),
			'start'         => array( 'type' => 'string', 'description' => 'Start, e.g. 2026-11-05 19:00 (in the event timezone, or the site timezone when none is given)' ),
			'end'           => array( 'type' => 'string', 'description' => 'End, same format. Defaults to one hour after the start (or the end of the day for an all-day event).' ),
			'all_day'       => array( 'type' => 'boolean' ),
			'timezone'      => array( 'type' => 'string', 'description' => 'Timezone name such as Asia/Jakarta (default: the site timezone)' ),
			'status'        => array( 'type' => 'string', 'enum' => self::STATUSES, 'description' => 'Default draft' ),
			'venue_id'      => array( 'type' => 'integer', 'description' => 'Existing venue ID (see tec_list_venues, tec_create_venue)' ),
			'organizer_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Existing organizer IDs' ),
			'categories'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Event category term IDs' ),
			'cost'          => array( 'type' => 'string', 'description' => 'Cost text, e.g. "$15" or "Free"' ),
			'url'           => array( 'type' => 'string', 'description' => 'Event website URL' ),
			'featured'      => array( 'type' => 'boolean', 'description' => 'Mark as a featured event' ),
		);
		return array(
			array(
				'name'        => 'tec_create_event',
				'description' => 'Create an event in The Events Calendar: title, start and end, all-day flag, timezone, description, venue, organizers, categories, cost and URL. Saved as a draft unless status is given (publishing needs the publish capability).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => $event_fields,
					'required'   => array( 'title', 'start' ),
				),
			),
			array(
				'name'        => 'tec_update_event',
				'description' => 'Update an event in The Events Calendar. Only the fields you pass change. Changing the start without an end keeps the event\'s length.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array_merge( array( 'id' => array( 'type' => 'integer', 'description' => 'Event ID' ) ), $event_fields ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'tec_delete_event',
				'description' => 'Delete an event; moves it to the trash by default, set force=true to delete it permanently.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer', 'description' => 'Event ID' ),
						'force' => array( 'type' => 'boolean', 'description' => 'Permanently delete instead of trashing (default false)' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'tec_create_venue',
				'description' => 'Create a venue for The Events Calendar: name, address, city, state or province, postal code, country, phone and website. Returns the venue ID to pass as venue_id when creating an event.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array( 'type' => 'string', 'description' => 'Venue name' ),
						'address'  => array( 'type' => 'string' ),
						'city'     => array( 'type' => 'string' ),
						'state'    => array( 'type' => 'string' ),
						'province' => array( 'type' => 'string' ),
						'zip'      => array( 'type' => 'string', 'description' => 'Postal code' ),
						'country'  => array( 'type' => 'string' ),
						'phone'    => array( 'type' => 'string' ),
						'website'  => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'description' => 'Default publish when you may publish, otherwise draft' ),
					),
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'tec_create_organizer',
				'description' => 'Create an organizer for The Events Calendar: name, email, phone and website. Returns the organizer ID to pass in organizer_ids when creating an event.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string', 'description' => 'Organizer name' ),
						'email'   => array( 'type' => 'string' ),
						'phone'   => array( 'type' => 'string' ),
						'website' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'description' => 'Default publish when you may publish, otherwise draft' ),
					),
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'tec_get_venue',
				'description' => 'Get one venue by ID: name, address, city, state or province, postal code, country and the events held there. Phone and website are included only for users who can edit the venue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'Venue ID' ),
					),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to manage events.' );
		}
		if ( ! function_exists( 'tribe_events' ) ) {
			throw new \Exception( 'The Events Calendar is not active.' );
		}

		switch ( $name ) {
			case 'tec_create_event':
				return self::create_event( $args );
			case 'tec_update_event':
				return self::update_event( $args );
			case 'tec_delete_event':
				return self::delete_event( $args );
			case 'tec_create_venue':
				return self::create_linked( 'venue', $args );
			case 'tec_create_organizer':
				return self::create_linked( 'organizer', $args );
			case 'tec_get_venue':
				return self::get_venue( $args );
		}
		throw new \Exception( 'Unknown events tool: ' . esc_html( $name ) );
	}

	

	private static function cap( string $post_type, string $which ): string {
		$object = get_post_type_object( $post_type );
		if ( ! $object ) {
			throw new \Exception( 'The ' . esc_html( $post_type ) . ' content type is not registered.' );
		}
		return (string) $object->cap->$which;
	}

	private static function status( $requested, string $post_type, string $fallback = 'draft' ): string {
		if ( null === $requested || '' === $requested ) {
			return $fallback;
		}
		$status = sanitize_key( (string) $requested );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			throw new \Exception( 'status must be one of: ' . esc_html( implode( ', ', self::STATUSES ) ) . '.' );
		}
		if ( 'draft' !== $status && 'pending' !== $status && ! current_user_can( self::cap( $post_type, 'publish_posts' ) ) ) {
			throw new \Exception( 'You do not have permission to publish. Use status draft or pending.' );
		}
		return $status;
	}

	private static function timezone( $name ): \DateTimeZone {
		if ( null === $name || '' === $name ) {
			return wp_timezone();
		}
		try {
			return new \DateTimeZone( (string) $name );
		} catch ( \Exception $e ) {
			throw new \Exception( 'Unknown timezone. Use a name such as Asia/Jakarta or UTC.' );
		}
	}

	private static function datetime( $value, \DateTimeZone $tz, string $field ): \DateTimeImmutable {
		try {
			return new \DateTimeImmutable( (string) $value, $tz );
		} catch ( \Exception $e ) {
			throw new \Exception( esc_html( $field ) . ' is not a date and time I can read. Use a form like 2026-11-05 19:00.' );
		}
	}

	private static function linked_ids( $ids, string $post_type, string $label ): array {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || get_post_type( $id ) !== $post_type ) {
				throw new \Exception( esc_html( $label . ' ' . $id . ' was not found.' ) );
			}
			$out[] = $id;
		}
		return $out;
	}

	private static function event_post( $id ): \WP_Post {
		$id   = (int) $id;
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || self::EVENT_CPT !== $post->post_type ) {
			throw new \Exception( 'Event not found' );
		}
		return $post;
	}

	private static function event_fields( array $args ): array {
		$set = array();
		if ( isset( $args['title'] ) ) {
			$set['title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$set['content'] = wp_kses_post( (string) $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$set['excerpt'] = sanitize_textarea_field( (string) $args['excerpt'] );
		}
		if ( isset( $args['cost'] ) ) {
			$set['cost'] = sanitize_text_field( (string) $args['cost'] );
		}
		if ( isset( $args['url'] ) ) {
			$url = esc_url_raw( (string) $args['url'], array( 'http', 'https' ) );
			if ( '' === $url && '' !== (string) $args['url'] ) {
				throw new \Exception( 'url must be an http(s) address.' );
			}
			$set['url'] = $url;
		}
		if ( isset( $args['featured'] ) ) {
			$set['featured'] = (bool) $args['featured'];
		}
		if ( isset( $args['all_day'] ) ) {
			$set['all_day'] = (bool) $args['all_day'];
		}
		if ( array_key_exists( 'venue_id', $args ) ) {
			$set['venue'] = empty( $args['venue_id'] ) ? array() : self::linked_ids( array( $args['venue_id'] ), self::VENUE_CPT, 'Venue' );
		}
		if ( array_key_exists( 'organizer_ids', $args ) ) {
			$set['organizer'] = self::linked_ids( $args['organizer_ids'], self::ORGANIZER_CPT, 'Organizer' );
		}
		if ( isset( $args['categories'] ) ) {
			$terms = array();
			foreach ( (array) $args['categories'] as $term_id ) {
				$term = get_term( (int) $term_id, self::CATEGORY_TAX );
				if ( ! $term || is_wp_error( $term ) ) {
					throw new \Exception( esc_html( 'Event category ' . (int) $term_id . ' was not found.' ) );
				}
				$terms[] = (int) $term->term_id;
			}
			$set['category'] = $terms;
		}
		return $set;
	}

	

	private static function create_event( array $args ): array {
		if ( ! current_user_can( self::cap( self::EVENT_CPT, 'create_posts' ) ) ) {
			throw new \Exception( 'You do not have permission to create events.' );
		}
		$title = trim( (string) ( $args['title'] ?? '' ) );
		if ( '' === $title ) {
			throw new \Exception( 'title is required.' );
		}
		if ( empty( $args['start'] ) ) {
			throw new \Exception( 'start is required.' );
		}

		$tz      = self::timezone( $args['timezone'] ?? null );
		$all_day = ! empty( $args['all_day'] );
		$start   = self::datetime( $args['start'], $tz, 'start' );
		if ( $all_day ) {
			$start = $start->setTime( 0, 0, 0 );
		}
		if ( ! empty( $args['end'] ) ) {
			$end = self::datetime( $args['end'], $tz, 'end' );
			if ( $all_day ) {
				$end = $end->setTime( 23, 59, 59 );
			}
		} else {
			$end = $all_day ? $start->setTime( 23, 59, 59 ) : $start->modify( '+1 hour' );
		}
		if ( $end < $start ) {
			throw new \Exception( 'end must not be before start.' );
		}

		$set               = self::event_fields( $args );
		$set['title']      = sanitize_text_field( $title );
		$set['start_date'] = $start->format( 'Y-m-d H:i:s' );
		$set['end_date']   = $end->format( 'Y-m-d H:i:s' );
		$set['timezone']   = $tz->getName();
		$set['status']     = self::status( $args['status'] ?? null, self::EVENT_CPT );

		$event = tribe_events()->set_args( $set )->create();
		if ( ! $event || empty( $event->ID ) ) {
			throw new \Exception( 'Failed to create event' );
		}
		return array(
			'id'      => (int) $event->ID,
			'status'  => get_post_status( $event->ID ),
			'url'     => get_permalink( $event->ID ),
			'message' => 'Event created successfully',
		);
	}

	private static function update_event( array $args ): array {
		$post = self::event_post( $args['id'] ?? 0 );
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new \Exception( 'You do not have permission to edit this event.' );
		}

		$set = self::event_fields( $args );
		if ( array_key_exists( 'title', $args ) && '' === trim( (string) $args['title'] ) ) {
			throw new \Exception( 'title cannot be empty.' );
		}
		if ( isset( $args['status'] ) ) {
			$set['status'] = self::status( $args['status'], self::EVENT_CPT, $post->post_status );
		}

		$touches_time = isset( $args['start'] ) || isset( $args['end'] ) || isset( $args['timezone'] ) || isset( $args['all_day'] );
		if ( $touches_time ) {
			$tz_name  = isset( $args['timezone'] ) ? $args['timezone'] : get_post_meta( $post->ID, '_EventTimezone', true );
			$tz       = self::timezone( $tz_name );
			$all_day  = isset( $args['all_day'] ) ? (bool) $args['all_day'] : (bool) get_post_meta( $post->ID, '_EventAllDay', true );
			$old_from = self::datetime( (string) get_post_meta( $post->ID, '_EventStartDate', true ), $tz, 'start' );
			$old_to   = self::datetime( (string) get_post_meta( $post->ID, '_EventEndDate', true ), $tz, 'end' );

			$start = isset( $args['start'] ) ? self::datetime( $args['start'], $tz, 'start' ) : $old_from;
			if ( isset( $args['end'] ) ) {
				$end = self::datetime( $args['end'], $tz, 'end' );
			} else {
				
				$end = $start->modify( '+' . max( 0, $old_to->getTimestamp() - $old_from->getTimestamp() ) . ' seconds' );
			}
			if ( $all_day ) {
				$start = $start->setTime( 0, 0, 0 );
				$end   = $end->setTime( 23, 59, 59 );
			}
			if ( $end < $start ) {
				throw new \Exception( 'end must not be before start.' );
			}
			$set['start_date'] = $start->format( 'Y-m-d H:i:s' );
			$set['end_date']   = $end->format( 'Y-m-d H:i:s' );
			$set['timezone']   = $tz->getName();
		}

		if ( empty( $set ) ) {
			throw new \Exception( 'Nothing to update: pass at least one field besides id.' );
		}

		$saved = tribe_events()->by( 'post_status', 'any' )->by( 'id', $post->ID )->set_args( $set )->save();
		if ( empty( $saved ) || in_array( false, (array) $saved, true ) ) {
			throw new \Exception( 'Failed to update event' );
		}
		return array(
			'id'      => (int) $post->ID,
			'message' => 'Event updated successfully',
		);
	}

	private static function delete_event( array $args ): array {
		$post = self::event_post( $args['id'] ?? 0 );
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			throw new \Exception( 'You do not have permission to delete this event.' );
		}
		$force = ! empty( $args['force'] );
		if ( ! $force && 'trash' === $post->post_status ) {
			return array( 'id' => (int) $post->ID, 'message' => 'Event is already in trash' );
		}

		$done = $force ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );
		if ( ! $done ) {
			throw new \Exception( 'Failed to delete event' );
		}
		return array(
			'id'      => (int) $post->ID,
			'message' => $force ? 'Event permanently deleted' : 'Event moved to trash',
		);
	}

	

	private static function create_linked( string $kind, array $args ): array {
		$post_type = 'venue' === $kind ? self::VENUE_CPT : self::ORGANIZER_CPT;
		if ( ! current_user_can( self::cap( $post_type, 'create_posts' ) ) ) {
			throw new \Exception( esc_html( 'You do not have permission to create a ' . $kind . '.' ) );
		}
		$name = trim( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name ) {
			throw new \Exception( 'name is required.' );
		}

		$set = array( $kind => sanitize_text_field( $name ) );
		if ( 'venue' === $kind ) {
			foreach ( array( 'address', 'city', 'state', 'province', 'zip', 'country', 'phone' ) as $key ) {
				if ( isset( $args[ $key ] ) ) {
					$set[ $key ] = sanitize_text_field( (string) $args[ $key ] );
				}
			}
		} elseif ( isset( $args['email'] ) ) {
			$email = sanitize_email( (string) $args['email'] );
			if ( '' !== (string) $args['email'] && ! is_email( $email ) ) {
				throw new \Exception( 'email is not a valid address.' );
			}
			$set['email'] = $email;
		}
		if ( 'organizer' === $kind && isset( $args['phone'] ) ) {
			$set['phone'] = sanitize_text_field( (string) $args['phone'] );
		}
		if ( isset( $args['website'] ) ) {
			$site = esc_url_raw( (string) $args['website'], array( 'http', 'https' ) );
			if ( '' === $site && '' !== (string) $args['website'] ) {
				throw new \Exception( 'website must be an http(s) address.' );
			}
			$set['website'] = $site;
		}
		$set['status'] = self::status( $args['status'] ?? null, $post_type, current_user_can( self::cap( $post_type, 'publish_posts' ) ) ? 'publish' : 'draft' );

		$repository = 'venue' === $kind ? tribe_venues() : tribe_organizers();
		$created    = $repository->set_args( $set )->create();
		if ( ! $created || empty( $created->ID ) ) {
			throw new \Exception( esc_html( 'Failed to create ' . $kind ) );
		}
		return array(
			'id'      => (int) $created->ID,
			'message' => ucfirst( $kind ) . ' created successfully',
		);
	}

	private static function get_venue( array $args ): array {
		$id   = (int) ( $args['id'] ?? 0 );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || self::VENUE_CPT !== $post->post_type ) {
			throw new \Exception( 'Venue not found' );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( 'Venue not found' );
		}

		$meta = static function ( string $key ) use ( $id ) {
			$value = get_post_meta( $id, $key, true );
			return '' === $value || null === $value ? null : (string) $value;
		};
		$out = array(
			'id'          => $id,
			'name'        => get_the_title( $id ),
			'status'      => $post->post_status,
			'description' => wp_strip_all_tags( (string) $post->post_content ),
			'address'     => $meta( '_VenueAddress' ),
			'city'        => $meta( '_VenueCity' ),
			'state'       => $meta( '_VenueState' ),
			'province'    => $meta( '_VenueProvince' ),
			'zip'         => $meta( '_VenueZip' ),
			'country'     => $meta( '_VenueCountry' ),
			'url'         => get_permalink( $id ),
		);
		if ( current_user_can( 'edit_post', $id ) ) {
			$out['phone']   = $meta( '_VenuePhone' );
			$out['website'] = $meta( '_VenueURL' );
		}

		$events = get_posts(
			array(
				'post_type'      => self::EVENT_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'meta_value',
				'meta_key'       => '_EventStartDate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering a small, capped list.
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the venue link lives in meta.
					array(
						'key'   => '_EventVenueID',
						'value' => $id,
					),
				),
				'fields'         => 'ids',
			)
		);
		$out['events'] = array();
		foreach ( $events as $event_id ) {
			$out['events'][] = array(
				'id'    => (int) $event_id,
				'title' => get_the_title( $event_id ),
				'start' => (string) get_post_meta( $event_id, '_EventStartDate', true ),
			);
		}
		return $out;
	}
}
