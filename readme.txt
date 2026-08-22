=== More MCP – Secure AI Connector for Claude, ChatGPT & Gemini ===
Contributors: moremcp
Tags: mcp, ai, claude, chatgpt, gutenberg
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 0.6.0
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

= 82 Core Tools + 69 Integration Tools + 19 Gutenberg Tools + 10 Lifecycle Tools =

**WordPress Core (82 tools):**

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

= Plugin Integrations (Conditional) =

More MCP automatically detects compatible plugins and adds specialized MCP tools. No configuration needed, if the plugin is active, the tools appear.

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

**Elementor Integration (12 tools):**
When Elementor (free or Pro) is active, AI agents can clone and customize existing Elementor pages without trying to generate page-builder JSON from scratch:

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
When Gravity Forms or Fluent Forms is active, AI agents can work with forms and submissions through one normalized surface, the same tool shape regardless of which form plugin is installed:

* List forms and read a form's normalized field schema (id, label, type, required)
* List submissions, paginated, returning privacy-safe summary rows only (id, date, status, read state), never field values, which can hold personal data
* Read one submission in full through an addressed call; sensitive metadata (IP, user agent, browser/device, payment/transaction fields) is redacted by default
* Read aggregate submission counts by status without returning any submission bodies
* Guarded writes: change a submission's status, or move a submission to trash (never a permanent delete). The trash tool takes a two-part confirmation (`confirm=true` plus `confirm_entry_id`), and both writes go through the provider's own API and emit an undo token
* An unsupported status for a given provider is refused by name rather than written to a plausible-looking value

= More MCP and the WordPress Core Abilities API =

WordPress 6.9 shipped the Abilities API in November 2025, a primitive that lets plugins register typed capabilities AI agents can call. Core ships three default abilities (site info, user info, environment info) and the `wordpress/mcp-adapter` package bridges abilities to the MCP protocol.

**As of 1.4.38, every More MCP tool also registers as a WordPress ability.** You get three ways to reach the same tools: (1) More MCP's native `/wp-json/more-mcp/v1/mcp` endpoint (unchanged and always available), (2) the WordPress MCP Adapter if you install it, More MCP registers a named `more-mcp-server` alongside adapter's default server, or (3) WordPress core REST directly at `/wp-json/wp-abilities/v1/abilities/{name}/run`. Same handlers, three transports, one set of per-tool capability gates. The abilities layer can be disabled with a single option flag if needed.

More MCP is a complete, production-ready MCP server that predates the official adapter. It runs the full Streamable HTTP transport, enforces API key authentication on every request, ships OAuth 2.0 for Claude Desktop's native connector flow, rate-limits per-IP, redacts sensitive data, and logs every interaction. Out of the box it includes 82 tools for WordPress core operations plus 64 integration tools that auto-load when WooCommerce, Elementor, Divi, Advanced Custom Fields (ACF), Redirection, a supported analytics plugin (Site Kit, Jetpack Stats, MonsterInsights), or a supported forms plugin (Gravity Forms, Fluent Forms) is active.

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

No. WordPress 6.9 added the Abilities API, a primitive for registering AI-callable functions, and the `wordpress/mcp-adapter` package bridges abilities to the MCP protocol. More MCP is a full MCP server with the security layer, connector flows, and plugin integrations that the bare primitive does not include: enforced API key auth, OAuth 2.0 for Claude Desktop, per-IP rate limiting, audit logging, sensitive-data redaction, 82 ready-to-use WordPress core tools, and 64 integration tools that auto-load for WooCommerce, Elementor, Divi, Advanced Custom Fields, Redirection, supported analytics plugins (Site Kit, Jetpack Stats, MonsterInsights), and supported forms plugins (Gravity Forms, Fluent Forms).

= Does More MCP work with WooCommerce? =

Yes. When WooCommerce is active, More MCP automatically adds 26 MCP tools spanning product management (simple and variable, including variation CRUD and global attribute management), full coupon management (list/get/create/update/delete + bulk trash purge), order management (view, update status), customer data, and store statistics. No additional configuration is needed, the tools appear automatically in the MCP tools list.

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

= 0.6.0 =

Broad capability-coverage release. New discovery surface, opt-in integration control, several new provider integrations, three new core tools, and a round of Elementor and dead-code fixes.

**Breaking: third-party integrations are now opt-in, default off.**

* Every third-party integration (WooCommerce, Elementor, Divi, the forms providers, and the rest) now starts disabled. Turn each one on per product under Settings then Permissions. While off, its tools are absent from the tool list entirely. Core content tools, Gutenberg blocks, the SEO subsystem, lifecycle tools, and the SEO-data providers are unaffected. This is a behaviour change: a site that relied on an integration's tools appearing automatically must now enable that integration once.

New:

* Capability map: a read-only view of what the site can do and which active providers back each capability, exposed as the more_mcp://capabilities MCP resource and a read-only Capabilities admin tab. Discovery only; it never changes how tools dispatch.
* Discovered-tools importer (opt-in): wrap abilities other plugins register in the WordPress Abilities API as MCP tools. Off by default; only abilities an admin explicitly enables are advertised, and each ability's own permission check still runs.
* Forms coverage extended to five providers: Gravity Forms, Fluent Forms, Contact Form 7, WPForms, and Ninja Forms, behind one forms tool contract, each provider's real storage model represented honestly (read-only where a provider stores no entries).
* New read-only status providers: Email/SMTP (WP Mail SMTP, Easy WP SMTP), BackWPup backups (second backup provider beside UpdraftPlus), Akismet spam, Imagify image optimization, TranslatePress multilingual, FluentCRM contacts, and LearnPress courses. None return credentials or personal data.
* Meta Box added as a second custom-fields provider beside ACF.
* wp_create_menu: create a navigation menu (and optionally assign it to theme locations) so the menu-item tools have something to populate.
* wp_set_front_page: set a static front page and posts page, validated (the page must exist, be a page, and be published) and written atomically.
* elementor_set_template_conditions: assign display conditions to a Theme Builder template through Elementor's own save path, so the template actually renders at its location.

