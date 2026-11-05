# Quinrel features

A careful WordPress site can still be hard to find. The theme prints a title you did not write. A redesigned URL returns 404. Two posts answer the same query and split the clicks. The picture that should appear in image search has no alternative text. Search performance lives in another Google tab, so a query can slip for weeks before anyone on the site notices. A share on LinkedIn or Facebook shows a blank card. An AI crawler requests the site, and the only trace is a line in a log nobody reads.

Visibility & Discovery Intelligence for WordPress, with technical SEO included.

Quinrel is the place in WordPress for the on-site work that decides whether a page can be discovered: what the page claims to be, which URL is canonical, whether a crawler may request it, and whether the old address still resolves. Technical SEO works with no Quinrel account. A Quinrel account is optional.

Open Quinrel after activation. The dashboard shows whether search engines are discouraged, the sitemap address, missing titles, images with no alternative text, redirects, and not-found requests. Those counts come from this site. The setup wizard can store a site type and profile URLs. The local tools work before you finish the wizard.

On its own, Quinrel covers titles and meta descriptions, social cards, Schema Markup, XML Sitemaps, the HTML Sitemap, redirects, Image SEO, internal links, IndexNow, and a Technical SEO Audit of this site. Google Search Console, Google Analytics 4, and Bing Webmaster traffic sit next to that work after you connect them. The Keyword Rank Tracker reads query movement from Search Console on this site.

Connecting a Quinrel account can bring intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. When the account is connected, the writing assistant and content gap analysis can be sent to that API, and the screen shows what the API returns. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host. It does not score AI mentions, Google presence, or competitors. The plugin does not invent those results on the site.

The names below are the same names used in `readme.txt` and the admin screens.

## Editor

Search results and social cards use the title and description saved on the post. In the post editor you can set the SEO title and meta description, read a search-result preview, and check readability before you update the post. Readability uses Flesch Reading Ease. The same panel covers:

- SEO titles and meta descriptions
- Readability
- A search-result preview
- Social metadata for Facebook, X, LinkedIn, and Pinterest
- Canonical URLs and robots meta
- FAQ schema
- Internal link suggestions
- A notice when cornerstone content has not been updated for 180 days
- PageSpeed for the post you are editing

The writing assistant uses an OpenAI, Anthropic, or Ollama key saved on this site. A remote Ollama host receives the text you submit. It runs when an editor asks it to run.

## Schema Markup

A page with only a title is easy to treat as a generic article. Schema Markup tells a search engine what a page is. It can describe a page as Article, NewsArticle, WebPage, BreadcrumbList, FAQ, HowTo, LocalBusiness, Recipe, Event, Product, VideoObject, Person, or Organization. The schema screen also has templates for BlogPosting, PodcastEpisode, Course, JobPosting, Book, SoftwareApplication, Restaurant, Service, Review, Movie, MusicRecording, and Dataset. You can also add one custom JSON-LD node, or import JSON-LD from a public page. Quinrel does not fetch a private or local address, and it does not run that page's JavaScript. Breadcrumb Schema creates nested BreadcrumbList structured data automatically based on post hierarchy and taxonomy terms.

When WooCommerce is active, product schema can include an aggregate rating if that product has reviews. Structured data does not guarantee a rich result.

## XML Sitemaps and the HTML Sitemap

A crawler finds new URLs faster when the site publishes the list. Quinrel's sitemap stays off until an administrator turns it on. When it is on:

- The sitemap index is `/sitemap.xml`.
- The news sitemap is `/sitemap-news.xml` and `/news-sitemap.xml`.
- The video sitemap is `/video-sitemap.xml`. Each further set of 500 posts is `/video-sitemap-2.xml`, then 3, and so on. A video duration is included only when the page states one.
- `[quinrel_sitemap]` prints an HTML Sitemap.

## Technical SEO

