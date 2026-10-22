# AI Visibility, llms.txt & Crawler Shield

As search behavior shifts toward conversational answers and synthetic citations, web publishers require granular governance over how Large Language Model (LLM) systems ingest, summarize, and attribute their content.

Quinrel provides an AI Governance suite built around the open `/llms.txt` specification, dynamic crawler access controls, and explicit copyright training opt-outs.

---

## 1. The llms.txt Standard

The `/llms.txt` file format is an open standard designed to provide AI agents, research crawlers, and LLMs with a clean, lightweight, machine-readable digest of your website's authoritative content.

### What Quinrel Publishes:
* **Primary Manifest (`/llms.txt`):** A curated markdown document containing your organization profile, core topics, and direct links to foundational cornerstone articles.
* **Extended Content Digest:** A comprehensive markdown digest of all published public articles, stripped of CSS, JavaScript, sidebars, and navigation menus, exposing purely semantic headings and text.

### Configuring Your Digest:
1. Navigate to **Quinrel > AI Visibility > llms.txt Generator**.
2. Toggle **Enable llms.txt Output**.
3. **Site Summary / Bio:** Provide a concise 2–3 sentence description of your publication, its editorial standards, and its primary areas of subject-matter expertise.
4. **Included Post Types:** Check which public post types to include (e.g., Posts, Knowledge Base, Case Studies).
5. **Cornerstone Prioritization:** Toggle whether cornerstone content is grouped into an "Essential Reading" section at the top of the manifest.
6. Verify output by visiting `https://example.com/llms.txt` in your browser.

---

## 2. 3-Tier AI Crawler Shield

Quinrel introduces a 3-tier crawler management model integrated into your dynamic `robots.txt` configuration:

### Tier 1: Traditional Search Crawlers
* **Bots:** `Googlebot`, `Bingbot`, `YandexBot`, `DuckDuckBot`.
* **Policy:** Always allowed to index web pages, images, and sitemaps for standard organic web search.

### Tier 2: Search & Citation Crawlers
* **Bots:** `PerplexityBot`, `SearchGPT`, `BingPreview`.
* **Purpose:** These systems browse the web in real time to fetch factual citations for user search prompts.
* **Control:** Configurable under **Quinrel > AI Visibility > Crawler Permissions**. Allowing these bots enables your content to be cited as an authoritative source in conversational search answers.

### Tier 3: Model Training & Scraping Bots
* **Bots:** `GPTBot`, `CCBot` (Common Crawl), `Bytespider` (ByteDance/TikTok), `ClaudeBot`, `Anthropic-AI`, `cohere-ai`, `Diffbot`.
* **Purpose:** Bulk crawling to train commercial foundation models without necessarily driving direct referral traffic.
* **Control:** You can selectively block Tier 3 scrapers with a single click. When blocked, Quinrel adds standard `Disallow: /` directives for each targeted user-agent in your virtual `robots.txt`.

---

## 3. Training Opt-Out Headers (`noai`, `noimageai`)

In addition to `robots.txt` rules, Quinrel provides machine-readable HTTP response headers and `<meta>` tags to assert your copyright preferences:

* **HTTP Header:**
  ```http
  X-Robots-Tag: noai, noimageai
  ```
* **HTML Head Meta Tag:**
  ```html
  <meta name="robots" content="noai, noimageai" />
  ```

### Why Use Both?
While `robots.txt` governs crawler access before a request is fetched, `X-Robots-Tag` and `<meta>` directives attach directly to individual files (including PDFs and images) and persist across CDNs and caching layers. This provides legal and technical evidence of training opt-out under jurisdictions recognizing machine-readable reservations of rights (e.g., EU Copyright Directive Article 4).

---

## 4. AI Content Disclosures & Schema Authorship

Transparency regarding AI involvement in content generation is increasingly valued by search engines and human audiences alike. Quinrel allows you to disclose content generation methodology per post under the **Advanced** tab in the editor:

* **Human Authored:** 100% created and verified by human authors.
* **AI-Assisted (Human Reviewed):** Researched or drafted with AI tooling, edited and fact-checked by human editorial staff.
* **AI-Generated:** Automated content produced via algorithmic pipelines with human oversight.

These selections inject corresponding structured data attributes into your JSON-LD entity graph, differentiating human expertise (`author`) from algorithmic generation tools.

