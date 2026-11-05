=== Quinrel ===
Contributors: skoorite
Tags: seo, schema, sitemap, indexing, search console
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visibility & Discovery Intelligence for WordPress, with technical SEO included.

== Description ==

A careful WordPress site can still be hard to find. The theme prints a title you did not write. A redesigned URL returns 404. Two posts answer the same query and split the clicks. The picture that should appear in image search has no alternative text. Search performance lives in another Google tab, so a query can slip for weeks before anyone on the site notices. A share on LinkedIn or Facebook shows a blank card. An AI crawler requests the site, and the only trace is a line in a log nobody reads.

Quinrel is Visibility & Discovery Intelligence for WordPress, with technical SEO included. It is the place in WordPress for the on-site work that decides whether a page can be discovered: what the page claims to be, which URL is canonical, whether a crawler may request it, and whether the old address still resolves. Technical SEO works with no Quinrel account. A Quinrel account is optional.

Open Quinrel after activation. The dashboard shows whether search engines are discouraged, the sitemap address, missing titles, images with no alternative text, redirects, and not-found requests. Those counts come from this site. The setup wizard can store a site type and profile URLs. The local tools work before you finish the wizard.

On its own, Quinrel covers titles and meta descriptions, social cards, Schema Markup, XML Sitemaps, the HTML Sitemap, redirects, Image SEO, internal links, IndexNow, and a Technical SEO Audit of this site. Google Search Console, Google Analytics 4, and Bing Webmaster traffic sit next to that work after you connect them. The Keyword Rank Tracker reads query movement from Search Console on this site.

Connecting a Quinrel account can bring intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. When the account is connected, the writing assistant and content gap analysis can be sent to that API, and the screen shows what the API returns. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host. It does not score AI mentions, Google presence, or competitors. The plugin does not invent those results on the site.

### Editor

Search results and social cards use the title and description saved on the post. In the post editor you can set the SEO title and meta description, read a search-result preview, and check readability before you update the post. The same panel covers:

* SEO titles and meta descriptions
* Readability
* A search-result preview
* Social metadata for Facebook, X, LinkedIn, and Pinterest
* Canonical URLs and robots meta
* FAQ schema
* Internal link suggestions
* A notice when cornerstone content has not been updated for 180 days
* PageSpeed for the post you are editing

The writing assistant uses an OpenAI, Anthropic, or Ollama key saved on this site. A remote Ollama host receives the text you submit. It runs when an editor asks it to run.

### Schema Markup

A page with only a title is easy to treat as a generic article. Schema Markup tells a search engine what a page is. It can describe a page as Article, NewsArticle, WebPage, BreadcrumbList, FAQ, HowTo, LocalBusiness, Recipe, Event, Product, VideoObject, Person, or Organization. The schema screen also has templates for BlogPosting, PodcastEpisode, Course, JobPosting, Book, SoftwareApplication, Restaurant, Service, Review, Movie, MusicRecording, and Dataset. You can also add one custom JSON-LD node, or import JSON-LD from a public page. Quinrel does not fetch a private or local address, and it does not run that page's JavaScript. Breadcrumb Schema creates nested BreadcrumbList structured data automatically based on post hierarchy and taxonomy terms.

When WooCommerce is active, product schema can include an aggregate rating if that product has reviews. Structured data does not guarantee a rich result.

### XML Sitemaps and the HTML Sitemap

A crawler finds new URLs faster when the site publishes the list. Quinrel's sitemap stays off until an administrator turns it on. When it is on:

* The sitemap index is `/sitemap.xml`.
* The news sitemap is `/sitemap-news.xml` and `/news-sitemap.xml`.
* The video sitemap is `/video-sitemap.xml`. Each further set of 500 posts is `/video-sitemap-2.xml`, then 3, and so on. A video duration is included only when the page states one.
* `[quinrel_sitemap]` prints an HTML Sitemap.

### Technical SEO

People and crawlers keep requesting an address after the page has moved. The Technical SEO screen includes the Redirects Manager. A redirect can be 301, 302, 307, 410, or 451, including a regular expression, and you can import rules from CSV. The 404 log records missing URLs so you can decide which ones need a rule.

