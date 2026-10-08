<?php
namespace More_MCP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Option_Presets {

    const SHAPE_SCALAR = 'scalar'; 
    const SHAPE_ARRAY  = 'array';  

    const SOURCE_CORE = 'wordpress';

    public static function groups() {
        $groups = [];

        $groups['core_identity'] = [
            'source'      => self::SOURCE_CORE,
            'label'       => __('Site identity', 'mordenhost-mcp-server'),
            'description' => __('Site title and tagline. The two settings most often adjusted on request, and the easiest to verify afterwards.', 'mordenhost-mcp-server'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'blogname'        => __('Site title', 'mordenhost-mcp-server'),
                'blogdescription' => __('Tagline', 'mordenhost-mcp-server'),
            ],
        ];

        $groups['core_reading'] = [
            'source'      => self::SOURCE_CORE,
            'label'       => __('Reading and formatting', 'mordenhost-mcp-server'),
            'description' => __('How many posts a page lists, and how dates and times are displayed.', 'mordenhost-mcp-server'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'posts_per_page'   => __('Posts per page', 'mordenhost-mcp-server'),
                'date_format'      => __('Date format', 'mordenhost-mcp-server'),
                'time_format'      => __('Time format', 'mordenhost-mcp-server'),
                'start_of_week'    => __('Week starts on', 'mordenhost-mcp-server'),
                'timezone_string'  => __('Site timezone', 'mordenhost-mcp-server'),
            ],
        ];

        $groups['core_discussion'] = [
            'source'      => self::SOURCE_CORE,
            'label'       => __('Comments and discussion', 'mordenhost-mcp-server'),
            'description' => __('Comment defaults and moderation behavior.', 'mordenhost-mcp-server'),
            'shape'       => self::SHAPE_SCALAR,
            'caution'     => __('Turning moderation off site-wide is a spam decision, not a formatting one. Allow these only if you want an agent making that call.', 'mordenhost-mcp-server'),
            'options'     => [
                'default_comment_status'       => __('Comments open on new posts', 'mordenhost-mcp-server'),
                'comment_moderation'           => __('Hold comments for moderation', 'mordenhost-mcp-server'),
                'comment_registration'         => __('Require registration to comment', 'mordenhost-mcp-server'),
                'close_comments_for_old_posts' => __('Close comments on old posts', 'mordenhost-mcp-server'),
                'thread_comments'              => __('Enable threaded comments', 'mordenhost-mcp-server'),
            ],
        ];

        $groups['core_media'] = [
            'source'      => self::SOURCE_CORE,
            'label'       => __('Media image sizes', 'mordenhost-mcp-server'),
            'description' => __('Dimensions WordPress generates for new uploads. Changing these does not regenerate existing images.', 'mordenhost-mcp-server'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'thumbnail_size_w' => __('Thumbnail width', 'mordenhost-mcp-server'),
                'thumbnail_size_h' => __('Thumbnail height', 'mordenhost-mcp-server'),
                'medium_size_w'    => __('Medium width', 'mordenhost-mcp-server'),
                'medium_size_h'    => __('Medium height', 'mordenhost-mcp-server'),
                'large_size_w'     => __('Large width', 'mordenhost-mcp-server'),
                'large_size_h'     => __('Large height', 'mordenhost-mcp-server'),
            ],
        ];

        
        
        $groups['core_visibility'] = [
            'source'      => self::SOURCE_CORE,
            'label'       => __('Search engine visibility', 'mordenhost-mcp-server'),
            'description' => __('Whether search engines are asked to index this site.', 'mordenhost-mcp-server'),
            'shape'       => self::SHAPE_SCALAR,
            'caution'     => __('One wrong write here deindexes the site, and the damage is invisible on the site itself: you find out from traffic weeks later. Leave this off unless you have a specific reason.', 'mordenhost-mcp-server'),
            'options'     => [
                'blog_public' => __('Allow search engines to index this site', 'mordenhost-mcp-server'),
            ],
        ];

        if (self::woocommerce_active()) {
            $groups['woocommerce_locale'] = [
                'source'      => 'woocommerce',
                'label'       => __('Currency and units', 'mordenhost-mcp-server'),
                'description' => __('Store currency, price formatting, and measurement units. Each is its own option, so a write changes only that one setting.', 'mordenhost-mcp-server'),
                'shape'       => self::SHAPE_SCALAR,
                'options'     => [
                    'woocommerce_currency'              => __('Currency', 'mordenhost-mcp-server'),
                    'woocommerce_currency_pos'          => __('Currency symbol position', 'mordenhost-mcp-server'),
                    'woocommerce_price_thousand_sep'    => __('Thousand separator', 'mordenhost-mcp-server'),
                    'woocommerce_price_decimal_sep'     => __('Decimal separator', 'mordenhost-mcp-server'),
                    'woocommerce_price_num_decimals'    => __('Number of decimals', 'mordenhost-mcp-server'),
                    'woocommerce_weight_unit'           => __('Weight unit', 'mordenhost-mcp-server'),
                    'woocommerce_dimension_unit'        => __('Dimension unit', 'mordenhost-mcp-server'),
                    'woocommerce_default_country'       => __('Store base country / region', 'mordenhost-mcp-server'),
                ],
            ];

            $groups['woocommerce_catalog'] = [
                'source'      => 'woocommerce',
                'label'       => __('Catalog behavior', 'mordenhost-mcp-server'),
                'description' => __('Reviews, ratings, and stock display on product pages.', 'mordenhost-mcp-server'),
                'shape'       => self::SHAPE_SCALAR,
                'options'     => [
                    'woocommerce_enable_reviews'            => __('Enable product reviews', 'mordenhost-mcp-server'),
                    'woocommerce_enable_review_rating'      => __('Enable star ratings', 'mordenhost-mcp-server'),
                    'woocommerce_review_rating_required'    => __('Star rating required', 'mordenhost-mcp-server'),
                    'woocommerce_manage_stock'              => __('Manage stock', 'mordenhost-mcp-server'),
                    'woocommerce_notify_low_stock_amount'   => __('Low stock threshold', 'mordenhost-mcp-server'),
                    'woocommerce_notify_no_stock_amount'    => __('Out of stock threshold', 'mordenhost-mcp-server'),
                ],
            ];
        }

        if (self::yoast_active()) {
            $groups['yoast'] = [
                'source'      => 'yoast',
                'label'       => __('Global SEO settings', 'mordenhost-mcp-server'),
                'description' => __('Yoast stores its settings grouped into a few options, each holding many values at once.', 'mordenhost-mcp-server'),
                'shape'       => self::SHAPE_ARRAY,
                'caution'     => __('Each of these is one option containing dozens of settings. A write replaces the whole thing, so an agent that does not read the current value first will wipe every other Yoast setting in that group. Per-post SEO fields do not need this: those are post meta, already writable through the SEO tools.', 'mordenhost-mcp-server'),
                'options'     => [
                    'wpseo'        => __('General settings', 'mordenhost-mcp-server'),
                    'wpseo_titles' => __('Titles and metadata templates', 'mordenhost-mcp-server'),
                    'wpseo_social' => __('Social profiles and defaults', 'mordenhost-mcp-server'),
                ],
            ];
        }

        if (self::rank_math_active()) {
            $groups['rank_math'] = [
                'source'      => 'rank_math',
                'label'       => __('Global SEO settings', 'mordenhost-mcp-server'),
                'description' => __('Rank Math groups its settings into a few options, each holding many values at once.', 'mordenhost-mcp-server'),
                'shape'       => self::SHAPE_ARRAY,
                'caution'     => __('Each of these is one option containing many settings, replaced wholesale on write. Per-post SEO fields are post meta and do not need this.', 'mordenhost-mcp-server'),
                'options'     => [
                    'rank-math-options-general' => __('General settings', 'mordenhost-mcp-server'),
                    'rank-math-options-titles'  => __('Titles and metadata', 'mordenhost-mcp-server'),
                    'rank-math-options-sitemap' => __('Sitemap settings', 'mordenhost-mcp-server'),
                ],
            ];
        }

        $filtered = apply_filters('more_mcp_option_presets', $groups);

        return is_array($filtered) ? $filtered : $groups;
    }

