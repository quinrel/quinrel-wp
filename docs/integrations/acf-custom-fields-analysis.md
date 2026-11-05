# Advanced Custom Fields (ACF) & Meta Analysis Playbook

Quinrel can read an Advanced Custom Fields value, a Meta Box value, or post meta when a title, description, or schema template uses `acf:`, `cf:`, or `custom_field:`. Flexible content and repeaters are flattened to text for that lookup. Quinrel does not score the page as thin content.

---

## 1. The Challenge of Custom Field SEO

When editors build landing pages using ACF Flexible Content blocks:
* `post_content` in the `wp_posts` table is frequently blank or contains only minimal markup.
* Standard SEO analyzers score the post at 0 words, flagging it for missing focus keywords and low readability.
* Internal link recommendations fail because the engine cannot parse links inside custom layout modules.

Quinrel solves this by resolving custom fields on demand during authoring and aggregating them into the analysis engine.

---

## 2. Automatic Field Discovery

Whenever you open the Quinrel panel in the Block Editor or Classic Editor, the **Custom Field Scanner** automatically inspects the active post:

1. **ACF Field Groups:** Scans for registered field groups bound to the current post type.
2. **Supported Field Types:**
   * Text & Text Area (`text`, `textarea`)
   * WYSIWYG Editor (`wysiwyg`)
   * Repeaters (`repeater` sub-fields)
   * Flexible Content layouts (`flexible_content` layout sections)
   * Clone Fields (`clone`)
3. **Native Post Meta:** Discovers any public post meta keys containing textual content.

---

## 3. Configuring Analyzed Fields in the Editor

In the Block Editor sidebar:
1. Open the **Quinrel SEO** panel and switch to the **Advanced** tab.
2. Under **Custom Fields SEO Analysis**, you will see a list of all discovered fields categorized by field group (e.g., "Hero Section", "Features Grid", "FAQ Items").
3. Check the boxes beside the fields containing substantive editorial copy.
4. **Instant Recalculation:** Quinrel immediately recalculates:
   * **Total Word Count:** Combines `post_content` with selected ACF fields.
   * **Focus Keyword Density:** Evaluates keyword presence across custom fields.
   * **Flesch Reading Ease & Complexity:** Audits readability across your custom layout modules.
   * **Internal Links:** Discovers internal links placed inside ACF WYSIWYG or text fields.

---

## 4. Mapping ACF Fields to Schema.org Properties

You can map specific ACF fields directly into Schema.org nodes without writing custom PHP:

* **Example 1: Recipe Schema:**
  * Map ACF Repeater `recipe_ingredients` ➔ Schema `recipeIngredient` array.
  * Map ACF Text `cooking_duration` ➔ Schema `cookTime`.
* **Example 2: Event Schema:**
  * Map ACF Date Picker `event_date` ➔ Schema `startDate`.
  * Map ACF Google Map field `venue_address` ➔ Schema `location.address`.

For programmatic customization, use the `quinrel_schema_graph` filter:
```php
add_filter( 'quinrel_schema_graph', function( array $graph, int $postId ): array {
    if ( function_exists( 'get_field' ) ) {
        $prepTime = get_field( 'recipe_prep_time_minutes', $postId );
        if ( $prepTime ) {
            foreach ( $graph as &$node ) {
                if ( isset( $node['@type'] ) && 'Recipe' === $node['@type'] ) {
                    $node['prepTime'] = 'PT' . intval( $prepTime ) . 'M';
                }
            }
        }
    }
    return $graph;
}, 10, 2 );
```

---

## 5. Performance & Caching Guarantees

* **Zero Frontend Penalty:** The ACF scanner runs only in the WordPress admin editor context. On frontend visitor requests, meta values are fetched using standard, highly optimized WordPress object caching.
* **Transient Memory Bounds:** Discovered field keys are cached in memory per request to prevent redundant `wp_postmeta` lookups during bulk editor operations.

