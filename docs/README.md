# Quinrel Documentation Hub

Welcome to the official documentation for **Quinrel**, engineered by **Skoorite Team**.

Quinrel is a high-performance WordPress SEO and AI search visibility plugin. Operating via a modern, decoupled React administration interface and clean REST endpoints, Quinrel eliminates frontend database bloat and delivers precision search engine optimization and AI crawler governance.

---

## Documentation Directory

### 1. User Guides (`docs/user-guides/`)
* [01. Getting Started & Setup Guide](user-guides/01-getting-started-setup-wizard.md) — Prerequisites, initial onboarding, entity schema, and multi-environment setup.
* [02. Meta Tags, Snippet Optimization & Content Scoring](user-guides/02-meta-tags-and-content-scoring.md) — Focus keywords, morphological stemming, readability, and cornerstone strategy.
* [03. Rich Snippets & Schema Catalog](user-guides/03-rich-snippets-and-schema-catalog.md) — Comprehensive reference for all 26 supported Schema.org structured data types.
* [04. XML Sitemaps & Instant Indexing](user-guides/04-xml-sitemaps-and-instant-indexing.md) — Memory-bounded XML sitemaps, IndexNow integration, and Google Indexing API.
* [05. Redirects Manager & 404 Monitoring](user-guides/05-redirects-and-404-monitoring.md) — 301/302/410 management, regex patterns, auto-healing permalink changes, and 404 logs.
* [06. AI Visibility, llms.txt & Crawler Shield](user-guides/06-ai-visibility-llms-txt-and-bot-shield.md) — Machine-readable `/llms.txt`, 3-tier crawler shield, and AI training opt-outs.

### 2. Developer Reference (`docs/developer-reference/`)
* [01. REST API Specification](developer-reference/01-rest-api-specification.md) — Comprehensive route documentation, payload schemas, and response contracts.
* [02. Actions & Filters Reference](developer-reference/02-actions-and-filters-hooks.md) — WordPress hooks for modifying titles, descriptions, schema graphs, and custom fields.
* [03. Headless WordPress Integration](developer-reference/03-headless-wordpress-integration.md) — Implementation recipes for Next.js 14+ (App Router), Nuxt 3, and Astro.

### 3. Integration & Migration Playbooks (`docs/integrations/`)
* [WooCommerce SEO Playbook](integrations/woocommerce-setup.md) — Google Merchant Center identifiers (GTIN, MPN, Brand), return policy schema, and clean category permalinks.
* [Advanced Custom Fields (ACF) Playbook](integrations/acf-custom-fields-analysis.md) — Aggregating ACF Repeaters, Flexible Content, and post meta into SEO scoring.
* [1-Click Migration Playbook](integrations/1-click-migration-yoast-rankmath-aioseo.md) — Safely migrating metadata from Yoast SEO, Rank Math, and All in One SEO.

### 4. Privacy & Compliance (`docs/compliance/`)
* [CCPA, GPC & AI Governance Playbook](compliance/ccpa-gpc-and-ai-governance.md) — Global Privacy Control (`Sec-GPC: 1`), GDPR tools, and machine-readable training opt-out headers (`noai`, `noimageai`).

---

## Operations & Architecture Reference
* [Features Overview](features.md)
* [Third-Party Services Policy](third-party-services.md)
* [Support Runbook](support-runbook.md)
* [Competitive Backlog & Strategic Roadmap](competitive-backlog-and-strategic-roadmap.md)
* [Google Search Console connect](google-search-console-connect.md) — held until Quinrel’s Google OAuth app is verified. Read this before changing the Connect button.
* [IndexNow and Bing Webmaster](indexnow-and-bing-webmaster.md) — production tasks, acceptance checks, and QA. Read this before changing either feature.
