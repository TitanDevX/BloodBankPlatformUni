# Blood Bank Platform — complete workflow class design

Updated 27 October 2026. The design uses `docs/ER/code/erd-low-level.mmd` as the shared entity/attribute baseline and the current functional requirements, detailed use cases and AI forecasting plan for behavior. This revision closes the six gaps found in review. It is a design update, not backend implementation or a database migration.

## Scope and correspondence

There are now **16 entities, 189 attributes and 26 role-specific associations** in both the detailed ERD and detailed class model. All 14 original entity identifiers and all 135 original field names/types are preserved. Additions are seven routing/rejection fields on `requests` and the two forecast entities `forecast_runs` and `demand_forecasts`.

Existing names remain verbatim lowercase plural snake_case. No aliases such as DonationAttempt, BloodCollection or HospitalRequest are introduced. `roles` remains an entity. The A4 overview suppresses types and shows selected members; `class-diagram-detailed.mmd` contains every ERD field/type, full proposed operations, association captions and key/nullability annotations. Existing main/horizontal/A4 overview filenames contain the same current source.

Association direction is a layout choice for these undirected links. Both endpoint multiplicities are preserved. The two request–bank associations intentionally have different roles and cardinalities: `addressed` is required for a submitted request; `accepted` is optional until acceptance. These captions remain visible in the A4 view so their meanings cannot be confused. Other role captions remain in source comments and the detailed view.

## Closed review findings

| Finding | Design fix | ERD impact |
| --- | --- | --- |
| Pending requests had no durable destination bank | `requests.addressed_bank_id`, `matched_at`, `matching_policy_version`; `submit()` rechecks and retains the destination | Adds a required addressed-bank association; the original optional accepting-bank association is unchanged |
| Saved forecasts were absent | `forecast_runs.generate()/validate_result()/save_result()/retrieve_saved()` and `demand_forecasts.validate()` | Adds two entities, bank/requester ownership and run-to-point associations |
| Nearest-bank matching was not assigned | Static `requests.find_nearest_match(criteria)`; `blood_banks.can_supply()` and `blood_components.is_available()` support it | No saved request or reservation is created by matching |
| Request/expiry notices had no owner | `requests.notify_inventory_staff()`, `blood_components.check_expiry()/notify_inventory_staff()`, `blood_banks.list_notices()` | Notices are authorized read projections of existing durable rows, not new entities |
| Processing writes and release reevaluation were missing | `blood_components.record_processing()/reevaluate_availability()`; collection separation and laboratory finalization trigger reevaluation | Uses the existing processing, test, separation, stock and time fields |
| Rejection audit and competing requests were incomplete | `requests.reject()/reevaluate_pending_requests()`; rejection actor/time/reason/source retained | Adds `rejected_by_staff_id`, `rejected_at`, `rejection_reason`, `rejection_source` |

The A4 `requests` and `blood_components` classes now display the exact `abo_group` and `rh_factor` fields rather than hiding these essential matching inputs.

## Operation responsibilities and invariants

### Match, submit and decide

`requests.find_nearest_match(criteria)` is a static query, underlined in the diagram. It can run before any request object is persisted. It uses the hospital location and requested component, ABO/Rh and quantity, then finds the nearest single bank with the full eligible quantity. Every unit must be processing-accepted, laboratory-cleared, verified, unexpired, undisposed and available. A retrieval/location failure is an error, not a no-match result. A deterministic tie policy belongs to `matching_policy_version`.

A no-match search, Retry and cancellation do not create a submitted request or reserve stock. `submit()` checks hospital scope, revalidates the current match, records the addressed bank and match metadata, saves the pending request and exposes its bank worklist notice. Submitted bank, time, component/group and quantity are original facts; the current scope does not introduce edits, rerouting or correction policy for them.

