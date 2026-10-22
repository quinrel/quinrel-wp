# Privacy Compliance, GPC & AI Governance Playbook

In an evolving global regulatory landscape, SEO and website discovery tools must maintain strict privacy safeguards. Quinrel is engineered with privacy-by-design principles, offering native compliance with CCPA/CPRA, GDPR, Global Privacy Control (GPC), and modern AI training reservation standards.

---

## 1. Global Privacy Control (GPC) & CCPA/CPRA

The **Global Privacy Control (GPC)** is a browser-level signal (`Sec-GPC: 1` HTTP header and `navigator.globalPrivacyControl` JavaScript DOM property) that conveys a user's legal request to opt out of the sale or sharing of their personal data.

### How Quinrel Handles GPC:
1. **Server-Side Header Evaluation:** On every incoming web request, Quinrel inspects whether the client transmitted `Sec-GPC: 1`.
2. **Action Trigger:** When detected, Quinrel automatically executes the WordPress action:
   ```php
   do_action( 'quinrel_gpc_opt_out' );
   ```
3. **Cookie Banner Suppression:** If the Quinrel Cookie Banner module is active, it automatically marks marketing and analytics cookie categories as **Rejected** for that session without requiring the visitor to interact with a modal.
4. **Zero Script Injection:** Tracking pixels or marketing analytics tags hooked to Quinrel are withheld from the response payload.

---

## 2. WordPress Core Privacy Integration (GDPR)

Quinrel hooks directly into native WordPress core privacy mechanisms under **Tools > Export Personal Data** and **Tools > Erase Personal Data**.

### Personal Data Export
When an administrator processes a GDPR Data Subject Access Request (DSAR):
* Quinrel exports any identifiable data associated with the user's email address, including:
  * Link building and outreach CRM records containing the contact's name or email.
  * User activity logs in the Technical SEO Audit history.
  * Saved preferences in user meta.

### Personal Data Erasure
When a GDPR Right to Be Forgotten erasure request is executed:
* Quinrel scrubs corresponding email addresses and contact names from the Outreach CRM database table (`quinrel_outreach_prospects`).
* Anonymizes any corresponding audit log entries while maintaining statistical referential integrity.

---

## 3. AI Training Rights Reservation (EU Copyright Directive Article 4)

Under Article 4 of the European Union Directive on Copyright in the Digital Single Market (Directive 2019/790), web publishers are permitted to reserve the rights to text and data mining (TDM) provided such reservation is expressed in a machine-readable manner.

Quinrel implements this requirement through dual protocols:

### A. HTTP Transport Headers
Every HTML response, media file, and document served can emit:
```http
X-Robots-Tag: noai, noimageai
```
This ensures proxies, caches, and headless API consumers inherit the copyright reservation even if HTML `<meta>` tags are stripped.

### B. In-Document Semantic Directives
Within the `<head>` of all HTML responses:
```html
<meta name="robots" content="noai, noimageai" />
```

### C. Crawler Shield Directives (`robots.txt`)
Quinrel dynamically configures `Disallow: /` directives for identified AI training bots:
* `GPTBot` (OpenAI training)
* `CCBot` (Common Crawl foundation corpus)
* `Bytespider` (ByteDance / TikTok LLMs)
* `ClaudeBot` / `Anthropic-AI` (Anthropic training)
* `cohere-ai` (Cohere models)

---

## 4. Data Minimization & Bounded Retention

Quinrel does not collect telemetry, analytics, or user behavior data unless explicitly configured by the site administrator:

* **Bounded 404 Logs:** Retains a maximum of 1,000 unique records; older non-recurring errors are automatically purged by scheduled WP-Cron events.
* **No Cloud Transmissions by Default:** All keyword rankings, schema generation, and readability calculations execute locally on your WordPress server. No page contents are transmitted to external services without an explicit API key configured by the administrator.
* **Tenant Isolation in Multisite:** In WordPress Multisite (WPMU) installations, each subsite's redirect tables, outreach prospects, and schema configurations are strictly isolated by site ID (`$wpdb->prefix`).

