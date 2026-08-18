<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Terms implements Handler {

	public static function get_tools(): array {
		return [
			['name' => 'wp_get_categories', 'description' => 'List blog categories (the `category` taxonomy). Returns id, name, slug, count, and parent for each. For custom taxonomies (product_cat, brand, etc.), use wp_get_terms instead.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of categories (default 100, max 100)']]]],
			['name' => 'wp_get_tags', 'description' => 'List blog tags (the `post_tag` taxonomy). Returns id, name, slug, and count for each. For custom taxonomies, use wp_get_terms.', 'inputSchema' => ['type' => 'object', 'properties' => ['per_page' => ['type' => 'integer', 'description' => 'Number of tags (default 100, max 100)']]]],
			['name' => 'wp_create_term', 'description' => 'Create a term in any registered taxonomy (category, post_tag, or any custom taxonomy). Description may contain inline HTML — WordPress permits <a>, <strong>, <em>, <blockquote>, <code>, <cite>, <abbr>, <acronym> in term descriptions; block-level tags (<p>, <h1>-<h6>, <ul>) are stripped by WP core. Use wp_get_taxonomies to discover available taxonomy slugs.', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat)'], 'description' => ['type' => 'string', 'description' => 'Optional description. May contain inline HTML (<a>, <strong>, <em>, etc.); block-level tags are stripped by WP core.'], 'parent' => ['type' => 'integer', 'description' => 'Parent term ID (only applies to hierarchical taxonomies)'], 'slug' => ['type' => 'string', 'description' => 'Optional URL-friendly slug. Auto-generated from name if omitted.']], 'required' => ['name', 'taxonomy']]],
			['name' => 'wp_update_term', 'description' => 'Update an existing term in any taxonomy. Use this to rename a tag/category, edit its description, or change its slug. Description may contain inline HTML (WP core strips block-level tags). Pair with wp_update_term_meta to edit SEO meta on tags (Yoast/Rank Math/AIOSEO store tag SEO data in wp_termmeta).', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'description' => ['type' => 'string', 'description' => 'Optional description. May contain inline HTML.'], 'parent' => ['type' => 'integer', 'description' => 'Parent term ID (hierarchical taxonomies only)']], 'required' => ['id', 'taxonomy']]],
			['name' => 'wp_delete_term', 'description' => 'Delete a term from any registered taxonomy.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug the term belongs to']], 'required' => ['id', 'taxonomy']]],
			['name' => 'wp_add_post_terms', 'description' => 'Add or replace terms on a post in any taxonomy.', 'inputSchema' => ['type' => 'object', 'properties' => ['post_id' => ['type' => 'integer'], 'terms' => ['type' => 'array', 'items' => ['type' => 'integer']], 'taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat)']], 'required' => ['post_id', 'terms', 'taxonomy']]],
			['name' => 'wp_get_terms', 'description' => 'List terms in any registered taxonomy with paginated output. Returns id, name, slug, description, count, parent. Use to map term names to IDs before wp_add_post_terms, or to walk a taxonomy tree.', 'inputSchema' => ['type' => 'object', 'properties' => ['taxonomy' => ['type' => 'string', 'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat, any custom taxonomy)'], 'search' => ['type' => 'string', 'description' => 'Optional name-substring filter (case-insensitive).'], 'hide_empty' => ['type' => 'boolean', 'description' => 'Exclude terms with zero attached posts. Default false.'], 'parent' => ['type' => 'integer', 'description' => 'Return only children of this parent term ID (hierarchical taxonomies).'], 'per_page' => ['type' => 'integer', 'description' => 'Results per page. Default 100, max 500.'], 'page' => ['type' => 'integer', 'description' => 'Page number, 1-indexed. Default 1.']], 'required' => ['taxonomy']]],
			['name' => 'wp_count_terms', 'description' => 'Get term counts in a taxonomy', 'inputSchema' => ['type' => 'object', 'properties' => ['taxonomy' => ['type' => 'string']]]],
			['name' => 'wp_get_taxonomies', 'description' => 'Get all registered public taxonomies (built-in plus custom taxonomies registered by themes/plugins like product_cat, brand, etc.). Returns the taxonomy slug, label, hierarchical flag, and which post types it applies to.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
		];
	}

	public static function supports( string $name ): bool {
		static $names = [
			'wp_get_categories', 'wp_get_tags', 'wp_create_term', 'wp_update_term', 'wp_delete_term',
			'wp_add_post_terms', 'wp_get_terms', 'wp_count_terms', 'wp_get_taxonomies',
		];
		return in_array( $name, $names, true );
	}

	public static function execute_tool( string $name, array $args ) {
		switch ( $name ) {
			case 'wp_get_taxonomies':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list taxonomies.');
				}
				$taxonomies = get_taxonomies(['public' => true], 'objects');
				return array_values(array_map(function($tax) {

					

					
					return [
						'slug'         => $tax->name,
						'name'         => $tax->name,
						'label'        => $tax->label,
						'description'  => $tax->description,
						'hierarchical' => (bool) $tax->hierarchical,
						'object_type'  => array_values((array) $tax->object_type),
						'show_in_rest' => (bool) $tax->show_in_rest,
					];
				}, $taxonomies));

			case 'wp_get_categories':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list categories.');
				}
				$cats = get_categories(['number' => min(intval($args['per_page'] ?? 100), 100), 'hide_empty' => false]);
				return array_map(function($c) {
					return ['id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => $c->count, 'parent' => $c->parent];
				}, $cats);

			case 'wp_get_tags':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to list tags.');
				}
				$tags = get_tags(['number' => min(intval($args['per_page'] ?? 100), 100), 'hide_empty' => false]);
				return array_map(function($t) {
					return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count];
				}, $tags ?: []);

			case 'wp_create_term':
				$taxonomy = sanitize_text_field($args['taxonomy']);
				if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
				$tax_obj = get_taxonomy($taxonomy);

				
				$edit_terms_cap = $tax_obj && !empty($tax_obj->cap->edit_terms) ? $tax_obj->cap->edit_terms : 'manage_categories';
				if (!current_user_can($edit_terms_cap)) {
					throw new \Exception('You do not have permission to create terms in ' . esc_html($taxonomy) . '.');
				}
				$term_args = [];

				
				if (!empty($args['description'])) $term_args['description'] = wp_kses_post($args['description']);
				if (!empty($args['slug'])) $term_args['slug'] = sanitize_title($args['slug']);
				if (!empty($args['parent']) && $tax_obj && $tax_obj->hierarchical) $term_args['parent'] = intval($args['parent']);
				$result = wp_insert_term(sanitize_text_field($args['name']), $taxonomy, $term_args);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return ['id' => $result['term_id'], 'taxonomy' => $taxonomy, 'message' => 'Term created successfully'];

			case 'wp_update_term':
				$taxonomy = sanitize_text_field($args['taxonomy']);
				if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
				$term_id = intval($args['id']);
				if (!get_term($term_id, $taxonomy)) throw new \Exception('Term not found in taxonomy ' . esc_html($taxonomy));
				
				if (!current_user_can('edit_term', $term_id)) {
					throw new \Exception('You do not have permission to edit this term.');
				}
				$update_args = [];
				
				if (isset($args['name']) && $args['name'] !== '') $update_args['name'] = sanitize_text_field($args['name']);
				if (isset($args['slug']) && $args['slug'] !== '') $update_args['slug'] = sanitize_title($args['slug']);
				
				if (isset($args['description']) && $args['description'] !== '') $update_args['description'] = wp_kses_post($args['description']);
				if (isset($args['parent'])) {
					$tax_obj = get_taxonomy($taxonomy);
					if ($tax_obj && $tax_obj->hierarchical) $update_args['parent'] = intval($args['parent']);
				}
				if (empty($update_args)) throw new \Exception('No update fields provided. Pass at least one of: name, slug, description, parent.');
				$result = wp_update_term($term_id, $taxonomy, $update_args);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return ['id' => $term_id, 'taxonomy' => $taxonomy, 'message' => 'Term updated successfully'];

			case 'wp_delete_term':
				$taxonomy = sanitize_text_field($args['taxonomy']);
				if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
				$term_id = intval($args['id']);
				if (!get_term($term_id, $taxonomy)) throw new \Exception('Term not found in taxonomy ' . esc_html($taxonomy));
				
				if (!current_user_can('delete_term', $term_id)) {
					throw new \Exception('You do not have permission to delete this term.');
				}
				$result = wp_delete_term($term_id, $taxonomy);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				if (!$result) throw new \Exception('Failed to delete term');
				return ['message' => 'Term deleted successfully'];

			case 'wp_get_terms':
				if (!current_user_can('edit_posts')) {
					throw new \Exception('You do not have permission to list terms.');
				}
				$taxonomy = sanitize_text_field($args['taxonomy'] ?? '');
				if ($taxonomy === '') throw new \Exception('A taxonomy slug is required.');
				if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
				$per_page = isset($args['per_page']) ? max(1, min(intval($args['per_page']), 500)) : 100;
				$page     = isset($args['page']) ? max(1, intval($args['page'])) : 1;
				$offset   = ($page - 1) * $per_page;
				$get_args = [
					'taxonomy'   => $taxonomy,
					'hide_empty' => !empty($args['hide_empty']),
					'number'     => $per_page,
					'offset'     => $offset,
					'orderby'    => 'name',
					'order'      => 'ASC',
				];
				if (!empty($args['search'])) {
					$get_args['search'] = sanitize_text_field($args['search']);
				}
				if (isset($args['parent'])) {
					$get_args['parent'] = intval($args['parent']);
				}
				$terms = get_terms($get_args);
				if (is_wp_error($terms)) throw new \Exception(esc_html($terms->get_error_message()));
				$total = (int) wp_count_terms([
					'taxonomy'   => $taxonomy,
					'hide_empty' => !empty($args['hide_empty']),
				]);
				return [
					'taxonomy'    => $taxonomy,
					'page'        => $page,
					'per_page'    => $per_page,

					
					'total'       => $total,
					'total_count' => $total,
					'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
					'terms'       => array_map(function ($t) {
						return [
							'id'          => (int) $t->term_id,
							'name'        => $t->name,
							'slug'        => $t->slug,
							'description' => $t->description,
							'count'       => (int) $t->count,
							'parent'      => (int) $t->parent,
						];
					}, $terms),
				];

			case 'wp_add_post_terms':
				$taxonomy = sanitize_text_field($args['taxonomy']);
				if (!taxonomy_exists($taxonomy)) throw new \Exception('Unknown taxonomy: ' . esc_html($taxonomy) . '. Use wp_get_taxonomies to list available taxonomies.');
				
				$post_id = intval($args['post_id'] ?? $args['id'] ?? 0);
				if ($post_id <= 0 || !get_post($post_id)) throw new \Exception('Post not found.');

				if (!current_user_can('edit_post', $post_id)) {
					throw new \Exception('You do not have permission to edit this post.');
				}
				
				$tax_obj = get_taxonomy($taxonomy);
				$assign_cap = $tax_obj && !empty($tax_obj->cap->assign_terms) ? $tax_obj->cap->assign_terms : 'edit_posts';
				if (!current_user_can($assign_cap)) {
					throw new \Exception('You do not have permission to assign terms in ' . esc_html($taxonomy) . '.');
				}
				$result = wp_set_post_terms($post_id, array_map('intval', $args['terms']), $taxonomy, true);
				if (is_wp_error($result)) throw new \Exception(esc_html($result->get_error_message()));
				return ['message' => 'Terms added to post successfully'];

			case 'wp_count_terms':
				if (!current_user_can('read')) {
					throw new \Exception('You do not have permission to count terms.');
				}
				$taxonomy = sanitize_text_field($args['taxonomy'] ?? 'category');
				$count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
				return ['taxonomy' => $taxonomy, 'count' => $count];
		}

		throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
	}
}
