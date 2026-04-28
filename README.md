# EuroComply — EU Compliance Plugin Suite

A monorepo of 25 WordPress plugins + 1 site-wide aggregator dashboard, covering EU regulatory obligations end-to-end:

> GDPR · ePrivacy · GCM v2 · VAT OSS · GPSR · EPR (WEEE / Packaging / Batteries) · EAA · ZUGFeRD/Factur-X/Peppol · Omnibus 30-day price · DSA · Age verification · GDPR DSAR · NIS2 + CRA · Right-to-Repair + Energy Label · AI Act Art. 50 · Whistleblower · ePrivacy + tracker scanner · Pay Transparency · CBAM · CSRD/ESRS · PSD2/SCA · EUDR · DORA · CER · MiCA · Toy Safety.

Built so EU-27 merchants and service providers can meet these regulations without paying €500+/month to specialists.

## Suite at a glance

| # | Plugin | Slug | Statutory reference |
|---|--------|------|---------------------|
| 1 | Legal Pages | `wp-legal-pages` | GDPR Art. 13–14 · ePrivacy · TMG §5 · DSGVO |
| 2 | Cookie Consent + GCM v2 | `wp-cookie-consent` | ePrivacy 2002/58 · GDPR Art. 7 · Google Consent Mode v2 |
| 3 | VAT OSS | `wp-vat-oss` | Council Dir. 2017/2455 (EU e-commerce VAT) |
| 4 | General Product Safety | `wp-gpsr-compliance` | Reg. (EU) 2023/988 |
| 5 | Extended Producer Responsibility | `wp-epr-reporting` | WEEE 2012/19 · Packaging 94/62 · Batteries 2023/1542 |
| 6 | European Accessibility Act | `wp-eaa-accessibility` | Dir. (EU) 2019/882 · WCAG 2.1 AA |
| 7 | E-Invoicing | (folded into VAT OSS) | EN 16931 · ZUGFeRD/Factur-X · Peppol BIS 3.0 |
| 8 | Omnibus 30-day price | `wp-omnibus-pricing` | Dir. (EU) 2019/2161 |
| 9 | DSA Transparency | `wp-dsa-transparency` | Reg. (EU) 2022/2065 Art. 16 / 17 / 30 |
| 10 | Age Verification | `wp-age-verification` | JMStV (DE) · ARCOM (FR) · OSA (UK) · alcohol laws (IT/ES/NL) |
| 11 | GDPR DSAR | `wp-gdpr-dsar` | GDPR Art. 15 / 16 / 17 / 18 / 20 / 21 |
| 12 | NIS2 & CRA | `wp-nis2-resilience` | Dir. (EU) 2022/2555 + Cyber Resilience Act |
| 13 | Right-to-Repair & Energy Label | `wp-right-to-repair` | Dir. (EU) 2024/1799 · ESPR 2024/1781 · Reg. 2017/1369 |
| 14 | AI Act Transparency | `wp-ai-act-transparency` | Reg. (EU) 2024/1689 Art. 50 |
| 15 | Whistleblower | `wp-whistleblower` | Dir. (EU) 2019/1937 |
| 16 | ePrivacy & Tracker Registry | `wp-eprivacy-tracker` | Dir. 2002/58/EC + ePrivacy Reg. (proposal) |
| 17 | Pay Transparency | `wp-pay-transparency` | Dir. (EU) 2023/970 |
| 18 | CBAM | `wp-cbam` | Reg. (EU) 2023/956 + 2023/1773 |
| 19 | CSRD / ESRS | `wp-csrd-esrs` | Dir. (EU) 2022/2464 + ESRS Set 1 |
| 20 | PSD2 / SCA | `wp-psd2-sca` | Dir. (EU) 2015/2366 + RTS 2018/389 |
| 21 | EUDR | `wp-eudr` | Reg. (EU) 2023/1115 |
| 22 | DORA | `wp-dora` | Reg. (EU) 2022/2554 |
| 23 | CER | `wp-cer` | Dir. (EU) 2022/2557 |
| 24 | MiCA | `wp-mica` | Reg. (EU) 2023/1114 |
| 25 | Toy Safety | `wp-toy-safety` | Toy Safety Regulation (revising Dir. 2009/48/EC) |
| Φ | Compliance Dashboard | `wp-eurocomply-dashboard` | site-wide aggregator across all of the above |

