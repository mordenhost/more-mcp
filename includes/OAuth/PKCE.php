<?php
namespace More_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PKCE (Proof Key for Code Exchange) utility.
 *
 * Implements S256 code challenge verification per OAuth 2.1 / RFC 7636.
 */
class PKCE {

    /**
     * Verify a PKCE code_verifier against a stored code_challenge.
     *
     * @param string $code_verifier  The verifier sent by the client in the token request.
     * @param string $code_challenge The challenge stored from the authorization request.
     * @return bool True if the verifier matches the challenge.
     */
    public static function verify( $code_verifier, $code_challenge ) {
        $computed = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
        return hash_equals( $code_challenge, $computed );
    }
}
