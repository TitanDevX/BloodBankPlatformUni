# Five-pass review — updated ERD-aligned class diagram

Source: `docs/ER/code/erd-low-level.mmd` in the user's reorganized ERD folder. This review supersedes the earlier 15-class design and its counts.

## 1 — Source selection and exact names

Read the updated low-level ERD and checked the reorganized high-level draw.io/ERD documentation context. Replaced earlier renamed domain concepts with exact ERD identifiers. Restored `roles` as a class. Removed classes not present in the chosen ERD from the aligned model; retained forecasting/notification/offline scope gaps as notes. No ERD files were edited.

## 2 — Attributes, types and responsibilities

Generated the detailed model directly from all 135 ERD attributes across 14 entities. Compared every attribute name/type against the parsed ERD, including IDs, foreign keys, ABO/Rh copies, assessment measurements, separation, follow-up and actor/time fields. Retained key/nullability metadata in comments. Selected exact-name attributes for the A4 view and suppressed their types there to preserve legibility. Public operations are proposed workflow responsibilities, not statements about existing Laravel classes.

## 3 — Relationships, multiplicities and lifecycle

Converted all 22 ERD relationships to UML associations. Compared normalized class pairs and both endpoint multiplicities, accounting for endpoint reversal during layout. Restored the direct `donations`–`blood_components` association and all drawn staff associations. Restored the optional accepting-bank meaning of `requests`; removed the prior addressed-bank reinterpretation. Reviewed temporary/permanent deferral, zero-component outcomes, laboratory/processing release, full-quantity atomic acceptance, physical disposal and role scope against the ERD comments.

## 4 — Layout review and edits

Confirmed that the 14-node, 22-edge graph admits a planar drawing. Compared orientations/orderings and compacted selected members without removing relationships or renaming fields. Used plain SVG text and matching global/theme font settings to avoid wrapping/measurement defects. The selected A4 overview uses left-to-right direction and has zero detected connector crossings and zero detected paths through class boxes. Association role captions are kept in source comments/detailed view rather than crowding the A4 drawing.

## 5 — Final correspondence and print verification

Rechecked the saved overview and detailed source after layout edits. Confirmed 14 exact class names, an ERD-subset of overview fields, all 135 exact detailed attribute/type pairs, and all 22 exact association multiplicities. Confirmed the ERD source hash was unchanged. Rebuilt all current overview source/preview entry points and replaced the earlier A4 PDF. The final PDF has one A4 landscape page, contains every exact class name, uses around 8.59 pt vector diagram text and has no text outside the page. Rendered and visually inspected the final page for text alignment, wrapping, labels and compartment borders.

These checks apply to the supplied renderer/font/configuration. The detailed model is intentionally not claimed to fit on one A4 page. The current class model follows the ERD; conflicts with forecast/notification persistence and addressed-bank use cases are documented for a separate ERD decision.
