=== EuroComply E-Invoicing ===
Contributors: eurocomply
Tags: e-invoicing, factur-x, zugferd, peppol, woocommerce, invoice, en 16931, xrechnung
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU e-invoicing for WooCommerce. Factur-X MINIMUM hybrid PDF with embedded EN 16931 CII XML. Peppol / XRechnung / Chorus Pro / SDI / KSeF (Pro).

== Description ==

EuroComply E-Invoicing generates a **Factur-X MINIMUM** hybrid invoice (PDF with an embedded EN 16931 Cross-Industry Invoice XML attachment) whenever a WooCommerce order reaches the configured trigger status. Customers can download the PDF from their *My account* page; admins can regenerate, download, or export the full invoice log.

Free tier:
* Factur-X MINIMUM profile XML (ZUGFeRD 2.2 MINIMUM equivalent)
* Hybrid PDF with the `factur-x.xml` attachment
* Admin invoice log + CSV export
* Shop-wide seller identity settings
* Manual regenerate for any order ID

Pro (stubs — activate with an EC-XXXXXX license key):
* Factur-X **BASIC / EN 16931 / EXTENDED** profiles (full line-item detail)
* **PDF/A-3** archival conformance
* **Peppol BIS Billing 3.0** UBL XML + Access Point sending (SMP lookup)
* **XRechnung** (DE), **Chorus Pro** (FR), **SDI / FatturaPA** (IT), **KSeF** (PL)
* 10-year GoBD archival + signed PDFs
* Bulk regenerate / resend, webhook status updates

This plugin is part of the [EuroComply](https://github.com/mrshahbazdev/eu-compliance-suite) compliance suite.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it through the *Plugins* menu.
3. Under **E-Invoicing → Settings**, fill in your seller identity (name, VAT ID, country, address) and choose the trigger status (Completed or Processing).
4. Complete a WooCommerce order — the hybrid invoice is generated and available via **E-Invoicing → Invoices** and the customer's *My account* page.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold: Factur-X MINIMUM XML + hybrid PDF generator, WC hooks, admin 4-tab UI, CSV export, license stub.
