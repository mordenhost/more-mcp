<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MCP Session Store.
 *
 * DB-backed storage for MCP session state.
 *
 * When an object cache drop-in (object-cache.php) is active, set_transient()
 * writes to the cache layer instead of wp_options. Some cache backends evict
 * keys between requests, so a session that writes successfully reads back as
 * `false` milliseconds later — every MCP request after `initialize` returns
 * 404 "Session not found". Direct DB storage with sha256-hashed lookup gives
 * reliable persistence regardless of which cache backend (if any) is active.
 */
class Session_Store {

    /** Default session lifetime in seconds (24h sliding window). */
    const SESSION_TTL = DAY_IN_SECONDS;

    /**
     * Get the sessions table name.
     */
    public static function sessions_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_sessions';
    }

    /**
     * Create the sessions table. Called from activation AND from the runtime
     * migration check in more-mcp.php. Idempotent.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table           = self::sessions_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_hash varchar(64) NOT NULL,
            auth_fingerprint varchar(64) NOT NULL DEFAULT '',
            last_event_id bigint(20) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_seen_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_hash (session_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;" );
    }

    /**
     * Drop the sessions table. Called from uninstall.
     */
    public static function drop_tables() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }

    /**
     * Persist a new session.
     *
     * Hashes the raw session_id before storage. The caller keeps the plaintext
     * to return in the Mcp-Session-Id response header; we only need the hash
     * for lookups. Same defense-in-depth pattern Token_Store uses for auth
     * codes and access tokens — if the table is ever leaked, attackers can't
     * replay the session IDs.
     *
     * @param string $session_id       Raw session ID (caller-generated).
     * @param string $auth_fingerprint sha256 of the credentials that opened the session.
     * @return int|false Rows affected (1) on insert, false on failure.
     */
    public static function create_session( $session_id, $auth_fingerprint = '' ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct insert.
        return $wpdb->insert(
            self::sessions_table(),
            [
                'session_hash'     => hash( 'sha256', $session_id ),
                'auth_fingerprint' => (string) $auth_fingerprint,
                'last_event_id'    => 0,
                'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL ),
            ],
            [ '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * Check that a session is valid and refresh its TTL.
     *
     * Two-step SELECT-then-UPDATE. We can't collapse this to a single UPDATE
     * because datetime columns are second-resolution: if two MCP requests
     * for the same session arrive in the same wall-clock second, both
     * UPDATEs would set last_seen_at and expires_at to identical values, and
     * MySQL would report 0 affected rows even though the row exists — which
     * is indistinguishable from "session not found." The SELECT confirms
     * existence first, then the UPDATE slides the TTL forward.
     *
     * @param string $session_id Raw session ID.
     * @return bool True if the session was valid (and was refreshed).
     */
    public static function touch_session( $session_id ) {
        global $wpdb;
        $table = self::sessions_table();
        $hash  = hash( 'sha256', $session_id );
        $now   = gmdate( 'Y-m-d H:i:s' );
        $new_expiry = gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper.
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM `{$table}` WHERE session_hash = %s AND expires_at > %s LIMIT 1",
                $hash,
                $now
            )
        );
        if ( ! $exists ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table}` SET last_seen_at = %s, expires_at = %s WHERE session_hash = %s",
                $now,
                $new_expiry,
                $hash
            )
        );

        return true;
    }

    /**
     * Read the auth_fingerprint stored alongside a session.
     *
     * Used by the credential-binding check in Server.php — every MCP request
     * after initialize must come from the same credentials that opened the
     * session, so a leaked Mcp-Session-Id alone can't be replayed across
     * auth contexts.
     *
     * @param string $session_id Raw session ID.
     * @return string|null The fingerprint, or null if the session doesn't exist or is expired.
     */
    public static function get_fingerprint( $session_id ) {
        global $wpdb;
        $table = self::sessions_table();
        $hash  = hash( 'sha256', $session_id );
        $now   = gmdate( 'Y-m-d H:i:s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT auth_fingerprint FROM `{$table}` WHERE session_hash = %s AND expires_at > %s LIMIT 1",
                $hash,
                $now
            ),
            ARRAY_A
        );

        return $row ? (string) $row['auth_fingerprint'] : null;
    }

    /**
     * Delete a single session (client-initiated termination via DELETE).
     *
     * @param string $session_id Raw session ID.
     * @return int|false Rows affected.
     */
    public static function delete_session( $session_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct delete.
        return $wpdb->delete(
            self::sessions_table(),
            [ 'session_hash' => hash( 'sha256', $session_id ) ],
            [ '%s' ]
        );
    }

    /**
     * List active (unexpired) transport sessions, newest activity first.
     *
     * These are MCP Streamable-HTTP sessions, which are a different thing from
     * the OAuth grants listed by Token_Store::list_active_grants() — one grant
     * can open many sessions over its lifetime, and an API-key client opens
     * sessions without any OAuth grant at all. The admin screen shows both, so
     * the distinction has to survive into the UI rather than being flattened.
     *
     * The raw session_id is NOT recoverable here — only its sha256 hash is
     * stored (see create_session). Rows are therefore keyed by the row `id` for
     * revocation, and the hash is exposed only as a short prefix so an admin can
     * tell two rows apart without the value being replayable.
     *
     * @param int $limit  Maximum sessions to return.
     * @param int $offset Sessions to skip before returning results.
     * @return array Rows: id, hash_prefix, auth_fingerprint_prefix, last_event_id,
     *               created_at, last_seen_at, expires_at.
     */
    public static function list_sessions( $limit = 50, $offset = 0 ) {
        global $wpdb;
        $table  = self::sessions_table();
        $now    = gmdate( 'Y-m-d H:i:s' );
        $limit  = max( 1, min( 200, (int) $limit ) );
        $offset = max( 0, (int) $offset );

        // id breaks ties on last_seen_at for the same reason the grants query
        // orders by client_id: datetime columns are second-resolution, and two
        // sessions touched in the same second would otherwise be free to swap
        // places between the queries a pager makes.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_hash, auth_fingerprint, last_event_id, created_at, last_seen_at, expires_at
                   FROM `{$table}`
                  WHERE expires_at > %s
                  ORDER BY last_seen_at DESC, id DESC
                  LIMIT %d OFFSET %d",
                $now,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $row ) {
            $out[] = [
                'id'                      => (int) $row['id'],
                // First 12 hex chars only. Enough to distinguish rows in a list;
                // not enough to reconstruct the hash, let alone the session ID.
                'hash_prefix'             => substr( (string) $row['session_hash'], 0, 12 ),
                'auth_fingerprint_prefix' => substr( (string) $row['auth_fingerprint'], 0, 12 ),
                'last_event_id'           => (int) $row['last_event_id'],
                'created_at'              => (string) $row['created_at'],
                'last_seen_at'            => (string) $row['last_seen_at'],
                'expires_at'              => (string) $row['expires_at'],
            ];
        }

        return $out;
    }

    /**
     * Count active (unexpired) transport sessions.
     *
     * @return int
     */
    public static function count_active() {
        global $wpdb;
        $table = self::sessions_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from safe helper.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE expires_at > %s",
                gmdate( 'Y-m-d H:i:s' )
            )
        );
    }

    /**
     * Delete one session by its table row ID.
     *
     * Row ID rather than session ID because the admin UI never sees a raw
     * session ID — only the stored hash exists server-side, and even that is
     * surfaced as a truncated prefix. A client whose session is deleted here
     * gets a 404 on its next request and re-initializes, which is the intended
     * "kick this one connection" behavior.
     *
     * @param int $row_id Sessions-table primary key.
     * @return int|false Rows affected (1 on success, 0 if no such row), or false on DB error.
     */
    public static function delete_by_id( $row_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct delete.
        return $wpdb->delete(
            self::sessions_table(),
            [ 'id' => (int) $row_id ],
            [ '%d' ]
        );
    }

    /**
     * Delete every session row, expired or not.
     *
     * Powers the admin "End all sessions" action. This clears transport state
     * only — it does not revoke credentials, because every MCP request
     * authenticates before its session is looked up. A client holding a valid
     * token simply re-initializes and gets a fresh session, which is exactly what
     * is wanted when the symptom is a stuck or unrecoverable session rather than
     * a credential that should no longer work.
     *
     * @return int Rows deleted.
     */
    public static function delete_all() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->query( "DELETE FROM `{$table}`" );
    }

    /**
     * Delete all expired sessions. Hooked to the existing more_mcp_token_cleanup
     * daily cron action (Token_Store::cleanup_expired runs on the same hook).
     */
    public static function cleanup_expired() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        $now   = gmdate( 'Y-m-d H:i:s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE expires_at < %s",
                $now
            )
        );
    }
}