`accept()` checks the deciding inventory staff member's current bank scope and request state, locks/rechecks eligible units, records the accepting bank/actor/time and reserves exactly `quantity_units` specific components in one transaction. The accepting bank equals the addressed bank. In that same decision, `reevaluate_pending_requests()` automatically rejects similar pending requests that remaining stock cannot fully satisfy and leaves adequately supplied requests pending. Partial reservation and duplicate component allocation are prohibited. Acceptance is not proof of delivery.

Manual `reject()` records actor, time, source=`manual` and a reason code; when the UI supplies no free-text reason, a code such as `manual_decision` records the actual action without adding a mandatory form step. Automatic insufficient-stock rejection records time/source/reason and leaves the staff actor null; it does not fabricate a user. A pending request has neither acceptance nor rejection audit values. A rejected request has no allocations. Accepted requests have no rejection values. The existing staff–request association represents the required submitter; optional decision actors remain separate typed fields, following the ERD's existing one-line-per-staff-pair convention.

### Processing, laboratory clearance and stock

`blood_units` keeps collection and separation outcomes, failure details and responsible staff/time. `blood_components.record_processing()` records each container's identity, type, volume, expiry and acceptance/rejection. In the current FR-16 single processing action, the processing technician is traceable through the linked unit's `separated_by_staff_id`, with component `processed_at`. A future workflow allowing independent per-component decisions by different technicians would need a separate actor/history design.

Processing and testing can complete in either order. Successful separation creates components; failed separation creates none. `record_processing()`, `record_separation()` and `lab_test_results.finalize()` coordinate `reevaluate_availability()`. Release requires accepted processing, successful separation, passed tests and verified ABO/Rh. Verified values are copied into the existing component fields. Failed laboratory screening is distinct from pre-donation deferral.

`is_available()` additionally checks current expiry, allocation and disposal whenever stock is used. A delayed/failed monitoring task cannot make an expired component eligible. `component_disposals.record()` records actual physical disposal, responsible staff and actual time; a notice or rejected/expired state alone is not that record. Donor → donation → unit → component links and the direct donation → component link remain intact.

### Notices without a new notification entity

`blood_banks.list_notices()` returns two current worklists for authorized active inventory assignments at that bank:

- pending `requests` where `addressed_bank_id` is that bank;
- expired, undisposed `blood_components` traced to that bank.

The corresponding entity operations expose/update these durable states; `check_expiry()` makes unusability explicit without deleting allocation history or pretending physical disposal occurred. Request and expiry notices become accessible even though there is no separate per-recipient notification row. FR-18/FR-23 require accessible notices, not read receipts, sent-message history or an email/SMS provider. Those features would need their own future persistence/delivery design. Worklist/query failure never bypasses stock validity checks.

### Forecast generation and retrieval

`forecast_runs` belongs to one bank and one requesting staff account. Access requires a current permitted bank-administrator assignment at generation, job execution and retrieval. Generation reads authorized aggregates of original submitted request quantities, grouped by their addressed bank, component and ABO/Rh. Later pending/accepted/rejected status does not change the original demand event. No-match searches and cancelled drafts are not stored submissions and are not counted. Unknown historical attribution is not backfilled from today's nearest bank or merely from `accepted_bank_id`.

The run pins the cutoff/timezone, history window, target/dataset/feature/attribution/model versions, parameters, completeness, checksummed snapshot/artifact references and a result manifest. Snapshot-dependent fields may be absent during preparation/failure; successful/partial publication requires real complete provenance, never placeholder values. Input snapshots contain aggregates, not patient references, donor records or credentials. Operating completeness and synchronization data remain prerequisites supplied by an ingestion contract; these run fields do not implement offline synchronization.

Run states are queued → running → succeeded/partial/failed. `validate_result()` verifies every requested series/date, finite nonnegative expectations, appropriate units and matching provenance/model versions. Optional quantiles are all absent unless validated, then nonnegative and ordered. Unavailable series are explicitly represented in the manifest with reasons; they are not invented as zero points. Point rows have result status ok/fallback.

