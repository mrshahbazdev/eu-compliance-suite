=== EuroComply GDPR DSAR ===
Contributors: eurocomply
Tags: gdpr, privacy, dsar, data subject access request, right to be forgotten, article 15, article 17, woocommerce
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR Data Subject Access Request (DSAR) handler for WordPress and WooCommerce. Public request form, email-token verification, 30-day deadline tracking, one-click JSON + CSV ZIP export, pseudonymisation + WooCommerce anonymiser, request log.

== Description ==

Turns the WordPress built-in privacy exporter / eraser registry into a proper DSAR portal, with:

* Public `[eurocomply_dsar_form]` shortcode — users submit access / erase / portability / rectify / object / restrict requests
* Email-token identity verification (GDPR Art. 12(6))
* Request log with status lifecycle (received → verifying → in progress → completed / rejected / cancelled / expired) and Art. 12(3) 30-day deadline tracking
* Admin processor with one-click **Build export** → generates a ZIP containing `request.json`, `export.csv`, `README.txt`, and the full personal-data payload aggregated from every registered exporter (WP core, WooCommerce, contact-form plugins, etc.)
* Erasure workflow with configurable grace window before hard user-delete; integrates with WooCommerce's order anonymiser
* Hashed-IP rate limiting on the public form (SHA-256 + `wp_salt`)
* 6-tab admin UI (Dashboard / Requests / Settings / Exporters / Pro / License)
* CSV export of the request log (500 rows free, 5,000 Pro)

== Free vs Pro ==

Free:

* Unlimited requests
* All six GDPR request types (Art. 15 / 16 / 17 / 18 / 20 / 21)
* JSON + CSV ZIP export
* Email-token verification
* Request log + CSV (500 rows)
* Built-in rate limiting

Pro (stubs, not implemented in this scaffold):

* CRM eraser integrations (HubSpot, Mailchimp, Stripe, ActiveCampaign, Klaviyo)
* SFTP / encrypted-email delivery of export ZIPs
* Signed PDF audit report (DPA-ready)
* MFA verification (SMS OTP + TOTP)
* Deadline extension by up to 2 months (Art. 12(3))
* Multi-site aggregator across a WordPress network
* Helpdesk import (Zendesk / Freshdesk / Help Scout)
* REST API for DSAR submission & status polling
* 5,000-row CSV cap
* WPML / Polylang multilingual email templates

== Installation ==

1. Upload and activate `eurocomply-gdpr-dsar`.
2. Visit **EuroComply DSAR → Settings**, set your DPO contact, notification emails, and deadline.
3. Create a page and add the shortcode `[eurocomply_dsar_form]`.
4. Link that page from your privacy policy and site footer.

== Frequently Asked Questions ==

= Does this replace the WordPress privacy tools? =

No — it *enhances* them. WordPress ships with personal-data exporters and erasers registered via `wp_privacy_personal_data_exporters` / `wp_privacy_personal_data_erasers`. This plugin calls those same hooks when building exports and running erasures, so any plugin that already registers into the core registry (Contact Form 7, Gravity Forms, WooCommerce, etc.) is automatically included.

= Where are export archives stored? =

`wp-content/uploads/eurocomply-dsar/` with a `.htaccess` and `index.html` that deny direct access.

= Is GDPR legal advice bundled? =

No. The plugin is a technical implementation. Consult a DPO / legal counsel for specific compliance questions.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold
