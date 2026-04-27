# EuroComply CER

Directive (EU) 2022/2557 — Critical Entities Resilience toolkit for WordPress.

Transposition deadline **17 October 2024**. Member States identify "critical entities" by 17 July 2026. Covers 11 sectors of essential services.

## Coverage

| Reference | Surface |
|---|---|
| Annex | 11-sector taxonomy + sub-sectors (energy · transport · banking · FMI · health · drinking water · waste water · digital infrastructure · public administration · space · food) |
| Art. 12 | Risk assessment register — likelihood × impact = 1–25 score, four-yearly cadence with overdue tracker |
| Art. 13 | Resilience measures register — 8 categories (physical, continuity, recovery, access control, training, governance, supply chain, cyber) |
| Art. 15 | Significant-disruption incident register — 24-hour early warning + 1-month follow-up deadline tracker |
| Art. 17 | Cross-border flag on services + incidents |

## Architecture

- 5 DB tables: `services`, `assets`, `risk`, `measures`, `incidents`.
- 11-tab admin: Dashboard · Sectors · Services · Assets · Risk · Measures · Incidents · Reports · Settings · Pro · License.
- Per-incident XML (`urn:cer:eurocomply:0.1`) + JSON payload for early-warning / follow-up stages.
- License stub `EC-XXXXXX` (identical to all 22 sister plugins).

## Pro stubs (not implemented in free)

- Live competent-authority submission.
- Art. 14 background-check workflow.
- Signed PDF reports.
- REST / SIEM webhooks (Splunk / ELK / Datadog).
- Cross-border coordination dashboards.
- OpenStreetMap site view.
- WC service meta.
- WPML / Polylang.
- Multi-site network aggregator.
- 5,000-row CSV cap.
- Slack / Teams alerts on overdue early warnings.
- NIS2 cross-link (cyber significant incidents).
