=== EuroComply Cookie Consent ===
Contributors: eurocomply
Tags: gdpr, cookies, consent, consent mode, privacy
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR + ePrivacy cookie-consent banner with Google Consent Mode v2 built in. Part of the EuroComply compliance suite.

== Description ==

EuroComply Cookie Consent is a lightweight, privacy-first consent banner for WordPress. It ships with Google Consent Mode v2 support out of the box so analytics and ads vendors receive the correct signals *before* the user makes a choice.

Features (free):

* Banner with 4 pre-configured categories (Necessary, Preferences, Statistics, Marketing).
* Accept all / Reject non-essential / Preferences buttons.
* Google Consent Mode v2 `default` + `update` signals with the correct category → signal mapping (including `ad_user_data` and `ad_personalization`).
* `ads_data_redaction` and `url_passthrough` toggles.
* Blocks third-party `<script type="text/plain" data-eurocomply-cc="marketing">` tags until the matching category is granted.
* GDPR Art. 7(1) append-only consent log table (salted SHA-256 hashed IP + user agent).
* English + German banner copy out of the box; locale auto-detection.

Pro (stubs only in 0.1.0):

* IAB TCF v2.2 framework support.
* Automated cookie scanner.
* CSV export of the consent log.
* Geo-IP based banner suppression outside the EEA.

== Installation ==

1. Upload the `wp-cookie-consent` folder to `/wp-content/plugins/`.
2. Activate the plugin in the WordPress admin.
3. Go to *Cookie Consent* in the admin menu and configure the banner + Google Consent Mode v2 tab.

== Changelog ==

= 0.1.0 =
* Initial release. Banner, Consent Mode v2, consent log, Pro stubs.
