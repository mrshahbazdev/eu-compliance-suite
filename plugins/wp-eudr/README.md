# EuroComply EUDR

Plugin #21 of the EuroComply EU Compliance Suite.

Implements **Regulation (EU) 2023/1115** on the placing on the Union market and the export from the Union of certain commodities and products associated with deforestation and forest degradation (EUDR).

## Coverage

| Surface | What |
|---|---|
| Commodity registry | 7 in-scope commodities (Annex I): cattle · cocoa · coffee · oil palm · rubber · soya · wood — with representative HS chapters |
| Supplier directory | Role taxonomy: producer / trader / cooperative / aggregator / broker |
| Plot register | **Art. 9(1)(d)** geolocation — point or polygon (GeoJSON), area in hectares, production date range, deforestation-check status (pending / pass / fail / inconclusive) |
| Country-risk classifier | **Art. 29** — heuristic high-risk seed per commodity, admin overrides per country (low / standard / high) |
| Shipment / DDS register | Commodity · HS code · quantity · country of origin · plot IDs · upstream-DDS · TRACES reference · status lifecycle (draft → submitted → accepted / rejected) |
| Risk-assessment log | **Art. 10 / Art. 11** — 9 factor categories × 3 levels × negligible / non-negligible conclusion |
| DDS builder | XML envelope (`urn:eudr:eurocomply:0.1`) + JSON payload per shipment, ready for TRACES NT pre-filing |
| Export | CSV (shipments / suppliers / plots / risk) — 500 free / 5,000 Pro · XML + JSON per shipment |

## Architecture

| Component | Purpose |
|---|---|
| `Plugin` | Singleton bootstrap |
| `Settings` | 13 fields incl. `cutoff_date=2020-12-31`, `operator_role` (operator/trader/sme_*), `is_sme()` |
| `License` | `EC-XXXXXX` stub |
| `CommodityRegistry` | Static `commodities()` + `options()` + `get()` |
| `CountryRisk` | Heuristic seed + admin overrides (`level()`, `set_override()`, `clear_override()`) |
| `SupplierStore` (`wp_eurocomply_eudr_suppliers`) | Supplier directory CRUD |
| `PlotStore` (`wp_eurocomply_eudr_plots`) | Plot register with GeoJSON polygons |
| `ShipmentStore` (`wp_eurocomply_eudr_shipments`) | Shipment / DDS lifecycle |
| `RiskStore` (`wp_eurocomply_eudr_risk`) | Risk-assessment + mitigation log |
| `DdsBuilder` | XML envelope + JSON payload per shipment |
| `CsvExport` | 4 CSV datasets + XML + JSON per shipment |
| `Admin` | 10-tab UI |

## Free vs Pro

Free ships every compliance-tracking surface an operator / trader needs to **collect supply-chain data and self-prepare** a DDS for TRACES NT. Pro stubs (Pro tab + readme.txt, **not** implemented):

- Live TRACES NT submission
- Satellite-imagery deforestation check vs. 31 Dec 2020 cut-off
- Commission country-risk-list sync (Art. 29)
- Signed PDF Due Diligence Statement
- WooCommerce per-product EUDR meta
- Polygon ingest (KML / Shapefile / GeoJSON FeatureCollection)
- Map view (OpenLayers) with deforestation overlay
- Supplier portal (third-party uploads)
- REST API
- Slack / Teams alerts on rejected DDS or non-negligible risk
- WPML / Polylang
- Multi-site network aggregator
- 5,000-row CSV export cap

## Privacy posture

- Supply-chain trace data only, no personal data
- No cookies, no IP logging, no third-party network calls in free tier
- Polygon GeoJSON is validated as JSON before persistence
