<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Optimizer_Target {

	public static function attachment( array $args, $write ) {
		$id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
		if ( $id <= 0 ) {
			throw new \Exception( 'attachment_id is required.' );
		}
		$post = get_post( $id );
		if ( ! is_object( $post ) || 'attachment' !== ( $post->post_type ?? '' ) ) {
			throw new \Exception( esc_html( 'No media attachment with id ' . $id . '.' ) );
		}
		$cap = $write ? 'edit_post' : 'read_post';
		if ( ! current_user_can( $cap, $id ) ) {
			throw new \Exception( esc_html( 'You do not have permission to ' . ( $write ? 'change' : 'read' ) . ' attachment ' . $id . '.' ) );
		}
		return $post;
	}

	public static function confirmed( array $args ) {
		if ( ! array_key_exists( 'confirm', $args ) ) {
			return false;
		}
		return in_array( $args['confirm'], array( true, 1, '1', 'true' ), true );
	}

	public static function summary( $post ) {
		return array(
			'attachment_id' => (int) $post->ID,
			'title'         => (string) ( $post->post_title ?? '' ),
			'mime'          => (string) ( $post->post_mime_type ?? '' ),
		);
	}

	public static function percent( $before, $after ) {
		if ( null === $before || null === $after || $before <= 0 ) {
			return null;
		}
		return round( ( max( 0, $before - $after ) / $before ) * 100, 2 );
	}

	public static function schema( $with_confirm = false ) {
		$properties = array(
			'attachment_id' => array(
				'type'        => 'integer',
				'description' => 'Media library attachment id (for a post\'s featured image, read it with wp_get_post first).',
			),
		);
		if ( $with_confirm ) {
			$properties['confirm'] = array(
				'type'        => 'boolean',
				'description' => 'Must be true to actually run. Omit it first to get a preview of what would happen, including whether a backup of the original will be kept.',
			);
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => array( 'attachment_id' ),
		);
	}
}
