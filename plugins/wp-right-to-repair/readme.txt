=== EuroComply Right-to-Repair & Energy Label ===
Contributors: eurocomply
Tags: right-to-repair, r2r, energy-label, ecodesign, espr, eprel, woocommerce
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Right-to-Repair Directive (EU 2024/1799), Ecodesign ESPR, and Energy Labelling Regulation (EU 2017/1369) compliance for WooCommerce: per-product energy class, reparability score, spare-parts availability years, repair manual URL, spare-parts and repairer directories.

== Description ==

Turns a WooCommerce shop into a repair-and-energy-transparent seller under the EU Right-to-Repair framework.

**Per-product meta** (in WC product "General" panel):

* ESPR category (washing machine / dishwasher / fridge / TV / vacuum / phone / tablet / welding / server / light source / PSU / n.a.)
* Energy class A–G + kWh/year (consumption)
* Reparability score 0.0–10.0 (FR Indice de réparabilité)
* Disassembly ease score 0.0–10.0
* Spare-parts guaranteed years (0–15, with category default)
* Spare-parts catalogue URL
* Repair manual URL
* EPREL registration ID (European Product Registry for Energy Labelling)
* Warranty years (per-product override)

**Frontend**:

* Coloured A–G energy badge (WCAG-contrast palette)
* Colour-coded reparability score badge (green ≥ 8, lime ≥ 6, amber ≥ 4, red < 4)
* Spare-parts years badge
* Dedicated "Repair & parts" product tab with full spec sheet
* Shop-grid badges (configurable)

**Directories**:

* Spare-parts supplier directory (admin CRUD, public `[eurocomply_r2r_spares]` shortcode)
* Authorised-repairer directory (FR L.111-4 compliant, admin CRUD, public `[eurocomply_r2r_repairers]` shortcode)

**Admin UI**: 7-tab (Dashboard / Products / Spare parts / Repairers / Settings / Pro / License).

**CSV export**: suppliers, repairers, and per-product R2R meta (500 rows free, 5,000 Pro).

== Free vs Pro ==

Free:

* Full per-product R2R meta + admin editor
* Energy + reparability + spare-parts badges
* Spare-parts and repairer directories with public shortcodes
* 500-row CSV export for suppliers, repairers, products

Pro (stubs, not implemented):

* EPREL database sync (automatic energy-label fetch + image)
* FR Indice de réparabilité auto-calculator (5 criteria, per-category formulae)
* German ReparaturIndex draft calculator (once finalised by BMUV)
* Digital Product Passport (QR / datamatrix on invoices and labels)
* Multi-country spare-parts cross-border availability matrix
* Extended-warranty tracker (per-sale, per-SKU)
* Energy label image generator (A–G tricolour)
* REST API for catalogue sync
* 5,000-row CSV cap
* WPML / Polylang multilingual product info
* EU R2R platform submission (once published by the Commission)

== Installation ==

1. Upload and activate `eurocomply-r2r`.
2. WooCommerce required for per-product meta (suppliers / repairers directories work without WC).
3. Edit any product and populate the "EuroComply — Right-to-Repair & Energy" section in the **General** tab.
4. Optionally place `[eurocomply_r2r_spares]` and `[eurocomply_r2r_repairers]` on a public `/repair` page.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold
