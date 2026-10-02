<?php
namespace More_MCP\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Well_Known_Notice {

    const TRANSIENT_KEY                = 'more_mcp_well_known_status';
    const TRANSIENT_TTL                = 12 * HOUR_IN_SECONDS;
    const USER_DISMISS_KEY             = 'more_mcp_well_known_dismissed';
    const STALE_DISMISS_KEY            = 'more_mcp_well_known_stale_dismissed';
    const HTML_BODY_DISMISS_KEY        = 'more_mcp_well_known_html_body_dismissed';
    const REGISTER_301_TRANSIENT       = 'more_mcp_register_301_status';
    const REGISTER_301_DISMISS_KEY     = 'more_mcp_register_301_dismissed';
    const IMUNIFY360_DISMISS_KEY       = 'more_mcp_imunify360_dismissed';
    const BITNINJA_DISMISS_KEY         = 'more_mcp_bitninja_dismissed';
    const PLAIN_PERMALINKS_DISMISS_KEY = 'more_mcp_plain_permalinks_dismissed';
    const SUPPORT_URL                  = 'https://mordenhost.com/more-mcp/docs/siteground-well-known-404.html';
    const STALE_SUPPORT_URL            = 'https://mordenhost.com/more-mcp/docs/stale-well-known-static-files.html';
    const HTML_BODY_SUPPORT_URL        = 'https://mordenhost.com/more-mcp/docs/well-known-served-as-html.html';
    const REGISTER_301_SUPPORT_URL     = 'https://mordenhost.com/more-mcp/docs/oauth-register-trailing-slash-301.html';
    const IMUNIFY360_SUPPORT_URL       = 'https://mordenhost.com/more-mcp/docs/imunify360-blocks-mcp.html';
    const BITNINJA_SUPPORT_URL         = 'https://mordenhost.com/more-mcp/docs/bitninja-webshield-blocks-mcp.html';
    const PLAIN_PERMALINKS_SUPPORT_URL = 'https://mordenhost.com/more-mcp/docs/plain-permalinks-blocks-discovery.html';

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'maybe_render_notice' ] );
        add_action( 'admin_init', [ $this, 'maybe_dismiss' ] );
        add_action( 'update_option_more_mcp_settings', [ $this, 'invalidate_check' ] );
        
        
        
        
        add_action( 'update_option_permalink_structure', [ $this, 'invalidate_check' ] );
    }

    
    public function maybe_render_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        $allowed_screens = [
            'plugins',
            'toplevel_page_more-mcp',
            'more-mcp_page_more-mcp-logs',
        ];
        if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $settings = get_option( 'more_mcp_settings', [] );
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        if ( $this->is_dev_host() ) {
            return;
        }

        if ( is_multisite() && ! is_main_site() ) {
            return;
        }

        
        
        
        
        
        if ( '' === (string) get_option( 'permalink_structure', '' )
            && ! get_user_meta( $user_id, self::PLAIN_PERMALINKS_DISMISS_KEY, true )
        ) {
            $this->render_plain_permalinks_notice();
            return;
        }

        $status = $this->check_well_known();

        
        
        
        
        if ( 'imunify360_blocked' === $status
            && ! get_user_meta( $user_id, self::IMUNIFY360_DISMISS_KEY, true )
        ) {
            $this->render_imunify360_notice();
            return;
        }

        
        
        
        
        
        
        
        
        if ( 'bitninja_blocked' === $status
            && ! get_user_meta( $user_id, self::BITNINJA_DISMISS_KEY, true )
        ) {
            $this->render_bitninja_notice();
            return;
        }

        if ( 'blocked' === $status
            && ! get_user_meta( $user_id, self::USER_DISMISS_KEY, true )
        ) {
            $this->render_blocked_notice();
            return;
        }

        if ( 'stale_static' === $status
            && ! get_user_meta( $user_id, self::STALE_DISMISS_KEY, true )
        ) {
            $this->render_stale_static_notice();
            return;
        }

        if ( 'body_is_html' === $status
            && ! get_user_meta( $user_id, self::HTML_BODY_DISMISS_KEY, true )
        ) {
            $this->render_html_body_notice();
            return;
        }

        
        
        
        
        if ( $this->check_register_301()
            && ! get_user_meta( $user_id, self::REGISTER_301_DISMISS_KEY, true )
        ) {
            $this->render_register_301_notice();
        }
    }

    
    private function check_well_known() {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = home_url( '/.well-known/oauth-authorization-server' );

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 5,
                'redirection' => 0,
                'sslverify'   => true,
                'user-agent'  => 'More MCP Self-Check',
            ]
        );

        if ( is_wp_error( $response ) ) {
            $status = 'unknown';
        } else {
            $code    = (int) wp_remote_retrieve_response_code( $response );
            $body    = (string) wp_remote_retrieve_body( $response );
            $headers = wp_remote_retrieve_headers( $response );
            $headers = is_array( $headers ) ? $headers : iterator_to_array( $headers );
            $status  = self::classify_response( $code, $body, $headers, rtrim( home_url(), '/' ) );
        }

        set_transient( self::TRANSIENT_KEY, $status, self::TRANSIENT_TTL );

        return $status;
    }

    
    public static function classify_response( $code, $body, array $headers, $expected_issuer ) {
        if ( 200 === $code ) {
            
            
            
            
            
            
            
            
            if ( false !== stripos( $body, 'wsidchk' )
                && false !== strpos( $body, 'webdriverCheck' )
            ) {
                return 'bitninja_blocked';
            }

            
            
            
            
            
            
            $body_head = strtolower( ltrim( $body ) );
            $html_prefixes = [ '<!doctype html', '<html', '<head', '<?xml' ];
            foreach ( $html_prefixes as $prefix ) {
                if ( 0 === strpos( $body_head, $prefix ) ) {
                    return 'body_is_html';
                }
            }

            $data = json_decode( $body, true );

            
            
            
            
            
            
            
            
            
            if ( is_array( $data )
                && isset( $data['message'] )
                && false !== stripos( (string) $data['message'], 'Imunify360' )
            ) {
                return 'imunify360_blocked';
            }

            if ( ! is_array( $data ) || empty( $data['issuer'] ) ) {
                return 'mismatch';
            }

            $issuer_ok = rtrim( $data['issuer'], '/' ) === $expected_issuer;

            
            
            
            
            $endpoints = [
                $data['authorization_endpoint'] ?? '',
                $data['token_endpoint']         ?? '',
                $data['registration_endpoint']  ?? '',
            ];
            foreach ( $endpoints as $endpoint ) {
                if ( '' !== $endpoint && false !== strpos( $endpoint, '/wp-json/more-mcp/v1/' ) ) {
                    return 'stale_static';
                }
            }

            return $issuer_ok ? 'ok' : 'mismatch';
        }

        if ( 404 === $code ) {
            $has_php_hdr  = ! empty( $headers['x-httpd'] );
            $is_tiny_body = strlen( $body ) < 500;
            return ( ! $has_php_hdr && $is_tiny_body ) ? 'blocked' : 'unknown';
        }

        return 'unknown';
    }

    
    private function is_dev_host() {
        $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        if ( '' === $host ) {
            return false;
        }
        if ( 'localhost' === $host || '127.0.0.1' === $host ) {
            return true;
        }
        $dev_tlds = [ '.test', '.local', '.localhost', '.dev' ];
        foreach ( $dev_tlds as $tld ) {
            if ( substr( $host, -strlen( $tld ) ) === $tld ) {
                return true;
            }
        }
        return false;
    }

    
    public function invalidate_check() {
        delete_transient( self::TRANSIENT_KEY );
        delete_transient( self::REGISTER_301_TRANSIENT );
    }

    
    public function check_register_301() {
        $cached = get_transient( self::REGISTER_301_TRANSIENT );
        if ( false !== $cached ) {
            return 'redirect' === $cached;
        }

        $url = home_url( '/register' );

        
        
        
        $response = wp_remote_post(
            $url,
            [
                'timeout'     => 5,
                'redirection' => 0,
                'sslverify'   => true,
                'user-agent'  => 'More MCP Self-Check',
                'headers'     => [ 'Content-Type' => 'application/json' ],
                'body'        => '{}',
            ]
        );

        $status = 'ok';
        if ( ! is_wp_error( $response ) ) {
            $code     = (int) wp_remote_retrieve_response_code( $response );
            $location = (string) wp_remote_retrieve_header( $response, 'location' );
            if ( 301 === $code && '' !== $location ) {
                $location_path = (string) wp_parse_url( $location, PHP_URL_PATH );
                if ( '/register/' === $location_path ) {
                    $status = 'redirect';
                }
            }
        }

        set_transient( self::REGISTER_301_TRANSIENT, $status, self::TRANSIENT_TTL );

        return 'redirect' === $status;
    }

    
    public function maybe_dismiss() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['more_mcp_dismiss_well_known'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_well_known' )
        ) {
            update_user_meta( get_current_user_id(), self::USER_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_well_known', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_stale_static'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_stale_static' )
        ) {
            update_user_meta( get_current_user_id(), self::STALE_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_stale_static', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_html_body'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_html_body' )
        ) {
            update_user_meta( get_current_user_id(), self::HTML_BODY_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_html_body', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_register_301'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_register_301' )
        ) {
            update_user_meta( get_current_user_id(), self::REGISTER_301_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_register_301', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_imunify360'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_imunify360' )
        ) {
            update_user_meta( get_current_user_id(), self::IMUNIFY360_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_imunify360', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_bitninja'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_bitninja' )
        ) {
            update_user_meta( get_current_user_id(), self::BITNINJA_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_bitninja', '_wpnonce' ] ) );
            exit;
        }

        if ( isset( $_GET['more_mcp_dismiss_plain_permalinks'] )
            && isset( $_GET['_wpnonce'] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'more_mcp_dismiss_plain_permalinks' )
        ) {
            update_user_meta( get_current_user_id(), self::PLAIN_PERMALINKS_DISMISS_KEY, time() );
            wp_safe_redirect( remove_query_arg( [ 'more_mcp_dismiss_plain_permalinks', '_wpnonce' ] ) );
            exit;
        }
    }

    private function render_blocked_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_well_known', '1' ),
            'more_mcp_dismiss_well_known'
        );

        ?>
        <div class="notice notice-warning more-mcp-well-known-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth discovery is being blocked by your host.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: literal URL path code */
                    esc_html__( 'Your web server is returning a 404 for %s before WordPress sees the request. Claude.ai and other MCP clients will fail to connect until this is fixed. SiteGround and a few other managed hosts reserve this path for their own use.', 'more-mcp' ),
                    '<code>/.well-known/oauth-authorization-server</code>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url( self::SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'See the 5-minute fix', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_stale_static_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_stale_static', '1' ),
            'more_mcp_dismiss_stale_static'
        );

        ?>
        <div class="notice notice-error more-mcp-stale-static-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: stale OAuth discovery files detected in your webroot.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: file path, 2: file path */
                    esc_html__( 'Static files at %1$s and %2$s are advertising old OAuth endpoint URLs (under /wp-json/more-mcp/v1/) that no longer exist. Claude.ai reads these and tries to register against a 404, so connection silently fails.', 'more-mcp' ),
                    '<code>/.well-known/oauth-authorization-server</code>',
                    '<code>/.well-known/oauth-protected-resource</code>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e( 'These files were likely placed by a host-support workaround for an earlier version. Delete them and More MCP will serve fresh metadata from PHP automatically.', 'more-mcp' ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'SSH/SFTP fix:', 'more-mcp' ); ?></strong>
                <code>rm /path/to/your/webroot/.well-known/oauth-authorization-server /path/to/your/webroot/.well-known/oauth-protected-resource</code>
            </p>
            <p>
                <a href="<?php echo esc_url( self::STALE_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'See the full fix', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_html_body_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_html_body', '1' ),
            'more_mcp_dismiss_html_body'
        );

        ?>
        <div class="notice notice-warning more-mcp-html-body-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth discovery is being served as HTML by another plugin or theme.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: literal URL path code */
                    esc_html__( '%s returned an HTML document instead of JSON. A membership plugin (ARMember, MemberPress, Restrict Content Pro) or a theme template is intercepting the request and serving its own page. Discovery clients require JSON, so claude.ai and other MCP clients will fail to connect.', 'more-mcp' ),
                    '<code>/.well-known/oauth-authorization-server</code>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e( 'Quick things to try:', 'more-mcp' ); ?>
            </p>
            <ul style="margin-left: 1.5rem; list-style: disc;">
                <li><?php esc_html_e( 'Add the OAuth paths (/.well-known/, /register, /authorize, /token) to your membership plugin\'s unrestricted-URL list.', 'more-mcp' ); ?></li>
                <li><?php esc_html_e( 'Re-save Permalinks (Settings → Permalinks → Save) to flush rewrite rules.', 'more-mcp' ); ?></li>
                <li><?php esc_html_e( 'Temporarily deactivate suspect plugins one at a time to identify the culprit.', 'more-mcp' ); ?></li>
            </ul>
            <p>
                <a href="<?php echo esc_url( self::HTML_BODY_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'See the troubleshooting guide', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_imunify360_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_imunify360', '1' ),
            'more_mcp_dismiss_imunify360'
        );

        ?>
        <div class="notice notice-warning more-mcp-imunify360-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth discovery is being blocked by Imunify360 bot-protection.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: literal URL path code, 2: literal URL path code */
                    esc_html__( 'Your host runs Imunify360 (a CloudLinux security layer on many shared cPanel hosts), and it is intercepting %1$s and %2$s before WordPress can respond. Claude.ai and other MCP clients will fail to connect until your host allowlists these paths. No WordPress setting can fix this.', 'more-mcp' ),
                    '<code>/.well-known/*</code>',
                    '<code>/wp-json/*</code>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e( 'Ask your host to allowlist these paths in Imunify360:', 'more-mcp' ); ?>
                <code>/.well-known/*</code>, <code>/wp-json/*</code>, <code>/authorize</code>, <code>/token</code>, <code>/register</code>.
            </p>
            <p>
                <a href="<?php echo esc_url( self::IMUNIFY360_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'Copy-paste hosting request', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_bitninja_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_bitninja', '1' ),
            'more_mcp_dismiss_bitninja'
        );

        ?>
        <div class="notice notice-warning more-mcp-bitninja-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth discovery is being blocked by BitNinja WebShield.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: literal URL path code, 2: literal URL path code */
                    esc_html__( 'Your host runs BitNinja WebShield, a bot-protection layer that serves a JavaScript challenge page in place of %1$s and %2$s. MCP clients (Claude, ChatGPT, Cursor) do not execute JavaScript and cannot solve the challenge, so the OAuth handshake fails at discovery. No WordPress setting can fix this: the exclusion must happen at the host layer.', 'more-mcp' ),
                    '<code>/.well-known/*</code>',
                    '<code>/authorize</code>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e( 'Ask your host to disable BitNinja WebShield for these paths, or for the whole domain if per-path exclusion isn\'t offered:', 'more-mcp' ); ?>
                <code>/.well-known/*</code>, <code>/wp-json/*</code>, <code>/authorize</code>, <code>/token</code>, <code>/register</code>.
            </p>
            <p>
                <em><?php esc_html_e( 'Insist on a path-based or domain-level exclusion, not an IP allowlist. Claude.ai\'s outbound IPs rotate, so an IP-only rule silently breaks again in weeks.', 'more-mcp' ); ?></em>
            </p>
            <p>
                <a href="<?php echo esc_url( self::BITNINJA_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'Copy-paste hosting request', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_plain_permalinks_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_plain_permalinks', '1' ),
            'more_mcp_dismiss_plain_permalinks'
        );

        $permalinks_admin_url = admin_url( 'options-permalink.php' );

        ?>
        <div class="notice notice-warning more-mcp-plain-permalinks-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth discovery requires pretty permalinks.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: literal URL path code, 2: literal URL path code, 3: literal URL path code, 4: literal URL path code */
                    esc_html__( 'WordPress is currently set to Plain permalinks. More MCP serves its OAuth endpoints (%1$s, %2$s, %3$s, %4$s) from the domain root via rewrite rules, and rewrite rules don\'t fire on Plain. Claude.ai cannot complete the connection until this is changed.', 'more-mcp' ),
                    '<code>/.well-known/oauth-authorization-server</code>',
                    '<code>/authorize</code>',
                    '<code>/token</code>',
                    '<code>/register</code>'
                );
                ?>
            </p>
            <p>
                <?php esc_html_e( 'The fix takes 10 seconds: open Settings → Permalinks, choose any option except Plain (Post name is a safe default), and Save Changes.', 'more-mcp' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( $permalinks_admin_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Fix in Permalink Settings', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( self::PLAIN_PERMALINKS_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button" style="margin-left: 0.5rem;">
                    <?php esc_html_e( 'Read full explanation', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function render_register_301_notice() {
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'more_mcp_dismiss_register_301', '1' ),
            'more_mcp_dismiss_register_301'
        );

        ?>
        <div class="notice notice-warning more-mcp-register-301-notice">
            <p>
                <strong><?php esc_html_e( 'More MCP: OAuth registration may be blocked by your web server.', 'more-mcp' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: 1: literal URL path code, 2: literal URL path code */
                    esc_html__( 'Your web server is redirecting %1$s to %2$s with a 301. OAuth clients don\'t follow 301s on POST, so claude.ai\'s registration request dies before it reaches More MCP. This is a web-server config issue (Nginx, Apache mod_dir, or .htaccess canonicalization), not a More MCP setting.', 'more-mcp' ),
                    '<code>/register</code>',
                    '<code>/register/</code>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url( self::REGISTER_301_SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'See Nginx and Apache fixes', 'more-mcp' ); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left: 1rem;">
                    <?php esc_html_e( 'Dismiss', 'more-mcp' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
