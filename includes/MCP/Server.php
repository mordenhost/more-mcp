<?php
namespace Mordenhost_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Mordenhost_MCP\Integrations\WooCommerce as WooIntegration;
use Mordenhost_MCP\Integrations\LiteSpeed as LSIntegration;
use Mordenhost_MCP\Integrations\Elementor as ElementorIntegration;
use Mordenhost_MCP\Integrations\Divi as DiviIntegration;
use Mordenhost_MCP\Integrations\BeaverBuilder as BeaverIntegration;
use Mordenhost_MCP\Integrations\SiteOriginPanels as SiteOriginIntegration;
use Mordenhost_MCP\Integrations\Advanced_Custom_Fields as ACFIntegration;
use Mordenhost_MCP\Integrations\MetaBox as MetaBoxIntegration;
use Mordenhost_MCP\Integrations\Redirection as RedirectionIntegration;
use Mordenhost_MCP\Integrations\RankMath as RankMathIntegration;
use Mordenhost_MCP\Integrations\Analytics as AnalyticsIntegration;
use Mordenhost_MCP\Integrations\Forms as FormsIntegration;
use Mordenhost_MCP\Integrations\Email as EmailIntegration;
use Mordenhost_MCP\Integrations\WPRocket as WPRocketIntegration;
use Mordenhost_MCP\Integrations\W3TotalCache as W3TCIntegration;
use Mordenhost_MCP\Integrations\RedisObjectCache as RedisIntegration;
use Mordenhost_MCP\Integrations\Autoptimize as AutoptimizeIntegration;
use Mordenhost_MCP\Integrations\WPOptimize as WPOptimizeIntegration;
use Mordenhost_MCP\Integrations\WPSuperCache as WPSuperCacheIntegration;
use Mordenhost_MCP\Integrations\WPFastestCache as WPFastestCacheIntegration;
use Mordenhost_MCP\Integrations\UpdraftPlus as UpdraftPlusIntegration;
use Mordenhost_MCP\Integrations\BackWPup as BackWPupIntegration;
use Mordenhost_MCP\Integrations\Duplicator as DuplicatorIntegration;
use Mordenhost_MCP\Integrations\WPvivid as WPvividIntegration;
use Mordenhost_MCP\Integrations\Wordfence as WordfenceIntegration;
use Mordenhost_MCP\Integrations\Defender as DefenderIntegration;
use Mordenhost_MCP\Integrations\SolidSecurity as SolidSecurityIntegration;
use Mordenhost_MCP\Integrations\Sucuri as SucuriIntegration;
use Mordenhost_MCP\Integrations\ReallySimpleSecurity as ReallySimpleSecurityIntegration;
use Mordenhost_MCP\Integrations\AIOS as AIOSIntegration;
use Mordenhost_MCP\Integrations\LimitLoginAttemptsReloaded as LLARIntegration;
use Mordenhost_MCP\Integrations\Akismet as AkismetIntegration;
use Mordenhost_MCP\Integrations\Imagify as ImagifyIntegration;
use Mordenhost_MCP\Integrations\EWWW as EWWWIntegration;
use Mordenhost_MCP\Integrations\Smush as SmushIntegration;
use Mordenhost_MCP\Integrations\ShortPixel as ShortPixelIntegration;
use Mordenhost_MCP\Integrations\InstantImages as InstantImagesIntegration;
use Mordenhost_MCP\Integrations\EventsCalendar as EventsCalendarIntegration;
use Mordenhost_MCP\Integrations\SugarCalendar as SugarCalendarIntegration;
use Mordenhost_MCP\Integrations\EventsManager as EventsManagerIntegration;
use Mordenhost_MCP\Integrations\ModernEventsCalendar as ModernEventsCalendarIntegration;
use Mordenhost_MCP\Integrations\AIMedia as AIMediaIntegration;
use Mordenhost_MCP\Integrations\Snippets as SnippetsIntegration;
use Mordenhost_MCP\Integrations\TranslatePress as TranslatePressIntegration;
use Mordenhost_MCP\Integrations\Polylang as PolylangIntegration;
use Mordenhost_MCP\Integrations\WPML as WPMLIntegration;
use Mordenhost_MCP\Integrations\Weglot as WeglotIntegration;
use Mordenhost_MCP\Integrations\GeoDirectory as GeoDirectoryIntegration;
use Mordenhost_MCP\Integrations\BusinessDirectory as BusinessDirectoryIntegration;
use Mordenhost_MCP\Integrations\IvorySearch as IvorySearchIntegration;
use Mordenhost_MCP\Integrations\UncannyAutomator as UncannyAutomatorIntegration;
use Mordenhost_MCP\Integrations\Blog2Social as Blog2SocialIntegration;
use Mordenhost_MCP\Integrations\Estatik as EstatikIntegration;
use Mordenhost_MCP\Integrations\ToolsetTypes as ToolsetTypesIntegration;
use Mordenhost_MCP\Integrations\FluentCRM as FluentCRMIntegration;
use Mordenhost_MCP\Integrations\MailPoet as MailPoetIntegration;
use Mordenhost_MCP\Integrations\Groundhogg as GroundhoggIntegration;
use Mordenhost_MCP\Integrations\GiveWP as GiveWPIntegration;
use Mordenhost_MCP\Integrations\PaidMembershipsPro as PaidMembershipsProIntegration;
use Mordenhost_MCP\Integrations\SimpleMembership as SimpleMembershipIntegration;
use Mordenhost_MCP\Integrations\ProfilePress as ProfilePressIntegration;
use Mordenhost_MCP\Integrations\RestrictContent as RestrictContentIntegration;
use Mordenhost_MCP\Integrations\UltimateMember as UltimateMemberIntegration;
use Mordenhost_MCP\Integrations\Charitable as CharitableIntegration;
use Mordenhost_MCP\Integrations\BBPress as BBPressIntegration;
use Mordenhost_MCP\Integrations\BuddyPress as BuddyPressIntegration;
use Mordenhost_MCP\Integrations\FluentSupport as FluentSupportIntegration;
use Mordenhost_MCP\Integrations\LearnPress as LearnPressIntegration;
use Mordenhost_MCP\Integrations\Sensei as SenseiIntegration;
use Mordenhost_MCP\Integrations\Masteriyo as MasteriyoIntegration;
use Mordenhost_MCP\Integrations\Tutor as TutorIntegration;
use Mordenhost_MCP\Integrations\LifterLMS as LifterLMSIntegration;
use Mordenhost_MCP\Integrations\Amelia as AmeliaIntegration;
use Mordenhost_MCP\Integrations\LatePoint as LatePointIntegration;
use Mordenhost_MCP\Integrations\Bookly as BooklyIntegration;
use Mordenhost_MCP\Integrations\BookingPress as BookingPressIntegration;
use Mordenhost_MCP\Integrations\Pods as PodsIntegration;
use Mordenhost_MCP\Integrations\CptUi as CptUiIntegration;
use Mordenhost_MCP\Integrations\MbCpt as MbCptIntegration;
use Mordenhost_MCP\Integrations\EDD as EDDIntegration;
use Mordenhost_MCP\Integrations\FluentCart as FluentCartIntegration;
use Mordenhost_MCP\Integrations\Ai1wm as Ai1wmIntegration;
use Mordenhost_MCP\Integrations\Patchstack as PatchstackIntegration;
use Mordenhost_MCP\Integrations\Relevanssi as RelevanssiIntegration;
use Mordenhost_MCP\Integrations\SliceWP as SliceWPIntegration;
use Mordenhost_MCP\Integrations\AutomatorWP as AutomatorWPIntegration;
use Mordenhost_MCP\Integrations\WPJobManager as WPJobManagerIntegration;
use Mordenhost_MCP\Integrations\WpGoMaps as WpGoMapsIntegration;
use Mordenhost_MCP\Integrations\Jetpack as JetpackIntegration;
use Mordenhost_MCP\Webhooks\Manager as WebhooksManager;
use Mordenhost_MCP\Integrations\Elementor_Coexistence;
use Mordenhost_MCP\Blocks\Content as BlocksContent;
use Mordenhost_MCP\Blocks\Templates as BlocksTemplates;
use Mordenhost_MCP\Blocks\Global_Styles as BlocksGlobalStyles;
use Mordenhost_MCP\Lifecycle\Manager as LifecycleManager;
use Mordenhost_MCP\Knowledge\Experimental;
use Mordenhost_MCP\Knowledge\Memory_Manager;
use Mordenhost_MCP\Knowledge\Recall;
use Mordenhost_MCP\SEO_Data\Manager as SEODataManager;
use Mordenhost_MCP\Tools\Registry as ToolsRegistry;
use Mordenhost_MCP\Abilities\Importer as AbilitiesImporter;
use Mordenhost_MCP\Capabilities\Map as CapabilityMap;
use Mordenhost_MCP\MCP\Prompts;
use Mordenhost_MCP\Access\Policy as AccessPolicy;
use Mordenhost_MCP\Access\Safety;
use Mordenhost_MCP\Auth\Api_Tokens;
use Mordenhost_MCP\Auth\App_Passwords;

