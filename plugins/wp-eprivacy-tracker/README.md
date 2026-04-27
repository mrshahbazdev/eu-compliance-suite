# EuroComply ePrivacy & Tracker Registry

Plugin #16 of the EuroComply EU Compliance Suite.

Static HTML scanner + live cookie observer for ePrivacy 2002/58 + GDPR Art. 7 compliance.

## What it detects (80+ trackers)

Analytics: GA4, GA-UA legacy, Matomo, Plausible, Fathom, Simple Analytics, Mixpanel, Heap, Amplitude, FullStory, Crazy Egg, Microsoft Clarity, Hotjar, Segment, Jetpack stats.

Advertising: Google Ads, DoubleClick, Meta Pixel, LinkedIn Insight, TikTok, Pinterest, Snapchat, Reddit, Microsoft Ads (Bing UET), Criteo, AdRoll, Taboola, Outbrain, Klaviyo, Mailchimp, HubSpot, Salesforce Pardot, Marketo, Amazon Associates, Sumo, WPQuads.

Social: Facebook SDK, Instagram, Twitter/X, YouTube, Vimeo, SoundCloud, Spotify, AddThis, AddToAny, ShareThis, Shareaholic.

Functional: reCAPTCHA, Cloudflare, Google Maps, Google Fonts, Gravatar, jsDelivr, unpkg, cdnjs, Stripe, PayPal, Klarna, Sezzle, Algolia, AWS CloudFront, Recurly, Auth0, Firebase, Intercom, Drift, Crisp, Tawk.to, Zendesk, Help Scout, WPForms, Contact Form 7, Shopify Buy SDK.

Preferences: Optimizely, VWO, Usercentrics, Cookiebot, OneTrust, CookieYes, iubenda.

## Architecture

| Component | Purpose |
|-----------|---------|
| `TrackerRegistry` | Static map of slug → name, vendor, country, category, consent flag, regex patterns, cookie fingerprints |
| `Scanner` | `wp_safe_remote_get()` each configured URL, `match_html()` against registry, write to `FindingStore` |
| `CookieObserver` | Inline JS in `wp_footer` posts cookie *names* (no values) to admin-ajax; classifies via `match_cookie_name()` |
| `ScanStore` / `FindingStore` / `CookieStore` | 3 DB tables for run history, per-URL findings, per-cookie observations |
| `Scanner::compliance_gaps()` | Cross-references the latest scan against EuroComply Cookie Consent #2's declared categories |
| `Admin` | 8-tab UI (Dashboard / Trackers / Scan / Findings / Cookies / Settings / Pro / License) |
| `CsvExport` | findings · cookies · scans datasets (500 / 5,000) |

## Privacy posture

- Cookie observer sends **names only**. Cookie values never leave the browser.
- Sessions are de-duplicated by `hash_hmac('sha256', remote_addr|user_agent, wp_salt('nonce'))` — raw IPs not stored.
- Scanner uses a clearly-identified user-agent so it can be excluded from your own analytics.
