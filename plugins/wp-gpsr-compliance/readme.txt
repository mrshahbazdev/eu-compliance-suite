=== EuroComply GPSR Compliance Manager ===
Contributors: eurocomply
Tags: gpsr, eu, product safety, compliance, woocommerce, manufacturer, eu representative
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add EU General Product Safety Regulation (GPSR) traceability data to your WooCommerce products and display it where the law requires it.

== Description ==

Since 13 December 2024, every product sold to EU consumers must carry GPSR traceability information: manufacturer identity and address, importer or EU Responsible Person, warnings and batch/lot number. This plugin adds those fields to WooCommerce products and renders them on the frontend.

**Free tier includes:**

* Per-product fields: manufacturer, importer, EU Responsible Person, warnings, batch/lot
* Shop-wide defaults (when you are the manufacturer for every product)
* Automatic GPSR block on single-product pages
* `[eurocomply_gpsr]` shortcode for custom placement
* Compliance dashboard: which products are missing which fields
* CSV export of compliance status
* Clean uninstall option for settings and licensing data

**Pro tier (stubbed for future release):**

* AI-fill warnings from product description
* EU Responsible Person marketplace lookup
* Auto-geoblock non-compliant products from EU shipping
* Bulk CSV import with schema validation
* Incident / recall workflow for authorities

Part of the [EuroComply](https://github.com/mrshahbazdev/eu-compliance-suite) EU compliance plugin suite.

== Installation ==

1. Upload `wp-gpsr-compliance` to `/wp-content/plugins/` or install via the plugin installer.
2. Activate the plugin in WordPress → Plugins.
3. Go to *GPSR → Settings* to configure shop-wide defaults.
4. Open any product and fill the *GPSR compliance* metabox.
5. Visit the *Compliance Dashboard* to see which products still need attention.

== Frequently Asked Questions ==

= Does the plugin require WooCommerce? =
Yes. The product metabox and frontend block hook into WooCommerce. The admin dashboard will show a notice if WooCommerce is not active.

= Is this legal advice? =
No. This plugin is a data-entry and rendering tool. Please consult a qualified advisor to confirm which fields apply to your products.

= Will uninstalling delete my product safety data? =
No. The plugin leaves product meta in place on uninstall so you can reactivate without losing data. Only settings and the license key are removed.

== Changelog ==

= 0.1.0 =
* Initial release. Free-tier GPSR traceability fields, frontend block, compliance dashboard, CSV export.