All plugins are **GPL-2.0-or-later**. The Compliance Dashboard ships with three implemented Pro features (daily WP-Cron snapshot · REST API · 5,000-row CSV cap) as a working reference for the rest of the suite; every other plugin currently ships free-tier scaffolds with Pro features documented as stubs.

## Plugin catalogue

### 1. Legal Pages — `wp-legal-pages`
Statutory reference: GDPR Art. 13–14 · ePrivacy · TMG §5 / MStV §18 · DSGVO.

- Generates **Impressum / Datenschutzerklärung / AGB / Widerrufsbelehrung** with country-specific templates (DE / AT / CH + EU-27 fallbacks).
- `{{field_name}}` template substitution against site-owner data captured in Settings (legal name, address, VAT ID, supervisory authority, MStV §18 editorial-responsibility person, etc.).
- One-click "draft pages" button creates the four legal pages as WP `page` post-types you can edit further.
- Honest-compliance posture: outputs structurally correct legal text; explicitly does not gate the content behind a paywall.
- Free / Pro: free covers DACH; Pro stubs cover non-DACH EU-27 country packs and AGB variants per business model.

### 2. Cookie Consent + GCM v2 — `wp-cookie-consent`
Statutory reference: ePrivacy Dir. 2002/58 · GDPR Art. 7 · Google Consent Mode v2.

- Front-end consent banner with category toggles (necessary / analytics / advertising / functional / preferences) and a per-cookie inventory.
- **Google Consent Mode v2** signals (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, `functionality_storage`, `personalization_storage`, `security_storage`) emitted before any tag fires.
- Consent log stored locally (no third-party cookie) with revoke endpoint.
- Pro stubs: IAB TCF v2.2 string, A/B-test banner copy, geo-aware presets, IAB CMP Validator integration.

### 3. VAT OSS + E-Invoicing — `wp-vat-oss`
Statutory reference: Council Dir. 2017/2455 · EN 16931 · ZUGFeRD/Factur-X · Peppol BIS 3.0.

- VIES VAT-number validation on checkout with B2B reverse-charge auto-application.
- Destination-country EU-27 VAT rate engine for OSS sellers; per-product rate overrides.
- ZUGFeRD/Factur-X PDF/A-3 invoice attachment on order completion (free generates the XML envelope; Pro signs it).
- Peppol BIS 3.0 export for German GoBD / French Chorus Pro / Italian SDI flows.
- Pro stubs: certified e-invoice signing, automatic Peppol AP submission, multi-vat-rate split lines, VAT MOSS quarterly report builder.

### 4. General Product Safety (GPSR) — `wp-gpsr-compliance`
Statutory reference: Reg. (EU) 2023/988.

- Per-WC-product compliance fields: manufacturer, EU Responsible Person / importer, batch / lot, warnings (12 categories), care instructions, compliance docs URL.
- Front-end "Safety information" block on single product + shop loop badge.
- 14-day **Safety Gate / RAPEX** notification helper — admin can mark a product recalled and the plugin builds the JSON envelope.
- Pro stubs: Safety Gate API submission, multi-language warnings, multilingual frontend block, per-marketplace API (Amazon / eBay / Etsy).

### 5. Extended Producer Responsibility (EPR) — `wp-epr-reporting`
Statutory reference: WEEE 2012/19 · Packaging 94/62 · Batteries 2023/1542.

- Per-WC-product EPR data: France Triman + Agec, Germany LUCID / VerpackG, Spain RAEE / SCRAP, Italy CONAI, Netherlands Verpact, plus 5 more registries.
- Quarterly volume aggregation per country / category / weight class.
- CSV export per registry in their published column format.
- Pro stubs: LUCID / Agec API submission, weight-class auto-classification, multi-country bulk report builder.

### 6. European Accessibility Act (EAA) — `wp-eaa-accessibility`
Statutory reference: Dir. (EU) 2019/882 · WCAG 2.1 AA.

- Site-wide WCAG 2.1 AA scanner: alt text, heading order, form labels, link text, landmarks, basic contrast.
- Issue tracker with severity (A / AA / AAA), WCAG success-criterion link, and "ignore" workflow.
- Auto-generated **accessibility statement** page (Art. 13 / EN 301 549).
- Pro stubs: PDF/UA scanner for media library, axe-core integration, multilingual statements, scheduled scan + email digest.

### 7. Omnibus 30-day price — `wp-omnibus-pricing`
Statutory reference: Dir. (EU) 2019/2161.

- Records every WC product price change into `wp_eurocomply_omnibus_history`.
- When a product is on sale, frontend shows "Lowest price in last 30 days: €X" alongside the current sale price.
- Backfill button to import the last 30 days from existing WC data on activation.
- Pro stubs: per-variant 30-day tracking, scheduled audit report, multi-currency, exclude-promo filter.

### 8. DSA Transparency — `wp-dsa-transparency`
Statutory reference: Reg. (EU) 2022/2065 Art. 16 / 17 / 30.

- **Art. 16** notice-and-action public form `[eurocomply_dsa_notice]` with category taxonomy.
- **Art. 17** "statement of reasons" builder — admin issues, plugin formats per Annex II.
- **Art. 30** transparency-report dataset with 14-day SLA tracker.
- Trusted-flagger registry + appeals workflow.
- Pro stubs: EU DSA Transparency Database submission, signed PDF report, internal-complaint board.

### 9. Age Verification — `wp-age-verification`
Statutory reference: JMStV (DE) · ARCOM (FR) · OSA (UK) · alcohol laws (IT / ES / NL).

- Site-wide modal age-gate with per-country minimum-age rules (alcohol 16/18, tobacco 18, gambling 18/21, adult 18, knives 18).
- WooCommerce **product-category gating** — only specific categories trigger the modal.
- DOB-based identity check (free) + ID-document upload stub (Pro).
- Honeypot + nonce + hashed-IP rate-limit on the verification form.
- Pro stubs: Veriff / Onfido / Yoti integration, JMStV closed-user-group API, parental consent flows.

### 10. GDPR DSAR — `wp-gdpr-dsar`
Statutory reference: GDPR Art. 15 / 16 / 17 / 18 / 20 / 21.

- Public request form `[eurocomply_dsar_request]` with 6 request types (access · rectification · erase · restrict · portability · object).
- **Email-token identity verification** before any data is exposed.
- 30-day **Art. 12(3) deadline tracker** with overdue red flags + dashboard alerts.
- Auto-collected data bundle: WP user, WC orders/addresses, comments, consent logs.
- Tamper-evident access log per Art. 18.
- Pro stubs: signed PDF report, scheduled retention purge (Art. 17), REST API, multi-site aggregator, supplemental data sources (CRM, Mailchimp).

### 11. NIS2 & CRA — `wp-nis2-resilience`
Statutory reference: Dir. (EU) 2022/2555 + Cyber Resilience Act.

- Local **security event log** with 9 hooks (`wp_login_failed`, `wp_login`, `user_register`, `deleted_user`, `set_user_role`, `activated_plugin`, `deactivated_plugin`, `upgrader_process_complete`, `switch_theme`); hashed-IP, 12-month retention.
- **Incident register** with **Art. 23 deadline engine**: 24h early warning · 72h notification · 30d intermediate · 30d final. Overdue stages turn red.
- Notification-content builder (plain-text + JSON envelope `schema=eurocomply-nis2-report-1`) for paste-into-CSIRT submission.
- **EU CSIRT directory** — 27 national CSIRTs + ENISA with emails / portals (DE BSI · FR ANSSI · IT ACN · NL NCSC · BE CCB · etc.).
- CRA-aligned public vulnerability shortcode `[eurocomply_nis2_vuln_report]`.
- Pro stubs: SIEM forwarding (Splunk / ELK / Datadog / Graylog), MISP / OpenCTI, signed PDF, REST API, auto CSIRT submission, multisite aggregator, Wordfence / iThemes correlation.

### 12. Right-to-Repair & Energy Label — `wp-right-to-repair`
Statutory reference: Dir. (EU) 2024/1799 · ESPR 2024/1781 · Reg. (EU) 2017/1369.

- Per-WC-product meta (10 fields): ESPR category · energy class A–G · kWh/year · reparability score 0.0–10.0 (FR Indice) · disassembly ease · spare-parts years · catalogue URL · repair manual URL · EPREL ID · warranty.
- ESPR statutory defaults per category (washers/dryers 10y, fridges/TVs 7y, phones/tablets 7y, light sources 6y, PSUs 5y, …).
- Frontend WCAG-palette A–G energy badge + 4-tier reparability score badge + "Repair & parts" product tab.
- Spare-parts supplier directory + FR L.111-4 authorised-repairer directory.
- Shortcodes: `[eurocomply_r2r_info]` · `[eurocomply_r2r_spares]` · `[eurocomply_r2r_repairers]`.
- Pro stubs: EPREL sync, FR Indice auto-calc, DE ReparaturIndex, Digital Product Passport (QR / datamatrix), energy-label image generator, REST API.

