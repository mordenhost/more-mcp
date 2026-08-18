<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OAuth Token Store.
 *
 * Handles CRUD for access/refresh tokens, authorization codes,
 * and dynamically registered OAuth clients.
 */
class Token_Store {

    /** Token lifetimes in seconds. */
    const ACCESS_TOKEN_TTL  = 86400;      // 24 hours — default fallback; site-configurable via get_access_token_ttl()
    const REFRESH_TOKEN_TTL = 2592000;    // 30 days
    const AUTH_CODE_TTL     = 600;        // 10 minutes

    /** Whitelist of access token TTL values selectable in Settings → OAuth. */
    const ACCESS_TOKEN_TTL_CHOICES = [ 3600, 28800, 86400, 604800 ];

    /**
     * Return the effective access-token TTL in seconds.
     *
     * Reads more_mcp_settings['access_token_ttl_seconds'] (whitelist-guarded),
     * falls back to ACCESS_TOKEN_TTL when unset or invalid, then exposes a
     * more_mcp_access_token_ttl filter for per-role / per-client policies.
     * The filter has final say — matches WP convention where filters override
     * options.
     *
     * @return int TTL in seconds.
     */
    public static function get_access_token_ttl() {
        $settings   = get_option( 'more_mcp_settings', [] );
        // Guard against a corrupted option value (string, null, object). Any non-array
        // shape falls back to the constant default rather than blowing up on array access.
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $configured = isset( $settings['access_token_ttl_seconds'] ) ? (int) $settings['access_token_ttl_seconds'] : 0;
        $ttl        = in_array( $configured, self::ACCESS_TOKEN_TTL_CHOICES, true ) ? $configured : self::ACCESS_TOKEN_TTL;

        /**
         * Filter the access-token TTL in seconds.
         *
         * Overrides the site-owner UI selection. Return any positive integer.
         *
         * @param int $ttl Effective TTL after option lookup + whitelist check.
         */
        $filtered = (int) apply_filters( 'more_mcp_access_token_ttl', $ttl );

        return $filtered > 0 ? $filtered : self::ACCESS_TOKEN_TTL;
    }

    /* ------------------------------------------------------------------
     *  Table helpers
     * ----------------------------------------------------------------*/