Fixed:

* Elementor widget-registry classification is now deterministic: Pro and Theme Builder widget slugs are no longer rejected as unregistered depending on per-request bootstrap timing.
* Theme Builder widgets (title, featured image, excerpt, site logo) no longer require a dynamic binding they self-provide; a supplied binding is validated instead of a valid empty one being rejected.
* elementor_list_local_templates reads the real template type and its filter agrees with its labels.
* elementor_import_template returns the regenerated element IDs so callers can address the imported elements.
* more_mcp_connection_health no longer reports the WordPress core version as the Gutenberg plugin version when the Gutenberg plugin is absent.
* Removed dead code: the unused outbound MCP client, an unreachable SSE branch, and the orphaned mcp_servers option.

= 0.5.0 =

Adds a capability map for discovery: a read-only view of what the site can do and which active providers back each capability.

* New: the capability map derives, from each active integration's manifest, a capability-to-provider view (commerce, page building, forms, analytics, custom fields, redirects, caching, security, backup). Inactive providers never appear; multi-provider integrations report only their active sub-providers.
* New: an MCP resource, more_mcp://capabilities, lets an AI client read the site's capability surface from a single resource instead of inferring it from the tool list. The resources capability is now declared at initialize.
* New: a read-only Capabilities admin tab showing the same information for the operator. Discovery is not permission: appearing there means a provider is detected, not that any change is allowed. Every write still runs the caller's WordPress capability check and the Permissions toggles.
* Discovery only: tool dispatch is unchanged and page builders keep their own native tools; the map never merges them into one generic editor.

= 0.4.1 =

Maintenance release. No new tools or behavior changes.

* Hardened the build: shell scripts (build.sh, release.sh) and the GitHub-facing README.md are now excluded from the distributed plugin zip, with a backstop check that fails the build if any leak into staging.
* Stripped em-dashes from every user-facing and agent-facing string (tool descriptions, admin UI, exception messages, readme) so the text reads uniformly across clients.
* Documentation: recorded the engineering principles (YAGNI, SRP/Compose Method, WordPress best practices) in the tech-debt notes; corrected stale plugin/docs URLs.

= 0.4.0 =

**Removed the GuardPress, SiteVault, and ForgeCache integrations.** These three optional third-party integrations have been removed from the plugin entirely. Their 16 tools (GuardPress 7, SiteVault 6, ForgeCache 3) no longer register even when the host plugin is active.

* Removed: the `gp_*`, `sv_*`, and `fc_*` tool families and their runtime detection. A call to any of these tool names now returns "Unknown tool".
* Unchanged: the 101 always-registered core and Gutenberg tools, and every other integration, WooCommerce, Elementor, Divi, ACF, Redirection, LiteSpeed Cache, Analytics, and the forms providers.

**Elementor editing reaches the site-wide kit (global colors, typography, theme styles).** The existing Elementor tools only ever touched `_elementor_data` (per-page widgets and templates) and had no coverage of the kit document that holds a site's global colors, fonts, theme styles, layout defaults, and custom CSS. Five native `elementor_*` tools close that gap. They are native, not a proxy: nothing depends on Elementor's own MCP plugin being installed.

* New: `elementor_get_kit`, `elementor_get_kit_schema`, and `elementor_get_kit_fonts`: read the active kit's settings, its per-tab control schema, and its font list/groups.
* New: `elementor_update_kit`: patch kit settings. Merge-not-replace by default, matching `elementor_update_widget`: sending only `system_colors` leaves typography, layout, and custom CSS intact; `replace_settings: true` swaps wholesale and reports every discarded key. Supports `dry_run` and emits an undo token snapshotting the full pre-write settings.
* New: `elementor_sync_library_type`: set a library document's `_elementor_template_type` and its taxonomy together so the two never disagree.
* Safety: a kit write feeds every page's stylesheet, so it invalidates site-wide via `files_manager->clear_cache()` (the bulk clear Elementor runs on a kit save) plus the kit's own `_elementor_css`, distinct from the per-page derived-state clear the addressed writes use. `elementor_update_kit` and `sync_library_type` carry explicit `edit_theme_options` / `edit_post` capability maps rather than the too-loose `elementor_`-prefix default. Invalidation degrades to a warning and never throws, since it runs after the write commits.
* New: `tests/elementor-kit-test.php`: kit read/patch, merge-versus-replace semantics, dry-run, capability maps, site-wide invalidation, and undo restoration.

**Security and cache plugins are now reachable through four guarded integrations.** WP Rocket, UpdraftPlus, Wordfence, and WP Defender each register tools only when their host plugin is detected at runtime, degrading silently when absent like every other integration.