    public static function source_labels() {
        $labels = [
            self::SOURCE_CORE => __('WordPress core', 'mordenhost-mcp-server'),
            'woocommerce'     => __('WooCommerce', 'mordenhost-mcp-server'),
            'yoast'           => __('Yoast SEO', 'mordenhost-mcp-server'),
            'rank_math'       => __('Rank Math', 'mordenhost-mcp-server'),
        ];

        $filtered = apply_filters('more_mcp_option_preset_sources', $labels);

        return is_array($filtered) ? $filtered : $labels;
    }

    public static function sources() {
        $labels = self::source_labels();
        $out    = [];

        foreach (self::groups() as $group_slug => $group) {
            if (empty($group['options']) || !is_array($group['options'])) {
                continue;
            }

            $source = isset($group['source']) && is_string($group['source']) && $group['source'] !== ''
                ? $group['source']
                : 'other';

            if (!isset($out[$source])) {
                $out[$source] = [
                    'label'  => $labels[$source] ?? __('Other plugins', 'mordenhost-mcp-server'),
                    'groups' => [],
                ];
            }

            $out[$source]['groups'][$group_slug] = $group;
        }

        if (isset($out[self::SOURCE_CORE])) {
            $core = $out[self::SOURCE_CORE];
            unset($out[self::SOURCE_CORE]);
            $out = array_merge([self::SOURCE_CORE => $core], $out);
        }

        return $out;
    }

