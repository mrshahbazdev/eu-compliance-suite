# EuroComply Whistleblower

Plugin #15 of the EuroComply EU compliance suite. Implements an internal reporting channel under **Directive (EU) 2019/1937** (Whistleblower Directive), transposed into EU-27 law by 17 December 2023.

## Scope

| Article | Surface |
|---------|---------|
| Art. 6 | 12 EU-aligned report categories on the public form |
| Art. 7 | External authority surfaced on the auto-generated policy |
| Art. 8 | Internal reporting channel + Designated Recipient role |
| Art. 9(1)(b) | 7-day acknowledgement deadline tracker |
| Art. 9(1)(c) | Follow-up token for anonymous reporters |
| Art. 9(1)(f) | 3-month feedback deadline tracker |
| Art. 11 | EU-27 external authority directory |
| Art. 12 | Designated impartial recipient capability |
| Art. 16 | Confidential storage + hashed-IP rate limit |
| Art. 18 | Tamper-evident access log of every view/update |

## Components

- **ReportStore** (`wp_eurocomply_wb_reports`) — id · created_at · status · category · subject · body · anonymous · contact · files · ip_hash · acknowledged_at · feedback_sent_at · closed_at · internal_notes
- **AccessLog** (`wp_eurocomply_wb_access`) — every view, status change, export
- **Recipient** — registers `eurocomply_wb_recipient` role and `eurocomply_wb_view` / `eurocomply_wb_manage` caps
- **Shortcodes** — submission form (honeypot + nonce + rate-limit), status check, policy
- **PolicyPageGenerator** — auto-generated policy with country-specific external authority
- **CsvExport** — 2 datasets (reports, access_log), 500 free / 5,000 Pro
- **Admin** — 8-tab UI (Dashboard / Reports / Recipients / Channels / Access log / Settings / Pro / License)
- **License** — `EC-XXXXXX` stub

## Privacy posture

- IPs stored only as `hash_hmac('sha256', ip, wp_salt('nonce'))`
- Follow-up tokens stored as `hash_hmac('sha256', token, wp_salt('auth'))` (token shown to reporter once)
- Anonymous reports are first-class; contact details are optional unless the operator disables anonymity
- Reports are visible only to users with the recipient capability; access events are written to the audit log on every view

## Non-goals

- Outbound regulator submissions (Pro)
- PGP-encrypted-at-rest bodies (Pro)
- Voice / phone reporting (Pro)
- Not legal advice
