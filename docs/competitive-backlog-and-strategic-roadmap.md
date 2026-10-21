# Quinrel Strategic Backlog: Task Units & Execution Checklist

> **Release Target:** Quinrel v1.2.0 – v2.0.0  
> **Source Commit:** Based on Quinrel v1.1.0 Architecture  
> **Status:** Active Execution Tracking  

---

## Master Status Checklist

### Phase 1: AI-Era SEO Category Ownership & Documentation Hub (Weeks 1–3)
- [ ] **TASK-AI-01**: Unified "AI Visibility & Control" Admin Hub
- [ ] **TASK-AI-02**: Curated `/llms.txt` and `/llms-full.txt` Advanced Builder & Caching
- [ ] **TASK-AI-03**: AI Crawler Shield: Granular Search Ingestion vs. Training Scraping
- [ ] **TASK-AI-04**: Automated AI Directives & Training Opt-Out Headers (`noai`, `TDM-Reservation`)
- [ ] **TASK-DOC-01**: Core End-User Guides (`docs/user-guides/`)
- [ ] **TASK-DOC-02**: Developer Reference & Hook Architecture (`docs/developer-reference/`)
- [ ] **TASK-DOC-03**: Integration, Migration & Compliance Playbooks (`docs/integrations/`, `docs/compliance/`)
- [ ] **TASK-REF-02**: Static Analysis (PHPStan Level 6+ with WordPress Stubs)

### Phase 2: Schema Expansion & Custom Field Intelligence (Weeks 4–7)
- [ ] **TASK-SCH-01**: Modular Schema Architecture & `SchemaTypeInterface` Refactoring
- [ ] **TASK-SCH-02**: Podcast SEO Module (`PodcastSeries`, `PodcastEpisode`, Enclosure Detection)
- [ ] **TASK-SCH-03**: Educational & Employment Schemas (`Course`, `JobPosting`)
- [ ] **TASK-SCH-04**: Creative Work & Media Schemas (`Book`, `Movie`, `Music`, `SoftwareApplication`, `Dataset`)
- [ ] **TASK-SCH-05**: Local & Commercial Entity Schemas (`Restaurant`, 25+ `LocalBusiness` Subtypes, `Service`)
- [ ] **TASK-SCH-06**: Critique & Structured Lists (`Review`, `AggregateRating`, `ItemList`)
- [ ] **TASK-SCH-07**: Schema Stack Editor React UI (`editor.js`)
- [ ] **TASK-ACF-01**: ACF & Custom Field Deep Content Scanner
- [ ] **TASK-ACF-02**: ACF Smart Tag Resolver (`%%acf:field_name%%`)
- [ ] **TASK-ACF-03**: ACF Schema Property Binding Interface

### Phase 3: Advanced WooCommerce Suite & Gutenberg Schema Blocks (Weeks 8–11)
- [ ] **TASK-WOO-01**: WooCommerce Product Identifiers (`GTIN`, `MPN`, `Brand`)
- [ ] **TASK-WOO-02**: WooCommerce Core Schema Conflict Resolver & Clean Replacement
- [ ] **TASK-WOO-03**: WooCommerce `priceValidUntil`, Sale Conditions & Product Taxonomy Meta
- [ ] **TASK-WOO-04**: WooCommerce Product Gallery XML Sitemap Integration
- [ ] **TASK-BLK-01**: Native Gutenberg FAQ Accordion Block (`quinrel/faq`)
- [ ] **TASK-BLK-02**: Native Gutenberg HowTo Block (`quinrel/howto`)
- [ ] **TASK-BLK-03**: Native Gutenberg Review/Rating Card Block (`quinrel/review`)
- [ ] **TASK-BLK-04**: Native Gutenberg Table of Contents Block (`quinrel/toc`)
- [ ] **TASK-REF-01**: React SPA Modularization (`App.js` & `editor.js` Component Splitting)

### Phase 4: Off-Page Growth & Outreach Workflow (Weeks 12–14)
- [ ] **TASK-LNK-01**: Unlinked Brand Mention Discovery via GSC Query Cross-Referencing
- [ ] **TASK-LNK-02**: Broken External Backlink Reclamation Monitor
- [ ] **TASK-LNK-03**: Outreach Workflow Database & Admin CRM Table (`quinrel_outreach`)
- [ ] **TASK-LNK-04**: AI Pitch Draftsman via `AiGateway`

---

## Detailed Task Units

---

### Phase 1: AI-Era SEO & Documentation

#### TASK-AI-01: Unified "AI Visibility & Control" Admin Hub
- **Status:** `- [ ] Not Started`
- **Objective:** Consolidate disparate AI tools (`AiBotTracker`, `LlmsTxtGenerator`, `AiDisclosureManager`, `CcpaPrivacyTools`) into a single, high-visibility top-level admin menu: **"AI Visibility & Control"**.
- **Requirements:**
  1. Register top-level/submenu admin page slug `quinrel-ai-visibility` with capability `manage_seo`.
  2. Embed tabs: *Overview & Ingestion*, *llms.txt Builder*, *AI Crawler Shield*, and *Content Disclosure & Opt-Out*.
  3. Wire up unified REST endpoint `GET /wp-json/quinrel/v1/ai-governance/settings` and `POST /wp-json/quinrel/v1/ai-governance/settings`.
