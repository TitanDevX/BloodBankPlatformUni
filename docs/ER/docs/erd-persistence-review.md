# ERD persistence and removability review

Scope: the 16-table proposal in `blood-bank-erd-redesign.mmd`, evaluated against FR-01–FR-24 and `docs/ARCHITECTURE.md` as of 24 October 2026. These are design recommendations, not existing implementation claims.

**Persistence need (P):** 1 means the information can be discarded or recomputed; 10 means losing it would break a required workflow, traceability, or a recorded decision. **Table removability (R):** 1 means the separate table is strongly needed; 10 means its information can readily be moved to another durable source without losing requirements. The scores are independent: a table can be removable while its information must remain durable. **Memory only** means RAM or an expiring cache as the sole authoritative store, not a cache in front of a database.

The current Laravel project uses a database-backed cache by default (`backend/config/cache.php`). That cache is still a cache, and it should not replace the relational source of truth for donations, components, requests, or decisions.

| Table | P / 10 | R / 10 | Memory only? | Assessment |
| --- | ---: | ---: | --- | --- |
| `blood_banks` | 10 | 1 | No | Registered bank identities, locations, and active state are needed across donations, inventory, requests, staff scope, and forecasts. Cache reads if useful; keep authoritative rows. |
| `hospitals` | 10 | 1 | No | Registered hospital identity and location anchor staff scope and requests. |
| `staff_accounts` | 10 | 1 | No | Authentication and the identity of staff who made consequential decisions require durable accounts. The current implementation calls this table `users`. |
| `roles` | 4 | 9 | Yes, from code | No requirement lets users create or edit role definitions. A fixed catalog can be versioned as application constants or seed data, then loaded into memory. Assigned roles themselves must remain durable. Keep the table if roles need independent database management later. |
| `staff_assignments` | 10 | 2 | No | Many-to-many role and organization assignments, including removal history, determine access. A session cache may speed checks but must not be authoritative after an assignment changes. |
| `donor_profiles` | 10 | 1 | No | Reception must find and update a donor across visits; donations must retain their donor link. |
| `donations` | 10 | 1 | No | This is the durable attempt and state record tying the entire donation journey together. Queues can be queried from its status. |
| `assessment_results` | 10 | 5 | No | The decision, answers, reason, actor, and time must survive. Because there is at most one per donation, these fields could technically move into `donations`, but keeping the table makes the assessment event explicit. |
| `blood_units` | 10 | 2 | No | Records collection and separation outcomes, including bag identity, volume, failure reasons, responsible staff, and times. A failed separation remains recorded even though it produces no components. |
| `lab_test_results` | 10 | 3 | No | The final screening result and verified ABO/Rh control whether components can be released. Never make a cache the release authority. A one-to-one merge into `donations` is possible but would obscure the lab decision. |
| `blood_components` | 10 | 1 | No | Each physical component needs its own identity, type, volume, expiry, processing decision, and inventory state. This is authoritative stock. |
| `requests` | 10 | 1 | No | Submitted needs, quantity, hospital-scoped patient reference, acceptance, and current status must persist for fulfillment and historical demand. A displayed match can be recomputed. |
| `request_allocations` | 10 | 2 | No | Records exactly which components were reserved for an accepted request. Durable rows and a database transaction prevent competing requests from claiming the same component. |
| `expiry_notifications` | 7 | 7 | Conditional | If FR-23 means a live worklist of expired components, derive it from `blood_components.expires_at` and status; no notification table is needed. If users need sent/read state, delivery retries, or proof of notice, persist notification records. RAM alone cannot reliably retain that history. |
| `component_disposals` | 10 | 1 | No | FR-24 explicitly requires a record of actual physical disposal, responsible staff, reason, and time. An expiry notice is not a substitute. |
| `demand_forecasts` | 9 | 4 | No | FR-22 requires retrieval of *saved* forecasts. Intermediate calculations can be in memory, but the generated result and provenance must survive. A database row is the simplest durable form. |

## Simplifications applied

Separation outcome, failure reason, staff member, and completion time now live on `blood_units`. The patient reference now lives directly on `requests`, scoped by its `hospital_id`. Both remain durable even though their former tables have been removed.

## Further optional simplification

Keep all authoritative operational data durable. If minimizing tables is the goal, the clearest candidates are:

1. Replace `roles` with a fixed, version-controlled role catalog while retaining durable `staff_assignments`.
2. Derive an expiry worklist from component expiry and status. Retain `expiry_notifications` if sent/read/delivery history matters.

`assessment_results` and `lab_test_results` could be merged into `donations` on cardinality alone, but keeping them separate makes decisions, ownership, and testing clearer. Do not remove `request_allocations` to save a table: the exact unit-to-request link is central to safe acceptance.

## Appropriate use of memory and cache

- Cache **copies** of bank/hospital details, static roles, and read-only stock summaries. Invalidate or refresh them after authoritative database changes.
- Compute the screening queue from `donations.status`, the visit-ticket barcode from `donations.donation_reference`, the nearest-bank match from current eligible stock, and expiry candidates from `expires_at`. These are not additional persistent tables.
- Use transient memory for in-progress forecast calculations or a user's unsaved form. Save completed forecasts and submitted decisions to durable storage.
- At request acceptance, recheck eligible stock and create allocations together in a database transaction. A cached match is advisory.

Laravel's cache is intended for temporary retrieval of data, and its `array`/memo stores are useful for tests or a single execution. Its database transaction API commits or rolls back related writes as one operation. See [Laravel cache](https://laravel.com/framework/docs/13.x/cache) and [Laravel database transactions](https://laravel.com/framework/docs/13.x/database#database-transactions).
