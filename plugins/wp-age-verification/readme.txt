=== EuroComply Age Verification ===
Contributors: eurocomply
Tags: age verification, age gate, jmstv, arcom, woocommerce, alcohol, adult, compliance, eu
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU / EEA age-verification gate for WordPress and WooCommerce with per-country rules (DE JMStV, FR ARCOM, SE Systembolaget 20+) and category-level gating for alcohol and adult shops.

== Description ==

Selling alcohol, tobacco, vaping, CBD, adult or age-restricted content in the EU requires an age-verification gate that actually checks age — a simple "I am over 18" checkbox has been ruled insufficient in Germany (JMStV §4) and is being tightened in France (ARCOM), the UK (OSA), and Ireland.

EuroComply Age Verification gives you a ready-to-go gate for your WordPress / WooCommerce site:

* **Site-wide, category-only, or shortcode gating modes.**
* **Date-of-birth entry** that computes age server-side and compares against the applicable minimum.
* **Per-country minimum-age table** covering all EU-27 + common alcohol laws (DE 16/18, SE 20, FR/IT/ES/etc. 18).
* **WooCommerce integration** — product-category gate, per-product age override, cart & checkout enforcement.
* **Hashed-IP audit log** recording every pass / block with timestamp, method, computed age, required age, context — so you can demonstrate to regulators that the gate was in place and working.
* **Session cookie signed with HMAC** — users don't need to re-verify on every page.

The free tier is enough to operate a compliant single-site alcohol or adult shop. Pro unlocks stronger identity flows: German AusweisIdent / eID, SCHUFA age-check, Veriff / Onfido biometric + ID document, SMS OTP, parental consent, and signed PDF audit reports.

== Free features ==

* Three gate modes: site-wide, category-only, shortcode-only
* DOB entry or simple-checkbox verification
* Per-country minimum-age rules (EU-27 defaults bundled)
* Per-product / per-category age override
* WooCommerce add-to-cart blocking + checkout block
* Verification log with hashed IP (no raw PII stored)
* CSV export (500 rows)
* Admin exempt toggle
* HMAC-signed session cookie
* Configurable modal copy, blocked redirect URL
* 6-tab admin UI

== Pro features (stubs) ==

* AusweisIdent / eID integration (Germany)
* SCHUFA age-check (Germany) — pass/fail only
* Veriff / Onfido biometric + ID-document upload
* SMS OTP fallback with country-code routing
* Parental consent workflow for ages 13–15 (UK OSA / COPPA-style)
* Per-variation age override (WooCommerce variations)
* WPML / Polylang multilingual modal
* Per-post / per-page gating
* Signed PDF audit reports (DE KJM / FR ARCOM)
* 5,000-row CSV export cap

== Installation ==

1. Upload the `wp-age-verification` folder to `/wp-content/plugins/`.
2. Activate through the "Plugins" menu.
3. Visit "Age Verification" in the wp-admin sidebar.
4. Choose a gate mode under Settings → Gate mode.
5. If using category mode, tag restricted product categories under Categories.
6. Optional: place `[eurocomply_age_gate min_age="18"]` on any page to show the gate there only.

== Frequently Asked Questions ==

= Is a DOB entry legally sufficient in Germany? =

The JMStV (Jugendmedienschutz-Staatsvertrag) requires that age-restricted content providers take "geeignete Maßnahmen" (suitable measures) to ensure children cannot access 18+ content. DOB entry is the baseline and is accepted by some state media authorities (KJM) for retail. Stronger measures (eID, video ident) may be required depending on your vertical — use the Pro add-ons.

= What about French ARCOM? =

Since 2024 France requires "double-blind" age verification for adult content: a third party must verify age without knowing what site you're visiting. The Pro tier ships a Veriff / Onfido integration supporting this model.

= Does the checkbox method count? =

The plugin includes a checkbox-only mode for low-risk use cases (e.g. vaping content aggregators), but it is flagged as "weaker — not JMStV-compliant" in the UI. For regulated goods, use DOB or a Pro identity flow.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold.
* DOB + checkbox verification methods.
* Per-country rules + per-category overrides.
* WooCommerce cart / checkout enforcement + per-product override.
* Hashed-IP verification audit log with CSV export.
* HMAC-signed session cookie.
* License stub (EC-XXXXXX format).