- **Files to touch:**
  - `src/Modules/Admin/AdminMenu.php`
  - `src/Modules/Ai/AiGovernanceModule.php` (new orchestrator)
  - `src/assets/js/App.js`
- **Done when:**
  - Navigating to wp-admin `Quinrel -> AI Visibility` renders the four tabs.
  - Toggling settings persists data through REST without page reload.
  - Nonces and `manage_seo` permissions are strictly verified.
  - Unit test `tests/Unit/Admin/AiGovernanceHubTest.php` passes.

---

#### TASK-AI-02: Curated `/llms.txt` and `/llms-full.txt` Advanced Builder & Caching
- **Status:** `- [ ] Not Started`
- **Objective:** Upgrade `LlmsTxtGenerator.php` from a static dump to a configurable, cached generator complying with the official `llms.txt` specification.
- **Requirements:**
  1. Allow administrators to select:
     - Included post types (`post`, `page`, custom post types).
     - Whether to generate both `/llms.txt` (curated index) and `/llms-full.txt` (full markdown body synthesis).
     - Custom introductory markdown preamble and contact notes.
     - Maximum link count limit (default: 100 links).
  2. Implement transient/file caching: rebuild on post publish/trash or at most once every 6 hours; never generate raw markdown on synchronous HTTP request if cache exists.
  3. Ensure virtual rewrite route `/llms-full.txt` matches virtual rewrite route `/llms.txt`.
- **Files to touch:**
  - `src/Modules/Ai/LlmsTxtGenerator.php`
  - `src/Plugin.php` (virtual route handler)
  - `tests/Unit/Ai/LlmsTxtGeneratorTest.php`
- **Done when:**
  - Requesting `curl -I https://example.test/llms.txt` returns `Content-Type: text/markdown; charset=utf-8`.
  - Requesting `curl -I https://example.test/llms-full.txt` returns compiled markdown content.
  - Cached response header `X-Quinrel-Cache: HIT` is present on repeat calls.
  - Updating a post invalidates the cached file.

---

#### TASK-AI-03: AI Crawler Shield: Granular Search Ingestion vs. Training Scraping
- **Status:** `- [ ] Not Started`
- **Objective:** Give publishers granular control over AI user agents, distinguishing between AI Search Bots (PerplexityBot, ChatGPT-User) and Bulk Training Scrapers (GPTBot, CCBot, ByteSpider, Google-Extended).
- **Requirements:**
  1. Maintain an up-to-date catalog of AI user agents with metadata (`name`, `category: search|training`, `ua_regex`, `default_action`).
  2. In `AiBotTracker.php`, dynamically output `robots.txt` rules and HTTP header rejections according to user choices:
     - *Allow all*
     - *Allow AI search bots, block AI training scrapers*
     - *Block all AI bots*
  3. Log bot hit counts into `{prefix}quinrel_ai_bot_logs` aggregated by bot name and date.
- **Files to touch:**
  - `src/Modules/Ai/AiBotTracker.php`
  - `src/Modules/TechnicalSeo/RobotsTxtEditor.php`
  - `src/Database/Installer.php` (table schema)
  - `tests/Unit/Ai/AiBotTrackerTest.php`
- **Done when:**
  - Setting mode to "Block Training Scrapers" automatically adds `User-agent: GPTBot \n Disallow: /` to `robots.txt`.
  - Simulated request with User-Agent `CCBot` receives a 403 or Disallow directive depending on enforcement mode.
  - Live hits increment daily bot telemetry counts viewable in the AI Visibility tab.

---

#### TASK-AI-04: Automated AI Directives & Training Opt-Out Headers (`noai`, `TDM-Reservation`)
- **Status:** `- [ ] Not Started`
- **Objective:** Output modern machine-readable copyright and AI opt-out headers on all front-end responses.
- **Requirements:**
  1. When option `quinrel_ai_training_optout` is `'yes'`, send the following HTTP headers on all public template requests:
     - `X-Robots-Tag: noai, noimageai`
     - `TDM-Reservation: 1` (EU Text and Data Mining reservation under DSM Directive Art. 4(3))
  2. Inject `<meta name="robots" content="noai, noimageai">` and `<meta name="tdm-reservation" content="1">` into `<head>`.
  3. Allow per-post override via post meta `_quinrel_ai_optout` (`inherit`, `allow`, `block`).
- **Files to touch:**
  - `src/Modules/Compliance/AiDisclosureManager.php`
  - `src/Modules/Seo/MetaTagsGenerator.php`
  - `tests/Unit/Compliance/AiOptOutTest.php`
- **Done when:**
  - `curl -I https://example.test/sample-post/` shows `X-Robots-Tag: noai, noimageai` and `TDM-Reservation: 1`.
  - Inspecting `<head>` confirms presence of matching meta tags.
  - Setting post-level override to `allow` suppresses the header for that specific URL.

---