* New: `wpr_purge_all`, `wpr_purge_minify` (both `manage_options`), and `wpr_purge_url` (`edit_post` on the target), WP Rocket cache purges, each guarded on the relevant WP Rocket function existing.
* New: `up_start_backup`: trigger an UpdraftPlus backup behind two-part confirmation (`confirm` plus `confirm_components` matching the resolved set); the unconfirmed call returns a preview and starts nothing. No restore or delete is exposed.
* New: `wf_start_scan`: start a Wordfence scan of the admin-configured type behind the same two-part confirmation (`confirm` plus `confirm_scan`), guarded on a scan not already running.
* New: `def_get_*`: read-only WP Defender status and report tools.
* New: `tests/security-cache-integrations-test.php`: 108 assertions covering availability gating, capability checks, the two-part confirmation and preview-before-write paths, and running-state guards.

**SEO & analytics data sources arrive, using Google Service Account signing.** A new `seo_data` provider surface adds read tools for Semrush, DataForSEO, SE Ranking, Ahrefs, Google Search Console, and Google Analytics 4, each behind a per-provider credential card on the AI Providers panel with configured/active status. These are the plugin's first deliberate, opt-in outbound calls to third-party vendors; a provider contributes no tools until it is configured and enabled. Google authentication uses a Service Account key (JWT-bearer grant), not an interactive OAuth flow: a short-lived access token is minted from the signed assertion, cached in the provider slot, and re-minted on expiry.

* New: `wp_ahrefs_domain_rating_free`: free Ahrefs Domain Rating, no API key, off by default (opt-in).
* New: `semrush_*` and `dataforseo_*` and `seranking_*`: keyed research, keyword, competitor, and backlink tools.
* New: `gsc_*` (Search Console) and `ga4_*` (Analytics 4), authenticated through the shared `SEO_Data\Google_Service_Account` signer; GA4 admin calls target `analyticsadmin.googleapis.com` and reporting calls the data host.
* Safety: `SEO_Data\Http` runs the SSRF guard on every outbound URL, injects per-provider auth (header/bearer/query/basic), and normalizes errors (auth rejected / out of credits / rate limited). Every tool gates on `manage_options`, re-checked in the handler. The Service Account JSON is parsed to `client_email`/`private_key`/`token_uri` and never re-rendered into the settings form.
* New: `tests/seo-data-test.php`: 76 assertions covering gating, auth shapes, SSRF, error normalization, SA key parsing (OAuth-client and non-JSON keys rejected), JWT-bearer token minting, cached-token reuse, expired-token re-mint, rejected-assertion handling, and cross-provider `tool_names` dispatch.

= 0.3.1 =

**Elementor editing reaches loop templates and element reordering.** Two gaps blocked working on real Elementor sites: there was no way to move an existing element (only to insert a new one at a position), and Loop Grid / Loop Carousel widgets were opaque, their content lives in a separate loop-item template that nothing resolved.

* New: `elementor_move_widget`: move one existing element (widget or container) to a new location within the same page: before or after a reference element, or as its first/last child (child positions require a container/section/column target). The element keeps its settings and its own children; this only re-parents it. Refuses moving an element into its own subtree, takes an `expected_widget_type` guard, supports `dry_run`, and emits an undo token restoring the full pre-move tree. Atomic (V4) elements can be moved because relocating a whole node does not interpret its schema, the same reasoning that already allowed deleting them.
* New: `elementor_get_loop_template`: resolve a Loop Grid / Loop Carousel widget (or any widget carrying a `template_id`) to the loop-item document it renders, returning `loop_post_id`, template type, title, and the item's element outline. The loop item is an ordinary `elementor_library` document, so you then edit it with the existing tools (`elementor_get_widget_settings` / `update_widget` / `add_widget` / `delete_widget` / `move_widget`) by passing `loop_post_id` as their `post_id`. Source-verified against the ProElements submissions/loop query surface; live editor/render verification remains part of the post-deployment matrix.
* Fixed: `elementor_get_page_outline` now includes each node's element `id`. The addressed tools all need it and the description implied it was present, but the outline omitted it, forcing a second call to discover an ID. Loop widgets also surface their `loop_template_id` inline.
* `elementor_move_widget` reuses the existing `elementor_element_write` undo op and derived-cache invalidation, so a move clears Post CSS, the element cache, and page assets exactly as the other addressed writes do; `tests/elementor-cache-test.php` gains a per-path assertion for it.
* Scope note: the classic and Container models are fully editable through these tools. Editor V4 Atomic widgets/containers (`a-*` / `e-*`) remain edit-refused, their settings schema is not publicly documented, so a merge could silently corrupt them, but can be moved and deleted.
* Tests: `tests/elementor-widget-write-test.php` now covers outline IDs, move (reparent, sibling before/after, guards, atomic-move-allowed) and loop-template resolution, 137 assertions; `elementor-cache-test.php` at 47.

**Forms and lead capture is now readable, with guarded entry writes, through one normalized surface.** More MCP had no way to discover a form's field schema or read its submissions, that data usually lives in provider custom tables that core WordPress tools cannot model. Following the plugin integration roadmap's first P0 category, a `forms_*` capability contract now covers Gravity Forms and Fluent Forms behind one shape, so a client reads either the same way and unsupported operations are refused by name rather than emulated.

