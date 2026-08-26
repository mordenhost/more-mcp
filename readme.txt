=== More MCP – Secure AI Connector for Claude, ChatGPT & Gemini ===
Contributors: moremcp
Tags: mcp, ai, claude, chatgpt, gutenberg
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 0.10.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security-first MCP server. Connect Claude, ChatGPT & Gemini to WordPress with API key auth, rate limiting, audit logs, and Gutenberg block tools.

== Description ==

More MCP, by Mordenhost, is a security-first Model Context Protocol (MCP) server for WordPress. It gives AI platforms like Claude, ChatGPT, and Google Gemini typed, permission-checked access to your WordPress content, with the authentication, rate limiting, and audit logging that most MCP bridges leave out.

Many public MCP servers accept tool calls with no authentication at all, an open door on a production site. More MCP takes the opposite stance: every session authenticates with OAuth 2.0 or an API key, every request is rate-limited per IP, every tool call passes a WordPress capability check, and every interaction is logged for audit.

= Why Security Matters for MCP =

MCP gives AI agents the ability to read, create, update, and delete your WordPress content. Without proper authentication, anyone who discovers your MCP endpoint can:

* Read all your posts, pages, and media
* Create or delete content
* Access user data and plugin information
* Overwhelm your server with rapid-fire requests

More MCP prevents all of this with API key authentication on session initialization, timing-safe key comparison, per-IP rate limiting (60 requests/minute), and a full activity log of every MCP interaction.

= Requirements =

* **PHP 7.4 or later.** PHP 8.0+ is recommended, it is what the plugin is developed and tested against, and PHP 7.4 reached end of life in November 2022, so it no longer receives security fixes from the PHP project.
* **WordPress 5.8 or later.** Tested up to WordPress 7.0.
* **MySQL 5.6+ or MariaDB 10.1+.** Activation creates five tables.
* **HTTPS**, for any client using the OAuth connector flow. The OAuth 2.0 specification requires it, and Claude Desktop's native connector will not complete a handshake over plain HTTP. Static API-key auth works without it but sends the key in a header, so HTTPS is strongly recommended there too.
* **Pretty permalinks** must be enabled (Settings → Permalinks, anything other than "Plain"). The OAuth endpoints are served from domain-root rewrite rules, which do not exist under the plain permalink structure.
* **WordPress 6.9 or later** for the Abilities API surface. This is optional, the MCP endpoint and the REST API work on 5.8+ regardless.

= Free, Self-Hosted, Fully Featured =

More MCP is fully featured in its free, GPL-licensed release. There is no Pro version &mdash; all tools ship in the wp.org plugin, and updates go through the standard WordPress plugin updater.

Your credentials stay on your server. More MCP runs entirely inside WordPress: API keys, OAuth tokens, and session state all live in your own database. More MCP makes no outbound connections to any vendor server &mdash; no license check, no telemetry, no traffic beacon. If you prefer to keep AI inference local too, Ollama and LM Studio are first-class platforms alongside Claude, ChatGPT, and Gemini.

= 107 Always-On Tools + Opt-In Integrations =

More MCP always registers **107 tools** regardless of which other plugins are installed: 85 WordPress-core tools, 19 Gutenberg block tools (blocks are core WordPress, so they are never gated on a third-party plugin), and 3 outbound-webhook management tools. On top of that, opt-in third-party integrations contribute more tools when you enable them (WooCommerce alone adds 29), and the plugin/theme lifecycle tools appear when their admin toggle is on. Exact live counts are shown in the Documentation panel, which reads the registry directly.

**WordPress Core (85 tools):**

