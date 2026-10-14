<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/Elementor/Runtime.php';
require_once __DIR__ . '/Elementor/Kit.php';
require_once __DIR__ . '/Elementor/Assets.php';
require_once __DIR__ . '/Elementor/Code.php';
require_once __DIR__ . '/Elementor/Image_Alt.php';
require_once __DIR__ . '/Elementor/Css_Blocks.php';


class Elementor {

	
	public static function is_available() {
		return class_exists( '\Elementor\Plugin' );
	}

	
	public static function get_manifest() {
		return array(
			'providers'    => array( 'elementor' ),
			'capabilities' => array( 'page_building', 'site_settings' ),
			'kind'         => 'builder',
		);
	}

	
	public static function get_tools() {
		if ( ! self::is_available() ) {
			return [];
		}

		$tools = [
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
				'description' => 'Replace text in the text-bearing settings of an Elementor page. Walks the _elementor_data tree and substitutes matching strings in every widget setting that holds copy: titles, descriptions, text-editor and HTML-widget content, button and label text, captions, alt text, and the items of repeaters such as icon lists, tabs and nested accordion/tab titles. Styling, links, attributes and internal keys are left alone unless scope is "all". Inside HTML content (HTML widget, text editor, rich-text repeater fields) only the visible text between tags is matched: tags, attribute values such as href and class, <script>/<style> blocks and comments stay byte-identical, unless find itself contains < or > (it then matches across tags as written) or scope is "all". Case-sensitive by default. Atomic widgets are skipped (opaque passthrough). Returns the replacement count (substitutions actually made), matches_skipped (occurrences scope "text" left in markup, links or other settings) and which elements changed (element_id, widget_type, setting paths). When nothing matches it writes nothing and says how to widen the search. Saves the page as a WordPress revision before writing, so the prior version stays restorable with wp_restore_revision after the undo token expires; the response reports whether that revision was created.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer' ],
						'find'             => [ 'type' => 'string', 'description' => 'Text to find' ],
						'replace'          => [ 'type' => 'string', 'description' => 'Text to substitute' ],
						'case_insensitive' => [ 'type' => 'boolean', 'description' => 'Default false' ],
						'scope'            => [ 'type' => 'string', 'enum' => [ 'text', 'all' ], 'description' => 'text (default): the visible text of settings that hold copy. all: every string setting except internal keys (ids, dynamic tags), including link URLs, HTML markup and other attributes, e.g. to move a domain across a page.' ],
					],
					'required'   => [ 'post_id', 'find', 'replace' ],
				],
			],
			[
				'name'        => 'elementor_replace_image',
				'description' => 'Swap image URLs in an Elementor page across all image-bearing widgets (image widget, background image, gallery items, etc.). Optionally also remap WP attachment IDs. Returns count of replacements made. Saves the page as a WordPress revision before writing, so the prior version stays restorable with wp_restore_revision after the undo token expires; the response reports whether that revision was created.',
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
				'description' => 'Extract a simplified outline of an Elementor page: section/container hierarchy, widget types per slot, and short text snippets from text-bearing widgets. Returns JSON small enough for an AI to reason over without consuming the full _elementor_data budget (~2KB for typical pages). Useful before calling clone or replace_text to understand the structure first. On a large page pass depth, ids_only or max_nodes to keep the result small (the response reports total_nodes, and says when it was truncated). Any element a prior MCP write touched carries an mcp_edits marker (the tool, the setting keys last written, and when), so you can see which nodes are not pristine before editing and avoid clobbering a tuned element from a stale ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'   => [ 'type' => 'integer' ],
						'depth'     => [ 'type' => 'integer', 'minimum' => 0, 'description' => 'Optional. Deepest nesting level to expand (0 = top-level elements only). Elements at that level report child_count instead of children.' ],
						'max_nodes' => [ 'type' => 'integer', 'minimum' => 1, 'description' => 'Optional. Stop after this many elements, in document order. The response then carries truncated, returned_nodes and total_nodes.' ],
						'ids_only'  => [ 'type' => 'boolean', 'description' => 'Optional. Return only id, elType and widgetType for each element, dropping snippets, loop template ids and mcp_edits markers. Much smaller on large pages. Default false.' ],
					],
					'required'   => [ 'post_id' ],
				],
			],
			[
				'name'        => 'elementor_get_widget_settings',
				'description' => 'Read the full settings object for a single Elementor element (widget, container, section, or column) by its ID. Use after elementor_get_page_outline to inspect a specific element before proposing a modification. Returns element_type, widget_type (widgets only), depth in the tree, has_children flag, child_count, and the raw settings object. If the element is not found, returns found=false with the count of elements searched (helps diagnose wrong IDs). When a prior MCP write touched this element, the response also carries an mcp_edits marker (the tool, the setting keys last written, and when) so you can tell a pristine element from one an agent already changed. Requires read_post on the parent post_id.',
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
				'description' => 'Enumerate saved templates from the Elementor Library (the elementor_library custom post type). Returns id, name, type, and date_modified for each. `type` is the item\'s _elementor_template_type (kit/section/header/footer/single/archive/popup/loop-item/page/etc.), or null when the item has no type set — never a fabricated value, so list(type=T) returns exactly the items the unfiltered list labels T. Filter by type if needed. Use this before elementor_import_template to discover available templates.',
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
				'description' => 'Create a new Elementor template (in the elementor_library CPT) from a JSON payload. Accepts the structure exported by the Elementor editor (an array of section/container elements) and also a hand-authored payload with no element IDs at all. Validates top-level shape and stores the data as _elementor_data on a new template post. Every element is ASSIGNED a fresh ID: IDs you supplied in template_json are discarded to avoid collisions, and elements that arrived without one are given one, so the whole tree is addressable. The response returns `template_id`, `ids_regenerated: true`, and an `outline` carrying the assigned IDs (same shape as elementor_get_page_outline). Address elements by the outline IDs, not the ones you wrote.',
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
				'name'        => 'elementor_set_page_data',
				'description' => 'Replace the whole Elementor element tree (_elementor_data) of an existing post or page in one call, safely. Use this instead of writing _elementor_data with wp_update_post_meta when building or rebuilding a page from JSON. Every element needs a unique id, because Elementor scopes each element\'s CSS by it: an element with no id, a malformed id or a repeated one is given a fresh unique id (ids you supplied that are well-formed and unique are kept), and each replacement is listed in `ids_assigned`. The tree is validated like elementor_import_template (unregistered widget slugs are refused), and `warnings` flags settings Elementor ignores, e.g. "css_classes" on a widget (widgets use "_css_classes") or "_css_classes" on a container. The write saves a WordPress revision first, clears the page\'s CSS cache, reads the tree back, and returns an undo token plus an `outline` with the final ids. mode "append" (or "prepend") adds the elements after (or before) the existing top-level elements instead of replacing the tree: build a large page one section per call, sending each section once, while the elements already on the page keep their ids; the outline then lists only the elements added. Set dry_run to preview ids_assigned, warnings and the outline without writing. The post must be in Elementor builder mode (_elementor_edit_mode "builder") for the tree to render; a warning says so when it is not. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'  => [ 'type' => 'integer', 'description' => 'Target post or page ID.' ],
						'elements' => [
							'oneOf'       => [ [ 'type' => 'array' ], [ 'type' => 'string' ], [ 'type' => 'object' ] ],
							'description' => 'The element tree: an array of top-level elements (the _elementor_data shape), the same array as a JSON string, or an Elementor export object with a "content" array.',
						],
						'mode'     => [ 'type' => 'string', 'enum' => [ 'replace', 'append', 'prepend' ], 'description' => 'replace (default): elements become the whole tree. append / prepend: add them after / before the existing top-level elements and keep the rest of the page; on a post with no Elementor data yet this starts the tree.' ],
						'dry_run'  => [ 'type' => 'boolean', 'description' => 'Preview ids_assigned, warnings and the outline without writing. Default false.' ],
					],
					'required'   => [ 'post_id', 'elements' ],
				],
			],
			[
				'name'        => 'elementor_validate_data',
				'description' => 'Check Elementor data before it is written, without writing anything. Accepts the _elementor_data element tree (a JSON array of elements), an Elementor template export (.json: {"content": [...], "page_settings", "version", "title", "type"}), or a WordPress export file (WXR .xml) whose items carry _elementor_data. Reports JSON or XML syntax errors with line and column, and the structural problems Elementor does not report but renders wrong: missing, malformed or repeated element ids (Elementor scopes CSS by id), an unknown elType, a widget with no widgetType or one not registered on this site, children placed where Elementor drops them (a column outside a section, a non-column inside a section, a widget with children that does not nest them), settings sent as a list instead of an object, malformed __dynamic__ / __globals__, repeater items without a unique _id, link or image settings given as a plain string, and javascript: URLs. Each issue carries a code, a JSON path and the fix; valid is true only when there are no errors. target "mcp" (the default for JSON) reports id problems as warnings because elementor_set_page_data and elementor_import_template repair them; target "raw" (the default for XML) reports them as errors, for data imported or stored as-is. Run it before elementor_set_page_data, elementor_import_template or an import of an export file. Read-only; requires edit_posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'data'           => [
							'oneOf'       => [ [ 'type' => 'string' ], [ 'type' => 'array' ], [ 'type' => 'object' ] ],
							'description' => 'The data to check: a JSON string, an XML (WXR) string, or the element array / export object itself. Up to 5 MB.',
						],
						'format'         => [ 'type' => 'string', 'enum' => [ 'auto', 'elements', 'template', 'wxr' ], 'description' => 'auto (default) detects XML by a leading "<", an export object by its "content" key, and an element array otherwise.' ],
						'target'         => [ 'type' => 'string', 'enum' => [ 'mcp', 'raw' ], 'description' => 'mcp: the data goes through elementor_set_page_data / elementor_import_template, which repair ids, so id problems are warnings. raw: the data is stored as-is (WXR import, meta write), so they are errors. Defaults to raw for XML and mcp otherwise.' ],
						'check_registry' => [ 'type' => 'boolean', 'description' => 'Confirm widget slugs, and which widgets nest children, against this site\'s Elementor widget registry. Default true; false checks structure only (e.g. for a file meant for another site).' ],
					],
					'required'   => [ 'data' ],
				],
			],
			[
				'name'        => 'elementor_add_widget',
				'description' => 'Add a new widget or container to an existing Elementor page. Dual-surface: RAW (any widget_type + full settings object) or CURATED (high-frequency widget types with flat parameters the tool expands into the canonical settings object internally, saving tokens). Curated widget_types: container, heading, text-editor, button, image, image-box, icon-box, icon-list, video, divider, spacer. For any other widget_type, supply settings directly. Containers and nesting widgets (nested-accordion, nested-tabs, nested-carousel) can include children inline (one call drops parent + N children, recursive); the children of a nesting widget must be containers, one per panel/tab item, and a widget that cannot hold children is refused rather than having them dropped. Atomic widgets (Editor V4, widget_type prefixed a- or e-) pass through opaquely via the raw path. Returns the new element ID + parent context + edit URL, the setting keys written (settings_keys, never their values), the ids of inline children (children, an id-only outline) and a one-line summary; include_settings=true also returns the inserted element as written. Image alt text: Elementor shows a library image\'s alt from its attachment, not from the widget, so an image object with an attachment id and an alt (image: {id, url, alt}, also in galleries, background images and repeater rows, and in inline children) writes that alt to the attachment (_wp_attachment_image_alt) when the attachment has none; overwrite_alt=true replaces an existing one. Needs edit_post on the attachment; the response lists alt_written (with previous_alt) and alt_skipped (with a reason). Saves the page as a WordPress revision before writing, so the version without this widget stays restorable with wp_restore_revision; the response reports whether that revision was created. Cap-checked via edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'          => [ 'type' => 'integer', 'description' => 'Target post or page ID. Must be Elementor-edited.' ],
						'widget_type'      => [ 'type' => 'string', 'description' => 'Elementor widget slug (e.g. heading, button, html, wp-widget-text), or "container" for a Flexbox container.' ],
						'settings'         => [ 'type' => 'object', 'description' => 'RAW path: full Elementor settings object for this widget. When supplied, raw wins (curated params ignored). Required for non-curated widget_types.' ],
						'parent_id'        => [ 'type' => 'string', 'description' => 'Optional. Element ID to insert under. Must be a container, section, column, or a nesting widget such as nested-accordion (which accepts containers only). If omitted, appended at document top level.' ],
						'position'         => [ 'type' => 'integer', 'description' => 'Optional. Zero-indexed position within parent. If omitted, appended at end.' ],
						'flex_direction'   => [ 'type' => 'string', 'enum' => [ 'row', 'column' ], 'description' => 'Curated container: row or column. Default column.' ],
						'content_width'    => [ 'type' => 'string', 'enum' => [ 'boxed', 'full' ], 'description' => 'Curated container: boxed or full. Default boxed.' ],
						'children'         => [ 'type' => 'array', 'description' => 'Inline child definitions for a container or a nesting widget (nested-accordion, nested-tabs, nested-carousel; its children must be containers). Each item is an object with widget_type + curated params or settings, and may carry its own children.' ],
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
						'include_settings' => [ 'type' => 'boolean', 'description' => 'Also return `element`, the inserted node as written (settings and children). Default false.' ],
						'overwrite_alt'    => [ 'type' => 'boolean', 'description' => 'Replace an attachment\'s existing alt text with the alt in the widget\'s image settings. Default false: the alt is written only to an attachment that has none, because it shows everywhere the image is used.' ],
					],
					'required'   => [ 'post_id', 'widget_type' ],
				],
			],
			[
				'name'        => 'elementor_update_widget',
				'description' => 'Change the settings of one existing element (widget or container) addressed by its element ID. The read-side mirror is elementor_get_widget_settings. IMPORTANT: settings are MERGED into the element by default, not replaced. An Elementor settings object holds content and styling keys side by side (title next to title_color, typography_font_size, margin), so sending only the keys you want changed is the safe call and everything else is preserved. Pass replace_settings=true for a wholesale swap, which discards every key you did not send including all styling. Set dry_run=true to preview the change without writing. The response is compact: keys_changed, keys_removed (and keys_unchanged for keys sent with the value already stored), settings_count and a one-line summary, not the settings object; a dry run adds a per-key changes preview (values up to 200 bytes as is, larger ones by shape). Pass include_settings=true for the full settings (settings after a write, settings_before / settings_after on a dry run). expected_widget_type aborts the write unless the target is the widget type you expected, which protects against a stale element ID. An image object you send with an attachment id and an alt (image: {id, url, alt}, also in galleries and repeater rows) writes that alt to the attachment when it has none (overwrite_alt=true replaces an existing one; needs edit_post on the attachment), because Elementor shows a library image\'s alt from its attachment; alt_written / alt_skipped report it (alt_would_write on a dry run) and the undo token puts the previous alt back. Atomic elements (Editor V4, type prefixed a- or e- in widgetType or elType) are refused: their schema is not publicly documented, so a merge could corrupt them. Emits an undo token, and saves the page as a WordPress revision before writing so the prior version stays restorable with wp_restore_revision after that token expires; the response reports whether the revision was created. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'              => [ 'type' => 'integer', 'description' => 'Post or page ID holding the element.' ],
						'element_id'           => [ 'type' => 'string', 'description' => 'Element ID to update (short hex string, e.g. "a1b2c3d"). Get it from elementor_get_page_outline or elementor_get_widget_settings.' ],
						'settings'             => [ 'type' => 'object', 'description' => 'Settings keys to apply. Merged into the existing settings unless replace_settings is true. Passing a key with null removes it.' ],
						'replace_settings'     => [ 'type' => 'boolean', 'description' => 'Replace the whole settings object instead of merging. Discards every key not supplied, including styling. Default false.' ],
						'expected_widget_type' => [ 'type' => 'string', 'description' => 'Guard: abort unless the target element has this widgetType (or elType for containers). Recommended whenever the element ID came from an earlier call.' ],
						'dry_run'              => [ 'type' => 'boolean', 'description' => 'Preview the change without writing. Default false.' ],
						'include_settings'     => [ 'type' => 'boolean', 'description' => 'Also return the full settings object: settings after a write, settings_before / settings_after on a dry run. Default false: the response names the changed keys only.' ],
						'overwrite_alt'        => [ 'type' => 'boolean', 'description' => 'Replace an attachment\'s existing alt text with the alt in the image settings you send. Default false: the alt is written only to an attachment that has none.' ],
					],
					'required'   => [ 'post_id', 'element_id', 'settings' ],
				],
			],
			[
				'name'        => 'elementor_delete_widget',
				'description' => 'Remove one element (widget or container) from an Elementor page, including every element nested inside it. Deleting a container deletes its children with it, so the response reports how many elements went and dry_run is worth running first on anything with children. Atomic widgets can be deleted: removing a whole node does not require understanding its schema, unlike editing one. expected_widget_type aborts unless the target is what you expected, which matters more here than anywhere else because a stale element ID means deleting the wrong part of the page. Emits an undo token that restores the full pre-deletion tree, and saves the page as a WordPress revision before writing so the prior version stays restorable with wp_restore_revision after that token expires; the response reports whether the revision was created. Requires edit_post on the target post.',
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
				'description' => 'Move one existing element (widget or container) to a new location within the same page. Give the element to move (element_id) and a reference element (target_id) plus a position: before or after the reference, or first_child / last_child of it (child positions require the reference to be a container, section, column, or a nesting widget such as nested-accordion, which takes containers only). The element keeps its settings and its own children: this only re-parents it. Refuses moving an element into its own subtree, which would orphan the tree. expected_widget_type aborts unless the moved element is the type you expected (element IDs are per-document and shift on rebuild). Atomic (V4) elements can be moved: moving does not interpret their schema. Supports dry_run and emits an undo token restoring the full pre-move tree, and saves the page as a WordPress revision before writing so the prior version stays restorable with wp_restore_revision after that token expires; the response reports whether the revision was created. Requires edit_post on the target post.',
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
				'description' => 'Read the active Elementor kit (Site Settings): global colors (system_colors, custom_colors), global typography (system_typography, custom_typography), theme style defaults for text/buttons/images/form-fields, site identity (site_name, site_description, site_logo, site_favicon), layout (container_width, space_between_widgets, default_page_template, breakpoints), background, lightbox, page transitions, and custom CSS. These are the values shared across every page the theme renders. IMPORTANT: by default this returns a KEY INDEX, not the values — one row per settings key describing its shape (a repeater\'s row count, a string\'s length), because the full kit object is larger than a single tool result allows on a real site. Pass keys:["system_colors","custom_css"] to read specific values, or include_all:true to force the whole object. To stay under the result limit, search:"button" narrows the index (or include_all) to keys whose name contains the term, and max_chars with offset pages through a long string value such as custom_css instead of returning it whole (truncated says where each cut value continues). css_search:"hero" returns only the custom_css blocks whose text contains the term (rules, @media and other at-rule blocks, comments), each with its character offset and length; a match inside an @media block lists the inner rules that matched. Pair with elementor_get_kit_schema to learn each field\'s type and valid values before writing, and elementor_update_kit to change them. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'keys'        => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Return the actual values for just these settings keys. A key absent from the kit is reported in unset_keys (it still holds Elementor\'s default) rather than silently omitted.',
						],
						'include_all' => [
							'type'        => 'boolean',
							'description' => 'Return the complete settings object instead of the key index. Expect a very large response; prefer keys.',
						],
						'search'      => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring of the key name. Narrows the index, or include_all, to the matching keys; ignored with keys. The response reports matched_keys.',
						],
						'max_chars'   => [
							'type'        => 'integer',
							'description' => 'With keys or include_all: return at most this many characters of each top-level string value (100-100000). A cut value is listed in truncated with its length and next_offset.',
						],
						'offset'      => [
							'type'        => 'integer',
							'description' => 'Character offset to start each top-level string value from, with max_chars; pass the next_offset from truncated to continue. It applies to every string returned, so page one long key at a time.',
						],
						'css_search'  => [
							'type'        => 'string',
							'description' => 'Case-insensitive term to find in the custom_css text (not in key names; that is search). Returns matches: the top-level blocks containing it (rule, at-rule, statement or comment) with offset and length in characters, prelude (selector or at-rule header) and css; an @media / @supports block matched only inside lists the matching inner blocks under nested. At most 100 blocks; css text is capped by max_chars (default 20000), past which a block is listed with css_omitted. keys, search and include_all are ignored with it.',
						],
					],
				],
			],
			[
				'name'        => 'elementor_get_kit_schema',
				'description' => 'Get the control schema for the active Elementor kit, grouped by Site Settings tab (global-colors, global-typography, theme-style-typography, theme-style-buttons, theme-style-images, theme-style-form-fields, settings-site-identity, settings-background, settings-layout, settings-lightbox, settings-page-transitions, settings-custom-css, plus any tab a plugin registers via elementor/kit/register_tabs). Each control reports its label, type, default, options, and, for repeaters like colors and typography, the nested field definitions. IMPORTANT: by default this returns a TAB INDEX (each tab and its control count), because the complete schema is far larger than one tool result allows. Pass tab:"global-colors" to read one tab\'s controls in full, search:"typography" to find controls by id or label across every tab, or include_all:true to force everything. Read this before elementor_update_kit so the values you send match the field types Elementor expects rather than being silently dropped. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'tab'         => [
							'type'        => 'string',
							'description' => 'Return this tab\'s controls in full, e.g. "global-colors", "settings-layout". Call with no arguments first to list the tabs this site registers.',
						],
						'search'      => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring match against control ids and labels across all tabs. Use when you do not know which tab a control lives in.',
						],
						'include_all' => [
							'type'        => 'boolean',
							'description' => 'Return every control on every tab. Expect a very large response; prefer tab or search.',
						],
					],
				],
			],
			[
				'name'        => 'elementor_list_widget_types',
				'description' => 'Enumerate every Elementor widget type currently registered on THIS site, so you can discover what elementor_add_widget / elementor_update_widget can target before building a call. This is the Elementor analogue of blocks_list_types for Gutenberg: it reads Elementor\'s live widget registry, not a hardcoded list, so every installed and active addon pack (Ultimate Addons, ElementsKit, Premium Addons, The Plus Addons, Essential Addons, and any pack not yet released) contributes its widgets here automatically and becomes usable through the existing widget CRUD tools. Each entry carries name (the widget_type slug you pass to elementor_add_widget\'s RAW path), title, categories, keywords, and icon. Paginated (default 60 per page) because a site with Pro plus addon packs registers a few hundred widgets, which does not fit in one response; category and search narrow the list before paging. Pair with elementor_get_widget_type_schema to learn a widget\'s settings fields. Read-only. Requires edit_posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [
							'type'        => 'string',
							'description' => 'Optional. Return only widgets in this Elementor category (e.g. "basic", "general", "theme-elements", or an addon pack\'s own category slug).',
						],
						'search'   => [
							'type'        => 'string',
							'description' => 'Optional. Case-insensitive substring match against the widget name (slug) and title.',
						],
						'page'     => [
							'type'        => 'integer',
							'description' => 'Page number, 1-indexed. Default 1. The response reports pages and has_more.',
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Widgets per page. Default 60, max 200.',
						],
					],
				],
			],
			[
				'name'        => 'elementor_get_widget_type_schema',
				'description' => 'Return the control schema for ONE registered Elementor widget type: its field names, types, defaults, options, and (for repeater fields) nested field definitions, plus which tab (content / style / advanced) each control belongs to. This is the Elementor analogue of blocks_get_type_schema. Read it before constructing the settings object for elementor_add_widget or elementor_update_widget so the keys you send match what the widget actually accepts rather than being silently dropped. IMPORTANT: defaults to the CONTENT tab, which is what authoring a widget needs — a core widget can register 275 controls, mostly responsive styling variants, which does not fit in one tool result. Every response reports total_controls and controls_by_tab so nothing is hidden; pass tab:"style" / tab:"advanced" to read those, search:"color" to find one control across tabs, or include_all:true for everything. The schema is read from the widget class\'s own control registration, so third-party addon widgets are described exactly like core ones. Editor V4 Atomic widgets (a-*/e-*) report atomic:true with no control stack — pass their settings opaquely. Read-only. Requires edit_posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'widget_type' => [
							'type'        => 'string',
							'description' => 'The widget type slug, e.g. "heading", "call-to-action", or an addon pack\'s slug as reported by elementor_list_widget_types.',
						],
						'tab'         => [
							'type'        => 'string',
							'description' => 'Which control tab to return: "content" (the default), "style", or "advanced". See controls_by_tab in any response for what this widget actually registers.',
						],
						'search'      => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring match against control ids and labels across every tab. Use when you know the control name but not its tab.',
						],
						'include_all' => [
							'type'        => 'boolean',
							'description' => 'Return every control on every tab. Expect a very large response; prefer tab or search.',
						],
					],
					'required'   => [ 'widget_type' ],
				],
			],
			[
				'name'        => 'elementor_get_kit_fonts',
				'description' => 'List the fonts Elementor knows about: system, Google, and any registered by plugins, with their group classification, whether Google Fonts loading is enabled, and the font-display setting. Use it to pick valid font_family values before writing typography into the kit with elementor_update_kit. Elementor bundles the entire Google Fonts catalogue (~1,900 families), so this is paginated at 100 per page and the usual call is search:"Roboto" to check one family, or group:"system" to browse one group. fonts_by_group reports how many families each group holds. Read-only. Requires edit_theme_options.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'   => [
							'type'        => 'string',
							'description' => 'Case-insensitive substring match against the font family name. The direct way to check whether a font is available.',
						],
						'group'    => [
							'type'        => 'string',
							'description' => 'Return only families in this group, e.g. "system", "googlefonts", "earlyaccess", or a group a plugin registers. See fonts_by_group.',
						],
						'page'     => [
							'type'        => 'integer',
							'description' => 'Page number, 1-indexed. Default 1.',
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Families per page. Default 100, max 500.',
						],
					],
				],
			],
			[
				'name'        => 'elementor_update_kit',
				'description' => 'Patch the active Elementor kit (Site Settings). IMPORTANT: the keys you send are MERGED into the existing kit settings, not replaced. The kit holds every Site Settings tab in one object (global colors beside typography beside layout beside custom CSS), so sending only system_colors leaves typography and everything else untouched. Pass replace_settings=true only for a deliberate wholesale swap, which discards every key you do not send (all of Site Settings) and reports what it removed. Accepts any kit control key from any registered tab: global-colors (system_colors, custom_colors), global-typography (system_typography, custom_typography, default_generic_fonts), theme-style-* defaults, settings-site-identity (site_name, site_description, site_logo, site_favicon), settings-layout (container_width, space_between_widgets, default_page_template, breakpoints), settings-background, settings-lightbox, settings-custom-css, and plugin-registered keys. Repeaters (system_colors, system_typography) carry a stable _id per row (primary, secondary, text, accent): read the current rows with elementor_get_kit keys:["system_colors"] and send the full repeater array back with your edits, since a repeater value replaces the whole list. Call elementor_get_kit for current values and elementor_get_kit_schema for field types first. Set dry_run=true to preview: that returns a per-key diff of what the call would touch, not the whole settings object, since the full before/after pair does not fit in one tool result. custom_css_mode:"append" adds settings.custom_css after the kit\'s current custom CSS (on a new line) instead of replacing it, so adding one block does not mean resending the whole stylesheet; the response\'s custom_css_append says where the block starts (appended_at, a character offset) and the lengths before and after, and the result is checked, stored and undoable exactly like a replace sending that full value. After a write the response verifies the keys it wrote by reading them back; pass include_all:true if you also want the complete settings object. Emits an undo token restoring the full pre-write kit settings. Site-wide change: invalidates Elementor\'s global CSS cache after writing. Requires edit_theme_options.',
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
						'custom_css_mode'  => [
							'type'        => 'string',
							'enum'        => [ 'replace', 'append' ],
							'description' => 'replace (default): settings.custom_css becomes the kit\'s whole custom CSS. append: settings.custom_css is added after the current custom CSS. Not combinable with replace_settings.',
						],
						'dry_run'          => [
							'type'        => 'boolean',
							'description' => 'Preview without writing. Returns a per-key diff (changes) describing what each touched key would go from and to. Default false.',
						],
						'include_all'      => [
							'type'        => 'boolean',
							'description' => 'Include the complete kit settings object in the response: settings_before / settings_after on a dry_run, settings after a write. Expect a very large response.',
						],
					],
					'required'   => [ 'settings' ],
				],
			],
			[
				'name'        => 'elementor_sync_library_type',
				'description' => 'Set the Elementor library template type on an existing elementor_library post: writes the _elementor_template_type meta AND the elementor_library_type taxonomy term together, which is what Elementor\'s own library UI keys off. Use it when a library item created outside elementor_import_template / elementor_clone_page (which already set both) has the wrong type or none, a template saved as "page" that should be a "header", say. Refuses any post that is not of type elementor_library. Returns the resolved terms after the write. Requires edit_post on the target post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'       => [ 'type' => 'integer', 'description' => 'Elementor library post ID (post type must be elementor_library).' ],
						'template_type' => [ 'type' => 'string', 'description' => 'Template type slug to set, e.g. page, section, header, footer, popup, single, archive, loop-item. Validate against elementor_list_local_templates / the site\'s registered types.' ],
					],
					'required'   => [ 'post_id', 'template_type' ],
				],
			],
			[
				'name'        => 'elementor_set_template_conditions',
				'description' => 'Assign display conditions to an Elementor Pro Theme Builder template (header/footer/single/archive/...) AND rebuild the conditions cache Elementor consults at render time, so the template actually appears on the front-end. Setting the _elementor_conditions meta alone is NOT enough: Elementor resolves which document renders at a location from a separate options-backed cache (elementor_pro_theme_builder_conditions), which is only rebuilt by its own save path — never by a bare meta write. This tool routes through Elementor Pro\'s conditions_manager->save_conditions(), which writes the meta and regenerates that cache in one step. Conditions use Elementor\'s slash-joined form, e.g. "include/general" (everywhere), "include/singular/post", "exclude/archive". Pass an empty conditions array to clear a template\'s conditions. Also accepts an Elementor Custom Code snippet (elementor_snippet), under the same gates as elementor_update_code. The call is idempotent: re-sending a template\'s existing conditions succeeds and reports unchanged:true, since success is determined by reading the stored conditions back rather than by whether the meta row was rewritten. Requires Elementor Pro active and edit_post on the template. Returns the saved conditions and whether the cache was rebuilt.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'    => [ 'type' => 'integer', 'description' => 'Theme Builder template post ID (an elementor_library post with a Theme Builder type such as header/footer/single/archive).' ],
						'conditions' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Display-condition strings in Elementor\'s slash form. "include/general" targets the whole site; "include/singular/post" a post type; "exclude/..." subtracts. An empty array clears all conditions on the template.',
						],
					],
					'required'   => [ 'post_id', 'conditions' ],
				],
			],
		];

		
		
		
		
		if ( \Mordenhost_MCP\Integrations\Elementor\Assets::is_available() ) {
			$tools = array_merge( $tools, self::asset_tools() );
		}

		
		
		
		
		
		if ( \Mordenhost_MCP\Integrations\Elementor\Code::is_available() ) {
			$tools = array_merge( $tools, self::code_tools() );
		}

		return $tools;
	}

	
	private static function asset_tools() {
		return [
			[
				'name'        => 'elementor_list_fonts',
				'description' => 'List Elementor Pro Custom Fonts. Returns each font\'s family name, font_id, variation count, and type. Read-only. Requires Elementor Pro.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_get_font',
				'description' => 'Get one Elementor Pro Custom Font by font_id, with its full variation list. Each variation reports weight, style, type, and the uploaded font files (woff2/woff/ttf/eot/svg) with resolved URLs and attachment IDs. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'font_id' => [ 'type' => 'integer', 'description' => 'The elementor_font post ID (from elementor_list_fonts).' ],
					],
					'required'   => [ 'font_id' ],
				],
			],
			[
				'name'        => 'elementor_create_font',
				'description' => 'Create an Elementor Pro Custom Font. The family becomes the font post title; each variation names a weight (normal|bold|100..900) and style (normal|italic|oblique) and references uploaded font files. Upload the font files first with wp_upload_media / wp_upload_media_from_url to obtain attachment IDs, then pass them here. Elementor\'s @font-face CSS is regenerated so pages render the font immediately. Not destructive: no confirmation. Returns font_id.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'family'     => [ 'type' => 'string', 'description' => 'Font-family name (becomes the post title).' ],
						'variations' => [ 'type' => 'array', 'description' => 'Array of variation objects: { weight, style, type?, files: { woff2?, woff?, ttf?, eot?, svg? } }. Each file is an attachment ID, a URL, or a { url, id } object.' ],
					],
					'required'   => [ 'family' ],
				],
			],
			[
				'name'        => 'elementor_update_font',
				'description' => 'Update an Elementor Pro Custom Font. Rename via family; replace the variation list via variations (the array replaces the stored list wholesale, same rule as the kit repeater fields — a variation has no stable key to merge on). The @font-face CSS is regenerated. Emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'font_id'    => [ 'type' => 'integer', 'description' => 'The elementor_font post ID.' ],
						'family'     => [ 'type' => 'string', 'description' => 'New font-family name. Omit to leave unchanged.' ],
						'variations' => [ 'type' => 'array', 'description' => 'Replacement variation list (wholesale). Omit to leave unchanged.' ],
					],
					'required'   => [ 'font_id' ],
				],
			],
			[
				'name'        => 'elementor_delete_font',
				'description' => 'Delete an Elementor Pro Custom Font. Pass dry_run=true first to preview how many kit/page slots reference the family before committing. The real delete emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'font_id' => [ 'type' => 'integer', 'description' => 'The elementor_font post ID.' ],
						'dry_run' => [ 'type' => 'boolean', 'description' => 'Preview the blast radius without deleting. Default false.' ],
					],
					'required'   => [ 'font_id' ],
				],
			],
			[
				'name'        => 'elementor_list_icon_sets',
				'description' => 'List Elementor Pro Custom Icon sets. Returns each set\'s set_id, label, CSS prefix, and icon count. Read-only. Requires Elementor Pro.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_get_icon_set',
				'description' => 'Get one Elementor Pro Custom Icon set by set_id, with its CSS prefix and the names of the icons it contains. Read-only. There is no create tool: icon sets are uploaded IcoMoon/Fontello/Fontastic zips, which an agent has no way to author.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'set_id' => [ 'type' => 'integer', 'description' => 'The elementor_icons post ID (from elementor_list_icon_sets).' ],
					],
					'required'   => [ 'set_id' ],
				],
			],
			[
				'name'        => 'elementor_delete_icon_set',
				'description' => 'Delete an Elementor Pro Custom Icon set. Pass dry_run=true first to preview. The real delete prunes the set from Elementor\'s icon-sets config cache and emits an undo token.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'set_id'  => [ 'type' => 'integer', 'description' => 'The elementor_icons post ID.' ],
						'dry_run' => [ 'type' => 'boolean', 'description' => 'Preview without deleting. Default false.' ],
					],
					'required'   => [ 'set_id' ],
				],
			],
		];
	}

	
	private static function code_tools() {
		$conditions_doc = 'Display conditions in Elementor\'s form, e.g. ["include/general"] (every page), ["include/singular/page"], ["include/singular/page/42"], ["exclude/singular/post"]. A snippet with no conditions runs on every page. Elementor\'s conditions cache is rebuilt so the change takes effect.';
		$tools = [
			[
				'name'        => 'elementor_list_code',
				'description' => 'List Elementor Pro Custom Code snippets (site-wide HTML/JS that Elementor injects into head or body). Returns code_id, title, location, priority, active state and display conditions, plus writes_enabled: whether this site lets an agent create or change Custom Code (the "Site-wide code" switch under Mordenhost MCP > Access, which also needs manage_options and unfiltered_html). Requires the Elementor Custom Code module.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new \stdClass(),
				],
			],
			[
				'name'        => 'elementor_get_code',
				'description' => 'Get one Elementor Pro Custom Code snippet by code_id: location, priority, active state, display conditions and the exact HTML/JS body. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'code_id' => [ 'type' => 'integer', 'description' => 'The elementor_snippet post ID (from elementor_list_code).' ],
					],
					'required'   => [ 'code_id' ],
				],
			],
		];

		if ( ! \Mordenhost_MCP\Integrations\Elementor\Code::toggle_on() ) {
			return $tools;
		}

		$tools[] = [
			'name'        => 'elementor_create_code',
			'description' => 'Create an Elementor Pro Custom Code snippet: site-wide HTML/JS (a tracking pixel, meta tag, CSS in a <style> tag, a script) that Elementor itself prints at the chosen location. Use this, not an HTML widget on a page, for code that belongs on every page. The body is stored byte for byte (backslashes and regex escapes included); a PHP open tag is refused. Two-part: the first call returns a preview, repeat it with confirm=true and confirm_slug. Saved inactive unless active=true. Returns an undo token. Needs the "Site-wide code" switch, manage_options and unfiltered_html.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'title'        => [ 'type' => 'string', 'description' => 'Snippet title, shown in Elementor > Custom Code.' ],
					'code'         => [ 'type' => 'string', 'description' => 'The HTML/JS body, exactly as it should be printed. Wrap CSS in <style> and JS in <script>.' ],
					'location'     => [ 'type' => 'string', 'enum' => [ 'elementor_head', 'elementor_body_start', 'elementor_body_end' ], 'description' => 'Where Elementor prints it. Default elementor_head.' ],
					'priority'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'Order within the location, 1-10, lower prints first. Default 1.' ],
					'conditions'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => $conditions_doc ],
					'active'       => [ 'type' => 'boolean', 'description' => 'true publishes it so it runs immediately. Default false (saved as a draft).' ],
					'confirm'      => [ 'type' => 'boolean', 'description' => 'Set true (with confirm_slug) to apply. Omit for a preview.' ],
					'confirm_slug' => [ 'type' => 'string', 'description' => 'Echo the target from the preview to confirm.' ],
				],
				'required'   => [ 'title', 'code' ],
			],
		];
		$tools[] = [
			'name'        => 'elementor_update_code',
			'description' => 'Change an Elementor Pro Custom Code snippet. Only the fields you pass change. active=true publishes it (it runs immediately), active=false deactivates it. The body is stored byte for byte; if storage ever differs, the response names the first differing byte. Two-part: preview first, then repeat with confirm=true and confirm_slug ("elementor-code-<code_id>"). Returns an undo token. Same gates as elementor_create_code.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'code_id'      => [ 'type' => 'integer', 'description' => 'The elementor_snippet post ID.' ],
					'title'        => [ 'type' => 'string', 'description' => 'New title. Omit to leave unchanged.' ],
					'code'         => [ 'type' => 'string', 'description' => 'New HTML/JS body, sent in full. Omit to leave unchanged.' ],
					'location'     => [ 'type' => 'string', 'enum' => [ 'elementor_head', 'elementor_body_start', 'elementor_body_end' ], 'description' => 'New location. Omit to leave unchanged.' ],
					'priority'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'New priority, 1-10. Omit to leave unchanged.' ],
					'conditions'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => $conditions_doc . ' Pass [] to clear. Omit to leave unchanged.' ],
					'active'       => [ 'type' => 'boolean', 'description' => 'true to activate, false to deactivate. Omit to leave unchanged.' ],
					'confirm'      => [ 'type' => 'boolean', 'description' => 'Set true (with confirm_slug) to apply. Omit for a preview.' ],
					'confirm_slug' => [ 'type' => 'string', 'description' => 'Echo the target from the preview to confirm.' ],
				],
				'required'   => [ 'code_id' ],
			],
		];
		$tools[] = [
			'name'        => 'elementor_delete_code',
			'description' => 'Delete an Elementor Pro Custom Code snippet permanently. Pass dry_run=true first to preview. The delete returns an undo token that re-creates the snippet with its code, location, priority, conditions and active state. Same gates as elementor_create_code.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'code_id' => [ 'type' => 'integer', 'description' => 'The elementor_snippet post ID.' ],
					'dry_run' => [ 'type' => 'boolean', 'description' => 'Preview without deleting. Default false.' ],
				],
				'required'   => [ 'code_id' ],
			],
		];
		return $tools;
	}

	
	public static function execute_tool( $name, $args ) {
		return \Mordenhost_MCP\Integrations\Elementor\Runtime::execute_tool( $name, $args );
	}

	public static function invalidate_derived_state_public( $post_id ) {
		return \Mordenhost_MCP\Integrations\Elementor\Runtime::invalidate_derived_state_public( $post_id );
	}

	public static function restore_kit_settings_public( array $settings ) {
		return \Mordenhost_MCP\Integrations\Elementor\Kit::restore_kit_settings_public( $settings );
	}

	
	public static function undo_asset_write( array $snapshot ) {
		return \Mordenhost_MCP\Integrations\Elementor\Assets::undo_asset_write( $snapshot );
	}
}
