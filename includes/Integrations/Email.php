<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email {

	const PROVIDERS = array( 'wpmailsmtp', 'easywpsmtp' );

	public static function is_available() {
		foreach ( self::PROVIDERS as $provider ) {
			if ( self::provider_available( $provider ) ) {
				return true;
			}
		}
		return false;
	}

	public static function get_manifest() {
		$providers = array();
		foreach ( self::PROVIDERS as $provider ) {
			if ( self::provider_available( $provider ) ) {
				$providers[] = $provider;
			}
		}
		return array(
			'providers'    => $providers,
			'capabilities' => array( 'email' ),
			'kind'         => 'plugin',
		);
	}

	private static function provider_available( $provider ) {
		if ( 'wpmailsmtp' === $provider ) {
			return function_exists( 'wp_mail_smtp' ) || defined( 'WPMS_PLUGIN_VER' ) || class_exists( '\WPMailSMTP\Options' );
		}
		if ( 'easywpsmtp' === $provider ) {

			return class_exists( '\EasyWPSMTP\Options' ) || false !== get_option( 'swpsmtp_options', false );
		}
		return false;
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'email_get_status',
				'description' => 'Read the site\'s outgoing-email (SMTP) configuration status through an installed mailer plugin (WP Mail SMTP, Easy WP SMTP). Returns the active mailer slug, whether setup is complete, the non-secret From name and From email, and the last send-error summary when the plugin exposes one. Credentials, API keys, and passwords are NEVER returned, and there is no write tool: this is diagnostic only. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => array_merge( array( 'all' ), self::PROVIDERS ), 'description' => 'Mailer plugin to inspect. Defaults to all active providers.' ),
					),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use email tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'No supported SMTP/mailer plugin is active.' );
		}
		if ( 'email_get_status' !== $name ) {
			throw new \Exception( 'Unknown email tool: ' . esc_html( $name ) );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'all';
		if ( 'all' !== $provider && ! in_array( $provider, self::PROVIDERS, true ) ) {
			throw new \Exception( 'Unknown email provider: ' . esc_html( $provider ) );
		}

		$requested = 'all' === $provider ? self::PROVIDERS : array( $provider );
		$results   = array();
		foreach ( $requested as $one ) {
			if ( ! self::provider_available( $one ) ) {
				continue;
			}
			$results[ $one ] = 'wpmailsmtp' === $one ? self::wpmailsmtp_status() : self::easywpsmtp_status();
		}
		return array( 'providers' => $results );
	}

	private static function wpmailsmtp_status() {
		$mailer   = '';
		$from_name = '';
		$from_email = '';
		$complete = false;
		$last_error = '';

		if ( class_exists( '\WPMailSMTP\Options' ) ) {
			$options = \WPMailSMTP\Options::init();
			$mailer  = (string) $options->get( 'mail', 'mailer' );
			$from_name  = (string) $options->get( 'mail', 'from_name' );
			$from_email = (string) $options->get( 'mail', 'from_email' );

			

			if ( '' !== $mailer && 'mail' !== $mailer && function_exists( 'wp_mail_smtp' ) ) {
				$app = wp_mail_smtp();
				if ( is_object( $app ) && method_exists( $app, 'get_providers' ) ) {
					try {
						$phpmailer = method_exists( $app, 'get_processor' ) ? $app->get_processor()->get_phpmailer() : null;
						$obj       = $app->get_providers()->get_mailer( $mailer, $phpmailer );
						$complete  = is_object( $obj ) && method_exists( $obj, 'is_mailer_complete' ) ? (bool) $obj->is_mailer_complete() : false;
					} catch ( \Throwable $e ) {
						$complete = false;
					}
				}
			}
		}

		return array(
			'provider'   => 'wpmailsmtp',
			'mailer'     => $mailer,
			'configured' => '' !== $mailer && 'mail' !== $mailer,
			'complete'   => $complete,
			'from_name'  => $from_name,
			'from_email' => $from_email,
			'last_error' => $last_error,
		);
	}

	private static function easywpsmtp_status() {
		$opts = get_option( 'swpsmtp_options', array() );
		$opts = is_array( $opts ) ? $opts : array();

		
		
		$smtp       = isset( $opts['smtp_settings'] ) && is_array( $opts['smtp_settings'] ) ? $opts['smtp_settings'] : array();
		$from_email = (string) ( $opts['from_email_field'] ?? '' );
		$from_name  = (string) ( $opts['from_name_field'] ?? '' );
		$host       = (string) ( $smtp['host'] ?? '' );

		return array(
			'provider'   => 'easywpsmtp',
			'mailer'     => '' !== $host ? 'smtp' : '',
			'configured' => '' !== $host,

			
			'complete'   => '' !== $host,
			'from_name'  => $from_name,
			'from_email' => $from_email,
			'smtp_host'  => $host,
			'last_error' => '',
		);
	}
}