* Posts - create, read, update, delete, search, count (any registered public post type, featured images supported)
* Pages - full CRUD with parent page support
* Post Types - discover all registered public post types on the site
* Post Revisions - list revision history and roll a post back to any prior version
* Media - browse, upload from URL or base64, update alt text/caption/title/description, set as featured image, delete
* Comments - create, read, delete; full moderation suite (list pending, approve, mark spam, trash)
* Users - display names and roles (emails and usernames are not exposed)
* Categories & Tags & Custom Taxonomies - create, update (rename/re-slug/edit/move), delete, assign, count, discover all registered taxonomies
* Term Meta - read, update, delete raw `wp_termmeta` values. For SEO fields on a term, use the SEO Meta tools below instead: Yoast keeps taxonomy SEO in an option and All in One SEO in its own table, so neither is reachable through term meta
* Menus - list menus, list menu items, create / update / delete / reorder menu items
* Post Meta - read, update, delete custom fields (works with ACF, MetaBox, JetEngine, Pods, CPT UI)
* SEO Meta - read and write title/description/focus keyword/noindex/canonical/OG/Twitter fields on posts **and terms**, across six SEO plugins (Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, The SEO Framework). Each field is routed to wherever the detected plugin actually stores it; a field that plugin does not store is refused by name rather than written to a plausible-looking key that would save cleanly and change nothing on the page
* Site Info - site name, description, WordPress version, timezone
* Site Status - full site health snapshot (WordPress version, PHP version, active theme, active plugins, cron activity) for AI-driven pre-write validation
* Error Log - read recent PHP error log entries so AI agents can diagnose silent failures without shell access
* Cron Schedule - list scheduled WP cron events with next-run timestamps and hook names
* Connection Health - MCP session diagnostic returning route, auth method, session ID, and More MCP version details for any authenticated caller
* Plugins & Themes - list installed plugins and themes with active status
* Theme Appearance - get active theme, read/write theme mods (gated by admin toggle + allowlist), read/write Custom CSS
* Search - full-text content search across post types
* Permalink Structure - read and update permalink settings (gated by admin toggle)
* Options - read allowlisted core options, read full plugin settings by slug (sensitive keys redacted), and write to allowlisted options when an admin enables it

= Plugin & Theme Management (10 tools, opt-in) =

Disabled by default. Enable "Allow AI to manage plugins and themes" under Settings → Permissions to expose these; while the toggle is off they are not listed to MCP clients at all.

These tools change the code running on your site, not just its content, so they are gated more tightly than anything else in the plugin:

* Every write requires the matching WordPress capability, re-checked inside the handler
* Every write requires a two-part confirmation: `confirm=true` AND `confirm_slug` echoing the exact target. A call without confirmation returns a preview of what would change, so the first call is always a dry run
* `wp_install_plugin` accepts WordPress.org slugs only, package URLs are rejected rather than downloaded, and the preview shows name, author, version, and active-install count so a typo-squat is visible before you confirm
* More MCP cannot deactivate or delete itself; the `more_mcp_protected_plugins` filter protects others
* Deleting a plugin requires deactivating it first, deletion is irreversible, so deactivation is the checkpoint
* Sites whose filesystem needs FTP/SSH credentials are refused rather than prompted; credentials must never travel through MCP arguments
* Every operation returns the state read back afterwards, so an upgrader that silently no-ops is visible rather than reported as success

Plugins: list pending updates, activate, deactivate, update, install (wp.org), delete.
Themes: list status with pending updates, activate (switch), update, delete.

= Plugin Integrations (Opt-In) =

More MCP detects compatible plugins and adds specialized MCP tools. As of 0.6.0 each third-party integration is **opt-in and off by default**: turn it on per product under Settings → Permissions. While an integration is off, its tools are absent from the tool list entirely. Core content tools, Gutenberg blocks, the SEO subsystem, and the lifecycle tools are unaffected.

**WooCommerce Integration (29 tools):**
When WooCommerce is active, AI agents can manage your store end-to-end:

* Browse and search products by category, status, or type
* Create and update simple and variable products with prices, SKUs, stock levels
* Manage variable products, list, get, create, update, delete, and batch-update product variations
* Manage global attributes (`pa_*` taxonomies), list registered attributes, list attribute terms, register new attributes, assign attributes to a product as variation axes
* Manage coupons, list, search by code, get, create, update, delete (trash or permanent), and bulk-purge trash; supports all standard WC coupon fields (discount type, expiry, usage limits, product/category restrictions, email allowlists)
* View orders, order details, and update order status
* List customers with order count and total spent
* Get store statistics, revenue, order count, average order value by period

**Elementor Integration (18 tools):**
When Elementor (free or Pro) is active and you enable the integration, AI agents can clone and customize existing Elementor pages without trying to generate page-builder JSON from scratch:

