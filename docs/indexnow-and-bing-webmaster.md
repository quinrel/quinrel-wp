# IndexNow and Bing Webmaster

Status: done. Tasks 1–9 passed their unit checks on PHP 8.2.4. PHPCS on the four touched PHP files exited 0. The UAT settings read returned a key file, and both Bing traffic calls with no key returned `bing_unconfigured` at status 400. The Indexing and Bing settings panels rendered in the browser with no console error and no request to `ssl.bing.com`. A live IndexNow submission and a populated Bing grid were not run. Save stays off until a settings read succeeds, so the initial empty IndexNow field is not posted. After a successful save, a newly typed Bing key is replaced by the stored mask and the open Bing tab requests traffic.

This makes two existing features usable inside Quinrel:

- IndexNow queues a public URL and sends it to `https://api.indexnow.org/indexnow` after an administrator turns it on.
- Bing Webmaster shows the last 28 days of clicks and impressions for this site after an administrator saves an API key.

No new admin page. No Quinrel account. No Bing OAuth. No chart library.

## UI bar

Apply this to every screen change in this document. The settings hub is already the product UI. Match it.

- Change `src/assets/js/components/GlobalSettingsHub.js` for these two sections. Do not add a page, a wizard, a tour, a modal, a chart, an icon font, or a new CSS file.
- Keep the section shell already on the tab: a white card, `border-slate-200`, `rounded-2xl`, `p-6`. One `h3` at `text-base font-bold text-slate-900`. One help sentence under it at `text-xs text-slate-500`.
- One primary button per section. Reuse the button classes already in that file. `type="button"` unless the control really submits a form.
- Status text uses the existing alert: emerald border and text for success, rose or red for failure. `role="status"` for a finished load. `role="alert"` for a failure. `text-xs`. No emoji. No checkmark list.
- Bing totals use the same grid as the Search Console 28-day snapshot in this file: `grid grid-cols-2 lg:grid-cols-4`. Three cells at most: Clicks, Impressions, and the change when the previous window exists. No sparkline.
- Loading is one sentence inside the section: “Loading Bing performance…” Fetch only while that settings tab is open.
- An empty state is one sentence plus the field the person still has to fill.
- Labels come from `TextControl` and `ToggleControl`. Do not add a placeholder as the only label.
- Every new string uses `__()` and the text domain `quinrel`. A string with `%s` or `%d` gets a translator comment.
- Do not write “seamless”, “powerful”, “unlock”, “supercharge”, “instant”, “Site Kit”, or “1 click”.
- A failure is a sentence. A red box alone is not the message.

`App.js` `fetchSettings` replaces the whole settings object. A new settings field has to be added to the initial `useState` and to the object inside `fetchSettings`. Bing totals and the IndexNow log stay in component state. They must not be copied onto the settings object, because Save posts that object.

Rebuild with `npm run build` after any change under `src/assets/js/`. Copy changed files to the UAT plugin one file at a time with Python `shutil.copy2`. Do not rsync a directory.

## Locked sentences

`tests/Unit/FeatureCopyTest.php` requires these to remain:

- `nothing is sent until an administrator turns the feature on` in `readme.txt`
- `IndexNow` in `readme.txt`, `docs/features.md`, and `src/assets/js/App.js`
- `Requires PHP: 8.2` and `Tested up to: 7.1`
- The readme must not contain `guaranteed indexing` or `ranking improvement`

`docs/features.md` and `readme.txt` share the IndexNow and Bing paragraphs. Edit them together.

## Task 1. Activation leaves IndexNow off

Status: done. `IndexNowSubmissionTest` (10 tests, 194 assertions) includes `testPluginActivationLeavesIndexNowOff`.

Priority: first. A new site must not notify IndexNow before an administrator turns it on.

`Plugin::activate()` in `src/Plugin.php` writes `quinrel_indexnow_key` and sets `quinrel_auto_index` to `yes` when the key option is empty. Remove that block. Do not generate a key anywhere else on activation.

`BingIndexNowApi::isEnabled()` stays `'yes' === get_option( 'quinrel_auto_index', 'no' )` and a stored key that passes `isValidKey()`. A missing option stays off.

Replace `IndexNowSubmissionTest::testPluginActivationAutoGeneratesIndexNowKeyWhenUnset`. After `Plugin::activate()` with both options deleted, `quinrel_indexnow_key` is unset and `quinrel_auto_index` is not `yes`.

### Acceptance

