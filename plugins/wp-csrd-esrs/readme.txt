=== EuroComply CSRD / ESRS ===
Contributors: eurocomply
Tags: csrd, esrs, sustainability, esg, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

EU Corporate Sustainability Reporting Directive (Directive (EU) 2022/2464) + ESRS Set 1: applicability detection, double-materiality assessment workflow, datapoint collection across ESRS 2 / E1–E5 / S1–S4 / G1, assurance log, XBRL-style report builder.

== Description ==

* Applicability auto-detection by company size, listing status, employees, turnover, balance-sheet and EU-revenue thresholds. Returns first reporting year per Directive 2022/2464 phase-in.
* ESRS standards directory (12 codes: ESRS 1, ESRS 2, E1–E5, S1–S4, G1) + ~50 representative datapoints across the most-frequently-asked disclosures.
* Double-materiality register `wp_eurocomply_csrd_materiality` — impact + financial scoring with configurable threshold, time horizon, value-chain scope, rationale.
* Datapoint store `wp_eurocomply_csrd_datapoints` — year-keyed numeric/narrative values per datapoint, source-tagged.
* Assurance log `wp_eurocomply_csrd_assurance` — limited / reasonable / unaudited engagements with provider, scope, opinion, signatory, signed timestamp.
* Report builder produces an XBRL-style XML envelope (`urn:csrd:eurocomply:0.1`) plus a JSON payload mirror; both downloadable per stored snapshot.
* CSV export across 4 datasets (datapoints / materiality / assurance / reports), 500 free / 5,000 Pro.

== Pro features (stubs only) ==

* Full EFRAG ESRS XBRL taxonomy (~1,100 datapoints) + auto-validation
* ESEF inline-XBRL (iXBRL) tagged management report
* Signed PDF sustainability statement (auditor-ready)
* Materiality matrix renderer (PDF + interactive plot)
* Supplier portal — value-chain partners upload S2/E1-Scope-3 data
* GHG Protocol Scope-3 calculator (15 categories)
* EU Taxonomy (Reg. 2020/852) eligibility & alignment KPI engine
* REST API
* WPML / Polylang multi-language disclosures
* 5,000-row CSV export cap
* Multi-site network aggregator (group consolidation)
* Assurance-engagement signed-evidence vault

== Changelog ==

= 0.1.0 =
* Initial scaffold: 9-tab admin, 4 DB tables, applicability engine, materiality + datapoint + assurance stores, XBRL/JSON report builder, license stub.