* Clone an existing Elementor page with a new title and fresh element IDs (so the duplicate opens in the editor without ID collisions)
* Bulk-replace text across heading, text-editor, button, image-box, icon-box, icon-list, testimonial, tabs, accordion, toggle, star-rating, call-to-action, and flip-box widgets
* Swap image URLs across image, image-box, background_image, and gallery widget settings
* Get a compact outline of any page (section/container hierarchy, widget types, text snippets, and each node's element ID) so Claude can reason over a full page in a few KB instead of the raw JSON
* Read full settings for a single widget/container/section/column by ID (for precise agent editing without loading the entire page tree)
* Add a widget or container to an existing page, either from curated parameters for the common widget types or from a full settings object
* Change one element's settings in place, addressed by element ID. Settings are **merged** by default, not replaced: an Elementor settings object holds content next to styling, so a caller fixing a heading typo does not discard the widget's colours and typography. A wholesale replace is available but has to be asked for
* Delete one element and its descendants, addressed by element ID. The response reports how many elements went, and a dry run reports it before anything is removed
* Move one existing element to a new location (before/after a reference element, or as its first/last child) within the same page, keeping the element's own settings and children. Supports a dry run
* Resolve a Loop Grid / Loop Carousel widget to the separate loop-item template it renders: returns the loop template's post ID and element outline, which you then edit with the ordinary Elementor tools by that post ID
* The addressed writes (update, delete, move) take an `expected_widget_type` guard and emit an undo token. Element IDs are per-document and shift when a page is rebuilt, so a stale ID would otherwise edit, delete, or move the wrong part of the page
* Atomic widgets and containers (Elementor 4.0+ Editor V4 elements) remain opaque, we never decode Atomic schemas because Elementor itself may shift them. Editing an Atomic widget whose type is stored in `widgetType`, or an Atomic container whose `e-*` type is stored directly in `elType`, is refused by name for that reason. Deleting or moving either is allowed, because relocating or removing a whole element needs only its boundaries, not its schema. Raw creation of an Atomic widget remains a caller-supplied opaque passthrough; More MCP does not construct its settings schema.

**Compatibility target:** the current source contracts are verified against Elementor Core 4.2.2, and the post-deployment live matrix targets Elementor Pro 4.2.1 alongside it. This is a tested-against statement, not a numeric version gate: older and newer releases still load through Elementor's normal runtime detection. The Atomic-container guard and the Pro 4.2.1 candidate still require live editor/render verification after this build is deployed.

**Divi Integration (5 tools):**

* Read a compact positional outline from legacy Divi 4 `et_pb_*` shortcodes or native Divi 5 `divi/*` blocks without rendering the page or executing shortcodes
* Read one module or block by a dot-separated zero-based path returned by the outline
* Replace, insert, or delete one whole addressed node. Divi 4 writes accept raw balanced shortcode subtrees and preserve untouched bytes; Divi 5 writes accept whole `divi/*` blocks and pass the Gutenberg round-trip check
* All writes require `expected_type`, object-level `edit_post`, support `dry_run`, emit undo tokens, re-read stored content, and invalidate Divi's derived resources through the source-verified `ET_Core_PageResource` contract when available
* Divi 4 attributes are never reconstructed or merged; Divi 5 attributes remain whole-block/verbatim. Mixed legacy regions remain opaque and descendants beneath them are not addressable
* Theme Builder management, Theme Options, presets, library layouts, rendering, migration, and live Divi verification remain separate follow-up work

**Advanced Custom Fields Integration (4 tools):**
When ACF (free or Pro) is active, AI agents can read and write ACF fields with the field-type-aware formatting the ACF UI uses, instead of the raw serialized values WordPress meta returns:

* Read a single ACF field, formatted per its Return Format setting (hydrated post objects, parsed repeater rows, image arrays, etc.)
* Read every ACF field on a post in one call, with name/label/type/value bundled, the most efficient way for an AI to discover what fields exist and read them all
* Update an ACF field with type-aware value handling (scalar for text/number, array for repeaters and flex content, post ID for relationships, attachment ID for images)
* Enumerate ACF field groups on the site, optionally filtered by post type, for AI-driven discovery of available custom fields before reading/writing

**Redirection Integration (4 tools):**
When John Godley's Redirection plugin is active, AI agents can manage 301 / 302 / 307 redirects:

* List redirects with group + URL-substring filters
* Create new redirects (source, target, status code, regex, group, title)
* Update existing redirects (target, status, enabled state)
* List redirect groups

**Analytics Integration (3 tools, read-only):**
When Site Kit by Google, Jetpack Stats, or MonsterInsights is active, AI agents can read normalized analytics status and reports through the installed plugin &mdash; never by owning vendor credentials or calling a vendor API directly:

* Read provider status: which providers are active, connection/configuration state, and non-secret identifiers (GA4 property, measurement ID, active modules). Credentials, OAuth tokens, and API keys are never returned
* Read a normalized traffic summary over a bounded date range through the plugin's own supported local report path
* Read top content by views through the same plugin-mediated path
* When a provider cannot expose reports without its authenticated flow (Site Kit's REST OAuth, for example), the tool returns an explicit `report_unavailable` result rather than guessing private storage or calling a vendor API

**Forms & Lead Capture Integration (7 tools):**
When Gravity Forms, Fluent Forms, Contact Form 7, WPForms, or Ninja Forms is active, AI agents can work with forms and submissions through one normalized surface, the same tool shape regardless of which form plugin is installed (read-only where a provider stores no entries):

* List forms and read a form's normalized field schema (id, label, type, required)
* List submissions, paginated, returning privacy-safe summary rows only (id, date, status, read state), never field values, which can hold personal data
* Read one submission in full through an addressed call; sensitive metadata (IP, user agent, browser/device, payment/transaction fields) is redacted by default
* Read aggregate submission counts by status without returning any submission bodies
* Guarded writes: change a submission's status, or move a submission to trash (never a permanent delete). The trash tool takes a two-part confirmation (`confirm=true` plus `confirm_entry_id`), and both writes go through the provider's own API and emit an undo token
* An unsupported status for a given provider is refused by name rather than written to a plausible-looking value

= More MCP and the WordPress Core Abilities API =

WordPress 6.9 shipped the Abilities API in November 2025, a primitive that lets plugins register typed capabilities AI agents can call. Core ships three default abilities (site info, user info, environment info) and the `wordpress/mcp-adapter` package bridges abilities to the MCP protocol.

**Every More MCP tool also registers as a WordPress ability.** You get three ways to reach the same tools: (1) More MCP's native `/wp-json/more-mcp/v1/mcp` endpoint (unchanged and always available), (2) the WordPress MCP Adapter if you install it, More MCP registers a named `more-mcp-server` alongside adapter's default server, or (3) WordPress core REST directly at `/wp-json/wp-abilities/v1/abilities/{name}/run`. Same handlers, three transports, one set of per-tool capability gates. The abilities layer can be disabled with a single option flag if needed.

More MCP is a complete, production-ready MCP server that predates the official adapter. It runs the full Streamable HTTP transport, enforces API key authentication on every request, ships OAuth 2.0 for Claude Desktop's native connector flow, rate-limits per-IP, redacts sensitive data, and logs every interaction. Out of the box it registers 107 always-on tools (WordPress core, Gutenberg blocks, and outbound webhooks) plus opt-in integration tools that you enable per product for WooCommerce, Elementor, Divi, Advanced Custom Fields (ACF), Meta Box, Redirection, a supported analytics plugin (Site Kit, Jetpack Stats, MonsterInsights), a supported forms plugin (Gravity Forms, Fluent Forms, Contact Form 7, WPForms, Ninja Forms), and the other detected integrations.

= Supported AI Platforms =

* **Claude (Anthropic)** - Full MCP support via Claude Desktop, Claude Code, and VS Code
* **OpenAI / ChatGPT** - GPT-5.5, GPT-5, GPT-5 Mini, o3
* **Google Gemini** - Gemini 3.5 Flash, 3.1 Flash-Lite
* **Groq** - Llama 3.3, Llama 3.1, GPT-OSS
* **Azure OpenAI** - Azure-hosted OpenAI deployments
* **AWS Bedrock** - Claude, Llama, Titan models
* **Ollama / LM Studio** - Local self-hosted models (no external data transmission)
* **Custom MCP Servers** - Connect to any MCP-compatible endpoint

= Compatible Clients & Frameworks =

<!-- compliance: technical-context -->
More MCP works with any MCP-compliant client, IDE, or AI agent framework, no per-tool configuration required. Each entry below describes the specific integration path More MCP provides for that target, so customers can answer "will this work with the tool I already use?":

* **Desktop AI apps** - Claude Desktop (native MCP connector via OAuth 2.0), ChatGPT Desktop, Gemini Advanced.
* **AI code IDEs** - Claude Code, VS Code (with MCP extension), Cursor, Windsurf, Continue, Cline, Zed, JetBrains AI Assistant.
* **API testing tools** - Postman, Bruno, Insomnia (use the API key in the `MMCP-Key` header).
* **Custom field plugins** - Advanced Custom Fields (ACF) has dedicated `acf_*` tools that return values formatted per each field's Return Format setting (the same way the ACF UI shows them). MetaBox, JetEngine, Pods, CPT UI, and Custom Field Suite are supported through the `wp_get_post_meta` / `wp_update_post_meta` tools, so AI agents can populate custom fields just like a human editor.
* **Page builders** - Elementor has dedicated clone-and-customize tools. Divi has structural read tools plus guarded whole-node replace/insert/delete for Divi 4 shortcodes and Divi 5 blocks. Beaver Builder, Bricks, Gutenberg, Spectra, and Stackable remain reachable through standard post or block content, but builder-specific storage is opaque unless covered by a dedicated integration.
* **Multilingual** - WPML, Polylang, TranslatePress, qTranslate. Translated posts appear as separate posts and can be read or written via the standard post tools.
* **AI agent frameworks** - LangChain, AutoGen, CrewAI, LlamaIndex, Haystack - any MCP-compatible framework can call More MCP's tools.
* **AI app platforms** - Anthropic Console, OpenAI Playground, Google AI Studio, Vertex AI, Azure AI Studio, Amazon Bedrock Console.

= MCP Spec Compliance =

More MCP implements the [MCP 2025-11-25 Streamable HTTP transport specification](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports#streamable-http):

* Single `/mcp` endpoint for all JSON-RPC communication
* POST for client messages, GET for server-sent events, DELETE for session termination
* Cryptographically secure session IDs with transient-based storage
* Origin header validation to prevent DNS rebinding attacks
* Proper CORS handling for browser-based MCP clients

== External Services ==

This plugin connects to third-party AI services to enable AI platforms to interact with your WordPress content. **No data is transmitted until you explicitly configure and enable a platform connection.**

**What data is sent:** Your WordPress content (posts, pages, media metadata) as requested by the connected AI platform through authenticated MCP tool calls.

**When data is sent:** Only when you have configured a platform with API credentials AND enabled that platform connection AND the AI platform makes an authenticated request.

**Supported services and their policies:**

* **Anthropic Claude**: Used for Claude AI integration
  [Terms of Service](https://www.anthropic.com/legal/consumer-terms) | [Privacy Policy](https://www.anthropic.com/legal/privacy)

* **OpenAI**: Used for ChatGPT/GPT-4 integration
  [Terms of Use](https://openai.com/policies/terms-of-use) | [Privacy Policy](https://openai.com/policies/privacy-policy)

* **Google Gemini**: Used for Gemini AI integration
  [Terms of Service](https://ai.google.dev/terms) | [Privacy Policy](https://policies.google.com/privacy)

* **Groq**: Used for Groq LPU inference
  [Terms of Service](https://groq.com/terms-of-use/) | [Privacy Policy](https://groq.com/privacy-policy/)

* **Microsoft Azure OpenAI**: Used for Azure-hosted OpenAI models
  [Terms of Service](https://azure.microsoft.com/en-us/support/legal/) | [Privacy Policy](https://privacy.microsoft.com/en-us/privacystatement)

* **AWS Bedrock**: Used for AWS-hosted AI models
  [Terms of Service](https://aws.amazon.com/service-terms/) | [Privacy Policy](https://aws.amazon.com/privacy/)

* **Ollama / LM Studio**: Local self-hosted models (no external data transmission)

* **Custom MCP Servers**: User-configured servers (data sent to user-specified endpoints only)

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

No. WordPress 6.9 added the Abilities API, a primitive for registering AI-callable functions, and the `wordpress/mcp-adapter` package bridges abilities to the MCP protocol. More MCP is a full MCP server with the security layer, connector flows, and plugin integrations that the bare primitive does not include: enforced API key auth, OAuth 2.0 for Claude Desktop, per-IP rate limiting, audit logging, sensitive-data redaction, 107 always-on WordPress tools, and opt-in integration tools you enable per product for WooCommerce, Elementor, Divi, Advanced Custom Fields, Meta Box, Redirection, supported analytics plugins (Site Kit, Jetpack Stats, MonsterInsights), and supported forms plugins (Gravity Forms, Fluent Forms, Contact Form 7, WPForms, Ninja Forms).

= Does More MCP work with WooCommerce? =

Yes. When WooCommerce is active and you enable the WooCommerce integration under Settings → Permissions, More MCP adds 29 MCP tools spanning product management (simple and variable, including variation CRUD and global attribute management), full coupon management (list/get/create/update/delete + bulk trash purge), order management (view, create, update, update status, order notes), customer data, and store statistics. Integrations are opt-in and off by default; once enabled, the tools appear automatically in the MCP tools list.

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

About 90% of "can't connect" / "OAuth failed" / "tools missing" issues resolve in a basic 4-step pass before any host-specific fix is needed. In order: (1) update More MCP to the latest version (every recent release fixes meaningful OAuth edge cases), (2) run a conflict test, deactivate all other plugins, switch to a default theme like Twenty Twenty-Five, and purge every cache layer (any cache plugin, your host's server-level cache, Cloudflare/CDN, and browser cache), (3) wipe stale OAuth state, use the Reset OAuth State button in More MCP → Settings if you're on 1.4.17 or newer, or run the four `DELETE` SQL queries against the `more_mcp_oauth_clients`, `more_mcp_oauth_tokens`, `more_mcp_oauth_auth_codes`, and `more_mcp_sessions` tables, (4) check More MCP → Activity Logs for the most recent `oauth:` row, which records exactly which validation rule fired. Only proceed to host-specific fixes (Cloudflare AI Bots toggle, SiteGround `/.well-known/` static files, edge-cache exclusions) after the four basics are ruled out, most "advanced infrastructure" reports actually resolve in those four steps.

= I restored my WordPress database from backup and Claude can't reconnect. How do I fix this? =

When you restore from backup, the OAuth client credentials Claude was holding no longer match anything on the WordPress side, so Claude's connector ends up with a stale token that no More MCP installation will accept. The fix in More MCP 1.4.17+ is one click: go to **More MCP → Settings** and click the **Reset OAuth State** button. This wipes all stale OAuth clients, issued access/refresh tokens, and pending authorization codes. Then in Claude, delete the existing connector entirely, wait 30 seconds, and re-add it from scratch, the full OAuth flow runs fresh against the cleaned-up state and the connection works. On 1.4.16 or older the same effect can be achieved by emptying the `more_mcp_oauth_clients`, `more_mcp_oauth_tokens`, `more_mcp_oauth_auth_codes`, and `more_mcp_sessions` tables by hand. The plugin's settings, API key, and Activity Log are not affected by Reset OAuth State, only the OAuth handshake state.

= Claude says "Couldn't register with sign-in service" or "Session not found": what's wrong? =

Both messages (plus "no tools available" in Claude.ai after connecting) usually mean one of More MCP's OAuth or sessions database tables is physically missing. The fix is to update More MCP to 1.4.29 or newer, the new runtime healer detects missing tables and recreates them automatically on the next pageload, with no deactivate/reactivate required. After updating, delete the existing More MCP connector in Claude, wait 30 seconds, then re-add it fresh. If you can't update yet and need to recover immediately, the manual workaround is `wp option delete more_mcp_db_version` followed by loading any wp-admin page, that clears the stored schema version, so the installer re-runs and recreates the missing tables.

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

Yes. The `wp_get_posts` and `wp_create_post` tools accept a `post_type` parameter and validate it against registered public post types, so private or internal post types are not exposed. Plugin authors can disable specific tools entirely with the `more_mcp_disabled_tools` filter, or scope the option-write allowlist with `more_mcp_writable_options`. WordPress's standard capability checks also apply to every tool call.

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

