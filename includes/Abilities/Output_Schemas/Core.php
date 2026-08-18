<?php
/**
 * Output schemas for More MCP core tools (wp_* + more_mcp_*).
 *
 * Schemas are JSON Schema Draft-4-ish, matching WP core's rest_validate_value_from_schema
 * expectations. additionalProperties defaults to true because handler responses often
 * include host-supplied extras (e.g. WordPress caching layer adding fields) — strict
 * enforcement would false-positive on those. Where a tool's response shape is truly
 * stable and closed, additionalProperties is set explicitly to false.
 *
 * Partial helpers keep the file navigable — {@see post_summary_schema()} etc. are
 * reused across list/get/create/update responses that all return a post-shaped object.
 */

namespace More_MCP\Abilities\Output_Schemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core {

	/**
	 * Public entry — Registry::get_for_tool() dispatches here for wp_* + more_mcp_* tools.
	 */
	public static function get( string $tool_name ): ?array {
		$map = self::map();
		return $map[ $tool_name ] ?? null;
	}

	private static function map(): array {
		return array(

			// ==================== POSTS ====================
			'wp_get_posts'      => array(
				'type'  => 'array',
				'items' => self::post_summary_schema(),
			),
			'wp_get_post'       => self::post_full_schema(),
			'wp_create_post'     => self::post_write_response_schema(),
			'wp_update_post'     => self::post_write_response_schema(),
			'wp_replace_in_post' => self::replace_in_content_response_schema(),
			'wp_get_post_types' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string' ),
						'label'        => array( 'type' => 'string' ),
						'hierarchical' => array( 'type' => 'boolean' ),
						'public'       => array( 'type' => 'boolean' ),
					),
				),
			),
			'wp_delete_post'    => self::message_schema(),
			'wp_count_posts'    => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'integer' ),
			),

			// ==================== PAGES ====================
			'wp_get_pages'    => array(
				'type'  => 'array',
				'items' => self::post_summary_schema(),
			),
			'wp_get_page'     => self::post_full_schema(),
			'wp_create_page'     => self::post_write_response_schema(),
			'wp_update_page'     => self::post_write_response_schema(),
			'wp_replace_in_page' => self::replace_in_content_response_schema(),
			'wp_delete_page'  => self::message_schema(),

			// ==================== MEDIA ====================
			'wp_get_media'             => array(
				'type'       => 'object',
				'properties' => array(
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'pages'    => array( 'type' => 'integer' ),
					'returned' => array( 'type' => 'integer' ),
					'has_more' => array( 'type' => 'boolean' ),
					'media'    => array(
						'type'  => 'array',
						'items' => self::media_summary_schema(),
					),
				),
			),
			'wp_get_media_item'        => self::media_full_schema(),
			'wp_upload_media_from_url' => self::media_upload_response_schema(),
			'wp_upload_media'          => self::media_upload_response_schema(),
			'wp_set_featured_image'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer' ),
					'media_id'    => array( 'type' => array( 'integer', 'null' ) ),
					'action'      => array( 'type' => 'string' ),
				),
			),
			'wp_update_media' => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'wp_delete_media' => self::message_schema(),
			'wp_count_media'  => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'integer' ),
			),

			// ==================== TERMS ====================
			'wp_get_categories' => array( 'type' => 'array', 'items' => self::term_summary_schema() ),
			'wp_get_tags'       => array( 'type' => 'array', 'items' => self::term_summary_schema() ),
			'wp_create_term'    => self::term_write_response_schema(),
			'wp_update_term'    => self::term_write_response_schema(),
			'wp_delete_term'    => self::message_schema(),
			'wp_add_post_terms' => self::message_schema(),
			'wp_get_terms'      => array(
				'type'       => 'object',
				'properties' => array(
					'terms'       => array( 'type' => 'array', 'items' => self::term_summary_schema() ),
					'total_count' => array( 'type' => 'integer' ),
					'page'        => array( 'type' => 'integer' ),
					'per_page'    => array( 'type' => 'integer' ),
				),
			),
			'wp_count_terms'    => array( 'type' => 'integer' ),
			'wp_get_taxonomies' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string' ),
						'label'        => array( 'type' => 'string' ),
						'hierarchical' => array( 'type' => 'boolean' ),
						'post_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
			),

			// ==================== TERM META ====================
			'wp_get_term_meta'    => array(
				'type'       => 'object',
				'properties' => array(
					'term_id' => array( 'type' => 'integer' ),
					'key'     => array( 'type' => 'string' ),
					'value'   => array(), // any JSON type
				),
			),
			'wp_update_term_meta' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'message' => array( 'type' => 'string' ),
						'term_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					self::meta_verification_props()
				),
			),
			'wp_delete_term_meta' => self::message_schema(),

			// ==================== COMMENTS ====================
			'wp_get_comments'         => array(
				'type'  => 'array',
				'items' => self::comment_summary_schema(),
			),
			'wp_create_comment'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'      => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'wp_delete_comment'       => self::message_schema(),
			'wp_get_pending_comments' => array(
				'type'  => 'array',
				'items' => self::comment_summary_schema(),
			),
			'wp_approve_comment' => self::comment_status_change_schema(),
			'wp_spam_comment'    => self::comment_status_change_schema(),
			'wp_trash_comment'   => self::comment_status_change_schema(),

			// ==================== USERS ====================
			'wp_get_users' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'integer' ),
						'display_name' => array( 'type' => 'string' ),
						'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
			),
			'wp_get_user'  => array(
				'type'       => 'object',
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'display_name' => array( 'type' => 'string' ),
					'roles'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'registered'   => array( 'type' => 'string' ),
				),
			),

			// ==================== POST META ====================
			// wp_get_post_meta returns either {key, value} OR a full meta map — union shape.
			'wp_get_post_meta'    => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'wp_update_post_meta' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'message' => array( 'type' => 'string' ),
						'result'  => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					self::meta_verification_props()
				),
			),
			'wp_add_post_meta'    => array(
				'type'       => 'object',
				'properties' => array_merge(
					array(
						'meta_id' => array( 'type' => array( 'integer', 'null' ) ),
						'created' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'key'     => array( 'type' => 'string' ),
					),
					self::meta_verification_props()
				),
			),
			'wp_delete_post_meta' => self::message_schema(),

			// ==================== SITE & SEARCH ====================
			'wp_get_site_info'   => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'url'         => array( 'type' => 'string' ),
					'language'    => array( 'type' => 'string' ),
					'timezone'    => array( 'type' => 'string' ),
					'wp_version'  => array( 'type' => 'string' ),
				),
			),
			'more_mcp_connection_health' => array(
				'type'       => 'object',
				'properties' => array(
					'route'          => array( 'type' => 'string' ),
					'auth_method'    => array( 'type' => 'string' ),
					'relay'          => array( 'type' => array( 'string', 'null' ) ),
					'token_ttl'      => array( 'type' => array( 'integer', 'null' ) ),
					'session_id'     => array( 'type' => array( 'string', 'null' ) ),
					'active_scopes'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'server_version' => array( 'type' => 'string' ),
					'wp_version'     => array( 'type' => 'string' ),
					'php_version'    => array( 'type' => 'string' ),
					'builders'       => array(
						'type'       => 'object',
						'properties' => array(
							'divi_version'      => array( 'type' => array( 'string', 'null' ) ),
							'elementor_version' => array( 'type' => array( 'string', 'null' ) ),
							'gutenberg_version' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'wp_get_site_status' => array(
				'type'       => 'object',
				'properties' => array(
					'wp_version'          => array( 'type' => 'string' ),
					'php_version'         => array( 'type' => 'string' ),
					'mysql_version'       => array( 'type' => array( 'string', 'null' ) ),
					'is_multisite'        => array( 'type' => 'boolean' ),
					'active_plugin_count' => array( 'type' => 'integer' ),
					'active_theme'        => array(
						'type'       => 'object',
						'properties' => array(
							'name'       => array( 'type' => 'string' ),
							'stylesheet' => array( 'type' => 'string' ),
							'template'   => array( 'type' => 'string' ),
							'version'    => array( 'type' => 'string' ),
						),
					),
					'memory_limit'        => array( 'type' => 'string' ),
					'max_upload_size'     => array( 'type' => 'string' ),
					'max_execution_time'  => array( 'type' => 'integer' ),
					'timezone'            => array( 'type' => 'string' ),
					'debug_log_enabled'   => array( 'type' => 'boolean' ),
					'disk_free_bytes'     => array( 'type' => array( 'integer', 'null' ) ),
					'disk_free_human'     => array( 'type' => array( 'string', 'null' ) ),
					'install_age_days'    => array( 'type' => array( 'integer', 'null' ) ),
					'site_url'            => array( 'type' => 'string' ),
					'home_url'            => array( 'type' => 'string' ),
				),
			),
			'wp_get_error_log_tail' => array(
				'type'       => 'object',
				'properties' => array(
					'status'         => array( 'type' => 'string' ),
					'message'        => array( 'type' => 'string' ),
					'path'           => array( 'type' => 'string' ),
					'filesize_bytes' => array( 'type' => 'integer' ),
					'window_bytes'   => array( 'type' => 'integer' ),
					'truncated'      => array( 'type' => 'boolean' ),
					'filter'         => array( 'type' => 'string' ),
					'total_returned' => array( 'type' => 'integer' ),
					'lines'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'wp_get_cron_schedule' => array(
				'type'       => 'object',
				'properties' => array(
					'events'      => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'hook'          => array( 'type' => 'string' ),
								'next_run_ts'   => array( 'type' => 'integer' ),
								'next_run_iso'  => array( 'type' => 'string' ),
								'seconds_until' => array( 'type' => 'integer' ),
								'is_overdue'    => array( 'type' => 'boolean' ),
								'recurrence'    => array( 'type' => array( 'string', 'null' ) ),
								'args'          => array( 'type' => 'array' ),
							),
						),
					),
					'total_count' => array( 'type' => 'integer' ),
					'now_ts'      => array( 'type' => 'integer' ),
					'now_iso'     => array( 'type' => 'string' ),
				),
			),
			'wp_search' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'type'    => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'snippet' => array( 'type' => 'string' ),
					),
				),
			),

			// ==================== OPTIONS ====================
			'wp_get_option'          => array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array( 'type' => 'string' ),
					'value' => array(), // any JSON type
				),
			),
			'wp_get_plugin_settings' => array(
				'type'       => 'object',
				'properties' => array(
					'slug'    => array( 'type' => 'string' ),
					'options' => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_option'       => array(
				'type'       => 'object',
				'properties' => array(
					'name'     => array( 'type' => 'string' ),
					'updated'  => array( 'type' => 'boolean' ),
					'previous' => array(), // any JSON type
				),
			),

			// ==================== MENUS ====================
			'wp_get_menus'      => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'name' => array( 'type' => 'string' ),
						'slug' => array( 'type' => 'string' ),
					),
				),
			),
			'wp_get_menu_items' => array(
				'type'  => 'array',
				'items' => self::menu_item_schema(),
			),
			'wp_create_menu_item' => self::menu_item_write_response_schema(),
			'wp_update_menu_item' => self::menu_item_write_response_schema(),
			'wp_delete_menu_item' => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'menu_item_id' => array( 'type' => 'integer' ),
				),
			),
			'wp_reorder_menu_items' => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'menu_id'   => array( 'type' => 'integer' ),
					'count'     => array( 'type' => 'integer' ),
					'reordered' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'skipped'   => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'menu_item_id' => array( 'type' => 'integer' ),
								'reason'       => array( 'type' => 'string' ),
							),
						),
					),
				),
			),

			// ==================== PLUGINS & THEMES ====================
			'wp_get_plugins' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'version' => array( 'type' => 'string' ),
						'active'  => array( 'type' => 'boolean' ),
						'author'  => array( 'type' => 'string' ),
					),
				),
			),
			'wp_get_themes' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'version' => array( 'type' => 'string' ),
						'active'  => array( 'type' => 'boolean' ),
						'author'  => array( 'type' => 'string' ),
					),
				),
			),

			// ==================== THEME & APPEARANCE ====================
			'wp_get_active_theme' => array(
				'type'       => 'object',
				'properties' => array(
					'name'           => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'template'       => array( 'type' => 'string' ),
					'stylesheet'     => array( 'type' => 'string' ),
					'version'        => array( 'type' => 'string' ),
					'author'         => array( 'type' => 'string' ),
					'description'    => array( 'type' => 'string' ),
					'parent_slug'    => array( 'type' => array( 'string', 'null' ) ),
					'screenshot_url' => array( 'type' => array( 'string', 'boolean' ) ),
					'status'         => array( 'type' => 'string' ),
				),
			),
			'wp_get_theme_mods'   => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
			'wp_update_theme_mod' => array(
				'type'       => 'object',
				'properties' => array(
					'mod_name'       => array( 'type' => 'string' ),
					'previous_value' => array(),
					'new_value'      => array(),
				),
			),
			'wp_get_custom_css'    => array(
				'type'       => 'object',
				'properties' => array(
					'css'        => array( 'type' => 'string' ),
					'theme_slug' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
				),
			),
			'wp_update_custom_css' => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'post_id'    => array( 'type' => 'integer' ),
					'theme_slug' => array( 'type' => 'string' ),
					'byte_count' => array( 'type' => 'integer' ),
				),
			),

			// ==================== SEO META ====================
			// Response varies by detected plugin and by which fields that plugin
			// supports, so these stay loose objects with the stable keys pinned.
			'wp_get_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'           => array( 'type' => 'string' ),
					'plugin_name'      => array( 'type' => 'string' ),
					'all_active'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'post_id'          => array( 'type' => 'integer' ),
					'slug'             => array( 'type' => 'string' ),
					'supported_fields' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'raw'              => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'      => array( 'type' => 'string' ),
					'plugin_name' => array( 'type' => 'string' ),
					'all_active'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'post_id'     => array( 'type' => 'integer' ),
					'updated'     => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_get_term_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'           => array( 'type' => 'string' ),
					'plugin_name'      => array( 'type' => 'string' ),
					'all_active'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'term_id'          => array( 'type' => 'integer' ),
					'taxonomy'         => array( 'type' => 'string' ),
					'slug'             => array( 'type' => 'string' ),
					'supported_fields' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'raw'              => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			'wp_update_term_seo_meta' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'plugin'      => array( 'type' => 'string' ),
					'plugin_name' => array( 'type' => 'string' ),
					'all_active'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'term_id'     => array( 'type' => 'integer' ),
					'taxonomy'    => array( 'type' => 'string' ),
					'updated'     => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
			// Unlike the meta tools above, this shape does not vary by plugin — it
			// parses the rendered head — so the schema can be exact rather than loose.
			'seo_audit_meta_tags' => array(
				'type'       => 'object',
				'properties' => array(
					'url'    => array( 'type' => 'string' ),
					'status' => array( 'type' => 'integer' ),
					'title'  => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'length'     => array( 'type' => 'integer' ),
							'duplicates' => array( 'type' => 'integer' ),
						),
					),
					'description' => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'length'     => array( 'type' => 'integer' ),
							'duplicates' => array( 'type' => 'integer' ),
						),
					),
					'canonical' => array(
						'type'       => 'object',
						'properties' => array(
							'value'      => array( 'type' => 'string' ),
							'duplicates' => array( 'type' => 'integer' ),
							'is_self'    => array( 'type' => 'boolean' ),
						),
					),
					'viewport' => array(
						'type'       => 'object',
						'properties' => array(
							'present' => array( 'type' => 'boolean' ),
							'content' => array( 'type' => 'string' ),
						),
					),
					'og' => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image'       => array( 'type' => 'string' ),
							'url'         => array( 'type' => 'string' ),
							'type'        => array( 'type' => 'string' ),
						),
					),
					'twitter' => array(
						'type'       => 'object',
						'properties' => array(
							'card'        => array( 'type' => 'string' ),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image'       => array( 'type' => 'string' ),
						),
					),
				),
			),

			// ==================== PERMALINK STRUCTURE ====================
			'wp_get_permalink_structure' => array(
				'type'       => 'object',
				'properties' => array(
					'permalink_structure' => array( 'type' => 'string' ),
					'category_base'       => array( 'type' => 'string' ),
					'tag_base'            => array( 'type' => 'string' ),
				),
			),
			'wp_update_permalink_structure' => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'previous' => array( 'type' => 'string' ),
					'current'  => array( 'type' => 'string' ),
				),
			),

			// ==================== POST REVISIONS ====================
			'wp_get_post_revisions' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer' ),
					'total'     => array( 'type' => 'integer' ),
					'offset'    => array( 'type' => 'integer' ),
					'limit'     => array( 'type' => 'integer' ),
					'returned'  => array( 'type' => 'integer' ),
					'has_more'  => array( 'type' => 'boolean' ),
					'note'      => array( 'type' => 'string' ),
					'revisions' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'properties'           => array(
								'revision_id'           => array( 'type' => 'integer' ),
								'parent_id'             => array( 'type' => 'integer' ),
								'author_id'             => array( 'type' => 'integer' ),
								'author_name'           => array( 'type' => 'string' ),
								'date'                  => array( 'type' => 'string' ),
								'title'                 => array( 'type' => 'string' ),
								'word_count'            => array( 'type' => 'integer' ),
								'content_length'        => array( 'type' => 'integer' ),
								'differs_from_parent'   => array( 'type' => 'boolean' ),
								'elementor_data_length' => array( 'type' => array( 'integer', 'null' ) ),
							),
						),
					),
				),
			),
			'wp_restore_revision' => array(
				'type'       => 'object',
				'properties' => array(
					'success'              => array( 'type' => 'boolean' ),
					'parent_id'            => array( 'type' => 'integer' ),
					'restored_revision_id' => array( 'type' => 'integer' ),
				),
			),
		);
	}

	// =============================================================
	// PARTIAL SCHEMAS — reused across multiple tools
	// =============================================================

	/**
	 * Post/page summary shape returned by list endpoints (wp_get_posts, wp_get_pages).
	 * Loose additionalProperties=true because list handlers may include extras like
	 * featured_media_url that are host/plugin dependent.
	 */
	private static function post_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'status' => array( 'type' => 'string' ),
				'url'    => array( 'type' => 'string' ),
				'date'   => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Full post/page shape returned by wp_get_post / wp_get_page. Loose because
	 * post arrays include a large surface of author/meta/taxonomy fields we don't
	 * enumerate exhaustively.
	 */
	private static function post_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
				'date'    => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Post write response — carries the actual saved values per INVARIANTS.md §11
	 * (read-after-write verification catches silent-drop / silent-modify).
	 */
	private static function post_write_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'              => array( 'type' => 'integer' ),
				'saved_fields'    => array( 'type' => 'object', 'additionalProperties' => true ),
				'modified_by_wp'  => array( 'type' => 'object', 'additionalProperties' => true ),
				'message'         => array( 'type' => 'string' ),
			),
		);
	}

	private static function replace_in_content_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'                    => array( 'type' => 'integer' ),
				'occurrences'           => array( 'type' => 'integer' ),
				'replaced'              => array( 'type' => 'integer' ),
				'verified'              => array( 'type' => 'boolean' ),
				'dry_run'               => array( 'type' => 'boolean' ),
				'content_length'        => array( 'type' => 'integer' ),
				'content_length_before' => array( 'type' => 'integer' ),
				'content_length_after'  => array( 'type' => 'integer' ),
				'modified_by_wp'        => array( 'type' => 'object', 'additionalProperties' => true ),
				'message'               => array( 'type' => 'string' ),
			),
		);
	}

	private static function message_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * The read-after-write verification fields every meta write tool returns.
	 *
	 * JSON Schema treats an absent additionalProperties as "allowed", so these
	 * would validate without being declared. They are declared anyway: an
	 * ability consumer reading the schema should be able to discover that a
	 * write reports whether it round-tripped, without having to call the tool
	 * and inspect the response.
	 */
	private static function meta_verification_props(): array {
		return array(
			'saved_value_matches_sent' => array( 'type' => 'boolean' ),
			'stored_type'              => array( 'type' => 'string' ),
			'stored_length'            => array( 'type' => array( 'integer', 'null' ) ),
			'warnings'                 => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);
	}


	private static function term_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'          => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'count'       => array( 'type' => 'integer' ),
				'parent'      => array( 'type' => 'integer' ),
			),
		);
	}

	private static function term_write_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'term_id' => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			),
		);
	}

	private static function comment_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'      => array( 'type' => 'integer' ),
				'post_id' => array( 'type' => 'integer' ),
				'author'  => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'date'    => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
			),
		);
	}

	private static function comment_status_change_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'comment_id' => array( 'type' => 'integer' ),
				'new_status' => array( 'type' => 'string' ),
			),
		);
	}

	private static function media_summary_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				// The handler returns 'url'. This schema previously declared
				// 'source_url', which no row ever carried.
				'url'       => array( 'type' => 'string' ),
				'mime_type' => array( 'type' => 'string' ),
				'date'      => array( 'type' => 'string' ),
				// Same value under both names: 'alt' is what the handler always
				// emitted, 'alt_text' is what the tool description promised.
				'alt'       => array( 'type' => 'string' ),
				'alt_text'  => array( 'type' => 'string' ),
			),
		);
	}

	private static function media_full_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'         => array( 'type' => 'integer' ),
				'title'      => array( 'type' => 'string' ),
				'source_url' => array( 'type' => 'string' ),
				'alt_text'   => array( 'type' => 'string' ),
				'mime_type'  => array( 'type' => 'string' ),
			),
		);
	}

	private static function media_upload_response_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'         => array( 'type' => 'integer' ),
				'source_url' => array( 'type' => 'string' ),
			),
		);
	}

	private static function menu_item_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'url'    => array( 'type' => 'string' ),
				'parent' => array( 'type' => array( 'integer', 'string' ) ),
				'order'  => array( 'type' => 'integer' ),
			),
		);
	}

	private static function menu_item_write_response_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'menu_item_id' => array( 'type' => 'integer' ),
				'menu_id'      => array( 'type' => 'integer' ),
			),
		);
	}
}
