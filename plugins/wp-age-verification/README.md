# EuroComply Age Verification

Plugin #10 in the EuroComply suite. EU / EEA age-verification gate for WordPress and WooCommerce with per-country minimum-age rules and category-level gating.

## Scope

| Regulation | Jurisdiction | How this plugin helps |
|-----------|--------------|-----------------------|
| JMStV §4 Abs. 2 | Germany | DOB entry baseline + Pro AusweisIdent / SCHUFA |
| ARCOM double-blind | France | Gate + Pro Veriff/Onfido integration |
| Systembolaget 20+ | Sweden | Per-country minimum-age table bundles SE=20 |
| UK Online Safety Act | United Kingdom | Gate + Pro parental-consent workflow |
| General alcohol / tobacco / adult commerce | EU-27 | WooCommerce category gate + per-product override |

## Features

- Three gate modes: **site-wide**, **category-only** (WooCommerce), **shortcode-only**.
- DOB-based verification (server-side age computation) or simple-checkbox mode.
- **Per-country minimum-age rules** seeded for all EU-27 (DE 16, SE 20, LT 20, LU/AT 16, CY/MT 17, rest 18).
- **WooCommerce integration**: per-category rules, per-product age override, cart & checkout enforcement.
- **Verification audit log** with hashed IP (no raw PII stored), queryable from admin, CSV export.
- **HMAC-signed session cookie** — no re-verification on every page while still being tamper-evident.
- 6-tab admin UI: Dashboard · Settings · Verification Log · Categories · Pro · License.
- Shortcode `[eurocomply_age_gate min_age="18"]` for page-level placement.

## Data model

- `wp_eurocomply_av_verifications` — every attempt (pass or block) with hashed IP, declared birth year, computed age, required age, method, context, session-token hash.
- Product post-meta `_eurocomply_av_min_age` — per-product override.
- Option `eurocomply_av_settings` — gate mode, default age, restricted categories, country rules, cookie duration, modal copy.

## Non-goals

- Content classification / "is this product alcohol?" — operator defines restricted categories.
- ID-document OCR, biometric matching, liveness checks — Pro (Veriff / Onfido integration).
- Legal advice or jurisdiction-specific compliance assurance.

## License

GPL-2.0-or-later.
