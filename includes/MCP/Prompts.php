<?php
namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prompts {

	private static function catalog(): array {
		return [
			'edit_gutenberg_blocks' => [
				'name'        => 'edit_gutenberg_blocks',
				'description' => 'The read-tree → mutate → verify loop for block content, including why index paths shift and which guards prevent silent mis-edits.',
				'body'        => <<<'TXT'
Editing Gutenberg blocks safely:

1. Read the tree first: blocks_get_post_tree with the post_id. Index paths (like "1.2") address blocks in THIS snapshot.
2. Pick the mutation verb, never a full-content rewrite: blocks_insert, blocks_update, blocks_delete, blocks_move.
3. Re-read the tree after EVERY mutation. Index paths shift when siblings are inserted or removed — a path from an earlier read may now point at a different block.
4. Guard writes: pass expected_block_name so a stale path fails loudly against the wrong block instead of silently editing it. Use dry_run first when unsure.
5. Validate hand-authored markup with blocks_validate_markup before blocks_insert. Validation returns a confidence level, not pass/fail (PHP cannot run a block's JS save()), so treat anything below high as needs-review.
6. For templates, blocks_get_template / blocks_update_template resolve through the active theme, with a database customization shadowing the theme file.

The full argument surface of each tool is in its description. For a page builder instead of core blocks, check whether a dedicated integration is enabled (Elementor, Divi, Beaver Builder, SiteOrigin Page Builder have outline tools of their own).
TXT,
				'availability' => fn( array $tools ) => self::tool_exists( 'blocks_get_post_tree', $tools )
					? ''
					: 'The blocks_* tools are not registered on this site.',
			],
			'build_elementor_site_structure' => [
				'name'        => 'build_elementor_site_structure',
				'description' => 'Decomposing a page design or full HTML export into Elementor site structure: Theme Builder header/footer templates with display conditions, page body content, and Kit design tokens, plus the Pro check to make before planning. Use when building or restructuring an Elementor site, giving a page its own header/footer, or whenever you hold a complete page export to reproduce.',
				'body'        => <<<'TXT'
Structuring an Elementor build (site chrome vs page content):

1. Orient before planning: Theme Builder templates, display conditions, custom fonts, and custom code are Elementor Pro features; free Elementor builds page content only. The note on this prompt states which situation this site is in. Then read what exists: elementor_list_local_templates lists any header/footer/single/archive templates already wired.
2. Decompose, never paste. A complete design or HTML export is three targets: the site-wide header and footer are Theme Builder templates, the middle section is the page's own content, global colors and typography are Kit settings. Pasting the whole document into one page (or one HTML widget) welds the chrome onto every page and nothing stays reusable.
3. Header/footer: elementor_import_template with the element tree for that location, template_type header or footer. Import regenerates every element ID, so address elements by the outline the response returns, never the IDs you wrote. Then wire the location with elementor_set_template_conditions: include/general is site-wide, include/singular/post narrows to a post type, exclude/... carves out, and a more specific condition overrides a general one. Conditions go through that tool only: it also rebuilds the location cache a bare meta write skips, which is why the template then actually renders.
4. Page body: create the page with wp_create_page, build containers and widgets with elementor_add_widget (one call can drop a parent container plus its children), and reuse an existing layout with elementor_clone_page. A freshly created page has no Elementor data yet and elementor_add_widget refuses it: bootstrap once with wp_update_post_meta on _elementor_edit_mode ("builder") and _elementor_data ("[null]"), build, then read _elementor_data back and remove the placeholder null before handing off (Elementor's own renderer errors on a bare null entry). Do not fall back to pasting the whole export into the page's content, which is the failure this prompt exists to prevent. Design tokens belong in the Kit, not repeated per widget: elementor_get_kit first, then elementor_update_kit, which merges by default.
5. Verify structure, not just success: elementor_get_page_outline on the new page. A single giant HTML widget holding the whole export is exactly the failure this prompt exists to prevent. elementor_list_local_templates again, so the header/footer templates are confirmed to exist with the conditions you set.

One guard worth restating because it bites during builds: elementor_update_widget merges settings, replace_settings discards styling. The full argument surface of each tool is in its description.
TXT,
				'availability' => function ( array $tools ) {
					if ( ! self::tool_exists( 'elementor_import_template', $tools ) ) {
						return 'The elementor_* tools are not registered on this site.';
					}

					
					if ( ! ( class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) ) {
						return 'Elementor is active without a Pro layer: site-wide header/footer templates and display conditions are unavailable.';
					}
					return '';
				},
			],
			'safe_plugin_change' => [
				'name'        => 'safe_plugin_change',
				'description' => 'The two-part confirmation sequence for plugin and theme lifecycle operations, including why the preview call IS the intended first call.',
				'body'        => <<<'TXT'
Changing plugins or themes safely:

1. The first call is meant to be UNCONFIRMED: it returns a preview and writes nothing. This is not an error and not a permission problem — read the preview. It is the designed first step of a two-part sequence.
2. To act, call again with the same parameters plus confirm: true and the echoed confirm slug from the preview. The slug cannot be guessed without having read the preview, which is the point.
3. The preview's version and active-install count tell you whether an update is routine or risky. Surface them to the user before confirming an update on a low-install or major-version-jump release.
4. Install accepts a WordPress.org slug only, never a URL or file path: accepting a package URL would make this a download-and-execute tool.
5. Sites needing FTP/SSH credentials for filesystem writes are refused rather than prompted — credentials must never travel through tool arguments.
6. Every lifecycle operation reports the post-operation state read back from the site, so a silent no-op is visible rather than reported as success.

The current pending updates are listed by the same tools that act on them.
TXT,
				'availability' => fn( array $tools ) => self::tool_exists( 'wp_get_plugin_updates', $tools )
					? ''
					: 'The plugin/theme lifecycle tools are not enabled on this site (they are off by default under Settings → Access).',
			],
			'bulk_content_edit' => [
				'name'        => 'bulk_content_edit',
				'description' => 'Surgical find-and-replace across existing content using dry_run and expected_count guards, instead of resending whole post bodies.',
				'body'        => <<<'TXT'
Editing text across existing content:

1. Never resend a whole post body to change a string — the post may contain block markup, builder JSON, or embedded payloads that a full rewrite can corrupt. Use the find/replace tools.
2. Preview first: run with dry_run: true to get the occurrence count without writing.
3. Guard the write: pass expected_count equal to the dry-run count. The write aborts unless exactly that many matches exist, so content that changed between preview and write fails loudly instead of hitting the wrong occurrences.
4. Matching is case-sensitive literal text, not regex. Escape nothing; pass the literal string.
5. Page-builder content: for Elementor pages, the elementor_replace_text tool understands widget settings and is safer than raw content replace. Same for Divi's module-aware tools. Use the generic replace only when no builder-aware tool applies.

The tools report the verified content after writing; trust that read-back over any assumption about what happened.
TXT,
				'availability' => fn( array $tools ) => self::tool_exists( 'wp_replace_in_post', $tools )
					? ''
					: 'The find/replace tools are not registered on this site.',
			],
			'seo_meta_workflow' => [
				'name'        => 'seo_meta_workflow',
				'description' => 'Read stored meta, audit what actually renders, then write: the DB-vs-rendered distinction that trips silent SEO failures.',
				'body'        => <<<'TXT'
Adjusting a page's SEO metadata:

1. Read the stored values first: wp_get_seo_meta with the post_id. This reads from wherever the ACTIVE SEO plugin actually stores each field (post meta, custom tables, or an option) and names the plugin it read from.
2. Audit what crawlers receive: seo_audit_meta_tags with the same post_id. This fetches the RENDERED page and parses the head — title, meta description, canonical, Open Graph, Twitter Card, viewport.
3. Compare the two before writing. A mismatch means a theme or another plugin is overriding the output: writing the field again will change nothing. That is why the audit tool exists and why "stored correctly" does not imply "rendered correctly".
4. Write through wp_update_seo_meta (it routes each field to the active plugin's real storage). The slug field is WordPress-native and works with no SEO plugin active.
5. Re-run the audit after writing. On cached sites the rendered head may be stale until the page cache is purged — LiteSpeed, WP Rocket, and the other cache integrations expose a purge tool for exactly this.
6. More than one SEO plugin active produces duplicate tags in the rendered head; the audit shows that too.

Term-level (category/tag archive) SEO uses wp_get_term_seo_meta / wp_update_term_seo_meta with the same read-audit-write loop.
TXT,
				'availability' => fn( array $tools ) => self::tool_exists( 'wp_get_seo_meta', $tools )
					? ''
					: 'The SEO meta tools are not registered on this site.',
			],
			'troubleshoot_this_site' => [
				'name'        => 'troubleshoot_this_site',
				'description' => 'A narrowing order for diagnosing a sick site: environment snapshot, error tail, cron health, connection health.',
				'body'        => <<<'TXT'
Diagnosing a problem on this site, in the order that narrows fastest:

1. wp_get_site_status: WordPress/PHP/MySQL versions, plugin count, theme, memory limit, disk free, debug-log state, in one call. Version mismatches and a full disk explain a lot of mysteries immediately.
2. wp_get_error_log_tail: the last lines of debug.log, optionally filtered by a substring (a plugin slug is usually the fastest filter). Skips cleanly when debug logging is off.
3. wp_get_cron_schedule: every scheduled event with its next run and an is_overdue flag, overdue first. A missed wp-cron explains posts that never publish and emails that never send.
4. more_mcp_connection_health: MCP transport state — auth method, session, negotiated capabilities — for when the problem is the connection rather than the site.
5. Then narrow by symptom: wp_get_plugin_updates for an update-gone-wrong, wp_get_comments / wp_get_pending_comments for moderation backlogs, the cache integrations' status tools for stale-content reports.

Read-only diagnostics all: none of these change state, so they can be run in any order without risk — this order just converges faster.
TXT,
				'availability' => fn( array $tools ) => self::tool_exists( 'wp_get_site_status', $tools )
					? ''
					: 'The diagnostic tools are not registered on this site.',
			],
		];
	}

	public static function list_prompts( array $tools ): array {
		$out = [];
		foreach ( self::catalog() as $prompt ) {
			$description = $prompt['description'];
			$note        = call_user_func( $prompt['availability'], $tools );
			if ( '' !== $note ) {
				$description .= ' NOTE: ' . $note;
			}
			$out[] = [
				'name'        => $prompt['name'],
				'description' => $description,
			];
		}
		return $out;
	}

	public static function get_prompt( string $name, array $tools ): array {
		if ( '' === $name ) {
			return [
				'error' => [
					'code'    => -32602,
					'message' => 'Prompt name is required.',
				],
			];
		}

		$catalog = self::catalog();
		if ( ! isset( $catalog[ $name ] ) ) {
			return [
				'error' => [
					'code'    => -32602,
					'message' => 'Unknown prompt: ' . $name,
				],
			];
		}

		$prompt = $catalog[ $name ];

		$body = $prompt['body'];
		$note = call_user_func( $prompt['availability'], $tools );
		if ( '' !== $note ) {
			$body .= "\n\nOn this site: " . $note;
		}

		return [
			'description' => $prompt['description'],
			'messages'    => [
				[
					'role'    => 'user',
					'content' => [
						'type' => 'text',
						'text' => $body,
					],
				],
			],
		];
	}

	private static function tool_exists( string $tool_name, array $tools ): bool {
		foreach ( $tools as $tool ) {
			if ( isset( $tool['name'] ) && $tool['name'] === $tool_name ) {
				return true;
			}
		}
		return false;
	}
}
