<?php

namespace Mordenhost_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Options implements Handler {

	const READABLE_OPTIONS = ['blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'posts_per_page', 'date_format', 'time_format', 'timezone_string', 'googlesitekit_analytics-4_settings', 'wpseo_taxonomy_meta', 'show_on_front', 'page_on_front', 'page_for_posts', 'WPLANG', 'permalink_structure', 'category_base', 'tag_base', 'sticky_posts', 'elementor_pro_theme_builder_conditions', 'elementor_css_print_method'];

	const READABLE_PREFIX = 'elementor_experiment-';

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_option', 'description' => 'Get a single WordPress option value. Only an allowlist is readable: site identity and reading settings, the front-page options, a few plugin configs, and the Elementor options that decide what renders (elementor_pro_theme_builder_conditions, elementor_css_print_method, elementor_experiment-*). A refused name gets the full list back. For all of a plugin\'s settings use wp_get_plugin_settings. Sensitive keys are redacted.', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
			['name' => 'wp_get_plugin_settings', 'description' => 'Get all options stored by a plugin, looked up by slug. Sensitive keys (api keys, secrets, tokens, passwords) are redacted before return.', 'inputSchema' => ['type' => 'object', 'properties' => ['plugin_slug' => ['type' => 'string', 'description' => 'Plugin slug, e.g. woocommerce or rank-math']], 'required' => ['plugin_slug']]],
			['name' => 'wp_update_option', 'description' => 'Update a WordPress option. Requires the "Site options" switch (Mordenhost MCP > Access) to be on, and the option name must be in the allowlist (extend via the mordenhost_mcp_writable_options filter). Sensitive option names are permanently denylisted. Some settings have a dedicated, validated tool instead: front page and posts page (wp_set_front_page), permalink structure and category/tag base (wp_update_permalink_structure), site language, timezone and the other general settings (wp_update_site_settings), sticky posts (wp_update_post with sticky).', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'value' => ['description' => 'New value (any JSON type). Full overwrite: read first, merge in your client, then write back.']], 'required' => ['name', 'value']]],
			['name' => 'wp_set_front_page', 'description' => 'Set the site\'s front page (Settings → Reading). Pass page_id to serve a static page at "/" (sets show_on_front="page" + page_on_front=page_id atomically); the page must exist and be published. Optionally pass posts_page_id to set the blog/posts page; pass posts_page_id alone to change only the posts page (0 clears it). Pass show_posts=true instead to revert to the classic latest-posts front (show_on_front="posts"). This is the atomic, validated path for "make this page the homepage" — the front-page options are cross-validated (a page ID must resolve to a published page) rather than written blind. Requires manage_options.', 'inputSchema' => ['type' => 'object', 'properties' => ['page_id' => ['type' => 'integer', 'description' => 'ID of a published page to serve as the static front page. Sets show_on_front="page" and page_on_front. Mutually exclusive with show_posts.'], 'posts_page_id' => ['type' => 'integer', 'description' => 'Optional ID of a published page to use as the posts/blog page (page_for_posts). Must differ from the front page (page_id, or the current page_on_front when page_id is omitted). 0 clears the posts page.'], 'show_posts' => ['type' => 'boolean', 'description' => 'Set true to revert to the latest-posts front page (show_on_front="posts"). Mutually exclusive with page_id.']]]],
		];
	}

	public static function supports( string $name ): bool {
		return 'wp_get_option' === $name || 'wp_get_plugin_settings' === $name || 'wp_update_option' === $name || 'wp_set_front_page' === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_option':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read site options.');
				}

				

				

				

				

				

				

				

				$name = isset($args['name']) && is_scalar($args['name']) ? sanitize_text_field((string) $args['name']) : '';
				if ('' === $name) {
					throw new \Exception('name is required: pass the option name, e.g. {"name": "blogname"}.');
				}
				if (!self::is_readable_option($name)) {
					throw new \Exception(self::read_refusal($name)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- read_refusal() escapes the option name.
				}
				return ['name' => $name, 'value' => Options_Support::redact_sensitive_keys(get_option($name))];

			case 'wp_get_plugin_settings':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to read plugin settings.');
				}
				$slug = sanitize_text_field($args['plugin_slug'] ?? '');
				if (empty($slug)) throw new \Exception('plugin_slug is required.');
				return [
					'slug'    => $slug,
					'options' => Options_Support::find_plugin_options($slug),
				];

			case 'wp_update_option':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to write site options.');
				}
				$name = sanitize_text_field($args['name'] ?? '');
				if (empty($name)) throw new \Exception('Option name is required.');

				$rmcp_settings = get_option('mordenhost_mcp_settings', []);
				if (empty($rmcp_settings['allow_option_writes'])) {
					throw new \Exception('Option writes are disabled. The site owner can turn on the "Site options" switch under Mordenhost MCP > Access.');
				}

				if (Options_Support::is_denylisted_option($name)) {
					throw new \Exception('Option is permanently denylisted: ' . esc_html($name) . '.' . Options_Support::dedicated_tool_hint($name, true));
				}

				$default_writable = ['blogname', 'blogdescription', 'posts_per_page', 'date_format', 'time_format'];
				$writable = apply_filters('mordenhost_mcp_writable_options', $default_writable);
				if (!is_array($writable)) $writable = $default_writable;
				if (!in_array($name, $writable, true)) {
					$hint = Options_Support::dedicated_tool_hint($name, true);
					throw new \Exception('Option not in allowlist: ' . esc_html($name) . '.' . ('' !== $hint ? $hint : ' Plugin authors can opt their settings in via add_filter("mordenhost_mcp_writable_options", ...).'));
				}

				$value = $args['value'] ?? null;
				$previous = get_option($name);
				$result = update_option($name, $value);
				return [
					'name'     => $name,
					'updated'  => (bool) $result,
					'previous' => Options_Support::redact_sensitive_keys($previous),
				];

			case 'wp_set_front_page':
				if (!current_user_can('manage_options')) {
					throw new \Exception('You do not have permission to set the front page.');
				}
				$want_posts     = ! empty( $args['show_posts'] );
				$has_page       = isset( $args['page_id'] );
				$has_posts_page = isset( $args['posts_page_id'] );
				if ( $want_posts && $has_page ) {
					throw new \Exception('Pass either page_id (static front page) or show_posts=true (latest-posts front page), not both.');
				}
				if ( ! $want_posts && ! $has_page && ! $has_posts_page ) {
					throw new \Exception('Provide page_id to set a static front page, show_posts=true to use the latest-posts front page, or posts_page_id alone to change only the posts page.');
				}

				

				if ( $want_posts ) {
					$prev = get_option('show_on_front');
					update_option('show_on_front', 'posts');
					return [
						'success'       => true,
						'show_on_front' => 'posts',
						'previous_show_on_front' => $prev,
					];
				}

				

				$page_id = null;
				if ( $has_page ) {
					$page_id = (int) $args['page_id'];
					$front   = get_post( $page_id );
					if ( ! $front || 'page' !== $front->post_type ) {
						throw new \Exception('page_id ' . esc_html((string) $page_id) . ' is not a page. Pass the ID of an existing page.');
					}
					if ( 'publish' !== $front->post_status ) {
						throw new \Exception('page_id ' . esc_html((string) $page_id) . ' is not published (status: ' . esc_html($front->post_status) . '). Publish it before making it the front page.');
					}
				}

				
				$posts_page_id = null;
				if ( $has_posts_page ) {
					$posts_page_id = (int) $args['posts_page_id'];
					$front_id      = null !== $page_id ? $page_id : (int) get_option('page_on_front');
					if ( 0 !== $posts_page_id ) { 
						if ( $posts_page_id === $front_id ) {
							throw new \Exception( null !== $page_id
								? 'posts_page_id must differ from page_id — the front page and the posts page cannot be the same page.'
								: 'posts_page_id must differ from the current front page (page_on_front = ' . esc_html((string) $front_id) . ') — the front page and the posts page cannot be the same page.' );
						}
						$posts_page = get_post( $posts_page_id );
						if ( ! $posts_page || 'page' !== $posts_page->post_type ) {
							throw new \Exception('posts_page_id ' . esc_html((string) $posts_page_id) . ' is not a page.');
						}
						if ( 'publish' !== $posts_page->post_status ) {
							throw new \Exception('posts_page_id ' . esc_html((string) $posts_page_id) . ' is not published.');
						}
					}
				} elseif ( null !== $page_id && $page_id === (int) get_option('page_for_posts') ) {
					throw new \Exception('page_id ' . esc_html((string) $page_id) . ' is the current posts page (page_for_posts) — the front page and the posts page cannot be the same page. Pass posts_page_id with another page, or posts_page_id=0 to clear it.');
				}

				$prev = [
					'show_on_front' => get_option('show_on_front'),
					'page_on_front' => (int) get_option('page_on_front'),
					'page_for_posts' => (int) get_option('page_for_posts'),
				];
				if ( null !== $page_id ) {
					update_option('show_on_front', 'page');
					update_option('page_on_front', $page_id);
				}
				if ( null !== $posts_page_id ) {
					update_option('page_for_posts', $posts_page_id);
				}

				$result = [
					'success'       => true,
					'show_on_front' => null !== $page_id ? 'page' : (string) get_option('show_on_front'),
					'page_on_front' => null !== $page_id ? $page_id : (int) get_option('page_on_front'),
					'previous'      => $prev,
				];
				if ( null !== $posts_page_id ) {
					$result['page_for_posts'] = $posts_page_id;
				}
				if ( null === $page_id && $posts_page_id && 'page' !== $result['show_on_front'] ) {
					$result['note'] = 'The front page shows the latest posts (show_on_front="posts"), so WordPress uses the posts page only once a static front page is set with page_id.';
				}
				return $result;
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}

	private static function is_readable_option( string $name ): bool {
		if (Options_Support::is_sensitive_key($name)) {
			return false;
		}
		if (in_array($name, self::READABLE_OPTIONS, true)) {
			return true;
		}
		return 1 === preg_match('/^' . preg_quote(self::READABLE_PREFIX, '/') . '[a-z0-9_\-]+$/', $name);
	}

	private static function read_refusal( string $name ): string {
		$message = 'Option not allowed: ' . esc_html($name) . '.' . Options_Support::dedicated_tool_hint($name, false);
		if (Options_Support::is_sensitive_key($name)) {
			return $message . ' Its name marks it as a credential (a key, token, secret or password), so it is deliberately not readable over MCP.';
		}
		return $message . ' wp_get_option reads only: ' . implode(', ', self::READABLE_OPTIONS) . ', ' . self::READABLE_PREFIX . '*.'
			. ' For general site settings use wp_get_site_settings. For a plugin\'s settings use wp_get_plugin_settings with the plugin slug (e.g. "elementor" or "elementor-pro"); it returns every option under that prefix with credentials redacted.';
	}
}
