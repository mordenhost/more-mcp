<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OAuth 2.0 Authorization Server for MCP.
 *
 * Implements the OAuth 2.1 authorization code flow with PKCE
 * per the MCP specification.
 *
 * Endpoints (served at domain root via rewrite rules):
 *  - GET  /.well-known/oauth-authorization-server  → metadata()
 *  - POST /register                                → register()
 *  - GET  /authorize                               → authorize_get()
 *  - POST /authorize                               → authorize_post()
 *  - POST /token                                   → token()
 */
class Server {

    /**
     * The current OAuth action being handled (metadata|authorize|token|register|protected_resource).
     * Set by dispatch() so error logging knows which endpoint fired.
     */
    private $current_action = 'unknown';

    /**
     * Dispatch an OAuth request based on the query var value.
     *
     * @param string $action The more_mcp_oauth query var (metadata|authorize|token|register).
     */
    public function dispatch( $action ) {
        $this->current_action = $action;
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

        // Set CORS headers for token and register endpoints (may be called cross-origin).
        if ( in_array( $action, [ 'token', 'register', 'metadata', 'protected_resource' ], true ) ) {
            header( 'Access-Control-Allow-Origin: *' );
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );

            if ( 'OPTIONS' === $request_method ) {
                status_header( 204 );
                exit;
            }
        }

