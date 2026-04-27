# EuroComply Toy Safety

Toy Safety Regulation (revising Directive 2009/48/EC) toolkit for WordPress.

## Coverage

| Surface | Surface |
|---|---|
| Toy register | GTIN/EAN, age range, ≤ 36 months flag, batch, CE mark, DoC URL, materials, warnings, status (draft / on_market / recalled / withdrawn) |
| Restricted substances (Annex II + Appx. C) | CMR, endocrine disruptor, PFAS, lead, cadmium, mercury, arsenic, phthalates, nitrosamines, bisphenols, 55 prohibited fragrances, formaldehyde, primary aromatic amines |
| Conformity assessment | Modules A, A2, B, B+C, B+E with notified-body name + 4-digit NB number + certificate number + issued/valid-until + standards (EN 71-x, EN IEC 62115) |
| Safety Gate / RAPEX incidents | hazard taxonomy (16), severity, country, injuries, fatalities, operator-configurable initial-h + follow-up-d windows with overdue tracker |
| Economic-operator chain | manufacturer, authorised rep, importer, distributor, fulfilment service provider (EU 2019/1020), online marketplace |
| Digital Product Passport | per-toy bundle: toy + operators + assessments + substances → XML (`urn:toy:eurocomply:0.1`) + JSON |

## Architecture

- 5 DB tables: `toys`, `substances`, `assessments`, `incidents`, `operators`.
- 10-tab admin: Dashboard · Toys · Substances · Conformity · Incidents · Operators · DPP · Settings · Pro · License.
- CSV per dataset + per-toy DPP XML/JSON.
- License stub `EC-XXXXXX`.
