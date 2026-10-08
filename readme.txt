=== Mordenhost MCP Server with OAuth 2.0 for Claude, ChatGPT and Gemini ===
Contributors: sadewadee
Tags: mcp, ai, claude, chatgpt, gutenberg
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 0.18.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MCP server for WordPress with OAuth 2.0, API-key auth, rate limiting, and audit logs. Works with Claude, ChatGPT, Gemini, and MCP clients.

== Description ==

Mordenhost MCP Server (called More MCP in the WordPress admin menu) is a security-first Model Context Protocol (MCP) server for WordPress, giving AI platforms like Claude, ChatGPT, and Gemini typed, permission-checked access to your content with the auth, rate limiting, and audit logging most bridges leave out.

MCP lets AI agents read, create, update, and delete your content, and many public MCP servers accept calls with no authentication at all. More MCP takes the opposite stance: every session authenticates with OAuth 2.0 or an API key (timing-safe), every request is rate-limited per IP, every call passes a capability check, and everything is logged.

Requires PHP 7.4+, WP 5.8+, MySQL/MariaDB, HTTPS. On WP 6.9+ tools also register through the core Abilities API, and abilities from other plugins import as `discovered_*` tools.

More MCP is fully featured in its free, GPL-licensed release; there is no Pro version. It has no telemetry, no license checks, and never phones home. It contacts a third-party service only when an administrator turns on a feature that needs one (stock photos, SEO and analytics data, AI image generation), and each is listed under External services below.

More MCP always registers **111 tools** no matter which plugins are installed: 87 WordPress-core tools (content, media, comments, users, taxonomies, menus, meta, options, SEO meta routed to where each detected SEO plugin stores it), 21 Gutenberg block tools (round-trip-safe trees, reusable blocks, patterns, FSE templates, global styles), and 3 webhooks.

Opt-in integrations add tools when enabled, each off by default behind two gates: host plugin active AND the integration enabled under Settings > Access. There is no MCP route to enable one, so an agent cannot widen its own surface. WooCommerce (29 tools), Elementor (21, up to 31 with Pro), Divi, ACF, Meta Box, Redirection, analytics, forms, cache, email, security, backup, CRM, LMS, events, multilingual, and 60+ more; per-integration lists live in the Documentation panel. The 10 lifecycle tools (install, activate, update, delete plugins and themes) are gated most tightly: two-part confirmation where the first call is a dry run, wp.org slugs only, and More MCP can't delete itself.

Claude is a trademark of Anthropic, ChatGPT of OpenAI, and Gemini of Google. This plugin is an independent project by Mordenhost and is not affiliated with, endorsed by, or sponsored by any of them; their names are used only to say which clients it works with.

