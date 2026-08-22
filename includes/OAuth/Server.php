<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Server {

    
    private $current_action = 'unknown';

    
    public function dispatch( $action ) {
        $this->current_action = $action;
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

        
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
            'service_documentation'                 => 'https://mordenhost.com/more-mcp/docs/',
        ];

        $this->json_response( $metadata, 200, [ 'Cache-Control' => 'public, max-age=3600' ] );
    }

    

    private function register( $request_method = 'GET' ) {
        if ( 'POST' !== $request_method ) {
            $this->json_error( 'invalid_request', 'POST method required.', 405 );
        }

        
        $ip            = $this->get_client_ip();
        $transient_key = 'more_mcp_reg_rate_' . md5( $ip );
        $count         = (int) get_transient( $transient_key );
        if ( $count >= 10 ) {
            $this->json_error( 'rate_limit', 'Too many registration attempts. Try again later.', 429 );
        }
        set_transient( $transient_key, $count + 1, 60 );

        
        $body = json_decode( file_get_contents( 'php://input' ), true );
        if ( ! is_array( $body ) ) {
            $this->json_error( 'invalid_request', 'Invalid JSON body.', 400 );
        }

        
        $redirect_uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : [];
        foreach ( $redirect_uris as $uri ) {
            if ( ! $this->is_valid_redirect_uri( $uri ) ) {
                $this->json_error( 'invalid_redirect_uri', 'Redirect URIs must be localhost or HTTPS.', 400 );
            }
        }

        $client = Token_Store::register_client( $body );

        
        
        
        
        
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

    

    private function authorize_get() {
        
        
        $response_type         = isset( $_GET['response_type'] ) ? sanitize_text_field( wp_unslash( $_GET['response_type'] ) ) : '';
        $client_id             = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
        $redirect_uri          = isset( $_GET['redirect_uri'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_uri'] ) ) : '';
        $code_challenge        = isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : '';
        $code_challenge_method = isset( $_GET['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) : '';
        $state                 = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        $scope                 = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : 'mcp:full';

        
        $client = Token_Store::get_client( $client_id );
        if ( ! $client ) {
            $this->log_event( 'invalid_client', 'Unknown client_id at /authorize.', 400 );
            wp_die(
                esc_html__( 'Unknown client_id. The application has not been registered.', 'more-mcp' ),
                esc_html__( 'Authorization Error', 'more-mcp' ),
                [ 'response' => 400 ]
            );
        }

        
        if ( empty( $redirect_uri ) || ! Token_Store::validate_redirect_uri( $redirect_uri, $client ) ) {
            $this->log_event( 'invalid_redirect_uri', 'Invalid or missing redirect_uri at /authorize (GET).', 400 );
            wp_die(
                esc_html__( 'Invalid redirect_uri.', 'more-mcp' ),
                esc_html__( 'Authorization Error', 'more-mcp' ),
                [ 'response' => 400 ]
            );
        }

        
        if ( 'code' !== $response_type ) {
            $this->authorize_error( $redirect_uri, $state, 'unsupported_response_type', 'Only response_type=code is supported.' );
        }

        
        if ( empty( $code_challenge ) || 'S256' !== $code_challenge_method ) {
            $this->authorize_error( $redirect_uri, $state, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
        }

        
        if ( ! is_user_logged_in() ) {
            
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

        
        $current_user = wp_get_current_user();
        $site_name    = get_bloginfo( 'name' );

        
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

        
        include MORE_MCP_PLUGIN_DIR . 'templates/admin/authorize.php';
        exit;
        
    }

    

    private function authorize_post() {
        
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

        
        if ( 'deny' === $action ) {
            $this->authorize_error( $redirect_uri, $state, 'access_denied', 'The user denied the authorization request.' );
        }

        
        $client = Token_Store::get_client( $client_id );
        if ( ! $client ) {
            $this->log_event( 'invalid_client', 'Unknown client_id at /authorize (POST).', 400 );
            wp_die( esc_html__( 'Unknown client.', 'more-mcp' ), '', [ 'response' => 400 ] );
        }

        
        if ( empty( $redirect_uri ) || ! Token_Store::validate_redirect_uri( $redirect_uri, $client ) ) {
            $this->log_event( 'invalid_redirect_uri', 'Invalid or missing redirect_uri at /authorize (POST).', 400 );
            wp_die( esc_html__( 'Invalid redirect URI.', 'more-mcp' ), '', [ 'response' => 400 ] );
        }

        
        if ( ! is_user_logged_in() ) {
            $this->log_event( 'unauthorized', 'Authorize POST received without an authenticated WP user session.', 401 );
            wp_die( esc_html__( 'Not authenticated.', 'more-mcp' ), '', [ 'response' => 401 ] );
        }

        
        $code = bin2hex( random_bytes( 32 ) );

        Token_Store::store_auth_code( $code, [
            'user_id'               => get_current_user_id(),
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => $code_challenge_method,
            'scope'                 => $scope,
        ] );

        
        $redirect = add_query_arg(
            [
                'code'  => $code,
                'state' => $state,
            ],
            $redirect_uri
        );

        $this->log_event( 'code_issued', 'Authorization code issued; redirecting to client callback.', 302, 'success' );

        
        wp_redirect( $redirect );
        exit;
    }

    

    private function token( $request_method = 'GET' ) {
        
        
        if ( 'POST' !== $request_method ) {
            $this->json_error( 'invalid_request', 'POST method required.', 405 );
        }

        
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

    
    private function token_authorization_code() {
        $code          = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $redirect_uri  = isset( $_POST['redirect_uri'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_uri'] ) ) : '';
        $client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $code_verifier = isset( $_POST['code_verifier'] ) ? sanitize_text_field( wp_unslash( $_POST['code_verifier'] ) ) : '';

        if ( empty( $code ) || empty( $client_id ) || empty( $code_verifier ) || empty( $redirect_uri ) ) {
            $this->json_error( 'invalid_request', 'Missing required parameters: code, client_id, code_verifier, redirect_uri.', 400 );
        }

        
        $code_data = Token_Store::consume_auth_code( $code );
        if ( ! $code_data ) {
            $this->json_error( 'invalid_grant', 'Authorization code is invalid, expired, or already used.', 400 );
        }

        
        if ( ! hash_equals( $code_data['client_id'], $client_id ) ) {
            $this->json_error( 'invalid_grant', 'client_id mismatch.', 400 );
        }

        
        if ( $redirect_uri !== $code_data['redirect_uri'] ) {
            $this->json_error( 'invalid_grant', 'redirect_uri mismatch.', 400 );
        }

        
        if ( ! PKCE::verify( $code_verifier, $code_data['code_challenge'] ) ) {
            $this->json_error( 'invalid_grant', 'PKCE verification failed.', 400 );
        }

        
        $client = Token_Store::get_client( $client_id );
        if ( $client && 'client_secret_post' === ( $client['token_endpoint_auth_method'] ?? 'none' ) ) {
            $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
            if ( empty( $client_secret ) || ! hash_equals( $client['client_secret_hash'], hash( 'sha256', $client_secret ) ) ) {
                $this->json_error( 'invalid_client', 'Client authentication failed.', 401 );
            }
        }

        
        $tokens = Token_Store::create_token_pair( $client_id, $code_data['user_id'], $code_data['scope'] ?? '' );

        
        $resource = isset( $_POST['resource'] ) ? sanitize_text_field( wp_unslash( $_POST['resource'] ) ) : '';
        if ( ! empty( $resource ) ) {
            $tokens['resource'] = $resource;
        }

        $this->log_event( 'token_issued', 'Access + refresh tokens issued via authorization_code grant.', 200, 'success' );

        $this->json_response( $tokens, 200, [ 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' ] );
    }

    
    private function token_refresh() {
        $refresh_token = isset( $_POST['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) ) : '';
        $client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';

        if ( empty( $refresh_token ) || empty( $client_id ) ) {
            $this->json_error( 'invalid_request', 'Missing required parameters: refresh_token, client_id.', 400 );
        }

        
        $token_data = Token_Store::consume_refresh_token( $refresh_token );
        if ( ! $token_data ) {
            $this->json_error( 'invalid_grant', 'Refresh token is invalid, expired, or revoked.', 400 );
        }

        
        if ( ! hash_equals( $token_data['client_id'], $client_id ) ) {
            $this->json_error( 'invalid_grant', 'client_id mismatch.', 400 );
        }

        
        $tokens = Token_Store::create_token_pair( $client_id, (int) $token_data['user_id'], $token_data['scope'] ?? '' );

        $this->log_event( 'token_refreshed', 'Access + refresh tokens rotated via refresh_token grant.', 200, 'success' );

        $this->json_response( $tokens, 200, [ 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache' ] );
        
    }

    

    
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

    
    private function log_event( $code, $description, $http_status, $log_status = 'error' ) {
        global $wpdb;

        
        
        
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

    
    private function log_pick_param( $key ) {
        
        if ( isset( $_POST[ $key ] ) ) {
            return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        }
        if ( isset( $_GET[ $key ] ) ) {
            return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        }
        
        return '';
    }

    
    private function authorize_error( $redirect_uri, $state, $error, $description ) {
        
        
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

        
        wp_redirect( $redirect );
        exit;
    }

    
    private function is_valid_redirect_uri( $uri ) {
        $parsed = wp_parse_url( $uri );
        if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
            return false;
        }

        $is_localhost = in_array( $parsed['host'], [ 'localhost', '127.0.0.1', '::1' ], true );
        return $is_localhost || 'https' === $parsed['scheme'];
    }

    
    private function get_client_ip() {
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
    }
}
