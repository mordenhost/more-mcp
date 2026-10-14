<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yoast_Features implements Handler {

	const TOOLS = array( 'seo_get_yoast_features', 'seo_update_yoast_features' );

	const UNDO_OP = 'yoast_features_write';

	public static function writable(): array {
		return array(
			
			'keyword_analysis_active'            => array( 'wpseo', 'SEO analysis', false ),
			'content_analysis_active'            => array( 'wpseo', 'Readability analysis', false ),
			'inclusive_language_analysis_active' => array( 'wpseo', 'Inclusive language analysis', false ),
			'enable_cornerstone_content'         => array( 'wpseo', 'Cornerstone content', false ),
			'enable_text_link_counter'           => array( 'wpseo', 'Text link counter', false ),
			'enable_metabox_insights'            => array( 'wpseo', 'Insights', false ),
			'enable_link_suggestions'            => array( 'wpseo', 'Link suggestions', true ),
			'enable_xml_sitemap'                 => array( 'wpseo', 'XML sitemaps', false ),
			'enable_admin_bar_menu'              => array( 'wpseo', 'Admin bar menu', false ),
			'enable_headless_rest_endpoints'     => array( 'wpseo', 'REST API: head endpoint', false ),
			'enable_enhanced_slack_sharing'      => array( 'wpseo', 'Enhanced Slack sharing', false ),
			'enable_index_now'                   => array( 'wpseo', 'IndexNow', true ),
			'enable_ai_generator'                => array( 'wpseo', 'AI title and description generator', true ),
			'enable_schema'                      => array( 'wpseo', 'Schema framework', false ),
			'enable_schema_aggregation_endpoint' => array( 'wpseo', 'Schema aggregation endpoint', false ),
			'enable_task_list'                   => array( 'wpseo', 'Task list', false ),
			
			'opengraph'                          => array( 'wpseo_social', 'Open Graph data', false ),
			'twitter'                            => array( 'wpseo_social', 'X (Twitter) card data', false ),

			'disable-author'                     => array( 'wpseo_titles', 'Disable author archives', false ),
			'disable-date'                       => array( 'wpseo_titles', 'Disable date archives', false ),
			'disable-post_format'                => array( 'wpseo_titles', 'Disable format-based archives', false ),
			'disable-attachment'                 => array( 'wpseo_titles', 'Redirect attachment pages to the file itself', false ),
			'breadcrumbs-enable'                 => array( 'wpseo_titles', 'Breadcrumbs', false ),
		);
	}

	public static function reported_only(): array {
		return array(
			'enable_llms_txt'      => array( 'wpseo', 'llms.txt', 'Not writable through this tool: switching it on makes Yoast write a /llms.txt file, which overlaps with other llms.txt generators. Change it in the Yoast SEO settings.' ),
			'disableadvanced_meta' => array( 'wpseo', 'Security: no advanced or schema settings for authors', 'Not writable through this tool: turning it off lets every author change noindex, canonical and schema settings. Change it in the Yoast SEO settings.' ),
			'tracking'             => array( 'wpseo', 'Usage tracking', 'Not writable through this tool: sending usage data to Yoast is a consent decision for the site owner. Change it in the Yoast SEO settings.' ),
		);
	}

	public static function is_available(): bool {
		return class_exists( 'WPSEO_Options' );
	}

	public static function get_tools(): array {

		if ( ! self::is_available() ) {
			return array();
		}
		$keys = implode( ', ', array_keys( self::writable() ) );
		return array(
			array(
				'name'        => 'seo_get_yoast_features',
				'description' => 'Read Yoast SEO\'s site feature toggles: SEO and readability analysis, XML sitemaps, Open Graph and X card data, author/date/format archives, attachment-page redirects, breadcrumbs, IndexNow, schema, and more. Each entry gives the Yoast option key, its current on/off value, Yoast\'s default, and whether this connection can change it (a toggle locked by the multisite network admin, or one reported for context only, is marked). Booleans only: no token, verification code or other setting is returned. Requires manage_options. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'seo_update_yoast_features',
				'description' => 'Switch Yoast SEO site feature toggles on or off through Yoast\'s own option API (WPSEO_Options::set), so Yoast validates the value and runs its usual follow-up work. Pass features as an object of key => true/false using the keys seo_get_yoast_features returns. The disable-* keys keep Yoast\'s meaning: disable-author=true turns author archives OFF. A site-wide change, so it is a two-part confirmation: call without confirm for a preview of every change (nothing is written); call again with confirm=true and confirm_keys listing exactly the keys in features to apply it (dry_run=true always previews). Every key is read back after the write, and the response carries an undo token that restores the previous values. Writable keys: ' . $keys . '. enable_llms_txt, disableadvanced_meta and tracking are refused. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'features'     => array(
							'type'                 => 'object',
							'description'          => 'Toggle key => true (on) or false (off). Keys as returned by seo_get_yoast_features.',
							'additionalProperties' => array( 'type' => 'boolean' ),
						),
						'confirm'      => array( 'type' => 'boolean', 'description' => 'Must be true to write. Omit or false to receive a preview instead. That preview is the intended first call.' ),
						'dry_run'      => array( 'type' => 'boolean', 'description' => 'true returns the preview and writes nothing, even with confirm=true. Use it for the first call on a site that requires approval for site-wide changes.' ),
						'confirm_keys' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Repeat every key from features. Required alongside confirm=true.',
						),
					),
					'required'   => array( 'features' ),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to manage Yoast SEO features.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Yoast SEO is not active.' );
		}
		switch ( $name ) {
			case 'seo_get_yoast_features':
				return self::get_features();
			case 'seo_update_yoast_features':
				return self::update_features( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	

	private static function get_features(): array {
		$features = array();
		foreach ( self::writable() as $key => $spec ) {
			$locked     = self::network_locked( $spec[0], $key );
			$features[] = array(
				'key'      => $key,
				'option'   => $spec[0],
				'label'    => $spec[1],
				'enabled'  => self::current( $spec[0], $key ),
				'default'  => self::default_value( $spec[0], $key ),
				'premium'  => $spec[2],
				'writable' => ! $locked,
				'locked'   => $locked ? 'network' : null,
				'note'     => $locked ? 'Locked by the multisite network admin; Yoast keeps its current value.' : null,
			);
		}
		foreach ( self::reported_only() as $key => $spec ) {
			$features[] = array(
				'key'      => $key,
				'option'   => $spec[0],
				'label'    => $spec[1],
				'enabled'  => self::current( $spec[0], $key ),
				'default'  => self::default_value( $spec[0], $key ),
				'premium'  => false,
				'writable' => false,
				'locked'   => 'reported_only',
				'note'     => $spec[2],
			);
		}
		return array(
			'provider'     => 'yoast',
			'version'      => defined( 'WPSEO_VERSION' ) ? (string) constant( 'WPSEO_VERSION' ) : null,
			'features'     => $features,
			'settings_url' => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=wpseo_page_settings' ) : null,
		);
	}

	private static function current( string $group, string $key ): ?bool {
		$option = self::read_group( $group );
		if ( array_key_exists( $key, $option ) ) {
			return null === $option[ $key ] ? null : (bool) $option[ $key ];
		}
		return null;
	}

	private static function default_value( string $group, string $key ): ?bool {
		if ( method_exists( 'WPSEO_Options', 'get_default' ) ) {
			$default = \WPSEO_Options::get_default( $group, $key );
			return null === $default ? null : (bool) $default;
		}
		return null;
	}

	private static function read_group( string $group ): array {
		$value = null;
		if ( method_exists( 'WPSEO_Options', 'get_option' ) ) {
			$value = \WPSEO_Options::get_option( $group );
		}
		if ( ! is_array( $value ) ) {
			$value = get_option( $group, array() );
		}
		return is_array( $value ) ? $value : array();
	}

	private static function network_locked( string $group, string $key ): bool {
		if ( ! method_exists( 'WPSEO_Options', 'get_option_instance' ) ) {
			return false;
		}
		$instance = \WPSEO_Options::get_option_instance( $group );
		if ( ! is_object( $instance ) || ! method_exists( $instance, 'is_disabled' ) ) {
			return false;
		}
		return (bool) $instance->is_disabled( $key );
	}

	

	private static function update_features( array $args ): array {
		$requested = self::validate_request( $args['features'] ?? null );

		$writable = self::writable();
		$changes  = array();
		$same     = array();
		foreach ( $requested as $key => $want ) {
			$group = $writable[ $key ][0];
			$from  = self::current( $group, $key );
			if ( $from === $want ) {
				$same[] = $key;
				continue;
			}
			$changes[ $key ] = array(
				'key'    => $key,
				'option' => $group,
				'label'  => $writable[ $key ][1],
				'from'   => $from,
				'to'     => $want,
			);
		}

		$keys = array_keys( $requested );
		sort( $keys );
		$confirm_keys = self::confirm_keys( $args['confirm_keys'] ?? null );

		$dry_run   = \Mordenhost_MCP\Access\Destructive::is_dry_run( 'seo_update_yoast_features', $args );
		$confirmed = ! $dry_run && ! empty( $args['confirm'] ) && $confirm_keys === $keys;

		if ( ! $confirmed ) {
			return array(
				'preview'      => true,
				'changes'      => array_values( $changes ),
				'unchanged'    => $same,
				'confirm_keys' => $keys,
				'message'      => $changes
					? 'Preview only, nothing was written. To apply, call again with confirm=true and confirm_keys=' . wp_json_encode( $keys ) . '.'
					: 'Nothing to change: every requested toggle already has that value.',
			);
		}

		if ( ! $changes ) {
			return array(
				'success'   => true,
				'changed'   => array(),
				'unchanged' => $same,
				'message'   => 'Nothing to change: every requested toggle already has that value.',
			);
		}

		$applied  = array();
		$previous = array();
		foreach ( $changes as $key => $change ) {
			\WPSEO_Options::set( $key, $change['to'], $change['option'] );
			$stored            = self::current( $change['option'], $key );
			$change['stored']  = $stored;
			$change['saved']   = ( $stored === $change['to'] );
			$applied[]         = $change;
			if ( $stored !== $change['from'] ) {
				$previous[ $key ] = array(
					'option' => $change['option'],
					'value'  => $change['from'],
				);
			}
		}

		$verified = ! in_array( false, array_column( $applied, 'saved' ), true );
		$result   = array(
			'success'     => $verified,
			'changed'     => $applied,
			'unchanged'   => $same,
			'verified'    => $verified,
			'verify_note' => $verified
				? 'Every key was read back from Yoast\'s option after the write.'
				: 'At least one key did not keep the requested value after the write (Yoast\'s validation or a network lock kept the old one). See saved/stored per key.',
		);

		if ( $previous ) {
			$result['undo'] = \Mordenhost_MCP\MCP\Undo_Store::store(
				array(
					'op'           => self::UNDO_OP,
					'summary'      => 'Yoast SEO features: ' . implode( ', ', array_keys( $previous ) ),
					'target'       => array( 'provider' => 'yoast' ),
					'pre_op_state' => $previous,
				)
			);
		}
		return $result;
	}

	private static function validate_request( $features ): array {
		if ( is_object( $features ) ) {
			$features = get_object_vars( $features );
		}
		if ( ! is_array( $features ) || ! $features ) {
			throw new \Exception( 'features is required: an object of Yoast toggle key => true/false. Call seo_get_yoast_features for the keys.' );
		}

		$writable = self::writable();
		$reported = self::reported_only();
		$out      = array();
		foreach ( $features as $key => $value ) {
			$key = (string) $key;
			if ( isset( $reported[ $key ] ) ) {
				throw new \Exception( esc_html( $key . ': ' . $reported[ $key ][2] ) );
			}
			if ( ! isset( $writable[ $key ] ) ) {
				throw new \Exception( 'Unknown Yoast feature key: ' . esc_html( $key ) . '. Writable keys: ' . esc_html( implode( ', ', array_keys( $writable ) ) ) . '.' );
			}
			if ( ! is_bool( $value ) ) {
				throw new \Exception( 'The value for ' . esc_html( $key ) . ' must be true or false.' );
			}
			if ( self::network_locked( $writable[ $key ][0], $key ) ) {
				throw new \Exception( esc_html( $key ) . ' is locked by the multisite network admin, so Yoast would keep its current value. Nothing was written.' );
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	private static function confirm_keys( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$keys = array_values( array_unique( array_map( 'strval', $value ) ) );
		sort( $keys );
		return $keys;
	}

	

	
	public static function undo_features_write( array $snapshot ): array {
		if ( ! self::is_available() ) {
			throw new \Exception( 'Yoast SEO is not active, so this change cannot be undone.' );
		}
		$previous = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();
		$writable = self::writable();
		$restored = array();
		foreach ( $previous as $key => $entry ) {
			$key = (string) $key;
			if ( ! isset( $writable[ $key ] ) || ! is_array( $entry ) || ! array_key_exists( 'value', $entry ) ) {
				continue;
			}
			$group = $writable[ $key ][0];
			$value = null === $entry['value'] ? null : (bool) $entry['value'];
			if ( null === $value ) {

				$value = self::default_value( $group, $key );
				if ( null === $value ) {
					continue;
				}
			}
			\WPSEO_Options::set( $key, $value, $group );
			$stored     = self::current( $group, $key );
			$restored[] = array(
				'key'    => $key,
				'option' => $group,
				'value'  => $value,
				'stored' => $stored,
				'saved'  => $stored === $value,
			);
		}
		if ( ! $restored ) {
			throw new \Exception( 'The undo snapshot holds no Yoast toggle to restore.' );
		}
		return array(
			'success'          => ! in_array( false, array_column( $restored, 'saved' ), true ),
			'op'               => self::UNDO_OP,
			'restored'         => $restored,
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}
}
