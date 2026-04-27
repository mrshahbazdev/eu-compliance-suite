=== EuroComply Compliance Dashboard ===
Contributors: eurocomply
Tags: compliance, dashboard, gdpr, dsa, nis2, ai-act, eu, woocommerce
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-wide aggregator for the EuroComply EU compliance suite. Unified score, plugin status grid, alerts feed, deadline calendar, snapshot history.

== Description ==

A meta-plugin that surfaces a single pane of glass over the EuroComply compliance suite:

* Unified compliance score (0–100) across all active EuroComply plugins
* Plugin status grid (20 cards: Legal Pages, Cookie Consent, VAT OSS, GPSR, EPR, EAA, Omnibus, DSA, Age Verification, GDPR DSAR, NIS2 & CRA, Right-to-Repair, AI Act, Whistleblower, ePrivacy & Tracker Registry, Pay Transparency, CBAM, CSRD/ESRS, PSD2/SCA, EUDR)
* Alerts feed — overdue NIS2 24h / 72h, DSAR 30-day, Whistleblower 7-day acknowledgement / 3-month feedback, Pay Transparency 2-month info-request, EUDR high-risk shipment, CBAM default-emission rows, missing legal pages, etc.
* Statutory deadline calendar (NIS2 Art. 23, GDPR Art. 12(3), GPSR Art. 20, EPR quarterly, EAA bi-annual, DSA annual, AI Act review)
* Snapshot history with manual capture button and CSV export
* CSV export — plugins / alerts / snapshots (500 free, 5,000 Pro)

The dashboard does not duplicate any individual plugin's data; each connector reads from the active plugin's own option / table and aggregates a uniform shape.

== Free vs Pro ==

Free:

* Live overview, alerts, calendar
* Manual snapshot capture
* 500-row CSV cap

Pro (implemented in 0.2.0):

* Daily WP-Cron compliance-score snapshot with retention pruning (gated by `enable_daily_snapshot` setting + active license)
* REST API: `GET /wp-json/eurocomply/v1/compliance`, `GET /compliance/summary`, `GET /snapshots`, `POST /snapshots` — capability `manage_options`, returns 402 when license inactive
* 5,000-row CSV cap (vs 500 free) on plugins / alerts / snapshots datasets

Pro stubs (still on the roadmap):

* Email digest (weekly / monthly) to the compliance officer
* Slack / Teams / PagerDuty webhook on alert
* SIEM forwarding (Splunk / ELK / Datadog)
* Multisite aggregator
* Signed PDF compliance report
* WPML / Polylang multi-language reports

== Installation ==

1. Activate one or more EuroComply suite plugins.
2. Activate **EuroComply Compliance Dashboard**.
3. Visit *EuroComply* in the WP admin sidebar.

== Changelog ==

= 0.2.0 =
* Pro reference implementation: daily WP-Cron snapshot, REST API (`eurocomply/v1` namespace) and 5,000-row CSV cap.
* Pro tab now reports per-feature status (Active / Inactive) plus next cron run.

= 0.1.0 =
* Initial MVP scaffold