* New: `forms_list` and `forms_get`: list forms across active providers (id, provider, title, active, entry_count) and read one form's normalized field schema (`[{id, label, type, required}]`). Provider identity is carried in every request and echoed on every response row.
* New: `forms_list_entries`: paginated submissions using the shared `{total, page, per_page, pages, returned, has_more, entries[]}` envelope. Rows are summaries only (id, date, status, read state); field values are never returned by a list, because they carry personal data.
* New: `forms_get_entry`: an addressed full read that returns field values, with sensitive metadata (IP, user agent, browser/device, and payment/transaction fields) redacted by default.
* New: `forms_get_stats`: aggregate submission counts by status with no submission bodies.
* New: `forms_update_entry_status` and `forms_trash_entry`: guarded writes through each provider's own API. Status maps to the provider's model (Gravity Forms active/spam and read/unread; Fluent Forms read/unread and trashed); a status the provider does not support is refused by name. Trash moves an entry to trash rather than hard-deleting it (Gravity Forms sets `status=trash` via `update_entry_property`, not `delete_entry`), and takes a two-part confirmation: `confirm=true` plus `confirm_entry_id` echoing the target, with an unconfirmed call returning a preview and writing nothing. Both writes emit a 72-hour undo token; the undo handler re-checks the capability because a token may be redeemed by a different caller.
* Safety: every forms tool gates on `manage_options` inside the handler, and the capability check runs before the availability check so a lower-privilege caller cannot use the error to fingerprint which form plugins are installed, matching the Analytics integration.
* New: `tests/forms-test.php`: 61 assertions covering registration, cross-provider shape equality (the same normalized keys for Gravity Forms and Fluent Forms), summary-only list rows, addressed-read redaction, pagination, the two-part trash confirmation, status-write and trash undo round-trips, provider validation, the capability-before-availability gate, and the ability cap/schema/registry wiring.
* Note: the Gravity Forms path uses the public `GFAPI` class with signatures confirmed from the official documentation; the exact `gravityforms_*` capability slugs and the Fluent Forms read API/capability string are marked `needs-live-verification` in the source and are resolved against the installed plugin before the live compatibility matrix.

**Divi pages are now structurally readable and support guarded whole-node writes without rendering them.** More MCP previously reported `divi_version` in connection health but had no Divi tools. Generic post reads exposed Divi 4 as nested shortcode text and Divi 5 as block markup, leaving an agent unable to identify or safely change one section, row, column, or module.

* New: `divi_get_page_outline`: a compact positional outline for legacy Divi 4 `et_pb_*` shortcode layouts and native Divi 5 `divi/*` blocks. Format is detected per post from its content, not from the global version, because Divi 5 can retain legacy shortcode pages.
* New: `divi_get_module`: read one node by a dot-separated zero-based path returned by the outline. Divi 4 attributes remain literal strings; Divi 5 block attrs pass through verbatim because their internal schema is not a stable public contract.
* New: `divi_replace_module`, `divi_insert_module`, and `divi_delete_module`: replace, insert, or remove one whole addressed node. D4 uses exact byte-range splices with one balanced raw `et_pb_*` subtree, preserving every untouched byte; D5 reuses the Gutenberg tree mutation and serialize→reparse→serialize safety gate with one whole `divi/*` block.
* Safety: every Divi write requires object-level `edit_post`, a matching `expected_type`, and current positional paths. Dry runs do not snapshot, write, or invalidate. Committed writes snapshot exact `post_content`, use `wp_slash`, verify stored bytes, return a refreshed outline, and emit a 72-hour undo token. Undo restores exact content and invalidates again.
* Cache correctness: writes feature-detect and call Divi's own `ET_Core_PageResource::remove_static_resources( $post_id, 'core', false )` entry point rather than deleting files directly. That contract is source-verified against public Divi 5.8.1 code and older compatible signatures; live Divi 4/5 verification remains pending. A committed write reports invalidation failures as warnings because retrying a successful content write would be destructive.
* Mixed content remains explicit: Divi 5 shortcode-wrapper and freeform legacy regions are opaque nodes. An opaque wrapper can be replaced or deleted as one advertised boundary, but descendants beneath it cannot be addressed and child insertion is refused.
* Security: no tool calls `do_shortcode`, render callbacks, migration code, or Visual Builder execution. Theme Builder, Theme Options, presets, library layouts, rendering previews, settings management, and D4→D5 migration remain separate milestones.
* New: `tests/divi-parser-test.php`: 76 assertions covering quote-aware D4 tokenization, splice boundaries, raw-subtree validation, nested paths, malformed recovery, D5 verbatim attrs, mixed opacity, access gates, registration, schemas, and undo wiring.
* New: `tests/divi-write-test.php`: 34 assertions covering D4 byte preservation, D5 whole-block fixed points, guards, dry-run, write verification, opacity refusals, cache invalidation, snapshot shape, and undo restoration.
* New: `tests/divi-compatibility-test.php`: optional extracted-source checks for the Divi cache invalidation entry point, observation action, and D4/D5 dynamic-cache keys. Without `DIVI_SRC`, it prints an explicit skip; that skip is not source or live verification.

**Elementor gained addressed single-element writes.** There was no way to change one widget's settings or remove one element: the only write paths were page-wide (`elementor_replace_text`, `elementor_replace_image`), whole-page (`elementor_clone_page`), or additive (`elementor_add_widget`). Fixing a typo in one heading meant a pattern-match across every widget on the page.

