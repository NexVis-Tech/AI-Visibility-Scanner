# AI Visibility Scanner — POC Implementation Spec

**Plugin name:** AI Visibility Scanner
**Scope of this document:** Track 1 only (read-only audit + scoring + report). No auto-fix logic in this POC. Fix capability is planned as a future unlock within the same plugin (see "Future: Fix Module" at the end) — do not build it now, but do not architect anything that would block it either.

**Distribution decision (locked):** Public WP.org directory listing + public GitHub repository as the canonical source. This is a deliberate branding move — the plugin is a public case study and proof-of-skill for nexvistech.com, not just a lead-gen tool. See §12 for WP.org-specific compliance detail and §13 for the GitHub/repo workflow — this choice changes how the freemium boundary, branding, and release process all have to work, so treat it as load-bearing, not cosmetic.

**Monetization decision (locked):** Free tier distributed on WP.org, capped at 30 pages per scan (configurable — see §7). Pro tier (unlimited pages, whitelabel, eventually the Fix Module) sold outside WP.org from your own site — WP.org does not allow charging within the directory itself, only fully-functional free/freemium distribution.

**Audience:** This doc is written to be handed to a coding agent or a human developer with no prior context beyond this file. Every module below should be buildable independently and testably in isolation.

---

## 1. Compatibility Targets

Based on July 2026 WordPress ecosystem state:

