# Google Search Console connect

Status: blocked. Do not implement this in the plugin until both gates below are true.

1. Quinrel’s Google OAuth app is verified for `https://www.googleapis.com/auth/webmasters.readonly`.
2. `https://api.quinrel.com` has the broker routes in the contract below, and a click against a verified app returns to a WordPress admin without the unverified-app warning.

While the app is in Google’s testing mode, the consent screen warns that Google has not verified the app and the project is capped at 100 users. That screen is where people abandon the flow. The shared button stays off until verification is done.

Confirmed in Google Cloud before submission: this scope is sensitive. A restricted classification would also require a security assessment. The consent screen needs a live homepage, a privacy policy, a support email, and a recording of this exact click. The only registered redirect URI is `https://api.quinrel.com/gsc/callback`.

## What the person does

They are already signed in to WordPress. They open Quinrel, Settings, Google Search Console, and press **Connect Google Search Console**.

1. Google asks which signed-in account to use, then shows the read-only Search Console consent screen.
2. The site encrypts the tokens it receives and selects the Search Console property that matches this site.
3. The browser lands on `admin.php?page=quinrel&tab=settings_gsc`.

No Quinrel account. No Client ID on the normal screen. The read-only scope does not verify the site in Search Console and does not submit a sitemap. The line under the button says that.

If several properties match, the existing property `<select>` (`#quinrel-gsc-property-select`) stays on screen with the match pre-selected. If none match, the connection is kept, `selected_property` stays empty, and the screen shows one sentence plus a link to `https://search.google.com/search-console`.

If `api.quinrel.com` does not answer, the button reports that and opens the advanced drawer. The rest of Quinrel keeps working.

## Why the hop exists

Google accepts a shared client only at redirect URIs registered in advance. Each site’s callback is its own `rest_url( 'quinrel/v1/gsc/callback' )`, so the shared app cannot send the browser there. `api.quinrel.com` is the one registered redirect. Customer localhost addresses work, because Google never has to know them.

This is a different route from Quinrel Cloud. `CloudCredential::autoConnect()` sends people to `https://api.quinrel.com/oauth/authorize` and pairs a Quinrel account. Search Console connect does not call that route and does not read `quinrel_api_key`.

## Broker contract

The broker is not in this repository. The plugin calls only these paths on the host of `BackendApiClient::getApiUrl()` (default `https://api.quinrel.com/v1`, overridable in code by `quinrel_backend_api_url`). Strip the `/v1` suffix. Reject any other host. The settings screen cannot change the host.

The client secret stays on the broker. The plugin never contains it and never sends it to Google for a brokered connection.

| Method and path | Who calls it | What it does |
| --- | --- | --- |
| `GET /gsc/connect` | The administrator’s browser, after the plugin has stored state | Query: `state`, `site_url`, `return_url`. Redirects to `https://accounts.google.com/o/oauth2/v2/auth` with Quinrel’s client id, `redirect_uri=https://api.quinrel.com/gsc/callback`, `response_type=code`, scope `https://www.googleapis.com/auth/webmasters.readonly`, `access_type=offline`, `prompt=select_account consent`, and a broker state that still names this site. |
| `GET /gsc/callback` | Google | Exchanges the Google code. Stores the token pair only until the one-time code is redeemed or for 10 minutes, whichever is first. Redirects the browser to `return_url` with `code` (one-time) and `state`. The Google access token and refresh token are not query parameters. |
| `POST /gsc/exchange` | The site, server to server | Body: `code`, `state`, `site_url`. Returns `access_token`, `refresh_token`, and `expires_in` once, then deletes its copy. |
| `POST /gsc/refresh` | The site, server to server, when the access token is inside 60 seconds of expiry | Body: the site’s refresh token and `site_url`. Returns a new access token and `expires_in`. If Google also rotates the refresh token, return the new one. Do not write either token to disk. |
| `POST /gsc/revoke` | The site, server to server, on disconnect | Body: the refresh token. Calls Google’s revoke endpoint. Do not write the token. |

`return_url` is accepted only when its host equals the host of `site_url` and its path is `/wp-json/quinrel/v1/gsc/callback` (a subdirectory install keeps the prefix WordPress reports). The scheme is `https`, or `http` when the host is `localhost` or `127.0.0.1`. Anything else is rejected. That stops an open redirect from delivering the one-time code to another origin.

## Plugin work after the gates pass

Keep the change inside the current Search Console classes. Do not add a setup wizard.

### Start

`GlobalSettingsHub.js` `handleConnectGsc` posts to `/gsc/auth-url`.

- Saved Client ID and Client Secret: send them, as today. `GoogleSearchConsoleIntegration::getAuthUrl()` keeps building the Google URL with those credentials and `redirect_uri` = this site’s `/gsc/callback`. Change `prompt` from `consent` to `select_account consent`.
- No saved credentials: send an empty body. `getAuthUrl()` stores a single-use state for the current user for 10 minutes and returns the browser to `{broker}/gsc/connect`. It does not return 400, and it does not open the drawer on an empty form.
- The plugin server checks that the broker answers before it returns that URL. Timeout 5 seconds. On failure, return an error and the UI opens the advanced drawer (`setShowCustomGscDrawer(true)`), which already accepts a site-owned Client ID and secret.

