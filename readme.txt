=== AI Visibility Scanner ===
Contributors: nexvis, mudassarijaz
Donate link: https://nexvistech.com/
Tags: ai, geo, seo, schema, robots.txt
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit, score, and optimize your WordPress site for AI search engines, answer engines, and LLM web crawlers. Developed by NexVis Technologies & Mudassar Ijaz.

== Description ==

Is your website visible to AI search engines? Millions of users now turn directly to AI platforms like ChatGPT, Perplexity, Claude, SearchGPT, and Google Gemini to find recommendations and answers. If AI web crawlers are blocked by your site's settings or if your content lacks structured JSON-LD data and clean headings, your site will be skipped in AI answers.

**AI Visibility Scanner (AVS)** is an open-source, background-queued diagnostic plugin developed by **NexVis Technologies** and **Mudassar Ijaz** that measures how easily AI agents, web crawlers (such as GPTBot, ClaudeBot, PerplexityBot, Google-Extended), and search engines can discover, parse, understand, and extract content from your WordPress website.

AVS audits your website across four fundamental pillars of Generative Engine Optimization (GEO) and Answer Engine Optimization (AEO), assigns an aggregate **AI Readiness Score (0-100)**, and delivers a prioritized, high-ROI action plan.

= Key Pillars Audited =
* **AI Crawlability & Indexing**: Verifies robots.txt permissions for priority AI bots (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bytespider, CCBot), detects physical vs. virtual robots.txt conflicts, checks XML sitemap discovery, meta robots noindex flags, and Cloudflare edge firewall risk.
* **Schema & Structured Data**: Validates JSON-LD structured data blocks, checks required entity properties (Article, Organization, FAQPage, HowTo, Product), and flags syntax errors.
* **Content Extractability**: Analyzes H1-H6 heading hierarchy, meta description length signals, and discovers un-schematized FAQ and HowTo content opportunities.
* **Experience & Technical Readiness**: Checks INP and Core Web Vitals readiness flags, HTML-to-text ratio, and clean readability for LLM RAG pipelines.

= Why Choose AI Visibility Scanner? =
* **Prioritized Action Plan**: Subscores and fix recommendations are automatically sorted by Impact vs. Effort ROI so you know exactly what to fix first.
* **100% Self-Contained**: Works right out of the box. No external API keys, user registration, or paid third-party subscriptions required.
* **Shared Hosting Ready**: Uses Action Scheduler non-blocking background queueing to run smoothly on basic shared hosting without execution timeouts or memory errors.
* **Client-Ready PDF Reports**: Download professional PDF audit summaries to share with clients, management, or team members.
* **Diagnostics Suite**: Features real-time background scan monitoring, self-test health checks, and ad-hoc single URL testing.

== Installation ==

1. Log in to your WordPress Admin Dashboard.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Select the `ai-visibility-scanner.zip` file and click **Install Now**.
4. Activate the plugin through the **Plugins** menu.
5. Go to **AI Visibility** in your WordPress admin menu to run your first scan.

== Frequently Asked Questions ==

= Will this plugin slow down my website? =
No. All scans execute asynchronously in the background using Action Scheduler. Your live site and visitor load times remain completely unaffected.

= Do I need an OpenAI API key or paid subscription? =
No. AI Visibility Scanner runs 100% locally on your WordPress server with no external API dependency or fees.

= Does this plugin work on shared hosting? =
Yes! AI Visibility Scanner utilizes background queued processing, breaking up tasks so scanning runs reliably without exceeding hosting memory or execution time limits.

= What is Generative Engine Optimization (GEO)? =
GEO (Generative Engine Optimization) is the process of optimizing content so AI models (ChatGPT, Perplexity, Claude) can read, understand, and cite your site as a source in generated answers.

== Screenshots ==

1. **Dashboard Overview**: Overall AI Readiness Score (0-100) with category breakdown across Crawlability, Schema, Content, and Experience.
2. **Prioritized Action Plan**: Impact vs. Effort matrix sorting fix recommendations by ROI.
3. **Diagnostics & Self-Test Panel**: Real-time scan logger, environment collector, system diagnostics, and ad-hoc single URL tester.
4. **PDF Audit Report**: Downloadable client-ready PDF export.

== Changelog ==

= 1.0.0 =
* Initial public release of AI Visibility Scanner.
* Added 4-pillar audit checks (Crawlability, Schema, Content Extractability, Experience).
* Added Action Scheduler background job queueing.
* Added Prioritized Fix List sorted by Impact vs. Effort ratio.
* Added PDF report exporter and Diagnostics Panel.
