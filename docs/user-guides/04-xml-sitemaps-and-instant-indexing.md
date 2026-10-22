# XML Sitemaps & Instant Indexing

Quinrel includes a scalable XML Sitemap engine and instant search engine notification via **IndexNow** and the **Google Indexing API**.

---

## 1. XML Sitemaps Architecture

Quinrel generates standards-compliant XML sitemaps dynamically without writing static files to your server disk, ensuring instant updates when content changes.

### Sitemap Index Structure:
Your main sitemap is located at:
`https://example.com/sitemap.xml` (or `https://example.com/sitemap_index.xml`)

The index references dedicated sub-sitemaps:
* `post-sitemap.xml` — Blog posts, split into pages of 1,000 URLs if your catalog exceeds threshold.
* `page-sitemap.xml` — Static pages.
* `{cpt}-sitemap.xml` — Any public custom post types (e.g., `product-sitemap.xml`).
* `category-sitemap.xml` — Category archive landing pages.
* `author-sitemap.xml` — Public author archive pages (disabled by default if author archives are thin).

### Automatic Exclusions:
* Posts or pages marked as `noindex` are automatically purged from all sitemaps.
* Password-protected or private posts are excluded.
* Redirected URLs are excluded.
* You can manually exclude individual post IDs under **Quinrel > XML Sitemaps > Excluded Posts**.

### Image Sitemap Extension:
Quinrel enriches sitemap entries with Google Image tags:
```xml
<url>
  <loc>https://example.com/sample-article/</loc>
  <lastmod>2026-10-22T10:00:00+00:00</lastmod>
  <image:image>
    <image:loc>https://example.com/wp-content/uploads/2026/10/featured.jpg</image:loc>
    <image:title>Sample Article Featured Graphic</image:title>
  </image:image>
</url>
```

---

## 2. IndexNow Instant Indexing

IndexNow allows websites to instantly notify participating search engines (including Microsoft Bing, Yandex, Seznam, and Naver) whenever content is created, updated, or deleted.

### Configuring IndexNow:
1. Navigate to **Quinrel > XML Sitemaps > IndexNow**.
2. Click **Generate IndexNow Key**.
3. Quinrel generates an RFC 4122 UUID key and automatically handles key verification at `https://example.com/{your-key}.txt`.
4. Enable **Automatic Submissions**.
5. When any post, page, or custom post type is published, edited, or trashed, Quinrel sends an asynchronous HTTP POST payload containing the modified URL to the IndexNow gateway.
6. View the **IndexNow Submission Log** to inspect HTTP status codes and timestamps for all recent pings.

---

## 3. Google Indexing API Setup

The Google Indexing API allows site owners to directly notify Google when pages are added or removed:

### Step-by-Step Configuration:
1. **Google Cloud Console:** Create a project and enable the **Web Search Indexing API**.
2. **Service Account:** Create a Service Account, generate a JSON Key, and download it.
3. **Google Search Console:** In your verified domain property, navigate to **Settings > Users and permissions** and add the service account email as an **Owner**.
4. **Quinrel Integration:** Under **Quinrel > XML Sitemaps > Google Indexing**, paste the contents of your downloaded Service Account JSON key.
5. Save settings. Quinrel validates the OAuth 2.0 token handshake with Google's authentication servers.

### Supported Events:
* `URL_UPDATED`: Dispatched immediately when content is published or updated.
* `URL_DELETED`: Dispatched immediately when content is moved to trash or deleted.

---

## 4. Human-Readable HTML Sitemap

For visitors and accessibility navigation, Quinrel provides an HTML sitemap:

* **Shortcode:** Insert `[quinrel_html_sitemap]` into any WordPress page.
* **Attributes:**
  * `[quinrel_html_sitemap post_types="page,post" columns="2" show_dates="true"]`
* **Features:**
  * Semantic HTML nested unordered lists (`<ul>`, `<li>`).
  * Respects `noindex` exclusions.
  * Alphabetical or date-based ordering.