People and crawlers keep requesting an address after the page has moved. The Technical SEO screen includes the Redirects Manager. A redirect can be 301, 302, 307, 410, or 451, including a regular expression, and you can import rules from CSV. The 404 log records missing URLs so you can decide which ones need a rule.

robots.txt starts from the file WordPress already generated. Quinrel appends its lines unless an administrator confirms a full replacement. AI crawler rules are added when you turn them on.

The `.htaccess` editor stays off until an administrator turns it on. It does not run when WordPress file editing is disabled. Nginx does not read `.htaccess`.

RSS feed text can add a line before or after each item. The default "The post first appeared on" line stays off until an administrator turns it on. Text saved in the before and after boxes is used either way.

## Technical SEO Audit

The audit is the list of on-site reasons a page is hard to request or hard to understand. Run the Technical SEO Audit when you want that list. It checks meta descriptions, image alt text, 404s, redirect chains, orphaned pages, noindex directives, the sitemap, robots.txt, the site icon, SSL, schema, duplicate titles, cornerstone staleness, and canonicals. It does not score AI mentions, Google presence, or competitors.

The site icon check is part of this audit. Quinrel does not include a separate favicon tool.

## Link Health and the Broken Link Checker

A published URL that no other page points to is easy for a crawler to miss. Link Health lists internal links and orphaned pages, so you can see which published URLs nothing else points to. The Broken Link Checker scans published content in small batches and reports the broken links it finds.

## llms.txt

Tools that write answers sometimes fetch a short reading list from the site before they decide which pages to use. Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it.

The file is ready when the plugin is active. It is built on this site from the site name, the tagline, and recent posts, and it is served at `/llms.txt`. On the llms.txt screen you choose the summary and the post types. Set a contact URL there when the site has no page named "contact". With neither a contact URL nor that page, the public file includes the administrator email address.

Schema Markup is printed with the page, on this site, so a machine can tell a product, a place, or an article from a generic post. AI crawler rules are added to robots.txt only after an administrator turns them on. The `noai` and `noimageai` tag is added only after the training opt-out is on, or a post is marked opted out. Both stay off until then.

You can record visits from known AI crawlers, including GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and Applebot-Extended. The screen shows a visit count and the last hit. The count is kept on this site for 30 days after the latest visit, and it increases only when the request reaches WordPress. A page cache that serves the HTML without running PHP does not record that hit. Clearing the object cache can clear the count. That record is a visit count on this site.

Publishing the file, printing schema, writing the crawler rules, and counting those visits do not call https://api.quinrel.com. A citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude is coming soon.

## Image SEO

Image search and screen readers both need a description of the picture. Image SEO lists images that have no alternative text, so you can add a description for readers and image search. It can rename new uploads to lowercase hyphenated file names, and can add image schema. Reversible compression stays off until an administrator turns it on.

## Bulk Meta Editor

A missing title across a large site stays missing when the only fix is opening every post. The Bulk Meta Editor edits SEO titles and meta descriptions for many posts, so a missing title does not require opening each post.

## cookie notice

The cookie notice records a visitor choice. It does not block third-party scripts and is not a consent-management platform. Quinrel can also add an AI content disclosure.

## Roles & Capabilities

On a site with several editors, someone has to own a title change, a redirect, and a settings change. Roles & Capabilities control who can manage redirects, edit SEO meta, and open SEO screens. An audit log records changes to titles, descriptions, and settings.

## Google Analytics 4, Google Search Console, and IndexNow

The numbers that show whether anyone found the page usually live in another product. Google Analytics 4 and Google Search Console load after you connect them. Google Search Console connects after an administrator saves a Google OAuth client ID and client secret. Connect then opens Google's account screen for read-only access. The administrator chooses the Search Console property. Search Console can list queries matched by more than one page, which is how you see two URLs competing for the same query.

