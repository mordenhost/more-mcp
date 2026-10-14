<?php



if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}


function mordenhost_mcp_uninstall_site() {
    global $wpdb;
    
    delete_option('mordenhost_mcp_settings');

    
    delete_option('mordenhost_mcp_access');
    delete_option('mordenhost_mcp_access_policies');

    
    
    
    delete_option('mordenhost_mcp_db_version');
    delete_option('mordenhost_mcp_flush_rewrites');
    delete_option('mordenhost_mcp_legacy_migrated');
    delete_option('mordenhost_mcp_legacy_tables_pending');
    
    foreach ((array) get_option('mordenhost_mcp_legacy_tables_kept', array()) as $mordenhost_mcp_kept_suffix) {
        if (!is_string($mordenhost_mcp_kept_suffix) || !preg_match('/^\w+$/', $mordenhost_mcp_kept_suffix)) {
            continue;
        }
        $mordenhost_mcp_kept_table = esc_sql($wpdb->prefix . 'more_mcp_' . $mordenhost_mcp_kept_suffix);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup on uninstall, name built from the prefix and a \w+ suffix, escaped via esc_sql()
        $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_kept_table}`");
    }
    delete_option('mordenhost_mcp_legacy_tables_kept');
    delete_option('mordenhost_mcp_legacy_lock');

    
    
    $mordenhost_mcp_table_name = esc_sql($wpdb->prefix . 'mordenhost_mcp_logs');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup on uninstall, table name escaped via esc_sql()
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_table_name}`");

    
    $mordenhost_mcp_tokens_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_oauth_tokens');
    $mordenhost_mcp_clients_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_oauth_clients');
    $mordenhost_mcp_auth_codes_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_oauth_auth_codes');
    $mordenhost_mcp_sessions_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_sessions');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_tokens_table}`");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_clients_table}`");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_auth_codes_table}`");
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_sessions_table}`");

    
    $mordenhost_mcp_edit_trail_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_edit_trail');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_edit_trail_table}`");

    
    if (class_exists('\Mordenhost_MCP\Platform\Schema') || file_exists(__DIR__ . '/includes/Platform/Schema.php')) {
        require_once __DIR__ . '/includes/Platform/Schema.php';
        \Mordenhost_MCP\Platform\Schema::drop_all();
    }
    delete_option('mordenhost_mcp_safety');
    delete_option('mordenhost_mcp_preview_links');
    
    
    
    delete_option('mordenhost_mcp_llms_txt');
    delete_option('mordenhost_mcp_token_settings');

    
    
    
    $mordenhost_mcp_memory_table = esc_sql($wpdb->prefix . 'mordenhost_mcp_memory');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$mordenhost_mcp_memory_table}`");
    delete_option('mordenhost_mcp_memory_schema');

    
    
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $mordenhost_mcp_skill_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'mordenhost_mcp_skill'));
    foreach ((array) $mordenhost_mcp_skill_ids as $mordenhost_mcp_skill_id) {
        wp_delete_post((int) $mordenhost_mcp_skill_id, true);
    }

    
    delete_transient('mordenhost_mcp_cache');

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mordenhost_mcp_authcode_%' OR option_name LIKE '_transient_timeout_mordenhost_mcp_authcode_%'");

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mordenhost_mcp_session_%' OR option_name LIKE '_transient_timeout_mordenhost_mcp_session_%'");

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mordenhost_mcp_undo_%'");

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mordenhost_mcp_idem_%'");

    
    wp_clear_scheduled_hook('mordenhost_mcp_token_cleanup');

    
    delete_metadata('user', 0, 'mordenhost_mcp_dismissed_notices', '', true);
    delete_metadata('user', 0, 'mordenhost_mcp_founders_dismissed', '', true);
    
    delete_metadata('user', 0, 'mordenhost_mcp_review_dismissed_version', '', true);
}

if (is_multisite()) {
    $mordenhost_mcp_site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($mordenhost_mcp_site_ids as $mordenhost_mcp_site_id) {
        switch_to_blog((int) $mordenhost_mcp_site_id);
        mordenhost_mcp_uninstall_site();
        restore_current_blog();
    }
    delete_site_option('mordenhost_mcp_network');
    delete_site_option('mordenhost_mcp_legacy_migrated_network');
} else {
    mordenhost_mcp_uninstall_site();
}