- A clean activation does not write `quinrel_indexnow_key`.
- A clean activation does not set `quinrel_auto_index` to `yes`.
- An existing site that already has the option set to `yes` is left alone. Do not run a migration that turns sites off.
- Publishing a public post with the option missing does not call IndexNow and does not add a queue row.

### QA

- Run `IndexNowSubmissionTest`. The old test name must not remain as an assertion that activation creates a key.
- In the unit harness, call `autoSubmitOnPublish()` with `quinrel_auto_index` deleted and assert `mock_http_log` is empty and the queue option is empty.

## Task 2. The Indexing tab shows the key and the key file

Status: done. The same IndexNow suite covers keep, omit, clear, and invalid key. On UAT the settings read returned a `.txt` key-file URL whose basename matched the stored key.

Priority: second. The field is blank today because `AdminModule::getSettings()` omits `indexnow_key`, and `fetchSettings` forces `indexnow_key` to `''`.

Return these from `getSettings()` to the same capability that can already read settings:

- `indexnow_key`: the stored key when `BingIndexNowApi::isValidKey()` accepts it, otherwise `''`.
- `indexnow_key_url`: `home_url( '/' . $key . '.txt' )` when the key is valid, otherwise `''`.

Hydrate both in the initial settings state and in `fetchSettings`.

`updateSettings()` today writes the key only when the posted value is non-empty. That was safe while the client always posted `''`. After the key is hydrated, change it as follows:

- A posted key that passes `isValidKey()` is saved.
- A posted key that is present and empty deletes `quinrel_indexnow_key`.
- A request that omits `indexnow_key` leaves the stored key alone.
- An invalid non-empty key still returns `invalid_indexnow_key` and does not write later fields. That rejection already exists. Keep it.

Show the key in the existing IndexNow `TextControl`. Under it, when `indexnow_key_url` is non-empty, show the address as a link (`esc` is React text, so render it as text inside an `<a href>` built from that same string). The link text is the URL. The sentence beside it is “IndexNow fetches this file to verify the key.”

The Generate button keeps putting a new 32-character key into local state and tells the person to save. It does not call the REST API by itself.

### Acceptance

- Reloading settings shows the saved key and the key-file URL.
- Saving an unrelated setting, with the key still in the form, keeps the same key.
- Clearing the field and saving removes the option. `isEnabled()` is then false even if `quinrel_auto_index` is `yes`.
- An invalid key rejects the save and leaves the previous key and the breadcrumb label unchanged. `testSettingsRejectAnInvalidKeyAndDoNotInventOne` still passes.
- The key file for a valid key still returns the key as `text/plain` at the home path plus `/{key}.txt`.

### QA

- PHPUnit: extend `IndexNowSubmissionTest` or `SettingsTransfer` coverage so a settings payload with `indexnow_key: ''` deletes the option, and a payload that omits the key does not.
- Browser, Indexing tab: save a generated key, reload, confirm the field and the link match. Open the link in the same browser and confirm the body is only the key. Save a different settings tab and confirm the key is still there. Clear the field, save, reload, and confirm the field is empty and the link is gone.
- Keyboard: the key field, Generate, the toggle, and the key-file link are reachable in that order. Focus is visible.

## Task 3. The test URL control queues IndexNow

Status: done. `testManualSubmitQueuesASameSiteUrlAndRejectsTheOthers` covers the same-site queue, the disabled flag, a foreign host, and the editor post-ID path.

Priority: third. “Test Instant URL Dispatch” posts `{ url }` to `/quinrel/v1/index-url`. `AdminModule::manualIndexUrl()` requires `post_id` and ignores `url`, so the button cannot succeed.

Point `handleTestManualIndex` at `POST /quinrel/v1/indexing/indexnow-submit` with JSON `{ "url": "<trimmed value>" }`. That route already validates the URL, checks `isEnabled()`, and queues.

The block editor keeps posting `{ post_id }` to `/index-url`. Do not change that contract.

Rename the section heading to “Send a test URL”. The help sentence is “Queues one URL from this site. IndexNow is notified in the background after you turn notifications on and save a key.” The button label is “Queue URL”. The success alert repeats the API message. It must say the URL was queued. It must not say Bing has crawled the page.

### Acceptance

- With notifications on and a valid key, a same-site `https` URL returns 200, `queued: true`, and the URL is in `quinrel_indexnow_queue`.
- With notifications off, the response is `indexnow_disabled` at status 400 and the queue is unchanged.
- A URL on another host is `indexnow_not_queued` at status 400.
- The editor control still sends `post_id` and still queues that post’s permalink when IndexNow is on.

### QA

