# EuroComply — Legal Pages

**Plugin slug:** `eurocomply-legal-pages`
**Status:** MVP / 0.1.0
**Target:** WordPress 6.2+, PHP 7.4+

Generates Impressum, Datenschutzerklärung, AGB, and Widerrufsbelehrung pages for EU-27 websites.

## Local development

Clone this repo and symlink the plugin into a WordPress install:

```bash
ln -s $(pwd)/plugins/wp-legal-pages /path/to/wordpress/wp-content/plugins/eurocomply-legal-pages
```

Then activate from **WP Admin → Plugins**.

## Building a WP.org-ready zip

```bash
cd plugins/wp-legal-pages
zip -r ../../dist/eurocomply-legal-pages-0.1.0.zip . \
  -x "*.git*" "*.DS_Store" "node_modules/*" "tests/*"
```

## Architecture

```
eurocomply-legal-pages/
├── eurocomply-legal-pages.php   # main plugin file (header + bootstrap)
├── uninstall.php                # cleanup on plugin delete
├── readme.txt                   # WP.org readme format
├── README.md                    # this file
├── includes/
│   ├── class-plugin.php         # Singleton bootstrap
│   ├── class-admin.php          # Admin menu, pages, assets
│   ├── class-settings.php       # Business-info settings model
│   ├── class-templates.php      # Template registry (country × type)
│   ├── class-generator.php      # Render template → HTML
│   ├── class-publisher.php      # Create/update WP pages, shortcodes
│   └── class-license.php        # Pro license stub (local-only for 0.1)
├── templates/
│   ├── impressum/{de,at,ch}.php
│   ├── datenschutz/de.php
│   ├── agb/de.php               # Pro
│   └── widerruf/de.php          # Pro
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── languages/
    └── eurocomply-legal.pot
```

## Free vs Pro matrix

| Feature | Free | Pro (€9/mo) |
|--------|------|------|
| Impressum DE/AT/CH | ✓ | ✓ |
| Impressum other EU-27 | — | ✓ |
| Datenschutzerklärung | Basic DE | All EU-27, lawyer-reviewed |
| AGB | — | ✓ |
| Widerrufsbelehrung | — | ✓ |
| Auto-updates on law change | — | ✓ |
| WooCommerce checkout integration | — | ✓ |
| Multi-language generation | 3 langs | 24 EU langs |

## Roadmap (this plugin)

- **0.2** — All EU-27 Impressum templates
- **0.3** — AGB + Widerrufsbelehrung Pro content
- **0.4** — Law-change subscription feed
- **0.5** — WooCommerce checkout integration
- **1.0** — WP.org submission
