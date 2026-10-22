# Getting Started & Setup Guide

This guide walks you through activating, configuring, and verifying **Quinrel** on your WordPress site. Quinrel provides modular, high-performance SEO infrastructure with zero frontend database overhead on ordinary visitor page loads.

---

## 1. Prerequisites & System Requirements

Before activating Quinrel, verify your server and WordPress environment meet the minimum requirements:

* **PHP:** Version 8.2 or higher.
* **WordPress:** Version 6.4 or higher.
* **Database:** MySQL 5.7+ or MariaDB 10.4+.
* **REST API:** Active and accessible at `/wp-json/quinrel/v1/`.
* **User Capability:** An active account with the `manage_options` capability (typically Administrator).

---

## 2. Initial Setup Workflow

When you first activate Quinrel under **Plugins > Installed Plugins**, Quinrel registers its management screens under the top-level **Quinrel** admin menu.

```
Quinrel
├── Dashboard
├── Technical SEO
├── Schema Markup
├── XML Sitemaps
├── Redirects
├── AI Visibility
├── Link Health
├── Outreach CRM
└── Settings
```

### Step 1: Site Representation & Entity Schema
1. Navigate to **Quinrel > Settings > General**.
2. Select whether this site represents an **Organization** or a **Person**:
   * **Organization:** Enter your legal Organization Name, Alternate Name, Legal Registration Number, and upload your Organization Logo (minimum 512×512px SVG or PNG).
   * **Person:** Select the WordPress user who represents the site author, or input a custom display name and profile image.
3. Add your official social profiles (e.g., YouTube, X/Twitter, LinkedIn, GitHub). These URLs populate the `sameAs` array in your global JSON-LD knowledge graph.

### Step 2: Search Engine Visibility Defaults
1. Under **Quinrel > Settings > Content Types**, configure indexing rules per post type:
   * **Posts:** Index (`index, follow`), include in XML Sitemaps.
   * **Pages:** Index (`index, follow`), include in XML Sitemaps.
   * **Custom Post Types:** Toggle indexing and schema defaults per CPT (e.g., Products, Portfolios, Knowledge Base).
2. Configure **Taxonomies**:
   * Categories: Index or Noindex depending on your site architecture.
   * Tags: By default, Quinrel recommends setting thin tag archives to `noindex, follow` to prevent duplicate snippet dilution.

### Step 3: Importing from Yoast SEO, Rank Math, or All in One SEO
If migrating from an existing SEO plugin, Quinrel provides a non-destructive 1-click importer:
1. Navigate to **Quinrel > Settings > Tools & Migration**.
2. Quinrel automatically detects legacy metadata in your database:
   * **Yoast SEO:** `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_canonical`, `_yoast_wpseo_meta-robots-noindex`.
   * **Rank Math:** `rank_math_title`, `rank_math_description`, `rank_math_canonical_url`, `rank_math_robots`.
   * **All in One SEO (AIOSEO):** `_aioseo_title`, `_aioseo_description`, `_aioseo_canonical_url`.
3. Click **Import Meta Data**. Quinrel transforms and saves the data into `_quinrel_*` post meta fields in memory-bounded batches of 100 rows.
4. **Safety Guarantee:** Quinrel never deletes original competitor meta records during migration. If you ever need to roll back, your previous plugin data remains 100% intact.
5. Once imported, deactivate the previous SEO plugin under **Plugins > Installed Plugins** to avoid duplicate OpenGraph and meta title generation.

---

## 3. Verifying the Installation

After completing initial setup, verify that output tags and REST endpoints operate as expected:

### 1. Source Inspection
View any published post or page in an incognito browser window and inspect the `<head>` markup:
```html
<!-- Quinrel SEO Markup -->
<title>Your Article Title | Your Site Name</title>
<meta name="description" content="Your target snippet description here." />
<link rel="canonical" href="https://example.com/your-post/" />
<meta property="og:title" content="Your Article Title" />
<meta property="og:description" content="Your target snippet description here." />
<meta property="og:url" content="https://example.com/your-post/" />
<meta property="og:site_name" content="Your Site Name" />
<meta name="twitter:card" content="summary_large_image" />
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [ ... ]
}
</script>
```

### 2. REST API Verification
Ensure the Quinrel settings endpoint responds correctly:
```bash
curl -i https://example.com/wp-json/quinrel/v1/settings
```
Expected response: HTTP `401 Unauthorized` or `403 Forbidden` for unauthenticated requests, confirming endpoint registration and security boundary enforcement.

---

## 4. Multi-Environment & Staging Considerations

* **Local Environments (e.g., LocalWP, XAMPP, Docker):**
  Quinrel runs fully offline. Outbound API pings (such as IndexNow or Google Indexing API) remain silent until production credentials are submitted and valid public domain resolution is detected.
* **Staging Sites (`staging.*` or password-protected):**
  If `get_option('blog_public')` is set to `0` (Search Engine Discouraged), Quinrel automatically injects `<meta name="robots" content="noindex, nofollow" />` globally regardless of individual post overrides.

