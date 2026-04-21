=== EuroComply DSA Transparency ===
Contributors: eurocomply
Tags: dsa, digital services act, transparency, marketplace, notice and action, kybp, compliance, eu
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU Digital Services Act compliance helper for WordPress / WooCommerce marketplaces. Article 16 notice-and-action, Article 17 statements of reasons, Article 30 trader traceability (KYBP), Article 15 / 24 transparency reporting.

== Description ==

The Digital Services Act (Regulation (EU) 2022/2065) is the EU's horizontal rulebook for online platforms. Since 17 February 2024 it applies to every provider of "hosting services" offered to users in the EU — including WordPress/WooCommerce marketplaces, community forums, classifieds, and UGC sites.

EuroComply DSA Transparency gives site operators the tooling required to meet the core DSA obligations without hiring a compliance engineer:

* **Article 16 — Notice-and-action:** a public form where any recipient can report illegal content, with a nonce-gated POST handler, honeypot, per-IP rate limit, and admin log.
* **Article 17 — Statement of reasons:** a structured log of every moderation decision (removal, demotion, account suspension, monetisation suspension) with decision ground, legal reference, automated-detection flag, and redress info.
* **Article 30 — Trader traceability:** a vendor-facing form collecting legal name, address, contact, trade-register number, VAT, and self-certification, with admin verification workflow.
* **Article 15 / 24 — Transparency reports:** one-click JSON + CSV export of aggregated stats (notices received by category, statements issued by restriction type, share of automated decisions, trader verification status) over any period.
* **Article 14 — T&Cs metadata:** settings fields for terms URL, complaints/ODR URL, contact-point email, EU legal representative — renderable by theme or block.

The free tier ships everything you need to demonstrate compliance on a single-site WooCommerce marketplace.

== Free features ==

* Notice-and-action shortcode `[eurocomply_dsa_notice_form]` (public)
* Trader-info shortcode `[eurocomply_dsa_trader_form]` (vendor)
* Admin dashboard + full notice / statement / trader logs
* Issue statement of reasons from wp-admin
* Verify / reject traders from wp-admin
* Annual transparency report: JSON + CSV export
* CSV export for each dataset (500-row cap)
* Settings for contact-point email, legal representative, T&Cs URL, complaints URL
* Nonce-gated POST handlers + per-IP honeypot + rate limit
* Admin menu with 8 tabs

== Pro features (stubs) ==

* DSA Transparency Database submission (Commission XML schema)
* Out-of-court dispute resolution workflow (Art. 21)
* Strike / reputation system for repeat offenders (Art. 23)
* Marketplace plugin integrations (WC Vendors / Dokan / WCFM) — sync vendor KYBP fields
* Scheduled annual / semi-annual / quarterly report cron with email delivery
* Multi-language T&Cs and notice form (WPML / Polylang)
* Signed PDF transparency reports with embedded JSON for auditors
* Trusted-flagger whitelisting (Art. 22)
* REST API endpoints for external moderation tools
* Higher CSV export cap (5,000 rows)

== Installation ==

1. Upload the plugin folder `wp-dsa-transparency` to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Visit "DSA" in the wp-admin sidebar.
4. Fill in Settings → contact-point email, EU legal representative, T&Cs URL, complaints URL.
5. Place `[eurocomply_dsa_notice_form]` on a public "Report illegal content" page.
6. Place `[eurocomply_dsa_trader_form]` on your vendor onboarding / account page.

== Frequently Asked Questions ==

= Does this make my site DSA-compliant? =

The plugin provides the *technical primitives* you need to meet Articles 14, 15, 16, 17, 24, and 30. Legal compliance also requires human moderation, published T&Cs, and transparency reports matching your actual activity. Consult a DSA compliance lawyer for formal assurance.

= Do I need Pro to submit to the DSA Transparency Database? =

Yes — the XML submission generator and API credentials are Pro-only. The free tier lets you generate the transparency report JSON, which you can submit manually to the Commission's database.

= What is a statement of reasons? =

DSA Art. 17 requires that every time you restrict user content (remove a post, demote a product, suspend an account), you issue a structured statement explaining: what was restricted, why (legal basis or ToS clause), whether automated tools were involved, and how the user can appeal.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold.
* Article 16 notice-and-action public form + admin log.
* Article 17 statement-of-reasons log + admin issuing UI.
* Article 30 trader form + admin verification.
* Article 15 / 24 JSON + CSV transparency report.
* CSV export for notices / statements / traders.
* License stub (EC-XXXXXX format).
