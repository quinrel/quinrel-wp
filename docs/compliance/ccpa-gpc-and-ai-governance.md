# Privacy Compliance, GPC & AI Governance Playbook

Quinrel can record a visitor's cookie choice, and it can honor a Global Privacy Control header when that option is on. The cookie notice does not block third-party scripts and is not a consent-management platform. The site owner remains responsible for legal compliance. Quinrel does not make a site compliant with a privacy law.

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

## 3. AI training opt-out

The training opt-out stays off until an administrator turns it on, or a single post is marked opted out. When it is on, Quinrel adds these tags to the HTML response:

```html
<meta name="robots" content="noai, noimageai" />
<meta name="tdm-reservation" content="1" />
```

On the paths that support the header, Quinrel can also send `X-Robots-Tag: noai, noimageai`. A normal page does not emit these tags while the opt-out is off.

AI crawler rules in `robots.txt` are a separate switch. They stay off until an administrator turns them on. The screen names the crawlers, including GPTBot, ClaudeBot, and PerplexityBot. The site owner remains responsible for any legal reservation. Quinrel does not make a site compliant with a copyright or privacy law.

`llms.txt`, schema, the crawler rules, and the visit count stay on this site. They do not call `https://api.quinrel.com`. The visit count is kept for 30 days after the latest visit, and only when the request reaches WordPress.

---

## 4. Data Minimization & Bounded Retention

Quinrel does not collect telemetry, analytics, or user behavior data unless explicitly configured by the site administrator:

* **Bounded 404 Logs:** Retains a maximum of 1,000 unique records; older non-recurring errors are automatically purged by scheduled WP-Cron events.
* **No cloud transmission for the on-site reading tools:** `llms.txt`, schema, AI crawler rules, and the crawler visit count stay on this site. Query movement is read from Google Search Console after you connect it. A live rank check uses a SerpApi key. Page contents are sent out only when an editor runs the writing assistant or content gap analysis, or when another service in [third-party services](../third-party-services.md) is turned on.
* **Tenant Isolation in Multisite:** In WordPress Multisite (WPMU) installations, each subsite's redirect tables, outreach prospects, and schema configurations are strictly isolated by site ID (`$wpdb->prefix`).

