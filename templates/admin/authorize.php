<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// $rmcp_oauth is set by OAuth\Server::authorize_get() before including this template.
// Values are escaped at point of output below — do NOT pre-escape here.
$more_mcp_client_name   = $rmcp_oauth['client_name'];
$more_mcp_site_name     = $rmcp_oauth['site_name'];
$more_mcp_user_display  = $rmcp_oauth['user_display_name'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html__( 'Authorize', 'more-mcp' ) . ' — ' . esc_html( get_bloginfo( 'name' ) ); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #f0f0f1;
            color: #1d2327;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .auth-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            max-width: 440px;
            width: 100%;
            padding: 32px;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-header .site-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #2c3338;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .auth-header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .auth-header .subtitle {
            color: #646970;
            font-size: 14px;
        }
        .auth-details {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .auth-details h3 {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #646970;
            margin-bottom: 12px;
        }
        .auth-details ul {
            list-style: none;
            padding: 0;
        }
        .auth-details li {
            padding: 6px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .auth-details li::before {
            content: "\2713";
            color: #00a32a;
            font-weight: 700;
        }
        .auth-user {
            text-align: center;
            font-size: 13px;
            color: #646970;
            margin-bottom: 20px;
        }
        .auth-user strong { color: #1d2327; }
        .auth-buttons {
            display: flex;
            gap: 12px;
        }
        .auth-buttons button {
            flex: 1;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            transition: background .15s;
        }
        .btn-authorize {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }
        .btn-authorize:hover { background: #135e96; }
        .btn-deny {
            background: #fff;
            border-color: #dcdcde;
            color: #1d2327;
        }
        .btn-deny:hover { background: #f6f7f7; }
        .auth-footer {
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: #a7aaad;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="site-icon"><?php echo esc_html( mb_substr( get_bloginfo( 'name' ), 0, 1 ) ); ?></div>
            <h1><?php echo esc_html( $more_mcp_client_name ); ?></h1>
            <p class="subtitle"><?php esc_html_e( 'wants to connect to your WordPress site', 'more-mcp' ); ?></p>
        </div>

        <div class="auth-details">
            <h3><?php esc_html_e( 'This will allow the application to:', 'more-mcp' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Read your posts, pages, and media', 'more-mcp' ); ?></li>
                <li><?php esc_html_e( 'Create and edit content', 'more-mcp' ); ?></li>
                <li><?php esc_html_e( 'Manage categories, tags, and menus', 'more-mcp' ); ?></li>
                <li><?php esc_html_e( 'View site settings and user info', 'more-mcp' ); ?></li>
            </ul>
        </div>

        <p class="auth-user">
            <?php
            printf(
                /* translators: 1: user display name, 2: site name */
                esc_html__( 'Signed in as %1$s on %2$s', 'more-mcp' ),
                '<strong>' . esc_html( $more_mcp_user_display ) . '</strong>',
                '<strong>' . esc_html( $more_mcp_site_name ) . '</strong>'
            );
            ?>
        </p>

        <form method="post" action="<?php echo esc_url( home_url( '/authorize' ) ); ?>">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $rmcp_oauth['nonce'] ); ?>">
            <input type="hidden" name="client_id" value="<?php echo esc_attr( $rmcp_oauth['client_id'] ); ?>">
            <input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $rmcp_oauth['redirect_uri'] ); ?>">
            <input type="hidden" name="code_challenge" value="<?php echo esc_attr( $rmcp_oauth['code_challenge'] ); ?>">
            <input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $rmcp_oauth['code_challenge_method'] ); ?>">
            <input type="hidden" name="state" value="<?php echo esc_attr( $rmcp_oauth['state'] ); ?>">
            <input type="hidden" name="scope" value="<?php echo esc_attr( $rmcp_oauth['scope'] ); ?>">

            <div class="auth-buttons">
                <button type="submit" name="authorize_action" value="deny" class="btn-deny">
                    <?php esc_html_e( 'Deny', 'more-mcp' ); ?>
                </button>
                <button type="submit" name="authorize_action" value="approve" class="btn-authorize">
                    <?php esc_html_e( 'Authorize', 'more-mcp' ); ?>
                </button>
            </div>
        </form>

        <p class="auth-footer">
            <?php esc_html_e( 'Powered by More MCP', 'more-mcp' ); ?>
        </p>
    </div>
</body>
</html>
