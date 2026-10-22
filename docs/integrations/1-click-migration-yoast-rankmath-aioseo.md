# 1-Click Migration Playbook: Yoast, Rank Math & AIOSEO

Switching your WordPress SEO plugin should never risk your search rankings, traffic, or metadata. Quinrel includes a non-destructive 1-click migration engine that extracts and converts existing data from Yoast SEO, Rank Math, and All in One SEO (AIOSEO).

---

## 1. Pre-Migration Checklist

Before initiating migration on a production site:

1. **Perform a Database Backup:**
   * Take a full database backup via WP-CLI:
     ```bash
     wp db export pre-quinrel-migration.sql
     ```
   * Or utilize your web host's snapshot backup tool.
2. **Leave Previous Plugins Active (Temporarily):**
   * Keep your current SEO plugin installed and active while performing the import so Quinrel can verify database table structures and options.
3. **Check PHP Memory Limit:**
   * Ensure `WP_MEMORY_LIMIT` is set to at least `128M` (recommended: `256M`).

---

## 2. Supported Data Mapping Reference

Quinrel safely converts the following metadata structures:

| Field Type | Yoast SEO Field | Rank Math Field | AIOSEO Field | Quinrel Destination |
| :--- | :--- | :--- | :--- | :--- |
| **SEO Title** | `_yoast_wpseo_title` | `rank_math_title` | `_aioseo_title` | `_quinrel_meta_title` |
| **Meta Description** | `_yoast_wpseo_metadesc` | `rank_math_description` | `_aioseo_description` | `_quinrel_meta_description` |
| **Canonical URL** | `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `_aioseo_canonical_url` | `_quinrel_canonical_url` |
| **Focus Keyword** | `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `_aioseo_keywords` | `_quinrel_focus_keyword` |
| **Robots Noindex** | `_yoast_wpseo_meta-robots-noindex` | `rank_math_robots` | `_aioseo_noindex` | `_quinrel_robots_noindex` |
| **Robots Nofollow** | `_yoast_wpseo_meta-robots-nofollow` | `rank_math_robots` | `_aioseo_nofollow` | `_quinrel_robots_nofollow` |
| **OpenGraph Title** | `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `_aioseo_og_title` | `_quinrel_og_title` |
| **OpenGraph Image** | `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `_aioseo_og_image` | `_quinrel_og_image` |
| **Twitter Card** | `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `_aioseo_twitter_title` | `_quinrel_twitter_title` |

---

## 3. Step-by-Step Migration Workflow

1. Navigate to **Quinrel > Settings > Tools & Migration**.
2. **Automatic Detection:** The dashboard identifies detected legacy records in your database (e.g., "Found 1,420 Yoast SEO records").
3. Select which data categories to import:
   * **Post & Page Metadata:** Titles, descriptions, focus keywords, canonicals.
   * **Term & Category Metadata:** Category and tag archive descriptions and titles.
   * **Redirect Rules:** Import 301/302 rules from Rank Math Redirects or Redirection plugin tables.
4. Click **Start Migration**.
5. **Batch Processing:** Quinrel runs the migration in asynchronous chunks of 100 posts per request via the REST API. A live progress bar displays completion percentage without timing out or consuming excessive PHP memory.
6. Once complete, a success summary report details the number of records converted.

---

## 4. The Non-Destructive Guarantee

* **Zero Deletion Policy:** Quinrel **never** deletes, truncates, or overwrites the original competitor metadata in your database.
* **Instant Rollback Safety:** If you ever decide to deactivate Quinrel, simply reactivate your previous SEO plugin—all original metadata remains unaltered.

---

## 5. Post-Migration Verification & Clean-Up

### Step 1: Headless / Frontend Audit
Inspect 5 representative URLs across different content formats (e.g., your homepage, a standard blog post, an e-commerce product, and a category archive):
* Right-click and choose **View Page Source**.
* Search for `<title>` and `<meta name="description">` to verify your customized snippets display accurately.
* Confirm that canonical tags point to the expected URL structure.

### Step 2: Deactivate Previous SEO Plugin
Navigate to **Plugins > Installed Plugins** and **Deactivate** Yoast, Rank Math, or AIOSEO.
* *Do not click "Delete" yet* until you have observed site performance and SERP behavior for 7–14 days.
* Deactivating prevents duplicate `<title>` or `<meta name="robots">` tags from colliding in the HTML output.

### Step 3: Resubmit Sitemaps (If Changed)
If your sitemap URL changed from a custom path to Quinrel's `/sitemap.xml`:
* Log into **Google Search Console** and **Bing Webmaster Tools**.
* Submit `https://example.com/sitemap.xml`.

