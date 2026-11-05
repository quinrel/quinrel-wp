# Getting started

Quinrel is for the WordPress site that is already published and still hard to find: the wrong title, the dead URL, the query split across two posts, the blank social card. The on-site tools work before you connect anything. A Quinrel account is optional. Connecting can bring intelligence, quick fixes, and an action plan from the Quinrel API. The plugin does not invent those results on the site.

Quinrel runs on WordPress 6.0 or newer and PHP 8.2 or newer. Activate it from the Plugins screen, then open Quinrel in the admin menu.

## What you see first

The dashboard shows whether search engines are discouraged, the sitemap address, missing titles, images with no alternative text, redirects, and not-found requests. Those counts come from this site. A Quinrel account is optional.

The setup wizard can store a site type and profile URLs. Titles, the audit, redirects, and schema work before you finish the wizard. XML Sitemaps stay off until an administrator turns them on.

## Where to go next

- Set a title and meta description on a post. The Quinrel panel is in the block editor.
- Run the Technical SEO Audit when you want a list of on-site problems. It does not score AI mentions, Google presence, or competitors.
- Open Redirects Manager when a URL has changed.
- Import from Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework when you are switching. The import shows how many fields it will copy and does not write until you confirm.
- Open `/llms.txt`. Quinrel publishes that file on this site from the site name, the tagline, and recent posts. Schema, crawler rules, and the visit count stay on this site too. The [llms.txt guide](06-ai-visibility-llms-txt-and-bot-shield.md) covers the contact URL, the switches that stay off, and what a visit count includes.

## Connecting other services

Google Search Console connects after an administrator saves a Google OAuth client ID and client secret. Connect then opens Google's account screen for read-only access. The administrator chooses the Search Console property.

IndexNow, PageSpeed, Bing Webmaster traffic, and the writing assistant stay quiet until you save their credentials and use them. Nothing is sent until an administrator turns the feature on.

Search Console connect without your own Google Cloud client is coming soon. A client reporting portal and a workspace for more than one brand are coming soon.
