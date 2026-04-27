=== EuroComply ePrivacy & Tracker Registry ===
Contributors: eurocomply
Tags: gdpr, eprivacy, cookies, privacy, tracker, scanner, consent
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

ePrivacy 2002/58 + GDPR Art. 7 tracker scanner: detects 80+ third-party trackers (GA4, Meta Pixel, Hotjar, Clarity, LinkedIn Insight, TikTok, Pinterest, Klaviyo, Intercom, HubSpot, Segment, Mixpanel, Stripe, PayPal, etc.) on configured URLs, observes browser cookies via a JS sniffer, and surfaces a compliance gap report against your active consent banner.

== Description ==

* Static HTML scan: fetches each configured URL with the WP HTTP API and matches the response body against the tracker registry.
* Live cookie observer: tiny inline script in `wp_footer` (sample-rate configurable) sends cookie names (never values) to admin-ajax for classification.
* Compliance gap report: trackers detected on the latest scan that require GDPR Art. 7 consent but are not declared in EuroComply Cookie Consent's category map.
* Tamper-evident logs: every scan is recorded in `wp_eurocomply_eprivacy_scans`, every finding in `wp_eurocomply_eprivacy_findings`, every cookie observation in `wp_eurocomply_eprivacy_cookies`.
* CSV export: findings, cookies, scans (500 free / 5,000 Pro).

== Pro features (stubs only — not implemented in this scaffold) ==

* Hourly WP-Cron scan with email digest of new compliance gaps
* Headless Chrome / browserless deep scan (executes JS, follows network)
* JS event capture (gtag(), fbq(), dataLayer.push)
* IAB TCF v2.2 stub + GVL vendor lookup
* Slack / Teams / PagerDuty webhook on new tracker detection
* Signed PDF audit report (DPO-ready)
* REST API: /eurocomply/v1/eprivacy/{scans,findings,cookies}
* 5,000-row CSV export cap
* WPML / Polylang multi-language scan profiles
* Auto-fix: write missing categories into Cookie Consent #2
* Multi-site network aggregator

== Changelog ==

= 0.1.0 =
* Initial scaffold: static HTML scanner, JS cookie observer, 80+ tracker registry, 8-tab admin, CSV export, license stub.