robots.txt starts from the file WordPress already generated. Quinrel appends its lines unless an administrator confirms a full replacement. AI crawler rules are added when you turn them on.

The `.htaccess` editor stays off until an administrator turns it on. It does not run when WordPress file editing is disabled. Nginx does not read `.htaccess`.

RSS feed text can add a line before or after each item. The default “The post first appeared on” line stays off until an administrator turns it on. Text saved in the before and after boxes is used either way.

### Technical SEO Audit

The audit is the list of on-site reasons a page is hard to request or hard to understand. Run the Technical SEO Audit when you want that list. It checks meta descriptions, image alt text, 404s, redirect chains, orphaned pages, noindex directives, the sitemap, robots.txt, the site icon, SSL, schema, duplicate titles, cornerstone staleness, and canonicals. It does not score AI mentions, Google presence, or competitors.

The site icon check is part of this audit. Quinrel does not include a separate favicon tool.

### Link Health and the Broken Link Checker

A published URL that no other page points to is easy for a crawler to miss. Link Health lists internal links and orphaned pages, so you can see which published URLs nothing else points to. The Broken Link Checker scans published content in small batches and reports the broken links it finds.

### llms.txt

Tools that write answers sometimes fetch a short reading list from the site before they decide which pages to use. Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it.

The file is ready when the plugin is active. It is built on this site from the site name, the tagline, and recent posts, and it is served at `/llms.txt`. On the llms.txt screen you choose the summary and the post types. Set a contact URL there when the site has no page named "contact". With neither a contact URL nor that page, the public file includes the administrator email address.

Schema Markup is printed with the page, on this site, so a machine can tell a product, a place, or an article from a generic post. AI crawler rules are added to robots.txt only after an administrator turns them on. The `noai` and `noimageai` tag is added only after the training opt-out is on, or a post is marked opted out. Both stay off until then.

You can record visits from known AI crawlers, including OAI-SearchBot, Claude-SearchBot, GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and Applebot-Extended. OAI-SearchBot is the crawler for ChatGPT search. Claude-SearchBot is the crawler for Claude search. GPTBot and ClaudeBot are training crawlers. When training crawlers are blocked, OAI-SearchBot and Claude-SearchBot stay allowed. Blocking every AI crawler disallows them too. The screen shows a visit count and the last hit. The count is kept on this site for 30 days after the latest visit, and it increases only when the request reaches WordPress. A page cache that serves the HTML without running PHP does not record that hit. Clearing the object cache can clear the count. That record is a visit count on this site.

Publishing the file, printing schema, writing the crawler rules, and counting those visits do not call https://api.quinrel.com. A citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude is coming soon.

### Image SEO

Image search and screen readers both need a description of the picture. Image SEO lists images that have no alternative text, so you can add a description for readers and image search. It can rename new uploads to lowercase hyphenated file names, and can add image schema. Reversible compression stays off until an administrator turns it on.

### Bulk Meta Editor

A missing title across a large site stays missing when the only fix is opening every post. The Bulk Meta Editor edits SEO titles and meta descriptions for many posts, so a missing title does not require opening each post.

### cookie notice

The cookie notice records a visitor choice. It does not block third-party scripts and is not a consent-management platform. Quinrel can also add an AI content disclosure.

### Roles & Capabilities

On a site with several editors, someone has to own a title change, a redirect, and a settings change. Roles & Capabilities control who can manage redirects, edit SEO meta, and open SEO screens. An audit log records changes to titles, descriptions, and settings.

### Google Analytics 4, Google Search Console, and IndexNow

The numbers that show whether anyone found the page usually live in another product. Google Analytics 4 and Google Search Console load after you connect them. Google Search Console connects after an administrator saves a Google OAuth client ID and client secret. Connect then opens Google's account screen for read-only access. The administrator chooses the Search Console property. Search Console can list queries matched by more than one page, which is how you see two URLs competing for the same query.