#### TASK-DOC-01: Core End-User Documentation Hub
- **Status:** `- [ ] Not Started`
- **Objective:** Build comprehensive, publication-ready user documentation covering every module.
- **Requirements:**
  1. Author the following manuals in `docs/user-guides/`:
     - `01-getting-started-setup-wizard.md`: First-run onboarding, site identification, importing from Yoast/Rank Math/AIOSEO.
     - `02-meta-tags-and-content-scoring.md`: Focus keywords, smart tags, readability, cornerstone content.
     - `03-rich-snippets-and-schema-catalog.md`: Complete guide to all 20+ supported schema types.
     - `04-xml-sitemaps-and-instant-indexing.md`: IndexNow key setup, Google Indexing API service account creation, sub-sitemaps.
     - `05-redirects-and-404-monitoring.md`: 301/302/410 setup, regex patterns, auto-healing broken links.
     - `06-ai-visibility-llms-txt-and-bot-shield.md`: Configuration of `llms.txt`, crawler controls, training opt-outs.
- **Files to touch:**
  - `docs/user-guides/*.md`
- **Done when:**
  - All 6 markdown files exist, contain zero placeholder sections, and feature step-by-step instructions with code examples.

---

#### TASK-DOC-02: Developer Reference & Hook Architecture
- **Status:** `- [ ] Not Started`
- **Objective:** Document the complete developer surface area (REST APIs, PHP filters, actions, headless endpoints).
- **Requirements:**
  1. Author `docs/developer-reference/01-rest-api-specification.md`:
     - Document all 60+ REST routes with URL, method, required capability, sample request payload, and sample JSON response.
  2. Author `docs/developer-reference/02-actions-and-filters-hooks.md`:
     - Document all plugin hooks (e.g., `quinrel_meta_title`, `quinrel_schema_graph`, `quinrel_sitemap_url_list`, `quinrel_ai_bot_blocked`).
  3. Author `docs/developer-reference/03-headless-wordpress-integration.md`:
     - Code recipes for Next.js, Nuxt, and Astro querying Quinrel's public headless endpoint (`/wp-json/quinrel/v1/headless/post/{id}`).
- **Files to touch:**
  - `docs/developer-reference/*.md`
- **Done when:**
  - All developer docs are authored with validated JSON payloads and syntax-highlighted PHP/JS snippets.

---

#### TASK-DOC-03: Integration, Migration & Compliance Playbooks
- **Status:** `- [ ] Not Started`
- **Objective:** Provide actionable playbooks for WooCommerce, ACF, competitor migrations, and privacy compliance.
- **Requirements:**
  1. Author `docs/integrations/woocommerce-setup.md`: Setting up GTIN, MPN, brands, and resolving schema conflicts.
  2. Author `docs/integrations/acf-custom-fields-analysis.md`: Binding ACF fields to Content Assistant and Schema.
  3. Author `docs/integrations/1-click-migration-yoast-rankmath-aioseo.md`: Pre-migration checklist, backup advice, verification steps.
  4. Author `docs/compliance/ccpa-gpc-and-ai-governance.md`: Privacy compliance, GPC headers, and data retention rules.
- **Files to touch:**
  - `docs/integrations/*.md`
  - `docs/compliance/*.md`
- **Done when:**
  - All 4 playbook documents are fully articulated and cross-referenced in `docs/README.md`.

---

#### TASK-REF-02: Static Analysis (PHPStan Level 6+ with WordPress Stubs)
- **Status:** `- [ ] Not Started`
- **Objective:** Introduce strict static analysis into the CI/development pipeline.
- **Requirements:**
  1. Add `phpstan/phpstan` and `szepeviktor/phpstan-wordpress` to `composer.json` dev dependencies.
  2. Create `phpstan.neon.dist` configured at Level 6, scanning `src/` and excluding `vendor/`.
  3. Add `composer phpstan` command script.
  4. Fix any level 6 baseline typing defects identified across `src/`.
- **Files to touch:**
  - `composer.json`
  - `phpstan.neon.dist` (new file)
- **Done when:**
  - Running `./vendor/bin/phpstan analyse` exits with code 0.

---

### Phase 2: Schema Expansion & Custom Field Intelligence

#### TASK-SCH-01: Modular Schema Architecture & `SchemaTypeInterface` Refactoring
- **Status:** `- [ ] Not Started`
- **Objective:** Deconstruct the monolithic `CustomSchema.php` into modular schema classes implementing a unified interface.
- **Requirements:**
  1. Create interface `Quinrel\WordPress\Modules\Schema\Contracts\SchemaTypeInterface`:
     - `getId(): string`
     - `isApplicable( int $postId ): bool`
     - `generate( int $postId ): array`
     - `getValidationRules(): array`
  2. Create directory `src/Modules/Schema/Types/Concrete/`.
  3. Refactor existing types into dedicated classes:
     - `ProductSchema.php`, `FaqSchema.php`, `HowToSchema.php`, `LocalBusinessSchema.php`, `RecipeSchema.php`, `EventSchema.php`.
  4. Update `SchemaGenerator.php` to discover and register all schema providers via DI container.
