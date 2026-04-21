# EuroComply GDPR DSAR

GDPR Data Subject Access Request handler for WordPress and WooCommerce.

## Scope

| Article | Right | Supported |
|---------|-------|-----------|
| Art. 15 | Access | yes |
| Art. 16 | Rectification | yes |
| Art. 17 | Erasure | yes (with grace window + WC anonymiser) |
| Art. 18 | Restriction | yes (manual admin workflow) |
| Art. 20 | Portability | yes (machine-readable JSON + CSV ZIP) |
| Art. 21 | Objection | yes (manual admin workflow) |
| Art. 12(3) | 30-day deadline | tracked per-request + dashboard "Overdue" card |
| Art. 12(6) | Identity verification | email-token |

## Features (free)

- Public shortcode `[eurocomply_dsar_form]` — logged-out + logged-in users
- Email-token identity verification (TTL configurable)
- 6 request types mapped to GDPR articles
- Request log DB table with status lifecycle and deadline tracking
- One-click **Build export** → ZIP with `request.json` + `export.csv` + `README.txt`
- Erasure workflow: runs every registered eraser, then hard-deletes the WP user after a grace window
- WC anonymiser automatic (WooCommerce registers its eraser natively)
- Hashed-IP rate limiting (SHA-256 + `wp_salt('nonce')`)
- 6-tab admin UI (Dashboard / Requests / Settings / Exporters / Pro / License)
- CSV export of the request log (500 rows free / 5,000 Pro)
- Acknowledgement + verification + admin notification emails (configurable From + notification list)

## Features (Pro stubs)

- CRM eraser integrations (HubSpot, Mailchimp, Stripe, ActiveCampaign, Klaviyo)
- SFTP / encrypted-email delivery of ZIPs
- Signed PDF audit report
- MFA verification (SMS OTP + TOTP)
- Art. 12(3) 2-month deadline extension workflow
- Multi-site aggregator
- Helpdesk import (Zendesk / Freshdesk / Help Scout)
- REST API
- 5,000-row CSV cap
- WPML / Polylang multilingual email templates

## Data model

| Item | Storage |
|------|---------|
| Request log | `wp_eurocomply_dsar_requests` (15 cols, JSON details) |
| Settings | `eurocomply_dsar_settings` option |
| License | `eurocomply_dsar_license` option |
| Export archives | `wp-content/uploads/eurocomply-dsar/` (deny-all `.htaccess`) |
| Raw IPs | never stored; only SHA-256(ip + `wp_salt('nonce')`) |

## Non-goals

- Not legal advice
- No built-in PII classifier — relies on existing `wp_privacy_personal_data_exporters` registry
- No marketplace-level aggregation (see DSA Transparency plugin)
