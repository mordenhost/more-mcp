<?php


namespace More_MCP\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coupon_Handler {

	public static function supports( $name ) {
		static $names = array( 'wc_get_coupons', 'wc_get_coupon', 'wc_get_coupon_count', 'wc_create_coupon', 'wc_update_coupon', 'wc_delete_coupon', 'wc_empty_coupon_trash' );
		return in_array( $name, $names, true );
	}

	public static function execute_tool( $name, $args ) {
		switch ( $name ) {
		case 'wc_get_coupons':
			$per_page        = min( intval( $args['per_page'] ?? 10 ), 100 );
			$paged           = max( intval( $args['page'] ?? 1 ), 1 );
			$allowed_status  = [ 'publish', 'draft', 'trash', 'any' ];
			$status          = in_array( $args['status'] ?? 'publish', $allowed_status, true ) ? ( $args['status'] ?? 'publish' ) : 'publish';
			$query_args      = [
				'post_type'      => 'shop_coupon',
				'post_status'    => $status,
				'posts_per_page' => $per_page,
				'paged'          => $paged,
			];
			if ( ! empty( $args['search'] ) ) {
				$query_args['s'] = sanitize_text_field( $args['search'] );
			}
			$posts = get_posts( $query_args );
			return array_map( function( $post ) {
				return self::format_coupon_summary( new \WC_Coupon( $post->ID ) );
			}, $posts );

		case 'wc_get_coupon':
			if ( isset( $args['id'] ) ) {
				$id = intval( $args['id'] );
				if ( $id <= 0 ) {
					throw new \Exception( 'Invalid coupon ID' );
				}
				$coupon = new \WC_Coupon( $id );
			} elseif ( isset( $args['code'] ) ) {
				$coupon = new \WC_Coupon( sanitize_text_field( $args['code'] ) );
			} else {
				throw new \Exception( 'id or code is required' );
			}
			if ( ! $coupon->get_id() || get_post_type( $coupon->get_id() ) !== 'shop_coupon' ) {
				throw new \Exception( 'Coupon not found' );
			}
			return self::format_coupon_detail( $coupon );

		case 'wc_get_coupon_count':
			$counts = wp_count_posts( 'shop_coupon' );
			return [
				'publish' => (int) $counts->publish,
				'draft'   => (int) $counts->draft,
				'trash'   => (int) $counts->trash,
			];

		case 'wc_create_coupon':
			$code = strtolower( sanitize_text_field( $args['code'] ?? '' ) );
			if ( empty( $code ) ) {
				throw new \Exception( 'Coupon code is required' );
			}
			
			
			
			
			if ( wc_get_coupon_id_by_code( $code ) ) {
				throw new \Exception( 'A coupon with this code already exists' );
			}
			$coupon = new \WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'fixed_cart' ); 
			self::apply_coupon_fields( $coupon, $args );
			$coupon_id = $coupon->save();
			if ( ! $coupon_id ) {
				throw new \Exception( 'Failed to create coupon' );
			}
			return [ 'id' => $coupon_id, 'code' => $code, 'message' => 'Coupon created successfully' ];

		case 'wc_update_coupon':
			$id = intval( $args['id'] );
			if ( $id <= 0 ) {
				throw new \Exception( 'Invalid coupon ID' );
			}
			$coupon = new \WC_Coupon( $id );
			if ( ! $coupon->get_id() || get_post_type( $coupon->get_id() ) !== 'shop_coupon' ) {
				throw new \Exception( 'Coupon not found' );
			}
			if ( isset( $args['code'] ) ) {
				$new_code = strtolower( sanitize_text_field( $args['code'] ) );
				$existing = wc_get_coupon_id_by_code( $new_code );
				if ( $existing && $existing !== $coupon->get_id() ) {
					throw new \Exception( 'A coupon with this code already exists' );
				}
				$coupon->set_code( $new_code );
			}
			self::apply_coupon_fields( $coupon, $args );
			$coupon->save();
			return [ 'id' => $coupon->get_id(), 'message' => 'Coupon updated successfully' ];

		case 'wc_delete_coupon':
			$id = intval( $args['id'] );
			if ( $id <= 0 ) {
				throw new \Exception( 'Invalid coupon ID' );
			}
			$coupon = new \WC_Coupon( $id );
			if ( ! $coupon->get_id() || get_post_type( $coupon->get_id() ) !== 'shop_coupon' ) {
				throw new \Exception( 'Coupon not found' );
			}
			$force       = isset( $args['force'] ) ? (bool) $args['force'] : false;
			$coupon_id   = $coupon->get_id();
			$post_status = get_post_status( $coupon_id );
			if ( ! $force && 'trash' === $post_status ) {
				return [ 'id' => $coupon_id, 'message' => 'Coupon is already in trash' ];
			}
			$result  = wp_delete_post( $coupon_id, $force );
			if ( ! $result ) {
				throw new \Exception( 'Failed to delete coupon' );
			}
			$message = $force ? 'Coupon permanently deleted' : 'Coupon moved to trash';
			return [ 'id' => $coupon_id, 'message' => $message ];

		case 'wc_empty_coupon_trash':
			$trashed = get_posts( [
				'post_type'      => 'shop_coupon',
				'post_status'    => 'trash',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );
			if ( empty( $trashed ) ) {
				return [ 'deleted' => 0, 'message' => 'Coupon trash is empty' ];
			}
			$deleted = 0;
			foreach ( $trashed as $post_id ) {
				if ( wp_delete_post( intval( $post_id ), true ) ) {
					$deleted++;
				}
			}
			return [ 'deleted' => (int) $deleted, 'message' => 'Permanently deleted ' . (int) $deleted . ' coupon(s) from trash' ];

			default:
				throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
		}
	}