IndexNow sends published URLs to https://api.indexnow.org/indexnow only after an administrator saves a key and turns notifications on. IndexNow does not guarantee indexing. The Google Indexing API is optional, applies to eligible JobPosting or livestream pages, and does not guarantee indexing.

The Keyword Rank Tracker shows how queries moved in Google Search Console. The change compares the last 28 days with the 28 days before that. A lower position number means the query moved up. A SerpApi key is optional and is used only for a live Google results check.

A Bing Webmaster tools verification code can be saved in settings. An administrator who saves a Bing API key can read the last 28 days of clicks and impressions for this site. The site address is sent to https://ssl.bing.com only when that administrator opens the Bing section. Quinrel does not submit pages or sitemaps to the Bing Webmaster API.

Social posting can send to Facebook, X, LinkedIn, and Pinterest. Instagram and YouTube can be stored as profile URLs. Quinrel does not post to Instagram or TikTok.

## Settings

The on-site rules and the outside accounts live on one screen, split by job. Settings are on separate tabs: General, Quinrel Cloud, Google Search Console, Bing Webmaster, Indexing & APIs, Performance, AI Assistant, Roles, and Advanced Tools.

You can export settings as JSON and import a file after Quinrel checks its shape. The import does not copy API keys or OAuth secrets.

## Import, multisite, and headless

Switching SEO plugins should not mean retyping every title. The setup wizard can store a site type and profile URLs. Technical SEO already works before you finish it.

Quinrel can import titles, descriptions, and settings from Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework. The import shows how many fields it will copy and does not write until you confirm. While Yoast, Rank Math, All in One SEO, or SEOPress is active, Quinrel does not replace titles and meta until an administrator confirms it.

On a multisite network, a network screen is available. It is not shown on a single site.

The REST API can read public SEO fields for a published post. Writing SEO data requires a user who can edit that post.

## Connecting Quinrel

A Quinrel account is optional. Connecting the site brings intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host.

An administrator can pair the site automatically with 1-click auto-connect, or paste a site token or an authorization code. Quinrel sends that request only to `https://api.quinrel.com`. The base address is `https://api.quinrel.com/v1` unless code overrides it with the `quinrel_backend_api_url` filter.

A site token is JSON posted to `/auth/site-token` with `grant_type` set to `site_token`, the pasted `site_token`, and `site_url` set to this site's home address. An authorization code is JSON posted to `/auth/token` with `grant_type` set to `authorization_code`, `code`, and the same `site_url`. Quinrel does not store the pasted token or code. It stores the returned access token only after `GET /auth/verify` says `active` is true.

Each of these requests waits 10 seconds. Quinrel tries once more when the connection fails, or when the response is HTTP 408, 429, or 500 or higher. A rejected credential is not tried again, and nothing is saved.

Checking the saved credential sends `GET /auth/verify`. Rotating it sends `POST /auth/rotate` with the saved credential and `site_url`. The replacement is stored only after it verifies. If that fails, the saved credential stays.

Disconnect sends `POST /auth/revoke`, then deletes the credential stored on this site. If Quinrel cannot be reached, the credential is still removed here. Titles, the sitemap, redirects, and 404 logs are kept. The remote token can remain valid until Quinrel is reachable.

A page view does not call Quinrel. A tier comes from the verification response and expires. The settings screen cannot set it.

`readme.txt` lists each external service, what is sent, and when.

## Coming soon

A connected account is there for discovery beyond the pages on this server. These parts are not in the current plugin:

- Search Console connect that does not ask for a Google Cloud client ID and client secret. Until then, an administrator saves both on this site and Connect opens Google for read-only access.
- A client reporting portal.
- A workspace for more than one brand.
- A citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude. The crawler visit record on this site is not that count.
- Schema import from a page whose JSON-LD appears only after JavaScript runs.

It does not score AI mentions, Google presence, or competitors. A connected account can receive intelligence, quick fixes, and an action plan from the Quinrel API. The plugin does not invent those results on the site.
