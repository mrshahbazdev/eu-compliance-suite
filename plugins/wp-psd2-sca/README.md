# EuroComply PSD2 / SCA

Plugin #20 of the EuroComply EU Compliance Suite.

Implements **Directive (EU) 2015/2366** (PSD2) + **Reg. (EU) 2018/389** (SCA RTS) + **EBA GL/2018/05** (fraud reporting).

## Coverage

| Surface | What |
|---|---|
| SCA decision engine | Input transaction context → exemption / "SCA required" with cited article |
| Exemption library | Art. 13 trusted-beneficiary · Art. 14 recurring · Art. 16 low-value · Art. 17 corporate · Art. 18 TRA (Art. 19 ETV tiers) · MIT · one-leg-out |
| 3-DS2 challenge log | Outcome categories × version tagging |
| PSU consent register | HMAC-SHA-256 token storage · 90-day TTL · revoke + expiry tracking (Art. 10 RTS) |
| TPP directory | AISP / PISP / CBPII / PSP entries with authorisation ID + competent authority |
| Fraud event log | 7 categories × 5 channels · reimbursement + Art. 73 on-time-refund tracking |
| Quarterly report | XML (`urn:psd2:eurocomply:0.1`) + JSON: totals, exemption breakdown, fraud rate, challenge failure rate, refund compliance |
| Export | CSV (transactions / consents / TPPs / fraud) — 500 free / 5,000 Pro · XML + JSON downloads |

## Architecture

| Component | Purpose |
|---|---|
| `Plugin` | Singleton bootstrap |
| `Settings` | 16 fields incl. `current_period()` (YYYY-Qn) |
| `License` | `EC-XXXXXX` stub |
| `ScaRules` | Static `decide()` + `exemptions()` + `tra_tier()` |
| `TransactionStore` (`wp_eurocomply_psd2_transactions`) | SCA + 3-DS2 outcomes |
| `ConsentStore` (`wp_eurocomply_psd2_consents`) | PSU consents with HMAC tokens |
| `TppStore` (`wp_eurocomply_psd2_tpps`) | TPP directory |
| `FraudStore` (`wp_eurocomply_psd2_fraud`) | Fraud event log |
| `ReportBuilder` | Quarterly XML + JSON |
| `CsvExport` | 4 CSV datasets + XML + JSON |
| `Admin` | 10-tab UI |

## Free vs Pro

Free ships every compliance-tracking surface a PSP / merchant needs to **run their own SCA decisions, log fraud events, and self-prepare** an Art. 96(6) report for their NCA. Pro stubs (listed in Pro tab + readme.txt, **not** implemented):

- Live EBA TPP-register sync (daily cron)
- Stripe / Adyen / Mollie webhook adapters → auto-log transactions
- WooCommerce gateway hooks (auto SCA decision on every order)
- Signed PDF Art. 96(6) report (NCA-ready)
- EBA GL/2018/05 fraud reporting CSV (full schema)
- REST API
- Webhook out — push events to SIEM / fraud team
- Slack / Teams alerts on Art. 73 refund-window breach
- Multi-PSP consolidation (group fraud rate calculation)
- WPML / Polylang TPP directory translations
- 5,000-row CSV export cap
- Multi-site network aggregator

## Privacy posture

- PSU consent tokens stored as `HMAC-SHA-256(token, wp_salt('auth'))` — **raw token never persisted**
- PAN / CVV / authentication factors **never collected** — this layer is a compliance tracker, not a PCI-DSS scoped system
- This plugin does not transmit any data outside the WP install (the live EBA + webhook adapters are Pro)
