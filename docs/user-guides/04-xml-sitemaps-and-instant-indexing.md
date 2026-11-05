# Sitemaps and IndexNow

A new URL is easy to miss when the only way to find it is by following links. The sitemap is the list you publish on purpose. IndexNow tells participating engines that a URL changed. It does not guarantee indexing.

## XML Sitemaps

Quinrel's sitemap stays off until an administrator turns it on. Turn it on from XML Sitemaps when you want a list of public URLs for search engines.

When it is on:

- The sitemap index is `/sitemap.xml`.
- The news sitemap is `/sitemap-news.xml` and `/news-sitemap.xml`.
- The video sitemap is `/video-sitemap.xml`. Each further set of 500 posts is `/video-sitemap-2.xml`, then 3, and so on. A video duration is included only when the page states one.
- `[quinrel_sitemap]` prints an HTML Sitemap.

Posts you mark noindex are left out. The address on the dashboard is the one to submit.

## IndexNow

IndexNow sends published URLs to https://api.indexnow.org/indexnow only after an administrator saves a key and turns notifications on. The same notice can go to https://www.bing.com, https://yandex.com, and https://search.seznam.cz. IndexNow does not guarantee indexing.

The Google Indexing API is separate. It is optional, it applies to an eligible JobPosting or livestream page, and it does not guarantee indexing.

A normal page view does not call either service. Nothing is sent until an administrator turns the feature on.