	private static function format_coupon_summary( $coupon ) {
		return [
			'id'            => $coupon->get_id(),
			'code'          => $coupon->get_code(),
			'discount_type' => $coupon->get_discount_type(),
			'amount'        => $coupon->get_amount(),
			'usage_count'   => $coupon->get_usage_count(),
			'usage_limit'   => $coupon->get_usage_limit(),
			'date_expires'  => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d' ) : null,
		];
	}

	private static function format_coupon_detail( $coupon ) {
		return [
			'id'                          => $coupon->get_id(),
			'code'                        => $coupon->get_code(),
			'description'                 => $coupon->get_description(),
			'discount_type'               => $coupon->get_discount_type(),
			'amount'                      => $coupon->get_amount(),
			'individual_use'              => $coupon->get_individual_use(),
			'product_ids'                 => $coupon->get_product_ids(),
			'excluded_product_ids'        => $coupon->get_excluded_product_ids(),
			'usage_limit'                 => $coupon->get_usage_limit(),
			'usage_limit_per_user'        => $coupon->get_usage_limit_per_user(),
			'limit_usage_to_x_items'      => $coupon->get_limit_usage_to_x_items(),
			'usage_count'                 => $coupon->get_usage_count(),
			'free_shipping'               => $coupon->get_free_shipping(),
			'product_categories'          => $coupon->get_product_categories(),
			'excluded_product_categories' => $coupon->get_excluded_product_categories(),
			'exclude_sale_items'          => $coupon->get_exclude_sale_items(),
			'minimum_amount'              => $coupon->get_minimum_amount(),
			'maximum_amount'              => $coupon->get_maximum_amount(),
			'email_restrictions'          => $coupon->get_email_restrictions(),
			'date_expires'                => $coupon->get_date_expires() ? $coupon->get_date_expires()->format( 'Y-m-d H:i:s' ) : null,
			'date_created'                => $coupon->get_date_created() ? $coupon->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			'date_modified'               => $coupon->get_date_modified() ? $coupon->get_date_modified()->format( 'Y-m-d H:i:s' ) : null,
		];
	}

	private static function apply_coupon_fields( $coupon, $args ) {
		$allowed_types = [ 'percent', 'fixed_cart', 'fixed_product' ];
		if ( isset( $args['discount_type'] ) && in_array( $args['discount_type'], $allowed_types, true ) ) {
			$coupon->set_discount_type( $args['discount_type'] );
		}
		if ( isset( $args['amount'] ) ) {
			$coupon->set_amount( sanitize_text_field( $args['amount'] ) );
		}
		if ( isset( $args['description'] ) ) {
			
			
			$coupon->set_description( wp_kses_post( $args['description'] ) );
		}
		if ( isset( $args['date_expires'] ) ) {
			$raw = sanitize_text_field( $args['date_expires'] );
			if ( '' === $raw ) {
				$coupon->set_date_expires( null ); 
			} else {
				$timestamp = strtotime( $raw );
				if ( false === $timestamp ) {
					throw new \Exception( 'Invalid date_expires format' );
				}
				$coupon->set_date_expires( $timestamp );
			}
		}
		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( intval( $args['usage_limit'] ) );
		}
		if ( isset( $args['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( intval( $args['usage_limit_per_user'] ) );
		}
		if ( isset( $args['limit_usage_to_x_items'] ) ) {
			$coupon->set_limit_usage_to_x_items( intval( $args['limit_usage_to_x_items'] ) );
		}
		if ( isset( $args['individual_use'] ) ) {
			$coupon->set_individual_use( (bool) $args['individual_use'] );
		}
		if ( isset( $args['free_shipping'] ) ) {
			$coupon->set_free_shipping( (bool) $args['free_shipping'] );
		}
		if ( isset( $args['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( (bool) $args['exclude_sale_items'] );
		}
		if ( isset( $args['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( sanitize_text_field( $args['minimum_amount'] ) );
		}
		if ( isset( $args['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( sanitize_text_field( $args['maximum_amount'] ) );
		}
		if ( isset( $args['product_ids'] ) && is_array( $args['product_ids'] ) ) {
			$coupon->set_product_ids( array_values( array_filter( array_map( 'intval', $args['product_ids'] ), function( $v ) { return $v > 0; } ) ) );
		}
		if ( isset( $args['excluded_product_ids'] ) && is_array( $args['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( array_values( array_filter( array_map( 'intval', $args['excluded_product_ids'] ), function( $v ) { return $v > 0; } ) ) );
		}
		if ( isset( $args['product_categories'] ) && is_array( $args['product_categories'] ) ) {
			$coupon->set_product_categories( array_values( array_filter( array_map( 'intval', $args['product_categories'] ), function( $v ) { return $v > 0; } ) ) );
		}
		if ( isset( $args['excluded_product_categories'] ) && is_array( $args['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( array_values( array_filter( array_map( 'intval', $args['excluded_product_categories'] ), function( $v ) { return $v > 0; } ) ) );
		}
		if ( isset( $args['email_restrictions'] ) && is_array( $args['email_restrictions'] ) ) {
			$emails = array_values( array_filter( array_map( 'sanitize_email', $args['email_restrictions'] ), 'is_email' ) );
			$coupon->set_email_restrictions( $emails );
		}
	}
}
