<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Redirection {

	public static function is_available() {
		return class_exists( '\Red_Item' ) && class_exists( '\Red_Group' );
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'redirection_list_redirects',
				'description' => 'List redirects from the Redirection plugin. Direct read from the wp_redirection_items table. Filter by group_id or a URL substring; default limit 50, max 200.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit'    => array( 'type' => 'integer', 'description' => 'Max redirects to return (default 50, max 200)' ),
						'group_id' => array( 'type' => 'integer', 'description' => 'Only return redirects in this group (use redirection_list_groups to discover IDs)' ),
						'search'   => array( 'type' => 'string', 'description' => 'Case-insensitive substring match against source URL' ),
					),
				),
			),
			array(
				'name'        => 'redirection_create_redirect',
				'description' => 'Create a redirect using the Redirection plugin. Wraps Red_Item::create. status_code defaults to 301 (permanent).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'source_url'  => array( 'type' => 'string', 'description' => 'Source path (e.g. /old-page/) or full URL' ),
						'target_url'  => array( 'type' => 'string', 'description' => 'Destination path or full URL' ),
						'status_code' => array( 'type' => 'integer', 'enum' => array( 301, 302, 307 ), 'description' => 'HTTP status code (301 permanent, 302 temp, 307 preserved-method)' ),
						'group_id'    => array( 'type' => 'integer', 'description' => 'Group to file this redirect under. Defaults to Redirection\'s default group.' ),
						'regex'       => array( 'type' => 'boolean', 'description' => 'Treat source_url as a regex pattern. Default false.' ),
						'title'       => array( 'type' => 'string', 'description' => 'Optional human-readable title for admin display.' ),
					),
					'required'   => array( 'source_url', 'target_url' ),
				),
			),
			array(
				'name'        => 'redirection_update_redirect',
				'description' => 'Update an existing Redirection entry by ID. Pass only the fields you want to change.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer', 'description' => 'Redirect ID (use redirection_list_redirects to discover)' ),
						'target_url'  => array( 'type' => 'string', 'description' => 'New destination path or full URL' ),
						'status_code' => array( 'type' => 'integer', 'enum' => array( 301, 302, 307 ) ),
						'enabled'     => array( 'type' => 'boolean', 'description' => 'Enable (true) or disable (false) the redirect' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'redirection_list_groups',
				'description' => 'List the Redirection group registry. Direct read from wp_redirection_groups. Use group IDs to file new redirects into the correct category.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use Redirection tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Redirection plugin is not active' );
		}

		switch ( $name ) {
			case 'redirection_list_redirects':
				return self::list_redirects( $args );
			case 'redirection_create_redirect':
				return self::create_redirect( $args );
			case 'redirection_update_redirect':
				return self::update_redirect( $args );
			case 'redirection_list_groups':
				return self::list_groups();
			default:
				throw new \Exception( 'Unknown Redirection tool: ' . esc_html( $name ) );
		}
	}

	private static function list_redirects( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'redirection_items';
		$limit = isset( $args['limit'] ) ? max( 1, min( intval( $args['limit'] ), 200 ) ) : 50;

		$where  = array( '1=1' );
		$params = array();
		if ( isset( $args['group_id'] ) ) {
			$where[]  = 'group_id = %d';
			$params[] = intval( $args['group_id'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'url LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$sql = "SELECT id, url, match_url, action_data, action_type, action_code, regex, position, status, group_id FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$params[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map( function ( $r ) {
			return array(
				'id'          => (int) $r['id'],
				'source_url'  => (string) $r['url'],
				'target_url'  => (string) $r['action_data'],
				'status_code' => (int) $r['action_code'],
				'regex'       => ! empty( $r['regex'] ),
				'enabled'     => 'enabled' === $r['status'],
				'group_id'    => (int) $r['group_id'],
				'position'    => (int) $r['position'],
			);
		}, $rows );
	}

	private static function create_redirect( $args ) {
		if ( empty( $args['source_url'] ) || empty( $args['target_url'] ) ) {
			throw new \Exception( 'source_url and target_url are required' );
		}
		if ( ! method_exists( '\Red_Item', 'create' ) ) {
			throw new \Exception( 'Red_Item::create not available in this Redirection version.' );
		}

		$data = array(
			'url'         => sanitize_text_field( $args['source_url'] ),
			'action_data' => array( 'url' => esc_url_raw( $args['target_url'] ) ),
			'action_type' => 'url',
			'action_code' => isset( $args['status_code'] ) ? intval( $args['status_code'] ) : 301,
			'match_type'  => 'url',
			'group_id'    => isset( $args['group_id'] ) ? intval( $args['group_id'] ) : 0,
			'regex'       => ! empty( $args['regex'] ),
			'title'       => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
		);

		$result = \Red_Item::create( $data );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to create redirect: ' . esc_html( $result->get_error_message() ) );
		}
		$id = is_object( $result ) && method_exists( $result, 'get_id' ) ? (int) $result->get_id() : 0;
		return array(
			'id'          => $id,
			'source_url'  => $data['url'],
			'target_url'  => $data['action_data']['url'],
			'status_code' => $data['action_code'],
			'message'     => 'Redirect created.',
		);
	}

	private static function update_redirect( $args ) {
		if ( empty( $args['id'] ) ) {
			throw new \Exception( 'id is required' );
		}
		if ( ! method_exists( '\Red_Item', 'get_by_id' ) ) {
			throw new \Exception( 'Red_Item::get_by_id not available in this Redirection version.' );
		}

		$item = \Red_Item::get_by_id( intval( $args['id'] ) );
		if ( ! $item ) {
			throw new \Exception( 'Redirect not found: ' . intval( $args['id'] ) );
		}
		if ( ! method_exists( $item, 'update' ) ) {
			throw new \Exception( 'Red_Item::update not available in this Redirection version.' );
		}

		$data = array();
		if ( isset( $args['target_url'] ) ) {
			$data['action_data'] = array( 'url' => esc_url_raw( $args['target_url'] ) );
		}
		if ( isset( $args['status_code'] ) ) {
			$data['action_code'] = intval( $args['status_code'] );
		}
		if ( isset( $args['enabled'] ) ) {
			$data['status'] = $args['enabled'] ? 'enabled' : 'disabled';
		}

		if ( empty( $data ) ) {
			throw new \Exception( 'No updatable fields provided (target_url, status_code, or enabled)' );
		}

		$result = $item->update( $data );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to update redirect: ' . esc_html( $result->get_error_message() ) );
		}
		return array(
			'id'      => intval( $args['id'] ),
			'updated' => array_keys( $data ),
			'message' => 'Redirect updated.',
		);
	}

	private static function list_groups() {
		global $wpdb;
		$table = $wpdb->prefix . 'redirection_groups';
		$rows  = $wpdb->get_results( "SELECT id, name, tracking, enabled, position, module_id FROM {$table} ORDER BY position, id", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map( function ( $r ) {
			return array(
				'id'        => (int) $r['id'],
				'name'      => (string) $r['name'],
				'tracking'  => ! empty( $r['tracking'] ),
				'enabled'   => 'enabled' === $r['enabled'],
				'position'  => (int) $r['position'],
				'module_id' => (int) $r['module_id'],
			);
		}, $rows );
	}
}
