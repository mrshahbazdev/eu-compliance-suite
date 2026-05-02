=== EuroComply Forced Labour ===
Contributors: eurocomply
Tags: forced-labour, supply-chain, due-diligence, ilo, eu, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reg. (EU) 2024/3015 prohibition of products made with forced labour — operator-side toolkit.

== Description ==

Operator-side compliance toolkit for **Reg. (EU) 2024/3015** prohibiting products made with forced labour from being placed or made available on the EU market or exported from it.

* **Supplier register** with country, region, sector, tier (1 / 2 / 3+) and 0–100 risk score
* **ILO 11-indicator** risk register (abuse of vulnerability, deception, restriction of movement, isolation, physical/sexual violence, intimidation/threats, retention of identity documents, withholding of wages, debt bondage, abusive working/living conditions, excessive overtime)
* **Audit & certification log** keyed to 17 schemes (SA8000, amfori BSCI, Sedex SMETA, FLA, WRAP, ISO 45001, ISO 26000, RBA VAP, Fairtrade, Rainforest Alliance, UTZ, RSPO, GOTS, OEKO-TEX, first-party, second-party, other) with expiry tracking
* **Public information submission form** (Art. 9) — anonymous-by-default, follow-up token, hashed email, hashed IP, 6-state status lifecycle (received / acknowledged / investigating / forwarded / closed / rejected)
* **Withdrawal procedure log** for ban-decision implementation (Art. 20+) with decision ref, channels, units recalled, disposal method
* **Country-risk seed list** of 35 high-risk jurisdictions and **16 sector taxonomy** (cotton, apparel, fisheries, electronics, polysilicon, cocoa, coffee, sugar, palm oil, rubber, mining, bricks, tobacco, construction, agriculture, other)
* 9-tab admin: Dashboard · Suppliers · Risk register · Audits · Submissions · Withdrawals · Settings · Pro · License
* 2 shortcodes: `[eurocomply_fl_statement]` and `[eurocomply_fl_submit]`
* CSV export for 5 datasets (500 free / 5,000 Pro)

= Privacy posture =

* Submitter emails stored only as HMAC-SHA-256
* Follow-up tokens stored only as HMAC-SHA-256; raw token shown to submitter exactly once
* Anonymous submissions are first-class
* Spam protection: nonce + honeypot field

= Pro roadmap (stubs) =

* EU "single information submission point" forwarder
* Walk Free Global Slavery Index country-risk auto-sync
* Sedex / amfori / SAI API ingestion of audit reports
* Signed PDF supplier-due-diligence dossier
* REST API for ESG dashboards
* CSDDD plugin bridge (re-use chain-of-activities)
* WPML / Polylang multilingual public submission form
* Slack / Teams alert on new submission
* 5,000-row CSV cap (free tier 500)
* Bulk CSV import for supplier register
* Withdrawal-procedure signed evidence packet (Art. 20 fulfilment)

== Changelog ==

= 0.1.0 =
* Initial scaffold.