- PHPUnit already covers `manualSubmit()`. Add one assertion if the disabled and foreign-host cases are not both present.
- Browser: turn IndexNow on, save a key, submit the home URL, and confirm the success alert. Turn it off, submit again, and confirm the error alert names the missing switch. Submit `https://example.com/` and confirm the error alert. Tab to the field and the button without a mouse.

## Task 4. The Indexing tab shows recent submissions

Status: done. `testIndexNowLogRouteReturnsStoredRows` covers the log route. On UAT the Indexing panel showed the log help and the queue control.

Priority: fourth. `GET /quinrel/v1/indexing/indexnow-log` already returns the log. Nothing in the admin reads it.

When the Indexing settings tab is the open tab, fetch that route once. Keep the rows in component state. Show at most 10. Each row shows the URL, the stored time, and the status code.

Wording:

- `200` or `202`: “Accepted”.
- Any other code, including `0`: “Not accepted”, with the code in the same sentence.
- No rows: “No IndexNow submissions yet. Publish a public post, or queue a test URL below.”

The heading is “Recent IndexNow submissions”. One sentence under the list: “Accepted means the IndexNow hub took the batch. It does not mean the page was crawled or indexed.”

Do not poll. A successful queue from Task 3 refetches the log once.

### Acceptance

- The log request runs only after the Indexing tab is opened, and it sends the REST nonce.
- A user who cannot `manage_seo` or `manage_options` gets the existing 401 or 403 and the panel shows the permission failure in a sentence.
- More than 10 stored rows still render 10.
- The panel does not claim a crawl or an index.

### QA

- Unit: the log route callback returns the stored rows and the permission callback is unchanged. A render test is not required.
- Browser: queue a URL, confirm a new row appears without a full reload. Confirm a network failure shows `role="alert"` and the rest of the settings hub still saves. Narrow the window to 375px and confirm the row wraps instead of covering the next section.

## Task 5. IndexNow copy matches the sender

Status: done. `FeatureCopyTest` and `MigrationTest::testReadmeDescribesTheImportPreview` passed (6 tests, 164 assertions). The Indexing panel does not say “instant”, “Naver”, or that a delete is sent.

Priority: fifth. Ship it with Tasks 2 through 4.

In `GlobalSettingsHub.js` replace the IndexNow heading “Bing & IndexNow Instant Crawl Protocol” and the sentence that lists Naver and says content is deleted. Replacement help:

“When this is on, publishing a public post queues its URL. A background job sends up to 10 URLs at a time to https://api.indexnow.org/indexnow. Deleting a post does not send a notice.”

The toggle label is “Notify IndexNow when a public post is published”. The help is “The save finishes immediately. The notice is sent in the background.”

In `src/assets/js/App.js`, the multisite line that says Bing IndexNow receives URLs automatically becomes “IndexNow sends a published URL only when that site has notifications on and a key saved.” Keep the word `IndexNow` in `App.js`.

`readme.txt` and `docs/features.md` already say IndexNow sends published URLs only after an administrator saves a key and turns notifications on, and that it does not guarantee indexing. Leave that meaning in place. Add that the request goes to `https://api.indexnow.org/indexnow` only after that choice. The external-services paragraph in `readme.txt` already names the allow-listed hosts. Keep it.

`docs/third-party-services.md` already describes IndexNow. Keep the sentence that nothing is sent during the publish request.

### Acceptance

- The Indexing tab does not say “instant”, “Naver”, or that a delete is sent.
- `readme.txt` still contains `nothing is sent until an administrator turns the feature on` and does not contain `guaranteed indexing`.
- `FeatureCopyTest` passes.

### QA

- `vendor/bin/phpunit --filter FeatureCopyTest`
- Read the Indexing tab aloud once. The next action is the toggle and the key field, and the test control is below the explanation.

## Task 6. Bing traffic comes from one real request

Status: done. `BingWebmasterTrafficTest` plus `testBingKeyIsMaskedAndEncrypted` passed (5 tests, 34 assertions). `GetPageStats` is absent from `src/`.

Priority: sixth. `BingWebmasterIntegration::getDecayingPages()` posts twice to `GetPageStats` and ignores `$days` and `$offsetDays`, so both calls are the same payload and the decay list is empty. `GetPageStats` also returns every page Bing has stored. Do not use it.

Replace the `/analytics/bing/decay` route with `GET /quinrel/v1/analytics/bing/traffic`. Remove `getDecayingPages()`, `fetchPageStats()`, and `calculateDecay()`.

Permission callback: `manage_seo` or `manage_options`. Same capability as saving the key.