- **Files to touch:**
  - `src/Modules/Schema/Contracts/SchemaTypeInterface.php` (new)
  - `src/Modules/Schema/Types/Concrete/*.php` (new)
  - `src/Modules/Schema/SchemaGenerator.php`
  - `tests/Unit/Schema/SchemaGeneratorTest.php`
- **Done when:**
  - Existing schema generation unit tests pass unchanged.
  - New schema types can be added simply by implementing `SchemaTypeInterface` and registering with `SchemaGenerator`.

---

#### TASK-SCH-02: Podcast SEO Module (`PodcastSeries`, `PodcastEpisode`, Enclosure Detection)
- **Status:** `- [ ] Not Started`
- **Objective:** Deliver native podcast structured data for audio publishers.
- **Requirements:**
  1. Create `src/Modules/Schema/Types/Concrete/PodcastEpisodeSchema.php`:
     - Generates Schema.org `PodcastEpisode` linked to parent `PodcastSeries`.
     - Output properties: `name`, `description`, `episodeNumber`, `seasonNumber`, `duration` (ISO 8601), `associatedMedia` (`@type: AudioObject`, `contentUrl`), `transcript`.
  2. Create `src/Modules/Schema/Types/Concrete/PodcastSeriesSchema.php`:
     - Generates Schema.org `PodcastSeries` output for the main podcast archive/home.
  3. Implement audio enclosure auto-detector:
     - If episode meta is empty, inspect post content for `wp:audio` block or `[audio]` shortcode and extract audio source URL automatically.
- **Files to touch:**
  - `src/Modules/Schema/Types/Concrete/PodcastEpisodeSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/PodcastSeriesSchema.php` (new)
  - `src/Modules/Schema/AudioExtractor.php` (new)
  - `src/Modules/Admin/AdminModule.php` (meta fields)
  - `tests/Unit/Schema/PodcastSchemaTest.php` (new)
- **Done when:**
  - Post with audio block and podcast meta outputs valid Schema.org `PodcastEpisode` JSON-LD in `wp_footer`.
  - Rich Results Test confirms rich audio snippet eligibility.
  - Unit test verifies audio URL auto-extraction.

---

#### TASK-SCH-03: Educational & Employment Schemas (`Course`, `JobPosting`)
- **Status:** `- [ ] Not Started`
- **Objective:** Add rich snippet support for courses and job boards.
- **Requirements:**
  1. Create `CourseSchema.php`:
     - Generates `Course` and `CourseInstance`.
     - Fields: `name`, `description`, `courseCode`, `provider` (`@type: Organization`), `courseWorkload`, `educationalCredentialAwarded`.
  2. Create `JobPostingSchema.php`:
     - Generates `JobPosting`.
     - Fields: `title`, `description`, `datePosted`, `validThrough`, `employmentType` (FULL_TIME, PART_TIME, CONTRACT), `hiringOrganization`, `jobLocation` (or `jobLocationType: TELECOMMUTE` for remote roles), `baseSalary` (currency, value, unitText).
  3. Auto-populate post title/content if manual schema title/description are blank.
- **Files to touch:**
  - `src/Modules/Schema/Types/Concrete/CourseSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/JobPostingSchema.php` (new)
  - `tests/Unit/Schema/CourseAndJobPostingSchemaTest.php` (new)
- **Done when:**
  - Generating schema for a Course post outputs valid `Course` JSON-LD.
  - Generating schema for a Job post outputs valid `JobPosting` JSON-LD compliant with Google Jobs specifications.

---

#### TASK-SCH-04: Creative Work & Media Schemas (`Book`, `Movie`, `Music`, `SoftwareApplication`, `Dataset`)
- **Status:** `- [ ] Not Started`
- **Objective:** Implement structured data for media, digital applications, and research datasets.
- **Requirements:**
  1. Create `BookSchema.php`: `name`, `isbn`, `numberOfPages`, `bookFormat` (Hardcover, EBook, AudioBook), `author`.
  2. Create `MovieSchema.php`: `name`, `director`, `actor`, `duration`, `dateCreated`.
  3. Create `MusicSchema.php`: `MusicAlbum` and `MusicRecording`, with `byArtist`, `numTracks`, `duration`.
  4. Create `SoftwareApplicationSchema.php`: `name`, `operatingSystem`, `applicationCategory`, `softwareVersion`, `fileSize`, `offers`.
  5. Create `DatasetSchema.php`: `name`, `description`, `creator`, `distribution`, `license`, `temporalCoverage`.
- **Files to touch:**
  - `src/Modules/Schema/Types/Concrete/BookSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/MovieSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/MusicSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/SoftwareApplicationSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/DatasetSchema.php` (new)
  - `tests/Unit/Schema/MediaSchemasTest.php` (new)
- **Done when:**
  - Unit tests verify valid JSON-LD structures for all 5 entities.
  - Output conforms to Schema.org standards without schema validation errors.

---

