<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seo implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_seo_meta', 'description' => 'Get the SEO meta fields for a post (title, description, focus keyword, noindex, canonical, OG/Twitter overrides, schema page type, URL slug). Auto-detects Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, or The SEO Framework and reads from wherever that plugin actually stores each field. The response lists which fields the active plugin supports, includes the raw stored values for diagnostics, and flags when more than one SEO plugin is active (which produces duplicate meta tags in the rendered head). The slug is a WordPress-native field and is returned regardless of SEO plugin. Complements seo_audit_meta_tags, which reads the rendered output rather than the database.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer']], 'required' => ['post_id']]],
			['name' => 'wp_update_seo_meta', 'description' => 'Update SEO meta fields on a post. Auto-routes each field to wherever the active plugin actually reads it — Yoast SEO, Rank Math, All in One SEO (custom tables, not post meta), SEOPress, Slim SEO, or The SEO Framework. A field the active plugin does not store is REFUSED by name rather than written to a plausible-looking key that would store cleanly and change nothing on the page. Every write is read back from storage and the returned values are what was actually stored, so a sanitizer or normalising filter is visible instead of being reported as a clean success. The slug field is WordPress-native and works with no SEO plugin active. Requires edit_post capability on the target post.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'title' => ['type' => 'string', 'description' => 'SEO title (replaces the meta title used in browser tabs and SERPs)'], 'description' => ['type' => 'string', 'description' => 'SEO meta description (used in SERPs)'], 'focus_keyword' => ['type' => 'string', 'description' => 'Primary focus keyword for SEO scoring. Yoast, Rank Math, and SEOPress only.'], 'noindex' => ['type' => 'boolean', 'description' => 'Tell search engines not to index this URL'], 'canonical' => ['type' => 'string', 'description' => 'Canonical URL override'], 'og_title' => ['type' => 'string', 'description' => 'Open Graph title (Facebook / Slack / LinkedIn previews)'], 'og_description' => ['type' => 'string', 'description' => 'Open Graph description'], 'twitter_title' => ['type' => 'string', 'description' => 'Twitter Card title'], 'twitter_description' => ['type' => 'string', 'description' => 'Twitter Card description'], 'schema_page_type' => ['type' => 'string', 'description' => 'Schema.org page type for the @graph (Yoast only). Values that render: WebPage, ContactPage, AboutPage, CollectionPage, FAQPage, ItemPage, ProfilePage, SearchResultsPage, CheckoutPage, RealEstateListing, QAPage.'], 'slug' => ['type' => 'string', 'description' => 'URL slug (post_name). WordPress will sanitize and ensure uniqueness; the actually-saved value is returned in the response so the caller can confirm.']], 'required' => ['post_id']]],
			['name' => 'wp_get_term_seo_meta', 'description' => 'Get the SEO meta fields for a term (category, tag, or any custom taxonomy term). Reads from wherever the active SEO plugin actually stores term data, which is NOT always term meta: Yoast keeps taxonomy SEO in the wpseo_taxonomy_meta option keyed by taxonomy and term ID, and All in One SEO uses its own aioseo_terms table. Use this instead of wp_get_term_meta for SEO fields — wp_get_term_meta reads the raw meta table and will report nothing for those two plugins. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress. Requires the taxonomy, because Yoast cannot be looked up by term ID alone.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to (e.g. category, post_tag, product_cat). Required.']], 'required' => ['term_id', 'taxonomy']]],
			['name' => 'wp_update_term_seo_meta', 'description' => 'Update SEO meta fields on a term, writing to the location the active SEO plugin actually reads. This is the correct tool for taxonomy noindex: wp_update_term_meta writes to the term meta table, which Yoast never reads for noindex, so that call returns success and leaves the archive indexable (GitHub #6). Yoast writes are merged into wpseo_taxonomy_meta read-modify-write, so other terms\' settings are preserved. Yoast term noindex is tri-state — "default" inherits the taxonomy-wide setting and is not the same as an explicit index; passing noindex=false writes an explicit index. A field the active plugin does not store at term level is refused by name. Every write is read back from storage. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress. Requires manage_categories.', 'inputSchema' => ['type' => 'object', 'properties' => ['term_id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to. Required.'], 'title' => ['type' => 'string', 'description' => 'SEO title for the term archive'], 'description' => ['type' => 'string', 'description' => 'SEO meta description for the term archive'], 'focus_keyword' => ['type' => 'string', 'description' => 'Focus keyword. Yoast and Rank Math only at term level.'], 'noindex' => ['type' => 'boolean', 'description' => 'Tell search engines not to index this term archive'], 'canonical' => ['type' => 'string', 'description' => 'Canonical URL override for the term archive'], 'og_title' => ['type' => 'string', 'description' => 'Open Graph title'], 'og_description' => ['type' => 'string', 'description' => 'Open Graph description'], 'twitter_title' => ['type' => 'string', 'description' => 'Twitter Card title'], 'twitter_description' => ['type' => 'string', 'description' => 'Twitter Card description']], 'required' => ['term_id', 'taxonomy']]],
			['name' => 'seo_audit_meta_tags', 'description' => 'Fetch a post\'s actual rendered HTML and parse the head for title, meta description, canonical, viewport, Open Graph and Twitter Card tags. Catches theme/plugin/cache conflicts that only appear in the served output — duplicate title tags, mismatched canonicals, missing OG images, viewport misconfiguration. Complements wp_get_seo_meta (which reads DB fields) by validating what actually reaches crawlers. Pass a post_id (URL is resolved via get_permalink) or a same-site url. Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer', 'description' => 'Post ID whose permalink to audit. Either post_id or url is required.'], 'url' => ['type' => 'string', 'description' => 'Absolute URL to audit. Must be on this site (same host as home_url). Either post_id or url is required.']]]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_seo_meta', 'wp_update_seo_meta', 'wp_get_term_seo_meta',
			'wp_update_term_seo_meta', 'seo_audit_meta_tags',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_seo_meta':
				$post_id = intval($args['post_id'] ?? $args['id'] ?? 0);
				if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
				if (!get_post($post_id)) throw new \Exception('Post not found: ' . esc_html((string) $post_id));

				if (!current_user_can('read_post', $post_id)) {
					throw new \Exception('You do not have permission to read SEO meta on this post.');
				}

				
				
				$slug     = (string) get_post_field('post_name', $post_id);
				$detected = \More_MCP\SEO\Detector::primary();
				$response = array_merge(
					\More_MCP\SEO\Detector::report(),
					['post_id' => $post_id]
				);
				if ($detected === 'none') {
					$response['slug'] = $slug;
					$response['note'] = 'No supported SEO plugin detected. Supported: Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, The SEO Framework. The slug field is still returned because it is a WordPress-native field.';
					return $response;
				}
				$response = array_merge($response, \More_MCP\SEO\Meta::read('post', $post_id));
				$response['slug']             = $slug;
				$response['supported_fields'] = \More_MCP\SEO\Fields::supported($detected, 'post');

				
				$response['raw']              = \More_MCP\SEO\Meta::raw('post', $post_id);
				return $response;

			case 'wp_update_seo_meta':
				$post_id = intval($args['post_id'] ?? $args['id'] ?? 0);
				if ($post_id <= 0) throw new \Exception('post_id (or id) is required.');
				if (!get_post($post_id)) throw new \Exception('Post not found: ' . esc_html((string) $post_id));
				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('edit_post capability required for this post.');
				}

				

				$detected   = \More_MCP\SEO\Detector::primary();
				$seo_fields = [];
				foreach (\More_MCP\SEO\Fields::LOGICAL as $seo_field_key) {
					if (array_key_exists($seo_field_key, $args)) {
						$seo_fields[$seo_field_key] = $args[$seo_field_key];
					}
				}
				if (!empty($seo_fields) && $detected === 'none') {
					throw new \Exception('No supported SEO plugin is active (Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, or The SEO Framework). Install one first, or pass only the slug field, which is WordPress-native and works without an SEO plugin.');
				}

				$updated = empty($seo_fields)
					? []
					: \More_MCP\SEO\Meta::write('post', $post_id, '', $seo_fields);

				

				
				
				if (array_key_exists('slug', $args)) {
					$requested_slug = sanitize_title((string) $args['slug']);
					if ($requested_slug === '') {
						throw new \Exception('slug cannot be empty after sanitization. Pass a non-empty slug or omit the field.');
					}
					$update_result = wp_update_post([
						'ID'        => $post_id,
						'post_name' => $requested_slug,
					], true);
					if (is_wp_error($update_result)) {
						throw new \Exception('Slug update failed: ' . $update_result->get_error_message());
					}
					$saved_slug = (string) get_post_field('post_name', $post_id);
					$updated['slug'] = $saved_slug;
					if ($saved_slug !== $requested_slug) {
						$updated['slug_note'] = sprintf(
							'WordPress modified the slug to avoid a collision: requested "%s", saved "%s".',
							$requested_slug,
							$saved_slug
						);
					}
				}
				return array_merge(
					\More_MCP\SEO\Detector::report(),
					[
						'post_id' => $post_id,
						'updated' => $updated,
					]
				);

			case 'wp_get_term_seo_meta':
				$seo_term_id  = isset($args['term_id']) ? (int) $args['term_id'] : 0;
				$seo_taxonomy = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
				if ($seo_term_id <= 0) throw new \Exception('term_id is required.');
				if ($seo_taxonomy === '') throw new \Exception('taxonomy is required. Yoast keys its taxonomy SEO option by taxonomy, so a term ID alone does not identify a row.');
				if (!taxonomy_exists($seo_taxonomy)) {
					throw new \Exception('Taxonomy not found: ' . esc_html($seo_taxonomy));
				}
				$seo_term = get_term($seo_term_id, $seo_taxonomy);
				if (!$seo_term || is_wp_error($seo_term)) {
					throw new \Exception(sprintf('Term %d not found in taxonomy %s.', $seo_term_id, esc_html($seo_taxonomy)));
				}

				if (!current_user_can('manage_categories')) {
					throw new \Exception('manage_categories capability required to read term SEO meta.');
				}
				$seo_detected = \More_MCP\SEO\Detector::primary();
				$term_response = array_merge(
					\More_MCP\SEO\Detector::report(),
					[
						'term_id'  => $seo_term_id,
						'taxonomy' => $seo_taxonomy,
						'slug'     => (string) $seo_term->slug,
					]
				);
				if ($seo_detected === 'none') {
					$term_response['note'] = 'No supported SEO plugin detected. Supported at term level: Yoast SEO, Rank Math, All in One SEO, SEOPress.';
					return $term_response;
				}
				if (\More_MCP\SEO\Fields::spec($seo_detected, 'term') === null) {
					$term_response['note'] = sprintf(
						'%s is active but More MCP does not implement term-level SEO for it. Plugins with term-level support: Yoast SEO, Rank Math, All in One SEO, SEOPress.',
						\More_MCP\SEO\Detector::label($seo_detected)
					);
					$term_response['supported_fields'] = [];
					return $term_response;
				}
				$term_response = array_merge(
					$term_response,
					\More_MCP\SEO\Meta::read('term', $seo_term_id, $seo_taxonomy)
				);
				$term_response['supported_fields'] = \More_MCP\SEO\Fields::supported($seo_detected, 'term');
				$term_response['raw']              = \More_MCP\SEO\Meta::raw('term', $seo_term_id, $seo_taxonomy);
				if ($seo_detected === 'yoast') {

					
					$term_response['noindex_note'] = 'Yoast term noindex is tri-state: an absent or "default" value inherits the taxonomy-wide setting from Yoast\'s Search Appearance settings, which is not the same as an explicit index. See raw.wpseo_noindex for the stored value.';
				}
				return $term_response;

			case 'wp_update_term_seo_meta':
				$seo_term_id  = isset($args['term_id']) ? (int) $args['term_id'] : 0;
				$seo_taxonomy = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
				if ($seo_term_id <= 0) throw new \Exception('term_id is required.');
				if ($seo_taxonomy === '') throw new \Exception('taxonomy is required. Yoast keys its taxonomy SEO option by taxonomy, so a term ID alone does not identify a row.');
				if (!taxonomy_exists($seo_taxonomy)) {
					throw new \Exception('Taxonomy not found: ' . esc_html($seo_taxonomy));
				}
				$seo_term = get_term($seo_term_id, $seo_taxonomy);
				if (!$seo_term || is_wp_error($seo_term)) {
					throw new \Exception(sprintf('Term %d not found in taxonomy %s.', $seo_term_id, esc_html($seo_taxonomy)));
				}
				if (!current_user_can('manage_categories')) {
					throw new \Exception('manage_categories capability required to update term SEO meta.');
				}
				$term_seo_fields = [];
				foreach (\More_MCP\SEO\Fields::LOGICAL as $seo_field_key) {
					if (array_key_exists($seo_field_key, $args)) {
						$term_seo_fields[$seo_field_key] = $args[$seo_field_key];
					}
				}
				if (empty($term_seo_fields)) {
					throw new \Exception('No SEO fields supplied. Pass at least one of: ' . implode(', ', \More_MCP\SEO\Fields::LOGICAL) . '.');
				}

				
				$term_updated = \More_MCP\SEO\Meta::write('term', $seo_term_id, $seo_taxonomy, $term_seo_fields);
				return array_merge(
					\More_MCP\SEO\Detector::report(),
					[
						'term_id'  => $seo_term_id,
						'taxonomy' => $seo_taxonomy,
						'updated'  => $term_updated,
					]
				);

			case 'seo_audit_meta_tags':
				if (!current_user_can('read')) {
					throw new \Exception('read capability required.');
				}
				$seo_post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
				$seo_url     = isset($args['url']) ? esc_url_raw((string) $args['url']) : '';
				if ($seo_post_id <= 0 && $seo_url === '') {
					throw new \Exception('Either post_id or url is required.');
				}
				if ($seo_post_id > 0) {
					if (!get_post($seo_post_id)) {
						throw new \Exception('Post not found: ' . esc_html((string) $seo_post_id));
					}
					$seo_url = get_permalink($seo_post_id);
					if (!$seo_url) {
						throw new \Exception('Cannot resolve permalink for post ' . esc_html((string) $seo_post_id));
					}
				}

				

				
				$seo_parts  = wp_parse_url($seo_url);
				$home_parts = wp_parse_url(home_url());
				if (!$seo_parts || empty($seo_parts['host']) || empty($home_parts['host'])) {
					throw new \Exception('Invalid URL.');
				}
				if (strcasecmp($seo_parts['host'], $home_parts['host']) !== 0) {
					throw new \Exception('url must be on this site (same host as home_url). Cross-domain audits are not supported.');
				}

				$safe_path  = isset($seo_parts['path'])  ? $seo_parts['path']         : '/';
				$safe_query = isset($seo_parts['query']) ? '?' . $seo_parts['query']  : '';
				$seo_url    = rtrim(home_url(), '/') . $safe_path . $safe_query;
				$seo_response = wp_remote_get($seo_url, [
					'timeout'     => 10,
					'redirection' => 3,
					'user-agent'  => 'More MCP SEO Audit',
					'sslverify'   => true,
				]);
				if (is_wp_error($seo_response)) {
					throw new \Exception('Failed to fetch URL: ' . esc_html($seo_response->get_error_message()));
				}
				$seo_status = (int) wp_remote_retrieve_response_code($seo_response);
				$seo_html   = (string) wp_remote_retrieve_body($seo_response);
				if ($seo_status < 200 || $seo_status >= 300) {
					throw new \Exception('Non-2xx HTTP status ' . $seo_status . ' when fetching ' . esc_html($seo_url));
				}
				if ($seo_html === '') {
					throw new \Exception('Empty response body from ' . esc_html($seo_url));
				}

				$prev_libxml = libxml_use_internal_errors(true);
				$seo_dom     = new \DOMDocument();
				$seo_dom->loadHTML('<?xml encoding="UTF-8">' . $seo_html);
				libxml_clear_errors();
				libxml_use_internal_errors($prev_libxml);

				$title_nodes  = $seo_dom->getElementsByTagName('title');
				$title_first  = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : '';

				$meta_desc_values     = [];
				$viewport_content     = '';
				$og_fields            = ['title' => '', 'description' => '', 'image' => '', 'url' => '', 'type' => ''];
				$tw_fields            = ['card' => '', 'title' => '', 'description' => '', 'image' => ''];
				$meta_nodes           = $seo_dom->getElementsByTagName('meta');
				foreach ($meta_nodes as $m) {
					$name     = strtolower((string) $m->getAttribute('name'));
					$property = strtolower((string) $m->getAttribute('property'));
					$content  = (string) $m->getAttribute('content');
					if ($name === 'description') {
						$meta_desc_values[] = $content;
					} elseif ($name === 'viewport') {
						$viewport_content = $content;
					} elseif (str_starts_with($property, 'og:')) {
						$og_key = substr($property, 3);
						if (array_key_exists($og_key, $og_fields) && $og_fields[$og_key] === '') {
							$og_fields[$og_key] = $content;
						}
					} elseif (str_starts_with($name, 'twitter:')) {
						$tw_key = substr($name, 8);
						if (array_key_exists($tw_key, $tw_fields) && $tw_fields[$tw_key] === '') {
							$tw_fields[$tw_key] = $content;
						}
					}
				}

				$canonical_hrefs = [];
				$link_nodes      = $seo_dom->getElementsByTagName('link');
				foreach ($link_nodes as $l) {
					if (strtolower((string) $l->getAttribute('rel')) === 'canonical') {
						$canonical_hrefs[] = (string) $l->getAttribute('href');
					}
				}
				$canonical_first = $canonical_hrefs[0] ?? '';
				$canonical_norm  = $canonical_first !== '' ? untrailingslashit($canonical_first) : '';
				$requested_norm  = untrailingslashit($seo_url);

				return [
					'url'    => $seo_url,
					'status' => $seo_status,
					'title'  => [
						'value'      => $title_first,
						'length'     => strlen($title_first),
						'duplicates' => max(0, $title_nodes->length - 1),
					],
					'description' => [
						'value'      => $meta_desc_values[0] ?? '',
						'length'     => isset($meta_desc_values[0]) ? strlen($meta_desc_values[0]) : 0,
						'duplicates' => max(0, count($meta_desc_values) - 1),
					],
					'canonical' => [
						'value'      => $canonical_first,
						'duplicates' => max(0, count($canonical_hrefs) - 1),
						'is_self'    => $canonical_norm !== '' && strcasecmp($canonical_norm, $requested_norm) === 0,
					],
					'viewport' => [
						'present' => $viewport_content !== '',
						'content' => $viewport_content,
					],
					'og'      => $og_fields,
					'twitter' => $tw_fields,
				];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
