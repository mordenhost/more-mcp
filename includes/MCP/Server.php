<?php
namespace More_MCP\MCP;

use More_MCP\Integrations\WooCommerce as WooIntegration;
use More_MCP\Integrations\LiteSpeed as LSIntegration;
use More_MCP\Integrations\Elementor as ElementorIntegration;
use More_MCP\Integrations\Divi as DiviIntegration;
use More_MCP\Integrations\ACF as ACFIntegration;
use More_MCP\Integrations\MetaBox as MetaBoxIntegration;
use More_MCP\Integrations\Redirection as RedirectionIntegration;
use More_MCP\Integrations\Analytics as AnalyticsIntegration;
use More_MCP\Integrations\Forms as FormsIntegration;
use More_MCP\Integrations\Email as EmailIntegration;
use More_MCP\Integrations\WPRocket as WPRocketIntegration;
use More_MCP\Integrations\UpdraftPlus as UpdraftPlusIntegration;
use More_MCP\Integrations\BackWPup as BackWPupIntegration;
use More_MCP\Integrations\Wordfence as WordfenceIntegration;
use More_MCP\Integrations\Defender as DefenderIntegration;
use More_MCP\Integrations\Akismet as AkismetIntegration;
use More_MCP\Integrations\Imagify as ImagifyIntegration;
use More_MCP\Integrations\TranslatePress as TranslatePressIntegration;
use More_MCP\Integrations\FluentCRM as FluentCRMIntegration;
use More_MCP\Integrations\LearnPress as LearnPressIntegration;
use More_MCP\Integrations\Elementor_Coexistence;
use More_MCP\Blocks\Content as BlocksContent;
use More_MCP\Blocks\Templates as BlocksTemplates;
use More_MCP\Lifecycle\Manager as LifecycleManager;
use More_MCP\SEO_Data\Manager as SEODataManager;
use More_MCP\Tools\Registry as ToolsRegistry;
use More_MCP\Abilities\Importer as AbilitiesImporter;
use More_MCP\Capabilities\Map as CapabilityMap;

if (!defined('ABSPATH')) {
    exit;
}

class Server {

    private $rate_limit_max = 60;
    private $rate_limit_window = 60; 

    private $request_auth_fingerprint = '';

    private $request_auth_method  = null;   
    private $request_token_ttl    = null;   
    private $request_session_id   = null;   

