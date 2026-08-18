<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor MCP Integration
 *
 * Registers MCP tools for the Elementor page builder. Only loaded when Elementor
 * is active. Strategy: never generate Elementor JSON from scratch — always work
 * from an existing-known-good source. Atomic widgets (Editor V4, Elementor 4.0+)
 * pass through as opaque blobs since their JSON schema is not publicly documented.
 *
 * Tools:
 *  - elementor_clone_page         — duplicate an existing page with fresh element IDs
 *  - elementor_replace_text       — bulk text substitution across widget settings
 *  - elementor_replace_image      — image URL swap across image-bearing widgets
 *  - elementor_get_page_outline   — extract simplified structure for AI reasoning (<2KB)
 *  - elementor_get_widget_settings — read one element's settings by ID
 *  - elementor_list_local_templates — enumerate saved templates from the library
 *  - elementor_import_template    — create a new template from a JSON payload
 *  - elementor_add_widget         — insert a widget or container into an existing page
 *  - elementor_update_widget      — change one element's settings, merged by default
 *  - elementor_delete_widget      — remove one element and its descendants
 *  - elementor_move_widget        — re-parent/reorder one existing element within a page
 *  - elementor_get_loop_template  — resolve a Loop Grid/Carousel widget to its loop-item doc
 *
 * Scope of "editing": the classic and Container models are fully editable. A Loop
 * Grid's loop-item template is a separate elementor_library document — resolve it
 * with elementor_get_loop_template, then edit it by its post_id with the ordinary
 * tools. Editor V4 Atomic widgets/containers (a-* / e-*) remain edit-refused: their
 * settings schema is not publicly documented, so a merge could silently corrupt
 * them. They can still be moved and deleted (neither interprets the schema).
 *
 * Every tool that writes _elementor_data must call invalidate_derived_state()
 * afterwards. These writes go through update_post_meta() directly rather than
 * Elementor's Document::save(), so nothing else clears the Post CSS and element
 * caches Elementor derives from that data — and a stale cache means the page
 * renders the previous version while the tool reports success. See that method
 * for why it uses Elementor's own API rather than deleting the meta keys.
 */
class Elementor {

