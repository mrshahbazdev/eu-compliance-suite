=== EuroComply NIS2 & CRA ===
Contributors: eurocomply
Tags: nis2, security, incident, cra, gdpr, csirt, woocommerce
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NIS2 Directive (EU 2022/2555) and Cyber Resilience Act compliance toolkit: local security event log, incident register with Art. 23 24h / 72h / 30d / final deadlines, EU CSIRT contact directory, notification templates, vulnerability-report shortcode.

== Description ==

A WordPress-native answer to NIS2 (Directive (EU) 2022/2555) for essential and important entities:

* **Security event log** — hooks on `wp_login_failed`, `wp_login`, `user_register`, `deleted_user`, `set_user_role`, `activated_plugin`, `deactivated_plugin`, `upgrader_process_complete`, `switch_theme`, optionally `updated_option`. IP addresses are stored only as `SHA-256(ip + '|' + wp_salt('nonce'))`.
* **Incident register** with NIS2 Art. 23 deadline tracking:
  * 24h early warning
  * 72h incident notification
  * 30d intermediate report
  * 30d post-handling final report
  * Dashboard card for overdue deadlines
* **EU CSIRT directory** with 27 national CSIRTs + ENISA (emails, websites, submission portals where published).
* **Notification builder** — generates plain-text templates and machine-readable JSON for all four report stages; deliberately does not POST to CSIRT APIs (every regulator expects human-reviewed submissions).
* **Vulnerability-report shortcode** — `[eurocomply_nis2_vuln_report]`, CRA-aligned, with honeypot + nonce + email notification to the security contact. Automatically creates a draft incident.
* **7-tab admin UI** (Dashboard / Security events / Incidents / CSIRTs / Settings / Pro / License).
* **CSV export** for events and incidents (500 rows free, 5,000 Pro).

== Free vs Pro ==

Free:

* All event hooks + hashed-IP log
* Incident register with full Art. 23 deadline tracking
* 27-country CSIRT directory
* Plain-text + JSON report builder for all four stages
* Public vulnerability shortcode (CRA-aligned)
* 500-row CSV export

Pro (stubs, not implemented in this scaffold):

* SIEM forwarding (Splunk / ELK / Datadog / Graylog) via syslog + webhooks
* MISP / OpenCTI threat-intel feed ingestion
* Signed PDF incident reports
* REST API for SIEM / SOAR integration
* Automatic CSIRT portal submission (BE CCB, NL NCSC, IT ACN, PL CERT.PL)
* Multisite aggregator across WordPress network
* Correlation with WP Activity Log / Wordfence / iThemes
* 5,000-row CSV cap
* Scheduled event pruning
* Slack / Teams / PagerDuty webhook routing

== Installation ==

1. Upload and activate `eurocomply-nis2-cra`.
2. Visit **EuroComply NIS2 → Settings**, set organisation name, entity type, sector, primary CSIRT country, and security contact.
3. Optionally place `[eurocomply_nis2_vuln_report]` on a dedicated `/security` page.

== Frequently Asked Questions ==

= Does this plugin forward incidents to my national CSIRT automatically? =

No. Every CSIRT has its own workflow and regulators expect the reporting entity to review the report before submission. The plugin builds the plain-text and JSON content; you email it or paste it into the CSIRT's portal.

= Is this plugin a replacement for a SIEM? =

No. It provides a WordPress-level audit trail for use in post-incident analysis and NIS2 reporting. Production deployments should forward events to an external SIEM (available as a Pro stub).

= Does this plugin qualify my organisation as NIS2-compliant? =

No. Compliance is an organisation-wide undertaking covering risk management (Art. 21), governance (Art. 20), reporting (Art. 23), training, incident-response plans, and more. This plugin supports the reporting and evidence parts.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold
