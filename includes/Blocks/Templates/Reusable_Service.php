<?php
namespace More_MCP\Blocks\Templates;

use More_MCP\MCP\Undo_Store;
use More_MCP\Blocks\Parser;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Reusable_Service {
	
	public static function list_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to list reusable blocks.' );
		}

		$per_page = min( max( (int) ( $args['per_page'] ?? 20 ), 1 ), 100 );
		$page     = max( (int) ( $args['page'] ?? 1 ), 1 );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		$query = [
			'post_type'      => 'wp_block',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		if ( $search !== '' ) {
			$query['s'] = $search;
		}

		$posts = get_posts( $query );
		$total = wp_count_posts( 'wp_block' )->publish ?? 0;

		$blocks = [];
		foreach ( $posts as $post ) {
			$blocks[] = [
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'snippet'     => Parser::plain_text_snippet( $post->post_content, 200 ),
				'content_hash' => hash( 'sha256', $post->post_content ),
			];
		}

		return [
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'pages'         => (int) ceil( $total / $per_page ),
			'reusable_count' => count( $blocks ),
			'reusable'      => $blocks,
		];
	}

	public static function get_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read reusable blocks.' );
		}
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		$blocks = Parser::parse( $post->post_content );
		$summary = Parser::summarize( $blocks, '', 10, 80 );

		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'content_hash' => hash( 'sha256', $post->post_content ),
			'block_count' => count( $summary ),
			'structure'   => $summary,
			'reusable_note' => 'This block is global. Editing it is reflected everywhere it is embedded, including in pages you do not have edit_posts on.',
		];
	}

	public static function create_reusable( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to create reusable blocks.' );
		}
		if ( empty( $args['title'] ) || ! is_string( $args['title'] ) ) {
			throw new \Exception( 'title is required.' );
		}
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) || $args['content'] === '' ) {
			throw new \Exception( 'content is required.' );
		}

		$check = Parser::round_trip_check( Parser::parse( $args['content'] ) );
		if ( ! $check['ok'] ) {
			throw new \Exception( 'Block markup failed the self-consistency check: ' . esc_html( $check['reason'] ) );
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_slash( $check['content'] ),
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to create reusable block: ' . esc_html( $post_id->get_error_message() ) );
		}

		return [
			'id'          => $post_id,
			'title'       => sanitize_text_field( $args['title'] ),
			'content_hash' => hash( 'sha256', $check['content'] ),
			'snippet'     => Parser::plain_text_snippet( $check['content'], 200 ),
		];
	}

	public static function update_reusable( array $args ): array {
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( 'You do not have permission to edit this reusable block.' );
		}
		if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) || $args['content'] === '' ) {
			throw new \Exception( 'content is required.' );
		}

		$pre_content = $post->post_content;
		$new_content = $args['content'];
		$new_title   = isset( $args['title'] ) && is_string( $args['title'] ) && $args['title'] !== ''
			? sanitize_text_field( $args['title'] )
			: $post->post_title;

		$check = Parser::round_trip_check( Parser::parse( $new_content ) );
		if ( ! $check['ok'] ) {
			throw new \Exception( 'Block markup failed the self-consistency check: ' . esc_html( $check['reason'] ) );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'       => true,
				'reusable_id'   => $id,
				'title'         => $new_title,
				'new_content'   => $check['content'],
				'content_hash'  => hash( 'sha256', $check['content'] ),
				'previous_hash' => hash( 'sha256', $pre_content ),
			];
		}

		$undo = Undo_Store::store( [
			'op'           => 'blocks_reusable_update',
			'summary'      => sprintf( 'Restored reusable block %d (%s) to its previous content.', $id, $new_title ),
			'target'       => [ 'post_id' => $id ],
			'pre_op_state' => [ 'post_content' => $pre_content ],
		] );

		$result = wp_update_post( [
			'ID'           => $id,
			'post_title'   => $new_title,
			'post_content' => wp_slash( $check['content'] ),
		], true );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to update reusable block: ' . esc_html( $result->get_error_message() ) );
		}

		$fresh    = get_post( $id );
		$verified = $fresh instanceof \WP_Post && (string) $fresh->post_content === $check['content'];

		return [
			'reusable_id'   => $id,
			'title'         => $new_title,
			'content_hash'  => hash( 'sha256', $check['content'] ),
			'verified'      => $verified,
			'previous_hash' => hash( 'sha256', $pre_content ),
			'undo'          => $undo,
		];
	}

	public static function delete_reusable( array $args ): array {
		$id = isset( $args['reusable_id'] ) ? (int) $args['reusable_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'reusable_id is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'wp_block' ) {
			throw new \Exception( 'Reusable block not found.' );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			throw new \Exception( 'You do not have permission to delete this reusable block.' );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'    => true,
				'reusable_id' => $id,
				'title'      => $post->post_title,
				'snippet'    => Parser::plain_text_snippet( $post->post_content, 200 ),
			];
		}

		$pre_content = $post->post_content;
		$title       = $post->post_title;

		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			throw new \Exception( 'Failed to delete reusable block.' );
		}

		$undo = Undo_Store::store( [
			'op'           => 'blocks_reusable_delete',
			'summary'      => sprintf( 'Restored deleted reusable block "%s".', $title ),
			'target'       => [ 'post_id' => $id ],
			'pre_op_state' => [
				'post_content' => $pre_content,
				'post_title'   => $title,
			],
		] );

		return [
			'reusable_id' => $id,
			'title'       => $title,
			'snippet'     => Parser::plain_text_snippet( $pre_content, 200 ),
			'undo'        => $undo,
			'note'        => 'This block has been permanently deleted. All places where it was embedded will now render nothing.',
		];
	}

}
