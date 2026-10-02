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
			'wp_set_front_page'             => 'manage_options',
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
			'wp_create_menu'        => 'edit_theme_options',
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

			
			
			
			
			
			
			'email_get_status'          => 'manage_options',

			
			
			
			
			
			
			'stock_search_images'       => 'upload_files',
			'stock_get_provider_settings' => 'upload_files',

			
			
			
			
			
			'ai_generate_image'         => 'manage_options',
			'ai_generate_alt_text'      => 'manage_options',

			
			
			
			
			
			'snippet_list'              => 'manage_options',
			'snippet_get'               => 'manage_options',
			'snippet_create'            => 'manage_options',
			'snippet_update'            => 'manage_options',

			
			
			
			'webhook_list'   => 'manage_options',
			'webhook_create' => 'manage_options',
			'webhook_delete' => 'manage_options',

			
			
			
			
			
			
			
			
			'ahrefs_domain_rating'         => 'manage_options',
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
			
			
			
			
			'wp_process_image'         => 'upload_files',

			
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

			
			
			
			
			
			
			'blocks_get_global_styles'    => 'edit_theme_options',
			'blocks_update_global_styles' => 'edit_theme_options',

			
			
			
			
			
			'wp_list_page_templates' => 'edit_posts',
			'wp_set_page_template'   => 'edit_posts',

			
			
			'blocks_list_reusable'   => 'edit_posts',
			'blocks_get_reusable'    => 'edit_posts',
			'blocks_create_reusable' => 'edit_posts',
			'blocks_update_reusable' => 'edit_posts',
			'blocks_delete_reusable' => 'delete_posts',

			
			
			
			
			
			
			
			'elementor_get_kit'          => 'edit_theme_options',
			'elementor_get_kit_schema'   => 'edit_theme_options',
			'elementor_get_kit_fonts'    => 'edit_theme_options',
			'elementor_update_kit'       => 'edit_theme_options',

			
			
			
			
			'elementor_list_fonts'       => 'edit_theme_options',
			'elementor_get_font'         => 'edit_theme_options',
			'elementor_create_font'      => 'edit_theme_options',
			'elementor_update_font'      => 'edit_theme_options',
			'elementor_delete_font'      => 'edit_theme_options',
			'elementor_list_icon_sets'   => 'edit_theme_options',
			'elementor_get_icon_set'     => 'edit_theme_options',
			'elementor_delete_icon_set'  => 'edit_theme_options',

			
			
			
			
			
			'elementor_list_code'        => 'manage_options',
			'elementor_get_code'         => 'manage_options',
			'elementor_create_code'      => 'manage_options',
			'elementor_update_code'      => 'manage_options',
			'elementor_delete_code'      => 'manage_options',

			
			
			
			
			
			
			
			
			'ls_purge_all'       => 'manage_options',
			'ls_purge_url'       => 'edit_posts',

			
			
			
			
			
			
			'wpr_purge_all'    => 'manage_options',
			'wpr_purge_minify' => 'manage_options',

			
			
			
			
			
			'w3tc_purge_all'         => 'manage_options',
			'w3tc_purge_minify'      => 'manage_options',
			'w3tc_purge_object_cache' => 'manage_options',

			
			
			
			
			
			
			
			
			
			'akismet_check_comment'  => 'moderate_comments',
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
		if ( strpos( $tool_name, 'beaver_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'siteorigin_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'ls_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'acf_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'mb_' ) === 0 )        return 'edit_posts';
		if ( strpos( $tool_name, 'redirection_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'analytics_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'forms_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'wpr_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'w3tc_' ) === 0 )      return 'edit_posts';
		if ( strpos( $tool_name, 'redis_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'ao_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'wpo_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'up_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'bwu_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'dup_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'wpvivid_' ) === 0 )   return 'manage_options';
		if ( strpos( $tool_name, 'wf_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'def_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'solid_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'sucuri_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'rsssl_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'akismet_' ) === 0 )   return 'manage_options';
		if ( strpos( $tool_name, 'imagify_' ) === 0 )   return 'manage_options';
		if ( strpos( $tool_name, 'ewww_' ) === 0 )      return 'manage_options';
		if ( strpos( $tool_name, 'smush_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'shortpixel_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'tec_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'sugarcal_' ) === 0 )  return 'edit_posts';
		if ( strpos( $tool_name, 'em_' ) === 0 )        return 'edit_posts';
		if ( strpos( $tool_name, 'trp_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'pll_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'wpml_' ) === 0 )      return 'manage_options';
		if ( strpos( $tool_name, 'weglot_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'geodir_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'bdp_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'ivory_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'uncanny_automator_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'blog2social_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'estatik_' ) === 0 )   return 'manage_options';
		if ( strpos( $tool_name, 'toolset_' ) === 0 )   return 'manage_options';
		if ( strpos( $tool_name, 'crm_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'mailpoet_' ) === 0 )  return 'manage_options';
		if ( strpos( $tool_name, 'groundhogg_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'givewp_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'pmpro_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'swpm_' ) === 0 )      return 'manage_options';
		if ( strpos( $tool_name, 'ppress_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'rcp_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'charitable_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'bbp_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'bp_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'fluentsupport_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'lms_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'sensei_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'mto_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'tutor_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'llms_' ) === 0 )      return 'manage_options';
		if ( strpos( $tool_name, 'amelia_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'latepoint_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'bookly_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'bookingpress_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'pods_' ) === 0 )      return 'manage_options';
		if ( strpos( $tool_name, 'cptui_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'mbcpt_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'edd_' ) === 0 )       return 'manage_options';
		if ( strpos( $tool_name, 'fluentcart_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'ai1wm_' ) === 0 )     return 'manage_options';
		if ( strpos( $tool_name, 'patchstack_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'relevanssi_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'slicewp_' ) === 0 )    return 'manage_options';
		if ( strpos( $tool_name, 'automatorwp_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'wpjm_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'wpgm_' ) === 0 )        return 'manage_options';

		
		return 'manage_options';
	}
}
