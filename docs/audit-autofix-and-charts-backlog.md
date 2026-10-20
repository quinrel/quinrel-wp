# Quinrel Audits, Autofix & UX Charts — Engineering Backlog

**Document Status:** Approved for Engineering Execution  
**Target Release:** Quinrel v1.1.0 / v1.2.0  
**Target Environments:** WordPress 6.0 – 7.1+, PHP 8.2+  
**Architecture Reference:** [AGENTS.md](file:///Applications/XAMPP/xamppfiles/htdocs/quinrel-wp/AGENTS.md) & [Production Readiness](file:///Applications/XAMPP/xamppfiles/htdocs/quinrel-wp/docs/production-readiness.md)

---

## 1. Executive Summary & Problem Statement

Quinrel currently provides foundational audit engines across four core pillars:
1. **Lighthouse / PageSpeed Insights**
2. **Accessibility (WCAG 2.1 AA)**
3. **SEO Crawl & Site Health**
4. **Google Search Console Index Status**

While the plugin can **run** checks across all four areas and already provides **recommendations** in the Action Items To-Do hub and editor sidebars, significant capabilities remain **manual or partial**:
* PageSpeed fixes are limited to WebP conversion and CLS dimensions (missing automated script deferral, font display swap, and scheduled background CWV tracking).
* Accessibility fixes require manual per-image action or filename guessing (missing background Vision AI queues and contrast checking).
* SEO Crawl identifies dead links and redirect chains, but authors must manually edit articles to fix link URLs (missing 1-click in-content link rewriting and automated orphan re-linking).
* Google Index Status requires manual per-URL inspection clicks (missing scheduled automated index syncing and an end-to-end Indexation Funnel).

Furthermore, the administrative user experience lacks **visual diagnostic charts** (Core Web Vitals gauges, Indexation Funnels, Crawl Depth graphs, and WCAG Radar breakdowns) that enterprise plugins provide to communicate value and status at a glance.

This backlog defines the exact engineering specifications, database schemas, REST APIs, background worker jobs, and lightweight native React SVG chart components to transition all four areas into **autonomous self-healing systems with executive-grade data visualizations**.

---

## 2. Architecture & Visualization Design Principles

1. **Zero External Heavy Charting Bloat:**
   * No heavy 500KB+ runtime charting packages (e.g. Chart.js, Recharts, or D3).
   * All charts are implemented using **modular, accessible, responsive React SVG components** built natively with standard DOM elements, Tailwind utility classes, and ARIA attributes.
   * Every chart must render a visually-hidden, screen-reader-accessible table alternative (`.screen-reader-text` or `<table aria-label="...">`).
2. **Defensive Batch Processing:**
   * Every autofix or audit loop must run in bounded chunks (max 10–20 posts per execution) using Quinrel's `QueueManager` and WP-Cron.
   * Never block user save requests (`save_post`) or admin page loads with synchronous remote HTTP calls.
3. **Data Integrity & Non-Destructive In-Place Edits:**
   * In-place content modifications (e.g. link rewriting) must create a post revision, log the modification to `quinrel_audit_log`, and preserve all original HTML comments and shortcodes.
4. **WordPress Capability & Nonce Security:**
   * State-changing endpoints require `manage_seo` or `manage_options` plus valid REST nonce verification.

---

## 3. Epics & Technical Specifications

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                               QUINREL ENGINEERING ROADMAP                             │
├─────────────────────┬─────────────────────┬─────────────────────┬──────────────────────┤
│  EPIC 1: PAGESPEED  │   EPIC 2: A11Y      │   EPIC 3: CRAWL     │  EPIC 4: INDEXATION  │
│  & CWV AUTONOMY     │   & VISION AUDIT    │   & LINK HEALING    │  FUNNEL & GSC SYNC   │
├─────────────────────┼─────────────────────┼─────────────────────┼──────────────────────┤
│ • Script Deferral   │ • Background AI Alt │ • 1-Click Link Fix  │ • Auto GSC Sync Job  │
│ • Font Display Swap │ • Color Contrast    │ • Orphan Connector  │ • Indexation Funnel  │
│ • Scheduled Audits  │ • Form/Link Audit   │ • Redirect Flattener│ • Bulk Re-Index Push │
│ • SVG CWV Gauges    │ • WCAG Radar Chart  │ • Crawl Depth Chart │ • Cannibalization Map│
└─────────────────────┴─────────────────────┴─────────────────────┴──────────────────────┘
```

---

### EPIC 1: Autonomous PageSpeed & Core Web Vitals Optimization

#### 1.1 Epic Overview
* **Current State:** Runs PageSpeed v5 API on demand; autofixes WebP conversion and image dimensions; displays raw text metrics and opportunities.
* **Target State:** Automated script deferral/async tag injection, automated font display swap, scheduled background CWV audits for high-impact pages, and an executive Core Web Vitals interactive dashboard with SVG radial gauges and 30-day trend sparklines.

---

#### 1.2 Backlog Tasks

##### Task 1.2.1: Automated Non-Critical Script Deferral & Async Filter
* **Type:** Backend Autofix
* **Module:** `Quinrel\WordPress\Modules\Performance\ScriptOptimizer`
* **Specification:**
  * Hook into the WordPress core `script_loader_tag` filter.
  * When enabled via `quinrel_defer_non_critical_scripts` setting, inspect registered script handles.
  * Exempt WordPress core essentials (`jquery`, `jquery-core`, `jquery-migrate`, `wp-polyfill`).
  * Append `defer="defer"` (or `async="async"` for analytics tags) to all frontend script tags not flagged as critical.
  * Provide an exclusion filter `quinrel_script_defer_exclusions` for themes and third-party plugin overrides.
* **Acceptance Criteria:**
  * Frontend HTML output renders `<script src="..." defer="defer"></script>` for non-exempt scripts.
  * Admin and Block Editor scripts are never deferred.
  * Lighthouse "Eliminate render-blocking resources" penalty is reduced by ≥80%.

##### Task 1.2.2: Automated `font-display: swap` & Google Font Preconnect Injector
* **Type:** Backend Autofix
* **Module:** `Quinrel\WordPress\Modules\Performance\FontOptimizer`
* **Specification:**
  * Hook into `style_loader_tag` and `wp_head`.
  * Detect enqueued stylesheets from `fonts.googleapis.com`.
  * Ensure the `&display=swap` parameter is present in all Google Font URLs; if missing, rewrite the URL parameter during enqueue.
  * Inject preconnect links in `wp_head` at priority 1:
    ```html
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    ```
* **Acceptance Criteria:**
  * Zero FOIT (Flash of Invisible Text) audits triggered in Lighthouse.
  * Output verified with PHPUnit test cases covering various Google Font URL formats.

##### Task 1.2.3: Scheduled Top-Pages PageSpeed Background Worker
* **Type:** Background Audit Engine
* **Module:** `Quinrel\WordPress\Modules\Analytics\PageSpeedScheduler`
* **Specification:**
  * Define weekly WP-Cron event `quinrel_weekly_pagespeed_cron`.
  * Query the top 10 most viewed posts/pages (via GSC API impressions or post query) plus the homepage.
  * Push inspection tasks to `QueueManager` to process 1 URL per 30 seconds to respect Google API quotas.
  * Store timestamped scores in a new database table `quinrel_pagespeed_history` (`id`, `post_id`, `device`, `perf_score`, `lcp`, `cls`, `inp`, `created_at`).
* **Database Schema:**
  ```sql
  CREATE TABLE {$wpdb->prefix}quinrel_pagespeed_history (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      post_id BIGINT UNSIGNED NOT NULL,
      device VARCHAR(10) NOT NULL,
      perf_score TINYINT UNSIGNED NOT NULL,
      lcp_ms INT UNSIGNED NOT NULL,
      cls_score DECIMAL(5,3) NOT NULL,
      inp_ms INT UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_post_device (post_id, device),
      KEY idx_created (created_at)
  ) {$charset};
  ```

---

#### 1.3 UI & Visual Charts Specification: Core Web Vitals Executive Card

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  CORE WEB VITALS & LIGHTHOUSE PERFORMANCE (Mobile & Desktop)               [Run Scan] │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│     PERFORMANCE        ACCESSIBILITY        BEST PRACTICES             SEO             │
│        ┌───┐               ┌───┐                 ┌───┐                ┌───┐            │
│       │  94 │             │  98 │               │ 100 │              │  96 │           │
│        └───┘               └───┘                 └───┘                └───┘            │
│       Good (Green)        Good (Green)          Good (Green)         Good (Green)      │
│                                                                                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  CORE WEB VITALS METRICS (Field & Lab Data)                                            │
│                                                                                        │
│  LCP (Largest Contentful)     INP (Interaction Next Paint)    CLS (Cumulative Layout)  │
│  1.4s  [■■■■■■■■■■] Good     48ms  [■■■■■■■■■■] Good         0.01  [■■■■■■■■■■] Good  │
│  Trend: ─/─── 15% faster      Trend: ─────── Stable           Trend: ─────── 0 shifts  │
│                                                                                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  TOP OPTIMIZATION OPPORTUNITIES                                                        │
│  • Eliminate render-blocking CSS/JS   Est. Savings: 480ms   [1-Click Apply Deferral]   │
│  • Properly size hero banner image    Est. Savings: 220KB   [Auto-Compress WebP]       │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

* **React Component:** `src/assets/js/components/charts/CoreWebVitalsGauge.js`
* **SVG Radial Meter Specs:**
  * Outer radius: 45px, Stroke width: 6px, SVG Viewbox: `0 0 100 100`.
  * Background track: `#e2e8f0` (slate-200).
  * Color thresholds: `≥90` `#10b981` (emerald-500), `50–89` `#f59e0b` (amber-500), `<50` `#ef4444` (rose-500).
  * CSS `stroke-dasharray` / `stroke-dashoffset` transition animation on load.
* **Sparkline Specs:**
  * Clean inline SVG path `<path d="M..." fill="none" stroke="#6366f1" strokeWidth="2" />` showing 30-day metric changes.

---

### EPIC 2: Comprehensive Accessibility (WCAG 2.1 AA) & Vision AI

#### 2.1 Epic Overview
* **Current State:** Media alt text audit and basic heading checks; single-image AI alt generation or filename auto-fill.
* **Target State:** Asynchronous background Vision AI queue for bulk alt text generation, automated DOM color contrast auditor, empty link/button detector, and a WCAG 2.1 AA Compliance Radar/Donut chart.

---

#### 2.2 Backlog Tasks

##### Task 2.2.1: Background Vision AI Alt Text Generation Queue
* **Type:** Asynchronous AI Worker
* **Module:** `Quinrel\WordPress\Modules\ImageSeo\AiAltQueueWorker`
* **Specification:**
  * REST Endpoint: `POST /quinrel/v1/images/queue-ai-alt` (accepts optional list of attachment IDs or defaults to all uncaptioned images).
  * Enqueue batch jobs into `QueueManager` with rate-limiting (e.g. 5 images per background tick).
  * Call configured multimodal AI provider (OpenAI GPT-4o-mini Vision, Anthropic Claude 3.5 Haiku, or local Ollama).
  * Prompt instructions: *"Describe this image objectively in under 120 characters for a visually impaired user using a screen reader. Avoid saying 'image of' or 'picture of'."*
  * Save result to `_wp_attachment_image_alt` and flag `_quinrel_alt_source = 'ai_vision'`.
* **Acceptance Criteria:**
  * 100 uncaptioned media items can be queued without HTTP gateway timeouts.
  * Screen-reader descriptions are saved into WordPress media metadata.
  * Audit log records every generated alt attribute.

##### Task 2.2.2: Automated Color Contrast Ratio Audit (WCAG AA)
* **Type:** Client & Server DOM Audit
* **Module:** `Quinrel\WordPress\Modules\Accessibility\ContrastAuditor`
* **Specification:**
  * Scan rendered frontend templates for computed text color versus background color.
  * Compute relative luminance according to W3C formula:
    $$L = 0.2126 \cdot R + 0.7152 \cdot G + 0.0722 \cdot B$$
  * Compute contrast ratio:
    $$\text{Ratio} = \frac{L_1 + 0.05}{L_2 + 0.05}$$
  * Flag elements failing `4.5:1` (normal text) and `3:1` (large text $\ge 18\text{pt}$ or bold $\ge 14\text{pt}$).
  * Return failing selectors and suggest closest compliant hex color.

##### Task 2.2.3: Structural Accessibility Scanner (Empty Links, Buttons & Form Labels)
* **Type:** Content Audit
* **Module:** `Quinrel\WordPress\Modules\Accessibility\DomAccessibilityScanner`
* **Specification:**
  * Parse post content during audit run for:
    1. `<a>` anchors with no inner text, no `aria-label`, and no `title`.
    2. `<button>` elements with empty labels or untagged SVG icons.
    3. Input fields lacking matching `<label for="...">` or `aria-label`.
    4. Skipped heading levels (e.g. `<h1>` followed immediately by `<h3>`).
  * Return specific line numbers / HTML snippets to the audit results store.

---

#### 2.3 UI & Visual Charts Specification: WCAG AA Accessibility Scorecard

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  WCAG 2.1 AA ACCESSIBILITY AUDIT                                         [Run Audit]   │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│     WCAG COMPLIANCE SCORE             ACCESSIBILITY BREAKDOWN (By Principle)          │
│            ┌───────┐                  • Perceivable (Alt, Contrast)     92% [■■■■■■■]  │
│           │   88%   │                 • Operable (Keyboard, Links)      85% [■■■■■■░]  │
│            └───────┘                  • Understandable (Readability)    95% [■■■■■■■]  │
│         Level AA Passing              • Robust (Semantic HTML, ARIA)    80% [■■■■■░░]  │
│                                                                                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  MEDIA ACCESSIBILITY STATUS                                                            │
│  [■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■░░░░░░░░░░] 78% Coverage               │
│  • 142 Human Alt    • 86 AI Vision Alt    • 24 Missing Alt   [Queue AI Alt Generator]  │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  ACCESSIBILITY ACTION ITEMS                                                            │
│  [High]   24 images missing alt text in Media Library      [Auto-Fill with Vision AI]  │
│  [Medium] 3 links in "About Us" have empty anchor text     [Edit in Gutenberg]         │
│  [Low]    Button in footer has low contrast ratio (3.2:1)  [View Recommended Hex]      │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

* **React Component:** `src/assets/js/components/charts/AccessibilityBreakdownChart.js`
* **Donut Chart Specs:**
  * SVG circle with `strokeDasharray="calc(percent * circumference) circumference"`.
  * Center text: Overall score `%` and WCAG AA status badge.
* **Bar Specs:**
  * Multi-segment progress bar representing Human vs AI vs Missing alt tags with interactive hover tooltips.

---

### EPIC 3: Self-Healing SEO Crawl & In-Place Link Repair

#### 3.1 Epic Overview
* **Current State:** 14-point audit detects orphaned pages, 404s, and redirect chains. Post slug changes auto-generate 301 redirects.
* **Target State:** 1-Click "Self-Healing Link Replacer" that rewrites published content to point directly to final URLs, automated orphan content re-linking engine, and an interactive Crawl Depth & Redirect Chain visualizer.

---

#### 3.2 Backlog Tasks

##### Task 3.2.1: 1-Click Self-Healing In-Place Link Replacer
* **Type:** Backend Database Self-Healing
* **Module:** `Quinrel\WordPress\Modules\TechnicalSeo\ContentLinkHealer`
* **Specification:**
  * REST Route: `POST /quinrel/v1/broken-links/heal`
  * Payload: `{ "broken_link_id": 123 }` or `{ "all_known_redirects": true }`
  * Process:
    1. Identify all broken or redirecting links discovered by `BrokenLinkChecker`.
    2. Check if a valid 301 target exists in `RedirectManager` or if the destination URL resolves to 200 OK.
    3. Query the parent post, parse `post_content`, and execute safe URL replacement matching the exact `<a href="...">` attribute.
    4. Call `wp_update_post()` (creating a revision).
    5. Mark link as `resolved = 1` in `quinrel_broken_links`.
    6. Record action to `quinrel_audit_log`.
* **Acceptance Criteria:**
  * Replaces redirected/dead internal links directly in stored post content.
  * Eliminates internal 301 redirect hops across the site.
  * Verified safe against regex false-positives and escapes.

##### Task 3.2.2: Automated Orphan Content Re-Connector
* **Type:** Content Engine
* **Module:** `Quinrel\WordPress\Modules\ContentAssistant\OrphanLinker`
* **Specification:**
  * REST Route: `POST /quinrel/v1/content/reconnect-orphan`
  * Algorithm:
    1. Identify posts with zero inbound internal links (`orphanedPages()`).
    2. Query candidate parent posts sharing identical primary categories or high-overlap tags.
    3. Use `InternalLinkEngine` to identify natural anchor text matches in the candidate parent post.
    4. Provide the user with a 1-click preview: *"Insert link to [Orphan Post Title] inside [Parent Post Title] at paragraph 3"*.
    5. On approval, insert the internal link and invalidate the orphan cache.
* **Acceptance Criteria:**
  * Reduces orphaned content count to zero with minimal author friction.
  * Preserves editorial control via one-click approval cards in the To-Do Hub.

##### Task 3.2.3: Redirect Chain Collapser Engine
* **Type:** Technical SEO Maintenance
* **Module:** `Quinrel\WordPress\Modules\TechnicalSeo\RedirectChainCollapser`
* **Specification:**
  * Scan `quinrel_redirects` for multi-hop routes (e.g. `/url-a` ➔ `/url-b` ➔ `/url-c`).
  * Auto-collapse intermediate hops: update `/url-a` target directly to `/url-c`.
  * Detect circular loops (A ➔ B ➔ A) and deactivate the offending loop record with an admin warning notice.
* **Acceptance Criteria:**
  * Zero 301 redirect chains exceed 1 hop.
  * Circular loops are neutralized automatically.

---

#### 3.3 UI & Visual Charts Specification: Site Crawl & Link Graph Visualizer

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  SITE CRAWL HEALTH & ARCHITECTURE                                        [Run Crawl]   │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│     CRAWL HEALTH INDEX                   HTTP STATUS DISTRIBUTION                      │
│            ┌───────┐                     • 200 OK           184 pages [■■■■■■■■■░] 92% │
│           │   94%   │                    • 301 Redirect      12 pages [■░░░░░░░░░]  6% │
│            └───────┘                     • 404 Not Found      4 pages [■░░░░░░░░░]  2% │
│         Excellent Status                 • 500 Server Error   0 pages [░░░░░░░░░░]  0% │
│                                                                                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  CLICK DEPTH DISTRIBUTION                                                              │
│  Level 1 (Homepage)    : [■■] 1 page                                                   │
│  Level 2 (1 Click)     : [■■■■■■■■■■■■■■■■■■■■■■] 86 pages                             │
│  Level 3 (2 Clicks)    : [■■■■■■■■■■■■■■■■■■■■■■■■■■■■] 105 pages                      │
│  Level 4+ (Deep/Orphan): [■■■] 8 pages  (Requires internal linking attention)         │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  SELF-HEALING ACTIONS                                                                  │
│  • 12 Internal links point to 301 redirects        [1-Click Auto-Rewrite to Final URL] │
│  • 8 Orphan pages have 0 inbound internal links    [Suggest In-Content Links]          │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

* **React Component:** `src/assets/js/components/charts/CrawlDepthChart.js`
* **Horizontal Bar Histogram:**
  * Clean SVG horizontal bars showing distribution of pages by click depth from homepage.
  * Depth 4+ highlighted in amber/red warning colors to immediately draw attention to buried content.

---

### EPIC 4: Google Indexation Funnel & Automated Sync Engine

#### 4.1 Epic Overview
* **Current State:** On-demand GSC URL inspection for single or bulk (up to 10) posts; manual troubleshooting tips; instant push on save.
* **Target State:** Automated background indexation sync for all public URLs, an executive **Indexation Funnel Chart**, a 1-click bulk re-indexing push tool, and an automated query cannibalization resolver.

---

#### 4.2 Backlog Tasks

##### Task 4.2.1: Automated Background GSC Indexation Synchronizer
* **Type:** Asynchronous Worker
* **Module:** `Quinrel\WordPress\Modules\Indexing\GscIndexSyncWorker`
* **Specification:**
  * Hook into daily WP-Cron `quinrel_daily_gsc_sync`.
  * Maintain table `quinrel_index_status`:
    ```sql
    CREATE TABLE {$wpdb->prefix}quinrel_index_status (
        post_id BIGINT UNSIGNED NOT NULL,
        url VARCHAR(2048) NOT NULL,
        verdict VARCHAR(20) NOT NULL,
        coverage_state VARCHAR(100) NOT NULL,
        robots_txt_state VARCHAR(20) NOT NULL,
        indexing_state VARCHAR(20) NOT NULL,
        last_crawled DATETIME DEFAULT NULL,
        last_inspected DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (post_id),
        KEY idx_verdict (verdict),
        KEY idx_coverage (coverage_state)
    ) {$charset};
    ```
  * Process in rate-limited batches (e.g. 25 URLs per cron tick, respecting GSC daily inspection limits of 2,000/day).
  * Automatically refresh status for URLs older than 7 days.
* **Acceptance Criteria:**
  * Site administrator can see site-wide indexation status without clicking "Inspect" manually on every post.

##### Task 4.2.2: 1-Click Bulk Re-Indexation & IndexNow Push Hub
* **Type:** Indexing Integration
* **Module:** `Quinrel\WordPress\Modules\Indexing\BulkIndexingDispatcher`
* **Specification:**
  * REST Route: `POST /quinrel/v1/indexing/bulk-push`
  * Action: Dispatches all URLs marked `Discovered - currently not indexed` or updated within 48 hours simultaneously to:
    1. Google Indexing API (for eligible job/broadcast schema or general index queue)
    2. Bing / Yandex / Naver via IndexNow API
  * Updates transient cache and records batch submission timestamp.
* **Acceptance Criteria:**
  * Single click triggers instant notifications across all supported search engines.
  * Detailed response logs returned to the UI.

##### Task 4.2.3: Keyword Cannibalization Resolution Engine
* **Type:** Search Analytics
* **Module:** `Quinrel\WordPress\Modules\Analytics\CannibalizationResolver`
* **Specification:**
  * Query 90-day GSC search queries where multiple internal URLs rank for the same query with impressions $\ge 50$.
  * Compute impression and click split between competing URLs.
  * Provide a 1-click resolution drawer:
    * Option A: Set primary canonical tag pointing from weaker URL to dominant URL.
    * Option B: Create a 301 redirect consolidating weaker article into primary article.
    * Option C: De-optimize secondary post's focus keyword.

---

#### 4.3 UI & Visual Charts Specification: The Google Indexation Funnel

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  GOOGLE SEARCH CONSOLE INDEXATION FUNNEL                                [Sync Status]  │
├────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                        │
│  Total Published Posts        [████████████████████████████████████████] 200 (100%)    │
│            │                                                                           │
│  In XML Sitemap               [██████████████████████████████████████░░] 196 (98%)     │
│            │                                                                           │
│  Discovered by Google         [████████████████████████████████████░░░░] 188 (94%)     │
│            │                                                                           │
│  Crawled by Googlebot         [██──────────────────────────────────────] 180 (90%)     │
│            │                                                                           │
│  Indexed in Google Search     [████████████████████████████████░░░░░░░░] 168 (84%)     │
│            │                                                                           │
│  Generating Impressions (90d) [████████████████████████░░░░░░░░░░░░░░░░] 132 (66%)     │
│                                                                                        │
├────────────────────────────────────────────────────────────────────────────────────────┤
│  INDEX STATUS BREAKDOWN                                                                │
│  • Indexed & Valid            : 168 URLs (Green)                                       │
│  • Crawled - Not Indexed      :  12 URLs (Amber)   [Review Content Quality & Links]    │
│  • Discovered - Not Indexed   :   8 URLs (Amber)   [1-Click Push via IndexNow & GSC]   │
│  • Duplicate Without Canonical:   4 URLs (Orange)  [Auto-Align Canonical Tags]         │
│  • Excluded by 'noindex'      :   8 URLs (Gray)    [View Noindexed Content]            │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

* **React Component:** `src/assets/js/components/charts/IndexationFunnelChart.js`
* **Funnel Visualization Specs:**
  * Cascading tapered SVG bars with smooth gradients and percentage tags.
  * Interactive click on any bar filters the URL list below (e.g. clicking "Discovered - Not Indexed" displays the 8 URLs with 1-click push actions).

---

## 4. Implementation Phasing & Work Breakdown Structure

| Phase | Sprint / Milestone | Epics Included | Key Engineering Deliverables |
| :--- | :--- | :--- | :--- |
| **Phase A** | **Sprint 1: Self-Healing Crawl & Link Repair** | Epic 3 | • `ContentLinkHealer.php` (1-click link rewrite)<br>• `RedirectChainCollapser.php`<br>• `CrawlDepthChart.js` (SVG histogram) |
| **Phase B** | **Sprint 2: Vision AI & A11y Suite** | Epic 2 | • `AiAltQueueWorker.php` (Background Vision AI)<br>• `ContrastAuditor.php`<br>• `AccessibilityBreakdownChart.js` (Donut & bars) |
| **Phase C** | **Sprint 3: Automated Indexation Funnel** | Epic 4 | • `quinrel_index_status` DB migration<br>• `GscIndexSyncWorker.php` (Cron sync)<br>• `IndexationFunnelChart.js` (Interactive funnel) |
| **Phase D** | **Sprint 4: Autonomous CWV Optimization** | Epic 1 | • `ScriptOptimizer.php` (Defer filter)<br>• `FontOptimizer.php` (Swap injector)<br>• `CoreWebVitalsGauge.js` (Radial SVG gauges) |

---

## 5. Verification & Definition of Done

Each task in this backlog is subject to the strict engineering gate:

1. **Unit Testing:** 100% PHPUnit pass rate with dedicated test cases for every new class (`tests/Unit/Performance/`, `tests/Unit/Accessibility/`, `tests/Unit/Indexing/`).
2. **WordPress Standards:** 0 errors, 0 warnings across whole-tree `phpcs --standard=WordPress`.
3. **No External Bloat:** Verification that bundled JavaScript bundle (`dist/` or `build/`) does not include heavy third-party charting libraries.
4. **Data Safety:** Any in-place content rewriting must verify that revisions are created and rollbacks are supported.
5. **Internationalization:** All UI strings wrapped in `__()`, `_e()`, or `_n()` with `'quinrel'` text domain.
6. **UAT Validation:** Verified on local testbed at `http://localhost/holvek-uat/` with zero PHP notices or console errors.

