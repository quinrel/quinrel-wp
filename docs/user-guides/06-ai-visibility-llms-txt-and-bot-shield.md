# llms.txt and crawler visits

Answer engines sometimes fetch a short reading list, the schema on a page, or the page itself, before they decide what to use. Quinrel prepares those on this site. A Quinrel account is optional. None of the steps below call https://api.quinrel.com.

Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it. It does not score AI mentions, Google presence, or competitors.

## The reading list

The file is ready when the plugin is active. It is built from the site name, the tagline, and recent posts, and it is served at `/llms.txt`. Open that address on the site to read what was published. Publishing it does not place the site in an answer.

On the llms.txt screen, choose the summary and the post types included. Set a contact URL when the site has no page named "contact". With neither a contact URL nor that page, the public file includes the administrator email address.

## Schema

Schema Markup is printed with the page. A machine that fetches the HTML can tell a product, a place, an article, or an event from a generic post. The markup stays in the response. It is not sent to Quinrel. Structured data does not guarantee a rich result, and it does not place the page in an AI answer.

## Crawler rules and the training opt-out

AI crawler rules are added to robots.txt when you turn them on. They stay off until then. WordPress still builds the rest of the file. Quinrel appends its lines unless an administrator confirms a full replacement.

The `noai` and `noimageai` tag, and a `tdm-reservation` tag, are added when the training opt-out is on, or when a post is marked opted out. Both stay off until then. The site owner remains responsible for any legal reservation. Quinrel does not make a site compliant with a copyright or privacy law.

## Crawler visits

Quinrel can record visits from known AI crawlers, including GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and Applebot-Extended. The screen shows a visit count and the last hit.

The count is stored on this site and kept for 30 days after the latest visit. It increases only when the request reaches WordPress. A page cache that serves the HTML without running PHP does not record that hit. Clearing the object cache can clear the count. That count is not a citation score, and it is not a ranking.

You can also add an AI content disclosure on content you choose. The cookie notice is separate. It records a visitor choice and does not block third-party scripts.

A count of citations inside ChatGPT, Perplexity, Google AI Overviews, or Claude is coming soon.
