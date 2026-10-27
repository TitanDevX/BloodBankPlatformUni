# Blood Bank Platform — ERD-aligned class design

The authoritative detailed schema for this revision is `docs/ER/code/erd-low-level.mmd`. The class model preserves its entity identifiers, attribute identifiers, detailed data types, association meanings, and multiplicities. No entity is renamed or replaced with a synonym.

## Deliverables

- `high-level-class-diagram-a4.mmd`: compact Mermaid class diagram for A4 landscape.
- `high-level-class-diagram.mmd` and `high-level-class-diagram-horizontal.mmd`: identical current overview entry points, so older links remain current.
- `class-diagram-detailed.mmd`: all 14 ERD entities, all 135 attributes with exact ERD types, all 22 associations with ERD captions, proposed operations, and key/nullability annotations.
- `high-level-class-diagram-a4.svg` / `.png`: vector diagram and print preview.
- `output/pdf/Blood_Bank_Class_Diagram_A4.pdf`: verified one-page A4 landscape PDF.

The overview displays selected fields and operations with types suppressed to retain readable text on A4. This is a UML presentation choice, not a schema change. The detailed source retains every ERD field and type. Names remain lowercase plural snake_case exactly as requested, rather than being converted to conventional singular class names. Attributes use private visibility (`-`); proposed operations use public visibility (`+`). Operations are proposed responsibilities, not claims about existing PHP methods.

## Classes and selected overview members

| Exact ERD / class name | Selected attributes | Proposed operations |
| --- | --- | --- |
| requests | patient_reference, component_type, quantity_units, status | accept(), reject() |
| hospitals | display_name, address | submit_request() |
| blood_units | volume_ml, collection_status, separation_outcome | finish_collection(), record_separation() |
| request_allocations | allocated_at | reserve() |
| component_disposals | reason, disposed_at | record() |
| blood_components | component_type, expires_at, status | is_available() |
| donor_profiles | full_name, phone | update_profile() |
| staff_accounts | name, email | authenticate() |
| roles | code, scope | is_in_scope() |
| staff_assignments | scope, removed_at | assign(), remove() |
| donations | donation_reference, status | register(), print_ticket() |
| blood_banks | display_name, latitude, longitude | can_supply() |
| lab_test_results | status, requires_follow_up | finalize() |
| assessment_results | final_decision, deferral_reason | record_decision() |

Methods do not turn a row or stored result into an authorization authority. Application services/policies coordinate authorized workflows, transactions and queries. Constructors/factories, search/list operations, persistence, queues and controllers are not exhaustively modeled in this domain overview. No additional service classes, inheritance hierarchy or persistent entities are introduced merely to display more UML relationship types.

## All 22 associations, matching the ERD

The multiplicities below are written beside their corresponding class. For example, `blood_banks [0..1] — requests [0..*]` means each request has at most one accepting bank and each bank may accept many requests.

| Class A | A multiplicity | ERD meaning | Class B | B multiplicity |
| --- | --- | --- | --- | --- |
| hospitals | 1 | creates | requests | 0..* |
| blood_banks | 0..1 | accepts | requests | 0..* |
| staff_accounts | 1 | submits | requests | 0..* |
| requests | 1 | has | request_allocations | 0..* |
| blood_components | 1 | assigned_to | request_allocations | 0..1 |
| donor_profiles | 1 | makes | donations | 0..* |
| blood_banks | 1 | receives | donations | 0..* |
| staff_accounts | 1 | registers | donations | 0..* |
| donations | 1 | has_assessment | assessment_results | 0..1 |
| staff_accounts | 1 | records | assessment_results | 0..* |
| donations | 1 | collected_as | blood_units | 0..1 |
| staff_accounts | 1 | collects_and_separates | blood_units | 0..* |
| donations | 1 | tested_by | lab_test_results | 0..1 |
| staff_accounts | 1 | records | lab_test_results | 0..* |
| blood_units | 1 | separates_into | blood_components | 0..* |
| donations | 1 | traces | blood_components | 0..* |
| blood_components | 1 | disposed_as | component_disposals | 0..1 |
| staff_accounts | 1 | records | component_disposals | 0..* |
| staff_accounts | 1 | holds | staff_assignments | 0..* |
| roles | 1 | grants | staff_assignments | 0..* |
| blood_banks | 0..1 | scopes | staff_assignments | 0..* |
| hospitals | 0..1 | scopes | staff_assignments | 0..* |