Behavior:

- No stored key: `WP_Error` `bing_unconfigured`, status 400. Message: “Save a Bing Webmaster API key first. In Bing Webmaster Tools, open Settings, then API Access, and generate a key.”
- One `wp_safe_remote_get()` to `https://ssl.bing.com/webmaster/api.svc/json/GetRankAndTrafficStats` with query arguments `siteUrl` = `home_url( '/' )` and `apikey` = the decrypted key. Timeout 15 seconds. Header `Accept: application/json`. GET, not POST.
- Do not write the request URL or the key to a log, a transient, or the REST body.
- Transport failure: status 502, code `bing_http_failed`. Message: “Bing did not respond. Try again in a few minutes.”
- HTTP status other than 200: status 502, code `bing_api_error`. Message includes the numeric status. If the JSON body has a short `Message` string, append it only after removing the API key from that string. Do not return the raw body.
- Parse `d` as a list of objects with `Clicks`, `Impressions`, and `Date`. The date is Microsoft’s `/Date(milliseconds)/` or `/Date(milliseconds+offset)/`. Read the milliseconds. Ignore rows with no parseable date.
- Sort by date ascending. The current window is the newest 28 rows. The previous window is the 28 rows before those. Sum `Clicks` and `Impressions` as integers.
- Cache the summed result in transient `quinrel_bing_traffic_28_` plus `md5( home_url( '/' ) )` for `6 * HOUR_IN_SECONDS`. `storeKey()` deletes that transient when the key changes or is cleared.
- Response JSON:

```json
{
  "success": true,
  "site_url": "https://example.com/",
  "window_days": 28,
  "measured_days": 28,
  "clicks": 0,
  "impressions": 0,
  "previous_clicks": null,
  "previous_impressions": null,
  "clicks_change_percent": null,
  "impressions_change_percent": null,
  "message": ""
}
```

- `previous_*` and both percents stay null when fewer than 29 dated rows exist. The message is then “Bing has not returned enough days for a comparison.”
- An empty `d` is still HTTP 200, with zeros, `measured_days` 0, and message “Bing has no traffic rows for this site yet.”
- Percent change is rounded to one decimal and is present only when the previous total is greater than 0.

`site_url` in the response is `home_url( '/' )`. Bing must have that exact property, including scheme and trailing slash. Do not retry other spellings.

### Acceptance

- A missing key performs no HTTP call.
- A fixture body with 40 dated rows produces current sums from the newest 28 and previous sums from the older 12 only when those 12 are all that exist before the window. With 56 dated rows, previous uses 28 rows.
- The API key is absent from the REST response and from the transient.
- A second request inside the TTL performs no HTTP call.
- Saving a new key deletes the transient.
- The old route `/analytics/bing/decay` is gone.

### QA

- Add `tests/Unit/BingWebmasterTrafficTest.php`. Cover: no key, fixture sum, short history with null comparison, non-200 without the key in the error, cache hit, and transient cleared on `storeKey()`.
- The HTTP mock must assert the method is GET and the URL host is `ssl.bing.com`.
- Run the new test file and `GoogleSearchConsoleIntegrationTest` (it round-trips a Bing key through `storeKey()`).

## Task 7. The Bing section shows those numbers

Status: done. On UAT, with no API key stored, the Bing panel showed the empty-key sentence and did not request `ssl.bing.com`.

Priority: seventh. Depends on Task 6.

Replace the Bing section help “Sync click and impression trends from Microsoft Bing to catch organic traffic shifts across multiple search engines.” with “After you save an API key, Quinrel reads clicks and impressions for this site from Bing. Opening this tab is what sends the request.”

Keep the API key `TextControl`. When `settings.bing_api_key` is the masked value or a newly typed value has been saved, and this tab is open, fetch `/analytics/bing/traffic` once. A key that is only typed and not saved yet does not fetch. Use the masked value `••••••••••••` as the signal that a key is already stored.

Render:

- The three-cell grid from the UI bar when `measured_days > 0`.
- The change cell only when `clicks_change_percent` or `impressions_change_percent` is a number. The sentence is “Compared with the previous 28 days.” Include the sign and the percent. When the percent is null, omit the cell.
- The API `message` under the grid when it is non-empty.
- No key: no fetch, and this sentence under the field: “Create a key in Bing Webmaster Tools under Settings, then API Access. The site in Bing must match this site’s address.”
- Failure: the error sentence in `role="alert"`. The key field remains usable.

Do not add a page table, a query table, or a chart.

