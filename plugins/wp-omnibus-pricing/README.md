# EuroComply Omnibus

WordPress / WooCommerce plugin that enforces the **EU Omnibus Directive**'s price-transparency rule (Article 6a Price Indication Directive, Directive (EU) 2019/2161): every sale price must show the trader's **lowest price from the 30 days before the reduction**.

Part of the [EuroComply compliance suite](../../).

## Scope (v0.1.0 — free MVP)

- Records every product and variation price change on `save_post_product` + `save_post_product_variation`
- Filters `woocommerce_get_price_html` on sale items to append `Previous lowest price (last 30 days): …`
- Renders on single-product, shop, category, tag, cart, and checkout pages (loop / cart toggles configurable)
- Honors the introductory-period exemption (new products skip the disclosure until they accumulate history)
- Admin menu **Omnibus** with 5 tabs: *Dashboard · Settings · Price History · Pro Features · License*
- One-click **Backfill** that seeds a history row for every published product / variation
- CSV export of recent price history (nonce-gated, capability-gated, 500-row cap on free)
- `EC-XXXXXX` license stub matching the rest of the EuroComply suite

## Pro stubs (locked behind a valid license key)

- Extended reference windows: 60 / 90 / 180 days
- Per-language (WPML / Polylang) and multi-currency (CURCY / WC Multi-Currency / Aelia) reference prices
- Daily snapshot cron for price changes made via REST / WP-CLI / import (bypasses `save_post`)
- Auditor-ready PDF reports: per-product 30-day lowest timeline, sale windows, introductory-period flag
- Block-editor disclosure block
- Bulk CSV import of historical prices
- Configurable email digest of significant price drops

## Data model

| Table | Purpose |
|-------|---------|
| `wp_eurocomply_omnibus_history` | One row per recorded price state: `product_id`, `parent_id` (for variations), `regular_price`, `sale_price`, `effective_price`, `currency`, `trigger_source` (`save` / `backfill` / `cron`), `recorded_at` |
| Option `eurocomply_omnibus_settings` | Window size, display position, introductory-period config, label template, loop/cart toggles |
| Option `eurocomply_omnibus_license` | License state (activated key + timestamp) |
| Post meta `_eurocomply_omnibus_last` | Last-recorded snapshot per product — used to deduplicate saves that don't change the price |

Uninstall drops the history table, options, license, and per-product meta. Generated exports (CSV downloads) live in the browser only and are not retained server-side.

## Reference-price algorithm

1. On every product / variation save, `PriceTracker::track()` reads `_regular_price` + `_sale_price` and inserts a row unless the snapshot is identical to the previous one.
2. When rendering a sale price, `PriceDisplay::filter_price_html()`:
   - If `exclude_introductory` is on, checks the first recorded `recorded_at` and skips products younger than `introductory_days`.
   - Uses `PriceStore::sale_started_at()` to find when the current sale began, and queries the lowest `effective_price` in the `reference_days` window **before** that timestamp.
   - Falls back to the lowest price in the last `reference_days` if no sale-start marker is found (e.g. long-running sales).
3. The result is rendered as `<span class="eurocomply-omnibus-reference">…</span>` and appended / prepended to the sale price HTML depending on `display_position`.

## Non-goals (v0.1.0)

- No multi-currency price tracking (Pro feature — free tier records everything in the shop default currency)
- No WPML / Polylang integration (Pro)
- No PDF auditor reports (Pro)
- No WP-Cron snapshot job (Pro)
- No block-editor block (Pro)
- No HTTPS licensing endpoint — regex stub only, matches the pattern used by every other EuroComply plugin
