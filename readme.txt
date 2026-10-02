=== More MCP – MCP Server with OAuth 2.0 (works with Claude, ChatGPT & Gemini) ===
Contributors: moremcp
Tags: mcp, ai, claude, chatgpt, gutenberg
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 0.15.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MCP server for WordPress with OAuth 2.0, API-key auth, rate limiting, and audit logs. Works with Claude, ChatGPT, Gemini, and MCP clients.

== Description ==

More MCP, by Mordenhost, is a security-first Model Context Protocol (MCP) server for WordPress, giving AI platforms like Claude, ChatGPT, and Gemini typed, permission-checked access to your content with the auth, rate limiting, and audit logging most bridges leave out.

MCP lets AI agents read, create, update, and delete your content, and many public MCP servers accept calls with no authentication at all. More MCP takes the opposite stance: every session authenticates with OAuth 2.0 or an API key (timing-safe), every request is rate-limited per IP, every call passes a capability check, and everything is logged.

Requires PHP 7.4+, WP 5.8+, MySQL/MariaDB, HTTPS. On WP 6.9+ tools also register through the core Abilities API, and abilities from other plugins import as `discovered_*` tools.

More MCP is fully featured in its free, GPL-licensed release; there is no Pro version. Credentials stay on your server, with no outbound vendor connections, license checks, or telemetry. Local inference via Ollama or LM Studio works.

More MCP always registers **111 tools** no matter which plugins are installed: 87 WordPress-core tools (content, media, comments, users, taxonomies, menus, meta, options, SEO meta routed to where each detected SEO plugin stores it), 21 Gutenberg block tools (round-trip-safe trees, reusable blocks, patterns, FSE templates, global styles), and 3 webhooks.

Opt-in integrations add tools when enabled, each off by default behind two gates: host plugin active AND the integration enabled under Settings > Access. There is no MCP route to enable one, so an agent cannot widen its own surface. WooCommerce (29 tools), Elementor (20, up to 33 with Pro), Divi, ACF, Meta Box, Redirection, analytics, forms, cache, email, security, backup, CRM, LMS, events, multilingual, and 60+ more; per-integration lists live in the Documentation panel. The 10 lifecycle tools (install, activate, update, delete plugins and themes) are gated most tightly: two-part confirmation where the first call is a dry run, wp.org slugs only, and More MCP can't delete itself.

