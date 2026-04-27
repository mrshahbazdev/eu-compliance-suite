=== EuroComply CBAM ===
Contributors: eurocomply
Tags: cbam, carbon, eu, customs, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

EU Carbon Border Adjustment Mechanism (Reg. (EU) 2023/956 + implementing Reg. 2023/1773): per-product CN-8 mapping, embedded-emissions tracking, quarterly Q-report builder (XML), declarant register and accredited-verifier directory.

== Description ==

* Per-product sidebar metabox: CN-8 code, country of origin, direct + indirect tCO₂e, production route, supplier, verified flag.
* Auto-classifies CN-8 codes into Annex I CBAM categories (cement / iron & steel / aluminium / fertilisers / electricity / hydrogen / downstream).
* Imports register `wp_eurocomply_cbam_imports` — per-import line records with reporting period, quantity + unit, direct/indirect emissions, data-source classification (default / estimate / verified).
* Q-report builder produces a transitional-period XML envelope (`urn:cbam:eurocomply:0.1` schema, 2023/1773 reference) downloadable per snapshot.
* Verifier directory `wp_eurocomply_cbam_verifiers` — admin CRUD with country, accreditation ID, scope, contact.
* Frontend: Schema-style emissions card on single product pages.
* CSV export: imports, reports, verifiers (500 free / 5,000 Pro). XML export per stored Q-report.

== Pro features (stubs only) ==

* Full TARIC CN-8 sync with monthly updates
* EU CBAM Registry / Trader Portal API submission
* Signed PDF Q-report
* Supplier portal for verified emissions intake
* WooCommerce order → import-line auto-create
* CBAM-certificate price tracker (definitive period 2026+)
* REST API: /eurocomply/v1/cbam/{imports,reports,verifiers}
* Slack / Teams alerts on quarterly deadline T-7
* WPML / Polylang
* 5,000-row CSV export cap
* Multi-site network aggregator

== Changelog ==

= 0.1.0 =
* Initial scaffold: 9-tab admin, 3 DB tables, CN-8→category map, Annex IV defaults, Q-report XML builder, license stub.
