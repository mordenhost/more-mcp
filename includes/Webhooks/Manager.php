<?php
namespace More_MCP\Webhooks;

use More_MCP\Platform\Registry;
use More_MCP\Lifecycle\Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	const OPTION_KEY          = 'webhooks'; 
	const MAX_PAYLOAD_BYTES   = 16384;
	const MAX_TITLE_LENGTH    = 512;
	const MAX_TOOL_LENGTH     = 128;
	const MAX_STATUS_LENGTH   = 128;

	public static function known_events() {
		return array(
			'post_published'   => 'A post or page was published.',
			'comment_posted'   => 'A comment was submitted.',
			'user_registered'  => 'A new user registered.',
			'tool_called'      => 'An MCP tool was invoked (carries tool name + status, never argument values).',
		);
	}

	public static function register_listeners() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
		add_action( 'comment_post', array( __CLASS__, 'on_comment_post' ), 10, 1 );
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );
		add_action( 'more_mcp_tool_context', array( __CLASS__, 'on_tool_context' ), 10, 2 );
	}

	
	public static function subscriptions() {
		$settings = get_option( 'more_mcp_settings', array() );
		$subs = ( is_array( $settings ) && isset( $settings[ self::OPTION_KEY ] ) && is_array( $settings[ self::OPTION_KEY ] ) )
			? $settings[ self::OPTION_KEY ]
			: array();
		return array_values( array_filter( $subs, 'is_array' ) );
	}

	private static function save_subscriptions( array $subs ) {
		$settings = get_option( 'more_mcp_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings[ self::OPTION_KEY ] = array_values( $subs );
		update_option( 'more_mcp_settings', $settings );
	}

	public static function tool_names() {
		return array( 'webhook_list', 'webhook_create', 'webhook_delete' );
	}

	public static function get_tools() {
		$events = self::known_events();
		$event_list = implode( ', ', array_keys( $events ) );
		return array(
			array(
				'name'        => 'webhook_list',
				'description' => 'List configured outbound webhook subscriptions. Returns id, url, subscribed events, and whether a signing secret is set (the secret value itself is never returned). Read-only.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
			array(
				'name'        => 'webhook_create',
				'description' => 'Create an outbound webhook: when one of the subscribed WordPress events fires, More MCP POSTs a small JSON payload to the URL, signed with an HMAC-SHA256 header (X-More-MCP-Signature) keyed on a generated secret. The secret is returned ONCE here and never again. No AI, no scheduling — event-triggered notification only. Requires manage_options and two-part confirmation. Available events: ' . $event_list . '.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'          => array( 'type' => 'string', 'description' => 'HTTPS URL to POST to. Must not resolve to a private/loopback address.' ),
						'events'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Which events to subscribe to: ' . $event_list . '.' ),
						'confirm'      => array( 'type' => 'boolean' ),
						'confirm_slug' => array( 'type' => 'string' ),
					),
					'required'   => array( 'url', 'events' ),
				),
			),
			array(
				'name'        => 'webhook_delete',
				'description' => 'Delete an outbound webhook subscription by id. Requires manage_options and two-part confirmation.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array( 'type' => 'string', 'description' => 'Subscription id from webhook_list.' ),
						'confirm'      => array( 'type' => 'boolean' ),
						'confirm_slug' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		Guard::require_cap( 'manage_options', 'manage webhooks' );
		switch ( $name ) {
			case 'webhook_list':
				return self::list_subs();
			case 'webhook_create':
				return self::create_sub( $args );
			case 'webhook_delete':
				return self::delete_sub( $args );
		}
		throw new \Exception( 'Unknown webhooks tool: ' . esc_html( $name ) );
	}

	private static function list_subs() {
		$out = array();
		foreach ( self::subscriptions() as $s ) {
			$out[] = array(
				'id'         => (string) ( $s['id'] ?? '' ),
				'url'        => (string) ( $s['url'] ?? '' ),
				'events'     => isset( $s['events'] ) && is_array( $s['events'] ) ? array_values( $s['events'] ) : array(),
				'secret_set' => ! empty( $s['secret'] ),
			);
		}
		return array( 'count' => count( $out ), 'webhooks' => $out );
	}

	private static function create_sub( $args ) {
		$url = isset( $args['url'] ) ? esc_url_raw( trim( (string) $args['url'] ) ) : '';
		if ( '' === $url ) {
			throw new \Exception( 'url is required.' );
		}
		$ok = Registry::validate_external_url( $url );
		if ( is_wp_error( $ok ) ) {
			throw new \Exception( esc_html( $ok->get_error_message() ) );
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			throw new \Exception( 'Webhook URL must be an https URL with a host.' );
		}

		$known  = self::known_events();
		$events = array();
		if ( isset( $args['events'] ) && is_array( $args['events'] ) ) {
			foreach ( $args['events'] as $e ) {
				$e = sanitize_key( (string) $e );
				if ( isset( $known[ $e ] ) ) {
					$events[] = $e;
				}
			}
		}
		$events = array_values( array_unique( $events ) );
		if ( empty( $events ) ) {
			throw new \Exception( 'At least one valid event is required. Available: ' . esc_html( implode( ', ', array_keys( $known ) ) ) . '.' );
		}

		$target = (string) ( $parts['host'] ?? 'webhook' );
		if ( ! Guard::is_confirmed( (array) $args, $target ) ) {
			return Guard::preview(
				'webhook_create',
				$target,
				sprintf( 'Create a webhook to %s for events [%s].', $url, implode( ', ', $events ) )
			);
		}

		$id     = 'wh_' . bin2hex( random_bytes( 6 ) );
		$secret = bin2hex( random_bytes( 24 ) );
		$subs   = self::subscriptions();
		$subs[] = array( 'id' => $id, 'url' => $url, 'events' => $events, 'secret' => $secret );
		self::save_subscriptions( $subs );

		return array(
			'success' => true,
			'id'      => $id,
			'url'     => $url,
			'events'  => $events,
			'secret'  => $secret, 
			'message' => 'Webhook created. Store the secret now: it is shown only this once and is used to verify the X-More-MCP-Signature header.',
		);
	}

	private static function delete_sub( $args ) {
		$id = isset( $args['id'] ) ? sanitize_text_field( $args['id'] ) : '';
		if ( '' === $id ) {
			throw new \Exception( 'id is required.' );
		}
		$subs  = self::subscriptions();
		$found = false;
		foreach ( $subs as $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) { $found = true; break; }
		}
		if ( ! $found ) {
			throw new \Exception( 'Webhook not found: ' . esc_html( $id ) );
		}
		if ( ! Guard::is_confirmed( (array) $args, $id ) ) {
			return Guard::preview(
				'webhook_delete',
				$id,
				sprintf( 'Delete webhook %s.', $id )
			);
		}
		$subs = array_values( array_filter( $subs, function ( $s ) use ( $id ) {
			return ( $s['id'] ?? '' ) !== $id;
		} ) );
		self::save_subscriptions( $subs );
		return array( 'success' => true, 'id' => $id, 'message' => 'Webhook deleted.' );
	}

	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status && is_object( $post ) ) {
			
			$type = (string) ( $post->post_type ?? '' );
			if ( in_array( $type, array( 'post', 'page' ), true ) || post_type_exists( $type ) ) {
				self::dispatch( 'post_published', array(
					'post_id'   => (int) $post->ID,
					'post_type' => $type,
					'title'     => get_the_title( $post->ID ),
					'link'      => get_permalink( $post->ID ),
				) );
			}
		}
	}

	public static function on_comment_post( $comment_id ) {
		self::dispatch( 'comment_posted', array( 'comment_id' => (int) $comment_id ) );
	}

	public static function on_user_register( $user_id ) {
		self::dispatch( 'user_registered', array( 'user_id' => (int) $user_id ) );
	}

	public static function on_tool_context( $tool_name, $context ) {

		self::dispatch( 'tool_called', array(
			'tool'          => (string) $tool_name,
			'status'        => is_array( $context ) ? ( $context['status'] ?? '' ) : '',
			'is_destructive'=> is_array( $context ) ? (bool) ( $context['is_destructive'] ?? false ) : false,
		) );
	}

	private static function bound_data( array $data ) {
		$caps = array(
			'title'  => self::MAX_TITLE_LENGTH,
			'tool'   => self::MAX_TOOL_LENGTH,
			'status' => self::MAX_STATUS_LENGTH,
		);
		$bounded = array();
		foreach ( $data as $key => $value ) {
			if ( is_int( $value ) || is_bool( $value ) || is_float( $value ) ) {
				$bounded[ $key ] = $value;
				continue;
			}
			if ( is_string( $value ) ) {
				$cap = isset( $caps[ $key ] ) ? $caps[ $key ] : self::MAX_TITLE_LENGTH;
				if ( function_exists( 'mb_substr' ) ) {
					$bounded[ $key ] = mb_substr( $value, 0, $cap );
				} else {
					$bounded[ $key ] = substr( $value, 0, $cap );
				}
				continue;
			}
			
		}
		return $bounded;
	}

	private static function dispatch( $event, array $data ) {
		$subs = self::subscriptions();
		if ( empty( $subs ) ) {
			return;
		}
		$data = self::bound_data( $data );
		$body = wp_json_encode( array(
			'event'     => $event,
			'site'      => home_url(),
			'timestamp' => time(),
			'data'      => $data,
		) );
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_PAYLOAD_BYTES ) {
			return;
		}
		foreach ( $subs as $s ) {
			$events = isset( $s['events'] ) && is_array( $s['events'] ) ? $s['events'] : array();
			if ( ! in_array( $event, $events, true ) ) {
				continue;
			}
			$url = (string) ( $s['url'] ?? '' );
			if ( '' === $url || is_wp_error( Registry::validate_external_url( $url ) ) ) {
				continue;
			}
			$secret = (string) ( $s['secret'] ?? '' );
			$sig    = '' !== $secret ? hash_hmac( 'sha256', $body, $secret ) : '';
			$headers = array( 'Content-Type' => 'application/json' );
			if ( '' !== $sig ) {
				$headers['X-More-MCP-Signature'] = 'sha256=' . $sig;
			}
			$headers['X-More-MCP-Event'] = $event;
			wp_safe_remote_post( $url, array(
				'timeout'  => 5,
				'blocking' => false, 
				'headers'  => $headers,
				'body'     => $body,
				'limit_response_size' => 1024,
			) );
		}
	}
}
