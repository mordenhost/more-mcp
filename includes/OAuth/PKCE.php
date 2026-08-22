<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PKCE {

    public static function verify( $code_verifier, $code_challenge ) {
        $computed = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
        return hash_equals( $code_challenge, $computed );
    }
}