The compact overview suppresses relationship captions to avoid crowding, but retains every association and endpoint multiplicity. Each ERD caption is preserved in an overview source comment and displayed in the detailed Mermaid model. Reordering undirected endpoints for layout does not change their meaning.

## Important rules retained from this ERD

1. `donations` includes attempts that never progress to collection. It has at most one `assessment_results`, `blood_units` and `lab_test_results` record. A failed collection/separation may produce zero `blood_components`.
2. `blood_components` links directly to `donations` and also to `blood_units`. Its `donation_id` must agree with the linked blood unit's `donation_id`; the direct trace association is explicitly retained.
3. The accepting bank is `requests.accepted_bank_id`. It is optional before acceptance and identifies exactly one bank on acceptance. The relationship is not reinterpreted as a matched/addressed bank. Pending/rejected requests have no allocations; acceptance allocates the complete exact-match quantity from the accepting bank atomically.
4. `requests.patient_reference` is scoped by `hospital_id`; no separate Patient class is introduced.
5. `roles` is an explicit class associated with `staff_assignments`, not an enum substituted for the ERD entity. `staff_assignments.scope` agrees with `roles.scope`. A bank scope has one bank, a hospital scope one hospital, and a system scope neither. A removed assignment no longer grants access.
6. `blood_components.abo_group` and `rh_factor` are actual ERD fields. Verified values are copied from laboratory results; they are not replaced by a solely derived value object. These attributes remain in the detailed diagram even when suppressed in the A4 overview.
7. Component availability requires successful separation, accepted processing, passed laboratory results, verified ABO/Rh, no expiry and no conflicting stock state. Testing and processing may complete in either order. Failed laboratory testing is distinct from pre-donation deferral.
8. Temporary deferral requires `deferral_until`; permanent deferral does not. Eligible decisions have no deferral fields. Follow-up indicators/reasons, screening measurements, failure reasons, physical disposal information, staff audit fields and timestamps remain in the detailed model exactly as supplied by the ERD.
9. The ERD draws one association per class pair for several staff actor roles. The class overview follows that same convention. Nullable additional actor fields, including request acceptance and assignment removal actors, remain nullable in the detailed field annotations; the combined line is not a claim that those optional events have already occurred.
10. Disposal records actual physical disposal. Expiry or rejection alone is not a physical disposal record. Historical traceability and audit records are retained; no composition/cascade-delete claim is introduced.

## Reconciliation with other project documents

The current low-level ERD is authoritative for class/entity and attribute naming in this revision. `docs/ER/code/erd-high-level.drawio` and the exported high-level image provide overview context; the low-level source explicitly extends that overview with `roles` and `staff_assignments`.

The earlier class diagram's `DonationAttempt`, `BloodCollection`, `EligibilityAssessment`, `LaboratoryScreening`, `HospitalRequest`, `Donor` and other renamed entities are superseded by the exact ERD identifiers. Its generic `Notification` and `DemandForecast` classes are absent from this ERD and therefore are not carried into this ERD-aligned model.

This naming alignment does not cancel forecasting or expiry notification requirements. FR-21–FR-23 still describe forecasting and expiry notices; those persistence/service decisions remain outside the current low-level ERD. The high-level draw.io contains a `Demand Forecasting` capability label without a corresponding low-level entity. These are recorded as source coverage gaps, not silently resolved by adding or renaming classes.

The newer request use cases describe a bank addressed at submission and request notifications; the current ERD provides an optional accepting bank. The class diagram now follows the ERD's actual accepting-bank relationship. Resolving the addressed-bank/notification persistence requirement needs a separate explicit ERD decision.

The literature review's offline recording/synchronization remains documented separately, following the user's instruction. No offline/synchronization classes are introduced into the main diagram. Donor portals, appointment tools, transfusion records, external integrations and image analysis are likewise not imported from related systems.

No ERD, backend source, functional requirements, use cases or literature documents were changed by this update. This is a proposed class design, not a claim that the corresponding PHP classes are implemented.

## Verification

Five review-and-edit passes checked source selection/names, every attribute/type, all association meanings/multiplicities, A4 layout and final PDF output. Automated comparison confirmed 14 exact entity names, all 135 detailed attributes/types and all 22 normalized associations with matching multiplicities. The final overview has zero detected connector crossings and zero detected paths through class boxes. The exported PDF is one A4 landscape page, with diagram text around 8.59 pt and no text outside page bounds. It was rendered and visually inspected. Other Mermaid versions/fonts may alter automatic placement; the PDF is the verified print result.
