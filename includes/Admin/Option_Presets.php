<?php
namespace More_MCP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Curated presets for the writable-options allowlist.
 *
 * The allowlist field used to be a bare textarea, which asked the admin for
 * something the screen gave them no way to know: the exact `wp_options` row name
 * a plugin stores its settings under. Those names are not the labels shown on a
 * settings page, they are rarely documented, and a guess fails silently — the
 * entry saves, looks correct, and the option simply stays unwritable. This class
 * turns the common cases into checkboxes so the name never has to be typed.
 *
 * Three properties of this catalogue are deliberate:
 *
 * 1. It is ADDITIVE, not authoritative. The textarea remains for anything not
 *    covered here, and a preset is only a shortcut for typing a name. Nothing in
 *    this file can widen what `wp_update_option` accepts — the stored result is
 *    still a flat list of option names checked against the same denylist.
 *
 * 2. Groups gate on plugin presence. A site without WooCommerce is not offered
 *    WooCommerce options, because a list of settings the site does not have is
 *    noise on the screen that answers "what should I allow".
 *
 * 3. Each group carries a `shape` and, where relevant, a `caution`. This is the
 *    part that matters most: an option holding a serialized array is replaced
 *    WHOLESALE by `wp_update_option`, so an agent writing one key discards every
 *    other setting in that option. Presenting those groups identically to
 *    single-value options would hide a real risk behind a friendly checkbox.
 *
 * Adding a group: keep option names verified against the plugin's own source, not
 * inferred from its UI labels. A wrong name here is worse than no preset, because
 * a checkbox implies the name was checked by someone.
 */
class Option_Presets {

    /**
     * Option-name shapes, used to explain what a write actually does.
     */
    const SHAPE_SCALAR = 'scalar'; // One option, one value. A write replaces just that value.
    const SHAPE_ARRAY  = 'array';  // One option holding many settings. A write replaces ALL of them.