`tests/Unit/GoogleSearchConsoleIntegrationTest.php` `testGetAuthUrlWithoutConfigReturnsWpError` expects `no_config` / 400. Replace that expectation with the broker URL once this ships. `testGetAuthUrlWithCredentialsSavesConfigAndReturnsUrl` stays on the direct Google URL and still asserts the secret is absent from the URL and stored as `quinrel_enc::`.

### Return

`handleCallback()` keeps verifying state and the administrator capability (auth cookie restore is already there).

- Brokered return: `code` is the one-time code. The site POSTs it to `/gsc/exchange`. `writeToken()` encrypts both tokens through KeyVault. `quinrel_gsc_config` records `auth_mode=broker` and leaves `client_id` and `client_secret` empty.
- Bring-your-own return: keep `exchangeCode()` against `https://oauth2.googleapis.com/token`. `auth_mode=byo`.

A mismatched state, a Google `error`, or an empty code still redirects to the Search Console tab with `gsc_error`. Success still uses `gsc_connected=1`.

### Refresh and disconnect

`refreshToken()` branches on `auth_mode`. `byo` keeps the direct Google refresh, which needs the saved client secret. `broker` POSTs to `/gsc/refresh` and does not call Google with a client secret. `getValidToken()` already refreshes inside 60 seconds of expiry.

`disconnect()` asks `/gsc/revoke` when `auth_mode=broker`, then deletes `quinrel_gsc_token` and the Search Console transients as it does now. A revoke failure still deletes the local token and tells the administrator that Google may still list the app until they remove it in their Google account.

### Property

After a successful exchange, call the existing sites list (`GET https://www.googleapis.com/webmasters/v3/sites`) and set `selected_property` when one entry matches:

1. Exact URL-prefix match of `home_url( '/' )`, including scheme and trailing slash.
2. Otherwise `sc-domain:` plus the host of `home_url()`.
3. Otherwise leave `selected_property` empty.

Do not fall through to `home_url( '/' )` for a brokered site with no match. `getPerformance()` and `getDecliningKeywords()` currently do that, and Google rejects a property string the account does not have. An empty property returns the existing not-connected style error from those methods until the person picks one. Bring-your-own sites that already saved a property keep it.

The `<select>` already lists `siteEntry` values from `/gsc/sites`. Pre-select the match. Several matches stay in that control.

### Copy, in the same change

The screen and the readme currently describe a flow the code does not perform.

- `src/assets/js/components/GlobalSettingsHub.js` around the connect banner. Remove “Site Kit-Style”, “1 click”, “verify ownership”, and “No tracking or third-party brokers”. The button label stays **Connect Google Search Console**. Under it: read-only Search Console access; the click sends this site’s address to `https://api.quinrel.com` so Google can return here; a Quinrel account is not required; the connection does not verify the site and does not submit a sitemap.
- `readme.txt` and `docs/features.md` share the sentence that says Search Console connects via a 1-click Google OAuth flow with automatic property selection. Replace it with the flow above. `readme.txt` External services names `https://api.quinrel.com` for this click, the data sent (site address, return address, and the OAuth code exchange), and that nothing is sent until an administrator presses the button. `docs/third-party-services.md` gets the same row.
- `FeatureCopyTest` asserts the words “Google Search Console” in the readme. It does not lock the 1-click sentence. Re-run it after the readme edit. `docs/features.md` is read beside the readme in `MigrationTest` and `FeatureCopyTest`; keep those sentences aligned.

Rebuild admin assets (`npm run build`) because `GlobalSettingsHub.js` changes. Copy the built file to the UAT plugin with a single-file copy, not a multi-source rsync.

## Leave these out

- A client secret in the plugin or the release zip.
- The refresh token or access token on the browser return URL.
- A Quinrel Cloud login, site token, or call to `/oauth/authorize` as a step of this button.
- A second settings wizard.
- Phone-home on an ordinary front-end or admin page view. The broker is contacted only from the button, the callback, refresh, and disconnect.
- Making technical SEO wait on this connection.
- A new Plugin Check ignore.

## Checks before calling it done

- Unit: no credentials returns the broker URL on `api.quinrel.com` and nothing else. Saved credentials still return the Google URL, with `prompt=select_account consent`, and the secret stays encrypted. Callback state mismatch does not write a token. Broker exchange stores `quinrel_enc::` tokens. Broker refresh does not post `client_secret` to Google. Property matching covers URL-prefix, `sc-domain:`, several matches, and no match.
- Live, on `http://localhost/holvek-uat/`, with the verified app: from a browser that has two Google sessions, the button shows Google’s account chooser, consent, and a return to the Search Console tab with the matching property selected. With the broker stopped, the button opens the advanced drawer and the rest of the settings hub still loads.
- `readme.txt` and the settings screen name the same hop.

Plugin files this change touches: `src/Modules/Analytics/GoogleSearchConsoleIntegration.php`, `src/assets/js/components/GlobalSettingsHub.js`, `tests/Unit/GoogleSearchConsoleIntegrationTest.php`, `readme.txt`, `docs/features.md`, `docs/third-party-services.md`.