IndexNow sends published URLs to https://api.indexnow.org/indexnow only after an administrator saves a key and turns notifications on. IndexNow does not guarantee indexing. The Google Indexing API is optional, applies to eligible JobPosting or livestream pages, and does not guarantee indexing.

The Keyword Rank Tracker shows how queries moved in Google Search Console. The change compares the last 28 days with the 28 days before that. A lower position number means the query moved up. A SerpApi key is optional and is used only for a live Google results check.

A Bing Webmaster tools verification code can be saved in settings. An administrator who saves a Bing API key can read the last 28 days of clicks and impressions for this site. The site address is sent to https://ssl.bing.com only when that administrator opens the Bing section. Quinrel does not submit pages or sitemaps to the Bing Webmaster API.

Social posting can send to Facebook, X, LinkedIn, and Pinterest. Instagram and YouTube can be stored as profile URLs. Quinrel does not post to Instagram or TikTok.

### Global Settings Hub and Settings Portability

The on-site rules and the outside accounts live on one screen, split by job. Settings are organized into dedicated tabs for General, Quinrel Cloud, Google Search Console, Bing Webmaster, Indexing & APIs, Performance, AI Assistant, Roles, and Advanced Tools.

Settings Portability lets administrators export plugin settings as a JSON file or import a saved configuration with schema validation.

### Setup, import, multisite, and headless

Switching SEO plugins should not mean retyping every title. The setup wizard can store a site type and profile URLs. Technical SEO already works before you finish it.

Quinrel can import titles, descriptions, and settings from Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework. The import shows how many fields it will copy and does not write until you confirm.

On a multisite network, a network screen is available. It is not shown on a single site.

The REST API can read public SEO fields for a published post. Writing SEO data requires a user who can edit that post.

### Connecting Quinrel

A Quinrel account is optional. Connecting the site brings intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host.

An administrator can pair the site automatically with 1-click auto-connect, or paste a site token or an authorization code. Quinrel sends that request only to `https://api.quinrel.com`. The base address is `https://api.quinrel.com/v1` unless code overrides it with the `quinrel_backend_api_url` filter.

A site token is JSON posted to `/auth/site-token` with `grant_type` set to `site_token`, the pasted `site_token`, and `site_url` set to this site's home address. An authorization code is JSON posted to `/auth/token` with `grant_type` set to `authorization_code`, `code`, and the same `site_url`. Quinrel does not store the pasted token or code. It stores the returned access token only after `GET /auth/verify` says `active` is true.

Each of these requests waits 10 seconds. Quinrel tries once more when the connection fails, or when the response is HTTP 408, 429, or 500 or higher. A rejected credential is not tried again, and nothing is saved.

Checking the saved credential sends `GET /auth/verify`. Rotating it sends `POST /auth/rotate` with the saved credential and `site_url`. The replacement is stored only after it verifies. If that fails, the saved credential stays.

Disconnect sends `POST /auth/revoke`, then deletes the credential stored on this site. If Quinrel cannot be reached, the credential is still removed here. Titles, the sitemap, redirects, and 404 logs are kept. The remote token can remain valid until Quinrel is reachable.

A page view does not call Quinrel. A tier comes from the verification response and expires. The settings screen cannot set it.

### Coming soon

A connected account is there for discovery beyond the pages on this server. These parts are not in the current plugin:

* Search Console connect that does not ask for a Google Cloud client ID and client secret. Until then, an administrator saves both on this site and Connect opens Google for read-only access.
* A citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude. The crawler visit record on this site is not that count.
* A client reporting portal.
* A workspace for more than one brand.
* Schema import from a page whose JSON-LD appears only after JavaScript runs.

It does not score AI mentions, Google presence, or competitors. A connected account can receive intelligence, quick fixes, and an action plan from the Quinrel API. The plugin does not invent those results on the site.

= External services =

For every service below, nothing is sent until an administrator turns the feature on. A normal page view does not call these services.

Publishing llms.txt, printing schema, writing AI crawler rules, and counting AI crawler visits stay on this site. Those steps do not send the page, the site address, or the visit record to https://api.quinrel.com or to an answer engine.