	/**
	 * Check if Elementor is available.
	 */
	public static function is_available() {
		return class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Get tool definitions for MCP tools/list response.
	 */
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
		];
	}

	/**
	 * Execute an Elementor MCP tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Tool arguments.
	 * @return mixed Result data.
	 * @throws \Exception If tool fails.
	 */
	public static function execute_tool( $name, $args ) {
		// Umbrella cap check runs BEFORE is_available for anti-fingerprint: unprivileged
		// callers get "no permission" not "Elementor is not active", so plugin presence
		// is not leaked to callers who couldn't use any tool anyway. Every Elementor tool
		// requires at least edit_posts (per-tool cap checks below tighten further for
		// object-level ops like read_post / edit_post on a specific ID).
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'You do not have permission to use Elementor tools.' );
		}

		if ( ! self::is_available() ) {
			throw new \Exception( 'Elementor is not active' );
		}

		switch ( $name ) {
			case 'elementor_clone_page':
				return self::clone_page( $args );

			case 'elementor_replace_text':
				return self::replace_text( $args );

			case 'elementor_replace_image':
				return self::replace_image( $args );

			case 'elementor_get_page_outline':
				return self::get_page_outline( $args );

			case 'elementor_get_widget_settings':
				return self::get_widget_settings( $args );

			case 'elementor_list_local_templates':
				return self::list_local_templates( $args );

			case 'elementor_import_template':
				return self::import_template( $args );

			case 'elementor_add_widget':
				return self::add_widget( $args );

			case 'elementor_update_widget':
				return self::update_widget( $args );

			case 'elementor_delete_widget':
				return self::delete_widget( $args );

			case 'elementor_move_widget':
				return self::move_widget( $args );

			case 'elementor_get_loop_template':
				return self::get_loop_template( $args );

			default:
				throw new \Exception( 'Unknown Elementor tool: ' . esc_html( $name ) );
		}
	}

	// ============================================================
	// update_widget / delete_widget — addressed single-element writes
	// ============================================================

	/**
	 * Change one element's settings in place.
	 *
	 * The design commitment this file opens with — never generate Elementor JSON
	 * from scratch, always work from an existing-known-good source — permits this
	 * and always did. The element already exists and already has a valid shape;
	 * only values inside its settings object change. It is a narrower operation
	 * than elementor_replace_text, which pattern-matches across every widget on
	 * the page: this one touches exactly one addressed node.
	 *
	 * Merge is the default, and that is the important decision here. An Elementor
	 * settings object holds content and presentation side by side — `title` sits
	 * next to `title_color`, `typography_font_size`, `_margin`, and often dozens
	 * more once someone has styled the widget in the editor. A caller who wants
	 * to fix a typo in a heading and sends `{ title: 'New' }` under replace
	 * semantics would silently discard all of that styling, and the page would
	 * render unstyled with no error. Replace is still available, because
	 * deliberately clearing a widget back to defaults is a real intent, but it
	 * has to be asked for.
	 *
	 * @throws \Exception When the post has no Elementor data, the element does
	 *                    not resolve, the target is atomic, or the guard fails.
	 */
	private static function update_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		if ( ! isset( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
			throw new \Exception( 'settings is required and must be an object of setting keys to apply.' );
		}

		$tree    = self::load_tree( $post_id );
		$current = self::find_element_by_id( $tree, $element_id );
		if ( $current === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $current, $args, 'update' );

		// Atomic widgets and containers are refused for edits specifically. Their
		// settings schema is not publicly documented, so a merge cannot know whether
		// a key it is writing alongside means what it appears to mean. Widgets carry
		// their type in widgetType; Atomic containers carry it directly in elType.
		// Deleting either is fine — see delete_widget.
		$widget_type   = (string) ( $current['widgetType'] ?? '' );
		$detected_type = $widget_type !== ''
			? $widget_type
			: (string) ( $current['elType'] ?? '' );
		if ( self::is_atomic_widget_type( $detected_type ) ) {
			throw new \Exception( sprintf(
				'Element %s is an Elementor V4 atomic element (%s). Its settings schema is not publicly documented, so More MCP will not edit it rather than risk corrupting it — the same reason elementor_replace_text skips atomic widgets. Edit it in the Elementor editor, or delete and re-add it.',
				esc_html( $element_id ),
				esc_html( $detected_type )
			) );
		}

		$existing = ( isset( $current['settings'] ) && is_array( $current['settings'] ) )
			? $current['settings']
			: [];
		$replace  = ! empty( $args['replace_settings'] );

		if ( $replace ) {
			$new_settings = $args['settings'];
		} else {
			$new_settings = $existing;
			foreach ( $args['settings'] as $key => $value ) {
				// An explicit null removes the key, which is how a caller resets
				// one setting to Elementor's default without wiping the rest.
				if ( null === $value ) {
					unset( $new_settings[ $key ] );
					continue;
				}
				$new_settings[ $key ] = $value;
			}
		}

		$removed_keys = $replace
			? array_values( array_diff( array_keys( $existing ), array_keys( (array) $new_settings ) ) )
			: array_values( array_filter( array_keys( $args['settings'] ), static function ( $k ) use ( $args ) {
				return null === $args['settings'][ $k ];
			} ) );

		// Validate the element as it will look AFTER the merge, before writing. The
		// element already exists and was already valid, but a caller can merge away
		// a Theme Builder widget's __dynamic__ binding (e.g. replace_settings, or an
		// explicit null on the binding key) and turn a live widget into a silent
		// placeholder. Build the post-merge element shape and run the same document
		// validator the other write paths use. __dynamic__ is preserved byte-for-byte
		// by the merge above — the validator only reads it, never rewrites it.
		$merged_element              = $current;
		$merged_element['settings']  = $new_settings;
		// allow_unavailable: the widget already exists and was valid, so a
		// transiently unreachable registry must not block an edit — only the
		// dynamic-binding check is load-bearing here.
		$doc_errors = self::validate_document( [ $merged_element ], true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'          => true,
				'written'          => false,
				'post_id'          => $post_id,
				'element_id'       => $element_id,
				'element_type'     => (string) ( $current['elType'] ?? 'unknown' ),
				'widget_type'      => $widget_type !== '' ? $widget_type : null,
				'mode'             => $replace ? 'replace' : 'merge',
				'settings_before'  => $existing,
				'settings_after'   => $new_settings,
				'keys_removed'     => $removed_keys,
			];
		}

		$updated_tree = self::replace_element_settings( $tree, $element_id, $new_settings );

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_update_widget on element %s of post %d (%s mode)',
				$element_id,
				$post_id,
				$replace ? 'replace' : 'merge'
			),
			'target'       => [ 'post_id' => $post_id ],
			// Snapshot the whole tree, not just the one element's settings. A
			// partial snapshot could not restore correctly if the tree changed
			// shape between the write and the undo.
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		self::save_tree( $post_id, $updated_tree );
		$inval = self::invalidate_derived_state( $post_id );

		// Read back out of storage rather than echoing what we sent, matching
		// the read-after-write contract the meta and SEO tools already have.
		$verify_tree = self::load_tree( $post_id );
		$verify_el   = self::find_element_by_id( $verify_tree, $element_id );
		$stored      = ( $verify_el !== null && isset( $verify_el['settings'] ) && is_array( $verify_el['settings'] ) )
			? $verify_el['settings']
			: [];

		return self::with_invalidation( [
			'success'      => true,
			'written'      => true,
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'element_type' => (string) ( $current['elType'] ?? 'unknown' ),
			'widget_type'  => $widget_type !== '' ? $widget_type : null,
			'mode'         => $replace ? 'replace' : 'merge',
			'settings'     => $stored,
			'keys_removed' => $removed_keys,
			'verified'     => $stored == $new_settings, // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- a JSON round-trip changes int/string types without changing meaning.
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	/**
	 * Remove one element and everything nested inside it.
	 *
	 * Atomic widgets are allowed here, unlike in update_widget: removing a whole
	 * node does not require understanding its schema, only its boundaries.
	 *
	 * @throws \Exception When the post has no Elementor data, the element does
	 *                    not resolve, or the guard fails.
	 */
	private static function delete_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}

		$tree    = self::load_tree( $post_id );
		$current = self::find_element_by_id( $tree, $element_id );
		if ( $current === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $current, $args, 'delete' );

		$widget_type = (string) ( $current['widgetType'] ?? '' );
		// Counted before the removal, because afterwards there is nothing to
		// count. A container can take a large subtree with it, and a caller who
		// only read the outline's top level may not realise how much.
		$descendants = self::count_descendants( $current );

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'             => true,
				'written'             => false,
				'post_id'             => $post_id,
				'element_id'          => $element_id,
				'element_type'        => (string) ( $current['elType'] ?? 'unknown' ),
				'widget_type'         => $widget_type !== '' ? $widget_type : null,
				'descendants_removed' => $descendants,
				'total_removed'       => $descendants + 1,
				'outline_removed'     => self::build_outline( [ $current ], 0 ),
			];
		}

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_delete_widget removed element %s (+%d descendants) from post %d',
				$element_id,
				$descendants,
				$post_id
			),
			'target'       => [ 'post_id' => $post_id ],
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		$updated_tree = self::remove_element_by_id( $tree, $element_id );
		self::save_tree( $post_id, $updated_tree );
		$inval = self::invalidate_derived_state( $post_id );

		// Read back and confirm the element is actually gone.
		$verify_tree = self::load_tree( $post_id );
		$still_there = self::find_element_by_id( $verify_tree, $element_id ) !== null;

		return self::with_invalidation( [
			'success'             => true,
			'written'             => true,
			'post_id'             => $post_id,
			'element_id'          => $element_id,
			'element_type'        => (string) ( $current['elType'] ?? 'unknown' ),
			'widget_type'         => $widget_type !== '' ? $widget_type : null,
			'descendants_removed' => $descendants,
			'total_removed'       => $descendants + 1,
			'verified'            => ! $still_there,
			'undo'                => $undo,
			'edit_url'            => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	/**
	 * Move one existing element to a new location within the same page.
	 *
	 * A move is remove-then-insert on one tree: capture the source subtree, remove
	 * it, then splice it in relative to the target. Reuses find_element_by_id,
	 * remove_element_by_id, and the insert helpers — the same the add and delete
	 * paths use — so the tree-spine rebuild semantics are identical.
	 */
	private static function move_widget( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		$target_id  = isset( $args['target_id'] ) ? (string) $args['target_id'] : '';
		$position   = isset( $args['position'] ) ? (string) $args['position'] : '';
		if ( $post_id <= 0 || $element_id === '' || $target_id === '' ) {
			throw new \Exception( 'post_id, element_id, and target_id are required.' );
		}
		if ( ! in_array( $position, [ 'before', 'after', 'first_child', 'last_child' ], true ) ) {
			throw new \Exception( 'position must be one of: before, after, first_child, last_child.' );
		}
		if ( $element_id === $target_id ) {
			throw new \Exception( 'element_id and target_id must differ — an element cannot be moved relative to itself.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}

		$tree   = self::load_tree( $post_id );
		$source = self::find_element_by_id( $tree, $element_id );
		if ( $source === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Element IDs are per-document and shift when a page is rebuilt — re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		self::assert_expected_type( $source, $args, 'move' );

		$target = self::find_element_by_id( $tree, $target_id );
		if ( $target === null ) {
			throw new \Exception( 'target_id not found in this page: ' . esc_html( $target_id ) );
		}

		// Refuse moving an element into its own subtree — it would detach that
		// subtree from the document entirely (the target vanishes with the source
		// on removal, and the re-insert has nowhere to land).
		if ( self::find_element_by_id( [ $source ], $target_id ) !== null ) {
			throw new \Exception( 'Cannot move an element into its own subtree; target_id is a descendant of element_id.' );
		}

		// Child positions require a container-like target.
		if ( in_array( $position, [ 'first_child', 'last_child' ], true ) ) {
			$target_type = (string) ( $target['elType'] ?? '' );
			if ( ! in_array( $target_type, [ 'container', 'section', 'column' ], true ) ) {
				throw new \Exception( sprintf(
					'position "%s" requires target_id to be a container, section, or column. Found: %s.',
					esc_html( $position ),
					esc_html( $target_type !== '' ? $target_type : 'unknown' )
				) );
			}
		}

		$widget_type = (string) ( $source['widgetType'] ?? '' );

		if ( ! empty( $args['dry_run'] ) ) {
			return [
				'dry_run'      => true,
				'written'      => false,
				'post_id'      => $post_id,
				'element_id'   => $element_id,
				'target_id'    => $target_id,
				'position'     => $position,
				'element_type' => (string) ( $source['elType'] ?? 'unknown' ),
				'widget_type'  => $widget_type !== '' ? $widget_type : null,
			];
		}

		$undo = \More_MCP\MCP\Undo_Store::store( [
			'op'           => 'elementor_element_write',
			'summary'      => sprintf(
				'elementor_move_widget moved element %s %s %s in post %d',
				$element_id,
				$position,
				$target_id,
				$post_id
			),
			'target'       => [ 'post_id' => $post_id ],
			'pre_op_state' => [ 'elementor_data' => wp_json_encode( $tree ) ],
		] );

		// Remove the source, then insert its captured copy relative to the target.
		$without = self::remove_element_by_id( $tree, $element_id );
		$moved   = self::insert_relative_to( $without, $target_id, $position, $source );

		self::save_tree( $post_id, $moved );
		$inval = self::invalidate_derived_state( $post_id );

		// Read back and confirm the element now sits under the expected parent.
		$verify_tree     = self::load_tree( $post_id );
		$verify_parent   = self::parent_id_of( $verify_tree, $element_id );
		$expected_parent = in_array( $position, [ 'first_child', 'last_child' ], true )
			? $target_id
			: self::parent_id_of( $verify_tree, $target_id );
		$verified = self::find_element_by_id( $verify_tree, $element_id ) !== null
			&& $verify_parent === $expected_parent;

		return self::with_invalidation( [
			'success'      => true,
			'written'      => true,
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'target_id'    => $target_id,
			'position'     => $position,
			'element_type' => (string) ( $source['elType'] ?? 'unknown' ),
			'widget_type'  => $widget_type !== '' ? $widget_type : null,
			'verified'     => $verified,
			'undo'         => $undo,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		], $inval );
	}

	// PLACEHOLDER_LOOP_HELPERS

	/**
	 * Insert $new_element relative to the element identified by $target_id.
	 * before/after place it as a sibling of the target; first_child/last_child
	 * place it inside the target's own elements list.
	 */
	private static function insert_relative_to( $tree, $target_id, $position, $new_element ) {
		if ( 'first_child' === $position || 'last_child' === $position ) {
			$pos = 'first_child' === $position ? 0 : null;
			return self::walk_and_insert( $tree, $target_id, $pos, $new_element );
		}
		return self::walk_and_insert_sibling( $tree, $target_id, $position, $new_element );
	}

	/**
	 * Sibling insert: walk to the list that directly contains $target_id and
	 * splice $new_element before or after it. Rebuilds the tree spine.
	 */
	private static function walk_and_insert_sibling( $list, $target_id, $position, $new_element ) {
		if ( ! is_array( $list ) ) {
			return $list;
		}
		$index = null;
		foreach ( $list as $i => $el ) {
			if ( is_array( $el ) && isset( $el['id'] ) && (string) $el['id'] === (string) $target_id ) {
				$index = $i;
				break;
			}
		}
		if ( $index !== null ) {
			$insert_at = 'after' === $position ? $index + 1 : $index;
			array_splice( $list, $insert_at, 0, [ $new_element ] );
			return array_values( $list );
		}
		$out = [];
		foreach ( $list as $el ) {
			if ( is_array( $el ) && isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_and_insert_sibling( $el['elements'], $target_id, $position, $new_element );
			}
			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Return the id of the element that directly contains $child_id, or '' when
	 * the child sits at top level or is absent. Used for move read-back verify.
	 */
	private static function parent_id_of( $tree, $child_id, $parent_id = '' ) {
		if ( ! is_array( $tree ) ) {
			return '';
		}
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $child_id ) {
				return (string) $parent_id;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::parent_id_of( $el['elements'], $child_id, (string) ( $el['id'] ?? '' ) );
				if ( $found !== '' ) {
					return $found;
				}
			}
		}
		return '';
	}

	/**
	 * Read the loop-item template a loop widget renders. Elementor Pro's Loop Grid
	 * and Loop Carousel store the chosen loop-item document's post ID under a
	 * template_id setting; the item itself is an ordinary elementor_library
	 * document, editable through the normal post-scoped tools by that ID.
	 */
	private static function get_loop_template( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';
		if ( $post_id <= 0 || $element_id === '' ) {
			throw new \Exception( 'post_id and element_id are required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$tree    = self::load_tree( $post_id );
		$element = self::find_element_by_id( $tree, $element_id );
		if ( $element === null ) {
			throw new \Exception( sprintf(
				'Element %s not found in post %d. Re-read the page with elementor_get_page_outline.',
				esc_html( $element_id ),
				$post_id
			) );
		}

		$widget_type = (string) ( $element['widgetType'] ?? '' );
		$loop_id     = self::loop_template_id_of( $element );
		if ( $loop_id <= 0 ) {
			return [
				'has_loop_template' => false,
				'post_id'           => $post_id,
				'element_id'        => $element_id,
				'widget_type'       => $widget_type !== '' ? $widget_type : null,
				'message'           => 'This widget has no template_id set, so it renders no loop-item template. Loop Grid/Carousel widgets carry a template_id once a loop template is chosen in the editor.',
			];
		}

		$loop_post = get_post( $loop_id );
		if ( ! $loop_post ) {
			return [
				'has_loop_template' => false,
				'post_id'           => $post_id,
				'element_id'        => $element_id,
				'widget_type'       => $widget_type !== '' ? $widget_type : null,
				'loop_post_id'      => $loop_id,
				'message'           => sprintf( 'template_id %d is set but that post no longer exists.', $loop_id ),
			];
		}

		$loop_tree    = get_post_meta( $loop_id, '_elementor_data', true );
		$loop_outline = [];
		if ( ! empty( $loop_tree ) ) {
			$decoded = is_string( $loop_tree ) ? json_decode( $loop_tree, true ) : $loop_tree;
			if ( is_array( $decoded ) ) {
				$loop_outline = self::build_outline( $decoded, 0 );
			}
		}

		return [
			'has_loop_template' => true,
			'post_id'           => $post_id,
			'element_id'        => $element_id,
			'widget_type'       => $widget_type !== '' ? $widget_type : null,
			'loop_post_id'      => $loop_id,
			'template_type'     => get_post_meta( $loop_id, '_elementor_template_type', true ) ?: null,
			'title'             => $loop_post->post_title,
			'outline'           => $loop_outline,
			'edit_hint'         => sprintf(
				'Edit the loop item by passing loop_post_id (%d) as post_id to elementor_get_widget_settings / update_widget / add_widget / delete_widget / move_widget.',
				$loop_id
			),
		];
	}

	/**
	 * Extract a loop widget's referenced loop-item document ID from its settings.
	 * Elementor stores it under `template_id`. Returns 0 when absent or non-positive.
	 */
	private static function loop_template_id_of( $element ) {
		$settings = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : [];
		if ( ! isset( $settings['template_id'] ) ) {
			return 0;
		}
		return (int) $settings['template_id'];
	}

	/**
	 * Enforce the expected_widget_type guard.
	 *
	 * Element IDs are per-document and shift when a page is rebuilt, so an ID a
	 * caller holds from an earlier read may now address a different element. For
	 * a delete that means removing the wrong part of the page, which is why the
	 * tool description recommends the guard for updates and states it strongly
	 * for deletes.
	 *
	 * Compares against widgetType for widgets and elType for containers,
	 * sections, and columns, so 'container' is a usable expected value.
	 *
	 * @throws \Exception When the guard is supplied and does not match.
	 */
	private static function assert_expected_type( array $element, array $args, string $operation ) {
		if ( empty( $args['expected_widget_type'] ) || ! is_string( $args['expected_widget_type'] ) ) {
			return;
		}
		$expected = $args['expected_widget_type'];
		$el_type  = (string) ( $element['elType'] ?? 'unknown' );
		$actual   = ( 'widget' === $el_type )
			? (string) ( $element['widgetType'] ?? '' )
			: $el_type;

		if ( $actual === $expected ) {
			return;
		}
		throw new \Exception( sprintf(
			'Refusing to %s: expected_widget_type was "%s" but element %s is "%s". Element IDs shift when a page is rebuilt, so this usually means the ID is stale — re-read the page with elementor_get_page_outline.',
			esc_html( $operation ),
			esc_html( $expected ),
			esc_html( (string) ( $element['id'] ?? '' ) ),
			esc_html( $actual !== '' ? $actual : 'unknown' )
		) );
	}

	/**
	 * Whether a type identifier belongs to an Editor V4 Atomic element.
	 *
	 * Atomic widgets carry this identifier in widgetType, while Atomic containers
	 * carry it directly in elType. Shared by replace_text (which skips Atomic
	 * widgets), add_widget (which passes raw Atomic widgets through opaquely), and
	 * update_widget (which refuses both forms), so the prefix definition cannot
	 * drift between callers.
	 */
	private static function is_atomic_widget_type( $widget_type ) {
		$widget_type = (string) $widget_type;
		return strpos( $widget_type, 'a-' ) === 0 || strpos( $widget_type, 'e-' ) === 0;
	}

	/**
	 * Theme Builder dynamic widgets — the `theme-*` widgets Elementor Pro (and the
	 * PRO Elements fork) register only inside a Theme Builder template. Each entry
	 * names the settings control that carries the dynamic binding and whether that
	 * binding is mandatory for the widget to render live post/site data instead of
	 * an editor placeholder.
	 *
	 * This is an explicit catalogue, deliberately, in the same spirit as the SEO
	 * subsystem's Fields.php: a `theme-*` prefix alone does not tell you which
	 * control holds the binding (title-family widgets bind `title`, image-family
	 * widgets bind `image`, excerpt binds `excerpt`), and a wrong guess reintroduces
	 * exactly the silent-placeholder failure this whole safeguard exists to stop.
	 * The binding key is the control name; the actual value is a dynamic-tag
	 * shortcode Elementor stores under settings.__dynamic__[ <key> ].
	 *
	 * `theme-post-content` is intentionally listed with no binding key: it renders
	 * the active post's content directly, with no __dynamic__ entry.
	 *
	 * Slugs and binding controls verified against Elementor Pro theme-builder
	 * widget source (post-title.php, post-content.php, post-featured-image.php,
	 * post-excerpt.php, site-title.php, site-logo.php, page-title.php,
	 * archive-title.php, and the shared title-widget-base.php).
	 *
	 * @return array<string, array{binding: ?string, requires_binding: bool, tag: ?string}>
	 */
	private static function theme_builder_widgets() {
		return [
			'theme-post-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'post-title' ],
			'theme-page-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'page-title' ],
			'theme-archive-title'       => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'archive-title' ],
			'theme-site-title'          => [ 'binding' => 'title',   'requires_binding' => true,  'tag' => 'site-title' ],
			'theme-post-featured-image' => [ 'binding' => 'image',   'requires_binding' => true,  'tag' => 'post-featured-image' ],
			'theme-site-logo'           => [ 'binding' => 'image',   'requires_binding' => true,  'tag' => 'site-logo' ],
			'theme-post-excerpt'        => [ 'binding' => 'excerpt', 'requires_binding' => true,  'tag' => 'post-excerpt' ],
			'theme-post-content'        => [ 'binding' => null,      'requires_binding' => false, 'tag' => null ],
		];
	}

	/**
	 * Classify a widget_type against Elementor's live widget registry. Tri-state
	 * replacement for the old fail-open is_known_widget_type() boolean, whose
	 * "return true when the registry is unreachable" branch let a semantically
	 * invalid slug (e.g. Core-style `post-content` instead of Theme Builder's
	 * `theme-post-content`) serialize into _elementor_data and render as a silent
	 * empty placeholder — the wedesign.id post 22122 failure.
	 *
	 * Returns one of:
	 *  - 'atomic'       — an Editor V4 Atomic slug (a-* / e-*), passed through opaquely
	 *  - 'registered'   — present in widgets_manager->get_widget_types()
	 *  - 'unregistered' — registry IS reachable and the slug is NOT in it (reject)
	 *  - 'unavailable'  — registry could not be reached (Elementor not bootstrapped)
	 *
	 * The decision of what to DO with each state lives in the caller, not here, so
	 * that add/update/import/clone can share one classifier while the curated path
	 * keeps its own leniency.
	 *
	 * @param string $widget_type
	 * @return string
	 */
	private static function classify_widget_type( $widget_type ) {
		$widget_type = (string) $widget_type;
		if ( self::is_atomic_widget_type( $widget_type ) ) {
			return 'atomic';
		}
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return 'unavailable';
		}
		$manager = isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance->widgets_manager : null;
		if ( ! $manager || ! method_exists( $manager, 'get_widget_types' ) ) {
			return 'unavailable';
		}
		$registered = $manager->get_widget_types();
		if ( ! is_array( $registered ) ) {
			return 'unavailable';
		}
		return isset( $registered[ $widget_type ] ) ? 'registered' : 'unregistered';
	}

	/**
	 * Report which Elementor capability tier this site actually has, by asking the
	 * live registry — never by reading a vendor version constant.
	 *
	 * Critical distinction: official Elementor Pro and the PRO Elements GPL fork
	 * BOTH define ELEMENTOR_PRO_VERSION and the ElementorPro\Plugin class, so those
	 * cannot tell them apart. Only PRO Elements defines IS_PRO_ELEMENTS. But the
	 * point of this method is diagnostics, not gating: the real gate is whether a
	 * slug is in the registry (classify_widget_type). This only shapes the human
	 * hint we attach when a Theme Builder slug is rejected — "install Pro / PRO
	 * Elements" reads very differently from "your Pro is present but this widget is
	 * only registered inside a Theme Builder template".
	 *
	 * @return string One of: core_only, official_pro, pro_elements,
	 *                official_pro_plus_pro_elements, unknown_third_party,
	 *                registry_unavailable.
	 */
	private static function detect_provider_capability() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return 'registry_unavailable';
		}
		$has_pro_class    = class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' );
		$is_pro_elements  = defined( 'IS_PRO_ELEMENTS' ) && IS_PRO_ELEMENTS;

		if ( ! $has_pro_class ) {
			// No Pro layer at all. If a theme-* slug is nonetheless registered, an
			// unknown third-party plugin supplied it.
			foreach ( array_keys( self::theme_builder_widgets() ) as $slug ) {
				if ( self::classify_widget_type( $slug ) === 'registered' ) {
					return 'unknown_third_party';
				}
			}
			return 'core_only';
		}

		// A Pro-shaped layer is present. IS_PRO_ELEMENTS is the only marker that
		// distinguishes the fork; when it is set we can only prove the fork is
		// active, never the absence of an official Pro alongside it, so we name
		// the fork rather than overclaiming a combined state we cannot detect.
		if ( $is_pro_elements ) {
			return 'pro_elements';
		}

		return 'official_pro';
	}

	/**
	 * Validate a single element's dynamic bindings against the Theme Builder
	 * catalogue. A `theme-*` widget that requires a binding but has no
	 * settings.__dynamic__[ key ] renders a placeholder ("Add Your Heading Text
	 * Here", a generic image) with no error — the exact confusion this safeguard
	 * prevents. Presence/shape only: this never generates a binding (a hardcoded
	 * dynamic-tag shortcode would rot); generation, if ever added, must go through
	 * Plugin::elementor()->dynamic_tags->tag_data_to_tag_text() at runtime.
	 *
	 * @param array $element A single element node (may be a widget or container).
	 * @return string[] Human-readable errors; empty when the element is fine.
	 */
	private static function validate_dynamic_bindings( array $element ) {
		$errors = [];
		if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
			return $errors;
		}
		$widget_type = (string) ( $element['widgetType'] ?? '' );
		$catalogue   = self::theme_builder_widgets();
		if ( ! isset( $catalogue[ $widget_type ] ) ) {
			return $errors;
		}
		$spec = $catalogue[ $widget_type ];
		if ( ! $spec['requires_binding'] ) {
			return $errors;
		}
		$key      = $spec['binding'];
		$settings = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : [];
		$dynamic  = ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) ) ? $settings['__dynamic__'] : [];
		if ( empty( $dynamic[ $key ] ) || ! is_string( $dynamic[ $key ] ) ) {
			$errors[] = sprintf(
				'Widget "%s" (id %s) is a Theme Builder dynamic widget and needs a dynamic binding on its "%s" setting, or it renders a placeholder instead of live data. '
				. 'Add settings.__dynamic__["%s"] = "[elementor-tag id=\"...\" name=\"%s\" settings=\"%%7B%%7D\"]". '
				. 'The binding value is generated by Elementor from the "%s" dynamic tag; copy one from a working widget of this type rather than inventing the id.',
				$widget_type,
				(string) ( $element['id'] ?? '?' ),
				$key,
				$key,
				(string) $spec['tag'],
				(string) $spec['tag']
			);
		}
		return $errors;
	}

	/**
	 * Recursively validate an element tree before any write. Collects ALL errors
	 * rather than throwing at the first, so a caller sees every problem in one
	 * response. Called by add/update/import/clone BEFORE the undo snapshot, the
	 * write, and cache invalidation — a rejected tree writes nothing, matching the
	 * SEO subsystem's "a rejected field aborts the whole write" contract.
	 *
	 * @param array $tree                Element list.
	 * @param bool  $allow_unavailable   When true (curated-only contexts), an
	 *                                   unreachable registry is tolerated; when
	 *                                   false, an unresolvable non-atomic slug is
	 *                                   an error (fail-closed).
	 * @return string[] All validation errors; empty when the tree is valid.
	 */
	private static function validate_document( array $tree, $allow_unavailable = false ) {
		$errors   = [];
		$provider = null; // resolved lazily, only when we actually need the hint
		$walk = function ( $elements ) use ( &$walk, &$errors, &$provider, $allow_unavailable ) {
			foreach ( $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$el_type = (string) ( $element['elType'] ?? '' );
				if ( $el_type === 'widget' ) {
					$widget_type = (string) ( $element['widgetType'] ?? '' );
					if ( $widget_type === '' ) {
						$errors[] = sprintf( 'Element id %s has elType "widget" but no widgetType.', (string) ( $element['id'] ?? '?' ) );
					} else {
						$class = self::classify_widget_type( $widget_type );
						if ( $class === 'unregistered' ) {
							if ( $provider === null ) {
								$provider = self::detect_provider_capability();
							}
							$errors[] = self::unregistered_widget_message( $widget_type, $provider );
						} elseif ( $class === 'unavailable' && ! $allow_unavailable ) {
							$errors[] = sprintf(
								'Cannot verify widget "%s" (id %s): Elementor\'s widget registry is not reachable in this request, so the slug cannot be confirmed as registered. '
								. 'Rather than write an unverifiable widget that may render as a blank placeholder, this write is refused. Retry once Elementor is fully loaded.',
								$widget_type,
								(string) ( $element['id'] ?? '?' )
							);
						}
					}
					foreach ( self::validate_dynamic_bindings( $element ) as $binding_error ) {
						$errors[] = $binding_error;
					}
				}
				if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};
		$walk( $tree );
		return $errors;
	}

	/**
	 * Build the rejection message for an unregistered widget slug, adding a
	 * provider-aware hint. A Theme Builder slug on a core_only site needs a
	 * different remedy (install Pro) than the same slug on a site that HAS Pro but
	 * is not editing a Theme Builder template.
	 *
	 * @param string $widget_type
	 * @param string $provider Result of detect_provider_capability().
	 * @return string
	 */
	private static function unregistered_widget_message( $widget_type, $provider ) {
		$catalogue = self::theme_builder_widgets();
		$base = sprintf(
			'widget_type "%s" is not registered with Elementor on this site, so it would serialize into _elementor_data and render as a silent empty placeholder.',
			$widget_type
		);
		if ( isset( $catalogue[ $widget_type ] ) ) {
			// It IS a real Theme Builder slug — the problem is availability/context.
			if ( in_array( $provider, [ 'core_only' ], true ) ) {
				return $base . ' It is an Elementor Pro Theme Builder widget, but only Elementor Core (free) is active here — install Elementor Pro or PRO Elements to use it.';
			}
			if ( $provider === 'registry_unavailable' ) {
				return $base . ' It is a Theme Builder widget; the registry could not be reached to confirm availability.';
			}
			return $base . ' It is a Theme Builder widget and Pro is present, but these widgets only register inside a Theme Builder template document (Single, Archive, Header, Footer, etc.). Confirm the target post is a Theme Builder template, not an ordinary page.';
		}
		$core_equivalents = [
			'post-title'          => 'theme-post-title',
			'post-content'        => 'theme-post-content',
			'post-featured-image' => 'theme-post-featured-image',
			'post-excerpt'        => 'theme-post-excerpt',
		];
		if ( isset( $core_equivalents[ $widget_type ] ) ) {
			return $base . sprintf(
				' Did you mean the Theme Builder widget "%s"? Core-style dynamic slugs like "%s" are not registered widget types.',
				$core_equivalents[ $widget_type ],
				$widget_type
			);
		}
		return $base . ' Use a curated type (' . implode( ', ', self::$curated_widget_types ) . '), an Elementor V4 atomic widget (a-* / e-*), or any slug returned by Elementor\'s widget registry.';
	}

	/**
	 * Count every element nested below this one, at any depth.
	 */
	private static function count_descendants( array $element ) {
		if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $element['elements'] as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$count += 1 + self::count_descendants( $child );
		}
		return $count;
	}

	/**
	 * Rebuild the tree with one element's settings replaced.
	 *
	 * Rebuilds rather than mutating in place. PHP arrays are value types, so
	 * navigating to a nested node and assigning to it edits a detached copy —
	 * the bug the Blocks parser harness caught before it shipped. Every mutation
	 * here returns a new tree for that reason.
	 */
	private static function replace_element_settings( $tree, $element_id, $new_settings ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $element_id ) {
				// Cast emptied settings to an object so they serialize as {}
				// rather than []: Elementor reads settings as an object, and
				// json_encode turns an empty PHP array into an array.
				$el['settings'] = ( is_array( $new_settings ) && $new_settings === [] )
					? new \stdClass()
					: $new_settings;
				$out[]          = $el;
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::replace_element_settings( $el['elements'], $element_id, $new_settings );
			}
			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Rebuild the tree with one element (and its subtree) removed.
	 *
	 * Elementor expects `elements` to serialize as a JSON array, and a PHP array
	 * left with non-sequential integer keys encodes as an object instead, which
	 * the editor rejects. Building a fresh `$out` with `[] =` is what guarantees
	 * that here — appending always produces sequential keys, so no gap can form
	 * from the skipped element. The array_values() call is belt-and-braces for
	 * anyone who later changes this to unset in place, where the gap would be
	 * real; it is not what makes the current implementation correct.
	 */
	private static function remove_element_by_id( $tree, $element_id ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $element_id ) {
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::remove_element_by_id( $el['elements'], $element_id );
			}
			$out[] = $el;
		}
		return array_values( $out );
	}

	/**
	 * Read and decode _elementor_data, or throw with a message that says what to
	 * check. Shared by the addressed-write tools so the three failure modes (no
	 * data, unparseable, not an array) read identically wherever they occur.
	 *
	 * @throws \Exception
	 */
	private static function load_tree( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			throw new \Exception( sprintf(
				'Post %d has no Elementor data — was it edited with Elementor?',
				(int) $post_id
			) );
		}
		$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}
		return $tree;
	}

	/**
	 * Encode and store a tree. wp_slash because WordPress unslashes meta on the
	 * way in and JSON is full of backslashes — GitHub #1 was exactly that
	 * corruption in _elementor_data.
	 */
	private static function save_tree( $post_id, $tree ) {
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	}

	// ============================================================
	// Derived-state invalidation
	// ============================================================

	/**
	 * Public wrapper for the undo handler in MCP\Server.
	 *
	 * Restoring a snapshot leaves the derived caches stale in exactly the same
	 * way the original write did, so the undo path has to clear them too or the
	 * undo appears not to have worked. Same guarantees as the private method:
	 * never throws, returns what it cleared and what it could not.
	 *
	 * @param int $post_id Post whose derived state is now stale.
	 * @return array{invalidated: string[], warnings: string[]}
	 */
	public static function invalidate_derived_state_public( $post_id ) {
		return self::invalidate_derived_state( $post_id );
	}

	/**
	 * Invalidate the caches Elementor derives from _elementor_data.
	 *
	 * Every tool here writes _elementor_data with a direct update_post_meta()
	 * call, which bypasses Document::save() and therefore bypasses the derived
	 * state Elementor clears or rebuilds on its own save. Without this the write
	 * lands in the database, reads back correctly, and renders with the previous
	 * version until someone opens the editor and presses Update — the same
	 * write-succeeded-page-unchanged failure the SEO subsystem exists to prevent,
	 * and the reason block writes verify their round-trip.
	 *
	 * Three separate caches, and they fail differently:
	 *
	 * - **Post CSS** (`_elementor_css`) holds the per-page stylesheet, inline in
	 *   post meta or as a file under uploads/elementor/css/ depending on the
	 *   site's CSS-print method. A newly inserted widget has no rules in a stale
	 *   stylesheet at all, so it renders unstyled rather than merely outdated.
	 * - **Element cache** (`_elementor_element_cache`) holds rendered element
	 *   HTML plus the script/style handles that render enqueued. On a hit
	 *   Elementor skips printing the elements entirely, so a stale entry serves
	 *   the *old markup*, not just old styling. Gated on the `e_element_cache`
	 *   experiment, so its blast radius is conditional — unlike Post CSS.
	 * - **Page assets** (`_elementor_page_assets`) is the handle list Elementor
	 *   derives by iterating the element tree. `Frontend::handle_page_assets()`
	 *   early-returns when the row is non-empty, while `Assets::is_action_needed()`
	 *   returns false whenever the saved value is an array. A stale list is
	 *   therefore authoritative until removed. The legacy per-element enqueue
	 *   fallback covers get_style_depends(), but not control-declared conditional
	 *   assets such as text-editor's drop-cap stylesheet.
	 *
	 * Elementor's own bulk invalidation treats all three as one unit in
	 * core/files/manager.php: Post_CSS::META_KEY, Document::CACHE_META_KEY, and
	 * Assets::ASSETS_META_KEY.
	 *
	 * Deliberately NOT calling Document::save(): it also runs version
	 * migrations, rewrites settings, and fires elementor/document/after_save.
	 * Routing writes through it would change behaviour well beyond invalidation
	 * and deserves its own decision.
	 *
	 * Deliberately NOT regenerating the CSS: Elementor rebuilds it lazily on the
	 * next render, so deleting is sufficient and eager generation would mean
	 * instantiating the whole element tree server-side for no benefit.
	 *
	 * @param int $post_id Post whose derived state is now stale.
	 * @return array{invalidated: string[], warnings: string[]} What was cleared,
	 *               and what could not be. Never throws: the data write has
	 *               already succeeded by the time this runs, so a failure here
	 *               must degrade to a reported warning rather than turning a
	 *               successful write into an exception.
	 */
	private static function invalidate_derived_state( $post_id ) {
		$post_id     = (int) $post_id;
		$invalidated = [];
		$warnings    = [];

		// Go through Elementor's own Post_CSS::delete() rather than deleting
		// _elementor_css ourselves. That method branches on use_external_file():
		// external -> unlink the file AND drop the meta; inline -> drop the meta
		// only. A meta-only delete on a site using external CSS files would leave
		// the stale .css on disk and still being served.
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			try {
				// create() routes through Plugin::$instance->files_manager, so it
				// needs Elementor bootstrapped, not merely autoloadable. Catch
				// Throwable, not Exception: a missing files_manager surfaces as an
				// Error, which is not an Exception.
				\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
				$invalidated[] = 'post_css';
			} catch ( \Throwable $e ) {
				$warnings[] = 'Post CSS cache could not be cleared (' . $e->getMessage() . '). The page may render with stale CSS until the Elementor editor is opened and the page saved.';
			}
		} else {
			$warnings[] = 'Elementor\'s Post CSS class was not available, so the CSS cache was not cleared. The page may render with stale CSS until the Elementor editor is opened and the page saved.';
		}

		// Plain meta deletes are correct for the two caches that have no file
		// counterpart here. Page assets is rebuilt lazily by Elementor's render
		// iteration once the saved row is absent; leaving an empty array would not
		// work because is_action_needed() treats any array as already evaluated.
		delete_post_meta( $post_id, '_elementor_element_cache' );
		$invalidated[] = 'element_cache';
		delete_post_meta( $post_id, '_elementor_page_assets' );
		$invalidated[] = 'page_assets';

		return [
			'invalidated' => $invalidated,
			'warnings'    => $warnings,
		];
	}

	/**
	 * Merge invalidation results into a tool response.
	 *
	 * Surfaced rather than silent: an agent told only `success: true` cannot know
	 * the rendered page is stale, while one that sees the warning can tell the
	 * user to open the editor once. Clean runs add nothing to the payload, so
	 * the common case stays quiet.
	 *
	 * @param array $response   Tool response so far.
	 * @param array $inval      Return value of invalidate_derived_state().
	 * @return array
	 */
	private static function with_invalidation( array $response, array $inval ) {
		if ( ! empty( $inval['warnings'] ) ) {
			$response['cache_invalidation'] = [
				'cleared'  => $inval['invalidated'],
				'warnings' => $inval['warnings'],
			];
		}
		return $response;
	}

	// ============================================================
	// Tool implementations
	// ============================================================

	/**
	 * Clone an Elementor page or post as a new draft.
	 */
	private static function clone_page( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$source_id = (int) ( $args['source_post_id'] ?? 0 );
		$new_title = sanitize_text_field( $args['new_title'] ?? '' );
		$new_status = isset( $args['new_status'] ) ? sanitize_key( $args['new_status'] ) : 'draft';
		if ( ! in_array( $new_status, [ 'draft', 'publish', 'private', 'pending' ], true ) ) {
			$new_status = 'draft';
		}
		if ( $source_id <= 0 || $new_title === '' ) {
			throw new \Exception( 'source_post_id and new_title are required.' );
		}
		$source = get_post( $source_id );
		if ( ! $source ) {
			throw new \Exception( 'Source post not found.' );
		}
		$elementor_data = get_post_meta( $source_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Source post does not have Elementor data — was it edited with Elementor?' );
		}

		// Parse, regenerate IDs, re-serialize.
		// Elementor stores _elementor_data as a JSON-encoded string (sometimes
		// already-decoded array depending on filter timing — handle both).
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse source _elementor_data as a JSON array.' );
		}
		$regenerated = self::regenerate_element_ids( $tree );

		// Validate the regenerated tree before creating the clone. A source page
		// can already be broken (unregistered slugs, unbound theme-* widgets), and
		// silently propagating that into a new post just doubles the problem.
		// allow_unavailable is true: if the source renders on this site its slugs
		// are registered, and a momentarily unreachable registry must not block a
		// pure duplication. Transactional — no post is inserted on failure.
		$doc_errors = self::validate_document( $regenerated, true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html(
				'The source page has invalid Elementor elements, so cloning it would duplicate a page that renders placeholders. ' . implode( ' ', $doc_errors )
			) );
		}

		// Create the new post.
		$new_post_data = [
			'post_title'  => $new_title,
			'post_status' => $new_status,
			'post_type'   => $source->post_type,
			'post_author' => get_current_user_id() ?: $source->post_author,
		];
		$new_id = wp_insert_post( $new_post_data, true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( $new_id->get_error_message() );
		}

		// Copy Elementor meta. _elementor_data is stored as a slashed JSON string;
		// re-encoding from our parsed array gives the same shape.
		// Important: wp_slash before update_post_meta because WP unslashes on read.
		update_post_meta( $new_id, '_elementor_data', wp_slash( wp_json_encode( $regenerated ) ) );

		// Copy structural Elementor meta to make the editor open cleanly.
		// Deliberately excludes all three derived caches: _elementor_css,
		// _elementor_element_cache, and _elementor_page_assets. The assets list
		// would happen to be the same for many clones because the widget types are
		// preserved, but it is still derived from the tree and can include
		// conditional assets. One rule is safer than a special case: copy source
		// data, rebuild all derived state lazily.
		$meta_keys_to_copy = [
			'_elementor_edit_mode',
			'_elementor_template_type',
			'_elementor_version',
			'_elementor_pro_version',
			'_elementor_page_settings',
		];
		foreach ( $meta_keys_to_copy as $key ) {
			$value = get_post_meta( $source_id, $key, true );
			if ( $value !== '' && $value !== null && $value !== false ) {
				update_post_meta( $new_id, $key, $value );
			}
		}

		// Set edit mode = 'builder' if it wasn't on source (rare).
		if ( get_post_meta( $new_id, '_elementor_edit_mode', true ) === '' ) {
			update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		}

		// wp_insert_post can reach a post ID that previously existed and left
		// derived state behind, so clear rather than assume a clean slate.
		$inval = self::invalidate_derived_state( $new_id );

		return self::with_invalidation( [
			'success'        => true,
			'new_post_id'    => (int) $new_id,
			'new_title'      => $new_title,
			'new_status'     => $new_status,
			'source_post_id' => $source_id,
			'edit_url'       => admin_url( 'post.php?post=' . $new_id . '&action=elementor' ),
			'view_url'       => $new_status === 'publish' ? get_permalink( $new_id ) : get_preview_post_link( $new_id ),
		], $inval );
	}

	/**
	 * Walk an Elementor element tree and replace every element's id with a fresh random 8-char hex.
	 * Preserves all other fields. Recurses into nested elements.
	 *
	 * @param array $elements
	 * @return array
	 */
	private static function regenerate_element_ids( $elements ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) ) {
				$el['id'] = self::generate_element_id();
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::regenerate_element_ids( $el['elements'] );
			}
			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Generate an opaque 8-character hexadecimal element ID compatible with
	 * Elementor's serialized element shape.
	 */
	private static function generate_element_id() {
		return bin2hex( random_bytes( 4 ) );
	}

	/**
	 * Replace text in known text-bearing widget settings.
	 */
	private static function replace_text( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$find = (string) ( $args['find'] ?? '' );
		$replace = (string) ( $args['replace'] ?? '' );
		$case_insensitive = ! empty( $args['case_insensitive'] );
		if ( $post_id <= 0 || $find === '' ) {
			throw new \Exception( 'post_id and find are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$counter = [ 'count' => 0 ];
		$updated = self::walk_widgets_text( $tree, $find, $replace, $case_insensitive, $counter );

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $updated ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		return self::with_invalidation( [
			'success'       => true,
			'post_id'       => $post_id,
			'replacements'  => $counter['count'],
			'find'          => $find,
			'replace'       => $replace,
		], $inval );
	}

	/**
	 * Recursively walk widgets and substitute text in known text-bearing fields.
	 * Atomic widgets (elType=widget with widgetType matching atomic patterns) are
	 * skipped — their schema is not publicly documented and we don't want to corrupt them.
	 */
	private static function walk_widgets_text( $elements, $find, $replace, $case_insensitive, &$counter ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		// Known text-bearing setting keys per widget type. Conservative list —
		// widgets not in this map have their settings left alone. Covers Container
		// model + legacy section/column model.
		$text_fields = [
			'heading'        => [ 'title' ],
			'text-editor'    => [ 'editor' ],
			'button'         => [ 'text' ],
			'image'          => [ 'caption', 'alt' ],
			'image-box'      => [ 'title_text', 'description_text' ],
			'icon-box'       => [ 'title_text', 'description_text' ],
			'icon-list'      => [ 'icon_list' ], // array — handled per-item below
			'video'          => [ 'caption' ],
			'testimonial'    => [ 'testimonial_content', 'testimonial_name', 'testimonial_job' ],
			'tabs'           => [ 'tabs' ], // array
			'accordion'      => [ 'tabs' ], // array (Elementor calls them tabs internally)
			'toggle'         => [ 'tabs' ], // array
			'star-rating'    => [ 'title' ],
			'call-to-action' => [ 'title', 'description', 'button' ],
			'flip-box'       => [ 'title_text_a', 'description_text_a', 'title_text_b', 'description_text_b', 'button_text' ],
			'blockquote'     => [ 'author_name', 'blockquote_content' ],
		];

		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}

			if ( ( $el['elType'] ?? '' ) === 'widget' ) {
				$widget_type = (string) ( $el['widgetType'] ?? '' );

				// Skip atomic widgets — they live under a different schema in Editor V4
				// and we shouldn't blindly mutate their settings.
				$is_atomic = self::is_atomic_widget_type( $widget_type );

				if ( ! $is_atomic && isset( $text_fields[ $widget_type ] ) && isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
					foreach ( $text_fields[ $widget_type ] as $key ) {
						if ( ! isset( $el['settings'][ $key ] ) ) {
							continue;
						}
						$value = $el['settings'][ $key ];
						if ( is_string( $value ) ) {
							$new_value = self::str_replace_count( $find, $replace, $value, $case_insensitive, $counter );
							$el['settings'][ $key ] = $new_value;
						} elseif ( is_array( $value ) ) {
							// Repeater fields (icon-list, tabs, etc.) — walk one level into each item's text fields.
							foreach ( $value as $i => $item ) {
								if ( ! is_array( $item ) ) {
									continue;
								}
								foreach ( $item as $item_key => $item_value ) {
									if ( is_string( $item_value ) ) {
										$value[ $i ][ $item_key ] = self::str_replace_count( $find, $replace, $item_value, $case_insensitive, $counter );
									}
								}
							}
							$el['settings'][ $key ] = $value;
						}
					}
				}
			}

			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_widgets_text( $el['elements'], $find, $replace, $case_insensitive, $counter );
			}

			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Count-aware string replace. Increments $counter['count'] by the number of replacements.
	 */
	private static function str_replace_count( $find, $replace, $subject, $case_insensitive, &$counter ) {
		$c = 0;
		if ( $case_insensitive ) {
			$out = str_ireplace( $find, $replace, $subject, $c );
		} else {
			$out = str_replace( $find, $replace, $subject, $c );
		}
		$counter['count'] += $c;
		return $out;
	}

	/**
	 * Swap image URLs across image-bearing widget settings.
	 */
	private static function replace_image( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$old_url = esc_url_raw( $args['old_url'] ?? '' );
		$new_url = esc_url_raw( $args['new_url'] ?? '' );
		$old_id = isset( $args['old_id'] ) ? (int) $args['old_id'] : 0;
		$new_id = isset( $args['new_id'] ) ? (int) $args['new_id'] : 0;
		if ( $post_id <= 0 || $old_url === '' || $new_url === '' ) {
			throw new \Exception( 'post_id, old_url, and new_url are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$counter = [ 'count' => 0 ];
		$updated = self::walk_widgets_image( $tree, $old_url, $new_url, $old_id, $new_id, $counter );

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $updated ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		return self::with_invalidation( [
			'success'      => true,
			'post_id'      => $post_id,
			'replacements' => $counter['count'],
			'old_url'      => $old_url,
			'new_url'      => $new_url,
		], $inval );
	}

	/**
	 * Recursively walk widgets and swap image URLs in known image-bearing keys.
	 */
	private static function walk_widgets_image( $elements, $old_url, $new_url, $old_id, $new_id, &$counter ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		$out = [];
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}

			if ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
				// Walk every settings key — if it's a dict with 'url' that matches, swap.
				// Covers image widget (settings.image.url), background images
				// (settings.background_image.url), and similar.
				$el['settings'] = self::swap_image_in_settings( $el['settings'], $old_url, $new_url, $old_id, $new_id, $counter );
			}

			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_widgets_image( $el['elements'], $old_url, $new_url, $old_id, $new_id, $counter );
			}

			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Walk a settings dict and replace image URL refs.
	 */
	private static function swap_image_in_settings( $settings, $old_url, $new_url, $old_id, $new_id, &$counter ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				// Elementor image-shape: { 'url': '...', 'id': N, 'size': '...', ... }
				if ( isset( $value['url'] ) && is_string( $value['url'] ) && $value['url'] === $old_url ) {
					$settings[ $key ]['url'] = $new_url;
					$counter['count']++;
					if ( $old_id > 0 && $new_id > 0 && isset( $value['id'] ) && (int) $value['id'] === $old_id ) {
						$settings[ $key ]['id'] = $new_id;
					}
				} else {
					// Recurse — gallery items, repeaters, etc.
					$settings[ $key ] = self::swap_image_in_settings( $value, $old_url, $new_url, $old_id, $new_id, $counter );
				}
			}
		}
		return $settings;
	}

	/**
	 * Build a simplified outline of an Elementor page.
	 */
	private static function get_page_outline( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$outline = self::build_outline( $tree, 0 );
		$post = get_post( $post_id );

		return [
			'post_id'        => $post_id,
			'post_title'     => $post ? $post->post_title : '',
			'post_type'      => $post ? $post->post_type : '',
			'edit_mode'      => get_post_meta( $post_id, '_elementor_edit_mode', true ) ?: null,
			'template_type'  => get_post_meta( $post_id, '_elementor_template_type', true ) ?: null,
			'outline'        => $outline,
		];
	}

	/**
	 * 1.4.37 Candidate 2 — read the full settings object for a single Elementor element.
	 *
	 * Read-only half of the widget CRUD trio; the write halves (update_widget /
	 * delete_widget) remain deferred to post-WCUS pending assessment of what
	 * Elementor's own MCP module ends up covering.
	 */
	private static function get_widget_settings( $args ) {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = isset( $args['element_id'] ) ? (string) $args['element_id'] : '';

		if ( $post_id <= 0 ) {
			throw new \Exception( 'post_id is required.' );
		}
		if ( '' === $element_id ) {
			throw new \Exception( 'element_id is required.' );
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			throw new \Exception( 'read_post capability required.' );
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data.' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		$searched_count = 0;
		$found = self::find_element_with_depth( $tree, $element_id, 0, $searched_count );

		if ( null === $found ) {
			return [
				'post_id'        => $post_id,
				'element_id'     => $element_id,
				'found'          => false,
				'searched_count' => $searched_count,
			];
		}

		$el       = $found['element'];
		$depth    = $found['depth'];
		$el_type  = (string) ( $el['elType'] ?? 'unknown' );
		$children = ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : [];

		return [
			'post_id'      => $post_id,
			'element_id'   => $element_id,
			'found'        => true,
			'element_type' => $el_type,
			'widget_type'  => ( 'widget' === $el_type ) ? (string) ( $el['widgetType'] ?? '' ) : null,
			'depth'        => $depth,
			'has_children' => count( $children ) > 0,
			'child_count'  => count( $children ),
			'settings'     => isset( $el['settings'] ) ? $el['settings'] : new \stdClass(),
		];
	}

	/**
	 * DFS-search an Elementor element tree for a matching element ID.
	 * Returns [ 'element' => array, 'depth' => int ] on hit, null on miss.
	 * Counts every element inspected into &$searched_count for diagnostic
	 * "wrong-ID" reporting on miss.
	 *
	 * Distinct from find_element_by_id (2-arg simple lookup used by
	 * add_widget's parent-resolution path) — this one carries the extra
	 * bookkeeping needed for the widget-settings diagnostic.
	 */
	private static function find_element_with_depth( $elements, $target_id, $depth = 0, &$searched_count = 0 ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$searched_count++;
			if ( isset( $el['id'] ) && (string) $el['id'] === $target_id ) {
				return [ 'element' => $el, 'depth' => $depth ];
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
				$hit = self::find_element_with_depth( $el['elements'], $target_id, $depth + 1, $searched_count );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	/**
	 * Recursively build an outline summary.
	 */
	private static function build_outline( $elements, $depth ) {
		$out = [];
		if ( $depth > 6 ) {
			return [ '...deep nesting truncated...' ];
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = (string) ( $el['elType'] ?? 'unknown' );
			$entry = [
				'id'     => (string) ( $el['id'] ?? '' ),
				'elType' => $el_type,
			];
			if ( $el_type === 'widget' ) {
				$entry['widgetType'] = (string) ( $el['widgetType'] ?? 'unknown' );
				// Surface a short text snippet if the widget has one.
				$snippet = self::widget_text_snippet( $el );
				if ( $snippet !== '' ) {
					$entry['snippet'] = $snippet;
				}
				// Loop widgets reference a separate loop-item library document via
				// a template_id setting. Surface it so a reader sees the pointer
				// without a second call, and can resolve it with
				// elementor_get_loop_template.
				$loop_template_id = self::loop_template_id_of( $el );
				if ( $loop_template_id > 0 ) {
					$entry['loop_template_id'] = $loop_template_id;
				}
			} elseif ( $el_type === 'container' && isset( $el['settings']['flex_direction'] ) ) {
				$entry['flex_direction'] = (string) $el['settings']['flex_direction'];
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && count( $el['elements'] ) > 0 ) {
				$entry['children'] = self::build_outline( $el['elements'], $depth + 1 );
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Extract a short text snippet from a widget for the outline (max 80 chars).
	 */
	private static function widget_text_snippet( $widget ) {
		$widget_type = (string) ( $widget['widgetType'] ?? '' );
		$s = $widget['settings'] ?? [];
		if ( ! is_array( $s ) ) {
			return '';
		}
		$snippet_candidates = [
			'heading'        => [ 'title' ],
			'text-editor'    => [ 'editor' ],
			'button'         => [ 'text' ],
			'image-box'      => [ 'title_text' ],
			'icon-box'       => [ 'title_text' ],
			'call-to-action' => [ 'title' ],
		];
		if ( ! isset( $snippet_candidates[ $widget_type ] ) ) {
			return '';
		}
		foreach ( $snippet_candidates[ $widget_type ] as $key ) {
			if ( isset( $s[ $key ] ) && is_string( $s[ $key ] ) && $s[ $key ] !== '' ) {
				$plain = wp_strip_all_tags( $s[ $key ] );
				return mb_strimwidth( $plain, 0, 80, '...' );
			}
		}
		return '';
	}

	/**
	 * Enumerate saved templates from the Elementor Library CPT.
	 */
	private static function list_local_templates( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$type_filter = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : '';
		$limit = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;

		$query_args = [
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];
		if ( $type_filter !== '' ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'elementor_library_type',
					'field'    => 'slug',
					'terms'    => $type_filter,
				],
			];
		}
		$posts = get_posts( $query_args );

		$templates = [];
		foreach ( $posts as $tpl ) {
			$terms = wp_get_post_terms( $tpl->ID, 'elementor_library_type', [ 'fields' => 'slugs' ] );
			$templates[] = [
				'id'            => (int) $tpl->ID,
				'name'          => $tpl->post_title,
				'type'          => is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ? (string) $terms[0] : 'page',
				'date_modified' => $tpl->post_modified_gmt,
			];
		}

		return [
			'count'     => count( $templates ),
			'templates' => $templates,
		];
	}

	/**
	 * Create a new Elementor template from a JSON payload.
	 */
	private static function import_template( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'edit_posts capability required.' );
		}
		$title = sanitize_text_field( $args['title'] ?? '' );
		$template_type = isset( $args['template_type'] ) ? sanitize_key( $args['template_type'] ) : 'page';
		$template_json = (string) ( $args['template_json'] ?? '' );
		if ( $title === '' || $template_json === '' ) {
			throw new \Exception( 'title and template_json are required.' );
		}

		$decoded = json_decode( $template_json, true );
		if ( ! is_array( $decoded ) ) {
			throw new \Exception( 'template_json must be a JSON-encoded array of Elementor elements.' );
		}
		// If the payload is the full Elementor export shape ({ 'content': [...], 'page_settings': {...}, ... })
		// extract content. Otherwise assume it's the bare elements array.
		if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
			$elements = $decoded['content'];
		} else {
			$elements = $decoded;
		}

		// Regenerate element IDs so re-importing on the origin site doesn't collide with existing IDs.
		$elements = self::regenerate_element_ids( $elements );

		// Validate the full payload before creating any post. An imported template
		// full of Core-style dynamic slugs or unbound theme-* widgets would create a
		// broken template that renders placeholders with no error. allow_unavailable
		// is false: an import is a fresh authoring act, so an unverifiable slug is
		// refused rather than written. Transactional — nothing is inserted on failure.
		$doc_errors = self::validate_document( $elements );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		// Create the template post in the elementor_library CPT.
		$new_id = wp_insert_post( [
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'elementor_library',
			'post_author' => get_current_user_id(),
		], true );
		if ( is_wp_error( $new_id ) ) {
			throw new \Exception( $new_id->get_error_message() );
		}

		// Set the template type taxonomy.
		wp_set_object_terms( $new_id, $template_type, 'elementor_library_type' );

		// Set Elementor meta.
		update_post_meta( $new_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		update_post_meta( $new_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $new_id, '_elementor_template_type', $template_type );

		$inval = self::invalidate_derived_state( $new_id );

		return self::with_invalidation( [
			'success'       => true,
			'template_id'   => (int) $new_id,
			'title'         => $title,
			'template_type' => $template_type,
			'edit_url'      => admin_url( 'post.php?post=' . $new_id . '&action=elementor' ),
		], $inval );
	}

	// ============================================================
	// add_widget — dual-surface widget insertion (1.4.29)
	// ============================================================

	/**
	 * Curated widget types — when widget_type is one of these and `settings` is
	 * not supplied, the tool builds the canonical settings object from flat
	 * curated params. When `settings` IS supplied, raw wins (curated params ignored).
	 */
	private static $curated_widget_types = [
		'container', 'heading', 'text-editor', 'button', 'image',
		'image-box', 'icon-box', 'icon-list', 'video', 'divider', 'spacer',
	];

	/**
	 * Main entry point — add a widget or container to an Elementor page.
	 */
	private static function add_widget( $args ) {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$widget_type = isset( $args['widget_type'] ) ? sanitize_key( $args['widget_type'] ) : '';
		if ( $post_id <= 0 || $widget_type === '' ) {
			throw new \Exception( 'post_id and widget_type are required.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( 'edit_post capability required on target post.' );
		}
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \Exception( 'Target post does not have Elementor data — was it edited with Elementor?' );
		}
		$tree = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( ! is_array( $tree ) ) {
			throw new \Exception( 'Could not parse _elementor_data as a JSON array.' );
		}

		// Build the new element (raw vs curated, recursive for container children).
		// build_element_from_args already ran the fail-closed registry gate on the
		// element and every inline child, so validate_document here is only for the
		// Theme Builder dynamic-binding check — allow_unavailable is true so it does
		// not re-reject a curated widget when the registry is momentarily down.
		$new_element = self::build_element_from_args( $args );

		$doc_errors = self::validate_document( [ $new_element ], true );
		if ( ! empty( $doc_errors ) ) {
			throw new \Exception( esc_html( implode( ' ', $doc_errors ) ) );
		}

		// Targeting.
		$parent_id = isset( $args['parent_id'] ) ? (string) $args['parent_id'] : null;
		$position = isset( $args['position'] ) ? (int) $args['position'] : null;
		if ( $parent_id !== null ) {
			$parent = self::find_element_by_id( $tree, $parent_id );
			if ( $parent === null ) {
				throw new \Exception( 'parent_id not found in this page: ' . esc_html( $parent_id ) );
			}
			if ( ! isset( $parent['elType'] ) || ! in_array( $parent['elType'], [ 'container', 'section', 'column' ], true ) ) {
				throw new \Exception( 'parent_id must reference a container, section, or column. Found: ' . esc_html( $parent['elType'] ?? 'unknown' ) );
			}
			// If we're inserting a container under another container, mark as inner.
			if ( $new_element['elType'] === 'container' && $parent['elType'] === 'container' ) {
				$new_element['isInner'] = true;
			}
		}

		// Insert.
		$tree = self::insert_into_tree( $tree, $parent_id, $position, $new_element );

		// Save.
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		$inval = self::invalidate_derived_state( $post_id );

		$notice = ! empty( $args['settings'] ) && in_array( $widget_type, self::$curated_widget_types, true )
			? 'Raw settings supplied for a curated widget_type — curated params were ignored. To use curated, omit the settings parameter.'
			: null;

		$response = [
			'success'      => true,
			'post_id'      => $post_id,
			'new_id'       => (string) $new_element['id'],
			'widget_type'  => $widget_type,
			'parent_id'    => $parent_id,
			'position'     => $position,
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		];
		if ( $notice !== null ) {
			$response['notice'] = $notice;
		}
		return self::with_invalidation( $response, $inval );
	}

	/**
	 * Build the element shape (recursive for container children) from args.
	 * Routes to raw or curated path based on whether `settings` was supplied.
	 */
	private static function build_element_from_args( $args ) {
		$widget_type = isset( $args['widget_type'] ) ? sanitize_key( $args['widget_type'] ) : '';
		if ( $widget_type === '' ) {
			throw new \Exception( 'widget_type is required for every element (including children).' );
		}

		// Raw path: explicit settings supplied → use them verbatim.
		// Curated path: settings absent + widget_type in curated list → build canonical settings.
		// Pure raw for unknown widget_types: settings required.
		$is_curated = in_array( $widget_type, self::$curated_widget_types, true );
		$has_settings = isset( $args['settings'] ) && is_array( $args['settings'] );

		if ( ! $is_curated && ! $has_settings ) {
			throw new \Exception( 'widget_type "' . esc_html( $widget_type ) . '" is not curated — supply a `settings` object directly.' );
		}

		// Raw path: a typo'd widget_type with any settings object would otherwise
		// serialize into _elementor_data and render as a silent empty placeholder.
		// Reject unknown slugs at the boundary so the caller sees the failure.
		// classify_widget_type replaces the old fail-open is_known_widget_type():
		// 'unregistered' is always rejected, and 'unavailable' is now fail-closed
		// for non-curated slugs (we cannot confirm the slug, so we refuse rather
		// than write something that may render blank).
		if ( ! $is_curated ) {
			$class = self::classify_widget_type( $widget_type );
			if ( $class === 'unregistered' ) {
				throw new \Exception( esc_html( self::unregistered_widget_message( $widget_type, self::detect_provider_capability() ) ) );
			}
			if ( $class === 'unavailable' ) {
				throw new \Exception( sprintf(
					'Cannot verify widget_type "%s": Elementor\'s widget registry is not reachable in this request. Rather than write an unverifiable widget that may render as a blank placeholder, this write is refused. Retry once Elementor is fully loaded.',
					esc_html( $widget_type )
				) );
			}
		}

		if ( $has_settings ) {
			// Raw path.
			$settings = $args['settings'];
			$el_type = $widget_type === 'container' ? 'container' : 'widget';
		} else {
			// Curated path.
			$settings = self::build_curated_settings( $widget_type, $args );
			$el_type = $widget_type === 'container' ? 'container' : 'widget';
		}

		$element = [
			'id'       => self::generate_element_id(),
			'elType'   => $el_type,
			'settings' => is_array( $settings ) ? $settings : (object) [],
			'elements' => [],
			'isInner'  => false,
		];
		if ( $el_type === 'widget' ) {
			$element['widgetType'] = $widget_type;
		}

		// Container children — both raw and curated paths support inline children.
		// Raw path: $args['children'] OR $settings does NOT carry children (children live at envelope level).
		// Curated path: $args['children'] populates the elements array recursively.
		if ( $widget_type === 'container' && isset( $args['children'] ) && is_array( $args['children'] ) ) {
			foreach ( $args['children'] as $child_args ) {
				if ( ! is_array( $child_args ) ) {
					continue;
				}
				$child = self::build_element_from_args( $child_args );
				if ( $child['elType'] === 'container' ) {
					$child['isInner'] = true;
				}
				$element['elements'][] = $child;
			}
		}

		return $element;
	}

	/**
	 * Curated → settings dispatcher. Each curated widget has its own builder.
	 */
	private static function build_curated_settings( $widget_type, $args ) {
		switch ( $widget_type ) {
			case 'container':   return self::curated_container( $args );
			case 'heading':     return self::curated_heading( $args );
			case 'text-editor': return self::curated_text_editor( $args );
			case 'button':      return self::curated_button( $args );
			case 'image':       return self::curated_image( $args );
			case 'image-box':   return self::curated_image_box( $args );
			case 'icon-box':    return self::curated_icon_box( $args );
			case 'icon-list':   return self::curated_icon_list( $args );
			case 'video':       return self::curated_video( $args );
			case 'divider':     return self::curated_divider( $args );
			case 'spacer':      return self::curated_spacer( $args );
		}
		// Should be unreachable — caller pre-checks against the curated list.
		throw new \Exception( 'No curated builder for widget_type: ' . esc_html( $widget_type ) );
	}

	// ----- Curated builders -----

	private static function curated_container( $args ) {
		$flex_direction = isset( $args['flex_direction'] ) && in_array( $args['flex_direction'], [ 'row', 'column' ], true )
			? $args['flex_direction'] : 'column';
		$content_width = isset( $args['content_width'] ) && in_array( $args['content_width'], [ 'boxed', 'full' ], true )
			? $args['content_width'] : 'boxed';
		return [
			'content_width'  => $content_width,
			'flex_direction' => $flex_direction,
		];
	}

	private static function curated_heading( $args ) {
		$title = isset( $args['title'] ) ? (string) $args['title'] : '';
		if ( $title === '' ) {
			throw new \Exception( 'Curated heading requires `title`.' );
		}
		$header_size = isset( $args['header_size'] ) && in_array( $args['header_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['header_size'] : 'h2';
		return [
			'title'       => $title,
			'header_size' => $header_size,
		];
	}

	private static function curated_text_editor( $args ) {
		$editor = isset( $args['editor'] ) ? (string) $args['editor'] : '';
		if ( $editor === '' ) {
			throw new \Exception( 'Curated text-editor requires `editor` (HTML content).' );
		}
		return [ 'editor' => $editor ];
	}

	private static function curated_button( $args ) {
		$text = isset( $args['text'] ) ? (string) $args['text'] : '';
		$link_url = isset( $args['link_url'] ) ? esc_url_raw( $args['link_url'] ) : '';
		if ( $text === '' || $link_url === '' ) {
			throw new \Exception( 'Curated button requires `text` and `link_url`.' );
		}
		$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
		return [
			'text' => $text,
			'link' => self::wrap_link( $link_url, $target ),
		];
	}

	private static function curated_image( $args ) {
		$image_url = isset( $args['image_url'] ) ? esc_url_raw( $args['image_url'] ) : '';
		if ( $image_url === '' ) {
			throw new \Exception( 'Curated image requires `image_url`.' );
		}
		$image_alt = isset( $args['image_alt'] ) ? sanitize_text_field( $args['image_alt'] ) : '';
		$settings = [
			'image' => self::wrap_image( $image_url, $image_alt ),
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link_to'] = 'custom';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_image_box( $args ) {
		$image_url = isset( $args['image_url'] ) ? esc_url_raw( $args['image_url'] ) : '';
		$title_text = isset( $args['title_text'] ) ? (string) $args['title_text'] : '';
		if ( $image_url === '' || $title_text === '' ) {
			throw new \Exception( 'Curated image-box requires `image_url` and `title_text`.' );
		}
		$image_alt = isset( $args['image_alt'] ) ? sanitize_text_field( $args['image_alt'] ) : '';
		$description_text = isset( $args['description_text'] ) ? (string) $args['description_text'] : '';
		$title_size = isset( $args['title_size'] ) && in_array( $args['title_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['title_size'] : 'h3';
		$settings = [
			'image'            => self::wrap_image( $image_url, $image_alt ),
			'title_text'       => $title_text,
			'description_text' => $description_text,
			'title_size'       => $title_size,
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_icon_box( $args ) {
		$icon = isset( $args['icon'] ) ? (string) $args['icon'] : '';
		$title_text = isset( $args['title_text'] ) ? (string) $args['title_text'] : '';
		if ( $icon === '' || $title_text === '' ) {
			throw new \Exception( 'Curated icon-box requires `icon` and `title_text`.' );
		}
		$description_text = isset( $args['description_text'] ) ? (string) $args['description_text'] : '';
		$title_size = isset( $args['title_size'] ) && in_array( $args['title_size'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ], true )
			? $args['title_size'] : 'h3';
		$settings = [
			'selected_icon'    => [
				'value'   => $icon,
				'library' => self::derive_icon_library( $icon ),
			],
			'title_text'       => $title_text,
			'description_text' => $description_text,
			'title_size'       => $title_size,
		];
		if ( ! empty( $args['link_url'] ) ) {
			$target = isset( $args['link_target'] ) ? (string) $args['link_target'] : '_self';
			$settings['link'] = self::wrap_link( esc_url_raw( $args['link_url'] ), $target );
		}
		return $settings;
	}

	private static function curated_icon_list( $args ) {
		if ( empty( $args['items'] ) || ! is_array( $args['items'] ) ) {
			throw new \Exception( 'Curated icon-list requires `items` (array of {text, icon?, link_url?}).' );
		}
		$icon_list = [];
		foreach ( $args['items'] as $i => $item ) {
			if ( ! is_array( $item ) || empty( $item['text'] ) ) {
				throw new \Exception( 'icon-list item at index ' . (int) $i . ' missing required `text`.' );
			}
			$icon = isset( $item['icon'] ) && $item['icon'] !== '' ? (string) $item['icon'] : 'fas fa-check';
			$row = [
				'_id'           => self::generate_repeater_id(),
				'text'          => (string) $item['text'],
				'selected_icon' => [
					'value'   => $icon,
					'library' => self::derive_icon_library( $icon ),
				],
			];
			if ( ! empty( $item['link_url'] ) ) {
				$row['link'] = self::wrap_link( esc_url_raw( $item['link_url'] ), '_self' );
			}
			$icon_list[] = $row;
		}
		return [
			'icon_list' => $icon_list,
			'view'      => 'traditional',
		];
	}

	private static function curated_video( $args ) {
		$video_url = isset( $args['video_url'] ) ? (string) $args['video_url'] : '';
		if ( $video_url === '' ) {
			throw new \Exception( 'Curated video requires `video_url`.' );
		}
		$routed = self::route_video_url( $video_url );
		$aspect_ratio = isset( $args['aspect_ratio'] ) && in_array( $args['aspect_ratio'], [ '169', '219', '43', '32', '11', '916' ], true )
			? $args['aspect_ratio'] : '169';
		$settings = [
			'video_type'   => $routed['video_type'],
			$routed['url_field'] => $routed['url_value'],
			'aspect_ratio' => $aspect_ratio,
		];
		if ( ! empty( $args['autoplay'] ) ) {
			$settings['autoplay'] = 'yes';
		}
		return $settings;
	}

	private static function curated_divider( $args ) {
		$settings = [ 'style' => 'solid' ];
		if ( isset( $args['weight'] ) ) {
			$settings['weight'] = self::wrap_slider_px( (int) $args['weight'] );
		}
		if ( ! empty( $args['color'] ) ) {
			$settings['color'] = sanitize_hex_color( $args['color'] ) ?: (string) $args['color'];
		}
		return $settings;
	}

	private static function curated_spacer( $args ) {
		$space = isset( $args['space'] ) ? (int) $args['space'] : 50;
		return [ 'space' => self::wrap_slider_px( $space ) ];
	}

	// ----- Shape helpers -----

	/**
	 * Build an Elementor URL control object: { url, is_external, nofollow }.
	 */
	private static function wrap_link( $url, $target = '_self', $nofollow = false ) {
		return [
			'url'         => (string) $url,
			'is_external' => ( $target === '_blank' ) ? 'on' : '',
			'nofollow'    => $nofollow ? 'on' : '',
		];
	}

	/**
	 * Build an Elementor MEDIA control object: { url, id, alt, source, size }.
	 * External URLs use id='' (string) since there's no WP attachment.
	 */
	private static function wrap_image( $url, $alt = '' ) {
		return [
			'url'    => (string) $url,
			'id'     => '',
			'alt'    => (string) $alt,
			'source' => 'library',
			'size'   => '',
		];
	}

	/**
	 * Wrap an int as Elementor's SLIDER value shape: { size: N, unit: 'px' }.
	 */
	private static function wrap_slider_px( $size ) {
		return [ 'size' => (int) $size, 'unit' => 'px' ];
	}

	/**
	 * Derive the FontAwesome library identifier from an icon class.
	 * fas → fa-solid, far → fa-regular, fab → fa-brands. Unknown → fa-solid.
	 */
	private static function derive_icon_library( $icon_value ) {
		$icon_value = trim( (string) $icon_value );
		if ( strpos( $icon_value, 'fas ' ) === 0 ) {
			return 'fa-solid';
		}
		if ( strpos( $icon_value, 'far ' ) === 0 ) {
			return 'fa-regular';
		}
		if ( strpos( $icon_value, 'fab ' ) === 0 ) {
			return 'fa-brands';
		}
		return 'fa-solid';
	}

	/**
	 * Detect video source from URL and return matching Elementor field name + value.
	 * Supports youtube, vimeo, dailymotion. Self-hosted / VideoPress raise.
	 */
	private static function route_video_url( $url ) {
		if ( preg_match( '#(?:youtube\.com|youtu\.be)#i', $url ) ) {
			return [ 'video_type' => 'youtube', 'url_field' => 'youtube_url', 'url_value' => (string) $url ];
		}
		if ( preg_match( '#vimeo\.com#i', $url ) ) {
			return [ 'video_type' => 'vimeo', 'url_field' => 'vimeo_url', 'url_value' => (string) $url ];
		}
		if ( preg_match( '#dailymotion\.com#i', $url ) ) {
			return [ 'video_type' => 'dailymotion', 'url_field' => 'dailymotion_url', 'url_value' => (string) $url ];
		}
		throw new \Exception( 'Curated video supports YouTube, Vimeo, and Dailymotion URLs. For self-hosted or VideoPress, use the raw path with explicit settings (video_type + matching url field).' );
	}

	/**
	 * Generate a 7-char hex ID for repeater items. Elementor's editor uses
	 * 7-char hex IDs for icon-list repeater items (smaller than the 8-char
	 * element IDs used for elType=widget/container).
	 */
	private static function generate_repeater_id() {
		// 4 bytes = 8 hex chars; truncate to 7 to match Elementor's repeater convention.
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	// ----- Tree manipulation -----

	/**
	 * Recursively search the element tree for an element with the given ID.
	 * Returns the matching element by reference? No — returns a copy for
	 * inspection. The insert path uses insert_into_tree which walks again
	 * and modifies in-place.
	 */
	private static function find_element_by_id( $tree, $id ) {
		if ( ! is_array( $tree ) ) {
			return null;
		}
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $id ) {
				return $el;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::find_element_by_id( $el['elements'], $id );
				if ( $found !== null ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Insert a new element into the tree.
	 * If parent_id is null → append (or insert at $position) at top level.
	 * If parent_id is provided → recurse to that element and insert into its `elements`.
	 *
	 * Returns the mutated tree (not modified in place; rebuilt to preserve clean copy).
	 */
	private static function insert_into_tree( $tree, $parent_id, $position, $new_element ) {
		if ( $parent_id === null ) {
			return self::insert_at_position( $tree, $position, $new_element );
		}
		// Walk the tree, find parent, insert into its elements.
		return self::walk_and_insert( $tree, $parent_id, $position, $new_element );
	}

	private static function walk_and_insert( $tree, $parent_id, $position, $new_element ) {
		if ( ! is_array( $tree ) ) {
			return $tree;
		}
		$out = [];
		foreach ( $tree as $el ) {
			if ( ! is_array( $el ) ) {
				$out[] = $el;
				continue;
			}
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $parent_id ) {
				$children = isset( $el['elements'] ) && is_array( $el['elements'] ) ? $el['elements'] : [];
				$el['elements'] = self::insert_at_position( $children, $position, $new_element );
				$out[] = $el;
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_and_insert( $el['elements'], $parent_id, $position, $new_element );
			}
			$out[] = $el;
		}
		return $out;
	}

	private static function insert_at_position( $list, $position, $new_element ) {
		$count = count( $list );
		if ( $position === null || $position >= $count ) {
			$list[] = $new_element;
			return $list;
		}
		if ( $position <= 0 ) {
			array_unshift( $list, $new_element );
			return $list;
		}
		array_splice( $list, $position, 0, [ $new_element ] );
		return $list;
	}
}
