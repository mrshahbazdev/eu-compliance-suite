# EuroComply E-Invoicing

WordPress / WooCommerce plugin that generates **Factur-X MINIMUM** hybrid invoices (PDF with embedded EN 16931 CII XML) for every completed order.

Part of the [EuroComply compliance suite](../../).

## Scope (v0.1.0 — free MVP)

- Factur-X 1.0 **MINIMUM** profile XML (ZUGFeRD 2.2 MINIMUM equivalent, guideline `urn:factur-x.eu:1p0:minimum`)
- Hybrid PDF 1.7 container with `factur-x.xml` attached (`AFRelationship=/Data`)
- WooCommerce hook: auto-generate on order completion (or `processing` status — configurable)
- Admin menu **E-Invoicing** with 4 tabs: *Invoices · Settings · Pro Features · License*
- Invoice log table `wp_eurocomply_einv_log` + CSV export (nonce-gated)
- Per-order download link on the customer **My account → Orders** page
- `EC-XXXXXX` license stub matching the rest of the EuroComply suite

## Pro stubs (locked behind a valid license key)

- Factur-X **BASIC / EN 16931 / EXTENDED** profiles (full line-item detail)
- **PDF/A-3** archival conformance for the hybrid container
- **Peppol BIS Billing 3.0** UBL XML export
- **Peppol Access Point** sending with SMP lookup
- Country-specific profiles: **DE XRechnung**, **FR Chorus Pro**, **IT SDI / FatturaPA**, **PL KSeF**
- 10-year GoBD archival, signed PDFs, bulk regenerate, ERP webhooks

## Data model

| Table | Purpose |
|-------|---------|
| `wp_eurocomply_einv_log` | One row per generated invoice: order_id, invoice_number, profile, total, currency, file path/URL, status, timestamp |
| Option `eurocomply_einv_settings` | Seller identity + profile + trigger status |
| Option `eurocomply_einv_license` | License state (activated key + timestamp) |

Generated PDFs live under `wp-content/uploads/eurocomply-einvoicing/`. Uninstall drops the log table and options but **preserves generated PDFs** for 10-year bookkeeping compliance.

## Non-goals (v0.1.0)

- PDF/A-3 conformance (Pro — requires an XMP metadata + color-profile pipeline outside the minimal PDF writer used here)
- Line-item detail in the XML (MINIMUM profile is aggregate-only by design — upgrade to BASIC or higher for line items)
- Actual HTTPS licensing endpoint (stub only — local regex validation)
- Peppol network connectivity
