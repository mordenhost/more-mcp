<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email {

	const PROVIDERS = array( 'wpmailsmtp', 'easywpsmtp', 'fluentsmtp', 'postsmtp', 'gosmtp', 'suremails' );

	const TEST_EMAIL_COOLDOWN = 30;

	const TEST_EMAIL_LOCK = 'mordenhost_mcp_email_test_lock';

	const FLUENTSMTP_SECRET_FIELDS = array( 'password', 'api_key', 'secret_key', 'access_key', 'client_secret' );

	const FLUENTSMTP_PUBLIC_FIELDS = array( 'host', 'port', 'encryption', 'auth', 'auto_tls', 'region', 'domain_name', 'message_stream' );

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
		if ( 'gosmtp' === $provider ) {
			
			return defined( 'GOSMTP_FILE' ) || class_exists( '\GOSMTP\Mailer\Loader' );
		}
		if ( 'suremails' === $provider ) {

			return defined( 'SUREMAILS_CONNECTIONS' ) || defined( 'SUREMAILS_VERSION' );
		}
		return false;
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		$tools = array(
			array(
				'name'        => 'email_get_status',
				'description' => 'Read the site\'s outgoing-email (SMTP) configuration status through an installed mailer plugin (WP Mail SMTP, Easy WP SMTP, FluentSMTP, Post SMTP, GoSMTP, SureMail). Returns the active mailer slug, whether setup is complete, the non-secret From name and From email, and the last send-error summary when the plugin exposes one. Credentials, API keys, and passwords are NEVER returned, and no mailer setting is ever written: this is diagnostic only. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => array_merge( array( 'all' ), self::PROVIDERS ), 'description' => 'Mailer plugin to inspect. Defaults to all active providers.' ),
					),
				),
			),
		);
		if ( self::provider_available( 'fluentsmtp' ) ) {
			$tools = array_merge( $tools, self::fluentsmtp_tools() );
		}
		return $tools;
	}

	private static function fluentsmtp_tools() {
		$provider = array( 'type' => 'string', 'enum' => array( 'fluentsmtp' ), 'description' => 'Mailer plugin. Only fluentsmtp is supported, and it is the default.' );
		return array(
			array(
				'name'        => 'email_get_connections',
				'description' => 'Read FluentSMTP\'s connections and sending settings. Per connection: its key, mailer (smtp, ses, sendgrid, gmail, outlook, ...), sender email and name, whether it is the default or the fallback connection, the From addresses routed to it, non-secret server settings (SMTP host, port, encryption; SES or Mailgun region), whether a credential is saved and where it is stored (database or wp-config.php), and the result of FluentSMTP\'s own daily connection health check. Also reports simulation mode (messages logged, not sent), email logging and retention, and whether FluentSMTP actually handles wp_mail() on this site. Passwords, API keys, client secrets and OAuth tokens are never returned, not even masked. Requires manage_options. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'provider' => $provider ),
				),
			),
			array(
				'name'        => 'email_send_test',
				'description' => 'Send FluentSMTP\'s own test email (its fixed text or HTML test message, the one its settings screen sends) through one FluentSMTP connection, to check delivery end to end. FluentSMTP routes by From address, so this sends from the chosen connection\'s sender email: pass connection (a key from email_get_connections) or omit it for the default connection. FluentSMTP skips its fallback connection for a test, so a failure is that connection\'s own. It sends a real email, so it is a two-part confirmation: call without confirm for a preview (nothing is sent); call again with confirm=true and confirm_to repeating the recipient (dry_run=true always previews). to defaults to the site admin email and must be one plain address. At most one test every ' . self::TEST_EMAIL_COOLDOWN . ' seconds. In FluentSMTP simulation mode the message is logged, not delivered, and the response says so. A send error is returned with anything token-like redacted. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider'   => $provider,
						'to'         => array( 'type' => 'string', 'description' => 'One recipient address. Defaults to the site admin email.' ),
						'connection' => array( 'type' => 'string', 'description' => 'FluentSMTP connection key from email_get_connections. Defaults to the default connection.' ),
						'html'       => array( 'type' => 'boolean', 'description' => 'true (default) sends the HTML test message, false the plain-text one.' ),
						'confirm'    => array( 'type' => 'boolean', 'description' => 'Must be true to send. Omit or false to receive a preview instead. That preview is the intended first call.' ),
						'confirm_to' => array( 'type' => 'string', 'description' => 'Repeat the recipient address from the preview. Required alongside confirm=true.' ),
						'dry_run'    => array( 'type' => 'boolean', 'description' => 'true returns the preview and sends nothing, even with confirm=true.' ),
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
		if ( 'email_get_connections' === $name || 'email_send_test' === $name ) {
			return self::fluentsmtp_tool( $name, is_array( $args ) ? $args : array() );
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
			} elseif ( 'gosmtp' === $one ) {
				$results[ $one ] = self::gosmtp_status();
			} elseif ( 'suremails' === $one ) {
				$results[ $one ] = self::suremails_status();
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

	
	private static function fluentsmtp_tool( $name, array $args ) {
		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'fluentsmtp';
		if ( 'fluentsmtp' !== $provider ) {
			throw new \Exception( esc_html( $name ) . ' supports FluentSMTP only. Use email_get_status for the other mailer plugins.' );
		}
		if ( ! self::provider_available( 'fluentsmtp' ) ) {
			throw new \Exception( 'FluentSMTP is not active.' );
		}
		if ( function_exists( 'fluentMailCurrentUserCanManage' ) && ! fluentMailCurrentUserCanManage() ) {
			throw new \Exception( 'You do not have permission to manage FluentSMTP.' );
		}
		if ( 'email_get_connections' === $name ) {
			return self::fluentsmtp_connections();
		}
		return self::fluentsmtp_send_test( $args );
	}

	private static function fluentsmtp_raw_settings() {
		$settings = get_option( 'fluentmail-settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	private static function fluentsmtp_raw_connections( array $settings ) {
		$connections = isset( $settings['connections'] ) && is_array( $settings['connections'] ) ? $settings['connections'] : array();
		$out         = array();
		foreach ( $connections as $key => $connection ) {
			if ( is_array( $connection ) && isset( $connection['provider_settings'] ) && is_array( $connection['provider_settings'] ) ) {
				$out[ (string) $key ] = $connection;
			}
		}
		return $out;
	}

	private static function fluentsmtp_misc( array $settings ) {
		$defaults = array(
			'log_emails'              => 'yes',
			'log_saved_interval_days' => '14',
			'simulate_emails'         => 'no',
			'send_as_text'            => 'no',
			'default_connection'      => '',
			'fallback_connection'     => '',
		);
		$stored = isset( $settings['misc'] ) && is_array( $settings['misc'] ) ? $settings['misc'] : array();
		foreach ( $stored as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && ! empty( $value ) && is_scalar( $value ) ) {
				$defaults[ $key ] = (string) $value;
			}
		}
		return $defaults;
	}

	private static function fluentsmtp_default_key( array $connections, array $misc ) {
		if ( '' !== $misc['default_connection'] && isset( $connections[ $misc['default_connection'] ] ) ) {
			return $misc['default_connection'];
		}
		foreach ( $connections as $key => $connection ) {
			return (string) $key;
		}
		return '';
	}

	private static function fluentsmtp_simulating( array $misc ) {
		if ( 'yes' === $misc['simulate_emails'] ) {
			return 'setting';
		}
		if ( defined( 'FLUENTMAIL_SIMULATE_EMAILS' ) && constant( 'FLUENTMAIL_SIMULATE_EMAILS' ) ) {
			return 'constant';
		}
		return null;
	}

	private static function yes_no( $value ) {
		if ( 'yes' === $value ) {
			return true;
		}
		if ( 'no' === $value ) {
			return false;
		}
		return null;
	}

	private static function fluentsmtp_credential_saved( $mailer, array $ps ) {
		if ( 'default' === $mailer || '' === $mailer ) {
			return null;
		}
		if ( 'wp_config' === ( $ps['key_store'] ?? '' ) ) {
			return null;
		}
		if ( 'smtp' === $mailer && 'no' === ( $ps['auth'] ?? 'yes' ) ) {
			return null;
		}
		foreach ( self::FLUENTSMTP_SECRET_FIELDS as $field ) {
			if ( ! empty( $ps[ $field ] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function fluentsmtp_owns_wp_mail() {
		if ( ! function_exists( 'wp_mail' ) || ! defined( 'FLUENTMAIL_PLUGIN_FILE' ) ) {
			return null;
		}
		try {
			$file = ( new \ReflectionFunction( 'wp_mail' ) )->getFileName();
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_string( $file ) || '' === $file ) {
			return null;
		}
		return wp_normalize_path( dirname( $file ) ) === wp_normalize_path( dirname( (string) constant( 'FLUENTMAIL_PLUGIN_FILE' ) ) );
	}

	private static function scrub_message( $message ) {
		$message = trim( wp_strip_all_tags( (string) $message ) );
		$message = (string) preg_replace( '/\b(api[_-]?key|password|passwd|pass|secret|token|authorization|bearer)\b(\s*[:=]?\s*)("?)[^\s",;]+/i', '$1$2$3[redacted]', $message );
		$message = (string) preg_replace( '/[A-Za-z0-9_\-+\/=]{24,}/', '[redacted]', $message );
		if ( strlen( $message ) > 300 ) {
			$message = substr( $message, 0, 300 ) . '...';
		}
		return $message;
	}

	private static function fluentsmtp_connections() {
		$settings    = self::fluentsmtp_raw_settings();
		$connections = self::fluentsmtp_raw_connections( $settings );
		$misc        = self::fluentsmtp_misc( $settings );
		$default_key = self::fluentsmtp_default_key( $connections, $misc );
		$fallback    = '' !== $misc['fallback_connection'] && isset( $connections[ $misc['fallback_connection'] ] ) ? $misc['fallback_connection'] : null;
		$mappings    = isset( $settings['mappings'] ) && is_array( $settings['mappings'] ) ? $settings['mappings'] : array();

		$health = get_option( '_fluentsmtp_connection_health', array() );
		$health = is_array( $health ) ? $health : array();

		$rows = array();
		foreach ( $connections as $key => $connection ) {
			$ps     = $connection['provider_settings'];
			$mailer = sanitize_key( (string) ( $ps['provider'] ?? '' ) );

			$senders = array();
			foreach ( $mappings as $email => $mapped_key ) {
				if ( (string) $mapped_key === $key ) {
					$senders[] = sanitize_email( (string) $email );
				}
			}
			sort( $senders );

			$public = array();
			foreach ( self::FLUENTSMTP_PUBLIC_FIELDS as $field ) {
				if ( isset( $ps[ $field ] ) && is_scalar( $ps[ $field ] ) && '' !== (string) $ps[ $field ] ) {
					$public[ $field ] = sanitize_text_field( (string) $ps[ $field ] );
				}
			}

			$row = array(
				'key'                => $key,
				'title'              => sanitize_text_field( (string) ( $connection['title'] ?? '' ) ),
				'mailer'             => $mailer,
				'sender_email'       => sanitize_email( (string) ( $ps['sender_email'] ?? '' ) ),
				'sender_name'        => sanitize_text_field( wp_specialchars_decode( (string) ( $ps['sender_name'] ?? '' ), ENT_QUOTES ) ),
				'is_default'         => $key === $default_key,
				'is_fallback'        => $key === $fallback,
				'force_from_email'   => self::yes_no( $ps['force_from_email'] ?? null ),
				'force_from_name'    => self::yes_no( $ps['force_from_name'] ?? null ),
				'routed_senders'     => $senders,
				'settings'           => $public ? $public : new \stdClass(),
				'credential_storage' => 'wp_config' === ( $ps['key_store'] ?? '' ) ? 'wp_config' : 'database',
				'credential_saved'   => self::fluentsmtp_credential_saved( $mailer, $ps ),
				'health'             => null,
			);
			if ( in_array( $mailer, array( 'gmail', 'outlook' ), true ) ) {
				$row['authenticated'] = ! empty( $ps['access_token'] );
			}
			if ( isset( $health[ $key ] ) && is_array( $health[ $key ] ) ) {
				$row['health'] = array(
					'status'     => sanitize_key( (string) ( $health[ $key ]['status'] ?? '' ) ),
					'message'    => self::scrub_message( $health[ $key ]['message'] ?? '' ),
					'checked_at' => sanitize_text_field( (string) ( $health[ $key ]['checked_at'] ?? '' ) ),
				);
			}
			$rows[] = $row;
		}

		$simulating = self::fluentsmtp_simulating( $misc );
		return array(
			'provider'                      => 'fluentsmtp',
			'version'                       => defined( 'FLUENTMAIL_PLUGIN_VERSION' ) ? (string) constant( 'FLUENTMAIL_PLUGIN_VERSION' ) : null,
			'connections_count'             => count( $rows ),
			'connections'                   => $rows,
			'default_connection'            => '' === $default_key ? null : $default_key,
			'fallback_connection'           => $fallback,
			'routing'                       => 'FluentSMTP picks the connection by From address: a routed sender uses its own connection, anything else goes through the default connection. The fallback connection is tried when a send fails.',
			'simulate_emails'               => null !== $simulating,
			'simulate_source'               => $simulating,
			'log_emails'                    => 'yes' === $misc['log_emails'],
			'log_retention_days'            => is_numeric( $misc['log_saved_interval_days'] ) ? (int) $misc['log_saved_interval_days'] : null,
			'send_as_text'                  => 'yes' === $misc['send_as_text'],
			'wp_mail_handled_by_fluentsmtp' => self::fluentsmtp_owns_wp_mail(),
			'settings_url'                  => admin_url( 'options-general.php?page=fluent-mail' ),
		);
	}

	private static function fluentsmtp_send_test( array $args ) {
		$settings    = self::fluentsmtp_raw_settings();
		$connections = self::fluentsmtp_raw_connections( $settings );
		if ( ! $connections ) {
			throw new \Exception( 'FluentSMTP has no connection yet, so there is nothing to test. Add one in Settings > FluentSMTP.' );
		}
		if ( ! class_exists( '\FluentMail\App\Models\Settings' ) || ! method_exists( '\FluentMail\App\Models\Settings', 'sendTestEmail' ) ) {
			throw new \Exception( 'This FluentSMTP version has no test email API.' );
		}

		
		
		if ( isset( $args['to'] ) && ! is_string( $args['to'] ) ) {
			throw new \Exception( 'to must be one plain email address (no display name, list or header).' );
		}
		$raw_to = isset( $args['to'] ) ? trim( $args['to'] ) : '';
		if ( '' === $raw_to ) {
			$raw_to = (string) get_option( 'admin_email', '' );
		}
		$to = sanitize_email( $raw_to );
		if ( '' === $to || $to !== $raw_to || ! is_email( $to ) ) {
			throw new \Exception( 'to must be one plain email address (no display name, list or header).' );
		}

		$misc        = self::fluentsmtp_misc( $settings );
		$default_key = self::fluentsmtp_default_key( $connections, $misc );
		$key         = $default_key;
		if ( isset( $args['connection'] ) && is_scalar( $args['connection'] ) && '' !== (string) $args['connection'] ) {
			$key = (string) $args['connection'];
			if ( ! isset( $connections[ $key ] ) ) {
				throw new \Exception( 'Unknown FluentSMTP connection: ' . esc_html( $key ) . '. Call email_get_connections for the keys.' );
			}
		}
		$ps   = $connections[ $key ]['provider_settings'];
		$from = sanitize_email( (string) ( $ps['sender_email'] ?? '' ) );
		if ( '' === $from || ! is_email( $from ) ) {
			throw new \Exception( 'FluentSMTP connection ' . esc_html( $key ) . ' has no valid sender email to send from.' );
		}

		
		$mappings = isset( $settings['mappings'] ) && is_array( $settings['mappings'] ) ? $settings['mappings'] : array();
		if ( $key !== $default_key && ( ! isset( $mappings[ $from ] ) || (string) $mappings[ $from ] !== $key ) ) {
			throw new \Exception( 'FluentSMTP does not route ' . esc_html( $from ) . ' to connection ' . esc_html( $key ) . ', so the test would go through another connection. Re-save that connection in FluentSMTP first.' );
		}

		$mailer     = sanitize_key( (string) ( $ps['provider'] ?? '' ) );
		$simulating = self::fluentsmtp_simulating( $misc );
		$html       = ! array_key_exists( 'html', $args ) || ! empty( $args['html'] );

		
		$dry_run   = \Mordenhost_MCP\Access\Destructive::is_dry_run( 'email_send_test', $args );
		$confirmed = ! $dry_run && ! empty( $args['confirm'] ) && isset( $args['confirm_to'] ) && is_string( $args['confirm_to'] ) && trim( $args['confirm_to'] ) === $to;
		if ( ! $confirmed ) {
			return array(
				'preview'    => true,
				'to'         => $to,
				'from'       => $from,
				'connection' => $key,
				'mailer'     => $mailer,
				'html'       => $html,
				'simulated'  => null !== $simulating,
				'message'    => 'Preview only, nothing was sent. To send FluentSMTP\'s test email, call again with confirm=true and confirm_to=' . $to . '.'
					. ( null !== $simulating ? ' FluentSMTP is in simulation mode, so the message will be logged, not delivered.' : '' ),
			);
		}

		if ( get_transient( self::TEST_EMAIL_LOCK ) ) {
			throw new \Exception( 'A test email was sent less than ' . (int) self::TEST_EMAIL_COOLDOWN . ' seconds ago. Wait before sending another.' );
		}
		set_transient( self::TEST_EMAIL_LOCK, 1, self::TEST_EMAIL_COOLDOWN );

		
		if ( ! defined( 'FLUENTMAIL_EMAIL_TESTING' ) ) {
			define( 'FLUENTMAIL_EMAIL_TESTING', true );
		}
		$error   = '';
		$on_fail = static function ( $wp_error ) use ( &$error ) {
			if ( is_object( $wp_error ) && method_exists( $wp_error, 'get_error_message' ) ) {
				$error = (string) $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $on_fail );
		$started = microtime( true );
		try {
			$model = new \FluentMail\App\Models\Settings();

			
			$sent = $model->sendTestEmail(
				array(
					'email'  => $to,
					'from'   => $from,
					'isHtml' => $html ? 'true' : 'false',
				),
				$settings
			);
		} catch ( \Throwable $e ) {
			$sent = false;
			if ( '' === $error ) {
				$error = $e->getMessage();
			}
		} finally {
			remove_action( 'wp_mail_failed', $on_fail );
		}
		$elapsed = round( microtime( true ) - $started, 3 );

		$ok = ( true === $sent || 1 === $sent ) && '' === $error;
		if ( ! $ok && '' === $error ) {
			$error = 'wp_mail() returned false without a reason.';
		}
		return array(
			'success'            => $ok,
			'sent'               => $ok && null === $simulating,
			'simulated'          => null !== $simulating,
			'to'                 => $to,
			'from'               => $from,
			'connection'         => $key,
			'mailer'             => $mailer,
			'html'               => $html,
			'time_taken_seconds' => $elapsed,
			'error'              => $ok ? null : self::scrub_message( $error ),
			'message'            => $ok
				? ( null !== $simulating
					? 'FluentSMTP is in simulation mode: the test email was logged, not delivered.'
					: 'FluentSMTP handed the test email to ' . $mailer . '. Check the inbox of ' . $to . ' (and its spam folder).' )
				: 'The test email was not sent. See error.',
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

	
	private static function gosmtp_status() {
		$options = get_option( 'gosmtp_options', array() );
		$options = is_array( $options ) ? $options : array();

		$connections = isset( $options['mailer'] ) && is_array( $options['mailer'] ) ? $options['mailer'] : array();
		$primary     = isset( $connections[0] ) && is_array( $connections[0] ) ? $connections[0] : array();
		$mailer      = (string) ( $primary['mail_type'] ?? '' );
		$configured  = '' !== $mailer && 'mail' !== $mailer;

		$backup = null;
		if ( isset( $primary['backup_connection'] ) && '' !== (string) $primary['backup_connection'] ) {
			$backup = (int) $primary['backup_connection'];
		}

		return array(
			'provider'          => 'gosmtp',
			'mailer'            => $configured ? $mailer : '',
			'configured'        => $configured,

			'complete'          => $configured,
			'from_name'         => (string) ( $options['from_name'] ?? '' ),
			'from_email'        => (string) ( $options['from_email'] ?? '' ),
			'nickname'          => (string) ( $primary['nickname'] ?? '' ),
			'connections_count' => count( $connections ),
			'backup_connection' => $backup,
			'last_error'        => '',
		);
	}

	
	private static function suremails_status() {
		$settings = get_option( 'suremails_connections', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$connections = isset( $settings['connections'] ) && is_array( $settings['connections'] ) ? $settings['connections'] : array();
		$default     = isset( $settings['default_connection'] ) && is_array( $settings['default_connection'] ) ? $settings['default_connection'] : array();

		$default_id = (string) ( $default['id'] ?? '' );
		$primary    = '' !== $default_id && isset( $connections[ $default_id ] ) && is_array( $connections[ $default_id ] )
			? $connections[ $default_id ]
			: ( ! empty( $connections ) && is_array( reset( $connections ) ) ? reset( $connections ) : array() );

		$mailer     = (string) ( $primary['type'] ?? ( $default['type'] ?? '' ) );
		$configured = ! empty( $connections ) && '' !== $mailer;

		return array(
			'provider'           => 'suremails',
			'mailer'             => $configured ? strtolower( $mailer ) : '',
			'configured'         => $configured,
			'complete'           => $configured,
			'from_name'          => (string) ( $primary['from_name'] ?? '' ),
			'from_email'         => (string) ( $primary['from_email'] ?? ( $default['email'] ?? '' ) ),
			'connection'         => (string) ( $primary['connection_title'] ?? ( $default['connection_title'] ?? '' ) ),
			'connections_count'  => count( $connections ),
			'email_simulation'   => 'yes' === ( $settings['email_simulation'] ?? 'no' ),
			'email_log_enabled'  => 'yes' === ( $settings['log_emails'] ?? 'yes' ),
			'last_error'         => '',
		);
	}
}
