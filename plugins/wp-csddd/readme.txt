=== EuroComply CSDDD ===
Contributors: eurocomply
Tags: csddd, due-diligence, supply-chain, human-rights, sustainability, eu, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Corporate Sustainability Due Diligence Directive (Dir. (EU) 2024/1760) toolkit.

== Description ==

Implements operator-side obligations of Dir. (EU) 2024/1760 (CSDDD):

* Chain-of-activities supplier register (Tier 1 / 2 / 3+ / downstream / business partner) with NACE codes and risk scores
* Adverse-impact register covering 12 human-rights categories (Annex Part I) and 6 environmental categories (Annex Part II)
* Art. 10 preventive and Art. 11 corrective action plans with deadlines and ownership
* Art. 14 stakeholder complaints / notification mechanism via `[eurocomply_csddd_complaint_form]` (anonymous-first, follow-up token, hashed-IP rate-limited)
* Art. 22 climate transition plan with 1.5°C / target-year configuration
* Art. 16 annual due-diligence statement (CSRD-linked)
* Auto-generated due-diligence policy via `[eurocomply_csddd_policy]`
* 9-tab admin (Dashboard, Suppliers, Risks, Actions, Complaints, Climate, Settings, Pro, License) with CSV export

= Privacy posture =

Complainant emails are stored only as HMAC-SHA-256 (never plaintext). Follow-up tokens are HMAC-SHA-256 hashed in DB and shown to complainant exactly once. Anonymous filing is supported.

= Pro roadmap (stubs) =

* ESG-data API ingestion (RepRisk / Sustainalytics / EcoVadis)
* Supplier survey portal with scheduled reminders
* Signed PDF Art. 16 annual statement
* Embedded supplier-portal complaint form
* REST API for SIEM / ESG-data-room integration
* CSRD bridge: ESRS S1 / S2 / G1 disclosures auto-link
* 5,000-row CSV cap (free tier 500)
* WPML / Polylang for multi-jurisdiction policy
* Multi-tenant for parent-company group view

== Changelog ==

= 0.1.0 =
* Initial scaffold.