### 13. AI Act Transparency — `wp-ai-act-transparency`
Statutory reference: Reg. (EU) 2024/1689 Art. 50.

- Per-post sidebar metabox (8 fields): AI-generated · provider (14 known) · model · purpose (12 categories) · human-edited · deepfake · prompt summary · C2PA URL.
- Frontend visible label + `<meta name="ai-generated">` + Schema.org `CreativeWork` JSON-LD.
- **Provider registry** with GPAI flag (Art. 51) + high-risk flag (Annex III).
- Tamper-evident **disclosure audit log** (Art. 70 evidence).
- Auto-generated AI Transparency policy page.
- 4 shortcodes: `[eurocomply_ai_disclosure]` (Art. 50(1) chatbot) · `[eurocomply_ai_label]` · `[eurocomply_ai_provider_list]` · `[eurocomply_ai_policy]`.
- Pro stubs: C2PA verify · watermark detect (SynthID-style) · GPAI scorecard · Annex III classifier · 24-language templates · OpenAI/Anthropic/Gemini auto-marker · REST API.

### 14. Whistleblower — `wp-whistleblower`
Statutory reference: Dir. (EU) 2019/1937.

- Public report form `[eurocomply_whistleblower_form]` — anonymous **or** identified, 12 EU-aligned categories.
- **Follow-up token** (Art. 9(1)(c)) — HMAC-SHA-256, shown to reporter once, anonymous status check via `[eurocomply_whistleblower_status]`.
- **Designated Recipient** role with `eurocomply_wb_view` / `eurocomply_wb_manage` caps.
- **Art. 9 deadline tracker** — 7-day acknowledgement + 3-month feedback windows with overdue red flags.
- **Tamper-evident access log** (Art. 18) — every view / status change / CSV export / token check is recorded.
- EU-27 external authority directory (Art. 11): DE BfJ · FR Défenseur · IT ANAC · NL Huis voor Klokkenluiders · ES AAI · 22 more.
- Privacy posture: IPs only as HMAC-SHA-256, anonymous reports first-class, file uploads validated.
- Pro stubs: PGP-at-rest · S3/SFTP storage · retention purge · 2FA · Slack/Teams alerts · webhook to authority · voice intake · signed PDF case bundle · REST API · multi-tenant.

### 15. ePrivacy & Tracker Registry — `wp-eprivacy-tracker`
Statutory reference: Dir. 2002/58/EC + ePrivacy Reg. (proposal).

