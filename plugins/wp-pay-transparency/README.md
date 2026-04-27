# EuroComply Pay Transparency

Plugin #17 of the EuroComply EU Compliance Suite.

Implements **Directive (EU) 2023/970** (Pay Transparency) — transposition deadline 7 June 2026, reporting from 7 June 2027.

## Article-by-article coverage

| Article | Surface |
|---|---|
| Art. 5 | Per-post sidebar metabox (min/max + currency + period + category) → auto pay-range badge on job posts |
| Art. 6 | Pay-setting & progression criteria text blocks via `[eurocomply_pay_setting_criteria]` and `[eurocomply_pay_progression]` |
| Art. 7 | Worker right-to-information form `[eurocomply_pay_info_request]` with 2-month response tracker (overdue red card) |
| Art. 9 | Annual gender-pay-gap report — CSV upload of pay records → `GapCalculator::run()` → snapshot with full per-category payload |
| Art. 10 | Joint-pay-assessment trigger when any category gap exceeds the configured threshold (default 5%) |
| Art. 11 | Pay-categories taxonomy with skills/effort/responsibility/conditions levels |

## Privacy posture

- Employee store retains only `HMAC-SHA-256(external_ref, wp_salt('auth'))` + (category, gender ∈ {w,m,x,u}, total_comp, hours_per_week, currency, year). No names, emails or national IDs touch the DB.
- Worker request form uses honeypot + nonce + hashed-IP rate limit; raw IPs never stored.
- Follow-up tokens stored as `HMAC-SHA-256(token, wp_salt('auth'))`; the raw token is shown to the requester exactly once.

## Architecture

| Component | Purpose |
|---|---|
| `Plugin` | Singleton bootstrap |
| `Settings` | 16 fields incl. `joint_assessment_threshold`, plus `reporting_obligation()` cadence helper (250+ annual / 150+ triennial / 100+ triennial-from-2031) |
| `CategoryStore` (`wp_eurocomply_pt_categories`) | Art. 11 taxonomy |
| `EmployeeStore` (`wp_eurocomply_pt_employees`) | Pseudonymised pay records |
| `RequestStore` (`wp_eurocomply_pt_requests`) | Art. 7 requests (token-keyed) |
| `ReportStore` (`wp_eurocomply_pt_reports`) | Annual snapshots |
| `GapCalculator` | Hourly-equivalent mean+median gap overall and per-category |
| `JobAd` | Sidebar metabox + `the_content` filter |
| `Shortcodes` | 4 frontend shortcodes |
| `CsvImport` / `CsvExport` | Employees ingest + 4 export datasets |
| `Admin` | 9-tab UI |
| `License` | `EC-XXXXXX` stub |

## Free vs Pro

Free ships everything regulators expect for transparency, intake and reporting. Pro stubs (listed in the Pro tab + readme.txt, **not** implemented):

- Payroll integrations (DATEV / SAP / Personio / BambooHR / HiBob / Workday)
- NACE Rev.2 classifier
- Signed PDF Art. 9 report
- REST API
- Slack / Teams alerts
- Joint-assessment workflow with worker-rep approval
- WPML / Polylang
- Schema.org JobPosting `baseSalary`
- 5,000-row CSV export cap
- Multi-site network aggregator
- EU monitoring-body submission helper
