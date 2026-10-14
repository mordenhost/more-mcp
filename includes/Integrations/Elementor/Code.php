<?php
namespace Mordenhost_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Mordenhost_MCP\Access\Safety;
use Mordenhost_MCP\Integrations\Write_Verify;
use Mordenhost_MCP\Lifecycle\Guard;
use Mordenhost_MCP\MCP\Undo_Store;

class Code {

	const CPT             = 'elementor_snippet';
	const META_LOCATION   = '_elementor_location';
	const META_PRIORITY   = '_elementor_priority';
	const META_CODE       = '_elementor_code';
	const META_CONDITIONS = '_elementor_conditions';

	const TOGGLE_KEY       = 'allow_code_writes';
	const UNDO_OP          = 'elementor_custom_code';
	const DEFAULT_LOCATION = 'elementor_head';
	const DEFAULT_PRIORITY = 1;
	const MAX_PRIORITY     = 10;

	const LOCATIONS = array( 'elementor_head', 'elementor_body_start', 'elementor_body_end' );

	public static function is_available() {
		return defined( 'ELEMENTOR_PRO_VERSION' ) && post_type_exists( self::CPT );
	}

	public static function register() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'guard_generic_writes' ), 10, 4 );
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

	public static function toggle_on() {
		$settings = get_option( 'mordenhost_mcp_settings', array() );
		return is_array( $settings ) && ! empty( $settings[ self::TOGGLE_KEY ] );
	}

	public static function writes_allowed() {
		return self::toggle_on() && current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	public static function require_write_gates() {
		if ( ! self::toggle_on() ) {
			throw new \Exception( 'Custom Code writes are off on this site. The site owner can turn on "Site-wide code" under Mordenhost MCP > Access; until then Custom Code can only be read.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to write Elementor Custom Code (manage_options required).' );
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			throw new \Exception( 'Writing Custom Code needs the unfiltered_html capability, which this account does not have (on multisite only super admins hold it, and DISALLOW_UNFILTERED_HTML removes it).' );
		}
	}

	public static function guard_generic_writes( $caps, $cap, $user_id, $args ) {
		if ( empty( $args[0] ) || ! Safety::in_tool_call() ) {
			return $caps;
		}

		
		
		$watched = array( 'edit_post', 'delete_post', 'publish_post' );
		$type    = get_post_type_object( self::CPT );
		if ( $type && isset( $type->cap ) ) {
			$watched[] = $type->cap->edit_post;
			$watched[] = $type->cap->delete_post;
			$watched[] = $type->cap->publish_posts;
		}
		if ( ! in_array( $cap, $watched, true ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return $caps;
		}
		return self::writes_allowed() ? $caps : array( 'do_not_allow' );
	}

	public static function refuse_generic_update( $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			throw new \Exception( 'Post ' . (int) $post_id . ' is an Elementor Custom Code snippet. Its code lives in post meta, not in post content, so this tool cannot change it. Use elementor_update_code (title, code, location, priority, conditions, active).' );
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
				'code_id'    => (int) $post->ID,
				'title'      => $post->post_title,
				'location'   => self::read_location( $post->ID ),
				'priority'   => self::read_priority( $post->ID ),
				'active'     => ( 'publish' === $post->post_status ),
				'conditions' => self::read_conditions( $post->ID ),
			);
		}

		return array(
			'count'          => count( $rows ),
			'code'           => $rows,
			'writes_enabled' => self::writes_allowed(),
		);
	}

	public static function get_code( $args ) {
		self::require_read_cap();
		self::require_available();

		$post = self::resolve_post( $args );

		return array(
			'code_id'    => (int) $post->ID,
			'title'      => $post->post_title,
			'location'   => self::read_location( $post->ID ),
			'priority'   => self::read_priority( $post->ID ),
			'active'     => ( 'publish' === $post->post_status ),
			'conditions' => self::read_conditions( $post->ID ),
			'code'       => (string) get_post_meta( $post->ID, self::META_CODE, true ),
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
		$location   = self::validate_location( $args['location'] ?? self::DEFAULT_LOCATION );
		$priority   = self::validate_priority( $args['priority'] ?? self::DEFAULT_PRIORITY );
		$conditions = array_key_exists( 'conditions', $args ) ? self::normalize_conditions( $args['conditions'] ) : null;
		$activate   = ! empty( $args['active'] ) && true === filter_var( $args['active'], FILTER_VALIDATE_BOOLEAN );
		$held       = $activate && Safety::force_draft();

		$slug = sanitize_title( $title );
		$slug = '' !== $slug ? $slug : 'elementor-code';
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'elementor_create_code',
				$slug,
				sprintf(
					'Create the Elementor Custom Code snippet "%s" (%d bytes) at %s, priority %d, %s, %s.',
					$title,
					strlen( $code ),
					$location,
					$priority,
					self::describe_conditions( $conditions ),
					$activate && ! $held ? 'ACTIVE (it runs on the site as soon as it is saved)' : 'inactive (saved as a draft)'
				),
				array( 'code_sha256' => hash( 'sha256', $code ) )
			);
		}
		if ( null !== $conditions ) {
			self::require_theme_builder();
		}

		$post_id = wp_insert_post( wp_slash( array(
			'post_type'   => self::CPT,
			'post_title'  => $title,
			'post_status' => 'draft',
		) ), true );
		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create the Custom Code post: ' . esc_html( $post_id->get_error_message() ) );
		}
		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_CODE, wp_slash( $code ) );
		update_post_meta( $post_id, self::META_LOCATION, $location );
		update_post_meta( $post_id, self::META_PRIORITY, $priority );

		$cache = null !== $conditions ? self::save_conditions( $post_id, $conditions ) : self::refresh_cache();
		if ( $activate && ! $held ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		}

		
		$states = array(
			Write_Verify::state_of( (string) get_post_meta( $post_id, self::META_CODE, true ), $code, Write_Verify::NO_PRIOR ),
			Write_Verify::state_of( self::read_location( $post_id ), $location, Write_Verify::NO_PRIOR ),
			Write_Verify::state_of( self::read_priority( $post_id ), $priority, Write_Verify::NO_PRIOR ),
		);
		if ( null !== $conditions ) {
			$states[] = Write_Verify::state_of( self::read_conditions( $post_id ), $conditions, Write_Verify::NO_PRIOR );
		}
		$active = ( 'publish' === get_post_status( $post_id ) );
		if ( $activate && ! $held ) {
			$states[] = Write_Verify::state_of( $active, true, Write_Verify::NO_PRIOR );
		}

		$undo = Undo_Store::store( array(
			'op'      => self::UNDO_OP,
			'summary' => sprintf( 'Created Elementor Custom Code "%s" (id %d)', $title, $post_id ),
			'target'  => array( 'kind' => 'create', 'post_id' => $post_id ),
		) );

		$result = array(
			'success'       => true,
			'code_id'       => $post_id,
			'title'         => $title,
			'location'      => self::read_location( $post_id ),
			'priority'      => self::read_priority( $post_id ),
			'conditions'    => self::read_conditions( $post_id ),
			'active'        => $active,
			'cache_rebuilt' => $cache,
			'message'       => $active
				? 'Elementor Custom Code created and active. ' . self::where_it_runs( $post_id )
				: 'Elementor Custom Code created and saved inactive. Activate it with elementor_update_code (active: true) or in Elementor > Custom Code.',
		);
		if ( $held ) {
			$result['held_as_draft'] = true;
			$result['message']       = 'Elementor Custom Code created but kept inactive: this site holds every change an agent would make live for a person to publish (Mordenhost MCP > Safety). Activate it in Elementor > Custom Code.';
		}
		return array_merge( $result, self::verify_write( $states, $post_id, $code ), array( 'undo' => $undo ) );
	}

	public static function update_code( $args ) {
		self::require_write_gates();
		self::require_available();

		$post = self::resolve_post( $args );
		$id   = (int) $post->ID;

		$new_title      = isset( $args['title'] ) && '' !== (string) $args['title'] ? sanitize_text_field( (string) $args['title'] ) : null;
		$new_code       = array_key_exists( 'code', $args ) ? self::clean_code( $args['code'] ) : null;
		$new_location   = isset( $args['location'] ) ? self::validate_location( $args['location'] ) : null;
		$new_priority   = isset( $args['priority'] ) ? self::validate_priority( $args['priority'] ) : null;
		$new_conditions = array_key_exists( 'conditions', $args ) ? self::normalize_conditions( $args['conditions'] ) : null;
		$new_active     = array_key_exists( 'active', $args ) ? ( true === filter_var( $args['active'], FILTER_VALIDATE_BOOLEAN ) ) : null;

		if ( null === $new_title && null === $new_code && null === $new_location && null === $new_priority && null === $new_conditions && null === $new_active ) {
			throw new \Exception( 'Provide at least one of title, code, location, priority, conditions, active to update.' );
		}
		if ( null !== $new_code && '' === $new_code ) {
			throw new \Exception( 'code cannot be empty; omit it to leave the code unchanged, or delete the snippet with elementor_delete_code.' );
		}

		$prior = self::snapshot( $post );
		$held  = true === $new_active && ! $prior['active'] && Safety::force_draft();

		$slug = 'elementor-code-' . $id;
		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'elementor_update_code',
				$slug,
				sprintf( 'Update Elementor Custom Code id %d ("%s"): %s.', $id, $prior['title'], implode( '; ', self::describe_changes( $prior, $new_title, $new_code, $new_location, $new_priority, $new_conditions, $new_active ) ) ),
				null !== $new_code ? array( 'code_sha256' => hash( 'sha256', $new_code ) ) : array()
			);
		}
		if ( null !== $new_conditions ) {
			self::require_theme_builder();
		}

		if ( null !== $new_title ) {
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $new_title ) ) );
		}
		if ( null !== $new_code ) {
			update_post_meta( $id, self::META_CODE, wp_slash( $new_code ) );
		}
		if ( null !== $new_location ) {
			update_post_meta( $id, self::META_LOCATION, $new_location );
		}
		if ( null !== $new_priority ) {
			update_post_meta( $id, self::META_PRIORITY, $new_priority );
		}
		$cache = null !== $new_conditions ? self::save_conditions( $id, $new_conditions ) : self::refresh_cache();
		if ( null !== $new_active && $new_active !== $prior['active'] && ! $held ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $new_active ? 'publish' : 'draft' ) );
		}

		
		
		$states = array();
		if ( null !== $new_code ) {
			$states[] = Write_Verify::state_of( (string) get_post_meta( $id, self::META_CODE, true ), $new_code, $prior['code'] );
		}
		if ( null !== $new_location ) {
			$states[] = Write_Verify::state_of( self::read_location( $id ), $new_location, $prior['location'] );
		}
		if ( null !== $new_priority ) {
			$states[] = Write_Verify::state_of( self::read_priority( $id ), $new_priority, $prior['priority'] );
		}
		if ( null !== $new_conditions ) {
			$states[] = Write_Verify::state_of( self::read_conditions( $id ), $new_conditions, $prior['conditions'] );
		}
		if ( null !== $new_title ) {
			$fresh    = get_post( $id );
			$states[] = Write_Verify::state_of( is_object( $fresh ) ? (string) $fresh->post_title : Write_Verify::UNREADABLE, $new_title, $prior['title'] );
		}
		$active = ( 'publish' === get_post_status( $id ) );
		if ( null !== $new_active && ! $held ) {
			$states[] = Write_Verify::state_of( $active, $new_active, $prior['active'] );
		}
		Write_Verify::refuse_if_discarded( Write_Verify::combine( $states ), __( 'Custom Code snippet', 'mordenhost-mcp' ) );

		
		$undo = Undo_Store::store( array(
			'op'           => self::UNDO_OP,
			'summary'      => sprintf( 'Updated Elementor Custom Code id %d', $id ),
			'target'       => array( 'kind' => 'update', 'post_id' => $id ),
			'pre_op_state' => $prior,
		) );

		$result = array(
			'success'       => true,
			'code_id'       => $id,
			'location'      => self::read_location( $id ),
			'priority'      => self::read_priority( $id ),
			'conditions'    => self::read_conditions( $id ),
			'active'        => $active,
			'cache_rebuilt' => $cache,
			'message'       => 'Elementor Custom Code updated. ' . ( $active ? self::where_it_runs( $id ) : 'It is inactive, so it does not run.' ),
		);
		if ( $held ) {
			$result['held_as_draft'] = true;
			$result['message']       = 'Elementor Custom Code updated but left inactive: this site holds every change an agent would make live for a person to publish (Mordenhost MCP > Safety). Activate it in Elementor > Custom Code.';
		}
		return array_merge( $result, self::verify_write( $states, $id, $new_code ), array( 'undo' => $undo ) );
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

		$prior = self::snapshot( $post );

		wp_delete_post( $id, true );
		self::refresh_cache();

		
		$state = Write_Verify::state_of_presence( ! get_post( $id ) );
		Write_Verify::refuse_if_discarded(
			$state,
			__( 'Custom Code deletion', 'mordenhost-mcp' ),
			__( 'The Custom Code post is still present.', 'mordenhost-mcp' )
		);
		$verify = Write_Verify::report( $state, __( 'Custom Code deletion', 'mordenhost-mcp' ) );

		$undo = Undo_Store::store( array(
			'op'           => self::UNDO_OP,
			'summary'      => sprintf( 'Deleted Elementor Custom Code id %d (%s)', $id, $post->post_title ),
			'target'       => array( 'kind' => 'delete', 'post_id' => $id ),
			'pre_op_state' => $prior,
		) );

		return array_merge(
			array(
				'success' => true,
				'code_id' => $id,
				'title'   => $post->post_title,
				'message' => 'Elementor Custom Code deleted.',
			),
			$verify,
			array( 'undo' => $undo )
		);
	}

	
	public static function undo_code_write( array $snapshot ) {
		$target = isset( $snapshot['target'] ) && is_array( $snapshot['target'] ) ? $snapshot['target'] : array();
		$kind   = isset( $target['kind'] ) ? (string) $target['kind'] : '';
		$id     = isset( $target['post_id'] ) ? (int) $target['post_id'] : 0;
		$pre    = isset( $snapshot['pre_op_state'] ) && is_array( $snapshot['pre_op_state'] ) ? $snapshot['pre_op_state'] : array();

		switch ( $kind ) {
			case 'create':
				$post = $id > 0 ? get_post( $id ) : null;
				if ( $post && self::CPT === $post->post_type ) {
					wp_delete_post( $id, true );
					self::refresh_cache();
				}
				return array(
					'success' => true,
					'op'      => self::UNDO_OP,
					'code_id' => $id,
					'message' => 'Elementor Custom Code creation undone (the snippet was removed).',
				);

			case 'update':
				$post = $id > 0 ? get_post( $id ) : null;
				if ( ! $post || self::CPT !== $post->post_type ) {
					throw new \Exception( 'The target Custom Code no longer exists.' );
				}
				self::restore( $id, $pre );
				return array(
					'success' => true,
					'op'      => self::UNDO_OP,
					'code_id' => $id,
					'active'  => ( 'publish' === get_post_status( $id ) ),
					'message' => 'Elementor Custom Code restored to its pre-operation state.',
				);

			case 'delete':
				$postarr = array(
					'post_type'   => self::CPT,
					'post_title'  => (string) ( $pre['title'] ?? '' ),
					'post_status' => 'draft',
				);
				if ( $id > 0 && ! get_post( $id ) ) {
					
					$postarr['import_id'] = $id;
				}
				$new_id = wp_insert_post( wp_slash( $postarr ), true );
				if ( is_wp_error( $new_id ) ) {
					throw new \Exception( 'Failed to re-create the Custom Code: ' . esc_html( $new_id->get_error_message() ) );
				}
				$new_id = (int) $new_id;
				self::restore( $new_id, $pre );
				return array(
					'success' => true,
					'op'      => self::UNDO_OP,
					'code_id' => $new_id,
					'active'  => ( 'publish' === get_post_status( $new_id ) ),
					'message' => $new_id === $id
						? 'Elementor Custom Code re-created from the undo snapshot.'
						: 'Elementor Custom Code re-created from the undo snapshot. Its post ID changed to ' . $new_id . '.',
				);

			default:
				throw new \Exception( 'Unknown Elementor Custom Code undo kind.' );
		}
	}

	
	private static function theme_builder() {
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return null;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		return ( $module && method_exists( $module, 'get_conditions_manager' ) ) ? $module : null;
	}

	private static function require_theme_builder() {
		if ( null === self::theme_builder() ) {
			throw new \Exception( 'Display conditions need Elementor Pro\'s Theme Builder, which is not reachable on this site. Omit conditions (a snippet without conditions runs on every page) or set them in Elementor > Custom Code.' );
		}
	}

	private static function save_conditions( int $post_id, array $conditions ) {
		$result = Runtime::save_conditions_for( $post_id, $conditions );
		return ! empty( $result['cache_rebuilt'] );
	}

	public static function refresh_cache() {
		$module = self::theme_builder();
		if ( null === $module ) {
			return null;
		}
		try {
			$manager = $module->get_conditions_manager();
			if ( ! method_exists( $manager, 'get_cache' ) ) {
				return false;
			}
			$manager->get_cache()->regenerate();
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function normalize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			throw new \Exception( 'conditions must be an array of condition strings such as ["include/general"]. Pass [] to clear them.' );
		}
		$out = array();
		foreach ( $conditions as $condition ) {
			$condition = trim( (string) $condition, " \t\n\r\0\x0B/" );
			if ( '' === $condition ) {
				continue;
			}
			if ( ! preg_match( '#^(include|exclude)(/[A-Za-z0-9_\-]+)+$#', $condition ) ) {
				throw new \Exception( 'Invalid condition "' . esc_html( $condition ) . '". Use Elementor\'s form: include/general, include/singular/page, exclude/singular/post/123.' );
			}
			$out[] = $condition;
		}
		return array_values( array_unique( $out ) );
	}

	private static function snapshot( $post ) {
		$id = (int) $post->ID;
		return array(
			'title'      => (string) $post->post_title,
			'active'     => ( 'publish' === $post->post_status ),
			'code'       => (string) get_post_meta( $id, self::META_CODE, true ),
			'location'   => self::read_location( $id ),
			'priority'   => self::read_priority( $id ),
			'conditions' => self::read_conditions( $id ),
		);
	}

	private static function restore( int $id, array $pre ) {
		if ( array_key_exists( 'title', $pre ) ) {
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => (string) $pre['title'] ) ) );
		}
		if ( array_key_exists( 'code', $pre ) ) {
			update_post_meta( $id, self::META_CODE, wp_slash( (string) $pre['code'] ) );
		}
		update_post_meta( $id, self::META_LOCATION, self::validate_location( $pre['location'] ?? self::DEFAULT_LOCATION ) );
		update_post_meta( $id, self::META_PRIORITY, (int) ( $pre['priority'] ?? self::DEFAULT_PRIORITY ) );
		$conditions = isset( $pre['conditions'] ) && is_array( $pre['conditions'] ) ? $pre['conditions'] : array();
		if ( null !== self::theme_builder() ) {
			Runtime::save_conditions_for( $id, $conditions );
		} elseif ( $conditions ) {
			update_post_meta( $id, self::META_CONDITIONS, wp_slash( $conditions ) );
		} else {
			delete_post_meta( $id, self::META_CONDITIONS );
		}
		self::refresh_cache();
		if ( array_key_exists( 'active', $pre ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => ! empty( $pre['active'] ) ? 'publish' : 'draft' ) );
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

	private static function read_conditions( $id ) {
		$c = get_post_meta( $id, self::META_CONDITIONS, true );
		return is_array( $c ) ? array_values( array_map( 'strval', $c ) ) : array();
	}

	private static function validate_location( $location ) {
		$location = (string) $location;
		if ( ! in_array( $location, self::LOCATIONS, true ) ) {
			throw new \Exception( 'location must be one of: ' . implode( ', ', self::LOCATIONS ) . '.' );
		}
		return $location;
	}

	private static function validate_priority( $priority ) {
		$p = filter_var( $priority, FILTER_VALIDATE_INT );
		if ( false === $p || $p < 1 || $p > self::MAX_PRIORITY ) {
			throw new \Exception( 'priority must be a whole number from 1 to ' . self::MAX_PRIORITY . ' (lower prints first).' );
		}
		return $p;
	}

	private static function clean_code( $code ) {
		$code = trim( (string) $code );
		if ( preg_match( '/^```[a-zA-Z0-9]*[ \t]*\r?\n(.*)\r?\n```$/s', $code, $m ) ) {
			$code = trim( $m[1] );
		}
		if ( false !== stripos( $code, '<?php' ) || false !== strpos( $code, '<?=' ) ) {
			throw new \Exception( 'The code contains a PHP open tag. Elementor Custom Code is HTML/JS printed into the page, not PHP.' );
		}
		return $code;
	}

	private static function describe_conditions( $conditions ) {
		if ( null === $conditions || array() === $conditions ) {
			return 'no display conditions (Elementor prints it on every page)';
		}
		return 'conditions ' . implode( ', ', $conditions );
	}

	private static function describe_changes( array $prior, $title, $code, $location, $priority, $conditions, $active ) {
		$out = array();
		if ( null !== $title && $title !== $prior['title'] ) {
			$out[] = sprintf( 'title "%s" -> "%s"', $prior['title'], $title );
		}
		if ( null !== $code ) {
			$out[] = $code === $prior['code'] ? 'code unchanged' : sprintf( 'code %d -> %d bytes', strlen( $prior['code'] ), strlen( $code ) );
		}
		if ( null !== $location && $location !== $prior['location'] ) {
			$out[] = sprintf( 'location %s -> %s', $prior['location'], $location );
		}
		if ( null !== $priority && $priority !== $prior['priority'] ) {
			$out[] = sprintf( 'priority %d -> %d', $prior['priority'], $priority );
		}
		if ( null !== $conditions ) {
			$out[] = self::describe_conditions( $conditions );
		}
		if ( null !== $active && $active !== $prior['active'] ) {
			$out[] = $active ? 'ACTIVATE (it runs on the site immediately)' : 'deactivate';
		}
		return $out ? $out : array( 'no field changes' );
	}

	private static function where_it_runs( int $id ) {
		$conditions = self::read_conditions( $id );
		return $conditions
			? 'It runs at ' . self::read_location( $id ) . ' where these conditions match: ' . implode( ', ', $conditions ) . '.'
			: 'It runs at ' . self::read_location( $id ) . ' on every page.';
	}

	private static function verify_write( array $states, int $id, $sent_code ) {
		$state  = Write_Verify::combine( $states );
		$report = Write_Verify::report(
			$state,
			__( 'Custom Code snippet', 'mordenhost-mcp' ),
			__( 'Re-read the snippet with elementor_get_code before relying on it.', 'mordenhost-mcp' )
		);
		if ( null !== $sent_code ) {
			$stored = (string) get_post_meta( $id, self::META_CODE, true );
			$diff   = self::first_difference( $stored, (string) $sent_code );
			if ( null !== $diff ) {
				$report['code_difference'] = $diff;
				$report['verify_note']    .= sprintf( ' The stored code first differs from what was sent at byte %d: sent "%s", stored "%s".', $diff['offset'], $diff['sent'], $diff['stored'] );
			}
		}
		return $report;
	}

	public static function first_difference( string $stored, string $sent ) {
		if ( $stored === $sent ) {
			return null;
		}
		$max    = min( strlen( $stored ), strlen( $sent ) );
		$offset = 0;
		while ( $offset < $max && $stored[ $offset ] === $sent[ $offset ] ) {
			++$offset;
		}
		$start = max( 0, $offset - 12 );
		return array(
			'offset' => $offset,
			'sent'   => self::excerpt( $sent, $start ),
			'stored' => self::excerpt( $stored, $start ),
		);
	}

	private static function excerpt( string $s, int $start ) {
		while ( $start > 0 && isset( $s[ $start ] ) && 0x80 === ( ord( $s[ $start ] ) & 0xC0 ) ) {
			--$start;
		}
		$end = min( strlen( $s ), $start + 32 );
		while ( $end < strlen( $s ) && 0x80 === ( ord( $s[ $end ] ) & 0xC0 ) ) {
			++$end;
		}
		return substr( $s, $start, $end - $start );
	}
}
