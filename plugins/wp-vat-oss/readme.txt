=== EuroComply EU VAT Validator & OSS Rates ===
Contributors:      eurocomply
Tags:              vat, vies, eu, woocommerce, oss, reverse-charge, tax, gdpr, compliance
Requires at least: 6.2
Tested up to:      6.6
Requires PHP:      7.4
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Real VIES validation, EU-27 VAT rates, B2B reverse-charge and OSS destination
tax for WooCommerce. Built for German / French / DACH merchants.

== Description ==

**EuroComply VAT & OSS** is the EU tax-compliance companion to your
WooCommerce store:

* Validate customer EU VAT numbers against the official **VIES** endpoint.
* Apply **B2B reverse charge** (zero VAT) on valid cross-border EU B2B sales.
* Use the buyer country's VAT rate for B2C cross-border sales (**OSS**).
* Keep a pseudonymised transaction log so you can prove due-diligence at
  VAT audit time.

All of the above is free. The Pro tier adds bulk VIES, per-country filters,
the quarterly MOSS/OSS CSV export and the €10,000 threshold tracker.

== Installation ==

1. Upload to `wp-content/plugins/eurocomply-vat-oss/` and activate.
2. Navigate to **VAT & OSS → Settings** and pick your shop country.
3. (Optional) Paste an `EC-XXXXXX` Pro key in **License**.

== Frequently Asked Questions ==

= Does the free tier really include live VIES? =

Yes. VIES is a free public service run by the European Commission. The plugin
caches responses for 24h to stay well below their per-MS rate limits.

= What happens if VIES is down? =

The plugin still runs the local per-country format check. You can also
disable live VIES in Settings if your server has outbound HTTP problems;
in that case the format check is the only gate.

== Changelog ==

= 0.1.0 =
* Initial public release (MVP).
* VIES REST validator, EU-27 rates table, WooCommerce reverse-charge
  integration, admin UI, transaction log, license stub.