class Server {

    private $rate_limit_max = 60;
    private $rate_limit_window = 60; 

    private $request_auth_fingerprint = '';

    private $request_auth_method  = null;   
    private $request_token_ttl    = null;   
    private $dry_run_tools        = null;   
    private $request_session_id   = null;   

    private $request_policy_key   = null;

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

        $allowed_hosts = apply_filters('mordenhost_mcp_allowed_origins', $allowed_hosts);

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
        $settings = get_option('mordenhost_mcp_settings', []);

        if (empty($settings['enabled'])) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Mordenhost MCP is currently disabled.',
                ],
            ], 403);
        }

        

        

        

        
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header) && stripos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            if (0 === strpos($token, Api_Tokens::PREFIX)) {
                return $this->validate_named_token($token);
            }
            $oauth_result = $this->validate_bearer_token($token);
            if (true === $oauth_result) {
                return true;
            }
            $api_key_result = $this->validate_api_key_value($token, $settings);
            if (true === $api_key_result) {
                return true;
            }

            
            Log_Store::log_auth_failure('invalid or expired credential');
            return $oauth_result;
        }

        
        
        $api_key = $request->get_header('MMCP-Key');
        if (!empty($api_key)) {
            if (0 === strpos($api_key, Api_Tokens::PREFIX)) {
                return $this->validate_named_token($api_key);
            }
            $key_result = $this->validate_api_key_value($api_key, $settings);
            if (true !== $key_result) {
                Log_Store::log_auth_failure('invalid API key');
            }
            return $key_result;
        }

        

        
        if (!empty($auth_header) && 0 === stripos($auth_header, 'Basic ') && App_Passwords::enabled()) {
            return $this->validate_app_password($auth_header);
        }

        

        
        
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'This endpoint requires credentials: send an OAuth 2.0 access token as "Authorization: Bearer <token>", or supply your API key in the "MMCP-Key" header.',
            ],
        ], 401);
        $response->header('WWW-Authenticate', $this->www_authenticate());
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private function validate_api_key_value($api_key, $settings = null) {
        if (null === $settings) {
            $settings = get_option('mordenhost_mcp_settings', []);
        }

        $stored = isset($settings['api_key']) ? (string) $settings['api_key'] : '';

        

        
        if (\Mordenhost_MCP\Auth\Api_Key::is_legacy_format($api_key)) {
            return $this->api_key_error(
                'This API key uses the retired format. Open Mordenhost MCP → Settings and click Regenerate to mint a key in the current format, then update your client.'
            );
        }

        if (!\Mordenhost_MCP\Auth\Api_Key::is_valid_format($api_key)) {
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
        $this->request_policy_key       = AccessPolicy::API_KEY;

        $this->request_auth_method = 'api-key';
        $this->request_token_ttl   = null;

        Request_Context::set([
            'key'   => AccessPolicy::API_KEY,
            'label' => 'API key',
            'type'  => 'api-key',
        ]);

        return true;
    }

    private function validate_named_token($raw) {
        $result = Api_Tokens::authenticate($raw);
        if ('' !== $result['error']) {
            $messages = [
                'expired'        => 'This API token has expired. Create a new one in Mordenhost MCP > Tokens.',
                'revoked'        => 'This API token has been revoked.',
                'wrong_site'     => 'This API token belongs to a different site.',
                'user_gone'      => 'The WordPress user this API token acts as no longer exists.',
                'policy_missing' => 'This API token\'s access level could not be found. Revoke it and create a new one in Mordenhost MCP > Tokens.',
            ];

            
            $known = is_array($result['row'])
                ? ['credential' => 'Token: ' . $result['row']['label'], 'token_id' => (int) $result['row']['id']]
                : [];
            Log_Store::log_auth_failure('API token refused: ' . $result['error'], $known);
            return $this->api_key_error($messages[$result['error']] ?? 'Invalid API token.');
        }

        $row   = $result['row'];
        $limit = Api_Tokens::rate_limit($row);
        if ($limit > 0 && !Rate_Limiter::check('token:' . (int) $row['id'], $limit, 60)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'This token\'s rate limit was exceeded. Maximum ' . $limit . ' requests per minute.',
                ],
            ], 429);
        }

        wp_set_current_user((int) $row['user_id']);

        $id = (int) $row['id'];
        $this->request_policy_key       = Api_Tokens::key_for($id);
        $this->request_auth_fingerprint = hash('sha256', 'token:' . $id . ':' . (int) $row['user_id']);
        $this->request_auth_method      = 'api-token';
        $this->request_token_ttl        = null;
        if (!empty($row['expires_at'])) {
            $this->request_token_ttl = max(0, (int) strtotime($row['expires_at'] . ' UTC') - time());
        }

        Request_Context::set([
            'key'         => $this->request_policy_key,
            'label'       => 'Token: ' . $row['label'],
            'type'        => 'api-token',
            'token_id'    => $id,
            'token_scope' => (string) $row['preset'],
        ]);
        Api_Tokens::touch($row, Request_Context::ip());

        return true;
    }

    private function validate_app_password($header) {
        $result = App_Passwords::authenticate((string) $header);
        if (!$result['ok']) {
            Log_Store::log_auth_failure(App_Passwords::failure_reason($result['error'], $result['core_code']), array('auth_method' => App_Passwords::METHOD));
            if ('insecure' === $result['error']) {

                $response = new \WP_REST_Response([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32600,
                        'message' => App_Passwords::message('insecure'),
                    ],
                ], 403);
                $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                $response->header('Pragma', 'no-cache');
                return $response;
            }
            return $this->api_key_error(App_Passwords::message($result['error']));
        }

        $user_id = (int) $result['user']->ID;
        wp_set_current_user($user_id);

        
        $this->request_policy_key       = AccessPolicy::key_for_app_password($user_id, $result['uuid']);
        $this->request_auth_fingerprint = hash('sha256', 'app-password:' . $user_id . ':' . $result['uuid']);
        $this->request_auth_method      = App_Passwords::METHOD;
        $this->request_token_ttl        = null;

        Request_Context::set([
            'key'          => $this->request_policy_key,
            'label'        => 'App password: ' . $result['name'],
            'type'         => App_Passwords::METHOD,
            'app_password' => [
                'name' => $result['name'],
                'uuid' => $result['uuid'],
            ],
        ]);

        return true;
    }

    private function api_key_error($message) {
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => $message,
            ],
        ], 401);
        $response->header('WWW-Authenticate', $this->www_authenticate('invalid_token'));
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private function www_authenticate($error = '') {
        $parts = [];
        if ('' !== $error) {
            $parts[] = 'error="' . $error . '"';
        }
        if (\Mordenhost_MCP\OAuth\Token_Store::oauth_enabled()) {
            $parts[] = 'resource_metadata="' . home_url('/.well-known/oauth-protected-resource' . \Mordenhost_MCP\Platform\Legacy::resource_metadata_suffix()) . '"';
        }
        return 'Bearer' . ($parts ? ' ' . implode(', ', $parts) : '');
    }

    private function validate_bearer_token($raw_token) {
        $token_data = \Mordenhost_MCP\OAuth\Token_Store::validate_token($raw_token);

        if (!$token_data) {
            $response = new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid or expired access token.',
                ],
            ], 401);
            $response->header('WWW-Authenticate', $this->www_authenticate('invalid_token'));
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
            return $response;
        }

        wp_set_current_user((int) $token_data['user_id']);

        
        $this->request_policy_key = AccessPolicy::key_for_grant(
            (string) $token_data['client_id'],
            (int) $token_data['user_id']
        );

        
        
        $this->request_auth_fingerprint = hash(
            'sha256',
            'oauth:' . (string) $token_data['client_id'] . ':' . (int) $token_data['user_id']
        );

        $client       = \Mordenhost_MCP\OAuth\Token_Store::get_client((string) $token_data['client_id']);
        $client_label = (is_array($client) && !empty($client['client_name'])) ? (string) $client['client_name'] : (string) $token_data['client_id'];
        Request_Context::set([
            'key'    => $this->request_policy_key,
            'label'  => 'OAuth: ' . $client_label,
            'type'   => 'oauth',
            'client' => (string) $token_data['client_id'],
        ]);

        $this->request_auth_method = 'oauth-bearer';
        if ( ! empty( $token_data['expires_at'] ) ) {
            $expires_ts = strtotime( (string) $token_data['expires_at'] . ' UTC' );
            if ( $expires_ts ) {
                $this->request_token_ttl = max( 0, $expires_ts - time() );
            }
        }

        return true;
    }

    private function check_ip_allowlist() {
        if (!AccessPolicy::ip_allowlist_enabled()) {
            return true;
        }

        $ip = AccessPolicy::client_ip();
        if (AccessPolicy::ip_allowed($ip)) {
            return true;
        }

        do_action('mordenhost_mcp_ip_blocked', $ip);

        return new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Access from this IP address is not allowed.',
            ],
        ], 403);
    }

    private function check_rate_limit($ip) {

        $max = \Mordenhost_MCP\Platform\Config::int('rate_limit', (int) $this->rate_limit_max);
        $max = (int) apply_filters('mordenhost_mcp_rate_limit', $max > 0 ? $max : (int) $this->rate_limit_max);
        if ($max < 1) {
            $max = (int) $this->rate_limit_max;
        }
        if (!Rate_Limiter::check($ip, $max, (int) $this->rate_limit_window)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Rate limit exceeded. Maximum ' . $max . ' requests per minute.',
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

            
            ...\Mordenhost_MCP\Tools\Posts::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Pages::get_tools(),

            
            
            ...\Mordenhost_MCP\Tools\Media::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Terms::get_tools(),

            
            ...\Mordenhost_MCP\Tools\TermMeta::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Comments::get_tools(),

            

            
            ...\Mordenhost_MCP\Tools\Users::get_tools(),

            
            ...\Mordenhost_MCP\Tools\PostMeta::get_tools(),

            

            
            ...\Mordenhost_MCP\Tools\Site::get_tools(),
            ['name' => 'mordenhost_mcp_connection_health', 'description' => 'Diagnostic probe for the current MCP connection. Returns MCP endpoint route, authentication method used by this request (api-key, api-token, oauth-bearer or app-password), OAuth access token time-to-live in seconds (null for api-key and app-password), current MCP session ID, active MCP capabilities negotiated at initialize, plus Mordenhost MCP + WordPress + PHP version strings. For a manage_options user it also reports integrations: which are active, which supported plugins are detected but not enabled for MCP (with the tool prefixes they would add), and where an administrator enables them; null for other users and for connections that may not read the plugin list. Integrations cannot be enabled over MCP. No arguments. Call at connection start to confirm setup, when an expected plugin\'s tools are missing, or when diagnosing 401/403/404 issues. Any authenticated caller.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'mordenhost_mcp_undo_last_operation', 'description' => 'Reverse one prior operation using the token from its response. LIMITED SCOPE: only some tools emit undo tokens, and a tool that does not emit one cannot be undone through this. Currently covered: the Gutenberg block mutations (blocks_insert, blocks_update, blocks_delete, blocks_move), the template and reusable-block writes (blocks_update_template, blocks_revert_template, blocks_update_reusable, blocks_delete_reusable), wp_reorder_menu_items, the Elementor element writes (elementor_update_widget, elementor_delete_widget, elementor_set_page_data, and wp_update_post_meta on the _elementor_data key), and the Elementor kit write (elementor_update_kit), the FSE global styles write (blocks_update_global_styles), and the Elementor Pro font/icon writes (elementor_create_font, elementor_update_font, elementor_delete_font, elementor_delete_icon_set), and the Elementor Pro Custom Code writes (elementor_create_code, elementor_update_code, elementor_delete_code), and the Divi whole-node writes (divi_replace_module, divi_insert_module, divi_delete_module), and the forms entry writes (forms_update_entry_status, forms_trash_entry), and the data-model writes (cptui_save_post_type, cptui_delete_post_type, cptui_save_taxonomy, cptui_delete_taxonomy, the same four toolset_* and mbcpt_* tools, and mb_update_field), and wp_delete_menu, and the Yoast SEO feature toggle write (seo_update_yoast_features). No undo token, but recorded in change history and restorable with wp_history_list + wp_history_apply: wp_update_post, wp_update_option, wp_update_seo_meta, wp_update_term_seo_meta, wp_update_post_meta on any other key, and the other elementor_* tools that name a post_id (history covers posts with their meta and terms, options, theme mods, custom CSS, terms, comments and users). Neither covers wp_update_widget or the wc_* tools. Check for an `undo` key in a response rather than assuming one exists. Tokens live 72 hours and are one-shot, consumed on a successful undo. The capability requirement matches the original operation and is re-checked at redemption, because a token may be presented by a different caller than the one who created it.', 'inputSchema' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string', 'description' => 'The undo token from a prior tool response (response.undo.token).']], 'required' => ['token']]],
            ...\Mordenhost_MCP\Tools\Search::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Options::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Menus::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Appearance::get_tools(),

            

            ...\Mordenhost_MCP\Tools\PageTemplates::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Seo::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Permalink::get_tools(),

            
            ...\Mordenhost_MCP\Tools\Revisions::get_tools(),

            
            ...ToolsRegistry::extended_tools(),
        ];

        

        

        
        
        $integration_classes = [
            'woocommerce'    => WooIntegration::class,
            'litespeed'      => LSIntegration::class,
            'elementor'      => ElementorIntegration::class,
            'divi'           => DiviIntegration::class,
            'beaver-builder' => BeaverIntegration::class,
            'siteorigin-panels' => SiteOriginIntegration::class,
            'acf'            => ACFIntegration::class,
            'metabox'        => MetaBoxIntegration::class,
            'redirection'    => RedirectionIntegration::class,
            'rank_math'      => RankMathIntegration::class,
            'analytics'      => AnalyticsIntegration::class,
            'email'          => EmailIntegration::class,
            'forms'          => FormsIntegration::class,
            'wp-rocket'      => WPRocketIntegration::class,
            'w3tc'           => W3TCIntegration::class,
            'redis-cache'    => RedisIntegration::class,
            'autoptimize'    => AutoptimizeIntegration::class,
            'wp-optimize'    => WPOptimizeIntegration::class,
            'wp-super-cache' => WPSuperCacheIntegration::class,
            'wp-fastest-cache' => WPFastestCacheIntegration::class,
            'updraftplus'    => UpdraftPlusIntegration::class,
            'backwpup'       => BackWPupIntegration::class,
            'duplicator'     => DuplicatorIntegration::class,
            'wpvivid'        => WPvividIntegration::class,
            'wordfence'      => WordfenceIntegration::class,
            'defender'       => DefenderIntegration::class,
            'solid-security' => SolidSecurityIntegration::class,
            'sucuri'         => SucuriIntegration::class,
            'really-simple-security' => ReallySimpleSecurityIntegration::class,
            'aios'           => AIOSIntegration::class,
            'llar'           => LLARIntegration::class,
            'akismet'        => AkismetIntegration::class,
            'imagify'        => ImagifyIntegration::class,
            'ewww'           => EWWWIntegration::class,
            'smush'          => SmushIntegration::class,
            'shortpixel'     => ShortPixelIntegration::class,
            'instant-images' => InstantImagesIntegration::class,
            'events-calendar' => EventsCalendarIntegration::class,
            'sugar-calendar' => SugarCalendarIntegration::class,
            'events-manager' => EventsManagerIntegration::class,
            'modern-events-calendar' => ModernEventsCalendarIntegration::class,
            'translatepress' => TranslatePressIntegration::class,
            'polylang'       => PolylangIntegration::class,
            'wpml'           => WPMLIntegration::class,
            'weglot'         => WeglotIntegration::class,
            'geodirectory'   => GeoDirectoryIntegration::class,
            'business-directory' => BusinessDirectoryIntegration::class,
            'ivory-search'   => IvorySearchIntegration::class,
            'uncanny-automator' => UncannyAutomatorIntegration::class,
            'blog2social'    => Blog2SocialIntegration::class,
            'estatik'        => EstatikIntegration::class,
            'toolset-types'  => ToolsetTypesIntegration::class,
            'fluentcrm'      => FluentCRMIntegration::class,
            'mailpoet'       => MailPoetIntegration::class,
            'groundhogg'     => GroundhoggIntegration::class,
            'givewp'         => GiveWPIntegration::class,
            'pmpro'          => PaidMembershipsProIntegration::class,
            'swpm'           => SimpleMembershipIntegration::class,
            'profilepress'   => ProfilePressIntegration::class,
            'restrictcontent' => RestrictContentIntegration::class,
            'ultimate-member' => UltimateMemberIntegration::class,
            'charitable'     => CharitableIntegration::class,
            'bbpress'        => BBPressIntegration::class,
            'buddypress'     => BuddyPressIntegration::class,
            'fluentsupport'  => FluentSupportIntegration::class,
            'learnpress'     => LearnPressIntegration::class,
            'sensei'         => SenseiIntegration::class,
            'masteriyo'      => MasteriyoIntegration::class,
            'tutor'          => TutorIntegration::class,
            'lifterlms'      => LifterLMSIntegration::class,
            'amelia'         => AmeliaIntegration::class,
            'latepoint'      => LatePointIntegration::class,
            'bookly'         => BooklyIntegration::class,
            'bookingpress'   => BookingPressIntegration::class,
            'pods'           => PodsIntegration::class,
            'cptui'          => CptUiIntegration::class,
            'mbcpt'          => MbCptIntegration::class,
            'edd'            => EDDIntegration::class,
            'fluentcart'     => FluentCartIntegration::class,
            'ai1wm'          => Ai1wmIntegration::class,
            'patchstack'     => PatchstackIntegration::class,
            'relevanssi'     => RelevanssiIntegration::class,
            'slicewp'        => SliceWPIntegration::class,
            'automatorwp'    => AutomatorWPIntegration::class,
            'wpjm'           => WPJobManagerIntegration::class,
            'wp-go-maps'     => WpGoMapsIntegration::class,
            'jetpack'        => JetpackIntegration::class,
        ];
        foreach ( $integration_classes as $slug => $class ) {
            if ( ! \Mordenhost_MCP\Capabilities\Toggles::is_enabled( $slug ) ) {
                continue; 
            }
            $tools = array_merge( $tools, $class::get_tools() );
        }

        

        
        
        $tools = array_merge( $tools, AIMediaIntegration::get_tools() );

        
        
        if ( SnippetsIntegration::is_available() ) {
            $tools = array_merge( $tools, SnippetsIntegration::get_tools() );
        }

        
        
        $tools = array_merge( $tools, WebhooksManager::get_tools() );

        
        
        $tools = array_merge( $tools, BlocksContent::get_tools() );
        $tools = array_merge( $tools, BlocksTemplates::get_tools() );
        $tools = array_merge( $tools, BlocksGlobalStyles::get_tools() );

        
        
        $tools = array_merge( $tools, LifecycleManager::get_tools() );

        
        
        $tools = array_merge( $tools, Memory_Manager::get_tools() );

        

        $tools = array_merge( $tools, SEODataManager::get_tools() );

        

        
        
        $tools = array_merge( $tools, AbilitiesImporter::get_tools() );

        

        $tools = Elementor_Coexistence::filter_elementor_tool_descriptions( $tools );

        return apply_filters( 'mordenhost_mcp_tools', $tools );
    }

    public function handle_mcp($request) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        if ($method === 'OPTIONS') {
            return $this->cors_response();
        }

        Request_Context::reset();

        $origin_check = $this->validate_origin($request);
        if ($origin_check !== true) {
            return $origin_check;
        }

        
        
        $ip_check = $this->check_ip_allowlist();
        if ($ip_check !== true) {
            Log_Store::log_ip_blocked(Request_Context::ip(), 'mcp');
            return $ip_check;
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

            

            
            
            $max_post_bytes  = (int) apply_filters('mordenhost_mcp_max_post_bytes', 5 * 1024 * 1024);
            $declared_length = isset($_SERVER['CONTENT_LENGTH']) ? absint($_SERVER['CONTENT_LENGTH']) : 0;
            if ($max_post_bytes > 0 && $declared_length > $max_post_bytes) {
                return new \WP_REST_Response([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32000, 
                        'message' => 'Request body exceeds the maximum accepted size.',
                    ],
                ], 413);
            }

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
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, Mcp-Session-Id, MCP-Protocol-Version, Mcp-Method, Mcp-Name, MMCP-Key');
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
            echo ": mordenhost-mcp-keepalive\n\n";
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

        

        $proto_error = Protocol::validate($method, $params, [
            'protocol_version' => (string) $request->get_header('MCP-Protocol-Version'),
            'method'           => (string) $request->get_header('Mcp-Method'),
            'name'             => (string) $request->get_header('Mcp-Name'),
        ]);
        if (null !== $proto_error) {
            $error = ['code' => $proto_error['code'], 'message' => $proto_error['message']];
            if (isset($proto_error['data'])) {
                $error['data'] = $proto_error['data'];
            }
            return $this->json_response(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error], $proto_error['status']);
        }
        if (Protocol::is_modern($params)) {
            return $this->handle_stateless_message($method, $params, $id);
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

    private function handle_stateless_message($method, $params, $id) {
        $this->request_session_id = null;

        $result = $this->process_method($method, $params, $id);

        if (null === $id) {
            return new \WP_REST_Response(null, 202);
        }
        if (!is_array($result)) {
            return $this->json_response(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32603, 'message' => 'Internal error']], 500);
        }

        if (isset($result['error']['code']) && -32601 === $result['error']['code']) {
            return $this->json_response($result, 404);
        }

        $ttl_ms = (int) apply_filters('mordenhost_mcp_list_ttl_ms', 30000);
        $result = Protocol::decorate($method, $result, $this->server_info(), max(0, $ttl_ms));
        return $this->json_response($result, 200);
    }

    private function server_info() {
        return [
            'name'    => 'Mordenhost MCP WordPress',
            'version' => MORDENHOST_MCP_VERSION,
        ];
    }

    private function server_capabilities() {
        return [
            'tools'     => new \stdClass(),
            'resources' => new \stdClass(),
            'prompts'   => new \stdClass(),
        ];
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
                $init = [
                    'protocolVersion' => Protocol::LEGACY,
                    'serverInfo' => $this->server_info(),
                    'capabilities' => $this->server_capabilities(),
                ];

                
                
                $instructions = $this->experimental_instructions();
                if ('' !== $instructions) {
                    $init['instructions'] = $instructions;
                }
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => $init,
                ];

            case 'server/discover':
                $discover = Protocol::discover_result($this->server_capabilities());

                
                $instructions = $this->experimental_instructions();
                if ('' !== $instructions) {
                    $discover['instructions'] = $instructions;
                }
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => $discover,
                ];

            case 'notifications/initialized':
            case 'initialized':
                return null; 

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => $this->tools_for_connection($this->get_tools()),
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
                    'result' => ['prompts' => Prompts::list_prompts( $this->get_all_tools() )],
                ];

            case 'prompts/get':
                return $this->handle_prompt_get($id, $params);

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

    private function tools_for_connection(array $tools): array {
        $policy = AccessPolicy::for_key($this->request_policy_key);

        $chat_approval = Safety::require_approval() && 'chat' === Safety::approval_mode();
        $tools         = $chat_approval ? $tools : array_values(array_filter($tools, static function ($tool) {
            return !isset($tool['name']) || 'mordenhost_mcp_approve_request' !== $tool['name'];
        }));

        
        if (!Safety::privileged_enabled()) {
            $tools = array_values(array_filter($tools, static function ($tool) {
                return !isset($tool['name']) || !in_array($tool['name'], Safety::PRIVILEGED_TOOLS, true);
            }));
        }

        $safety_hides = Safety::whitelist_patterns() || Safety::disabled_tools() || Safety::disable_destructive();

        if (!$safety_hides && !AccessPolicy::read_only_mode() && (null === $policy || AccessPolicy::FULL === $policy['access'])) {
            return $tools;
        }

        return array_values(array_filter($tools, static function ($tool) use ($policy) {
            return isset($tool['name'])
                && AccessPolicy::is_visible((string) $tool['name'], $policy)
                && Safety::is_visible((string) $tool['name']);
        }));
    }

    private function handle_tool_call($id, $params) {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        
        if (is_string($name) && is_array($args)) {
            $args = Arg_Aliases::apply($name, $args);
        }

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
        } catch (Pending_Exception $e) {

            

            
            $this->log_tool_call($name, $args, 'refused', $e->getMessage(), $start, null, $e);
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => wp_json_encode($e->result(), JSON_PRETTY_PRINT),
                    ]],
                ],
            ];
        } catch (\Throwable $e) {

            

            

            

            
            
            $this->log_tool_call($name, $args, $e instanceof Refused_Exception ? 'refused' : 'error', $e->getMessage(), $start, null, $e);
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
        $resources = [
            [
                'uri'         => 'mordenhost_mcp://capabilities',
                'name'        => 'Abilities by area',
                'description' => 'What this WordPress site can do and which active plugin/theme providers back each ability (page building, commerce, forms, analytics, SEO, caching, security, backup, custom fields, redirects). Read this to choose the right ability family; it does not replace the abilities themselves.',
                'mimeType'    => 'application/json',
            ],
        ];

        
        
        foreach (Recall::resources() as $resource) {
            if (Recall::is_memory($resource['uri']) && null !== $this->memory_denied('memory_list')) {
                continue;
            }
            $resources[] = $resource;
        }

        return $resources;
    }

    private function memory_denied(string $tool): ?string {
        $denied = AccessPolicy::decide($tool, AccessPolicy::for_key($this->request_policy_key));
        if (null !== $denied) {
            return $denied;
        }
        return Safety::blocked($tool);
    }

    private function experimental_instructions(): string {
        return Recall::instructions(
            null === $this->memory_denied('memory_list'),
            null === $this->memory_denied('memory_save')
        );
    }

    private function handle_resource_read($id, $params) {
        $uri = isset($params['uri']) ? (string) $params['uri'] : '';

        if (Recall::handles($uri)) {
            $denied = Recall::is_memory($uri) ? $this->memory_denied('memory_list') : null;
            $read   = null === $denied ? Recall::read($uri) : ['error' => ['code' => -32603, 'message' => $denied]];
            if (isset($read['error'])) {
                return [
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'error'   => $read['error'],
                ];
            }
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => [
                    'contents' => [[
                        'uri'      => $uri,
                        'mimeType' => $read['mimeType'],
                        'text'     => $read['text'],
                    ]],
                ],
            ];
        }

        if ('mordenhost_mcp://capabilities' !== $uri) {
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

    private function handle_prompt_get($id, $params) {
        $name = isset($params['name']) ? (string) $params['name'] : '';

        $result = Prompts::get_prompt($name, $this->get_all_tools());

        if (isset($result['error'])) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'error'   => $result['error'],
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    private function log_tool_call($tool_name, $args, $status, $error_message, $start_time = null, $result = null, $exception = null) {
        $args_array = is_array($args) ? $args : [];

        $request_meta = [
            'tool'     => (string) $tool_name,
            'arg_keys' => array_keys($args_array),
            'args'     => Args_Summary::redact($args_array),
            'actor'    => Log_Store::actor(),
        ];

        $response_meta = [ 'status' => $status ];
        if ('error' === $status && $error_message) {
            $response_meta['error'] = (string) $error_message;
        }
        if ('refused' === $status) {
            $response_meta['reason'] = (string) $error_message;
        }
        if (null !== $start_time) {
            $response_meta['duration_ms'] = (int) round((microtime(true) - (float) $start_time) * 1000);
        }

        if (is_array($result) && !empty($result['idempotency']['replayed'])) {
            $response_meta['idempotent_replay'] = true;
        }

        Log_Store::write(
            'tools/call:' . (string) $tool_name,
            $request_meta,
            $response_meta,
            'success' === $status ? 'success' : ('refused' === $status ? 'refused' : 'error')
        );

        

        $hook_status = 'success' === $status ? 'success' : 'error';

        do_action('mordenhost_mcp_tool_called', (string) $tool_name, (string) $hook_status, (string) ($error_message ?? ''));

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
            'status'              => (string) $hook_status,
            'is_refused'          => 'refused' === $status,
            'error_message'       => (string) ( $error_message ?? '' ),
            'error_class'         => ( $exception instanceof \Throwable ) ? get_class( $exception ) : null,
            'latency_ms'          => $latency_ms,
            'response_size_bytes' => $response_size,
            'tool_args_hash'      => $args_hash,
            'arg_keys'            => is_array( $args ) ? array_keys( $args ) : [],
            'is_destructive'      => $this->is_destructive_tool( (string) $tool_name ),
        ];

        do_action( 'mordenhost_mcp_tool_context', (string) $tool_name, $context );
    }

    private function is_destructive_tool( $tool_name ) {
        return \Mordenhost_MCP\Access\Destructive::is( (string) $tool_name );
    }

    public function get_all_tools(): array {
        return $this->get_tools();
    }

    public function invoke( string $name, array $args ) {
        return $this->execute_tool( $name, $args );
    }

    private function execute_tool($name, $args) {

        if (is_array($args)) {
            $args = Arg_Aliases::apply((string) $name, $args);
        }
        $key = Idempotency::take_key($args);
        $tool = (string) $name;
        if (null === $key || !is_array($args) || $this->is_read_call($tool, $args)) {
            return $this->run_tool($name, $args);
        }
        if (!Idempotency::valid_key($key)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
            throw new Refused_Exception(Idempotency::INVALID_KEY_MESSAGE);
        }

        $denied = AccessPolicy::decide($tool, AccessPolicy::for_key($this->request_policy_key));
        if (null === $denied) {
            $denied = Safety::blocked($tool);
        }
        if (null !== $denied) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
            throw new Refused_Exception($denied);
        }

        $slot      = Idempotency::slot($tool, $key);
        $args_hash = Idempotency::args_hash($tool, $args);
        $entry     = Idempotency::find($slot);
        if (null !== $entry || !Idempotency::claim($slot, $tool, $args_hash)) {
            
            return Idempotency::repeat($tool, $key, $args_hash, $entry ?? Idempotency::find($slot));
        }

        try {
            $result = $this->run_tool($name, $args);
        } catch (\Throwable $e) {

            Idempotency::release($slot);
            throw $e;
        }
        Idempotency::complete($slot, $tool, $args_hash, $result);
        return $result;
    }

    private function is_read_call(string $tool, array $args): bool {
        if (\Mordenhost_MCP\Access\Classifier::is_read_only($tool)) {
            return true;
        }
        if (array_key_exists('dry_run', $args) && !$this->tool_accepts_dry_run($tool)) {
            unset($args['dry_run']);
        }
        return \Mordenhost_MCP\Access\Destructive::is_dry_run($tool, $args);
    }

    private function run_tool($name, $args) {

        

        

        
        if (is_array($args)) {
            $args = Arg_Aliases::apply((string) $name, $args);
        }
        $denied = AccessPolicy::decide((string) $name, AccessPolicy::for_key($this->request_policy_key));
        if (null !== $denied) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML; escaping would turn the quoted tool name into entities the model has to read.
            throw new Refused_Exception($denied);
        }

        
        
        $safety_args = is_array($args) ? $args : [];

        
        if (array_key_exists('dry_run', $safety_args) && !$this->tool_accepts_dry_run((string) $name)) {
            unset($safety_args['dry_run']);
        }
        $denied      = Safety::blocked((string) $name);
        if (null !== $denied) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
            throw new Refused_Exception($denied);
        }

        
        $request = Safety::approval_request((string) $name, $safety_args);
        if (null !== $request) {
            $message = \Mordenhost_MCP\Access\Approvals::refusal_message((string) $name, $request);
            if ($request['id'] <= 0) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
                throw new Refused_Exception($message);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML.
            throw new Pending_Exception($message, \Mordenhost_MCP\Access\Approvals::pending_result((string) $name, $request, $safety_args));
        }

        

        
        $history = Change_History::begin((string) $name, $safety_args);
        $outer   = Safety::enter();
        try {
            $result = $this->dispatch_tool($name, $args);
        } catch (\Exception $e) {

            throw Arg_Aliases::explain_error((string) $name, is_array($args) ? $args : [], $e, fn() => $this->get_tools());
        } finally {
            $notices = Safety::leave($outer);
        }
        Change_History::finish($history, $result);

        if ($notices && is_array($result)) {
            $result['safety_notice'] = 'Force draft is on: ' . count($notices) . ' item(s) that would have been published or scheduled were saved as a draft. A person has to publish them.';
            $result['saved_as_draft'] = $notices;
        }
        return $result;
    }

    private function tool_accepts_dry_run(string $name): bool {
        if (null === $this->dry_run_tools) {
            $this->dry_run_tools = [];
            foreach ($this->get_tools() as $tool) {
                $props = $tool['inputSchema']['properties'] ?? [];
                if (is_object($props)) {
                    $props = get_object_vars($props);
                }
                if (is_array($props) && array_key_exists('dry_run', $props) && isset($tool['name'])) {
                    $this->dry_run_tools[(string) $tool['name']] = true;
                }
            }
        }
        return isset($this->dry_run_tools[$name]);
    }

    private function integrations_report(): ?array {
        if (!current_user_can('manage_options')) {
            return null;
        }
        if (null !== AccessPolicy::decide('wp_get_plugins', AccessPolicy::for_key($this->request_policy_key))) {
            return null;
        }
        return \Mordenhost_MCP\Capabilities\Toggles::client_report();
    }

    private function dispatch_tool($name, $args) {

        

        

        
        
        switch ($name) {

            

            case 'mordenhost_mcp_connection_health':
                global $wp_version;

                
                
                $builders = [
                    'divi_version'      => defined('ET_BUILDER_VERSION') ? (string) constant('ET_BUILDER_VERSION') : null,
                    'elementor_version' => defined('ELEMENTOR_VERSION') ? (string) constant('ELEMENTOR_VERSION') : null,

                    

                    'gutenberg_version' => defined('GUTENBERG_VERSION') ? (string) constant('GUTENBERG_VERSION') : null,
                ];
                return [
                    'route'          => rest_url('mordenhost-mcp/v1/mcp'),
                    'auth_method'    => $this->request_auth_method ?? 'unauthenticated',
                    'relay'          => null,
                    'token_ttl'      => $this->request_token_ttl,
                    'session_id'     => $this->request_session_id,
                    'active_scopes'  => ['tools'],
                    'server_version' => defined('MORDENHOST_MCP_VERSION') ? MORDENHOST_MCP_VERSION : 'unknown',
                    'wp_version'     => isset($wp_version) ? (string) $wp_version : (string) get_bloginfo('version'),
                    'php_version'    => PHP_VERSION,
                    'builders'       => $builders,

                    
                    'rate_limit_backend' => Rate_Limiter::backend(),

                    'access' => [
                        'read_only_mode' => AccessPolicy::read_only_mode(),
                        'connection'     => AccessPolicy::describe(AccessPolicy::for_key($this->request_policy_key)),
                        'ip_allowlist'   => AccessPolicy::ip_allowlist_enabled(),
                    ],
                    'safety' => [
                        'paused'           => Safety::paused(),
                        'force_draft'      => Safety::force_draft(),
                        'require_approval' => Safety::require_approval(),
                        'approval_mode'    => Safety::require_approval() ? Safety::approval_mode() : null,
                        'tool_allowlist'   => (bool) Safety::whitelist_patterns(),
                        'disabled_tools'   => count(Safety::disabled_tools()) + (Safety::disable_destructive() ? 1 : 0) > 0,
                    ],

                    'idempotency' => [
                        'argument'       => Idempotency::ARG,
                        'applies_to'     => 'write tools',
                        'window_seconds' => Idempotency::TTL,
                        'usage'          => 'Add a unique idempotency_key (for example a UUID) to a write. If the call times out, repeat it with the same key and arguments: you get the first result back and the change is not made twice. Use a new key for each new change.',
                    ],

                    
                    'experimental_mode' => Experimental::is_enabled(),

                    
                    
                    'integrations' => $this->integrations_report(),
                ];

            case 'mordenhost_mcp_undo_last_operation':

                return \Mordenhost_MCP\MCP\Undo_Dispatcher::dispatch($args);

            default:

                

                if ( in_array( $name, LifecycleManager::tool_names(), true ) ) {
                    return LifecycleManager::execute_tool( $name, $args );
                }

                

                if ( in_array( $name, Memory_Manager::tool_names(), true ) ) {
                    return Memory_Manager::execute_tool( $name, $args );
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

                    
                    
                    if ( in_array( $name, BlocksGlobalStyles::tool_names(), true ) ) {
                        return BlocksGlobalStyles::execute_tool( $name, $args );
                    }
                    return BlocksContent::execute_tool( $name, $args );
                }

                

                

                
                
                if ( ! \Mordenhost_MCP\Capabilities\Toggles::tool_is_allowed( $name ) ) {
                    $disabled_slug = \Mordenhost_MCP\Capabilities\Toggles::slug_for_tool( $name );
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML; built from catalogue strings only, never from the caller's tool name.
                    throw new \Exception( \Mordenhost_MCP\Capabilities\Toggles::disabled_message( $disabled_slug ) );
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
                if ( strpos( $name, 'beaver_' ) === 0 ) {
                    return BeaverIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'siteorigin_' ) === 0 ) {
                    return SiteOriginIntegration::execute_tool( $name, $args );
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
                if ( strpos( $name, 'rank_math_' ) === 0 ) {
                    return RankMathIntegration::execute_tool( $name, $args );
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
                if ( strpos( $name, 'w3tc_' ) === 0 ) {
                    return W3TCIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'redis_' ) === 0 ) {
                    return RedisIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ao_' ) === 0 ) {
                    return AutoptimizeIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpo_' ) === 0 ) {
                    return WPOptimizeIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpsc_' ) === 0 ) {
                    return WPSuperCacheIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpfc_' ) === 0 ) {
                    return WPFastestCacheIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'up_' ) === 0 ) {
                    return UpdraftPlusIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bwu_' ) === 0 ) {
                    return BackWPupIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'dup_' ) === 0 ) {
                    return DuplicatorIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpvivid_' ) === 0 ) {
                    return WPvividIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wf_' ) === 0 ) {
                    return WordfenceIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'def_' ) === 0 ) {
                    return DefenderIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'solid_' ) === 0 ) {
                    return SolidSecurityIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'sucuri_' ) === 0 ) {
                    return SucuriIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'rsssl_' ) === 0 ) {
                    return ReallySimpleSecurityIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'aios_' ) === 0 ) {
                    return AIOSIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'llar_' ) === 0 ) {
                    return LLARIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'akismet_' ) === 0 ) {
                    return AkismetIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'imagify_' ) === 0 ) {
                    return ImagifyIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ewww_' ) === 0 ) {
                    return EWWWIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'smush_' ) === 0 ) {
                    return SmushIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'shortpixel_' ) === 0 ) {
                    return ShortPixelIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'stock_' ) === 0 ) {
                    return InstantImagesIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'tec_' ) === 0 ) {
                    return EventsCalendarIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'sugarcal_' ) === 0 ) {
                    return SugarCalendarIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'em_' ) === 0 ) {
                    return EventsManagerIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'mec_' ) === 0 ) {
                    return ModernEventsCalendarIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'trp_' ) === 0 ) {
                    return TranslatePressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'pll_' ) === 0 ) {
                    return PolylangIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpml_' ) === 0 ) {
                    return WPMLIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'weglot_' ) === 0 ) {
                    return WeglotIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'geodir_' ) === 0 ) {
                    return GeoDirectoryIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bdp_' ) === 0 ) {
                    return BusinessDirectoryIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ivory_' ) === 0 ) {
                    return IvorySearchIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'uncanny_automator_' ) === 0 ) {
                    return UncannyAutomatorIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'blog2social_' ) === 0 ) {
                    return Blog2SocialIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'estatik_' ) === 0 ) {
                    return EstatikIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'toolset_' ) === 0 ) {
                    return ToolsetTypesIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'crm_' ) === 0 ) {
                    return FluentCRMIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'mailpoet_' ) === 0 ) {
                    return MailPoetIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'groundhogg_' ) === 0 ) {
                    return GroundhoggIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'givewp_' ) === 0 ) {
                    return GiveWPIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'pmpro_' ) === 0 ) {
                    return PaidMembershipsProIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'swpm_' ) === 0 ) {
                    return SimpleMembershipIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ppress_' ) === 0 ) {
                    return ProfilePressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'rcp_' ) === 0 ) {
                    return RestrictContentIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'um_' ) === 0 ) {
                    return UltimateMemberIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'charitable_' ) === 0 ) {
                    return CharitableIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bbp_' ) === 0 ) {
                    return BBPressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bp_' ) === 0 ) {
                    return BuddyPressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'fluentsupport_' ) === 0 ) {
                    return FluentSupportIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'lms_' ) === 0 ) {
                    return LearnPressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'sensei_' ) === 0 ) {
                    return SenseiIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'mto_' ) === 0 ) {
                    return MasteriyoIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'tutor_' ) === 0 ) {
                    return TutorIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'llms_' ) === 0 ) {
                    return LifterLMSIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'amelia_' ) === 0 ) {
                    return AmeliaIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'latepoint_' ) === 0 ) {
                    return LatePointIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bookly_' ) === 0 ) {
                    return BooklyIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'bookingpress_' ) === 0 ) {
                    return BookingPressIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'pods_' ) === 0 ) {
                    return PodsIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'cptui_' ) === 0 ) {
                    return CptUiIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'mbcpt_' ) === 0 ) {
                    return MbCptIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'edd_' ) === 0 ) {
                    return EDDIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'fluentcart_' ) === 0 ) {
                    return FluentCartIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ai1wm_' ) === 0 ) {
                    return Ai1wmIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'patchstack_' ) === 0 ) {
                    return PatchstackIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'relevanssi_' ) === 0 ) {
                    return RelevanssiIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'slicewp_' ) === 0 ) {
                    return SliceWPIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'automatorwp_' ) === 0 ) {
                    return AutomatorWPIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpjm_' ) === 0 ) {
                    return WPJobManagerIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'wpgm_' ) === 0 ) {
                    return WpGoMapsIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'jetpack_' ) === 0 ) {
                    return JetpackIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'ai_generate_' ) === 0 ) {
                    return AIMediaIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'snippet_' ) === 0 ) {
                    return SnippetsIntegration::execute_tool( $name, $args );
                }
                if ( in_array( $name, WebhooksManager::tool_names(), true ) ) {
                    return WebhooksManager::execute_tool( $name, $args );
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
        return \Mordenhost_MCP\SEO\Detector::primary();
    }

}