#### TASK-SCH-05: Local & Commercial Entity Schemas (`Restaurant`, 25+ `LocalBusiness` Subtypes, `Service`)
- **Status:** `- [ ] Not Started`
- **Objective:** Match Rank Math's extensive Local SEO and commercial schema capabilities.
- **Requirements:**
  1. Create `RestaurantSchema.php`:
     - Subclass of `FoodEstablishment`.
     - Fields: `servesCuisine`, `menu` (URL), `acceptsReservations`, `starRating`, `priceRange`, `openingHoursSpecification`.
  2. Refactor `LocalBusinessSchema.php`:
     - Expand dropdown to support 25+ specific schema subtypes: `AutomotiveBusiness`, `FinancialService`, `LegalService`, `MedicalBusiness` (Dentist, Pharmacy, Physician), `RealEstateAgent`, `HomeAndConstructionBusiness`, etc.
     - Add `geo` (latitude/longitude), `telephone`, `address` (`PostalAddress`), `openingHoursSpecification`.
  3. Create `ServiceSchema.php`:
     - Fields: `serviceType`, `provider`, `areaServed`, `termsOfService`, `offers`.
- **Files to touch:**
  - `src/Modules/Schema/Types/Concrete/RestaurantSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/LocalBusinessSchema.php` (refactored)
  - `src/Modules/Schema/Types/Concrete/ServiceSchema.php` (new)
  - `tests/Unit/Schema/LocalCommercialSchemaTest.php` (new)
- **Done when:**
  - Selecting "Dentist" under LocalBusiness generates `@type: Dentist` with full address and geo coordinates.
  - Selecting "Restaurant" generates `@type: Restaurant` with cuisine and menu links.

---

#### TASK-SCH-06: Critique & Structured Lists (`Review`, `AggregateRating`, `ItemList`)
- **Status:** `- [ ] Not Started`
- **Objective:** Support standalone product/book/movie critique reviews and structured list items.
- **Requirements:**
  1. Create `ReviewSchema.php`:
     - Output `@type: Review`.
     - Fields: `itemReviewed` (`@type: Thing`), `reviewRating` (`ratingValue`, `bestRating`, `worstRating`), `reviewBody`, `author`.
  2. Create `ItemListSchema.php`:
     - Supports carousel/ranked list structures (`itemListElement` array of `ListItem`).
- **Files to touch:**
  - `src/Modules/Schema/Types/Concrete/ReviewSchema.php` (new)
  - `src/Modules/Schema/Types/Concrete/ItemListSchema.php` (new)
  - `tests/Unit/Schema/ReviewAndListSchemaTest.php` (new)
- **Done when:**
  - Post configured as a review outputs Google-compliant `Review` markup displaying stars in search results.

---

#### TASK-SCH-07: Schema Stack Editor React UI (`editor.js`)
- **Status:** `- [ ] Not Started`
- **Objective:** Provide an intuitive schema type picker and visual form in the Gutenberg block editor sidebar.
- **Requirements:**
  1. In `src/assets/js/editor.js`, replace the current limited schema dropdown with a searchable 22-type schema catalog.
  2. Render conditional form fields matching the selected schema entity (e.g., selecting "Recipe" renders Cook Time, Prep Time, Ingredients repeater; selecting "JobPosting" renders Salary, Hiring Org, Employment Type).
  3. Save schema payload directly to post meta `_quinrel_schema_stack` as structured JSON.
- **Files to touch:**
  - `src/assets/js/editor.js`
  - `src/Modules/Admin/AdminModule.php` (meta registration)
- **Done when:**
  - Selecting any of the 22 schema types in the editor dynamically mounts the corresponding field inputs.
  - Saving the post updates the schema footer output instantly on preview.

---

#### TASK-ACF-01: ACF & Custom Field Deep Content Scanner
- **Status:** `- [ ] Not Started`
- **Objective:** Scan Advanced Custom Fields (ACF) and Meta Box field values so custom theme content is included in SEO scoring and internal link suggestions.
- **Requirements:**
  1. Create `src/Modules/ContentAssistant/CustomFieldScanner.php`.
  2. Detect if ACF (`class_exists('ACF')`) or Meta Box is active.
  3. Extract text content from supported field types: `text`, `textarea`, `wysiwyg`, and nested subfields in `repeater` / `flexible_content`.
  4. Inject aggregated custom field text into:
     - `WordComplexityScorer.php` (readability score)
     - `MorphologicalKeywordMatcher.php` (focus keyword density)
     - `InternalLinkEngine.php` (link indexing)
- **Files to touch:**
  - `src/Modules/ContentAssistant/CustomFieldScanner.php` (new)
  - `src/Modules/ContentAssistant/ContentAssistantModule.php`
  - `tests/Unit/ContentAssistant/CustomFieldScannerTest.php` (new)
- **Done when:**
  - A post with empty standard body content but 1,000 words in ACF flexible content fields correctly receives an accurate readability and keyword score instead of a "Content too short" warning.

---

#### TASK-ACF-02: ACF Smart Tag Resolver (`%%acf:field_name%%`)
- **Status:** `- [ ] Not Started`
- **Objective:** Allow dynamic inclusion of ACF field values in SEO title and meta description templates.
- **Requirements:**
  1. Extend `src/Modules/Seo/SmartTagResolver.php`.
  2. Parse pattern `%%acf:([a-zA-Z0-9_\-]+)%%`.
  3. If ACF is active, retrieve field value via `get_field( $field_name, $postId )`.
  4. Sanitize and replace tag with string representation of the field value.
