<?php
/**
 * Per-tool capability map for the WordPress Abilities API permission_callback.
 *
 * Ability-level cap is the FIRST gate (returns fast when caller lacks it).
 * Per-tool internal caps inside Server::execute_tool remain the SECOND, fine-grained
 * gate (e.g. read_post on a specific post ID). This map's job is to reject callers
 * who could never legitimately use the tool at all — not to duplicate object-level checks.
 *
 * Order of resolution:
 *   1. Explicit override in {@see $overrides}
 *   2. Prefix/verb heuristic in {@see infer_cap()}
 *   3. Conservative fallback: manage_options
 */

namespace More_MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Capabilities {

	/**
	 * Explicit per-tool overrides for atypical caps. Tools not listed fall through
	 * to {@see infer_cap()}. Keep this list tight — only tools whose cap can't be
	 * derived from the prefix + verb rules belong here.
	 */
	private static function overrides(): array {
		return array(
			// ==================== Core: options / admin ====================
			'wp_get_option'                 => 'manage_options',
			'wp_update_option'              => 'manage_options',
			'wp_get_plugin_settings'        => 'manage_options',
			'wp_get_permalink_structure'    => 'manage_options',
			'wp_update_permalink_structure' => 'manage_options',
			'wp_get_site_status'            => 'manage_options',
			'wp_get_error_log_tail'         => 'manage_options',
			'wp_get_cron_schedule'          => 'manage_options',
			'wp_get_plugins'                => 'activate_plugins',

			// ==================== Lifecycle: plugins + themes ====================
			// These match the capability each handler re-checks internally. The
			// ability layer can only express one static capability, so it carries
			// the same gate rather than a looser one — a caller who cannot pass
			// the handler check should not get as far as calling it.
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

			// ==================== Core: users ====================
			'wp_get_users' => 'list_users',
			'wp_get_user'  => 'list_users',

			// ==================== Core: themes + appearance ====================
			'wp_get_themes'       => 'switch_themes',
			'wp_get_active_theme' => 'switch_themes',
			'wp_get_theme_mods'   => 'edit_theme_options',
			'wp_update_theme_mod' => 'edit_theme_options',
			'wp_get_custom_css'   => 'edit_theme_options',
			'wp_update_custom_css'=> 'edit_theme_options',

			// ==================== Core: menus ====================
			'wp_get_menus'          => 'edit_theme_options',
			'wp_get_menu_items'     => 'edit_theme_options',
			'wp_create_menu_item'   => 'edit_theme_options',
			'wp_update_menu_item'   => 'edit_theme_options',
			'wp_delete_menu_item'   => 'edit_theme_options',
			'wp_reorder_menu_items' => 'edit_theme_options',

			// ==================== Core: comments moderation ====================
			'wp_get_pending_comments' => 'moderate_comments',
			'wp_approve_comment'      => 'moderate_comments',
			'wp_spam_comment'         => 'moderate_comments',
			'wp_trash_comment'        => 'moderate_comments',
			'wp_delete_comment'       => 'moderate_comments',

			// ==================== Core: terms (write) ====================
			'wp_create_term'      => 'manage_categories',
			'wp_update_term'      => 'manage_categories',
			'wp_delete_term'      => 'manage_categories',
			'wp_get_term_meta'    => 'manage_categories',
			'wp_update_term_meta' => 'manage_categories',
			'wp_delete_term_meta' => 'manage_categories',
			'wp_get_terms'        => 'edit_posts', // Server.php:2042 uses edit_posts
			// Term SEO tools match the other term tools rather than the post SEO
			// tools: the object being edited is a term, and infer_cap() would
			// otherwise resolve these to edit_posts off the wp_ + get/update
			// verb rules.
			'wp_get_term_seo_meta'    => 'manage_categories',
			'wp_update_term_seo_meta' => 'manage_categories',

			// ==================== Forms: reads + guarded writes ====================
			// All gate on manage_options — the same admin-tier floor the handler
			// enforces. Entries carry personal data and two tools mutate state, so
			// the ability layer carries the same gate rather than deriving a looser
			// edit_posts from infer_cap()'s prefix rules. Provider-native caps
			// (gravityforms_view_entries etc.) are re-checked at the live matrix;
			// the static ability cap stays at the safe floor.
			'forms_list'                => 'manage_options',
			'forms_get'                 => 'manage_options',
			'forms_list_entries'        => 'manage_options',
			'forms_get_entry'           => 'manage_options',
			'forms_get_stats'           => 'manage_options',
			'forms_update_entry_status' => 'manage_options',
			'forms_trash_entry'         => 'manage_options',

			// ==================== Core: media ====================
			'wp_get_media'             => 'upload_files',
			'wp_get_media_item'        => 'upload_files',
			'wp_count_media'           => 'upload_files',
			'wp_upload_media_from_url' => 'upload_files',
			'wp_upload_media'          => 'upload_files',

			// ==================== Core: pages (edit_pages, not edit_posts) ====================
			'wp_create_page' => 'edit_pages',
			'wp_update_page' => 'edit_pages',
			'wp_delete_page' => 'edit_pages',

			// ==================== Core: content find/replace ====================
			// 'replace' is not a verb infer_cap() knows, so both tools would
			// otherwise fall through to the manage_options fallback.
			'wp_replace_in_post' => 'edit_posts',
			'wp_replace_in_page' => 'edit_pages',

			// ==================== Core: connection health — any authenticated caller ====================
			'more_mcp_connection_health' => 'read',

			// ==================== SEO: rendered-head audit ====================
			// `seo_` is not a prefix infer_cap() knows, so this would otherwise
			// fall through to the manage_options fallback — stricter than the
			// handler's own `read` gate, which would refuse via the Abilities
			// API a caller the MCP endpoint accepts. The tool fetches a public
			// URL and parses its head; it exposes nothing a visitor cannot see.
			'seo_audit_meta_tags' => 'read',

			// ==================== Blocks: Gutenberg content ====================
			// These mirror the per-tool checks inside Blocks\Content. The
			// handlers still enforce object-level caps (read_post / edit_post)
			// on the specific target; the ability layer can only express a
			// static capability, so it carries the coarse gate.
			'blocks_get_post_tree'   => 'read',
			'blocks_get_block'       => 'read',
			'blocks_list_types'      => 'edit_posts',
			'blocks_get_type_schema' => 'edit_posts',
			'blocks_validate_markup' => 'edit_posts',
			'blocks_insert'          => 'edit_posts',
			'blocks_update'          => 'edit_posts',
			'blocks_delete'          => 'edit_posts',
			'blocks_move'            => 'edit_posts',

			// ==================== Blocks: FSE site templates ====================
			// wp_template / wp_template_part map every capability to
			// edit_theme_options in WP core, so these are theme-level tools.
			'blocks_list_templates'  => 'edit_theme_options',
			'blocks_get_template'    => 'edit_theme_options',
			'blocks_update_template' => 'edit_theme_options',
			'blocks_revert_template' => 'edit_theme_options',
			'blocks_list_patterns'   => 'edit_theme_options',

			// ==================== Blocks: reusable blocks (wp_block) ====================
			// wp_block maps to ordinary post caps, NOT edit_theme_options.
			'blocks_list_reusable'   => 'edit_posts',
			'blocks_get_reusable'    => 'edit_posts',
			'blocks_create_reusable' => 'edit_posts',
			'blocks_update_reusable' => 'edit_posts',
			'blocks_delete_reusable' => 'delete_posts',

			// ==================== ForgeCache: admin ops ====================
			'fc_clear_cache'     => 'manage_options',
			'fc_get_cache_stats' => 'manage_options',

			// ==================== LiteSpeed Cache: purge ops ====================
			// Full-site purge is admin-tier. Per-URL purge is edit_posts here,
			// matching the fc_purge_url precedent and the handler's own umbrella
			// gate: the fine-grained edit_post-on-target check still fires inside
			// LiteSpeed::execute_tool(), which the ability layer cannot express as
			// a single static cap. Setting this to manage_options would lock out
			// Editors on the Abilities REST surface who can legitimately purge via
			// MCP — the same tool must gate the same way on every surface.
			'ls_purge_all'       => 'manage_options',
			'ls_purge_url'       => 'edit_posts',
		);
	}

