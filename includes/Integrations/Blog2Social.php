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
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use social publishing tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Blog2Social is not active.' );
		}
		if ( 'blog2social_get_status' === $name ) {
			return self::get_status();
		}
		throw new \Exception( 'Unknown Blog2Social tool: ' . esc_html( $name ) );
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

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}
}