    private function validate_origin($request) {
        $origin = $request->get_header('Origin');

        
        if (empty($origin)) {
            return true;
        }

        $origin_parts = wp_parse_url($origin);
        if (!$origin_parts || empty($origin_parts['host'])) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Origin header',
                ],
            ], 400);
        }

        $origin_host = $origin_parts['host'];

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $allowed_hosts = [
            $site_host,
            'localhost',
            '127.0.0.1',
            '::1',
            'claude.ai',           
            'www.claude.ai',
            'anthropic.com',
            'www.anthropic.com',
        ];

        $allowed_hosts = apply_filters('more_mcp_allowed_origins', $allowed_hosts);

        if (!in_array($origin_host, $allowed_hosts, true)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Origin not allowed',
                ],
            ], 403);
        }

        return true;
    }

    private function validate_auth($request) {
        $settings = get_option('more_mcp_settings', []);

        if (empty($settings['enabled'])) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'More MCP is currently disabled.',
                ],
            ], 403);
        }

        

        

        

        
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header) && stripos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            $oauth_result = $this->validate_bearer_token($token);
            if (true === $oauth_result) {
                return true;
            }
            $api_key_result = $this->validate_api_key_value($token, $settings);
            if (true === $api_key_result) {
                return true;
            }

            
            return $oauth_result;
        }

        
        
        $api_key = $request->get_header('MMCP-Key');
        if (!empty($api_key)) {
            return $this->validate_api_key_value($api_key, $settings);
        }

        

        
        
        $resource_metadata_url = home_url( '/.well-known/oauth-protected-resource' );
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'This endpoint requires credentials: send an OAuth 2.0 access token as "Authorization: Bearer <token>", or supply your API key in the "MMCP-Key" header.',
            ],
        ], 401);
        $response->header('WWW-Authenticate', 'Bearer resource_metadata="' . $resource_metadata_url . '"');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private function validate_api_key_value($api_key, $settings = null) {
        if (null === $settings) {
            $settings = get_option('more_mcp_settings', []);
        }

        $stored = isset($settings['api_key']) ? (string) $settings['api_key'] : '';

        

        
        if (\More_MCP\Auth\Api_Key::is_legacy_format($api_key)) {
            return $this->api_key_error(
                'This API key uses the retired format. Open More MCP → Settings and click Regenerate to mint a key in the current format, then update your client.'
            );
        }

        if (!\More_MCP\Auth\Api_Key::is_valid_format($api_key)) {
            return $this->api_key_error('Invalid API key.');
        }

        
        
        if ($stored === '' || !hash_equals($stored, $api_key)) {
            return $this->api_key_error('Invalid API key.');
        }

        
        if (!is_user_logged_in()) {
            $admins = get_users([
                'role'    => 'administrator',
                'number'  => 1,
                'orderby' => 'ID',
                'order'   => 'ASC',
                'fields'  => 'ID',
            ]);
            if (!empty($admins)) {
                wp_set_current_user((int) $admins[0]);
            }
        }

        $this->request_auth_fingerprint = hash('sha256', 'apikey:' . $api_key);

        $this->request_auth_method = 'api-key';
        $this->request_token_ttl   = null;

        return true;
    }

    private function api_key_error($message) {
        $resource_metadata_url = home_url( '/.well-known/oauth-protected-resource' );
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => $message,
            ],
        ], 401);
        $response->header('WWW-Authenticate', 'Bearer error="invalid_token", resource_metadata="' . $resource_metadata_url . '"');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private function validate_bearer_token($raw_token) {
        $token_data = \More_MCP\OAuth\Token_Store::validate_token($raw_token);

        if (!$token_data) {
            $response = new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid or expired access token.',
                ],
            ], 401);
            $resource_metadata_url = home_url( '/.well-known/oauth-protected-resource' );
            $response->header('WWW-Authenticate', 'Bearer error="invalid_token", resource_metadata="' . $resource_metadata_url . '"');
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
            return $response;
        }

        wp_set_current_user((int) $token_data['user_id']);

        
        
        $this->request_auth_fingerprint = hash(
            'sha256',
            'oauth:' . (string) $token_data['client_id'] . ':' . (int) $token_data['user_id']
        );

        $this->request_auth_method = 'oauth-bearer';
        if ( ! empty( $token_data['expires_at'] ) ) {
            $expires_ts = strtotime( (string) $token_data['expires_at'] . ' UTC' );
            if ( $expires_ts ) {
                $this->request_token_ttl = max( 0, $expires_ts - time() );
            }
        }

        return true;
    }

    private function check_rate_limit($ip) {
        $transient_key = 'more_mcp_rate_' . md5($ip);
        $data = get_transient($transient_key);

        if ($data === false) {
            set_transient($transient_key, ['count' => 1, 'start' => time()], $this->rate_limit_window);
            return true;
        }

        if (time() - $data['start'] > $this->rate_limit_window) {
            set_transient($transient_key, ['count' => 1, 'start' => time()], $this->rate_limit_window);
            return true;
        }

        $data['count']++;
        set_transient($transient_key, $data, $this->rate_limit_window);

        if ($data['count'] > $this->rate_limit_max) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Rate limit exceeded. Maximum ' . $this->rate_limit_max . ' requests per minute.',
                ],
            ], 429);
        }

        return true;
    }

    private function validate_accept_header($request) {
        $accept = $request->get_header('Accept');

        if (empty($accept)) {
            return true;
        }

        $accepts_json = strpos($accept, 'application/json') !== false ||
                        strpos($accept, '*/*') !== false;

        return $accepts_json;
    }

    private function validate_session_id_format($session_id) {
        if (empty($session_id)) {
            return false;
        }

        $length = strlen($session_id);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($session_id[$i]);
            if ($ord < 0x21 || $ord > 0x7E) {
                return false;
            }
        }

        return true;
    }

    private function is_valid_session($session_id) {
        if (!$this->validate_session_id_format($session_id)) {
            return false;
        }

        

        
        
        return Session_Store::touch_session($session_id);
    }

    private function store_session($session_id, $auth_fingerprint = '') {

        
        Session_Store::create_session($session_id, $auth_fingerprint);
    }

    private function delete_session($session_id) {
        Session_Store::delete_session($session_id);
    }

    private function get_tools() {
        $tools = [

            
            ...\More_MCP\Tools\Posts::get_tools(),

            
            ...\More_MCP\Tools\Pages::get_tools(),

            
            
            ...\More_MCP\Tools\Media::get_tools(),

            
            ...\More_MCP\Tools\Terms::get_tools(),

            
            ...\More_MCP\Tools\TermMeta::get_tools(),

            
            ...\More_MCP\Tools\Comments::get_tools(),

            

            
            ...\More_MCP\Tools\Users::get_tools(),

            
            ...\More_MCP\Tools\PostMeta::get_tools(),

            

            
            ...\More_MCP\Tools\Site::get_tools(),
            ['name' => 'more_mcp_connection_health', 'description' => 'Diagnostic probe for the current MCP connection. Returns MCP endpoint route, authentication method used by this request (api-key or oauth-bearer), OAuth access token time-to-live in seconds (null for api-key), current MCP session ID, active MCP capabilities negotiated at initialize, plus More MCP + WordPress + PHP version strings. No arguments. Call at connection start to confirm setup, or when diagnosing 401/403/404 issues. Any authenticated caller.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'more_mcp_undo_last_operation', 'description' => 'Reverse one prior operation using the token from its response. LIMITED SCOPE: only some tools emit undo tokens, and a tool that does not emit one cannot be undone through this. Currently covered: the Gutenberg block mutations (blocks_insert, blocks_update, blocks_delete, blocks_move), the template and reusable-block writes (blocks_update_template, blocks_revert_template, blocks_update_reusable, blocks_delete_reusable), wp_reorder_menu_items, the Elementor element writes (elementor_update_widget, elementor_delete_widget), and the Elementor kit write (elementor_update_kit), and the Divi whole-node writes (divi_replace_module, divi_insert_module, divi_delete_module), and the forms entry writes (forms_update_entry_status, forms_trash_entry). NOT covered, these write without any undo: wp_update_post, wp_update_option, wp_update_widget, wp_update_seo_meta, wp_update_term_seo_meta, wp_update_post_meta, the other elementor_* tools, and every wc_* tool. Check for an `undo` key in a response rather than assuming one exists. Tokens live 72 hours and are one-shot, consumed on a successful undo. The capability requirement matches the original operation and is re-checked at redemption, because a token may be presented by a different caller than the one who created it. Free basic mode: single-op restore, local storage.', 'inputSchema' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string', 'description' => 'The undo token from a prior tool response (response.undo.token).']], 'required' => ['token']]],
            ...\More_MCP\Tools\Search::get_tools(),

            
            ...\More_MCP\Tools\Options::get_tools(),

            
            ...\More_MCP\Tools\Menus::get_tools(),

            
            ...\More_MCP\Tools\Appearance::get_tools(),

            
            ...\More_MCP\Tools\Seo::get_tools(),

            
            ...\More_MCP\Tools\Permalink::get_tools(),

            
            ...\More_MCP\Tools\Revisions::get_tools(),
        ];

        

        

        
        
        $integration_classes = [
            'woocommerce'    => WooIntegration::class,
            'litespeed'      => LSIntegration::class,
            'elementor'      => ElementorIntegration::class,
            'divi'           => DiviIntegration::class,
            'acf'            => ACFIntegration::class,
            'metabox'        => MetaBoxIntegration::class,
            'redirection'    => RedirectionIntegration::class,
            'analytics'      => AnalyticsIntegration::class,
            'email'          => EmailIntegration::class,
            'forms'          => FormsIntegration::class,
            'wp-rocket'      => WPRocketIntegration::class,
            'updraftplus'    => UpdraftPlusIntegration::class,
            'backwpup'       => BackWPupIntegration::class,
            'wordfence'      => WordfenceIntegration::class,
            'defender'       => DefenderIntegration::class,
            'akismet'        => AkismetIntegration::class,
            'imagify'        => ImagifyIntegration::class,
            'translatepress' => TranslatePressIntegration::class,
            'fluentcrm'      => FluentCRMIntegration::class,
            'learnpress'     => LearnPressIntegration::class,
        ];
        foreach ( $integration_classes as $slug => $class ) {
            if ( ! \More_MCP\Capabilities\Toggles::is_enabled( $slug ) ) {
                continue; 
            }
            $tools = array_merge( $tools, $class::get_tools() );
        }

        
        
        $tools = array_merge( $tools, BlocksContent::get_tools() );
        $tools = array_merge( $tools, BlocksTemplates::get_tools() );

        
        
        $tools = array_merge( $tools, LifecycleManager::get_tools() );

        

        $tools = array_merge( $tools, SEODataManager::get_tools() );

        

        
        
        $tools = array_merge( $tools, AbilitiesImporter::get_tools() );

        

        $tools = Elementor_Coexistence::filter_elementor_tool_descriptions( $tools );

        return apply_filters( 'more_mcp_tools', $tools );
    }

    public function handle_mcp($request) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        if ($method === 'OPTIONS') {
            return $this->cors_response();
        }

        $origin_check = $this->validate_origin($request);
        if ($origin_check !== true) {
            return $origin_check;
        }

        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
        $rate_check = $this->check_rate_limit($client_ip);
        if ($rate_check !== true) {
            return $rate_check;
        }

        if ($method === 'GET') {
            return $this->handle_get_stream($request);
        }

        if ($method === 'POST') {
            
            if (!$this->validate_accept_header($request)) {
                return $this->json_response([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32600,
                        'message' => 'Accept header must include application/json',
                    ],
                ], 400);
            }
            return $this->handle_post_message($request);
        }

        if ($method === 'DELETE') {
            return $this->handle_delete_session($request);
        }

        return new \WP_REST_Response(['error' => 'Method not allowed'], 405);
    }

    private function cors_response() {
        $response = new \WP_REST_Response(null, 204);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, Mcp-Session-Id, MMCP-Key');
        $response->header('Access-Control-Max-Age', '86400');
        return $response;
    }

    private function handle_get_stream($request) {
        $auth_check = $this->validate_auth($request);
        if ($auth_check !== true) {
            return $auth_check;
        }

        

        
        $user_agent = $request->get_header('User-Agent');
        if (is_string($user_agent) && stripos($user_agent, 'Claude-User') !== false) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            status_header(200);
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); 
            echo ": more-mcp-keepalive\n\n";
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit;
        }

        
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Server-sent events (SSE) are not supported on this client. Use HTTP POST for all MCP communication.',
            ],
        ], 405);
        $response->header('Allow', 'POST, DELETE, OPTIONS');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private function handle_post_message($request) {
        
        $body = $request->get_json_params();

        if (!$body || !isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return $this->json_response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid JSON-RPC request',
                ],
            ], 400);
        }

        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];
        $id = $body['id'] ?? null;

        $session_id = $request->get_header('Mcp-Session-Id');

        $this->request_session_id = $session_id ? (string) $session_id : null;

        $auth_check = $this->validate_auth($request);
        if ($auth_check !== true) {
            return $auth_check;
        }

        $auth_fingerprint = $this->build_auth_fingerprint($request);

        if ($method !== 'initialize') {
            
            if (empty($session_id)) {
                return $this->json_response([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Mcp-Session-Id header required. Please initialize first.',
                    ],
                ], 400);
            }

            if (!$this->validate_session_id_format($session_id)) {
                return $this->json_response([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid session ID format',
                    ],
                ], 400);
            }

            if (!$this->is_valid_session($session_id)) {
                return $this->json_response([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Session not found or expired. Please re-initialize.',
                    ],
                ], 404);
            }

            
            $stored_fingerprint = Session_Store::get_fingerprint($session_id);
            if (!empty($stored_fingerprint) && !hash_equals($stored_fingerprint, $auth_fingerprint)) {
                return $this->json_response([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Session credentials mismatch. Please re-initialize.',
                    ],
                ], 403);
            }
        }

        $result = $this->process_method($method, $params, $id);

        if ($method === 'initialize' && $result && isset($result['result'])) {
            $new_session_id = $this->generate_session_id();
            
            $this->store_session($new_session_id, $auth_fingerprint);
            $response = $this->json_response($result, 200);
            $response->header('Mcp-Session-Id', $new_session_id);
            return $response;
        }

        if ($id === null) {
            return new \WP_REST_Response(null, 202);
        }

        return $this->json_response($result, 200);
    }

    private function handle_delete_session($request) {
        
        $auth_check = $this->validate_auth($request);
        if ($auth_check !== true) {
            return $auth_check;
        }

        $session_id = $request->get_header('Mcp-Session-Id');

        if (empty($session_id)) {
            return $this->json_response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Mcp-Session-Id header required',
                ],
            ], 400);
        }

        if (!$this->validate_session_id_format($session_id)) {
            return $this->json_response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid session ID format',
                ],
            ], 400);
        }

        $this->delete_session($session_id);

        $response = new \WP_REST_Response(null, 200);
        $response->header('Access-Control-Allow-Origin', '*');
        return $response;
    }

    private function build_auth_fingerprint($request) {
        unset($request); 
        return $this->request_auth_fingerprint;
    }

    private function generate_session_id() {
        return bin2hex(random_bytes(16));
    }

    private function json_response($data, $status = 200) {
        $response = new \WP_REST_Response($data, $status);
        $response->header('Content-Type', 'application/json');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Expose-Headers', 'Mcp-Session-Id');
        return $response;
    }

    private function process_method($method, $params, $id) {
        switch ($method) {
            case 'initialize':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => '2025-11-25',
                        'serverInfo' => [
                            'name' => 'More MCP WordPress',
                            'version' => MORE_MCP_VERSION,
                        ],
                        'capabilities' => [
                            'tools' => new \stdClass(),
                            'resources' => new \stdClass(),
                        ],
                    ],
                ];

            case 'notifications/initialized':
            case 'initialized':
                return null; 

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => $this->get_tools(),
                    ],
                ];

            case 'tools/call':
                return $this->handle_tool_call($id, $params);

            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => new \stdClass(),
                ];

            case 'resources/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => ['resources' => $this->list_resources()],
                ];

            case 'resources/read':
                return $this->handle_resource_read($id, $params);

            case 'prompts/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => ['prompts' => []],
                ];

            default:
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => 'Method not found: ' . $method,
                    ],
                ];
        }
    }

    private function handle_tool_call($id, $params) {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $start = microtime(true);

        try {
            $result = $this->execute_tool($name, $args);
            $this->log_tool_call($name, $args, 'success', null, $start, $result, null);
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => is_string($result) ? $result : wp_json_encode($result, JSON_PRETTY_PRINT),
                    ]],
                ],
            ];
        } catch (\Throwable $e) {

            

            

            

            
            
            $this->log_tool_call($name, $args, 'error', $e->getMessage(), $start, null, $e);
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Error: ' . $e->getMessage(),
                    ]],
                    'isError' => true,
                ],
            ];
        }
    }

    private function list_resources(): array {
        return [
            [
                'uri'         => 'more_mcp://capabilities',
                'name'        => 'Site capabilities',
                'description' => 'What this WordPress site can do and which active plugin/theme providers back each capability (page building, commerce, forms, analytics, SEO, caching, security, backup, custom fields, redirects). Read this to choose the right native tool family; it does not replace the tools themselves.',
                'mimeType'    => 'application/json',
            ],
        ];
    }

    private function handle_resource_read($id, $params) {
        $uri = isset($params['uri']) ? (string) $params['uri'] : '';

        if ('more_mcp://capabilities' !== $uri) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'error'   => [
                    'code'    => -32602,
                    'message' => 'Unknown resource: ' . $uri,
                ],
            ];
        }

        $payload = [
            'capabilities' => CapabilityMap::for_display(),
        ];

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'contents' => [[
                    'uri'      => $uri,
                    'mimeType' => 'application/json',
                    'text'     => wp_json_encode($payload, JSON_PRETTY_PRINT),
                ]],
            ],
        ];
    }

    private function log_tool_call($tool_name, $args, $status, $error_message, $start_time = null, $result = null, $exception = null) {
        global $wpdb;

        $request_meta = [
            'tool'     => (string) $tool_name,
            'arg_keys' => is_array($args) ? array_keys($args) : [],
        ];

        $response_meta = [ 'status' => $status ];
        if ('error' === $status && $error_message) {
            $response_meta['error'] = (string) $error_message;
        }

        $wpdb->insert(
            $wpdb->prefix . 'more_mcp_logs',
            [
                'mcp_server'    => 'MCP Server',
                'action'        => 'tools/call:' . sanitize_text_field((string) $tool_name),
                'request_data'  => wp_json_encode($request_meta),
                'response_data' => wp_json_encode($response_meta),
                'status'        => 'success' === $status ? 'success' : 'error',
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        do_action('more_mcp_tool_called', (string) $tool_name, (string) $status, (string) ($error_message ?? ''));

        $latency_ms = ( null !== $start_time )
            ? (int) round( ( microtime(true) - (float) $start_time ) * 1000 )
            : 0;

        $response_size = 0;
        if ( null !== $result ) {
            $encoded = wp_json_encode( $result );
            $response_size = is_string( $encoded ) ? strlen( $encoded ) : 0;
        }

        $args_hash = '';
        if ( is_array( $args ) ) {
            $args_encoded = wp_json_encode( $args );
            if ( is_string( $args_encoded ) ) {
                $args_hash = hash( 'sha256', $args_encoded );
            }
        }

        $context = [
            'status'              => (string) $status,
            'error_message'       => (string) ( $error_message ?? '' ),
            'error_class'         => ( $exception instanceof \Throwable ) ? get_class( $exception ) : null,
            'latency_ms'          => $latency_ms,
            'response_size_bytes' => $response_size,
            'tool_args_hash'      => $args_hash,
            'arg_keys'            => is_array( $args ) ? array_keys( $args ) : [],
            'is_destructive'      => $this->is_destructive_tool( (string) $tool_name ),
        ];

        do_action( 'more_mcp_tool_context', (string) $tool_name, $context );
    }

    private function is_destructive_tool( $tool_name ) {
        static $destructive_prefixes = [
            'wp_delete_',
            'wc_delete_',
            'wp_trash_',
            'wp_spam_',
        ];

        static $destructive_exact = [
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
            'wp_restore_revision',
            'wp_update_permalink_structure',
            'wp_update_custom_css',
            'wp_update_widget',
            'wp_update_option',
            'wc_create_order',
            'wc_update_order',
            'wc_add_order_note',
            'fc_clear_cache',
            'fc_purge_url',
            'ls_purge_all',
            'ls_purge_url',
            'sv_create_backup',
        ];

        if ( in_array( $tool_name, $destructive_exact, true ) ) {
            return true;
        }
        foreach ( $destructive_prefixes as $prefix ) {
            if ( 0 === strpos( $tool_name, $prefix ) ) {
                return true;
            }
        }
        return false;
    }

    public function get_all_tools(): array {
        return $this->get_tools();
    }

    public function invoke( string $name, array $args ) {
        return $this->execute_tool( $name, $args );
    }

    private function execute_tool($name, $args) {

        

        

        
        switch ($name) {

            

            case 'more_mcp_connection_health':
                global $wp_version;

                
                
                $builders = [
                    'divi_version'      => defined('ET_BUILDER_VERSION') ? (string) constant('ET_BUILDER_VERSION') : null,
                    'elementor_version' => defined('ELEMENTOR_VERSION') ? (string) constant('ELEMENTOR_VERSION') : null,

                    

                    'gutenberg_version' => defined('GUTENBERG_VERSION') ? (string) constant('GUTENBERG_VERSION') : null,
                ];
                return [
                    'route'          => rest_url('more-mcp/v1/mcp'),
                    'auth_method'    => $this->request_auth_method ?? 'unauthenticated',
                    'relay'          => null,
                    'token_ttl'      => $this->request_token_ttl,
                    'session_id'     => $this->request_session_id,
                    'active_scopes'  => ['tools'],
                    'server_version' => defined('MORE_MCP_VERSION') ? MORE_MCP_VERSION : 'unknown',
                    'wp_version'     => isset($wp_version) ? (string) $wp_version : (string) get_bloginfo('version'),
                    'php_version'    => PHP_VERSION,
                    'builders'       => $builders,
                ];

            case 'more_mcp_undo_last_operation':

                return \More_MCP\MCP\Undo_Dispatcher::dispatch($args);

            default:

                

                if ( in_array( $name, LifecycleManager::tool_names(), true ) ) {
                    return LifecycleManager::execute_tool( $name, $args );
                }

                

                $core_handler = ToolsRegistry::find_handler( $name );
                if ( null !== $core_handler ) {
                    return $core_handler::execute_tool( $name, $args );
                }

                

                if ( strpos( $name, 'blocks_' ) === 0 ) {
                    static $blocks_template_tools = null;
                    if ( null === $blocks_template_tools ) {
                        $blocks_template_tools = array_column( BlocksTemplates::get_tools(), 'name' );
                    }
                    if ( in_array( $name, $blocks_template_tools, true ) ) {
                        return BlocksTemplates::execute_tool( $name, $args );
                    }
                    return BlocksContent::execute_tool( $name, $args );
                }

                

                

                
                
                if ( ! \More_MCP\Capabilities\Toggles::tool_is_allowed( $name ) ) {
                    $disabled_slug = \More_MCP\Capabilities\Toggles::slug_for_tool( $name );
                    throw new \Exception(
                        'The ' . esc_html( $disabled_slug ) . ' integration is turned off for MCP. '
                        . 'Enable it in More MCP settings (Permissions) before its tools can be used.'
                    );
                }
                if ( strpos( $name, 'wc_' ) === 0 ) {
                    return WooIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ls_' ) === 0 ) {
                    return LSIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'elementor_' ) === 0 ) {
                    return ElementorIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'divi_' ) === 0 ) {
                    return DiviIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'acf_' ) === 0 ) {
                    return ACFIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'mb_' ) === 0 ) {
                    return MetaBoxIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'redirection_' ) === 0 ) {
                    return RedirectionIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'analytics_' ) === 0 ) {
                    return AnalyticsIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'email_' ) === 0 ) {
                    return EmailIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'forms_' ) === 0 ) {
                    return FormsIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpr_' ) === 0 ) {
                    return WPRocketIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'up_' ) === 0 ) {
                    return UpdraftPlusIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bwu_' ) === 0 ) {
                    return BackWPupIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wf_' ) === 0 ) {
                    return WordfenceIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'def_' ) === 0 ) {
                    return DefenderIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'akismet_' ) === 0 ) {
                    return AkismetIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'imagify_' ) === 0 ) {
                    return ImagifyIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'trp_' ) === 0 ) {
                    return TranslatePressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'crm_' ) === 0 ) {
                    return FluentCRMIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'lms_' ) === 0 ) {
                    return LearnPressIntegration::execute_tool( $name, $args );
                }

                

                if ( in_array( $name, SEODataManager::tool_names(), true ) ) {
                    return SEODataManager::execute_tool( $name, $args );
                }

                

                
                
                if ( in_array( $name, AbilitiesImporter::tool_names(), true ) ) {
                    return AbilitiesImporter::execute_tool( $name, $args );
                }
                throw new \Exception('Unknown tool: ' . esc_html($name));
        }
    }

    private function detect_seo_plugin() {
        return \More_MCP\SEO\Detector::primary();
    }

    

    
    public function handle_sse($request) {
        
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode([
            'error' => 'SSE transport deprecated',
            'message' => 'Please use the Streamable HTTP transport at /wp-json/more-mcp/v1/mcp',
            'endpoint' => rest_url('more-mcp/v1/mcp'),
            'spec' => '2025-11-25'
        ]);
        exit;
    }

    public function handle_message($request) {
        
        return $this->handle_mcp($request);
    }
}
