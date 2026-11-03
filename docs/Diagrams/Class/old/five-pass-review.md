# Five-pass review — closing missing functionality

Reviewed 27 October 2026. This supersedes the prior ERD-only 14-class review. Final design: 16 entities, 189 attributes, 26 role-specific associations; all 14 original names and all 135 original fields/types preserved.

## 1 — Requirements and workflow gaps

Assigned nearest complete exact-match search, validated submission/destination, request and expiry worklists, processing writes/release reevaluation, manual/automatic rejection and competing-request reevaluation, saved forecast generation and exact saved retrieval. Matching is static over unsaved criteria; no-match searches create no request. No runtime implementation is claimed.

## 2 — Schema changes and identifier preservation

Added only `addressed_bank_id`, `matched_at`, `matching_policy_version` and four rejection audit fields to `requests`; retained the separate original accepting-bank field. Added the previously proposed `forecast_runs` and `demand_forecasts` names with explicit provenance, state and daily expected-result fields. Compared every original field/type against the pre-change source. Snapshot-dependent values remain nullable during preparation/failure and required for publication; no fabricated provenance is allowed. Notices are derived worklists rather than new persistent entities. Offline sync remains separately scoped.

## 3 — Full ERD/class correspondence and business invariants

Compared all 189 typed detailed attributes and all 26 endpoint multiplicities, using a multiset that keeps the two bank–request roles distinct. All original associations remain with their original meanings. Reviewed full atomic reservations, bank agreement, exact ABO/Rh, no partial allocation, terminal decision audits, automatic-null actor rules, processing/testing in either order, real disposal, authorized worklists, immutable run publication, idempotent retries and unavailable forecast manifests without invented zeros. Proposed operation responsibilities implement these contracts through appropriate transaction/policy services in a future backend.

## 4 — Geometry and print edits

Checked that the expanded graph can be drawn without crossings. Compared compact orientations/orderings while retaining all associations. Kept both bank-role captions and essential ABO/Rh fields. Used plain SVG text with consistent measurement/font settings. Selected left-to-right A4 overview: zero detected connector crossings and zero detected paths through class boxes. Suppressed overview types instead of renaming fields or removing associations.

## 5 — Final sources and output

Rechecked the final source aliases, preserved original identifiers/types, static/full Mermaid parsing, distinct role captions, schema/dictionary counts, operation coverage and revised forecast-plan/architecture descriptions. Exported a one-page A4 landscape PDF and visually checked the rendered page for full labels, readable text, compartment borders and no clipping. PDF diagram text is approximately 8.14 pt. Regenerated the low-level ERD preview and current Markdown dictionary. Existing high-level draw.io/Word historical references are retained and their scope is recorded; no backend or user requirement/use-case documents are edited by this task.

Geometry checks apply to the supplied Mermaid 12.2.0/font/configuration. Source/table agreement and a clean drawing do not constitute runtime transaction, authorization or model-performance testing. Implementation, data readiness and offline synchronization remain separate work.
