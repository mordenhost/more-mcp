<?php


namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Capabilities {

	
	private static function overrides(): array {
		return array(
			
			'wp_get_option'                 => 'manage_options',
			'wp_update_option'              => 'manage_options',
			'wp_get_plugin_settings'        => 'manage_options',
			'wp_get_permalink_structure'    => 'manage_options',
			'wp_update_permalink_structure' => 'manage_options',
			'wp_get_site_status'            => 'manage_options',
			'wp_get_error_log_tail'         => 'manage_options',
			'wp_get_cron_schedule'          => 'manage_options',
			'wp_get_plugins'                => 'activate_plugins',

			
			
			
			
			
			'wp_get_plugin_updates' => 'update_plugins',
			'wp_activate_plugin'    => 'activate_plugins',
			'wp_deactivate_plugin'  => 'activate_plugins',
			'wp_update_plugin'      => 'update_plugins',
			'wp_install_plugin'     => 'install_plugins',
			'wp_delete_plugin'      => 'delete_plugins',
			'wp_get_themes_status'  => 'switch_themes',
			'wp_activate_theme'     => 'switch_themes',
			'wp_update_theme'       => 'update_themes',
			'wp_delete_theme'       => 'delete_themes',

			
			'wp_get_users' => 'list_users',
			'wp_get_user'  => 'list_users',

			
			'wp_get_themes'       => 'switch_themes',
			'wp_get_active_theme' => 'switch_themes',
			'wp_get_theme_mods'   => 'edit_theme_options',
			'wp_update_theme_mod' => 'edit_theme_options',
			'wp_get_custom_css'   => 'edit_theme_options',
			'wp_update_custom_css'=> 'edit_theme_options',

			
			'wp_get_menus'          => 'edit_theme_options',
			'wp_get_menu_items'     => 'edit_theme_options',
			'wp_create_menu_item'   => 'edit_theme_options',
			'wp_update_menu_item'   => 'edit_theme_options',
			'wp_delete_menu_item'   => 'edit_theme_options',
			'wp_reorder_menu_items' => 'edit_theme_options',

			
			'wp_get_pending_comments' => 'moderate_comments',
			'wp_approve_comment'      => 'moderate_comments',
			'wp_spam_comment'         => 'moderate_comments',
			'wp_trash_comment'        => 'moderate_comments',
			'wp_delete_comment'       => 'moderate_comments',

			
			'wp_create_term'      => 'manage_categories',
			'wp_update_term'      => 'manage_categories',
			'wp_delete_term'      => 'manage_categories',
			'wp_get_term_meta'    => 'manage_categories',
			'wp_update_term_meta' => 'manage_categories',
			'wp_delete_term_meta' => 'manage_categories',
			'wp_get_terms'        => 'edit_posts', 
			
			
			
			
			'wp_get_term_seo_meta'    => 'manage_categories',
			'wp_update_term_seo_meta' => 'manage_categories',

			
			
			
			
			
			
			
			'forms_list'                => 'manage_options',
			'forms_get'                 => 'manage_options',
			'forms_list_entries'        => 'manage_options',
			'forms_get_entry'           => 'manage_options',
			'forms_get_stats'           => 'manage_options',
			'forms_update_entry_status' => 'manage_options',
			'forms_trash_entry'         => 'manage_options',

			
			
			
			
			
			
			
			
			
			
			'wp_ahrefs_domain_rating_free' => 'manage_options',
			'semrush_domain_overview'      => 'manage_options',
			'semrush_organic_keywords'     => 'manage_options',
			'semrush_competitors'          => 'manage_options',
			'semrush_keyword_overview'     => 'manage_options',
			'semrush_related_keywords'     => 'manage_options',
			'semrush_keyword_difficulty'   => 'manage_options',
			'semrush_question_keywords'    => 'manage_options',
			'semrush_url_keywords'         => 'manage_options',
			'semrush_backlinks_overview'   => 'manage_options',
			'semrush_backlinks_list'       => 'manage_options',
			'semrush_referring_domains'    => 'manage_options',
			'semrush_backlink_anchors'     => 'manage_options',
			'semrush_api_units'            => 'manage_options',
			'dataforseo_serp'              => 'manage_options',
			'dataforseo_keyword_volume'    => 'manage_options',
			'dataforseo_ranked_keywords'   => 'manage_options',
			'dataforseo_backlinks_summary' => 'manage_options',
			'dataforseo_referring_domains' => 'manage_options',
			'dataforseo_onpage_instant'    => 'manage_options',
			'seranking_domain_overview'        => 'manage_options',
			'seranking_domain_overview_global' => 'manage_options',
			'seranking_domain_keywords'        => 'manage_options',
			'seranking_domain_competitors'     => 'manage_options',
			'seranking_top_pages'              => 'manage_options',
			'seranking_subdomains'             => 'manage_options',
			'seranking_keyword_overview'       => 'manage_options',
			'seranking_keyword_compare'        => 'manage_options',
			'seranking_related_keywords'       => 'manage_options',
			'seranking_similar_keywords'       => 'manage_options',
			'seranking_question_keywords'      => 'manage_options',
			'seranking_longtail_keywords'      => 'manage_options',
			'seranking_backlinks'              => 'manage_options',
			'seranking_domain_authority'       => 'manage_options',
			'seranking_ai_visibility'          => 'manage_options',
			'gsc_list_sites'          => 'manage_options',
			'gsc_search_analytics'    => 'manage_options',
			'gsc_list_sitemaps'       => 'manage_options',
			'gsc_get_sitemap'         => 'manage_options',
			'gsc_inspect_url'         => 'manage_options',
			'ga4_list_accounts'          => 'manage_options',
			'ga4_list_properties'        => 'manage_options',
			'ga4_get_property'           => 'manage_options',
			'ga4_metadata'               => 'manage_options',
			'ga4_run_report'             => 'manage_options',
			'ga4_run_pivot_report'       => 'manage_options',
			'ga4_realtime_report'        => 'manage_options',
			'ga4_list_data_streams'      => 'manage_options',
			'ga4_list_conversion_events' => 'manage_options',
			'ga4_list_custom_dimensions' => 'manage_options',
			'ga4_list_custom_metrics'    => 'manage_options',

			
			'wp_get_media'             => 'upload_files',
			'wp_get_media_item'        => 'upload_files',
			'wp_count_media'           => 'upload_files',
			'wp_upload_media_from_url' => 'upload_files',
			'wp_upload_media'          => 'upload_files',

			
			'wp_create_page' => 'edit_pages',
			'wp_update_page' => 'edit_pages',
			'wp_delete_page' => 'edit_pages',

			
			
			
			'wp_replace_in_post' => 'edit_posts',
			'wp_replace_in_page' => 'edit_pages',

			
			'more_mcp_connection_health' => 'read',

			
			
			
			
			
			
			'seo_audit_meta_tags' => 'read',

			
			
			
			
			
			'blocks_get_post_tree'   => 'read',
			'blocks_get_block'       => 'read',
			'blocks_list_types'      => 'edit_posts',
			'blocks_get_type_schema' => 'edit_posts',
			'blocks_validate_markup' => 'edit_posts',
			'blocks_insert'          => 'edit_posts',
			'blocks_update'          => 'edit_posts',
			'blocks_delete'          => 'edit_posts',
			'blocks_move'            => 'edit_posts',

			
			
			
			'blocks_list_templates'  => 'edit_theme_options',
			'blocks_get_template'    => 'edit_theme_options',
			'blocks_update_template' => 'edit_theme_options',
			'blocks_revert_template' => 'edit_theme_options',
			'blocks_list_patterns'   => 'edit_theme_options',

			
			
			'blocks_list_reusable'   => 'edit_posts',
			'blocks_get_reusable'    => 'edit_posts',
			'blocks_create_reusable' => 'edit_posts',
			'blocks_update_reusable' => 'edit_posts',
			'blocks_delete_reusable' => 'delete_posts',

			
			
			
			
			
			
			
			'elementor_get_kit'          => 'edit_theme_options',
			'elementor_get_kit_schema'   => 'edit_theme_options',
			'elementor_get_kit_fonts'    => 'edit_theme_options',
			'elementor_update_kit'       => 'edit_theme_options',

			
			
			
			
			
			
			
			
			'ls_purge_all'       => 'manage_options',
			'ls_purge_url'       => 'edit_posts',

			
			
			
			
			
			
			'wpr_purge_all'    => 'manage_options',
			'wpr_purge_minify' => 'manage_options',
		);
	}

	
	public static function for_tool( string $tool_name ): string {
		$overrides = self::overrides();
		if ( isset( $overrides[ $tool_name ] ) ) {
			return $overrides[ $tool_name ];
		}
		return self::infer_cap( $tool_name );
	}

	
	private static function infer_cap( string $tool_name ): string {
		
		if ( preg_match( '/^(wp|more_mcp)_(get|count|list|search)/', $tool_name ) ) {
			return 'read';
		}
		
		if ( preg_match( '/^wp_(create|update|delete|add|set|restore)_/', $tool_name ) ) {
			return 'edit_posts';
		}
		
		if ( strpos( $tool_name, 'wc_' ) === 0 )        return 'manage_woocommerce';
		if ( strpos( $tool_name, 'elementor_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'divi_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'ls_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'acf_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'redirection_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'analytics_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'forms_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'wpr_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'up_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'wf_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'def_' ) === 0 )       return 'manage_options';

		
		return 'manage_options';
	}
}
