<?php
namespace More_MCP\API;

use More_MCP\API\Controllers\Media_Controller;
use More_MCP\API\Controllers\Pages_Controller;
use More_MCP\API\Controllers\Posts_Controller;
use More_MCP\API\Controllers\Site_Controller;
use More_MCP\API\Controllers\WooCommerce_Controller;

if (!defined('ABSPATH')) {
    exit;
}

class REST_Controller {

    private $namespace = 'more-mcp/v1';

    public function register_routes() {
        ( new Posts_Controller( $this ) )->register_routes();
        ( new Pages_Controller( $this ) )->register_routes();
        ( new Media_Controller( $this ) )->register_routes();
        ( new Site_Controller( $this ) )->register_routes();
        ( new WooCommerce_Controller( $this ) )->register_routes();
    }

    public function check_permission($request) {
        $api_key = $request->get_header('MMCP-Key');

        if (empty($api_key)) {
            return new \WP_Error(
                'missing_api_key',
                __('API key is required', 'more-mcp'),
                ['status' => 401]
            );
        }

        $settings = get_option('more_mcp_settings', []);

        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return new \WP_Error(
                'plugin_disabled',
                __('More MCP integration is disabled', 'more-mcp'),
                ['status' => 403]
            );
        }

        if (!isset($settings['api_key']) || $api_key !== $settings['api_key']) {
            $this->log_request($request, 'unauthorized', 'Invalid API key');
            return new \WP_Error(
                'invalid_api_key',
                __('Invalid API key', 'more-mcp'),
                ['status' => 403]
            );
        }