* New: `elementor_update_widget`: change the settings of one element addressed by its element ID. Settings are **merged** by default, not replaced. An Elementor settings object holds content next to presentation, so `title` sits beside `title_color`, `typography_font_size`, and `_margin`; a caller fixing a heading typo who sent only `title` under replace semantics would silently discard every styling key and the page would render unstyled with no error. `replace_settings: true` performs the wholesale swap when that is genuinely what is wanted, and the response names each key it discarded. Passing a key as `null` removes just that key.
* New: `elementor_delete_widget`: remove one element and everything nested inside it. Deleting a container takes its subtree, so the response reports the descendant count, and a dry run reports it before anything is removed.
* New: both take an `expected_widget_type` guard that aborts unless the target is the type the caller expected. Element IDs are per-document and shift when a page is rebuilt, so an ID held from an earlier read can address something else entirely, for a delete that means removing the wrong part of the page. The guard compares `widgetType` for widgets and `elType` for containers, so `container` is a usable expected value.
* New: both support `dry_run`, and both emit an undo token. The snapshot captures the whole element tree rather than the single element, because a partial snapshot could not restore correctly if the tree changed shape between the write and the undo.
* Fixed: `elementor_update_widget` now refuses Editor V4 Atomic containers as well as Atomic widgets. Core 4.2 stores container types such as `e-div-block`, `e-flexbox`, and `e-grid` directly in `elType` with no `widgetType`; checking only `widgetType` produced an empty type and allowed an opaque settings object into the merge path. Detection now uses `widgetType` when present and otherwise `elType`, before the undo snapshot, content write, or cache invalidation. **Deleting** either Atomic form remains allowed because whole-node removal does not interpret its schema.
* New: `tests/elementor-widget-write-test.php`: 96 assertions. Covers merge-versus-replace, single-key removal, the guard on both tools, Atomic widget and Atomic-container refusal for edits, no undo or cache side effects on those refusals, Atomic deletion being permitted, dry runs writing nothing, cache invalidation on both writes, undo round-trips asserted byte-for-byte, and the serialization shapes Elementor requires, settings encoding as `{}` rather than `[]` when emptied, and a sibling list staying a JSON array after a middle element is removed.
* Enhancement: `more_mcp_undo_last_operation` now documents its actual coverage. Its description claimed "NARROW SCOPE: reverses ONE tool only, wp_reorder_menu_items" and stated that `elementor_*` tools write without undo, which had already stopped being true for the block and template tools. It now lists what is covered and what is not, and says to check for an `undo` key in a response rather than assuming one.

* New: `tests/elementor-compatibility-test.php`: 48 focused optional source contracts for the Core 4.2.2 compatibility target. With `ELEMENTOR_SRC` set to an extracted Elementor directory, it verifies the current Atomic element types and registration, widget-versus-Atomic serialization identifiers, all three derived-cache identifiers and Post CSS deletion API, template-library taxonomy/meta names, and the widget slugs/control keys emitted by More MCP's curated insertion path. Without source, it prints an explicit skip with the exact contract count; a skip is not reported as source verification.

**Elementor writes left the page rendering its previous version.** Every Elementor tool that changes a page saved `_elementor_data` with a direct meta write, which bypasses Elementor's own save routine and therefore bypassed three pieces of derived state Elementor clears or rebuilds there. The write landed in the database, read back correctly, and the page kept rendering the old version until someone opened the Elementor editor and pressed Update, while the tool reported success. Affected `elementor_clone_page`, `elementor_replace_text`, `elementor_replace_image`, `elementor_import_template`, and `elementor_add_widget`.

Three caches, and they failed differently. **Post CSS** holds the per-page stylesheet, so a changed page rendered with stale styling and a newly added widget rendered with no styling at all, having no rules in the old stylesheet. The **element cache** holds rendered element HTML together with the script and style handles that render enqueued; on a cache hit Elementor skips printing the elements entirely, so a stale entry served the old *markup*, not merely old styling. That second cache is gated on Elementor's `e_element_cache` experiment, so it affected fewer sites; the Post CSS staleness affected all of them. **Page assets** is the script/style handle list derived from the element tree. Elementor treats any saved array as already evaluated and its frontend returns early on a non-empty value, so stale conditional assets, such as the text-editor drop-cap stylesheet, stay authoritative until the row is removed.

* Fixed: all five write paths now clear all three. Post CSS is cleared through Elementor's own `Post_CSS::delete()` rather than by deleting the `_elementor_css` meta key directly, because that method branches on whether the site stores CSS inline or as files, a meta-only delete would leave a stale `.css` file on disk and still being served. The element cache and page assets have no file counterpart, so plain meta deletion is correct; Elementor rebuilds them lazily on render.
* Fixed: `elementor_clone_page` no longer carries any derived state from the source, Post CSS, element cache, or page assets, to the new post. A clone preserves the widget types, so the page-assets list would often happen to match, but it can contain conditional assets and is still derived from the tree. One rule is safer than a special case: copy source data, rebuild all derived state lazily.
* By design: a failed invalidation does not fail the write. The content write has already committed by that point, so reporting it as an error would be wrong and would invite a retry. Instead the response carries a `cache_invalidation` block naming what could not be cleared and saying that opening the Elementor editor once will resolve it. A clean run adds nothing to the response.
* By design: this does not route writes through Elementor's `Document::save()`. That method also runs version migrations, rewrites settings, and fires `elementor/document/after_save`; adopting it would change behaviour well beyond cache invalidation. Nor does it regenerate the CSS eagerly, Elementor rebuilds it on the next render, so deleting is enough.
* New: `tests/elementor-cache-test.php`: 49 assertions on this branch (48 in the cache-only PR, plus the commit-site assertion for the two addressed-write helpers). Asserts *which* cache was cleared and for *which* post rather than that a write returned without throwing, since the latter passed against the bug. One assertion per write path, so a new write tool added without invalidation fails for that tool alone. Covers the degradation path with a stub that raises an `Error` rather than an `Exception`, matching the real failure mode. When an Elementor source tree is supplied, six upstream assertions verify all three keys, the Post CSS save call, the page-assets early return, and Elementor's own bulk invalidation list; without that source the suite prints a loud skip naming exactly what was not verified.