The verification-code section below stays as it is. It is how the site proves itself to Bing. Do not merge it into the traffic card.

### Acceptance

- Opening General, Cloud, or Indexing does not request `/analytics/bing/traffic`.
- Opening Bing with no key does not request it.
- Opening Bing with a stored key requests it once per visit to the tab.
- Clicks and impressions on screen equal the JSON fields.
- A 400 or 502 shows the API message and does not show zeros as if they were measured traffic.
- Save still posts the settings object without `clicks` or `impressions` mixed in.

### QA

- Browser, desktop and a 375px window: empty key, then a stored key with the UAT site’s missing key still showing the empty sentence. After a real key is available, the grid matches a second call’s cached JSON. Throttle the network to offline, reload the tab, and confirm the alert and that Save still works.
- Keyboard through the key field and the verification fields. The heading order stays h3 inside the tab.

## Task 8. Docs say what the screen does

Status: done. `readme.txt` and `docs/features.md` no longer say there is no Bing traffic screen. Both say the site address is sent to `https://ssl.bing.com` only when an administrator opens that section.

Priority: eighth. Same change as Task 7. Do not describe the screen before the route exists.

In `readme.txt` and `docs/features.md`, replace “Quinrel does not include a Bing traffic screen” and “This release does not include a Bing traffic screen” with: an administrator who saves a Bing API key can read the last 28 days of clicks and impressions for this site. The site address is sent to `https://ssl.bing.com` only when that administrator opens the Bing section. Quinrel does not submit pages or sitemaps to the Bing Webmaster API.

In `docs/third-party-services.md`, add that traffic row. Keep the verification-code sentence.

Do not edit `docs/production-readiness.md` or `docs/engineering-backlog.md`.

### Acceptance

- The two “no Bing traffic screen” sentences are gone from `readme.txt` and `docs/features.md`.
- `FeatureCopyTest` still passes, including the locked external-services sentence.
- The docs do not say the plugin guarantees indexing or a ranking change.

### QA

- `vendor/bin/phpunit --filter 'FeatureCopyTest|MigrationTest::testReadmeDescribesTheImportPreview'`
- Search the repo for `does not include a Bing traffic screen` and `GetPageStats`. The first is gone from `readme.txt` and `docs/features.md`. The second is gone from `src/`.

## Task 9. Release gate for this change

Status: done. PHPCS on the four touched PHP files exited 0 with no new ignore. The named unit tests passed. `build/index.js` on UAT matches the workspace build. The settings screen loaded with no console error. A live IndexNow queue and a populated Bing grid were not required for this gate.

Priority: last. Do not bump the plugin version. Do not run `package-zip.sh`. Do not submit to WordPress.org.

### Acceptance

- `vendor/bin/phpcs` passes on the PHP files touched in Tasks 1 through 8.
- The unit tests named in those tasks pass.
- `src/assets/js/` changes are built, and the settings screen loads from `build/` without a console error.
- No new Plugin Check ignore, exclude, or `phpcs:ignore` was added to hide a finding.

### QA

On `http://localhost/holvek-uat/`, signed in as the administrator:

1. Indexing tab, key empty: the test control returns the off message and the log is empty or unchanged.
2. Generate a key, save, turn notifications on, save. The key file link returns the key. Queue the home URL. A row appears. After cron or Action Scheduler runs, that row’s code is 200 or 202, or the panel shows “Not accepted” with the real code. Either result is a pass if it matches `quinrel_indexnow_log`. A pass is not a claim that Bing crawled the URL.
3. Publish one public post. The queue or the log gains that permalink, and the publish request itself does not wait on `api.indexnow.org`.
4. Bing tab with no key: the empty sentence is visible and the network panel has no request to `ssl.bing.com`.
5. With a real API key saved, the grid shows the cached totals or the API error sentence. Reloading the tab does not send a second request to Bing inside six hours.
6. Walk the two tabs with the keyboard only. At 375px width, the cards stack and the totals stay inside the card.

Record the log status code and the Bing HTTP status in the task notes. Do not record the IndexNow key, the Bing API key, or the administrator password.

## Leave these out

- Bing OAuth, crawl stats, inbound links, and query or keyword reports.
- `GetPageStats` and any per-page loop.
- A second Bing request to try `http`, `https`, or a missing slash.
- Naver, a delete notification, and a promise that a URL will be indexed.
- A new chart, icon set, onboarding modal, or settings page.
- Turning IndexNow on for sites that are already off, or off for sites that are already on.
- A plugin version bump, a release zip, or a WordPress.org submission.
