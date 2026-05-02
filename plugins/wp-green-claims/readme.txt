=== EuroComply Green Claims ===
Contributors: eurocomply
Tags: green-claims, ucpd, crd, sustainability, ecolabel, eu, compliance
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Empowering Consumers for the Green Transition (Dir. (EU) 2024/825) toolkit.

== Description ==

Implements operator-side obligations of Dir. (EU) 2024/825 amending UCPD (2005/29/EC) and CRD (2011/83/EU):

* **Substantiation register** — record claim text, evidence type (LCA, PEF, ISO 14021/14024/14025, EU Ecolabel, EU Energy Label, EU Organic, Fairtrade, FSC, MSC, ASC, GOTS, OEKO-TEX, Cradle to Cradle, RSPO, B Corp, …), evidence URL, verifier, verification date and expiry per product/post.
* **Sustainability label registry** — pre-seeded with 20 EU and global schemes; add custom labels and mark third-party verification.
* **Banned-claim scanner** — detects 23 generic phrases ("eco-friendly", "climate neutral", "biodegradable", "sustainable", "green", "natural", multi-lingual variants); auto-disclaims or hard-blocks unverified claims via `the_content` filter.
* **CRD Art. 5a per-product disclosures** — durability (months), software-update period (years), repairability score (0–10), extended commercial guarantee. Side metabox on every post / page / product. Schema.org `Product.additionalProperty` injected to `wp_head` for SEO/structured data.
* **4 public shortcodes** — `[eurocomply_gc_substantiation product_id="X"]`, `[eurocomply_gc_durability post_id="X"]`, `[eurocomply_gc_disclaimer]`, `[eurocomply_gc_labels eu_only="1"]`.
* **8-tab admin** — Dashboard · Claims · Labels · Scanner · Durability · Settings · Pro · License.
* CSV export for both datasets (500 free / 5000 Pro).

= Pro roadmap (stubs) =

* Third-party verification API (Bureau Veritas / TÜV / SGS / DEKRA)
* EPREL bridge: pull energy-label data from EU registry
* Signed PDF substantiation file per product / per claim
* PEF / OEF method import (Recommendation 2013/179/EU)
* REST API for compliance dashboards
* WPML / Polylang for multi-language disclosures
* Slack / Teams alert on expired evidence
* 5,000-row CSV cap (free tier 500)
* Bulk CSV import for product-level CRD Art. 5a fields
* CSDDD bridge: link evidence to chain-of-activities suppliers
* EU Commission "Green Claims" registry submission helper

== Changelog ==

= 0.1.0 =
* Initial scaffold.
