<?php
namespace More_MCP\MCP;

if (!defined('ABSPATH')) {
    exit;
}

class Client {

    private $servers = [];

    public function __construct() {
        $this->load_servers();
    }

    private function load_servers() {
        $settings = get_option('more_mcp_settings', []);
        $this->servers = $settings['mcp_servers'] ?? [];
    }

    public function get_enabled_servers() {
        return array_filter($this->servers, function($server) {
            return isset($server['enabled']) && $server['enabled'];
        });
    }

    public function send_request($server_name, $method, $endpoint, $data = [], $headers = []) {
        $server = $this->get_server_by_name($server_name);

        if (!$server) {
            return new \WP_Error(
                'server_not_found',
                /* translators: %s: MCP server name */
                sprintf(__('MCP server "%s" not found', 'more-mcp'), $server_name),
                ['status' => 404]
            );
        }

        if (!isset($server['enabled']) || !$server['enabled']) {
            return new \WP_Error(
                'server_disabled',
                /* translators: %s: MCP server name */
                sprintf(__('MCP server "%s" is disabled', 'more-mcp'), $server_name),
                ['status' => 403]
            );
        }

        $url = trailingslashit($server['url']) . ltrim($endpoint, '/');

        $url_check = \More_MCP\Platform\Registry::validate_external_url( $url );
        if ( is_wp_error( $url_check ) ) {
            return $url_check;
        }

        $args = [
            'method' => strtoupper($method),
            'headers' => array_merge([
                'Content-Type' => 'application/json',
            ], $headers),
            'timeout' => 10,
        ];

        if (!empty($server['api_key'])) {
            $args['headers']['Authorization'] = 'Bearer ' . $server['api_key'];
        }

        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = json_encode($data);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log_mcp_request($server_name, $endpoint, $data, $response->get_error_message(), 'error');
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        $this->log_mcp_request($server_name, $endpoint, $data, $body, $status_code >= 200 && $status_code < 300 ? 'success' : 'error');

        if ($status_code >= 200 && $status_code < 300) {
            return $decoded;
        } else {
            return new \WP_Error(
                'mcp_request_failed',
                /* translators: %d: HTTP status code */
                sprintf(__('MCP request failed with status %d', 'more-mcp'), $status_code),
                ['status' => $status_code, 'body' => $decoded]
            );
        }
    }

    public function get_server_by_name($name) {
        foreach ($this->servers as $server) {
            if ($server['name'] === $name) {
                return $server;
            }
        }
        return null;
    }

    public function test_connection($server_name) {
        $server = $this->get_server_by_name($server_name);

        if (!$server) {
            return [
                'success' => false,
                'message' => __('Server not found', 'more-mcp'),
            ];
        }

        $url_check = \More_MCP\Platform\Registry::validate_external_url( $server['url'] );
        if ( is_wp_error( $url_check ) ) {
            return [
                'success' => false,
                'message' => $url_check->get_error_message(),
            ];
        }

        $response = wp_remote_get($server['url'], [
            'timeout' => 10,
            'headers' => !empty($server['api_key']) ? [
                'Authorization' => 'Bearer ' . $server['api_key'],
            ] : [],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $status_code >= 200 && $status_code < 500,
            /* translators: %d: HTTP status code */
            'message' => sprintf(__('Server responded with status %d', 'more-mcp'), $status_code),
            'status_code' => $status_code,
        ];
    }

    private function log_mcp_request($server_name, $endpoint, $request_data, $response_data, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'more_mcp_logs';

        $wpdb->insert(
            $table_name,
            [
                'mcp_server' => $server_name,
                'action' => $endpoint,
                'request_data' => json_encode($request_data),
                'response_data' => is_string($response_data) ? $response_data : json_encode($response_data),
                'status' => $status,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    public function broadcast_to_servers($endpoint, $data = []) {
        $enabled_servers = $this->get_enabled_servers();
        $results = [];

        foreach ($enabled_servers as $server) {
            $result = $this->send_request($server['name'], 'POST', $endpoint, $data);
            $results[$server['name']] = $result;
        }

        return $results;
    }
}