- **Tracker registry** — 80+ known SDKs / pixels (GA4 · GTM · Meta Pixel · Hotjar · Clarity · LinkedIn · TikTok · Pinterest · Klaviyo · Mailchimp · Intercom · Zendesk · HubSpot · Segment · Mixpanel · Heap · Amplitude · FullStory · Optimizely · VWO · Stripe · PayPal · …).
- **Static HTML scanner** — fetches admin-listed URLs, regex-matches against tracker fingerprints, records findings per URL per scan.
- **Live cookie observer** — JS sniffer in `wp_footer` posts cookie *names* (no values) to admin-ajax; sessions de-duped by HMAC-SHA-256(addr|UA, salt).
- **Compliance gap report** — trackers detected but missing from EuroComply Cookie Consent (#2) categories → red flags.
- Pro stubs: hourly cron · headless Chrome deep scan · JS event capture (gtag/fbq/dataLayer) · IAB TCF v2.2 · auto-fix into Cookie Consent.

### 16. Pay Transparency — `wp-pay-transparency`
Statutory reference: Dir. (EU) 2023/970 (transposition deadline 7 June 2026).

- **Art. 5** pay-range disclosure on job ads (post meta + `[eurocomply_pay_range]` + frontend badge).
- **Art. 6** pay-setting & progression criteria (admin CMS block + public shortcodes).
- **Art. 7** worker right-to-information form `[eurocomply_pay_info_request]` with **2-month response tracker** (overdue red).
- **Art. 9** annual gender-pay-gap report — CSV upload → `GapCalculator` (hourly-equivalent mean + median by category) → snapshot.
- **Art. 10** joint-assessment trigger (gap > 5% on any category).
- **Art. 11** pay-categories taxonomy (skills · effort · responsibility · conditions, levels 0–10).
- Privacy posture: `EmployeeStore` retains only HMAC-SHA-256(external_ref) + (category, gender ∈ {w,m,x,u}, total_comp, hours_per_week). No names / emails / national IDs.
- Pro stubs: payroll integrations (DATEV / SAP / Personio / BambooHR / HiBob / Workday) · NACE Rev.2 classifier · joint-assessment workflow · Schema.org JobPosting `baseSalary` · WPML.

### 17. CBAM — `wp-cbam`
Statutory reference: Reg. (EU) 2023/956 + implementing Reg. 2023/1773.

- Importer registry — CN8 codes mapped to CBAM goods categories (cement · iron & steel · aluminium · fertilisers · electricity · hydrogen + downstream).
- Per-WC-product CBAM meta: CN code · embedded emissions (tCO₂e direct + indirect) · country of origin · supplier · production route · default-vs-verified flag.
- **Quarterly Q-report builder** — XML envelope per Reg. 2023/1773 schema covering 1 Oct 2023 – 31 Dec 2025 transitional period; full definitive period from 2026.
- CBAM declarant register + accredited verifier directory.
- Schema.org `Product` augmentation surfacing embedded emissions as `additionalProperty`.
- Pro stubs: TARIC API sync · EU CBAM Registry submission · signed PDF Q-report · supplier-portal embedded-emissions intake · REST API.

### 18. CSRD / ESRS — `wp-csrd-esrs`
Statutory reference: Dir. (EU) 2022/2464 + ESRS Set 1 (1/2 cross-cutting · E1–E5 environment · S1–S4 social · G1 governance).

- 12 ESRS topical-standard datapoint registers (each with key metrics + narratives).
- **Double-materiality assessment** workflow: impact materiality + financial materiality scored 1–5.
- Phase-in eligibility helper (large undertakings 2024 · listed SMEs 2026 · 2028 deferral options).
- **iXBRL-friendly tagging** of report sections via Schema.org metadata.
- Auto-generated sustainability-statement page draft.
- Pro stubs: full XBRL export · ESEF tagging · third-party assurance workflow · Eurostat reference values · REST API.

### 19. PSD2 / SCA — `wp-psd2-sca`
Statutory reference: Dir. (EU) 2015/2366 + RTS 2018/389.

- **SCA-applicability decision engine** — per-transaction evaluation with full audit trail.
- Exemption library: low-value (≤ €30) · recurring · MIT (merchant-initiated) · TRA (transaction risk analysis) · trusted-beneficiary · corporate-payment.
- Per-WC-order risk-score field + decline reason recorder.
- Public-page disclosure shortcode for the strong-auth method.
- Audit register `wp_eurocomply_psd2_decisions` (90-day default retention, configurable).
- Pro stubs: Stripe / Mollie / Adyen risk-engine integration · 3DS2 challenge mediator · per-issuer fraud feedback · TRA fraud-rate calculator.

### 20. EUDR — `wp-eudr`
Statutory reference: Reg. (EU) 2023/1115 (deforestation-free products).

- 7-commodity registry: cattle · cocoa · coffee · oil palm · rubber · soya · wood (+ derived products).
- Per-WC-product due-diligence statement: GeoJSON plot polygons / lat-long, supplier ref, harvest date, country of production, risk classification.
- Supplier directory + per-shipment due-diligence record.
- High-risk vs low-risk simplified due diligence path.
- Geolocation validator (basic GeoJSON shape sanity).
- Pro stubs: TRACES NT submission · EU Information System integration · scheduled risk-classification refresh · supplier portal.

### 21. DORA — `wp-dora`
Statutory reference: Reg. (EU) 2022/2554.

- ICT-related **incident register** with Art. 19 classification (major / significant) + 4h / 72h / 1-month deadline tracker.
- ICT third-party register with criticality classification + Art. 28 contract requirements checklist.
- Threat-intel feed register (free imports manually).
- ICT-policy / risk-management framework template register.
- Resilience-test register (TLPT / vulnerability assessments).
- Pro stubs: regulator API submission · MISP / OpenCTI / TIBER-EU · scheduled stress tests · multi-entity aggregator.

### 22. CER — `wp-cer`
Statutory reference: Dir. (EU) 2022/2557.

- 11-sector taxonomy: energy · transport · banking · FMI · health · drinking water · waste water · digital infrastructure · public administration · space · food.
- Critical-services register + risk register (likelihood × impact).
- Resilience-measures register (technical / organisational / hybrid).
- Asset register with criticality classification.
- **Incident register with deadline tracker** + report builder (early-warning + final).
- Pro stubs: ENISA submission · cross-sector dependency mapper · scheduled exercises · signed PDF · REST API.

### 23. MiCA — `wp-mica`
Statutory reference: Reg. (EU) 2023/1114.

- **Crypto-asset register** — ART (asset-referenced) · EMT (e-money) · other.
- **White-paper register** with NCA notification + 12-day standstill (Art. 8 / 17 / 51).
- Marketing-communications register with disclaimer requirements.
- **Complaint-handling register** (Art. 31) with statutory response-time tracker.
- Disclosure register (ongoing publication obligations).
- Public risk-disclosure shortcode + warning-banner shortcode.
- Pro stubs: ESMA / EBA submission · auto-translation of disclaimers · multi-issuer dashboards · signed PDF · REST API.

### 24. Toy Safety — `wp-toy-safety`
Statutory reference: Toy Safety Regulation (revising Dir. 2009/48/EC).

- Per-WC-product toy register with EAN/GTIN, age range, "intended for under 36 months" flag.
- **Restricted-substance register** — CMR · endocrine disruptor · PFAS · 5 more categories with limit values.
- Conformity-documentation register (EU declaration of conformity, technical file refs, notified-body number).
- CE-mark + age-warning frontend block.
- Recall workflow with Safety Gate notification builder.
- Pro stubs: notified-body API · auto-generated DoC · Safety Gate submission · multi-language warnings.

### Φ. Compliance Dashboard — `wp-eurocomply-dashboard` (v0.2.0)
Site-wide aggregator. Surfaces a unified compliance score + alerts feed across every active EuroComply plugin.

- **20 plugin connectors** (Legal Pages → EUDR) — each detects active state via class/option/table presence and returns a uniform `metrics` + `alerts` + `score (0-100)` shape; not-installed plugins degrade gracefully.
- **Aggregator** — overall compliance score = mean across active plugins · merged alert stream · traffic-light score labels (green ≥ 80 · amber ≥ 50 · red < 50).
- **Real overdue-deadline detection** — connectors don't just count rows, they query for actually overdue items (DSAR > 30d · NIS2 > 24h/72h · Whistleblower 7d ack / 3-month feedback · Pay Transparency 2-month info request · EUDR high-risk shipments · CBAM default-emission rows).
- **`SnapshotStore`** — `wp_eurocomply_dashboard_snapshots` with daily compliance-score capture + retention pruning.
- **8-tab admin**: Overview · Plugins · Alerts · Calendar · History · Settings · Pro · License.
- **Hero card**: 72px score number with green/amber/red border + "X of 20 plugins active" + alert link.
- **Calendar** with statutory deadlines (NIS2 24h/72h/30d · GDPR 30d · GPSR 14d · EAA 6m · DSA 12m · EPR Q+30 · AI Act 12m).
- **CSV export** — 3 datasets (plugins / alerts / snapshots), 500 free / 5,000 Pro.

#### Pro features (implemented in v0.2.0 — reference for the rest of the suite)

- **Daily WP-Cron compliance-score snapshot** — `Aggregator::cron_snapshot()` short-circuits unless `License::is_pro()` AND `Settings::enable_daily_snapshot` are both on; retention pruning runs after each capture.
- **REST API** (namespace `eurocomply/v1`, capability `manage_options`, returns HTTP 402 when license inactive, `X-EuroComply-Schema` headers for contract pinning):

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/compliance` | Full payload — overall, per-plugin connectors, merged alerts |
| GET | `/compliance/summary` | Headline numbers + alert counts by severity |
| GET | `/snapshots?per_page=N` | Paginated snapshot history (capped 500) |
| POST | `/snapshots` | Trigger an immediate capture |

- **5,000-row CSV cap** (vs 500 free) on plugins / alerts / snapshots datasets.

#### Pro stubs still on the roadmap

Email digest · Slack / Teams / PagerDuty webhook · SIEM forwarding · multisite aggregator · signed PDF compliance report · WPML / Polylang report templates · upgrade nudges & changelog feed.

## Repository layout

```
eu-compliance-suite/
├── plugins/
│   ├── wp-legal-pages/                # #1
│   ├── wp-cookie-consent/             # #2
│   ├── wp-vat-oss/                    # #3 (+ #7 e-invoicing)
│   ├── wp-gpsr-compliance/            # #4
│   ├── wp-epr-reporting/              # #5
│   ├── wp-eaa-accessibility/          # #6
│   ├── wp-omnibus-pricing/            # #8
│   ├── wp-dsa-transparency/           # #9
│   ├── wp-age-verification/           # #10
│   ├── wp-gdpr-dsar/                  # #11
│   ├── wp-nis2-resilience/            # #12
│   ├── wp-right-to-repair/            # #13
│   ├── wp-ai-act-transparency/        # #14
│   ├── wp-whistleblower/              # #15
│   ├── wp-eprivacy-tracker/           # #16
│   ├── wp-pay-transparency/           # #17
│   ├── wp-cbam/                       # #18
│   ├── wp-csrd-esrs/                  # #19
│   ├── wp-psd2-sca/                   # #20
│   ├── wp-eudr/                       # #21
│   ├── wp-dora/                       # #22
│   ├── wp-cer/                        # #23
│   ├── wp-mica/                       # #24
│   ├── wp-toy-safety/                 # #25
│   └── wp-eurocomply-dashboard/       # Phase 4 — site-wide aggregator (v0.2.0)
├── docs/
│   ├── ROADMAP.md                     # 18-month roadmap
│   └── BRAND.md                       # Brand & positioning
├── .github/workflows/ci.yml           # PHP 7.4 → 8.3 lint matrix
├── composer.json                      # dev tooling (PHPCS / WPCS / PHPStan)
├── phpcs.xml.dist
├── phpstan.neon.dist
└── README.md
```

## Suite-wide architecture

Every plugin in the monorepo follows the same conventions, by design, so you only have to learn the pattern once:

- **PSR-4 autoloader** mapping `EuroComply\{Plugin}\ClassName` → `includes/class-class-name.php`.
- **Singleton `Plugin` bootstrap** on `plugins_loaded`: loads textdomain, runs every `*Store::maybe_upgrade()`, instantiates `Admin` + `CsvExport`.
- **`Settings` static class** with `OPTION_KEY`, `defaults()`, `get()`, `sanitize()`, plus enum helpers (categories, hazards, modules, …).
- **`License` static class** — `EC-XXXXXX` regex stub gating Pro features.
- **One `*Store` class per entity** with `install() / uninstall() / maybe_upgrade()` + CRUD + helper counts.
- **N-tab admin UI** (Dashboard · entity tabs · Settings · Pro · License) with POST handlers checking `current_user_can('manage_options')` + `check_admin_referer()` and redirecting via `add_query_arg(..., 'settings-updated' => 'true', admin_url('admin.php'))` so `add_settings_error()` notices survive (the EAA PR #8 fix applied uniformly).
- **`CsvExport` singleton** — 500 free / 5,000 Pro cap, `nocache_headers()` + `exit` after stream.
- **Privacy-by-default storage** — IPs only as HMAC-SHA-256, follow-up tokens hashed, anonymous flows first-class wherever the regulation allows.
- **Pro stubs** — listed in the Pro tab + `readme.txt`; the Compliance Dashboard is currently the only plugin with implemented Pro features (daily cron + REST API + 5k CSV cap), kept as a reference for the rest of the suite.

The Phase 4 **Compliance Dashboard** plugs into all of the above via per-plugin connectors that detect active state from option / class / table presence and degrade gracefully when a sister plugin isn't installed.

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md).

## Local development

```bash
# Lint every PHP file in the repo with the same matrix CI uses (defaults to system PHP).
find plugins -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null

# Or with composer dev dependencies installed:
composer install
composer lint     # `php -l` over all plugin PHP files
composer phpcs    # WordPress Coding Standards (warnings allowed; errors fail)
composer phpstan  # Static analysis (level 5, opt-in once configured per plugin)
```

## CI

`.github/workflows/ci.yml` runs `php -l` on every `*.php` in `plugins/` across the matrix `PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3` on every push and pull request. Lint failures block merges.

## License

Each plugin is **GPL-2.0-or-later** (WordPress.org-compatible). See individual plugin directories.

## Contributing

Active commercial project. Issues and PRs welcome for bug reports and non-business-critical improvements.
