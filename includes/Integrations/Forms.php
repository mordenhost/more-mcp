<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forms {

	const PROVIDERS = array( 'gravityforms', 'fluentforms' );

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
			'capabilities' => array( 'forms' ),
			'kind'         => 'plugin',
		);
	}

	private static function provider_available( $provider ) {
		if ( 'gravityforms' === $provider ) {
			return class_exists( 'GFAPI' );
		}
		if ( 'fluentforms' === $provider ) {

			return function_exists( 'fluentFormApi' ) || class_exists( '\FluentForm\App\Models\Submission' );
		}
		return false;
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		$provider_enum = array_merge( array( 'all' ), self::PROVIDERS );

		return array(
			array(
				'name'        => 'forms_list',
				'description' => 'List forms across active form plugins (Gravity Forms, Fluent Forms). Returns id, provider, title, active flag, and entry_count for each. Normalized so every provider looks the same. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => $provider_enum, 'description' => 'Provider to query. Defaults to all active providers.' ),
					),
				),
			),
			array(
				'name'        => 'forms_get',
				'description' => 'Get one form and its normalized field schema: [{id, label, type, required}]. Provider identity must be supplied because form ids are only unique within a provider. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'form_id'  => array( 'type' => 'integer', 'description' => 'Form id within that provider.' ),
					),
					'required'   => array( 'provider', 'form_id' ),
				),
			),
			array(
				'name'        => 'forms_list_entries',
				'description' => 'List submissions for a form, paginated. Returns SUMMARY rows only (id, date, status, is_read), never field values, which can hold personal data. Use forms_get_entry for an addressed full read. Filters: status, start_date, end_date. Returns {total, page, per_page, pages, returned, has_more, entries[]}.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider'   => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'form_id'    => array( 'type' => 'integer' ),
						'status'     => array( 'type' => 'string', 'description' => 'Normalized status filter: active, spam, trash, read, unread. Mapped per provider.' ),
						'start_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD lower bound on submission date.' ),
						'end_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD upper bound on submission date.' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'Rows per page, 1 to 100. Default 20.' ),
					),
					'required'   => array( 'provider', 'form_id' ),
				),
			),
			array(
				'name'        => 'forms_get_entry',
				'description' => 'Read one submission in full, including field values. Sensitive metadata (IP, user agent, browser/device, and payment/transaction fields) is redacted by default. This is the addressed read that returns personal data; list tools never do.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'entry_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'provider', 'entry_id' ),
				),
			),
			array(
				'name'        => 'forms_get_stats',
				'description' => 'Aggregate submission counts for a form by status, without returning any submission bodies. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'form_id'  => array( 'type' => 'integer' ),
					),
					'required'   => array( 'provider', 'form_id' ),
				),
			),
			array(
				'name'        => 'forms_update_entry_status',
				'description' => 'Change one submission\'s status through the provider\'s own API. Normalized status: active, spam, read, unread (mapped per provider). Emits an undo token. Requires an admin capability.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider' => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'entry_id' => array( 'type' => 'integer' ),
						'status'   => array( 'type' => 'string', 'enum' => array( 'active', 'spam', 'read', 'unread' ), 'description' => 'New normalized status.' ),
					),
					'required'   => array( 'provider', 'entry_id', 'status' ),
				),
			),
			array(
				'name'        => 'forms_trash_entry',
				'description' => 'Move one submission to trash (never a permanent delete). Two-part confirmation: call without confirm to receive a preview of the target and write nothing; call with confirm=true AND confirm_entry_id matching entry_id to apply. Emits an undo token that restores the prior status. Requires an admin capability.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'provider'        => array( 'type' => 'string', 'enum' => self::PROVIDERS ),
						'entry_id'        => array( 'type' => 'integer' ),
						'confirm'         => array( 'type' => 'boolean', 'description' => 'Must be true to apply. Omit or false to receive a preview instead. That preview is the intended first call.' ),
						'confirm_entry_id' => array( 'type' => 'integer', 'description' => 'Repeat entry_id. Required alongside confirm=true; cannot be satisfied without having read the preview.' ),
					),
					'required'   => array( 'provider', 'entry_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use forms tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'No supported form plugin is active.' );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'all';

		switch ( $name ) {
			case 'forms_list':
				if ( 'all' !== $provider && ! in_array( $provider, self::PROVIDERS, true ) ) {
					throw new \Exception( 'Unknown forms provider: ' . esc_html( $provider ) );
				}
				$forms = array();
				foreach ( self::requested_providers( $provider ) as $requested ) {
					if ( ! self::provider_available( $requested ) ) {
						continue;
					}
					$forms = array_merge( $forms, self::list_forms( $requested ) );
				}
				return array( 'forms' => $forms );

			case 'forms_get':
				self::require_provider( $provider );
				return self::get_form( $provider, absint( $args['form_id'] ?? 0 ) );

			case 'forms_list_entries':
				self::require_provider( $provider );
				return self::list_entries( $provider, $args );

			case 'forms_get_entry':
				self::require_provider( $provider );
				return self::get_entry( $provider, absint( $args['entry_id'] ?? 0 ) );

			case 'forms_get_stats':
				self::require_provider( $provider );
				return self::get_stats( $provider, absint( $args['form_id'] ?? 0 ) );

			case 'forms_update_entry_status':
				self::require_provider( $provider );
				return self::update_entry_status( $provider, absint( $args['entry_id'] ?? 0 ), sanitize_key( $args['status'] ?? '' ) );

			case 'forms_trash_entry':
				self::require_provider( $provider );
				return self::trash_entry( $provider, absint( $args['entry_id'] ?? 0 ), $args );

			default:
				throw new \Exception( 'Unknown forms tool: ' . esc_html( $name ) );
		}
	}

	private static function requested_providers( $provider ) {
		return 'all' === $provider ? self::PROVIDERS : array( $provider );
	}

	private static function require_provider( $provider ) {
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			throw new \Exception( 'A specific provider is required for this tool: ' . esc_html( implode( ', ', self::PROVIDERS ) ) );
		}
		if ( ! self::provider_available( $provider ) ) {
			throw new \Exception( 'The ' . esc_html( $provider ) . ' plugin is not active.' );
		}
	}

	private static function list_forms( $provider ) {
		return 'gravityforms' === $provider ? Forms\Gravity_Forms_Adapter::gf_list_forms() : Forms\Fluent_Forms_Adapter::ff_list_forms();
	}

	private static function get_form( $provider, $form_id ) {
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		return 'gravityforms' === $provider ? Forms\Gravity_Forms_Adapter::gf_get_form( $form_id ) : Forms\Fluent_Forms_Adapter::ff_get_form( $form_id );
	}

	private static function list_entries( $provider, $args ) {
		$form_id = absint( $args['form_id'] ?? 0 );
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$range    = Forms\Normalizers::date_range( $args );
		return 'gravityforms' === $provider
			? Forms\Gravity_Forms_Adapter::gf_list_entries( $form_id, $status, $range, $page, $per_page )
			: Forms\Fluent_Forms_Adapter::ff_list_entries( $form_id, $status, $range, $page, $per_page );
	}

	private static function get_entry( $provider, $entry_id ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		return 'gravityforms' === $provider ? Forms\Gravity_Forms_Adapter::gf_get_entry( $entry_id ) : Forms\Fluent_Forms_Adapter::ff_get_entry( $entry_id );
	}

	private static function get_stats( $provider, $form_id ) {
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		return 'gravityforms' === $provider ? Forms\Gravity_Forms_Adapter::gf_get_stats( $form_id ) : Forms\Fluent_Forms_Adapter::ff_get_stats( $form_id );
	}

	private static function update_entry_status( $provider, $entry_id, $status ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		if ( ! in_array( $status, array( 'active', 'spam', 'read', 'unread' ), true ) ) {
			throw new \Exception( 'status must be one of: active, spam, read, unread.' );
		}
		return 'gravityforms' === $provider
			? Forms\Gravity_Forms_Adapter::gf_update_entry_status( $entry_id, $status )
			: Forms\Fluent_Forms_Adapter::ff_update_entry_status( $entry_id, $status );
	}

	private static function trash_entry( $provider, $entry_id, $args ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		$confirmed = ! empty( $args['confirm'] ) && absint( $args['confirm_entry_id'] ?? 0 ) === $entry_id;
		if ( ! $confirmed ) {
			
			return array(
				'preview'  => true,
				'written'  => false,
				'provider' => $provider,
				'entry_id' => $entry_id,
				'action'   => 'trash',
				'message'  => 'Preview only. To apply, call again with confirm=true and confirm_entry_id=' . $entry_id . '. The entry is moved to trash, not permanently deleted, and an undo token is returned.',
			);
		}
		return 'gravityforms' === $provider
			? Forms\Gravity_Forms_Adapter::gf_trash_entry( $entry_id )
			: Forms\Fluent_Forms_Adapter::ff_trash_entry( $entry_id );
	}

	
	public static function undo_entry_write( array $snapshot ) {
		$target   = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();
		$provider = isset( $target['provider'] ) ? sanitize_key( $target['provider'] ) : '';
		$entry_id = isset( $target['entry_id'] ) ? (int) $target['entry_id'] : 0;
		$prior    = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();

		if ( ! in_array( $provider, self::PROVIDERS, true ) || $entry_id <= 0 || empty( $prior['property'] ) ) {
			throw new \Exception( 'Undo snapshot is incomplete.' );
		}
		if ( ! self::provider_available( $provider ) ) {
			throw new \Exception( 'The ' . esc_html( $provider ) . ' plugin is no longer active; cannot undo.' );
		}

		$property = (string) $prior['property'];
		$value    = (string) ( $prior['value'] ?? '' );

		if ( 'gravityforms' === $provider ) {
			$result = \GFAPI::update_entry_property( $entry_id, $property, $value );
			if ( is_wp_error( $result ) || false === $result ) {
				throw new \Exception( 'Failed to restore Gravity Forms entry.' );
			}
		} else {
			$model = Forms\Fluent_Forms_Adapter::ff_submission_model();
			if ( ! $model ) {
				throw Forms\Fluent_Forms_Adapter::ff_unavailable();
			}
			$entry = $model::query()->find( $entry_id );
			if ( ! $entry ) {
				throw new \Exception( 'Fluent Forms submission no longer exists; cannot undo.' );
			}
			$entry->{$property} = $value;
			$entry->save();
		}

		return array(
			'success'  => true,
			'op'       => 'forms_entry_write',
			'provider' => $provider,
			'entry_id' => $entry_id,
			'restored' => array( 'property' => $property, 'value' => $value ),
			'restored_summary' => isset( $snapshot['summary'] ) ? (string) $snapshot['summary'] : '',
		);
	}
}
