# EuroComply DSA Transparency

Plugin #9 in the EuroComply suite. Implements the core EU Digital Services Act (Regulation (EU) 2022/2065) obligations for WordPress / WooCommerce marketplaces.

## Scope

| Article | Obligation | How this plugin delivers |
|--------|------------|---------------------------|
| Art. 14 | T&Cs | Settings fields for terms URL, complaints URL, contact email, EU legal rep |
| Art. 15 | Transparency reports | Aggregated JSON + CSV export over any period |
| Art. 16 | Notice-and-action | Public shortcode `[eurocomply_dsa_notice_form]` + admin log |
| Art. 17 | Statement of reasons | Structured log with decision ground, category, automated flags, redress info |
| Art. 20 | Internal complaint handling | Settings → complaints URL (external workflow for now) |
| Art. 24 | Quantitative transparency | Same report generator; cadence settings |
| Art. 30 | Trader traceability / KYBP | Public shortcode `[eurocomply_dsa_trader_form]` + admin verification |

## Free vs Pro

**Free** ships everything needed to operate a single-site marketplace in compliance:

- 8-tab admin UI (Dashboard · Notices · Statements · Traders · Report · Settings · Pro · License)
- Notice form with nonce, honeypot, per-IP rate limit, hashed IP log
- Trader form with logged-in guard + admin verification workflow
- Issue statement of reasons from wp-admin
- Annual transparency report → JSON + CSV download
- Dataset CSV exports (notices / statements / traders), 500-row cap

**Pro stubs** (not implemented in this scaffold):

- DSA Transparency Database submission (Commission XML schema)
- Out-of-court dispute resolution workflow (Art. 21)
- Strike / reputation system (Art. 23)
- Marketplace plugin integrations (WC Vendors / Dokan / WCFM) with vendor KYBP sync
- Scheduled annual / semi-annual / quarterly report cron
- Multi-language T&Cs and notice form (WPML / Polylang)
- Signed PDF transparency reports
- Trusted-flagger whitelisting (Art. 22)
- REST API for external moderation tools
- 5,000-row CSV export cap

## Data model

- `wp_eurocomply_dsa_notices` — Art. 16 notice log (reporter, target, category, legal basis, status).
- `wp_eurocomply_dsa_statements` — Art. 17 decisions (ground, restriction, category, automated flags).
- `wp_eurocomply_dsa_traders` — Art. 30 trader dossier (unique by user_id; verification workflow).

## Non-goals

- Automated content moderation (illegal content detection, classifiers).
- Legal advice or guaranteed compliance assurance.
- DSA Transparency Database submission (Pro only).
- Direct marketplace plugin integrations (Pro only).

## License

GPL-2.0-or-later.
