# Rich Snippets & Schema Catalog

Quinrel features a Schema.org JSON-LD graph generator that produces interconnected knowledge graphs instead of disconnected JSON chunks. All schemas conform strictly to Google Search Central specifications.

---

## 1. Graph Architecture & Interlinking

Every page rendered by Quinrel outputs a single `<script type="application/ld+json">` containing an interconnected `@graph` array:

```
[WebSite] <──isPartOf── [WebPage] <──mainEntity── [Article / Product / Event]
                              │
                              └──breadcrumb── [BreadcrumbList]
```

* **Deterministic Node IDs:** Each entity receives a stable `@id` URI (e.g., `https://example.com/#website`, `https://example.com/sample-post/#article`).
* **Author & Publisher Association:** Author nodes reference the global Person or Organization entity without duplicate inline declarations.

---

## 2. Complete Catalog of 26 Supported Schema Types

Below is the complete reference of schema types configurable in the Quinrel Block Editor and Settings.

### Editorial & Content Schemas
1. **Article:** Standard editorial articles, guides, and long-form publications.
2. **NewsArticle:** Structured with `dateline`, `printEdition`, and publisher guidelines for Google News inclusion.
3. **BlogPosting:** Casual or diary-style blog updates.
4. **BreadcrumbList:** Automatically generated hierarchy reflecting site taxonomy and custom post parentage.
5. **Person:** Detailed author entity with bio, job title, credentials, and social profile links (`sameAs`).

### Media & Entertainment Schemas
6. **PodcastEpisode:** Deep podcast episode metadata including `duration`, `episodeNumber`, audio enclosure URL, and parent series binding.
7. **PodcastSeries:** Series-level schema defining podcast title, cover artwork, feed URL, host person/organization, and language.
8. **AudioObject:** General audio content schema with bitrate, file size, encoding format, and audio stream URL. Automatically detected from Gutenberg `core/audio` blocks.
9. **VideoObject:** Video structured data with thumbnail URL, upload date, duration, embed URL, and interaction statistics.
10. **MusicRecording:** Audio track data including artist, duration, album name, and ISRC identifier.
11. **Movie:** Feature film or documentary data including director, actors, duration, and production company.

### Commercial & E-Commerce Schemas
12. **Product:** Fully featured e-commerce markup supporting GTIN, MPN, ISBN, Brand, SKU, Price, Currency, Price Valid Until, and Stock Status. (Automated when WooCommerce is active).
13. **LocalBusiness:** Physical business locations including street address, geo-coordinates, telephone, opening hours, and accepted payment methods.
14. **Restaurant:** Specialized food establishment schema adding `servesCuisine`, menu URL, and reservations URL.
15. **Service:** B2B or B2C service offerings including provider, service area, and terms of service.
16. **Review:** Independent critic or editorial review with item reviewed, reviewer name, rating scale, and rating value.

### Practical & Informational Schemas
17. **FAQPage:** Collapsible question-and-answer pairs eligible for expanded Google SERP accordions.
18. **HowTo:** Step-by-step instructions with preparation steps, required tools, supply lists, and individual step images.
19. **Recipe:** Culinary recipes with prep time, cook time, ingredients list, nutritional calories, yields, and step-by-step instructions.
20. **Course:** Educational courses, syllabus modules, educational credential awards, and provider information.
21. **JobPosting:** Career opportunities with hiring organization, job location, salary ranges, employment type (Full-Time, Remote), and valid through date.
22. **Book:** Literary publications with author, ISBN, format (EBook, Hardcover), and publishing date.
23. **SoftwareApplication:** Desktop, mobile, or web software with operating system requirements, application category, and download URL.
24. **Event:** In-person or virtual events with start/end date, attendance mode, venue location, and ticket offer details.
25. **Dataset:** Scientific, statistical, or public datasets with license terms, spatial/temporal coverage, and download distribution links.
26. **Custom:** Raw, validated JSON-LD entry for edge cases or proprietary schema extensions.

---

## 3. Audio & Video Auto-Detection

Quinrel automatically scans post content when saving in Gutenberg:
* **Gutenberg `core/audio` Blocks:** Extracts audio file URL, title, and MIME type directly from the block attributes and embeds a corresponding `AudioObject` or `PodcastEpisode` schema.
* **HTML5 `<audio>` tags & Shortcodes:** Parses raw `<audio src="...">` and `[audio]` shortcode enclosures.
* **Gutenberg `core/video` & YouTube Embeds:** Detects embedded YouTube, Vimeo, and local video blocks, prompting the editor to confirm thumbnail and duration metadata.

---

## 4. Rich Results Health Scoring in the Editor

The Quinrel schema panel includes an integrated **Rich Results Validator**:
* **Required Properties:** Flags missing Google-mandated fields (e.g., missing `image` on an Article, or missing `offers` on a Product).
* **Recommended Properties:** Highlights optional fields that improve SERP presentation (e.g., `aggregateRating`, `nutrition` on Recipes).
* **Live JSON-LD Code Inspector:** Displays the exact rendered JSON-LD output in real time so you can copy and test it directly in the [Google Rich Results Test](https://search.google.com/test/rich-results).