- **Files to touch:**
  - `src/Modules/Seo/SmartTagResolver.php`
  - `tests/Unit/Seo/SmartTagResolverTest.php`
- **Done when:**
  - Template `%%title%% - %%acf:subtitle%% | %%sitename%%` replaces `%%acf:subtitle%%` with the post's ACF `subtitle` value in the rendered `<title>`.

---

#### TASK-ACF-03: ACF Schema Property Binding Interface
- **Status:** `- [ ] Not Started`
- **Objective:** Allow binding Schema properties directly to custom field slugs.
- **Requirements:**
  1. Allow schema property values to reference `acf:field_name`.
  2. During JSON-LD compilation, resolve referenced ACF fields dynamically.
  3. Support nested properties (e.g., binding Event `location.name` to ACF `venue_name`).
- **Files to touch:**
  - `src/Modules/Schema/SchemaGenerator.php`
  - `tests/Unit/Schema/AcfSchemaBindingTest.php` (new)
- **Done when:**
  - Schema configured with `offers.price` = `acf:custom_product_price` outputs the actual ACF value in JSON-LD.

---

### Phase 3: Advanced WooCommerce Suite & Gutenberg Schema Blocks

#### TASK-WOO-01: WooCommerce Product Identifiers (`GTIN`, `MPN`, `Brand`)
- **Status:** `- [ ] Not Started`
- **Objective:** Add standard e-commerce identifiers natively to WooCommerce products for Google Merchant / Rich Snippets compliance.
- **Requirements:**
  1. In `src/Modules/Schema/WooCommerceProductSchema.php`, hook into WooCommerce's product data tabs (`woocommerce_product_options_inventory_product_data`).
  2. Add inputs for:
     - `_quinrel_gtin` (GTIN-8, GTIN-12/UPC, GTIN-13/EAN, or GTIN-14/ISBN).
     - `_quinrel_mpn` (Manufacturer Part Number).
     - `_quinrel_brand` (Brand name or custom taxonomy mapping).
  3. Map these fields directly into Schema.org `Product` node: `gtin`, `mpn`, `brand` (`@type: Brand`, `name`).
- **Files to touch:**
  - `src/Modules/Schema/WooCommerceProductSchema.php`
  - `src/Modules/Admin/TaxonomySeo.php`
  - `tests/Unit/Schema/WooCommerceProductIdentifiersTest.php` (new)
- **Done when:**
  - Editing a WooCommerce product displays GTIN, MPN, and Brand fields.
  - Rendered product schema includes `gtin`, `mpn`, and `brand` properties.

---

#### TASK-WOO-02: WooCommerce Core Schema Conflict Resolver & Clean Replacement
- **Status:** `- [ ] Not Started`
- **Objective:** Cleanly suppress WooCommerce's default schema to prevent duplicate, conflicting, or invalid `Product` nodes.
- **Requirements:**
  1. When Quinrel WooCommerce SEO is enabled, remove WooCommerce's default structured data filter:
     - `remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 )`
  2. Ensure Quinrel outputs the single authoritative, complete `@type: Product` node within the unified `@graph`.
  3. Include WooCommerce breadcrumbs into Quinrel's `BreadcrumbList` schema.
- **Files to touch:**
  - `src/Modules/Schema/WooCommerceProductSchema.php`
  - `tests/Unit/Schema/WooCommerceSchemaCleanupTest.php` (new)
- **Done when:**
  - Inspecting a WooCommerce product page reveals exactly one `@type: Product` JSON-LD block.
  - Zero duplicate schema warnings in Google Search Console / Rich Results Test.

---

#### TASK-WOO-03: WooCommerce `priceValidUntil`, Sale Conditions & Product Taxonomy Meta
- **Status:** `- [ ] Not Started`
- **Objective:** Output sale expiration dates and custom SEO metadata for Product Categories and Tags.
- **Requirements:**
  1. On products on sale with scheduled end dates, output `priceValidUntil: YYYY-MM-DD` in `Offer`.
  2. Enable custom SEO Title, Description, Robots (noindex/follow), and Social Meta on WooCommerce `product_cat` and `product_tag` taxonomy edit screens.
- **Files to touch:**
  - `src/Modules/Schema/WooCommerceProductSchema.php`
  - `src/Modules/Admin/TaxonomySeo.php`
  - `tests/Unit/Schema/WooCommerceTaxonomySeoTest.php` (new)
- **Done when:**
  - Scheduled sale product outputs `priceValidUntil` matching WooCommerce sale end date.
  - Product Category archives render Quinrel's custom meta title and description.

---

#### TASK-WOO-04: WooCommerce Product Gallery XML Sitemap Integration
- **Status:** `- [ ] Not Started`
- **Objective:** Ensure all WooCommerce product gallery images are indexed in XML sitemaps.
- **Requirements:**
  1. In `src/Modules/Sitemap/SitemapGenerator.php`, when querying `product` post types, extract all gallery image IDs from `_product_image_gallery`.
  2. Add `<image:image><image:loc>...</image:loc><image:title>...</image:title></image:image>` nodes to the sitemap entry.