    /**
     * Get the tokens table name.
     */
    public static function tokens_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_tokens';
    }

    /**
     * Get the clients table name.
     */
    public static function clients_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_clients';
    }

    /**
     * Get the authorization codes table name.
     */
    public static function auth_codes_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_oauth_auth_codes';
    }

    /**
     * Create all OAuth tables. Called from plugin activation AND from the
     * runtime migration check in more-mcp.php (which fires on plugins_loaded
     * when more_mcp_db_version doesn't match MORE_MCP_VERSION). Idempotent.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tokens_table     = self::tokens_table();
        $clients_table    = self::clients_table();
        $auth_codes_table = self::auth_codes_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta needs each CREATE TABLE as a separate call.
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

        // Authorization codes in a dedicated table (not transients). Object-
        // cache drop-ins on some host stacks silently evict transient keys
        // between /authorize and /token, breaking the OAuth handshake. Direct
        // DB storage with sha256-hashed lookup gives reliable consume semantics
        // regardless of which cache backend is active.
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

    /**
     * Drop OAuth tables. Called from uninstall.
     */
    public static function drop_tables() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $clients_table    = esc_sql( self::clients_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$tokens_table}`" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$clients_table}`" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$auth_codes_table}`" );
    }

    /* ------------------------------------------------------------------
     *  Authorization codes  (DB-backed — see create_tables comment)
     * ----------------------------------------------------------------*/

    /**
     * Store an authorization code.
     *
     * Stores only the sha256 hash of the code, not the code itself — same
     * defense-in-depth pattern we use for access/refresh tokens. If the table
     * is ever leaked, attackers can't replay the codes (and they're expired
     * within 10 minutes anyway).
     *
     * @param string $code The raw authorization code (caller keeps the plaintext).
     * @param array  $data Payload: user_id, client_id, redirect_uri, code_challenge, code_challenge_method, scope.
     */
    public static function store_auth_code( $code, array $data ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct insert.
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

    /**
     * Consume an authorization code (single-use, atomic).
     *
     * Two-step pattern: an atomic UPDATE marks the row used IFF it exists,
     * is unused, and is unexpired — returning affected_rows = 1 if we won the
     * race (single-row MySQL lock semantics make this safe for concurrent
     * /token POSTs). If we won, a SELECT reads the payload. If two requests
     * arrive simultaneously with the same code, exactly one will get the
     * payload; the other gets false.
     *
     * @param string $code The raw code presented by the client.
     * @return array|false The stored payload, or false if invalid/expired/already-used.
     */
    public static function consume_auth_code( $code ) {
        global $wpdb;
        $table = self::auth_codes_table();
        $hash  = hash( 'sha256', $code );
        $now   = gmdate( 'Y-m-d H:i:s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table}` SET used = 1 WHERE code_hash = %s AND used = 0 AND expires_at > %s",
                $hash,
                $now
            )
        );

        if ( ! $claimed ) {
            return false; // Code doesn't exist, already consumed, or expired.
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, client_id, redirect_uri, code_challenge, code_challenge_method, scope FROM `{$table}` WHERE code_hash = %s LIMIT 1",
                $hash
            ),
            ARRAY_A
        );

        return $row ? $row : false;
    }

    /* ------------------------------------------------------------------
     *  Access / Refresh tokens
     * ----------------------------------------------------------------*/

    /**
     * Generate and store a token pair (access + refresh).
     *
     * @param string $client_id WordPress OAuth client ID.
     * @param int    $user_id   WordPress user ID.
     * @param string $scope     Space-separated scopes.
     * @return array [ 'access_token' => …, 'refresh_token' => …, 'expires_in' => … ]
     */
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

    /**
     * Store a single token (hashed) in the database.
     */
    private static function store_token( $raw_token, $type, $client_id, $user_id, $scope, $ttl ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    /**
     * Validate a Bearer token.
     *
     * @param string $raw_token The raw access token from the Authorization header.
     * @return array|false Token row (with user_id, client_id, scope) or false.
     */
    public static function validate_token( $raw_token ) {
        global $wpdb;
        $table = self::tokens_table();
        $hash  = hash( 'sha256', $raw_token );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
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

    /**
     * Validate and consume a refresh token (token rotation).
     *
     * @param string $raw_refresh_token The raw refresh token.
     * @return array|false Token row or false.
     */
    public static function consume_refresh_token( $raw_refresh_token ) {
        global $wpdb;
        $table = self::tokens_table();
        $hash  = hash( 'sha256', $raw_refresh_token );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
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

        // Revoke the old refresh token (rotation).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            $table,
            [ 'revoked' => 1 ],
            [ 'id' => $row['id'] ],
            [ '%d' ],
            [ '%d' ]
        );

        return $row;
    }

    /**
     * Soft-delete every unrevoked access + refresh token in one operation.
     *
     * Powers the "Revoke all active sessions" button on Settings → OAuth.
     * Uses soft-delete (revoked = 1) rather than hard truncate so future
     * audit-log surfaces can still inspect what was revoked. Does not touch
     * registered clients or in-flight authorization codes.
     *
     * @return int Number of token rows revoked.
     */
    public static function revoke_all_tokens() {
        global $wpdb;
        $table = self::tokens_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        $count = (int) $wpdb->query(
            "UPDATE `{$table}` SET revoked = 1 WHERE revoked = 0"
        );
        return $count;
    }

    /**
     * List the active OAuth grants, one row per client_id + user_id pair.
     *
     * "Grant" rather than "session" is deliberate: a single connected MCP client
     * holds an access token AND a refresh token, and rotates the access token on
     * every refresh, so counting token rows would report one client as several
     * sessions and the number would climb on its own. Grouping by the pair that
     * the session fingerprint is bound to (see Server::validate_bearer_token)
     * gives one row per thing an admin would recognize as "a connected client".
     *
     * The client_name comes from the clients table in a second query rather than
     * a JOIN, because a grant can outlive its client row — a client deleted by
     * Reset OAuth State leaves tokens behind until the daily cleanup runs, and a
     * JOIN would silently hide those rows from the very screen an admin opens to
     * revoke them.
     *
     * @param int $limit  Maximum grants to return.
     * @param int $offset Grants to skip before returning results. Paired with the
     *                    stable ORDER BY below so paging cannot show a row twice.
     * @return array List of grant rows: client_id, client_name, user_id, access_tokens,
     *               refresh_tokens, first_seen, last_issued, expires_at.
     */
    public static function list_active_grants( $limit = 50, $offset = 0 ) {
        global $wpdb;
        $tokens_table  = self::tokens_table();
        $clients_table = self::clients_table();
        $now           = gmdate( 'Y-m-d H:i:s' );
        $limit         = max( 1, min( 200, (int) $limit ) );
        $offset        = max( 0, (int) $offset );

        // ORDER BY last_issued alone is not a total order — several grants can
        // share a timestamp to the second, and MySQL may then return them in a
        // different order between the two queries a pager makes. client_id breaks
        // the tie so page 2 cannot repeat or skip a row from page 1.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
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

        // Resolve display names for the client IDs actually present. Built as a
        // placeholder list so the IN() clause stays prepared.
        $client_ids   = array_values( array_unique( wp_list_pluck( $rows, 'client_id' ) ) );
        $placeholders = implode( ', ', array_fill( 0, count( $client_ids ), '%s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper; values are prepared placeholders.
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
                // Manually configured static client — never has a clients-table row.
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

    /**
     * Revoke every token belonging to one client_id + user_id grant.
     *
     * The per-grant counterpart to revoke_all_tokens(): disconnects one client
     * without kicking every other connected client off the site. Soft-delete for
     * the same reason revoke_all_tokens() uses it — the rows stay inspectable
     * until the daily cleanup cron removes them.
     *
     * @param string $client_id OAuth client ID.
     * @param int    $user_id   WordPress user ID the grant was issued to.
     * @return int Number of token rows revoked.
     */
    public static function revoke_grant( $client_id, $user_id ) {
        global $wpdb;
        $table = self::tokens_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table}` SET revoked = 1 WHERE client_id = %s AND user_id = %d AND revoked = 0",
                (string) $client_id,
                (int) $user_id
            )
        );
    }

    /**
     * Count the distinct active grants. Cheaper than list_active_grants() when
     * only the headline number is needed.
     *
     * @return int
     */
    public static function count_active_grants() {
        global $wpdb;
        $table = self::tokens_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT CONCAT(client_id, ':', user_id)) FROM `{$table}` WHERE revoked = 0 AND expires_at > %s",
                gmdate( 'Y-m-d H:i:s' )
            )
        );
    }

    /**
     * Revoke all tokens for a client+user combination.
     */
    public static function revoke_tokens_for_user( $client_id, $user_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            self::tokens_table(),
            [ 'revoked' => 1 ],
            [ 'client_id' => $client_id, 'user_id' => $user_id ],
            [ '%d' ],
            [ '%s', '%d' ]
        );
    }

    /**
     * Wipe ALL OAuth state — registered clients, issued tokens, and pending
     * auth codes — in one operation. Used by the admin "Reset OAuth State"
     * button to recover from stuck handshakes without resorting to wp-cli SQL.
     *
     * All connected MCP clients will need to re-authorize after this runs.
     * Does NOT touch settings (API key, allow-lists), Activity Log entries,
     * or any other plugin state.
     *
     * @return array Counts of deleted rows: [ 'clients' => N, 'tokens' => N, 'auth_codes' => N ].
     */
    public static function reset_all_oauth_state() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $clients_table    = esc_sql( self::clients_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );

        // Wipe all rows in each OAuth table. Table names come from esc_sql() above; no user input. phpcs:ignore comments are per-line because the security scanner grep is line-scoped, not block-scoped.
        $tokens     = (int) $wpdb->query( "DELETE FROM `{$tokens_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $clients    = (int) $wpdb->query( "DELETE FROM `{$clients_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $auth_codes = (int) $wpdb->query( "DELETE FROM `{$auth_codes_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Belt-and-suspenders: clear any legacy in-flight authcode transients from earlier storage backends.
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_more_mcp_authcode_%' OR option_name LIKE '_transient_timeout_more_mcp_authcode_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Also clear any manually-configured static OAuth client_id / client_secret so the
        // connector falls back to Dynamic Client Registration on the next handshake.
        // Without this clear, an admin in manual-creds mode has no UI path back to DCR.
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

    /**
     * Delete expired and revoked tokens, plus expired and consumed auth codes.
     * Called by scheduled cleanup.
     */
    public static function cleanup_expired() {
        global $wpdb;
        $tokens_table     = esc_sql( self::tokens_table() );
        $auth_codes_table = esc_sql( self::auth_codes_table() );
        $now              = gmdate( 'Y-m-d H:i:s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$tokens_table}` WHERE revoked = 1 OR expires_at < %s",
                $now
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$auth_codes_table}` WHERE used = 1 OR expires_at < %s",
                $now
            )
        );
    }

    /* ------------------------------------------------------------------
     *  Dynamic client registration
     * ----------------------------------------------------------------*/

    /**
     * Register a new OAuth client.
     *
     * @param array $data Client registration data.
     * @return array|\WP_Error Stored client record on success, WP_Error if the DB write failed.
     */
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    /**
     * Look up a registered client by client_id.
     *
     * Checks the database first (dynamic clients), then falls back
     * to the static client configured in plugin settings.
     *
     * @param string $client_id The client ID.
     * @return array|false Client row or false.
     */
    public static function get_client( $client_id ) {
        global $wpdb;
        $table = self::clients_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper method.
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE client_id = %s LIMIT 1", $client_id ),
            ARRAY_A
        );

        if ( $row ) {
            $row['redirect_uris'] = json_decode( $row['redirect_uris'], true ) ?: [];
            return $row;
        }

        // Check static client from settings.
        $settings = get_option( 'more_mcp_settings', [] );
        if ( ! empty( $settings['oauth_client_id'] ) && hash_equals( $settings['oauth_client_id'], $client_id ) ) {
            return [
                'client_id'                  => $settings['oauth_client_id'],
                'client_secret_hash'         => ! empty( $settings['oauth_client_secret'] ) ? hash( 'sha256', $settings['oauth_client_secret'] ) : null,
                'client_name'                => get_bloginfo( 'name' ) . ' (static)',
                'redirect_uris'              => [], // Static clients accept any localhost/HTTPS redirect.
                'grant_types'                => 'authorization_code',
                'token_endpoint_auth_method' => ! empty( $settings['oauth_client_secret'] ) ? 'client_secret_post' : 'none',
                'is_static'                  => true,
            ];
        }

        return false;
    }

    /**
     * Validate a redirect URI against a client's registered URIs.
     *
     * @param string $redirect_uri The URI to validate.
     * @param array  $client       The client record from get_client().
     * @return bool True if allowed.
     */
    public static function validate_redirect_uri( $redirect_uri, $client ) {
        // Must be localhost (any port) or HTTPS.
        $parsed = wp_parse_url( $redirect_uri );
        if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
            return false;
        }

        $is_localhost = in_array( $parsed['host'], [ 'localhost', '127.0.0.1', '::1' ], true );
        if ( ! $is_localhost && 'https' !== $parsed['scheme'] ) {
            return false;
        }

        // Static clients (from settings) accept any valid localhost/HTTPS URI.
        if ( ! empty( $client['is_static'] ) ) {
            return true;
        }

        // Dynamic clients: exact match required.
        $registered = $client['redirect_uris'] ?? [];
        if ( empty( $registered ) ) {
            return true; // No URIs registered = accept any valid one (matches Claude Desktop behavior).
        }

        return in_array( $redirect_uri, $registered, true );
    }
}
