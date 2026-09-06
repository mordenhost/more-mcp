<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UncannyAutomator {

	const RECIPE_POST_TYPE  = 'uo-recipe';
	const TRIGGER_POST_TYPE = 'uo-trigger';
	const ACTION_POST_TYPE  = 'uo-action';

	public static function is_available() {

		return function_exists( 'Automator' ) && defined( 'AUTOMATOR_POST_TYPE_RECIPE' );
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'uncanny-automator' ),
			'capabilities' => array( 'automation' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'uncanny_automator_get_recipes',
				'description' => 'List Uncanny Automator recipes: id, title, post status (publish = live, draft), and the count of triggers and actions each recipe has (its published uo-trigger / uo-action children). Paginated, newest first. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Rows per page, 1 to 50. Default 20.' ),
						'status'   => array( 'type' => 'string', 'description' => 'Optional post-status filter (e.g. publish, draft).' ),
					),
				),
			),
			array(
				'name'        => 'uncanny_automator_get_run_stats',
				'description' => 'Aggregate Uncanny Automator run statistics from its recipe log: total logged runs, completed runs, and incomplete runs, plus recipe counts by post status. The recipe log records which user each run belongs to — that column is never selected; counts only. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
		);
	}

	public static function execute_tool( $name, $args ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use automation tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Uncanny Automator is not active.' );
		}
		if ( 'uncanny_automator_get_recipes' === $name ) {
			return self::get_recipes( $args );
		}
		if ( 'uncanny_automator_get_run_stats' === $name ) {
			return self::get_run_stats();
		}
		throw new \Exception( 'Unknown Uncanny Automator tool: ' . esc_html( $name ) );
	}

	private static function get_recipes( $args ) {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, absint( $args['per_page'] ?? 20 ) ) );
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';

		$query_args = array(
			'post_type'      => self::RECIPE_POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'post_status'    => '' !== $status ? $status : array( 'publish', 'draft', 'pending' ),
		);
		$query = new \WP_Query( $query_args );

		$recipes = array();
		foreach ( (array) $query->posts as $post ) {
			$recipes[] = array(
				'id'            => (int) $post->ID,
				'title'         => (string) get_the_title( $post ),
				'status'        => (string) $post->post_status,
				'trigger_count' => self::child_count( self::TRIGGER_POST_TYPE, (int) $post->ID ),
				'action_count'  => self::child_count( self::ACTION_POST_TYPE, (int) $post->ID ),
			);
		}

		$total = (int) $query->found_posts;
		return array(
			'provider'  => 'uncanny-automator',
			'total'     => $total,
			'page'      => $page,
			'per_page'  => $per_page,
			'pages'     => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'returned'  => count( $recipes ),
			'has_more'  => ( $page - 1 ) * $per_page + count( $recipes ) < $total,
			'recipes'   => $recipes,
		);
	}

	private static function get_run_stats() {
		global $wpdb;

		$by_status = array();
		$counts    = wp_count_posts( self::RECIPE_POST_TYPE );
		foreach ( (array) $counts as $status => $n ) {
			if ( is_numeric( $n ) && (int) $n > 0 ) {
				$by_status[ (string) $status ] = (int) $n;
			}
		}

		$log = $wpdb->prefix . 'uap_recipe_log';
		if ( ! self::table_exists( 'uap_recipe_log' ) ) {
			return array(
				'provider'         => 'uncanny-automator',
				'recipes_by_status' => $by_status,
				'runs'             => array( 'available' => false, 'message' => 'The Uncanny Automator recipe-log table does not exist (no recipe has run).' ),
			);
		}

		
		
		$total     = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$log}" ); 
		$completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$log} WHERE completed = %d", 1 ) ); 

		return array(
			'provider'          => 'uncanny-automator',
			'recipes_by_status' => $by_status,
			'runs'              => array(
				'available'  => true,
				'total'      => $total,
				'completed'  => $completed,
				'incomplete' => max( 0, $total - $completed ),
			),
		);
	}

	private static function child_count( $post_type, $recipe_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( 
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s AND post_status = 'publish'",
				$recipe_id,
				$post_type
			)
		);
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); 
		return $found === $table;
	}
}
