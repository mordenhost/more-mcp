<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Extras implements Handler {

	public static function get_tools(): array {
		return array(
			array(
				'name'        => 'wp_get_post_full',
				'description' => 'Get a post with everything attached in one call: content, all non-protected meta, terms in every taxonomy, featured image (id, url, alt) and author (id, display name). Replaces four or five separate calls when auditing a page. Needs read_post; for a password-protected post the content is withheld unless you can edit it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Post ID (any post type).' ) ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_create_preview_link',
				'description' => 'Create a temporary link that shows an unpublished post or page (draft, pending, scheduled, private) to someone without a login, until it expires. The link is read-only, never indexed or cached, and works for that one post. Needs edit_post. Default lifetime 48 hours, maximum 30 days.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => array( 'type' => 'integer', 'description' => 'Post ID.' ),
						'expires_in_hours' => array( 'type' => 'integer', 'description' => 'Hours the link stays valid (default 48, max 720).' ),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_get_term',
				'description' => 'Get one term from any taxonomy (category, post_tag or custom): name, slug, description, parent, count and archive link.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'Term ID.' ),
						'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy slug the term belongs to.' ),
					),
					'required'   => array( 'id', 'taxonomy' ),
				),
			),
			array(
				'name'        => 'wp_get_revision',
				'description' => 'Get one revision with its full title, content and excerpt, for comparing or restoring. Needs read_post on the parent post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Revision ID (from wp_get_post_revisions).' ) ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_delete_revision',
				'description' => 'Permanently delete one revision. The parent post and its other revisions are untouched. Needs delete_post on the parent post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Revision ID.' ) ),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'wp_get_post_statuses',
				'description' => 'List every post status this site supports (publish, draft, pending, private, future, plus any a plugin registers) with label and whether it is public, private or internal, so a valid status can always be chosen.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new \stdClass() ),
			),
		);
	}

	public static function supports( string $name ): bool {
		static $names = array(
			'wp_get_post_full', 'wp_create_preview_link', 'wp_get_term',
			'wp_get_revision', 'wp_delete_revision', 'wp_get_post_statuses',
		);
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_post_full':
				return self::get_post_full( $args );
			case 'wp_create_preview_link':
				return self::create_preview_link( $args );
			case 'wp_get_term':
				return self::get_term_tool( $args );
			case 'wp_get_revision':
				return self::get_revision_tool( $args );
			case 'wp_delete_revision':
				return self::delete_revision_tool( $args );
			case 'wp_get_post_statuses':
				return self::post_statuses();
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function post_or_fail( int $id ): \WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || 'revision' === $post->post_type ) {
			throw new \Exception( 'Post not found.' );
		}
		return $post;
	}

	private static function get_post_full( array $args ): array {
		$post = self::post_or_fail( (int) ( $args['id'] ?? 0 ) );
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			throw new \Exception( 'You do not have permission to read this post.' );
		}
		$can_edit = current_user_can( 'edit_post', $post->ID );

		
		$meta      = array();
		$protected = array();
		foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
			if ( is_protected_meta( (string) $key, 'post' ) ) {
				$protected[] = $key;
				continue;
			}
			if ( Options_Support::is_sensitive_key( (string) $key ) ) {
				$meta[ $key ] = '[REDACTED]';
				continue;
			}
			$decoded      = array_map( 'maybe_unserialize', (array) $values );
			$meta[ $key ] = 1 === count( $decoded ) ? $decoded[0] : $decoded;
		}

		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			$assigned = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $assigned ) || ! $assigned ) {
				continue;
			}
			$terms[ $taxonomy ] = array_map(
				static function ( $t ) {
					return array(
						'id'   => (int) $t->term_id,
						'name' => $t->name,
						'slug' => $t->slug,
					);
				},
				$assigned
			);
		}

		$thumb_id = (int) get_post_thumbnail_id( $post );
		$featured = null;
		if ( $thumb_id ) {
			$featured = array(
				'id'  => $thumb_id,
				'url' => wp_get_attachment_url( $thumb_id ),
				'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			);
		}

		$withheld = post_password_required( $post ) && ! $can_edit;

		return array(
			'id'              => (int) $post->ID,
			'type'            => $post->post_type,
			'status'          => $post->post_status,
			'title'           => $post->post_title,
			'slug'            => $post->post_name,
			'url'             => get_permalink( $post ),
			'content'         => $withheld ? '' : $post->post_content,
			'content_withheld' => $withheld,
			'excerpt'         => $withheld ? '' : $post->post_excerpt,
			'date'            => $post->post_date,
			'modified'        => $post->post_modified,
			'parent'          => (int) $post->post_parent,
			'author'          => array(
				'id'           => (int) $post->post_author,
				'display_name' => get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'featured_image'  => $featured,
			'terms'           => (object) $terms,
			'meta'            => (object) $meta,
			'protected_meta_keys' => $protected,
		);
	}

	private static function create_preview_link( array $args ): array {
		$post = self::post_or_fail( (int) ( $args['id'] ?? 0 ) );
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new \Exception( 'You do not have permission to share this post.' );
		}
		if ( 'publish' === $post->post_status ) {
			throw new \Exception( 'This post is already published; use its normal link: ' . esc_url_raw( (string) get_permalink( $post ) ) );
		}
		$hours = isset( $args['expires_in_hours'] ) ? (int) $args['expires_in_hours'] : 48;
		$hours = max( 1, min( $hours, 720 ) );

		$link = Preview_Links::create( (int) $post->ID, $hours * HOUR_IN_SECONDS, get_current_user_id() );
		return array(
			'post_id'          => (int) $post->ID,
			'status'           => $post->post_status,
			'url'              => $link['url'],
			'expires_at'       => gmdate( 'c', $link['expires_at'] ),
			'expires_in_hours' => $link['expires_in_hours'],
			'note'             => 'Anyone with this link can read the post until it expires. It is not indexed or cached.',
		);
	}

	private static function get_term_tool( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read terms.' );
		}
		$taxonomy = sanitize_key( (string) ( $args['taxonomy'] ?? '' ) );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			throw new \Exception( 'Unknown taxonomy. Use wp_get_taxonomies to list them.' );
		}
		$term = get_term( (int) ( $args['id'] ?? 0 ), $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			throw new \Exception( 'Term not found in that taxonomy.' );
		}
		$link = get_term_link( $term );
		return array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'link'        => is_wp_error( $link ) ? '' : $link,
		);
	}

	private static function revision_or_fail( int $id ): \WP_Post {
		$revision = $id > 0 ? wp_get_post_revision( $id ) : null;
		if ( ! $revision ) {
			throw new \Exception( 'Revision not found.' );
		}
		return $revision;
	}

	private static function get_revision_tool( array $args ): array {
		$revision = self::revision_or_fail( (int) ( $args['id'] ?? 0 ) );
		if ( ! current_user_can( 'read_post', (int) $revision->post_parent ) ) {
			throw new \Exception( 'You do not have permission to read revisions of this post.' );
		}
		return array(
			'revision_id' => (int) $revision->ID,
			'parent_id'   => (int) $revision->post_parent,
			'author_id'   => (int) $revision->post_author,
			'date'        => $revision->post_date,
			'title'       => $revision->post_title,
			'content'     => $revision->post_content,
			'excerpt'     => $revision->post_excerpt,
		);
	}

	private static function delete_revision_tool( array $args ): array {
		$revision = self::revision_or_fail( (int) ( $args['id'] ?? 0 ) );

		
		if ( ! current_user_can( 'delete_post', (int) $revision->post_parent ) ) {
			throw new \Exception( 'You do not have permission to delete revisions of this post.' );
		}
		if ( ! wp_delete_post_revision( $revision->ID ) ) {
			throw new \Exception( 'WordPress could not delete that revision.' );
		}
		return array(
			'revision_id' => (int) $revision->ID,
			'parent_id'   => (int) $revision->post_parent,
			'message'     => 'Revision deleted.',
		);
	}

	private static function post_statuses(): array {
		if ( ! current_user_can( 'read' ) ) {
			throw new \Exception( 'You do not have permission to list post statuses.' );
		}
		$rows = array();
		foreach ( get_post_stati( array(), 'objects' ) as $slug => $status ) {
			$rows[] = array(
				'slug'     => $slug,
				'label'    => $status->label,
				'public'   => (bool) $status->public,
				'private'  => (bool) $status->private,
				'protected' => (bool) $status->protected,
				'internal' => (bool) $status->internal,
			);
		}
		return $rows;
	}
}
