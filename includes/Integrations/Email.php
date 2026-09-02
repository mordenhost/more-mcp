<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email {

	const PROVIDERS = array( 'wpmailsmtp', 'easywpsmtp', 'fluentsmtp', 'postsmtp' );

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
		if ( 'fluentsmtp' === $provider ) {

			return defined( 'FLUENTMAIL_PLUGIN_FILE' ) || class_exists( '\FluentMail\Includes\Core\Application' );
		}
		if ( 'postsmtp' === $provider ) {

			
			
			return defined( 'POST_SMTP_VER' ) || class_exists( '\PostmanOptions' );
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
				'description' => 'Read the site\'s outgoing-email (SMTP) configuration status through an installed mailer plugin (WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP). Returns the active mailer slug, whether setup is complete, the non-secret From name and From email, and the last send-error summary when the plugin exposes one. Credentials, API keys, and passwords are NEVER returned, and there is no write tool: this is diagnostic only. Read-only.',
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
			if ( 'wpmailsmtp' === $one ) {
				$results[ $one ] = self::wpmailsmtp_status();
			} elseif ( 'easywpsmtp' === $one ) {
				$results[ $one ] = self::easywpsmtp_status();
			} elseif ( 'fluentsmtp' === $one ) {
				$results[ $one ] = self::fluentsmtp_status();
			} else {
				$results[ $one ] = self::postsmtp_status();
			}
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

	
	private static function fluentsmtp_status() {
		$settings = get_option( 'fluentmail-settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$connections = isset( $settings['connections'] ) && is_array( $settings['connections'] ) ? $settings['connections'] : array();

		
		
		$key = '';
		if ( isset( $settings['misc']['default_connection'] ) && isset( $connections[ $settings['misc']['default_connection'] ] ) ) {
			$key = (string) $settings['misc']['default_connection'];
		} elseif ( ! empty( $connections ) ) {
			$key = (string) key( $connections );
		}

		$provider_settings = '' !== $key && isset( $connections[ $key ]['provider_settings'] ) && is_array( $connections[ $key ]['provider_settings'] )
			? $connections[ $key ]['provider_settings']
			: array();

		
		
		$mailer     = (string) ( $provider_settings['provider'] ?? '' );
		$from_email = (string) ( $provider_settings['sender_email'] ?? '' );
		$from_name  = (string) ( $provider_settings['sender_name'] ?? '' );

		return array(
			'provider'   => 'fluentsmtp',
			'mailer'     => $mailer,
			'configured' => '' !== $mailer,

			
			'complete'       => '' !== $mailer,
			'from_name'  => $from_name,
			'from_email' => $from_email,
			'connection' => $key,
			'connections_count' => count( $connections ),
			'last_error' => '',
		);
	}

	
	private static function postsmtp_status() {
		$mailer     = '';
		$from_name  = '';
		$from_email = '';
		$configured = false;
		$logging    = null;

		if ( class_exists( '\PostmanOptions' ) && method_exists( '\PostmanOptions', 'getInstance' ) ) {

			$options = \PostmanOptions::getInstance();
			try {
				$is_new = method_exists( $options, 'isNew' ) && $options->isNew();
				if ( ! $is_new && method_exists( $options, 'getTransportType' ) ) {

					$mailer = (string) $options->getTransportType();
				}
				if ( method_exists( $options, 'getMessageSenderName' ) ) {
					$from_name = (string) $options->getMessageSenderName();
				}
				if ( method_exists( $options, 'getMessageSenderEmail' ) ) {
					$from_email = (string) $options->getMessageSenderEmail();
				}
				if ( method_exists( $options, 'isMailLoggingEnabled' ) ) {
					$logging = (bool) $options->isMailLoggingEnabled();
				}
			} catch ( \Throwable $e ) {

				$mailer = '';
			}
		}

		$configured = '' !== $mailer && 'default' !== $mailer;

		return array(
			'provider'   => 'postsmtp',
			'mailer'     => $configured ? $mailer : '',
			'configured' => $configured,

			'complete'       => $configured,
			'from_name'  => $from_name,
			'from_email' => $from_email,
			'mail_logging_enabled' => $logging,
			'last_error' => '',
		);
	}
}
