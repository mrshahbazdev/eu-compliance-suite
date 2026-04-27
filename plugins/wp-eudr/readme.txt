=== EuroComply EUDR ===
Contributors: eurocomply
Tags: eudr, deforestation, supply-chain, traces, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

EU Deforestation-free Products Regulation (Reg. (EU) 2023/1115) toolkit: 7-commodity registry, supplier directory, plot / geolocation register, country risk overrides, shipment & DDS register with TRACES NT-style XML / JSON envelopes.

== Description ==

* 7 in-scope commodities (Annex I): cattle · cocoa · coffee · oil palm · rubber · soya · wood — with representative HS chapters.
* Supplier directory `wp_eurocomply_eudr_suppliers` with role taxonomy (producer / trader / cooperative / aggregator / broker).
* Plot / geolocation register `wp_eurocomply_eudr_plots` (Art. 9(1)(d)) — point or polygon (GeoJSON), area in hectares, production date range, deforestation check status (pending / pass / fail / inconclusive).
* Country-risk classifier (Art. 29) — heuristic high-risk seed per commodity, admin overrides per country (low / standard / high). Pro syncs the Commission's official list when published.
* Shipment / DDS register `wp_eurocomply_eudr_shipments` — commodity, HS code, quantity, country of origin, plot IDs, upstream-DDS reference, TRACES reference, status lifecycle (draft → submitted → accepted / rejected).
* Risk-assessment + mitigation log `wp_eurocomply_eudr_risk` (Art. 10 / Art. 11) — 9 factor categories × 3 levels × negligible / non-negligible conclusion.
* DDS builder — XML envelope (`urn:eudr:eurocomply:0.1`) + JSON payload per shipment, ready for TRACES NT pre-filing.
* CSV export across 4 datasets (shipments / suppliers / plots / risk), 500 free / 5,000 Pro.

== Pro features (stubs only) ==

* Live TRACES NT submission (operator role)
* Satellite-imagery deforestation check vs. 31 Dec 2020 cut-off
* Commission country-risk-list sync (Art. 29)
* Signed PDF Due Diligence Statement
* WooCommerce per-product EUDR meta
* Polygon ingest (KML / Shapefile / GeoJSON FeatureCollection)
* Map view (OpenLayers) with deforestation overlay
* Supplier portal (third-party uploads)
* REST API
* Slack / Teams alerts on rejected DDS or non-negligible risk
* WPML / Polylang
* Multi-site network aggregator
* 5,000-row CSV export cap

== Privacy ==

This plugin handles supply-chain trace data, not personal data. No cookies, no IP logging, no third-party network calls in free tier.

== Changelog ==

= 0.1.0 =
* Initial scaffold: 10-tab admin, 4 DB tables, 7-commodity Annex-I registry, plot register with GeoJSON polygons, country-risk classifier, shipment / DDS register, risk-assessment log, DDS builder (XML + JSON), license stub.
