# EuroComply AI Act Transparency

EU AI Act (Regulation (EU) 2024/1689) Article 50 transparency obligations for WordPress.

## Scope

| Reference | Area | Supported |
|-----------|------|-----------|
| Reg. (EU) 2024/1689 Art. 50(1) | AI-interaction disclosure | `[eurocomply_ai_disclosure]` |
| Reg. (EU) 2024/1689 Art. 50(2) | Generative-AI output marker | per-post checkbox + frontend label + `<meta>` + JSON-LD |
| Reg. (EU) 2024/1689 Art. 50(3) | Deepfake label | per-post deepfake flag + dedicated label |
| Reg. (EU) 2024/1689 Art. 50(4) | Public-interest AI text disclosure | per-post visible label |
| Reg. (EU) 2024/1689 Art. 51–55 | GPAI provider obligations | provider-registry GPAI flag (Pro: scorecard) |
| Reg. (EU) 2024/1689 Annex III | High-risk system classifier | provider-registry flag (Pro: wizard) |
| Reg. (EU) 2024/1689 Art. 70 | Market-surveillance evidence | disclosure audit log |

## Features (free)

- Per-post sidebar metabox with 8 fields (generated · provider · model · purpose · human-edited · deepfake · prompt · C2PA URL)
- Visible AI label (top / bottom / both) on AI-marked posts
- HTML `<meta>` head tags + Schema.org `CreativeWork` JSON-LD
- Provider registry (`wp_eurocomply_aiact_providers`) with admin CRUD
- Disclosure audit log (`wp_eurocomply_aiact_log`) for marking changes
- Auto-generated AI Transparency policy page + live preview
- 4 public shortcodes: `[eurocomply_ai_disclosure]`, `[eurocomply_ai_label]`, `[eurocomply_ai_provider_list]`, `[eurocomply_ai_policy]`
- 8-tab admin UI
- CSV export (posts / providers / log) — 500 rows / 5,000 Pro

## Features (Pro stubs)

- C2PA manifest server-side verification
- SynthID-style watermark detection
- GPAI compliance scorecard
- High-risk Annex III classifier
- 24-language disclosure templates
- Automated chatbot detection
- Auto-marker on OpenAI / Anthropic / Gemini API hooks
- REST API + webhooks
- 5,000-row CSV cap
- WPML / Polylang
- EU AI Office submission templates

## Data model

| Item | Storage |
|------|---------|
| Per-post meta | Post meta (`_eurocomply_aiact_*`, 8 keys) |
| Providers | `wp_eurocomply_aiact_providers` |
| Audit log | `wp_eurocomply_aiact_log` |
| Settings | `eurocomply_ai_act_settings` option |
| License | `eurocomply_ai_act_license` option |

## Non-goals

- Not legal advice
- Does not verify C2PA manifests or detect invisible watermarks (Pro)
- Does not auto-mark content from third-party AI APIs (Pro)
- Does not classify high-risk systems automatically (Pro wizard)
- Does not file Art. 52 incident notifications with the EU AI Office (Pro templates)
