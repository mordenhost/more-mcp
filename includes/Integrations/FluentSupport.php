<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentSupport {

	private const STATUS_LABELS = array(
		'new'    => 'New',
		'active' => 'Active',
		'closed' => 'Closed',
	);

	private const PRIORITY_LABELS = array(
		'normal'   => 'Normal',
		'medium'   => 'Medium',
		'critical' => 'Critical',
	);

	public static function is_available() {
		return defined( 'FLUENT_SUPPORT_VERSION' )
			|| class_exists( '\FluentSupport\App\Services\Helper' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'fluentsupport' ),
			'capabilities' => array( 'helpdesk' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'fluentsupport_get_status',
				'description' => 'Read Fluent Support desk health: total tickets with breakdowns by status (new, active, closed), by agent-set priority, and by customer-set priority; per-mailbox ticket counts; conversation volume; mailbox, product, agent, and customer totals; and service-timing aggregates (average first-response seconds, average time-to-close seconds, resolved-ticket count, total responses). Returns aggregate counts only. It never returns a ticket, a ticket title or number, ticket or conversation content, an attachment, or any customer or agent identity (no name, email, IP, or address), and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'fluentsupport_get_mailboxes',
				'description' => 'Read Fluent Support desk configuration: the mailbox definitions tickets arrive into (id, name, slug, box type, default flag, and whether an inbound address and a mapped address are configured) and the product catalogue tickets are filed against (id, title, source, mailbox id). Configuration only. The mailbox email address itself is never returned, only whether one is set, and the mailbox settings blob is never read because on an IMAP-connected mailbox it holds mail credentials; a product description and its settings blob are likewise never returned. No ticket, customer, or agent data of any kind. Read-only diagnostic; cannot create, edit, or delete a mailbox or product.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'include_products' => array(
							'type'        => 'boolean',
							'description' => 'When true (default), include the product catalogue alongside the mailboxes.',
						),
						'limit'            => array(
							'type'        => 'integer',
							'description' => 'Maximum rows per list, applied to mailboxes and products independently (1-200). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use helpdesk tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Fluent Support is not active.' );
		}

		if ( 'fluentsupport_get_mailboxes' === $name ) {
			return self::get_mailboxes( is_array( $args ) ? $args : array() );
		}
		if ( 'fluentsupport_get_status' !== $name ) {
			throw new \Exception( 'Unknown Fluent Support tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function table_total( $table ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	private static function grouped_counts( $table, $column, $labels = null ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return null;
		}
		
		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$table} GROUP BY {$column}", ARRAY_A );

		$by    = array();
		$total = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$raw   = isset( $row['k'] ) ? (string) $row['k'] : '';
				$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
				$key   = sanitize_key( '' === $raw ? 'unset' : $raw );
				if ( null !== $labels && isset( $labels[ $raw ] ) ) {
					$key = sanitize_key( $raw );
				}
				$by[ $key ] = isset( $by[ $key ] ) ? $by[ $key ] + $count : $count;
				$total     += $count;
			}
		}
		return array(
			'total' => $total,
			'by'    => $by,
		);
	}

	private static function status_labels() {
		$labels = self::plugin_vocabulary( 'ticketStatuses' );
		return null === $labels ? self::STATUS_LABELS : $labels;
	}

	private static function priority_labels() {
		$labels = self::plugin_vocabulary( 'adminTicketPriorities' );
		return null === $labels ? self::PRIORITY_LABELS : $labels;
	}

	private static function plugin_vocabulary( $method ) {
		$helper = '\FluentSupport\App\Services\Helper';
		if ( ! class_exists( $helper ) || ! method_exists( $helper, $method ) ) {
			return null;
		}
		$labels = call_user_func( array( $helper, $method ) );
		if ( ! is_array( $labels ) || empty( $labels ) ) {
			return null;
		}
		$clean = array();
		foreach ( $labels as $key => $label ) {
			$clean[ (string) $key ] = is_scalar( $label ) ? (string) $label : (string) $key;
		}
		return $clean;
	}

	private static function timing_aggregates( $tickets_table ) {
		global $wpdb;
		if ( ! self::table_exists( $tickets_table ) ) {
			return null;
		}

		
		$row = $wpdb->get_row(
			"SELECT
				AVG(first_response_time) AS avg_first_response,
				AVG(total_close_time) AS avg_total_close,
				SUM(response_count) AS total_responses,
				SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved_total
			FROM {$tickets_table}",
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$avg_first = isset( $row['avg_first_response'] ) && null !== $row['avg_first_response']
			? (int) round( (float) $row['avg_first_response'] )
			: null;
		$avg_close = isset( $row['avg_total_close'] ) && null !== $row['avg_total_close']
			? (int) round( (float) $row['avg_total_close'] )
			: null;

		$timing = array(
			'resolved_total'  => isset( $row['resolved_total'] ) ? (int) $row['resolved_total'] : 0,
			'total_responses' => isset( $row['total_responses'] ) ? (int) $row['total_responses'] : 0,
		);

		if ( null !== $avg_first ) {
			$timing['avg_first_response_seconds'] = $avg_first;
		}
		if ( null !== $avg_close ) {
			$timing['avg_total_close_seconds'] = $avg_close;
		}

		return $timing;
	}

	private static function get_status() {
		$tickets_table = self::table( 'fs_tickets' );
		$persons_table = self::table( 'fs_persons' );

		$by_status          = self::grouped_counts( $tickets_table, 'status', self::status_labels() );
		$by_priority        = self::grouped_counts( $tickets_table, 'priority', self::priority_labels() );
		$by_client_priority = self::grouped_counts( $tickets_table, 'client_priority', self::priority_labels() );
		$by_mailbox         = self::grouped_counts( $tickets_table, 'mailbox_id' );
		$persons            = self::grouped_counts( $persons_table, 'person_type' );
		$conversations      = self::table_total( self::table( 'fs_conversations' ) );
		$mailboxes          = self::table_total( self::table( 'fs_mail_boxes' ) );
		$products           = self::table_total( self::table( 'fs_products' ) );
		$timing             = self::timing_aggregates( $tickets_table );

		if ( null === $by_status && null === $persons && null === $mailboxes && null === $conversations ) {
			return array(
				'provider'  => 'fluentsupport',
				'available' => false,
				'message'   => 'Fluent Support tables were not found; cannot read desk counts on this version.',
			);
		}

		$result = array(
			'provider'  => 'fluentsupport',
			'available' => true,
		);

		if ( null !== $by_status ) {
			$tickets = array(
				'total'     => $by_status['total'],
				'by_status' => $by_status['by'],
			);
			if ( null !== $by_priority ) {
				$tickets['by_priority'] = $by_priority['by'];
			}
			if ( null !== $by_client_priority ) {
				$tickets['by_client_priority'] = $by_client_priority['by'];
			}
			if ( null !== $by_mailbox ) {

				
				$tickets['by_mailbox_id'] = $by_mailbox['by'];
			}
			$result['tickets'] = $tickets;
		}

		if ( null !== $persons ) {

			$people = array( 'total' => $persons['total'] );
			foreach ( $persons['by'] as $type => $count ) {
				$people[ $type ] = $count;
			}
			$result['people'] = $people;
		}

		if ( null !== $conversations ) {
			
			$result['conversations'] = array( 'total' => $conversations );
		}
		if ( null !== $mailboxes ) {
			$result['mailboxes'] = array( 'total' => $mailboxes );
		}
		if ( null !== $products ) {
			$result['products'] = array( 'total' => $products );
		}
		if ( null !== $timing ) {
			$result['timing'] = $timing;
		}

		return $result;
	}

	private static function get_mailboxes( $args ) {
		global $wpdb;

		$mailbox_table = self::table( 'fs_mail_boxes' );
		$product_table = self::table( 'fs_products' );

		$has_mailboxes = self::table_exists( $mailbox_table );
		$has_products  = self::table_exists( $product_table );

		if ( ! $has_mailboxes && ! $has_products ) {
			return array(
				'provider'  => 'fluentsupport',
				'available' => false,
				'message'   => 'Fluent Support mailbox and product tables were not found; cannot read desk configuration on this version.',
			);
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}

		$include_products = ! isset( $args['include_products'] ) || (bool) $args['include_products'];

		$result = array(
			'provider'  => 'fluentsupport',
			'available' => true,
		);

		if ( $has_mailboxes ) {

			
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, slug, box_type, is_default,
						CASE WHEN email IS NULL OR email = '' THEN 0 ELSE 1 END AS has_email,
						CASE WHEN mapped_email IS NULL OR mapped_email = '' THEN 0 ELSE 1 END AS has_mapped_email
					FROM {$mailbox_table} ORDER BY id ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			);

			$list = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$list[] = array(
						'id'                      => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'name'                    => isset( $row['name'] ) ? (string) $row['name'] : '',
						'slug'                    => isset( $row['slug'] ) ? (string) $row['slug'] : '',
						'box_type'                => isset( $row['box_type'] ) ? (string) $row['box_type'] : '',
						'is_default'              => isset( $row['is_default'] ) && 'yes' === $row['is_default'],
						'inbound_email_configured' => ! empty( $row['has_email'] ),
						'mapped_email_configured' => ! empty( $row['has_mapped_email'] ),
					);
				}
			}
			$result['mailboxes'] = $list;
		}

		if ( $include_products && $has_products ) {

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title, source, mailbox_id FROM {$product_table} ORDER BY id ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			);

			$list = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$list[] = array(
						'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
						'source'     => isset( $row['source'] ) ? (string) $row['source'] : '',
						'mailbox_id' => isset( $row['mailbox_id'] ) ? (int) $row['mailbox_id'] : 0,
					);
				}
			}
			$result['products'] = $list;
		}

		return $result;
	}
}
