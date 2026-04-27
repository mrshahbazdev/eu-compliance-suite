=== EuroComply Omnibus ===
Contributors: eurocomply
Tags: omnibus, price-transparency, woocommerce, eu-compliance, pricing, consumer-protection, article-6a, pid
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU Omnibus Directive price-transparency for WooCommerce. Shows the previous lowest price from the last 30 days next to every sale price (Art. 6a PID).

== Description ==

The EU Omnibus Directive (Directive (EU) 2019/2161) amended the Price Indication Directive so that **every price reduction announcement** must display the lowest price the trader applied during at least the 30 days before the reduction. Enforcement is active since 28 May 2022 and national implementations (DE PAngV §11, FR art. L112-1-1 Code de la consommation, AT PrAG, PL ustawa o informowaniu o cenach) are now generating fines.

EuroComply Omnibus is a focused WooCommerce plugin that automates this for shops:

* **Records every price change** on `save_post_product` / `save_post_product_variation` — no manual bookkeeping
* **Renders a reference disclosure** (`Previous lowest price (last 30 days): …`) next to the sale price on single, shop, category, tag, cart and checkout pages
* **Honors the introductory-period exemption** — brand-new products (younger than the configurable threshold) are skipped, as permitted by Art. 6a(3) PID
* **Backfill** button to seed history for an existing catalog in one click
* **CSV export** of the full history (nonce-gated, capability-gated, 500-row cap on free)
* **`EC-XXXXXX` license stub** matching the rest of the EuroComply compliance suite

== Free vs Pro ==

Free tier:
* 30-day reference window (EU baseline)
* Shop-wide default currency
* On-save tracking + manual backfill
* Admin dashboard with recent history + per-product lookup
* CSV export (500 rows)

Pro (locked behind a valid license key):
* 60 / 90 / 180-day reference windows for national regulators that go beyond the EU baseline
* Per-language reference pricing (WPML / Polylang)
* Multi-currency reference pricing (WooCommerce Multi-Currency, CURCY, Aelia)
* Daily snapshot cron that catches REST / CLI / import price changes
* Auditor-ready PDF reports (per-product 30-day lowest timeline)
* Block-editor disclosure block
* Bulk CSV import of historical prices from ERP exports
* Email digest of significant price drops

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it through the *Plugins* menu.
3. Go to **Omnibus → Dashboard** and click **Backfill history from current prices** to seed an initial row for every product.
4. Adjust display position, loop/cart toggles, and the introductory-period window under **Omnibus → Settings**.
5. From day 30 onwards, every sale product shows its 30-day lowest price automatically.

== Frequently Asked Questions ==

= Does this replace my legal review of sale announcements? =

No. The plugin automates the technical display; the commercial decision whether a given price reduction qualifies as a "price announcement" under Art. 6a PID is still yours.

= What about the introductory-period exemption? =

Enabled by default. Products whose first recorded history row is younger than the configured `introductory_days` threshold render no disclosure. You can disable this if your national regulator does not grant the exemption.

= Why is the disclosure not showing on day 1? =

Because there is no 30-day history yet. Click **Backfill history from current prices** on install, then wait for the first 30 days of natural price movement — or enable the Pro daily snapshot cron.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold: price history store, auto-tracker, 30-day lowest-price disclosure filter, admin 5-tab UI, backfill tool, CSV export, license stub.
