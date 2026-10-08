<?php
namespace More_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Code {

	const CPT             = 'elementor_snippet';
	const META_LOCATION   = '_elementor_location';
	const META_PRIORITY   = '_elementor_priority';
	const META_CODE       = '_elementor_code';

	const DEFAULT_LOCATION = 'elementor_head';
	const DEFAULT_PRIORITY = 1;

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
}
