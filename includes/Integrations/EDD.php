<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EDD {

	const DOWNLOAD_CPT = 'download';

	const ORDER_STATUSES = array(
		'pending',
		'processing',
		'complete',
		'refunded',
		'partially_refunded',
		'revoked',
		'failed',
		'abandoned',
		'on_hold',
	);

	public static function is_available() {
		if ( class_exists( 'Easy_Digital_Downloads' ) || function_exists( 'EDD' ) || defined( 'EDD_VERSION' ) ) {
			return true;
		}

		if ( function_exists( 'post_type_exists' ) && function_exists( 'edd_get_order_counts' ) ) {
			return post_type_exists( self::DOWNLOAD_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'edd' ),
			'capabilities' => array( 'commerce' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'edd_get_status',
				'description' => 'Read Easy Digital Downloads store scale: download counts by post status, order counts by payment status (pending, complete, refunded, failed, etc.), total customers, lifetime earnings, and the store currency. Aggregate counts and totals only, never an order record, a purchase, or a customer identity, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'edd_get_download_catalog',
				'description' => 'List the published Easy Digital Downloads products (by page): each download\'s id, title, type (single or bundle), price or variable-price range, variable-price tier count, and file count (count only, never file URLs). Product DEFINITIONS only — no order or purchase data, no customer data, no downloadable file URLs or license keys. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page of downloads to return (1-indexed). Default 1.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Downloads per page (1-100). Default 50.',
						),
					),
				),
			),
			array(
				'name'        => 'edd_get_discount_catalog',
				'description' => 'List the store\'s discount codes (by page): each discount\'s name, code, status (active/inactive), amount type (percent or flat), amount, scope, use count, max uses, and start/end dates. Discount DEFINITIONS only — never a redemption, an order, a purchase, or a customer identity. Read-only; cannot modify the catalogue.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page of discounts to return (1-indexed). Default 1.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Discounts per page (1-100). Default 50.',
						),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use commerce tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Easy Digital Downloads is not active.' );
		}

		if ( 'edd_get_download_catalog' === $name ) {
			return self::get_download_catalog( $args );
		}
		if ( 'edd_get_discount_catalog' === $name ) {
			return self::get_discount_catalog( $args );
		}
		if ( 'edd_get_status' !== $name ) {
			throw new \Exception( 'Unknown commerce tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function get_status() {
		
		$downloads_by_status = array();
		$downloads_total     = 0;
		if ( function_exists( 'wp_count_posts' ) ) {
			$counts = wp_count_posts( self::DOWNLOAD_CPT );
			foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
				$n                            = isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
				$downloads_by_status[ $status ] = $n;
				$downloads_total             += $n;
			}
		}

		
		
		$orders_by_status = array();
		$orders_total     = null;
		if ( function_exists( 'edd_get_order_counts' ) ) {
			$raw = edd_get_order_counts();
			if ( is_array( $raw ) && ! empty( $raw ) ) {
				foreach ( self::ORDER_STATUSES as $status ) {
					$orders_by_status[ $status ] = isset( $raw[ $status ] ) ? (int) $raw[ $status ] : 0;
				}
				$orders_total = isset( $raw['total'] ) ? (int) $raw['total'] : array_sum( $orders_by_status );
			}
		}

		return array(
			'provider'            => 'edd',
			'downloads_total'     => $downloads_total,
			'downloads_by_status' => $downloads_by_status,
			'orders_total'        => $orders_total,
			'orders_by_status'    => empty( $orders_by_status ) ? null : $orders_by_status,
			'customers_total'     => function_exists( 'edd_count_customers' ) ? (int) edd_count_customers() : null,
			'lifetime_earnings'   => function_exists( 'edd_get_total_earnings' ) ? (float) edd_get_total_earnings() : null,
			'currency'            => function_exists( 'edd_get_currency' ) ? (string) edd_get_currency() : null,
		);
	}

	private static function get_download_catalog( $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		if ( $per_page < 1 ) {
			$per_page = 50;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider'        => 'edd',
				'page'            => $page,
				'per_page'        => $per_page,
				'downloads_total' => 0,
				'download_count'  => 0,
				'downloads'       => array(),
			);
		}

		$q = new \WP_Query(
			array(
				'post_type'      => self::DOWNLOAD_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$ids   = is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : array();
		$total = isset( $q->found_posts ) ? (int) $q->found_posts : count( $ids );

		$downloads = array();
		foreach ( $ids as $id ) {
			$downloads[] = self::download_summary( $id );
		}

		return array(
			'provider'        => 'edd',
			'page'            => $page,
			'per_page'        => $per_page,
			'downloads_total' => $total,
			'download_count'  => count( $downloads ),
			'downloads'       => $downloads,
		);
	}

	private static function get_discount_catalog( $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		if ( $per_page < 1 ) {
			$per_page = 50;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		if ( ! function_exists( 'edd_get_discounts' ) ) {

			
			
			return array(
				'provider'        => 'edd',
				'page'            => $page,
				'per_page'        => $per_page,
				'discounts_total' => 0,
				'discount_count'  => 0,
				'discounts'       => array(),
			);
		}

		

		$discounts_raw = edd_get_discounts(
			array(
				'number' => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);
		if ( ! is_array( $discounts_raw ) ) {
			$discounts_raw = array();
		}

		$discounts = array();
		foreach ( $discounts_raw as $discount ) {
			if ( ! is_object( $discount ) && ! is_array( $discount ) ) {
				continue;
			}
			$discounts[] = self::discount_summary( $discount );
		}

		return array(
			'provider'        => 'edd',
			'page'            => $page,
			'per_page'        => $per_page,
			'discounts_total' => count( $discounts ),
			'discount_count'  => count( $discounts ),
			'discounts'       => $discounts,
		);
	}

	private static function discount_summary( $discount ) {
		$get = function ( $field ) use ( $discount ) {
			if ( is_object( $discount ) ) {
				
				return isset( $discount->{$field} ) ? $discount->{$field} : null;
			}
			return isset( $discount[ $field ] ) ? $discount[ $field ] : null;
		};

		return array(
			'name'        => (string) $get( 'name' ),
			'code'        => (string) $get( 'code' ),
			'status'      => (string) $get( 'status' ),
			'amount_type' => (string) $get( 'amount_type' ),
			'amount'      => self::to_amount( $get( 'amount' ) ),
			'scope'       => (string) $get( 'scope' ),
			'use_count'   => (int) $get( 'use_count' ),
			'max_uses'    => (int) $get( 'max_uses' ),
			'start_date'  => self::nullable_string( $get( 'start_date' ) ),
			'end_date'    => self::nullable_string( $get( 'end_date' ) ),
		);
	}

	private static function nullable_string( $value ) {
		return ( null === $value || '' === $value ) ? null : (string) $value;
	}

	private static function download_summary( $id ) {
		$summary = array(
			'download_id'         => (int) $id,
			'title'               => (string) get_the_title( $id ),
			'type'                => 'default',
			'has_variable_prices' => false,
			'price'               => null,
			'price_min'           => null,
			'price_max'           => null,
			'variable_price_tiers' => 0,
			'file_count'          => 0,
		);

		if ( ! function_exists( 'edd_get_download' ) ) {
			return $summary;
		}
		$download = edd_get_download( $id );
		if ( ! is_object( $download ) ) {
			return $summary;
		}

		if ( method_exists( $download, 'get_type' ) ) {
			$type = (string) $download->get_type();
			if ( '' !== $type ) {
				$summary['type'] = $type;
			}
		}

		$is_variable = method_exists( $download, 'has_variable_prices' ) && $download->has_variable_prices();
		$summary['has_variable_prices'] = (bool) $is_variable;

		if ( $is_variable && method_exists( $download, 'get_prices' ) ) {
			$prices  = $download->get_prices();
			$amounts = array();
			if ( is_array( $prices ) ) {
				foreach ( $prices as $row ) {
					if ( is_array( $row ) && isset( $row['amount'] ) && '' !== $row['amount'] ) {
						$amounts[] = self::to_amount( $row['amount'] );
					}
				}
			}
			$summary['variable_price_tiers'] = count( $amounts );
			if ( ! empty( $amounts ) ) {
				$summary['price_min'] = min( $amounts );
				$summary['price_max'] = max( $amounts );
			}
		} elseif ( method_exists( $download, 'get_price' ) ) {
			$summary['price'] = self::to_amount( $download->get_price() );
		}

		if ( method_exists( $download, 'get_files' ) ) {
			$files = $download->get_files();
			$summary['file_count'] = is_array( $files ) ? count( $files ) : 0;
		}

		return $summary;
	}

	private static function to_amount( $value ) {
		if ( function_exists( 'edd_sanitize_amount' ) ) {
			return (float) edd_sanitize_amount( $value );
		}
		return (float) $value;
	}
}
