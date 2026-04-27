# EuroComply MiCA

Markets in Crypto-Assets Regulation (EU) 2023/1114 toolkit for WordPress.

## Coverage

| Article | Surface |
|---|---|
| Art. 4–8 | White-paper register for "other crypto-assets" — notify NCA → 12-day standstill timer → publish |
| Art. 7 | Marketing-communications log with `risk_warning` + `fair_clear` flags |
| Art. 17 / 19 | ART white papers (Art. 17 issuer info; Art. 19 white paper) |
| Art. 27 / 31 | Complaint-handling register with operator-configurable acknowledgement & resolution windows |
| Art. 36–38 | Reserve-assets composition free-form field on each ART / EMT entry (Pro: scheduled snapshots) |
| Art. 43 | `significant` flag on assets (Pro: EBA threshold tracker) |
| Art. 51 | EMT white papers |
| Art. 87 | Insider-information disclosure log |
| Art. 88 | Delay-of-disclosure justification field |

## Architecture

- 5 DB tables: `assets`, `whitepapers`, `comms`, `complaints`, `disclosures`.
- 10-tab admin: Dashboard · Assets · White papers · Marketing · Complaints · Insider info · Reports · Settings · Pro · License.
- Per-white-paper XML (`urn:mica:eurocomply:0.1`) + per-dataset CSV exports.
- License stub `EC-XXXXXX`.
