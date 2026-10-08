<?php
namespace More_MCP\Platform;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Url_Guard {

    public static function validate_external_url( $url ) {
        $parsed = wp_parse_url( $url );

        if ( empty( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
            return new \WP_Error( 'invalid_url_scheme', __( 'Only HTTP and HTTPS URLs are allowed.', 'mordenhost-mcp-server' ) );
        }

        if ( empty( $parsed['host'] ) ) {
            return new \WP_Error( 'invalid_url_host', __( 'URL must include a hostname.', 'mordenhost-mcp-server' ) );
        }

        $host = $parsed['host'];

        $blocked = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
        if ( in_array( strtolower( $host ), $blocked, true ) ) {
            return new \WP_Error( 'blocked_url', __( 'Localhost and loopback addresses are not allowed.', 'mordenhost-mcp-server' ) );
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return new \WP_Error( 'blocked_url', __( 'Private and reserved IP addresses are not allowed.', 'mordenhost-mcp-server' ) );
            }
            return true;
        }

        if ( self::HOSTNAME_BLOCKED === self::url_resolves_to_blocked_address( $host ) ) {
            return new \WP_Error( 'blocked_url', __( 'The URL hostname resolves to a private or reserved address.', 'mordenhost-mcp-server' ) );
        }

        return true;
    }

    const HOSTNAME_OK           = 'ok';
    const HOSTNAME_UNRESOLVED   = 'unresolvable';
    const HOSTNAME_BLOCKED      = 'blocked';

    public static function url_resolves_to_blocked_address( $host ) {
        $host = strtolower( trim( (string) $host ) );

        if ( '' === $host ) {
            return self::HOSTNAME_UNRESOLVED;
        }

        if ( '[' === $host[0] && ']' === substr( $host, -1 ) ) {
            $host = substr( $host, 1, -1 );
        }

        

        if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
            $addresses = array( $host );
        } else {
            $addresses = @gethostbynamel( $host );
            if ( ! is_array( $addresses ) ) {
                $addresses = array();
            }

            $aaaa = function_exists( 'dns_get_record' )
                ? @dns_get_record( $host, DNS_AAAA )
                : false;
            if ( is_array( $aaaa ) ) {
                foreach ( $aaaa as $record ) {
                    if ( ! empty( $record['ipv6'] ) ) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        if ( empty( $addresses ) ) {
            return self::HOSTNAME_UNRESOLVED;
        }

        foreach ( $addresses as $address ) {
            if ( preg_match( '/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', (string) $address, $m ) ) {
                $address = $m[1];
            }
            if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return self::HOSTNAME_BLOCKED;
            }
        }

        return self::HOSTNAME_OK;
    }
}
