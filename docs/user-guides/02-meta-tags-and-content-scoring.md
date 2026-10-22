# Meta Tags, Snippet Optimization & Content Scoring

Quinrel integrates directly into the WordPress Gutenberg Block Editor and Classic Editor via a native sidebar panel. It evaluates content in real time across semantic density, readability, and structural search hygiene.

---

## 1. The Block Editor Sidebar

When editing any post, page, or custom post type:
1. Click the **Quinrel** icon in the top-right toolbar.
2. The sidebar expands with four core tabs:
   * **General:** Snippet Preview, Title, Description, Canonical URL, Focus Keyword.
   * **Social:** OpenGraph & Twitter/X card preview and image overrides.
   * **Schema:** Rich snippets configuration and live JSON-LD preview.
   * **Advanced:** Robots meta directives, custom fields selection, and cornerstone status.

---

## 2. Dynamic Replacement Variables (Smart Tags)

Quinrel supports smart tags to automatically construct meta titles and descriptions across your content catalog:

| Variable | Description | Example Output |
| :--- | :--- | :--- |
| `%%title%%` | The title of the current post or page | "Top 10 Coffee Machines" |
| `%%sitename%%` | The site title from WordPress General Settings | "Acme Reviews" |
| `%%sep%%` | The global title separator defined in Quinrel Settings | `-` or `\|` |
| `%%category%%` | The primary category of the current post | "Kitchen Appliances" |
| `%%currentdate%%` | Current four-digit year (auto-updates dynamically) | "2026" |
| `%%author%%` | Display name of the post author | "Jane Doe" |
| `%%excerpt%%` | Post excerpt or auto-trimmed content body | "A detailed review of..." |
| `%%tag%%` | First tag assigned to the post | "Espresso" |

### Best Practices:
* **Default Title Format:** `%%title%% %%sep%% %%sitename%%`
* **Length Guardrails:** 
  * Title: 50–60 characters (pixel width ~580px).
  * Description: 120–158 characters (pixel width ~960px).
  * Quinrel displays a live visual progress bar that turns green within optimal length thresholds and flags truncated snippets.

---

## 3. Focus Keyword Engine & Morphological Matching

Quinrel evaluates your primary focus keyword and secondary keywords across multiple content zones:

### Keyword Zones Evaluated:
1. **SEO Title:** Presence in the title tag, preferably near the beginning.
2. **Meta Description:** Organic inclusion within the snippet description.
3. **URL Slug:** Inclusion in the permalink slug.
4. **First 100 Words:** Early topic establishment in the opening paragraph.
5. **Headings (H2 / H3):** Distribution across structural subheadings.
6. **Body Content & Density:** Target density between 0.8% and 2.5% (avoiding keyword stuffing).
7. **Image Alt Text:** Presence in at least one relevant content image alternative text attribute.

### Morphological Stemming
Unlike legacy SEO tools that require exact character-for-character string matches, Quinrel utilizes algorithmic word stemming:
* Targeting `"running shoe"` automatically validates `"running shoes"`, `"runner shoe"`, and `"runs shoes"`.
* Inflections and plural variations satisfy keyword requirements without forcing unnatural phrasing into your prose.

---

## 4. Readability & Content Quality Metrics

Quinrel analyzes content against established linguistic clarity models:

* **Flesch Reading Ease:** Analyzes average sentence length and syllables per word. Scores above 60 are recommended for general web audiences.
* **Passive Voice Ratio:** Flags passive constructions when they exceed 10% of total sentences.
* **Sentence Length Variety:** Highlights paragraphs containing consecutive sentences over 20 words.
* **Transition Words:** Recommends at least 25% of sentences contain transition signposts (e.g., "furthermore", "consequently", "specifically").
* **Inclusive Language Detection:** Optional checker flagging non-inclusive terminology or idioms and suggesting modern, objective alternatives.
* **Complex Word Scoring:** Identifies polysyllabic jargon and provides clear synonyms.

---

## 5. Cornerstone Content Strategy

Marking a post as **Cornerstone Content** flags it in your database as an authoritative reference piece:
* **Enhanced Internal Link Recommendations:** Quinrel prioritizes cornerstone articles as suggested destination links when editing related articles.
* **Stricter Scoring Criteria:** Cornerstone posts require comprehensive topic coverage (minimum 1,200 words), complete schema definitions, and higher heading distribution scores.
* **Content Audit Highlighting:** Easily filter cornerstone posts in your Quinrel Content Audit dashboard to track staleness and update intervals.

---

## 6. Advanced Robots Directives

Under the **Advanced** tab, you can override global robots meta directives for specific pages:

* **Noindex:** Instructs search engines not to display this page in search results.
* **Nofollow:** Instructs search engines not to follow outbound hyperlinks on this page.
* **Noarchive:** Prevents search engines from showing cached snapshot links.
* **Nosnippet:** Prevents text snippets or video previews from appearing in search results.
* **Max Snippet / Max Image Preview:** Restricts preview sizes (e.g., `max-image-preview:large`).
* **Canonical URL Override:** Points search engines to the original canonical version if this page republishes syndicated content.

