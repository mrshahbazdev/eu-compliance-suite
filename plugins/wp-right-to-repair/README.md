# EuroComply Right-to-Repair & Energy Label

EU Right-to-Repair (Directive (EU) 2024/1799), Ecodesign ESPR, and Energy Labelling Regulation (EU 2017/1369) compliance for WooCommerce.

## Scope

| Reference | Area | Supported |
|-----------|------|-----------|
| Directive (EU) 2024/1799 | Right-to-Repair disclosure | per-product meta + shortcodes |
| ESPR (Regulation 2024/1781) | Spare-parts availability years | category-default + per-product override |
| Regulation (EU) 2017/1369 | Energy label A–G | per-product + colour badge + kWh |
| FR Art. L.111-4 | Authorised repairer disclosure | admin CRUD + public shortcode |
| FR Indice de réparabilité | Reparability score | per-product; Pro auto-calculator |
| EPREL | Energy-label database ID | per-product; Pro auto-sync |
| EU Directive 1999/44/EC / 2019/771 | 2-year statutory warranty | configurable, per-product override |

## Features (free)

- Product "General" panel additions (ESPR category, energy class, kWh, reparability, disassembly, spare-parts years, supplier + manual URLs, EPREL ID, warranty)
- Coloured A–G energy badge
- Reparability score badge with 4-tier colour scale
- Spare-parts years badge
- "Repair & parts" product tab with full spec sheet
- Shop-grid badges
- Spare-parts supplier directory (`wp_eurocomply_r2r_suppliers`)
- Authorised-repairer directory (`wp_eurocomply_r2r_repairers`)
- Three public shortcodes: `[eurocomply_r2r_info]`, `[eurocomply_r2r_spares]`, `[eurocomply_r2r_repairers]`
- 7-tab admin UI
- CSV export (suppliers, repairers, products) — 500 rows / 5,000 Pro

## Features (Pro stubs)

- EPREL database sync + energy-label image embed
- FR Indice de réparabilité auto-calculator
- German ReparaturIndex draft calculator
- Digital Product Passport (QR / datamatrix)
- Multi-country spare-parts cross-border matrix
- Extended warranty tracker
- Energy label image generator
- REST API
- 5,000-row CSV cap
- WPML / Polylang multilingual product info
- EU R2R platform submission

## Data model

| Item | Storage |
|------|---------|
| Per-product meta | Post meta on `product` post type (10 keys, `_eurocomply_r2r_*`) |
| Suppliers | `wp_eurocomply_r2r_suppliers` |
| Repairers | `wp_eurocomply_r2r_repairers` |
| Settings | `eurocomply_r2r_settings` option |
| License | `eurocomply_r2r_license` option |

## Non-goals

- Not legal advice
- Does not auto-submit to EPREL, QualiRépar, or any national R2R platform (all Pro)
- Does not generate the official A–G energy-label artwork image (Pro)
- Does not compute FR Indice de réparabilité or DE ReparaturIndex automatically (Pro)
