=== EuroComply AI Act Transparency ===
Contributors: eurocomply
Tags: ai-act, ai, transparency, deepfake, c2pa, generative-ai, gpai
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EU AI Act (Regulation 2024/1689) Article 50 transparency obligations: per-post AI markers, deepfake labels, generative-AI provider registry, chatbot disclosure shortcode, auto-generated AI policy page, disclosure audit log.

== Description ==

A WordPress-native compliance toolkit for **Article 50 of Regulation (EU) 2024/1689** (the EU AI Act):

* Art. 50(1) — chatbot / AI-interaction disclosure (`[eurocomply_ai_disclosure]`)
* Art. 50(2) — generative-AI output marker (visible label + `<meta>` + JSON-LD)
* Art. 50(3) — deepfake / synthetic-media label
* Art. 50(4) — public-interest AI-generated text disclosure on posts/pages

**Per-post meta** (in a sidebar metabox on `post` and `page`):

* AI-generated checkbox
* Provider (OpenAI / Anthropic / Google / Mistral / Meta / Stability / Midjourney / Cohere / Aleph Alpha / Hugging Face / Azure OpenAI / AWS Bedrock / self-hosted / other)
* Model name (e.g. gpt-4o-2024-08-06)
* Purpose (text / translation / image / video / audio / code / chatbot / recommendation / classification / other)
* Human-edited checkbox
* Deepfake / synthetic media flag
* Prompt summary (internal)
* C2PA manifest URL

**Frontend signals**:

* Visible AI label injected before/after/both/none of post content
* `<meta name="ai-generated">`, `ai-deepfake`, `ai-provider`, `ai-model` head tags
* JSON-LD `CreativeWork` with `additionalProperty` AI markers (Schema.org compatible)

**Provider registry**: admin CRUD over `wp_eurocomply_aiact_providers` — list every AI tool the site uses (label, provider, model, purpose, vendor legal name, country, GPAI flag, high-risk flag, notes).

**Disclosure audit log**: tamper-evident `wp_eurocomply_aiact_log` recording marking / unmarking events for market-surveillance audit (Art. 70).

**Auto-generated AI Transparency policy page**: one-click draft creation, live preview, `[eurocomply_ai_policy]` shortcode.

**Admin UI**: 8-tab (Dashboard / Marked posts / Providers / Disclosure log / Policy / Settings / Pro / License).

**CSV export**: posts (AI-marked), providers, log — 500 rows free / 5,000 Pro.

== Free vs Pro ==

Free:

* All Art. 50 disclosure surfaces (label, meta tag, JSON-LD)
* Per-post sidebar metabox
* Provider registry + disclosure log
* Auto-generated policy page
* 4 public shortcodes
* 500-row CSV cap

Pro (stubs, not implemented):

* C2PA manifest server-side verification
* SynthID-style watermark detection on uploads
* GPAI provider compliance scorecard (Art. 53 / 55)
* High-risk system Annex III classifier wizard (Art. 6, 16, 17)
* Multi-language disclosure templates (24 EU languages)
* Automated chatbot detection on the live site
* Auto-mark posts published via OpenAI / Anthropic / Gemini API hooks
* REST API + webhook for SIEM ingestion
* 5,000-row CSV cap
* WPML / Polylang multilingual policy
* EU AI Office submission templates (Art. 52 incident notification)

== Installation ==

1. Upload and activate `eurocomply-ai-act`.
2. Visit *EuroComply AI Act → Settings* and tailor labels and visible signals.
3. Edit a post or page; tick *AI-generated or AI-assisted content* in the sidebar metabox; pick provider / model / purpose; save.
4. View the post on the site — the visible label appears above the content; `<meta>` and JSON-LD are present in `<head>`.
5. Optionally place `[eurocomply_ai_disclosure]` on a chatbot landing page and `[eurocomply_ai_policy]` on a transparency page.

== Changelog ==

= 0.1.0 =
* Initial MVP scaffold
