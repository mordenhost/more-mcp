<?php
namespace More_MCP\MCP;

use More_MCP\Integrations\WooCommerce as WooIntegration;
use More_MCP\Integrations\GuardPress as GPIntegration;
use More_MCP\Integrations\SiteVault as SVIntegration;
use More_MCP\Integrations\ForgeCache as FCIntegration;
use More_MCP\Integrations\LiteSpeed as LSIntegration;
use More_MCP\Integrations\Elementor as ElementorIntegration;
use More_MCP\Integrations\Divi as DiviIntegration;
use More_MCP\Integrations\ACF as ACFIntegration;
use More_MCP\Integrations\Redirection as RedirectionIntegration;
use More_MCP\Integrations\Analytics as AnalyticsIntegration;
use More_MCP\Integrations\Forms as FormsIntegration;
use More_MCP\Integrations\Elementor_Coexistence;
use More_MCP\Blocks\Content as BlocksContent;
use More_MCP\Blocks\Templates as BlocksTemplates;
use More_MCP\Lifecycle\Manager as LifecycleManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MCP Server — Streamable HTTP Transport.
 *
 * Single endpoint that accepts POST for all JSON-RPC messages
 * and returns either JSON or SSE stream based on Accept header.
 *
 * This replaces the deprecated HTTP+SSE transport.
 */
class Server {

    /**
     * Rate limit: max requests per window per IP
     */
    private $rate_limit_max = 60;
    private $rate_limit_window = 60; // seconds

    /**
     * Auth fingerprint for the current request. Populated by the credential
     * validators (validate_bearer_token / validate_api_key_value) once the
     * request has been authenticated; read by build_auth_fingerprint().
     *
     * Bound to client_id + user_id from Token_Store — these are stable across
     * hourly OAuth access-token rotation, so the fingerprint stays consistent
     * with the session it was minted against for the full 24h session TTL.
     * Hashing the raw bearer token here would invalidate the session on every
     * refresh and break long-running automations.
     */
    private $request_auth_fingerprint = '';

    /**
     * Auth-state context for the current request, populated by validate_bearer_token
     * / validate_api_key_value and consumed by the more_mcp_connection_health tool.
     * Kept separate from request_auth_fingerprint so the fingerprint's hashing
     * discipline (never expose the raw key/token) stays cleanly enforced.
     */
    private $request_auth_method  = null;   // 'api-key' | 'oauth-bearer' | null
    private $request_token_ttl    = null;   // int seconds until token expiry, or null (api-key never rotates)
    private $request_session_id   = null;   // MCP session ID from Mcp-Session-Id header, or null (no session for pre-initialize)