The writing assistant sends the current post title, meta description, or an excerpt to OpenAI at https://api.openai.com, or to Anthropic at https://api.anthropic.com, when an editor runs it. When a Quinrel account is connected, that same editor request can be sent to https://api.quinrel.com first. The saved OpenAI, Anthropic, or Ollama key is used when the Quinrel request is not available. Ollama defaults to http://localhost:11434 on this server. A remote http or https host receives the prompt, which can include the post title and content, only after an administrator confirms that host. A settings import cannot set a remote Ollama host.

Content gap analysis sends up to 200 focus keywords and titles from published posts and pages to OpenAI, Anthropic, Ollama, or https://api.quinrel.com when an editor runs it.

When an administrator turns on font display swap, and the page already loads a stylesheet from https://fonts.googleapis.com, Quinrel opens a preconnect to https://fonts.googleapis.com and https://fonts.gstatic.com for that request. The connection does not happen while the option is off.

The Keyword Rank Tracker reads query positions from Google Search Console after an administrator connects it. It sends focus keywords to SerpApi at https://serpapi.com when a key is saved and a check runs. PageSpeed sends the URL an administrator checks to Google's PageSpeed Insights API at https://www.googleapis.com. A saved API key is included in that request. A local address is not sent. Google Search Console uses read-only access for impressions, clicks, and click-through rate after an administrator connects it. Quinrel does not write Search Console data to Google. Google Analytics 4 reports are requested after an administrator saves the property credentials and opens the report.

IndexNow sends published URLs, after an administrator saves a key and turns notifications on, only to https://api.indexnow.org, https://www.bing.com, https://yandex.com, and https://search.seznam.cz. IndexNow does not guarantee indexing. The Google Indexing API sends a published URL only when an administrator turns it on, and only for an eligible JobPosting or livestream page. It does not guarantee indexing.

Social posting can send a post URL to Facebook, X, LinkedIn, or Pinterest after an administrator connects that account. Quinrel does not post to Instagram or TikTok.

A Bing Webmaster tools verification code can be saved in settings. An administrator who saves a Bing API key can read the last 28 days of clicks and impressions for this site. The site address is sent to https://ssl.bing.com only when that administrator opens the Bing section. Quinrel does not submit pages or sitemaps to the Bing Webmaster API.

Connecting Quinrel sends the requests described above only to https://api.quinrel.com. The settings screen cannot change that host. A Quinrel account is optional.

Service privacy policies: https://openai.com/privacy , https://www.anthropic.com/privacy , https://serpapi.com/privacy , https://policies.google.com/privacy , https://www.indexnow.org/ , and https://quinrel.com/privacy .

== Installation ==

1. Upload the `quinrel` folder to `/wp-content/plugins/`.
2. Activate **Quinrel** from the **Plugins** screen in WordPress.
3. Open **Quinrel** from the WordPress admin menu.
4. Technical SEO already works on this site. Use the setup wizard if you want to choose a site type. A Quinrel account is optional.
5. Connect Google Search Console when you want search data in WordPress. IndexNow, PageSpeed, and the Keyword Rank Tracker stay off until you save their credentials.

== Frequently Asked Questions ==

= Does Quinrel need an API key? =

No. Titles, meta descriptions, structured data, sitemaps, redirects, robots.txt, and the on-site audit work without an API key. A Quinrel account is optional.

Save a key or a connection only for the service you turn on. That includes Google Search Console, Google Analytics 4, IndexNow, PageSpeed, a live Keyword Rank Tracker check, Bing Webmaster traffic, the writing assistant, or a Quinrel account.

= What does a Quinrel account add? =

Connecting the site can bring intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. When the account is connected, the writing assistant and content gap analysis can be sent to https://api.quinrel.com, and the screen shows what the API returns. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host. It does not score AI mentions, Google presence, or competitors.

= Who is Quinrel for? =

Publishers, shops, local businesses, and editorial teams who already have a WordPress site and need one place for how that site is found. Schema Markup includes LocalBusiness when the page is a place. Social cards cover Facebook, X, LinkedIn, and Pinterest. The Technical SEO Audit, XML Sitemaps, redirects, and Image SEO work before any account exists.

