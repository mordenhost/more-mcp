<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Tool_Profiles {

    
    private static $override_profile = null;

    
    public static function register() {
        add_filter( 'more_mcp_tools', [ __CLASS__, 'apply_profile' ], 20 );
    }

    
    public static function set_override_profile( $profile ) {
        self::$override_profile = ( is_string( $profile ) && $profile !== '' ) ? $profile : null;
    }

    
    public static function apply_profile( $tools ) {
        $profile = self::current_profile();
        if ( $profile === null ) {
            return $tools;
        }

        $prefixes = self::get_profile_prefixes( $profile );
        if ( empty( $prefixes ) ) {
            return $tools;
        }

        $filtered = [];
        foreach ( $tools as $tool ) {
            $name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
            foreach ( $prefixes as $prefix ) {
                if ( strpos( $name, $prefix ) === 0 ) {
                    $filtered[] = $tool;
                    break;
                }
            }
        }
        return $filtered;
    }

    
    private static function current_profile() {
        if ( self::$override_profile !== null ) {
            return self::$override_profile;
        }
        
        if ( ! empty( $_GET['tools'] ) ) {
            
            return sanitize_key( wp_unslash( $_GET['tools'] ) );
        }
        return null;
    }

    
    private static function get_profile_prefixes( $profile ) {
        $profiles = [
            'core' => [ 'wp_', 'more_mcp_', 'mcp_', 'seo_' ],
        ];
        
        $filtered = apply_filters( 'more_mcp_tool_profile_prefixes', $profiles );
        
        if ( ! is_array( $filtered ) ) {
            $filtered = $profiles;
        }
        return isset( $filtered[ $profile ] ) && is_array( $filtered[ $profile ] ) ? $filtered[ $profile ] : [];
    }
}