	/**
	 * Resolve the capability required to call a tool via the Abilities API.
	 */
	public static function for_tool( string $tool_name ): string {
		$overrides = self::overrides();
		if ( isset( $overrides[ $tool_name ] ) ) {
			return $overrides[ $tool_name ];
		}
		return self::infer_cap( $tool_name );
	}

	/**
	 * Prefix + verb heuristic for tools not in the override map.
	 * Order matters: match longest / most-specific prefix first.
	 */
	private static function infer_cap( string $tool_name ): string {
		// Read-heavy verbs on core namespace default to WP's read cap.
		if ( preg_match( '/^(wp|more_mcp)_(get|count|list|search)/', $tool_name ) ) {
			return 'read';
		}
		// Core write verbs default to edit_posts (loosest content-write cap).
		if ( preg_match( '/^wp_(create|update|delete|add|set|restore)_/', $tool_name ) ) {
			return 'edit_posts';
		}
		// Integration-namespace defaults inherit each integration's baseline.
		if ( strpos( $tool_name, 'wc_' ) === 0 )        return 'manage_woocommerce';
		if ( strpos( $tool_name, 'elementor_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'divi_' ) === 0 ) return 'edit_posts';
		if ( strpos( $tool_name, 'fc_' ) === 0 )        return 'edit_posts';
		if ( strpos( $tool_name, 'ls_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'sv_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'gp_' ) === 0 )        return 'manage_options';
		if ( strpos( $tool_name, 'acf_' ) === 0 )       return 'edit_posts';
		if ( strpos( $tool_name, 'redirection_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'analytics_' ) === 0 ) return 'manage_options';
		if ( strpos( $tool_name, 'forms_' ) === 0 )     return 'manage_options';

		// Unknown prefix — conservative default.
		return 'manage_options';
	}
}