        return true;
    }

    
    public function get_posts($request) {
        $params = $request->get_params();

        $args = [
            'post_type' => 'post',
            'post_status' => $params['status'] ?? 'publish',
            'posts_per_page' => $params['per_page'] ?? 10,
            'paged' => $params['page'] ?? 1,
            'orderby' => $params['orderby'] ?? 'date',
            'order' => $params['order'] ?? 'DESC',
        ];

        if (isset($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        $query = new \WP_Query($args);

        $posts = array_map(function($post) {
            return $this->prepare_post_data($post);
        }, $query->posts);

        $this->log_request($request, 'success', 'Retrieved posts');

        return rest_ensure_response([
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    public function get_post($request) {
        $post_id = $request['id'];
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error(
                'post_not_found',
                __('Post not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $this->log_request($request, 'success', "Retrieved post {$post_id}");

        return rest_ensure_response($this->prepare_post_data($post));
    }

    public function create_post($request) {
        $params = $request->get_json_params();

        $post_data = [
            'post_title' => sanitize_text_field($params['title'] ?? ''),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_status' => sanitize_text_field($params['status'] ?? 'draft'),
            'post_type' => 'post',
            'post_author' => $params['author_id'] ?? get_current_user_id(),
        ];

        if (isset($params['excerpt'])) {
            
            $post_data['post_excerpt'] = wp_kses_post($params['excerpt']);
        }

        if (isset($params['categories'])) {
            $post_data['post_category'] = array_map('intval', (array) $params['categories']);
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->log_request($request, 'error', $post_id->get_error_message());
            return $post_id;
        }

        
        if (isset($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        
        if (isset($params['featured_media'])) {
            set_post_thumbnail($post_id, intval($params['featured_media']));
        }

        $this->log_request($request, 'success', "Created post {$post_id}");

        return rest_ensure_response([
            'id' => $post_id,
            'post' => $this->prepare_post_data(get_post($post_id)),
        ]);
    }

    public function update_post($request) {
        $post_id = $request['id'];
        $params = $request->get_json_params();

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error(
                'post_not_found',
                __('Post not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $post_data = ['ID' => $post_id];

        if (isset($params['title'])) {
            $post_data['post_title'] = sanitize_text_field($params['title']);
        }

        if (isset($params['content'])) {
            $post_data['post_content'] = wp_kses_post($params['content']);
        }

        if (isset($params['status'])) {
            $post_data['post_status'] = sanitize_text_field($params['status']);
        }

        if (isset($params['excerpt'])) {
            
            $post_data['post_excerpt'] = wp_kses_post($params['excerpt']);
        }

        if (isset($params['categories'])) {
            $post_data['post_category'] = array_map('intval', (array) $params['categories']);
        }

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            $this->log_request($request, 'error', $result->get_error_message());
            return $result;
        }

        if (isset($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }

        if (isset($params['featured_media'])) {
            set_post_thumbnail($post_id, intval($params['featured_media']));
        }

        $this->log_request($request, 'success', "Updated post {$post_id}");

        return rest_ensure_response([
            'id' => $post_id,
            'post' => $this->prepare_post_data(get_post($post_id)),
        ]);
    }

    public function delete_post($request) {
        $post_id = $request['id'];
        $force = $request->get_param('force') === 'true';

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error(
                'post_not_found',
                __('Post not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $result = wp_delete_post($post_id, $force);

        if (!$result) {
            $this->log_request($request, 'error', "Failed to delete post {$post_id}");
            return new \WP_Error(
                'delete_failed',
                __('Failed to delete post', 'more-mcp'),
                ['status' => 500]
            );
        }

        $this->log_request($request, 'success', "Deleted post {$post_id}");

        return rest_ensure_response(['success' => true, 'id' => $post_id]);
    }

    
    public function get_pages($request) {
        $params = $request->get_params();

        $args = [
            'post_type' => 'page',
            'post_status' => $params['status'] ?? 'publish',
            'posts_per_page' => $params['per_page'] ?? 10,
            'paged' => $params['page'] ?? 1,
            'orderby' => $params['orderby'] ?? 'date',
            'order' => $params['order'] ?? 'DESC',
        ];

        $query = new \WP_Query($args);

        $pages = array_map(function($post) {
            return $this->prepare_post_data($post);
        }, $query->posts);

        $this->log_request($request, 'success', 'Retrieved pages');

        return rest_ensure_response([
            'pages' => $pages,
            'total' => $query->found_posts,
            'pages_count' => $query->max_num_pages,
        ]);
    }

    public function get_page($request) {
        $post_id = $request['id'];
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'page') {
            return new \WP_Error(
                'page_not_found',
                __('Page not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $this->log_request($request, 'success', "Retrieved page {$post_id}");

        return rest_ensure_response($this->prepare_post_data($post));
    }

    public function create_page($request) {
        $params = $request->get_json_params();

        $post_data = [
            'post_title' => sanitize_text_field($params['title'] ?? ''),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_status' => sanitize_text_field($params['status'] ?? 'draft'),
            'post_type' => 'page',
            'post_author' => $params['author_id'] ?? get_current_user_id(),
        ];

        if (isset($params['parent_id'])) {
            $post_data['post_parent'] = intval($params['parent_id']);
        }

        if (isset($params['template'])) {
            $post_data['page_template'] = sanitize_text_field($params['template']);
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->log_request($request, 'error', $post_id->get_error_message());
            return $post_id;
        }

        if (isset($params['featured_media'])) {
            set_post_thumbnail($post_id, intval($params['featured_media']));
        }

        $this->log_request($request, 'success', "Created page {$post_id}");

        return rest_ensure_response([
            'id' => $post_id,
            'page' => $this->prepare_post_data(get_post($post_id)),
        ]);
    }

    public function update_page($request) {
        $post_id = $request['id'];
        $params = $request->get_json_params();

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') {
            return new \WP_Error(
                'page_not_found',
                __('Page not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $post_data = ['ID' => $post_id];

        if (isset($params['title'])) {
            $post_data['post_title'] = sanitize_text_field($params['title']);
        }

        if (isset($params['content'])) {
            $post_data['post_content'] = wp_kses_post($params['content']);
        }

        if (isset($params['status'])) {
            $post_data['post_status'] = sanitize_text_field($params['status']);
        }

        if (isset($params['parent_id'])) {
            $post_data['post_parent'] = intval($params['parent_id']);
        }

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            $this->log_request($request, 'error', $result->get_error_message());
            return $result;
        }

        if (isset($params['featured_media'])) {
            set_post_thumbnail($post_id, intval($params['featured_media']));
        }

        $this->log_request($request, 'success', "Updated page {$post_id}");

        return rest_ensure_response([
            'id' => $post_id,
            'page' => $this->prepare_post_data(get_post($post_id)),
        ]);
    }

    public function delete_page($request) {
        $post_id = $request['id'];
        $force = $request->get_param('force') === 'true';

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') {
            return new \WP_Error(
                'page_not_found',
                __('Page not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $result = wp_delete_post($post_id, $force);

        if (!$result) {
            $this->log_request($request, 'error', "Failed to delete page {$post_id}");
            return new \WP_Error(
                'delete_failed',
                __('Failed to delete page', 'more-mcp'),
                ['status' => 500]
            );
        }

        $this->log_request($request, 'success', "Deleted page {$post_id}");

        return rest_ensure_response(['success' => true, 'id' => $post_id]);
    }

    
    public function get_media($request) {
        $params = $request->get_params();

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $params['per_page'] ?? 10,
            'paged' => $params['page'] ?? 1,
        ];

        if (isset($params['mime_type'])) {
            $args['post_mime_type'] = sanitize_text_field($params['mime_type']);
        }

        $query = new \WP_Query($args);

        $media = array_map(function($post) {
            return $this->prepare_media_data($post);
        }, $query->posts);

        $this->log_request($request, 'success', 'Retrieved media');

        return rest_ensure_response([
            'media' => $media,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    public function get_media_item($request) {
        $media_id = $request['id'];
        $media = get_post($media_id);

        if (!$media || $media->post_type !== 'attachment') {
            return new \WP_Error(
                'media_not_found',
                __('Media not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $this->log_request($request, 'success', "Retrieved media {$media_id}");

        return rest_ensure_response($this->prepare_media_data($media));
    }

    public function upload_media($request) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new \WP_Error(
                'no_file',
                __('No file was uploaded', 'more-mcp'),
                ['status' => 400]
            );
        }

        $file = $files['file'];

        
        $allowed_types = get_allowed_mime_types();
        $filetype = wp_check_filetype($file['name']);

        if (!in_array($filetype['type'], $allowed_types)) {
            return new \WP_Error(
                'invalid_file_type',
                __('Invalid file type', 'more-mcp'),
                ['status' => 400]
            );
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            $this->log_request($request, 'error', $upload['error']);
            return new \WP_Error(
                'upload_failed',
                $upload['error'],
                ['status' => 500]
            );
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            $this->log_request($request, 'error', $attachment_id->get_error_message());
            return $attachment_id;
        }

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        $this->log_request($request, 'success', "Uploaded media {$attachment_id}");

        return rest_ensure_response([
            'id' => $attachment_id,
            'media' => $this->prepare_media_data(get_post($attachment_id)),
        ]);
    }

    public function delete_media($request) {
        $media_id = $request['id'];
        $force = $request->get_param('force') === 'true';

        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            return new \WP_Error(
                'media_not_found',
                __('Media not found', 'more-mcp'),
                ['status' => 404]
            );
        }

        $result = wp_delete_attachment($media_id, $force);

        if (!$result) {
            $this->log_request($request, 'error', "Failed to delete media {$media_id}");
            return new \WP_Error(
                'delete_failed',
                __('Failed to delete media', 'more-mcp'),
                ['status' => 500]
            );
        }

        $this->log_request($request, 'success', "Deleted media {$media_id}");

        return rest_ensure_response(['success' => true, 'id' => $media_id]);
    }

    
    public function get_site_info($request) {
        $this->log_request($request, 'success', 'Retrieved site info');

        return rest_ensure_response([
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => get_bloginfo('url'),
            'admin_email' => get_bloginfo('admin_email'),
            'language' => get_bloginfo('language'),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'posts_per_page' => get_option('posts_per_page'),
            'wordpress_version' => get_bloginfo('version'),
        ]);
    }

    
    public function search_content($request) {
        $params = $request->get_params();
        $search_query = sanitize_text_field($params['query'] ?? '');

        if (empty($search_query)) {
            return new \WP_Error(
                'empty_query',
                __('Search query is required', 'more-mcp'),
                ['status' => 400]
            );
        }

        $args = [
            'post_type' => $params['type'] ?? ['post', 'page'],
            'post_status' => 'publish',
            's' => $search_query,
            'posts_per_page' => $params['per_page'] ?? 10,
        ];

        $query = new \WP_Query($args);

        $results = array_map(function($post) {
            return $this->prepare_post_data($post);
        }, $query->posts);

        $this->log_request($request, 'success', "Searched for: {$search_query}");

        return rest_ensure_response([
            'results' => $results,
            'total' => $query->found_posts,
            'query' => $search_query,
        ]);
    }

    
    public function get_product_variations($request) {
        $product_id = intval($request['id']);
        $params = $request->get_params();

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_get_product_variations', [
                'product_id' => $product_id,
                'per_page'   => $params['per_page'] ?? 100,
            ]);
            $this->log_request($request, 'success', "Retrieved variations for product {$product_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function create_variation($request) {
        $product_id = intval($request['id']);
        $params = $request->get_json_params() ?? [];
        $params['product_id'] = $product_id;

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_create_variation', $params);
            $this->log_request($request, 'success', "Created variation for product {$product_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function get_variation($request) {
        $product_id   = intval($request['id']);
        $variation_id = intval($request['variation_id']);

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_get_variation', [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
            ]);
            $this->log_request($request, 'success', "Retrieved variation {$variation_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 404]);
        }
    }

    public function update_variation($request) {
        $product_id   = intval($request['id']);
        $variation_id = intval($request['variation_id']);
        $params = $request->get_json_params() ?? [];
        $params['product_id']   = $product_id;
        $params['variation_id'] = $variation_id;

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_update_variation', $params);
            $this->log_request($request, 'success', "Updated variation {$variation_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function delete_variation($request) {
        $product_id   = intval($request['id']);
        $variation_id = intval($request['variation_id']);
        $force = $request->get_param('force') !== 'false';

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_delete_variation', [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'force'        => $force,
            ]);
            $this->log_request($request, 'success', "Deleted variation {$variation_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    
    public function get_product_attributes($request) {
        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_get_product_attributes', []);
            $this->log_request($request, 'success', 'Retrieved product attributes');
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function create_product_attribute($request) {
        $params = $request->get_json_params() ?? [];

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_create_product_attribute', $params);
            $this->log_request($request, 'success', 'Created product attribute');
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function get_attribute_terms($request) {
        $attribute_id = intval($request['attribute_id']);
        $params = $request->get_params();

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_get_attribute_terms', [
                'attribute_id' => $attribute_id,
                'hide_empty'   => ($params['hide_empty'] ?? '') === 'true',
            ]);
            $this->log_request($request, 'success', "Retrieved terms for attribute {$attribute_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function set_product_attributes($request) {
        $product_id = intval($request['id']);
        $params = $request->get_json_params() ?? [];
        $params['product_id'] = $product_id;

        try {
            $result = \More_MCP\Integrations\WooCommerce::execute_tool('wc_set_product_attributes', $params);
            $this->log_request($request, 'success', "Set attributes for product {$product_id}");
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            $this->log_request($request, 'error', $e->getMessage());
            return new \WP_Error('wc_error', $e->getMessage(), ['status' => 400]);
        }
    }

    
    private function prepare_post_data($post) {
        $author = get_userdata($post->post_author);

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'slug' => $post->post_name,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'author' => [
                'id' => $post->post_author,
                'name' => $author ? $author->display_name : '',
            ],
            'featured_media' => get_post_thumbnail_id($post->ID),
            'categories' => wp_get_post_categories($post->ID),
            'tags' => wp_get_post_tags($post->ID, ['fields' => 'names']),
            'permalink' => get_permalink($post->ID),
        ];
    }

    private function prepare_media_data($media) {
        $metadata = wp_get_attachment_metadata($media->ID);

        return [
            'id' => $media->ID,
            'title' => $media->post_title,
            'description' => $media->post_content,
            'caption' => $media->post_excerpt,
            'alt_text' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
            'mime_type' => $media->post_mime_type,
            'url' => wp_get_attachment_url($media->ID),
            'date' => $media->post_date,
            'modified' => $media->post_modified,
            'sizes' => $metadata['sizes'] ?? [],
            'width' => $metadata['width'] ?? null,
            'height' => $metadata['height'] ?? null,
        ];
    }

    private function log_request($request, $status, $message = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'more_mcp_logs';

        $wpdb->insert(
            $table_name,
            [
                'mcp_server' => 'internal',
                'action' => $request->get_route(),
                'request_data' => json_encode([
                    'method' => $request->get_method(),
                    'params' => $request->get_params(),
                ]),
                'response_data' => $message,
                'status' => $status,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
}
