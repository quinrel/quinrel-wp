# About Quinrel

A careful WordPress site can still be hard to find. The theme prints a title you did not write. A redesigned URL returns 404. Two posts answer the same query and split the clicks. Search performance lives in another Google tab. A share on LinkedIn or Facebook shows a blank card. An AI crawler requests the site, and the only trace is a line in a log nobody reads.

Quinrel is the WordPress place for that work.

Visibility & Discovery Intelligence for WordPress, with technical SEO included.

Technical SEO works with no Quinrel account. A Quinrel account is optional.

## What you can do after activation

Open Quinrel from the WordPress admin menu. The dashboard shows whether search engines are discouraged, the sitemap address, missing titles, images with no alternative text, redirects, and not-found requests. Those counts come from this site.

The setup wizard can store a site type and profile URLs. Titles, the audit, and redirects work before you finish it. XML Sitemaps stay off until an administrator turns them on.

In the block editor, the Quinrel panel shows the SEO title, the meta description, a search-result preview, readability (Flesch Reading Ease), canonical and robots settings, FAQ schema, internal link suggestions, and social fields for Facebook, X, LinkedIn, and Pinterest. PageSpeed for that post runs when you ask for it. Search results and social cards use what you save there.

The writing assistant uses an OpenAI, Anthropic, or Ollama key saved on this site. A remote Ollama host receives the text you submit. It runs when an editor asks it to run. When a Quinrel account is connected, that same request can go to the Quinrel API first.

## Two ways to run it

On its own, Quinrel is the full on-site toolset: titles, social cards, Schema Markup, XML Sitemaps, redirects, Image SEO, internal links, IndexNow, Google Search Console query movement after you connect Google, and a Technical SEO Audit of this site. LocalBusiness schema is there when the page is a place. A news sitemap is there when you turn sitemaps on. WooCommerce product schema can include an aggregate rating when that product has reviews.

Connecting a Quinrel account can bring intelligence, quick fixes, and an action plan from the Quinrel API into WordPress. When the account is connected, the writing assistant and content gap analysis can be sent to the API. The screen shows what the API returns. Technical SEO on this site keeps working when Quinrel cannot be reached. The settings screen cannot change the Quinrel API host.

The Technical SEO Audit runs on this site. It does not score AI mentions, Google presence, or competitors. The plugin does not invent those results on the site.

## Who it is for

Publishers, shops, local businesses, and editorial teams who need one place for how the WordPress site is found. Roles & Capabilities decide who can edit SEO fields, and an audit log records changes to titles, descriptions, and settings.

Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it. The file is served at `/llms.txt` from the site name, the tagline, and recent posts. Schema Markup is printed with the page. AI crawler rules and the `noai` / `noimageai` tag stay off until an administrator turns them on. Known crawler visits are counted on this site for 30 days, and only when WordPress runs the request. A page cache that skips PHP does not record that hit. Set a contact URL on the llms.txt screen when the site has no page named "contact", or the public file includes the administrator email. None of this calls the Quinrel API. A citation count from those systems is not in this release. The visit record on this site is a visit count.

## What stays on the site

Quinrel stores its tables, options, and post meta under the `quinrel_` prefix. API keys and secrets are encrypted at rest with the `quinrel_enc::` prefix when OpenSSL is available. A normal page view does not call Quinrel or the other external services. Nothing is sent until an administrator turns the feature on.

Switching from another SEO plugin should not mean retyping every title. You can import titles, descriptions, and settings from Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework. The import shows how many fields it will copy and does not write until you confirm.

The feature list is [features.md](features.md). The WordPress.org description is `readme.txt`. If another page in this folder disagrees with those two, follow them.
