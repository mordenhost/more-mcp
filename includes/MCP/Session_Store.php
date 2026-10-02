<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Session_Store {

    
    const SESSION_TTL = DAY_IN_SECONDS;

    
    public static function sessions_table() {
        global $wpdb;
        return $wpdb->prefix . 'more_mcp_sessions';
    }

    
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

    
    public static function drop_tables() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
    }

    
    public static function create_session( $session_id, $auth_fingerprint = '' ) {
        global $wpdb;
        
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

    
    public static function touch_session( $session_id ) {
        global $wpdb;
        $table = self::sessions_table();
        $hash  = hash( 'sha256', $session_id );
        $now   = gmdate( 'Y-m-d H:i:s' );
        $new_expiry = gmdate( 'Y-m-d H:i:s', time() + self::SESSION_TTL );

        
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

    
    public static function get_fingerprint( $session_id ) {
        global $wpdb;
        $table = self::sessions_table();
        $hash  = hash( 'sha256', $session_id );
        $now   = gmdate( 'Y-m-d H:i:s' );

        
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

    
    public static function delete_session( $session_id ) {
        global $wpdb;
        
        return $wpdb->delete(
            self::sessions_table(),
            [ 'session_hash' => hash( 'sha256', $session_id ) ],
            [ '%s' ]
        );
    }

    
    public static function list_sessions( $limit = 50, $offset = 0 ) {
        global $wpdb;
        $table  = self::sessions_table();
        $now    = gmdate( 'Y-m-d H:i:s' );
        $limit  = max( 1, min( 200, (int) $limit ) );
        $offset = max( 0, (int) $offset );

        
        
        
        
        
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

    
    public static function count_active() {
        global $wpdb;
        $table = self::sessions_table();

        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE expires_at > %s",
                gmdate( 'Y-m-d H:i:s' )
            )
        );
    }

    
    public static function delete_by_id( $row_id ) {
        global $wpdb;
        
        return $wpdb->delete(
            self::sessions_table(),
            [ 'id' => (int) $row_id ],
            [ '%d' ]
        );
    }

    
    public static function delete_all() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        
        return (int) $wpdb->query( "DELETE FROM `{$table}`" );
    }

    
    public static function cleanup_expired() {
        global $wpdb;
        $table = esc_sql( self::sessions_table() );
        $now   = gmdate( 'Y-m-d H:i:s' );

        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE expires_at < %s",
                $now
            )
        );
    }
}
