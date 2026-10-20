# Quinrel for WordPress

Quinrel is Visibility & Discovery Intelligence for WordPress.

It has two operating modes. Both are part of the product.

## Standalone

Standalone mode works with no Quinrel account. It is the full technical SEO toolset on the site where the plugin is installed: titles and meta descriptions, canonical URLs, robots controls, schema, XML and HTML sitemaps, IndexNow, redirects, image SEO, internal linking, and the on-site technical checks.

Those checks measure this site. They do not invent an AI-mention score, a Google-presence score, or a competitor comparison.

If `api.quinrel.com` is unavailable, technical SEO on the site keeps working.

## Quinrel Cloud

A connected site uses the Quinrel API at `https://api.quinrel.com` to bring discovery intelligence, quick fixes, and an action plan into WordPress. The settings screen cannot change that host. Connection is optional. Cloud results are shown from the API response. They are not fabricated locally.

The product description is `quinrel-wp.md`. The screens shipped in this release are named in `docs/features.md`. Implementation lives under `src/`.

## Identity

| | |
|---|---|
| Git repository and checkout directory | `quinrel-wp` |
| WordPress plugin slug | `quinrel` |
| Plugin text domain | `quinrel` |
| PHP namespace | `Quinrel\WordPress` |
| Package and internal identifier | `quinrel-wp` |

The checkout directory is not the plugin slug. Plugin Check, gettext, and plugin activation use `quinrel`.

## Compatibility

- Requires at least WordPress 6.0.
- Requires PHP 8.2.
- Code may use only functions, hooks, and constants that exist in that WordPress minimum. `array_is_list()` does not. Use `Quinrel\WordPress\Core\Support\ValueList::isList()`.

## Data and Storage

Quinrel stores data in `quinrel_*` tables, options, and post meta. KeyVault encrypts stored secrets with the `quinrel_enc::` prefix using the site salt. Public APIs, script handles, and CSS classes use the `quinrel-` prefix.

## How the code stays reviewable

`Agents.md` is the engineering constitution. Queries name their columns and stay bounded as the site grows. Plugin Check findings are removed by changing the code. The release zip is defined by `package-zip.sh` and does not contain development files.
