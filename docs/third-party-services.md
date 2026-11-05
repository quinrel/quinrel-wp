# Third-Party Services & Data Disclosures

Quinrel does not send usage statistics to Quinrel. A normal page view does not call an external service.

The services below run only after an administrator saves a credential and uses the feature. For every service, nothing is sent until an administrator turns the feature on. Say so in the site's privacy policy. Quinrel adds a note to the WordPress privacy policy guide.

If you utilize these features, you should disclose them in your site's Privacy Policy. Quinrel automatically injects a template for this into the WordPress Privacy Policy Guide.

## What stays on this site

Publishing `llms.txt`, printing schema, writing AI crawler rules, and counting AI crawler visits do not leave the site. They do not send the page, the site address, or the visit record to `https://api.quinrel.com`, to OpenAI, to Anthropic, or to an answer engine. The visit count is kept here for 30 days after the latest visit. A page cache that serves HTML without running PHP does not record that hit. AI crawler rules and the `noai` / `noimageai` tag stay off until an administrator turns them on.

## Services That May Receive Data

| Feature | External Service | Data Transmitted | Privacy Policy |
| :--- | :--- | :--- | :--- |
| **LLM Writing Assistant** | OpenAI API (api.openai.com) | When explicitly triggered by an editor, the current post title, meta description, or content excerpts are sent to generate suggestions. | [OpenAI Privacy](https://openai.com/privacy) |
| **LLM Writing Assistant** | Anthropic API (api.anthropic.com) | Same as above, if Anthropic is selected instead of OpenAI. | [Anthropic Privacy](https://www.anthropic.com/privacy) |
| **Keyword Rank Tracker** | Google Search Console, then SerpApi (serpapi.com) for a live check | Query movement is read from Search Console after connect. Focus keywords are sent to SerpApi only when a key is saved and a live check runs. | [SerpApi Privacy](https://serpapi.com/privacy) |
| **PageSpeed Dashboard** | Google PageSpeed API | The URLs of your posts/pages are transmitted to fetch Core Web Vitals. | [Google Privacy](https://policies.google.com/privacy) |
| **Search Console** | Google Search Console API | Read-only OAuth2 access is used to fetch impressions, clicks, and CTR data. No data is written to Google. | [Google Privacy](https://policies.google.com/privacy) |
| **Bing Webmaster** | Bing Webmaster API (`ssl.bing.com`) | After an administrator saves an API key and opens the Bing section, this site's address is sent and the last 28 days of clicks and impressions are read. Quinrel does not submit pages or sitemaps. | [Microsoft Privacy](https://privacy.microsoft.com/) |
| **IndexNow** | IndexNow (`api.indexnow.org`, Bing, Yandex, Seznam) | After an administrator turns IndexNow on and saves a key, published URLs are queued and sent in small batches only to those allow-listed endpoints. IndexNow does not guarantee indexing. Quinrel does not create a key, and nothing is sent during the publish request. | [IndexNow Privacy](https://www.indexnow.org/) |
| **Google Indexing API** | Google Indexing API | If an administrator turns it on, a published URL is sent only for an eligible JobPosting or livestream page. It does not guarantee indexing. | [Google Privacy](https://policies.google.com/privacy) |
| **Quinrel API** | Quinrel (api.quinrel.com) | If you connect Quinrel, requests go only to `https://api.quinrel.com`. The settings screen cannot change that host. | [Quinrel Privacy](https://quinrel.com/privacy) |

## Data Retention & Storage
- **Stored credentials:** When the PHP OpenSSL extension is available, API keys, access tokens, and the Quinrel license string are encrypted in the WordPress options table. The key is derived from the option `quinrel_encryption_salt`. Settings export leaves out that salt, indexing credentials, the IndexNow key, API keys, and OAuth secrets. Import does not write them. A file that contains an unrecognized setting is rejected before any value is saved. A full database copy still contains the salt and the ciphertext, so that copy can be decrypted. Encryption does not protect a stolen database. If OpenSSL is missing, Quinrel shows an administrator notice and does not save those secrets.
- **Ollama:** The default address is `http://localhost:11434` on this server, and that traffic stays on the machine. If an administrator confirms a remote http or https host, Quinrel sends the prompt, which can include the post title and post content, to that host. A settings import cannot switch Ollama to a remote host.
- **404 Logs:** Missing URLs are logged in your local WordPress database to assist in redirect creation. By default, these logs are automatically purged after 90 days.
- **Audit Logs:** SEO setting changes are recorded in a local audit log for accountability. These are automatically purged after 365 days.