= Can the settings screen change the Quinrel API host? =

No. The settings screen cannot change the Quinrel API host. Connection requests go to https://api.quinrel.com and wait 10 seconds, with one retry when Quinrel does not answer. A Quinrel account is optional. Disconnecting removes the credential stored on this site even when Quinrel cannot be reached, and SEO data is kept. The remote token can remain valid until Quinrel is reachable.

= Can I use Quinrel with WooCommerce? =

Yes. Quinrel supports WooCommerce product structured data, including an aggregate rating when that product has reviews, and related SEO controls when WooCommerce is active.

= Can I migrate from Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework? =

Yes. Supported SEO data can be imported from Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework. The import shows how many fields it will copy and does not write until you confirm.

= Will Quinrel guarantee higher Google rankings? =

No SEO plugin can guarantee a particular ranking position or a particular appearance in search results.

Quinrel keeps the title, the social card, the sitemap, redirects, structured data, and indexing controls on your site, and it shows the on-site problems and the Search Console movement next to that work. The search engine decides what to do with them.

= Does Quinrel prepare the site for AI answers? =

Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it. The file is served from this site at /llms.txt. Schema Markup is printed with the page. AI crawler rules, and the noai and noimageai tag, stay off until an administrator turns them on. Visits from known AI crawlers are counted on this site for 30 days, and only when the request reaches WordPress. None of this calls https://api.quinrel.com. Set a contact URL on the llms.txt screen when the site has no page named "contact", or the public file includes the administrator email. A citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude is coming soon.

= Does Quinrel score AI mentions or compare competitors? =

No. The Technical SEO Audit runs on this site. It does not score AI mentions, Google presence, or competitors.

= Can I use Quinrel on a headless WordPress site? =

Yes. The REST API can read public SEO fields for a published post. Writing SEO data requires a user who can edit that post.

= What happens when I deactivate the plugin? =

Deactivating Quinrel stops its plugin functionality. Existing plugin data is preserved unless you explicitly choose to remove it during uninstall.

= How does Quinrel store its settings and encrypted secrets? =

Quinrel stores its tables, options, and post meta under the `quinrel_` prefix. The HTML sitemap shortcode is `[quinrel_sitemap]`. API keys and secrets are encrypted at rest with the `quinrel_enc::` ciphertext prefix when OpenSSL is available.

= Does Quinrel work with existing themes and page builders? =

Yes. Quinrel stores its SEO fields in WordPress and prints them from there. It does not require a particular theme or page builder. The document title stays with the theme unless a custom SEO title is saved.

== Screenshots ==

1. The Quinrel dashboard on a site with no Quinrel account. It shows indexability, the sitemap address, missing titles, missing alt text, redirects, and not-found requests.
2. The Quinrel panel in the block editor. It shows the on-page checks for the current post.
3. Redirects Manager. An administrator adds a 301 or 302 rule and sees the saved rules.
4. The technical SEO audit before a scan. It runs on this site and does not score AI mentions, Google presence, or competitors.
5. Image SEO. Reversible compression stays off until an administrator turns it on, and the alt-text audit lists images that have no alternative text.
6. Schema markup templates. No global display rule is saved on this site.
7. The keyword rank tracker. Query movement comes from Google Search Console. A live results check uses a SerpApi key.
8. XML sitemap settings. Quinrel's sitemap stays off until an administrator turns it on.

== Support ==

For documentation and technical guidance, see the plugin documentation included with Quinrel.

For WordPress.org support, use the Quinrel support forum:

https://wordpress.org/support/plugin/quinrel/

When reporting a problem, include your WordPress version, PHP version, Quinrel version, and the steps required to reproduce the issue.

== Changelog ==

= 1.1.2 =

* The description states the on-site discovery work, the optional Quinrel account, and that a citation count from ChatGPT, Perplexity, Google AI Overviews, or Claude is not in this release.
* OAI-SearchBot and Claude-SearchBot are counted on their own for 30 days, and only when the request reaches WordPress. Search agents only leaves those crawlers allowed. Block all AI crawlers disallows them.

