<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCRM {

	const MAX_IDS = 20;

	const MAX_CONTACTS = 100;

	const PIVOT_LIST = 'FluentCrm\App\Models\Lists';

	public static function is_available() {
		return defined( 'FLUENTCRM' ) || function_exists( 'FluentCrmApi' ) || class_exists( '\FluentCrm\App\Models\Subscriber' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'fluentcrm' ),
			'capabilities' => array( 'crm' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'crm_get_status',
				'description' => 'Read FluentCRM contact-list health: the total number of contacts and a breakdown by subscription status (subscribed, pending, unsubscribed, bounced, complained). Returns aggregate counts only, never contact records or any personal data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'crm_get_campaigns',
				'description' => 'Read FluentCRM email campaign health: a breakdown of campaigns by status (draft, scheduled, working, archived, etc.) and, optionally, a list of recent campaigns with title, type, status, recipient count, and scheduled/created dates. Returns campaign metadata only — never the email subject, pre-header, or body content, and never recipient identities or any personal data. Read-only diagnostic; cannot send, schedule, edit, or delete a campaign.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_list' => array(
							'type'        => 'boolean',
							'description' => 'When true, include a list of the most recent campaigns (metadata only). Default false (counts by status only).',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'Maximum campaigns to list when include_list is true (1-50). Default 20.',
						),
					),
				),
			),
			array(
				'name'        => 'crm_get_lists',
				'description' => 'Read the FluentCRM list catalogue: for each list its id, title, slug, and whether it is public, plus a per-list subscriber count as a pure aggregate. Returns list definitions and counts only — never a contact record, email address, or any personal data. Read-only diagnostic; cannot create, edit, or delete a list.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'crm_get_tags',
				'description' => 'Read the FluentCRM tag catalogue: for each tag its id, title, and slug. Returns tag definitions only — never a contact record, email address, tag membership, or any personal data. Read-only diagnostic; cannot create, edit, or delete a tag.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'crm_list_contacts',
				'description' => 'List FluentCRM contacts, newest first, through FluentCRM\'s own contact query. Filter by subscription status, tag ids, list ids (a contact matches when it has ANY of the given tags or lists), and a name/email search. Each row: id, email, first_name, last_name, status, contact_type, created_at, tag_ids, list_ids. Returns personal data (names and email addresses), never phone, address, IP, custom fields or notes. Paginated with limit (default 20, max 100) and offset; total is the full match count. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => 'Only contacts with this subscription status: subscribed, pending, unsubscribed, transactional, bounced, complained, or spammed (the site may register more).',
						),
						'tag_ids'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Only contacts carrying any of these tag ids (see crm_get_tags).',
						),
						'list_ids' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Only contacts in any of these list ids (see crm_get_lists).',
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Match against name and email, as the FluentCRM contact screen search does.',
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => 'Contacts per page (1-100). Default 20.',
						),
						'offset'   => array(
							'type'        => 'integer',
							'description' => 'Number of matching contacts to skip. Default 0.',
						),
					),
				),
			),
			array(
				'name'        => 'crm_get_contact',
				'description' => 'Get one FluentCRM contact by id or email: id, email, first_name, last_name, status, contact_type, source, linked WordPress user id, created/updated/last-activity dates, and the tags and lists it belongs to (id, title, slug). Returns personal data (name and email); phone, address, IP, date of birth, custom fields, notes and email history are never returned. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id' => array(
							'type'        => 'integer',
							'description' => 'FluentCRM contact id.',
						),
						'email'      => array(
							'type'        => 'string',
							'description' => 'Contact email, used when contact_id is not given.',
						),
					),
				),
			),
			self::membership_tool( 'crm_add_contact_tags', 'tag', 'add' ),
			self::membership_tool( 'crm_remove_contact_tags', 'tag', 'remove' ),
			self::membership_tool( 'crm_add_contact_lists', 'list', 'add' ),
			self::membership_tool( 'crm_remove_contact_lists', 'list', 'remove' ),
		);
	}

	private static function membership_tool( $name, $kind, $verb ) {
		$plural  = $kind . 's';
		$catalog = 'tag' === $kind ? 'crm_get_tags' : 'crm_get_lists';
		if ( 'add' === $verb ) {
			$what = sprintf( 'Add one FluentCRM contact to %1$s (by id; see %2$s). Ids the contact already has are left alone; ids that are not an existing %3$s are skipped and reported (the call is refused when none exist), so no new %3$s is ever created.', 'tag' === $kind ? 'one or more tags' : 'one or more lists', $catalog, $kind );
			$side = sprintf( 'Goes through FluentCRM\'s own %1$s, so it fires the "contact added to %2$s" event: any FluentCRM automation that starts on these %3$s will run and may email the contact.', 'tag' === $kind ? 'attachTags()' : 'attachLists()', $kind, $plural );
		} else {
			$what = sprintf( 'Remove one FluentCRM contact from %1$s (by id; see %2$s). Ids the contact does not have are reported and left alone. The %3$s itself is not deleted.', 'tag' === $kind ? 'one or more tags' : 'one or more lists', $catalog, $kind );
			$side = sprintf( 'Goes through FluentCRM\'s own %1$s, so it fires the "contact removed from %2$s" event, which can start or stop FluentCRM automations.', 'tag' === $kind ? 'detachTags()' : 'detachLists()', $kind );
		}
		return array(
			'name'        => $name,
			'description' => $what . ' ' . $side . ' Re-reads the contact afterwards and reports the ' . $plural . ' it now has. Reverse it with the matching ' . ( 'add' === $verb ? 'remove' : 'add' ) . ' tool.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'contact_id'     => array(
						'type'        => 'integer',
						'description' => 'FluentCRM contact id (see crm_list_contacts / crm_get_contact).',
					),
					$kind . '_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => ucfirst( $kind ) . ' ids to ' . $verb . ' (1-' . self::MAX_IDS . ').',
					),
				),
				'required'   => array( 'contact_id', $kind . '_ids' ),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use CRM tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'FluentCRM is not active.' );
		}

		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'crm_get_campaigns':
				return self::get_campaigns( $args );
			case 'crm_get_lists':
				return self::get_lists();
			case 'crm_get_tags':
				return self::get_tags();
			case 'crm_list_contacts':
				return self::list_contacts( $args );
			case 'crm_get_contact':
				return self::get_contact( $args );
			case 'crm_add_contact_tags':
				return self::change_membership( $args, 'tag', 'add' );
			case 'crm_remove_contact_tags':
				return self::change_membership( $args, 'tag', 'remove' );
			case 'crm_add_contact_lists':
				return self::change_membership( $args, 'list', 'add' );
			case 'crm_remove_contact_lists':
				return self::change_membership( $args, 'list', 'remove' );
		}

		if ( 'crm_get_status' !== $name ) {
			throw new \Exception( 'Unknown CRM tool: ' . esc_html( $name ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fc_subscribers';

		
		
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM contact table was not found; cannot read counts on this version.',
			);
		}

		
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name validated above, no user input in query.
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );

		$by_status = array();
		$total     = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status            = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
				$count             = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$by_status[ $status ] = $count;
				$total            += $count;
			}
		}

		return array(
			'provider'   => 'fluentcrm',
			'available'  => true,
			'total'      => $total,
			'by_status'  => $by_status,
		);
	}

	private static function get_campaigns( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_campaigns';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM campaigns table was not found; cannot read campaigns on this version.',
			);
		}

		
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name validated above, no user input in query.
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );

		$by_status = array();
		$total     = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status               = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'unknown';
				$count                = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$by_status[ $status ] = $count;
				$total               += $count;
			}
		}

		$result = array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'total'     => $total,
			'by_status' => $by_status,
		);

		if ( empty( $args['include_list'] ) ) {
			return $result;
		}

		
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 50 ) {
			$limit = 50;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated above; value is prepared.
		$campaign_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, type, status, recipients_count, scheduled_at, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$campaigns = array();
		if ( is_array( $campaign_rows ) ) {
			foreach ( $campaign_rows as $row ) {
				$campaigns[] = array(
					'id'               => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'title'            => isset( $row['title'] ) ? (string) $row['title'] : '',
					'type'             => isset( $row['type'] ) ? (string) $row['type'] : '',
					'status'           => isset( $row['status'] ) ? (string) $row['status'] : '',
					'recipients_count' => isset( $row['recipients_count'] ) ? (int) $row['recipients_count'] : 0,
					'scheduled_at'     => isset( $row['scheduled_at'] ) ? $row['scheduled_at'] : null,
					'created_at'       => isset( $row['created_at'] ) ? $row['created_at'] : null,
				);
			}
		}

		$result['campaigns'] = $campaigns;
		return $result;
	}

	private static function get_lists() {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_lists';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM lists table was not found; cannot read lists on this version.',
			);
		}

		
		
		$pivot       = $wpdb->prefix . 'fc_subscriber_pivot';
		$pivot_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pivot ) ) === $pivot;

		$counts = array();
		if ( $pivot_exist ) {
			
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated above; value is prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT object_id, COUNT(*) AS n FROM {$pivot} WHERE object_type = %s GROUP BY object_id", self::PIVOT_LIST ), ARRAY_A );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$counts[ (int) $row['object_id'] ] = (int) $row['n'];
				}
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name validated above, no user input.
		$list_rows = $wpdb->get_results( "SELECT id, title, slug, is_public FROM {$table} ORDER BY id", ARRAY_A );

		$lists = array();
		if ( is_array( $list_rows ) ) {
			foreach ( $list_rows as $row ) {
				$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$lists[] = array(
					'id'               => $id,
					'title'            => isset( $row['title'] ) ? (string) $row['title'] : '',
					'slug'             => isset( $row['slug'] ) ? (string) $row['slug'] : '',
					'is_public'        => ! empty( $row['is_public'] ),
					'subscriber_count' => $pivot_exist && isset( $counts[ $id ] ) ? $counts[ $id ] : 0,
				);
			}
		}

		$result = array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'lists'     => $lists,
			'count'     => count( $lists ),
		);
		if ( ! $pivot_exist ) {
			$result['subscriber_count_available'] = false;
		}
		return $result;
	}

	private static function get_tags() {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_tags';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'provider'  => 'fluentcrm',
				'available' => false,
				'message'   => 'FluentCRM tags table was not found; cannot read tags on this version.',
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name validated above, no user input.
		$rows = $wpdb->get_results( "SELECT id, title, slug FROM {$table} ORDER BY id", ARRAY_A );

		$tags = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$tags[] = array(
					'id'    => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
					'slug'  => isset( $row['slug'] ) ? (string) $row['slug'] : '',
				);
			}
		}

		return array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'tags'      => $tags,
			'count'     => count( $tags ),
		);
	}

	private static function list_contacts( array $args ) {
		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		$limit  = max( 1, min( self::MAX_CONTACTS, $limit ) );
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$status = '';
		if ( isset( $args['status'] ) && '' !== (string) $args['status'] ) {
			$status  = strtolower( trim( (string) $args['status'] ) );
			$allowed = self::contact_statuses();
			if ( ! in_array( $status, $allowed, true ) ) {
				throw new \Exception( esc_html( 'Unknown contact status. Use one of: ' . implode( ', ', $allowed ) . '.' ) );
			}
		}

		$tag_ids  = self::int_ids( $args['tag_ids'] ?? null );
		$list_ids = self::int_ids( $args['list_ids'] ?? null );
		if ( count( $tag_ids ) > self::MAX_IDS || count( $list_ids ) > self::MAX_IDS ) {
			throw new \Exception( esc_html( 'Filter by at most ' . self::MAX_IDS . ' tag ids and ' . self::MAX_IDS . ' list ids.' ) );
		}

		$search = isset( $args['search'] ) ? trim( self::clean_text( $args['search'] ) ) : '';
		if ( strlen( $search ) > 190 ) {
			$search = function_exists( 'mb_substr' ) ? mb_substr( $search, 0, 190 ) : substr( $search, 0, 190 );
		}

		$query_args = array(
			'with'      => array( 'tags', 'lists' ),
			'search'    => $search,
			'tags'      => $tag_ids,
			'lists'     => $list_ids,
			'statuses'  => '' !== $status ? array( $status ) : array(),
			'sort_by'   => 'id',
			'sort_type' => 'DESC',
			'limit'     => $limit,
			'offset'    => $offset,
		);

		$api   = self::contacts_api();
		$total = null;
		try {
			$count_query = $api->query( array_merge( $query_args, array( 'with' => array(), 'limit' => false, 'offset' => false ) ) );
			if ( is_object( $count_query ) && method_exists( $count_query, 'getModel' ) ) {
				$total = (int) $count_query->getModel()->count();
			}
		} catch ( \Throwable $e ) {
			$total = null;
		}

		$page = $api->query( $query_args );
		if ( ! is_object( $page ) || ! method_exists( $page, 'get' ) ) {
			throw new \Exception( 'This FluentCRM version does not offer the contact query API (FluentCrmApi(\'contacts\')->query()).' );
		}

		$contacts = array();
		foreach ( self::iterable( $page->get() ) as $contact ) {
			if ( ! is_object( $contact ) ) {
				continue;
			}
			$row             = self::contact_identity( $contact );
			$row['tag_ids']  = array_column( self::relation_rows( $contact, 'tag' ), 'id' );
			$row['list_ids'] = array_column( self::relation_rows( $contact, 'list' ), 'id' );
			$contacts[]      = $row;
		}

		return array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'total'     => $total,
			'count'     => count( $contacts ),
			'limit'     => $limit,
			'offset'    => $offset,
			'contacts'  => $contacts,
		);
	}

	private static function get_contact( array $args ) {
		$contact = self::resolve_contact( $args );

		$row = self::contact_identity( $contact );
		$row['source']        = self::attr_string( $contact, 'source' );
		$row['user_id']       = self::attr( $contact, 'user_id' ) ? (int) self::attr( $contact, 'user_id' ) : null;
		$row['updated_at']    = self::attr_date( $contact, 'updated_at' );
		$row['last_activity'] = self::attr_date( $contact, 'last_activity' );
		$row['tags']          = self::relation_rows( $contact, 'tag' );
		$row['lists']         = self::relation_rows( $contact, 'list' );

		return array(
			'provider'  => 'fluentcrm',
			'available' => true,
			'contact'   => $row,
		);
	}

	private static function change_membership( array $args, $kind, $verb ) {
		$plural     = $kind . 's';
		$contact_id = isset( $args['contact_id'] ) ? (int) $args['contact_id'] : 0;
		if ( $contact_id <= 0 ) {
			throw new \Exception( 'contact_id is required.' );
		}
		$requested = self::int_ids( $args[ $kind . '_ids' ] ?? null );
		if ( empty( $requested ) ) {
			throw new \Exception( esc_html( $kind . '_ids must list at least one ' . $kind . ' id.' ) );
		}
		if ( count( $requested ) > self::MAX_IDS ) {
			throw new \Exception( esc_html( 'At most ' . self::MAX_IDS . ' ' . $kind . ' ids per call.' ) );
		}

		$api     = self::contacts_api();
		$contact = $api->getContact( $contact_id );
		if ( ! is_object( $contact ) ) {
			throw new \Exception( esc_html( 'No FluentCRM contact with id ' . $contact_id . '.' ) );
		}

		$before  = array_column( self::relation_rows( $contact, $kind ), 'id' );
		$unknown = array();
		$skipped = array();

		if ( 'add' === $verb ) {

			
			$known   = self::existing_ids( $kind, $requested );
			$unknown = array_values( array_diff( $requested, $known ) );
			if ( empty( $known ) ) {
				throw new \Exception( esc_html( 'None of the given ' . $kind . ' ids exist. See ' . ( 'tag' === $kind ? 'crm_get_tags' : 'crm_get_lists' ) . '.' ) );
			}
			$skipped = array_values( array_intersect( $known, $before ) );
			$change  = array_values( array_diff( $known, $before ) );
			if ( $change ) {
				$method = 'tag' === $kind ? 'attachTags' : 'attachLists';
				$contact->{$method}( $change );
			}
		} else {
			$skipped = array_values( array_diff( $requested, $before ) );
			$change  = array_values( array_intersect( $requested, $before ) );
			if ( $change ) {
				$method = 'tag' === $kind ? 'detachTags' : 'detachLists';
				$contact->{$method}( $change );
			}
		}

		$fresh      = $api->getContact( $contact_id );
		$after_rows = is_object( $fresh ) ? self::relation_rows( $fresh, $kind ) : null;

		if ( null === $after_rows ) {
			$state = Write_Verify::UNKNOWN;
		} else {
			$after = array_column( $after_rows, 'id' );
			if ( 'add' === $verb ) {
				$state = Write_Verify::state_of_presence( ! array_diff( $change, $after ) );
			} else {
				$state = Write_Verify::state_of_presence( ! array_intersect( $change, $after ) );
			}
		}

		$result = array(
			'provider'   => 'fluentcrm',
			'contact_id' => $contact_id,
			( 'add' === $verb ? 'added' : 'removed' ) => $change,
			( 'add' === $verb ? 'already_present' : 'not_present' ) => $skipped,
		);
		if ( 'add' === $verb ) {
			$result['unknown_ids'] = $unknown;
		}
		$result[ $plural ] = $after_rows;

		return array_merge( $result, Write_Verify::report( $state, 'contact ' . $kind . ' membership' ) );
	}

	private static function resolve_contact( array $args ) {
		$api = self::contacts_api();
		if ( ! empty( $args['contact_id'] ) ) {
			$contact = $api->getContact( (int) $args['contact_id'] );
		} elseif ( isset( $args['email'] ) && '' !== trim( (string) $args['email'] ) ) {
			$email = sanitize_email( (string) $args['email'] );
			if ( '' === $email || ! is_email( $email ) ) {
				throw new \Exception( 'email is not a valid email address.' );
			}
			$contact = $api->getContact( $email );
		} else {
			throw new \Exception( 'contact_id or email is required.' );
		}
		if ( ! is_object( $contact ) ) {
			throw new \Exception( 'No FluentCRM contact matches that id or email.' );
		}
		return $contact;
	}

	private static function contacts_api() {
		if ( ! function_exists( 'FluentCrmApi' ) ) {
			throw new \Exception( 'This FluentCRM version does not offer its PHP API (FluentCrmApi).' );
		}
		try {
			$api = FluentCrmApi( 'contacts' );
		} catch ( \Throwable $e ) {
			$api = null;
		}
		if ( ! is_object( $api ) ) {
			throw new \Exception( 'This FluentCRM version does not offer the contacts API (FluentCrmApi(\'contacts\')).' );
		}
		return $api;
	}

	private static function contact_identity( $contact ) {
		return array(
			'id'           => (int) self::attr( $contact, 'id' ),
			'email'        => self::attr_string( $contact, 'email' ),
			'first_name'   => self::attr_string( $contact, 'first_name' ),
			'last_name'    => self::attr_string( $contact, 'last_name' ),
			'status'       => self::attr_string( $contact, 'status' ),
			'contact_type' => self::attr_string( $contact, 'contact_type' ),
			'created_at'   => self::attr_date( $contact, 'created_at' ),
		);
	}

	private static function relation_rows( $contact, $kind ) {
		$relation = 'tag' === $kind ? 'tags' : 'lists';
		$items    = self::attr( $contact, $relation );
		$rows     = array();
		foreach ( self::iterable( $items ) as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$rows[] = array(
				'id'    => (int) self::attr( $item, 'id' ),
				'title' => self::attr_string( $item, 'title' ),
				'slug'  => self::attr_string( $item, 'slug' ),
			);
		}
		return $rows;
	}

	private static function existing_ids( $kind, array $ids ) {
		global $wpdb;
		if ( empty( $ids ) ) {
			return array();
		}
		$table        = $wpdb->prefix . ( 'tag' === $kind ? 'fc_tags' : 'fc_lists' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name; values are prepared.
		$found = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
		$found = is_array( $found ) ? array_map( 'intval', $found ) : array();
		return array_values( array_intersect( $ids, $found ) );
	}

	private static function contact_statuses() {
		$statuses = function_exists( 'fluentcrm_subscriber_statuses' ) ? fluentcrm_subscriber_statuses() : null;
		if ( ! is_array( $statuses ) || empty( $statuses ) ) {
			$statuses = array( 'subscribed', 'pending', 'unsubscribed', 'transactional', 'bounced', 'complained', 'spammed' );
		}
		return array_values( array_map( 'strval', $statuses ) );
	}

	private static function int_ids( $value ) {
		if ( null === $value || '' === $value ) {
			return array();
		}
		$ids = array();
		foreach ( (array) $value as $id ) {
			if ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) || ( is_float( $id ) && floor( $id ) === $id ) ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function clean_text( $value ) {
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}

	private static function attr( $object, $key ) {
		if ( ! is_object( $object ) ) {
			return null;
		}
		try {
			return $object->{$key} ?? null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function attr_string( $object, $key ) {
		$value = self::attr( $object, $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function attr_date( $object, $key ) {
		$value = self::attr( $object, $key );
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}
		return is_scalar( $value ) && '' !== (string) $value ? (string) $value : null;
	}

	private static function iterable( $value ) {
		if ( is_array( $value ) || $value instanceof \Traversable ) {
			return $value;
		}
		return array();
	}
}