    public static function source_summaries() {
        $out = [];

        foreach (self::sources() as $source_slug => $source) {
            $names     = [];
            $cautions  = [];
            $has_array = false;
            $breakdown = [];

            foreach ($source['groups'] as $group) {
                if (empty($group['options']) || !is_array($group['options'])) {
                    continue;
                }

                foreach (array_keys($group['options']) as $name) {
                    $names[] = (string) $name;
                }

                if (($group['shape'] ?? '') === self::SHAPE_ARRAY) {
                    $has_array = true;
                }

                if (!empty($group['caution'])) {
                    $cautions[] = (string) $group['caution'];
                }

                $group_label = isset($group['label']) ? (string) $group['label'] : '';
                if ($group_label !== '') {
                    $breakdown[$group_label] = array_values($group['options']);
                }
            }

            if (empty($names)) {
                continue;
            }

            $out[$source_slug] = [
                'label'     => $source['label'],
                'names'     => array_values(array_unique($names)),
                'has_array' => $has_array,
                'cautions'  => $cautions,
                'breakdown' => $breakdown,
            ];
        }

        return $out;
    }

    public static function split_stored(array $stored) {
        $stored_set = [];
        foreach ($stored as $name) {
            $name = (string) $name;
            if ($name !== '') {
                $stored_set[$name] = true;
            }
        }

        $sources  = [];
        $absorbed = [];

        foreach (self::source_summaries() as $slug => $summary) {
            $names = $summary['names'];
            $on    = ! empty($names);
            foreach ($names as $name) {
                if (empty($stored_set[$name])) {
                    $on = false;
                    break;
                }
            }
            $sources[$slug] = $on;
            if ($on) {
                foreach ($names as $name) {
                    $absorbed[$name] = true;
                }
            }
        }

        $custom = [];
        foreach ($stored_set as $name => $_) {
            if (empty($absorbed[$name])) {
                $custom[] = $name;
            }
        }

        return [
            'sources' => $sources,
            'custom'  => array_values(array_unique($custom)),
        ];
    }

    private static function woocommerce_active() {
        return class_exists('WooCommerce');
    }

    private static function yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    private static function rank_math_active() {
        return class_exists('RankMath') || defined('RANK_MATH_VERSION');
    }
}
