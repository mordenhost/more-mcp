<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Undo_Dispatcher {

    public static function dispatch( array $args ) {
        $undo_token = isset($args['token']) ? sanitize_text_field((string) $args['token']) : '';
        if ($undo_token === '') {
            throw new \Exception('token is required.');
        }
        $undo_snapshot = \More_MCP\MCP\Undo_Store::read($undo_token);
        if (!$undo_snapshot) {

            throw new \Exception('Undo token not found, expired, or already consumed.');
        }
        $undo_op = $undo_snapshot['op'] ?? '';

        switch ($undo_op) {
            case 'blocks_mutation':

                

                $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                    ? $undo_snapshot['target']
                    : [];
                $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                if ($undo_post_id <= 0) {
                    throw new \Exception('Undo snapshot has no target post.');
                }
                if (!current_user_can('edit_post', $undo_post_id)) {
                    throw new \Exception('You do not have permission to edit this post, so this block change cannot be undone.');
                }
                $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('post_content', $undo_pre_op)) {
                    throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                }
                if (!get_post($undo_post_id)) {
                    throw new \Exception('The target post no longer exists.');
                }
                $undo_restored = wp_update_post([
                    'ID'           => $undo_post_id,
                    'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                ], true);
                if (is_wp_error($undo_restored)) {
                    throw new \Exception('Failed to restore post content: ' . esc_html($undo_restored->get_error_message()));
                }
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return [
                    'success' => true,
                    'op'      => 'blocks_mutation',
                    'post_id' => $undo_post_id,
                    'message' => 'Post content restored to its pre-operation state.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];

            case 'divi_content_write':

                
                $undo_divi_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                    ? $undo_snapshot['target']
                    : [];
                $undo_divi_post_id = isset($undo_divi_target['post_id']) ? (int) $undo_divi_target['post_id'] : 0;
                if ($undo_divi_post_id <= 0) {
                    throw new \Exception('Undo snapshot has no target post.');
                }
                if (!current_user_can('edit_post', $undo_divi_post_id)) {
                    throw new \Exception('You do not have permission to edit this post, so this Divi change cannot be undone.');
                }
                $undo_divi_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('post_content', $undo_divi_pre_op)) {
                    throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                }
                if (!get_post($undo_divi_post_id)) {
                    throw new \Exception('The target post no longer exists.');
                }
                if (!class_exists('\More_MCP\Integrations\Divi')) {
                    throw new \Exception('The Divi integration is unavailable, so its cache-safe undo cannot run.');
                }
                $undo_divi_restored = wp_update_post([
                    'ID'           => $undo_divi_post_id,
                    'post_content' => wp_slash((string) $undo_divi_pre_op['post_content']),
                ], true);
                if (is_wp_error($undo_divi_restored)) {
                    throw new \Exception('Failed to restore Divi post content: ' . esc_html($undo_divi_restored->get_error_message()));
                }
                $undo_divi_fresh    = get_post($undo_divi_post_id);
                $undo_divi_verified = $undo_divi_fresh && (string) $undo_divi_fresh->post_content === (string) $undo_divi_pre_op['post_content'];
                $undo_divi_inval    = \More_MCP\Integrations\Divi::invalidate_derived_state_public($undo_divi_post_id);
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return [
                    'success'            => true,
                    'op'                 => 'divi_content_write',
                    'post_id'            => $undo_divi_post_id,
                    'verified'           => $undo_divi_verified,
                    'message'            => 'Divi post content restored to its pre-operation state.',
                    'restored_summary'   => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                    'cache_invalidation' => $undo_divi_inval,
                ];

            case 'elementor_element_write':

                

                

                
                
                $undo_el_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                    ? $undo_snapshot['target']
                    : [];
                $undo_el_post_id = isset($undo_el_target['post_id']) ? (int) $undo_el_target['post_id'] : 0;
                if ($undo_el_post_id <= 0) {
                    throw new \Exception('Undo snapshot has no target post.');
                }
                if (!current_user_can('edit_post', $undo_el_post_id)) {
                    throw new \Exception('You do not have permission to edit this post, so this Elementor change cannot be undone.');
                }
                $undo_el_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('elementor_data', $undo_el_pre_op)) {
                    throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                }
                if (!get_post($undo_el_post_id)) {
                    throw new \Exception('The target post no longer exists.');
                }

                
                $undo_el_decoded = json_decode((string) $undo_el_pre_op['elementor_data'], true);
                if (!is_array($undo_el_decoded)) {
                    throw new \Exception('Undo snapshot does not contain a parseable Elementor tree, so it was not restored.');
                }

                
                update_post_meta(
                    $undo_el_post_id,
                    '_elementor_data',
                    wp_slash(wp_json_encode($undo_el_decoded))
                );

                
                $undo_el_inval = ['invalidated' => [], 'warnings' => []];
                if (class_exists('\More_MCP\Integrations\Elementor')) {
                    $undo_el_inval = \More_MCP\Integrations\Elementor::invalidate_derived_state_public($undo_el_post_id);
                }
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                $undo_el_response = [
                    'success'          => true,
                    'op'               => 'elementor_element_write',
                    'post_id'          => $undo_el_post_id,
                    'message'          => 'Elementor page data restored to its pre-operation state.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];
                if (!empty($undo_el_inval['warnings'])) {
                    $undo_el_response['cache_invalidation'] = [
                        'cleared'  => $undo_el_inval['invalidated'],
                        'warnings' => $undo_el_inval['warnings'],
                    ];
                }
                return $undo_el_response;

            case 'elementor_kit_write':

                

                

                
                
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('You do not have permission to edit Site Settings, so this Elementor kit change cannot be undone.');
                }
                if (!class_exists('\More_MCP\Integrations\Elementor')) {
                    throw new \Exception('The Elementor integration is unavailable, so its kit undo cannot run.');
                }
                $undo_kit_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('settings', $undo_kit_pre_op) || !is_array($undo_kit_pre_op['settings'])) {
                    throw new \Exception('Undo snapshot has no kit settings to restore.');
                }

                

                $undo_kit_result = \More_MCP\Integrations\Elementor::restore_kit_settings_public(
                    $undo_kit_pre_op['settings']
                );
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                $undo_kit_response = [
                    'success'          => true,
                    'op'               => 'elementor_kit_write',
                    'kit_id'           => isset($undo_kit_result['kit_id']) ? (int) $undo_kit_result['kit_id'] : 0,
                    'message'          => 'Elementor Site Settings restored to their pre-operation state.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];
                if (!empty($undo_kit_result['cache_invalidation']['warnings'])) {
                    $undo_kit_response['cache_invalidation'] = $undo_kit_result['cache_invalidation'];
                }
                return $undo_kit_response;

            case 'blocks_template_update':
            case 'blocks_template_revert':
            case 'blocks_reusable_update':

                

                
                $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                    ? $undo_snapshot['target']
                    : [];
                $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                if ($undo_post_id <= 0) {
                    throw new \Exception('Undo snapshot has no target post.');
                }
                $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('post_content', $undo_pre_op)) {
                    throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                }

                $undo_is_reusable = ('blocks_reusable_update' === $undo_op);
                if ($undo_is_reusable) {
                    if (!current_user_can('edit_post', $undo_post_id)) {
                        throw new \Exception('You do not have permission to edit this reusable block.');
                    }
                } elseif (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to undo this template change.');
                }

                
                $undo_existing = get_post($undo_post_id);
                if (!$undo_existing) {
                    if ('blocks_template_revert' !== $undo_op) {
                        throw new \Exception('The target post no longer exists.');
                    }
                    $undo_slug = isset($undo_target['slug']) ? sanitize_title((string) $undo_target['slug']) : '';
                    if ('' === $undo_slug) {
                        throw new \Exception('Undo snapshot has no slug to re-create the template from.');
                    }
                    $undo_new_id = wp_insert_post([
                        'post_type'    => isset($undo_target['post_type']) ? sanitize_key((string) $undo_target['post_type']) : 'wp_template',
                        'post_name'    => $undo_slug,
                        'post_title'   => isset($undo_target['title']) ? sanitize_text_field((string) $undo_target['title']) : $undo_slug,
                        'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                        'post_status'  => 'publish',
                    ], true);
                    if (is_wp_error($undo_new_id)) {
                        throw new \Exception('Failed to re-create template: ' . esc_html($undo_new_id->get_error_message()));
                    }
                    wp_set_object_terms($undo_new_id, get_stylesheet(), 'wp_theme');
                    \More_MCP\MCP\Undo_Store::delete($undo_token);
                    return [
                        'success' => true,
                        'op'      => $undo_op,
                        'post_id' => $undo_new_id,
                        'message' => 'Template customization re-created from the undo snapshot.',
                        'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                    ];
                }

                $undo_restored = wp_update_post([
                    'ID'           => $undo_post_id,
                    'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                ], true);
                if (is_wp_error($undo_restored)) {
                    throw new \Exception('Failed to restore content: ' . esc_html($undo_restored->get_error_message()));
                }
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return [
                    'success' => true,
                    'op'      => $undo_op,
                    'post_id' => $undo_post_id,
                    'message' => 'Content restored to its pre-operation state.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];

            case 'blocks_template_create':

                
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to undo this template change.');
                }
                $undo_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target'])
                    ? $undo_snapshot['target']
                    : [];
                $undo_post_id = isset($undo_target['post_id']) ? (int) $undo_target['post_id'] : 0;
                if ($undo_post_id <= 0) {
                    throw new \Exception('Undo snapshot has no target post.');
                }
                if (get_post($undo_post_id)) {
                    wp_delete_post($undo_post_id, true);
                }
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return [
                    'success' => true,
                    'op'      => 'blocks_template_create',
                    'post_id' => $undo_post_id,
                    'message' => 'Template customization removed; the theme-provided file renders again.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];

            case 'blocks_reusable_delete':
                
                if (!current_user_can('edit_posts')) {
                    throw new \Exception('edit_posts capability required to restore a reusable block.');
                }
                $undo_pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (!array_key_exists('post_content', $undo_pre_op)) {
                    throw new \Exception('Undo snapshot has no content to restore.');
                }
                $undo_new_id = wp_insert_post([
                    'post_type'    => 'wp_block',
                    'post_title'   => isset($undo_pre_op['post_title']) ? sanitize_text_field((string) $undo_pre_op['post_title']) : 'Restored block',
                    'post_content' => wp_slash((string) $undo_pre_op['post_content']),
                    'post_status'  => 'publish',
                ], true);
                if (is_wp_error($undo_new_id)) {
                    throw new \Exception('Failed to restore reusable block: ' . esc_html($undo_new_id->get_error_message()));
                }
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return [
                    'success' => true,
                    'op'      => 'blocks_reusable_delete',
                    'post_id' => $undo_new_id,
                    'message' => 'Reusable block restored. Note it has a new ID, so posts embedding the old ID will not automatically reconnect.',
                    'restored_summary' => isset($undo_snapshot['summary']) ? (string) $undo_snapshot['summary'] : '',
                ];

            case 'wp_reorder_menu_items':
                
                if (!current_user_can('edit_theme_options')) {
                    throw new \Exception('edit_theme_options capability required to undo this operation.');
                }
                $pre_op = isset($undo_snapshot['pre_op_state']) && is_array($undo_snapshot['pre_op_state'])
                    ? $undo_snapshot['pre_op_state']
                    : [];
                if (empty($pre_op)) {
                    throw new \Exception('Undo snapshot has no pre_op_state to restore.');
                }
                $restored_count = 0;
                $undo_skipped   = [];
                foreach ($pre_op as $item_id => $prior) {
                    $item_id_int = (int) $item_id;
                    if ($item_id_int <= 0 || !is_array($prior)) {
                        $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => 'invalid_snapshot_entry'];
                        continue;
                    }
                    $update_result = wp_update_post([
                        'ID'          => $item_id_int,
                        'menu_order'  => (int) ($prior['menu_order'] ?? 0),
                        'post_parent' => (int) ($prior['menu_item_parent'] ?? 0),
                    ], true);
                    if (is_wp_error($update_result)) {
                        $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => $update_result->get_error_message()];
                        continue;
                    }
                    if ($update_result === 0) {
                        
                        $undo_skipped[] = ['menu_item_id' => $item_id_int, 'reason' => 'menu_item_not_found'];
                        continue;
                    }
                    $restored_count++;
                }
                wp_cache_flush();
                
                \More_MCP\MCP\Undo_Store::delete($undo_token);

                $undo_response = [
                    'undone'   => true,
                    'op'       => $undo_op,
                    'target'   => $undo_snapshot['target'] ?? [],
                    'restored' => $restored_count,
                    'summary'  => $undo_snapshot['summary'] ?? '',
                ];
                if (!empty($undo_skipped)) {
                    $undo_response['skipped'] = $undo_skipped;
                }
                return $undo_response;

            case 'forms_entry_write':

                
                
                if (!current_user_can('manage_options')) {
                    throw new \Exception('manage_options capability required to undo this forms operation.');
                }
                $forms_undo_result = \More_MCP\Integrations\Forms::undo_entry_write($undo_snapshot);
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return $forms_undo_result;

            case 'metabox_field_write':

                
                
                $mb_target  = isset($undo_snapshot['target']) && is_array($undo_snapshot['target']) ? $undo_snapshot['target'] : array();
                $mb_post_id = isset($mb_target['post_id']) ? (int) $mb_target['post_id'] : 0;
                if ($mb_post_id <= 0 || !current_user_can('edit_post', $mb_post_id)) {
                    throw new \Exception('edit_post capability on the target post is required to undo this Meta Box operation.');
                }
                $mb_undo_result = \More_MCP\Integrations\MetaBox::undo_field_write($undo_snapshot);
                \More_MCP\MCP\Undo_Store::delete($undo_token);
                return $mb_undo_result;

            default:
                throw new \Exception('Unsupported op in undo snapshot: ' . esc_html((string) $undo_op) . '. This version of More MCP does not know how to undo that operation. Contact support if you saw this after a successful tool call.');
        }
    }
}
