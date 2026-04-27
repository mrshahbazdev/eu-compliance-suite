# EuroComply Compliance Dashboard

Site-wide aggregator for the EuroComply EU compliance suite. Surfaces a unified compliance score, plugin status grid, alerts feed, statutory deadline calendar, and snapshot history across all installed EuroComply plugins.

## Aggregated plugins (20)

| Plugin | Reference |
|--------|-----------|
| Legal Pages | GDPR Art. 13–14, ePrivacy, TMG §5 |
| Cookie Consent + GCM v2 | ePrivacy 2002/58, GDPR Art. 7, GCM v2 |
| VAT OSS | Council Directive 2017/2455 |
| General Product Safety | Regulation (EU) 2023/988 |
| Extended Producer Responsibility | WEEE 2012/19, Packaging 94/62, Batteries 2023/1542 |
| European Accessibility Act | Directive (EU) 2019/882 |
| Omnibus 30-day price | Directive (EU) 2019/2161 |
| DSA Transparency | Regulation (EU) 2022/2065 |
| Age Verification | JMStV (DE), ARCOM (FR), OSA (UK) |
| GDPR DSAR | GDPR Art. 15 / 16 / 17 / 18 / 20 / 21 |
| NIS2 & CRA | Directive (EU) 2022/2555 + CRA |
| Right-to-Repair & Energy Label | Dir. (EU) 2024/1799, ESPR 2024/1781, Reg. 2017/1369 |
| AI Act Transparency | Regulation (EU) 2024/1689 Art. 50 |
| Whistleblower | Directive (EU) 2019/1937 |
| ePrivacy & Tracker Registry | Dir. 2002/58/EC + ePrivacy Reg. (proposal) |
| Pay Transparency | Directive (EU) 2023/970 |
| Carbon Border Adjustment (CBAM) | Reg. (EU) 2023/956 + 2023/1773 |
| CSRD / ESRS | Dir. (EU) 2022/2464 + ESRS Set 1 |
| PSD2 / SCA | Dir. (EU) 2015/2366 + RTS 2018/389 |
| EU Deforestation Regulation (EUDR) | Reg. (EU) 2023/1115 |

## Components

- **Connectors** — one method per suite plugin in `class-connectors.php`; each detects active state via class/option/table presence and returns a uniform shape (`metrics`, `alerts`, `score`)
- **Aggregator** — runs all connectors, computes overall score (mean across active plugins) and merges the alert stream
- **SnapshotStore** — `wp_eurocomply_dashboard_snapshots` table for daily compliance-score capture
- **Admin** — 8-tab UI (Overview · Plugins · Alerts · Calendar · History · Settings · Pro · License)
- **CsvExport** — 3 datasets (plugins / alerts / snapshots), 500 / 5,000 cap
- **License** — `EC-XXXXXX` stub gating Pro features (daily cron, REST API, SIEM forwarding, multisite)

## Alerts surfaced today

- Legal Pages — fewer than 4 statutory pages configured
- Cookie Consent — no categories defined
- Omnibus — no price-history rows yet (run backfill)
- GDPR DSAR — open requests past Art. 12(3) 30-day deadline
- NIS2 — open incidents past Art. 23 24-hour or 72-hour windows
- Whistleblower — reports past Art. 9(1)(b) 7-day acknowledgement / Art. 9(1)(f) 3-month feedback windows
- ePrivacy — no tracker scans run yet
- Pay Transparency — worker info requests past Art. 7(1) two-month window
- CBAM — import rows still using default emissions
- CSRD — double-materiality assessment empty
- PSD2 — fraud rate exceeds RTS 2018/389 reference band
- EUDR — high-risk shipments / DDS still in draft

## Non-goals

- Does not duplicate any sister plugin's storage
- Does not make outbound regulator submissions (Pro: SIEM webhook, signed PDF)
- Not a substitute for legal advice