        switch ( $action ) {
            case 'protected_resource':
                $this->protected_resource_metadata();
                break;

            case 'metadata':
                $this->metadata();
                break;

            case 'register':
                $this->register( $request_method );
                break;

            case 'authorize':
                if ( 'POST' === $request_method ) {
                    $this->authorize_post();
                } else {
                    $this->authorize_get();
                }
                break;

            case 'token':
                $this->token( $request_method );
                break;

            default:
                status_header( 404 );
                exit;
        }
    }

    /* ------------------------------------------------------------------
     *  GET /.well-known/oauth-protected-resource  (RFC 9728)
     *  Tells the client which authorization server protects this resource.
     * ----------------------------------------------------------------*/

    private function protected_resource_metadata() {
        $base = home_url();

        $metadata = [
            'resource'              => $base . '/wp-json/more-mcp/v1',
            'authorization_servers' => [ $base ],
            'bearer_methods_supported' => [ 'header' ],
            'scopes_supported'      => [ 'mcp:full' ],
        ];

        $this->json_response( $metadata, 200, [ 'Cache-Control' => 'public, max-age=3600' ] );
    }

    /* ------------------------------------------------------------------
     *  GET /.well-known/oauth-authorization-server
     * ----------------------------------------------------------------*/

    private function metadata() {
        $base = home_url();

        $metadata = [
            'issuer'                                => $base,
            'authorization_endpoint'                => $base . '/authorize',
            'token_endpoint'                        => $base . '/token',
            'registration_endpoint'                 => $base . '/register',
            'response_types_supported'              => [ 'code' ],
            'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
            'token_endpoint_auth_methods_supported' => [ 'none', 'client_secret_post' ],
            'code_challenge_methods_supported'       => [ 'S256' ],
            'scopes_supported'                      => [ 'mcp:full' ],
            'service_documentation'                 => 'https://example.com/more-mcp/docs/',
        ];

        $this->json_response( $metadata, 200, [ 'Cache-Control' => 'public, max-age=3600' ] );
    }

    /* ------------------------------------------------------------------
     *  POST /register  — Dynamic Client Registration (RFC 7591)
     * ----------------------------------------------------------------*/

    private function register( $request_method = 'GET' ) {
        if ( 'POST' !== $request_method ) {
            $this->json_error( 'invalid_request', 'POST method required.', 405 );
        }

        // Rate-limit registrations by IP.
        $ip            = $this->get_client_ip();
        $transient_key = 'more_mcp_reg_rate_' . md5( $ip );
        $count         = (int) get_transient( $transient_key );
        if ( $count >= 10 ) {
            $this->json_error( 'rate_limit', 'Too many registration attempts. Try again later.', 429 );
        }
        set_transient( $transient_key, $count + 1, 60 );

        // Parse body.
        $body = json_decode( file_get_contents( 'php://input' ), true );
        if ( ! is_array( $body ) ) {
            $this->json_error( 'invalid_request', 'Invalid JSON body.', 400 );
        }

        // Validate redirect_uris.
        $redirect_uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : [];
        foreach ( $redirect_uris as $uri ) {
            if ( ! $this->is_valid_redirect_uri( $uri ) ) {
                $this->json_error( 'invalid_redirect_uri', 'Redirect URIs must be localhost or HTTPS.', 400 );
            }
        }

        $client = Token_Store::register_client( $body );

        // Defensive self-heal: if our tables are reported missing, attempt
        // create_tables() once and retry. Belt-and-suspenders for installs
        // where the autoloader transiently failed and the healer got latched.
        // Only runs on the failure path — happy path is unchanged. See
        // more-mcp.php maybe_upgrade_db for the full invariant.
        if ( is_wp_error( $client ) && $client->get_error_code() === 'more_mcp_register_failed' ) {
            Token_Store::create_tables();
            if ( class_exists( '\More_MCP\MCP\Session_Store' ) ) {
                \More_MCP\MCP\Session_Store::create_tables();
            }
            $client = Token_Store::register_client( $body );
        }

        if ( is_wp_error( $client ) ) {
            $this->json_error( 'server_error', $client->get_error_message(), 500 );
        }

        $this->log_event( 'client_registered', 'Dynamic client registered.', 201, 'success' );
        $this->json_response( $client, 201 );
    }

    /* ------------------------------------------------------------------
     *  GET /authorize  — Show consent screen
     * ----------------------------------------------------------------*/

    private function authorize_get() {
        // OAuth authorize endpoint — params come from external MCP client, no WP nonce possible.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $response_type         = isset( $_GET['response_type'] ) ? sanitize_text_field( wp_unslash( $_GET['response_type'] ) ) : '';
        $client_id             = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
        $redirect_uri          = isset( $_GET['redirect_uri'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_uri'] ) ) : '';
        $code_challenge        = isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : '';
        $code_challenge_method = isset( $_GET['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) : '';
        $state                 = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        $scope                 = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : 'mcp:full';

        // Validate client FIRST — never redirect to unvalidated redirect_uri (OAuth 2.1 §4.1.2.1).
        $client = Token_Store::get_client( $client_id );
        if ( ! $client ) {
            $this->log_event( 'invalid_client', 'Unknown client_id at /authorize.', 400 );
            wp_die(
                esc_html__( 'Unknown client_id. The application has not been registered.', 'more-mcp' ),
                esc_html__( 'Authorization Error', 'more-mcp' ),
                [ 'response' => 400 ]
            );
        }

        // Validate redirect_uri BEFORE any redirects.
        if ( empty( $redirect_uri ) || ! Token_Store::validate_redirect_uri( $redirect_uri, $client ) ) {
            $this->log_event( 'invalid_redirect_uri', 'Invalid or missing redirect_uri at /authorize (GET).', 400 );
            wp_die(
                esc_html__( 'Invalid redirect_uri.', 'more-mcp' ),
                esc_html__( 'Authorization Error', 'more-mcp' ),
                [ 'response' => 400 ]
            );
        }

        // Now safe to redirect errors to the validated redirect_uri.
        if ( 'code' !== $response_type ) {
            $this->authorize_error( $redirect_uri, $state, 'unsupported_response_type', 'Only response_type=code is supported.' );
        }

        // PKCE is required.
        if ( empty( $code_challenge ) || 'S256' !== $code_challenge_method ) {
            $this->authorize_error( $redirect_uri, $state, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
        }

        // Ensure user is logged into WordPress.
        if ( ! is_user_logged_in() ) {
            // Build the full authorize URL with all params to come back after login.
            $authorize_url = add_query_arg(
                [
                    'response_type'         => $response_type,
                    'client_id'             => $client_id,
                    'redirect_uri'          => $redirect_uri,
                    'code_challenge'        => $code_challenge,
                    'code_challenge_method' => $code_challenge_method,
                    'state'                 => $state,
                    'scope'                 => $scope,
                ],
                home_url( '/authorize' )
            );

            wp_safe_redirect( wp_login_url( $authorize_url ) );
            exit;
        }

        // User is logged in — render consent screen.
        $current_user = wp_get_current_user();
        $site_name    = get_bloginfo( 'name' );

        // Pass variables to the template.
        $rmcp_oauth = [
            'client_name'           => $client['client_name'] ?? $client_id,
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => $code_challenge_method,
            'state'                 => $state,
            'scope'                 => $scope,
            'user_display_name'     => $current_user->display_name,
            'site_name'             => $site_name,
            'nonce'                 => wp_create_nonce( 'more_mcp_authorize' ),
        ];

        // Load the consent template.
        include MORE_MCP_PLUGIN_DIR . 'templates/admin/authorize.php';
        exit;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /* ------------------------------------------------------------------
     *  POST /authorize  — Process consent
     * ----------------------------------------------------------------*/

    private function authorize_post() {
        // Verify nonce.
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'more_mcp_authorize' ) ) {
            $this->log_event( 'invalid_request', 'CSRF nonce verification failed at /authorize (POST).', 403 );
            wp_die(
                esc_html__( 'Security check failed. Please try again.', 'more-mcp' ),
                esc_html__( 'Authorization Error', 'more-mcp' ),
                [ 'response' => 403 ]
            );
        }

        $redirect_uri          = isset( $_POST['redirect_uri'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_uri'] ) ) : '';
        $client_id             = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $code_challenge        = isset( $_POST['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['code_challenge'] ) ) : '';
        $code_challenge_method = isset( $_POST['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_POST['code_challenge_method'] ) ) : '';
        $state                 = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
        $scope                 = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : 'mcp:full';
        $action                = isset( $_POST['authorize_action'] ) ? sanitize_text_field( wp_unslash( $_POST['authorize_action'] ) ) : '';

        // User denied.
        if ( 'deny' === $action ) {
            $this->authorize_error( $redirect_uri, $state, 'access_denied', 'The user denied the authorization request.' );
        }

        // Validate client still exists.
        $client = Token_Store::get_client( $client_id );
        if ( ! $client ) {
            $this->log_event( 'invalid_client', 'Unknown client_id at /authorize (POST).', 400 );
            wp_die( esc_html__( 'Unknown client.', 'more-mcp' ), '', [ 'response' => 400 ] );
        }

        // Validate redirect_uri again.
        if ( empty( $redirect_uri ) || ! Token_Store::validate_redirect_uri( $redirect_uri, $client ) ) {
            $this->log_event( 'invalid_redirect_uri', 'Invalid or missing redirect_uri at /authorize (POST).', 400 );
            wp_die( esc_html__( 'Invalid redirect URI.', 'more-mcp' ), '', [ 'response' => 400 ] );
        }

        // Must be logged in.
        if ( ! is_user_logged_in() ) {
            $this->log_event( 'unauthorized', 'Authorize POST received without an authenticated WP user session.', 401 );
            wp_die( esc_html__( 'Not authenticated.', 'more-mcp' ), '', [ 'response' => 401 ] );
        }

        // Generate authorization code.
        $code = bin2hex( random_bytes( 32 ) );

        Token_Store::store_auth_code( $code, [
            'user_id'               => get_current_user_id(),
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => $code_challenge_method,
            'scope'                 => $scope,
        ] );

        // Redirect back to the client with the code.
        $redirect = add_query_arg(
            [
                'code'  => $code,
                'state' => $state,
            ],
            $redirect_uri
        );

        $this->log_event( 'code_issued', 'Authorization code issued; redirecting to client callback.', 302, 'success' );

        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- OAuth callback URI is external (e.g. claude.ai).
        wp_redirect( $redirect );
        exit;
    }

    /* ------------------------------------------------------------------
     *  POST /token  — Token exchange
     * ----------------------------------------------------------------*/

    private function token( $request_method = 'GET' ) {
        // OAuth token endpoint — external MCP clients cannot provide WP nonces.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( 'POST' !== $request_method ) {
            $this->json_error( 'invalid_request', 'POST method required.', 405 );
        }

        // Parse form-encoded body (standard OAuth).
        $grant_type = isset( $_POST['grant_type'] ) ? sanitize_text_field( wp_unslash( $_POST['grant_type'] ) ) : '';

        switch ( $grant_type ) {
            case 'authorization_code':
                $this->token_authorization_code();
                break;

            case 'refresh_token':
                $this->token_refresh();
                break;

            default:
                $this->json_error( 'unsupported_grant_type', 'Supported grant types: authorization_code, refresh_token.', 400 );
        }
    }

    /**
     * Exchange an authorization code for tokens.
     */
    private function token_authorization_code() {
        $code          = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $redirect_uri  = isset( $_POST['redirect_uri'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_uri'] ) ) : '';
        $client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $code_verifier = isset( $_POST['code_verifier'] ) ? sanitize_text_field( wp_unslash( $_POST['code_verifier'] ) ) : '';

        if ( empty( $code ) || empty( $client_id ) || empty( $code_verifier ) || empty( $redirect_uri ) ) {
            $this->json_error( 'invalid_request', 'Missing required parameters: code, client_id, code_verifier, redirect_uri.', 400 );
        }

        // Consume the code (single-use).
        $code_data = Token_Store::consume_auth_code( $code );
        if ( ! $code_data ) {
            $this->json_error( 'invalid_grant', 'Authorization code is invalid, expired, or already used.', 400 );
        }

        // Validate client_id.
        if ( ! hash_equals( $code_data['client_id'], $client_id ) ) {
            $this->json_error( 'invalid_grant', 'client_id mismatch.', 400 );
        }

        // Validate redirect_uri (must match exactly).
        if ( $redirect_uri !== $code_data['redirect_uri'] ) {
            $this->json_error( 'invalid_grant', 'redirect_uri mismatch.', 400 );
        }

        // Verify PKCE.
        if ( ! PKCE::verify( $code_verifier, $code_data['code_challenge'] ) ) {
            $this->json_error( 'invalid_grant', 'PKCE verification failed.', 400 );
        }

        // Authenticate confidential clients.
        $client = Token_Store::get_client( $client_id );
        if ( $client && 'client_secret_post' === ( $client['token_endpoint_auth_method'] ?? 'none' ) ) {
            $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
            if ( empty( $client_secret ) || ! hash_equals( $client['client_secret_hash'], hash( 'sha256', $client_secret ) ) ) {
                $this->json_error( 'invalid_client', 'Client authentication failed.', 401 );
            }
        }

        // Issue tokens.
        $tokens = Token_Store::create_token_pair( $client_id, $code_data['user_id'], $code_data['scope'] ?? '' );

        // Include resource indicator if client sent one (RFC 8707).
        $resource = isset( $_POST['resource'] ) ? sanitize_text_field( wp_unslash( $_POST['resource'] ) ) : '';
        if ( ! empty( $resource ) ) {
            $tokens['resource'] = $resource;
        }

        $this->log_event( 'token_issued', 'Access + refresh tokens issued via authorization_code grant.', 200, 'success' );

        $this->json_response( $tokens, 200, [ 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' ] );
    }

    /**
     * Refresh an access token.
     */
    private function token_refresh() {
        $refresh_token = isset( $_POST['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) ) : '';
        $client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';

        if ( empty( $refresh_token ) || empty( $client_id ) ) {
            $this->json_error( 'invalid_request', 'Missing required parameters: refresh_token, client_id.', 400 );
        }

        // Consume the refresh token (rotation — old one is revoked).
        $token_data = Token_Store::consume_refresh_token( $refresh_token );
        if ( ! $token_data ) {
            $this->json_error( 'invalid_grant', 'Refresh token is invalid, expired, or revoked.', 400 );
        }

        // Validate client_id (timing-safe).
        if ( ! hash_equals( $token_data['client_id'], $client_id ) ) {
            $this->json_error( 'invalid_grant', 'client_id mismatch.', 400 );
        }

        // Issue new token pair.
        $tokens = Token_Store::create_token_pair( $client_id, (int) $token_data['user_id'], $token_data['scope'] ?? '' );

        $this->log_event( 'token_refreshed', 'Access + refresh tokens rotated via refresh_token grant.', 200, 'success' );

        $this->json_response( $tokens, 200, [ 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' ] );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /* ------------------------------------------------------------------
     *  Helpers
     * ----------------------------------------------------------------*/

    /**
     * Send a JSON response and exit.
     *
     * Default policy: no-store, to defeat aggressive edge caches (CDN, host-
     * level fastcgi cache, generic reverse proxies) that would otherwise key
     * cache by URL only and serve a stale 4xx response to subsequent requests
     * of any method.
     * Discovery/metadata endpoints opt-in to public caching by passing their
     * own Cache-Control header in $extra_headers.
     */
    private function json_response( $data, $status = 200, $extra_headers = [] ) {
        status_header( $status );
        header( 'Content-Type: application/json; charset=utf-8' );

        if ( ! isset( $extra_headers['Cache-Control'] ) ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
            header( 'Pragma: no-cache' );
        }

        foreach ( $extra_headers as $key => $value ) {
            header( $key . ': ' . $value );
        }
        echo wp_json_encode( $data );
        exit;
    }

    /**
     * Send an OAuth error response and exit.
     */
    private function json_error( $error, $description, $status = 400 ) {
        $this->log_event( $error, $description, $status );

        $this->json_response(
            [
                'error'             => $error,
                'error_description' => $description,
            ],
            $status
        );
    }

    /**
     * Log an OAuth event to the more_mcp_logs table.
     *
     * STRICT SAFELIST: this method only logs values that are already public
     * (client_id is in URLs, grant_type is a fixed enum, our code strings
     * are hardcoded) plus the request metadata WP already exposes (IP, UA,
     * REQUEST_URI). If a future change needs more context, expand this
     * safelist deliberately — DO NOT add the raw POST body, authorization
     * code, code_verifier, client_secret, refresh_token, or access_token
     * here under any circumstance. These are credentials in flight and
     * must never reach the logs table.
     *
     * @param string $code        Short machine-readable code (error name or success verb).
     * @param string $description Human-readable message.
     * @param int    $http_status HTTP status code returned to the client.
     * @param string $log_status  'error' (default) or 'success'.
     */
    private function log_event( $code, $description, $http_status, $log_status = 'error' ) {
        global $wpdb;

        // Read public OAuth identifiers from POST first (token/register/authorize-POST),
        // fall back to GET (authorize-GET). Avoid $_REQUEST — its contents depend on PHP's
        // request_order config and can include $_COOKIE on some hosts.
        $client_id     = $this->log_pick_param( 'client_id' );
        $grant_type    = $this->log_pick_param( 'grant_type' );
        $response_type = $this->log_pick_param( 'response_type' );

        $request_meta = [
            'method'        => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
            'uri'           => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'ip'            => $this->get_client_ip(),
            'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'client_id'     => $client_id,
            'grant_type'    => $grant_type,
            'response_type' => $response_type,
        ];

        $response_meta = [
            'http_status' => (int) $http_status,
            'code'        => $code,
            'description' => $description,
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct insert is intentional; this is the logs table.
        $wpdb->insert(
            $wpdb->prefix . 'more_mcp_logs',
            [
                'mcp_server'    => 'OAuth Server',
                'action'        => 'oauth:' . $this->current_action,
                'request_data'  => wp_json_encode( $request_meta ),
                'response_data' => wp_json_encode( $response_meta ),
                'status'        => 'success' === $log_status ? 'success' : 'error',
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Read a public OAuth identifier from $_POST or $_GET (POST wins).
     * Used only by log_event() for fields we already treat as non-secret
     * (client_id, grant_type, response_type). Never use for secrets.
     */
    private function log_pick_param( $key ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- OAuth endpoints are intentionally nonceless; logged values are public identifiers.
        if ( isset( $_POST[ $key ] ) ) {
            return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        }
        if ( isset( $_GET[ $key ] ) ) {
            return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        return '';
    }

    /**
     * Redirect to the client with an error (authorize endpoint).
     */
    private function authorize_error( $redirect_uri, $state, $error, $description ) {
        // Log every authorize-error redirect; the client (e.g. claude.ai) sees this
        // as a query string but our server otherwise has no record of it.
        $this->log_event( $error, $description, 302 );

        if ( empty( $redirect_uri ) ) {
            wp_die( esc_html( $description ), esc_html__( 'Authorization Error', 'more-mcp' ), [ 'response' => 400 ] );
        }

        $redirect = add_query_arg(
            [
                'error'             => $error,
                'error_description' => $description,
                'state'             => $state,
            ],
            $redirect_uri
        );

        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- OAuth error redirect to client's registered callback URI.
        wp_redirect( $redirect );
        exit;
    }

    /**
     * Validate a redirect URI (must be localhost or HTTPS).
     */
    private function is_valid_redirect_uri( $uri ) {
        $parsed = wp_parse_url( $uri );
        if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
            return false;
        }

        $is_localhost = in_array( $parsed['host'], [ 'localhost', '127.0.0.1', '::1' ], true );
        return $is_localhost || 'https' === $parsed['scheme'];
    }

    /**
     * Get the client IP address.
     */
    private function get_client_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            return trim( $ips[0] );
        }
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
    }
}
