=== EuroComply Product Liability ===
Contributors: eurocomply
Tags: product liability, eu, 2024/2853, ai liability, software defect
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 0.1.0
License: GPLv2 or later

Compliance scaffold for the revised EU Product Liability Directive (Dir. (EU) 2024/2853).

== Description ==

The revised PLD repeals Directive 85/374/EEC and modernises strict liability for defective products. This plugin scaffolds the operator-side surfaces that allow a WordPress / WooCommerce shop to:

* Maintain a register of products, components, software, AI systems and related digital services placed on the EU market.
* Capture consumer defect reports (Art. 8) with privacy-preserving anonymous intake.
* Open formal liability claims (Art. 17) with automatic 3-year-from-awareness limitation tracking and a 25-year extended window for latent personal injury.
* Log Art. 9 disclosure-of-evidence requests with confidentiality flags.
* Publish the statutory Art. 7 manufacturer / EU-representative / importer disclosures via shortcode.

= Free-tier surfaces =

* 4 DB tables: products, defects, claims, disclosures.
* 8-tab admin: Dashboard, Products, Defects, Claims, Disclosures, Settings, Pro, License.
* 3 shortcodes:
  * `[eurocomply_pl_policy]` — manufacturer / representative disclosure block (Art. 7).
  * `[eurocomply_pl_defect_report]` — consumer defect intake form with optional anonymity, follow-up token, honeypot + nonce + rate-limit hooks.
  * `[eurocomply_pl_register]` — public list of recent defect reports (anonymised).
* CSV export (4 datasets, 500 free / 5,000 Pro).
* Auto-calculated 10-year general limitation and 25-year latent-injury limitation per product.
* Auto-calculated 3-year claim-from-awareness limitation per claim.

= Pro stubs =

* Liability insurance auto-syndication (CSV / XML pack).
* Signed PDF claim dossier with evidence index.
* Court-portal export (e.g. e-Justice, beA, RPVA, PolyJur).
* REST API for legal-tech integrations.
* Slack / Teams alert on critical defect or new claim.
* Bulk CSV import for product / SKU register.
* GPSR + Toy Safety bridges (auto-clone product register).
* AI-Act bridge (auto-mark AI-system products & high-risk).
* 5,000-row CSV cap (free tier 500).
* Software-update obligation reminder cron (Art. 11).
* Recall coordinator (cross-plugin with #4 GPSR / #25 Toy Safety).
* Annual liability insurance certificate registry.

= Legal disclaimer =

This plugin is a compliance scaffold, not legal advice. Consult qualified counsel for your specific operations and member-state transposition.

== Changelog ==

= 0.1.0 =
* Initial scaffold.
