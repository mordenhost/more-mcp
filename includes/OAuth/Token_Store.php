<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Token_Store {

    
    const ACCESS_TOKEN_TTL  = 86400;      
    const REFRESH_TOKEN_TTL = 2592000;    
    const AUTH_CODE_TTL     = 600;        

    
    const ACCESS_TOKEN_TTL_CHOICES = [ 3600, 28800, 86400, 604800 ];

    
    public static function get_access_token_ttl() {
        $settings   = get_option( 'more_mcp_settings', [] );
        
        
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $configured = isset( $settings['access_token_ttl_seconds'] ) ? (int) $settings['access_token_ttl_seconds'] : 0;
        $ttl        = in_array( $configured, self::ACCESS_TOKEN_TTL_CHOICES, true ) ? $configured : self::ACCESS_TOKEN_TTL;

        
        $filtered = (int) apply_filters( 'more_mcp_access_token_ttl', $ttl );

        return $filtered > 0 ? $filtered : self::ACCESS_TOKEN_TTL;
    }

    

    
    public static function tokens_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_tokens';
    }

    
    public static function clients_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_clients';
    }

    
    public static function auth_codes_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_auth_codes';
    }

    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tokens_table     = self::tokens_table();
        $clients_table    = self::clients_table();
        $auth_codes_table = self::auth_codes_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        
        dbDelta( "CREATE TABLE IF NOT EXISTS $tokens_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_hash varchar(64) NOT NULL,
            token_type varchar(20) NOT NULL,
            client_id varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            scope varchar(255) DEFAULT '',
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            revoked tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY client_id (client_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;" );

        dbDelta( "CREATE TABLE IF NOT EXISTS $clients_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id varchar(255) NOT NULL,
            client_secret_hash varchar(64) DEFAULT NULL,
            client_name varchar(255) NOT NULL,
            redirect_uris text NOT NULL,
            grant_types varchar(255) DEFAULT 'authorization_code' NOT NULL,
            token_endpoint_auth_method varchar(50) DEFAULT 'none' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY client_id (client_id)
        ) $charset_collate;" );

        
        
        
        
        
        dbDelta( "CREATE TABLE IF NOT EXISTS $auth_codes_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code_hash varchar(64) NOT NULL,
            user_id bigint(20) NOT NULL,
            client_id varchar(255) NOT NULL,
            redirect_uri text NOT NULL,
            code_challenge varchar(255) NOT NULL,
            code_challenge_method varchar(10) NOT NULL DEFAULT 'S256',
            scope varchar(255) DEFAULT '',
            used tinyint(1) DEFAULT 0 NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;" );
    }

    
    public static function drop_tables() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $clients_table    = esc_sql( self::clients_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );
        
        $wpdb->query( "DROP TABLE IF EXISTS `{$tokens_table}`" );
        
        $wpdb->query( "DROP TABLE IF EXISTS `{$clients_table}`" );
        
        $wpdb->query( "DROP TABLE IF EXISTS `{$auth_codes_table}`" );
    }

    

    
    public static function store_auth_code( $code, array $data ) {
        global $wpdb;
        
        $wpdb->insert(
            self::auth_codes_table(),
            [
                'code_hash'             => hash( 'sha256', $code ),
                'user_id'               => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
                'client_id'             => isset( $data['client_id'] ) ? (string) $data['client_id'] : '',
                'redirect_uri'          => isset( $data['redirect_uri'] ) ? (string) $data['redirect_uri'] : '',
                'code_challenge'        => isset( $data['code_challenge'] ) ? (string) $data['code_challenge'] : '',
                'code_challenge_method' => isset( $data['code_challenge_method'] ) ? (string) $data['code_challenge_method'] : 'S256',
                'scope'                 => isset( $data['scope'] ) ? (string) $data['scope'] : '',
                'expires_at'            => gmdate( 'Y-m-d H:i:s', time() + self::AUTH_CODE_TTL ),
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    
    public static function consume_auth_code( $code ) {
        global $wpdb;
        $table = self::auth_codes_table();
        $hash  = hash( 'sha256', $code );
        $now   = gmdate( 'Y-m-d H:i:s' );

        
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table}` SET used = 1 WHERE code_hash = %s AND used = 0 AND expires_at > %s",
                $hash,
                $now
            )
        );

        if ( ! $claimed ) {
            return false; 
        }

        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, client_id, redirect_uri, code_challenge, code_challenge_method, scope FROM `{$table}` WHERE code_hash = %s LIMIT 1",
                $hash
            ),
            ARRAY_A
        );

        return $row ? $row : false;
    }

    

    
    public static function create_token_pair( $client_id, $user_id, $scope = '' ) {
        $access_token  = bin2hex( random_bytes( 32 ) );
        $refresh_token = bin2hex( random_bytes( 32 ) );
        $access_ttl    = self::get_access_token_ttl();

        self::store_token( $access_token, 'access', $client_id, $user_id, $scope, $access_ttl );
        self::store_token( $refresh_token, 'refresh', $client_id, $user_id, $scope, self::REFRESH_TOKEN_TTL );

        return [
            'access_token'  => $access_token,
            'token_type'    => 'Bearer',
            'expires_in'    => $access_ttl,
            'refresh_token' => $refresh_token,
            'scope'         => $scope,
        ];
    }

    
    private static function store_token( $raw_token, $type, $client_id, $user_id, $scope, $ttl ) {
        global $wpdb;
        
        $wpdb->insert(
            self::tokens_table(),
            [
                'token_hash' => hash( 'sha256', $raw_token ),
                'token_type' => $type,
                'client_id'  => $client_id,
                'user_id'    => $user_id,
                'scope'      => $scope,
                'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ]
        );
    }

    
    public static function validate_token( $raw_token ) {
        global $wpdb;
        $table = self::tokens_table();
        $hash  = hash( 'sha256', $raw_token );

        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE token_hash = %s AND token_type = 'access' AND revoked = 0 AND expires_at > %s LIMIT 1",
                $hash,
                gmdate( 'Y-m-d H:i:s' )
            ),
            ARRAY_A
        );

        return $row ? $row : false;
    }

    
    public static function consume_refresh_token( $raw_refresh_token ) {
        global $wpdb;
        $table = self::tokens_table();
        $hash  = hash( 'sha256', $raw_refresh_token );

        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE token_hash = %s AND token_type = 'refresh' AND revoked = 0 AND expires_at > %s LIMIT 1",
                $hash,
                gmdate( 'Y-m-d H:i:s' )
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return false;
        }

        
        
        $wpdb->update(
            $table,
            [ 'revoked' => 1 ],
            [ 'id' => $row['id'] ],
            [ '%d' ],
            [ '%d' ]
        );

        return $row;
    }

    
    public static function revoke_all_tokens() {
        global $wpdb;
        $table = self::tokens_table();
        
        $count = (int) $wpdb->query(
            "UPDATE `{$table}` SET revoked = 1 WHERE revoked = 0"
        );
        return $count;
    }

    
    public static function list_active_grants( $limit = 50, $offset = 0 ) {
        global $wpdb;
        $tokens_table  = self::tokens_table();
        $clients_table = self::clients_table();
        $now           = gmdate( 'Y-m-d H:i:s' );
        $limit         = max( 1, min( 200, (int) $limit ) );
        $offset        = max( 0, (int) $offset );

        
        
        
        
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT client_id, user_id,
                        SUM(CASE WHEN token_type = 'access'  THEN 1 ELSE 0 END) AS access_tokens,
                        SUM(CASE WHEN token_type = 'refresh' THEN 1 ELSE 0 END) AS refresh_tokens,
                        MIN(created_at) AS first_seen,
                        MAX(created_at) AS last_issued,
                        MAX(expires_at) AS expires_at
                   FROM `{$tokens_table}`
                  WHERE revoked = 0 AND expires_at > %s
                  GROUP BY client_id, user_id
                  ORDER BY last_issued DESC, client_id ASC
                  LIMIT %d OFFSET %d",
                $now,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return [];
        }

        
        
        $client_ids   = array_values( array_unique( wp_list_pluck( $rows, 'client_id' ) ) );
        $placeholders = implode( ', ', array_fill( 0, count( $client_ids ), '%s' ) );
        
        $names = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT client_id, client_name, created_at FROM `{$clients_table}` WHERE client_id IN ({$placeholders})",
                ...$client_ids
            ),
            ARRAY_A
        );

        $name_map = [];
        foreach ( (array) $names as $name_row ) {
            $name_map[ $name_row['client_id'] ] = [
                'client_name'       => (string) $name_row['client_name'],
                'client_registered' => (string) $name_row['created_at'],
            ];
        }

        $settings         = get_option( 'more_mcp_settings', [] );
        $static_client_id = is_array( $settings ) && ! empty( $settings['oauth_client_id'] )
            ? (string) $settings['oauth_client_id']
            : '';

        foreach ( $rows as &$row ) {
            $row['access_tokens']  = (int) $row['access_tokens'];
            $row['refresh_tokens'] = (int) $row['refresh_tokens'];
            $row['user_id']        = (int) $row['user_id'];

            if ( isset( $name_map[ $row['client_id'] ] ) ) {
                $row['client_name']       = $name_map[ $row['client_id'] ]['client_name'];
                $row['client_registered'] = $name_map[ $row['client_id'] ]['client_registered'];
                $row['client_missing']    = false;
            } elseif ( '' !== $static_client_id && hash_equals( $static_client_id, (string) $row['client_id'] ) ) {
                
                $row['client_name']       = __( 'Manually configured client', 'more-mcp' );
                $row['client_registered'] = '';
                $row['client_missing']    = false;
            } else {
                $row['client_name']       = '';
                $row['client_registered'] = '';
                $row['client_missing']    = true;
            }
        }
        unset( $row );

        return $rows;
    }

    
    public static function revoke_grant( $client_id, $user_id ) {
        global $wpdb;
        $table = self::tokens_table();

        
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table}` SET revoked = 1 WHERE client_id = %s AND user_id = %d AND revoked = 0",
                (string) $client_id,
                (int) $user_id
            )
        );
    }

    
    public static function count_active_grants() {
        global $wpdb;
        $table = self::tokens_table();

        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT CONCAT(client_id, ':', user_id)) FROM `{$table}` WHERE revoked = 0 AND expires_at > %s",
                gmdate( 'Y-m-d H:i:s' )
            )
        );
    }

    
    public static function revoke_tokens_for_user( $client_id, $user_id ) {
        global $wpdb;
        
        $wpdb->update(
            self::tokens_table(),
            [ 'revoked' => 1 ],
            [ 'client_id' => $client_id, 'user_id' => $user_id ],
            [ '%d' ],
            [ '%s', '%d' ]
        );
    }

    
    public static function reset_all_oauth_state() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $clients_table    = esc_sql( self::clients_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );

        
        $tokens     = (int) $wpdb->query( "DELETE FROM `{$tokens_table}`" ); 
        $clients    = (int) $wpdb->query( "DELETE FROM `{$clients_table}`" ); 
        $auth_codes = (int) $wpdb->query( "DELETE FROM `{$auth_codes_table}`" ); 

        
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_authcode_%' OR option_name LIKE '_transient_timeout_more_mcp_authcode_%'" ); 

        
        
        
        $static_creds_cleared = 0;
        $settings             = get_option( 'more_mcp_settings', [] );
        if ( is_array( $settings ) && ( ! empty( $settings['oauth_client_id'] ) || ! empty( $settings['oauth_client_secret'] ) ) ) {
            $settings['oauth_client_id']     = '';
            $settings['oauth_client_secret'] = '';
            update_option( 'more_mcp_settings', $settings );
            $static_creds_cleared = 1;
        }

        return [
            'clients'              => $clients,
            'tokens'               => $tokens,
            'auth_codes'           => $auth_codes,
            'static_creds_cleared' => $static_creds_cleared,
        ];
    }

    
    public static function cleanup_expired() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );
        $now              = gmdate( 'Y-m-d H:i:s' );

        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$tokens_table}` WHERE revoked = 1 OR expires_at < %s",
                $now
            )
        );

        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$auth_codes_table}` WHERE used = 1 OR expires_at < %s",
                $now
            )
        );
    }

    

    
    public static function register_client( array $data ) {
        global $wpdb;

        $client_id = 'rmcp_' . bin2hex( random_bytes( 16 ) );

        $client_secret      = null;
        $client_secret_hash = null;
        $auth_method        = isset( $data['token_endpoint_auth_method'] ) ? sanitize_text_field( $data['token_endpoint_auth_method'] ) : 'none';

        if ( 'client_secret_post' === $auth_method ) {
            $client_secret      = bin2hex( random_bytes( 32 ) );
            $client_secret_hash = hash( 'sha256', $client_secret );
        }

        $redirect_uris = isset( $data['redirect_uris'] ) && is_array( $data['redirect_uris'] )
            ? array_map( 'sanitize_url', $data['redirect_uris'] )
            : [];

        $client_name = isset( $data['client_name'] ) ? sanitize_text_field( $data['client_name'] ) : 'MCP Client';
        $grant_types = isset( $data['grant_types'] ) && is_array( $data['grant_types'] )
            ? sanitize_text_field( implode( ' ', $data['grant_types'] ) )
            : 'authorization_code';

        
        $inserted = $wpdb->insert(
            self::clients_table(),
            [
                'client_id'                  => $client_id,
                'client_secret_hash'         => $client_secret_hash,
                'client_name'                => $client_name,
                'redirect_uris'              => wp_json_encode( $redirect_uris ),
                'grant_types'                => $grant_types,
                'token_endpoint_auth_method' => $auth_method,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            return new \WP_Error(
                'more_mcp_register_failed',
                'Failed to persist client registration. The OAuth tables may be missing — deactivate and reactivate More MCP to recreate them.',
                [ 'db_error' => $wpdb->last_error ]
            );
        }

        $result = [
            'client_id'                  => $client_id,
            'client_name'                => $client_name,
            'redirect_uris'              => $redirect_uris,
            'grant_types'                => explode( ' ', $grant_types ),
            'token_endpoint_auth_method' => $auth_method,
            'response_types'             => [ 'code' ],
            'client_id_issued_at'        => time(),
        ];

        if ( $client_secret ) {
            $result['client_secret'] = $client_secret;
        }

        return $result;
    }

    
    public static function get_client( $client_id ) {
        global $wpdb;
        $table = self::clients_table();

        
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE client_id = %s LIMIT 1", $client_id ),
            ARRAY_A
        );

        if ( $row ) {
            $row['redirect_uris'] = json_decode( $row['redirect_uris'], true ) ?: [];
            return $row;
        }

        
        $settings = get_option( 'more_mcp_settings', [] );
        if ( ! empty( $settings['oauth_client_id'] ) && hash_equals( $settings['oauth_client_id'], $client_id ) ) {
            return [
                'client_id'                  => $settings['oauth_client_id'],
                'client_secret_hash'         => ! empty( $settings['oauth_client_secret'] ) ? hash( 'sha256', $settings['oauth_client_secret'] ) : null,
                'client_name'                => get_bloginfo( 'name' ) . ' (static)',
                'redirect_uris'              => [], 
                'grant_types'                => 'authorization_code',
                'token_endpoint_auth_method' => ! empty( $settings['oauth_client_secret'] ) ? 'client_secret_post' : 'none',
                'is_static'                  => true,
            ];
        }

        return false;
    }

    
    public static function validate_redirect_uri( $redirect_uri, $client ) {
        
        $parsed = wp_parse_url( $redirect_uri );
        if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
            return false;
        }

        $is_localhost = in_array( $parsed['host'], [ 'localhost', '127.0.0.1', '::1' ], true );
        if ( ! $is_localhost && 'https' !== $parsed['scheme'] ) {
            return false;
        }

        
        if ( ! empty( $client['is_static'] ) ) {
            return true;
        }

        
        $registered = $client['redirect_uris'] ?? [];
        if ( empty( $registered ) ) {
            return true; 
        }

        return in_array( $redirect_uri, $registered, true );
    }
}