Compatible with Claude Desktop/Code, VS Code, ChatGPT, Gemini, Cursor, Windsurf, Cline, JetBrains, Postman, Insomnia (`MMCP-Key` header), and LangChain or AutoGen. Implements the [MCP 2025-11-25 Streamable HTTP spec](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports#streamable-http): single `/mcp` endpoint, secure session IDs, Origin validation, CORS support.

== Installation ==

Requires PHP 7.4 or later (8.0+ recommended) and WordPress 5.8 or later. See Requirements in the description above for the full list, including the HTTPS and pretty-permalink prerequisites for OAuth connectors.

1. Upload the `more-mcp` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to More MCP → Connection in the admin menu
4. Copy the MCP server URL. For clients that use OAuth (Claude, ChatGPT) the URL is all you need; for other clients also copy your API key
5. In your AI client (Claude Desktop, VS Code, etc.), add the MCP server URL and, if asked, the API key

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

Yes. More MCP does not care which model sits behind the client: any MCP-capable client, including one driving a local model through Ollama or LM Studio, can connect to the MCP endpoint. With a local model and a local client, no content has to leave your own hardware.

= What are the experimental Skills and Memory features? =

Both are off unless you add `define( 'MORE_MCP_EXPERIMENTAL_MODE', true );` to wp-config.php. Skills are instructions you write once under More MCP > Skills (beta); every connected AI client sees the published ones in its prompt list. Memory lets connected agents save short project facts (conventions, decisions, pitfalls) and recall them in later sessions; you can review and delete everything under More MCP > Memory (beta). Both are shared by every administrator's AI on the site, stay in your WordPress database, and are only available to administrators. Nothing in them is ever run as code.

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

== External services ==

More MCP works without any third-party service. The services below are optional: each one is contacted only after a site administrator has turned on the feature that needs it, and only when a connected AI assistant calls the matching tool. Nothing is sent to the plugin author, and there is no telemetry, license check, or update ping. The AI assistants you connect (Claude, ChatGPT, Gemini and others) receive whatever the tools you allow return, under the terms of whichever assistant you use.

= WordPress AI Client and the AI provider you connect =

Used by the optional "AI media generation" feature (off by default, Settings > Access) to generate images and image alt text. It needs WordPress 7.0 or later. More MCP asks for no AI provider key and never contacts a provider itself: it hands the request to the AI Client built into WordPress, which sends it to the provider you connected under Settings > Connectors.

Data sent, and when: the text prompt of `ai_generate_image`, or for `ai_generate_alt_text` the image file you pointed it at plus a fixed instruction, each time an assistant runs one of those two tools. Terms and privacy depend on the provider you connected, for example OpenAI ([terms](https://openai.com/policies/terms-of-use), [privacy](https://openai.com/policies/privacy-policy)), Google Gemini ([terms](https://ai.google.dev/gemini-api/terms), [privacy](https://policies.google.com/privacy)), or Anthropic ([terms](https://www.anthropic.com/legal/consumer-terms), [privacy](https://www.anthropic.com/legal/privacy)).

= Unsplash and Pexels (stock photo search) =

Used by the `stock_search_images` tool, only when the Instant Images plugin is active and holds an API key for the provider. The key is read from Instant Images; More MCP stores none.

Data sent, and when: the search words, result count and orientation, plus that API key, to api.unsplash.com or api.pexels.com each time an assistant runs the tool. Unsplash: [terms](https://unsplash.com/terms), [API guidelines](https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines), [privacy](https://unsplash.com/privacy). Pexels: [terms](https://www.pexels.com/terms-of-service/), [privacy](https://www.pexels.com/privacy-policy/).

= Semrush, SE Ranking, Ahrefs and DataForSEO (SEO data) =

Used by the `semrush_*`, `seranking_*`, `ahrefs_domain_rating` and `dataforseo_*` tools so an assistant can read keyword, ranking and backlink data. Each provider is off until an administrator enters that provider's own API credentials under More MCP > External Services.

Data sent, and when: the domain, URL or keyword the assistant asks about, plus the administrator's API credentials, to api.semrush.com, api.seranking.com, api.ahrefs.com or api.dataforseo.com each time an assistant runs one of that provider's tools. Semrush: [terms](https://www.semrush.com/company/legal/terms-of-service/), [privacy](https://www.semrush.com/company/legal/privacy-policy/). SE Ranking: [terms](https://seranking.com/terms-of-services.html), [API terms](https://seranking.com/legal/api-terms-of-service.html), [privacy](https://seranking.com/legal/privacy-policy.html). Ahrefs: [terms](https://ahrefs.com/legal/terms), [privacy](https://ahrefs.com/privacy-policy). DataForSEO: [terms](https://dataforseo.com/terms-of-service), [privacy](https://dataforseo.com/privacy-policy).

= Google Search Console and Google Analytics 4 =

Used by the `gsc_*` and `ga4_*` tools so an assistant can read search performance and traffic reports. An administrator pastes a Google service account key under More MCP > External Services; the key stays in your database.

Data sent, and when: each time one of those tools runs, a signed token request goes to oauth2.googleapis.com, then the report request (property or site URL, date range, dimensions) goes to searchconsole.googleapis.com, analyticsdata.googleapis.com or analyticsadmin.googleapis.com. Google: [terms](https://policies.google.com/terms), [API terms](https://developers.google.com/terms), [privacy](https://policies.google.com/privacy).

= WordPress.org (plugin and theme installation) =

Used by the plugin and theme lifecycle tools (install, update, activate, delete), which are off by default and sit behind the "Allow plugin management" switch plus a two-part confirmation. Installation is limited to wordpress.org slugs and goes through WordPress core's own updater and plugin API.

Data sent, and when: the requested slug, together with what WordPress core itself sends when it checks for updates (WordPress and PHP version, locale, site URL), to api.wordpress.org and downloads.wordpress.org each time an assistant installs or updates something. WordPress.org: [privacy policy](https://wordpress.org/about/privacy/), [plugin directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

= Addresses you configure yourself =

Outbound webhooks post event details to the URLs an administrator adds under the webhook tools, and `wp_upload_media_from_url` downloads a file from a URL an assistant supplies. Neither contacts a fixed service. Both refuse private, loopback and reserved addresses, and the receiving server's own terms apply.

== Screenshots ==

1. Connection panel, the MCP Server URL every client uses, with the API key and a collapsible Advanced section
2. Permissions panel, the master switch, what is always enforced, and the three write scopes in ascending order of risk
3. Sessions panel, connected OAuth clients with the WordPress user each acts as, and a per-row disconnect
4. Sessions panel, Transport tab, open MCP sessions with per-row end, paginated
5. Documentation panel, client setup walkthroughs, the live tool inventory, REST reference, and troubleshooting
6. Activity Log, every tool call and OAuth event with its outcome
7. OAuth consent screen shown when a client authorizes

== Changelog ==

= 0.18.0 =
Deployment hardening for hosting teams.

* Optional HMAC for stored credentials: define `MORE_MCP_HMAC_KEY` (16+ characters) and API tokens, OAuth codes, access and refresh tokens and client secrets are hashed with HMAC-SHA-256 instead of plain SHA-256. Rotate with `MORE_MCP_HMAC_KEY_PREVIOUS`; existing credentials keep working and named tokens move to the new key on next use.
* OAuth device login (RFC 8628) for headless clients: `POST /mcp-device/code` returns a short code, the person approves it at `/mcp-device` while signed in to WordPress, and the client polls the token endpoint for tokens. Codes are stored hashed, expire after 15 minutes, can be redeemed once, and polling too fast gets `slow_down`.
* MCP 2026-07-28 stateless requests: a request that declares the revision in `_meta` is answered without a session, with `resultType`, `ttlMs` and `cacheScope` on results, `server/discover`, the required `MCP-Protocol-Version` / `Mcp-Method` / `Mcp-Name` headers checked (-32020 on mismatch, -32022 with the supported versions on an unknown revision) and unknown methods answered 404. The 2025-11-25 session flow is unchanged.
* Refresh tokens are claimed with a single atomic update, so two simultaneous refreshes can no longer both succeed.
* Settings can be fixed outside the dashboard with a `MORE_MCP_<NAME>` constant in wp-config.php or an environment variable (read-only mode, default OAuth access, IP allowlist, trusted proxies, safety switches and tool lists, token lifetimes, log retention, rate limit, and more). Fixed fields show as locked in the admin with the constant that sets them, and can't be changed from wp-admin or over MCP.
* New `MORE_MCP_ACCESS_TOKEN_TTL` (any number of seconds), `MORE_MCP_REFRESH_TOKEN_TTL` and `MORE_MCP_RATE_LIMIT` (requests per minute) constants.
* `MORE_MCP_OAUTH_ENABLED` (or the `more_mcp_oauth_enabled` filter) switches the OAuth server off: discovery, /authorize, /token and /register answer 404 and issued OAuth tokens stop working.
* White-label branding for agencies and hosts, set in wp-config.php so the client cannot undo it: `MORE_MCP_WHITE_LABEL_NAME` renames the plugin in the menu, the settings screens, the consent and device pages and the Plugins list; `MORE_MCP_WHITE_LABEL_AUTHOR` and `MORE_MCP_WHITE_LABEL_DESCRIPTION` change the Plugins list entry; `MORE_MCP_HIDE_MENU` and `MORE_MCP_HIDE_FROM_PLUGINS` hide the menu and the Plugins row. Tool names and endpoints do not change.
* Experimental Skills (off by default): instructions you write once under More MCP > Skills (beta) are served to connected clients as MCP prompts (names start with `skill_`). Administrator-only, and nothing in a skill is run as code.
* Experimental shared Memory (off by default): the `memory_list`, `memory_read`, `memory_save` and `memory_delete` tools let connected agents save and recall short project facts, and More MCP > Memory (beta) lets an administrator review and delete them. Deleting needs `confirm` plus a matching `confirm_key`, and read-only connections can't save or delete.
* Recall for Skills and Memory: a site skills index and a shared memory index are offered as MCP resources, and the `initialize` result and `server/discover` carry short instructions telling the client they exist.
* Skills and Memory stay off unless wp-config.php defines `MORE_MCP_EXPERIMENTAL_MODE`, and they are only available to administrators.
* `wp_create_post` and `wp_create_page` accept an optional `slug`. WordPress sanitizes it, and the response returns the saved slug with a note when WordPress had to change it (for example `-2` on a collision).
* `wp_create_page` with no `status` now creates a draft cleanly instead of raising an undefined-key notice.
* Review fix: Passing `dry_run` to a tool that does not support it no longer skips approval or the change-history snapshot.
* Review fix: The REST bridge matches routes case-insensitively and with repeated slashes collapsed, as WordPress does, and refuses writes to code-snippet plugins (Code Snippets, WPCode, Elementor Custom Code).
* Review fix: The code-meta guard compares `_elementor_code` case-insensitively.
* Review fix: The legacy `/more-mcp/v1` REST routes now honour Pause, the destructive-tools switch, approval (deletes) and force-draft.
* Review fix: The activity log and approvals screen keep only the size of option and meta values, and drop email addresses and phone numbers.
* Review fix: Named API tokens created as Full stay Full when the default for new OAuth connections changes.
* Review fix: Confidential OAuth clients must send their secret on refresh as well, and a refresh attempt without it no longer burns the token.
* Review fix: Privileged tools are hidden by name before the per-tool walk in tools/list, which makes the default tools/list cheaper.

= 0.17.0 =
Easy MCP parity: the tools, safety controls and plugin integrations a site owner would otherwise need a second MCP plugin for.

**Access and safety**

* Read-only mode, per-connection access (full, read-only or custom per permission group) and an IP allowlist for the MCP endpoint. They only ever remove access, and are enforced once in the tool-call path and mirrored in tools/list.
* Safety switches: pause all tool calls, force new content to draft, disable destructive tools, a glob allowlist of permitted tools, and single-use 15-minute approvals (admin or chat mode). Each can also be pinned from wp-config.php.
* Named API tokens with an access level, expiry, rate limit and site binding; own-token self-service on the user profile when enabled.
* Change history with before/after snapshots around every write, wp_history_list / get / diff / apply with a conflict check and verified restore, and an audit log (wp_audit_list) that redacts arguments.
* Stored third-party API keys and service-account JSON are encrypted at rest (AES-256-GCM) and migrated on upgrade; OAuth accepts RFC 8252 loopback redirects for native clients; multisite gets per-site tables, network-wide pause and per-site uninstall.
* New admin panels for Safety, API Tokens and Change History.

**Tools**

* 31 core tools: user management and user meta (self-delete and last-administrator guards), comment moderation and bulk moderation, full-post reads, preview links, term and revision reads, post statuses, menu get / update / delete, widget create / get / delete, site settings, Site Health, and running a cron event.
* Privileged tools, off until the owner enables them: database search-replace (preview first, serialized-data safe), read-only wp-content file access with secrets masked, an in-process REST bridge, and a feedback note.
* WooCommerce grows to 47 tools: customers, webhooks, reports, categories / gateways / shipping / tax, order notes and refunds, product delete and batch updates.
* BuddyPress (9 new tools), The Events Calendar event create / update / delete plus venue and organizer create and venue get, ACF term and user fields, seo_get_head and seo_get_content_analysis, plus GA4 compatibility, Search Console site detail and DataForSEO balance / keywords-for-site.
* Elementor: nested widgets keep their children (#295), derived CSS is regenerated after _elementor_* meta writes and the outline gains size controls (#296), elementor_replace_text covers every text-bearing setting (#297).

**New plugin support**

* SEO metadata: SiteSEO and SureRank join Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO and The SEO Framework, for posts and terms. A field a plugin does not store is still refused by name.
* Email status (email_get_status): GoSMTP and SureMail. Secrets are never returned.
* Forms: SureForms and Everest Forms support the full forms_* set. Status changes are verified by a re-read before an undo token is issued, trashing never deletes, and undo restores the exact prior status.
* Code snippets: snippet_list and snippet_get now also read WPCode. Still read-only.

= 0.16.0 =
WordPress.org review fixes: no code-insertion write tools, AI media on the core AI Client, every external service documented.

**Behaviour changes**

* Code insertion removed. The write tools snippet_create, snippet_update, elementor_create_code, elementor_update_code and elementor_delete_code are gone, together with the "Allow code snippets" switch and the matching undo operations. snippet_list, snippet_get, elementor_list_code and elementor_get_code stay as read-only tools, so an assistant can no longer insert PHP, JavaScript or CSS through More MCP. Elementor Pro with Custom Code now adds up to 31 Elementor tools (it was 34).
* AI media now uses the WordPress AI Client (WordPress 7.0 or later). ai_generate_image and ai_generate_alt_text hand the request to the provider connected under Settings > Connectors; More MCP no longer stores AI provider keys or calls any AI vendor. The AI Providers screen (the "AI models" sub-tab of External Services) and its test-connection button are removed, and any AI vendor keys saved by earlier versions are deleted from the options table on upgrade. The tools are listed only when the switch is on and WordPress has a capable provider.
* External Services now holds only the SEO and analytics data sources.
* The OAuth consent page no longer prints a "Powered by" credit, and its stylesheet is enqueued instead of inlined.

**Hardening and housekeeping**

* Every exception message that carries dynamic text is escaped, direct file access is blocked at the top of every file, and the SSRF guard used by webhooks, media sideloads and SEO data moved to its own class (Url_Guard).
* The ACF integration classes are named Advanced_Custom_Fields so they cannot be mistaken for a bundled ACF library.
* The lifecycle loader no longer re-includes wp-admin files that WordPress already loads.
* readme: Contributors corrected, and a new External services section documents each optional third-party service, what is sent, when, and links to its terms and privacy policy.

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

Earlier versions are listed in `changelog.txt` in the plugin folder.
