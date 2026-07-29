# AI Visibility Scanner — WordPress Plugin

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.4%2B-brightgreen.svg)](https://php.net)
[![GEO & AEO Ready](https://img.shields.io/badge/Optimization-GEO%20%26%20AEO-orange.svg)](#-what-is-ai-visibility--geo)

**AI Visibility Scanner (AVS)** is a powerful, open-source WordPress plugin designed to audit, score, and optimize your website for AI search engines, Answer Engine Optimization (AEO), Generative Engine Optimization (GEO), and LLM web crawlers (such as ChatGPT/GPTBot, Claude/ClaudeBot, Perplexity, and Google-Extended).

Developed and maintained by **[NexVis Technologies](https://nexvistech.com)** ([@NexVis-Tech](https://github.com/NexVis-Tech))  
**Lead Architect & Creator:** [Mudassar Ijaz](https://github.com/mudassarijaz) (@mudassarijaz)

---

## 💡 What is AI Visibility & GEO?

Traditional SEO optimizes your website for classic search engine results pages (SERPs). Today, millions of users ask questions directly to AI assistants like **ChatGPT**, **Perplexity**, **Claude**, **SearchGPT**, and **Google Gemini**.

These AI search engines send specialized web crawlers to read your site, parse structured data, and extract answers. If your website blocks these crawlers in `robots.txt`, lacks JSON-LD schema, or has broken heading structures, your content will **not** be included in AI-generated answers.

**AI Visibility Scanner** audits your site across four critical pillars, gives you an instant **AI Readiness Score (0-100)**, and provides a clear, prioritized action plan to fix issues before your competitors do.

---

## 🌟 Key Features

### 🛡️ 1. AI Crawlability & Indexing Diagnostics
* **AI Bot Permissions**: Scans `robots.txt` rules specifically targeting major AI crawlers (`GPTBot`, `ClaudeBot`, `PerplexityBot`, `Google-Extended`, `Bytespider`, `CCBot`).
* **Conflict Detection**: Identifies conflicts between physical `robots.txt` files on your server and WordPress virtual `robots.txt` outputs.
* **Sitemap Coverage**: Verifies XML sitemap index accessibility and URL coverage.
* **Meta Robots & Firewall Safeguards**: Flags unintended `noindex` directives and detects Cloudflare WAF / edge firewall blocking risks.

### 🏷️ 2. Schema & Structured Data Validation
* **JSON-LD Inspection**: Detects, parses, and validates JSON-LD structured data blocks across your content.
* **Required Entity Verification**: Validates essential schemas including `Article`, `Organization`, `FAQPage`, `HowTo`, and `Product`.
* **Syntax & Field Checking**: Highlights missing required properties or syntax errors that prevent AI engines from understanding your entity graph.

### 📝 3. Content Extractability & Structure
* **Heading Hierarchy Audit**: Checks `H1` through `H6` structure for logical parent-child relationships, ensuring AI can parse context cleanly.
* **Meta Description Signals**: Evaluates meta snippet length and clarity for AI search summary generation.
* **Untapped Schema Opportunities**: Automatically finds un-schematized FAQ and How-To content patterns on your pages so you can turn them into rich schema.

### ⚡ 4. Experience & Technical Alignment
* **AI Overview Eligibility**: Checks Core Web Vitals & INP (Interaction to Next Paint) readiness flags.
* **Text-to-HTML & Readability**: Analyzes content-to-code ratios to ensure fast, clean extraction for LLM retrieval-augmented generation (RAG) pipelines.

### 📊 5. Prioritized Action Plan & Scoring Engine
* **AI Readiness Score (0–100)**: Transparent, weighted subscores across Crawlability, Schema, Content, and Experience.
* **Impact vs. Effort ROI Sorting**: Fix recommendations are sorted by ROI (`High Impact / Easy Fix` first), telling you exactly what to fix first without feeling overwhelmed.

### 📄 6. Client-Ready PDF Report Export
* Export full diagnostic reports into professional, downloadable PDF documents for clients, team members, or executive reviews.

### 🩺 7. Advanced Diagnostics & Health Tools
* **Real-time Background Monitor**: Track scan progress live with step-by-step diagnostic logging.
* **Self-Test Suite**: Verifies PHP requirements, REST API endpoints, database tables, Action Scheduler queues, and loopback HTTP execution.
* **Ad-hoc Single URL Testing**: Test individual posts or landing pages instantly without re-running a full site scan.

### 🚀 8. Shared Hosting Resilient
* Built on top of WooCommerce's trusted **Action Scheduler** queueing library. Scans run asynchronously in the background without triggering PHP memory limits or 30-second server execution timeouts.

---

## 🚀 Quick Start Guide (For Non-Technical Users)

You don't need coding knowledge or paid API keys to use AI Visibility Scanner!

1. **Download**: Download the plugin `.zip` file from the [Releases](https://github.com/nexvis/AI-Visibility-Scanner/releases) page.
2. **Install**: Log into your WordPress Admin Dashboard, go to **Plugins > Add New > Upload Plugin**, choose the `.zip` file, and click **Install Now**.
3. **Activate**: Click **Activate Plugin**.
4. **Run Your First Scan**:
   - Go to **AI Visibility** in your WordPress side menu.
   - Click **Run Scan**.
   - Watch the scanner run in the background. Once complete, explore your score and follow the **Prioritized Fix List**!

---

## 🛠️ Installation & Setup (For Developers & Admins)

### Manual Installation via FTP / Git

```bash
cd wp-content/plugins/
git clone https://github.com/nexvis/AI-Visibility-Scanner.git ai-visibility-scanner
cd ai-visibility-scanner
composer install --no-dev
```

### Local Development Environment (Docker)

The project includes a ready-to-use Docker environment featuring WordPress, MariaDB, phpMyAdmin, and Mailpit:

```bash
# Windows
docker-start.bat

# Linux / macOS
chmod +x docker-start.sh
./docker-start.sh
```

- **WordPress Site**: `http://localhost:8080` (Admin: `admin` / `password`)
- **phpMyAdmin**: `http://localhost:8081`
- **Mailpit**: `http://localhost:8025`

---

## 🏗️ Architecture & How It Works

```
Admin UI / Trigger ──> WordPress REST API ──> Orchestrator
                                                   │
                                                   ▼
                                        Action Scheduler Queue
                                                   │
                                                   ▼
                                       Crawler (In-Process / Loopback)
                                                   │
                                                   ▼
                                       Check Pipeline (Crawl, Schema, Content, Exp)
                                                   │
                                                   ▼
                                       Scoring & Prioritization Engine
                                                   │
                                                   ▼
                                Custom DB Tables (`wp_avs_scans` & `wp_avs_check_results`)
                                                   │
                                                   ▼
                                Full Report UI & PDF Exporter
```

- **Storage**: Clean custom tables (`wp_avs_scans` and `wp_avs_check_results`) prevent database bloat in `wp_options`.
- **Autoloading**: Includes a PSR-4 fallback autoloader for environments running without Composer at runtime.

---

## ❓ Frequently Asked Questions

<details>
<summary><b>Will this plugin slow down my website?</b></summary>
<p>No. Scans run completely asynchronously in the background via Action Scheduler. Your frontend site performance and visitor speed are completely unaffected.</p>
</details>

<details>
<summary><b>Do I need an OpenAI API key or paid third-party subscription?</b></summary>
<p>No! AI Visibility Scanner is 100% self-contained within your WordPress installation. No external API keys or recurring fees required.</p>
</details>

<details>
<summary><b>Does this work on basic shared hosting (e.g. Bluehost, SiteGround, Hostinger)?</b></summary>
<p>Yes! Thanks to background queueing, the scan breaks work into lightweight background batches to prevent PHP execution timeouts.</p>
</details>

<details>
<summary><b>What is the difference between SEO and GEO / AEO?</b></summary>
<p>SEO (Search Engine Optimization) focuses on ranking links on Google search results. GEO (Generative Engine Optimization) and AEO (Answer Engine Optimization) focus on structuring your site so AI platforms like ChatGPT, Perplexity, and Claude quote and cite your content in synthesized answers.</p>
</details>

---

## 👨‍💻 Authors & Credits

- **Organization**: [NexVis Technologies](https://nexvistech.com) ([@NexVis-Tech](https://github.com/NexVis-Tech)) — Solutions Provider & Agency.
- **Lead Architect & Creator**: [Mudassar Ijaz](https://github.com/mudassarijaz) ([@mudassarijaz](https://github.com/mudassarijaz)) — Full-Stack Developer & Founder.

---

## 📄 License

This plugin is free open-source software licensed under the **GNU General Public License v2.0 or later** ([GPLv2+](LICENSE)).

Designed & Maintained with ❤️ by **[NexVis Technologies](https://nexvistech.com)** and **[Mudassar Ijaz](https://github.com/mudassarijaz)**.