    /**
     * The preset catalogue.
     *
     * Each group: label, description, shape, optional caution, optional
     * `available` callable (absent means always available), and options as
     * option_name => human label.
     *
     * @return array<string,array>
     */
    public static function groups() {
        $groups = [];

        $groups['core_identity'] = [
            'label'       => __('Site identity', 'more-mcp'),
            'description' => __('Site title and tagline. The two settings most often adjusted on request, and the easiest to verify afterwards.', 'more-mcp'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'blogname'        => __('Site title', 'more-mcp'),
                'blogdescription' => __('Tagline', 'more-mcp'),
            ],
        ];

        $groups['core_reading'] = [
            'label'       => __('Reading and formatting', 'more-mcp'),
            'description' => __('How many posts a page lists, and how dates and times are displayed.', 'more-mcp'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'posts_per_page'   => __('Posts per page', 'more-mcp'),
                'date_format'      => __('Date format', 'more-mcp'),
                'time_format'      => __('Time format', 'more-mcp'),
                'start_of_week'    => __('Week starts on', 'more-mcp'),
                'timezone_string'  => __('Site timezone', 'more-mcp'),
            ],
        ];

        $groups['core_discussion'] = [
            'label'       => __('Comments and discussion', 'more-mcp'),
            'description' => __('Comment defaults and moderation behavior.', 'more-mcp'),
            'shape'       => self::SHAPE_SCALAR,
            'caution'     => __('Turning moderation off site-wide is a spam decision, not a formatting one. Allow these only if you want an agent making that call.', 'more-mcp'),
            'options'     => [
                'default_comment_status'       => __('Comments open on new posts', 'more-mcp'),
                'comment_moderation'           => __('Hold comments for moderation', 'more-mcp'),
                'comment_registration'         => __('Require registration to comment', 'more-mcp'),
                'close_comments_for_old_posts' => __('Close comments on old posts', 'more-mcp'),
                'thread_comments'              => __('Enable threaded comments', 'more-mcp'),
            ],
        ];

        $groups['core_media'] = [
            'label'       => __('Media image sizes', 'more-mcp'),
            'description' => __('Dimensions WordPress generates for new uploads. Changing these does not regenerate existing images.', 'more-mcp'),
            'shape'       => self::SHAPE_SCALAR,
            'options'     => [
                'thumbnail_size_w' => __('Thumbnail width', 'more-mcp'),
                'thumbnail_size_h' => __('Thumbnail height', 'more-mcp'),
                'medium_size_w'    => __('Medium width', 'more-mcp'),
                'medium_size_h'    => __('Medium height', 'more-mcp'),
                'large_size_w'     => __('Large width', 'more-mcp'),
                'large_size_h'     => __('Large height', 'more-mcp'),
            ],
        ];

        // Search visibility is its own group precisely so it cannot be enabled as
        // a side effect of wanting the harmless reading settings. Setting it to 0
        // asks search engines to drop the site.
        $groups['core_visibility'] = [
            'label'       => __('Search engine visibility', 'more-mcp'),
            'description' => __('Whether search engines are asked to index this site.', 'more-mcp'),
            'shape'       => self::SHAPE_SCALAR,
            'caution'     => __('One wrong write here deindexes the site, and the damage is invisible on the site itself — you find out from traffic weeks later. Leave this off unless you have a specific reason.', 'more-mcp'),
            'options'     => [
                'blog_public' => __('Allow search engines to index this site', 'more-mcp'),
            ],
        ];

        if (self::woocommerce_active()) {
            $groups['woocommerce_locale'] = [
                'label'       => __('WooCommerce — currency and units', 'more-mcp'),
                'description' => __('Store currency, price formatting, and measurement units. Each is its own option, so a write changes only that one setting.', 'more-mcp'),
                'shape'       => self::SHAPE_SCALAR,
                'options'     => [
                    'woocommerce_currency'              => __('Currency', 'more-mcp'),
                    'woocommerce_currency_pos'          => __('Currency symbol position', 'more-mcp'),
                    'woocommerce_price_thousand_sep'    => __('Thousand separator', 'more-mcp'),
                    'woocommerce_price_decimal_sep'     => __('Decimal separator', 'more-mcp'),
                    'woocommerce_price_num_decimals'    => __('Number of decimals', 'more-mcp'),
                    'woocommerce_weight_unit'           => __('Weight unit', 'more-mcp'),
                    'woocommerce_dimension_unit'        => __('Dimension unit', 'more-mcp'),
                    'woocommerce_default_country'       => __('Store base country / region', 'more-mcp'),
                ],
            ];

            $groups['woocommerce_catalog'] = [
                'label'       => __('WooCommerce — catalog behavior', 'more-mcp'),
                'description' => __('Reviews, ratings, and stock display on product pages.', 'more-mcp'),
                'shape'       => self::SHAPE_SCALAR,
                'options'     => [
                    'woocommerce_enable_reviews'            => __('Enable product reviews', 'more-mcp'),
                    'woocommerce_enable_review_rating'      => __('Enable star ratings', 'more-mcp'),
                    'woocommerce_review_rating_required'    => __('Star rating required', 'more-mcp'),
                    'woocommerce_manage_stock'              => __('Manage stock', 'more-mcp'),
                    'woocommerce_notify_low_stock_amount'   => __('Low stock threshold', 'more-mcp'),
                    'woocommerce_notify_no_stock_amount'    => __('Out of stock threshold', 'more-mcp'),
                ],
            ];
        }

        if (self::yoast_active()) {
            $groups['yoast'] = [
                'label'       => __('Yoast SEO', 'more-mcp'),
                'description' => __('Yoast stores its settings grouped into a few options, each holding many values at once.', 'more-mcp'),
                'shape'       => self::SHAPE_ARRAY,
                'caution'     => __('Each of these is one option containing dozens of settings. A write replaces the whole thing, so an agent that does not read the current value first will wipe every other Yoast setting in that group. Per-post SEO fields do not need this — those are post meta, already writable through the SEO tools.', 'more-mcp'),
                'options'     => [
                    'wpseo'        => __('General settings', 'more-mcp'),
                    'wpseo_titles' => __('Titles and metadata templates', 'more-mcp'),
                    'wpseo_social' => __('Social profiles and defaults', 'more-mcp'),
                ],
            ];
        }

        if (self::rank_math_active()) {
            $groups['rank_math'] = [
                'label'       => __('Rank Math', 'more-mcp'),
                'description' => __('Rank Math groups its settings into a few options, each holding many values at once.', 'more-mcp'),
                'shape'       => self::SHAPE_ARRAY,
                'caution'     => __('Each of these is one option containing many settings, replaced wholesale on write. Per-post SEO fields are post meta and do not need this.', 'more-mcp'),
                'options'     => [
                    'rank-math-options-general' => __('General settings', 'more-mcp'),
                    'rank-math-options-titles'  => __('Titles and metadata', 'more-mcp'),
                    'rank-math-options-sitemap' => __('Sitemap settings', 'more-mcp'),
                ],
            ];
        }

        /**
         * Filter the preset catalogue.
         *
         * Plugin authors who would rather ship a checkbox than ask users to type
         * an option name can register a group here. Note this only affects what
         * the admin UI OFFERS — the effective allowlist is still whatever the
         * admin saved, plus the more_mcp_writable_options filter.
         *
         * @param array $groups Preset groups.
         */
        $filtered = apply_filters('more_mcp_option_presets', $groups);

        return is_array($filtered) ? $filtered : $groups;
    }

    /**
     * Every option name that appears in any preset group.
     *
     * Used to split a stored allowlist into "covered by a checkbox" and
     * "typed by hand", so the textarea shows only the latter and the stored value
     * stays a single flat list. Deriving this rather than storing the split keeps
     * one source of truth: if a name later becomes a preset, it simply moves from
     * the textarea to a ticked box on the next page load.
     *
     * @return array<string> Flat list of option names.
     */
    public static function all_preset_option_names() {
        $names = [];
        foreach (self::groups() as $group) {
            if (empty($group['options']) || !is_array($group['options'])) {
                continue;
            }
            foreach (array_keys($group['options']) as $name) {
                $names[] = (string) $name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Split a stored allowlist into preset-covered names and hand-typed ones.
     *
     * @param array $stored Stored allowlist (flat option names).
     * @return array{preset: array<string>, custom: array<string>}
     */
    public static function split_stored(array $stored) {
        $preset_names = self::all_preset_option_names();
        $preset       = [];
        $custom       = [];

        foreach ($stored as $name) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }
            if (in_array($name, $preset_names, true)) {
                $preset[] = $name;
            } else {
                $custom[] = $name;
            }
        }

        return [
            'preset' => array_values(array_unique($preset)),
            'custom' => array_values(array_unique($custom)),
        ];
    }

    /* ------------------------------------------------------------------
     *  Plugin detection
     *
     *  Same discipline as includes/Integrations/*: gate on a class, function,
     *  or constant the plugin actually defines, and degrade silently when it is
     *  absent. A missing plugin means a missing group, never a warning.
     * ----------------------------------------------------------------*/

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
