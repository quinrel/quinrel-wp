# Redirects Manager & 404 Monitoring

Quinrel features a high-performance redirect router and 404 error monitor designed to preserve link equity and eliminate crawl errors.

---

## 1. Supported Redirect Status Codes

When creating redirect rules, choose the appropriate HTTP status code:

| Status Code | Type | Search Engine Interpretation |
| :--- | :--- | :--- |
| `301` | Moved Permanently | Fully transfers page rank and link equity to the target URL. Default choice. |
| `302` | Found (Temporary) | Temporary redirection. Search engines keep the original URL indexed. |
| `307` | Temporary Redirect | Preserves request HTTP method (e.g., POST payloads). |
| `410` | Gone | Explicitly informs search engines the content has been permanently removed. Speeds up de-indexing of deleted pages. |
| `451` | Unavailable For Legal Reasons | Content withheld due to copyright, court order, or legal takedown. |

---

## 2. Creating Redirect Rules

Navigate to **Quinrel > Redirects > Add New**:

### Exact Match
Redirect a specific URL path to a new destination:
* **Source URL:** `/old-landing-page/`
* **Target URL:** `/new-landing-page/`
* **Type:** `301 Moved Permanently`

### Regular Expression (Regex) Matching
For pattern-based redirects across multiple URLs:
* Toggle **Regex Mode: On**.
* **Source Pattern:** `^/blog/2023/(.*)$`
* **Target Pattern:** `/articles/$1`
* Quinrel validates regex syntax prior to saving to prevent catastrophic backtracking or server errors.

### Query Parameter Preservation
* Toggle **Pass Query Parameters** to pass through UTM tracking codes, affiliate IDs, or custom GET parameters to the destination URL automatically.

---

## 3. Automatic Permalink Change Detection

When an author or editor changes the URL slug of an existing published post or page:
1. WordPress saves the new slug.
2. Quinrel intercepts the update, detects the permalink alteration, and checks if a redirect already exists.
3. If no rule exists, Quinrel automatically creates a `301 Moved Permanently` rule pointing the old URL to the new URL.
4. An admin notification alerts the editor that a 301 redirect was created to safeguard incoming backlinks.

---

## 4. 404 Error Log & Auto-Healing

Quinrel captures 404 errors as they occur without degrading database performance:

* **Bounded Retention:** The 404 log automatically caps stored rows (default 1,000 entries) and aggregates duplicate hits by incrementing a `hits` counter rather than creating duplicate rows.
* **External Referrer Tracking:** Quinrel captures the `HTTP_REFERER` header so you can identify broken backlinks coming from external websites.
* **1-Click Redirection:** Click **Redirect** beside any logged 404 to immediately pre-fill a 301 redirect modal pointing to a live page.
* **1-Click 410 Gone:** Mark junk crawler probes (e.g., `/wp-login.php`, `/.env`) as `410 Gone` to instruct automated bots to cease requesting them.
* **Exclusion Filters:** Ignore standard bot probing paths (e.g., `.well-known`, `favicon.ico`) to keep your log actionable.

---

## 5. CSV Import & Export

Migrate or bulk-edit redirect tables with ease:
* **Export:** Download all active redirects as a standard CSV format (`id, source_url, target_url, status_code, is_regex, created_at`).
* **Import:** Upload CSV spreadsheets from Nginx, Apache `.htaccess`, or third-party redirection plugins. Quinrel validates each row before committing to the database.

