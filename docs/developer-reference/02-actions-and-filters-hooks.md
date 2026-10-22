# Actions & Filters Reference (Plugin Hooks)

Quinrel provides a comprehensive suite of WordPress actions and filters allowing developers to customize meta tags, schema graphs, sitemap generation, and crawler shielding.

All hooks follow standard WordPress hook patterns and can be registered in your theme's `functions.php` or a custom mu-plugin.

---

## 1. Schema & Structured Data Filters

### `quinrel_schema_types`
Filters the list of supported schema type keys and class bindings registered with the Schema Engine.

* **Parameters:**
  * `$schemaTypes` (`array<string, class-string>`): Associative array mapping schema type identifiers (e.g., `'Article'`, `'PodcastEpisode'`, `'Recipe'`) to handler classes.
* **Returns:** `array<string, class-string>`

#### Example: Registering a Custom Schema Type
```php
add_filter( 'quinrel_schema_types', function( array $types ): array {
    $types['MedicalWebPage'] = MyCustomMedicalSchemaHandler::class;
    return $types;
} );
```

---

### `quinrel_schema_graph`
Filters the complete rendered JSON-LD graph array before it is serialized and rendered into the `<head>` of the page.

* **Parameters:**
  * `$graph` (`array<int, array<string, mixed>>`): Array of Schema.org entity objects.
  * `$postId` (`int`): Current post ID (0 for front page or taxonomy archives).
* **Returns:** `array<int, array<string, mixed>>`

#### Example: Injecting Publisher Know-How or ISO Credentials
```php
add_filter( 'quinrel_schema_graph', function( array $graph, int $postId ): array {
    foreach ( $graph as &$node ) {
        if ( isset( $node['@type'] ) && 'Organization' === $node['@type'] ) {
            $node['iso6523Code'] = '0199:724500ABCDEF';
            $node['knowsAbout']  = array( 'Aerospace Engineering', 'Avionics Systems' );
        }
    }
    return $graph;
}, 10, 2 );
```

---

### `quinrel_woocommerce_owns_product_schema`
Determines whether WooCommerce's native or Quinrel's enhanced product schema overrides custom post schema on single product pages.

* **Parameters:**
  * `$active` (`bool`): Whether WooCommerce product schema owns the single product output (default `true` when WooCommerce is active).
  * `$postId` (`int`): Current product post ID.
* **Returns:** `bool`

---

## 2. Meta Tags & Content Filters

### `quinrel_meta_title`
Filters the final SEO title before output in `<title>` and `<meta property="og:title">`.

* **Parameters:**
  * `$title` (`string`): The computed meta title with smart tags replaced.
  * `$postId` (`int`): Post ID.
* **Returns:** `string`

#### Example: Dynamically Appending Geo-Targeting to Titles
```php
add_filter( 'quinrel_meta_title', function( string $title, int $postId ): string {
    if ( is_singular( 'service' ) ) {
        $city = get_post_meta( $postId, '_service_city', true );
        if ( $city ) {
            return sprintf( '%s in %s', $title, esc_html( $city ) );
        }
    }
    return $title;
}, 10, 2 );
```

---

### `quinrel_meta_description`
Filters the generated meta description string.

* **Parameters:**
  * `$description` (`string`): The resolved meta description.
  * `$postId` (`int`): Post ID.
* **Returns:** `string`

---

### `quinrel_canonical_url`
Filters the computed canonical URL.

* **Parameters:**
  * `$canonical` (`string`): The fully qualified canonical URL.
  * `$postId` (`int`): Post ID.
* **Returns:** `string`

---

## 3. Custom Fields & Content Assistant Filters

### `quinrel_discovered_custom_fields`
Filters the list of metadata keys and ACF fields discovered for a given post.

* **Parameters:**
  * `$discovered` (`array<int, array<string, string>>`): Discovered fields with `key`, `label`, `type`, and `group`.
  * `$postId` (`int`): The post being analyzed.
* **Returns:** `array<int, array<string, string>>`

#### Example: Whitelisting Proprietary Custom Post Meta
```php
add_filter( 'quinrel_discovered_custom_fields', function( array $fields, int $postId ): array {
    $fields[] = array(
        'key'   => '_crm_whitepaper_executive_summary',
        'label' => 'Executive Summary (CRM)',
        'type'  => 'textarea',
        'group' => 'Enterprise Whitepaper',
    );
    return $fields;
}, 10, 2 );
```

---

### `quinrel_seo_analyzed_custom_fields`
Filters which custom field values are concatenated into the content string for keyword density and readability scoring.

* **Parameters:**
  * `$fields` (`array<int, string>`): Array of custom field keys selected for inclusion.
* **Returns:** `array<int, string>`

---

## 4. Sitemaps & Technical SEO Filters

### `quinrel_sitemap_urls_per_file`
Controls the maximum number of URLs included in each sub-sitemap chunk.

* **Parameters:**
  * `$maxUrls` (`int`): Default is `1000`. Maximum allowable per Google protocol is `50000`.
* **Returns:** `int`

#### Example: Lowering Batch Size for Shared Hosting
```php
add_filter( 'quinrel_sitemap_urls_per_file', function(): int {
    return 500; // Limits memory consumption during generation
} );
```

---

### `quinrel_script_defer_exclusions`
Filters the script handles that Quinrel's Performance Optimizer will not defer.

* **Parameters:**
  * `$exclusions` (`array<int, string>`): Array of script handles (e.g., `jquery`, `jquery-core`).
* **Returns:** `array<int, string>`

---

## 5. Action Hooks Reference

### `quinrel_gpc_opt_out`
Fires when an incoming visitor request includes a valid `Sec-GPC: 1` (Global Privacy Control) HTTP header.

```php
add_action( 'quinrel_gpc_opt_out', function() {
    // Disable external analytics cookies or third-party tracking pixels
} );
```

---

### `quinrel_404_logged`
Fires when an incoming unhandled request generates a 404 and is stored in the Quinrel Error Monitor.

* **Parameters:**
  * `$requestUri` (`string`): The 404 request URI.
  * `$referrer` (`string`): The HTTP Referrer header if available.
  * `$hitCount` (`int`): Aggregate hit count for this URI.

```php
add_action( 'quinrel_404_logged', function( string $uri, string $referrer, int $hits ) {
    if ( $hits > 100 && ! empty( $referrer ) ) {
        // Send Slack alert for high-volume broken backlink
    }
}, 10, 3 );
```

---

### `quinrel_indexnow_submitted`
Fires after a batch of URLs has been dispatched to the IndexNow API gateway.

* **Parameters:**
  * `$urls` (`array<int, string>`): URLs submitted.
  * `$statusCode` (`int`): HTTP status code returned by the gateway (e.g. `200`, `202`).

