<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
    <title><?php echo esc_html__( 'Authorize', 'mordenhost-mcp-server' ) . ': ' . esc_html( get_bloginfo( 'name' ) ); ?></title>
    <?php
    
    wp_enqueue_style( 'more-mcp-authorize', MORE_MCP_PLUGIN_URL . 'assets/css/authorize.css', array(), MORE_MCP_VERSION );
    wp_print_styles( 'more-mcp-authorize' );
    ?>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="site-icon"><?php echo esc_html( mb_substr( get_bloginfo( 'name' ), 0, 1 ) ); ?></div>
            <h1><?php echo esc_html( $more_mcp_client_name ); ?></h1>
            <p class="subtitle"><?php esc_html_e( 'wants to connect to your WordPress site', 'mordenhost-mcp-server' ); ?></p>
        </div>

        <div class="auth-details">
            <h3><?php esc_html_e( 'This will allow the application to:', 'mordenhost-mcp-server' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Read your posts, pages, and media', 'mordenhost-mcp-server' ); ?></li>
                <li><?php esc_html_e( 'Create and edit content', 'mordenhost-mcp-server' ); ?></li>
                <li><?php esc_html_e( 'Manage categories, tags, and menus', 'mordenhost-mcp-server' ); ?></li>
                <li><?php esc_html_e( 'View site settings and user info', 'mordenhost-mcp-server' ); ?></li>
            </ul>
        </div>

        <p class="auth-user">
            <?php
            printf(
                /* translators: 1: user display name, 2: site name */
                esc_html__( 'Signed in as %1$s on %2$s', 'mordenhost-mcp-server' ),
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
                    <?php esc_html_e( 'Deny', 'mordenhost-mcp-server' ); ?>
                </button>
                <button type="submit" name="authorize_action" value="approve" class="btn-authorize">
                    <?php esc_html_e( 'Authorize', 'mordenhost-mcp-server' ); ?>
                </button>
            </div>
        </form>
    </div>
</body>
</html>
