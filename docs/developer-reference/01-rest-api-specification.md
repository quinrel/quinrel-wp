# REST API Specification

Quinrel exposes a comprehensive REST API under the namespace `quinrel/v1`. The plugin operates on a decoupled architecture where the WordPress admin interface communicates strictly via these endpoints.

---

## 1. Authentication & Security

Quinrel strictly adheres to WordPress REST API authentication standards:

### Internal Requests (WordPress Admin React SPA)
* Utilizes standard WordPress session cookies and the `X-WP-Nonce` header.
* Nonce verification is handled automatically by the WordPress API client (`apiFetch`).

### Headless & External Clients
* Use WordPress Application Passwords via Basic Authentication:
  ```http
  Authorization: Basic base64(username:application_password)
  ```
* Alternatively, standard OAuth 2.0 or JWT Bearer tokens issued by compatible WordPress authentication plugins are supported.

### Capabilities
Unless explicitly designated as a **Public Endpoint**, routes require explicit user capabilities:
* `manage_options` for global plugin settings, database maintenance, migrations, and crawler shields.
* `edit_posts` / `edit_published_posts` for post-level meta reading, writing, and custom fields discovery.

---

## 2. API Response & Error Conventions

### Standard Success Response:
```json
{
  "success": true,
  "data": { ... }
}
```

### Standard Error Response (`WP_Error`):
```json
{
  "code": "quinrel_rest_forbidden",
  "message": "You are not permitted to manage SEO configuration.",
  "data": {
    "status": 403
  }
}
```

---

## 3. Core Route Reference

### A. Headless Content & Graph
Exposes computed SEO graph and meta tags for frontend frameworks (Next.js, Nuxt, Astro).

#### `GET /wp-json/quinrel/v1/headless/post/(?P<id>\d+)`
* **Access:** Public (no authentication required)
* **Response `200 OK`:**
```json
{
  "id": 142,
  "title": "Complete Guide to Modern WordPress SEO | Acme Corp",
  "meta_description": "Learn how to optimize your decoupled WordPress architecture.",
  "canonical_url": "https://example.com/modern-wordpress-seo/",
  "robots": {
    "index": true,
    "follow": true,
    "max_snippet": -1,
    "max_image_preview": "large",
    "noai": true,
    "noimageai": true
  },
  "opengraph": {
    "title": "Complete Guide to Modern WordPress SEO",
    "description": "Learn how to optimize your decoupled WordPress architecture.",
    "type": "article",
    "url": "https://example.com/modern-wordpress-seo/",
    "site_name": "Acme Corp",
    "image": "https://example.com/wp-content/uploads/2026/10/hero.png"
  },
  "twitter": {
    "card": "summary_large_image",
    "title": "Complete Guide to Modern WordPress SEO",
    "image": "https://example.com/wp-content/uploads/2026/10/hero.png"
  },
  "schema": {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "@id": "https://example.com/modern-wordpress-seo/#webpage"
      },
      {
        "@type": "Article",
        "@id": "https://example.com/modern-wordpress-seo/#article",
        "headline": "Complete Guide to Modern WordPress SEO"
      }
    ]
  }
}
```

---

### B. Post-Level SEO Overrides

#### `GET /wp-json/quinrel/v1/seo/(?P<id>\d+)`
* **Access:** Requires `edit_post` capability.
* **Returns:** Post-specific meta overrides (`_quinrel_meta_title`, `_quinrel_meta_description`, `_quinrel_schema_type`, focus keywords, and custom field tracking selections).

#### `PUT /wp-json/quinrel/v1/seo/(?P<id>\d+)`
* **Access:** Requires `edit_post` capability.
* **Payload:**
```json
{
  "title": "New Optimized Meta Title",
  "description": "Updated meta description within bounds.",
  "focus_keyword": "wordpress seo",
  "secondary_keywords": ["headless seo", "jamstack"],
  "canonical_url": "https://example.com/custom-canonical/",
  "schema_type": "PodcastEpisode",
  "cornerstone": true,
  "noindex": false,
  "nofollow": false,
  "noai": true
}
```

---

### C. AI Visibility & Crawler Shield

#### `GET /wp-json/quinrel/v1/ai/llms-txt`
* **Access:** Requires `manage_options`.
* **Returns:** Active configuration for `/llms.txt` generation (included post types, site bio, cornerstone inclusion).

#### `POST /wp-json/quinrel/v1/ai/llms-txt`
* **Access:** Requires `manage_options`.
* **Payload:**
```json
{
  "enabled": true,
  "site_bio": "Acme Corp produces peer-reviewed engineering analyses.",
  "post_types": ["post", "page", "case_study"],
  "include_cornerstones": true
}
```

#### `POST /wp-json/quinrel/v1/ai/crawler-shield`
* **Access:** Requires `manage_options`.
* **Payload:**
```json
{
  "block_training_crawlers": true,
  "blocked_agents": ["GPTBot", "CCBot", "Bytespider", "ClaudeBot", "Anthropic-AI"],
  "allow_search_crawlers": true
}
```

---

### D. Link Building & Outreach CRM

#### `GET /wp-json/quinrel/v1/outreach/prospects`
* **Access:** Requires `manage_options` or `edit_posts`.
* **Returns:** Array of all link prospecting records with domain, target URL, stage, and contact details.

#### `POST /wp-json/quinrel/v1/outreach/prospects`
* **Access:** Requires `manage_options` or `edit_posts`.
* **Payload:**
```json
{
  "domain": "techcrunch.com",
  "target_url": "https://example.com/my-study/",
  "contact_name": "Editor",
  "contact_email": "press@techcrunch.com",
  "pitch_type": "guest_post",
  "stage": "pitched",
  "da_tier": "tier_1",
  "notes": "Sent introductory data brief."
}
```

#### `GET /wp-json/quinrel/v1/outreach/broken-backlinks`
* **Access:** Requires `manage_options` or `edit_posts`.
* **Returns:** Top 404 requests originating from external referrer hosts for link reclamation.

#### `GET /wp-json/quinrel/v1/outreach/anchor-diversity`
* **Access:** Requires `manage_options` or `edit_posts`.
* **Parameters:** `?post_id=142`
* **Returns:** Internal and external anchor text breakdown, exact-match ratios, and penalty risk scoring.

---

### E. Redirects & 404 Log

#### `GET /wp-json/quinrel/v1/redirects`
* **Access:** Requires `manage_options`.
* **Parameters:** `?page=1&per_page=50&search=blog`
* **Returns:** Paginated list of active redirect rules.

#### `POST /wp-json/quinrel/v1/redirects`
* **Access:** Requires `manage_options`.
* **Payload:**
```json
{
  "source_url": "/old-path/",
  "target_url": "/new-path/",
  "status_code": 301,
  "is_regex": false
}
```

#### `DELETE /wp-json/quinrel/v1/redirects/(?P<id>\d+)`
* **Access:** Requires `manage_options`.
* **Deletes:** Specified redirect rule.

---

### F. Instant Indexing

#### `POST /wp-json/quinrel/v1/indexing/indexnow`
* **Access:** Requires `manage_options`.
* **Payload:**
```json
{
  "urls": ["https://example.com/new-article/"]
}
```
* **Returns:** API response code from Microsoft Bing / IndexNow gateway.

