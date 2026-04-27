# EuroComply — EU Compliance Plugin Suite

A monorepo of WordPress plugins covering EU regulatory obligations end-to-end: GDPR, ePrivacy, GPSR, EAA, EPR, DSA, NIS2, CRA, AI Act, R2R, Omnibus, Whistleblower, Pay Transparency, CBAM, CSRD/ESRS, PSD2/SCA, EUDR, DORA, CER, MiCA, Toy Safety — plus a site-wide aggregator dashboard.

Built so EU-27 merchants and service providers can meet these regulations without paying €500+/month to specialists.

## Status

| Plugin | Slug | Reference | Status |
|---|---|---|---|
| Legal Pages | `wp-legal-pages` | GDPR Art. 13–14 · ePrivacy · TMG §5 · DSGVO | merged |
| Cookie Consent + GCM v2 | `wp-cookie-consent` | ePrivacy 2002/58 · GDPR Art. 7 · GCM v2 | merged |
| VAT OSS | `wp-vat-oss` | Council Dir. 2017/2455 (e-commerce VAT) | merged |
| General Product Safety (GPSR) | `wp-gpsr-compliance` | Reg. (EU) 2023/988 | merged |
| Extended Producer Responsibility (EPR) | `wp-epr-reporting` | WEEE 2012/19 · Packaging 94/62 · Batteries 2023/1542 | merged |
| European Accessibility Act (EAA) | `wp-eaa-accessibility` | Dir. (EU) 2019/882 | merged |
| Omnibus 30-day price | `wp-omnibus-pricing` | Dir. (EU) 2019/2161 | merged |
| DSA Transparency | `wp-dsa-transparency` | Reg. (EU) 2022/2065 | merged |
| Age Verification | `wp-age-verification` | JMStV (DE) · ARCOM (FR) · OSA (UK) | merged |
| GDPR DSAR | `wp-gdpr-dsar` | GDPR Art. 15 / 16 / 17 / 18 / 20 / 21 | merged |
| NIS2 & CRA | `wp-nis2-resilience` | Dir. (EU) 2022/2555 + CRA | merged |
| Right-to-Repair & Energy Label | `wp-right-to-repair` | Dir. (EU) 2024/1799 · ESPR 2024/1781 · Reg. 2017/1369 | merged |
| AI Act Transparency | `wp-ai-act-transparency` | Reg. (EU) 2024/1689 Art. 50 | merged |
| Whistleblower | `wp-whistleblower` | Dir. (EU) 2019/1937 | merged |
| ePrivacy & Tracker Registry | `wp-eprivacy-tracker` | Dir. 2002/58/EC + ePrivacy Reg. (proposal) | merged |
| Pay Transparency | `wp-pay-transparency` | Dir. (EU) 2023/970 | merged |
| Carbon Border Adjustment (CBAM) | `wp-cbam` | Reg. (EU) 2023/956 + 2023/1773 | merged |
| CSRD / ESRS | `wp-csrd-esrs` | Dir. (EU) 2022/2464 + ESRS Set 1 | merged |
| PSD2 / SCA | `wp-psd2-sca` | Dir. (EU) 2015/2366 + RTS 2018/389 | merged |
| EU Deforestation Regulation (EUDR) | `wp-eudr` | Reg. (EU) 2023/1115 | merged |
| **DORA** | `wp-dora` | Reg. (EU) 2022/2554 | **PR open** |
| **CER** | `wp-cer` | Dir. (EU) 2022/2557 | **PR open** |
| **MiCA** | `wp-mica` | Reg. (EU) 2023/1114 | **PR open** |
| **Toy Safety** | `wp-toy-safety` | Toy Safety Regulation (revising Dir. 2009/48/EC) | **PR open** |
| Phase 4 Compliance Dashboard | `wp-eurocomply-dashboard` | site-wide aggregator over all of the above | merged |

**Suite total: 25 plugins + 1 site-wide aggregator.**

## Repository layout

```
eu-compliance-suite/
├── plugins/
│   ├── wp-legal-pages/                # #1 — Legal Pages
│   ├── wp-cookie-consent/             # #2 — Cookie Consent + GCM v2
│   ├── wp-vat-oss/                    # #3 — VAT OSS
│   ├── wp-gpsr-compliance/            # #4 — General Product Safety
│   ├── wp-epr-reporting/              # #5 — EPR
│   ├── wp-eaa-accessibility/          # #6 — European Accessibility Act
│   ├── wp-omnibus-pricing/            # #8 — Omnibus 30-day price
│   ├── wp-dsa-transparency/           # #9 — DSA Transparency
│   ├── wp-age-verification/           # #10 — Age Verification
│   ├── wp-gdpr-dsar/                  # #11 — GDPR DSAR
│   ├── wp-nis2-resilience/            # #12 — NIS2 & CRA
│   ├── wp-right-to-repair/            # #13 — Right-to-Repair & Energy
│   ├── wp-ai-act-transparency/        # #14 — AI Act Art. 50
│   ├── wp-whistleblower/              # #15 — Whistleblower (2019/1937)
│   ├── wp-eprivacy-tracker/           # #16 — ePrivacy + tracker scanner
│   ├── wp-pay-transparency/           # #17 — Pay Transparency (2023/970)
│   ├── wp-cbam/                       # #18 — CBAM (2023/956 + 2023/1773)
│   ├── wp-csrd-esrs/                  # #19 — CSRD / ESRS Set 1
│   ├── wp-psd2-sca/                   # #20 — PSD2 / SCA (RTS 2018/389)
│   ├── wp-eudr/                       # #21 — EUDR (2023/1115)
│   ├── wp-dora/                       # #22 — DORA (2022/2554)
│   ├── wp-cer/                        # #23 — CER (2022/2557)
│   ├── wp-mica/                       # #24 — MiCA (2023/1114)
│   ├── wp-toy-safety/                 # #25 — Toy Safety Regulation
│   └── wp-eurocomply-dashboard/       # Phase 4 — site-wide aggregator
├── docs/
│   ├── ROADMAP.md                     # 18-month roadmap
│   └── BRAND.md                       # Brand & positioning
├── .github/workflows/ci.yml           # PHP 7.4–8.3 lint matrix
├── composer.json                      # dev tooling (PHPCS / WPCS / PHPStan)
└── README.md
```

## Suite-wide architecture

Every plugin in the monorepo follows the same conventions, by design, so you only have to learn the pattern once:

- **PSR-4 autoloader** mapping `EuroComply\{Plugin}\ClassName` → `includes/class-class-name.php`.
- **Singleton `Plugin` bootstrap** on `plugins_loaded`: loads textdomain, runs every `*Store::maybe_upgrade()`, instantiates `Admin` + `CsvExport`.
- **`Settings` static class** with `OPTION_KEY`, `defaults()`, `get()`, `sanitize()`, plus enum helpers (categories, hazards, modules, …).
- **`License` static class** — `EC-XXXXXX` regex stub gating Pro features.
- **One `*Store` class per entity** with `install() / uninstall() / maybe_upgrade()` + CRUD + helper counts.
- **10-tab admin UI** (Dashboard · entity tabs · Settings · Pro · License) with POST handlers checking `current_user_can('manage_options')` + `check_admin_referer()` and redirecting via `add_query_arg(..., 'settings-updated' => 'true', admin_url('admin.php'))` so `add_settings_error()` notices survive (the EAA PR #8 fix applied uniformly).
- **`CsvExport` singleton** — 500 free / 5,000 Pro cap, `nocache_headers()` + `exit` after stream.
- **Pro stubs** — listed in the Pro tab + `readme.txt`, never implemented in the free tier.

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
