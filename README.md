# Quinrel for WordPress

Visibility & Discovery Intelligence for WordPress, with technical SEO included.

A published WordPress site can still be hard to find: the theme title is wrong, an old URL 404s, two posts chase one query, image search has nothing to read, and search performance lives in another tab. Quinrel is the on-site place for that work. A Quinrel account is optional.

Connecting the site can bring intelligence, quick fixes, and an action plan from the Quinrel API at `https://api.quinrel.com`. When the account is connected, the writing assistant and content gap analysis can be sent there, and the screen shows what the API returns. Technical SEO on the site keeps working when that API cannot be reached. The settings screen cannot change the Quinrel API host. It does not score AI mentions, Google presence, or competitors. The plugin does not invent those results on the site.

What this release ships is `docs/features.md` and `readme.txt`. The two operating modes are in `architecture.md`. The product intent is `quinrel-wp.md`. Where the intent names a discovery score this repository does not calculate, the feature list wins.

## Identity

- Plugin slug and text domain: `quinrel`
- PHP namespace: `Quinrel\WordPress`
- WordPress 6.0 or newer. PHP 8.2 or newer.
- License: GPL-2.0-or-later

The checkout directory `quinrel-wp` is not the plugin slug.

## What stays on the site

Titles and meta descriptions, social cards, schema, XML and HTML sitemaps, redirects, image SEO, internal links, IndexNow, and the Technical SEO Audit run here. Query movement in the Keyword Rank Tracker is read from Google Search Console. A live results check uses a SerpApi key saved on this site. Rank checks are not sent to the Quinrel API.

`llms.txt` is published on this site at `/llms.txt` from the site name, the tagline, and recent posts. Schema is printed with the page. AI crawler rules and the `noai` / `noimageai` tag stay off until an administrator turns them on. Visits from known AI crawlers are counted here for 30 days after the latest visit, and only when PHP runs. A page cache that skips PHP does not record that hit. Set a contact URL when the site has no page named "contact", or the public file includes the administrator email. Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it. A citation count is not in this release.

## Import

Titles, descriptions, and settings can be imported from Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework. The import shows how many fields it will copy and does not write until you confirm.

## Headless

`GET` and `PUT /wp-json/quinrel/v1/seo/{post_id}`, and `GET /wp-json/quinrel/v1/seo/global`. A public read returns SEO fields for a published post. Writing requires a user who can edit that post.

## Local setup

1. Place the plugin in `wp-content/plugins/quinrel`.
2. Run `composer install` so the `Quinrel\` autoload map exists.
3. Run `npm run build` to compile the admin and editor bundles.
4. Activate Quinrel in WordPress.

`package-zip.sh` builds the release zip from a clean git tag. The zip omits tests, docs, and development configuration.
