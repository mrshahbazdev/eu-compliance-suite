# EuroComply EU VAT Validator & OSS Rates

WordPress / WooCommerce plugin that:

- Validates EU VAT numbers against the official **VIES** REST endpoint
  (`https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{country}/vat/{number}`)
  with a per-country format pre-check and 24h transient cache.
- Ships the **EU-27 standard VAT rates** as a browsable admin table
  (reduced / super-reduced rates are Pro-tier).
- Applies **B2B reverse charge** at WooCommerce checkout for cross-border EU
  sales with a VIES-valid VAT number (zeroes the tax lines and relabels them
  "Reverse charge (EU B2B xx)").
- Keeps a pseudonymised **transaction log** of every VIES check and every
  order-level decision so the merchant can prove due-diligence during a VAT
  audit.
- Exposes a REST endpoint for the storefront JS to hit without leaking the
  VIES endpoint directly.

## Admin UI

Top-level menu **VAT & OSS** with 5 tabs:

| Tab          | What it does                                                    |
| ------------ | --------------------------------------------------------------- |
| Settings     | Shop country, reverse-charge toggle, VIES on/off, timeouts, copy. |
| VAT Rates    | Read-only EU-27 standard-rate table (source: EU TEDB v3).       |
| VIES Test    | Manually check a VAT number from the admin.                     |
| Transactions | Paginated recent log with purge-all button.                     |
| License      | Paste an `EC-XXXXXX` Pro key (stub validation for now).         |

## REST endpoints

```
POST /wp-json/eurocomply-vat/v1/validate   (nonce-gated, used by checkout.js)
GET  /wp-json/eurocomply-vat/v1/rates      (public, returns the EU-27 table)
```

`POST /validate` requires the standard `X-WP-Nonce` header. It:

1. Normalises the input (strip spaces / dots / dashes, uppercase).
2. Runs a cheap per-country regex pre-check.
3. Hits VIES (unless `validate_via_vies` is turned off).
4. Writes a `vies_check` row to the transaction log.

## Checkout integration

When WooCommerce is active:

- Adds a `billing_eurocomply_vat` field to the billing section (priority 125).
- Debounces 300ms then POSTs to `/validate` on blur, showing inline
  valid / invalid / checking / error states.
- On a valid response, triggers WooCommerce's `update_checkout` so the
  tax lines are recalculated.
- `woocommerce_matched_rates` filter zeroes the rate + relabels it
  "Reverse charge (EU B2B xx)" when:
  - `reverse_charge_b2b` is on
  - VAT number is VIES-valid
  - buyer country is EU and differs from shop country
- On order creation, persists the VAT + decision to order meta and writes an
  `order_checkout` row to the log.
- Admin order screen shows a green "VIES-validated" / red "Not validated"
  badge next to the billing address.

## Data sources

- **VIES REST**: public, no key required, rate limited by Member State.
- **Rates**: `data/eu-vat-rates.json`, sourced from the EU Commission's Taxes
  in Europe Database (TEDB) v3, January 2025 snapshot. Must be refreshed
  manually when a Member State changes its standard rate.

## Out of scope (flagged for follow-up)

- **Reduced / super-reduced rate application** per product category.
  Free tier only ships the table; application logic is Pro-only.
- **OSS threshold tracker** (€10,000 EU cross-border B2C turnover per year).
  Placeholder setting exists; tracker itself is Pro.
- **Quarterly MOSS/OSS CSV export** — Pro.
- **Bulk VIES validation** of existing customer base — Pro.
- **Licensing server** — `License::verify()` is a local-regex stub; a real
  HTTPS licensing endpoint replaces it in a follow-up PR.

## Development

`php -l` is the only hard gate in this MVP. No unit tests yet; they arrive
alongside Phase 2 when we add WP-CLI commands. Runtime GUI testing is
expected in the companion PR following the same methodology as Plugin #1
and Plugin #2.