| Requirement | Minimum | Recommended / Target |
|---|---|---|
| WordPress core | 6.5 | Test against 6.9 and 7.0 (current as of mid-2026) |
| PHP | 7.4 (WP 7.0's own hard floor) | Develop and test against 8.3; do not use syntax that breaks under 8.1+ |
| MySQL / MariaDB | 5.7 / 10.4 | 8.0 / 10.6+ |
| Browser (admin UI) | Last 2 versions of Chrome, Firefox, Safari, Edge | — |

Practical implications for coding:
- Write PHP compatible with 7.4 syntax (no enums, no readonly properties, no first-class callable syntax) so the plugin doesn't break on lagging client hosts — but avoid deprecated functions removed in 8.x (e.g. avoid `each()`, `create_function()`, curly-brace string offsets).
- Use `wp_remote_get()` / `WP_Http` for all HTTP calls, never raw `curl` or `file_get_contents()` — required for compatibility with hosts that disable those functions and for proper WP filter hook support.
- Declare `Requires PHP: 7.4` and `Requires at least: 6.5` in the plugin header, and `Tested up to: 7.0`.
- Register with the Plugin Check (PCP) tool mentally in mind from day one — WP.org submission requires passing it, and retrofitting compliance later is expensive. Key PCP concerns: no direct `$_GET`/`$_POST` access without sanitization, no direct DB queries without `$wpdb->prepare()`, all output escaped, all user-facing strings translatable.

---

## 2. Architecture Overview

Single plugin, object-oriented, PSR-4-ish autoloading (via Composer or a lightweight custom autoloader — recommend Composer since build tooling is already part of your stack on other projects).

**High-level flow:**

```
Admin clicks "Run Scan"
  → Scan Orchestrator enqueues a scan job
  → Crawler fetches site URLs (sitemap-driven, capped list for POC)
  → Each fetched page is passed through the Check Pipeline
  → Each Check Module returns a structured result (pass/warn/fail + evidence + fix hint)
  → Scoring Engine aggregates check results into sub-scores + composite score
  → Results persisted to custom DB table
  → Admin UI renders the report from stored results
  → PDF export renders the same data via a report template
```

**Why a queued/async job model even for POC:** scanning more than a handful of pages synchronously in a single HTTP request will hit PHP `max_execution_time` on shared hosting (commonly 30–60s). Build the scan as a background job from day one using Action Scheduler (bundled dependency, same library WooCommerce uses — reliable, well-tested, avoids reinventing cron reliability). This is the single most important architectural decision in this spec — retrofitting async behavior after a synchronous v1 is a rewrite, not a patch.

---

## 3. File / Folder Structure

```
ai-visibility-scanner/
├── ai-visibility-scanner.php          # Main plugin file, header, activation/deactivation hooks
├── composer.json
├── uninstall.php                       # Clean removal of DB tables + options on uninstall
├── includes/
│   ├── class-plugin.php                # Bootstraps everything, singleton
│   ├── class-activator.php             # Creates DB tables on activation
│   ├── class-deactivator.php           # Cleanup on deactivation (not uninstall)
│   ├── admin/
│   │   ├── class-admin-menu.php        # Registers wp-admin page(s)
│   │   ├── class-admin-assets.php      # Enqueues CSS/JS only on plugin's own admin page
│   │   ├── views/
│   │   │   ├── dashboard.php           # "Run Scan" screen + latest score summary
│   │   │   ├── report.php              # Full report view
│   │   │   └── settings.php            # Scan scope settings (see §7)
│   ├── scanner/
│   │   ├── class-orchestrator.php      # Coordinates a scan run end-to-end
│   │   ├── class-crawler.php           # Fetches URLs (sitemap parse + queue)
│   │   ├── class-page-fetcher.php      # wp_remote_get wrapper, handles redirects/timeouts
│   │   ├── class-scan-job.php          # Action Scheduler task definition
│   │   └── checks/
│   │       ├── interface-check.php     # Contract every check module implements
│   │       ├── class-check-registry.php
│   │       ├── crawlability/
│   │       │   ├── class-robots-ai-bots.php
│   │       │   ├── class-sitemap-coverage.php
│   │       │   └── class-noindex-canonical.php
│   │       ├── schema/
│   │       │   ├── class-schema-presence.php
│   │       │   └── class-schema-validity.php
│   │       ├── content/
│   │       │   ├── class-heading-hierarchy.php
│   │       │   ├── class-meta-description.php
│   │       │   └── class-faq-howto-opportunity.php
│   │       └── experience/
│   │           └── class-core-web-vitals-flag.php  # Flag-only, no fix, in POC
│   ├── scoring/
│   │   ├── class-scoring-engine.php    # Weighted aggregation logic (see §6)
│   │   └── class-score-history.php     # Before/after tracking
│   ├── report/
│   │   ├── class-report-builder.php    # Assembles data for display
│   │   └── class-pdf-exporter.php      # Uses existing PDF pipeline pattern
│   └── db/
│       └── class-schema.php            # dbDelta table definitions
├── assets/
│   ├── css/admin.css
│   └── js/admin.js                     # Polls scan status, renders progress bar
└── languages/                           # .pot file for translations
```

---

## 4. Data Model

Two custom tables (using `dbDelta()` via `class-schema.php`, prefixed with `$wpdb->prefix`):

**`{prefix}avs_scans`** — one row per scan run
| column | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| site_url | VARCHAR(255) | snapshot at scan time |
| status | ENUM('queued','running','completed','failed') | |
| pages_scanned | INT | |
| pages_total | INT | |
| composite_score | TINYINT | 0–100, nullable until completed |
| subscore_crawlability | TINYINT | |
| subscore_schema | TINYINT | |
| subscore_content | TINYINT | |
| subscore_experience | TINYINT | |
| started_at | DATETIME | |
| completed_at | DATETIME NULL | |

**`{prefix}avs_check_results`** — one row per check per page per scan
| column | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| scan_id | BIGINT UNSIGNED | FK to avs_scans.id |
| page_url | VARCHAR(255) | |
| check_slug | VARCHAR(100) | e.g. `robots_ai_bots`, `schema_validity` |
| category | VARCHAR(50) | crawlability / schema / content / experience |
| result | ENUM('pass','warn','fail') | |
| evidence | TEXT | raw finding, e.g. the actual robots.txt line, or the invalid JSON-LD snippet |
| fix_hint | TEXT | one-line human-readable fix suggestion |
| effort_score | TINYINT | 1 (trivial) – 5 (dev work), used for prioritized fix list |
| impact_score | TINYINT | 1–5, used for prioritized fix list |

Rationale for storing per-page, per-check rows rather than a JSON blob: makes the prioritized fix list, filtering, and before/after diffing trivial with plain SQL, and keeps report rendering fast without deserializing large blobs.

**On uninstall**, `uninstall.php` drops both tables — don't leave orphaned data on a client's production DB after removal.

---

## 5. Check Modules — Detailed Logic

Every check implements `Check_Interface`:

```php
interface Check_Interface {
    public function get_slug(): string;
    public function get_category(): string;
    public function run( string $page_url, string $html_body, array $context ): Check_Result;
}
```

`$context` carries shared data fetched once per scan (not per page) — e.g. the parsed `robots.txt`, the sitemap URL list — so checks that need site-level data don't each re-fetch it.

### Category A: Crawlability

**`robots_ai_bots`**
- Fetch `/robots.txt` once per scan (site-level, not per-page).
- Parse for `User-agent` blocks matching `GPTBot`, `ClaudeBot`, `PerplexityBot`, `Google-Extended`, `CCBot`, `Bytespider`.
- Fail if any of the four priority bots (GPTBot, ClaudeBot, PerplexityBot, Google-Extended) are disallowed via `Disallow: /`.
- Warn if robots.txt doesn't exist at all (WordPress virtual robots.txt via `do_robots()` may mean no physical file — check the actual served output, not just file existence).
- Evidence: the exact matching `User-agent`/`Disallow` lines found.
- **Fetch this over HTTP (loopback), never via direct filesystem read — see §11.2.**

**`cloudflare_edge_bot_risk`** *(new — see §11.3 for full rationale, required, do not skip)*
- Detect Cloudflare via response headers on a normal fetch (`cf-ray`, `cf-cache-status`, or equivalent).
- If detected: regardless of `robots_ai_bots`' result, always attach a companion notice that Cloudflare's edge-level AI bot rules can independently override robots.txt, and that this plugin cannot verify that dashboard setting from inside WordPress. This check's result should never be a clean "pass" when Cloudflare is present — at minimum a "warn" with the disclosure, even if robots.txt itself looks correct.
- Evidence: the detected Cloudflare header(s).

**`sitemap_coverage`**
- Locate sitemap via `wp-sitemap.xml` (WP core sitemaps, 5.5+) or common alternates (Yoast `sitemap_index.xml`, RankMath) — check `<link rel="sitemap">` in HTML head first, fall back to known paths.
- Parse sitemap, compare URL count/types against actual published post/page count via `wp_count_posts()`.
- Warn if sitemap is missing entirely, or if coverage is under ~90% of published content.
- This check runs once per scan, not per page.

**`noindex_canonical`**
- Per-page: parse `<meta name="robots">` for `noindex`, and `<link rel="canonical">`.
- Fail if a page intended to be indexable (published, not excluded by user settings) carries `noindex`.
- Warn if canonical URL points to a different domain or a URL returning non-200 (light validation only in POC — don't crawl the canonical target, just flag mismatched domain as a warning).

### Category B: Schema

**`schema_presence`**
- Per-page: extract all `<script type="application/ld+json">` blocks.
- Fail if zero schema blocks found on content types that typically warrant it (posts, pages with substantial content) — this is a warn, not a hard fail, since not every page needs schema.
- Record which `@type` values are present (Article, FAQPage, HowTo, Organization, etc).

**`schema_validity`**
- For each JSON-LD block found: attempt `json_decode()`. Fail if invalid JSON.
- If valid JSON, check for required fields per common type (e.g. `Article` needs `headline`, `author`, `datePublished`; `FAQPage` needs `mainEntity` array with `Question`/`Answer` pairs).
- This is a **local structural check, not a full Rich Results Test integration** — POC scope explicitly excludes calling Google's Rich Results Test API (rate limits, external dependency risk). Note this as a known gap in the report itself ("basic structural validation only — for full validation, run flagged pages through Google's Rich Results Test").

### Category C: Content

**`heading_hierarchy`**
- Per-page: parse H1–H6 order.
- Fail if zero or multiple H1s.
- Warn if heading levels skip (H2 directly to H4).

**`meta_description`**
- Per-page: check `<meta name="description">` presence and length (warn if under 50 or over 160 chars — not a hard SEO rule anymore but still a reasonable extractability signal).

**`faq_howto_opportunity`**
- Heuristic only, no fix: scan page text for question-like headings (heading text ending in "?", or matching patterns like "How to", "What is") that aren't already backed by FAQPage/HowTo schema (cross-reference with `schema_presence` results for that page).
- This is a "warn" opportunity flag, not a pass/fail — frame in the report as "content opportunity" not "error."

### Category D: Experience (flag-only in POC)

**`core_web_vitals_flag`**
- Do not run a real Lighthouse/CrUX call in the POC (adds external dependency + latency). Instead, just flag: "Page experience (Core Web Vitals / INP) affects AI Overview eligibility — recommend running Speed Shield or PageSpeed Insights separately."
- This check exists mainly to create the cross-sell touchpoint into Speed Shield in the report, not to produce real data yet.

---

## 6. Scoring Engine

Simple, transparent, and defensible — avoid a black-box score that clients can't understand or dispute.

- Each check result maps to points: `pass = 1.0`, `warn = 0.5`, `fail = 0.0`, multiplied by that check's weight.
- Suggested initial weights (tune after piloting on real sites):
  - Crawlability: 30%
  - Schema: 30%
  - Content: 30%
  - Experience: 10% (low weight since it's flag-only in POC)
- Sub-score = weighted average of that category's checks, scaled to 0–100.
- Composite = weighted average of the four sub-scores.
- **Prioritized fix list**: sort all `fail`/`warn` results by `impact_score / effort_score` descending — surfaces "high impact, low effort" fixes first, which is what makes the report read as advice rather than a dump.

Store scoring weights as filterable constants (`apply_filters( 'avs_category_weights', [...] )`) so they can be tuned without a code deploy once you have pilot data.

---

## 7. Admin UI Flow

1. **Settings screen** (build minimal for POC): scan scope — which post types to include (default: posts + pages), max pages to crawl, optional URL exclude list.
   - **Page cap: default 30, configurable via a settings field, hard-capped in code at 30 for the free/WP.org build via a filter (`apply_filters( 'avs_max_pages_free', 30 )`).** The settings field lets *you* adjust the number during pilots/demos without a code change, but the filter ceiling is what keeps the free build honest for WP.org review — it must not be raisable by the end user past 30 in the free build, or it stops being a real permanent scope limit and starts drifting toward the kind of unlimited-but-nagged pattern reviewers push back on.
   - Framing matters for both compliance and conversion: **"Scans your 30 most important pages, no time limit, no expiry" reads as a real product, not a crippled trial** — that's the distinction WP.org's "no trialware" rule cares about, and it's also the better sales framing. A site with 80 pages hitting a clean "upgrade to scan all 80" message at the end of a genuinely useful 30-page report is a stronger conversion moment than a countdown timer would be — it's positioned as "you got real value, here's more," not "your trial expired."
   - Page selection for the 30: don't just take the first 30 by ID — prioritize by a simple heuristic (homepage, top-level pages, most-recent posts, and pages already appearing in the sitemap's `priority` field if present) so the free scan covers what actually matters to the client, not an arbitrary slice.
2. **Dashboard screen**: "Run Scan" button → triggers orchestrator → Action Scheduler job enqueued → JS polls a REST endpoint every few seconds for job status → progress bar (`pages_scanned / pages_total`).
3. **Report screen**: composite score (large, prominent), four sub-scores, prioritized fix list, full per-page/per-check table (filterable by category/result).
4. **Export**: "Download PDF Report" button. Free build always renders a small, non-intrusive "Generated with AI Visibility Scanner — nexvistech.com" credit line in the PDF footer, on by default. This lives inside the generated report artifact, not embedded on the client's live public-facing website, so it sits outside WP.org guideline 10 (which governs forward-facing credit links injected into the site itself) — but keep a settings toggle to hide it anyway, off by default, purely for cleanliness and reviewer goodwill. **Whitelabel (replacing that credit with the agency's own logo/branding, or removing it entirely) is a Pro-only, paid-customer feature — gate it behind a license key check, not a free setting.** This is your natural, guideline-compliant whitelabel boundary: free users get a fully functional scanner with your credit on the report; paying customers (agencies reselling it to their own clients) buy the right to remove it.
5. **History**: simple list of past scans with date + composite score, so before/after comparison is visible without extra tooling.

Use WordPress admin UI conventions (`WP_List_Table` for the check-results table, standard `postbox` metabox styling for the dashboard) rather than a fully custom React SPA for the POC — faster to build, matches user expectations in wp-admin, and avoids a build-tooling dependency you don't need yet. React/Vue admin UI can be a v2 consideration if the product needs richer interactivity later.

REST API endpoints needed (registered under `avs/v1` namespace, capability-checked to `manage_options`):
- `POST /avs/v1/scans` — start a scan
- `GET /avs/v1/scans/{id}` — poll status
- `GET /avs/v1/scans/{id}/report` — fetch full report data

### 7a. Command center vs. in-editor integration (AIOSEO/Yoast pattern) — decision

**Recommendation: standalone command center for the POC (as spec'd above), not per-post editor integration.** Reasoning:

- **The underlying subject matter doesn't fit the editor model.** Yoast/AIOSEO integrate into the post editor because classic on-page SEO (title tag, meta description, focus keyword density) is genuinely a per-page concern. GEO/AEO is mostly the opposite: robots.txt, sitemap coverage, Cloudflare edge behavior, and site-wide schema consistency are *site-level* concerns with no meaningful home inside a single post's editor screen. Only a minority of this plugin's checks (schema-on-this-page, heading hierarchy, meta description) are genuinely single-page scoped — building a full per-post editor panel would spend most of its UI surface on checks that don't belong there.
- **Build cost is much higher than it looks.** A real AIOSEO-style integration means hooking into Gutenberg's sidebar (`@wordpress/plugins`/`@wordpress/edit-post`) *and* separately into Elementor's own editor, Divi's builder canvas, and any other builder in use — each with its own editing environment that doesn't share Gutenberg's extension APIs. That's three to four times the UI surface area of the single clean report screen this spec already describes, for a POC whose entire point is to validate the concept and get to WP.org/GitHub quickly.
- **It matches your existing product family pattern.** Speed Shield's Mirror Readiness Scanner is a scan → score → report workflow, not an inline editor widget. Keeping AI Visibility Scanner in the same shape reinforces a consistent "command center" brand across your product line rather than fragmenting the UX story between products.

**What to add later (v1.x, not POC) as a lightweight middle ground, once the core scanner is validated:**
- A single Gutenberg sidebar panel (via `@wordpress/plugins`, native block editor only — this will not appear inside Elementor/Divi's own edit screens, which is an acceptable limitation for a v1.x add-on) surfacing just the per-page-scoped checks (schema validity, heading hierarchy, meta description) for the post being edited, with a "view full site report" link back to the command center. This is a small, contained addition, not a rebuild.
- A colored score column in the Posts/Pages `WP_List_Table` (the same low-cost, high-visibility pattern Yoast uses for its traffic-light column) — this is nearly free to build since it just reads already-stored per-page rows from the `avs_check_results` table (§4), and it's a much better cost/visibility ratio than full editor integration.

Do not build either of these in the POC — they're natural v1.x additions once the scanning core and scoring model have been validated against real client sites, not before.

---

## 8. Security & WP.org Compliance Checklist

- All REST endpoints: `permission_callback` checking `current_user_can( 'manage_options' )`.
- All DB writes via `$wpdb->prepare()` or `$wpdb->insert()`/`update()` with explicit format arrays — never string-concatenated SQL.
- All settings form inputs: sanitized on save (`sanitize_text_field`, `absint`, etc.) and escaped on output (`esc_html`, `esc_attr`, `esc_url`).
- Nonces on all state-changing actions (`wp_nonce_field`, verified via `check_admin_referer` or the REST nonce header `X-WP-Nonce`).
- No `eval()`, no dynamic file includes based on user input.
- Outbound HTTP calls (crawling own site) via `wp_remote_get()` with a sane `timeout` (10–15s) and explicit `user-agent` string identifying the plugin, so this doesn't look like a scraper to the site's own security plugins (Wordfence etc. may otherwise flag the plugin's own crawl as suspicious — consider a documented allowlist recommendation for users running such plugins).
- Uninstall cleanliness as noted in §4.
- Text domain + `load_plugin_textdomain()` wired even if you don't translate anything yet — required for WP.org and trivial to add now vs. retrofit.

---

## 9. Build Phases (for agent or human execution, in order)

**Phase 0 — Scaffolding**
- Plugin header, activation/deactivation hooks, DB table creation via `class-schema.php`, Composer autoload setup, Action Scheduler included as a dependency.
- Deliverable: plugin activates cleanly on a fresh WP install, creates tables, no PHP notices.

**Phase 1 — Crawler + single check, end-to-end**
- Build `class-crawler.php` (sitemap discovery + page list) and `class-page-fetcher.php`.
- Implement exactly one check (`robots_ai_bots` — it's site-level and simplest) end-to-end through orchestrator → Action Scheduler job → DB storage.
- Deliverable: clicking "Run Scan" produces one stored result row, provable via `wp db query` or a debug dump. This phase proves the async architecture works before investing in more checks.

**Phase 2 — Remaining check modules**
- Implement all checks from §5 against the now-proven pipeline.
- Deliverable: a full scan on a real test site (nexvistech.com is the right pilot target) produces results across all categories.

**Phase 3 — Scoring + Report UI**
- Scoring engine, dashboard/report admin screens, REST endpoints, JS polling.
- Deliverable: a human can run a scan from wp-admin and read a comprehensible report without touching the database.

**Phase 4 — PDF export + history**
- PDF report generation, scan history list.
- Deliverable: exportable PDF suitable to send to a prospect as the sales artifact.

**Phase 5 — Hardening pass**
- Security checklist (§8) verification, run through WP Plugin Check tool, test against PHP 7.4 and 8.3, test on a site with 200+ pages to confirm the async job doesn't stall or duplicate.

---

## 10. Testing Plan

- **Unit tests** (PHPUnit, using `WP_UnitTestCase` via the standard WP test scaffold) for: scoring math, JSON-LD validity parsing, robots.txt parsing logic. These are pure-logic functions and should not require a live HTTP call to test — pass in fixture HTML/robots.txt strings.
- **Manual pilot test**: run against nexvistech.com first (you already plan to use this as the Speed Shield before/after site — same site can validate this plugin too), then against 1–2 client sites with different builders (Elementor, Gutenberg-only) to confirm the crawler and checks don't choke on builder-specific markup.
- **Load/timeout test**: run against a site with 200+ published posts to confirm Action Scheduler batches correctly and doesn't hit execution timeouts.
- **PHP version matrix test**: activate on PHP 7.4 and PHP 8.3 environments (two local Docker/LocalWP instances) — confirm no fatal errors on either.

---

## 11. Crawl Reliability & Fail-Safe Design

This section governs how `class-crawler.php` and `class-page-fetcher.php` (§3) actually fetch content, and why. Get this wrong and the plugin either silently produces false results on a meaningful fraction of real client sites, or breaks outright on hardened hosting — this is not a minor implementation detail.

### 11.1 Two fetch strategies, not one

**Strategy 1 — In-process render (default, used for all content/schema/heading/meta checks).** Never parse raw `post_content` directly for these checks — page builders (Elementor, Divi, Bricks, WPBakery) store shortcodes/JSON in that field, not final markup. Instead, render the permalink through WP's own template loading pipeline to a string in-memory (the same general technique static-export plugins like Simply Static use), or at minimum run content through `apply_filters( 'the_content', ... )` plus the theme's header/footer hooks. This captures whatever the builder actually outputs, and critically **never touches the network** — no DNS, no Cloudflare, no WAF, no hosting-level bot protection, no cache layer. This is what makes the crawler resilient across nearly all of §11.2's scenarios simultaneously, and it should be the default path for every check that doesn't specifically need an outside-in perspective.

**Strategy 2 — Real HTTP self-fetch (used only for checks that need "what does the outside world actually receive").** Robots.txt as served, sitemap reachability, general reachability sanity-check. This is the only path exposed to firewalls, Cloudflare, and hosting security — scope it down to as few checks as possible rather than defaulting every check to this path.

For Strategy 2, prefer a **loopback request bound to `127.0.0.1` with a `Host` header override** pointing at the origin server directly, rather than a plain `wp_remote_get()` to the site's public URL — this is the same technique WordPress's own Site Health "loopback request" test uses, and it bypasses Cloudflare's edge entirely for that one request since it never goes out to the internet and back.

### 11.2 Scenario-by-scenario fail-safes

These reliability figures are engineering estimates for planning purposes, not measured statistics — do not use them in marketing copy without real pilot data to back them up.

- **Cloudflare (proxied domains).** Only Strategy 2 checks are exposed. Mitigation: loopback-to-origin as above. Estimated reliability ~90%+ with loopback; a naive public-URL fetch without it can be meaningfully less reliable depending on the zone's bot-fight-mode settings.
- **Security-hardened hosting / WAF plugins (Wordfence, Sucuri, iThemes Security).** Detect active security plugins via `is_plugin_active()` and show a pre-scan admin notice naming the detected plugin with allowlist instructions. Self-throttle requests (space Strategy 2 calls via Action Scheduler, don't burst) so the scan itself never resembles an attack pattern.
- **Hard file permissions.** Never read `robots.txt` via direct filesystem access — always request it over HTTP (loopback). WordPress's `do_robots()` generates it virtually if no physical file exists, so the HTTP path resolves correctly either way; a raw file read does not.
- **Advanced page builders.** Handled by Strategy 1 by design — this is the main reason Strategy 1 exists.
- **JS-only client-side-injected content** (rare: some accordion/infinite-scroll blocks, heavy client-hydrated React/Gutenberg content). Genuine, acknowledged gap — no headless browser rendering in the POC (too heavy for typical shared PHP hosting, and a real infra dependency to avoid at this stage). Instead: flag pages with an unusually low text-to-markup ratio as "possibly contains JS-rendered content — recommend manual review" rather than silently scoring them as failing checks they may actually pass.
- **Full-page caching (WP Rocket, LiteSpeed Cache, Varnish).** Prefer Strategy 1 (bypasses cache by construction). If a Strategy 2 fetch is unavoidable, add a cache-busting query parameter.

### 11.3 The Cloudflare AI-bot-block blind spot — design this in explicitly

This is worth calling out on its own because it's easy to build a check that reports a false "pass." Since mid-2025, new Cloudflare zones block major AI crawlers (GPTBot, ClaudeBot, PerplexityBot) by default **at the network edge, before robots.txt is even consulted** — and Cloudflare is tightening this further from September 15, 2026, with per-category (Search/Agent/Training) defaults for newly onboarding domains. Because this block happens at the WAF layer, **a site's own robots.txt can say "allow GPTBot" while Cloudflare is still hard-blocking that bot before it ever reaches WordPress** — and neither Strategy 1 nor a same-server Strategy 2 fetch can detect this, since neither actually replicates the IP ranges and routing an external AI bot request goes through.

Do not let the `robots_ai_bots` check report a clean "pass" in this situation. Add a companion check, `cloudflare_edge_bot_risk`:
- Detect Cloudflare via response headers on a normal fetch (`cf-ray`, `cf-cache-status`, or similar).
- If detected, regardless of what robots.txt says, surface a clearly worded flag: *"This site is served through Cloudflare. Robots.txt permissions look correct, but Cloudflare's own edge-level AI bot rules can independently override this. Check Security → AI Crawl Control in your Cloudflare dashboard directly — this plugin cannot verify that setting from inside WordPress."*
- This should be an explicit, honestly-labeled limitation in both the UI and the readme, not a silent gap. For a GEO tool aimed at a technically literate audience, an accurate "we can't fully verify this, here's exactly where to check" is more credible than a green checkmark that might be wrong — and it's a good showcase of technical judgment for the case-study/authority goal behind this plugin.

### 11.4 Reporting principle: confidence, not just pass/fail

Extend the `check_results` table (§4) conceptually — every check result should carry not just `pass/warn/fail` but an implicit confidence level baked into its wording. A check that ran via Strategy 1 (in-process, high confidence) should read differently from one that depended on a Strategy 2 fetch that partially failed (e.g., timed out, got a Cloudflare challenge page, or received a non-200 response) — in the latter case, the report should say "could not verify" rather than presenting a guess as a fact. Detect Cloudflare challenge interstitials by sniffing for known challenge-page markers in the response body (e.g., "Just a moment..." or a `cf-mitigated` header) and treat that as an explicit unknown, not a failed check.

---

## 11a. Open Decisions — Resolved

~~1. Page cap for POC scans~~ → **Resolved: 30, configurable in settings, hard-ceilinged in code for the free build (see §7).**
~~2. Report branding~~ → **Resolved: default "Powered by" credit on the free build's PDF, toggleable for cleanliness, whitelabel removal gated to Pro (see §7 item 4).**
~~3. Distribution model~~ → **Resolved: public WP.org listing + public GitHub repo, both from day one.** This means Phase 5's compliance pass (§9) is not optional or deferrable — build with WP.org rules in mind from Phase 0, not as a retrofit. See §12.

---

## 12. WP.org Submission Compliance — What This Actually Requires

Current WP.org Plugin Directory guidelines (verified July 2026) that specifically bear on this plugin's design:

**"No trialware" + "fully functional free version."** The entire free-tier feature set described in this spec (Categories A–D checks, scoring, report, PDF export, history) must ship complete and permanently usable in the WP.org build — nothing time-limited, nothing that stops working after N uses. The 30-page cap is a *scope* limit, which is an accepted freemium pattern, not a trial. Do not implement anything resembling "X scans then upgrade" — that's a countdown, and countdowns are what get rejected. A capacity ceiling that never expires is fine.

**Upsell placement is restricted.** Guideline: upsell messaging is only permitted from the plugin's own settings screen or a link on the plugin's entry in the installed-plugins list — never as dashboard-wide banners, admin notices on unrelated screens, or anything resembling "hijacking the admin experience." Practical implication for this build: put the Pro upsell as a clearly labeled tab or section within the AI Visibility Scanner's own admin pages (e.g., a "Get Pro" tab next to Dashboard/Report/Settings), and optionally a small "Upgrade" link next to the plugin's row on the Plugins list page. Do not add a global admin notice that fires on every wp-admin page.

**No third-party tracking without consent.** Don't wire up any analytics (Google Analytics, Mixpanel, etc.) inside the plugin without an explicit opt-in. If you want usage telemetry to inform the Pro roadmap later, that has to be an explicit, off-by-default opt-in setting with clear disclosure in the readme — not silent by default.

**GPL v2 (or later) compatibility is mandatory.** Every bundled library must be GPL-compatible. Action Scheduler (proposed in §2) is MIT-licensed, which is GPL-compatible — fine to bundle. If you add any JS charting library or PDF library later, check its license before bundling; MIT, Apache 2.0, and BSD are all generally fine, but confirm case by case.

**No minified-only assets.** If admin.js/admin.css get built through a bundler later, the unminified source must also be included in the repo (already natural if GitHub is the source of truth — see §13).

**readme.txt is a first-class deliverable, not an afterthought.** WP.org's directory listing, search ranking, and the "Tested up to" badge are all driven by `readme.txt` in the WP.org-specific format (Contributors, Tags, Requires at least, Tested up to, Stable tag, Short Description under 150 chars, full description, FAQ, Changelog sections). Write this properly from the first submission — it's also your public-facing sales copy for the case study angle, so it's worth real effort, not a stub.

**Plugin Check (PCP) tool as a pre-submission gate.** Run the official Plugin Check plugin against the codebase before submitting — it catches most of the mechanical issues (unescaped output, unprepared SQL, missing text domain, direct file access without an `ABSPATH` guard) automatically. Treat a clean PCP run as a Phase 5 exit criterion, not optional polish.

**Review timeline reality check.** New WP.org plugin submissions typically take anywhere from a few days to several weeks for first review, depending on directory volume — budget for this in your public-launch timeline; don't announce a launch date that assumes same-week approval.

---

## 13. Public GitHub Repo — Workflow & Case-Study Considerations

Since the explicit goal here is authority-building and social proof, treat the repo itself as a deliverable, not just a mirror of what gets zipped to WP.org.

- **Repo as source of truth; WP.org SVN as a deploy target, not the primary workspace.** WP.org plugin hosting runs on SVN, but essentially nobody develops directly against SVN anymore — standard practice is: develop on GitHub, then use a GitHub Action (community-standard ones exist, e.g. `10up/action-wordpress-plugin-deploy`) that pushes a tagged release to the WP.org SVN repo automatically. This means your public GitHub history, issues, and commits are the actual "here's how we build things" proof — that's the artifact prospective clients or collaborators will actually look at.
- **LICENSE file:** GPLv2-or-later license file at repo root, matching the plugin header's declared license — required for both WP.org and for the repo to be legitimately open source.
- **Two readmes, different jobs:** `readme.txt` (WP.org format, described in §12) for the directory listing, and a separate `README.md` for GitHub — written for developers/prospects browsing the repo, not for WP.org's parser. The GitHub README is where the "built by nexvistech" positioning, a short writeup of the GEO/AEO methodology behind the checks, and a link back to your agency site all belong. This is the actual case-study surface.
- **CI on the repo, visible in green checkmarks:** wire up a basic GitHub Actions workflow running PHPUnit (§10) and PHP CompatInfo or a syntax check across PHP 7.4–8.3 on every push. Passing CI badges on a public repo are exactly the kind of quiet, credible proof-of-skill signal that supports the branding goal here — more convincing to a technically literate prospect than any amount of marketing copy.
- **CHANGELOG discipline:** keep `CHANGELOG.md` (GitHub) and the Changelog section of `readme.txt` (WP.org) in sync at every tagged release — inconsistent changelogs are a small but visible tell of a sloppily-run project, which cuts against the exact reputation goal you're going for here.
- **Contribution basics** (`CONTRIBUTING.md`, issue templates) are optional for a v1 but worth adding before or shortly after public launch if you want the repo to look actively maintained rather than a one-off dump — even a minimal version signals intent.

---

## Future: Fix Module (not in POC scope — noted for architectural awareness only)

When Track 2 is built, it will most likely:
- Add a `fixes/` namespace mirroring `checks/`, where each fixable check has a corresponding fixer class implementing a `Fixer_Interface` (`can_fix()`, `apply_fix()`, `preview_diff()`).
- Require a confirmation/review UI before writing changes (same caution pattern as Speed Shield's Playwright gate) — likely a "preview change → approve → apply" flow rather than one-click blind fixes, especially for schema markup edits.
- Reuse the existing `check_results` table structure — the `fix_hint` column already gives fixers something to key off.

Nothing in this POC spec needs to change to accommodate this later — it's additive.
