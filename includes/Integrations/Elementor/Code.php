<?php
namespace More_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use More_MCP\Lifecycle\Guard;
use More_MCP\MCP\Undo_Store;

class Code {

	const CPT             = 'elementor_snippet';
	const META_LOCATION   = '_elementor_location';
	const META_PRIORITY   = '_elementor_priority';
	const META_CODE       = '_elementor_code';
	const META_CONDITIONS = '_elementor_conditions';

	const TOGGLE_KEY      = 'allow_code_snippets';
	const DEFAULT_LOCATION = 'elementor_head';
	const DEFAULT_PRIORITY = 1;

	const LOCATIONS = array( 'elementor_head', 'elementor_body_start', 'elementor_body_end' );

	public static function is_available() {
		return defined( 'ELEMENTOR_PRO_VERSION' ) && post_type_exists( self::CPT );
	}

	private static function require_available() {
		if ( ! self::is_available() ) {
			throw new \Exception( 'Elementor Custom Code module is not enabled (or Elementor Pro is not active), so these tools are unavailable.' );
		}
	}

	private static function require_read_cap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to read Elementor Custom Code (manage_options required).' );
		}
	}

	private static function toggle_on() {
		$settings = get_option( 'more_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	private static function require_write_gates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to write Elementor Custom Code.' );
		}
		if ( ! self::toggle_on() ) {
			throw new \Exception( 'Code writes are disabled. Enable "Allow code snippets" under More MCP settings first — it gates both the Code Snippets plugin surface and Elementor Custom Code.' );
		}
	}

	public static function list_code() {
		self::require_read_cap();
		self::require_available();

		$posts = get_posts( array(
			'post_type'        => self::CPT,
			'post_status'      => array( 'publish', 'draft' ),
			'numberposts'      => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );

		$rows = array();
		foreach ( $posts as $post ) {
			$rows[] = array(
				'code_id'  => (int) $post->ID,
				'title'    => $post->post_title,
				'location' => self::read_location( $post->ID ),
				'priority' => self::read_priority( $post->ID ),
				'active'   => ( 'publish' === $post->post_status ),
			);
		}

		return array(
			'count' => count( $rows ),
			'code'  => $rows,
		);
	}

	public static function get_code( $args ) {
		self::require_read_cap();
		self::require_available();

		$post = self::resolve_post( $args );

		return array(
			'code_id'  => (int) $post->ID,
			'title'    => $post->post_title,
			'location' => self::read_location( $post->ID ),
			'priority' => self::read_priority( $post->ID ),
			'active'   => ( 'publish' === $post->post_status ),
			'code'     => (string) get_post_meta( $post->ID, self::META_CODE, true ),
		);
	}

	public static function create_code( $args ) {
		self::require_write_gates();
		self::require_available();

		$title = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		if ( '' === $title ) {
			throw new \Exception( 'title is required.' );
		}
		$code = self::clean_code( $args['code'] ?? '' );
		if ( '' === $code ) {
			throw new \Exception( 'code is required.' );
		}
		$location = self::validate_location( $args['location'] ?? self::DEFAULT_LOCATION );
		$priority = isset( $args['priority'] ) ? (int) $args['priority'] : self::DEFAULT_PRIORITY;

		$slug = Guard::slug_of( sanitize_title( $title ) ?: 'elementor-code' );
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'elementor_create_code',
				$slug,
				sprintf(
					'Create an Elementor Custom Code snippet "%s" injected at %s, saved INACTIVE (you publish it in Elementor). Re-call with confirm=true and confirm_slug="%s".',
					$title,
					$location,
					$slug
				)
			);
		}

		
		$post_id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_title'  => $title,
			'post_status' => 'draft',
		), true );
		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create the Custom Code post: ' . esc_html( $post_id->get_error_message() ) );
		}

		
		
		update_post_meta( $post_id, self::META_CODE, $code );
		update_post_meta( $post_id, self::META_LOCATION, $location );
		update_post_meta( $post_id, self::META_PRIORITY, $priority );

		$undo = Undo_Store::store( array(
			'op'      => 'elementor_code_write',
			'summary' => sprintf( 'Created Elementor Custom Code "%s" (id %d, inactive)', $title, $post_id ),
			'target'  => array( 'kind' => 'create', 'post_id' => (int) $post_id ),
		) );

		return array(
			'success'  => true,
			'code_id'  => (int) $post_id,
			'title'    => $title,
			'location' => $location,
			'priority' => $priority,
			'active'   => false,
			'message'  => 'Elementor Custom Code created and saved INACTIVE. Publish it in the Elementor Custom Code admin to make it run.',
			'undo'     => $undo,
		);
	}

	public static function update_code( $args ) {
		self::require_write_gates();
		self::require_available();

		$post = self::resolve_post( $args );
		$id   = (int) $post->ID;

		$new_title    = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : null;
		$new_code     = array_key_exists( 'code', $args ) ? self::clean_code( $args['code'] ) : null;
		$new_location = isset( $args['location'] ) ? self::validate_location( $args['location'] ) : null;
		$new_priority = isset( $args['priority'] ) ? (int) $args['priority'] : null;

		if ( null === $new_title && null === $new_code && null === $new_location && null === $new_priority ) {
			throw new \Exception( 'Provide at least one of title, code, location, priority to update.' );
		}
		if ( null !== $new_code && '' === $new_code ) {
			throw new \Exception( 'code cannot be empty; omit it to leave the code unchanged.' );
		}

		$slug = Guard::slug_of( 'elementor-code-' . $id );
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'elementor_update_code',
				$slug,
				sprintf(
					'Update Elementor Custom Code id %d. Its active (published) state is left unchanged. Re-call with confirm=true and confirm_slug="%s".',
					$id,
					$slug
				)
			);
		}

		$undo = Undo_Store::store( array(
			'op'      => 'elementor_code_write',
			'summary' => sprintf( 'Updated Elementor Custom Code id %d', $id ),
			'target'  => array( 'kind' => 'update', 'post_id' => $id ),
			'pre_op_state' => array(
				'title'    => (string) $post->post_title,
				'code'     => (string) get_post_meta( $id, self::META_CODE, true ),
				'location' => self::read_location( $id ),
				'priority' => self::read_priority( $id ),
			),
		) );

		if ( null !== $new_title ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $new_title ) );
		}
		if ( null !== $new_code ) {
			update_post_meta( $id, self::META_CODE, $new_code );
		}
		if ( null !== $new_location ) {
			update_post_meta( $id, self::META_LOCATION, $new_location );
		}
		if ( null !== $new_priority ) {
			update_post_meta( $id, self::META_PRIORITY, $new_priority );
		}

		return array(
			'success'  => true,
			'code_id'  => $id,
			'location' => self::read_location( $id ),
			'priority' => self::read_priority( $id ),
			'active'   => ( 'publish' === get_post_status( $id ) ),
			'message'  => 'Elementor Custom Code updated. Active state left unchanged.',
			'undo'     => $undo,
		);
	}

	public static function delete_code( $args ) {
		self::require_write_gates();
		self::require_available();

		$post = self::resolve_post( $args );
		$id   = (int) $post->ID;

		if ( ! empty( $args['dry_run'] ) ) {
			return array(
				'dry_run'  => true,
				'code_id'  => $id,
				'title'    => $post->post_title,
				'location' => self::read_location( $id ),
				'active'   => ( 'publish' === $post->post_status ),
				'message'  => sprintf(
					'Would delete Elementor Custom Code "%s" (id %d). Re-call without dry_run to delete; an undo token will be returned.',
					$post->post_title,
					$id
				),
			);
		}

		$undo = Undo_Store::store( array(
			'op'      => 'elementor_code_write',
			'summary' => sprintf( 'Deleted Elementor Custom Code id %d (%s)', $id, $post->post_title ),
			'target'  => array( 'kind' => 'delete', 'post_id' => $id ),
			'pre_op_state' => array(
				'title'       => (string) $post->post_title,
				'post_status' => (string) $post->post_status,
				'code'        => (string) get_post_meta( $id, self::META_CODE, true ),
				'location'    => self::read_location( $id ),
				'priority'    => self::read_priority( $id ),
				'conditions'  => get_post_meta( $id, self::META_CONDITIONS, true ),
			),
		) );

		wp_delete_post( $id, true );

		return array(
			'success' => true,
			'code_id' => $id,
			'title'   => $post->post_title,
			'message' => 'Elementor Custom Code deleted.',
			'undo'    => $undo,
		);
	}

	
	public static function undo_code_write( array $snapshot ) {
		$target = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();
		$kind   = isset( $target['kind'] ) ? (string) $target['kind'] : '';
		$id     = isset( $target['post_id'] ) ? (int) $target['post_id'] : 0;
		$pre    = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();

		switch ( $kind ) {
			case 'create':
				
				if ( $id > 0 && get_post( $id ) ) {
					wp_delete_post( $id, true );
				}
				return array(
					'success' => true,
					'op'      => 'elementor_code_write',
					'code_id' => $id,
					'message' => 'Elementor Custom Code creation undone (the draft was removed).',
				);

			case 'update':
				if ( $id <= 0 || ! get_post( $id ) ) {
					throw new \Exception( 'The target Custom Code no longer exists.' );
				}
				if ( isset( $pre['title'] ) ) {
					wp_update_post( array( 'ID' => $id, 'post_title' => (string) $pre['title'] ) );
				}
				if ( array_key_exists( 'code', $pre ) ) {
					update_post_meta( $id, self::META_CODE, (string) $pre['code'] );
				}
				if ( array_key_exists( 'location', $pre ) ) {
					update_post_meta( $id, self::META_LOCATION, (string) $pre['location'] );
				}
				if ( array_key_exists( 'priority', $pre ) ) {
					update_post_meta( $id, self::META_PRIORITY, (int) $pre['priority'] );
				}
				return array(
					'success' => true,
					'op'      => 'elementor_code_write',
					'code_id' => $id,
					'message' => 'Elementor Custom Code restored to its pre-operation state.',
				);

			case 'delete':
				$title = isset( $pre['title'] ) ? (string) $pre['title'] : '';
				$new_id = wp_insert_post( array(
					'post_type'   => self::CPT,
					'post_title'  => $title,
					
					'post_status' => ( isset( $pre['post_status'] ) && 'publish' === $pre['post_status'] ) ? 'publish' : 'draft',
				), true );
				if ( is_wp_error( $new_id ) ) {
					throw new \Exception( 'Failed to re-create the Custom Code: ' . esc_html( $new_id->get_error_message() ) );
				}
				update_post_meta( $new_id, self::META_CODE, (string) ( $pre['code'] ?? '' ) );
				update_post_meta( $new_id, self::META_LOCATION, (string) ( $pre['location'] ?? self::DEFAULT_LOCATION ) );
				update_post_meta( $new_id, self::META_PRIORITY, (int) ( $pre['priority'] ?? self::DEFAULT_PRIORITY ) );
				if ( array_key_exists( 'conditions', $pre ) && '' !== $pre['conditions'] ) {
					update_post_meta( $new_id, self::META_CONDITIONS, $pre['conditions'] );
				}
				return array(
					'success' => true,
					'op'      => 'elementor_code_write',
					'code_id' => (int) $new_id,
					'message' => 'Elementor Custom Code re-created from the undo snapshot. Note: its post ID changed.',
				);

			default:
				throw new \Exception( 'Unknown Elementor code undo kind.' );
		}
	}

	private static function resolve_post( $args ) {
		$id   = isset( $args['code_id'] ) ? (int) $args['code_id'] : 0;
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || self::CPT !== $post->post_type ) {
			throw new \Exception( 'No Elementor Custom Code found for code_id ' . esc_html( (string) $id ) . '.' );
		}
		return $post;
	}

	private static function read_location( $id ) {
		$loc = (string) get_post_meta( $id, self::META_LOCATION, true );
		return '' !== $loc ? $loc : self::DEFAULT_LOCATION;
	}

	private static function read_priority( $id ) {
		$p = get_post_meta( $id, self::META_PRIORITY, true );
		return ( '' === $p || null === $p ) ? self::DEFAULT_PRIORITY : (int) $p;
	}

	private static function validate_location( $location ) {
		$location = (string) $location;
		if ( ! in_array( $location, self::LOCATIONS, true ) ) {
			throw new \Exception( 'location must be one of: ' . implode( ', ', self::LOCATIONS ) . '.' );
		}
		return $location;
	}

	private static function clean_code( $code ) {
		$code = (string) $code;
		$code = trim( $code );
		$code = preg_replace( '/^```[a-zA-Z0-9]*\s*\n?/', '', $code );
		$code = preg_replace( '/\n?```\s*$/', '', $code );
		$code = trim( $code );
		if ( false !== stripos( $code, '<?php' ) ) {
			throw new \Exception( 'The code contains a PHP open tag; Elementor Custom Code is HTML/JS only.' );
		}
		return $code;
	}
}
