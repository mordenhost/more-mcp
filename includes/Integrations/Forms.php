<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms and Lead Capture — normalized read + guarded-write adapter.
 *
 * Capabilities before brands: this class exposes ONE `forms_*` contract and puts
 * provider-specific adapters behind it, so an MCP client reads a WPForms-style
 * form the same way it reads a Gravity Forms one. Provider identity is carried in
 * every request (`provider`) and echoed on every response row.
 *
 * Providers (v1):
 *  - Gravity Forms — via the public, stable GFAPI class. Signatures confirmed from
 *    docs.gravityforms.com (get_forms/get_entries/get_entry/count_entries/
 *    update_entry_property). This is the first end-to-end provider.
 *  - Fluent Forms — via its Eloquent models / query builder. Table shape confirmed
 *    (wp_fluentform_submissions, wp_fluent_forms.form_fields JSON) but the exact
 *    stable read API and capability string are marked needs-live-verification below;
 *    resolve them against the installed plugin's source before relying on this path.
 *
 * Capability model: every tool gates on manage_options, matching the other
 * sensitive read integrations (GuardPress, SiteVault, Analytics). Form entries
 * routinely contain names, emails, phone numbers, and uploaded files, and the two
 * write tools mutate entry state — admin-tier is the safe floor. The cap check
 * fires BEFORE the availability check so a lower-privilege caller cannot use the
 * error to fingerprint which form plugins are installed.
 *
 * Writes (Stage 3, guarded):
 *  - forms_update_entry_status — provider-native status change, emits an undo token.
 *  - forms_trash_entry         — two-part confirmation, trashes (never hard-deletes),
 *                                emits an undo token. GF: status=>trash via
 *                                update_entry_property. FF: status=>trashed.
 */
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

	private static function provider_available( $provider ) {
		if ( 'gravityforms' === $provider ) {
			return class_exists( 'GFAPI' );
		}
		if ( 'fluentforms' === $provider ) {
			// FF ships this helper and its Eloquent models together; either signals
			// a usable install. The concrete read path is resolved at call time.
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
				'description' => 'List submissions for a form, paginated. Returns SUMMARY rows only (id, date, status, is_read) — never field values, which can hold personal data. Use forms_get_entry for an addressed full read. Filters: status, start_date, end_date. Returns {total, page, per_page, pages, returned, has_more, entries[]}.',
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
						'confirm'         => array( 'type' => 'boolean', 'description' => 'Must be true to apply. Omit or false to receive a preview instead — that preview is the intended first call.' ),
						'confirm_entry_id' => array( 'type' => 'integer', 'description' => 'Repeat entry_id. Required alongside confirm=true; cannot be satisfied without having read the preview.' ),
					),
					'required'   => array( 'provider', 'entry_id' ),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {
		// Cap check BEFORE availability — a lower-privilege caller must not be able
		// to tell from the error whether a form plugin is installed. All forms tools
		// are admin-tier: entries carry personal data and two tools mutate state.
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use forms tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'No supported form plugin is active.' );
		}

		// Reads may span providers ('all'); writes and addressed reads require one.
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

	/* ==================== Dispatch to per-provider adapters ==================== */

	private static function list_forms( $provider ) {
		return 'gravityforms' === $provider ? self::gf_list_forms() : self::ff_list_forms();
	}

	private static function get_form( $provider, $form_id ) {
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		return 'gravityforms' === $provider ? self::gf_get_form( $form_id ) : self::ff_get_form( $form_id );
	}

	private static function list_entries( $provider, $args ) {
		$form_id = absint( $args['form_id'] ?? 0 );
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$range    = self::date_range( $args );
		return 'gravityforms' === $provider
			? self::gf_list_entries( $form_id, $status, $range, $page, $per_page )
			: self::ff_list_entries( $form_id, $status, $range, $page, $per_page );
	}

	private static function get_entry( $provider, $entry_id ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		return 'gravityforms' === $provider ? self::gf_get_entry( $entry_id ) : self::ff_get_entry( $entry_id );
	}

	private static function get_stats( $provider, $form_id ) {
		if ( $form_id <= 0 ) {
			throw new \Exception( 'form_id is required.' );
		}
		return 'gravityforms' === $provider ? self::gf_get_stats( $form_id ) : self::ff_get_stats( $form_id );
	}

	private static function update_entry_status( $provider, $entry_id, $status ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		if ( ! in_array( $status, array( 'active', 'spam', 'read', 'unread' ), true ) ) {
			throw new \Exception( 'status must be one of: active, spam, read, unread.' );
		}
		return 'gravityforms' === $provider
			? self::gf_update_entry_status( $entry_id, $status )
			: self::ff_update_entry_status( $entry_id, $status );
	}

	private static function trash_entry( $provider, $entry_id, $args ) {
		if ( $entry_id <= 0 ) {
			throw new \Exception( 'entry_id is required.' );
		}
		$confirmed = ! empty( $args['confirm'] ) && absint( $args['confirm_entry_id'] ?? 0 ) === $entry_id;
		if ( ! $confirmed ) {
			// Unconfirmed call is not an error: return a preview and write nothing.
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
			? self::gf_trash_entry( $entry_id )
			: self::ff_trash_entry( $entry_id );
	}

	/* ==================== Gravity Forms ==================== */

	private static function gf_list_forms() {
		$forms = \GFAPI::get_forms();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
			$out[]   = array(
				'provider'    => 'gravityforms',
				'id'          => $form_id,
				'title'       => isset( $form['title'] ) ? (string) $form['title'] : '',
				'active'      => empty( $form['is_active'] ) ? false : (bool) $form['is_active'],
				'entry_count' => $form_id > 0 ? (int) \GFAPI::count_entries( $form_id, array() ) : 0,
			);
		}
		return $out;
	}

	private static function gf_get_form( $form_id ) {
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			throw new \Exception( 'Gravity Forms form not found.' );
		}
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			// GF fields are GF_Field objects; read via property access with guards.
			$fields[] = self::normalize_field(
				(string) ( is_object( $field ) ? ( $field->id ?? '' ) : ( $field['id'] ?? '' ) ),
				(string) ( is_object( $field ) ? ( $field->label ?? '' ) : ( $field['label'] ?? '' ) ),
				(string) ( is_object( $field ) ? ( $field->type ?? '' ) : ( $field['type'] ?? '' ) ),
				(bool) ( is_object( $field ) ? ( $field->isRequired ?? false ) : ( $field['isRequired'] ?? false ) )
			);
		}
		return array(
			'provider' => 'gravityforms',
			'id'       => (int) ( $form['id'] ?? $form_id ),
			'title'    => (string) ( $form['title'] ?? '' ),
			'active'   => empty( $form['is_active'] ) ? false : (bool) $form['is_active'],
			'fields'   => $fields,
		);
	}

	private static function gf_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$search = array();
		// Normalized read/unread is an is_read property in GF, not a status; only
		// active/spam/trash map to the GF status search key.
		if ( in_array( $status, array( 'active', 'spam', 'trash' ), true ) ) {
			$search['status'] = $status;
		}
		if ( ! empty( $range['start_date'] ) ) {
			$search['start_date'] = $range['start_date'];
		}
		if ( ! empty( $range['end_date'] ) ) {
			$search['end_date'] = $range['end_date'];
		}
		$sorting     = array( 'key' => 'id', 'direction' => 'DESC' );
		$paging      = array( 'offset' => ( $page - 1 ) * $per_page, 'page_size' => $per_page );
		$total_count = 0;
		$entries     = \GFAPI::get_entries( $form_id, $search, $sorting, $paging, $total_count );
		if ( is_wp_error( $entries ) ) {
			throw new \Exception( 'Gravity Forms entry query failed: ' . esc_html( $entries->get_error_message() ) );
		}
		$rows = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = self::normalize_entry_summary(
				'gravityforms',
				(int) ( $entry['id'] ?? 0 ),
				(string) ( $entry['date_created'] ?? '' ),
				(string) ( $entry['status'] ?? 'active' ),
				! empty( $entry['is_read'] )
			);
		}
		return self::paginate( (int) $total_count, $page, $per_page, $rows );
	}

	private static function gf_get_entry( $entry_id ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		return self::normalize_entry_detail( 'gravityforms', self::gf_redact( (array) $entry ) );
	}

	private static function gf_get_stats( $form_id ) {
		$by_status = array();
		foreach ( array( 'active', 'spam', 'trash' ) as $status ) {
			$by_status[ $status ] = (int) \GFAPI::count_entries( $form_id, array( 'status' => $status ) );
		}
		return array(
			'provider'  => 'gravityforms',
			'form_id'   => (int) $form_id,
			'total'     => (int) \GFAPI::count_entries( $form_id, array() ),
			'by_status' => $by_status,
		);
	}

	private static function gf_update_entry_status( $entry_id, $status ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		// read/unread are the is_read property; active/spam are the status property.
		if ( in_array( $status, array( 'read', 'unread' ), true ) ) {
			$property = 'is_read';
			$value    = 'read' === $status ? '1' : '0';
			$prior    = array( 'property' => 'is_read', 'value' => empty( $entry['is_read'] ) ? '0' : '1' );
		} else {
			$property = 'status';
			$value    = $status;
			$prior    = array( 'property' => 'status', 'value' => (string) ( $entry['status'] ?? 'active' ) );
		}
		$undo   = self::store_undo( 'gravityforms', $entry_id, $prior, 'Restore Gravity Forms entry #' . $entry_id . ' ' . $prior['property'] . ' to ' . $prior['value'] );
		$result = \GFAPI::update_entry_property( $entry_id, $property, $value );
		if ( is_wp_error( $result ) || false === $result ) {
			throw new \Exception( 'Failed to update Gravity Forms entry status.' );
		}
		return array(
			'written'  => true,
			'provider' => 'gravityforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	private static function gf_trash_entry( $entry_id ) {
		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! $entry ) {
			throw new \Exception( 'Gravity Forms entry not found.' );
		}
		$prior  = array( 'property' => 'status', 'value' => (string) ( $entry['status'] ?? 'active' ) );
		$undo   = self::store_undo( 'gravityforms', $entry_id, $prior, 'Restore Gravity Forms entry #' . $entry_id . ' from trash to ' . $prior['value'] );
		// Trash via status change, NOT GFAPI::delete_entry (which hard-deletes).
		$result = \GFAPI::update_entry_property( $entry_id, 'status', 'trash' );
		if ( is_wp_error( $result ) || false === $result ) {
			throw new \Exception( 'Failed to trash Gravity Forms entry.' );
		}
		return array(
			'written'  => true,
			'provider' => 'gravityforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	/**
	 * Strip sensitive metadata from a GF entry before an addressed read. Field
	 * values (keyed by numeric string ids) are kept — this is the full read — but
	 * IP, user agent, and payment/transaction metadata are removed by default.
	 */
	private static function gf_redact( array $entry ) {
		foreach ( array( 'ip', 'user_agent', 'payment_status', 'payment_amount', 'payment_date', 'payment_method', 'transaction_id', 'transaction_type' ) as $key ) {
			unset( $entry[ $key ] );
		}
		return $entry;
	}

	/* ==================== Fluent Forms ==================== */
	// needs-live-verification: the exact stable read API (Eloquent models vs the
	// wpFluent() query builder vs fluentFormApi()) and the FF capability string
	// were not confirmable from a primary source when this was written. The table
	// shape (wp_fluentform_submissions, wp_fluent_forms.form_fields JSON) and the
	// submission status strings (unread/read/trashed) ARE confirmed. Each method
	// below guards its dependencies and throws a clean error rather than fataling
	// when the assumed surface is absent, so an unverified path degrades safely.

	private static function ff_form_model() {
		$class = '\FluentForm\App\Models\Form';
		return class_exists( $class ) ? $class : null;
	}

	private static function ff_submission_model() {
		$class = '\FluentForm\App\Models\Submission';
		return class_exists( $class ) ? $class : null;
	}

	private static function ff_unavailable() {
		return new \Exception( 'Fluent Forms read API is unavailable in this version. Its model/query surface could not be resolved; verify against the installed plugin.' );
	}

	private static function ff_list_forms() {
		$model = self::ff_form_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$forms = $model::query()->get();
		$out   = array();
		foreach ( (array) $forms as $form ) {
			$form_id = (int) ( $form->id ?? 0 );
			$out[]   = array(
				'provider'    => 'fluentforms',
				'id'          => $form_id,
				'title'       => (string) ( $form->title ?? '' ),
				'active'      => isset( $form->status ) ? ( 'published' === $form->status ) : true,
				'entry_count' => $form_id > 0 ? self::ff_count( $form_id ) : 0,
			);
		}
		return $out;
	}

	private static function ff_get_form( $form_id ) {
		$model = self::ff_form_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$form = $model::query()->find( $form_id );
		if ( ! $form ) {
			throw new \Exception( 'Fluent Forms form not found.' );
		}
		$fields  = array();
		$decoded = json_decode( (string) ( $form->form_fields ?? '' ), true );
		if ( is_array( $decoded ) && isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ) {
			$fields = self::ff_flatten_fields( $decoded['fields'] );
		}
		return array(
			'provider' => 'fluentforms',
			'id'       => (int) ( $form->id ?? $form_id ),
			'title'    => (string) ( $form->title ?? '' ),
			'active'   => isset( $form->status ) ? ( 'published' === $form->status ) : true,
			'fields'   => $fields,
		);
	}

	/**
	 * FF stores fields as a nested tree (containers hold child fields). Flatten to
	 * the normalized [{id,label,type,required}] list, reading the attributes/
	 * settings shape FF uses for each field node.
	 */
	private static function ff_flatten_fields( array $nodes ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['fields'] ) && is_array( $node['fields'] ) ) {
				$out = array_merge( $out, self::ff_flatten_fields( $node['fields'] ) );
				continue;
			}
			if ( isset( $node['columns'] ) && is_array( $node['columns'] ) ) {
				foreach ( $node['columns'] as $column ) {
					if ( isset( $column['fields'] ) && is_array( $column['fields'] ) ) {
						$out = array_merge( $out, self::ff_flatten_fields( $column['fields'] ) );
					}
				}
				continue;
			}
			$attributes = isset( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : array();
			$settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$name       = (string) ( $attributes['name'] ?? ( $node['uniqElKey'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out[] = self::normalize_field(
				$name,
				(string) ( $settings['label'] ?? ( $settings['admin_field_label'] ?? '' ) ),
				(string) ( $node['element'] ?? '' ),
				! empty( $settings['validation_rules']['required']['value'] )
			);
		}
		return $out;
	}

	private static function ff_count( $form_id, $status = '' ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			return 0;
		}
		$query = $model::query()->where( 'form_id', $form_id );
		if ( '' !== $status ) {
			$query = $query->where( 'status', $status );
		}
		return (int) $query->count();
	}

	private static function ff_list_entries( $form_id, $status, $range, $page, $per_page ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$ff_status = self::ff_map_status_filter( $status );
		$query     = $model::query()->where( 'form_id', $form_id );
		if ( '' !== $ff_status ) {
			$query = $query->where( 'status', $ff_status );
		}
		if ( ! empty( $range['start_date'] ) ) {
			$query = $query->where( 'created_at', '>=', $range['start_date'] . ' 00:00:00' );
		}
		if ( ! empty( $range['end_date'] ) ) {
			$query = $query->where( 'created_at', '<=', $range['end_date'] . ' 23:59:59' );
		}
		$total   = (int) $query->count();
		$entries = $query->orderBy( 'id', 'DESC' )->skip( ( $page - 1 ) * $per_page )->take( $per_page )->get();
		$rows    = array();
		foreach ( (array) $entries as $entry ) {
			$rows[] = self::normalize_entry_summary(
				'fluentforms',
				(int) ( $entry->id ?? 0 ),
				(string) ( $entry->created_at ?? '' ),
				(string) ( $entry->status ?? 'unread' ),
				isset( $entry->status ) ? ( 'unread' !== $entry->status ) : false
			);
		}
		return self::paginate( $total, $page, $per_page, $rows );
	}

	private static function ff_get_entry( $entry_id ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}
		$values = json_decode( (string) ( $entry->response ?? '' ), true );
		return self::normalize_entry_detail(
			'fluentforms',
			array(
				'id'           => (int) ( $entry->id ?? 0 ),
				'form_id'      => (int) ( $entry->form_id ?? 0 ),
				'date_created' => (string) ( $entry->created_at ?? '' ),
				'status'       => (string) ( $entry->status ?? 'unread' ),
				'values'       => is_array( $values ) ? $values : array(),
				// ip/browser/device deliberately omitted (redacted by default).
			)
		);
	}

	private static function ff_get_stats( $form_id ) {
		$by_status = array();
		foreach ( array( 'unread', 'read', 'trashed' ) as $status ) {
			$by_status[ $status ] = self::ff_count( $form_id, $status );
		}
		return array(
			'provider'  => 'fluentforms',
			'form_id'   => (int) $form_id,
			'total'     => self::ff_count( $form_id ),
			'by_status' => $by_status,
		);
	}

	private static function ff_update_entry_status( $entry_id, $status ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}
		// FF has no 'spam'/'active'; normalized read->read, unread->unread. Anything
		// else is refused by name rather than written to a plausible-looking value.
		$ff_status = self::ff_map_status_write( $status );
		if ( null === $ff_status ) {
			throw new \Exception( 'Fluent Forms does not support the "' . esc_html( $status ) . '" status. Supported: read, unread.' );
		}
		$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'unread' ) );
		$undo  = self::store_undo( 'fluentforms', $entry_id, $prior, 'Restore Fluent Forms submission #' . $entry_id . ' status to ' . $prior['value'] );
		$entry->status = $ff_status;
		$entry->save();
		return array(
			'written'  => true,
			'provider' => 'fluentforms',
			'entry_id' => (int) $entry_id,
			'status'   => $status,
			'undo'     => $undo,
		);
	}

	private static function ff_trash_entry( $entry_id ) {
		$model = self::ff_submission_model();
		if ( ! $model ) {
			throw self::ff_unavailable();
		}
		$entry = $model::query()->find( $entry_id );
		if ( ! $entry ) {
			throw new \Exception( 'Fluent Forms submission not found.' );
		}
		$prior = array( 'property' => 'status', 'value' => (string) ( $entry->status ?? 'unread' ) );
		$undo  = self::store_undo( 'fluentforms', $entry_id, $prior, 'Restore Fluent Forms submission #' . $entry_id . ' from trash to ' . $prior['value'] );
		$entry->status = 'trashed';
		$entry->save();
		return array(
			'written'  => true,
			'provider' => 'fluentforms',
			'entry_id' => (int) $entry_id,
			'action'   => 'trash',
			'undo'     => $undo,
		);
	}

	private static function ff_map_status_filter( $status ) {
		// Normalized -> FF status column value for LIST filtering.
		if ( 'read' === $status ) {
			return 'read';
		}
		if ( 'unread' === $status ) {
			return 'unread';
		}
		if ( 'trash' === $status ) {
			return 'trashed';
		}
		return ''; // 'active'/'spam'/empty -> no FF status filter.
	}

	private static function ff_map_status_write( $status ) {
		if ( 'read' === $status ) {
			return 'read';
		}
		if ( 'unread' === $status ) {
			return 'unread';
		}
		return null; // active/spam unsupported in FF.
	}

	/* ==================== Shared normalizers + helpers ==================== */

	private static function normalize_field( $id, $label, $type, $required ) {
		return array(
			'id'       => (string) $id,
			'label'    => (string) $label,
			'type'     => (string) $type,
			'required' => (bool) $required,
		);
	}

	private static function normalize_entry_summary( $provider, $id, $date, $status, $is_read ) {
		// SUMMARY row: identity + state only. No field values — those carry PII and
		// are only returned by the addressed forms_get_entry read.
		return array(
			'provider' => (string) $provider,
			'id'       => (int) $id,
			'date'     => (string) $date,
			'status'   => (string) $status,
			'is_read'  => (bool) $is_read,
		);
	}

	private static function normalize_entry_detail( $provider, array $entry ) {
		$entry['provider'] = (string) $provider;
		return $entry;
	}

	/** Canonical paginated envelope, matching wp_get_media / wp_get_post_revisions. */
	private static function paginate( $total, $page, $per_page, array $rows ) {
		return array(
			'total'    => (int) $total,
			'page'     => (int) $page,
			'per_page' => (int) $per_page,
			'pages'    => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'returned' => count( $rows ),
			'has_more' => ( ( $page - 1 ) * $per_page + count( $rows ) ) < $total,
			'entries'  => $rows,
		);
	}

	private static function date_range( $args ) {
		$range = array( 'start_date' => '', 'end_date' => '' );
		foreach ( array( 'start_date', 'end_date' ) as $key ) {
			if ( empty( $args[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $args[ $key ] );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				throw new \Exception( $key . ' must be a valid YYYY-MM-DD date.' );
			}
			$range[ $key ] = $value;
		}
		if ( '' !== $range['start_date'] && '' !== $range['end_date'] && $range['start_date'] > $range['end_date'] ) {
			throw new \Exception( 'start_date must not be later than end_date.' );
		}
		return $range;
	}

	/**
	 * Persist a pre-op snapshot for a forms write and return the undo envelope.
	 * The Server undo switch consumes op='forms_entry_write' and calls
	 * {@see undo_entry_write()} to restore, re-checking the capability first.
	 */
	private static function store_undo( $provider, $entry_id, array $prior, $summary ) {
		return \More_MCP\MCP\Undo_Store::store(
			array(
				'op'           => 'forms_entry_write',
				'summary'      => (string) $summary,
				'target'       => array( 'provider' => (string) $provider, 'entry_id' => (int) $entry_id ),
				'pre_op_state' => $prior, // { property, value }
			)
		);
	}

	/**
	 * Restore an entry to its pre-op state from an undo snapshot. Called by the
	 * Server undo handler AFTER it has re-checked the manage_options capability
	 * (a token may be redeemed by a different caller than the one who created it).
	 *
	 * @param array $snapshot The full Undo_Store snapshot.
	 * @return array Result describing what was restored.
	 * @throws \Exception on missing target, absent provider, or provider failure.
	 */
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
			$model = self::ff_submission_model();
			if ( ! $model ) {
				throw self::ff_unavailable();
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