- **Files to touch:**
  - `src/Modules/Sitemap/SitemapGenerator.php`
  - `tests/Unit/Sitemap/WooCommerceSitemapTest.php` (new)
- **Done when:**
  - Product sitemap XML includes both featured image and all gallery images under `<image:image>` tags.

---

#### TASK-BLK-01: Native Gutenberg FAQ Accordion Block (`quinrel/faq`)
- **Status:** `- [ ] Not Started`
- **Objective:** Provide a native Gutenberg FAQ block that renders front-end accordions and automatically registers `FAQPage` schema.
- **Requirements:**
  1. Create block `quinrel/faq` in `src/Blocks/Faq/`:
     - InnerBlocks structure allowing multiple Question & Answer items.
     - Accessible HTML5 `<details><summary>` markup or accessible accordion with ARIA attributes.
  2. In server-side render (`render.php`), collect questions/answers and register them with `SchemaGenerator`:
     - Adds `@type: FAQPage` to the page's JSON-LD graph.
- **Files to touch:**
  - `src/Blocks/Faq/block.json` (new)
  - `src/Blocks/Faq/index.js` (new)
  - `src/Blocks/Faq/render.php` (new)
  - `src/Plugin.php` (register block type)
  - `tests/Unit/Blocks/FaqBlockTest.php` (new)
- **Done when:**
  - Adding a Quinrel FAQ block in the editor displays on the front end as a styled accordion.
  - Page footer automatically includes `@type: FAQPage` schema matching the block's questions and answers.

---

#### TASK-BLK-02: Native Gutenberg HowTo Block (`quinrel/howto`)
- **Status:** `- [ ] Not Started`
- **Objective:** Provide a native HowTo tutorial block with automatic schema output.
- **Requirements:**
  1. Create block `quinrel/howto` in `src/Blocks/HowTo/`:
     - Attributes: `totalTime`, `estimatedCost`, `supplies` (array), `tools` (array), `steps` (array of step title, text, image).
  2. Server render produces responsive step layout and injects `@type: HowTo` into `SchemaGenerator`.
- **Files to touch:**
  - `src/Blocks/HowTo/block.json` (new)
  - `src/Blocks/HowTo/index.js` (new)
  - `src/Blocks/HowTo/render.php` (new)
  - `tests/Unit/Blocks/HowToBlockTest.php` (new)
- **Done when:**
  - Adding a HowTo block outputs clean tutorial HTML and valid Schema.org `HowTo` JSON-LD.

---

#### TASK-BLK-03: Native Gutenberg Review/Rating Card Block (`quinrel/review`)
- **Status:** `- [ ] Not Started`
- **Objective:** Provide a visual product/critique review card with star ratings and `Review` schema.
- **Requirements:**
  1. Create block `quinrel/review` in `src/Blocks/Review/`:
     - Attributes: `itemName`, `ratingValue` (1–5 in 0.5 increments), `pros` (array), `cons` (array), `summary`, `affiliateButton` (text, url).
  2. Front-end renders visual card; server render attaches `@type: Review` to JSON-LD graph.
- **Files to touch:**
  - `src/Blocks/Review/block.json` (new)
  - `src/Blocks/Review/index.js` (new)
  - `src/Blocks/Review/render.php` (new)
  - `tests/Unit/Blocks/ReviewBlockTest.php` (new)
- **Done when:**
  - Review block renders visual stars and pros/cons list on front end with matching `Review` schema.

---

#### TASK-BLK-04: Native Gutenberg Table of Contents Block (`quinrel/toc`)
- **Status:** `- [ ] Not Started`
- **Objective:** Automatically parse headings (H2, H3, H4) in post content and output a nested table of contents.
- **Requirements:**
  1. Create block `quinrel/toc` in `src/Blocks/Toc/`:
     - Dynamic front-end parser that injects anchor IDs to post headings and renders clickable anchor navigation.
  2. Support smooth scrolling and schema `ItemList` output.
- **Files to touch:**
  - `src/Blocks/Toc/block.json` (new)
  - `src/Blocks/Toc/index.js` (new)
  - `src/Blocks/Toc/render.php` (new)
  - `tests/Unit/Blocks/TocBlockTest.php` (new)
- **Done when:**
  - Inserting block dynamically lists all post headings with working in-page anchor links.

---

#### TASK-REF-01: React SPA Modularization (`App.js` & `editor.js` Component Splitting)
- **Status:** `- [ ] Not Started`
- **Objective:** Deconstruct the monolithic React files (`App.js` 4.2k lines, `editor.js` 2.7k lines) into maintainable modules.
- **Requirements:**
  1. Split `src/assets/js/App.js` into modular components under `src/assets/js/components/`:
     - `DashboardTab.js`, `AiGovernanceTab.js`, `SchemaTab.js`, `RedirectsTab.js`, `SettingsTab.js`.
  2. Split `src/assets/js/editor.js` into:
     - `EditorSidebar.js`, `SnippetPreview.js`, `ReadabilityPanel.js`, `SchemaSelector.js`, `InternalLinkSuggester.js`.
  3. Rebuild assets via `@wordpress/scripts` (`npm run build`).