Compatible with Claude Desktop/Code, VS Code, ChatGPT, Gemini, Cursor, Windsurf, Cline, JetBrains, Postman, Insomnia (`MMCP-Key` header), and LangChain or AutoGen. Implements the [MCP 2025-11-25 Streamable HTTP spec](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports#streamable-http): single `/mcp` endpoint, secure session IDs, Origin validation, CORS support.

== Installation ==

Requires PHP 7.4 or later (8.0+ recommended) and WordPress 5.8 or later. See Requirements in the description above for the full list, including the HTTPS and pretty-permalink prerequisites for OAuth connectors.

1. Upload the `more-mcp` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to More MCP → Settings to configure
4. Copy your API key, you will need this to authenticate MCP connections
5. Add your AI platform(s) and enter their API keys
6. In your AI client (Claude Desktop, VS Code, etc.), configure the MCP server URL and API key

The Settings page shows the exact MCP server URL, the API key, and the required request header for each supported client.

== Frequently Asked Questions ==

= What is MCP and why does my WordPress site need it? =

Model Context Protocol (MCP) is an open standard created by Anthropic that lets AI assistants interact with external data sources. Without MCP, AI tools like Claude or ChatGPT can only work with content you copy and paste into them. With More MCP installed, these AI platforms can directly read your WordPress posts, create new content, manage your WooCommerce products, check your security status, and trigger backups, all through a structured, authenticated protocol.

= How is More MCP different from other WordPress MCP plugins? =

Security is the whole point. Many WordPress MCP bridges expose their tools with little or no authentication, which on a live site is an open door. More MCP requires OAuth 2.0 or an API key on every session, enforces a WordPress capability check inside each tool handler, applies a per-IP rate limit, and redacts sensitive data (user emails, PHP version, stored credentials) from responses. It is designed to be safe to point a production site at.

= Does More MCP duplicate what WordPress core now does? =

No. WordPress 6.9 added the Abilities API, a primitive for registering AI-callable functions, and the `wordpress/mcp-adapter` package bridges abilities to the MCP protocol. More MCP is a full MCP server with the security layer, connector flows, and plugin integrations that the bare primitive does not include: enforced API key auth, OAuth 2.0 for Claude Desktop, per-IP rate limiting, audit logging, sensitive-data redaction, 111 always-on WordPress tools, and opt-in integration tools you enable per product across the integration catalogue (commerce, builders, forms, analytics, security, backup, membership, donations, booking, LMS, events, multilingual, and more).

= Does More MCP work with WooCommerce? =

Yes. When WooCommerce is active and you enable the WooCommerce integration under Settings → Access, More MCP adds 29 MCP tools spanning product management (simple and variable, including variation CRUD and global attribute management), full coupon management (list/get/create/update/delete + bulk trash purge), order management (view, create, update, update status, order notes), customer data, and store statistics. Integrations are opt-in and off by default; once enabled, the tools appear automatically in the MCP tools list.

= Can AI assistants configure my plugins for me? =

Yes, with safety controls. More MCP exposes two tools for plugin configuration:

* `wp_get_plugin_settings` lets AI read any plugin's stored settings by slug. Sensitive values (API keys, secrets, tokens, passwords, license keys, OAuth credentials) are automatically replaced with `[REDACTED]` before they leave your server, so AI assistants can understand a plugin's configuration without ever seeing stored credentials.

* `wp_update_option` lets AI write to WordPress options, but only after passing three security gates:
    1. The site admin must enable the "Allow AI to write WordPress options" toggle on the More MCP settings page (off by default)
    2. The option name must be in a runtime allowlist. The default allowlist is intentionally tiny, `blogname`, `blogdescription`, `posts_per_page`, `date_format`, `time_format`. Plugin authors opt their own settings in via the `more_mcp_writable_options` filter.
    3. A hard denylist permanently blocks writes to sensitive option names (siteurl, home, license keys, secrets, salts, etc.) regardless of the allowlist or the toggle.

Plugin authors can opt in their settings with one line: `add_filter('more_mcp_writable_options', fn($opts) => array_merge($opts, ['my_plugin_settings']));`

= How do I connect Claude Desktop to WordPress? =

Install More MCP, go to More MCP → Settings, and copy your API key and MCP server URL. In Claude Desktop, add a new MCP server configuration with the URL and include the `MMCP-Key` header with your API key. If the connection fails, see the next FAQ.

= The connector won't connect: where do I start? =

About 90% of "can't connect" / "OAuth failed" / "tools missing" issues resolve in a basic 4-step pass before any host-specific fix is needed. In order: (1) update More MCP to the latest version (every recent release fixes meaningful OAuth edge cases), (2) run a conflict test, deactivate all other plugins, switch to a default theme like Twenty Twenty-Five, and purge every cache layer (any cache plugin, your host's server-level cache, Cloudflare/CDN, and browser cache), (3) wipe stale OAuth state: use the Reset OAuth State button in More MCP → Settings, or (as a manual fallback) run the four `DELETE` SQL queries against the `more_mcp_oauth_clients`, `more_mcp_oauth_tokens`, `more_mcp_oauth_auth_codes`, and `more_mcp_sessions` tables, (4) check More MCP → Activity Logs for the most recent `oauth:` row, which records exactly which validation rule fired. Only proceed to host-specific fixes (Cloudflare AI Bots toggle, SiteGround `/.well-known/` static files, edge-cache exclusions) after the four basics are ruled out, most "advanced infrastructure" reports actually resolve in those four steps.

= I restored my WordPress database from backup and Claude can't reconnect. How do I fix this? =

When you restore from backup, the OAuth client credentials Claude was holding no longer match anything on the WordPress side, so Claude's connector ends up with a stale token that no More MCP installation will accept. The fix is one click: go to **More MCP → Settings** and click the **Reset OAuth State** button. This wipes all stale OAuth clients, issued access/refresh tokens, and pending authorization codes. Then in Claude, delete the existing connector entirely, wait 30 seconds, and re-add it from scratch, the full OAuth flow runs fresh against the cleaned-up state and the connection works. The same effect can be achieved by hand by emptying the `more_mcp_oauth_clients`, `more_mcp_oauth_tokens`, `more_mcp_oauth_auth_codes`, and `more_mcp_sessions` tables. The plugin's settings, API key, and Activity Log are not affected by Reset OAuth State, only the OAuth handshake state.

= Claude says "Couldn't register with sign-in service" or "Session not found": what's wrong? =

Both messages (plus "no tools available" in Claude.ai after connecting) usually mean one of More MCP's OAuth or sessions database tables is physically missing. The fix is to update More MCP to the latest version: the runtime healer detects missing tables and recreates them automatically on the next pageload, with no deactivate/reactivate required. After updating, delete the existing More MCP connector in Claude, wait 30 seconds, then re-add it fresh. If you can't update yet and need to recover immediately, the manual workaround is `wp option delete more_mcp_db_version` followed by loading any wp-admin page, that clears the stored schema version, so the installer re-runs and recreates the missing tables.

= I'm auditing my install and can't find the OAuth endpoints under `/wp-json/more-mcp/v1/`. Where are they? =

By design, More MCP's OAuth endpoints (`/register`, `/token`, `/authorize`) are registered as **top-level WordPress rewrite rules at the site root**, not as REST API routes under `/wp-json/more-mcp/v1/`. This is required by the OAuth 2.0 specification (RFC 6749) and the MCP discovery specs (RFC 8414 and RFC 9728), which mandate predictable site-root paths so OAuth-discovery-aware clients can find them without per-plugin configuration. If you're auditing rewrite rules instead of REST routes, you can see ours via `wp rewrite list | grep more_mcp_oauth` from WP-CLI. The `/wp-json/more-mcp/v1/` namespace contains the JSON-RPC tool endpoint at `/mcp` plus supporting REST routes (`/posts`, `/pages`, `/site`, etc.), but not the OAuth handshake endpoints themselves. Both routing layers are normal and both need to be reachable for the connector to work end-to-end.

= Is my content safe? =

More MCP is designed with defense in depth. API key authentication is required for all MCP sessions. Rate limiting prevents abuse (60 requests per minute per IP). Activity logging records every tool call. Sensitive data is filtered, user emails, usernames, admin email, PHP version, and stored credentials inside plugin settings (api keys, secrets, tokens, passwords) are never exposed through MCP. Comment creation respects your WordPress moderation settings. Post meta values are sanitized before storage. Option writes are disabled by default and gated by three independent checks (admin toggle, allowlist, hard denylist) when enabled. The plugin itself starts disabled by default, nothing is accessible until you explicitly enable it.

= Can I use local AI models instead of cloud services? =

Yes. More MCP supports Ollama and LM Studio for fully local AI inference. When using local models, no data leaves your server, the AI model runs on your own hardware and communicates with WordPress through the MCP protocol on localhost.

= What happens if I uninstall More MCP? =

More MCP performs a clean uninstall. All plugin options, database tables (activity logs), transients, and user meta are removed. No orphaned data is left behind.

= Does More MCP work with Claude Code, VS Code, Cursor, Windsurf, or other AI IDEs? =

Yes. Any MCP-compliant client can connect to More MCP. Configure your IDE or client with the MCP server URL (`https://yoursite.com/wp-json/more-mcp/v1/mcp`) and the API key (sent in the `MMCP-Key` header). Claude Desktop additionally supports the native "Add Connector" OAuth 2.0 flow, which More MCP handles via Dynamic Client Registration (RFC 7591), no manual API key management required on that path. The same OAuth flow works in any client that follows the MCP 2025-11-25 spec.

= Does More MCP work with custom fields, ACF, MetaBox, JetEngine, Pods, or CPT UI? =

Yes. More MCP exposes WordPress's standard `wp_get_post_meta`, `wp_update_post_meta`, and `wp_delete_post_meta` tools, which read and write any custom field, including Advanced Custom Fields (ACF), MetaBox, JetEngine, Pods, CPT UI, and Custom Field Suite. AI agents can populate ACF fields, set repeater rows, update flexible content blocks, and read computed fields just like a human editor working in the WordPress admin.

= Will More MCP slow down my WordPress site? =

No. The MCP endpoint is a REST route that runs only when an authenticated AI client makes a request, it does not run on visitor-facing pages, frontend templates, or admin screens (except its own settings page). The activity log uses a single indexed database table and writes asynchronously after the response is sent. Rate limiting (60 requests/minute per IP) prevents accidental overload.

= Does More MCP work on WordPress multisite networks? =

Yes, on a per-site basis. Each site in a multisite network has its own API key, its own activity log, and its own settings. AI clients connect to a specific site's MCP endpoint, More MCP does not bridge requests between sites in the network.

= Can I limit which posts, pages, or post types AI can access? =

Yes. The `wp_get_posts` and `wp_create_post` tools accept a `post_type` parameter and validate it against registered public post types, so private or internal post types are not exposed by default. A site owner can opt specific non-public types in with the `more_mcp_allowed_post_types` filter, for example `add_filter('more_mcp_allowed_post_types', fn($types) => array_merge($types, ['elementor_snippet']));`. Opted-in types appear in `wp_get_post_types` with `public: false`, listing one requires that type's own `edit_posts` capability, and the usual per-type capability checks still apply on writes. For Elementor Pro Custom Code, prefer the dedicated `elementor_*_code` tools: a snippet's code lives in post meta rather than post content, so a plain `wp_create_post` is not enough to make it load. Plugin authors can disable specific tools entirely with the `more_mcp_disabled_tools` filter, or scope the option-write allowlist with `more_mcp_writable_options`. WordPress's standard capability checks also apply to every tool call.

= Does More MCP work with WPML, Polylang, or TranslatePress for multilingual content? =

<!-- compliance: technical-context -->
Yes. Translated posts appear as separate WordPress posts (each with its own ID and language meta) and are readable or writable via the standard `wp_get_posts`, `wp_create_post`, and `wp_update_post` tools. AI agents can list posts in a specific language by filtering on the language meta key, or translate a post and write the corresponding translation by ID.

= How do I monitor what AI is doing on my site? =

Every authenticated MCP request is logged to the More MCP activity log with timestamp, client IP, tool name, parameters (sensitive values redacted), and response status. The log is filterable by time range, client, tool, or status code, and exportable to CSV. The log page refreshes via AJAX so you can watch active sessions in real time.

== Screenshots ==

1. Connection panel, the MCP Server URL every client uses, with the API key and a collapsible Advanced section
2. Permissions panel, the master switch, what is always enforced, and the three write scopes in ascending order of risk
3. Sessions panel, connected OAuth clients with the WordPress user each acts as, and a per-row disconnect
4. Sessions panel, Transport tab, open MCP sessions with per-row end, paginated
5. Documentation panel, client setup walkthroughs, the live tool inventory, REST reference, and troubleshooting
6. Activity Log, every tool call and OAuth event with its outcome
7. OAuth consent screen shown when a client authorizes

== Changelog ==

= 0.15.0 =

Safe whole-page Elementor writes, a single switch path for plugin cards, writable-option presets that actually apply over MCP, opt-in non-public post types, and a settings UX pass.

**Safe whole-page Elementor writes (#300, closes #291).** New elementor_set_page_data tool writes a whole page tree through one pipeline: element id repair (valid caller ids are kept), validation, class-key and builder-mode warnings, a revision, CSS cache flush, read-back, an undo token, dry_run, and an outline of the result. Previously wp_update_post_meta stored _elementor_data verbatim, so a tree written without element ids made Elementor emit selectors that matched every element and one element's styles covered the whole page while the write reported success. wp_update_post_meta now routes _elementor_data through the same pipeline. The Elementor build prompt bootstraps pages with elementor_set_page_data instead of a "[null]" placeholder.

**Plugin card switches (#299).** The Yoast SEO and Rank Math cards can be switched on again: a card without native tools used to render an ability-namespace switch that posted an empty namespace, and their abilities landed on a second card. Plugin_Catalog is now the single plugin identity (namespace aliases, owned namespaces, on/off/partial state) and Plugin_Switch is the one write path for a card. The Plugins sub-tab gains an "Enable all plugins" switch with the security acknowledgement on the way on.

**Writable options apply on MCP requests (#298).** The filter that merges the admin's Settings > Permissions picks (including the Yoast and Rank Math presets) was registered only on admin screens, so wp_update_option refused every admin pick over MCP. It is now registered on every request. Behaviour change: sites that ticked a preset will now actually allow those option writes over MCP. The allow_option_writes toggle and the permanent denylist are unchanged.

**Opt-in non-public post types (#289, closes #7).** New more_mcp_allowed_post_types filter (empty by default) lets wp_create_post and wp_get_posts accept explicitly opted-in non-public post types such as elementor_snippet. Per-type capability checks still apply, and listing a non-public type needs its edit_posts capability. wp_get_post_types reports a public flag and lists opted-in types. Also fixes an "Undefined array key status" warning in wp_create_post.

**Settings UX (#279).** The Activity Log now lives inside the settings shell as an Operate panel, with plain-language action labels (raw identifier kept beneath for support) and the old log page redirecting to it. The Sessions monitor gets consistent Last activity and Expires columns, truncated client ids with the full id on hover, and a toolbar for End all sessions. Visual polish: quieter version line, warning icon on high-risk pills, consistent disclosure chevrons, neutral client avatars.

**Activity Log retention and tech debt (#278).** Log retention is now configurable from the Logs screen (GitHub #22). The legacy SSE routes (/sse, /messages) were removed. Elementor kit and Site Settings tools moved into their own class.

**WordPress.org readiness (#280).** Plugin Check fixes: unslashed and sanitized toggle input, the ABSPATH guard moved to the head of Server.php, distinct Author and Plugin URIs, the readme Description trimmed under the 2,500-character display limit, and Tested up to raised to 7.1.

= 0.14.0 =

Depth-standard release: 69 new read-only integration tools across five waves, so an agent can enumerate each integrated plugin's primary objects instead of only counting them. Every tool was verified against its host plugin's real source before shipping, reads through the plugin's own models and APIs where one exists, and follows the existing gates (per-integration admin toggle, plugin detection, manage_options, capability checked before availability). No new write surface.

**Wave 1 (38 tools, 24 adapters).** Booking (Amelia, Bookly, LatePoint, BookingPress): staff, event, and location catalogues. LMS (LearnPress, Sensei, Masteriyo, Tutor, LifterLMS): course catalogues, LearnPress categories, LifterLMS memberships. Membership and CRM (Paid Memberships Pro, FluentCRM, MailPoet, Groundhogg): order aggregates, discount codes, lists, tags, segments. Events (The Events Calendar, Sugar Calendar, Events Manager): venues, organizers, calendars, categories, locations. Directories and commerce (GeoDirectory, Business Directory, Easy Digital Downloads, FluentCart): categories, plans, discounts, coupons. Image optimizers (Imagify, EWWW, Smush, ShortPixel): settings plus unoptimized and pending queues.

**Wave 2 (9 tools, 8 adapters).** Redirection log groups and grouped-log aggregates, Relevanssi top search queries, Ivory Search form catalogue, WP Job Manager job types and categories, WP Go Maps marker aggregates, an AutomatorWP listing rework through the plugin's own ct_setup_table()/CT_Query layer, and more.

**Wave 3 (9 tools, 10 adapters).** The multi-provider families: GiveWP donation-form catalogue (price mode, goals, per-form earnings), Charitable campaign goals and donor aggregates, membership and affiliate surfaces (Restrict Content, Simple Membership, SliceWP), community (BuddyPress, bbPress), social publishing (Blog2Social), and real estate (Estatik).

**Wave 4 (7 tools, 8 adapters).** Backup, security, and language families: BackWPup archive inventory, WPvivid backup schedule, All-in-One WP Migration export/import status, Patchstack grouped event log, Sucuri SiteCheck verdict from the local scan cache, Really Simple Security header config, TranslatePress machine-translation usage.

**Wave 5 (6 tools, 6 adapters).** Redis Object Cache, Autoptimize, WP-Optimize, LiteSpeed, UpdraftPlus, and Fluent Support.

**Plugin directory review fixes (#271).** Ten missing index.php directory stubs added; stale 1.4.x version gates dropped from the FAQ.

**PHPCS scan fixes and trademark-safe rename (#273).** All 89 ERROR-level findings from the 2026-09-07 WordPress.org PHPCS scan fixed, including PHP 7.4 compatibility (str_starts_with replaced with strpos comparisons), wp_strip_all_tags where the plugin API offers it, and normalized ABSPATH guards. The plugin display name was renamed to trademark-safe wording. Audit batch 1 made one ineffective-ON toggle report its real state honestly instead of claiming to be enabled.

= 0.13.1 =

Bug-fix release for issues found in live 0.13.0 verification.

* Fixed the "Thank you for creating with WordPress." admin footer note rendering on top of the settings screen's sidebar on short viewports. WordPress core anchors #wpfooter with position: absolute relative to #wpcontent, independent of this screen's own content height; on a short window (or with browser dev tools open) that anchor point could land partway up the sticky sidebar instead of below it. The footer is now forced back into normal document flow on this screen, so it always renders below the sidebar.

= 0.13.0 =

Full-site editing parity: the two axes an agent could not reach on either supported builder.

**FSE global styles (theme.json).** Two new tools, blocks_get_global_styles and blocks_update_global_styles, cover the Site Editor's Styles sidebar: the colour palette, typography scale, spacing presets, layout widths, and per-block style overrides a block theme renders from. This is the Gutenberg counterpart of the Elementor kit tools, and until now it was the largest same-axis asymmetry between the two builders: Elementor had four tools for site-wide design tokens and Gutenberg had none. wp_update_theme_mod was not a substitute, because a block theme keeps almost no styling in theme mods, and blocks_update_template changes structure rather than the style system.

Three behaviours are worth knowing before the first call:

* Writes merge by default. The keys you send are merged into the existing user styles, so sending only settings.color.palette leaves typography and every per-block override untouched. replace_settings: true swaps the whole object and reports which top-level keys it discarded.
* A list value is replaced wholesale, never merged element by element. theme.json presets (settings.color.palette, settings.typography.fontSizes, settings.spacing.spacingSizes) are lists, and merging two lists position by position would pair the first incoming colour with the first existing one and produce a palette matching neither input. Read the current list first and send it back complete. The keyed siblings around the list still merge normally.
* Reads default to a key index, not the values. Merged theme.json on a real block theme carries a section per registered block and does not fit in one tool result, so blocks_get_global_styles returns one row per dot-path describing its shape. Pass keys: ["settings.color.palette"] for actual values, or include_all: true for everything. The same reason elementor_get_kit indexes by default.

Reading never creates the storage row, so inspecting a pristine site leaves nothing behind, and the three origins are separately addressable: user (what the Site Editor saved, and the only writable layer), theme (the theme's own defaults, useful for discovering which preset slugs exist), and merged (what actually renders). A classic theme is refused rather than written to, because nothing renders from theme.json there.

**Per-post render context.** wp_list_page_templates and wp_set_page_template reach _wp_page_template, the meta value deciding which template renders one post. This was the only full-site axis missing from both builders: an agent could set the site-wide default (Elementor's kit carries default_page_template) but not override it for a single page, so "make this one landing page a bare canvas with no header or footer" had no tool. The choice list is resolved live through WordPress rather than hardcoded, which is what makes one tool correct across every setup: Elementor's page-templates module injects Elementor Canvas, Elementor Full Width, and Theme through the theme_{$post_type}_templates filter, and a block theme's custom templates arrive through the same call.

A value outside the resolved list is refused by name, with the valid values listed. That matters more than it looks: _wp_page_template is a plain meta key, so any string at all stores cleanly and reads back correctly, then falls through to the default at render time. A blind write would report success, round-trip perfectly, and change nothing visible.

Both writes verify against storage rather than trusting the write call, both support dry_run, and the global-styles write emits an undo token restoring the complete prior object. Setting a template on an Elementor-built page invalidates Elementor's cached render state, since the template choice decides which chrome the cached HTML sits inside.

**One card per plugin, one master toggle.** The Access screen now shows one card per plugin with a single master switch in the card header, and that one switch governs the whole card: the integration's own tools, its writable-settings allowlist, and the abilities it imports through the WordPress Abilities API. The per-row switches and the separate global imported-abilities gate are gone. A plugin's imported abilities are governed by its own card, all-or-nothing, so there is exactly one place to turn a plugin's entire AI surface off and no second gate elsewhere to be surprised by.

An imported ability that duplicates an admin-enabled native integration no longer appears at all: it is absent from tools/list, absent from the card, and refused by name with an explanation. This resolves the WooCommerce confusion, where one card covered the native integration and a second card covered the same plugin's imported abilities, leaving two routes to the same operation under different rules. With the native integration off, importing remains the only route and the ability stays importable.

Upgrading keeps at least the surface you had: stored per-ability selections promote their whole namespace, and the previous global master-on state promotes every registered namespace. Nothing narrows on update.

**One word for what an agent can call: ability.** The user-facing vocabulary is now consistent. What the WordPress Abilities API exposes is an ability; what WordPress permissions gate is a capability. The "What this site can do" grouping panel on the Access screen is retired (it showed the same detection data as the plugin cards, only grouped per area), and the admin copy now speaks of imported abilities. The agent-facing resource keeps its per-area grouping under the same more_mcp://capabilities address, so clients and skills holding that URI keep working.

**The capability map now reports every integration.** The more_mcp://capabilities resource and the admin summary listed 25 of the integration fronts that publish a manifest; the other 56 did the work of declaring themselves and the map discarded it. The map now lists every manifest-publishing front, and the eleven capability areas this exposed for the first time (affiliate, ai_media, automation, data_models, directories, job_board, mapping, real_estate, search, snippets, social_publishing) carry proper labels instead of falling through to an empty fallback row.

**Snippet undo buttons now work.** Since the Activity Logs screen grew its Undo panel, every snippet write rendered an Undo button that threw an error when clicked: the tokens were minted with operations the undo dispatcher had no branch for. Both are redeemable now. Undoing a snippet update restores the previous code and name and deliberately never touches the snippet's active state: if a human activated the snippet after the write, the restore puts the old body back into the live snippet and leaves it live, and the response says so. Undoing a snippet create deletes the snippet it created and reports whether the code had been activated in the meantime, because in that case the undo removed running code. Locked snippets are refused with a pointer to the Code Snippets UI rather than silently reverted.

**Also fixed.** elementor_update_kit was missing from the destructive-tool classifier that feeds more_mcp_tool_context for audit and approval consumers, despite its own description calling itself a site-wide change and emitting an undo token. It read as non-destructive to those consumers. Both site-wide design-token writes are now classified together, and tests/destructive-classifier-test.php pins them.

= 0.12.0 =

Forty-three new third-party integrations, an MCP prompts surface, Elementor write safety, and a round of security fixes.

The integration catalogue grows from 36 to 79 (pinned by tests/integration-toggles-test.php). Five further providers join existing aggregate toggles rather than adding their own: Burst Statistics, Matomo, and Independent Analytics under Analytics, and Formidable Forms and Forminator under Forms.

New integrations (each opt-in, off by default, and only registered when its host plugin is active):

* Booking, a new capability with four read-only providers: Amelia, LatePoint, Bookly, and BookingPress.
* Membership, a new capability with four read-only providers: Paid Memberships Pro, Simple Membership, ProfilePress, and Restrict Content.
* Donations, a new capability with two read-only providers: GiveWP and Charitable.
* Commerce extended beyond WooCommerce with two read-only providers: Easy Digital Downloads and FluentCart.
* LMS extended to five read-only providers: Sensei LMS, Masteriyo, Tutor LMS, and LifterLMS join LearnPress.
* Events extended to three read-only providers: Sugar Calendar and Events Manager join The Events Calendar.
* Community, a new capability with two read-only providers: bbPress and BuddyPress.
* Directories, a new capability with two read-only providers: GeoDirectory and Business Directory Plugin.
* Search, a new capability with two read-only providers: Relevanssi and Ivory Search.
* Automation, a new capability with two read-only providers: AutomatorWP and Uncanny Automator.
* Helpdesk, a new capability with one read-only provider: Fluent Support.
* Job board, a new capability with one read-only provider: WP Job Manager.
* Affiliate, a new capability with one read-only provider: SliceWP.
* Social publishing, a new capability with one read-only provider: Blog2Social.
* Real estate, a new capability with one read-only provider: Estatik.
* Mapping, a new capability with one read-only provider: WP Go Maps.
* Page building extended to five providers: Beaver Builder and SiteOrigin Page Builder join Elementor, Divi, and the Gutenberg subsystem, both read-only (outline plus addressed node read).
* CRM extended to three read-only providers: MailPoet and Groundhogg join FluentCRM.
* Analytics extended to six read-only providers: Burst Statistics, Matomo, and Independent Analytics join Site Kit, Jetpack, and MonsterInsights. Matomo and Independent Analytics read the local database only.
* Multilingual completed to four read-only providers: Weglot joins TranslatePress, Polylang, and WPML.
* Backup completed to five read-only providers: All-in-One WP Migration joins UpdraftPlus, BackWPup, Duplicator, and WPvivid.
* Security completed to six read-only providers: Patchstack joins Wordfence, WP Defender, Solid Security, Sucuri Security, and Really Simple Security.
* Forms extended to seven providers: Formidable Forms and Forminator join Gravity, Fluent, WPForms, Ninja, Contact Form 7, and Elementor Pro Forms.
* Data models, four providers: Pods, Custom Post Type UI, MB Custom Post Types, and Toolset Types. CPT UI, Toolset Types, and MB Custom Post Types also accept registration-definition writes (create, update, delete), each behind its own confirmation.

New:

* MCP prompts surface: prompts/list and prompts/get expose six workflow prompts, including build_elementor_site_structure, an Elementor site-decomposition playbook.
* Elementor writes now snapshot the page as a WordPress revision before writing, so a builder edit is recoverable through the normal revision UI. Elementor's own revision hook does not fire on a meta write, so the revision is created deliberately before the write.
* Elementor per-element edit trail: each element records who changed it and when, surfaced in the reads that precede a write.
* Undo panel on the Logs screen: stored undo snapshots are visible and reversible from the admin, rather than only through a token returned to the client.
* A .pot translation template now ships in languages/, generated by a dependency-free tokenizer-based extractor (tools/make-pot.php, developer tooling, not shipped in the plugin zip).

Fixed:

* snippet_update reported success on a locked Code Snippets snippet while the plugin silently discarded the new code and name, and emitted an undo token for a change that never happened. The write is now verified against what was stored, a locked snippet is refused by name before the confirmation round-trip, the undo token is stored only once the write has landed, and both snippet reads report the locked state (GitHub #246).
* The same defect existed at write sites throughout the integrations: the undo token was minted before the write, so a write that failed or was silently discarded still handed the caller a token for a change that never happened. Since the Undo panel now lists stored snapshots on the Logs screen, such a token was visible to an administrator as a reversible change that is not one. Every write tool that emits a token now performs the write, reads the value back out of storage, classifies the outcome, and only then mints. A write the store discarded is refused by name rather than reported as success, and no token is issued for it; a write the store landed but normalized keeps its token and says so. A post-write read that itself fails reports the outcome as unknown rather than aborting, which would leave the site changed and the caller with nothing to reverse it (GitHub #248).
* Rate limiter counted on storage that can silently fail open, so the limit could never fire on an install whose object cache backend was down (GitHub #182).
* Webhook URL validation resolved hostnames before allowing delivery, closing an SSRF path; a follow-up closed literal IPv6 targets that bypassed the same check (GitHub #184).
* The /token endpoint gained its own rate limiter, 429 responses carry an exact Retry-After, and MCP POST bodies are capped (GitHub #185).
* The API key was rendered into the settings page source, and a follow-up removed it from the Documentation setup guide as well (GitHub #186).
* Access panel scope cards surface a missing-dependency state instead of appearing available (GitHub #183).
* The reverse-proxy single-bucket rate-limit limitation is now documented rather than silently surprising (GitHub #187).
* Elementor connectors reported dead: element ID assignment, no-op condition writes, and oversized payloads (GitHub #240).
* Prompt availability notes read the advertised tool list rather than the core registry, so a prompt no longer claims a tool the client cannot see (GitHub #241).

Changed:

* Plugin display name updated for directory clarity (trademark-safe wording, no functional change).
* Documentation reconciled with the shipped catalogue: 79 integrations are catalogued and pinned by tests/integration-toggles-test.php, and integration activation is documented as two-gate (host plugin detected at runtime AND the admin toggle on).

= 0.10.0 =

Critical tools/list fix, two more multilingual providers, and Elementor widget-type discovery.

Fixed:

* Critical: a single third-party WordPress ability whose input schema was not object-typed made strict MCP clients reject the entire tools/list, hiding every tool from the agent ("Invalid input: expected object" at tools.N.inputSchema.type). Imported ability schemas are now coerced to an object-typed schema for advertisement; the ability's own execution-time validation is unchanged.

New:

* Multilingual coverage extended to Polylang and WPML (read-only), beside TranslatePress: list languages, resolve a post's or term's language, and list its translation siblings. No secret is read; WPML element types are validated before any filter fires.
* Elementor widget-type discovery: elementor_list_widget_types and elementor_get_widget_type_schema read the live widget registry, so any installed Elementor addon pack's widgets become discoverable and usable through the existing Elementor widget tools without a per-vendor adapter.

Changed:

* Access panel (formerly Permissions) copy: remaining "Permissions" labels updated to "Access", and the dense High-impact scope band now keeps each scope's lead and consequence inline while moving the mechanics behind a "How this works" disclosure.

= 0.9.0 =

Broad integration expansion plus a Settings redesign.

New integrations (each opt-in, off by default, and only when its host plugin is active):

* Cache category completed to six providers: W3 Total Cache, Redis Object Cache, Autoptimize, and WP-Optimize join the existing LiteSpeed and WP Rocket adapters. Purges drive each plugin's own public API, never direct file or option deletion.
* Two more outgoing-mail providers behind the existing email_get_status contract: FluentSMTP and Post SMTP, joining WP Mail SMTP and Easy WP SMTP. Credentials are never returned (FluentSMTP is read from its raw option so its own decrypting helper is never called).
* Three read-only security-status providers: Solid Security, Sucuri Security, and Really Simple Security, alongside the existing Wordfence and WP Defender. No secret is ever read or returned.
* Two read-only backup-status providers: Duplicator and WPvivid, alongside UpdraftPlus and BackWPup. Package hashes, local storage paths, and log paths are never returned.

Changed:

* Settings screen redesigned around information architecture: five section-grouped panels (Connection, Access, Sessions, External Services, Documentation). Authentication and the two credential-recovery actions consolidate into Connection; the retired Capabilities panel folds into Access as a "What this site can do" summary. Old ?panel= URLs are aliased so existing links keep working, and the session-length and credential-preservation wiring is unchanged.

= 0.8.0 =

Elementor Pro coverage plus a round of bug fixes.

New Elementor Pro tools (present only when Elementor Pro and the relevant module are active):

* Custom Fonts and Custom Icons: list, get, create/update/delete custom fonts (regenerating the derived @font-face so the font actually renders), and list/get/delete custom icon sets. All gated on edit_theme_options; deletes preview via dry_run and emit undo tokens.
* Custom Code: list, get, create, update, delete site-wide Custom Code (head / body_start / body_end HTML/JS). Writes carry the full code-write envelope reused from code snippets: the "Allow code snippets" toggle, two-part confirmation, saved inactive (a human publishes it), Markdown fence stripping, PHP refused, and undo on every write.
* Elementor Pro Forms as a sixth Forms submission provider: the existing forms tools (list entries, get entry, stats, update status, trash) now work for Elementor Pro form submissions with no new tools. Trash uses Elementor's move-to-trash (never a hard delete), spam is refused by name (Elementor has no spam state), and IP/user-agent are redacted on read.

Fixed:

* wp_delete_plugin no longer fatals when deleting a plugin whose uninstall touches .htaccess (missing extract_from_markers()); the plugin directory is no longer left half-removed.
* Code Snippets writes no longer fatal at runtime when the plugin is active but its write API did not load in the request; the integration keys on the read primitive and verifies the write API at call time with a clear diagnostic instead of a fatal.
* acf_update_field now refuses an unregistered field instead of reporting success while writing orphan meta that no field definition points at.

= 0.7.2 =

Bug-fix release for issues found in live 0.7.1 verification.

* Fixed a data-loss bug on the Permissions screen: enabling an option-source toggle (or any save-on-change control) silently wiped the writable-options allowlist. The sanitize callback that runs on every settings write only understood the newline-textarea shape and rebuilt the AJAX array shape as empty, so an enabled source reverted to off on reload. It now accepts both shapes.
* Fixed a fatal in the Code Snippets integration: snippet_create and snippet_update called the plugin's global functions unqualified from a namespaced file (resolving to non-existent functions) and passed an array where a Snippet object is required. Both are corrected; the write path works and its guarantees are unchanged (CSS/JS only, PHP refused, saved disabled, active state never flipped).
* Plugins tab: plugins that are not installed no longer render as cards, cards sort enabled-first, and the "Show all abilities" disclosure now starts collapsed. Discovered abilities group into one all-or-nothing toggle per plugin namespace instead of one switch per ability, and WordPress core ability namespaces are no longer shown as a third-party plugin.
* Documentation corrected: integrations are opt-in and off by default (the tools appear only after an administrator enables each one), and the tool counts in both readmes now match the shipped registry.

= 0.7.1 =

Permissions screen fix (follow-up to the 0.7.0 redesign).

* Discovered abilities now group into one card with one all-or-nothing toggle per plugin namespace, instead of one switch per ability. A plugin that registers many abilities under a single namespace (for example Secure Custom Fields, with dozens under scf/) previously rendered a switch for each; the individual abilities now sit behind a "Show all" disclosure. The namespace toggle reads on only when every ability under it is enabled, so flipping the parent never silently narrows a hand-picked subset.
* WordPress core ability namespaces (core/, wp/, wordpress/) are no longer shown as a third-party plugin card. They are WordPress's own abilities, the same source as the WordPress tab's core scopes, not a plugin.