    /**
     * Validate Origin header to prevent DNS rebinding attacks
     * Per MCP spec: Servers MUST validate Origin header
     *
     * @param \WP_REST_Request $request The request object
     * @return bool|WP_REST_Response True if valid, error response if invalid
     */
    private function validate_origin($request) {
        $origin = $request->get_header('Origin');

        // No origin header - likely same-origin or non-browser client (CLI, etc.)
        // Allow these for MCP clients like Claude Desktop
        if (empty($origin)) {
            return true;
        }

        // Parse the origin
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

        // Get allowed hosts
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $allowed_hosts = [
            $site_host,
            'localhost',
            '127.0.0.1',
            '::1',
            'claude.ai',           // Claude web interface
            'www.claude.ai',
            'anthropic.com',
            'www.anthropic.com',
        ];

        // Allow filtering for custom allowed origins
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

    /**
     * Validate authentication for MCP requests.
     *
     * Accepts either:
     *  1. OAuth 2.0 Bearer token (Authorization: Bearer <token>)
     *  2. API key header (MMCP-Key: <key>)
     *
     * @param \WP_REST_Request $request The request object
     * @return bool|WP_REST_Response True if valid, error response if invalid
     */
    private function validate_auth($request) {
        $settings = get_option('more_mcp_settings', []);

        // Check plugin is enabled.
        if (empty($settings['enabled'])) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'More MCP is currently disabled.',
                ],
            ], 403);
        }

        // Try OAuth 2.0 Bearer token first. If that fails, fall back to
        // trying the same Bearer value as a static API key — most MCP
        // clients that follow the universal HTTP convention for bearer
        // credentials send their static API key via
        // `Authorization: Bearer <key>`, not the More-MCP-specific
        // `MMCP-Key` header. Route the Bearer value through
        // API-key validation as a fallback when OAuth validation rejects
        // it. This is a strict additive change: API keys were ALREADY
        // accepted as bearer credentials, just under a different header
        // name, so the security perimeter does not widen — it just
        // accepts the convention every modern MCP client uses.
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
            // Both failed — return the OAuth error response so OAuth-aware
            // clients still see the proper RFC 9728 WWW-Authenticate
            // challenge and can start a fresh authorization flow.
            return $oauth_result;
        }

        // Fall back to API key via the More-MCP-specific header (kept for
        // existing integrations + tighter privacy where the admin doesn't
        // want the API key to share a header name with OAuth tokens).
        $api_key = $request->get_header('MMCP-Key');
        if (!empty($api_key)) {
            return $this->validate_api_key_value($api_key, $settings);
        }

        // Neither provided — return 401 with WWW-Authenticate for OAuth discovery.
        // Per the MCP spec + RFC 9728, include resource_metadata URL.
        // Cache-Control: no-store is critical here. Without it, this 401
        // gets cached at edge (URL-keyed) and served to subsequent
        // authenticated requests, breaking every MCP client that hits
        // GET /mcp before sending its credentials.
        $resource_metadata_url = home_url( '/.well-known/oauth-protected-resource' );
        $response = new \WP_REST_Response([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Authentication required. Use Authorization: Bearer <token> or MMCP-Key header.',
            ],
        ], 401);
        $response->header('WWW-Authenticate', 'Bearer resource_metadata="' . $resource_metadata_url . '"');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    /**
     * Validate an API key value.
     *
     * @param string $api_key  The API key from the request header.
     * @param array  $settings Plugin settings.
     * @return bool|WP_REST_Response True if valid, error response if invalid.
     */
    private function validate_api_key_value($api_key, $settings = null) {
        if (null === $settings) {
            $settings = get_option('more_mcp_settings', []);
        }

        $stored = isset($settings['api_key']) ? (string) $settings['api_key'] : '';

        // Reject the pre-0.1.5 bare 32-hex format outright, even if it matches
        // what is stored. Accepting it would keep the weaker, ambiguity-prone
        // format alive indefinitely. The error text names the fix explicitly
        // because "invalid API key" alone sends admins hunting for a typo when
        // the real problem is that the credential shape is retired.
        if (\More_MCP\Auth\Api_Key::is_legacy_format($api_key)) {
            return $this->api_key_error(
                'This API key uses the retired format. Open More MCP → Settings and click Regenerate to mint a key in the current format, then update your client.'
            );
        }

        if (!\More_MCP\Auth\Api_Key::is_valid_format($api_key)) {
            return $this->api_key_error('Invalid API key.');
        }

        // hash_equals() on the raw strings. Both are known to be well-formed by
        // this point, and comparing before the format checks would leak whether
        // a legacy key happened to match via timing.
        if ($stored === '' || !hash_equals($stored, $api_key)) {
            return $this->api_key_error('Invalid API key.');
        }

        // The API key is stored in admin-only settings, so whoever presents it is admin-level trusted.
        // Set the current user to a site admin so capability checks (upload_files, edit_post, etc.) succeed.
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

        // API keys don't rotate, so hashing the raw key gives a stable session fingerprint.
        $this->request_auth_fingerprint = hash('sha256', 'apikey:' . $api_key);

        // capture auth context for more_mcp_connection_health diagnostic tool.
        $this->request_auth_method = 'api-key';
        $this->request_token_ttl   = null;

        return true;
    }

    /**
     * Build the 401 response for a rejected API key.
     *
     * 401, not 403, per RFC 7235 — wrong credentials means "auth failed",
     * which is 401. 403 is reserved for "auth succeeded but lacks permission".
     * Strict MCP clients (per RFC 9728 OAuth discovery) start the OAuth flow on
     * 401 but not 403, so returning 403 here would suppress legitimate retries.
     *
     * Cache-Control: no-store is required — a cached 401 gets replayed to
     * subsequent authenticated requests by URL-keyed edge caches.
     *
     * @param string $message Human-readable reason.
     * @return \WP_REST_Response
     */
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

    /**
     * Validate an OAuth Bearer token.
     *
     * @param string $raw_token The raw access token.
     * @return bool|WP_REST_Response True if valid, error response if invalid.
     */
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

        // Set the WordPress user context so downstream permission checks work.
        wp_set_current_user((int) $token_data['user_id']);

        // Bind the session fingerprint to identifiers that survive access-token
        // rotation — hashing the raw token would invalidate the session on
        // every hourly refresh. See the request_auth_fingerprint property doc.
        $this->request_auth_fingerprint = hash(
            'sha256',
            'oauth:' . (string) $token_data['client_id'] . ':' . (int) $token_data['user_id']
        );

        // capture auth context for more_mcp_connection_health diagnostic tool.
        $this->request_auth_method = 'oauth-bearer';
        if ( ! empty( $token_data['expires_at'] ) ) {
            $expires_ts = strtotime( (string) $token_data['expires_at'] . ' UTC' );
            if ( $expires_ts ) {
                $this->request_token_ttl = max( 0, $expires_ts - time() );
            }
        }

        return true;
    }

    /**
     * Check rate limit for an IP address.
     *
     * @param string $ip Client IP address
     * @return bool|WP_REST_Response True if allowed, error response if rate limited
     */
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

    /**
     * Validate Accept header for POST requests
     * Per MCP spec: Client MUST include Accept header with both application/json and text/event-stream
     *
     * @param \WP_REST_Request $request The request object
     * @return bool True if valid
     */
    private function validate_accept_header($request) {
        $accept = $request->get_header('Accept');

        // Be lenient - if no Accept header, assume client accepts JSON
        if (empty($accept)) {
            return true;
        }

        // Check if Accept includes application/json or */*
        $accepts_json = strpos($accept, 'application/json') !== false ||
                        strpos($accept, '*/*') !== false;

        return $accepts_json;
    }

    /**
     * Validate session ID format
     * Per MCP spec: Session ID MUST contain only visible ASCII characters (0x21 to 0x7E)
     *
     * @param string $session_id The session ID to validate
     * @return bool True if valid format
     */
    private function validate_session_id_format($session_id) {
        if (empty($session_id)) {
            return false;
        }

        // Check each character is in visible ASCII range (0x21 to 0x7E)
        $length = strlen($session_id);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($session_id[$i]);
            if ($ord < 0x21 || $ord > 0x7E) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a session exists and is valid
     *
     * @param string $session_id The session ID to check
     * @return bool True if session is valid
     */
    private function is_valid_session($session_id) {
        if (!$this->validate_session_id_format($session_id)) {
            return false;
        }

        // DB-backed session lookup. Transient-backed sessions silently
        // drop on hosts with an active WordPress object-cache drop-in that
        // evicts keys between requests, so we persist to a real table.
        // The sliding window is a single atomic UPDATE that refreshes
        // expires_at IFF the row exists and has not expired — safer than
        // a GET-then-SET pattern which can race and grant expired sessions.
        return Session_Store::touch_session($session_id);
    }

    /**
     * Store a new session
     *
     * @param string $session_id The session ID to store
     */
    private function store_session($session_id, $auth_fingerprint = '') {
        // DB-backed session persistence. 24-hour expiry, refreshed
        // on every valid hit via Session_Store::touch_session. See
        // is_valid_session() for the object-cache motivation.
        Session_Store::create_session($session_id, $auth_fingerprint);
    }

    /**
     * Delete a session
     *
     * @param string $session_id The session ID to delete
     */
    private function delete_session($session_id) {
        Session_Store::delete_session($session_id);
    }

    /**
     * Resolve a post identifier from tool args, accepting either
     * `post_id` (canonical) or `id` (alias). Different tools historically
     * used different argument names (wp_get_post took `id`, wp_get_post_meta
     * took `post_id`); AI drivers sometimes swap conventions and got
     * InputValidationError. Accept both everywhere a single post is
     * identified. Pages and media (also post types) are included; comments,
     * terms, and users are separate ID domains and keep their own arg names.
     */
    private static function resolve_post_id_arg(array $args): int {
        return intval($args['post_id'] ?? $args['id'] ?? 0);
    }

    /**
     * Build a read-after-write response for wp_update_post / wp_update_page.
     *
     * Reads the post back from DB after mutation so the response reflects
     * actual stored values, not the requested values. Fields WordPress
     * silently modified (post_parent coerced to 0 on unknown parent,
     * status transitions overridden, etc.) are surfaced via a
     * modified_by_wp entry so an LLM caller can react rather than treating
     * a hardcoded success string as truth.
     *
     * Text fields (title / content / excerpt) skip the diff-check because
     * sanitize_text_field / wp_kses_post / wp_slash naturally modify the
     * input; the stored value in saved_fields is the truth signal there
     * and comparing raw-vs-stored would trigger noisy modified_by_wp
     * entries on every call that contains any sanitizable input.
     * Int / enum / password fields participate in the diff-check because
     * WP-side modifications to those are always meaningful.
     *
     * @param int    $post_id  Post ID that was just updated.
     * @param array  $args     Raw args from the tool call (what the caller sent).
     * @param array  $data     Array passed to wp_update_post() (post-sanitization).
     * @param string $message  Human-readable success string (kept for backwards compat).
     *
     * @return array Response with id, saved_fields, optional modified_by_wp, message.
     */
    private static function build_update_response(int $post_id, array $args, array $data, string $message): array {
        $saved_post = get_post($post_id);
        $saved_fields = [];
        $modified_by_wp = [];
        // arg_key => [wp_post_property, cast_type, participates_in_diff_check]
        $map = [
            'title'          => ['post_title', 'string', false],
            'content'        => ['post_content', 'string', false],
            'status'         => ['post_status', 'string', true],
            'excerpt'        => ['post_excerpt', 'string', false],
            'post_author'    => ['post_author', 'int', true],
            'menu_order'     => ['menu_order', 'int', true],
            'post_parent'    => ['post_parent', 'int', true],
            'password'       => ['post_password', 'string', true],
            'comment_status' => ['comment_status', 'string', true],
            'ping_status'    => ['ping_status', 'string', true],
        ];
        foreach ($map as $arg_key => [$prop, $type, $diff_check]) {
            if (!array_key_exists($arg_key, $args)) continue;
            $stored = $saved_post->{$prop} ?? '';
            $stored = $type === 'int' ? (int) $stored : (string) $stored;
            $saved_fields[$arg_key] = $stored;
            if (!$diff_check) continue;
            if (!array_key_exists($prop, $data)) continue;
            $requested_effective = $type === 'int' ? (int) $data[$prop] : (string) $data[$prop];
            if ($stored !== $requested_effective) {
                $modified_by_wp[$arg_key] = [
                    'requested' => $requested_effective,
                    'actual'    => $stored,
                ];
            }
        }
        $response = [
            'id'           => $post_id,
            'saved_fields' => $saved_fields,
            'message'      => $message,
        ];
        if (!empty($modified_by_wp)) {
            $response['modified_by_wp'] = $modified_by_wp;
        }
        return $response;
    }


    /**
     * Shared implementation for wp_replace_in_post / wp_replace_in_page.
     *
     * Literal (case-sensitive) find/replace on post_content, so callers can
     * make surgical edits to large documents without re-transmitting the
     * full body through the MCP transport. All occurrences are replaced in
     * one call (str_replace semantics).
     *
     * Safety rails, in evaluation order:
     *   - find must be non-empty; replace must be present (may be "").
     *   - expected_count, when provided, aborts BEFORE writing unless the
     *     occurrence count matches exactly — protects against a stale
     *     mental model of the content (e.g. the post changed since the
     *     caller last read it).
     *   - dry_run reports the count and writes nothing.
     *   - zero occurrences is an error, not a silent no-op, so LLM callers
     *     cannot mistake a typo'd needle for success.
     *
     * The response is read-after-write in the same spirit as
     * build_update_response(): stored content is re-read and compared to
     * the computed replacement; a mismatch (kses stripping for callers
     * without unfiltered_html, content filters, etc.) is surfaced via
     * modified_by_wp rather than hidden behind a success message.
     *
     * @param int    $post_id Target post ID (existence checked by caller).
     * @param array  $args    Raw tool args (find, replace, expected_count, dry_run).
     * @param string $noun    'post' or 'page' — only used in messages.
     *
     * @return array Response with id, occurrences, replaced, verified, lengths, message.
     */
    private static function replace_in_post_content(int $post_id, array $args, string $noun): array {
        // object-level edit_post resolves to the PT-specific cap
        // (edit_page etc.) automatically via map_meta_cap — same gate as
        // wp_update_post / wp_update_page.
        if (!current_user_can('edit_post', $post_id)) {
            throw new \Exception('You do not have permission to edit this ' . esc_html($noun) . '.');
        }
        if (!isset($args['find']) || !is_string($args['find']) || $args['find'] === '') {
            throw new \Exception('find must be a non-empty string.');
        }
        if (!array_key_exists('replace', $args) || !is_string($args['replace'])) {
            throw new \Exception('replace must be a string (empty string deletes the matched text).');
        }
        $find = $args['find'];
        $replace = $args['replace'];
        // Raw stored content, NOT filtered output — replacement operates on
        // exactly what wp_update_post would receive back.
        $content = (string) get_post($post_id)->post_content;
        $occurrences = substr_count($content, $find);
        if (array_key_exists('expected_count', $args) && intval($args['expected_count']) !== $occurrences) {
            throw new \Exception(sprintf('expected_count is %d but %d occurrence(s) found; content unchanged.', intval($args['expected_count']), $occurrences));
        }
        if (!empty($args['dry_run'])) {
            return [
                'id' => $post_id,
                'dry_run' => true,
                'occurrences' => $occurrences,
                'content_length' => strlen($content),
                'message' => sprintf('Dry run: %d occurrence(s) found; nothing written.', $occurrences),
            ];
        }
        if ($occurrences === 0) {
            throw new \Exception('find string not found in ' . esc_html($noun) . ' content; nothing to replace. Use dry_run=true to probe safely.');
        }
        if ($find === $replace) {
            throw new \Exception('find and replace are identical; nothing to do.');
        }
        $new_content = str_replace($find, $replace, $content);
        // See wp_create_post for the wp_slash + no-wp_kses_post rationale;
        // kses still applies inside wp_update_post for callers without
        // unfiltered_html, which the verification below surfaces.
        $result = wp_update_post(['ID' => $post_id, 'post_content' => wp_slash($new_content)], true);
        if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
        $stored = (string) get_post($post_id)->post_content;
        $verified = ($stored === $new_content);
        $response = [
            'id' => $post_id,
            'occurrences' => $occurrences,
            'replaced' => $occurrences,
            'verified' => $verified,
            'content_length_before' => strlen($content),
            'content_length_after' => strlen($stored),
            'message' => sprintf('%s content updated: %d occurrence(s) replaced.', ucfirst($noun), $occurrences),
        ];
        if (!$verified) {
            $response['modified_by_wp'] = [
                'content' => 'Stored content differs from the computed replacement (sanitization or a content filter modified it on save). Re-read the ' . $noun . ' to inspect the stored result.',
            ];
        }
        return $response;
    }

    /**
     * Sanitize a meta value received from an MCP client.
     *
     * Accepts JSON scalars (string, int, float, bool, null), arrays, and
     * objects (JSON-decoded stdClass). Arrays and objects are walked
     * recursively.
     *
     * STRING HANDLING, and why it changed in 0.1.6:
     *
     * Until 0.1.6 every string leaf went through wp_kses_post() unconditionally.
     * That produced two silent-corruption bugs, because wp_kses_post() does two
     * things beyond stripping disallowed tags:
     *
     *   1. It runs wp_kses_stripslashes(), which removes the backslash before
     *      every double quote. A meta value holding JSON — `_elementor_data` is
     *      exactly this — came back with `\"` turned into `"`, so the field no
     *      longer parsed. Elementor then rendered zero sections while the page
     *      still returned HTTP 200. (GitHub #1)
     *
     *   2. It strips <script> but KEEPS the inner text. A JSON-LD block written
     *      to meta lost its wrapper and rendered as visible body copy. (GitHub #2)
     *
     * Both were reported as successful writes, which is the worst failure shape.
     *
     * The fix aligns meta with how this plugin already treats post_content:
     * sanitization follows WordPress's own `unfiltered_html` capability rather
     * than being applied blanket. A caller holding that capability — which on
     * single-site means administrators and editors, and which the API-key auth
     * path grants because the key lives in admin-only settings — stores the
     * bytes it sent. A caller without it still gets the wp_kses_post allow-list.
     *
     * That is deliberately a widening of what can be stored, so state it plainly:
     * an authenticated caller with `unfiltered_html` can now put <script> into
     * post meta. It could already do so in post_content through this same tool
     * surface, and through wp-admin, so this closes an inconsistency rather than
     * opening a new door. Sites wanting the old behaviour can force it back with
     * the more_mcp_meta_value_sanitizer filter.
     *
     * Rejects strings that look like PHP-serialized payloads regardless of
     * capability. get_post_meta() runs maybe_unserialize() by default, so
     * accepting a hand-crafted 'O:...' or 'a:...' string here would give a later
     * reader a PHP-object-injection primitive on the way out. Callers pass
     * arrays and objects directly and WordPress serializes them safely on write.
     *
     * @throws \Exception on a PHP-serialized string.
     */
    private static function sanitize_meta_value($value) {
        // PHP-serialized markers: a:N:{ ... }  O:N:"cls":M:{ ... }  s:N:"..."
        // i:[-]N;  d:[-]N[.N];  b:[01];  N;   Reject at boundary so
        // get_post_meta()'s maybe_unserialize() never runs on caller-supplied
        // input. Broad match — leading marker + digit-or-minus is enough; any
        // false positive would have to be a plain string that starts with
        // one of those 2-3 char shapes, which we accept as a fair tradeoff.
        if (is_string($value) && preg_match('/^(?:a|O|s):\d+[:{"]|^(?:i|d):-?\d+(?:\.\d+)?;|^b:[01];|^N;$/', $value)) {
            throw new \Exception('Value looks like a PHP-serialized string. Pass the structured value (array/object) directly — WordPress will serialize it for you.');
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? sanitize_text_field($k) : $k;
                $out[$key] = self::sanitize_meta_value($v);
            }
            return $out;
        }
        if (is_object($value)) {
            $out = [];
            foreach (get_object_vars($value) as $k => $v) {
                $out[sanitize_text_field($k)] = self::sanitize_meta_value($v);
            }
            return $out;
        }
        if (is_string($value)) {
            // See the docblock: kses is destructive to JSON and to structured
            // data, so it is applied only to callers WordPress itself would
            // filter.
            return current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
        }
        // Numbers, booleans, nulls pass through unchanged.
        return $value;
    }

    /**
     * Wrap sanitize_meta_value() with the more_mcp_meta_value_sanitizer filter.
     *
     * Filter signature: (mixed $sanitized, mixed $raw, string $meta_key, int $object_id, string $tool_name).
     * Site owners can return $raw verbatim for keys that store trusted HTML,
     * tighten the default wp_kses_post to plain text for validation-sensitive
     * keys, or plug a per-key custom sanitizer without patching the plugin.
     *
     * @param mixed  $raw       Raw caller-supplied value.
     * @param string $meta_key  Meta key being written.
     * @param int    $object_id Post or term ID.
     * @param string $tool_name 'wp_update_post_meta' | 'wp_add_post_meta' | 'wp_update_term_meta'.
     */
    private static function filter_meta_value($raw, $meta_key, $object_id, $tool_name) {
        $sanitized = self::sanitize_meta_value($raw);
        return apply_filters(
            'more_mcp_meta_value_sanitizer',
            $sanitized,
            $raw,
            (string) $meta_key,
            (int) $object_id,
            (string) $tool_name
        );
    }

    /**
     * Write a meta value and report what actually landed in the database.
     *
     * Two things happen here that callers should not have to know about.
     *
     * FIRST, the value is wp_slash()-ed on the way in. WordPress's
     * update_metadata() / add_metadata() run wp_unslash() on every value they
     * receive, because they were designed for $_POST data that PHP had already
     * slashed. A value that arrived over JSON-RPC was never slashed, so that
     * internal unslash strips one level of real escaping: `\"` becomes `"`, and
     * a meta field holding JSON stops parsing. Slashing first makes WordPress's
     * unslash a no-op and the stored bytes match what the caller sent.
     *
     * That is the mechanism behind GitHub #1 — writing `_elementor_data` back
     * through this tool produced invalid JSON and a page that rendered zero
     * Elementor sections while still returning HTTP 200.
     *
     * SECOND, the value is read back and compared. A write can still change the
     * value legitimately — a sanitizer filter, kses for callers without
     * unfiltered_html, or WordPress's own serialization round-trip — and the old
     * response shape ("Post meta updated successfully", result: true) hid that
     * completely. Now a mismatch is reported in the response, matching what
     * wp_update_post already does with saved_fields. (GitHub #5)
     *
     * @param string $meta_type 'post' | 'term'.
     * @param int    $object_id Post or term ID.
     * @param string $meta_key  Meta key.
     * @param mixed  $value     Sanitized value to store.
     * @param bool   $add       True to add a row (add_*_meta), false to update.
     * @param bool   $unique    Only meaningful when $add is true.
     * @return array {
     *     @type bool       $written        Whether WordPress reported a write.
     *     @type int|null   $meta_id        New row ID when adding, else null.
     *     @type bool       $verified       Stored value is identical to what was sent.
     *     @type string[]   $warnings       Human-readable differences, empty when verified.
     *     @type string     $stored_type    PHP type of the stored value.
     *     @type int|null   $stored_length  strlen of the stored value when it is a string.
     * }
     */
    private static function write_meta_verified($meta_type, $object_id, $meta_key, $value, $add = false, $unique = false) {
        $object_id = (int) $object_id;
        $is_term   = ('term' === $meta_type);

        // wp_slash() only touches strings (recursing into arrays), so passing
        // ints, bools and null through it is safe.
        $to_store = wp_slash($value);

        $meta_id = null;
        if ($add) {
            $result = $is_term
                ? add_term_meta($object_id, $meta_key, $to_store, $unique)
                : add_post_meta($object_id, $meta_key, $to_store, $unique);
            $written = ($result !== false);
            $meta_id = $written ? (int) $result : null;
        } else {
            $result  = $is_term
                ? update_term_meta($object_id, $meta_key, $to_store)
                : update_post_meta($object_id, $meta_key, $to_store);
            // update_*_meta returns false when the value is unchanged, which is
            // not a failure. Verification below is the real signal.
            $written = ($result !== false);
        }

        $stored = $is_term
            ? get_term_meta($object_id, $meta_key, true)
            : get_post_meta($object_id, $meta_key, true);

        $warnings = [];
        $verified = ($stored === $value);

        if (!$verified) {
            if (gettype($stored) !== gettype($value)) {
                $warnings[] = sprintf(
                    'Stored type is %s but %s was sent. Callers that parse this value should re-read it rather than assume the sent shape.',
                    gettype($stored),
                    gettype($value)
                );
            } elseif (is_string($stored) && is_string($value)) {
                $delta = strlen($stored) - strlen($value);
                $warnings[] = sprintf(
                    'Stored string differs from what was sent (%s%d bytes). A sanitizer, a more_mcp_meta_value_sanitizer filter, or kses filtering modified it.',
                    $delta >= 0 ? '+' : '',
                    $delta
                );
                // Name the two failure modes that actually bit users, so the
                // warning points at a cause rather than just reporting a delta.
                if (substr_count($value, '\\"') > substr_count($stored, '\\"')) {
                    $warnings[] = 'Backslash escapes before double quotes were removed. If this value is JSON it will no longer parse.';
                }
                if (stripos($value, '<script') !== false && stripos($stored, '<script') === false) {
                    $warnings[] = 'A <script> tag was removed but its inner text was kept. Callers without the unfiltered_html capability cannot store script tags in meta.';
                }
            } else {
                $warnings[] = 'Stored value differs from what was sent. Re-read the value to inspect the stored result.';
            }
        }

        return [
            'written'       => $written,
            'meta_id'       => $meta_id,
            'verified'      => $verified,
            'warnings'      => $warnings,
            'stored_type'   => gettype($stored),
            'stored_length' => is_string($stored) ? strlen($stored) : null,
        ];
    }

    /**
     * Shape the verification half of a meta-write response.
     *
     * Split out so all three write tools report identically, and so `warnings`
     * is omitted entirely when there is nothing to warn about — a caller
     * checking `if (response.warnings)` should not have to distinguish an empty
     * array from a real problem.
     *
     * @param array $write Return value of write_meta_verified().
     * @return array
     */
    private static function meta_write_report(array $write) {
        $report = [
            'saved_value_matches_sent' => $write['verified'],
            'stored_type'              => $write['stored_type'],
        ];
        if (null !== $write['stored_length']) {
            $report['stored_length'] = $write['stored_length'];
        }
        if (!empty($write['warnings'])) {
            $report['warnings'] = $write['warnings'];
        }
        return $report;
    }

    private function get_tools() {
        $tools = [
            // Posts (supports custom post types)
            ['name' => 'wp_get_posts', 'description' => 'Get WordPress posts (supports custom post types)', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of posts (max 100)'], 'search' => ['type' => 'string', 'description' => 'Search term'], 'status' => ['type' => 'string', 'description' => 'Post status (publish, draft, etc)'], 'post_type' => ['type' => 'string', 'description' => 'Post type slug (default: post). Use wp_get_post_types to discover available types']]]],
            ['name' => 'wp_get_post', 'description' => 'Get single post by ID (any post type)', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'Post ID']], 'required' => ['id']]],
            ['name' => 'wp_create_post', 'description' => 'Create new post (supports custom post types). Combine status="future" with date to schedule. Excerpt may contain safe HTML (same allow-list as post content).', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'future', 'pending', 'private']], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to schedule. Past dates auto-publish with that timestamp.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional excerpt. May contain safe HTML (same allow-list as post content).'], 'categories' => ['type' => 'array', 'items' => ['type' => 'integer']], 'post_type' => ['type' => 'string', 'description' => 'Post type slug (default: post)'], 'featured_media' => ['type' => 'integer', 'description' => 'Attachment ID to set as featured image'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to assign as the post author. Defaults to the authenticated MCP user (admin). Use wp_get_users to discover available author IDs.']], 'required' => ['title', 'content']]],
            ['name' => 'wp_update_post', 'description' => 'Update existing post (any post type). Response includes saved_fields (actual stored values, read back from DB) so silent-drop / silent-modify by WordPress is surfaced rather than hidden. Pass date to reschedule or backdate.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string'], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to reschedule, or use alone to backdate.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional excerpt. May contain safe HTML (same allow-list as post content).'], 'featured_media' => ['type' => 'integer', 'description' => 'Attachment ID to set as featured image (pass 0 to remove)'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to reassign as the post author. Use wp_get_users to discover available author IDs.'], 'menu_order' => ['type' => 'integer', 'description' => 'Order among sibling posts/pages. Lower = earlier.'], 'post_parent' => ['type' => 'integer', 'description' => 'Parent post ID (0 = no parent). Useful for hierarchical CPTs. Throws if the ID does not exist.'], 'password' => ['type' => 'string', 'description' => 'Post password. Empty string removes protection.'], 'comment_status' => ['type' => 'string', 'enum' => ['open', 'closed'], 'description' => 'Allow (open) or disallow (closed) new comments.'], 'ping_status' => ['type' => 'string', 'enum' => ['open', 'closed'], 'description' => 'Allow (open) or disallow (closed) trackbacks / pingbacks.']], 'required' => ['id']]],
            ['name' => 'wp_replace_in_post', 'description' => 'Find/replace a literal string inside a post\'s content (any post type) without resending the full body. Use for surgical edits to large posts (page-builder content, embedded base64 payloads) where wp_update_post\'s full-content replacement is impractical. Case-sensitive literal match, no regex. All occurrences are replaced. Response includes occurrence count and read-after-write verification. Set dry_run=true to preview the match count without writing; set expected_count to abort unless exactly that many matches exist.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'find' => ['type' => 'string', 'minLength' => 1, 'description' => 'Literal text to find in post_content (case-sensitive, no regex).'], 'replace' => ['type' => 'string', 'description' => 'Literal replacement text. Empty string deletes the matched text.'], 'expected_count' => ['type' => 'integer', 'description' => 'Optional guard: abort without writing unless the number of occurrences equals this value.'], 'dry_run' => ['type' => 'boolean', 'description' => 'Report the occurrence count without writing. Default false.']], 'required' => ['id', 'find', 'replace']]],
            ['name' => 'wp_get_post_types', 'description' => 'Get all registered public post types (including custom post types)', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_delete_post', 'description' => 'Delete post', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean', 'description' => 'Skip trash and permanently delete']], 'required' => ['id']]],
            ['name' => 'wp_count_posts', 'description' => 'Get post counts by status', 'inputSchema' => ['type' => 'object', 'properties' => ['post_type' => ['type' => 'string', 'description' => 'Post type (post, page, etc)']]]],

            // Pages
            ['name' => 'wp_get_pages', 'description' => 'List WordPress pages. Returns id, title, status, and URL for each. Filter by parent to walk the page hierarchy.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of pages (default 10, max 100)'], 'parent' => ['type' => 'integer', 'description' => 'Parent page ID — returns only direct children of this page']]]],
            ['name' => 'wp_get_page', 'description' => 'Get single page by ID', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'description' => 'Page ID']], 'required' => ['id']]],
            ['name' => 'wp_create_page', 'description' => 'Create new page. Combine status="future" with date to schedule. Excerpt (via wp_update_post_meta on _excerpt or via wp_create_post fallback) may contain safe HTML.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'future', 'pending', 'private']], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to schedule.'], 'parent' => ['type' => 'integer', 'description' => 'Parent page ID']], 'required' => ['title', 'content']]],
            ['name' => 'wp_update_page', 'description' => 'Update existing page. Response includes saved_fields (actual stored values, read back from DB) so silent-drop / silent-modify by WordPress is surfaced rather than hidden. Pass date to reschedule or backdate.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'status' => ['type' => 'string'], 'date' => ['type' => 'string', 'description' => 'ISO 8601 datetime in the site timezone (e.g. 2026-12-25T09:00:00). Combine with status=future to reschedule, or use alone to backdate.'], 'excerpt' => ['type' => 'string', 'description' => 'Optional page excerpt. May contain safe HTML.'], 'post_author' => ['type' => 'integer', 'description' => 'User ID to reassign as page author.'], 'menu_order' => ['type' => 'integer', 'description' => 'Order among sibling pages. Lower = earlier in navigation.'], 'post_parent' => ['type' => 'integer', 'description' => 'Parent page ID (0 = top-level). Throws if the ID does not exist.'], 'password' => ['type' => 'string', 'description' => 'Page password. Empty string removes protection.']], 'required' => ['id']]],
            ['name' => 'wp_replace_in_page', 'description' => 'Find/replace a literal string inside a page\'s content without resending the full body. Page-typed variant of wp_replace_in_post — same semantics: case-sensitive literal match, all occurrences replaced, dry_run preview, expected_count guard, read-after-write verification.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'find' => ['type' => 'string', 'minLength' => 1, 'description' => 'Literal text to find in the page content (case-sensitive, no regex).'], 'replace' => ['type' => 'string', 'description' => 'Literal replacement text. Empty string deletes the matched text.'], 'expected_count' => ['type' => 'integer', 'description' => 'Optional guard: abort without writing unless the number of occurrences equals this value.'], 'dry_run' => ['type' => 'boolean', 'description' => 'Report the occurrence count without writing. Default false.']], 'required' => ['id', 'find', 'replace']]],
            ['name' => 'wp_delete_page', 'description' => 'Delete page', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],

            // Media
            ['name' => 'wp_get_media', 'description' => 'List media library attachments, paginated. Returns {total, page, per_page, pages, returned, has_more, media[]}; each row has id, title, url, mime_type, date, and alt (also exposed as alt_text — same value, both keys present). Use has_more or total to know when to stop paging. Filter by mime_type to narrow to images / videos / audio / documents.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Items per page (default 10, max 100)'], 'page' => ['type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.'], 'mime_type' => ['type' => 'string', 'description' => 'Filter by mime type prefix or full type (image, video, audio, application/pdf)']]]],
            ['name' => 'wp_get_media_item', 'description' => 'Get single media item by ID', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
            ['name' => 'wp_upload_media_from_url', 'description' => 'Download an image from a public HTTPS URL and add it to the WordPress media library. Use this when you have an image URL (Unsplash, Pexels, client asset, etc) that needs to become a library attachment — for example before setting it as a featured image. Returns the new attachment ID.', 'inputSchema' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'Public HTTPS URL of the image to download'], 'filename' => ['type' => 'string', 'description' => 'Optional filename (with extension). Derived from URL if omitted.'], 'alt_text' => ['type' => 'string', 'description' => 'Alt text for accessibility and SEO'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['url']]],
            ['name' => 'wp_upload_media', 'description' => 'Upload an image to the media library from base64-encoded bytes. Use this for AI-generated images or pasted screenshots where you have raw bytes rather than a URL. For images already hosted somewhere, prefer wp_upload_media_from_url.', 'inputSchema' => ['type' => 'object', 'properties' => ['filename' => ['type' => 'string', 'description' => 'Filename with extension (e.g. hero.jpg)'], 'content_base64' => ['type' => 'string', 'description' => 'Base64-encoded file bytes'], 'alt_text' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string']], 'required' => ['filename', 'content_base64']]],
            ['name' => 'wp_set_featured_image', 'description' => 'Set or replace the featured image on a post or page. Accepts EITHER an existing media_id from wp_get_media, OR an image_url that will be downloaded into the library first. Pass media_id=0 to remove the featured image.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Post or page ID'], 'media_id' => ['type' => 'integer', 'description' => 'Existing attachment ID (use 0 to remove the featured image)'], 'image_url' => ['type' => 'string', 'description' => 'Public HTTPS image URL to download and use instead of media_id'], 'alt_text' => ['type' => 'string', 'description' => 'Alt text applied when image_url is provided']], 'required' => ['post_id']]],
            ['name' => 'wp_update_media', 'description' => 'Update metadata on an existing media attachment: alt text, caption, title, description. Great for adding SEO-friendly alt text to images already in the library.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'alt_text' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string']], 'required' => ['id']]],
            ['name' => 'wp_delete_media', 'description' => 'Delete media item', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],
            ['name' => 'wp_count_media', 'description' => 'Get media counts by type', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],

            // Categories & Tags (Terms)
            ['name' => 'wp_get_categories', 'description' => 'List blog categories (the `category` taxonomy). Returns id, name, slug, count, and parent for each. For custom taxonomies (product_cat, brand, etc.), use wp_get_terms instead.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of categories (default 100, max 100)']]]],
            ['name' => 'wp_get_tags', 'description' => 'List blog tags (the `post_tag` taxonomy). Returns id, name, slug, and count for each. For custom taxonomies, use wp_get_terms.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of tags (default 100, max 100)']]]],
            ['name' => 'wp_create_term', 'description' => 'Create a term in any registered taxonomy (category, post_tag, or any custom taxonomy). Description may contain inline HTML — WordPress permits <a>, <strong>, <em>, <blockquote>, <code>, <cite>, <abbr>, <acronym> in term descriptions; block-level tags (<p>, <h1>-<h6>, <ul>) are stripped by WP core. Use wp_get_taxonomies to discover available taxonomy slugs.', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat)'], 'description' => ['type' => 'string', 'description' => 'Optional description. May contain inline HTML (<a>, <strong>, <em>, etc.); block-level tags are stripped by WP core.'], 'parent' => ['type' => 'integer', 'description' => 'Parent term ID (only applies to hierarchical taxonomies)'], 'slug' => ['type' => 'string', 'description' => 'Optional URL-friendly slug. Auto-generated from name if omitted.']], 'required' => ['name', 'taxonomy']]],
            ['name' => 'wp_update_term', 'description' => 'Update an existing term in any taxonomy. Use this to rename a tag/category, edit its description, or change its slug. Description may contain inline HTML (WP core strips block-level tags). Pair with wp_update_term_meta to edit SEO meta on tags (Yoast/Rank Math/AIOSEO store tag SEO data in wp_termmeta).', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'description' => ['type' => 'string', 'description' => 'Optional description. May contain inline HTML.'], 'parent' => ['type' => 'integer', 'description' => 'Parent term ID (hierarchical taxonomies only)']], 'required' => ['id', 'taxonomy']]],
            ['name' => 'wp_delete_term', 'description' => 'Delete a term from any registered taxonomy.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to']], 'required' => ['id', 'taxonomy']]],
            ['name' => 'wp_add_post_terms', 'description' => 'Add or replace terms on a post in any taxonomy.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'terms' => ['type' => 'array', 'items' => ['type' => 'integer']], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat)']], 'required' => ['post_id', 'terms', 'taxonomy']]],
            ['name' => 'wp_get_terms', 'description' => 'List terms in any registered taxonomy with paginated output. Returns id, name, slug, description, count, parent. Use to map term names to IDs before wp_add_post_terms, or to walk a taxonomy tree.', 'inputSchema' => ['type' => 'object', 'properties' => ['taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat, any custom taxonomy)'], 'search' => ['type' => 'string', 'description' => 'Optional name-substring filter (case-insensitive).'], 'hide_empty' => ['type' => 'boolean', 'description' => 'Exclude terms with zero attached posts. Default false.'], 'parent' => ['type' => 'integer', 'description' => 'Return only children of this parent term ID (hierarchical taxonomies).'], 'per_page' => ['type' => 'integer', 'description' => 'Results per page. Default 100, max 500.'], 'page' => ['type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.']], 'required' => ['taxonomy']]],
            ['name' => 'wp_count_terms', 'description' => 'Get term counts in a taxonomy', 'inputSchema' => ['type' => 'object', 'properties' => ['taxonomy' => ['type' => 'string']]]],
            ['name' => 'wp_get_taxonomies', 'description' => 'Get all registered public taxonomies (built-in plus custom taxonomies registered by themes/plugins like product_cat, brand, etc.). Returns the taxonomy slug, label, hierarchical flag, and which post types it applies to.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],

            // Term Meta (for SEO-plugin tag/category meta — Yoast, Rank Math, AIOSEO)
            ['name' => 'wp_get_term_meta', 'description' => 'Get term meta data — the raw wp_termmeta table. For SEO fields, prefer wp_get_term_seo_meta: Yoast stores taxonomy SEO in the wpseo_taxonomy_meta option and All in One SEO in its own aioseo_terms table, so neither appears here. Rank Math and SEOPress do use term meta, so their keys are readable through this tool.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string', 'description' => 'Specific meta key. Omit to return all meta for the term.']], 'required' => ['term_id']]],
            ['name' => 'wp_update_term_meta', 'description' => 'Update term meta data — writes to the raw wp_termmeta table. For SEO fields, use wp_update_term_seo_meta instead. Two SEO plugins do NOT read term meta: Yoast keeps taxonomy SEO in the wpseo_taxonomy_meta option and All in One SEO in its aioseo_terms table, so a write here returns success, round-trips correctly on read, and changes nothing on the rendered archive (GitHub #6). Rank Math (rank_math_title / rank_math_description) and SEOPress do use term meta, so writing their keys here works. String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']]]], 'required' => ['term_id', 'key', 'value']]],
            ['name' => 'wp_delete_term_meta', 'description' => 'Delete term meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['term_id', 'key']]],

            // Comments
            ['name' => 'wp_get_comments', 'description' => 'List comments on the site or a specific post. Returns id, post_id, author, content, date, and status. Requires moderate_comments to list any status other than "approve".', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Limit to comments on this post ID'], 'per_page' => ['type' => 'integer', 'description' => 'Number of comments (default 10, max 100)'], 'status' => ['type' => 'string', 'enum' => ['approve', 'hold', 'spam', 'trash', 'all'], 'description' => 'Comment status filter. "approve" is public; other values require moderate_comments.']]]],
            ['name' => 'wp_create_comment', 'description' => 'Create a comment. Content may contain WordPress\'s standard comment HTML tags (<a>, <strong>, <em>, <blockquote>, <code>, <cite>, <abbr>, <acronym>) — matches what the WP comment form permits. Other tags are stripped.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'content' => ['type' => 'string'], 'author' => ['type' => 'string'], 'author_email' => ['type' => 'string']], 'required' => ['post_id', 'content']]],
            ['name' => 'wp_delete_comment', 'description' => 'Delete a comment', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'force' => ['type' => 'boolean']], 'required' => ['id']]],
            ['name' => 'wp_get_pending_comments', 'description' => 'Get comments awaiting moderation (status=hold). Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => 'Max comments to return (default 20, max 100)']]]],
            ['name' => 'wp_approve_comment', 'description' => 'Approve a pending comment. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],
            ['name' => 'wp_spam_comment', 'description' => 'Mark a comment as spam. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],
            ['name' => 'wp_trash_comment', 'description' => 'Move a comment to trash. Requires moderate_comments capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['comment_id' => ['type' => 'integer']], 'required' => ['comment_id']]],

            // Users
            ['name' => 'wp_get_users', 'description' => 'List site users. Returns id, display_name, role, and post_count. Emails and usernames are NOT exposed. Filter by role slug (administrator / editor / author / contributor / subscriber, or any custom role).', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of users (default 10, max 100)'], 'role' => ['type' => 'string', 'description' => 'Filter by role slug — use wp_get_user on a specific ID for full profile data.']]]],
            ['name' => 'wp_get_user', 'description' => 'Get user by ID', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],

            // Post Meta
            ['name' => 'wp_get_post_meta', 'description' => 'Get post meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['post_id']]],
            ['name' => 'wp_update_post_meta', 'description' => 'Update post meta. Value can be any JSON type (string, number, boolean, array, object). String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success. Arrays and objects are serialized by WordPress on write and returned as PHP arrays by wp_get_post_meta on read. Overwrites the existing row for this key (use wp_add_post_meta for multi-row keys).', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']], 'description' => 'Any JSON value. Do not pass PHP-serialized strings (a:1:{...}) — pass the structured value directly.']], 'required' => ['post_id', 'key', 'value']]],
            ['name' => 'wp_add_post_meta', 'description' => 'Add a meta row without overwriting existing values under the same key. Use for keys that store multiple rows (e.g. tag one post with several IDs under the same key). Value can be any JSON type. String values are stored verbatim for callers with the unfiltered_html capability (which the API-key auth path has); other callers get WordPress\'s post-content HTML allow-list, which strips script tags. Values holding JSON are safe either way — backslash escapes are preserved. Customize per key with the more_mcp_meta_value_sanitizer filter. Response includes saved_value_matches_sent plus a warnings array when the stored bytes differ from what was sent, so a sanitizer or filter that alters the value is surfaced rather than reported as a clean success. Arrays and objects are serialized by WordPress. If unique=true and a row with this key already exists, the call returns created=false.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'value' => ['oneOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'array'], ['type' => 'object']], 'description' => 'Any JSON value. Do not pass PHP-serialized strings.'], 'unique' => ['type' => 'boolean', 'description' => 'If true, fail (return created=false) when a row with this key already exists. Default false.']], 'required' => ['post_id', 'key', 'value']]],
            ['name' => 'wp_delete_post_meta', 'description' => 'Delete post meta data', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'key' => ['type' => 'string']], 'required' => ['post_id', 'key']]],

            // Site & Search
            ['name' => 'wp_get_site_info', 'description' => 'Get user-facing site metadata (name, description, URL, language, timezone, WP version). For operator-facing environment (PHP/MySQL versions, plugin count, memory limits, disk free), use wp_get_site_status.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_get_site_status', 'description' => 'One-shot site diagnostic. Returns WordPress version, PHP version, MySQL/MariaDB version, active plugin count, active theme details, memory limit, max upload size, timezone, WP_DEBUG_LOG state, disk free space, install age, and site/home URLs. Use this at the start of a debugging or environment-inspection conversation instead of piecing it together from wp_get_site_info + wp_get_plugins + wp_get_active_theme. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_get_error_log_tail', 'description' => 'Read the tail of wp-content/debug.log. Returns the last N lines (default 100, max 1000), optionally filtered by a case-insensitive substring. Automatically caps file read at last 1MB to prevent memory blowup on huge logs (truncated=true when this happens). Returns status="disabled" with instructions when WP_DEBUG_LOG is not enabled in wp-config.php. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => ['lines' => ['type' => 'integer', 'description' => 'Number of lines to return from the tail (default 100, max 1000).'], 'filter' => ['type' => 'string', 'description' => 'Optional case-insensitive substring filter applied before the last-N slice (e.g. "Fatal error", "Deprecated", a plugin slug).']]]],
            ['name' => 'wp_get_cron_schedule', 'description' => 'Enumerate scheduled wp_cron events. Returns each event with hook name, next run (unix + ISO 8601), seconds until next run, is_overdue flag, recurrence (hourly / twicedaily / daily / custom + interval in seconds), and args. Sorted by next-run ascending so overdue events come first. Useful for diagnosing missed schedules, plugin cron conflicts, or unfired hooks. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'more_mcp_connection_health', 'description' => 'Diagnostic probe for the current MCP connection. Returns MCP endpoint route, authentication method used by this request (api-key or oauth-bearer), OAuth access token time-to-live in seconds (null for api-key), current MCP session ID, active MCP capabilities negotiated at initialize, plus More MCP + WordPress + PHP version strings. No arguments. Call at connection start to confirm setup, or when diagnosing 401/403/404 issues. Any authenticated caller.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'more_mcp_undo_last_operation', 'description' => 'Reverse one prior operation using the token from its response. LIMITED SCOPE: only some tools emit undo tokens, and a tool that does not emit one cannot be undone through this. Currently covered: the Gutenberg block mutations (blocks_insert, blocks_update, blocks_delete, blocks_move), the template and reusable-block writes (blocks_update_template, blocks_revert_template, blocks_update_reusable, blocks_delete_reusable), wp_reorder_menu_items, the Elementor element writes (elementor_update_widget, elementor_delete_widget), and the Divi whole-node writes (divi_replace_module, divi_insert_module, divi_delete_module), and the forms entry writes (forms_update_entry_status, forms_trash_entry). NOT covered — these write without any undo: wp_update_post, wp_update_option, wp_update_widget, wp_update_seo_meta, wp_update_term_seo_meta, wp_update_post_meta, the other elementor_* tools, and every wc_* tool. Check for an `undo` key in a response rather than assuming one exists. Tokens live 72 hours and are one-shot, consumed on a successful undo. The capability requirement matches the original operation and is re-checked at redemption, because a token may be presented by a different caller than the one who created it. Free basic mode — single-op restore, local storage.', 'inputSchema' => ['type' => 'object', 'properties' => ['token' => ['type' => 'string', 'description' => 'The undo token from a prior tool response (response.undo.token).']], 'required' => ['token']]],
            ['name' => 'wp_search', 'description' => 'Search all content. Pass snippet>0 to receive a content excerpt around each match (saves tokens vs. fetching each result with wp_get_page).', 'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'post_type' => ['type' => 'string'], 'per_page' => ['type' => 'integer', 'description' => 'Number of results (default 20, max 100)'], 'snippet' => ['type' => 'integer', 'description' => 'Snippet length in characters around the matched term (default 0 = off, recommended 160-240). When set, results include slug and snippet fields.']], 'required' => ['query']]],

            // Options
            ['name' => 'wp_get_option', 'description' => 'Get a single WordPress option value (allowlisted, sensitive keys redacted)', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
            ['name' => 'wp_get_plugin_settings', 'description' => 'Get all options stored by a plugin, looked up by slug. Sensitive keys (api keys, secrets, tokens, passwords) are redacted before return.', 'inputSchema' => ['type' => 'object', 'properties' => ['plugin_slug' => ['type' => 'string', 'description' => 'Plugin slug, e.g. woocommerce or rank-math']], 'required' => ['plugin_slug']]],
            ['name' => 'wp_update_option', 'description' => 'Update a WordPress option. Requires the "Allow AI to write WordPress options" admin toggle to be enabled, and the option name must be in the allowlist (extend via the more_mcp_writable_options filter). Sensitive option names are permanently denylisted.', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'value' => ['description' => 'New value (any JSON type). Full overwrite — read first, merge in your client, then write back.']], 'required' => ['name', 'value']]],

            // Menus
            ['name' => 'wp_get_menus', 'description' => 'List all registered navigation menus (nav_menu taxonomy). Returns id, name, slug, and item count for each. Use wp_get_menu_items to enumerate items within a specific menu.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_get_menu_items', 'description' => 'Get menu items', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer']], 'required' => ['menu_id']]],
            ['name' => 'wp_create_menu_item', 'description' => 'Create a menu item in a navigation menu. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string', 'description' => 'External URL (leave empty if linking to a post/page via object_id)'], 'object_id' => ['type' => 'integer', 'description' => 'WordPress object ID (post, page, or term)'], 'object_type' => ['type' => 'string', 'enum' => ['post', 'page', 'category', 'custom'], 'description' => 'Type of object being linked (default: custom)'], 'parent_id' => ['type' => 'integer', 'description' => 'Parent menu item ID for nested items (0 = top level)'], 'position' => ['type' => 'integer', 'description' => 'Position in menu order (default: end)'], 'target' => ['type' => 'string', 'enum' => ['_self', '_blank'], 'description' => 'Link target']], 'required' => ['menu_id', 'title']]],
            ['name' => 'wp_update_menu_item', 'description' => 'Update an existing menu item. Only the fields you pass will change; unspecified fields are preserved from the existing item. The tool will refuse explicit-empty values for title or url that would destroy a non-empty existing value — to intentionally clear those, use wp_delete_menu_item then wp_create_menu_item. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_item_id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string'], 'parent_id' => ['type' => 'integer'], 'position' => ['type' => 'integer'], 'target' => ['type' => 'string', 'enum' => ['_self', '_blank']]], 'required' => ['menu_item_id']]],
            ['name' => 'wp_delete_menu_item', 'description' => 'Delete a menu item. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_item_id' => ['type' => 'integer']], 'required' => ['menu_item_id']]],
            ['name' => 'wp_reorder_menu_items', 'description' => 'Reorder menu items by passing an array of menu_item_ids in the desired order. Existing titles, URLs, parents, and other fields are preserved on every item touched. Every response includes an "undo" envelope with a token that more_mcp_undo_last_operation can consume for 72 hours to restore the pre-op menu order. If the response includes a "skipped" array, those items could not be safely reordered (e.g. missing or recently deleted) — the rest were reordered correctly. Requires edit_theme_options capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['menu_id' => ['type' => 'integer'], 'item_order' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Array of menu_item_ids in the desired order']], 'required' => ['menu_id', 'item_order']]],

            // Plugins & Themes
            ['name' => 'wp_get_plugins', 'description' => 'List all installed plugins. Returns plugin file path, name, version, description, author, and active status for each. Useful for diagnosing plugin conflicts and building a compatibility picture at the start of a debugging conversation.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_get_themes', 'description' => 'List all installed themes. Returns theme slug, name, version, author, and active flag for each. Use wp_get_active_theme for details on the currently-active theme only.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],

            // Theme & Appearance
            ['name' => 'wp_get_active_theme', 'description' => 'Get the active theme with name, version, parent (if child theme), and screenshot URL', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_get_theme_mods', 'description' => 'Get all customizer settings (theme_mods) for the active theme', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_update_theme_mod', 'description' => 'Update a single theme customizer setting. Requires the "Allow AI to modify theme appearance" admin toggle AND the mod name must be in the allowlist (extend via the more_mcp_writable_theme_mods filter).', 'inputSchema' => ['type' => 'object', 'properties' => ['mod_name' => ['type' => 'string'], 'value' => ['description' => 'New value (any JSON type compatible with set_theme_mod)']], 'required' => ['mod_name', 'value']]],
            ['name' => 'wp_get_custom_css', 'description' => 'Get the active theme\'s custom CSS', 'inputSchema' => ['type' => 'object', 'properties' => ['theme_slug' => ['type' => 'string', 'description' => 'Theme slug (defaults to active theme)']]]],
            ['name' => 'wp_update_custom_css', 'description' => 'Update the active theme\'s custom CSS. CSS is filtered through wp_kses (script tags stripped). Requires the "Allow AI to modify theme appearance" admin toggle and unfiltered_html capability.', 'inputSchema' => ['type' => 'object', 'properties' => ['css' => ['type' => 'string'], 'theme_slug' => ['type' => 'string', 'description' => 'Theme slug (defaults to active theme)']], 'required' => ['css']]],
            ['name' => 'wp_get_widgets', 'description' => 'List widget instances. Uses the WordPress core /wp/v2/widgets REST endpoint so classic and block widgets are returned uniformly. Omit sidebar to return widgets across ALL sidebars including wp_inactive_widgets (orphaned widgets from prior themes — these have rendered:"" and produce no front-end output). Filter by a specific sidebar ID (discover IDs via wp_get_sidebars) to scope results; a non-existent sidebar ID returns an empty array, not an error.', 'inputSchema' => ['type' => 'object', 'properties' => ['sidebar' => ['type' => 'string', 'description' => 'Optional sidebar ID to filter by. Omit to return widgets across all sidebars (includes wp_inactive_widgets).']]]],
            ['name' => 'wp_get_sidebars', 'description' => 'List registered sidebars (widget areas) on the active theme with their IDs, names, description, and status. Use to discover sidebar IDs before calling wp_get_widgets or wp_update_widget.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_update_widget', 'description' => 'Update a widget instance by ID. Requires the "Allow AI to modify theme appearance" admin toggle AND edit_theme_options capability. Uses WordPress core /wp/v2/widgets so classic and block widgets are handled uniformly. Pass the id returned by wp_get_widgets.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'description' => 'Widget ID (e.g. text-2, block-15)'], 'sidebar' => ['type' => 'string', 'description' => 'Sidebar ID to place the widget in (omit to leave unchanged)'], 'instance' => ['type' => 'object', 'description' => 'Widget instance data. For classic widgets, either pass the same {encoded, hash} object returned by wp_get_widgets or wrap raw settings as {raw: {…}}.'], 'form_data' => ['type' => 'string', 'description' => 'Serialized form data (classic widgets alternative to instance)']], 'required' => ['id']]],

            // SEO Meta (auto-detects Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, or The SEO Framework)
            ['name' => 'wp_get_seo_meta', 'description' => 'Get the SEO meta fields for a post (title, description, focus keyword, noindex, canonical, OG/Twitter overrides, schema page type, URL slug). Auto-detects Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, or The SEO Framework and reads from wherever that plugin actually stores each field. The response lists which fields the active plugin supports, includes the raw stored values for diagnostics, and flags when more than one SEO plugin is active (which produces duplicate meta tags in the rendered head). The slug is a WordPress-native field and is returned regardless of SEO plugin. Complements seo_audit_meta_tags, which reads the rendered output rather than the database.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer']], 'required' => ['post_id']]],
            ['name' => 'wp_update_seo_meta', 'description' => 'Update SEO meta fields on a post. Auto-routes each field to wherever the active plugin actually reads it — Yoast SEO, Rank Math, All in One SEO (custom tables, not post meta), SEOPress, Slim SEO, or The SEO Framework. A field the active plugin does not store is REFUSED by name rather than written to a plausible-looking key that would store cleanly and change nothing on the page. Every write is read back from storage and the returned values are what was actually stored, so a sanitizer or normalising filter is visible instead of being reported as a clean success. The slug field is WordPress-native and works with no SEO plugin active. Requires edit_post capability on the target post.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'title' => ['type' => 'string', 'description' => 'SEO title (replaces the meta title used in browser tabs and SERPs)'], 'description' => ['type' => 'string', 'description' => 'SEO meta description (used in SERPs)'], 'focus_keyword' => ['type' => 'string', 'description' => 'Primary focus keyword for SEO scoring. Yoast, Rank Math, and SEOPress only.'], 'noindex' => ['type' => 'boolean', 'description' => 'Tell search engines not to index this URL'], 'canonical' => ['type' => 'string', 'description' => 'Canonical URL override'], 'og_title' => ['type' => 'string', 'description' => 'Open Graph title (Facebook / Slack / LinkedIn previews)'], 'og_description' => ['type' => 'string', 'description' => 'Open Graph description'], 'twitter_title' => ['type' => 'string', 'description' => 'Twitter Card title'], 'twitter_description' => ['type' => 'string', 'description' => 'Twitter Card description'], 'schema_page_type' => ['type' => 'string', 'description' => 'Schema.org page type for the @graph (Yoast only). Values that render: WebPage, ContactPage, AboutPage, CollectionPage, FAQPage, ItemPage, ProfilePage, SearchResultsPage, CheckoutPage, RealEstateListing, QAPage.'], 'slug' => ['type' => 'string', 'description' => 'URL slug (post_name). WordPress will sanitize and ensure uniqueness; the actually-saved value is returned in the response so the caller can confirm.']], 'required' => ['post_id']]],
            ['name' => 'wp_get_term_seo_meta', 'description' => 'Get the SEO meta fields for a term (category, tag, or any custom taxonomy term). Reads from wherever the active SEO plugin actually stores term data, which is NOT always term meta: Yoast keeps taxonomy SEO in the wpseo_taxonomy_meta option keyed by taxonomy and term ID, and All in One SEO uses its own aioseo_terms table. Use this instead of wp_get_term_meta for SEO fields — wp_get_term_meta reads the raw meta table and will report nothing for those two plugins. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress. Requires the taxonomy, because Yoast cannot be looked up by term ID alone.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to (e.g. category, post_tag, product_cat). Required.']], 'required' => ['term_id', 'taxonomy']]],
            ['name' => 'wp_update_term_seo_meta', 'description' => 'Update SEO meta fields on a term, writing to the location the active SEO plugin actually reads. This is the correct tool for taxonomy noindex: wp_update_term_meta writes to the term meta table, which Yoast never reads for noindex, so that call returns success and leaves the archive indexable (GitHub #6). Yoast writes are merged into wpseo_taxonomy_meta read-modify-write, so other terms\' settings are preserved. Yoast term noindex is tri-state — "default" inherits the taxonomy-wide setting and is not the same as an explicit index; passing noindex=false writes an explicit index. A field the active plugin does not store at term level is refused by name. Every write is read back from storage. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress. Requires manage_categories.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to. Required.'], 'title' => ['type' => 'string', 'description' => 'SEO title for the term archive'], 'description' => ['type' => 'string', 'description' => 'SEO meta description for the term archive'], 'focus_keyword' => ['type' => 'string', 'description' => 'Focus keyword. Yoast and Rank Math only at term level.'], 'noindex' => ['type' => 'boolean', 'description' => 'Tell search engines not to index this term archive'], 'canonical' => ['type' => 'string', 'description' => 'Canonical URL override for the term archive'], 'og_title' => ['type' => 'string', 'description' => 'Open Graph title'], 'og_description' => ['type' => 'string', 'description' => 'Open Graph description'], 'twitter_title' => ['type' => 'string', 'description' => 'Twitter Card title'], 'twitter_description' => ['type' => 'string', 'description' => 'Twitter Card description']], 'required' => ['term_id', 'taxonomy']]],
            ['name' => 'seo_audit_meta_tags', 'description' => 'Fetch a post\'s actual rendered HTML and parse the head for title, meta description, canonical, viewport, Open Graph and Twitter Card tags. Catches theme/plugin/cache conflicts that only appear in the served output — duplicate title tags, mismatched canonicals, missing OG images, viewport misconfiguration. Complements wp_get_seo_meta (which reads DB fields) by validating what actually reaches crawlers. Pass a post_id (URL is resolved via get_permalink) or a same-site url. Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Post ID whose permalink to audit. Either post_id or url is required.'], 'url' => ['type' => 'string', 'description' => 'Absolute URL to audit. Must be on this site (same host as home_url). Either post_id or url is required.']]]],

            // Permalink Structure
            ['name' => 'wp_get_permalink_structure', 'description' => 'Get the WordPress permalink structure (e.g. /%postname%/, /%year%/%monthnum%/%postname%/). Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
            ['name' => 'wp_update_permalink_structure', 'description' => 'Update the WordPress permalink structure. Requires the "Allow AI to write WordPress options" admin toggle. Common values: /%postname%/, /%year%/%monthnum%/%postname%/, /%category%/%postname%/. Changing this rewrites every URL on the site — flushes rewrite rules automatically.', 'inputSchema' => ['type' => 'object', 'properties' => ['structure' => ['type' => 'string', 'description' => 'New permalink structure (e.g. /%postname%/)']], 'required' => ['structure']]],

            // Post Revisions
            ['name' => 'wp_get_post_revisions', 'description' => 'Get a post\'s revision history, paginated. Returns {total, offset, limit, returned, has_more, revisions[]}; each row carries revision_id, author, date, word_count, content_length, differs_from_parent, and elementor_data_length so revisions can be told apart without fetching each one. A revision_id is a valid post_id for the meta tools, so wp_get_post_meta(post_id=<revision_id>, key=_elementor_data) recovers a builder page\'s prior state — the practical route when wp_restore_revision is not enough, since it restores post_content while a builder page lives in meta.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => 'Max revisions to return (default 20, max 100)'], 'offset' => ['type' => 'integer', 'description' => 'Skip this many revisions before returning results. Use with limit to page through a long history.']], 'required' => ['post_id']]],
            ['name' => 'wp_restore_revision', 'description' => 'Restore a post to a specific revision. The current post content becomes the previous revision (so it can still be reverted again). Requires edit_post capability on the parent post.', 'inputSchema' => ['type' => 'object', 'properties' => ['revision_id' => ['type' => 'integer']], 'required' => ['revision_id']]],
        ];

        // Conditionally add integration tools
        $tools = array_merge( $tools, WooIntegration::get_tools() );
        $tools = array_merge( $tools, GPIntegration::get_tools() );
        $tools = array_merge( $tools, SVIntegration::get_tools() );
        $tools = array_merge( $tools, FCIntegration::get_tools() );
        $tools = array_merge( $tools, LSIntegration::get_tools() );
        $tools = array_merge( $tools, ElementorIntegration::get_tools() );
        $tools = array_merge( $tools, DiviIntegration::get_tools() );
        $tools = array_merge( $tools, ACFIntegration::get_tools() );
        $tools = array_merge( $tools, RedirectionIntegration::get_tools() );
        $tools = array_merge( $tools, AnalyticsIntegration::get_tools() );
        $tools = array_merge( $tools, FormsIntegration::get_tools() );

        // Gutenberg block tools. Unlike the integrations above these are not
        // gated on a third-party plugin — blocks are WordPress core — so they
        // register unconditionally and each handler checks its own caps.
        $tools = array_merge( $tools, BlocksContent::get_tools() );
        $tools = array_merge( $tools, BlocksTemplates::get_tools() );

        // Plugin/theme lifecycle tools. Returns an empty array unless the site
        // owner has enabled the master toggle, so on a default install these
        // never appear in tools/list — an agent cannot call what it cannot see.
        $tools = array_merge( $tools, LifecycleManager::get_tools() );

        // 1.4.37 Candidate 5 — when Elementor's own MCP module is present,
        // prefix our elementor_* tool descriptions with a routing hint so
        // agents pick the canonical primitive per task. No behavior change
        // to our tools; opt-in defer only.
        $tools = Elementor_Coexistence::filter_elementor_tool_descriptions( $tools );

        /**
         * Filter the full tool list returned to tools/list. Applied last so
         * profile trims + custom filters see every registered tool.
         *
         * @param array $tools Tool definitions with name, description, inputSchema.
         */
        return apply_filters( 'more_mcp_tools', $tools );
    }

    /**
     * Handle the MCP endpoint - Streamable HTTP transport
     * Single endpoint for all MCP communication
     */
    public function handle_mcp($request) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';

        // Handle OPTIONS for CORS preflight
        if ($method === 'OPTIONS') {
            return $this->cors_response();
        }

        // Validate Origin header to prevent DNS rebinding attacks
        $origin_check = $this->validate_origin($request);
        if ($origin_check !== true) {
            return $origin_check;
        }

        // Rate limiting
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
        $rate_check = $this->check_rate_limit($client_ip);
        if ($rate_check !== true) {
            return $rate_check;
        }

        // GET request = client wants to listen for server-initiated messages
        if ($method === 'GET') {
            return $this->handle_get_stream($request);
        }

        // POST request = client sending JSON-RPC message
        if ($method === 'POST') {
            // Validate Accept header per MCP spec
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

        // DELETE request = terminate session
        if ($method === 'DELETE') {
            return $this->handle_delete_session($request);
        }

        return new \WP_REST_Response(['error' => 'Method not allowed'], 405);
    }

    /**
     * Handle CORS preflight
     */
    private function cors_response() {
        $response = new \WP_REST_Response(null, 204);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, Mcp-Session-Id, MMCP-Key');
        $response->header('Access-Control-Max-Age', '86400');
        return $response;
    }

    /**
     * Handle GET - tri-state response for the MCP Streamable HTTP endpoint
     *
     * The single /mcp endpoint is hit by multiple clients with conflicting
     * probe behavior. Each "fix" we've shipped has broken a different client.
     * This is the 4th iteration. Auth check FIRST (RFC 9728), then dispatch by
     * User-Agent so each client gets its expected behavior:
     *
     *  1. Unauthenticated GET (any UA) →
     *     401 + WWW-Authenticate: Bearer resource_metadata="..."
     *     (RFC 9728 — required for Claude.ai web / ChatGPT pre-OAuth discovery.
     *     Handled inside validate_auth().)
     *
     *  2. Authenticated GET + UA contains "Claude-User" →
     *     200 + Content-Type: text/event-stream + a minimal keepalive comment.
     *     This is Anthropic's post-OAuth session-establishment probe. Without
     *     it Anthropic retries the GET four times then gives up, so the entire
     *     OAuth flow (which succeeded at /token in 1.4.17) still fails to
     *     produce a working session. Added in 1.4.18.
     *
     *  3. Authenticated GET + any other UA (mcp-remote, node-fetch, etc.) →
     *     405 + Allow: POST, DELETE, OPTIONS.
     *     Preserves the 1.4.12 fix that stopped mcp-remote retry storms. The
     *     MCP Streamable HTTP spec says servers MAY support GET for SSE and
     *     MUST return 405 if they don't — both are spec-compliant. mcp-remote
     *     handles 405 cleanly by falling back to POST-only.
     *
     * See _internal/more-mcp/MCP_ENDPOINT_BEHAVIOR_MATRIX.md before changing.
     */
    private function handle_get_stream($request) {
        $auth_check = $this->validate_auth($request);
        if ($auth_check !== true) {
            return $auth_check;
        }

        // Anthropic post-OAuth session probe — return 200 + SSE keepalive and
        // exit early. Can't use WP_REST_Response here because it auto-JSON-
        // encodes the body, which would corrupt SSE format. We have nothing
        // server-initiated to push, so a single keepalive comment followed by
        // clean termination is the minimum spec-compliant response.
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
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            echo ": more-mcp-keepalive\n\n";
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit;
        }

        // All other authenticated clients (mcp-remote, custom scripts) — SSE
        // not hosted here. 405 with Allow header so they fall back to POST.
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

        // Unreachable below — preserved for future SSE support.
        $session_id = $request->get_header('Mcp-Session-Id');
        $accept = $request->get_header('Accept');

        // Validate Accept header must include text/event-stream
        if (empty($accept) || strpos($accept, 'text/event-stream') === false) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Accept header must include text/event-stream for GET requests',
                ],
            ], 400);
        }

        // Session ID required for GET streams
        if (empty($session_id)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Mcp-Session-Id header required',
                ],
            ], 400);
        }

        // Validate session ID format
        if (!$this->validate_session_id_format($session_id)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid session ID format',
                ],
            ], 400);
        }

        // Check if session exists
        if (!$this->is_valid_session($session_id)) {
            return new \WP_REST_Response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Session not found or expired',
                ],
            ], 404);
        }

        // Check for Last-Event-ID for resumability
        $last_event_id = $request->get_header('Last-Event-ID');

        // Set SSE headers
        $response = new \WP_REST_Response(null, 200);
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Expose-Headers', 'Mcp-Session-Id');
        $response->header('X-Accel-Buffering', 'no'); // Disable nginx buffering

        // Note: WordPress REST API doesn't support long-lived SSE connections well
        // For production SSE, consider a dedicated endpoint outside WP REST API
        // This implementation acknowledges the stream and returns empty
        // Server-initiated messages would require a different architecture

        return $response;
    }

    /**
     * Handle POST - Process JSON-RPC message
     */
    private function handle_post_message($request) {
        // Parse JSON-RPC message
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

        // Get session ID from header
        $session_id = $request->get_header('Mcp-Session-Id');

        // stash session id for more_mcp_connection_health diagnostic tool.
        $this->request_session_id = $session_id ? (string) $session_id : null;

        // Authenticate EVERY request — API key or Bearer token required.
        $auth_check = $this->validate_auth($request);
        if ($auth_check !== true) {
            return $auth_check;
        }

        // Build auth fingerprint to bind sessions to credentials.
        $auth_fingerprint = $this->build_auth_fingerprint($request);

        // For non-initialize requests, validate session
        if ($method !== 'initialize') {
            // Per MCP spec: SHOULD respond with 400 Bad Request to requests without session ID
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

            // Validate session ID format
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

            // Check if session exists
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

            // Verify session is bound to the same credentials. 1.4.27 — DB-backed
            // lookup (see Session_Store class doc for the object-cache motivation).
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

        // Process the method
        $result = $this->process_method($method, $params, $id);

        // For initialize, generate and return session ID
        if ($method === 'initialize' && $result && isset($result['result'])) {
            $new_session_id = $this->generate_session_id();
            // Store the session bound to the authenticated credentials
            $this->store_session($new_session_id, $auth_fingerprint);
            $response = $this->json_response($result, 200);
            $response->header('Mcp-Session-Id', $new_session_id);
            return $response;
        }

        // Notifications don't get responses
        if ($id === null) {
            return new \WP_REST_Response(null, 202);
        }

        return $this->json_response($result, 200);
    }

    /**
     * Handle DELETE - Terminate session
     * Per MCP spec: Client SHOULD send DELETE to explicitly terminate session
     */
    private function handle_delete_session($request) {
        // Authenticate before allowing session termination.
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

        // Validate session ID format
        if (!$this->validate_session_id_format($session_id)) {
            return $this->json_response([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid session ID format',
                ],
            ], 400);
        }

        // Delete the session from storage
        $this->delete_session($session_id);

        // Return success
        $response = new \WP_REST_Response(null, 200);
        $response->header('Access-Control-Allow-Origin', '*');
        return $response;
    }

    /**
     * Return the fingerprint that binds this request's session to its
     * authenticated identity. Populated by validate_bearer_token /
     * validate_api_key_value, both of which run before this is read
     * (validate_auth is the first gate in handle_post_message).
     *
     * The $request argument is kept for API stability — the fingerprint is
     * now derived from the *validated* credential rather than the raw header
     * so a rotating OAuth access token still produces the same fingerprint.
     *
     * @param \WP_REST_Request $request The request object (unused; retained for signature stability).
     * @return string SHA-256 hash bound to stable auth identity, or '' if not authenticated.
     */
    private function build_auth_fingerprint($request) {
        unset($request); // Retained in signature; state comes from validate_auth().
        return $this->request_auth_fingerprint;
    }

    /**
     * Generate cryptographically secure session ID
     */
    private function generate_session_id() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create JSON response with proper headers.
     *
     * Cache-Control: no-store is mandatory on every MCP response. Without it,
     * edge caches (CDN, host-level fastcgi cache, generic intermediaries)
     * key on URL alone and serve a stale auth-error response regardless of
     * whether the Authorization / MMCP-Key header is present on
     * the second request. This is the same class of cache-poisoning bug
     * that gates every authenticated JSON-RPC endpoint.
     */
    private function json_response($data, $status = 200) {
        $response = new \WP_REST_Response($data, $status);
        $response->header('Content-Type', 'application/json');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Expose-Headers', 'Mcp-Session-Id');
        return $response;
    }

    /**
     * Process JSON-RPC method and return response object
     */
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
                        ],
                    ],
                ];

            case 'notifications/initialized':
            case 'initialized':
                return null; // No response for notifications

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
                    'result' => ['resources' => []],
                ];

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
            // \Throwable, not \Exception: a PHP Error (missing class, type
            // error, call to an undefined method) is not an Exception, so an
            // \Exception-only catch lets it escape as a fatal. On this route a
            // fatal means the client gets a truncated body or a 500 with no
            // JSON-RPC envelope at all, instead of an error result it can read
            // and act on — and nothing reaches the activity log, so the failure
            // is invisible to the admin afterwards. Caught here because a
            // missing autoloaded class in a subsystem is exactly the shape of
            // bug that produces an Error rather than an Exception.
            //
            // log_tool_call() already types its $exception param against
            // \Throwable (see the error_class field), so the logging side has
            // always expected this.
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

    /**
     * Log an MCP tool call to wp_more_mcp_logs.
     *
     * STRICT SAFELIST: logs the tool name and the KEYS of the argument array,
     * but NEVER the argument VALUES. Tool arguments can contain arbitrary
     * customer data (post content, search queries with PII, user-supplied
     * credentials passed through, etc.) — keys alone tell us "what was called"
     * without leaking "what data." Error messages from the tool dispatcher are
     * our own strings, safe to log.
     *
     * Closes an observability gap where the modern /mcp endpoint would
     * otherwise produce zero Activity Log entries even when actively serving
     * tool calls — making admins think the connection was broken.
     */
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct insert to the logs table.
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

        /**
         * Fires after every MCP tool invocation. Surfaces inbound MCP traffic
         * to observability plugins so they can
         * record + classify the calling agent in real-time.
         *
         * @param string $tool_name     The MCP tool that was called.
         * @param string $status        'success' | 'error'
         * @param string $error_message Empty string on success; the dispatcher
         *                              error message on failure.
         */
        do_action('more_mcp_tool_called', (string) $tool_name, (string) $status, (string) ($error_message ?? ''));

        /**
         * Fires after every MCP tool invocation with an enriched context payload.
         * Additive to `more_mcp_tool_called` — that hook stays byte-compatible
         * for subscribers on older signatures. New subscribers should prefer
         * this hook for richer observability + risk-classification context.
         *
         * The payload NEVER contains raw argument values or raw result content —
         * `tool_args_hash` (SHA-256 of sanitized args) enables dedupe / replay
         * detection without leaking argument data; `response_size_bytes` is a
         * length only, not a body.
         *
         * @param string $tool_name The MCP tool that was called.
         * @param array  $context   Enriched payload:
         *                          - status: 'success' | 'error'
         *                          - error_message: string
         *                          - error_class: string|null — exception class on failure
         *                          - latency_ms: int
         *                          - response_size_bytes: int
         *                          - tool_args_hash: string — sha256 hex of sanitized args
         *                          - arg_keys: string[] — argument keys (no values)
         *                          - is_destructive: bool — tool name matches destructive allowlist
         */
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

    /**
     * Static allowlist of tool names + name-prefix patterns that are considered
     * destructive for observability / approval-workflow classification.
     *
     * Not a security boundary — capability checks in the tool handlers own that.
     * Purpose is to give downstream subscribers (approval
     * workflow, audit dashboards) a one-bit signal to distinguish read tools
     * from tools that mutate or remove state.
     *
     * Additions welcome as new destructive surfaces ship.
     */
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
            // Lifecycle: these change the code running on the site, which is the
            // highest-impact class of write this plugin exposes. wp_delete_plugin
            // matches the wp_delete_ prefix already, but is listed explicitly so
            // the set reads completely at a glance.
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

    /**
     * Public accessor for the tool registry. Used by the Abilities API Registrar
     * to walk the full tool list without duplicating the tool definitions.
     */
    public function get_all_tools(): array {
        return $this->get_tools();
    }

    /**
     * Public entry point for tool invocation. Used by the Abilities API Registrar
     * to route ability calls through the same handler stack + per-tool capability
     * gates as MCP + REST calls.
     */
    public function invoke( string $name, array $args ) {
        return $this->execute_tool( $name, $args );
    }

    private function execute_tool($name, $args) {
        switch ($name) {
            // ==================== POSTS ====================
            case 'wp_get_posts':
                // Per-tool capability gate. Require `read` to call at all;
                // restricted post statuses (draft / private / trash / etc.)
                // require read_private_posts on the target post type. Without
                // these gates, a Subscriber-level OAuth token could pass a
                // non-public status and receive admin-owned content.
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list posts.');
                }
                $query_args = [
                    'numberposts' => min(intval($args['per_page'] ?? 10), 100),
                    's' => sanitize_text_field($args['search'] ?? ''),
                ];
                if (!empty($args['post_type'])) {
                    $pt = sanitize_text_field($args['post_type']);
                    $pto = get_post_type_object($pt);
                    if (!$pto || !$pto->public) throw new \Exception('Invalid or non-public post type: ' . esc_html($pt));
                    $query_args['post_type'] = $pt;
                }
                if (!empty($args['status'])) {
                    $requested_status = sanitize_text_field($args['status']);
                    // Allowlist of public WP post statuses (defaults to ['publish'];
                    // honors any custom statuses registered as public). Anything
                    // else — including 'any', 'private', 'draft', 'pending',
                    // 'future', 'trash', unknown values, or typos — requires
                    // read_private_posts for the relevant post type. Fail closed
                    // on unexpected values. Denylists are the wrong shape here:
                    // `status=any` and typo'd status names slip past denylists
                    // but are caught cleanly by the public-status allowlist.
                    $public_statuses = get_post_stati(['public' => true]);
                    if (!in_array($requested_status, $public_statuses, true)) {
                        $pto_for_caps = !empty($args['post_type']) ? get_post_type_object(sanitize_text_field($args['post_type'])) : get_post_type_object('post');
                        $needed_cap = $pto_for_caps && !empty($pto_for_caps->cap->read_private_posts)
                            ? $pto_for_caps->cap->read_private_posts
                            : 'read_private_posts';
                        if (!current_user_can($needed_cap)) {
                            throw new \Exception('You do not have permission to list ' . esc_html($requested_status) . ' posts.');
                        }
                    }
                    $query_args['post_status'] = $requested_status;
                }
                $posts = get_posts($query_args);
                return array_map(function($p) {
                    return [
                        'id' => $p->ID,
                        'title' => $p->post_title,
                        'excerpt' => wp_trim_words($p->post_content, 50),
                        'status' => $p->post_status,
                        'type' => $p->post_type,
                        'url' => get_permalink($p),
                        'date' => $p->post_date,
                    ];
                }, $posts);

            case 'wp_get_post':
                $post = get_post(self::resolve_post_id_arg($args));
                if (!$post) throw new \Exception('Post not found');
                // per-post read check. read_post via map_meta_cap
                // resolves to read_private_posts for non-public statuses.
                if (!current_user_can('read_post', $post->ID)) {
                    throw new \Exception('You do not have permission to read this post.');
                }
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'url' => get_permalink($post),
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'author' => get_the_author_meta('display_name', $post->post_author),
                ];

            case 'wp_create_post':
                $post_type = sanitize_text_field($args['post_type'] ?? 'post');
                $pto = get_post_type_object($post_type);
                if (!$pto || !$pto->public) throw new \Exception('Invalid or non-public post type: ' . esc_html($post_type));
                // Per-post-type edit + publish capability gate. Without it, a
                // Subscriber-level OAuth token could create-as-self at
                // status=publish. The per-PT cap object maps `edit_posts` to
                // the correct cap for custom post types (e.g. `edit_pages`).
                $create_cap = !empty($pto->cap->edit_posts) ? $pto->cap->edit_posts : 'edit_posts';
                if (!current_user_can($create_cap)) {
                    throw new \Exception('You do not have permission to create ' . esc_html($post_type) . ' posts.');
                }
                $requested_status = isset($args['status']) ? sanitize_text_field($args['status']) : 'draft';
                // publish_posts cap now gates future + private in
                // addition to publish. When the enum expanded from
                // ['publish', 'draft'] to include future/pending/private the
                // pre-existing 'publish' check needed matching coverage: WP
                // core silently downgrades unauthorized future/private to
                // pending, which would surface as a confusing "why did my
                // scheduled post become pending" bug rather than a clear
                // permission error. pending stays uncapped — it's just an
                // unpublished proposal, no publish-tier trust required.
                if (in_array($requested_status, ['publish', 'future', 'private'], true)) {
                    $publish_cap = !empty($pto->cap->publish_posts) ? $pto->cap->publish_posts : 'publish_posts';
                    if (!current_user_can($publish_cap)) {
                        throw new \Exception('You do not have permission to publish ' . esc_html($post_type) . ' posts.');
                    }
                }
                // Pre-validate featured_media so we don't create an orphan post if the ID is bad.
                if (isset($args['featured_media']) && intval($args['featured_media']) > 0) {
                    $fm = get_post(intval($args['featured_media']));
                    if (!$fm || $fm->post_type !== 'attachment') throw new \Exception('featured_media attachment not found.');
                }
                // Pre-validate post_author so we don't create a post owned by a non-existent user.
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    if (!get_userdata(intval($args['post_author']))) {
                        throw new \Exception('post_author user ID not found.');
                    }
                }
                // Two-part shape required for Gutenberg block JSON round-trip.
                // (1) Do NOT wrap in wp_kses_post() here: WP's own content_save_pre
                //     filter inside wp_insert_post() runs wp_filter_post_kses for
                //     callers without `unfiltered_html` and is block-aware;
                //     pre-calling wp_kses_post() HTML-encodes block delimiters.
                // (2) wp_slash() the content: wp_insert_post() runs wp_unslash()
                //     internally, which would otherwise strip the literal
                //     backslashes inside escape sequences (`\n`, `&`) that
                //     per-block `style.css` depends on.
                // status allowlist expanded to match schema enum.
                // future/pending/private are all standard WP statuses that
                // wp_insert_post handles natively. future requires post_date to
                // be in the future or WP silently downgrades to publish with
                // that backdate — same behavior as wp-admin scheduling.
                $post_data = [
                    'post_title' => sanitize_text_field($args['title']),
                    'post_content' => wp_slash($args['content']),
                    'post_status' => in_array($args['status'] ?? 'draft', ['publish', 'draft', 'future', 'pending', 'private']) ? $args['status'] : 'draft',
                    'post_type' => $post_type,
                ];
                // wp_kses_post matches what wp_insert_post's excerpt_save_pre
                // filter would apply; sanitize_text_field flattened any <p>/<a>/<strong>
                // formatting that legitimately renders on category archives + RSS feeds.
                if (!empty($args['excerpt'])) $post_data['post_excerpt'] = wp_kses_post($args['excerpt']);
                if (!empty($args['categories'])) $post_data['post_category'] = array_map('intval', $args['categories']);
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    $post_data['post_author'] = intval($args['post_author']);
                }
                // scheduling support. Parse ISO-8601 in site TZ, derive
                // GMT from the same timestamp so the two fields never disagree.
                if (!empty($args['date'])) {
                    $ts = strtotime((string) $args['date']);
                    if (false === $ts) {
                        throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
                    }
                    $post_data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
                    $post_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
                }
                $post_id = wp_insert_post($post_data);
                if (is_wp_error($post_id)) throw new \Exception(esc_html($post_id->get_error_message()));
                if (isset($args['featured_media'])) {
                    $this->apply_featured_media($post_id, intval($args['featured_media']));
                }
                return ['id' => $post_id, 'message' => ucfirst($post_type) . ' created successfully', 'url' => get_permalink($post_id)];

            case 'wp_update_post':
                $post_id = self::resolve_post_id_arg($args);
                // object-level edit_post resolves to edit_others_posts
                // when the target isn't owned by the current user, and to the
                // PT-specific cap (edit_page etc.) automatically via map_meta_cap.
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit this post.');
                }
                // Pre-validate featured_media before mutating the post.
                if (isset($args['featured_media']) && intval($args['featured_media']) > 0) {
                    $fm = get_post(intval($args['featured_media']));
                    if (!$fm || $fm->post_type !== 'attachment') throw new \Exception('featured_media attachment not found.');
                }
                // Pre-validate post_author before mutating the post.
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    if (!get_userdata(intval($args['post_author']))) {
                        throw new \Exception('post_author user ID not found.');
                    }
                }
                // Pre-validate post_parent: fail loudly on unknown parent rather
                // than let WP silently coerce to 0.
                if (isset($args['post_parent']) && intval($args['post_parent']) > 0) {
                    if (!get_post(intval($args['post_parent']))) {
                        throw new \Exception('post_parent post ID not found.');
                    }
                }
                // Validate comment_status / ping_status enums up-front.
                if (array_key_exists('comment_status', $args) && !in_array($args['comment_status'], ['open', 'closed'], true)) {
                    throw new \Exception('comment_status must be "open" or "closed".');
                }
                if (array_key_exists('ping_status', $args) && !in_array($args['ping_status'], ['open', 'closed'], true)) {
                    throw new \Exception('ping_status must be "open" or "closed".');
                }
                $data = ['ID' => $post_id];
                // empty-string-as-omit on text fields. AI drivers
                // sometimes template-fill optional text args with "" instead
                // of omitting; treating "" as "preserve" prevents silent
                // destructive writes (blanked post body, title, excerpt) on
                // what reads as a partial update. To explicitly clear a
                // field, edit via wp-admin or future dedicated clear tool.
                if (isset($args['title']) && $args['title'] !== '') $data['post_title'] = sanitize_text_field($args['title']);
                // See wp_create_post above for the wp_slash + no-wp_kses_post rationale.
                if (isset($args['content']) && $args['content'] !== '') $data['post_content'] = wp_slash($args['content']);
                if (isset($args['status'])) $data['post_status'] = sanitize_text_field($args['status']);
                // see wp_create_post above: preserve safe HTML in excerpts.
                if (isset($args['excerpt']) && $args['excerpt'] !== '') $data['post_excerpt'] = wp_kses_post($args['excerpt']);
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    $data['post_author'] = intval($args['post_author']);
                }
                if (array_key_exists('menu_order', $args)) $data['menu_order'] = intval($args['menu_order']);
                if (array_key_exists('post_parent', $args)) $data['post_parent'] = intval($args['post_parent']);
                if (array_key_exists('password', $args)) $data['post_password'] = (string) $args['password'];
                if (array_key_exists('comment_status', $args)) $data['comment_status'] = $args['comment_status'];
                if (array_key_exists('ping_status', $args)) $data['ping_status'] = $args['ping_status'];
                // scheduling support on the update path. edit_date=true
                // is REQUIRED on wp_update_post() to actually change post_date on
                // an existing post; without it WP silently ignores post_date args.
                if (!empty($args['date'])) {
                    $ts = strtotime((string) $args['date']);
                    if (false === $ts) {
                        throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
                    }
                    $data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
                    $data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
                    $data['edit_date'] = true;
                }
                $result = wp_update_post($data);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                if (isset($args['featured_media'])) {
                    $this->apply_featured_media($post_id, intval($args['featured_media']));
                }
                return self::build_update_response($post_id, $args, $data, 'Post updated successfully');

            case 'wp_replace_in_post':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                return self::replace_in_post_content($post_id, $args, 'post');

            case 'wp_delete_post':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0) throw new \Exception('Post not found.');
                // cap check BEFORE existence check so an unauthorized
                // caller probing nonexistent IDs gets "permission" rather than
                // "not found" (don't leak existence to non-deleters; audit
                // expects permission-error response regardless of target ID).
                // object-level delete_post via map_meta_cap resolves
                // to delete_others_posts when the target isn't owned by the
                // current user.
                if (!current_user_can('delete_post', $post_id)) {
                    throw new \Exception('You do not have permission to delete this post.');
                }
                if (!get_post($post_id)) throw new \Exception('Post not found.');
                $force = !empty($args['force']);
                $result = wp_delete_post($post_id, $force);
                if (!$result) throw new \Exception('Failed to delete post');
                return ['message' => $force ? 'Post permanently deleted' : 'Post moved to trash'];

            case 'wp_count_posts':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to view post counts.');
                }
                $type = sanitize_text_field($args['post_type'] ?? 'post');
                $counts = wp_count_posts($type);
                return (array) $counts;

            case 'wp_get_post_types':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list post types.');
                }
                $types = get_post_types(['public' => true], 'objects');
                return array_values(array_map(function($pt) {
                    return [
                        'name' => $pt->name,
                        'label' => $pt->label,
                        'description' => $pt->description,
                        'hierarchical' => $pt->hierarchical,
                        'has_archive' => (bool) $pt->has_archive,
                        'supports' => array_keys(array_filter(get_all_post_type_supports($pt->name))),
                    ];
                }, $types));

            case 'wp_get_taxonomies':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list taxonomies.');
                }
                $taxonomies = get_taxonomies(['public' => true], 'objects');
                return array_values(array_map(function($tax) {
                    // `slug` added as a clearer alias for the taxonomy
                    // identifier. WP_Taxonomy uses `name` for the slug for
                    // historical reasons, which surprises AI agents that
                    // expect a `slug` field on something called a "taxonomy".
                    // Both fields hold the same value; keep `name` for
                    // backward compat with anything already using it.
                    return [
                        'slug'         => $tax->name,
                        'name'         => $tax->name,
                        'label'        => $tax->label,
                        'description'  => $tax->description,
                        'hierarchical' => (bool) $tax->hierarchical,
                        'object_type'  => array_values((array) $tax->object_type),
                        'show_in_rest' => (bool) $tax->show_in_rest,
                    ];
                }, $taxonomies));

            // ==================== PAGES ====================
            case 'wp_get_pages':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list pages.');
                }
                $page_args = ['number' => min(intval($args['per_page'] ?? 10), 100)];
                if (!empty($args['parent'])) $page_args['parent'] = intval($args['parent']);
                $pages = get_pages($page_args);
                return array_map(function($p) {
                    return [
                        'id' => $p->ID,
                        'title' => $p->post_title,
                        'url' => get_permalink($p),
                        'status' => $p->post_status,
                        'parent' => $p->post_parent,
                    ];
                }, $pages);

            case 'wp_get_page':
                $page = get_post(self::resolve_post_id_arg($args));
                if (!$page || $page->post_type !== 'page') throw new \Exception('Page not found');
                if (!current_user_can('read_post', $page->ID)) {
                    throw new \Exception('You do not have permission to read this page.');
                }
                return [
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'content' => $page->post_content,
                    'status' => $page->post_status,
                    'url' => get_permalink($page),
                    'parent' => $page->post_parent,
                ];

            case 'wp_create_page':
                if (!current_user_can('edit_pages')) {
                    throw new \Exception('You do not have permission to create pages.');
                }
                // status allowlist expanded to match schema enum (same as wp_create_post).
                $page_status = in_array($args['status'] ?? 'draft', ['publish', 'draft', 'future', 'pending', 'private']) ? $args['status'] : 'draft';
                // publish_pages cap gates future + private too. See wp_create_post for rationale.
                if (in_array($page_status, ['publish', 'future', 'private'], true) && !current_user_can('publish_pages')) {
                    throw new \Exception('You do not have permission to publish pages.');
                }
                // See wp_create_post above for the wp_slash + no-wp_kses_post rationale.
                $page_data = [
                    'post_title' => sanitize_text_field($args['title']),
                    'post_content' => wp_slash($args['content']),
                    'post_status' => $page_status,
                    'post_type' => 'page',
                ];
                if (!empty($args['parent'])) $page_data['post_parent'] = intval($args['parent']);
                // scheduling support. See wp_create_post handler for rationale.
                if (!empty($args['date'])) {
                    $ts = strtotime((string) $args['date']);
                    if (false === $ts) {
                        throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
                    }
                    $page_data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
                    $page_data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
                }
                $page_id = wp_insert_post($page_data);
                if (is_wp_error($page_id)) throw new \Exception(esc_html($page_id->get_error_message()));
                return ['id' => $page_id, 'message' => 'Page created successfully', 'url' => get_permalink($page_id)];

            case 'wp_update_page':
                $page_id = self::resolve_post_id_arg($args);
                $existing_page = $page_id > 0 ? get_post($page_id) : null;
                if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
                if (!current_user_can('edit_post', $page_id)) {
                    throw new \Exception('You do not have permission to edit this page.');
                }
                // pre-validate post_author (new field).
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    if (!get_userdata(intval($args['post_author']))) {
                        throw new \Exception('post_author user ID not found.');
                    }
                }
                // pre-validate post_parent (new field).
                if (isset($args['post_parent']) && intval($args['post_parent']) > 0) {
                    if (!get_post(intval($args['post_parent']))) {
                        throw new \Exception('post_parent page ID not found.');
                    }
                }
                $data = ['ID' => $page_id];
                // see wp_update_post: "" preserves existing value.
                if (isset($args['title']) && $args['title'] !== '') $data['post_title'] = sanitize_text_field($args['title']);
                // See wp_create_post above for the wp_slash + no-wp_kses_post rationale.
                if (isset($args['content']) && $args['content'] !== '') $data['post_content'] = wp_slash($args['content']);
                if (isset($args['status'])) $data['post_status'] = sanitize_text_field($args['status']);
                if (isset($args['excerpt']) && $args['excerpt'] !== '') $data['post_excerpt'] = wp_kses_post($args['excerpt']);
                if (isset($args['post_author']) && intval($args['post_author']) > 0) {
                    $data['post_author'] = intval($args['post_author']);
                }
                if (array_key_exists('menu_order', $args)) $data['menu_order'] = intval($args['menu_order']);
                if (array_key_exists('post_parent', $args)) $data['post_parent'] = intval($args['post_parent']);
                if (array_key_exists('password', $args)) $data['post_password'] = (string) $args['password'];
                // scheduling / backdating support on the update path. See wp_update_post handler.
                if (!empty($args['date'])) {
                    $ts = strtotime((string) $args['date']);
                    if (false === $ts) {
                        throw new \Exception('Invalid date: could not parse "' . esc_html((string) $args['date']) . '" as ISO 8601.');
                    }
                    $data['post_date'] = wp_date('Y-m-d H:i:s', $ts);
                    $data['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
                    $data['edit_date'] = true;
                }
                $result = wp_update_post($data);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return self::build_update_response($page_id, $args, $data, 'Page updated successfully');

            case 'wp_replace_in_page':
                $page_id = self::resolve_post_id_arg($args);
                $existing_page = $page_id > 0 ? get_post($page_id) : null;
                if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
                return self::replace_in_post_content($page_id, $args, 'page');

            case 'wp_delete_page':
                $page_id = self::resolve_post_id_arg($args);
                $existing_page = $page_id > 0 ? get_post($page_id) : null;
                if (!$existing_page || $existing_page->post_type !== 'page') throw new \Exception('Page not found.');
                if (!current_user_can('delete_post', $page_id)) {
                    throw new \Exception('You do not have permission to delete this page.');
                }
                $force = !empty($args['force']);
                $result = wp_delete_post($page_id, $force);
                if (!$result) throw new \Exception('Failed to delete page');
                return ['message' => $force ? 'Page permanently deleted' : 'Page moved to trash'];

            // ==================== MEDIA ====================
            case 'wp_get_media':
                if (!current_user_can('upload_files')) {
                    throw new \Exception('You do not have permission to view the media library.');
                }
                $media_per_page = max(1, min(intval($args['per_page'] ?? 10), 100));
                $media_page     = max(1, intval($args['page'] ?? 1));
                $media_args = [
                    'post_type'   => 'attachment',
                    'post_status' => 'inherit',
                    // get_posts() maps numberposts -> posts_per_page but has no
                    // 'page' concept, so paging has to be an explicit offset.
                    // Without this, every page returned the same first N rows and
                    // a library larger than per_page was unreachable (GitHub #4).
                    'numberposts' => $media_per_page,
                    'offset'      => ($media_page - 1) * $media_per_page,
                ];
                if (!empty($args['mime_type'])) $media_args['post_mime_type'] = sanitize_text_field($args['mime_type']);
                $media = get_posts($media_args);

                // Total for the same filter, so a caller knows when to stop paging
                // instead of comparing consecutive pages for equality.
                $media_total_ids = get_posts(array_merge($media_args, [
                    'numberposts' => -1,
                    'offset'      => 0,
                    'fields'      => 'ids',
                ]));
                $media_total = is_array($media_total_ids) ? count($media_total_ids) : 0;

                $media_rows = array_map(function($m) {
                    $alt = get_post_meta($m->ID, '_wp_attachment_image_alt', true);
                    return [
                        'id'        => $m->ID,
                        'title'     => $m->post_title,
                        'url'       => wp_get_attachment_url($m->ID),
                        'mime_type' => $m->post_mime_type,
                        'date'      => $m->post_date,
                        // Both keys carry the same value. The tool description
                        // documented alt_text while the payload only had alt,
                        // which forced an N+1 of wp_get_media_item calls just to
                        // find images missing alt text (GitHub #4). Emitting both
                        // keeps existing callers working and makes the documented
                        // name real.
                        'alt'       => is_string($alt) ? $alt : '',
                        'alt_text'  => is_string($alt) ? $alt : '',
                    ];
                }, $media);

                return [
                    'total'    => $media_total,
                    'page'     => $media_page,
                    'per_page' => $media_per_page,
                    'pages'    => (int) ceil($media_total / $media_per_page),
                    'returned' => count($media_rows),
                    'has_more' => (($media_page - 1) * $media_per_page + count($media_rows)) < $media_total,
                    'media'    => $media_rows,
                ];

            case 'wp_get_media_item':
                $media = get_post(self::resolve_post_id_arg($args));
                if (!$media || $media->post_type !== 'attachment') throw new \Exception('Media not found');
                if (!current_user_can('read_post', $media->ID)) {
                    throw new \Exception('You do not have permission to read this media item.');
                }
                return [
                    'id' => $media->ID,
                    'title' => $media->post_title,
                    'url' => wp_get_attachment_url($media->ID),
                    'mime_type' => $media->post_mime_type,
                    'alt' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
                    'caption' => $media->post_excerpt,
                    'description' => $media->post_content,
                ];

            case 'wp_upload_media_from_url':
                if (!current_user_can('upload_files')) {
                    throw new \Exception('You do not have permission to upload files.');
                }
                $url = isset($args['url']) ? esc_url_raw(trim($args['url'])) : '';
                if (empty($url)) throw new \Exception('A url is required.');
                $attachment_id = $this->sideload_image_from_url(
                    $url,
                    isset($args['filename']) ? sanitize_file_name($args['filename']) : '',
                    isset($args['title']) ? sanitize_text_field($args['title']) : '',
                    isset($args['caption']) ? sanitize_text_field($args['caption']) : '',
                    isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
                );
                return [
                    'id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'message' => 'Image uploaded to media library.',
                ];

            case 'wp_upload_media':
                if (!current_user_can('upload_files')) {
                    throw new \Exception('You do not have permission to upload files.');
                }
                $filename = isset($args['filename']) ? sanitize_file_name($args['filename']) : '';
                $b64      = isset($args['content_base64']) ? (string) $args['content_base64'] : '';
                if (empty($filename) || empty($b64)) {
                    throw new \Exception('filename and content_base64 are required.');
                }
                // Strip data-URL prefix if present.
                if (strpos($b64, 'base64,') !== false) {
                    $b64 = substr($b64, strpos($b64, 'base64,') + 7);
                }
                $bytes = base64_decode($b64, true);
                if ($bytes === false) throw new \Exception('content_base64 is not valid base64.');
                $attachment_id = $this->sideload_image_from_bytes(
                    $bytes,
                    $filename,
                    isset($args['title']) ? sanitize_text_field($args['title']) : '',
                    isset($args['caption']) ? sanitize_text_field($args['caption']) : '',
                    isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
                );
                return [
                    'id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'message' => 'Image uploaded to media library.',
                ];

            case 'wp_set_featured_image':
                $post_id = intval($args['post_id'] ?? 0);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit this post.');
                }
                // Smart dispatcher: image_url takes precedence when present.
                if (!empty($args['image_url'])) {
                    if (!current_user_can('upload_files')) {
                        throw new \Exception('You do not have permission to upload files.');
                    }
                    $media_id = $this->sideload_image_from_url(
                        esc_url_raw(trim($args['image_url'])),
                        '',
                        '',
                        '',
                        isset($args['alt_text']) ? sanitize_text_field($args['alt_text']) : ''
                    );
                } else {
                    $media_id = isset($args['media_id']) ? intval($args['media_id']) : -1;
                    if ($media_id < 0) throw new \Exception('Provide either media_id or image_url.');
                }
                $this->apply_featured_media($post_id, $media_id);
                return [
                    'post_id'  => $post_id,
                    'media_id' => $media_id,
                    'url'      => $media_id > 0 ? wp_get_attachment_url($media_id) : null,
                    'message'  => $media_id > 0 ? 'Featured image set.' : 'Featured image removed.',
                ];

            case 'wp_update_media':
                $media_id = self::resolve_post_id_arg($args);
                $media = $media_id > 0 ? get_post($media_id) : null;
                if (!$media || $media->post_type !== 'attachment') throw new \Exception('Media not found.');
                if (!current_user_can('edit_post', $media_id)) {
                    throw new \Exception('You do not have permission to edit this media item.');
                }
                $update = ['ID' => $media_id];
                // see wp_update_post: "" preserves existing value.
                if (isset($args['title']) && $args['title'] !== '')       $update['post_title']   = sanitize_text_field($args['title']);
                if (isset($args['caption']) && $args['caption'] !== '')     $update['post_excerpt'] = sanitize_text_field($args['caption']);
                if (isset($args['description']) && $args['description'] !== '') $update['post_content'] = wp_kses_post($args['description']);
                if (count($update) > 1) {
                    $res = wp_update_post($update, true);
                    if (is_wp_error($res)) throw new \Exception(esc_html($res->get_error_message()));
                }
                if (isset($args['alt_text']) && $args['alt_text'] !== '') {
                    update_post_meta($media_id, '_wp_attachment_image_alt', sanitize_text_field($args['alt_text']));
                }
                return ['id' => $media_id, 'message' => 'Media updated successfully'];

            case 'wp_delete_media':
                $media_id = self::resolve_post_id_arg($args);
                $existing_media = $media_id > 0 ? get_post($media_id) : null;
                if (!$existing_media || $existing_media->post_type !== 'attachment') throw new \Exception('Media not found.');
                if (!current_user_can('delete_post', $media_id)) {
                    throw new \Exception('You do not have permission to delete this media item.');
                }
                $force = !empty($args['force']);
                $result = wp_delete_attachment($media_id, $force);
                if (!$result) throw new \Exception('Failed to delete media');
                return ['message' => 'Media deleted successfully'];

            case 'wp_count_media':
                if (!current_user_can('upload_files')) {
                    throw new \Exception('You do not have permission to view media library counts.');
                }
                $counts = wp_count_attachments();
                return (array) $counts;

            // ==================== CATEGORIES & TAGS ====================
            case 'wp_get_categories':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list categories.');
                }
                $cats = get_categories(['number' => min(intval($args['per_page'] ?? 100), 100), 'hide_empty' => false]);
                return array_map(function($c) {
                    return ['id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => $c->count, 'parent' => $c->parent];
                }, $cats);

            case 'wp_get_tags':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list tags.');
                }
                $tags = get_tags(['number' => min(intval($args['per_page'] ?? 100), 100), 'hide_empty' => false]);
                return array_map(function($t) {
                    return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count];
                }, $tags ?: []);

            case 'wp_create_term':
                $taxonomy = sanitize_text_field($args['taxonomy']);
                if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
                $tax_obj = get_taxonomy($taxonomy);
                // per-taxonomy edit_terms cap (resolves to
                // manage_categories for the category taxonomy, manage_post_tags
                // for tags, custom caps for custom taxonomies).
                $edit_terms_cap = $tax_obj && !empty($tax_obj->cap->edit_terms) ? $tax_obj->cap->edit_terms : 'manage_categories';
                if (!current_user_can($edit_terms_cap)) {
                    throw new \Exception('You do not have permission to create terms in ' . esc_html($taxonomy) . '.');
                }
                $term_args = [];
                // wp_kses_post preserves the same safe HTML allow-list WP admin
                // permits in term descriptions; sanitize_text_field flattened links/formatting
                // that render on category-archive pages under WC / Yoast / theme templates.
                if (!empty($args['description'])) $term_args['description'] = wp_kses_post($args['description']);
                if (!empty($args['slug'])) $term_args['slug'] = sanitize_title($args['slug']);
                if (!empty($args['parent']) && $tax_obj && $tax_obj->hierarchical) $term_args['parent'] = intval($args['parent']);
                $result = wp_insert_term(sanitize_text_field($args['name']), $taxonomy, $term_args);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return ['id' => $result['term_id'], 'taxonomy' => $taxonomy, 'message' => 'Term created successfully'];

            case 'wp_update_term':
                $taxonomy = sanitize_text_field($args['taxonomy']);
                if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
                $term_id = intval($args['id']);
                if (!get_term($term_id, $taxonomy)) throw new \Exception('Term not found in taxonomy ' . esc_html($taxonomy));
                // object-level edit_term cap.
                if (!current_user_can('edit_term', $term_id)) {
                    throw new \Exception('You do not have permission to edit this term.');
                }
                $update_args = [];
                // see wp_update_post: "" preserves existing value.
                if (isset($args['name']) && $args['name'] !== '') $update_args['name'] = sanitize_text_field($args['name']);
                if (isset($args['slug']) && $args['slug'] !== '') $update_args['slug'] = sanitize_title($args['slug']);
                // see wp_create_term above: preserve safe HTML in descriptions.
                if (isset($args['description']) && $args['description'] !== '') $update_args['description'] = wp_kses_post($args['description']);
                if (isset($args['parent'])) {
                    $tax_obj = get_taxonomy($taxonomy);
                    if ($tax_obj && $tax_obj->hierarchical) $update_args['parent'] = intval($args['parent']);
                }
                if (empty($update_args)) throw new \Exception('No update fields provided. Pass at least one of: name, slug, description, parent.');
                $result = wp_update_term($term_id, $taxonomy, $update_args);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return ['id' => $term_id, 'taxonomy' => $taxonomy, 'message' => 'Term updated successfully'];

            case 'wp_delete_term':
                $taxonomy = sanitize_text_field($args['taxonomy']);
                if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
                $term_id = intval($args['id']);
                if (!get_term($term_id, $taxonomy)) throw new \Exception('Term not found in taxonomy ' . esc_html($taxonomy));
                // object-level delete_term cap.
                if (!current_user_can('delete_term', $term_id)) {
                    throw new \Exception('You do not have permission to delete this term.');
                }
                $result = wp_delete_term($term_id, $taxonomy);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                if (!$result) throw new \Exception('Failed to delete term');
                return ['message' => 'Term deleted successfully'];

            case 'wp_get_terms':
                if (!current_user_can('edit_posts')) {
                    throw new \Exception('You do not have permission to list terms.');
                }
                $taxonomy = sanitize_text_field($args['taxonomy'] ?? '');
                if ($taxonomy === '') throw new \Exception('A taxonomy slug is required.');
                if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
                $per_page = isset($args['per_page']) ? max(1, min(intval($args['per_page']), 500)) : 100;
                $page     = isset($args['page']) ? max(1, intval($args['page'])) : 1;
                $offset   = ($page - 1) * $per_page;
                $get_args = [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => !empty($args['hide_empty']),
                    'number'     => $per_page,
                    'offset'     => $offset,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ];
                if (!empty($args['search'])) {
                    $get_args['search'] = sanitize_text_field($args['search']);
                }
                if (isset($args['parent'])) {
                    $get_args['parent'] = intval($args['parent']);
                }
                $terms = get_terms($get_args);
                if (is_wp_error($terms)) throw new \Exception(esc_html($terms->get_error_message()));
                $total = (int) wp_count_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => !empty($args['hide_empty']),
                ]);
                return [
                    'taxonomy'    => $taxonomy,
                    'page'        => $page,
                    'per_page'    => $per_page,
                    // INVARIANTS.md §8 compliance: list tools return
                    // total_count. Legacy 'total' kept alongside to avoid a
                    // breaking rename for existing callers.
                    'total'       => $total,
                    'total_count' => $total,
                    'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
                    'terms'       => array_map(function ($t) {
                        return [
                            'id'          => (int) $t->term_id,
                            'name'        => $t->name,
                            'slug'        => $t->slug,
                            'description' => $t->description,
                            'count'       => (int) $t->count,
                            'parent'      => (int) $t->parent,
                        ];
                    }, $terms),
                ];

            case 'wp_add_post_terms':
                $taxonomy = sanitize_text_field($args['taxonomy']);
                if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                // assigning terms to a post requires the post's own
                // edit cap (so a Subscriber can't tag an admin's draft).
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit this post.');
                }
                // Plus the per-taxonomy assign_terms cap.
                $tax_obj = get_taxonomy($taxonomy);
                $assign_cap = $tax_obj && !empty($tax_obj->cap->assign_terms) ? $tax_obj->cap->assign_terms : 'edit_posts';
                if (!current_user_can($assign_cap)) {
                    throw new \Exception('You do not have permission to assign terms in ' . esc_html($taxonomy) . '.');
                }
                $result = wp_set_post_terms($post_id, array_map('intval', $args['terms']), $taxonomy, true);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return ['message' => 'Terms added to post successfully'];

            case 'wp_count_terms':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to count terms.');
                }
                $taxonomy = sanitize_text_field($args['taxonomy'] ?? 'category');
                $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                return ['taxonomy' => $taxonomy, 'count' => $count];

            case 'wp_get_term_meta':
                $term_id = intval($args['term_id']);
                if (!get_term($term_id)) throw new \Exception('Term not found');
                if (!current_user_can('manage_categories')) {
                    throw new \Exception('You do not have permission to read term meta.');
                }
                // wrap return in a structured object for consistency
                // with wp_update_term_meta / wp_delete_term_meta which return
                // structured arrays. Single-key get returns {term_id, key,
                // value}; full-meta get returns {term_id, meta: {...}}.
                if (!empty($args['key'])) {
                    $key = sanitize_text_field($args['key']);
                    return [
                        'term_id' => $term_id,
                        'key'     => $key,
                        'value'   => get_term_meta($term_id, $key, true),
                    ];
                }
                return [
                    'term_id' => $term_id,
                    'meta'    => (array) get_term_meta($term_id),
                ];

            case 'wp_update_term_meta':
                $term_id = intval($args['term_id']);
                if (!get_term($term_id)) throw new \Exception('Term not found');
                if (!current_user_can('edit_term', $term_id)) {
                    throw new \Exception('You do not have permission to edit this term.');
                }
                if (!array_key_exists('value', $args)) {
                    throw new \Exception('A value is required.');
                }
                $meta_key   = sanitize_text_field($args['key']);
                $meta_value = self::filter_meta_value($args['value'], $meta_key, $term_id, 'wp_update_term_meta');
                $write      = self::write_meta_verified('term', $term_id, $meta_key, $meta_value);
                if (!$write['written']) throw new \Exception('Failed to update term meta');
                return array_merge([
                    'term_id' => $term_id,
                    'key'     => $meta_key,
                    'message' => 'Term meta updated',
                ], self::meta_write_report($write));

            case 'wp_delete_term_meta':
                $term_id = intval($args['term_id']);
                if (!get_term($term_id)) throw new \Exception('Term not found');
                if (!current_user_can('edit_term', $term_id)) {
                    throw new \Exception('You do not have permission to edit this term.');
                }
                $result = delete_term_meta($term_id, sanitize_text_field($args['key']));
                if (!$result) throw new \Exception('Failed to delete term meta (key may not exist)');
                return ['term_id' => $term_id, 'message' => 'Term meta deleted'];

            // ==================== COMMENTS ====================
            case 'wp_get_comments':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to list comments.');
                }
                $comment_args = ['number' => min(intval($args['per_page'] ?? 10), 100)];
                if (!empty($args['post_id'])) $comment_args['post_id'] = intval($args['post_id']);
                if (!empty($args['status'])) {
                    $requested_comment_status = sanitize_text_field($args['status']);
                    // Only 'approve' is public. Anything else ('all', 'hold',
                    // 'spam', 'trash', unknown values) requires moderate_comments.
                    // WP_Comment_Query with status=all returns hold/spam/trash
                    // too, so an allowlist here is the only shape that catches
                    // status=all without a special case.
                    if (!in_array($requested_comment_status, ['approve'], true)
                        && !current_user_can('moderate_comments')) {
                        throw new \Exception('You do not have permission to list ' . esc_html($requested_comment_status) . ' comments.');
                    }
                    $comment_args['status'] = $requested_comment_status;
                }
                $comments = get_comments($comment_args);
                return array_map(function($c) {
                    return [
                        'id' => $c->comment_ID,
                        'post_id' => $c->comment_post_ID,
                        'author' => $c->comment_author,
                        'content' => $c->comment_content,
                        'date' => $c->comment_date,
                        'status' => $c->comment_approved,
                    ];
                }, $comments);

            case 'wp_create_comment':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to create comments via the MCP API.');
                }
                $comment_post_id = intval($args['post_id']);
                $comment_target_post = $comment_post_id > 0 ? get_post($comment_post_id) : null;
                if (!$comment_target_post) throw new \Exception('Post not found.');
                // block commenting on closed/private posts unless the
                // user has the relevant edit cap on the target post.
                if ('open' !== $comment_target_post->comment_status
                    && !current_user_can('edit_post', $comment_post_id)) {
                    throw new \Exception('Comments are closed on this post.');
                }
                // wp_filter_kses uses WP's tight comment-form $allowedtags
                // (<a>, <strong>, <em>, <blockquote>, <code>, <cite>, <abbr>, <acronym>).
                // Matches what a user gets when submitting a comment through the standard
                // WP form; sanitize_text_field stripped all tags including the standard <a>.
                $comment_data = [
                    'comment_post_ID' => $comment_post_id,
                    'comment_content' => wp_filter_kses($args['content']),
                    'comment_author' => sanitize_text_field($args['author'] ?? 'Anonymous'),
                    'comment_author_email' => sanitize_email($args['author_email'] ?? ''),
                ];
                // Respect WordPress comment moderation settings
                $comment_data['comment_approved'] = wp_allow_comment($comment_data);
                $comment_id = wp_insert_comment($comment_data);
                if (!$comment_id) throw new \Exception('Failed to create comment');
                $status = $comment_data['comment_approved'] === 1 ? 'approved' : 'pending moderation';
                return ['id' => $comment_id, 'message' => 'Comment created (' . $status . ')'];

            case 'wp_delete_comment':
                $comment_id = intval($args['id']);
                if ($comment_id <= 0 || !get_comment($comment_id)) throw new \Exception('Comment not found.');
                // object-level edit_comment via map_meta_cap resolves
                // to moderate_comments for cross-author deletes.
                if (!current_user_can('edit_comment', $comment_id)) {
                    throw new \Exception('You do not have permission to delete this comment.');
                }
                $force = !empty($args['force']);
                $result = wp_delete_comment($comment_id, $force);
                if (!$result) throw new \Exception('Failed to delete comment');
                return ['message' => 'Comment deleted successfully'];

            case 'wp_get_pending_comments':
                if (!current_user_can('moderate_comments')) {
                    throw new \Exception('moderate_comments capability required.');
                }
                $limit = min(intval($args['limit'] ?? 20), 100);
                $get_args = ['status' => 'hold', 'number' => $limit];
                if (!empty($args['post_id'])) {
                    $get_args['post_id'] = intval($args['post_id']);
                }
                $comments = get_comments($get_args);
                return array_map(function($c) {
                    return [
                        'id' => (int) $c->comment_ID,
                        'post_id' => (int) $c->comment_post_ID,
                        'post_title' => get_the_title($c->comment_post_ID),
                        'author' => $c->comment_author,
                        'author_email_redacted' => $c->comment_author_email ? substr($c->comment_author_email, 0, 2) . '***@***' : '',
                        'content' => wp_strip_all_tags($c->comment_content),
                        'status' => 'pending',
                        'date' => $c->comment_date,
                    ];
                }, $comments);

            case 'wp_approve_comment':
                if (!current_user_can('moderate_comments')) {
                    throw new \Exception('moderate_comments capability required.');
                }
                $comment_id = intval($args['comment_id']);
                $result = wp_set_comment_status($comment_id, 'approve');
                if (!$result) throw new \Exception('Failed to approve comment.');
                return ['comment_id' => $comment_id, 'new_status' => 'approved'];

            case 'wp_spam_comment':
                if (!current_user_can('moderate_comments')) {
                    throw new \Exception('moderate_comments capability required.');
                }
                $comment_id = intval($args['comment_id']);
                $result = wp_set_comment_status($comment_id, 'spam');
                if (!$result) throw new \Exception('Failed to mark comment as spam.');
                return ['comment_id' => $comment_id, 'new_status' => 'spam'];

            case 'wp_trash_comment':
                if (!current_user_can('moderate_comments')) {
                    throw new \Exception('moderate_comments capability required.');
                }
                $comment_id = intval($args['comment_id']);
                $result = wp_trash_comment($comment_id);
                if (!$result) throw new \Exception('Failed to trash comment.');
                return ['comment_id' => $comment_id, 'new_status' => 'trash'];

            // ==================== USERS ====================
            case 'wp_get_users':
                // list_users is the cap WordPress core uses for the Users
                // admin screen and the WP REST users endpoint. Without this
                // gate a Subscriber-level OAuth token could enumerate every
                // account on the site.
                if (!current_user_can('list_users')) {
                    throw new \Exception('You do not have permission to list users.');
                }
                $user_args = ['number' => min(intval($args['per_page'] ?? 10), 100)];
                if (!empty($args['role'])) $user_args['role'] = sanitize_text_field($args['role']);
                $users = get_users($user_args);
                return array_map(function($u) {
                    return [
                        'id' => $u->ID,
                        'display_name' => $u->display_name,
                        'roles' => $u->roles,
                    ];
                }, $users);

            case 'wp_get_user':
                if (!current_user_can('list_users')) {
                    throw new \Exception('You do not have permission to view user accounts.');
                }
                $user = get_user_by('ID', intval($args['id']));
                if (!$user) throw new \Exception('User not found');
                return [
                    'id' => $user->ID,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles,
                    'registered' => $user->user_registered,
                ];

            // ==================== POST META ====================
            case 'wp_get_post_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                $key = !empty($args['key']) ? sanitize_text_field($args['key']) : '';
                // protected-meta gating. Mirrors WP core's
                // is_protected_meta() convention: underscore-prefixed keys
                // (_yoast_wpseo_*, _edit_lock, _wp_attached_file, ACF
                // internals, etc.) carry admin/SEO data and require
                // edit_post. Non-underscored keys keep read_post for
                // legitimate public-meta consumers. Empty-key requests
                // (return all meta) require edit_post too since the
                // response would otherwise leak underscored keys.
                // read_post on the parent gates the public path so a
                // Subscriber-level OAuth token cannot read meta on private
                // admin-owned posts.
                $needs_edit_cap = ($key === '' || strpos($key, '_') === 0);
                $cap = $needs_edit_cap ? 'edit_post' : 'read_post';
                if (!current_user_can($cap, $post_id)) {
                    throw new \Exception('You do not have permission to read meta on this post.');
                }
                if ($key !== '') {
                    $value = get_post_meta($post_id, $key, true);
                    return ['key' => $key, 'value' => $value];
                }
                return get_post_meta($post_id);

            case 'wp_update_post_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit meta on this post.');
                }
                if (!array_key_exists('value', $args)) {
                    throw new \Exception('A value is required. To remove a key entirely, use wp_delete_post_meta.');
                }
                $meta_key   = sanitize_text_field($args['key']);
                $meta_value = self::filter_meta_value($args['value'], $meta_key, $post_id, 'wp_update_post_meta');
                $write      = self::write_meta_verified('post', $post_id, $meta_key, $meta_value);
                return array_merge([
                    'message' => 'Post meta updated successfully',
                    'result'  => $write['written'],
                    'post_id' => $post_id,
                    'key'     => $meta_key,
                ], self::meta_write_report($write));

            case 'wp_add_post_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit meta on this post.');
                }
                if (!array_key_exists('value', $args)) {
                    throw new \Exception('A value is required.');
                }
                $key = sanitize_text_field($args['key'] ?? '');
                if ($key === '') throw new \Exception('A meta key is required.');
                $meta_value = self::filter_meta_value($args['value'], $key, $post_id, 'wp_add_post_meta');
                $unique = !empty($args['unique']);
                $write  = self::write_meta_verified('post', $post_id, $key, $meta_value, true, $unique);
                return array_merge([
                    'meta_id' => $write['meta_id'],
                    'created' => $write['written'],
                    'post_id' => $post_id,
                    'key'     => $key,
                ], self::meta_write_report($write));

            case 'wp_delete_post_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('You do not have permission to edit meta on this post.');
                }
                $result = delete_post_meta($post_id, sanitize_text_field($args['key']));
                if (!$result) throw new \Exception('Failed to delete post meta');
                return ['message' => 'Post meta deleted successfully'];

            // ==================== SITE & SEARCH ====================
            case 'wp_get_site_info':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to view site info.');
                }
                return [
                    'name' => get_bloginfo('name'),
                    'description' => get_bloginfo('description'),
                    'url' => home_url(),
                    'language' => get_locale(),
                    'timezone' => wp_timezone_string(),
                    'wp_version' => get_bloginfo('version'),
                ];

            // one-shot site diagnostic. Collapses the WP+PHP+MySQL+plugins+theme
                // discovery flurry (previously 3-5 separate tool calls at conversation start)
                // into a single read. Distinct from wp_get_site_info which is user-visible
                // metadata (name/description/URL); this is operator-visible environment.
            // 1.4.37 Candidate 1 — connection health probe. Any authenticated caller.
            // Every field is self-attributable (my auth method, my session, my token TTL),
            // no cap check needed — reaching execute_tool already required valid auth.
            case 'more_mcp_connection_health':
                global $wp_version;
                // builders block lets an agent plan multi-step edits without probing.
                // Knowing "site is on Divi 5" or "Elementor 4.0.8" at connection time
                // means the agent can pick the right JSON block / widget schema path
                // up front instead of running a discovery call before every write.
                $builders = [
                    'divi_version'      => defined('ET_BUILDER_VERSION') ? (string) constant('ET_BUILDER_VERSION') : null,
                    'elementor_version' => defined('ELEMENTOR_VERSION') ? (string) constant('ELEMENTOR_VERSION') : null,
                    'gutenberg_version' => defined('GUTENBERG_VERSION') ? (string) constant('GUTENBERG_VERSION') : (string) get_bloginfo('version'),
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

            case 'wp_get_site_status':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read site status.');
                }
                global $wpdb;

                // MySQL / MariaDB version — use $wpdb->db_version() when available (WP 6.3+
                // returns the numeric server version), fall back to raw SELECT VERSION() so
                // this works on older WP too.
                $db_server_info = null;
                if (method_exists($wpdb, 'db_version')) {
                    $db_server_info = $wpdb->db_version();
                }
                if (empty($db_server_info)) {
                    $db_server_info = (string) $wpdb->get_var('SELECT VERSION()');
                }

                // Active plugin count — active_plugins is the primary list; also count
                // network-active plugins on multisite so the number reflects reality.
                $active_plugins = (array) get_option('active_plugins', []);
                if (is_multisite()) {
                    $network_active = (array) get_site_option('active_sitewide_plugins', []);
                    $active_plugin_count = count($active_plugins) + count($network_active);
                } else {
                    $active_plugin_count = count($active_plugins);
                }

                $theme = wp_get_theme();

                // Disk usage on ABSPATH — capture just the free-bytes reading. disk_free_space
                // can be disabled by hosts (returns false) so guard for that.
                $disk_free_bytes = @disk_free_space(ABSPATH);

                // Uptime days — WP doesn't record install time separately from siteurl, so
                // use the oldest post_date_gmt as a proxy (nearly always the "Hello World"
                // seed post from install). Falls back to null if the site has no posts.
                $oldest_ts = null;
                $oldest_row = $wpdb->get_var("SELECT UNIX_TIMESTAMP(MIN(post_date_gmt)) FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future','pending')");
                if ($oldest_row && (int) $oldest_row > 0) {
                    $oldest_ts = (int) $oldest_row;
                }

                return [
                    'wp_version'          => get_bloginfo('version'),
                    'php_version'         => PHP_VERSION,
                    'mysql_version'       => $db_server_info,
                    'is_multisite'        => is_multisite(),
                    'active_plugin_count' => $active_plugin_count,
                    'active_theme'        => [
                        'name'       => $theme->get('Name'),
                        'stylesheet' => get_stylesheet(),
                        'template'   => get_template(),
                        'version'    => $theme->get('Version'),
                    ],
                    'memory_limit'        => ini_get('memory_limit'),
                    'max_upload_size'     => size_format(wp_max_upload_size()),
                    'max_execution_time'  => (int) ini_get('max_execution_time'),
                    'timezone'            => wp_timezone_string(),
                    'debug_log_enabled'   => (defined('WP_DEBUG') && WP_DEBUG) && (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG),
                    'disk_free_bytes'     => $disk_free_bytes === false ? null : (int) $disk_free_bytes,
                    'disk_free_human'     => $disk_free_bytes === false ? null : size_format((int) $disk_free_bytes),
                    'install_age_days'    => $oldest_ts ? (int) floor((time() - $oldest_ts) / DAY_IN_SECONDS) : null,
                    'site_url'            => site_url(),
                    'home_url'            => home_url(),
                ];

            // tail wp-content/debug.log for AI-driven diagnosis.
            // Manage_options gate: log lines routinely contain paths + stack traces
            // + occasionally sensitive tokens that shouldn't reach read-tier callers.
            case 'wp_get_error_log_tail':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read the error log.');
                }
                $lines = isset($args['lines']) ? max(1, min(intval($args['lines']), 1000)) : 100;
                $filter = isset($args['filter']) ? (string) $args['filter'] : '';

                $log_status = 'ok';
                $log_path   = defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== ''
                    ? (string) WP_DEBUG_LOG
                    : WP_CONTENT_DIR . '/debug.log';

                if (!(defined('WP_DEBUG') && WP_DEBUG) || !(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) {
                    return [
                        'status'  => 'disabled',
                        'message' => 'WP_DEBUG_LOG is not enabled in wp-config.php. Add: define(\'WP_DEBUG\', true); define(\'WP_DEBUG_LOG\', true); define(\'WP_DEBUG_DISPLAY\', false);',
                        'path'    => $log_path,
                        'filter'  => $filter,
                        'lines'   => [],
                        'total_returned' => 0,
                    ];
                }

                if (!file_exists($log_path)) {
                    return [
                        'status'  => 'no_log_file',
                        'message' => 'debug.log does not exist yet (no errors logged since it was last cleared).',
                        'path'    => $log_path,
                        'filter'  => $filter,
                        'lines'   => [],
                        'total_returned' => 0,
                    ];
                }

                if (!is_readable($log_path)) {
                    return [
                        'status'  => 'unreadable',
                        'message' => 'debug.log exists but is not readable by PHP (check file permissions).',
                        'path'    => $log_path,
                        'filter'  => $filter,
                        'lines'   => [],
                        'total_returned' => 0,
                    ];
                }

                // Cap read at last 1MB to prevent memory blowup on multi-GB debug.log
                // files. AI callers get whatever N lines fit in that window.
                $filesize = filesize($log_path);
                $chunk_max = 1 * 1024 * 1024;
                $offset    = ($filesize > $chunk_max) ? ($filesize - $chunk_max) : 0;

                $fh = @fopen($log_path, 'r');
                if (!$fh) {
                    throw new \Exception('Could not open debug.log for reading.');
                }
                if ($offset > 0) {
                    fseek($fh, $offset);
                    // Drop first (partial) line after the seek so we don't return
                    // half a stack-trace line.
                    fgets($fh);
                }
                $raw = stream_get_contents($fh);
                fclose($fh);

                $all_lines = $raw === false ? [] : preg_split("/\r\n|\n|\r/", (string) $raw);
                // Drop trailing empty line from final \n.
                if (!empty($all_lines) && '' === end($all_lines)) {
                    array_pop($all_lines);
                }

                if ($filter !== '') {
                    $all_lines = array_values(array_filter($all_lines, function ($ln) use ($filter) {
                        return false !== stripos($ln, $filter);
                    }));
                }

                $tail = array_slice($all_lines, -$lines);

                return [
                    'status'         => $log_status,
                    'path'           => $log_path,
                    'filesize_bytes' => (int) $filesize,
                    'window_bytes'   => (int) ($filesize - $offset),
                    'truncated'      => $offset > 0,
                    'filter'         => $filter,
                    'total_returned' => count($tail),
                    'lines'          => array_values($tail),
                ];

            // enumerate scheduled cron events. Stuck cron is a routine WP
            // diagnosis (missed_schedule reports on WP core, unfired hooks on plugin
            // conflicts) and agents previously had no visibility. Manage_options gate
            // because cron args occasionally carry token-like identifiers.
            case 'wp_get_cron_schedule':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read the cron schedule.');
                }
                $crons = _get_cron_array();
                if (!is_array($crons)) {
                    return ['events' => [], 'total_count' => 0];
                }
                $now = time();
                $events = [];
                foreach ($crons as $timestamp => $hooks) {
                    if (!is_array($hooks)) continue;
                    foreach ($hooks as $hook => $signatures) {
                        if (!is_array($signatures)) continue;
                        foreach ($signatures as $sig_key => $meta) {
                            $recurrence = null;
                            if (!empty($meta['schedule'])) {
                                $schedules = wp_get_schedules();
                                $recurrence = isset($schedules[$meta['schedule']])
                                    ? $meta['schedule'] . ' (' . (int) $schedules[$meta['schedule']]['interval'] . 's)'
                                    : (string) $meta['schedule'];
                            }
                            $events[] = [
                                'hook'          => (string) $hook,
                                'next_run_ts'   => (int) $timestamp,
                                'next_run_iso'  => wp_date('c', (int) $timestamp),
                                'seconds_until' => (int) $timestamp - $now,
                                'is_overdue'    => (int) $timestamp < $now,
                                'recurrence'    => $recurrence,
                                'args'          => $meta['args'] ?? [],
                            ];
                        }
                    }
                }
                // Sort by next-run ascending so overdue events + soonest come first.
                usort($events, function ($a, $b) {
                    return $a['next_run_ts'] <=> $b['next_run_ts'];
                });
                return [
                    'events'      => $events,
                    'total_count' => count($events),
                    'now_ts'      => $now,
                    'now_iso'     => wp_date('c', $now),
                ];

            case 'wp_search':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to search.');
                }
                $query = sanitize_text_field($args['query']);
                $per_page = isset($args['per_page']) ? max(1, min(intval($args['per_page']), 100)) : 20;
                $snippet_len = isset($args['snippet']) ? max(0, min(intval($args['snippet']), 1000)) : 0;
                $search_args = [
                    's' => $query,
                    'post_type' => !empty($args['post_type']) ? sanitize_text_field($args['post_type']) : 'any',
                    'numberposts' => $per_page,
                ];
                $posts = get_posts($search_args);
                return array_map(function($p) use ($query, $snippet_len) {
                    $row = ['id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type, 'url' => get_permalink($p)];
                    if ($snippet_len > 0) {
                        $row['slug'] = $p->post_name;
                        $row['snippet'] = $this->extract_snippet($p->post_content, $query, $snippet_len);
                    }
                    return $row;
                }, $posts);

            // ==================== OPTIONS ====================
            case 'wp_get_option':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read site options.');
                }
                // Site Kit GA4 config (property ID, measurement ID, web data stream ID) — no
                // secrets; redact_sensitive_keys() below runs on the return in case Site Kit
                // ever adds one. Site Kit has ~4M installs; this is a common read-tier lookup.
                //
                // wpseo_taxonomy_meta holds Yoast's per-term SEO settings, keyed
                // [taxonomy][term_id]. Readable because writing it requires
                // read-modify-write — the option carries every term on the site,
                // so a blind overwrite discards the rest. GitHub #6 raised read
                // access as a prerequisite for writing it safely. It holds no
                // credentials. wp_update_term_seo_meta is the supported write
                // path and does the merge for you; this read is for auditing.
                $allowed = ['blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'posts_per_page', 'date_format', 'time_format', 'timezone_string', 'googlesitekit_analytics-4_settings', 'wpseo_taxonomy_meta'];
                $name = sanitize_text_field($args['name']);
                if (!in_array($name, $allowed)) throw new \Exception('Option not allowed: ' . esc_html($name));
                return ['name' => $name, 'value' => $this->redact_sensitive_keys(get_option($name))];

            case 'wp_get_plugin_settings':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read plugin settings.');
                }
                $slug = sanitize_text_field($args['plugin_slug'] ?? '');
                if (empty($slug)) throw new \Exception('plugin_slug is required.');
                return [
                    'slug'    => $slug,
                    'options' => $this->find_plugin_options($slug),
                ];

            case 'wp_update_option':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to write site options.');
                }
                $name = sanitize_text_field($args['name'] ?? '');
                if (empty($name)) throw new \Exception('Option name is required.');

                // Gate 1: master toggle
                $rmcp_settings = get_option('more_mcp_settings', []);
                if (empty($rmcp_settings['allow_option_writes'])) {
                    throw new \Exception('Option writes are disabled. Enable "Allow AI to write WordPress options" under More MCP > Settings > General Settings.');
                }

                // Gate 2: permanent denylist (overrides allowlist)
                if ($this->is_denylisted_option($name)) {
                    throw new \Exception('Option is permanently denylisted: ' . esc_html($name));
                }

                // Gate 3: allowlist
                $default_writable = ['blogname', 'blogdescription', 'posts_per_page', 'date_format', 'time_format'];
                $writable = apply_filters('more_mcp_writable_options', $default_writable);
                if (!is_array($writable)) $writable = $default_writable;
                if (!in_array($name, $writable, true)) {
                    throw new \Exception('Option not in allowlist: ' . esc_html($name) . '. Plugin authors can opt their settings in via add_filter("more_mcp_writable_options", ...).');
                }

                // Value is intentionally accepted as-is (any JSON type). update_option will serialize.
                $value = $args['value'] ?? null;
                $previous = get_option($name);
                $result = update_option($name, $value);
                return [
                    'name'     => $name,
                    'updated'  => (bool) $result,
                    'previous' => $this->redact_sensitive_keys($previous),
                ];

            // ==================== MENUS ====================
            case 'wp_get_menus':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('You do not have permission to list menus.');
                }
                $menus = wp_get_nav_menus();
                return array_map(function($m) {
                    return ['id' => $m->term_id, 'name' => $m->name, 'slug' => $m->slug];
                }, $menus);

            case 'wp_get_menu_items':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('You do not have permission to list menu items.');
                }
                $items = wp_get_nav_menu_items(intval($args['menu_id']));
                if (!$items) return [];
                return array_map(function($i) {
                    return [
                        'id' => $i->ID,
                        'title' => $i->title,
                        'url' => $i->url,
                        'parent' => $i->menu_item_parent,
                        'order' => $i->menu_order,
                    ];
                }, $items);

            case 'wp_create_menu_item':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required.');
                }
                $menu_id = intval($args['menu_id']);
                if (!wp_get_nav_menu_object($menu_id)) {
                    throw new \Exception('Menu not found: ' . esc_html((string) $menu_id));
                }
                $object_type = sanitize_text_field($args['object_type'] ?? 'custom');
                $item_args = [
                    'menu-item-title'     => sanitize_text_field($args['title']),
                    'menu-item-url'       => esc_url_raw($args['url'] ?? ''),
                    'menu-item-status'    => 'publish',
                    'menu-item-type'      => $object_type === 'category' ? 'taxonomy' : ($object_type === 'custom' ? 'custom' : 'post_type'),
                    'menu-item-object'    => $object_type === 'category' ? 'category' : ($object_type === 'custom' ? '' : $object_type),
                    'menu-item-object-id' => intval($args['object_id'] ?? 0),
                    'menu-item-parent-id' => intval($args['parent_id'] ?? 0),
                    'menu-item-position'  => intval($args['position'] ?? 0),
                    'menu-item-target'    => sanitize_text_field($args['target'] ?? ''),
                ];
                $item_id = wp_update_nav_menu_item($menu_id, 0, $item_args);
                if (is_wp_error($item_id)) throw new \Exception(esc_html($item_id->get_error_message()));
                return ['menu_item_id' => (int) $item_id, 'menu_id' => $menu_id];

            case 'wp_update_menu_item':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required.');
                }
                $item_id = intval($args['menu_item_id']);
                $existing = get_post($item_id);
                if (!$existing || $existing->post_type !== 'nav_menu_item') {
                    throw new \Exception('Menu item not found.');
                }
                $menus = wp_get_post_terms($item_id, 'nav_menu', ['fields' => 'ids']);
                $menu_id = (!empty($menus) && !is_wp_error($menus)) ? (int) $menus[0] : 0;
                // Build overrides from caller-supplied fields only. The helper
                // reads existing values and merges so unspecified fields are
                // preserved — fixes the 1.4.17 destructive bug where partial
                // args zeroed title/url/parent on the existing item.
                $overrides = [];
                if (isset($args['title']))     $overrides['menu-item-title']     = sanitize_text_field($args['title']);
                if (isset($args['url']))       $overrides['menu-item-url']       = esc_url_raw($args['url']);
                if (isset($args['parent_id'])) $overrides['menu-item-parent-id'] = intval($args['parent_id']);
                if (isset($args['position']))  $overrides['menu-item-position']  = intval($args['position']);
                if (isset($args['target']))    $overrides['menu-item-target']    = sanitize_text_field($args['target']);
                $merged = $this->build_safe_menu_item_args($item_id, $overrides);
                if (is_wp_error($merged)) {
                    throw new \Exception(esc_html($merged->get_error_message()));
                }
                $result = wp_update_nav_menu_item($menu_id, $item_id, $merged);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return ['menu_item_id' => $item_id, 'menu_id' => $menu_id];

            case 'wp_delete_menu_item':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required.');
                }
                $item_id = intval($args['menu_item_id']);
                $existing = get_post($item_id);
                if (!$existing || $existing->post_type !== 'nav_menu_item') {
                    throw new \Exception('Menu item not found.');
                }
                $result = wp_delete_post($item_id, true);
                if (!$result) throw new \Exception('Failed to delete menu item.');
                return ['success' => true, 'menu_item_id' => $item_id];

            case 'wp_reorder_menu_items':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required.');
                }
                $menu_id = intval($args['menu_id']);
                $menu_obj = wp_get_nav_menu_object($menu_id);
                if (!$menu_obj) {
                    throw new \Exception('Menu not found.');
                }
                $order = $args['item_order'] ?? [];
                if (!is_array($order)) throw new \Exception('item_order must be an array of menu_item_ids.');

                // Snapshot the pre-op state of every item in this menu BEFORE any
                // mutation. Captures menu_order + menu_item_parent per item so a
                // full restore is possible even when the caller reorders only a
                // subset. Stored in wp_options via Undo_Store, TTL 72h.
                $pre_op_items = wp_get_nav_menu_items($menu_id) ?: [];
                $pre_op_state = [];
                foreach ($pre_op_items as $item) {
                    $pre_op_state[(int) $item->db_id] = [
                        'menu_order'       => (int) $item->menu_order,
                        'menu_item_parent' => (int) $item->menu_item_parent,
                    ];
                }

                // For each item, read existing values then send a full args
                // payload with only menu-item-position overridden. Sending
                // partial args here was the 1.4.17 destructive bug — WP merges
                // with defaults, wiping title/url/parent on every item touched.
                $position = 1;
                $reordered = [];
                $skipped = [];
                foreach ($order as $iid) {
                    $iid = intval($iid);
                    if ($iid <= 0) {
                        continue;
                    }
                    $merged = $this->build_safe_menu_item_args($iid, [
                        'menu-item-position' => $position,
                    ]);
                    if (is_wp_error($merged)) {
                        $skipped[] = ['menu_item_id' => $iid, 'reason' => $merged->get_error_message()];
                        continue;
                    }
                    $result = wp_update_nav_menu_item($menu_id, $iid, $merged);
                    if (is_wp_error($result)) {
                        $skipped[] = ['menu_item_id' => $iid, 'reason' => $result->get_error_message()];
                        continue;
                    }
                    $reordered[] = $iid;
                    $position++;
                }

                // Persist the snapshot and build the undo envelope.
                $undo_envelope = \More_MCP\MCP\Undo_Store::store([
                    'op'      => 'wp_reorder_menu_items',
                    'summary' => sprintf('Restore menu "%s" (%d items) to prior order', $menu_obj->name, count($pre_op_state)),
                    'target'  => ['menu_id' => $menu_id, 'menu_name' => $menu_obj->name],
                    'pre_op_state' => $pre_op_state,
                ]);

                $response = ['success' => true, 'menu_id' => $menu_id, 'count' => count($reordered), 'reordered' => $reordered];
                if (!empty($skipped)) {
                    $response['skipped'] = $skipped;
                }
                $response['undo'] = $undo_envelope;
                return $response;

            // ==================== SEO — served-HTML audit ====================
            case 'seo_audit_meta_tags':
                if (!current_user_can('read')) {
                    throw new \Exception('read capability required.');
                }
                $seo_post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
                $seo_url     = isset($args['url']) ? esc_url_raw((string) $args['url']) : '';
                if ($seo_post_id <= 0 && $seo_url === '') {
                    throw new \Exception('Either post_id or url is required.');
                }
                if ($seo_post_id > 0) {
                    if (!get_post($seo_post_id)) {
                        throw new \Exception('Post not found: ' . esc_html((string) $seo_post_id));
                    }
                    $seo_url = get_permalink($seo_post_id);
                    if (!$seo_url) {
                        throw new \Exception('Cannot resolve permalink for post ' . esc_html((string) $seo_post_id));
                    }
                }
                // SSRF guard — only allow same-host URLs (this site's own permalinks).
                // Two-layer defense: (1) hostname must match home_url's host, (2) we IGNORE
                // caller-supplied port + scheme and rebuild the fetch URL against home_url's
                // origin. Layer 2 defeats the same-host-different-port SSRF vector (e.g.
                // caller passing example.com:3306 or example.com:6379 to probe internal
                // services bound on the same hostname).
                $seo_parts  = wp_parse_url($seo_url);
                $home_parts = wp_parse_url(home_url());
                if (!$seo_parts || empty($seo_parts['host']) || empty($home_parts['host'])) {
                    throw new \Exception('Invalid URL.');
                }
                if (strcasecmp($seo_parts['host'], $home_parts['host']) !== 0) {
                    throw new \Exception('url must be on this site (same host as home_url). Cross-domain audits are not supported.');
                }
                // Rebuild the URL against home_url's origin — caller cannot override port or
                // scheme, only path/query. This is the port-SSRF fix.
                $safe_path  = isset($seo_parts['path'])  ? $seo_parts['path']         : '/';
                $safe_query = isset($seo_parts['query']) ? '?' . $seo_parts['query']  : '';
                $seo_url    = rtrim(home_url(), '/') . $safe_path . $safe_query;
                $seo_response = wp_remote_get($seo_url, [
                    'timeout'     => 10,
                    'redirection' => 3,
                    'user-agent'  => 'More MCP SEO Audit',
                    'sslverify'   => true,
                ]);
                if (is_wp_error($seo_response)) {
                    throw new \Exception('Failed to fetch URL: ' . esc_html($seo_response->get_error_message()));
                }
                $seo_status = (int) wp_remote_retrieve_response_code($seo_response);
                $seo_html   = (string) wp_remote_retrieve_body($seo_response);
                if ($seo_status < 200 || $seo_status >= 300) {
                    throw new \Exception('Non-2xx HTTP status ' . $seo_status . ' when fetching ' . esc_html($seo_url));
                }
                if ($seo_html === '') {
                    throw new \Exception('Empty response body from ' . esc_html($seo_url));
                }

                // Parse the head with DOMDocument — regex on HTML is fragile.
                $prev_libxml = libxml_use_internal_errors(true);
                $seo_dom     = new \DOMDocument();
                $seo_dom->loadHTML('<?xml encoding="UTF-8">' . $seo_html);
                libxml_clear_errors();
                libxml_use_internal_errors($prev_libxml);

                // Titles
                $title_nodes  = $seo_dom->getElementsByTagName('title');
                $title_first  = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : '';

                // Metas — walk once, bucket by name/property.
                $meta_desc_values     = [];
                $viewport_content     = '';
                $og_fields            = ['title' => '', 'description' => '', 'image' => '', 'url' => '', 'type' => ''];
                $tw_fields            = ['card' => '', 'title' => '', 'description' => '', 'image' => ''];
                $meta_nodes           = $seo_dom->getElementsByTagName('meta');
                foreach ($meta_nodes as $m) {
                    $name     = strtolower((string) $m->getAttribute('name'));
                    $property = strtolower((string) $m->getAttribute('property'));
                    $content  = (string) $m->getAttribute('content');
                    if ($name === 'description') {
                        $meta_desc_values[] = $content;
                    } elseif ($name === 'viewport') {
                        $viewport_content = $content;
                    } elseif (str_starts_with($property, 'og:')) {
                        $og_key = substr($property, 3);
                        if (array_key_exists($og_key, $og_fields) && $og_fields[$og_key] === '') {
                            $og_fields[$og_key] = $content;
                        }
                    } elseif (str_starts_with($name, 'twitter:')) {
                        $tw_key = substr($name, 8);
                        if (array_key_exists($tw_key, $tw_fields) && $tw_fields[$tw_key] === '') {
                            $tw_fields[$tw_key] = $content;
                        }
                    }
                }

                // Canonicals
                $canonical_hrefs = [];
                $link_nodes      = $seo_dom->getElementsByTagName('link');
                foreach ($link_nodes as $l) {
                    if (strtolower((string) $l->getAttribute('rel')) === 'canonical') {
                        $canonical_hrefs[] = (string) $l->getAttribute('href');
                    }
                }
                $canonical_first = $canonical_hrefs[0] ?? '';
                $canonical_norm  = $canonical_first !== '' ? untrailingslashit($canonical_first) : '';
                $requested_norm  = untrailingslashit($seo_url);

                return [
                    'url'    => $seo_url,
                    'status' => $seo_status,
                    'title'  => [
                        'value'      => $title_first,
                        'length'     => strlen($title_first),
                        'duplicates' => max(0, $title_nodes->length - 1),
                    ],
                    'description' => [
                        'value'      => $meta_desc_values[0] ?? '',
                        'length'     => isset($meta_desc_values[0]) ? strlen($meta_desc_values[0]) : 0,
                        'duplicates' => max(0, count($meta_desc_values) - 1),
                    ],
                    'canonical' => [
                        'value'      => $canonical_first,
                        'duplicates' => max(0, count($canonical_hrefs) - 1),
                        'is_self'    => $canonical_norm !== '' && strcasecmp($canonical_norm, $requested_norm) === 0,
                    ],
                    'viewport' => [
                        'present' => $viewport_content !== '',
                        'content' => $viewport_content,
                    ],
                    'og'      => $og_fields,
                    'twitter' => $tw_fields,
                ];

            // ==================== UNDO (Free basic mode) ====================
            case 'more_mcp_undo_last_operation':
                $undo_token = isset($args['token']) ? sanitize_text_field((string) $args['token']) : '';
                if ($undo_token === '') {
                    throw new \Exception('token is required.');
                }
                $undo_snapshot = \More_MCP\MCP\Undo_Store::read($undo_token);
                if (!$undo_snapshot) {
                    // Single generic message covers all failure modes (malformed,
                    // unknown, expired, consumed) — see Undo_Store::read security note.
                    throw new \Exception('Undo token not found, expired, or already consumed.');
                }
                $undo_op = $undo_snapshot['op'] ?? '';

                switch ($undo_op) {
                    case 'blocks_mutation':
                        // Cap check matches the original destructive tools
                        // (blocks_insert / update / delete / move all gate on
                        // object-level edit_post). Re-checked here rather than
                        // trusted from the snapshot: the token may be redeemed
                        // by a different caller than the one who created it.
                        $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                            ? $undo_snapshot['target']
                            : [];
                        $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                        if ($undo_post_id <= 0) {
                            throw new \Exception('Undo snapshot has no target post.');
                        }
                        if (!current_user_can('edit_post', $undo_post_id)) {
                            throw new \Exception('You do not have permission to edit this post, so this block change cannot be undone.');
                        }
                        $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (!array_key_exists('post_content', $undo_pre_op)) {
                            throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                        }
                        if (!get_post($undo_post_id)) {
                            throw new \Exception('The target post no longer exists.');
                        }
                        $undo_restored = wp_update_post([
                            'ID'           => $undo_post_id,
                            'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                        ], true);
                        if (is_wp_error($undo_restored)) {
                            throw new \Exception('Failed to restore post content: ' . esc_html($undo_restored->get_error_message()));
                        }
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return [
                            'success' => true,
                            'op'      => 'blocks_mutation',
                            'post_id' => $undo_post_id,
                            'message' => 'Post content restored to its pre-operation state.',
                            'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                        ];

                    case 'divi_content_write':
                        // Divi writes snapshot the complete post_content value. Re-check
                        // object-level capability because another caller may redeem the
                        // token, then invalidate Divi's derived state after restoration.
                        $undo_divi_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                            ? $undo_snapshot['target']
                            : [];
                        $undo_divi_post_id = isset($undo_divi_target['post_id']) ? (int) $undo_divi_target['post_id'] : 0;
                        if ($undo_divi_post_id <= 0) {
                            throw new \Exception('Undo snapshot has no target post.');
                        }
                        if (!current_user_can('edit_post', $undo_divi_post_id)) {
                            throw new \Exception('You do not have permission to edit this post, so this Divi change cannot be undone.');
                        }
                        $undo_divi_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (!array_key_exists('post_content', $undo_divi_pre_op)) {
                            throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                        }
                        if (!get_post($undo_divi_post_id)) {
                            throw new \Exception('The target post no longer exists.');
                        }
                        if (!class_exists('\More_MCP\Integrations\Divi')) {
                            throw new \Exception('The Divi integration is unavailable, so its cache-safe undo cannot run.');
                        }
                        $undo_divi_restored = wp_update_post([
                            'ID'           => $undo_divi_post_id,
                            'post_content' => wp_slash((string) $undo_divi_pre_op['post_content']),
                        ], true);
                        if (is_wp_error($undo_divi_restored)) {
                            throw new \Exception('Failed to restore Divi post content: ' . esc_html($undo_divi_restored->get_error_message()));
                        }
                        $undo_divi_fresh    = get_post($undo_divi_post_id);
                        $undo_divi_verified = $undo_divi_fresh && (string) $undo_divi_fresh->post_content === (string) $undo_divi_pre_op['post_content'];
                        $undo_divi_inval    = \More_MCP\Integrations\Divi::invalidate_derived_state_public($undo_divi_post_id);
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return [
                            'success'            => true,
                            'op'                 => 'divi_content_write',
                            'post_id'            => $undo_divi_post_id,
                            'verified'           => $undo_divi_verified,
                            'message'            => 'Divi post content restored to its pre-operation state.',
                            'restored_summary'   => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                            'cache_invalidation' => $undo_divi_inval,
                        ];

                    case 'elementor_element_write':
                        // Restores the whole _elementor_data tree captured before
                        // an elementor_update_widget / elementor_delete_widget
                        // call. The snapshot is the full tree rather than the one
                        // element, because a partial snapshot could not restore
                        // correctly if the tree changed shape in between.
                        //
                        // Cap check matches the original tools (both gate on
                        // object-level edit_post), re-checked here rather than
                        // trusted from the snapshot: a token may be redeemed by a
                        // different caller than the one who created it.
                        $undo_el_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                            ? $undo_snapshot['target']
                            : [];
                        $undo_el_post_id = isset($undo_el_target['post_id']) ? (int) $undo_el_target['post_id'] : 0;
                        if ($undo_el_post_id <= 0) {
                            throw new \Exception('Undo snapshot has no target post.');
                        }
                        if (!current_user_can('edit_post', $undo_el_post_id)) {
                            throw new \Exception('You do not have permission to edit this post, so this Elementor change cannot be undone.');
                        }
                        $undo_el_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (!array_key_exists('elementor_data', $undo_el_pre_op)) {
                            throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                        }
                        if (!get_post($undo_el_post_id)) {
                            throw new \Exception('The target post no longer exists.');
                        }
                        // Refuse to restore a snapshot that will not parse rather
                        // than writing it: unparseable _elementor_data renders as
                        // a blank page, which is worse than the state being undone.
                        $undo_el_decoded = json_decode((string) $undo_el_pre_op['elementor_data'], true);
                        if (!is_array($undo_el_decoded)) {
                            throw new \Exception('Undo snapshot does not contain a parseable Elementor tree, so it was not restored.');
                        }
                        // wp_slash for the same reason the write path does it:
                        // WordPress unslashes meta on the way in and JSON is full
                        // of backslashes (GitHub #1).
                        update_post_meta(
                            $undo_el_post_id,
                            '_elementor_data',
                            wp_slash(wp_json_encode($undo_el_decoded))
                        );
                        // Restoring the tree makes the derived caches stale in
                        // exactly the same way the original write did, so clear
                        // them here too or the undo appears not to have worked.
                        $undo_el_inval = ['invalidated' => [], 'warnings' => []];
                        if (class_exists('\More_MCP\Integrations\Elementor')) {
                            $undo_el_inval = \More_MCP\Integrations\Elementor::invalidate_derived_state_public($undo_el_post_id);
                        }
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        $undo_el_response = [
                            'success'          => true,
                            'op'               => 'elementor_element_write',
                            'post_id'          => $undo_el_post_id,
                            'message'          => 'Elementor page data restored to its pre-operation state.',
                            'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                        ];
                        if (!empty($undo_el_inval['warnings'])) {
                            $undo_el_response['cache_invalidation'] = [
                                'cleared'  => $undo_el_inval['invalidated'],
                                'warnings' => $undo_el_inval['warnings'],
                            ];
                        }
                        return $undo_el_response;

                    case 'blocks_template_update':
                    case 'blocks_template_revert':
                    case 'blocks_reusable_update':
                        // Restore prior post_content for a wp_template,
                        // wp_template_part, or wp_block post. Cap check matches
                        // the original tool: template posts map every capability
                        // to edit_theme_options, reusable blocks use ordinary
                        // post caps. Re-checked on redemption because the token
                        // may be presented by a different caller.
                        $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                            ? $undo_snapshot['target']
                            : [];
                        $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                        if ($undo_post_id <= 0) {
                            throw new \Exception('Undo snapshot has no target post.');
                        }
                        $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (!array_key_exists('post_content', $undo_pre_op)) {
                            throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                        }

                        $undo_is_reusable = ('blocks_reusable_update' === $undo_op);
                        if ($undo_is_reusable) {
                            if (!current_user_can('edit_post', $undo_post_id)) {
                                throw new \Exception('You do not have permission to edit this reusable block.');
                            }
                        } elseif (!current_user_can('edit_theme_options')) {
                            throw new \Exception('edit_theme_options capability required to undo this template change.');
                        }

                        // blocks_template_revert deleted its post, so the undo
                        // has to re-create it rather than update in place.
                        $undo_existing = get_post($undo_post_id);
                        if (!$undo_existing) {
                            if ('blocks_template_revert' !== $undo_op) {
                                throw new \Exception('The target post no longer exists.');
                            }
                            $undo_slug = isset($undo_target['slug']) ? sanitize_title((string) $undo_target['slug']) : '';
                            if ('' === $undo_slug) {
                                throw new \Exception('Undo snapshot has no slug to re-create the template from.');
                            }
                            $undo_new_id = wp_insert_post([
                                'post_type'    => isset($undo_target['post_type']) ? sanitize_key((string) $undo_target['post_type']) : 'wp_template',
                                'post_name'    => $undo_slug,
                                'post_title'   => isset($undo_target['title']) ? sanitize_text_field((string) $undo_target['title']) : $undo_slug,
                                'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                                'post_status'  => 'publish',
                            ], true);
                            if (is_wp_error($undo_new_id)) {
                                throw new \Exception('Failed to re-create template: ' . esc_html($undo_new_id->get_error_message()));
                            }
                            wp_set_object_terms($undo_new_id, get_stylesheet(), 'wp_theme');
                            \More_MCP\MCP\Undo_Store::delete($undo_token);
                            return [
                                'success' => true,
                                'op'      => $undo_op,
                                'post_id' => $undo_new_id,
                                'message' => 'Template customization re-created from the undo snapshot.',
                                'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                            ];
                        }

                        $undo_restored = wp_update_post([
                            'ID'           => $undo_post_id,
                            'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                        ], true);
                        if (is_wp_error($undo_restored)) {
                            throw new \Exception('Failed to restore content: ' . esc_html($undo_restored->get_error_message()));
                        }
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return [
                            'success' => true,
                            'op'      => $undo_op,
                            'post_id' => $undo_post_id,
                            'message' => 'Content restored to its pre-operation state.',
                            'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                        ];

                    case 'blocks_template_create':
                        // The original op created a new customization post, so
                        // undoing means deleting it and letting the theme file
                        // surface again.
                        if (!current_user_can('edit_theme_options')) {
                            throw new \Exception('edit_theme_options capability required to undo this template change.');
                        }
                        $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                            ? $undo_snapshot['target']
                            : [];
                        $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                        if ($undo_post_id <= 0) {
                            throw new \Exception('Undo snapshot has no target post.');
                        }
                        if (get_post($undo_post_id)) {
                            wp_delete_post($undo_post_id, true);
                        }
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return [
                            'success' => true,
                            'op'      => 'blocks_template_create',
                            'post_id' => $undo_post_id,
                            'message' => 'Template customization removed; the theme-provided file renders again.',
                            'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                        ];

                    case 'blocks_reusable_delete':
                        // Re-create the deleted wp_block post from the snapshot.
                        if (!current_user_can('edit_posts')) {
                            throw new \Exception('edit_posts capability required to restore a reusable block.');
                        }
                        $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (!array_key_exists('post_content', $undo_pre_op)) {
                            throw new \Exception('Undo snapshot has no content to restore.');
                        }
                        $undo_new_id = wp_insert_post([
                            'post_type'    => 'wp_block',
                            'post_title'   => isset($undo_pre_op['post_title']) ? sanitize_text_field((string) $undo_pre_op['post_title']) : 'Restored block',
                            'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                            'post_status'  => 'publish',
                        ], true);
                        if (is_wp_error($undo_new_id)) {
                            throw new \Exception('Failed to restore reusable block: ' . esc_html($undo_new_id->get_error_message()));
                        }
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return [
                            'success' => true,
                            'op'      => 'blocks_reusable_delete',
                            'post_id' => $undo_new_id,
                            'message' => 'Reusable block restored. Note it has a new ID, so posts embedding the old ID will not automatically reconnect.',
                            'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                        ];

                    case 'wp_reorder_menu_items':
                        // Cap check matches the original destructive tool.
                        if (!current_user_can('edit_theme_options')) {
                            throw new \Exception('edit_theme_options capability required to undo this operation.');
                        }
                        $pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                            ? $undo_snapshot['pre_op_state']
                            : [];
                        if (empty($pre_op)) {
                            throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                        }
                        $restored_count = 0;
                        $undo_skipped   = [];
                        foreach ($pre_op as $item_id => $prior) {
                            $item_id_int = (int) $item_id;
                            if ($item_id_int <= 0 || !is_array($prior)) {
                                $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => 'invalid_snapshot_entry'];
                                continue;
                            }
                            $update_result = wp_update_post([
                                'ID'          => $item_id_int,
                                'menu_order'  => (int) ($prior['menu_order'] ?? 0),
                                'post_parent' => (int) ($prior['menu_item_parent'] ?? 0),
                            ], true);
                            if (is_wp_error($update_result)) {
                                $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => $update_result->get_error_message()];
                                continue;
                            }
                            if ($update_result === 0) {
                                // Menu item was deleted after the snapshot — nothing to restore for this id.
                                $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => 'menu_item_not_found'];
                                continue;
                            }
                            $restored_count++;
                        }
                        wp_cache_flush();
                        // One-shot: consume the token so the undo can't be replayed.
                        \More_MCP\MCP\Undo_Store::delete($undo_token);

                        $undo_response = [
                            'undone'   => true,
                            'op'       => $undo_op,
                            'target'   => $undo_snapshot['target'] ?? [],
                            'restored' => $restored_count,
                            'summary'  => $undo_snapshot['summary'] ?? '',
                        ];
                        if (!empty($undo_skipped)) {
                            $undo_response['skipped'] = $undo_skipped;
                        }
                        return $undo_response;

                    case 'forms_entry_write':
                        // Restore a form entry's prior status/is_read. Re-check the
                        // capability the write tools require (manage_options): a token
                        // may be redeemed by a different caller than created it. The
                        // adapter re-checks provider availability and does the restore.
                        if (!current_user_can('manage_options')) {
                            throw new \Exception('manage_options capability required to undo this forms operation.');
                        }
                        $forms_undo_result = \More_MCP\Integrations\Forms::undo_entry_write($undo_snapshot);
                        \More_MCP\MCP\Undo_Store::delete($undo_token);
                        return $forms_undo_result;

                    default:
                        throw new \Exception('Unsupported op in undo snapshot: ' . esc_html((string) $undo_op) . '. This version of More MCP does not know how to undo that operation. Contact support if you saw this after a successful tool call.');
                }

            // ==================== PLUGINS & THEMES ====================
            case 'wp_get_plugins':
                if (!current_user_can('activate_plugins')) {
                    throw new \Exception('You do not have permission to list plugins.');
                }
                if (!function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $plugins = get_plugins();
                $active = get_option('active_plugins', []);
                $result = [];
                foreach ($plugins as $path => $data) {
                    $result[] = [
                        'name' => $data['Name'],
                        'version' => $data['Version'],
                        'active' => in_array($path, $active),
                        'author' => $data['Author'],
                    ];
                }
                return $result;

            case 'wp_get_themes':
                if (!current_user_can('switch_themes')) {
                    throw new \Exception('You do not have permission to list themes.');
                }
                $themes = wp_get_themes();
                $active = get_stylesheet();
                $result = [];
                foreach ($themes as $slug => $theme) {
                    $result[] = [
                        'name' => $theme->get('Name'),
                        'version' => $theme->get('Version'),
                        'active' => ($slug === $active),
                        'author' => $theme->get('Author'),
                    ];
                }
                return $result;

            // ==================== THEME & APPEARANCE ====================
            case 'wp_get_active_theme':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to view the active theme.');
                }
                $theme = wp_get_theme();
                if (!$theme->exists()) throw new \Exception('Active theme not found.');
                $parent = $theme->parent();
                return [
                    'name'           => $theme->get('Name'),
                    'slug'           => $theme->get_stylesheet(),
                    'template'       => $theme->get_template(),
                    'stylesheet'     => $theme->get_stylesheet(),
                    'version'        => $theme->get('Version'),
                    'author'         => wp_strip_all_tags((string) $theme->get('Author')),
                    'description'    => wp_strip_all_tags((string) $theme->get('Description')),
                    'parent_slug'    => $parent ? $parent->get_stylesheet() : null,
                    'screenshot_url' => $theme->get_screenshot(),
                    'status'         => $theme->get('Status'),
                ];

            case 'wp_get_theme_mods':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('You do not have permission to read theme mods.');
                }
                $mods = get_theme_mods();
                return is_array($mods) ? $mods : [];

            case 'wp_update_theme_mod':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('You do not have permission to update theme mods.');
                }
                $mod_name = sanitize_text_field($args['mod_name'] ?? '');
                if (empty($mod_name)) throw new \Exception('mod_name is required.');

                // Gate 1: master toggle
                $rmcp_settings = get_option('more_mcp_settings', []);
                if (empty($rmcp_settings['allow_theme_writes'])) {
                    throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
                }

                // Gate 2: allowlist (default empty — opt-in via filter)
                $writable = apply_filters('more_mcp_writable_theme_mods', []);
                if (!is_array($writable)) $writable = [];
                if (!in_array($mod_name, $writable, true)) {
                    throw new \Exception('Theme mod not in allowlist: ' . esc_html($mod_name) . '. Theme/plugin authors can opt their mods in via add_filter("more_mcp_writable_theme_mods", ...).');
                }

                $previous = get_theme_mod($mod_name);
                $value = $args['value'] ?? null;
                set_theme_mod($mod_name, $value);
                return [
                    'mod_name'       => $mod_name,
                    'previous_value' => $previous,
                    'new_value'      => get_theme_mod($mod_name),
                ];

            case 'wp_get_custom_css':
                if (!current_user_can('read')) {
                    throw new \Exception('You do not have permission to read custom CSS.');
                }
                $theme_slug = isset($args['theme_slug']) ? sanitize_key($args['theme_slug']) : get_stylesheet();
                $css = wp_get_custom_css($theme_slug);
                $post = wp_get_custom_css_post($theme_slug);
                return [
                    'css'        => (string) $css,
                    'theme_slug' => $theme_slug,
                    'post_id'    => $post ? (int) $post->ID : 0,
                ];

            case 'wp_update_custom_css':
                if (!current_user_can('unfiltered_html')) {
                    throw new \Exception('unfiltered_html capability required to update custom CSS.');
                }
                $rmcp_settings = get_option('more_mcp_settings', []);
                if (empty($rmcp_settings['allow_theme_writes'])) {
                    throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
                }
                $css = $args['css'] ?? '';
                if (!is_string($css)) throw new \Exception('css must be a string.');
                $theme_slug = isset($args['theme_slug']) ? sanitize_key($args['theme_slug']) : get_stylesheet();
                $result = wp_update_custom_css_post($css, ['stylesheet' => $theme_slug]);
                if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
                return [
                    'success'    => true,
                    'post_id'    => (int) $result->ID,
                    'theme_slug' => $theme_slug,
                    'byte_count' => strlen($css),
                ];

            case 'wp_get_widgets':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to list widgets.');
                }
                $request = new \WP_REST_Request('GET', '/wp/v2/widgets');
                if (!empty($args['sidebar'])) {
                    $request->set_param('sidebar', sanitize_key((string) $args['sidebar']));
                }
                $response = rest_do_request($request);
                if ($response->is_error()) {
                    throw new \Exception(esc_html($response->as_error()->get_error_message()));
                }
                return $response->get_data();

            case 'wp_get_sidebars':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to list sidebars.');
                }
                $request = new \WP_REST_Request('GET', '/wp/v2/sidebars');
                $response = rest_do_request($request);
                if ($response->is_error()) {
                    throw new \Exception(esc_html($response->as_error()->get_error_message()));
                }
                return $response->get_data();

            case 'wp_update_widget':
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to update widgets.');
                }
                $rmcp_settings = get_option('more_mcp_settings', []);
                if (empty($rmcp_settings['allow_theme_writes'])) {
                    throw new \Exception('Theme writes are disabled. Enable "Allow AI to modify theme appearance" under More MCP > Settings.');
                }
                $widget_id = isset($args['id']) ? sanitize_text_field((string) $args['id']) : '';
                if ($widget_id === '') {
                    throw new \Exception('Widget id is required.');
                }
                $request = new \WP_REST_Request('PUT', '/wp/v2/widgets/' . $widget_id);
                foreach (['sidebar', 'instance', 'form_data'] as $param) {
                    if (isset($args[$param])) {
                        $request->set_param($param, $args[$param]);
                    }
                }
                $response = rest_do_request($request);
                if ($response->is_error()) {
                    throw new \Exception(esc_html($response->as_error()->get_error_message()));
                }
                return $response->get_data();

            // ==================== SEO META (Yoast / Rank Math auto-detect) ====================
            case 'wp_get_seo_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
                if (!get_post($post_id)) throw new \Exception('Post not found: ' . esc_html((string) $post_id));
                // read_post on the parent gates SEO-meta reads
                // (private/draft posts' SEO meta is not public).
                if (!current_user_can('read_post', $post_id)) {
                    throw new \Exception('You do not have permission to read SEO meta on this post.');
                }
                // Slug is a WordPress-native field (post_name column) — always
                // returned regardless of whether an SEO plugin is detected.
                // Yoast and Rank Math both expose the slug in their post editors
                // alongside the SEO meta fields, so callers expect it here.
                $slug     = (string) get_post_field('post_name', $post_id);
                $detected = \More_MCP\SEO\Detector::primary();
                $response = array_merge(
                    \More_MCP\SEO\Detector::report(),
                    ['post_id' => $post_id]
                );
                if ($detected === 'none') {
                    $response['slug'] = $slug;
                    $response['note'] = 'No supported SEO plugin detected. Supported: Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, The SEO Framework. The slug field is still returned because it is a WordPress-native field.';
                    return $response;
                }
                $response = array_merge($response, \More_MCP\SEO\Meta::read('post', $post_id));
                $response['slug']             = $slug;
                $response['supported_fields'] = \More_MCP\SEO\Fields::supported($detected, 'post');
                // Raw storage alongside the normalised fields: tri-state
                // noindex collapses to a bool above, and someone auditing why a
                // URL is indexable needs to see "default" vs an explicit index.
                $response['raw']              = \More_MCP\SEO\Meta::raw('post', $post_id);
                return $response;

            case 'wp_update_seo_meta':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
                if (!get_post($post_id)) throw new \Exception('Post not found: ' . esc_html((string) $post_id));
                if (!current_user_can('edit_post', $post_id)) {
                    throw new \Exception('edit_post capability required for this post.');
                }
                // Slug is a WordPress-native field; it works regardless of which
                // (or whether any) SEO plugin is active. Only throw "no SEO
                // plugin" when the caller is actually trying to update an
                // SEO-plugin-routed field — a slug-only update on a site
                // without an SEO plugin should succeed cleanly.
                $detected   = \More_MCP\SEO\Detector::primary();
                $seo_fields = [];
                foreach (\More_MCP\SEO\Fields::LOGICAL as $seo_field_key) {
                    if (array_key_exists($seo_field_key, $args)) {
                        $seo_fields[$seo_field_key] = $args[$seo_field_key];
                    }
                }
                if (!empty($seo_fields) && $detected === 'none') {
                    throw new \Exception('No supported SEO plugin is active (Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, or The SEO Framework). Install one first, or pass only the slug field, which is WordPress-native and works without an SEO plugin.');
                }
                // Meta::write validates the whole set before writing any of it,
                // then reads each value back out of storage.
                $updated = empty($seo_fields)
                    ? []
                    : \More_MCP\SEO\Meta::write('post', $post_id, '', $seo_fields);
                // Slug (post_name) handling: we route through wp_update_post()
                // rather than a direct DB write so WordPress runs its slug-
                // uniqueness logic (appends -2, -3, etc on collision) and
                // fires the standard save_post hooks downstream tools (caches,
                // search indexes, sitemap generators) listen on. The actually-
                // saved slug is read back and returned so the caller knows
                // whether WordPress modified the requested value.
                if (array_key_exists('slug', $args)) {
                    $requested_slug = sanitize_title((string) $args['slug']);
                    if ($requested_slug === '') {
                        throw new \Exception('slug cannot be empty after sanitization. Pass a non-empty slug or omit the field.');
                    }
                    $update_result = wp_update_post([
                        'ID'        => $post_id,
                        'post_name' => $requested_slug,
                    ], true);
                    if (is_wp_error($update_result)) {
                        throw new \Exception('Slug update failed: ' . $update_result->get_error_message());
                    }
                    $saved_slug = (string) get_post_field('post_name', $post_id);
                    $updated['slug'] = $saved_slug;
                    if ($saved_slug !== $requested_slug) {
                        $updated['slug_note'] = sprintf(
                            'WordPress modified the slug to avoid a collision: requested "%s", saved "%s".',
                            $requested_slug,
                            $saved_slug
                        );
                    }
                }
                return array_merge(
                    \More_MCP\SEO\Detector::report(),
                    [
                        'post_id' => $post_id,
                        'updated' => $updated,
                    ]
                );

            case 'wp_get_term_seo_meta':
                $seo_term_id  = isset($args['term_id']) ? (int) $args['term_id'] : 0;
                $seo_taxonomy = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
                if ($seo_term_id <= 0) throw new \Exception('term_id is required.');
                if ($seo_taxonomy === '') throw new \Exception('taxonomy is required. Yoast keys its taxonomy SEO option by taxonomy, so a term ID alone does not identify a row.');
                if (!taxonomy_exists($seo_taxonomy)) {
                    throw new \Exception('Taxonomy not found: ' . esc_html($seo_taxonomy));
                }
                $seo_term = get_term($seo_term_id, $seo_taxonomy);
                if (!$seo_term || is_wp_error($seo_term)) {
                    throw new \Exception(sprintf('Term %d not found in taxonomy %s.', $seo_term_id, esc_html($seo_taxonomy)));
                }
                // manage_categories matches the capability the other term tools
                // use (wp_get_term_meta, wp_update_term).
                if (!current_user_can('manage_categories')) {
                    throw new \Exception('manage_categories capability required to read term SEO meta.');
                }
                $seo_detected = \More_MCP\SEO\Detector::primary();
                $term_response = array_merge(
                    \More_MCP\SEO\Detector::report(),
                    [
                        'term_id'  => $seo_term_id,
                        'taxonomy' => $seo_taxonomy,
                        'slug'     => (string) $seo_term->slug,
                    ]
                );
                if ($seo_detected === 'none') {
                    $term_response['note'] = 'No supported SEO plugin detected. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress.';
                    return $term_response;
                }
                if (\More_MCP\SEO\Fields::spec($seo_detected, 'term') === null) {
                    $term_response['note'] = sprintf(
                        '%s is active but More MCP does not implement term-level SEO for it. Plugins with term-level support: Yoast SEO, Rank Math, All in One SEO, SEOPress.',
                        \More_MCP\SEO\Detector::label($seo_detected)
                    );
                    $term_response['supported_fields'] = [];
                    return $term_response;
                }
                $term_response = array_merge(
                    $term_response,
                    \More_MCP\SEO\Meta::read('term', $seo_term_id, $seo_taxonomy)
                );
                $term_response['supported_fields'] = \More_MCP\SEO\Fields::supported($seo_detected, 'term');
                $term_response['raw']              = \More_MCP\SEO\Meta::raw('term', $seo_term_id, $seo_taxonomy);
                if ($seo_detected === 'yoast') {
                    // Yoast term noindex is tri-state and the boolean above
                    // cannot express "inherit". Say so rather than letting a
                    // caller read false as "explicitly indexable".
                    $term_response['noindex_note'] = 'Yoast term noindex is tri-state: an absent or "default" value inherits the taxonomy-wide setting from Yoast\'s Search Appearance settings, which is not the same as an explicit index. See raw.wpseo_noindex for the stored value.';
                }
                return $term_response;

            case 'wp_update_term_seo_meta':
                $seo_term_id  = isset($args['term_id']) ? (int) $args['term_id'] : 0;
                $seo_taxonomy = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
                if ($seo_term_id <= 0) throw new \Exception('term_id is required.');
                if ($seo_taxonomy === '') throw new \Exception('taxonomy is required. Yoast keys its taxonomy SEO option by taxonomy, so a term ID alone does not identify a row.');
                if (!taxonomy_exists($seo_taxonomy)) {
                    throw new \Exception('Taxonomy not found: ' . esc_html($seo_taxonomy));
                }
                $seo_term = get_term($seo_term_id, $seo_taxonomy);
                if (!$seo_term || is_wp_error($seo_term)) {
                    throw new \Exception(sprintf('Term %d not found in taxonomy %s.', $seo_term_id, esc_html($seo_taxonomy)));
                }
                if (!current_user_can('manage_categories')) {
                    throw new \Exception('manage_categories capability required to update term SEO meta.');
                }
                $term_seo_fields = [];
                foreach (\More_MCP\SEO\Fields::LOGICAL as $seo_field_key) {
                    if (array_key_exists($seo_field_key, $args)) {
                        $term_seo_fields[$seo_field_key] = $args[$seo_field_key];
                    }
                }
                if (empty($term_seo_fields)) {
                    throw new \Exception('No SEO fields supplied. Pass at least one of: ' . implode(', ', \More_MCP\SEO\Fields::LOGICAL) . '.');
                }
                // Meta::write refuses the whole call if any field is unsupported
                // by the active plugin at term level, rather than writing the
                // supported subset and silently dropping the rest.
                $term_updated = \More_MCP\SEO\Meta::write('term', $seo_term_id, $seo_taxonomy, $term_seo_fields);
                return array_merge(
                    \More_MCP\SEO\Detector::report(),
                    [
                        'term_id'  => $seo_term_id,
                        'taxonomy' => $seo_taxonomy,
                        'updated'  => $term_updated,
                    ]
                );

            // ==================== PERMALINK STRUCTURE ====================
            case 'wp_get_permalink_structure':
                if (!current_user_can('manage_options')) {
                    throw new \Exception('You do not have permission to read permalink structure.');
                }
                return [
                    'permalink_structure' => (string) get_option('permalink_structure', ''),
                    'category_base'       => (string) get_option('category_base', ''),
                    'tag_base'            => (string) get_option('tag_base', ''),
                ];

            case 'wp_update_permalink_structure':
                $rmcp_settings = get_option('more_mcp_settings', []);
                if (empty($rmcp_settings['allow_option_writes'])) {
                    throw new \Exception('Permalink writes are disabled. Enable "Allow AI to write WordPress options" under More MCP > Settings.');
                }
                if (!current_user_can('manage_options')) {
                    throw new \Exception('manage_options capability required.');
                }
                $structure = isset($args['structure']) ? sanitize_text_field((string) $args['structure']) : '';
                if (empty($structure)) {
                    throw new \Exception('structure is required (e.g. /%postname%/)');
                }
                $previous = (string) get_option('permalink_structure', '');
                global $wp_rewrite;
                if ($wp_rewrite) {
                    $wp_rewrite->set_permalink_structure($structure);
                    $wp_rewrite->flush_rules();
                } else {
                    update_option('permalink_structure', $structure);
                }
                return [
                    'success'  => true,
                    'previous' => $previous,
                    'current'  => (string) get_option('permalink_structure', ''),
                ];

            // ==================== POST REVISIONS ====================
            case 'wp_get_post_revisions':
                $post_id = self::resolve_post_id_arg($args);
                if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
                if (!get_post($post_id)) throw new \Exception('Post not found.');
                // revisions can contain past versions of admin-owned
                // content; gate behind the parent post's read cap.
                if (!current_user_can('read_post', $post_id)) {
                    throw new \Exception('You do not have permission to read revisions on this post.');
                }
                $limit  = max(1, min(intval($args['limit'] ?? 20), 100));
                $offset = max(0, intval($args['offset'] ?? 0));

                // 'number' is NOT a get_children() argument. wp_get_post_revisions()
                // forwards args straight to get_children(), which defaults
                // numberposts to -1 and never reads 'number' — so passing it was
                // silently ignored and every revision came back. On a page with a
                // long history that response was large enough for MCP clients to
                // reject outright, which is how it was reported (GitHub #3).
                // posts_per_page + offset are what get_posts() actually honours.
                $revisions = wp_get_post_revisions($post_id, [
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                ]);

                // Total is a separate count query rather than counting the sliced
                // result, so a caller can tell "20 of 475" from "20 of 20" and
                // know whether to page.
                $all_revision_ids = wp_get_post_revisions($post_id, [
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                ]);
                $total = is_array($all_revision_ids) ? count($all_revision_ids) : 0;

                $parent_content = (string) get_post_field('post_content', $post_id);
                $rows = array_map(function($r) use ($parent_content) {
                    $content = (string) $r->post_content;
                    // A content pointer per row, because {date, author, word_count}
                    // alone gives a caller no way to tell revisions apart. Length
                    // plus the elementor payload length is enough to spot where a
                    // page actually changed; differs_from_parent answers the most
                    // common question ("which revision is not the current one?").
                    $elementor = get_post_meta($r->ID, '_elementor_data', true);
                    return [
                        'revision_id'         => (int) $r->ID,
                        'parent_id'           => (int) $r->post_parent,
                        'author_id'           => (int) $r->post_author,
                        'author_name'         => get_the_author_meta('display_name', $r->post_author),
                        'date'                => $r->post_date,
                        'title'               => $r->post_title,
                        'word_count'          => str_word_count(wp_strip_all_tags($content)),
                        'content_length'      => strlen($content),
                        'differs_from_parent' => ($content !== $parent_content),
                        'elementor_data_length' => is_string($elementor) ? strlen($elementor) : null,
                    ];
                }, array_values($revisions));

                return [
                    'post_id'   => $post_id,
                    'total'     => $total,
                    'offset'    => $offset,
                    'limit'     => $limit,
                    'returned'  => count($rows),
                    'has_more'  => ($offset + count($rows)) < $total,
                    'revisions' => $rows,
                    'note'      => 'A revision_id is a valid post_id for the meta tools, so wp_get_post_meta(post_id=<revision_id>, key=_elementor_data) returns that revision\'s builder data. That is the practical route for recovering a page built with Elementor, since wp_restore_revision restores post_content and a builder page\'s real state lives in meta.',
                ];

            case 'wp_restore_revision':
                $revision_id = intval($args['revision_id'] ?? 0);
                if ($revision_id <= 0) throw new \Exception('revision_id is required.');
                $revision = wp_get_post_revision($revision_id);
                if (!$revision) throw new \Exception('Revision not found.');
                if (!current_user_can('edit_post', $revision->post_parent)) {
                    throw new \Exception('edit_post capability required for the parent post.');
                }
                $result = wp_restore_post_revision($revision_id);
                if (!$result) throw new \Exception('Failed to restore revision.');
                return [
                    'success'   => true,
                    'parent_id' => (int) $revision->post_parent,
                    'restored_revision_id' => $revision_id,
                ];

            default:
                // Plugin/theme lifecycle tools. Matched by explicit name list
                // rather than prefix: these share the generic wp_ prefix with
                // the core content tools, and the list must be recognised even
                // while the feature is disabled so a stale cached tools/list
                // produces "feature disabled" instead of "unknown tool".
                if ( in_array( $name, LifecycleManager::tool_names(), true ) ) {
                    return LifecycleManager::execute_tool( $name, $args );
                }

                // Gutenberg block tools (core WordPress, always available).
                // Both Blocks classes share the blocks_ prefix, so route by
                // the specific tool name each one declares rather than by
                // prefix alone.
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
                // Route to integration handlers
                if ( strpos( $name, 'wc_' ) === 0 ) {
                    return WooIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'gp_' ) === 0 ) {
                    return GPIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'sv_' ) === 0 ) {
                    return SVIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'fc_' ) === 0 ) {
                    return FCIntegration::execute_tool( $name, $args );
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
                if ( strpos( $name, 'redirection_' ) === 0 ) {
                    return RedirectionIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'analytics_' ) === 0 ) {
                    return AnalyticsIntegration::execute_tool( $name, $args );
                }
                if ( strpos( $name, 'forms_' ) === 0 ) {
                    return FormsIntegration::execute_tool( $name, $args );
                }
                throw new \Exception('Unknown tool: ' . esc_html($name));
        }
    }

    /**
     * Build menu-item update args that preserve existing values for any field
     * not in $overrides. Without this read-merge-write pattern, callers passing
     * partial args to wp_update_nav_menu_item() silently zero title/url/parent
     * on the existing item — a destructive class of bug where a well-intentioned
     * partial update erases fields the caller never touched.
     *
     * Returns an array suitable for wp_update_nav_menu_item(), or WP_Error if
     * the item doesn't exist, the merge would still destroy a non-empty
     * existing field, or the caller explicitly passed empty for a destructive
     * field that's currently non-empty.
     *
     * @param int   $item_id   Menu item post ID.
     * @param array $overrides Caller-supplied fields keyed by wp_update_nav_menu_item arg name (e.g. 'menu-item-title').
     * @return array|\WP_Error
     */
    private function build_safe_menu_item_args($item_id, $overrides) {
        $post = get_post($item_id);
        if (!$post || $post->post_type !== 'nav_menu_item') {
            return new \WP_Error('item_not_found', "Menu item {$item_id} not found.");
        }
        $existing = wp_setup_nav_menu_item($post);
        if (!$existing || is_wp_error($existing)) {
            return new \WP_Error('item_setup_failed', "Could not read menu item {$item_id} for safe merge.");
        }
        $classes = $existing->classes ?? '';
        if (is_array($classes)) {
            $classes = implode(' ', $classes);
        }
        $base = [
            'menu-item-db-id'       => (int) $item_id,
            'menu-item-object-id'   => (int) ($existing->object_id ?? 0),
            'menu-item-object'      => (string) ($existing->object ?? ''),
            'menu-item-parent-id'   => (int) ($existing->menu_item_parent ?? 0),
            'menu-item-position'    => (int) ($existing->menu_order ?? 0),
            'menu-item-type'        => (string) ($existing->type ?? 'custom'),
            'menu-item-title'       => (string) ($existing->title ?? ''),
            'menu-item-url'         => (string) ($existing->url ?? ''),
            'menu-item-description' => (string) ($existing->description ?? ''),
            'menu-item-attr-title'  => (string) ($existing->attr_title ?? ''),
            'menu-item-target'      => (string) ($existing->target ?? ''),
            'menu-item-classes'     => (string) $classes,
            'menu-item-xfn'         => (string) ($existing->xfn ?? ''),
            'menu-item-status'      => 'publish',
        ];
        // Destructive-operation guardrail: refuse if the caller explicitly
        // passed an empty value for a destructive-relevant field that's
        // currently non-empty. Catches the AI-agent failure mode where
        // partial args spell out empty strings for fields they didn't mean
        // to change. To clear a field intentionally, delete + recreate the
        // item instead.
        $destructive_fields = ['menu-item-title', 'menu-item-url'];
        foreach ($destructive_fields as $arg_key) {
            if (!array_key_exists($arg_key, $overrides)) {
                continue;
            }
            $existing_value = (string) ($base[$arg_key] ?? '');
            $new_value = (string) $overrides[$arg_key];
            if ($existing_value !== '' && $new_value === '') {
                return new \WP_Error(
                    'destructive_operation_blocked',
                    "Refused: passing empty '{$arg_key}' would zero a non-empty value on menu item {$item_id}. To clear intentionally, use wp_delete_menu_item + wp_create_menu_item."
                );
            }
        }
        return array_merge($base, $overrides);
    }

    /**
     * Set or remove the featured image on a post. media_id=0 removes it.
     */
    /**
     * Detect which SEO plugin is active.
     *
     * Retained as a thin delegate: the detection now lives in
     * More_MCP\SEO\Detector, which recognises six plugins rather than two and
     * can report multiple simultaneously. Kept because a third-party
     * integration or snippet may call it, and because it is the documented
     * shape ('yoast' | 'rankmath' | 'none') that the pre-0.1.7 tool responses
     * put in their `plugin` field.
     *
     * New code should call Detector::primary() or Detector::report() directly.
     *
     * @return string Plugin slug, or 'none'.
     */
    private function detect_seo_plugin() {
        return \More_MCP\SEO\Detector::primary();
    }

    private function apply_featured_media($post_id, $media_id) {
        if ($media_id <= 0) {
            delete_post_thumbnail($post_id);
            return;
        }
        $attachment = get_post($media_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            throw new \Exception('Media attachment not found.');
        }
        $result = set_post_thumbnail($post_id, $media_id);
        if (!$result) throw new \Exception('Failed to set featured image.');
    }

    /**
     * Download an image from a public URL and create a media attachment.
     * SSRF-hardened: blocks private/reserved IP ranges, requires https,
     * caps size and time, validates mime type against extension.
     */
    private function sideload_image_from_url($url, $filename, $title, $caption, $alt_text) {
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            throw new \Exception('URL must include scheme and host.');
        }
        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);
        $is_local_host = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $is_local_host)) {
            throw new \Exception('Only https:// URLs are allowed.');
        }
        // Resolve and block private/reserved IPs.
        if (!$is_local_host) {
            $ips = @gethostbynamel($host);
            if (empty($ips)) throw new \Exception('Could not resolve host.');
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new \Exception('URL resolves to a blocked address range.');
                }
            }
        }
        // Fetch.
        $response = wp_safe_remote_get($url, [
            'timeout'             => 10,
            'redirection'         => 3,
            'limit_response_size' => 20 * 1024 * 1024, // 20 MB
        ]);
        if (is_wp_error($response)) throw new \Exception(esc_html($response->get_error_message()));
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) throw new \Exception('Download failed with HTTP ' . intval($code) . '.');
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) throw new \Exception('Downloaded file is empty.');
        if (strlen($body) > 20 * 1024 * 1024) throw new \Exception('File exceeds 20 MB limit.');
        // Derive filename if not given.
        if (empty($filename)) {
            $path = isset($parts['path']) ? basename($parts['path']) : '';
            $filename = sanitize_file_name($path ?: 'download');
        }
        // Many image CDNs (Unsplash, Pexels, etc) serve extensionless URLs. If the filename has no
        // extension, derive one from the response Content-Type so wp_check_filetype_and_ext can validate.
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if ($content_type) {
                $content_type = strtolower(trim(explode(';', $content_type)[0]));
            }
            $mime_to_ext = [
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/bmp'  => 'bmp',
            ];
            if (isset($mime_to_ext[$content_type])) {
                $filename .= '.' . $mime_to_ext[$content_type];
            } else {
                throw new \Exception('Could not determine image type (Content-Type: ' . esc_html($content_type ?: 'unknown') . ').');
            }
        }
        return $this->sideload_image_from_bytes($body, $filename, $title, $caption, $alt_text);
    }

    /**
     * Persist raw image bytes to the uploads dir and create an attachment.
     * Validates mime against extension and rejects scriptable formats.
     */
    private function sideload_image_from_bytes($bytes, $filename, $title, $caption, $alt_text) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        if (empty($bytes))    throw new \Exception('No file contents provided.');
        if (empty($filename)) throw new \Exception('Filename is required.');
        if (strlen($bytes) > 20 * 1024 * 1024) throw new \Exception('File exceeds 20 MB limit.');

        // Reject scriptable formats outright — SVG/XML can contain script payloads.
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $blocked_ext = ['svg', 'svgz', 'html', 'htm', 'xml', 'js', 'php', 'phtml', 'phar', 'exe'];
        if (in_array($ext, $blocked_ext, true)) {
            throw new \Exception('File type .' . esc_html($ext) . ' is not allowed.');
        }

        // Write to a tmp file so we can run WP's own type/ext check.
        $tmp = wp_tempnam($filename);
        if (!$tmp) throw new \Exception('Could not create temp file.');
        if (file_put_contents($tmp, $bytes) === false) {
            wp_delete_file($tmp);
            throw new \Exception('Could not write temp file.');
        }
        $check = wp_check_filetype_and_ext($tmp, $filename);
        if (empty($check['type']) || empty($check['ext'])) {
            wp_delete_file($tmp);
            throw new \Exception('File type could not be verified or is not permitted by WordPress.');
        }
        if (strpos($check['type'], 'image/') !== 0) {
            wp_delete_file($tmp);
            throw new \Exception('Only image uploads are supported here (got ' . esc_html($check['type']) . ').');
        }

        $file_array = [
            'name'     => $check['proper_filename'] ?: $filename,
            'tmp_name' => $tmp,
        ];
        // Let WP move it to uploads and generate the attachment.
        $attachment_id = media_handle_sideload($file_array, 0, $title ?: null);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($tmp);
            throw new \Exception(esc_html($attachment_id->get_error_message()));
        }
        if (!empty($caption)) {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => $caption]);
        }
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
        return (int) $attachment_id;
    }

    /**
     * Look up all wp_options entries that appear to belong to a given plugin slug.
     * Uses a LIKE match on slug + slug_with_underscores. Sensitive keys inside
     * the returned values are redacted via redact_sensitive_keys().
     */
    private function find_plugin_options($slug) {
        global $wpdb;
        $slug = sanitize_key($slug);
        if (empty($slug)) return [];

        $variants = array_unique([
            $slug,
            str_replace('-', '_', $slug),
        ]);

        $clauses = [];
        $values  = [];
        foreach ($variants as $variant) {
            $clauses[] = '(option_name = %s OR option_name LIKE %s)';
            $values[]  = $variant;
            $values[]  = $wpdb->esc_like($variant) . '_%';
        }
        $where = implode(' OR ', $clauses);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE {$where} ORDER BY option_name ASC",
                $values
            )
        );
        if (empty($rows)) return [];

        $result = [];
        foreach ($rows as $row) {
            $value = maybe_unserialize($row->option_value);
            $result[$row->option_name] = $this->redact_sensitive_keys($value);
        }
        return $result;
    }

    /**
     * Extract a snippet of $len chars around the first occurrence of $query in $content.
     * Strips shortcodes + HTML first. Falls back to leading $len chars if no match
     * (e.g. when the term only hit the title). Multibyte-safe — WP runs utf8mb4.
     */
    private function extract_snippet($content, $query, $len) {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes((string) $content))));
        if ($text === '') return '';
        $total = mb_strlen($text);
        if ($total <= $len) return $text;

        $needle = $query;
        $pos = ($needle !== '') ? mb_stripos($text, $needle) : false;
        if ($pos === false) {
            $first_word = strtok($query, ' ');
            if ($first_word !== false && $first_word !== '') {
                $pos = mb_stripos($text, $first_word);
            }
        }
        if ($pos === false) {
            return rtrim(mb_substr($text, 0, $len)) . '…';
        }

        $start = max(0, $pos - intval($len / 2));
        $start = min($start, max(0, $total - $len));
        $excerpt = mb_substr($text, $start, $len);
        if ($start > 0) $excerpt = '…' . ltrim($excerpt);
        if ($start + $len < $total) $excerpt = rtrim($excerpt) . '…';
        return $excerpt;
    }

    /**
     * Walk a value (array/object/scalar) and replace any value whose KEY matches
     * a credential-shaped pattern with the literal string [REDACTED]. Non-array
     * scalars at the top level pass through unchanged.
     */
    private function redact_sensitive_keys($value) {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if ($this->is_sensitive_key($k)) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            $out[$k] = $this->redact_sensitive_keys($v);
        }
        return $out;
    }

    /**
     * Returns true if the given key name looks like a credential.
     * Pattern is intentionally aggressive — false positives are recoverable
     * (user can fetch the underlying option directly), false negatives leak.
     */
    private function is_sensitive_key($key) {
        if (!is_string($key) || $key === '') return false;
        $needles = [
            'password', 'passwd', 'secret', 'salt', 'token', 'nonce',
            'apikey', 'api_key', 'accesskey', 'access_key',
            'private_key', 'public_key',
            'client_secret', 'client_id', 'auth_key', 'auth_token',
            'bearer', 'license_key', 'consumer_secret', 'consumer_key',
            'webhook_secret', 'session_key', 'credentials',
        ];
        $key_lc = strtolower($key);
        foreach ($needles as $needle) {
            if (strpos($key_lc, $needle) !== false) return true;
        }
        return false;
    }

    /**
     * Hard denylist for option writes. These can never be written via MCP,
     * regardless of allowlist or admin toggle.
     */
    private function is_denylisted_option($name) {
        $name_lc = strtolower($name);

        // Hard exact-match denylist (compared case-insensitively).
        $exact = [
            'siteurl', 'home', 'db_version', 'wp_user_roles', 'cron', 'rewrite_rules',
            'wplang', 'template', 'stylesheet', 'active_plugins',
            'more_mcp_settings', // Self-protection: prevent AI from disabling its own gates.
        ];
        if (in_array($name_lc, $exact, true)) return true;

        // More MCP namespace is reserved.
        if (strpos($name_lc, 'more_mcp_') === 0) return true;

        // Pattern denylist on the option name itself.
        $patterns = [
            'secret', 'salt', 'auth_key', 'logged_in_key', 'nonce_key',
            'license_key', 'api_key', 'auth_token', 'private_key',
            'session_token', 'recovery_key',
        ];
        foreach ($patterns as $p) {
            if (strpos($name_lc, $p) !== false) return true;
        }
        return false;
    }

    // =========================================================================
    // LEGACY SSE SUPPORT (deprecated, kept for backwards compatibility)
    // =========================================================================

    /**
     * Legacy SSE endpoint handler - redirects to new streamable HTTP
     * @deprecated Use handle_mcp() instead
     */
    public function handle_sse($request) {
        // Return instructions to use the new endpoint
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

    /**
     * Legacy message handler - redirects to new endpoint
     * @deprecated Use handle_mcp() instead
     */
    public function handle_message($request) {
        // Forward to new handler
        return $this->handle_mcp($request);
    }
}
