=== EuroComply Pay Transparency ===
Contributors: eurocomply
Tags: pay-transparency, gender-pay-gap, gdpr, eu, compliance, hr
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

Directive (EU) 2023/970: pay-range disclosure on job ads (Art. 5), pay-setting & progression criteria (Art. 6), worker right-to-information request workflow with 2-month tracker (Art. 7), annual gender-pay-gap report (Art. 9), joint-assessment trigger above 5% (Art. 10), pay-categories taxonomy (Art. 11).

== Description ==

* Per-post sidebar metabox for pay min/max + currency + period + category — auto-injects an Art. 5 badge into job posts.
* Public shortcode `[eurocomply_pay_info_request]` — Art. 7 worker request form (honeypot + nonce + hashed-IP rate limit).
* Public shortcodes `[eurocomply_pay_setting_criteria]` and `[eurocomply_pay_progression]` — Art. 6 admin-managed text blocks.
* Pay-categories taxonomy (Art. 11) — admin CRUD with skills/effort/responsibility/working-conditions levels (0–10) and pay range.
* Pseudonymised employee store — only HMAC-keyed `external_ref` + (category, gender ∈ {w,m,x,u}, total_comp, hours_per_week). No names, no emails, no national IDs.
* Gap calculator — mean and median pay gap overall and per category, hourly-equivalent (kills part-time bias).
* Joint pay assessment trigger — any category gap above the configured % flips the snapshot's `joint_assessment_required` flag.
* Annual report snapshots stored in `wp_eurocomply_pt_reports` with full per-category JSON payload.
* CSV import (employees) + CSV export (categories, employees, reports, requests). 500 free / 5,000 Pro.

== Pro features (stubs only) ==

* Payroll integrations (DATEV / SAP SuccessFactors / Personio / BambooHR / HiBob / Workday)
* Eurostat NACE Rev.2 classifier auto-suggesting category groupings
* Signed PDF Art. 9 report (DPO-ready)
* REST API: /eurocomply/v1/pay/{requests,reports,categories}
* Slack / Teams alerts on every new Art. 7 request
* Joint-assessment workflow with worker-rep approval (Art. 10)
* WPML / Polylang multi-language pay-range translations
* Schema.org JobPosting structured data with `baseSalary` (Google Jobs)
* 5,000-row CSV export cap
* Multi-site network aggregator
* EU monitoring-body submission helper (national portal pre-fill)

== Changelog ==

= 0.1.0 =
* Initial scaffold: 9-tab admin, 4 DB tables, Art. 5/6/7/9/10/11 surfaces, gap calculator, CSV import/export, license stub.
