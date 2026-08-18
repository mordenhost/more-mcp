<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Undo_Store {

    const OPTION_PREFIX = 'more_mcp_undo_';
    const DEFAULT_TTL   = 259200; 

    
    public static function store( array $snapshot ): array {
        $token      = bin2hex( random_bytes( 16 ) );
        $expires_at = time() + self::DEFAULT_TTL;

        $envelope = array_merge( $snapshot, [
            'token'      => $token,
            'created_at' => time(),
            'expires_at' => $expires_at,
        ] );

        
        
        
        $stored = base64_encode( gzcompress( wp_json_encode( $envelope ), 9 ) );
        add_option( self::OPTION_PREFIX . $token, $stored, '', 'no' );

        return [
            'token'      => $token,
            'expires_at' => $expires_at,
            'summary'    => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
            'ttl_hours'  => (int) ( self::DEFAULT_TTL / 3600 ),
        ];
    }

    
    public static function read( string $token ): ?array {
        if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
            return null;
        }
        $stored = get_option( self::OPTION_PREFIX . $token );
        if ( ! $stored ) {
            return null;
        }
        $raw = @gzuncompress( base64_decode( $stored ) );
        if ( $raw === false ) {
            return null;
        }
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }
        if ( isset( $data['expires_at'] ) && (int) $data['expires_at'] < time() ) {
            self::delete( $token );
            return null;
        }
        return $data;
    }

    
    public static function delete( string $token ): bool {
        if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
            return false;
        }
        return delete_option( self::OPTION_PREFIX . $token );
    }

    
    public static function cleanup_expired(): int {
        global $wpdb;
        $prefix = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';
        
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix
            )
        );
        $deleted = 0;
        foreach ( (array) $option_names as $option_name ) {
            $token = substr( $option_name, strlen( self::OPTION_PREFIX ) );
            
            $existed_before = (bool) get_option( $option_name );
            self::read( $token );
            $existed_after = (bool) get_option( $option_name );
            if ( $existed_before && ! $existed_after ) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
