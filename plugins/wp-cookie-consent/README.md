# EuroComply Cookie Consent

GDPR + ePrivacy cookie-consent banner for WordPress with **Google Consent Mode v2** wired in by default. Part of the [EuroComply compliance suite](../../).

Status: **0.1.0 MVP** — ship-ready baseline, Pro features behind a licence stub.

## Features (free)

- Banner with 4 pre-configured categories: Necessary (locked), Preferences, Statistics, Marketing.
- Accept all / Reject non-essential / Preferences buttons.
- Google Consent Mode v2 signals:
  - Emits `gtag('consent', 'default', { ... })` at `wp_head` priority 1 with all non-security signals `denied`.
  - Emits `gtag('consent', 'update', { ... })` on the user's choice, mapping categories → Google signals.
  - Supports `ads_data_redaction` and `url_passthrough`.
- **Script blocking**: third-party scripts tagged with `<script type="text/plain" data-eurocomply-cc="marketing">` stay inert until the matching category is granted, then are swapped for real `<script>` nodes.
- **Consent log** (GDPR Art. 7(1) proof of consent) in a dedicated custom table, with salted SHA-256 hashed IP + user agent.
- English + German banner copy, locale auto-detection.
- REST endpoint `POST /wp-json/eurocomply-cc/v1/consent` with nonce-gated permission callback.

## Pro stubs (0.1.0)

Licence format `EC-XXXXXX`. The following are paywalled placeholders wired into the admin but will be built in follow-ups:

- IAB TCF v2.2 framework support.
- Automated cookie scanner.
- CSV export of the consent log.
- Geo-IP based banner suppression outside the EEA.

## Usage

1. Install & activate.
2. Visit *Cookie Consent* in the admin menu.
3. **Banner** tab: position, colours, button labels, linked pages.
4. **Categories** tab: toggle optional categories on/off. Necessary is always on.
5. **Integrations** tab: enable Consent Mode v2, set region scope, pick privacy flags, stash your GA4 / Meta Pixel / Google Ads IDs for later auto-load features.
6. **Consent Log** tab: review last 50 events; purge when needed.
7. **License** tab: paste an `EC-XXXXXX` key to unlock Pro features.

### Blocking third-party scripts

Wrap any script that fires marketing or analytics pixels:

```html
<script type="text/plain" data-eurocomply-cc="marketing">
  (function(){ /* Meta Pixel, GA4, Ads, etc. */ })();
</script>
```

When the visitor grants the `marketing` category, the script is upgraded in-place to a real `<script>` tag and executed.

## Security & privacy

- Nonce-verified admin POST handlers using `check_admin_referer()`.
- REST endpoint requires `X-WP-Nonce: wp_rest`.
- Consent log stores **salted SHA-256 hashes** of IP and user agent (site `auth` salt) — no raw PII.
- Hard-coded escape on every template output (`esc_html`, `esc_attr`, `esc_url`, `esc_js`).

## License

GPL-2.0-or-later. See [../../LICENSE](../../LICENSE).
