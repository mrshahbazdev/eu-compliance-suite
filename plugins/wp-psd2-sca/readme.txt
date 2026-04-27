=== EuroComply PSD2 / SCA ===
Contributors: eurocomply
Tags: psd2, sca, payment, banking, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

PSD2 (Directive (EU) 2015/2366) + SCA RTS (Reg. (EU) 2018/389) toolkit: SCA decision engine, exemption library, 3-DS2 challenge log, PSU consent register (Art. 10 RTS), TPP / AISP / PISP directory, fraud event log + Art. 96(6) quarterly report.

== Description ==

* SCA decision engine — input transaction context (amount, cumulative-since-SCA, recurring, MIT, trusted-beneficiary, corporate, TRA score, EEA legs) → output exemption code or "SCA required" with rationale.
* Exemption library: Art. 13 trusted-beneficiary · Art. 14 recurring · Art. 16 low-value (€30 / €100 cumulative / 5 since SCA) · Art. 17 corporate · Art. 18 TRA (with Art. 19 ETV tiers €100/€250/€500 and 13/6/1 bps reference fraud rates) · MIT (EBA Q&A 2018_4031) · one-leg-out.
* 3-DS2 challenge log — outcome categories (frictionless / passed / failed / abandoned / not_attempted) with version tag.
* PSU consent register `wp_eurocomply_psd2_consents` — token stored only as `HMAC-SHA-256(token, wp_salt('auth'))`, default 90-day TTL per Art. 10 RTS, revoke + expiry tracking.
* TPP directory `wp_eurocomply_psd2_tpps` — admin-managed list of interacting AISP / PISP / CBPII / PSP entities with authorisation ID + competent authority.
* Fraud event log `wp_eurocomply_psd2_fraud` — categories (card_lost_stolen / counterfeit / not_received / cnp_fraud / manipulation / phishing / other) × channels (remote_card / card_present / credit_transfer / direct_debit / emoney) with reimbursement + Art. 73 on-time-refund tracking.
* Quarterly fraud report builder — XML envelope (`urn:psd2:eurocomply:0.1`) + JSON payload with totals, exemption breakdown, challenge failure rate, fraud rate, refund compliance.
* CSV export across 4 datasets (transactions / consents / TPPs / fraud), 500 free / 5,000 Pro.

== Pro features (stubs only) ==

* Live EBA TPP-register sync (daily cron)
* Stripe / Adyen / Mollie webhook adapters
* WooCommerce gateway hooks (auto SCA decision on every order)
* Signed PDF Art. 96(6) report (NCA-ready)
* EBA GL/2018/05 fraud reporting CSV (full schema)
* REST API
* Webhook out — push events to SIEM / fraud team
* Slack / Teams alerts on Art. 73 refund-window breach
* Multi-PSP consolidation
* WPML / Polylang TPP directory translations
* 5,000-row CSV export cap
* Multi-site network aggregator

== Privacy ==

This plugin does not store raw PSU consent tokens — only HMAC-SHA-256 fingerprints. PAN, CVV, and authentication factors are never collected; this is a compliance-tracking layer that complements (not replaces) your PSP's PCI-DSS-scoped systems.

== Changelog ==

= 0.1.0 =
* Initial scaffold: 10-tab admin, 4 DB tables, SCA decision engine + exemption library, 3-DS2 log, consent register with HMAC token storage, TPP directory, fraud log, Art. 96(6) report builder, license stub.
