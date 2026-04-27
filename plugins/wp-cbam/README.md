# EuroComply CBAM

Plugin #18 of the EuroComply EU Compliance Suite.

Implements **Regulation (EU) 2023/956** (Carbon Border Adjustment Mechanism) + implementing **Regulation (EU) 2023/1773** (transitional period reporting).

## Coverage

| Surface | What |
|---|---|
| Annex I goods registry | 7 categories (cement / iron & steel / aluminium / fertilisers / electricity / hydrogen / downstream) + ~30 representative CN-8 codes |
| Annex IV defaults | Default direct + indirect tCO₂e per unit per category for transitional-period fallback |
| Per-product metabox | CN-8, country of origin, direct + indirect emissions, production route, supplier, verified flag |
| Imports register | One row per import line, scoped to a `YYYY-Qn` reporting period |
| Q-report builder | XML envelope (`urn:cbam:eurocomply:0.1`, 2023/1773 reference) with header (declarant + EORI + auth-CBAM-decl-id), per-import lines, totals |
| Verifier directory | Admin-CRUD list of accredited verifiers per ISO-2 country |
| Declarant settings | Name, EORI, country, authorised CBAM declarant ID (from 2026), reporting officer email |
| Frontend | Embedded-emissions card on single product pages |
| CSV/XML export | Imports / reports / verifiers CSV (500 free / 5,000 Pro), XML per stored Q-report |

## Architecture

| Component | Purpose |
|---|---|
| `Plugin` | Singleton bootstrap |
| `Settings` | 12 fields incl. `current_period()` helper |
| `License` | `EC-XXXXXX` stub |
| `CbamRegistry` | Static categories + CN-8 map + Annex IV defaults |
| `ProductMeta` | Sidebar metabox + `the_content` filter |
| `ImportStore` (`wp_eurocomply_cbam_imports`) | Per-import lines |
| `ReportStore` (`wp_eurocomply_cbam_reports`) | Stored Q-report snapshots with XML envelope |
| `VerifierStore` (`wp_eurocomply_cbam_verifiers`) | Accredited verifier directory |
| `ReportBuilder` | XML serializer |
| `CsvExport` | 3 CSV datasets + XML download |
| `Admin` | 9-tab UI |

## Free vs Pro

Free ships everything declarants need to **build and self-file** transitional-period Q-reports through the EU CBAM Registry portal manually. Pro stubs (listed in Pro tab + readme.txt, **not** implemented):

- Full TARIC CN-8 sync with monthly updates
- EU CBAM Registry / Trader Portal API submission
- Signed PDF Q-report
- Supplier portal for verified emissions intake
- WooCommerce order → import-line auto-create
- CBAM-certificate price tracker (definitive period 2026+)
- REST API
- Slack / Teams deadline alerts
- WPML / Polylang
- 5,000-row CSV export cap
- Multi-site network aggregator