= 1.1.1 =

* The keyword rank tracker shows query movement from Google Search Console for the last 28 days compared with the 28 days before that. A SerpApi key is used only when an administrator runs a live results check.
* SEOPress and The SEO Framework import with the same preview and confirm step as Yoast SEO, Rank Math, and All in One SEO.
* The video sitemap adds a file for each further set of 500 posts. A video duration is written only when the page states one.
* Google Search Console connects after an administrator saves a Google OAuth client ID and client secret. Connect opens Google for read-only access, and the administrator chooses the property.
* Bing traffic, PageSpeed, and indexation screens use the saved connection and leave the count empty when that source has not returned a result.

= 1.1.0 =

* Settings Architecture: Dedicated Global Settings Hub with individual tabs for General, Quinrel Cloud, Google Search Console, Bing Webmaster, Indexing & APIs, Performance, AI Assistant, Roles, and Advanced Tools.
* Google Search Console: an administrator saves a Google OAuth client ID and client secret, then connects for read-only access and chooses a Search Console property.
* Quinrel Cloud: 1-click automated site pairing endpoint alongside manual site token and authorization code methods.
* Bing Webmaster: Verification meta tags and API key telemetry integration.
* Breadcrumb Schema & RSS Scraping Protection: Automatic JSON-LD breadcrumb hierarchy generation and customizable RSS feed attribution backlinks.
* Settings Portability: Safe JSON settings export and schema-validated import utility.
* Performance & Assets: Core Web Vitals optimization options with script delay and passive listeners.
* Document titles stay with the theme unless a custom SEO title is saved.
* Archive pages after the first stay indexable unless an administrator turns on noindex for them.
* While Yoast, Rank Math, All in One SEO, or SEOPress is active, Quinrel does not replace titles and meta until an administrator confirms it.
* The default RSS attribution line stays off until an administrator turns it on.
* Script defer and the Google Fonts preconnect stay off until an administrator turns them on. Defer applies only to listed script handles.
* The Bing Webmaster API key, the Search Console client secret, and Search Console tokens are encrypted at rest.
* Content gap analysis reads at most 200 recent published posts and pages that have a focus keyword.
* Core Web Vitals scores come from a PageSpeed Insights audit the administrator runs. The screen does not show a score until that audit returns.
* Modern WordPress Admin layout maximizing workspace usage across large displays.
* Plugin author updated to Skoorite Team.

= 1.0.1 =

* Maintenance release for the fixes found after 1.0.0. Requires PHP 8.2. Tested up to WordPress 7.1.
* The package no longer contains development files, shell scripts, or hidden files.
* The request address, the 404 referrer, the browser name, and the Global Privacy Control header are sanitized before they are stored or compared.
* Post, image, redirect, and audit lists stay within a fixed maximum size.
* The on-page inspector is printed with the WordPress inline script tag.
* Reads of Quinrel's own tables name the columns they use.

= 1.0.0 =

* Initial release. Requires PHP 8.2. Tested on WordPress 7.1.3.
* Stores plugin tables, options, and meta under the `quinrel_` prefix and encrypts secrets at rest as `quinrel_enc::`.
* Technical SEO controls and metadata management.
* XML, News, and Video sitemap support.
* JSON-LD structured data with WooCommerce support.
* Redirect management and 404 monitoring.
* Image SEO and internal linking tools.
* Google Search Console integration.
* A Bing Webmaster tools verification code, and IndexNow notifications. IndexNow does not guarantee indexing.
* REST API support for public SEO fields on a published post.
* Migration tools for supported SEO plugins. The import shows how many fields it will copy and does not write until you confirm.
* The Technical SEO Audit checks this site. It does not score AI mentions, Google presence, or competitors.
* An administrator can connect, check, rotate, or disconnect a Quinrel site credential. Requests go to https://api.quinrel.com, wait 10 seconds, and retry once. The settings screen cannot change the Quinrel API host. A rejected credential is not saved. Disconnect removes the local credential and keeps SEO data.
