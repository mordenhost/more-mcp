<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$more_mcp_state = $rmcp_device['state'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html__( 'Connect a device', 'mordenhost-mcp-server' ) . ': ' . esc_html( get_bloginfo( 'name' ) ); ?></title>
    <?php
    
    wp_enqueue_style( 'more-mcp-authorize', MORE_MCP_PLUGIN_URL . 'assets/css/authorize.css', array(), MORE_MCP_VERSION );
    wp_print_styles( 'more-mcp-authorize' );
    ?>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="site-icon"><?php echo esc_html( mb_substr( $rmcp_device['site_name'], 0, 1 ) ); ?></div>
            <h1><?php esc_html_e( 'Connect a device', 'mordenhost-mcp-server' ); ?></h1>
            <p class="subtitle"><?php echo esc_html( $rmcp_device['site_name'] ); ?></p>
        </div>

        <?php if ( '' !== $rmcp_device['error'] ) : ?>
            <p class="device-error" role="alert"><?php echo esc_html( $rmcp_device['error'] ); ?></p>
        <?php endif; ?>

        <?php if ( 'enter' === $more_mcp_state ) : ?>
            <p class="device-note"><?php esc_html_e( 'Enter the code shown by the application you are connecting.', 'mordenhost-mcp-server' ); ?></p>
            <form method="post" action="<?php echo esc_url( home_url( '/mcp-device' ) ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $rmcp_device['nonce'] ); ?>">
                <input type="hidden" name="device_action" value="lookup">
                <input type="text" id="user_code" name="user_code" class="device-code" aria-label="<?php esc_attr_e( 'Code', 'mordenhost-mcp-server' ); ?>" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="ABCD-EFGH" autofocus>
                <div class="auth-buttons">
                    <button type="submit" class="btn-authorize"><?php esc_html_e( 'Continue', 'mordenhost-mcp-server' ); ?></button>
                </div>
            </form>

        <?php elseif ( 'confirm' === $more_mcp_state ) : ?>
            <div class="auth-details">
                <h3>
                    <?php
                    printf(
                        /* translators: %s: application name */
                        esc_html__( '%s wants to connect to your WordPress site.', 'mordenhost-mcp-server' ),
                        '<strong>' . esc_html( $rmcp_device['client'] ) . '</strong>'
                    );
                    ?>
                </h3>
                <p class="device-note"><?php esc_html_e( 'Check that this is the code your application is showing:', 'mordenhost-mcp-server' ); ?></p>
                <p class="device-code-shown"><?php echo esc_html( $rmcp_device['user_code'] ); ?></p>
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
                    '<strong>' . esc_html( $rmcp_device['user_name'] ) . '</strong>',
                    '<strong>' . esc_html( $rmcp_device['site_name'] ) . '</strong>'
                );
                ?>
            </p>
            <p class="device-note"><?php esc_html_e( 'Only continue if you started this on your own device.', 'mordenhost-mcp-server' ); ?></p>
            <form method="post" action="<?php echo esc_url( home_url( '/mcp-device' ) ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $rmcp_device['nonce'] ); ?>">
                <input type="hidden" name="user_code" value="<?php echo esc_attr( $rmcp_device['user_code'] ); ?>">
                <div class="auth-buttons">
                    <button type="submit" name="device_action" value="deny" class="btn-deny"><?php esc_html_e( 'Deny', 'mordenhost-mcp-server' ); ?></button>
                    <button type="submit" name="device_action" value="approve" class="btn-authorize"><?php esc_html_e( 'Authorize', 'mordenhost-mcp-server' ); ?></button>
                </div>
            </form>

        <?php elseif ( 'approved' === $more_mcp_state ) : ?>
            <p class="device-note"><strong><?php esc_html_e( 'Device connected.', 'mordenhost-mcp-server' ); ?></strong></p>
            <p class="device-note"><?php esc_html_e( 'You can close this page and return to your application.', 'mordenhost-mcp-server' ); ?></p>

        <?php else : ?>
            <p class="device-note"><strong><?php esc_html_e( 'Request denied.', 'mordenhost-mcp-server' ); ?></strong></p>
            <p class="device-note"><?php esc_html_e( 'The application was not given access.', 'mordenhost-mcp-server' ); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
