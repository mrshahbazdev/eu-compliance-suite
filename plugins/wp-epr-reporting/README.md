# EuroComply EPR Multi-Country Reporting

WooCommerce plugin that captures Extended Producer Responsibility (EPR) data per
product and produces the per-country CSV reports each EU registry expects.

## What is EPR?

Under EU Directive 2018/851 (and each Member State's implementation) producers
selling packaged goods into the EU must register with and report to a national
packaging registry for every country they ship to. Examples:

| Country | Registry | Registration label |
|---------|----------|--------------------|
| France  | Triman / Agec (CITEO, Ecologic) | Unique ID (IDU) |
| Germany | LUCID (Zentrale Stelle Verpackungsregister) | LUCID Registration Number |
| Spain   | Ecoembes / Ecovidrio (SCRAP) | NIMA / Registro |
| Italy   | CONAI | Codice CONAI |
| Netherlands | Afvalfonds Verpakkingen | Afvalfonds ID |
| Austria | ARA | ARA Lizenznummer |
| Belgium | Fost Plus / Valipac | Fost Plus ID |

Merchants currently pay €500+/month to specialist agencies to hand-fill these
reports. This plugin is the first step toward automating that workflow.

## Free tier (MVP)

* Per-country registration number metabox on each WooCommerce product.
* Packaging weight declaration (grams) per material:
  paper / plastic / glass / metal / wood / composite / other.
* Shop-wide defaults with per-product override (inherit toggle).
* Reporting Dashboard: compliant / warning / missing summary + per-country status grid.
* CSV export:
  * combined (all enabled countries)
  * per-country (one CSV formatted for the targeted registry upload)

## Pro tier (stub)

* Registry auto-submission (LUCID, CITEO, CONAI, Afvalfonds).
* Per-registry code mapping (ZSVR, CITEO, CONAI, Afvalfonds code sets).
* Batch/lot recall workflow (track batch → order → customer, trigger notifications).
* Multi-producer mode (separate EPR accounts per brand on one store).
* 10-year audit archive (GoBD-style immutable).

## Requirements

* WordPress 6.2+
* WooCommerce 7.0+
* PHP 7.4+

## License

GPL-2.0-or-later. See the root [LICENSE](../../LICENSE).
