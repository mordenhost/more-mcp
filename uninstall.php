<?php



if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}


delete_option('more_mcp_settings');




delete_option('more_mcp_db_version');


global $wpdb;

$more_mcp_table_name = esc_sql($wpdb->prefix . 'more_mcp_logs');

$wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_table_name}`");


$more_mcp_tokens_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_tokens');
$more_mcp_clients_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_clients');
$more_mcp_auth_codes_table = esc_sql($wpdb->prefix . 'more_mcp_oauth_auth_codes');
$more_mcp_sessions_table = esc_sql($wpdb->prefix . 'more_mcp_sessions');

$wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_tokens_table}`");

$wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_clients_table}`");

$wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_auth_codes_table}`");

$wpdb->query("DROP TABLE IF EXISTS `{$more_mcp_sessions_table}`");


delete_transient('more_mcp_cache');



$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_authcode_%' OR option_name LIKE '_transient_timeout_more_mcp_authcode_%'");



$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_session_%' OR option_name LIKE '_transient_timeout_more_mcp_session_%'");



$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'more_mcp_undo_%'");


wp_clear_scheduled_hook('more_mcp_token_cleanup');


delete_metadata('user', 0, 'more_mcp_dismissed_notices', '', true);
delete_metadata('user', 0, 'more_mcp_founders_dismissed', '', true);

delete_metadata('user', 0, 'more_mcp_review_dismissed_version', '', true);
