# EuroComply GPSR Compliance Manager

WordPress + WooCommerce plugin that adds EU General Product Safety Regulation
(GPSR) traceability fields to products and displays them on the storefront.

Part of the [EuroComply](../..) EU compliance plugin suite (Phase 1, Plugin #4).

## Why

The GPSR (Regulation (EU) 2023/988) has been in force since **13 December 2024**
for every product sold to EU consumers. Among other things it requires:

- Identity and postal address of the **manufacturer**
- Identity and address of the **importer** (when manufacturer is outside the EU)
- An **EU Responsible Person** when neither manufacturer nor importer is EU-based
- **Warnings / safety information** and a **batch / lot / serial** identifier

These details must be visible *before* the consumer purchases.

## Free-tier features

| Feature | Status |
| --- | --- |
| Per-product GPSR metabox (manufacturer, importer, EU-Rep, warnings, batch) | ✔ |
| Shop-wide defaults + "inherit when empty" fall-back | ✔ |
| Auto-render GPSR block on single-product pages | ✔ |
| `[eurocomply_gpsr id="123"]` shortcode for custom placement | ✔ |
| Compliance Dashboard (missing required / recommended fields) | ✔ |
| CSV export of compliance status | ✔ |
| Clean uninstall of settings + license key | ✔ |

## Pro-tier stubs (shipping in a later release)

- AI-fill warnings from product description
- EU Responsible Person marketplace lookup
- Auto-geoblock non-compliant products from EU shipping
- Bulk CSV import with schema validation
- Incident / recall workflow

## Install (dev)

```bash
git clone https://github.com/mrshahbazdev/eu-compliance-suite.git
ln -s "$(pwd)/eu-compliance-suite/plugins/wp-gpsr-compliance" /path/to/wp-content/plugins/wp-gpsr-compliance
```

Activate in *Plugins* and visit **GPSR → Compliance Dashboard**.

## Conventions

- Class names follow `EuroComply\Gpsr\*`; the autoloader expects
  `class-kebab-case.php` in `includes/`. For classes with consecutive
  capitals (e.g. `CsvExport`) the file is `class-csv-export.php`.
- All user input is sanitised (`sanitize_text_field` / `sanitize_textarea_field`)
  and all output is escaped (`esc_html`, `esc_attr`, `esc_url`,
  `esc_textarea`). `nl2br()` is always applied after `esc_html` to preserve
  line breaks in addresses / warnings.
- Admin-post endpoints use nonces + capability checks.

## License

GPL-2.0-or-later.
