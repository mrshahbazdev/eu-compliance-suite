# EuroComply DORA

Reg. (EU) 2022/2554 — Digital Operational Resilience Act toolkit for WordPress.

Applies from **17 January 2025** to credit institutions, investment firms, payment / e-money institutions, crypto-asset service providers, insurance / reinsurance, crowdfunding service providers, and other financial entities listed in Art. 2(1).

## Coverage

| Article | Surface |
|---|---|
| Art. 5–14 | Governance + ICT risk-management framework (policy register) |
| Art. 17–23 | Incident management — auto-classification (major / significant / none) + 4h / 72h / 1-month deadline tracker |
| Art. 24–27 | Resilience-testing log (vuln scan / pen test / TLPT / scenario / BCP) |
| Art. 28(3) | ICT third-party **Register of Information** with criticality tiers |
| Art. 45 | Information-sharing log (TLP-tagged) |

## Architecture

- 5 DB tables: `incidents`, `third_parties`, `tests`, `policies`, `intel`.
- 10-tab admin: Dashboard · Incidents · Third parties · Tests · Policies · Info sharing · Reports · Settings · Pro · License.
- Per-incident XML (`urn:dora:eurocomply:0.1`) + JSON payload, generated for any of the three reporting stages.
- License stub `EC-XXXXXX` (identical to all 21 sister plugins).

## Pro features (not implemented in free)

- Live competent-authority submission.
- Signed PDF + audit bundle.
- REST / SIEM webhooks (Splunk / ELK / Datadog).
- TLPT workflow (Art. 26).
- CTPP registry sync.
- XBRL submission helper.
- WooCommerce ICT-service per-product meta.
- WPML / Polylang.
- Multi-site network aggregator.
- 5,000-row CSV cap.
- Slack / Teams / PagerDuty alerts on overdue stages.
- NIS2 cross-link (deduplicates incidents).
