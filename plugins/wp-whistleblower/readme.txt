=== EuroComply Whistleblower ===
Contributors: eurocomply
Tags: whistleblower, compliance, eu, gdpr, reporting, 2019/1937
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU Whistleblower Directive (Dir. (EU) 2019/1937) internal reporting channel for WordPress: anonymous + identified reports, follow-up token, designated recipients, Art. 9 deadline tracker.

== Description ==

Internal reporting channel scaffolded against Directive (EU) 2019/1937 (transposed into EU-27 law by 17 December 2023):

* `[eurocomply_whistleblower_form]` — public submission form, anonymous OR identified, 12 EU-aligned categories
* `[eurocomply_whistleblower_status]` — anonymous reporters check status via follow-up token
* `[eurocomply_whistleblower_policy]` — auto-generated whistleblower policy
* Designated Recipient WP role + capability (`eurocomply_wb_view`, `eurocomply_wb_manage`)
* Art. 9 deadline tracker — 7-day acknowledgement and 3-month feedback deadlines surfaced as overdue alerts on the dashboard
* Tamper-evident access log — every report view, status change and CSV export is recorded
* EU-27 external authority directory (Art. 11) with email + website
* Hashed-IP rate limit (5/hour default), honeypot, nonce + capability gating
* Email notification to designated recipients on every new report
* Auto-generated draft policy page with one-click ensure-page action
* CSV export — reports / access log (500 free / 5,000 Pro)

== Free vs Pro ==

Pro stubs (listed in the Pro tab; not implemented):

* PGP-encrypted-at-rest report bodies and attachments
* Off-site storage (S3 / SFTP)
* Scheduled retention purge (Art. 18(2))
* 2FA enforcement for designated recipients
* Slack / Teams alert on new report
* External-authority pre-filled webhook submission
* Voice / phone reporting integration (Art. 9(2))
* Signed PDF case bundle (chain-of-custody)
* REST API
* 5,000-row CSV cap
* WPML / Polylang multi-language form
* Multi-tenant for parent-company groups (Art. 8(6))

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold
