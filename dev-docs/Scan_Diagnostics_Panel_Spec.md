# Scan Diagnostics Panel — Implementation Spec

**Status:** v1.1 priority item, builds on the existing AI Visibility Scanner POC (see main implementation spec, §11 in particular — this panel is the visible instrument for everything described there).

**Purpose:** Right now, when a check reports pass/warn/fail, you can see the *result* but not the *reasoning* — which fetch strategy ran, what the raw response actually was, whether a fallback fired, whether Cloudflare/a security plugin/a cache layer interfered. For the cross-host reliability testing you're doing right now (introduce issue → confirm detection → fix → confirm resolution, repeated across different hosts), that reasoning is the actual thing you need to see. This panel makes it visible.

**Build now:** full internal/verbose mode only, gated to admins, not for public consumption.
**Design for later, don't build yet:** a "public mode" rendering layer that shows a much shorter version to non-technical site owners when a check fails. The data model below is built so that split is a rendering change later, not a rearchitecture — see §7.

---

## 1. Data Model

Add one new table, kept separate from `avs_check_results` (already in the main spec) — diagnostics are far more verbose (raw headers, body snippets, timing) and have a different lifecycle (short retention, debug-purposed) than check results (which persist as the permanent scan history).

### `{prefix}avs_scan_diagnostics` — one row per fetch attempt

| column | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| scan_id | BIGINT UNSIGNED | FK to `avs_scans.id` |
| check_slug | VARCHAR(100) NULL | which check triggered this fetch; null for scan-level connectivity self-tests |
| page_url | VARCHAR(255) NULL | null for site-level fetches (robots.txt, sitemap) |
| fetch_strategy | ENUM('internal_render','http_loopback','http_external') | see main spec §11.1 — `http_loopback` is the 127.0.0.1+Host-header path, `http_external` is a plain public-URL fetch if that path is ever used as a fallback |
| target_url | VARCHAR(255) | the actual URL or internal route hit |
| request_headers | TEXT | JSON-encoded, especially the User-Agent string sent and any custom `X-AVS-*` header used |
| response_http_code | SMALLINT NULL | null if the request never got a response (DNS failure, timeout, connection refused) |
| response_headers | TEXT | JSON-encoded raw response headers — capture ALL of them, not a filtered subset; you don't know yet which header will end up being the diagnostic clue on some host you haven't tested |
| response_time_ms | INT | wall-clock time for this fetch |
| response_body_size_bytes | INT NULL | |
| response_body_snippet | TEXT | first ~2000 chars of the raw response body — enough to visually confirm a Cloudflare challenge page ("Just a moment..."), a WAF block page, a PHP fatal error, or a security-plugin block message, without storing entire page bodies |
| error_type | ENUM('none','timeout','dns_failure','connection_refused','ssl_error','http_error','cloudflare_challenge','waf_block_suspected','unknown') | `cloudflare_challenge` and `waf_block_suspected` are detected via body/header sniffing (see §2), not returned directly by the HTTP layer |
| error_message | TEXT NULL | raw `WP_Error` message if `wp_remote_get()` returned one |
| retry_count | TINYINT DEFAULT 0 | |
| fallback_triggered | BOOLEAN DEFAULT FALSE | whether this fetch was itself a fallback from a failed primary attempt |
| fallback_from_diagnostic_id | BIGINT UNSIGNED NULL | self-referencing FK, links a fallback attempt back to the attempt it followed |
| created_at | DATETIME | |

### `{prefix}avs_scan_environment` — one row per scan, captured once at scan start

This is the piece that makes cross-host comparison actually usable — without it, you have to remember which host each scan ran on. Capture it automatically, every time.

| column | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| scan_id | BIGINT UNSIGNED | FK, one-to-one with `avs_scans.id` |
| wp_version | VARCHAR(20) | |
| php_version | VARCHAR(20) | |
| active_theme | VARCHAR(100) | |
| active_page_builders | TEXT | JSON array — detected via `is_plugin_active()` against a known list: Elementor, Divi, Beaver Builder, Bricks, WPBakery, Oxygen |
| active_security_plugins | TEXT | JSON array — Wordfence, Sucuri, iThemes Security, All In One WP Security, etc. |
| active_cache_plugins | TEXT | JSON array — WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, etc. |
| active_seo_plugins | TEXT | JSON array — Yoast, RankMath, AIOSEO, etc. — relevant since they can inject their own schema/meta that your checks will read |
| cloudflare_detected | BOOLEAN | detected via `cf-ray`/`cf-cache-status` presence on the connectivity self-test |
| server_software | VARCHAR(255) | raw `Server` response header, where available |
| hosting_signature_guess | VARCHAR(100) NULL | best-effort detection of common managed hosts (WP Engine, Kinsta, Cloudways, Pressable) via known response headers — label clearly as a guess, not authoritative |
| loopback_connectivity | ENUM('ok','failed','not_tested') | result of the basic self-test described in §3 |
| site_url_snapshot | VARCHAR(255) | |
| created_at | DATETIME | |

**Retention:** diagnostics are debug data, not permanent record — keep only the last 10 scans' worth of `avs_scan_diagnostics` rows (prune older ones via a scheduled cleanup task tied to `avs_scans` retention), but keep `avs_scan_environment` rows indefinitely alongside `avs_scans` — they're small, and having a permanent environment history per scan is exactly what makes long-term cross-host comparison work later.

---

## 2. Detection Logic for `error_type` Sniffing

These aren't returned directly by `wp_remote_get()` — build a small classifier that runs on every completed fetch, before logging:

- **`cloudflare_challenge`**: response body contains known interstitial markers (e.g. "Just a moment...", "Checking your browser", `cf-mitigated` header present, or a 503 with `cf-ray` header present and a Cloudflare-branded body).
- **`waf_block_suspected`**: non-2xx response (typically 403 or 406) combined with a body containing common WAF-branded block-page language (Wordfence, Sucuri, and generic mod_security block pages have recognizable strings) — keep this as a heuristic list you can extend as you encounter new hosts in testing, not a hardcoded final list.
- **`timeout`** / **`dns_failure`** / **`connection_refused`** / **`ssl_error`**: map directly from the `WP_Error` code that `wp_remote_get()` returns.
- **`http_error`**: catch-all for any non-2xx response that didn't match the more specific categories above.
- **`none`**: clean 2xx response.

Log the classification result itself in `error_type`, but always keep `response_body_snippet` regardless of classification — you'll want to eyeball unclassified edge cases during testing, and the classifier will need refining as you hit hosts you haven't seen yet.

---

## 3. Connectivity Self-Test (standalone, separate from a full scan)

Build this as its own button/action, independent of "Run Scan" — you'll want to run it first on any new test environment, before committing to a full multi-page scan cycle.

Runs four checks, fast (should complete in a few seconds):
1. **Loopback reachability** — can the plugin reach its own site at all via `http_loopback`? This is the most basic signal; if this fails, every Strategy 2 check downstream is going to fail too, and you want that surfaced immediately rather than discovered 8 checks deep in a report.
2. **Robots.txt fetch** — raw content dump of what's actually returned, plus HTTP status.
3. **Sitemap fetch** — URL(s) checked, HTTP status, parsed URL count if successful.
4. **Cloudflare detection** — header sniff result, shown plainly ("Cloudflare detected: yes/no").

Output: a simple pass/fail card per check, each expandable to the same raw diagnostic detail (headers, body snippet, timing) as a full scan's log entries. This reuses the same `avs_scan_diagnostics` table — just tag these rows with `check_slug = null` and a dedicated `fetch_strategy` context so they're identifiable as self-test rows in the log, not check-driven fetches.

---

## 4. Ad-hoc Single-URL Test Tool

For your specific testing workflow (introduce one issue on one page, want to confirm detection quickly, without re-running the full 11-page scan every time): add a simple input field — paste a URL from the site, pick which check(s) to run against it (or "all"), hit "Test" — runs just those checks against just that one page and shows the diagnostic output immediately, without creating a full `avs_scans` row. This is the fastest iteration loop for exactly what you described doing next (introduce issues → verify detection → fix → verify resolution).

---

## 5. Admin UI — Diagnostics Tab

New tab alongside Dashboard/Report/Settings/History (per the main spec's admin structure). Layout, top to bottom:

1. **Environment Fingerprint card** — rendered from the current scan's `avs_scan_environment` row: WP/PHP versions, detected builders/security/cache/SEO plugins, Cloudflare status, hosting guess, loopback connectivity result. This should be the first thing visible — it's the context every other row in the log needs to be read correctly.
2. **Connectivity Self-Test button** — runs §3, shows results inline above the main log.
3. **Diagnostic log table** (`WP_List_Table`) — one row per `avs_scan_diagnostics` entry for the selected scan. Columns: check_slug, page_url, fetch_strategy, HTTP code, response time, error_type, fallback (yes/no). Filterable by error_type and fetch_strategy — when you're specifically hunting for "what failed on this host," filtering to `error_type != none` should be one click.
4. **Expandable row detail** — clicking a row expands to show full request headers, full response headers, the body snippet, retry count, and (if `fallback_triggered`) a link to the diagnostic row it fell back from.
5. **Scan Comparison / Diff view** — pick two scans (e.g. scan #4 before a fix, scan #5 after), get a table showing only what changed: checks that flipped fail→pass, pass→fail, or newly appeared/disappeared, with the underlying evidence from `avs_check_results` shown side by side. This is the direct tool for your "fix it, rerun, confirm it stopped reporting" workflow — build this as a first-class feature, not an afterthought, since it's the exact loop you're running right now and will keep running across every host you test.
6. **Single-URL ad-hoc test tool** (§4) — collapsed/secondary section, since it's used less often than the main log but should stay one click away.
7. **Export button** — dumps the full diagnostic log (environment + all fetch attempts) for the selected scan as JSON. Useful for attaching to a GitHub issue if a specific host's behavior needs a closer look later, or for sharing a repro case with a teammate without them needing access to that specific test environment.

---

## 6. Logging Integration Points

Where this hooks into the pipeline already defined in the main spec:

- **`class-page-fetcher.php`**: every call (both `http_loopback` and any `http_external` fallback) must log a diagnostics row on completion — success or failure. This is the single chokepoint for all Strategy 2 fetches, so instrument it once here rather than scattering logging calls across each check module.
- **In-process render path (Strategy 1)**: also log start/end time and any PHP errors/exceptions encountered during rendering (wrap in `try/catch`, capture via `set_error_handler()` scoped to the render call) — `internal_render` fetches can still fail (a fatal in a builder's render callback, for instance), and that failure needs to be visible in the same log, not silently swallowed.
- **`class-orchestrator.php`**: writes the `avs_scan_environment` row once at scan start, before any checks run — this needs the plugin/theme detection calls to happen first so the fingerprint is available for every diagnostic row generated afterward.
- **Check modules themselves** don't need to log fetches directly — they consume already-fetched content passed in via `$context` or the page fetcher's return value, so instrumenting the fetcher is sufficient to capture everything checks depend on.

---

## 7. Designing for the Future Public-Mode Split (don't build yet)

The reason to capture everything at full verbosity now: when you later want a lighter public-facing version (a site owner or their dev sees "we couldn't verify your sitemap — likely blocked by your security plugin, here's how to check" instead of a raw header dump), that should be a **rendering decision, not a data collection decision**. Plan for a constant/filter like `apply_filters( 'avs_diagnostics_verbosity', 'internal' )` that the admin UI checks before rendering — `internal` shows everything in §5, a future `public` mode would show only: check name, plain-language status ("couldn't verify"), the classified `error_type` translated to a one-line human explanation, and a link to a relevant help doc. Since `error_type` is already a clean enum rather than free text, that translation layer is a simple lookup table to build later — don't build the public strings now, just don't paint yourself into a corner where the verbose data can't be summarized cleanly.

---

## 8. Build Order (for the coding agent)

1. `avs_scan_environment` table + population logic in the orchestrator — do this first, it's a dependency for reading any diagnostics row correctly.
2. `avs_scan_diagnostics` table + logging calls in `class-page-fetcher.php` and the Strategy 1 render path.
3. Error-type classifier (§2) — build against real captured responses from your own test environments as you go, don't try to guess every WAF signature up front.
4. Connectivity self-test (§3) — standalone feature, reuses the logging table.
5. Diagnostics admin tab — environment card, log table, expandable rows.
6. Scan comparison/diff view — build this once you actually have at least two scans with a deliberate before/after issue to compare, so you're building against a real case rather than a hypothetical one.
7. Single-URL ad-hoc test tool.
8. Export-as-JSON.

Steps 1–4 are the minimum viable version that directly serves your immediate testing needs (confirming detection, confirming resolution, diagnosing host-specific failures). Steps 5–8 are the UI layer that makes that data usable without querying the database by hand — build 1–4 first if you want something usable within your current testing session, then layer the UI on top.
