=== EuroComply EAA Accessibility ===
Contributors: eurocomply
Tags: accessibility, wcag, eaa, a11y, compliance
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

European Accessibility Act (Directive 2019/882) readiness for WordPress: WCAG 2.1 AA scanner, accessibility statement generator, skip-to-content link.

== Description ==

EuroComply EAA Accessibility helps EU merchants meet the **European Accessibility Act (EAA, Directive 2019/882)** that became enforceable on 28 June 2025 for e-commerce and consumer services.

**Free tier (this plugin):**

* Site-wide WCAG 2.1 AA scanner (alt text, heading order, form labels, link text, landmarks, lang, iframe titles, duplicate IDs)
* Automatic scan when a post / page is saved
* Admin dashboard with severity breakdown + CSV export
* Auto-created **Accessibility statement** page with `[eurocomply_eaa_statement]` shortcode (EAA Art. 7)
* Skip-to-content link injection (opt-in focus-outline polyfill)

**Pro tier (coming soon — locked in this release):**

* AI alt-text auto-fill for media library and WooCommerce galleries
* Scheduled weekly scans + email alerts
* VPAT 2.5 / EN 301 549 conformance export
* In-page ARIA remediation editor
* Headless-Chromium computed contrast + focus order

Part of the [EuroComply compliance suite](https://github.com/mrshahbazdev/eu-compliance-suite).

== Installation ==

1. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or place the folder in `wp-content/plugins/`.
2. Activate **EuroComply EAA Accessibility**.
3. Visit **Accessibility → Scanner** and click **Scan published posts & pages**.
4. Visit **Accessibility → Statement** to fill in your entity name / contact e-mail; the auto-created page uses this data.

== Frequently Asked Questions ==

= Does this make my site fully EAA-compliant? =

No. The scanner covers machine-checkable WCAG 2.1 AA criteria. Manual testing (keyboard, screen-reader, cognitive) is still required, and the statement generator is a template — a qualified reviewer should sign off the final text.

= Does it work with WooCommerce? =

Yes. Scans cover any public URL — product pages included. WooCommerce is not required for the core plugin.

== Changelog ==

= 0.1.0 =

* Initial MVP scaffold: scanner, issue store, statement shortcode + auto-page, skip-link injector, CSV export, license stub.
