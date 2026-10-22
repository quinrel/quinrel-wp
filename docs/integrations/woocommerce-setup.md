# WooCommerce SEO Integration Playbook

When WooCommerce is active, Quinrel automatically enhances standard WooCommerce product schema and taxonomy management to provide Google Merchant Center compliance and cleaner e-commerce site architecture.

---

## 1. Google Merchant Center Identifiers (GTIN, MPN, ISBN, Brand)

Google requires specific structured identifiers in product feeds to qualify for Google Shopping organic listings and enhanced SERP rich snippets.

### Managing Product Identifiers:
1. Edit any product under **Products > All Products**.
2. Scroll to the **Product Data** metabox under the **Inventory** tab, or open the **Quinrel SEO** panel in the block editor.
3. Quinrel provides dedicated fields:
   * **GTIN / UPC / EAN:** Global Trade Item Number (barcodes: 8, 12, 13, or 14 digits).
   * **MPN:** Manufacturer Part Number (alphanumeric code assigned by manufacturer).
   * **ISBN:** International Standard Book Number (for books and publications).
   * **Brand:** The brand or manufacturer entity.
   * **Price Valid Until:** ISO 8601 date marking promotion or price expiration.

### Resulting JSON-LD Product Schema:
```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "@id": "https://example.com/product/premium-leather-boots/#product",
  "name": "Premium Leather Boots",
  "image": "https://example.com/wp-content/uploads/boots.jpg",
  "description": "Handcrafted waterproof leather work boots.",
  "sku": "BOOT-BRN-42",
  "gtin13": "0123456789012",
  "mpn": "PLB-42",
  "brand": {
    "@type": "Brand",
    "name": "Artisan Footwear"
  },
  "offers": {
    "@type": "Offer",
    "price": "189.00",
    "priceCurrency": "USD",
    "priceValidUntil": "2026-12-31",
    "availability": "https://schema.org/InStock",
    "url": "https://example.com/product/premium-leather-boots/",
    "seller": {
      "@type": "Organization",
      "name": "Acme Retail Store"
    },
    "hasMerchantReturnPolicy": {
      "@type": "MerchantReturnPolicy",
      "applicableCountry": "US",
      "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow",
      "merchantReturnDays": 30,
      "returnMethod": "https://schema.org/ReturnByMail",
      "returnFees": "https://schema.org/FreeReturn"
    }
  }
}
```

---

## 2. Merchant Return Policy Schema

Google Search now evaluates `hasMerchantReturnPolicy` directly within Product rich snippets:
1. Navigate to **Quinrel > Settings > WooCommerce**.
2. Configure your store's return policy parameters:
   * **Return Policy Category:** 30-Day finite return window, Free returns, or Not permitted.
   * **Merchant Return Days:** e.g., `30` or `60`.
   * **Return Method:** Return by mail, return in store.
   * **Return Fees:** Free return, customer pays return shipping.
   * **Country Code:** ISO 3166-1 alpha-2 code (e.g. `US`, `GB`, `CA`).
3. Quinrel automatically nests this policy inside all active product offer schemas.

---

## 3. Clean Product Category Permalinks (Strip Base)

By default, WooCommerce forces `/product-category/` into all category archive URLs (e.g., `example.com/product-category/shoes/boots/`).

### Enabling Clean Permalinks:
1. Navigate to **Quinrel > Settings > WooCommerce**.
2. Check **Remove Product Category Base**.
3. Save settings. Quinrel flushes rewrite rules safely:
   * `example.com/product-category/shoes/` ➔ `example.com/shoes/`
   * `example.com/product-category/shoes/boots/` ➔ `example.com/shoes/boots/`
4. **Automatic 301 Redirection:** Any legacy incoming requests to `/product-category/*` are automatically 301 redirected to the new stripped paths to preserve existing backlinks and Google indexation.

---

## 4. Primary Product Category for Breadcrumbs

Products frequently belong to multiple categories (e.g., "Sale", "Footwear", and "Winter Collection").

Quinrel allows store managers to select a **Primary Category**:
* In the product editor category checklist, click **Make Primary** beside your target canonical category.
* Quinrel uses this selection to construct deterministic canonical permalinks and breadcrumb trails:
  `Home > Footwear > Boots > Premium Leather Boots`
* Secondary categories remain active for visitor navigation and faceted filtering without creating duplicate breadcrumb graphs.

---

## 5. E-Commerce OpenGraph Meta Tags

When products are shared on Facebook, WhatsApp, Pinterest, and iMessage, Quinrel injects commercial OpenGraph tags:
```html
<meta property="og:type" content="product" />
<meta property="product:price:amount" content="189.00" />
<meta property="product:price:currency" content="USD" />
<meta property="product:availability" content="instock" />
<meta property="product:brand" content="Artisan Footwear" />
```
This enables rich product previews with live pricing and availability badges directly within messaging and social feeds.

