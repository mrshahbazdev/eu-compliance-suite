# EuroComply NIS2 & CRA

NIS2 Directive (EU 2022/2555) and Cyber Resilience Act compliance toolkit for WordPress.

## Scope

| Reference | Area | Supported |
|-----------|------|-----------|
| NIS2 Art. 20 | Governance | out of scope (organisational control) |
| NIS2 Art. 21 | Risk-management measures | evidence base via event log |
| NIS2 Art. 23(4)(a) | 24h early warning | yes, deadline tracked + template |
| NIS2 Art. 23(4)(b) | 72h incident notification | yes, deadline tracked + template |
| NIS2 Art. 23(4)(c) | 30d intermediate report | yes, deadline tracked + template |
| NIS2 Art. 23(4)(d) | 30d post-handling final report | yes, deadline tracked + template |
| CRA | Coordinated vulnerability disclosure | public `[eurocomply_nis2_vuln_report]` shortcode |

## Features (free)

- Security event log (`wp_login_failed`, `wp_login`, `user_register`, `deleted_user`, `set_user_role`, `activated_plugin`, `deactivated_plugin`, `upgrader_process_complete`, `switch_theme`, optional `updated_option`)
- Hashed-IP only (SHA-256 + `wp_salt('nonce')`)
- Incident register with severity, category, status lifecycle, Art. 23 deadlines
- Dashboard cards: critical events (24h), high events (24h), open incidents, overdue deadlines
- EU CSIRT contact directory (27 countries + ENISA)
- Plain-text + JSON report builder for all four NIS2 stages
- Mark-sent workflow with `early_warning_sent_at` / `notification_sent_at` / `intermediate_sent_at` / `final_sent_at`
- CRA-aligned public vulnerability-report shortcode
- 7-tab admin UI
- CSV export (events + incidents, 500 rows / 5,000 Pro)

## Features (Pro stubs)

- SIEM forwarding (Splunk / ELK / Datadog / Graylog) via syslog + webhooks
- MISP / OpenCTI threat-intel ingestion
- Signed PDF incident reports
- REST API
- Auto CSIRT portal submission (BE CCB, NL NCSC, IT ACN, PL CERT.PL)
- Multisite aggregator
- WP Activity Log / Wordfence / iThemes correlation
- 5,000-row CSV cap
- Scheduled retention pruning
- Slack / Teams / PagerDuty webhook routing per severity

## Data model

| Item | Storage |
|------|---------|
| Events | `wp_eurocomply_nis2_events` |
| Incidents | `wp_eurocomply_nis2_incidents` |
| Settings | `eurocomply_nis2_settings` option |
| License | `eurocomply_nis2_license` option |
| Raw IPs | never stored; SHA-256 + `wp_salt('nonce')` |

## Non-goals

- Not legal advice, not a formal compliance certification
- Does not auto-submit to CSIRT APIs (regulators expect human-reviewed submissions)
- Not a SIEM replacement — forward events to a proper SIEM via the Pro add-on
- Not a vulnerability scanner
