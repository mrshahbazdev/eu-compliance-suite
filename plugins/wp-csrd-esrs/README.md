# EuroComply CSRD / ESRS

Plugin #19 of the EuroComply EU Compliance Suite.

Implements **Directive (EU) 2022/2464** (Corporate Sustainability Reporting Directive) + **ESRS Set 1** (delegated Reg. (EU) 2023/2772): applicability detection, double-materiality, datapoint collection, assurance log, XBRL-style report builder.

## Coverage

| Surface | What |
|---|---|
| Applicability | Auto-detects first reporting year (PIE/listed-500+ → FY 2024 · large → FY 2025 · listed SME → FY 2026 · third-country parent > €150m → FY 2028) |
| ESRS standards | 12 codes (ESRS 1 / ESRS 2 / E1–E5 / S1–S4 / G1) |
| Datapoints | ~50 representative datapoints across most-frequently-asked disclosures |
| Materiality | Impact × financial scoring (0–5) with configurable threshold, horizon, value-chain scope, rationale |
| Assurance | Limited / reasonable / unaudited engagement records with provider, scope, opinion, signatory, timestamp |
| Reports | XBRL-style XML envelope (`urn:csrd:eurocomply:0.1`) + JSON payload per snapshot |
| Export | CSV (datapoints / materiality / assurance / reports), 500 free / 5,000 Pro · XBRL XML + JSON downloads per stored report |

## Architecture

| Component | Purpose |
|---|---|
| `Plugin` | Singleton bootstrap |
| `Settings` | 14 fields incl. `applicability()` engine |
| `License` | `EC-XXXXXX` stub |
| `EsrsRegistry` | Static standards + datapoints catalogue |
| `MaterialityStore` (`wp_eurocomply_csrd_materiality`) | Topic-level IRO assessments |
| `DatapointStore` (`wp_eurocomply_csrd_datapoints`) | Year-keyed numeric/narrative values |
| `AssuranceStore` (`wp_eurocomply_csrd_assurance`) | Limited/reasonable engagement log |
| `ReportStore` (`wp_eurocomply_csrd_reports`) | Report snapshots with XBRL + JSON |
| `ReportBuilder` | XBRL serializer + JSON payload |
| `CsvExport` | 4 CSV datasets + XBRL/JSON downloads |
| `Admin` | 9-tab UI |

## Free vs Pro

Free ships everything an in-scope undertaking needs to **prepare and self-publish** an ESRS-aligned sustainability statement and present it to a limited-assurance auditor for review. Pro stubs (listed in Pro tab + readme.txt, **not** implemented):

- Full EFRAG ESRS XBRL taxonomy (~1,100 datapoints) + auto-validation
- ESEF inline-XBRL (iXBRL) tagged management report
- Signed PDF sustainability statement (auditor-ready)
- Materiality matrix renderer (PDF + interactive plot)
- Supplier portal for value-chain emissions/labour data intake
- GHG Protocol Scope-3 calculator (15 categories)
- EU Taxonomy (Reg. 2020/852) eligibility + alignment KPI engine
- REST API
- WPML / Polylang multi-language disclosures
- 5,000-row CSV export cap
- Multi-site network aggregator (group consolidation)
- Assurance-engagement signed-evidence vault
