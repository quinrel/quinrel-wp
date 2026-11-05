# Redirects and 404s

Open Redirects Manager when a URL changes and you still want the old address to resolve. The rule lives on this site and runs before the missing page is shown.

## Status codes

- 301, when the move is permanent.
- 302 or 307, when the move is temporary. 307 keeps the original request method.
- 410, when the page is gone.
- 451, when the page is unavailable for legal reasons.

A rule can be a regular expression. You can import rules from CSV. Check the file before you confirm the import.

## 404 log

The 404 log records missing URLs so you can decide which ones need a rule. It does not create the redirect for you.

Link Health lists internal links and orphaned pages. The Broken Link Checker scans published content in small batches. A scan that runs off the site, for a very large library, is coming soon.
