<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/Elementor/Runtime.php';


class Elementor {

	
	public static function is_available() {
		return class_exists( '\Elementor\Plugin' );
	}

	
	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		return [
			[
				'name'        => 'elementor_clone_page',
				'description' => 'Duplicate an existing Elementor page or post as a new draft. Copies the full _elementor_data tree and regenerates every element ID to avoid duplicates. Preserves Container model, legacy section/column, and atomic widgets as-is. Returns the new post ID. The Elementor editor on the new page opens cleanly because IDs are unique within the document.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'source_post_id' => [ 'type' => 'integer', 'description' => 'Post or page ID to clone from. Must have Elementor data.' ],
						'new_title'      => [ 'type' => 'string', 'description' => 'Title for the new post' ],
						'new_status'     => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'private', 'pending' ], 'description' => 'Defaults to draft' ],
					],
					'required'   => [ 'source_post_id', 'new_title' ],
				],
			],
			[
				'name'        => 'elementor_replace_text',
				'description' => 'Replace text in all text-bearing widget settings of an Elementor page. Walks the _elementor_data tree and substitutes matching strings in known text fields (heading title, text-editor content, button text, image caption/alt, etc.). Case-sensitive by default. Atomic widgets are skipped (opaque passthrough). Returns count of replacements made.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer' ],
						'find'             => [ 'type' => 'string', 'description' => 'Text to find' ],
						'replace'          => [ 'type' => 'string', 'description' => 'Text to substitute' ],
						'case_insensitive' => [ 'type' => 'boolean', 'description' => 'Default false' ],
					],
					'required'   => [ 'post_id', 'find', 'replace' ],
				],
			],
			[
				'name'        => 'elementor_replace_image',
				'description' => 'Swap image URLs in an Elementor page across all image-bearing widgets (image widget, background image, gallery items, etc.). Optionally also remap WP attachment IDs. Returns count of replacements made.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'old_url' => [ 'type' => 'string', 'description' => 'URL to find' ],
						'new_url' => [ 'type' => 'string', 'description' => 'URL to replace with' ],
						'old_id'  => [ 'type' => 'integer', 'description' => 'Optional: old WP attachment ID' ],
						'new_id'  => [ 'type' => 'integer', 'description' => 'Optional: new WP attachment ID' ],
					],
					'required'   => [ 'post_id', 'old_url', 'new_url' ],
				],
			],
			[
				'name'        => 'elementor_get_page_outline',
				'description' => 'Extract a simplified outline of an Elementor page: section/container hierarchy, widget types per slot, and short text snippets from text-bearing widgets. Returns JSON small enough for an AI to reason over without consuming the full _elementor_data budget (~2KB for typical pages). Useful before calling clone or replace_text to understand the structure first.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'elementor_get_widget_settings',
				'description' => 'Read the full settings object for a single Elementor element (widget, container, section, or column) by its ID. Use after elementor_get_page_outline to inspect a specific element before proposing a modification. Returns element_type, widget_type (widgets only), depth in the tree, has_children flag, child_count, and the raw settings object. If the element is not found, returns found=false with the count of elements searched (helps diagnose wrong IDs). Requires read_post on the parent post_id.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'    => [ 'type' => 'integer', 'description' => 'The Elementor page/post to search within.' ],
						'element_id' => [ 'type' => 'string',  'description' => 'The Elementor element ID (short hex string, e.g. "a1b2c3d"). Obtained from elementor_get_page_outline or via editing the element.' ],
					],
					'required'   => [ 'post_id', 'element_id' ],
				],
			],
			[
				'name'        => 'elementor_list_local_templates',
				'description' => 'Enumerate saved templates from the Elementor Library (the elementor_library custom post type). Returns id, name, type (page/section/widget/popup/etc.), and date_modified for each. Filter by type if needed. Use this before elementor_import_template to discover available templates.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'type'  => [ 'type' => 'string', 'description' => 'Optional filter by template type (page, section, widget, popup, header, footer, single, archive)' ],
						'limit' => [ 'type' => 'integer', 'description' => 'Max templates to return (default 50)' ],
					],
				],
			],
			[
				'name'        => 'elementor_import_template',
				'description' => 'Create a new Elementor template (in the elementor_library CPT) from a JSON payload. Accepts the structure exported by the Elementor editor (an array of section/container elements). Validates top-level shape and stores the data as _elementor_data on a new template post. Returns the new template post ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'title'         => [ 'type' => 'string', 'description' => 'Template name' ],
						'template_type' => [ 'type' => 'string', 'description' => 'page, section, widget, popup, header, footer, single, archive. Defaults to page.' ],
						'template_json' => [ 'type' => 'string', 'description' => 'JSON-encoded array of Elementor elements (the export shape)' ],
					],
					'required'   => [ 'title', 'template_json' ],
				],
			],
			[
				'name'        => 'elementor_add_widget',
				'description' => 'Add a new widget or container to an existing Elementor page. Dual-surface: RAW (any widget_type + full settings object) or CURATED (high-frequency widget types with flat parameters the tool expands into the canonical settings object internally, saving tokens). Curated widget_types: container, heading, text-editor, button, image, image-box, icon-box, icon-list, video, divider, spacer. For any other widget_type, supply settings directly. Container widgets can include children inline (one call drops parent + N children, recursive). Atomic widgets (Editor V4, widget_type prefixed a- or e-) pass through opaquely via the raw path. Returns the new element ID + parent context + edit URL. Cap-checked via edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer', 'description' => 'Target post or page ID. Must be Elementor-edited.' ],
						'widget_type'      => [ 'type' => 'string', 'description' => 'Elementor widget slug (e.g. heading, button, html, wp-widget-text), or "container" for a Flexbox container.' ],
						'settings'         => [ 'type' => 'object', 'description' => 'RAW path: full Elementor settings object for this widget. When supplied, raw wins (curated params ignored). Required for non-curated widget_types.' ],
						'parent_id'        => [ 'type' => 'string', 'description' => 'Optional. Element ID to insert under. Must be a container, section, or column. If omitted, appended at document top level.' ],
						'position'         => [ 'type' => 'integer', 'description' => 'Optional. Zero-indexed position within parent. If omitted, appended at end.' ],
						'flex_direction'   => [ 'type' => 'string', 'enum' => [ 'row', 'column' ], 'description' => 'Curated container: row or column. Default column.' ],
						'content_width'    => [ 'type' => 'string', 'enum' => [ 'boxed', 'full' ], 'description' => 'Curated container: boxed or full. Default boxed.' ],
						'children'         => [ 'type' => 'array', 'description' => 'Curated container: inline child widget definitions. Each item is an object with widget_type + curated params or settings.' ],
						'title'            => [ 'type' => 'string', 'description' => 'Curated heading: title text.' ],
						'header_size'      => [ 'type' => 'string', 'description' => 'Curated heading: HTML tag (h1-h6, div, span, p). Default h2.' ],
						'editor'           => [ 'type' => 'string', 'description' => 'Curated text-editor: HTML content.' ],
						'text'             => [ 'type' => 'string', 'description' => 'Curated button: button label text.' ],
						'link_url'         => [ 'type' => 'string', 'description' => 'Curated button/image/image-box/icon-box: destination URL.' ],
						'link_target'      => [ 'type' => 'string', 'enum' => [ '_blank', '_self' ], 'description' => 'Curated button/image: link target. Default _self.' ],
						'image_url'        => [ 'type' => 'string', 'description' => 'Curated image/image-box: image URL.' ],
						'image_alt'        => [ 'type' => 'string', 'description' => 'Curated image/image-box: image alt text.' ],
						'title_text'       => [ 'type' => 'string', 'description' => 'Curated image-box/icon-box: title text.' ],
						'description_text' => [ 'type' => 'string', 'description' => 'Curated image-box/icon-box: description text.' ],
						'title_size'       => [ 'type' => 'string', 'description' => 'Curated image-box/icon-box: title HTML tag. Default h3.' ],
						'icon'             => [ 'type' => 'string', 'description' => 'Curated icon-box: FontAwesome icon class (e.g. fas fa-check). Library auto-derived from prefix.' ],
						'items'            => [ 'type' => 'array', 'description' => 'Curated icon-list: array of { text (required), icon?, link_url? } items.' ],
						'video_url'        => [ 'type' => 'string', 'description' => 'Curated video: YouTube, Vimeo, or Dailymotion URL. Self-hosted / VideoPress require raw mode.' ],
						'aspect_ratio'     => [ 'type' => 'string', 'enum' => [ '169', '219', '43', '32', '11', '916' ], 'description' => 'Curated video: aspect ratio (169 = 16:9). Default 169.' ],
						'autoplay'         => [ 'type' => 'boolean', 'description' => 'Curated video: autoplay. Default false.' ],
						'weight'           => [ 'type' => 'integer', 'description' => 'Curated divider: border thickness in pixels. Default 1.' ],
						'color'            => [ 'type' => 'string', 'description' => 'Curated divider: border color hex (e.g. #000000).' ],
						'space'            => [ 'type' => 'integer', 'description' => 'Curated spacer: height in pixels. Default 50.' ],
					],
					'required'   => [ 'post_id', 'widget_type' ],
				],
			],
			[
				'name'        => 'elementor_update_widget',
				'description' => 'Change the settings of one existing element (widget or container) addressed by its element ID. The read-side mirror is elementor_get_widget_settings. IMPORTANT: settings are MERGED into the element by default, not replaced — an Elementor settings object holds content and styling keys side by side (title next to title_color, typography_font_size, margin), so sending only the keys you want changed is the safe call and everything else is preserved. Pass replace_settings=true for a wholesale swap, which discards every key you did not send including all styling. Set dry_run=true to preview the resulting settings without writing. expected_widget_type aborts the write unless the target is the widget type you expected, which protects against a stale element ID. Atomic elements (Editor V4, type prefixed a- or e- in widgetType or elType) are refused: their schema is not publicly documented, so a merge could corrupt them. Emits an undo token. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'              => [ 'type' => 'integer', 'description' => 'Post or page ID holding the element.' ],
						'element_id'           => [ 'type' => 'string', 'description' => 'Element ID to update (short hex string, e.g. "a1b2c3d"). Get it from elementor_get_page_outline or elementor_get_widget_settings.' ],
						'settings'             => [ 'type' => 'object', 'description' => 'Settings keys to apply. Merged into the existing settings unless replace_settings is true. Passing a key with null removes it.' ],
						'replace_settings'     => [ 'type' => 'boolean', 'description' => 'Replace the whole settings object instead of merging. Discards every key not supplied, including styling. Default false.' ],
						'expected_widget_type' => [ 'type' => 'string', 'description' => 'Guard: abort unless the target element has this widgetType (or elType for containers). Recommended whenever the element ID came from an earlier call.' ],
						'dry_run'              => [ 'type' => 'boolean', 'description' => 'Preview the resulting settings without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'element_id', 'settings' ],
				],
			],
			[
				'name'        => 'elementor_delete_widget',
				'description' => 'Remove one element (widget or container) from an Elementor page, including every element nested inside it. Deleting a container deletes its children with it, so the response reports how many elements went and dry_run is worth running first on anything with children. Atomic widgets can be deleted — removing a whole node does not require understanding its schema, unlike editing one. expected_widget_type aborts unless the target is what you expected, which matters more here than anywhere else because a stale element ID means deleting the wrong part of the page. Emits an undo token that restores the full pre-deletion tree. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'              => [ 'type' => 'integer', 'description' => 'Post or page ID holding the element.' ],
						'element_id'           => [ 'type' => 'string', 'description' => 'Element ID to delete.' ],
						'expected_widget_type' => [ 'type' => 'string', 'description' => 'Guard: abort unless the target element has this widgetType (or elType for containers). Strongly recommended for deletes.' ],
						'dry_run'              => [ 'type' => 'boolean', 'description' => 'Report what would be removed, including the descendant count, without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'element_id' ],
				],
			],
			[
				'name'        => 'elementor_move_widget',
				'description' => 'Move one existing element (widget or container) to a new location within the same page. Give the element to move (element_id) and a reference element (target_id) plus a position: before or after the reference, or first_child / last_child of it (child positions require the reference to be a container, section, or column). The element keeps its settings and its own children — this only re-parents it. Refuses moving an element into its own subtree, which would orphan the tree. expected_widget_type aborts unless the moved element is the type you expected (element IDs are per-document and shift on rebuild). Atomic (V4) elements can be moved — moving does not interpret their schema. Supports dry_run and emits an undo token restoring the full pre-move tree. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'              => [ 'type' => 'integer', 'description' => 'Post or page ID holding both elements.' ],
						'element_id'           => [ 'type' => 'string', 'description' => 'Element ID to move.' ],
						'target_id'            => [ 'type' => 'string', 'description' => 'Reference element ID the move is relative to.' ],
						'position'             => [ 'type' => 'string', 'enum' => [ 'before', 'after', 'first_child', 'last_child' ], 'description' => 'Placement relative to target_id.' ],
						'expected_widget_type' => [ 'type' => 'string', 'description' => 'Guard: abort unless the moved element has this widgetType (or elType for containers).' ],
						'dry_run'              => [ 'type' => 'boolean', 'description' => 'Report the intended move without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'element_id', 'target_id', 'position' ],
				],
			],
			[
				'name'        => 'elementor_get_loop_template',
				'description' => 'Resolve a Loop Grid / Loop Carousel widget to the separate loop-item template it renders. Given the post_id and element_id of a loop widget (any widget carrying a template_id setting), returns the loop template document: loop_post_id, template_type, title, and its element outline. Edit the loop item itself with the ordinary Elementor tools (elementor_get_widget_settings / update_widget / add_widget / delete_widget / move_widget) using the returned loop_post_id as their post_id. Returns has_loop_template=false when the widget has no template_id set. Requires read_post on the page.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'    => [ 'type' => 'integer', 'description' => 'Page/post ID containing the loop widget.' ],
						'element_id' => [ 'type' => 'string', 'description' => 'Element ID of the loop grid/carousel widget.' ],
					],
					'required'   => [ 'post_id', 'element_id' ],
				],
			],

			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			[
				'name'        => 'elementor_get_kit',
				'description' => 'Read the active Elementor kit (Site Settings) as a flat settings object: global colors (system_colors, custom_colors), global typography (system_typography, custom_typography), theme style defaults for text/buttons/images/form-fields, site identity (site_name, site_description, site_logo, site_favicon), layout (container_width, space_between_widgets, default_page_template, breakpoints), background, lightbox, page transitions, and custom CSS. These are the values shared across every page the theme renders. Pair with elementor_get_kit_schema to learn each field\'s type and valid values before writing, and elementor_update_kit to change them. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_get_kit_schema',
				'description' => 'Get the control schema for the active Elementor kit, grouped by Site Settings tab (global-colors, global-typography, theme-style-typography, theme-style-buttons, theme-style-images, theme-style-form-fields, settings-site-identity, settings-background, settings-layout, settings-lightbox, settings-page-transitions, settings-custom-css, plus any tab a plugin registers via elementor/kit/register_tabs). Each control reports its label, type, default, options, and — for repeaters like colors and typography — the nested field definitions. Read this before elementor_update_kit so the values you send match the field types Elementor expects rather than being silently dropped. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_get_kit_fonts',
				'description' => 'List the fonts Elementor knows about — system, Google, and any registered by plugins — with their group classification, whether Google Fonts loading is enabled, and the font-display setting. Use it to pick valid font_family values before writing typography into the kit with elementor_update_kit. Read-only. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_update_kit',
				'description' => 'Patch the active Elementor kit (Site Settings). IMPORTANT: the keys you send are MERGED into the existing kit settings, not replaced — the kit holds every Site Settings tab in one object (global colors beside typography beside layout beside custom CSS), so sending only system_colors leaves typography and everything else untouched. Pass replace_settings=true only for a deliberate wholesale swap, which discards every key you do not send (all of Site Settings) and reports what it removed. Accepts any kit control key from any registered tab: global-colors (system_colors, custom_colors), global-typography (system_typography, custom_typography, default_generic_fonts), theme-style-* defaults, settings-site-identity (site_name, site_description, site_logo, site_favicon), settings-layout (container_width, space_between_widgets, default_page_template, breakpoints), settings-background, settings-lightbox, settings-page-transitions, settings-custom-css, and plugin-registered keys. Repeaters (system_colors, system_typography) carry a stable _id per row (primary, secondary, text, accent) — read the current rows with elementor_get_kit and send the full repeater array back with your edits, since a repeater value replaces the whole list. Call elementor_get_kit for current values and elementor_get_kit_schema for field types first. Set dry_run=true to preview the merged result without writing. Emits an undo token restoring the full pre-write kit settings. Site-wide change: invalidates Elementor\'s global CSS cache after writing. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'settings'         => [
							'type'        => 'object',
							'description' => 'Kit control keys to merge (or, with replace_settings, to set wholesale). Any key registered on a kit tab is accepted. Example: {"system_colors":[{"_id":"primary","title":"Primary","color":"#0053db"}]}.',
						],
						'replace_settings' => [
							'type'        => 'boolean',
							'description' => 'Replace the entire kit settings object instead of merging. Discards every Site Settings key you do not send. Default false.',
						],
						'dry_run'          => [
							'type'        => 'boolean',
							'description' => 'Preview the resulting merged settings without writing. Default false.',
						],
					],
					'required'   => [ 'settings' ],
				],
			],
			[
				'name'        => 'elementor_sync_library_type',
				'description' => 'Set the Elementor library template type on an existing elementor_library post: writes the _elementor_template_type meta AND the elementor_library_type taxonomy term together, which is what Elementor\'s own library UI keys off. Use it when a library item created outside elementor_import_template / elementor_clone_page (which already set both) has the wrong type or none — a template saved as "page" that should be a "header", say. Refuses any post that is not of type elementor_library. Returns the resolved terms after the write. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'       => [ 'type' => 'integer', 'description' => 'Elementor library post ID (post type must be elementor_library).' ],
						'template_type' => [ 'type' => 'string', 'description' => 'Template type slug to set, e.g. page, section, header, footer, popup, single, archive, loop-item. Validate against elementor_list_local_templates / the site\'s registered types.' ],
					],
					'required'   => [ 'post_id', 'template_type' ],
				],
			],
		];
	}

	
	public static function execute_tool( $name, $args ) {
		return \More_MCP\Integrations\Elementor\Runtime::execute_tool( $name, $args );
	}

	public static function invalidate_derived_state_public( $post_id ) {
		return \More_MCP\Integrations\Elementor\Runtime::invalidate_derived_state_public( $post_id );
	}

	public static function restore_kit_settings_public( array $settings ) {
		return \More_MCP\Integrations\Elementor\Runtime::restore_kit_settings_public( $settings );
	}
}
