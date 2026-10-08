<?php

namespace More_MCP\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destructive {

	const PREFIXES = array(
		'wp_delete_',
		'wc_delete_',
		'wp_trash_',
		'wp_spam_',
	);

	const EXACT = array(
		'wp_reorder_menu_items',
		'more_mcp_undo_last_operation',

		
		
		'wp_activate_plugin',
		'wp_deactivate_plugin',
		'wp_update_plugin',
		'wp_install_plugin',
		'wp_delete_plugin',
		'wp_activate_theme',
		'wp_update_theme',
		'wp_delete_theme',
		'blocks_insert',
		'blocks_update',
		'blocks_delete',
		'blocks_move',
		'blocks_update_template',
		'blocks_revert_template',
		'blocks_create_reusable',
		'blocks_update_reusable',
		'blocks_delete_reusable',

		

		

		'blocks_update_global_styles',
		'elementor_update_kit',

		'memory_delete',

		

		

		
		'divi_replace_module',
		'divi_insert_module',
		'divi_delete_module',
		'elementor_update_widget',
		'elementor_delete_widget',
		'wp_restore_revision',
		'wp_update_permalink_structure',
		'wp_update_custom_css',

		
		
		'wp_set_page_template',
		'wp_update_widget',
		'wp_update_option',
		'wc_create_order',
		'wc_update_order',
		'wc_add_order_note',
		'fc_clear_cache',
		'fc_purge_url',
		'ls_purge_all',
		'ls_purge_url',

		

		'w3tc_purge_all',
		'w3tc_purge_url',
		'w3tc_purge_minify',
		'w3tc_purge_object_cache',
		'redis_flush_object_cache',
		'ao_purge_all',
		'wpo_purge_cache',
		'sv_create_backup',

		

		

		'cptui_save_post_type',
		'cptui_delete_post_type',
		'cptui_save_taxonomy',
		'cptui_delete_taxonomy',

		

		

		
		'toolset_save_post_type',
		'toolset_delete_post_type',
		'toolset_save_taxonomy',
		'toolset_delete_taxonomy',

		

		

		
		
		'mbcpt_save_post_type',
		'mbcpt_delete_post_type',
		'mbcpt_save_taxonomy',
		'mbcpt_delete_taxonomy',

		
		'wp_create_user',
		'wp_update_user',
		'wp_update_user_meta',
		'wp_update_menu',
		'wp_update_site_settings',
		'wp_run_cron_event',
		'wp_bulk_moderate_comments',
		'wp_update_comment',
		'wp_create_widget',
		'wp_history_apply',
		'wp_privileged_search_replace_run',
		'wp_rest_write',
		'wp_rest_delete',

		'tec_delete_event',
		'bp_delete_activity',
		'acf_update_user_fields',
	);

	const HIGH_IMPACT = array(
		'wp_delete_post',
		'wp_delete_page',
		'wp_delete_media',
		'wp_delete_user',
		'wp_delete_term',
		'wp_delete_comment',
		'wp_delete_menu',
		'wp_delete_menu_item',
		'wp_delete_widget',
		'wp_delete_revision',
		'wp_delete_post_meta',
		'wp_delete_term_meta',
		'wp_delete_user_meta',
		'wp_delete_theme_mod',
		'wp_delete_plugin',
		'wp_delete_theme',
		'wp_activate_plugin',
		'wp_deactivate_plugin',
		'wp_install_plugin',
		'wp_update_plugin',
		'wp_activate_theme',
		'wp_update_theme',
		'wp_create_user',
		'wp_update_user',
		'wp_update_option',
		'wp_update_site_settings',
		'wp_update_permalink_structure',
		'wp_update_custom_css',
		'wp_run_cron_event',
		'wp_privileged_search_replace_run',
		'wp_rest_write',
		'wp_rest_delete',
		'elementor_delete_widget',
		'blocks_delete',
		'blocks_delete_reusable',
		'divi_delete_module',
		'wc_delete_product',
		'wc_delete_customer',
		'wc_delete_coupon',
		'wc_delete_webhook',
		'wc_delete_variation',
		'wc_empty_coupon_trash',
		'tec_delete_event',
		'bp_delete_activity',
	);

	public static function is( string $tool ): bool {
		if ( in_array( $tool, self::EXACT, true ) ) {
			return true;
		}
		foreach ( self::PREFIXES as $prefix ) {
			if ( 0 === strpos( $tool, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	const DRY_RUN_BY_DEFAULT = array( 'wp_privileged_search_replace_run' );

	public static function is_dry_run( string $tool, array $args ): bool {
		if ( array_key_exists( 'dry_run', $args ) ) {
			return ! empty( $args['dry_run'] ) && ! in_array( $args['dry_run'], array( 'false', '0', 'no' ), true );
		}
		return in_array( $tool, self::DRY_RUN_BY_DEFAULT, true );
	}

	public static function high_impact( string $tool ): bool {
		return in_array( $tool, self::HIGH_IMPACT, true );
	}

	public static function high_impact_list(): array {
		return self::HIGH_IMPACT;
	}
}
