<?php



if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}


function more_mcp_uninstall_site() {
    global $wpdb;
    
    delete_option('more_mcp_settings');

    
    delete_option('more_mcp_access');
    delete_option('more_mcp_access_policies');

    
    
    
    delete_option('more_mcp_db_version');
    delete_option('more_mcp_flush_rewrites');

    
    
    $more_mcp_table_name = esc_sql($wpdb->prefix . 'more_mcp_logs');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup on uninstall, table name escaped via esc_sql()
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_table_name}`");

    
    $more_mcp_tokens_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_tokens');
    $more_mcp_clients_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_clients');
    $more_mcp_auth_codes_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_auth_codes');
    $more_mcp_sessions_table = esc_sql($wpdb->prefix . 'more_mcp_sessions');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_tokens_table}`");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_clients_table}`");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_auth_codes_table}`");
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_sessions_table}`");

    
    $more_mcp_edit_trail_table = esc_sql($wpdb->prefix . 'more_mcp_edit_trail');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_edit_trail_table}`");

    
    if (class_exists('\More_MCP\Platform\Schema') || file_exists(__DIR__ . '/includes/Platform/Schema.php')) {
        require_once __DIR__ . '/includes/Platform/Schema.php';
        \More_MCP\Platform\Schema::drop_all();
    }
    delete_option('more_mcp_safety');
    delete_option('more_mcp_preview_links');
    delete_option('more_mcp_token_settings');

    
    
    
    $more_mcp_memory_table = esc_sql($wpdb->prefix . 'more_mcp_memory');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_memory_table}`");
    delete_option('more_mcp_memory_schema');

    
    
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $more_mcp_skill_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'more_mcp_skill'));
    foreach ((array) $more_mcp_skill_ids as $more_mcp_skill_id) {
        wp_delete_post((int) $more_mcp_skill_id, true);
    }

    
    delete_transient('more_mcp_cache');

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_authcode_%' OR option_name LIKE '_transient_timeout_more_mcp_authcode_%'");

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_session_%' OR option_name LIKE '_transient_timeout_more_mcp_session_%'");

    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'more_mcp_undo_%'");

    
    wp_clear_scheduled_hook('more_mcp_token_cleanup');

    
    delete_metadata('user', 0, 'more_mcp_dismissed_notices', '', true);
    delete_metadata('user', 0, 'more_mcp_founders_dismissed', '', true);
    
    delete_metadata('user', 0, 'more_mcp_review_dismissed_version', '', true);
}

if (is_multisite()) {
    $more_mcp_site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($more_mcp_site_ids as $more_mcp_site_id) {
        switch_to_blog((int) $more_mcp_site_id);
        more_mcp_uninstall_site();
        restore_current_blog();
    }
    delete_site_option('more_mcp_network');
} else {
    more_mcp_uninstall_site();
}
