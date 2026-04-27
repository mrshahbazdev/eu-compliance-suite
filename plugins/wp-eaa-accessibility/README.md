# EuroComply EAA Accessibility

European Accessibility Act (Directive 2019/882) readiness for WordPress.

**MVP scope:**

- WCAG 2.1 AA static scanner (12 machine-checkable rules across 1.1.1, 1.3.1, 2.4.4, 2.4.6, 3.1.1, 3.3.2, 4.1.1, 4.1.2)
- Issue store in `wp_eurocomply_eaa_issues` with severity breakdown (serious / moderate / minor)
- Admin dashboard (4 tabs: Scanner · Statement · Pro Features · License) with CSV export
- Auto-scan on `save_post` for published posts / pages (toggleable)
- Bulk **Scan published posts & pages** button from the admin
- Accessibility statement page auto-created on activation, powered by `[eurocomply_eaa_statement]` shortcode
- Frontend: skip-to-content link (on by default) + optional `:focus-visible` outline polyfill
- `EC-XXXXXX` license stub — same pattern as the other EuroComply plugins

**Pro stubs (locked in this release):**

- AI alt-text auto-fill
- Scheduled scans + email alerts
- VPAT 2.5 / EN 301 549 export
- ARIA remediation editor
- Computed contrast + keyboard focus-order checks

## Directory layout

```
plugins/wp-eaa-accessibility/
├── eurocomply-eaa.php           # Bootstrap + PSR-ish autoloader
├── README.md
├── readme.txt                   # WP.org readme
├── uninstall.php
├── assets/css/admin.css
├── assets/css/frontend.css
├── includes/
│   ├── class-admin.php          # 4-tab admin + handlers
│   ├── class-csv-export.php
│   ├── class-frontend.php       # Skip-link + CSS enqueue
│   ├── class-issue-store.php    # wp_eurocomply_eaa_issues CRUD
│   ├── class-license.php        # EC-XXXXXX stub
│   ├── class-plugin.php
│   ├── class-rules.php          # Rule catalogue (WCAG SCs)
│   ├── class-scanner.php        # DOMDocument static scanner
│   ├── class-settings.php
│   ├── class-statement-page.php # Auto-create statement page on activation
│   └── class-statement.php      # [eurocomply_eaa_statement] shortcode
└── languages/
    └── README.md
```

## Why the scanner stays static

Running a headless Chromium for every post would blow up shared-hosting budgets. `DOMDocument` + XPath covers the highest-leverage a11y bugs for the free tier; computed contrast, reflow and focus order are reserved for the Pro tier where we can afford Chromium-based checks.

Part of the [EuroComply compliance suite](https://github.com/mrshahbazdev/eu-compliance-suite).
