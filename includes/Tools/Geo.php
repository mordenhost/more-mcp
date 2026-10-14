<?php

namespace Mordenhost_MCP\Tools;

use Mordenhost_MCP\SEO\Detector;
use Mordenhost_MCP\SEO\Llms_Txt;
use Mordenhost_MCP\SEO\Schema;
use Mordenhost_MCP\SEO\Schema_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Geo implements Handler {

	const TOOLS = array(
		'wp_generate_llms_txt',
		'wp_get_llms_txt',
		'wp_update_llms_txt',
		'wp_get_schema',
		'wp_upsert_schema',
		'wp_validate_schema',
	);

	public static function register(): void {
		Llms_Txt::register();
		Schema::register();
	}

	public static function get_tools(): array {
		$schema_value = array(
			'anyOf'       => array(
				array( 'type' => 'object' ),
				array( 'type' => 'array' ),
				array( 'type' => 'string' ),
			),
			'description' => 'JSON-LD: one node object, a list of nodes, an {"@context","@graph"} object, or the same as a JSON string.',
		);
		$history = ' Recorded in change history; restore with wp_history_list + wp_history_apply.';
		return array(
			array(
				'name'        => 'wp_generate_llms_txt',
				'description' => 'Build an llms.txt (llmstxt.org) from the site: the site title as the H1, the tagline (or description) as the summary, one section per post type listing published entries as links with a short excerpt, then any custom_sections (use one for an identity or contact block; nothing is read from admin_email). Skips password-protected posts and posts the SEO plugin marks noindex. Saves it and serves it at /llms.txt from the database, with no file written, unless dry_run is true. Refuses to save while another source serves /llms.txt (a static llms.txt file, Yoast SEO, Rank Math, All in One SEO or Website LLMs.txt) and names it; a dry run still returns the draft. Replacing text that was last edited by hand needs overwrite: true.' . $history . ' Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_types'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Public post types to list, one section each, in this order. Default ["page", "post"].',
						),
						'exclude_ids'     => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Post IDs to leave out.',
						),
						'limit'           => array(
							'type'        => 'integer',
							'description' => 'Most entries per post type (1-200, default 50). Pages are ordered by menu order with the static front page first; other types newest first.',
						),
						'description'     => array(
							'type'        => 'string',
							'description' => 'One-line summary under the title. Default: the site tagline.',
						),
						'custom_sections' => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'title'   => array( 'type' => 'string' ),
									'content' => array( 'type' => 'string', 'description' => 'Markdown body of the section.' ),
								),
								'required'   => array( 'title', 'content' ),
							),
							'description' => 'Sections added after the post lists, e.g. {"title": "Contact", "content": "- Email: hello@example.com"}.',
						),
						'enabled'         => array(
							'type'        => 'boolean',
							'description' => 'Serve the saved text at /llms.txt. Default true.',
						),
						'overwrite'       => array(
							'type'        => 'boolean',
							'description' => 'Replace stored text that was last edited by hand with wp_update_llms_txt. Default false.',
						),
						'dry_run'         => array(
							'type'        => 'boolean',
							'description' => 'Return the draft without saving it. Default false.',
						),
					),
				),
			),
			array(
				'name'        => 'wp_get_llms_txt',
				'description' => 'Get the stored llms.txt text and whether it is served at /llms.txt, and if not, why: nothing stored, serving turned off, or another source serving /llms.txt (a static file, Yoast SEO, Rank Math, All in One SEO or Website LLMs.txt), with how to switch that source off. Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'wp_update_llms_txt',
				'description' => 'Replace the stored llms.txt text and/or turn serving at /llms.txt on or off. content replaces the whole file (Markdown, starting with "# Site name"); to change one block, such as the identity or contact section, read the text with wp_get_llms_txt, edit it and send it back. An empty content clears it and stops serving. Turning serving on is refused while another source serves /llms.txt; turning it off always works.' . $history . ' Requires manage_options.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'content' => array(
							'type'        => 'string',
							'description' => 'Full llms.txt text. Omit to change only enabled.',
						),
						'enabled' => array(
							'type'        => 'boolean',
							'description' => 'Serve the text at /llms.txt. Omit to keep the current setting (new text is served by default).',
						),
					),
				),
			),
			array(
				'name'        => 'wp_get_schema',
				'description' => 'Get the JSON-LD schema.org nodes Mordenhost MCP stores for a post, how they reach the page (joined to Yoast SEO\'s or Rank Math\'s schema graph when one of them is the active SEO plugin, otherwise printed in their own script tag), and a validation of the stored nodes. To see everything the page renders, including the SEO plugin\'s own nodes, use wp_validate_schema with post_id. Requires edit_posts and read access to the post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post, page or custom post type entry ID' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'wp_upsert_schema',
				'description' => 'Add or replace JSON-LD schema.org nodes (LocalBusiness, Service, FAQPage, Article, Product, Event, ...) on a post or page. Default mode "merge": each node replaces the stored node with the same @id, or (without @ids) the same @type, and other stored nodes stay; mode "replace" stores exactly what is sent, and an empty list removes them all. Every node is validated first: invalid JSON, a missing @type, a @context other than schema.org, or a missing required property (for example LocalBusiness without address, FAQPage without mainEntity) refuses the write; warnings (missing recommended properties, duplicate types, staging URLs) come back with the result. @context is added on output. Output follows the active SEO plugin: joined to Yoast SEO\'s or Rank Math\'s graph (a node of a type the plugin already outputs is merged into it, not duplicated), otherwise its own script tag. dry_run previews the stored result.' . $history . ' Requires edit_post on the post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post, page or custom post type entry ID' ),
						'schema'  => $schema_value,
						'mode'    => array(
							'type'        => 'string',
							'enum'        => array( 'merge', 'replace' ),
							'description' => 'merge (default) or replace.',
						),
						'dry_run' => array(
							'type'        => 'boolean',
							'description' => 'Validate and return what would be stored, without saving. Default false.',
						),
					),
					'required'   => array( 'post_id', 'schema' ),
				),
			),
			array(
				'name'        => 'wp_validate_schema',
				'description' => 'Validate JSON-LD. Pass schema to check a draft, or post_id / url to check what the page renders: every JSON-LD block in its head, from the SEO plugin and from Mordenhost MCP together (rendered in-process, like seo_get_head). Reports invalid JSON, a missing or non-schema.org @context or @type, missing required properties of common types (LocalBusiness, FAQPage, Product, Offer, Review, AggregateRating, Event, Recipe, VideoObject, JobPosting, BreadcrumbList, Person, ...), dates that are not ISO 8601, prices and ratings that are not numbers, non-absolute URLs, duplicate or conflicting types, and absolute URLs that still point at a staging, development or local host. Read-only. Requires edit_posts (and read access to the post).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'schema'  => $schema_value,
						'post_id' => array( 'type' => 'integer', 'description' => 'Check the JSON-LD this post renders.' ),
						'url'     => array( 'type' => 'string', 'description' => 'URL on this site (used when post_id is not given).' ),
					),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return in_array( $name, self::TOOLS, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_generate_llms_txt':
				self::require_manage_options();
				return self::generate_llms_txt( $args );
			case 'wp_get_llms_txt':
				self::require_manage_options();
				return self::get_llms_txt();
			case 'wp_update_llms_txt':
				self::require_manage_options();
				return self::update_llms_txt( $args );
			case 'wp_get_schema':
				return self::get_schema( $args );
			case 'wp_upsert_schema':
				return self::upsert_schema( $args );
			case 'wp_validate_schema':
				return self::validate_schema( $args );
		}
		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function require_manage_options(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'manage_options capability required: llms.txt is a site-wide file.' );
		}
	}

	private static function flag( array $args, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $args ) ) {
			return $default;
		}
		$value = $args[ $key ];
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( '', 'false', '0', 'no' ), true );
		}
		return (bool) $value;
	}

	

	private static function generate_llms_txt( array $args ): array {

		$dry_run = ! empty( $args['dry_run'] );
		$enabled = self::flag( $args, 'enabled', true );
		$draft   = Llms_Txt::generate( $args );

		$out = array(
			'dry_run'  => $dry_run,
			'saved'    => false,
			'url'      => Llms_Txt::url(),
			'bytes'    => strlen( $draft['content'] ),
			'sections' => $draft['sections'],
			'skipped'  => $draft['skipped'],
			'content'  => $draft['content'],
		);
		if ( $dry_run ) {
			$out['status'] = Llms_Txt::status();
			$out['note']   = 'Draft only; nothing was saved. Call again without dry_run to save and serve it.';
			return $out;
		}

		$current = Llms_Txt::state();
		if ( 'manual' === $current['source'] && '' !== $current['content'] && $current['content'] !== $draft['content'] && ! self::flag( $args, 'overwrite', false ) ) {
			throw new \Exception( 'Not saved: the stored llms.txt was last edited by hand (wp_update_llms_txt), and generating would replace those edits. Pass overwrite: true to replace it, or dry_run: true to compare the draft first.' );
		}
		if ( $enabled ) {
			Llms_Txt::refuse_if_conflicting( 'wp_generate_llms_txt' );
		}

		$changed      = Llms_Txt::save( $draft['content'], $enabled, 'generated' );
		$out['saved'] = true;
		if ( ! $changed ) {
			$out['unchanged'] = true;
		} else {
			$out['cache_purge'] = Llms_Txt::purge_cache();
		}
		$out['status'] = Llms_Txt::status();
		return $out;
	}

	private static function get_llms_txt(): array {
		$state = Llms_Txt::state();
		return array(
			'content'         => $state['content'],
			'bytes'           => strlen( $state['content'] ),
			'enabled'         => $state['enabled'],
			'source'          => '' === $state['source'] ? null : $state['source'],
			'updated_at'      => '' === $state['updated_at'] ? null : $state['updated_at'],
			'status'          => Llms_Txt::status(),
			'format_warnings' => Llms_Txt::format_warnings( $state['content'] ),
		);
	}

	private static function update_llms_txt( array $args ): array {
		$has_content = array_key_exists( 'content', $args );
		$has_enabled = array_key_exists( 'enabled', $args );
		if ( ! $has_content && ! $has_enabled ) {
			throw new \Exception( 'Pass content, enabled, or both.' );
		}
		if ( $has_content && ! is_string( $args['content'] ) ) {
			throw new \Exception( 'content must be a string.' );
		}

		$current = Llms_Txt::state();
		$content = $has_content ? Llms_Txt::sanitize_content( (string) $args['content'] ) : $current['content'];
		if ( $has_enabled ) {
			$enabled = self::flag( $args, 'enabled', false );
		} elseif ( $has_content && '' === $content ) {
			
			$enabled = false;
		} else {
			$enabled = ( $has_content && '' === $current['content'] ) ? true : $current['enabled'];
		}
		if ( $enabled && '' === $content ) {
			throw new \Exception( 'There is no llms.txt text to serve. Pass content, or build a draft with wp_generate_llms_txt.' );
		}
		if ( $enabled ) {
			Llms_Txt::refuse_if_conflicting( 'wp_update_llms_txt' );
		}

		$source  = $has_content ? 'manual' : ( '' !== $current['source'] ? $current['source'] : 'manual' );
		$changed = Llms_Txt::save( $content, $enabled, $source );
		$state   = Llms_Txt::state();
		$out     = array(
			'saved'           => true,
			'unchanged'       => ! $changed,
			'enabled'         => $state['enabled'],
			'bytes'           => strlen( $state['content'] ),
			'verified'        => $state['content'] === $content && $state['enabled'] === ( '' !== $content && $enabled ),
			'status'          => Llms_Txt::status(),
			'format_warnings' => Llms_Txt::format_warnings( $state['content'] ),
		);
		if ( $changed ) {
			$out['cache_purge'] = Llms_Txt::purge_cache();
		}
		return $out;
	}

	

	
	private static function post_for( int $post_id, string $cap ): \WP_Post {
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( 'Post not found: ' . esc_html( (string) $post_id ) );
		}
		if ( ! current_user_can( $cap, $post_id ) ) {
			throw new \Exception( 'read_post' === $cap ? 'You do not have permission to read this post.' : 'edit_post capability required for this post.' );
		}
		return $post;
	}

	private static function site_host(): string {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	private static function get_schema( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to read schema data.' );
		}
		$post  = self::post_for( (int) ( $args['post_id'] ?? 0 ), 'read_post' );
		$nodes = Schema::stored( (int) $post->ID );
		return array(
			'post_id'    => (int) $post->ID,
			'nodes'      => $nodes,
			'count'      => count( $nodes ),
			'output'     => Schema::output_report(),
			'validation' => $nodes
				? Schema_Validator::validate_nodes(
					$nodes,
					array(
						'require_context' => false,
						'site_host'       => self::site_host(),
					)
				)
				: null,
			'detection'  => Detector::report(),
		);
	}

	private static function upsert_schema( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to edit schema data.' );
		}
		$post    = self::post_for( (int) ( $args['post_id'] ?? 0 ), 'edit_post' );
		$post_id = (int) $post->ID;
		if ( function_exists( 'is_post_type_viewable' ) && ! is_post_type_viewable( $post->post_type ) ) {
			throw new \Exception( 'Post type "' . esc_html( $post->post_type ) . '" has no public pages, so schema on it would never be output.' );
		}
		if ( ! array_key_exists( 'schema', $args ) ) {
			throw new \Exception( 'schema is required.' );
		}
		$mode = isset( $args['mode'] ) ? (string) $args['mode'] : 'merge';
		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			throw new \Exception( 'mode must be "merge" or "replace".' );
		}
		$dry_run = ! empty( $args['dry_run'] );
		$opts    = array(
			'require_context' => false,
			'site_host'       => self::site_host(),
		);

		$raw      = $args['schema'];
		$clearing = 'replace' === $mode && ( array() === $raw || ( is_string( $raw ) && '[]' === trim( $raw ) ) );
		$incoming = array();
		if ( ! $clearing ) {
			$decoded = Schema_Validator::decode( $raw );
			if ( $decoded['errors'] ) {
				self::refuse_invalid( $decoded['errors'] );
			}
			$opts['context'] = $decoded['context'];
			$check           = Schema_Validator::validate_nodes( $decoded['nodes'], $opts );
			if ( ! $check['valid'] ) {
				self::refuse_invalid( $check['errors'] );
			}
			foreach ( $decoded['nodes'] as $node ) {
				unset( $node['@context'] );
				$incoming[] = $node;
			}
		}

		$existing = Schema::stored( $post_id );
		if ( 'replace' === $mode ) {
			$merge = array(
				'nodes'    => $incoming,
				'replaced' => array_map( array( Schema::class, 'label' ), $existing ),
				'added'    => array_map( array( Schema::class, 'label' ), $incoming ),
			);
		} else {
			$merge = Schema::merge( $existing, $incoming );
		}
		$nodes = $merge['nodes'];

		if ( count( $nodes ) > Schema::MAX_NODES ) {
			throw new \Exception( 'Not saved: a post holds at most ' . (int) Schema::MAX_NODES . ' schema nodes.' );
		}
		$bytes = strlen( Schema::encode_for_storage( $nodes ) );
		if ( $bytes > Schema::MAX_BYTES ) {
			throw new \Exception( 'Not saved: the schema is larger than ' . (int) ( Schema::MAX_BYTES / 1024 ) . ' KB.' );
		}

		
		$combined = $nodes ? Schema_Validator::validate_nodes( $nodes, array( 'require_context' => false, 'site_host' => $opts['site_host'] ) ) : null;
		$out      = array(
			'post_id'  => $post_id,
			'mode'     => $mode,
			'dry_run'  => $dry_run,
			'saved'    => false,
			'replaced' => $merge['replaced'],
			'added'    => $merge['added'],
			'nodes'    => $nodes,
			'count'    => count( $nodes ),
			'warnings' => $combined ? $combined['warnings'] : array(),
			'output'   => Schema::output_report(),
			'next'     => 'Check what the page now renders, SEO plugin nodes included, with wp_validate_schema (post_id ' . $post_id . ').',
		);
		if ( $dry_run ) {
			$out['note'] = 'Preview only; nothing was saved.';
			return $out;
		}
		if ( $nodes === $existing ) {
			$out['saved']     = true;
			$out['unchanged'] = true;
			return $out;
		}

		$write           = Schema::store( $post_id, $nodes );
		$stored          = Schema::stored( $post_id );
		$out['saved']    = ! empty( $write['written'] ) || $stored === $nodes;
		$out['verified'] = $stored === $nodes;
		if ( ! $out['verified'] ) {
			$out['verify_note'] = 'The stored schema differs from what was sent; a filter on post meta may have changed it. Read it back with wp_get_schema.';
			if ( ! empty( $write['warnings'] ) ) {
				$out['write_warnings'] = $write['warnings'];
			}
		}
		return $out;
	}

	private static function refuse_invalid( array $errors ): void {
		$shown = array_slice( $errors, 0, 12 );
		$more  = count( $errors ) - count( $shown );
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the MCP client (JSON result), never rendered as HTML; quoting the offending values is the point.
		throw new \Exception( 'Not saved: the schema is invalid. ' . implode( ' ', $shown ) . ( $more > 0 ? ' (' . $more . ' more)' : '' ) );
	}

	private static function validate_schema( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to validate schema data.' );
		}
		$has_schema = array_key_exists( 'schema', $args ) && null !== $args['schema'];
		$has_target = ! empty( $args['post_id'] ) || ! empty( $args['url'] );
		if ( $has_schema === $has_target ) {
			throw new \Exception( 'Pass either schema (to check a draft) or post_id / url (to check a rendered page), not both.' );
		}

		if ( $has_schema ) {
			$decoded = Schema_Validator::decode( $args['schema'] );
			if ( $decoded['errors'] ) {
				return array(
					'source'          => 'input',
					'valid'           => false,
					'errors'          => $decoded['errors'],
					'warnings'        => array(),
					'types'           => array(),
					'unchecked_types' => array(),
				);
			}
			$result = Schema_Validator::validate_nodes(
				$decoded['nodes'],
				array(
					'context'         => $decoded['context'],
					'require_context' => false,
					'site_host'       => self::site_host(),
				)
			);
			if ( null === $decoded['context'] ) {
				foreach ( $decoded['nodes'] as $node ) {
					if ( is_array( $node ) && ! isset( $node['@context'] ) ) {
						$result['warnings'][] = 'No @context: wp_upsert_schema adds "https://schema.org" for you, but a block written into a page by hand needs it.';
						break;
					}
				}
			}
			return array_merge( array( 'source' => 'input' ), $result );
		}

		
		$head_args = ! empty( $args['post_id'] ) ? array( 'post_id' => (int) $args['post_id'] ) : array( 'url' => (string) $args['url'] );
		$head      = Seo_Extras::execute_tool( 'seo_get_head', $head_args );
		$blocks    = array();
		$sources   = array();
		if ( preg_match_all( '#<script\b([^>]*)type=["\']application/ld\+json["\']([^>]*)>(.*?)</script>#is', (string) ( $head['html'] ?? '' ), $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $match ) {
				$attrs     = $match[1] . ' ' . $match[2];
				$blocks[]  = trim( $match[3] );
				$sources[] = self::block_source( $attrs );
			}
		}

		$post_id = (int) ( $head['post_id'] ?? 0 );
		$out     = array(
			'source'        => 'rendered',
			'post_id'       => $post_id,
			'permalink'     => $head['permalink'] ?? null,
			'rendered_by'   => $head['source'] ?? null,
			'blocks'        => count( $blocks ),
			'block_sources' => $sources,
		);
		if ( ! $blocks ) {
			$out = array_merge(
				$out,
				array(
					'valid'           => true,
					'errors'          => array(),
					'warnings'        => array( 'The page renders no JSON-LD. Add nodes with wp_upsert_schema.' ),
					'types'           => array(),
					'unchecked_types' => array(),
				)
			);
		} else {
			$result = Schema_Validator::validate_blocks( $blocks, array( 'site_host' => self::site_host() ) );
			unset( $result['blocks'] );
			$out = array_merge( $out, $result );
		}
		if ( ! empty( $head['truncated'] ) ) {
			$out['warnings'][] = 'The head was larger than seo_get_head returns, so the last JSON-LD block may be cut off and read as invalid.';
		}
		$out['stored_nodes'] = $post_id > 0 ? count( Schema::stored( $post_id ) ) : 0;
		$out['output']       = Schema::output_report();
		return $out;
	}

	private static function block_source( string $attrs ): string {
		if ( false !== stripos( $attrs, 'mordenhost-mcp-schema' ) ) {
			return 'mordenhost-mcp';
		}
		if ( false !== stripos( $attrs, 'yoast-schema-graph' ) ) {
			return 'yoast';
		}
		if ( false !== stripos( $attrs, 'rank-math-schema' ) ) {
			return 'rankmath';
		}
		if ( false !== stripos( $attrs, 'aioseo-schema' ) ) {
			return 'aioseo';
		}
		return 'other';
	}
}
