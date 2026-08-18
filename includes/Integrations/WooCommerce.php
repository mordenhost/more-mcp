<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WooCommerce {

	
	public static function is_available() {
		return class_exists( 'WooCommerce' );
	}

	
	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'wc_get_products',
				'description' => 'Get WooCommerce products',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page'       => [ 'type' => 'integer', 'description' => 'Number of products (max 100)' ],
						'status'         => [ 'type' => 'string', 'description' => 'Product status (publish, draft, etc)' ],
						'category'       => [ 'type' => 'string', 'description' => 'Category slug to filter by' ],
						'search'         => [ 'type' => 'string', 'description' => 'Search term' ],
						'type'           => [ 'type' => 'string', 'description' => 'Product type (simple, variable, grouped, external)' ],
						'attribute'      => [ 'type' => 'string', 'description' => 'Global attribute taxonomy slug (e.g. pa_color from wc_get_product_attributes). Requires attribute_term.' ],
						'attribute_term' => [ 'type' => 'string', 'description' => 'Term slug or term_id within the attribute (e.g. black-color from wc_get_attribute_terms). Requires attribute.' ],
					],
				],
			],
			[
				'name'        => 'wc_get_product',
				'description' => 'Get single WooCommerce product by ID',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Product ID' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_create_product',
				'description' => 'Create a WooCommerce product',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'          => [ 'type' => 'string', 'description' => 'Product name' ],
						'type'          => [ 'type' => 'string', 'enum' => [ 'simple', 'variable', 'grouped', 'external' ] ],
						'regular_price' => [ 'type' => 'string', 'description' => 'Regular price' ],
						'sale_price'    => [ 'type' => 'string', 'description' => 'Sale price' ],
						'description'   => [ 'type' => 'string', 'description' => 'Full description' ],
						'short_description' => [ 'type' => 'string', 'description' => 'Short description' ],
						'sku'           => [ 'type' => 'string', 'description' => 'SKU' ],
						'status'        => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ] ],
						'stock_quantity' => [ 'type' => 'integer', 'description' => 'Stock quantity' ],
						'categories'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category IDs' ],
					],
					'required'   => [ 'name' ],
				],
			],
			[
				'name'        => 'wc_update_product',
				'description' => 'Update a WooCommerce product',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'            => [ 'type' => 'integer' ],
						'name'          => [ 'type' => 'string' ],
						'regular_price' => [ 'type' => 'string' ],
						'sale_price'    => [ 'type' => 'string' ],
						'description'   => [ 'type' => 'string' ],
						'short_description' => [ 'type' => 'string' ],
						'sku'           => [ 'type' => 'string' ],
						'status'        => [ 'type' => 'string' ],
						'stock_quantity' => [ 'type' => 'integer' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_get_orders',
				'description' => 'Get WooCommerce orders. Returns {orders, page, per_page, total, total_pages} — iterate page until page >= total_pages.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Number of orders per page (default 10, max 100)' ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number, 1-indexed (default 1)' ],
						'status'   => [ 'type' => 'string', 'description' => 'Order status (processing, completed, on-hold, etc)' ],
					],
				],
			],
			[
				'name'        => 'wc_get_order',
				'description' => 'Get single WooCommerce order by ID',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Order ID' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_update_order_status',
				'description' => 'Update WooCommerce order status. Optional note may contain safe HTML — displayed in the WC admin order timeline.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer', 'description' => 'Order ID' ],
						'status' => [ 'type' => 'string', 'description' => 'New status (processing, completed, on-hold, cancelled, refunded)' ],
						'note'   => [ 'type' => 'string', 'description' => 'Optional order note. May contain safe HTML (links, formatting).' ],
					],
					'required'   => [ 'id', 'status' ],
				],
			],
			[
				'name'        => 'wc_create_order',
				'description' => 'Create a WooCommerce order programmatically. Use for B2B, wholesale, phone orders, manual invoicing. Stock is decremented only when status transitions into processing/completed — create as pending, then update status to processing to trigger stock reduction. Order emails are NOT auto-fired; pass send_emails=true to trigger the New Order email.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'customer_id'    => [ 'type' => 'integer', 'description' => 'Optional WP user ID for the customer. Omit to create a guest order.' ],
						'billing'        => [ 'type' => 'object', 'description' => 'Billing address: first_name, last_name, address_1, address_2, city, state, postcode, country, email, phone.' ],
						'shipping'       => [ 'type' => 'object', 'description' => 'Shipping address (same shape as billing, minus email/phone).' ],
						'line_items'     => [ 'type' => 'array', 'description' => 'Array of {product_id, quantity, variation_id?}. variation_id must belong to product_id.' ],
						'status'         => [ 'type' => 'string', 'description' => 'Initial order status (default pending). Accepted: pending, processing, on-hold, completed, cancelled.' ],
						'payment_method' => [ 'type' => 'string', 'description' => 'Payment method ID (e.g. bacs, cheque, cod, stripe).' ],
						'shipping_lines' => [ 'type' => 'array', 'description' => 'Optional shipping lines. Array of {method_id, method_title, total}.' ],
						'fee_lines'      => [ 'type' => 'array', 'description' => 'Optional fee lines. Array of {name, total}.' ],
						'meta_data'      => [ 'type' => 'array', 'description' => 'Optional custom order meta. Array of {key, value}.' ],
						'customer_note'  => [ 'type' => 'string', 'description' => 'Customer-facing note attached to the order.' ],
						'send_emails'    => [ 'type' => 'boolean', 'description' => 'If true, fire the WC New Order email after creation. Default false.' ],
					],
					'required'   => [ 'line_items' ],
				],
			],
			[
				'name'        => 'wc_update_order',
				'description' => 'Update an existing WooCommerce order. All fields except order_id are optional. line_items with an id update or remove (quantity 0) existing items; line_items without an id add new items. Recalculates totals after mutation.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'order_id'      => [ 'type' => 'integer', 'description' => 'Order ID to update.' ],
						'billing'       => [ 'type' => 'object', 'description' => 'Partial billing address — only provided keys are updated.' ],
						'shipping'      => [ 'type' => 'object', 'description' => 'Partial shipping address — only provided keys are updated.' ],
						'customer_note' => [ 'type' => 'string', 'description' => 'Replace customer-facing order note.' ],
						'status'        => [ 'type' => 'string', 'description' => 'New order status.' ],
						'meta_data'     => [ 'type' => 'array', 'description' => 'Array of {key, value} to add/replace on the order meta.' ],
						'line_items'    => [ 'type' => 'array', 'description' => 'Array of {product_id, quantity, variation_id?, id?}. id present + quantity 0 = remove; id present + quantity > 0 = update; no id = add.' ],
					],
					'required'   => [ 'order_id' ],
				],
			],
			[
				'name'        => 'wc_add_order_note',
				'description' => 'Add a note to a WooCommerce order. Private notes are internal (staff timeline only). Customer notes are emailed to the customer and shown on their order view. Content may contain safe HTML (links, formatting) — sanitized via wp_kses_post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'order_id'      => [ 'type' => 'integer', 'description' => 'Order ID.' ],
						'note'          => [ 'type' => 'string', 'description' => 'Note content. May contain safe HTML.' ],
						'customer_note' => [ 'type' => 'boolean', 'description' => 'If true, note is emailed to the customer. Default false (private/internal note).' ],
					],
					'required'   => [ 'order_id', 'note' ],
				],
			],
			[
				'name'        => 'wc_get_customers',
				'description' => 'Get WooCommerce customers',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'description' => 'Number of customers (max 100)' ],
						'search'   => [ 'type' => 'string', 'description' => 'Search by name or email' ],
					],
				],
			],
			[
				'name'        => 'wc_get_store_stats',
				'description' => 'Get WooCommerce store statistics (revenue, orders, products)',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'period' => [ 'type' => 'string', 'description' => 'Period: today, week, month, year', 'enum' => [ 'today', 'week', 'month', 'year' ] ],
					],
				],
			],
			[
				'name'        => 'wc_get_product_variations',
				'description' => 'Get all variations for a variable WooCommerce product',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id' => [ 'type' => 'integer', 'description' => 'Parent variable product ID' ],
						'per_page'   => [ 'type' => 'integer', 'description' => 'Number of variations to return (max 100)' ],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'name'        => 'wc_get_variation',
				'description' => 'Get a single product variation by ID',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'   => [ 'type' => 'integer', 'description' => 'Parent product ID' ],
						'variation_id' => [ 'type' => 'integer', 'description' => 'Variation ID' ],
					],
					'required'   => [ 'product_id', 'variation_id' ],
				],
			],
			[
				'name'        => 'wc_create_variation',
				'description' => 'Create a new variation for a variable product',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'     => [ 'type' => 'integer', 'description' => 'Parent variable product ID' ],
						'attributes'     => [
							'type'        => 'array',
							'description' => 'Variation attributes, e.g. [{"name":"color","option":"red"}]',
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'name'   => [ 'type' => 'string' ],
									'option' => [ 'type' => 'string' ],
								],
							],
						],
						'regular_price'  => [ 'type' => 'string', 'description' => 'Regular price' ],
						'sale_price'     => [ 'type' => 'string', 'description' => 'Sale price' ],
						'sku'            => [ 'type' => 'string', 'description' => 'SKU' ],
						'status'         => [ 'type' => 'string', 'enum' => [ 'publish', 'private' ] ],
						'manage_stock'   => [ 'type' => 'boolean', 'description' => 'Enable stock management' ],
						'stock_quantity' => [ 'type' => 'integer', 'description' => 'Stock quantity' ],
						'stock_status'   => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder' ] ],
						'weight'         => [ 'type' => 'string', 'description' => 'Weight' ],
						'dimensions'     => [
							'type'        => 'object',
							'description' => 'Product dimensions',
							'properties'  => [
								'length' => [ 'type' => 'string' ],
								'width'  => [ 'type' => 'string' ],
								'height' => [ 'type' => 'string' ],
							],
						],
						'description'    => [ 'type' => 'string', 'description' => 'Variation description' ],
						'image_id'       => [ 'type' => 'integer', 'description' => 'Image attachment ID' ],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'name'        => 'wc_update_variation',
				'description' => 'Update an existing product variation',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'     => [ 'type' => 'integer', 'description' => 'Parent product ID' ],
						'variation_id'   => [ 'type' => 'integer', 'description' => 'Variation ID' ],
						'attributes'     => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'name'   => [ 'type' => 'string' ],
									'option' => [ 'type' => 'string' ],
								],
							],
						],
						'regular_price'  => [ 'type' => 'string' ],
						'sale_price'     => [ 'type' => 'string' ],
						'sku'            => [ 'type' => 'string' ],
						'status'         => [ 'type' => 'string', 'enum' => [ 'publish', 'private' ] ],
						'manage_stock'   => [ 'type' => 'boolean' ],
						'stock_quantity' => [ 'type' => 'integer' ],
						'stock_status'   => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder' ] ],
						'weight'         => [ 'type' => 'string' ],
						'dimensions'     => [
							'type'       => 'object',
							'properties' => [
								'length' => [ 'type' => 'string' ],
								'width'  => [ 'type' => 'string' ],
								'height' => [ 'type' => 'string' ],
							],
						],
						'description'    => [ 'type' => 'string' ],
						'image_id'       => [ 'type' => 'integer' ],
					],
					'required'   => [ 'product_id', 'variation_id' ],
				],
			],
			[
				'name'        => 'wc_delete_variation',
				'description' => 'Delete a product variation',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'   => [ 'type' => 'integer', 'description' => 'Parent product ID' ],
						'variation_id' => [ 'type' => 'integer', 'description' => 'Variation ID' ],
						'force'        => [ 'type' => 'boolean', 'description' => 'Permanently delete (true) or trash (false). Default true.' ],
					],
					'required'   => [ 'product_id', 'variation_id' ],
				],
			],
			[
				'name'        => 'wc_batch_update_variations',
				'description' => 'Batch create, update, and/or delete product variations in one call. All operations are scoped to product_id — updates/deletes for variations belonging to a different product are rejected. Batch deletes are always permanent (force=true).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id' => [ 'type' => 'integer', 'description' => 'Parent variable product ID' ],
						'create'     => [
							'type'        => 'array',
							'description' => 'Variations to create (same fields as wc_create_variation minus product_id)',
							'items'       => [ 'type' => 'object' ],
						],
						'update'     => [
							'type'        => 'array',
							'description' => 'Variations to update — each must include variation_id',
							'items'       => [ 'type' => 'object' ],
						],
						'delete'     => [
							'type'        => 'array',
							'description' => 'Variation IDs to permanently delete',
							'items'       => [ 'type' => 'integer' ],
						],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'name'        => 'wc_get_product_attributes',
				'description' => 'List all registered global WooCommerce product attributes with their pa_* taxonomy slugs and IDs. Use this before wc_set_product_attributes or wc_get_attribute_terms to discover correct attribute IDs and slugs.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wc_get_attribute_terms',
				'description' => 'List all valid term options for a global WooCommerce attribute (e.g. all colours for pa_color). Pass the taxonomy slug (pa_*) returned by wc_get_product_attributes.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'     => [ 'type' => 'string', 'description' => 'Attribute taxonomy slug, e.g. pa_color (returned by wc_get_product_attributes)' ],
						'attribute_id' => [ 'type' => 'integer', 'description' => 'Attribute ID (alternative to taxonomy)' ],
						'hide_empty'   => [ 'type' => 'boolean', 'description' => 'Exclude terms with no products (default false)' ],
					],
				],
			],
			[
				'name'        => 'wc_create_product_attribute',
				'description' => 'Register a new global WooCommerce product attribute taxonomy (e.g. "Color" becomes pa_color). Returns the new attribute ID and pa_* slug.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'         => [ 'type' => 'string', 'description' => 'Attribute label shown in admin (e.g. Color)' ],
						'slug'         => [ 'type' => 'string', 'description' => 'Slug without pa_ prefix (auto-derived from name if omitted)' ],
						'type'         => [ 'type' => 'string', 'enum' => [ 'select', 'text', 'color', 'image', 'button' ], 'description' => 'Field type (default select)' ],
						'order_by'     => [ 'type' => 'string', 'enum' => [ 'menu_order', 'name', 'name_num', 'id' ], 'description' => 'Default sort order for terms (default menu_order)' ],
						'has_archives' => [ 'type' => 'boolean', 'description' => 'Enable public attribute archive pages (default false)' ],
					],
					'required'   => [ 'name' ],
				],
			],
			[
				'name'        => 'wc_set_product_attributes',
				'description' => 'Set which attributes a variable product uses — required before creating variations. For global attributes supply the attribute id (from wc_get_product_attributes) and options as term slugs or names. For custom (non-global) attributes use id 0 and supply a name.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id' => [ 'type' => 'integer', 'description' => 'Product ID' ],
						'attributes' => [
							'type'        => 'array',
							'description' => 'Attribute definitions',
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'        => [ 'type' => 'integer', 'description' => 'Global attribute ID (0 for custom attribute)' ],
									'name'      => [ 'type' => 'string', 'description' => 'Custom attribute name (required when id is 0)' ],
									'options'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Term slugs/names (global) or plain values (custom)' ],
									'position'  => [ 'type' => 'integer', 'description' => 'Sort order (auto-assigned if omitted)' ],
									'visible'   => [ 'type' => 'boolean', 'description' => 'Show on product page (default true)' ],
									'variation' => [ 'type' => 'boolean', 'description' => 'Used for variation selection (default false)' ],
								],
							],
						],
					],
					'required'   => [ 'product_id', 'attributes' ],
				],
			],
			[
				'name'        => 'wc_get_coupons',
				'description' => 'List WooCommerce coupons with optional code search, status filter, and pagination',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'   => [ 'type' => 'string', 'description' => 'Search by coupon code' ],
						'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'trash', 'any' ], 'description' => 'Coupon status (default: publish)' ],
						'per_page' => [ 'type' => 'integer', 'description' => 'Results per page (max 100, default 10)' ],
						'page'     => [ 'type' => 'integer', 'description' => 'Page number (default 1)' ],
					],
				],
			],
			[
				'name'        => 'wc_get_coupon',
				'description' => 'Get a single WooCommerce coupon by ID or code',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'   => [ 'type' => 'integer', 'description' => 'Coupon post ID' ],
						'code' => [ 'type' => 'string', 'description' => 'Coupon code (used if id is not provided)' ],
					],
				],
			],
			[
				'name'        => 'wc_get_coupon_count',
				'description' => 'Return published, draft, and trashed WooCommerce coupon counts',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'wc_create_coupon',
				'description' => 'Create a new WooCommerce coupon. Description may contain safe HTML.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'code'                        => [ 'type' => 'string', 'description' => 'Coupon code (required, always stored lowercase)' ],
						'discount_type'               => [ 'type' => 'string', 'enum' => [ 'percent', 'fixed_cart', 'fixed_product' ], 'description' => 'Discount type (default: fixed_cart)' ],
						'amount'                      => [ 'type' => 'string', 'description' => 'Discount amount' ],
						'description'                 => [ 'type' => 'string', 'description' => 'Internal coupon description' ],
						'date_expires'                => [ 'type' => 'string', 'description' => 'Expiry date/time (e.g. "2026-12-31" or "2026-12-31T23:59:59")' ],
						'usage_limit'                 => [ 'type' => 'integer', 'description' => 'Max total uses (0 = unlimited)' ],
						'usage_limit_per_user'        => [ 'type' => 'integer', 'description' => 'Max uses per customer (0 = unlimited)' ],
						'limit_usage_to_x_items'      => [ 'type' => 'integer', 'description' => 'Max cart items the discount applies to (0 = all)' ],
						'individual_use'              => [ 'type' => 'boolean', 'description' => 'Cannot be combined with other coupons' ],
						'free_shipping'               => [ 'type' => 'boolean', 'description' => 'Grant free shipping' ],
						'exclude_sale_items'          => [ 'type' => 'boolean', 'description' => 'Exclude sale-priced items' ],
						'minimum_amount'              => [ 'type' => 'string', 'description' => 'Minimum order subtotal required' ],
						'maximum_amount'              => [ 'type' => 'string', 'description' => 'Maximum order subtotal allowed' ],
						'product_ids'                 => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Product IDs the coupon applies to' ],
						'excluded_product_ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Product IDs excluded from the coupon' ],
						'product_categories'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category IDs the coupon applies to' ],
						'excluded_product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category IDs excluded from the coupon' ],
						'email_restrictions'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Restrict coupon to these email addresses' ],
					],
					'required' => [ 'code' ],
				],
			],
			[
				'name'        => 'wc_update_coupon',
				'description' => 'Update an existing WooCommerce coupon; only supplied fields are changed. Description may contain safe HTML.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'                          => [ 'type' => 'integer', 'description' => 'Coupon post ID' ],
						'code'                        => [ 'type' => 'string', 'description' => 'New coupon code (stored lowercase)' ],
						'discount_type'               => [ 'type' => 'string', 'enum' => [ 'percent', 'fixed_cart', 'fixed_product' ] ],
						'amount'                      => [ 'type' => 'string' ],
						'description'                 => [ 'type' => 'string' ],
						'date_expires'                => [ 'type' => 'string', 'description' => 'Expiry date/time, or empty string to clear' ],
						'usage_limit'                 => [ 'type' => 'integer' ],
						'usage_limit_per_user'        => [ 'type' => 'integer' ],
						'limit_usage_to_x_items'      => [ 'type' => 'integer' ],
						'individual_use'              => [ 'type' => 'boolean' ],
						'free_shipping'               => [ 'type' => 'boolean' ],
						'exclude_sale_items'          => [ 'type' => 'boolean' ],
						'minimum_amount'              => [ 'type' => 'string' ],
						'maximum_amount'              => [ 'type' => 'string' ],
						'product_ids'                 => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'excluded_product_ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'product_categories'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'excluded_product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'email_restrictions'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_delete_coupon',
				'description' => 'Delete a WooCommerce coupon; moves to trash by default, set force=true to permanently delete',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer', 'description' => 'Coupon post ID' ],
						'force' => [ 'type' => 'boolean', 'description' => 'Permanently delete instead of moving to trash (default: false)' ],
					],
					'required' => [ 'id' ],
				],
			],
			[
				'name'        => 'wc_empty_coupon_trash',
				'description' => 'Permanently delete all trashed WooCommerce coupons',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
		];
	}

	
	public static function execute_tool( $name, $args ) {
		
		
		
		
		
		
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			throw new \Exception( 'You do not have permission to use WooCommerce tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'WooCommerce is not active' );
		}


		

		if ( WooCommerce\Product_Handler::supports( $name ) ) {
			return WooCommerce\Product_Handler::execute_tool( $name, $args );
		}
		if ( WooCommerce\Order_Handler::supports( $name ) ) {
			return WooCommerce\Order_Handler::execute_tool( $name, $args );
		}
		if ( WooCommerce\Variation_Handler::supports( $name ) ) {
			return WooCommerce\Variation_Handler::execute_tool( $name, $args );
		}
		if ( WooCommerce\Attribute_Handler::supports( $name ) ) {
			return WooCommerce\Attribute_Handler::execute_tool( $name, $args );
		}
		if ( WooCommerce\Coupon_Handler::supports( $name ) ) {
			return WooCommerce\Coupon_Handler::execute_tool( $name, $args );
		}
		throw new \Exception( 'Unknown WooCommerce tool: ' . esc_html( $name ) );
	}
}
