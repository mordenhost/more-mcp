<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Endpoint tool profiles — trims the tools/list response by name prefix so
 * MCP clients that fail on large tool sets can request a curated subset by
 * appending `?tools=<profile>` to the MCP endpoint URL.
 *
 * This is a CLIENT-COMPATIBILITY affordance, not an authorization boundary. The
 * profile is chosen by the client, it is applied in Server::get_tools() only,
 * and execute_tool() does not consult it — so a client that omits a tool from
 * its list can still call that tool by name. Authorization lives in the
 * per-tool current_user_can() checks. See GitHub #12.
 *
 * Ships one stock profile:
 *   ?tools=core     — WordPress core + protocol meta-tools + SEO
 *                     (wp_*, more_mcp_*, mcp_*, seo_*), excluding integrations
 *
 * Extension points:
 *   more_mcp_tools                  — final filter over the full tool list
 *                                       (applied in Server::get_tools)
 *   more_mcp_tool_profile_prefixes  — filter to register additional profiles
 *                                       by mapping profile name → allowed
 *                                       tool-name prefixes
 */
class Tool_Profiles {

    /**
     * Programmatic profile override for CLI / test contexts where $_GET is
     * not populated. Web requests read $_GET['tools'] instead.
     */
    private static $override_profile = null;

    /**
     * Register the profile filter. Called from plugin bootstrap.
     */
    public static function register() {
        add_filter( 'more_mcp_tools', [ __CLASS__, 'apply_profile' ], 20 );
    }

    /**
     * Set the active profile explicitly, bypassing $_GET lookup. Test-only.
     *
     * @param string|null $profile Profile name, or null to reset.
     */
    public static function set_override_profile( $profile ) {
        self::$override_profile = ( is_string( $profile ) && $profile !== '' ) ? $profile : null;
    }

    /**
     * Filter callback — trims tool list to the current profile's prefix set.
     * Fail-open: unknown profile names return the unfiltered list rather
     * than an empty list, so a typo doesn't silently break the client.
     *
     * @param array $tools Tool definitions.
     * @return array Filtered tool list.
     */
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

    /**
     * Resolve the active profile name from override or $_GET.
     *
     * @return string|null Profile name or null when no profile selected.
     */
    private static function current_profile() {
        if ( self::$override_profile !== null ) {
            return self::$override_profile;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tool-list filter; profile name is sanitized and treated as opaque prefix key.
        if ( ! empty( $_GET['tools'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return sanitize_key( wp_unslash( $_GET['tools'] ) );
        }
        return null;
    }

    /**
     * Get the prefix allowlist for a profile name. Extensible via the
     * more_mcp_tool_profile_prefixes filter.
     *
     * @param string $profile Profile name.
     * @return array List of tool-name prefixes to keep.
     */
    private static function get_profile_prefixes( $profile ) {
        $profiles = [
            'core' => [ 'wp_', 'more_mcp_', 'mcp_', 'seo_' ],
        ];
        /**
         * Filter the profile → prefixes map. Pro adds custom profiles here.
         *
         * @param array<string,array<string>> $profiles Profile name → array of allowed name prefixes.
         */
        $filtered = apply_filters( 'more_mcp_tool_profile_prefixes', $profiles );
        // Reject non-array returns from misbehaving callbacks — fall back to stock profiles.
        if ( ! is_array( $filtered ) ) {
            $filtered = $profiles;
        }
        return isset( $filtered[ $profile ] ) && is_array( $filtered[ $profile ] ) ? $filtered[ $profile ] : [];
    }
}