= 0.2.0 =

**A tool that raised a PHP Error returned nothing readable.** `handle_tool_call()` caught `\Exception` but not `\Throwable`, and a PHP `Error`: a missing class, a type error, a call to an undefined method, is not an `Exception`. Those escaped the handler as a fatal, so instead of a JSON-RPC error result the client got a truncated body or a bare 500 with no envelope, and because the catch block is also what writes the log row, nothing reached the activity log either: the failure was invisible to the admin afterwards. Now caught as `\Throwable`. `log_tool_call()` already typed its exception parameter against `\Throwable` for the `error_class` field, so the logging side had always expected this. The Abilities layer already did the same in `Registrar::build_execute_callback()`; the MCP endpoint was the outlier.

**Requirements are now stated in the readme.** PHP 7.4 minimum (8.0+ recommended), WordPress 5.8 minimum, plus the prerequisites that were previously only discoverable by hitting them: HTTPS and pretty permalinks for any OAuth connector flow, and WordPress 6.9+ for the optional Abilities API surface.

**SEO meta reaches four more plugins, and terms.** Detection went from two SEO plugins to six, and taxonomy archives became writable through a tool that targets the location each plugin actually reads.

* New: `wp_get_term_seo_meta` and `wp_update_term_seo_meta`: SEO fields on categories, tags, and any custom taxonomy term. Supported at term level for Yoast SEO, Rank Math, All in One SEO, and SEOPress.
* Fixed: there was no working path to Yoast taxonomy noindex (GitHub #6). Yoast keeps per-term SEO in the `wpseo_taxonomy_meta` **option**, keyed by taxonomy and term ID, not in `wp_termmeta`: so a `wp_update_term_meta` call writing `_yoast_wpseo_noindex` returned success, round-tripped correctly on read, and left the archive indexable. `wp_update_term_seo_meta` writes the option instead, merging read-modify-write so other terms' settings survive. That merge is not an optimisation: one option row holds every term in every taxonomy on the site.
* New: `wp_get_seo_meta` and `wp_update_seo_meta` now detect All in One SEO, SEOPress, Slim SEO, and The SEO Framework alongside Yoast SEO and Rank Math. Previously those four sites got `plugin: none`: reads returned nothing and writes were refused outright.
* New: `canonical`, `twitter_title`, `twitter_description`, and `schema_page_type` are now first-class fields on `wp_update_seo_meta`. `schema_page_type` (Yoast only) drives the `@graph` page type and was previously reachable only by knowing the raw meta key: accepted values that render are WebPage, ContactPage, AboutPage, CollectionPage, FAQPage, ItemPage, ProfilePage, SearchResultsPage, CheckoutPage, RealEstateListing, and QAPage.
* Security-adjacent correctness: a field the detected plugin does not store is now **refused by name** rather than written to a plausible-looking key. Every plugin stores these differently and several would accept a wrong key silently, All in One SEO 4.x moved off post meta into its own `aioseo_posts` / `aioseo_terms` tables, so a write to `_aioseo_title` saves cleanly and renders nothing. Refusal names the plugin, the field, and which plugins do support it.
* New: every noindex encoding is handled rather than assumed. Yoast uses a `'1'`/`'0'` string on posts and a tri-state `noindex`/`index`/`default` on terms; Rank Math uses a robots array; SEOPress inverts the flag (`'yes'` means noindex); The SEO Framework uses `-1`/`0`/`1`; All in One SEO uses a boolean column and needs `robots_default` cleared or it ignores the explicit flag. Rank Math writes preserve every other robots directive, flipping noindex no longer drops a `nofollow` or `noarchive` set alongside it.
* New: reads expose `supported_fields` and a `raw` block. `raw` exists because the normalised boolean is lossy in one specific way: Yoast term noindex and The SEO Framework both distinguish "inherit the site-wide default" from an explicit index, and both collapse to `false`. Someone auditing why an archive is indexable needs to see which one it is.
* New: reads and writes report every SEO plugin found, and flag when more than one is active. Two plugins each emitting a title tag is a live defect that explains why a write through one of them is not what a crawler sees; resolving it silently by declaration order would hide that.
* New: every term SEO write is read back out of storage and the returned values are what was actually stored, extending the read-after-write verification `wp_update_post` already had.
* New: `wp_get_option` can now read `wpseo_taxonomy_meta`. It holds no credentials, and read access is a prerequisite for writing it safely, the option carries every term on the site, so anyone editing it by hand needs to see the current value first. Writes to it remain gated exactly as before; `wp_update_term_seo_meta` is the supported path and does the merge for you.
* Fixed: `seo_audit_meta_tags` had no output schema and no capability entry in the abilities map. Absent from the map, `seo_` matched no prefix rule and fell through to the `manage_options` default, stricter than the handler's own `read` gate, so the Abilities API and the WordPress REST abilities route refused callers the MCP endpoint accepts. Both added; the schema is exact rather than loose because this tool parses the rendered head and its response shape does not vary by plugin.
* Fixed: `Tool_Profiles.php` advertised a `?tools=seopress` profile whose `seopress_` prefix matched no tool in the codebase (GitHub #25). Removed, and the `core` profile now includes the `seo_` prefix so `seo_audit_meta_tags` is not dropped from a profile named "core". The docblock also now states plainly that profiles are a client-compatibility affordance and not an authorization boundary, `execute_tool()` does not consult them.
* Enhancement: `wp_get_term_meta` and `wp_update_term_meta` descriptions now say which SEO plugins do and do not read term meta, so the silent-success case is discoverable from the tool list rather than by experiment.
* Fixed: `canonical` is sanitized with `esc_url_raw()` rather than `sanitize_text_field()`. Canonical is a URL, and `sanitize_text_field` is the wrong tool for one, it happens to leave an ordinary URL intact, so this was latent rather than broken, but it is the wrong sanitizer for the field across all six plugins.
* Fixed: the All in One SEO table probe escapes its LIKE pattern. `SHOW TABLES LIKE` treats `_` as a single-character wildcard and the table name is full of them (`wp_aioseo_posts`), so on a site holding a table that matches the unescaped pattern the probe could resolve to that table instead, report AIOSEO's table as missing, and tell the admin to open a post in the editor, advice that would not have helped, for a table that already existed. Not an injection path: the table name is a catalogue literal. The same escaping was applied to the core table probe in `required_tables_exist()`, which had the same shape.
* New: `tests/seo-meta-test.php`: 211 assertions. Covers detection across all six plugins including the multi-plugin conflict case, per-plugin post and term round-trips asserting *where* each value landed, every noindex encoding, the Yoast option merge (sibling terms and other taxonomies preserved, string-keyed term IDs updated rather than duplicated), AIOSEO table insert-then-update, the LIKE-wildcard case against a decoy table that a missing `esc_like` would resolve to, canonical URL sanitization, refusal for unsupported field/plugin/level combinations, and that a rejected field aborts the whole write rather than leaving a partial one. The detection catalogue is read out of `Detector.php` as text and asserted, so adding a seventh plugin or renaming a slug fails the test loudly instead of leaving it passing against a stale copy.

**Settings screen redesign.** The sidebar drops from six panels to five, and the panels themselves were rebuilt around what an admin is actually trying to find out.

* New: Sessions panel now lists what is connected, split into two sub-tabs. **Connected clients** is one row per OAuth client (with the WordPress user it acts as, when it last refreshed its token, and when it expires); **Transport sessions** is one row per open MCP session. Each row has its own disconnect button. Previously the only available action was "revoke all", which is a poor answer to "one of these connectors looks wrong".
* New: Both session lists paginate at 20 rows per page, per tab. A busy site accumulates far more transport sessions than clients, and stacking both lists on one screen pushed the client table off the top of the page.
* New: "End all sessions" clears transport state without revoking any credentials, so clients reconnect on their own without re-authorizing. This is the right first move for clients stuck in a "Session not found" loop; revoking OAuth grants for that symptom was always an unnecessarily large hammer.
* New: Grants whose WordPress user has been deleted, and tokens outliving the client registration they belong to, are now flagged in the list rather than rendering as blank cells. Both states are reachable in normal operation and both are things an admin would want to clear.
* Enhancement: Setup Guides and API Reference are merged into one Documentation panel with four sub-tabs, Client setup, What agents can do, REST API reference, and Troubleshooting. The old `panel=guides` and `panel=endpoints` URLs still work; `panel=endpoints` lands on the REST tab rather than the default one, so an old bookmark reaches the content it was saved for.
* New: Documentation → What agents can do lists the live tool inventory grouped by area, read from the actual registry rather than a hand-maintained list. It reflects which optional integrations are active on the site in front of you, and states plainly that being listed is not the same as being permitted.
* New: Documentation → Troubleshooting collects the failure modes that previously lived only in conditional admin notices and support replies, Cloudflare AI-bot blocking, plain permalinks, hosts intercepting `.well-known`, stuck handshakes, empty tool lists, and rate limiting, each with what to check and what to do.
* Enhancement: REST API reference now documents all three HTTP surfaces in order of relevance (MCP endpoint, OAuth endpoints, legacy REST routes) instead of listing only the legacy routes under a title that implied they were the current ones.
* Enhancement: Advanced on the Connection panel is a titled, described section rather than a bare toggle whose label was a parenthesised list of its contents. It says what is inside and that nothing there is required. It also auto-expands when manual OAuth credentials are set, so a static client ID is not invisible to whoever inherits the site.
* Enhancement: Permissions is reorganized around risk. The master switch stands alone, an always-enforced section states what holds regardless of every toggle (capability checks, the permanent sensitive-option denylist, read-after-write verification, logging), and the three write scopes are presented in ascending order of how hard the damage is to undo.
* New: The writable-options allowlist is now a set of preset checkboxes instead of a bare textarea. It asked admins for something the screen gave them no way to know, the exact `wp_options` row name a plugin stores its settings under, which is not the label shown on a settings page and fails silently when guessed. Groups cover site identity, reading and formatting, comments, media sizes, search visibility, and (when the plugin is active) WooCommerce, Yoast, and Rank Math. The textarea remains, collapsed, for anything not covered.
* New: Options that hold many settings in one row are marked "bundled" and carry a warning, because `wp_update_option` replaces the whole option, an agent writing one key discards every other setting stored alongside it. Search engine visibility is deliberately its own group so it cannot be enabled as a side effect of wanting the harmless reading settings.
* Security: preset checkboxes are validated against the catalogue on save rather than trusted as posted, so the checkbox array cannot become a second, unvalidated path into the allowlist. The stored shape is unchanged, still one flat list of option names, so no migration is needed and the permanent denylist still runs first.
* Enhancement: AI Providers opens with an explicit two-column note on direction of travel. This panel is for WordPress calling out to an AI service; connecting Claude or ChatGPT to this site needs nothing here. Its empty state now says that empty is the correct configuration for most sites rather than presenting itself as unfinished setup.
* Enhancement: A provider with a required field left empty is flagged as incomplete, instead of failing later at a point far away from this screen.
* Enhancement: The sidebar carries a live count of connected clients, visible from every panel.
* New: `tests/sessions-panel-test.php`: 109 assertions covering the session and grant tables with rows present (including deleted users, orphaned grants, and already-lapsed expiries), pagination boundaries, the Documentation sub-tabs, the legacy panel aliases, the Advanced section's auto-expand, and the option-preset checkboxes. The existing round-trip test only ever rendered these panels against empty stores, so every row-rendering branch was uncovered.
* Fixed: the settings round-trip test now renders both Sessions sub-tabs. Session length is owned by the Clients sub-tab, so a save from Transport has to preserve it as a hidden input, testing only the default sub-tab would have missed a silent reset of session length to 24 hours.

= 0.1.6 =

Version bump only. 0.1.5 was never released, so everything listed under it below ships here.

Verified against a live WordPress 7.0.3 / PHP 8.1.34 site before tagging: MCP handshake, 107 tools listed, and the lifecycle opt-in confirmed absent from `tools/list` while the toggle is off (a call to `wp_activate_plugin` returns "Unknown tool" and performs no action). The lifecycle success paths, actually installing, updating, or deleting something, remain covered only by `tests/lifecycle-test.php` against WordPress doubles, not on a live site.

= 0.1.5 (unreleased, merged into 0.1.6) =
* New: Plugin and theme lifecycle tools, install, update, activate, deactivate, and delete plugins and themes over MCP. Disabled by default: enable "Allow AI to manage plugins and themes" under Settings → Permissions. While off, the tools are not listed to MCP clients at all.
* Security: every lifecycle write requires the matching WordPress capability, plus a two-part confirmation (confirm=true AND confirm_slug echoing the exact target). A call without confirmation returns a preview instead of writing, so the first call is always a dry run.
* Security: wp_install_plugin accepts WordPress.org slugs only, package URLs are rejected rather than downloaded. More MCP cannot deactivate or delete itself. Sites needing FTP/SSH filesystem credentials are refused rather than prompted, since credentials must never travel through MCP arguments.
* Fixed: wp_update_post_meta and wp_add_post_meta stripped backslash escapes, corrupting any meta value containing JSON, most visibly _elementor_data, where an affected page could render zero sections while still returning HTTP 200.
* Fixed: the meta sanitizer stripped <script> tags while keeping their inner text, so a JSON-LD block written to meta rendered as visible body copy. Callers with the unfiltered_html capability now store string meta verbatim, matching how post_content already behaved.
* New: all three meta write tools return saved_value_matches_sent plus a warnings array when the stored value differs from what was sent, extending the read-after-write verification wp_update_post already had.
* Fixed: wp_get_media ignored its page argument, making a library larger than per_page unreachable. Now returns a paginated envelope with total and has_more, and exposes alt under both alt and alt_text.
* Fixed: wp_get_post_revisions ignored its limit argument and returned every revision, producing responses large enough for MCP clients to reject. Now honours limit, adds offset, and each row carries content_length, differs_from_parent, and elementor_data_length so revisions can be told apart.
* New: API keys use a readable prefixed format, `mmcp_live_` followed by 22 base58 characters. The alphabet omits 0, O, I, and l, the characters people transcribe wrongly when copying a key by hand.
* New: The auth header is now `MMCP-Key` (was a longer plugin-prefixed name). Note it carries no `X-` prefix, RFC 6648 deprecated that convention for new headers in 2012.
* Breaking: API keys in the previous 32-character hex format are no longer accepted. Open More MCP → Settings and click Regenerate, then update any client that sends the API key. Clients using OAuth are unaffected.
* Enhancement: The settings screen is now a sidebar with one panel visible at a time (Connection, Permissions, Setup Guides, AI Providers, Sessions, API Reference) instead of a single long scroll. The active panel lives in the URL, so it survives a reload and can be bookmarked.
* New: Gutenberg block subsystem, 19 tools for block-level content editing.
* New: Read a post as a structured block tree with index-path addressing, then insert, update, move, or delete individual blocks without resending the whole document.
* New: Block type introspection, list registered block types and read any block's attribute schema before constructing markup.
* New: Server-side block validation reporting a confidence level (structural, registered, schema-checked) rather than a pass/fail verdict, because PHP cannot execute a block's JavaScript save() function.
* New: Full Site Editing support, list, read, customize, and revert wp_template and wp_template_part records. Reverting deletes the database customization so the theme file renders again.
* New: Block pattern listing and reusable block (wp_block) CRUD.
* New: Every block and template mutation supports dry_run, an expected-block guard, read-after-write verification, and emits a 72-hour undo token.
* New: Block parser test harness (tests/parser-test.php), 69 assertions, runs without a WordPress bootstrap.
* New: Reproducible build script producing an upload-ready plugin zip with version-parity, syntax, and brand checks.