- **Files to touch:**
  - `src/assets/js/components/*.js` (new)
  - `src/assets/js/App.js`
  - `src/assets/js/editor.js`
- **Done when:**
  - `npm run build` succeeds without warnings.
  - Admin SPA and editor sidebar function identically with identical bundle behavior.

---

### Phase 4: Off-Page Growth & Outreach Workflow

#### TASK-LNK-01: Unlinked Brand Mention Discovery via GSC Query Cross-Referencing
- **Status:** `- [ ] Not Started`
- **Objective:** Surface backlink acquisition opportunities by comparing search console impressions for brand keywords against inbound linking pages.
- **Requirements:**
  1. In `src/Modules/Analytics/GoogleSearchConsoleIntegration.php`, fetch top 1,000 queries over the past 90 days.
  2. Filter queries matching site name, brand terms, or author names configured in Settings.
  3. Identify external queries where landing page is not an exact match or external domains discussing the brand.
- **Files to touch:**
  - `src/Modules/ContentAssistant/BrandMentionFinder.php` (new)
  - `src/Modules/Analytics/GoogleSearchConsoleIntegration.php`
  - `tests/Unit/ContentAssistant/BrandMentionFinderTest.php` (new)
- **Done when:**
  - Admin UI displays list of brand query opportunities with impression counts.

---

#### TASK-LNK-02: Broken External Backlink Reclamation Monitor
- **Status:** `- [ ] Not Started`
- **Objective:** Turn 404 logs with external referrers into a 1-click backlink recovery engine.
- **Requirements:**
  1. In `src/Modules/TechnicalSeo/ErrorMonitor.php`, filter 404 entries where `referer` is external (not matching `home_url()`).
  2. Highlight high-traffic external 404s in the Link Building dashboard.
  3. Provide a 1-click action to create a 301 redirect to the closest active page or home.
- **Files to touch:**
  - `src/Modules/TechnicalSeo/ErrorMonitor.php`
  - `src/Modules/TechnicalSeo/RedirectManager.php`
  - `src/assets/js/App.js`
  - `tests/Unit/TechnicalSeo/BacklinkReclamationTest.php` (new)
- **Done when:**
  - External 404s show a "Reclaim Link" button.
  - Clicking button creates 301 redirect and marks 404 log as resolved.

---

#### TASK-LNK-03: Outreach Workflow Database & Admin CRM Table (`quinrel_outreach`)
- **Status:** `- [ ] Not Started`
- **Objective:** Provide a lightweight backlink outreach tracker inside WordPress.
- **Requirements:**
  1. Create database table `{prefix}quinrel_outreach`:
     - `id` (bigint auto)
     - `target_domain` (varchar 255)
     - `target_url` (text)
     - `contact_email` (varchar 255)
     - `contact_name` (varchar 100)
     - `pitch_status` (enum: `prospect`, `pitched`, `followup`, `won`, `declined`)
     - `suggested_anchor` (varchar 255)
     - `target_post_id` (bigint)
     - `created_at`, `updated_at` (datetime)
  2. Provide REST endpoints for CRUD operations (`/wp-json/quinrel/v1/outreach`).
  3. Build responsive React management table in Quinrel admin.
- **Files to touch:**
  - `src/Database/Installer.php` (table definition)
  - `src/Modules/ContentAssistant/OutreachManager.php` (new)
  - `src/assets/js/App.js`
  - `uninstall.php` (table drop on clean uninstall)
  - `tests/Unit/ContentAssistant/OutreachManagerTest.php` (new)
- **Done when:**
  - Table installs cleanly during activation.
  - Users can add, edit, filter, and change prospect status in the UI.

---

#### TASK-LNK-04: AI Pitch Draftsman via `AiGateway`
- **Status:** `- [ ] Not Started`
- **Objective:** Generate customized, non-spammy outreach email pitches using OpenAI, Anthropic, or local Ollama.
- **Requirements:**
  1. Create prompt template in `src/Modules/ContentAssistant/AiPitchDraftsman.php`.
  2. Accept input: `target_article_url`, `target_topic`, `our_article_id`, `value_proposition`.
  3. Dispatch prompt via `AiGateway` requesting:
     - Concise subject line
     - Personalized opening acknowledging the target article
     - Contextual justification for adding a link
     - Low-friction call to action
  4. Return draft directly to outreach CRM modal with 1-click "Copy to Clipboard" or "Open in Mail Client (`mailto:`)".
- **Files to touch:**
  - `src/Modules/ContentAssistant/AiPitchDraftsman.php` (new)
  - `src/Modules/Ai/AiGateway.php`
  - `src/assets/js/App.js`
  - `tests/Unit/ContentAssistant/AiPitchDraftsmanTest.php` (new)
- **Done when:**
  - Clicking "Generate Pitch" drafts a contextual email body based on the selected post.
