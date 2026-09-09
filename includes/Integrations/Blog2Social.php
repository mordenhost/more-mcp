<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blog2Social {

	const SCHEDULED_SENTINEL = '0000-00-00 00:00:00';

	public static function is_available() {
		return defined( 'B2S_PLUGIN_VERSION' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'blog2social' ),
			'capabilities' => array( 'social_publishing' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'blog2social_get_status',
				'description' => 'Blog2Social social-publishing health: whether Blog2Social is connected (a local API token row exists — the token itself is never returned), the number of scheduled vs published network-posts, the publish-error count, and a per-social-network breakdown of scheduled/published counts. Scheduled vs published is read from Blog2Social\'s own local b2s_posts table (publish_date sentinel). Aggregate counts only — never post content, a target URL, or an author. Read-only; no publish or schedule tool.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'blog2social_get_networks',
				'description' => 'Read the Blog2Social supported-network catalogue: each social network\'s id, name, and which account kinds it allows (profile, page, and/or group), read from Blog2Social\'s own B2S_PLUGIN_NETWORK and allow-matrix definitions. Network support configuration only — never a connected account, its display name, a token, or a post. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use social publishing tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Blog2Social is not active.' );
		}
		if ( 'blog2social_get_networks' === $name ) {
			return self::get_networks();
		}
		if ( 'blog2social_get_status' !== $name ) {
			throw new \Exception( 'Unknown Blog2Social tool: ' . esc_html( $name ) );
		}

		return self::get_status();
	}

	private static function get_status() {
		global $wpdb;

		$connected = null;
		if ( self::table_exists( 'b2s_user' ) ) {
			$user_table = $wpdb->prefix . 'b2s_user';
			
			$connected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$user_table}" ) > 0; 
		}

		if ( ! self::table_exists( 'b2s_posts' ) ) {
			return array(
				'provider'  => 'blog2social',
				'connected' => $connected,
				'posts'     => array( 'available' => false, 'message' => 'The Blog2Social posts table does not exist (nothing scheduled or published yet).' ),
			);
		}

		$posts = $wpdb->prefix . 'b2s_posts';
		
		$scheduled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$posts} WHERE hide = 0 AND publish_date = %s", self::SCHEDULED_SENTINEL ) ); 
		$published = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$posts} WHERE hide = 0 AND publish_date != %s", self::SCHEDULED_SENTINEL ) ); 
		$errors    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE hide = 0 AND publish_error_code != ''" ); 

		return array(
			'provider'   => 'blog2social',
			'connected'  => $connected,
			'posts'      => array(
				'available'   => true,
				'scheduled'   => $scheduled,
				'published'   => $published,
				'with_errors' => $errors,
			),
			'networks'   => self::network_breakdown(),
		);
	}

	private static function network_breakdown() {
		global $wpdb;
		if ( ! self::table_exists( 'b2s_posts' ) || ! self::table_exists( 'b2s_posts_network_details' ) ) {
			return null;
		}
		$posts   = $wpdb->prefix . 'b2s_posts';
		$details = $wpdb->prefix . 'b2s_posts_network_details';

		$rows = $wpdb->get_results( 
			$wpdb->prepare(
				"SELECT d.network_display_name AS name,
				 SUM( CASE WHEN b.publish_date = %s THEN 1 ELSE 0 END ) AS scheduled,
				 SUM( CASE WHEN b.publish_date != %s THEN 1 ELSE 0 END ) AS published
				 FROM {$posts} b
				 LEFT JOIN {$details} d ON ( d.id = b.network_details_id )
				 WHERE b.hide = 0
				 GROUP BY d.network_display_name",
				self::SCHEDULED_SENTINEL,
				self::SCHEDULED_SENTINEL
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$name = isset( $row->name ) && '' !== (string) $row->name ? (string) $row->name : 'unknown';
			$out[] = array(
				'network'   => $name,
				'scheduled' => (int) $row->scheduled,
				'published' => (int) $row->published,
			);
		}
		return $out;
	}

	private static function get_networks() {
		if ( ! defined( 'B2S_PLUGIN_NETWORK' ) ) {
			return array(
				'provider'  => 'blog2social',
				'available' => false,
				'message'   => 'The Blog2Social network catalogue constant was not found on this version.',
			);
		}

		$names = self::unserialize_const( 'B2S_PLUGIN_NETWORK' );
		if ( ! is_array( $names ) || empty( $names ) ) {
			return array(
				'provider'  => 'blog2social',
				'available' => false,
				'message'   => 'The Blog2Social network catalogue could not be read on this version.',
			);
		}

		$profiles = self::unserialize_const( 'B2S_PLUGIN_NETWORK_ALLOW_PROFILE' );
		$pages    = self::unserialize_const( 'B2S_PLUGIN_NETWORK_ALLOW_PAGE' );
		$groups   = self::unserialize_const( 'B2S_PLUGIN_NETWORK_ALLOW_GROUP' );

		$networks = array();
		foreach ( $names as $id => $name ) {
			$networks[] = array(
				'id'      => (int) $id,
				'name'    => (string) $name,
				'profile' => is_array( $profiles ) ? in_array( (int) $id, array_map( 'intval', $profiles ), true ) : null,
				'page'    => is_array( $pages ) ? in_array( (int) $id, array_map( 'intval', $pages ), true ) : null,
				'group'   => is_array( $groups ) ? in_array( (int) $id, array_map( 'intval', $groups ), true ) : null,
			);
		}

		return array(
			'provider'  => 'blog2social',
			'available' => true,
			'count'     => count( $networks ),
			'networks'  => $networks,
		);
	}

	private static function unserialize_const( $name ) {
		if ( ! defined( $name ) ) {
			return null;
		}
		$value = maybe_unserialize( constant( $name ) );
		return is_array( $value ) ? $value : null;
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}
}