`save_result()` atomically publishes the manifest and all successful daily `demand_forecasts` rows. The composite run idempotency key/fingerprint prevents duplicate retries and detects changed inputs; point uniqueness prevents duplicated run/series/dates. Published results and provenance are immutable; regeneration creates another run. `retrieve_saved()` returns the selected stored run/points without recalculating or substituting a different forecast. Forecasting does not allocate components or decide requests. Forecasts concern recorded submitted demand and do not claim unrecorded no-match needs or total clinical demand.

## Classes and role-specific relationships

The original 14 classes remain: `requests`, `hospitals`, `blood_units`, `request_allocations`, `component_disposals`, `blood_components`, `donor_profiles`, `staff_accounts`, `roles`, `staff_assignments`, `donations`, `blood_banks`, `lab_test_results`, `assessment_results`. Added: `forecast_runs`, `demand_forecasts`.

All original 22 associations retain their multiplicities and meanings. Four associations are added:

| Source class | Source multiplicity | Role | Target class | Target multiplicity |
| --- | --- | --- | --- | --- |
| blood_banks | 1 | receives_addressed | requests | 0..* |
| blood_banks | 1 | owns | forecast_runs | 0..* |
| staff_accounts | 1 | requests | forecast_runs | 0..* |
| forecast_runs | 1 | contains | demand_forecasts | 0..* |

A queued/failed run may contain zero points. The existing `blood_banks [0..1] — requests [0..*] : accepts` association remains separate. Role assignments still use one bank XOR one hospital, or neither for system roles. Removal revokes access while preserving actor/history references. No inheritance or composition diamond is introduced without its actual UML semantics. Public methods describe domain responsibilities; an implementation may delegate orchestration to services/policies/repositories without changing existing entity names. No runtime API signatures are specified by the compact diagram's suppressed parameter lists.

## Functional coverage

FR-01/02 organization registration, FR-03–07 staff/account queries, registration, updates, login and assignments, FR-08–10 donor search/registration/profile updates, FR-11/12 donation registration/ticket, FR-13 eligibility, FR-14/15 collection, FR-16 processing, FR-17 laboratory finalization, FR-18 submission/notices, FR-19 matching, FR-20 atomic decisions/rejection/reevaluation, FR-21 saved forecast generation, FR-22 exact saved retrieval, FR-23 expiry notices and FR-24 physical disposal all have an assigned entity operation or application query responsibility. Routine list/query and constructor plumbing is not exhaustively drawn on A4.

The current workflow follows the detailed use-case text: search before submission. The older process image's broader submit-before-match ordering remains illustrative. The older high-level draw.io summarizes forecasting as a capability and hides detail tables; the low-level source/current Markdown dictionary define exact records. The older Word dictionary, persistence review and root legacy ERD/SQL files are historical references, not the current field baseline. Their originals are retained rather than overwriting the user's edited documents.

Offline recording/synchronization stays separate per the user's choice. Notifications are worklists as stated above. Donor portals, appointments, transfusion records, external messaging, model deployment, migrations and training/runtime implementation are not added by this diagram task.

## Deliverables and verification

Current overview entry points: `high-level-class-diagram.mmd`, `high-level-class-diagram-horizontal.mmd`, `high-level-class-diagram-a4.mmd`. Detailed source: `class-diagram-detailed.mmd`. Current schema/dictionary: `docs/ER/code/erd-low-level.mmd`, `docs/ER/docs/erd-data-dictionary.md`. Checked print: `output/pdf/Blood_Bank_Class_Diagram_A4.pdf`.

Five passes check the six requirements gaps, original identifier/type preservation, every revised ERD field/association and multiplicity, layout, and final print. All 135 original fields/types remain; the revised 189 fields and 26 role-specific associations match the detailed class model. The A4 view includes ABO/Rh and both named bank roles and has zero detected connector crossings or paths through class boxes. The final PDF is one A4 landscape page with approximately 8.14 pt vector diagram text, no clipping and no class-name wrapping. Other renderers/fonts can change placement; the PDF is the verified print result. No backend source was implemented or altered.
