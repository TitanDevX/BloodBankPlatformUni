# Backend API reference — Graduation 1

**Status:** proposed implementation contract, not a list of working routes.

**Baseline:** requirements revised 15 October 2026; reference prepared 16 October 2026.

**Public API:** Laravel modular monolith, JSON over HTTPS, prefix `/v1`.

**Consumers:** React frontend; a future mobile client can use the same protected endpoints.

## 1. Sources, scope and decisions

This reference translates the following project documents into endpoint contracts:

- [Requirements and acceptance criteria](BloodBank_Requirements.docx): the authoritative functional baseline, FR-01–FR-20.
- [REST conventions](API_CONVENTIONS.md): paths, resource names, JSON, pagination, updates and errors.
- [Master ERD](erd/mermaid_only/main_erd.mmd): entities, relationships, constraints and bank-scoped forecasting notes.
- [Role permission matrix](role_permission_matrix.xlsx): action permissions, including mappings explicitly marked **proposed**.
- [Architecture document](Software_Architecture.docx) and [application diagram](architecture/application.mmd): module boundaries, shared database and Python integration.
- [Organization use cases](uml/UC-01-Organizations.puml), [identity use cases](uml/UC-02-Identity.puml), [login sequence](uml/SQ-02-Login.puml) and other organization/access diagrams in `uml/`.

Endpoints below are **designed**, unless identified as existing infrastructure. Business routes were not present in the inspected Laravel scaffold. Laravel currently configures its framework health route at `/up`.

### 1.1 Scope boundaries

- One request is supplied completely by **one** bank. Search exposes **one nearest exact-match bank**, or `NO_MATCH`; never a ranked candidate list.
- Match component type, confirmed ABO/Rh and all mandatory processing attributes exactly. Matching does not establish clinical suitability.
- One component is one physical container and one allocation unit. The original donation remains after separation.
- Donation registration precedes collection. Eligibility, collection, processing and laboratory release are separate concepts.
- Expiry is calculated from collection completion using the configured component policy. The client cannot submit an expiry date.
- Staff confirmation reserves stock; issuing the complete reservation set fulfills the request. Fulfilled does not mean delivered or transfused.
- Demand forecasting is the only AI capability. Model evaluation is development evidence, not a separate operational feature.
- No donor login, response prediction, shortage prediction, expiry-risk prediction, substitute-group matching, inter-bank transfers, barcode APIs, crossmatching, transfusion recording or government-system integration is included.
- FR-15 and most FR-17 screens are **Should Have**. FR-20 is **Could Have**. Their proposed APIs remain documented so the planned backend is complete; they are not silently promoted to Must Have.

### 1.2 Explicit design decisions and unresolved source differences

The requirements do not prescribe every transport field. This reference proposes concrete JSON schemas and guards; these choices require team approval before implementation.

| Topic | Decision for this contract |
| --- | --- |
| Login URL | Use the `/v1` convention. The older sequence diagram's `/api/login` is illustrative and needs updating, not an additional supported route. |
| Authentication transport | Propose a Sanctum bearer-token session, consistent with the login sequence. Requirements permit session or token authentication. Sanctum still needs to be installed/configured; this document does not claim it is already implemented. If first-party cookie authentication is selected instead, revise the authentication section before coding. |
| Donor ownership | FR-03 explicitly describes bank-owned donor profiles; use bank-scoped donors. The compact ERD omits `donors.bank_id` and its Bank relationship: add those to the ERD/migration design. No cross-bank donor discovery is implied. |
| Release storage | The compact ERD has one current `screening_decisions` row per donation; older paragraphs also place release fields on Donation. Make Screening the authoritative owner; Donation exposes a derived release summary, not a second independently editable decision. |
| Detailed fields | The compact ERD deliberately omits clinical snapshots, contact details and audit metadata. Their inclusion here comes from the expanded requirements; migrations must include them. |
| Specialist roles | Follow the workbook's grants, without assuming administrators or bank administrators inherit every operational permission. Cells marked proposed remain proposed here. Read permissions needed by a workflow are narrowly scoped supporting permissions, not permission to edit linked records. |
| Bank request inbox and acceptance | Proposed policy: an unallocated, nonterminal request is visible to fulfillment staff only when their bank is the currently selected nearest source; an allocated request is visible to its source bank. Acceptance records an event but does not assign stock or persist an allocation bank. Recheck the nearest source at confirmation. This fills an access/routing detail absent from the requirements. |
| Forecast target | FR-16 says requested units including unmet demand; ERD notes refer to unfulfilled attribution. Proposed interpretation: count each valid request's original requested units once in its submission-day demand, even if later fulfilled; source attribution uses its assigned source at the cutoff, or the radius rule if unassigned. Do not use only outstanding balances, which would erase fulfilled demand. The team and Baraa must approve this reconciliation before training; persist a target/attribution policy version. |
| Clinical field policy | Structured unknown/not-applicable states below are proposed validation rules, not clinical judgments. Confirm supported products, test codes, processing attributes, clinical units, history requirements and fixed shelf lives before demonstration. No medically valid shelf-life durations are invented here. |

## 2. Common API contract

### 2.1 Headers, identifiers and ownership

Protected endpoints require `Authorization: Bearer <accessToken>` and `Accept: application/json`. JSON writes also require `Content-Type: application/json`. Never place a token in the URL. Do not log passwords, tokens, clinical payloads or notification bodies.

All path placeholders below are **IDs**, not full resource names. Proposed IDs are server-generated, lowercase, URL-safe strings matching `[a-z0-9][a-z0-9-]{0,63}`. An internal numeric database key is not a public API requirement. Examples such as `bank-1` and `donation-1` are illustrative.

Every resource has an output-only `name`, its canonical relative path without `/v1`, and output-only `createTime`/`updateTime` where meaningful. Display text uses `displayName`. Foreign links use resource names, not bare IDs:

```json
{
  "name": "banks/bank-1/components/component-1",
  "donation": "banks/bank-1/donations/donation-1",
  "bank": "banks/bank-1",
  "createdBy": "users/user-4"
}
```

Canonical owners:

| Resource | Canonical name pattern |
| --- | --- |
| Bank / Hospital / User / Role / Session | `banks/{bank}`, `hospitals/{hospital}`, `users/{user}`, `roles/{role}`, `sessions/{session}` |
| Donor / Donation / Component | `banks/{bank}/donors/{donor}`, `banks/{bank}/donations/{donation}`, `banks/{bank}/components/{component}` |
| Screening result / Current decision / Notification | `banks/{bank}/donations/{donation}/screeningResults/{result}`, `banks/{bank}/donations/{donation}/screeningDecisions/current`, `banks/{bank}/donations/{donation}/donorNotifications/{notification}` |
| Patient / Hospital request | `hospitals/{hospital}/patients/{patient}`, `hospitals/{hospital}/requests/{request}` |
| Reservation / Issue / Status event | `hospitals/{hospital}/requests/{request}/reservations/{reservation}`, `hospitals/{hospital}/requests/{request}/issueRecords/{issue}`, `hospitals/{hospital}/requests/{request}/statusEvents/{event}` |
| Disposal record | `banks/{bank}/components/{component}/disposalRecords/{disposal}` |
| Forecast run / Point | `banks/{bank}/forecastRuns/{run}`, `banks/{bank}/forecastRuns/{run}/forecastPoints/{point}` |
| Audit event | `auditEvents/{event}` |

The bank request inbox is a **query view**, not a second owner of hospital requests. Linked records always return their canonical names.

### 2.2 Values and validation

- Times: RFC 3339 UTC, for example `2026-10-16T09:30:00Z`. Dates: `YYYY-MM-DD` only for birth dates, deferral dates and forecast target dates.
- Quantities: integer containers; `unitsRequested >= 1`. Measurements explicitly include a unit. Predicted units may be nonnegative decimals; the UI must not silently claim they are committed containers.
- ABO: `A`, `B`, `AB`, `O`; Rh: `POSITIVE`, `NEGATIVE`. Unknown reported donor values are `null`, not invented groups. Requests need known ABO and Rh to participate in exact matching.
- Configured codes such as component/test/processing codes are validated against the public catalog. Examples in this document are illustrative, not a clinical catalog.
- Latitude: finite number from -90 to 90; longitude: -180 to 180. Both are required when a facility is active/searchable. Zero is valid only when actually entered, never a missing-value fallback.
- Unknown JSON properties and unsupported filter/update fields are rejected with `INVALID_ARGUMENT`. Output-only fields cannot be supplied on writes.
- All bodies are objects. A field marked required must be supplied and non-null unless its schema explicitly permits null. Optional omitted fields use the documented default; omission is not permission to invent clinical data.
- Optional nullable profile fields may be cleared with explicit `null` and a matching update mask. Arrays are replaced as a whole; `[]` means explicitly no selections. Required fields cannot be cleared.
- IDs, organization ownership, creators, audit fields, counters, computed expiry and lifecycle status cannot be changed through generic PATCH.

### 2.3 PATCH

Use `?updateMask=displayName,contactEmail`. The mask contains lowerCamelCase field paths. If omitted, update the mutable fields present in the body; an explicitly empty mask performs no update. Fields named in a nonempty mask must appear in the body, including explicit null when clearing. Reject immutable paths and inconsistent body/mask combinations. Endpoint tables identify the exact permitted fields.

State transitions use POST custom methods, not `PATCH {"status": ...}`. Significant corrections use audited actions. No public DELETE exists for donors, clinical records, components, requests, forecasts or history.

### 2.4 Pagination, filtering and responses

All List endpoints use `pageSize` (default **25**, maximum **100**) and opaque `pageToken`. Omitted/zero means 25, above 100 is capped, negative is invalid. Every list returns `{collectionKey: [...], nextPageToken: "..."}`; the final token is `""`. Scope is applied before filtering and pagination. Tokens bind scope, filters and ordering; changing those invalidates the token. A page token is not an authorization grant or a transaction snapshot.

Endpoint tables list allowed `filter` fields. Proposed filter grammar: `field=value` or `field>=value` / `field<=value` for supported numbers/times; combine clauses with ` AND `. Quote string values, for example `collectionStatus="COLLECTED" AND createTime>="2026-10-01T00:00:00Z"`. No arbitrary SQL, OR, substring expressions or joins. `search` is a separate bounded plain-text query where listed (1–100 characters). `orderBy` accepts one listed field with optional `asc`/`desc`; default `createTime desc`, followed by canonical `name asc` as a stable tie-breaker. Catalog/role lists default to `name asc`; points default to `targetDate asc`; events default to `occurTime asc`.

Create, Get, PATCH and synchronous custom actions return **200**, following the project conventions, not a generic `data` wrapper. A custom action with multiple resulting resources returns the named response object defined below. Delete-session returns 200 with an empty body. Collections return their documented summary projection; never passwords/tokens or general donor/clinical details. Detailed linked information requires its own authorized request.

### 2.5 Errors and retry safety

Every endpoint inherits these errors: `400 INVALID_ARGUMENT`, `401 UNAUTHENTICATED`, `403 PERMISSION_DENIED`, `404 NOT_FOUND`, `429 RESOURCE_EXHAUSTED`, and `503 UNAVAILABLE`. Domain guards use `400 FAILED_PRECONDITION`; duplicate unique resources use `409 ALREADY_EXISTS`. Unexpected failures use `500 INTERNAL` with no confidential diagnostic detail. Authentication happens before business validation. For a protected organization, check scope before disclosing resource existence: unauthorized scope is 403; an authorized but absent record is 404.

```json
{
  "error": {
    "code": 400,
    "message": "The complete selection is no longer available. Refresh the source search.",
    "status": "FAILED_PRECONDITION",
    "details": [
      {
        "@type": "type.googleapis.com/google.rpc.ErrorInfo",
        "reason": "SELECTION_CHANGED",
        "domain": "api.bloodbank.example"
      }
    ]
  }
}
```

Use the deployed service domain instead of the example domain. Validation may add `google.rpc.BadRequest` details with `fieldViolations: [{field, description}]`; do not echo clinical values in errors.

**Proposed idempotency contract:** require an `Idempotency-Key` UUID header on resource-creating and mutating custom POST endpoints except login. Bind it to actor, route and normalized body; retain completed outcomes for at least 24 hours. A duplicate identical call returns its original result without repeating the mutation; a changed payload using the same key returns 409 with reason `IDEMPOTENCY_KEY_REUSED`. Concurrent use of a still-running key returns 400 `FAILED_PRECONDITION`, reason `REQUEST_IN_PROGRESS`; retry after the advertised `Retry-After`. Replays still recheck the caller's current access. PATCH, logout and provider callbacks do not rely on this header. Replayed outcomes describe the original operation; fetch the resource for current state. Rejection-event email deduplication and one-active-reservation database constraints remain mandatory independently of header retention.

## 3. Authorization model

| Alias | Role code | Scope |
| --- | --- | --- |
| A | `administrator` | Explicitly granted network administration, inventory inspection, confirmation, issue, disposal, traceability, reporting and forecasting. Not an automatic laboratory or collection role. |
| BA | `bank_admin` | One bank; permitted inventory/trace/report/forecast reads and limited staff role assignment. |
| C | `bank_donor_collection` | Own-bank donor and collection workflows. |
| I | `bank_inventory` | Own-bank prepared components, inventory and disposal. |
| F | `bank_fulfillment` | Authorized source-bank request review, reservation and issue. |
| LT | `laboratory_testing` | Assigned-bank test recording/correction. |
| LR | `laboratory_release` | Assigned-bank release decisions and failed-email retry. |
| H | `hospital_requester` | Own-hospital requests, associated patient entry, source lookup and permitted issue information. |

Specialist action mappings mostly carry **Proposed mapping** evidence in the workbook. These aliases make the draft executable as a design, not final permission approval. Laboratory assignment is proposed as one `bank` with no hospital; confirm this before provisioning laboratory accounts.

Every protected request checks the user is active, its organization scope, and the action permission. A deactivated account loses access even with a previously valid token. Inactive organizations retain historical reads by authorized users but cannot originate new donations, components, requests or allocations. Relevant write services recheck this; deactivation is not a deletion or automatic cancellation. Existing reservations remain historical/current commitments, but issue is blocked while the source is inactive; staff can cancel/release them. Re-activation is not added as a separate requirement in this draft.

Bank-admin role assignment requires actor and target to have the **same non-null bank**, different user IDs, and new role `bank_donor_collection`, `bank_inventory` or `bank_fulfillment`. It cannot change the target's organization, email, password, active state or any other field.

## 4. Shared resource and input schemas

Tables specify **required write inputs** and **returned fields** separately. Common output metadata is additional. `?` denotes optional input; `null` is allowed only where stated. Named schemas below are reused by endpoint tables: an endpoint returning `Donation` returns the full permitted Donation schema, not an unspecified payload.

### 4.1 Organizations, identity and catalogs

| Schema | Fields and meaning |
| --- | --- |
| Bank / Hospital | Output: `name`, `displayName`, `address`, `contactPhone` (nullable), `contactEmail` (nullable), `latitude`/`longitude` (nullable only while inactive), `active`, timestamps. Bank contact phone or email must exist before rejection-follow-up dispatch. |
| OrganizationCreate | Required: `displayName` (1–200 chars), `address` (1–500), `latitude`, `longitude`, `active` (boolean). Optional: `contactPhone`, `contactEmail`; defaults null. An active bank requires at least one contact channel for the follow-up workflow. |
| User | Output: `name`, `displayName`, `email`, `role` (Role resource name), `bank` (nullable), `hospital` (nullable), `active`, timestamps. Never includes password/hash/token. A user has one role; system role has neither organization, bank roles one bank, hospital role one hospital. |
| UserCreate | Required: `displayName`, unique `email`, `initialPassword`, `role`, `active`; `bank` or `hospital` required according to role, the other must be null/absent. Proposed password policy: minimum 12 characters, maximum 128; server hashes it. |
| UserSummary | `name`, `displayName`, `role`, `bank`, `hospital`, `active`; excludes email from general lists. A gets full User detail; BA's staff picker/detail uses UserSummary. |
| Role | `name`, `code`, `displayName`, `scope` (`SYSTEM`, `BANK`, `HOSPITAL`). Read-only seeded definitions; no role/permission editor in Graduation 1. |
| SessionCreateResponse | `name` (`sessions/{session}`), `user` (User), `accessToken` (one-time plaintext), `tokenType: "Bearer"`, `expireTime` (configured RFC 3339). Token is returned only by login, never listed/logged. |
| ClinicalCatalog | `name: "catalogs/clinical"`, `version`, `componentTypes: [{code, displayName, shelfLifeSeconds, expiryPolicyVersion}]`, `processingAttributes: [{code, displayName}]`, `screeningTests: [{code, displayName, requiredForRelease}]`, `urgencies: [{code, displayName}]`, `clinicalUnits` (field-to-allowed-units map), `clinicalValidationPolicyVersion`. Sanitized UI configuration only, no secrets/model paths. Missing shelf-life configuration is represented by null and cannot be used for component creation. |

Example Organization creation input:

```json
{
  "displayName": "Demonstration Bank A",
  "address": "Demonstration address, Damascus",
  "latitude": 33.51,
  "longitude": 36.29,
  "contactPhone": "+963-example",
  "contactEmail": "bank-a@example.org",
  "active": true
}
```

### 4.2 Donors, donations and laboratory

| Schema | Fields and meaning |
| --- | --- |
| DonorWrite | Required on create: `displayName`, `contactPhone`, `email`. Optional: `reportedAbo`, `reportedRh` (both default null). Public resource identifier is generated by the server. Email is required and validated, but is not assumed globally unique: profiles are not login accounts. |
| Donor | `name`, `bank`, DonorWrite fields, `groupVerificationStatus` (`UNVERIFIED`, `LAB_CONFIRMED`), `confirmedGroup` (nullable `{abo, rh, donation, confirmTime}`), timestamps. Reported values never overwrite historical confirmed donation results. Proposed profile projection: latest authorized laboratory-confirmed group, with provenance; no automatic clinical reconciliation between conflicting donations. |
| DonorSummary | `name`, `displayName`, `groupVerificationStatus`; no email, phone or clinical details. |
| Donation | `name`, `bank`, `donor`, `registeredBy`, `collector` (nullable), `notes` (nullable), `eligibility` (nullable `{outcome, reason, assessedBy, assessTime, deferredUntil}`), `collectionStatus`, `collectionStartTime`/`collectionCompleteTime` (nullable), `volumeMilliliters` (nullable), `processingStatus`, `releaseStatus`, `confirmedAbo`/`confirmedRh` (nullable), timestamps. `registeredBy` explicitly captures responsible staff before collection. |
| DonationSummary | `name`, `bank`, `donor` link, `collectionStatus`, `processingStatus`, `releaseStatus`, `collectionCompleteTime`, `createTime`; no donor contacts, test results or notes. |
| ScreeningResultWrite | `testCode`, `result` (`PENDING`, `PASS`, `FAIL`, `INCONCLUSIVE`); optional `notes` nullable. Proposed normalized interpretation flags recorded by staff, not automatic disease diagnosis. A configured test code occurs at most once per donation; use correction for subsequent values. |
| ScreeningResult | `name`, `donation`, ScreeningResultWrite fields, `recordedBy`, `recordTime`, `updateTime`. Original values/corrections are retained in restricted audit history. |
| ScreeningDecision | Singleton `name`, `donation`, `status` (`APPROVED`, `REJECTED`), `confirmedAbo`/`confirmedRh` (nullable on rejection), `reason` (nullable on approval), `decidedBy`, `decideTime`, `revision`. Pending is the Donation release state before a final decision, not a fabricated decision record. |
| DonorNotification | `name`, `donation`, `rejectionEventId`, `deliveryStatus` (`QUEUED`, `SENT`, `FAILED`), `queueTime`, `sendTime`/`failTime` (nullable), `attemptCount`, `lastErrorCode` (sanitized nullable). Detail additionally exposes `recipientEmail`, `subject`, `body` to LR only; list omits these. `SENT` means provider acceptance, not donor reading. |

Donation enum values: collection `REGISTERED`, `COLLECTING`, `COLLECTED`, `CANCELLED`, `FAILED`; processing `NOT_STARTED`, `IN_PROGRESS`, `PROCESSED`; release `PENDING`, `APPROVED`, `REJECTED`; eligibility `ELIGIBLE`, `DEFERRED`, `REJECTED`. Before assessment, `eligibility` is null. Initial collection/processing/release values are server-assigned. Eligibility reason is required for all recorded assessments under this draft; `deferredUntil` is an optional calendar date only for DEFERRED.

### 4.3 Components, inventory and disposal

| Schema | Fields and meaning |
| --- | --- |
| ComponentCreate | Required: `donation` (same-bank resource name), `componentType` (catalog code), `processTime`, `specialProcessingAttributes` (unique code array, may be explicitly `[]`), `storageLocation` (1–200 chars). No quantity, expiry, group, creator, bank override or approval input. One call creates one container. |
| Component | `name`, `bank`, `donation`, `componentType`, `confirmedAbo`/`confirmedRh` (nullable until confirmed), `processTime`, `expireTime`, `shelfLifeSecondsApplied`, `expiryPolicyVersion`, `specialProcessingAttributes`, `storageLocation`, `createdBy`, `state`, `available`, `unavailabilityReasons` (code array), timestamps. Group/release derive from the authoritative donation decision. |
| ComponentSummary | Component fields except staff-only notes/clinical links beyond the donation identifier; does not embed donor/patient/test data. |
| InventorySummary | `bank`, `asOfTime`, `groups: [{componentType, abo, rh, totalUnits, availableUnits, pendingUnits, reservedUnits, issuedUnits, rejectedUnits, expiredUnits, disposedUnits}]`. Counts are derived from container records, never manually editable. Unknown ABO/Rh group buckets use null. |
| DisposalRecord | `name`, `component`, `bank`, `reason`, `disposedBy`, `disposeTime`. Immutable once created. |

Component `state`: `PENDING`, `AVAILABLE`, `RESERVED`, `ISSUED`, `REJECTED`, `EXPIRED`, `DISPOSED`. `available` is a live stock predicate, not merely a cached state: collected source, approved decision, eligible unreserved component, active source and `expireTime > serverNow`. **Match eligibility** additionally requires exact attributes for the particular request; general component availability is not request-specific. At `expireTime <= serverNow`, it is unavailable even if a scheduled cleanup has not run. Effective inventory state precedence is DISPOSED, ISSUED, REJECTED, EXPIRED, RESERVED, AVAILABLE/PENDING. This disjoint classification prevents double-counting; reasons can still include multiple causes. Historical issued/disposed states are never rewritten by later expiry or rejection.

### 4.4 Patients and complete requests

Patients are registered/reused **within request creation**, not through an additional patient-management subsystem. Patient detail is read-only here; request snapshots preserve submitted identity and clinical facts.

`PatientInput` is either:

- `{ "existingPatient": "hospitals/hospital-1/patients/patient-1" }`, with the same-hospital identity read from that record; or
- `{ "medicalId": "HOSP-123", "displayName": "Example patient", "dateOfBirth": "1990-05-12", "gender": "UNKNOWN" }` to register a new patient. `(hospital, medicalId)` is unique. A duplicate returns 409 and requires selecting the existing patient, not silently overwriting it.

Gender codes proposed for recording are `FEMALE`, `MALE`, `OTHER`, `UNKNOWN`; they do not determine medical eligibility. DOB must be a valid date not in the future.

Every clinical measurement uses:

```json
{
  "state": "KNOWN",
  "value": 8.4,
  "unit": "g/dL",
  "measureTime": "2026-10-16T08:00:00Z"
}
```

`MeasurementInput`: `state` is `KNOWN`, `UNKNOWN` or `NOT_APPLICABLE`. KNOWN requires finite nonnegative `value`, a catalog-approved `unit` and nonfuture `measureTime`. INR uses the explicit dimensionless catalog unit `ratio`. UNKNOWN contains only `state`; NOT_APPLICABLE contains `state` and required `reason`. No zero/default substitutes for a missing measurement.

`HistoryInput`: KNOWN requires `text`; UNKNOWN has no text; NOT_APPLICABLE requires `reason`. `AntibodyScreenInput`: KNOWN requires `result` (`POSITIVE`, `NEGATIVE`, `INCONCLUSIVE`) and nonfuture `measureTime`; UNKNOWN/NOT_APPLICABLE follow the same state rules. These are proposed transport states; the system does not interpret a result as clinical authorization.

| Schema | Fields and meaning |
| --- | --- |
| RequestCreate | Required: `patient` (PatientInput), `clinical` (ClinicalInput below), `componentType`, `recipientAbo`, `recipientRh`, `groupVerificationStatus`, `groupSource`, `unitsRequested`, `specialRequirements` (unique processing-code array, explicitly `[]` if none), `urgency`, `requiredBy`, `destination`, `requesterContact`. Optional: `requestedVolume` (positive `{value, unit: "mL"}`, otherwise null). |
| ClinicalInput | Required fields: `transfusionReason` (nonempty text), `transfusionHistory` (HistoryInput), `pregnancyHistory` (HistoryInput), `hemoglobin`, `plateletCount`, `prothrombinTime`, `inr` (MeasurementInput), `antibodyScreen` (AntibodyScreenInput). Proposed conditional rule: every field must have an explicit state; pregnancy history cannot be silently omitted and NOT_APPLICABLE requires the staff-entered reason. Product-specific clinical requirements are versioned in the catalog and must be agreed, not inferred from patient gender. |
| HospitalRequest | `name`, `hospital`, `patient` (resource link), `patientSnapshot` (`medicalId`, `displayName`, `dateOfBirth`, `gender`), `clinical` snapshot, all exact product/group/quantity/processing/urgency/deadline/destination fields from RequestCreate, `requestedVolume` nullable, `createdBy`, `requesterDisplayName` (from authenticated user), `requesterContact`, `status`, `sourceBank` (null until allocation), `submitTime`, timestamps. No client-created hospital/actor fields. |
| RequestSummary | `name`, `hospital`, `componentType`, `recipientAbo`, `recipientRh`, `unitsRequested`, `specialRequirements`, `urgency`, `requiredBy`, `status`, `sourceBank`, `submitTime`, `createTime`. No patient identity, clinical snapshot or requester contact. |
| Patient | `name`, `hospital`, `medicalId`, `displayName`, `dateOfBirth`, `gender`, timestamps; detail only. Patient list returns `name`, masked `medicalId`, `displayName`, and no clinical history. |

`groupVerificationStatus`: `UNVERIFIED` or `LAB_CONFIRMED`; `groupSource` is a nonempty staff-entered description/reference. These are recorded claims, not automated validation of clinical suitability. `requiredBy` must be later than submission server time. Destination and requester contact are nonempty strings. Urgency must be a configured code; it never changes matching/selection rules. Requested volume does not convert to allocation units.

Example complete request input (catalog-dependent values are illustrative):

```json
{
  "patient": {
    "medicalId": "DEMO-100",
    "displayName": "Demonstration patient",
    "dateOfBirth": "1990-05-12",
    "gender": "UNKNOWN"
  },
  "clinical": {
    "transfusionReason": "Staff-entered clinical reason",
    "transfusionHistory": {"state": "UNKNOWN"},
    "pregnancyHistory": {"state": "UNKNOWN"},
    "hemoglobin": {"state": "KNOWN", "value": 8.4, "unit": "g/dL", "measureTime": "2026-10-16T08:00:00Z"},
    "plateletCount": {"state": "UNKNOWN"},
    "prothrombinTime": {"state": "UNKNOWN"},
    "inr": {"state": "UNKNOWN"},
    "antibodyScreen": {"state": "UNKNOWN"}
  },
  "componentType": "RED_CELLS",
  "recipientAbo": "B",
  "recipientRh": "POSITIVE",
  "groupVerificationStatus": "LAB_CONFIRMED",
  "groupSource": "Hospital laboratory record DEMO-12",
  "unitsRequested": 2,
  "requestedVolume": null,
  "specialRequirements": [],
  "urgency": "ROUTINE",
  "requiredBy": "2026-10-17T12:00:00Z",
  "destination": "Demonstration ward",
  "requesterContact": "Hospital extension 123"
}
```

### 4.5 Source selection, reservations and issue

| Schema | Fields and meaning |
| --- | --- |
| SourceSearchResponse | `request`, `outcome` (`MATCH`, `NO_MATCH`), `selectedBank` (nullable minimal `{name, displayName, contactPhone, contactEmail}`), `distanceKilometers` (nullable), `eligibleUnits` (0 on no match), `unitsRequested`, `matchTime`, `selectionPolicyVersion`, `explanationCodes`. Only one bank; no component/donor/clinical lists or other banks' counts. |
| AllocationPreview | `request` (RequestSummary), `bank` (selected Bank summary), `distanceKilometers`, `selectedComponents` (ComponentSummary array), `unitsSelected`, `canConfirm`, `reasonCodes`, `asOfTime`. Restricted to source F or A. Computed, not a reservation. |
| Reservation | `name`, `request`, `component`, `bank`, `confirmedBy`, `state` (`ACTIVE`, `RELEASED`, `ISSUED`), `reserveTime`, `releaseTime` nullable, `releaseReason` nullable. Active uniqueness is enforced at database level. |
| AllocationResponse | `request` (RequestSummary, RESERVED), `reservations` (complete set), `confirmTime`. |
| IssueRecord | `name`, `request`, `component`, `bank`, `issuedBy`, `issueTime`. Immutable; one component can have at most one issue record. |
| IssueResponse | `request` (RequestSummary, FULFILLED), `issueRecords` (complete set), `issueTime`. |
| RequestStatusEvent | `name`, `request`, `fromStatus` (null for creation), `toStatus`, `actor` (nullable for system), `origin` (`USER`, `SYSTEM`), `reason` nullable, `occurTime`. Immutable. |

Source search uses Haversine distance in kilometres with the same configured Earth-radius constant throughout. Compare full precision, then stable public bank ID ascending for exact ties; round only the displayed distance. Select containers **by stable component ID ascending within the chosen bank**, not FEFO or an AI score. Preview returns the first `unitsRequested` eligible containers. Confirmation must send exactly those names and recheck the current deterministic selection; a stale preview fails without partial changes.

### 4.6 Forecasting, traceability and reporting

| Schema | Fields and meaning |
| --- | --- |
| ForecastCreate | Required: `componentType`, `abo`, `rh`. Optional: `dataSource` (`HISTORICAL`, `SIMULATED`, default HISTORICAL), `horizonDays` (default and supported value 7), `granularity` (default and supported value DAILY). Simulation requires an explicitly enabled demonstration environment; clients do not upload arbitrary training data. |
| ForecastRun | `name`, `bank`, `componentType`, `abo`, `rh`, `dataSource`, `simulated` boolean, `modelVersion`, `targetPolicyVersion`, `attributionPolicyVersion`, `attributionRadiusKilometers`, `inputCutoffTime`, `trainingStartDate`, `trainingEndDate`, `horizonDays`, `granularity`, `generatedBy`, `generateTime`, `points` (seven ForecastPoint objects). `inputCutoffTime` distinguishes future knowledge from model input. |
| ForecastRunSummary | ForecastRun without `points`; no underlying patient/request rows or raw training data. |
| ForecastPoint | `name`, `targetDate`, `predictedUnits` (finite decimal >= 0). Unique target date per run; no shortage/risk/response-prediction fields. |
| ComponentTrace | `component`, `donation` link/summary, `donor` link only when caller is permitted to see that link, `screeningDecision`, `screeningResults` (restricted), `reservations`, `issueRecords`, `disposalRecords`, `events`, `redactedSections`. Linked request details are summarized; never leak patient clinical data through trace. Histories use the same paginated resources for additional pages. |
| RequestTrace | `request` (RequestSummary), `reservations`, `issueRecords`, `components` (summary), `statusEvents`, `redactedSections`; no donor identity or tests. |
| AuditEvent | `name`, `actor` nullable, `origin`, `subject` (resource name), `action`, `occurTime`, `details` (allowlisted changed-field metadata; sensitive prior/new values require separate specific permission). No public write endpoint. |
| OperationalReport | `reportType`, `scope` (bank resource or NETWORK), `startTime`, `endTime`, `asOfTime`, `totals`, `groups`, `hasData`. Type-specific shapes are in section 12. No persisted Report row. |
| DashboardResponse | `scope`, `asOfTime`, `inventory` (nullable permitted totals), `requests` (nullable permitted totals), `forecastRuns` (permitted ForecastRunSummary array). Null means not authorized/not applicable, not zero or a fabricated forecast. |

## 5. Identity, health and reference endpoints

**Module:** Identity, except framework health and sanitized catalog lookup. **Requirements:** FR-02, FR-17; catalog supports FR-04–FR-12.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| SYS-01 | `GET /up` | Public | No body/query. | Existing framework liveness route. Framework-defined response, not the `/v1` resource/error contract. Must not expose configuration, database contents or credentials; does not promise forecasting/email readiness. |
| AUTH-01 | `POST /v1/sessions` | Public, rate limited | Required `email`, `password`; optional `deviceDisplayName` (1–100 chars). | SessionCreateResponse. Generic 401 `INVALID_CREDENTIALS` for invalid/inactive login; never disclose account existence. Role/scope are resolved server-side. |
| AUTH-02 | `DELETE /v1/sessions/current` | Any active authenticated user | No body. `current` aliases the presenting token. | 200 empty; revoke current token only. Subsequent use is 401. No domain record/history deletion. |
| AUTH-03 | `GET /v1/users/me` | All roles | No body/query. `me` is an alias. | User with canonical `users/{id}` name and current scope. |
| AUTH-04 | `GET /v1/roles` | A, BA | Pagination; filter `scope`; order `name`. | `{roles: Role[], nextPageToken}`. BA sees only roles it may assign; A sees all defined roles. |
| AUTH-05 | `GET /v1/users` | A, BA | Pagination; filters `bank`, `hospital`, `role`, `active`; `search` matches display name; order `displayName`, `createTime`. | `{users: UserSummary[], nextPageToken}`. BA constrained to same-bank staff regardless of submitted filter. Supporting staff picker for required limited role assignment. |
| AUTH-06 | `GET /v1/users/{user}` | A; BA for same-bank picker | No body/query. | A: User; BA: UserSummary. Other staff use `/users/me`, not arbitrary-user lookup. |
| AUTH-07 | `POST /v1/users` | A; Should Have | UserCreate. | User. Duplicate email: 409 `EMAIL_ALREADY_EXISTS`; invalid role/org: 400 `INVALID_ROLE_SCOPE`. Initial password is never returned. |
| AUTH-08 | `PATCH /v1/users/{user}` | A; Should Have | Mask/body restricted to `displayName`, `email`. | User. Account role/organization/status cannot be mass-assigned. No password-change/reset workflow is added here. |
| AUTH-09 | `POST /v1/users/{user}:assignRole` | A, BA | Required `role`; A additionally supplies `bank`/`hospital` or explicit null as required by the new scope. BA supplies **role only**. | A: User; BA: UserSummary. Atomic role/scope validation and audit. BA: same non-null bank, different user, allowed target role only; violations 403 and no mutation. This BA policy/API supports **Must Have FR-02** even if optional FR-17 screens are deferred. |
| AUTH-10 | `POST /v1/users/{user}:deactivate` | A; Should Have | Required `reason`. | User with `active:false`; subsequent protected requests using existing tokens fail. Preserve actions. Proposed guard: cannot deactivate the last active system administrator. |
| REF-01 | `GET /v1/catalogs/clinical` | All roles | No body/query. | ClinicalCatalog. Read-only; public catalog does not expose secret runtime settings. Configuration editing is deployment work, not an added CRUD feature. |

## 6. Organizations endpoints

**Module:** Organizations. **Requirement:** FR-01. The following are separate bank and hospital routes, not a generic organization table leaked to clients.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| ORG-01 | `GET /v1/banks` | All roles, permitted summaries | Pagination; filters `active`; search display name; order `displayName`, `createTime`. | `{banks: Bank[], nextPageToken}` with facility/contact information only. No inventory counts or associated users. |
| ORG-02 | `GET /v1/banks/{bank}` | All roles, permitted facility detail | No body/query. | Bank. Cross-network facility visibility does not grant operational record access. |
| ORG-03 | `POST /v1/banks` | A | OrganizationCreate. | Bank. Validate coordinates/contact; linked historical records are not created implicitly. |
| ORG-04 | `PATCH /v1/banks/{bank}` | A | `updateMask`; mutable `displayName`, `address`, `contactPhone`, `contactEmail`, `latitude`, `longitude`. | Bank. Audit changes; changed coordinates affect subsequent searches, not historic recorded distances. Active/contact constraints remain valid. |
| ORG-05 | `POST /v1/banks/{bank}:deactivate` | A | Required `reason`. | Bank with `active:false`. Keep all links/history; no automatic bulk cancellation. |
| ORG-06 | `GET /v1/hospitals` | All roles, permitted summaries | Pagination; filters `active`; search display name; order `displayName`, `createTime`. | `{hospitals: Hospital[], nextPageToken}`. No patients or requests embedded. |
| ORG-07 | `GET /v1/hospitals/{hospital}` | All roles, permitted facility detail | No body/query. | Hospital. |
| ORG-08 | `POST /v1/hospitals` | A | OrganizationCreate. | Hospital. Active requesting hospitals need valid coordinates. |
| ORG-09 | `PATCH /v1/hospitals/{hospital}` | A | Same mutable fields/mask as ORG-04. | Hospital; preserve historical request snapshots. |
| ORG-10 | `POST /v1/hospitals/{hospital}:deactivate` | A | Required `reason`. | Hospital with `active:false`, linked patients/requests retained. |

## 7. Donor and donation endpoints

**Modules:** Donors and Donations. **Requirements:** FR-03, FR-04. Access C only, within assigned bank; narrow supporting donation reads are also available to LT/LR/I where needed for their own workflows, with donor contacts/notes omitted. This does not grant them donor profile editing.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| DONOR-01 | `GET /v1/banks/{bank}/donors` | C | Pagination; `search` matches display name or exact donor ID; filter `groupVerificationStatus`; order `displayName`, `createTime`. | `{donors: DonorSummary[], nextPageToken}`. No contacts in general results. |
| DONOR-02 | `GET /v1/banks/{bank}/donors/{donor}` | C | No body/query. | Donor detail. |
| DONOR-03 | `POST /v1/banks/{bank}/donors` | C | DonorWrite. | Donor with generated name and unverified reported group; creating a profile does not register a donation. |
| DONOR-04 | `PATCH /v1/banks/{bank}/donors/{donor}` | C | Mask/body `displayName`, `contactPhone`, `email`, `reportedAbo`, `reportedRh`. | Donor; audit prior/new values in restricted history. Updating reported group does not edit confirmed donation results. |
| DON-01 | `GET /v1/banks/{bank}/donations` | C; LT/LR/I supporting view | Pagination; filters `donor`, `collectionStatus`, `processingStatus`, `releaseStatus`, `createTime`; order `createTime`, `collectionCompleteTime`. | `{donations: DonationSummary[], nextPageToken}`. Filtering by donor supplies the donor's complete **own-bank** attempt history, including failed/cancelled attempts. |
| DON-02 | `GET /v1/banks/{bank}/donations/{donation}` | C; LT/LR/I supporting view | No body/query. | Donation; LT/LR/I receive no donor contact/collection notes beyond their authorized workflow projection. |
| DON-03 | `POST /v1/banks/{bank}/donations` | C | Required `donor`; optional `notes` nullable. Actor and bank derived from authentication/path. | Donation: REGISTERED / NOT_STARTED / PENDING, `eligibility:null`, no collection times/volume. Donor must belong to the same bank. |
| DON-04 | `PATCH /v1/banks/{bank}/donations/{donation}` | C | `updateMask=notes`; body `notes` (nullable). | Donation. All lifecycle/ownership fields are immutable here. |
| DON-05 | `POST /v1/banks/{bank}/donations/{donation}:recordEligibility` | C | Required `outcome`, `reason`; optional `deferredUntil` for DEFERRED only. | Donation with assessor and server assessment time. Proposed guard: only before collection starts; replacement assessments are audited. DEFERRED/REJECTED do not create components or fake collection failure. |
| DON-06 | `POST /v1/banks/{bank}/donations/{donation}:startCollection` | C | Required `collectionStartTime` (not future). | Donation, REGISTERED → COLLECTING; eligibility must be ELIGIBLE. Collector is authenticated actor. |
| DON-07 | `POST /v1/banks/{bank}/donations/{donation}:completeCollection` | C | Required `collectionCompleteTime`, positive finite `volumeMilliliters`. | Donation, COLLECTING → COLLECTED; completion >= start, not future. Does not approve release or automatically create components. |
| DON-08 | `POST /v1/banks/{bank}/donations/{donation}:cancelCollection` | C | Required `reason`. | Donation → CANCELLED from REGISTERED/COLLECTING; retain existing times, never fabricate completion. No later collection/component creation. |
| DON-09 | `POST /v1/banks/{bank}/donations/{donation}:failCollection` | C | Required `reason`. | Donation, COLLECTING → FAILED. Retain attempt; no usable component generation. |
| DON-10 | `POST /v1/banks/{bank}/donations/{donation}:setProcessingProgress` | C | Required `processingStatus` (`IN_PROGRESS`, `PROCESSED`). | Donation; COLLECTED required; forward-only NOT_STARTED → IN_PROGRESS → PROCESSED. Does not update release. No detailed processing-operations subsystem. |
| DON-11 | `POST /v1/banks/{bank}/donations/{donation}:correctCollection` | C | Required `reason`; at least one of `collectionStartTime`, `collectionCompleteTime`, `volumeMilliliters`. Only recorded facts can be corrected. | Donation after audited correction and affected component expiry recalculation using each stored applied duration. Revalidate/release complete reservation sets atomically. Preserve original issue history and audit any resulting expiry discrepancy for issued stock; do not rewrite issue timestamps. Inconsistent dates fail with no partial changes. |

## 8. Screening and follow-up endpoints

**Module:** Screening. **Requirement:** FR-05. Parent donation and bank authorization are checked for every route. Test/release decisions are made by authorized staff; API rules do not diagnose diseases.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| LAB-01 | `GET /v1/banks/{bank}/donations/{donation}/screeningResults` | LT, LR | Pagination; filters `testCode`, `result`; order `createTime`, `recordTime`. | `{screeningResults: ScreeningResult[], nextPageToken}`. This is a restricted clinical list, not a general inventory view. |
| LAB-02 | `GET /v1/banks/{bank}/donations/{donation}/screeningResults/{result}` | LT, LR | No body/query. | ScreeningResult. |
| LAB-03 | `POST /v1/banks/{bank}/donations/{donation}/screeningResults` | LT | ScreeningResultWrite. | ScreeningResult. Proposed guard: COLLECTED donation; recorder/time are server fields. Duplicate donation/test: 409 `TEST_ALREADY_RECORDED`. |
| LAB-04 | `POST /v1/banks/{bank}/donations/{donation}/screeningResults/{result}:correct` | LT | Required new `result`, `reason`; optional `notes` nullable. | ScreeningResult. Audit original/new values; if the existing approval no longer meets required-test rules, set effective release to PENDING and invalidate reservations atomically. Reapproval/rejection still requires LR; a test correction alone is not a donor-email trigger. |
| LAB-05 | `GET /v1/banks/{bank}/donations/{donation}/screeningDecisions/current` | LT, LR | No body/query. | ScreeningDecision, or 404 `NO_FINAL_DECISION` if none exists. Donation still returns PENDING. |
| LAB-06 | `POST /v1/banks/{bank}/donations/{donation}:recordReleaseDecision` | LR | Required `status` APPROVED/REJECTED; APPROVED requires `confirmedAbo`, `confirmedRh`; REJECTED requires `reason`, `requiresDonorFollowUp` boolean. Correcting an earlier final decision additionally requires `correctionReason`. | `{donation: Donation, screeningDecision: ScreeningDecision, donorNotification: DonorNotification-or-null}`. APPROVED requires collected source, known confirmed group and complete passing required tests. Rejection excludes stock and releases affected entire reservation sets atomically. Follow-up rejection creates exactly one notification/outbox event. |
| LAB-07 | `GET /v1/banks/{bank}/donations/{donation}/donorNotifications` | LR | Pagination; filter `deliveryStatus`; order `createTime`, `queueTime`. | `{donorNotifications: DonorNotification-summary[], nextPageToken}`; omit recipient, subject/body and raw provider error. |
| LAB-08 | `GET /v1/banks/{bank}/donations/{donation}/donorNotifications/{notification}` | LR | No body/query. | Restricted DonorNotification detail including event's immutable recipient/template snapshot. |
| LAB-09 | `POST /v1/banks/{bank}/donations/{donation}/donorNotifications/{notification}:retry` | LR | No payload fields; `{}`. | DonorNotification summary, FAILED → QUEUED; worker sends asynchronously. Reject QUEUED/SENT retry. Same event/recipient/template; no arbitrary subject/body/recipient override. |

**Email behavior:** use exactly the follow-up subject/template defined in the requirements, filled with donor/bank/collection references and configured bank contact. No test values or disease labels appear in that message. Rejection, audit, outbox insertion and reservation invalidation commit together. Delivery happens **after commit** in a worker; failure never restores stock or reverses rejection. Provider errors are sanitized. Corrected laboratory results preserve existing notification records.

For a correction of an existing decision, use revision checks inside the locked service and retain history. Material confirmed-group changes invalidate incompatible reservations even when approval remains valid. A new finalized rejection is a distinct audited event; retries of the same event must not create another notification. Missing contact configuration must be detected before committing a follow-up-required decision, with a clear `BANK_CONTACT_NOT_CONFIGURED` precondition error; it is not silently replaced by fabricated contact information.

No public `sendEmail`, arbitrary notification CRUD, provider credential, or unsolicited bank-alert endpoint exists. Provider status is recorded by the worker. A webhook is not required unless the chosen provider integration later needs one.

## 9. Components and inventory endpoints

**Module:** Inventory. **Requirements:** FR-06, FR-07, FR-08, FR-19.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| INV-01 | `GET /v1/banks/{bank}/components` | A, BA, I, F | Pagination; filters `donation`, `componentType`, `confirmedAbo`, `confirmedRh`, `state`, `available`, `processTime`, `expireTime`; order `createTime`, `expireTime`, `name`. | `{components: ComponentSummary[], nextPageToken}`. Live availability classification; no donor contacts or test payloads. |
| INV-02 | `GET /v1/banks/{bank}/components/{component}` | A, BA, I, F | No body/query. | Component. I may inspect its source donation through the restricted supporting read. |
| INV-03 | `POST /v1/banks/{bank}/components` | I | ComponentCreate. | Component with calculated expiry and current effective state. Collection completed, valid processing time >= completion, supported policy required. Missing policy: `EXPIRY_POLICY_MISSING`. An already expired recorded component is retained unavailable, never made fresh. |
| INV-04 | `PATCH /v1/banks/{bank}/components/{component}` | I | `updateMask=storageLocation`; nonempty `storageLocation`. | Component, audited. No type/group/expiry/processing-attribute edit or physical processing operation is implied. Issued/disposed components cannot be relocated by this endpoint. |
| INV-05 | `GET /v1/banks/{bank}/components:summary` | A, BA, I, F | Same product/group/state/time filters as INV-01; no pagination because response is bounded configured grouping. | InventorySummary; counts apply the same live predicates and filters as list. Not a historical stock reconstruction. |
| INV-06 | `POST /v1/banks/{bank}/components/{component}:dispose` | A, I | Required `reason`. | `{component: Component, disposalRecord: DisposalRecord, affectedRequests: RequestSummary[]}`. Reject ISSUED/already DISPOSED. For reserved component, release the **entire** request set; request returns to ACCEPTED and only still-eligible other stock becomes available. Atomic and audited. |
| INV-07 | `GET /v1/banks/{bank}/components/{component}/disposalRecords` | A, I | Pagination; order `createTime`, `disposeTime`. | `{disposalRecords: DisposalRecord[], nextPageToken}`; zero or one immutable record. |

The workbook separately grants dedicated expired-stock viewing only to A/I, while granting general inventory viewing more broadly. Proposed conservative policy: BA/F can see aggregate unavailable/expired counts but not expired-container rows/detail; INV-01 excludes those rows for them and explicitly requesting `state="EXPIRED"` is denied. INV-02 applies the same restriction. Review this overlap with the team instead of silently treating general inventory access as the separate expired-stock permission.

No manual stock-balance, manual expiry, direct reserve/release, or public "run expiry job" API is needed. Reservation changes occur through confirmation/cancellation/invalidation. Scheduled expiry reconciliation is an internal task, and reads/confirmation/issue must reject expired stock without waiting for that task. Expiry reconciliation returns affected requests to ACCEPTED and releases their entire reservation sets through the same domain service.

## 10. Patients, hospital requests and lifecycle endpoints

**Module:** HospitalRequests. **Requirements:** FR-09, FR-10, FR-18; relevant query views support FR-11–FR-13. `source F` means the proposed source-bank access rule in section 1.2, never arbitrary cross-hospital access. A can obtain RequestSummary and action-specific data needed for its explicitly permitted confirmation/issue, not unrestricted patient browsing.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| PAT-01 | `GET /v1/hospitals/{hospital}/patients` | H | Pagination; `search` exact medical ID or display-name prefix; order `displayName`, `createTime`. | `{patients: patient-summary[], nextPageToken}` as section 4.4. Scope before search; not a network patient directory. |
| PAT-02 | `GET /v1/hospitals/{hospital}/patients/{patient}` | H | No body/query. | Patient. No bank-side patient editing or separate patient CRUD is added. |
| REQ-01 | `GET /v1/hospitals/{hospital}/requests` | H; A summary-only supporting read | Pagination; filters `status`, `componentType`, `recipientAbo`, `recipientRh`, `urgency`, `sourceBank`, `createTime`, `requiredBy`; order `createTime`, `requiredBy`. | `{requests: RequestSummary[], nextPageToken}`. H restricted to own hospital. |
| REQ-02 | `GET /v1/banks/{bank}/requests` | F | Pagination; same request filters as REQ-01; optional `inboxState` `ACTIONABLE` (default) or `ASSIGNED_HISTORY`. | `{requests: RequestSummary[], nextPageToken}`. ACTIONABLE includes only nonterminal requests currently selected to this bank or allocated to it. ASSIGNED_HISTORY uses retained reservation/issue associations, never exposes another bank's inbox. Names remain hospital-owned. |
| REQ-03 | `GET /v1/hospitals/{hospital}/requests/{request}` | H, source F; A action summary | No body/query. | H/source F: HospitalRequest with necessary clinical snapshot for staff review. A: RequestSummary unless separately granted clinical detail permission. No linked donor data. |
| REQ-04 | `POST /v1/hospitals/{hospital}/requests` | H | RequestCreate; authenticated hospital must match path. | HospitalRequest, status PENDING, sourceBank null; atomically reuse/register patient, save immutable submitted snapshot and initial status event. Invalid clinical/identity/product fields save **nothing**. |
| REQ-05 | `POST /v1/hospitals/{hospital}/requests/{request}:accept` | Source F | Optional `notes` nullable. | RequestSummary, PENDING → ACCEPTED plus actor/time event. Acceptance does not reserve stock or set a final source allocation. |
| REQ-06 | `POST /v1/hospitals/{hospital}/requests/{request}:reject` | Source F | Required `reason`. | RequestSummary, PENDING → REJECTED; terminal. No inference that rejection erases recorded demand. |
| REQ-07 | `POST /v1/hospitals/{hospital}/requests/{request}:cancel` | H, source F | Required `reason`. | `{request: RequestSummary, releasedReservations: Reservation[]}`. PENDING/ACCEPTED/RESERVED → CANCELLED; full reservation release atomically. Terminal request: `INVALID_REQUEST_TRANSITION`. |
| REQ-08 | `GET /v1/hospitals/{hospital}/requests/{request}/statusEvents` | H, source F; A supporting trace | Pagination; filters `toStatus`, `occurTime`; order `occurTime`. | `{statusEvents: RequestStatusEvent[], nextPageToken}`. No edit/delete endpoint. |
| REQ-09 | `GET /v1/hospitals/{hospital}/requests/{request}/reservations` | Source F, A; H permitted projection | Pagination; filters `state`, `bank`; order `createTime`, `reserveTime`. | `{reservations: Reservation[], nextPageToken}`. H sees request/component/bank/state/times, not private staff details. History remains linked to the original allocation bank after reassignment. |
| REQ-10 | `GET /v1/hospitals/{hospital}/requests/{request}/issueRecords` | H, source F, A | Pagination; order `createTime`, `issueTime`. | `{issueRecords: IssueRecord[], nextPageToken}`; H receives permitted fulfillment identifiers, not donor/test data. |
| REQ-11 | `GET /v1/hospitals/{hospital}/requests/{request}/issueRecords/{issue}` | H, source F, A | No body/query. | IssueRecord with same projection/scope as REQ-10. |
| REQ-12 | `POST /v1/hospitals/{hospital}/requests/{request}:issue` | Source F, A | Required `bank` and `reservations` (complete unique array of active Reservation resource names). | IssueResponse. RESERVED → FULFILLED only after complete atomic issue. Recheck approval, exact attributes, all active reservations, ownership and expiry; no partial issue. Mark components ISSUED and reservations ISSUED, insert issue records and status event in one transaction. |

No generic request PATCH or manual `:fulfill` action exists. If entered requirements must change, cancel the nonterminal request and submit a new one; record the relationship in audit metadata if used. The demand pipeline must avoid counting such replacements twice under its versioned repeated-request policy. Detailed request-edit/amendment workflows are not silently added.

Lifecycle guards:

| Current state | Allowed next state | Trigger |
| --- | --- | --- |
| PENDING | ACCEPTED / REJECTED / CANCELLED | Explicit accept / reject / cancel |
| ACCEPTED | RESERVED / CANCELLED | Confirm allocation / cancel |
| RESERVED | FULFILLED / CANCELLED | Complete issue / cancel |
| RESERVED | ACCEPTED | System invalidation of reservation set after rejection, correction, expiry or disposal |
| FULFILLED / REJECTED / CANCELLED | None | Immutable terminal lifecycle |

`sourceBank` is set at successful allocation. Proposed behavior on invalidation: clear the current allocation bank while preserving all historical reservation banks; a new nearest-source search may choose a different bank. The status event records the invalidation reason and original bank. No request's terminal issue history is cleared.

## 11. Exact source search and allocation endpoints

**Modules:** Recommendations, HospitalRequests and Inventory. **Requirements:** FR-08, FR-11, FR-12, FR-13.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| SRC-01 | `GET /v1/hospitals/{hospital}/requests/{request}:findSource` | H, source F; A supporting allocation | No body. Request identity supplies exact specifications; client cannot override them in query. | SourceSearchResponse. Search all active banks internally; filter full exact quantity; select nearest by distance/ID. `NO_MATCH` is **200**, not an error, with selectedBank/distance null and eligibleUnits 0. No write, reservation or persisted candidate list. |
| SRC-02 | `GET /v1/hospitals/{hospital}/requests/{request}:previewAllocation` | Source F, A | Required query `bank` (Bank resource name). | AllocationPreview. Require ACCEPTED and bank equals current nearest qualifying source. Select full quantity by component ID; show names, distance and reasons. No match/stale requested source produces `canConfirm:false`, empty selection and safe reason codes; no stock mutation. |
| SRC-03 | `POST /v1/hospitals/{hospital}/requests/{request}:confirmAllocation` | Source F, A | Required `bank`, `components` (full unique array of selected Component names). | AllocationResponse. Human confirmation is explicit. Lock request/components, recompute source/selection, validate all-or-none exact quantity and eligibility, insert active reservations, set sourceBank and RESERVED, audit and commit. Stale source/selection: 400 `SELECTION_CHANGED`, no partial changes. |

Example successful search response:

```json
{
  "request": "hospitals/hospital-1/requests/request-1",
  "outcome": "MATCH",
  "selectedBank": {
    "name": "banks/bank-1",
    "displayName": "Demonstration Bank A",
    "contactPhone": "+963-example",
    "contactEmail": "bank-a@example.org"
  },
  "distanceKilometers": 2.81,
  "eligibleUnits": 5,
  "unitsRequested": 2,
  "matchTime": "2026-10-16T10:00:00Z",
  "selectionPolicyVersion": "exact-nearest-v1",
  "explanationCodes": ["EXACT_SPECIFICATION", "FULL_QUANTITY_AT_ONE_BANK", "NEAREST_STRAIGHT_LINE"]
}
```

Example confirmation input:

```json
{
  "bank": "banks/bank-1",
  "components": [
    "banks/bank-1/components/component-1",
    "banks/bank-1/components/component-2"
  ]
}
```

Example confirmation output:

```json
{
  "request": {
    "name": "hospitals/hospital-1/requests/request-1",
    "hospital": "hospitals/hospital-1",
    "componentType": "RED_CELLS",
    "recipientAbo": "B",
    "recipientRh": "POSITIVE",
    "unitsRequested": 2,
    "specialRequirements": [],
    "urgency": "ROUTINE",
    "requiredBy": "2026-10-17T12:00:00Z",
    "status": "RESERVED",
    "sourceBank": "banks/bank-1",
    "submitTime": "2026-10-16T09:00:00Z",
    "createTime": "2026-10-16T09:00:00Z"
  },
  "reservations": [
    {
      "name": "hospitals/hospital-1/requests/request-1/reservations/reservation-1",
      "request": "hospitals/hospital-1/requests/request-1",
      "component": "banks/bank-1/components/component-1",
      "bank": "banks/bank-1",
      "confirmedBy": "users/user-5",
      "state": "ACTIVE",
      "reserveTime": "2026-10-16T10:05:00Z",
      "releaseTime": null,
      "releaseReason": null
    },
    {
      "name": "hospitals/hospital-1/requests/request-1/reservations/reservation-2",
      "request": "hospitals/hospital-1/requests/request-1",
      "component": "banks/bank-1/components/component-2",
      "bank": "banks/bank-1",
      "confirmedBy": "users/user-5",
      "state": "ACTIVE",
      "reserveTime": "2026-10-16T10:05:00Z",
      "releaseTime": null,
      "releaseReason": null
    }
  ],
  "confirmTime": "2026-10-16T10:05:00Z"
}
```

Do not expose an endpoint where a hospital directly chooses arbitrary containers, marks stock approved, or bypasses source staff confirmation. Source search does not call the AI service; it is a deterministic exact-filter/distance operation.

## 12. Traceability, audit, reports and optional presentation

### 12.1 Traceability — Must Have FR-14

**Module:** Traceability. These are read models across modules, not new copies of clinical records.

| ID | Endpoint | Access | Accepts | Returns and behavior |
| --- | --- | --- | --- | --- |
| TRACE-01 | `GET /v1/banks/{bank}/components/{component}:trace` | A, BA, F | Optional `eventPageSize` (default 25, max 100); no body. | ComponentTrace. Initial histories capped at page size, each with `nextPageToken` and its authorized list URL. A may follow donor/test links under its trace permission; BA/F receive restricted/redacted clinical/donor sections. Never grant donor editing from trace access. |
| TRACE-02 | `GET /v1/hospitals/{hospital}/requests/{request}:trace` | A, source BA/F; H fulfillment projection | Optional `eventPageSize` as above. | RequestTrace with paginated initial histories. BA limited to its bank's allocation history; H sees permitted issue/status information only. |
| TRACE-03 | `GET /v1/auditEvents` | A | Pagination; filters `subject`, `action`, `actor`, `occurTime`; order `occurTime`. | `{auditEvents: AuditEvent[], nextPageToken}`; no secrets or unapproved raw clinical payloads. |
| TRACE-04 | `GET /v1/auditEvents/{event}` | A | No body/query. | AuditEvent with allowed detail projection. History is immutable; no edit/delete. |

For component trace, use the precise response shape `{component, donation, donor, screeningDecision, screeningResults: {screeningResults, nextPageToken, listUrl}, reservations: {reservations, nextPageToken, listUrl}, issueRecords: {issueRecords, nextPageToken, listUrl}, disposalRecords: {disposalRecords, nextPageToken, listUrl}, events: {auditEvents, nextPageToken, listUrl}, redactedSections}`. Null/omitted protected sections are listed in `redactedSections`, and no forbidden list URL is provided. RequestTrace similarly wraps `reservations`, `issueRecords` and `statusEvents`. A list URL is a navigational convenience, not a capability: it must enforce the same projection/permissions as the initial trace. Where a nested history has no standalone scoped list route, its list URL is the same trace endpoint with the section's opaque `<section>PageToken` query (for example `reservationsPageToken`); accept these token parameters on TRACE-01/02. Tokens bind the original component/request and cannot expand the chain. Requests and components that were never issued have empty issue lists, not broken links.

### 12.2 Operational reports — Should Have FR-15

**Module:** Reports. Access A network/permitted bank, BA own bank. All routes are read-only. Accept `bank` (required for BA, optional for A network), `startTime`, `endTime`; start inclusive/end exclusive, start < end. Period reports require both times; current inventory reports ignore no dates silently and reject supplied date filters. Output always includes `asOfTime` and scope.

| ID | Endpoint | Additional accepted query | Returns |
| --- | --- | --- | --- |
| RPT-01 | `GET /v1/reports:donations` | Optional `collectionStatus`; common period/scope. | OperationalReport: `reportType:DONATIONS`, `totals:{registeredAttempts, collectedAttempts, failedAttempts, cancelledAttempts, collectedVolumeMilliliters}`, `groups` by bank. Count attempts by createTime; successful volume only from those cohort records actually COLLECTED as of report time. Never count failed attempts as successful donations. |
| RPT-02 | `GET /v1/reports:inventory` | Optional `componentType`, `abo`, `rh`; bank scope only, **no period dates**. | OperationalReport: `reportType:INVENTORY`, totals with the disjoint component counts from InventorySummary; groups by bank/type/group, current asOfTime only. |
| RPT-03 | `GET /v1/reports:requests` | Optional `status`, `componentType`; common period/scope. | OperationalReport: `reportType:REQUESTS`, `totals:{submittedRequests, requestedUnits, pendingRequests, acceptedRequests, reservedRequests, fulfilledRequests, rejectedRequests, cancelledRequests}`, groups by status. Cohort by submitTime, current outcomes as of asOfTime. Bank scope uses source/history attribution, excludes unrelated/unassigned hospital requests. |
| RPT-04 | `GET /v1/reports:expiryAndDisposal` | Optional `componentType`; common period/scope. | OperationalReport: `reportType:EXPIRY_AND_DISPOSAL`, `totals:{expiredComponents, disposedComponents}`, groups by bank/type. Expired: expireTime within interval and not issued/disposed before expiry, using retained history. Disposal: disposeTime within interval. These two event counts may overlap and must not be summed as distinct containers. |

Each report's `groups` is an array of `{dimensions, totals}`. `dimensions` contains exactly its advertised grouping fields: `bank` for donations, `bank/componentType/abo/rh` for inventory, `status` for requests, and `bank/componentType` for expiry/disposal. `totals` uses the same keys as that report's top-level totals. Inventory keys are `totalUnits,availableUnits,pendingUnits,reservedUnits,issuedUnits,rejectedUnits,expiredUnits,disposedUnits`. Current-inventory reports return `startTime:null,endTime:null`; period reports echo their validated bounds. Empty reports return zeros, `groups:[]`, `hasData:false`. All aggregation/projection rules must be tested against a known seeded dataset. A period report is not a reconstruction of historical stock unless such a feature is separately designed.

### 12.3 Dashboard and CSV — Could Have FR-20

**Module:** Reports, delegating to permitted read services. A/BA can use bank/permitted network views; H can use only own-hospital request views. No new permission is obtained by using an export URL.

| ID | Endpoint | Accepts | Returns |
| --- | --- | --- | --- |
| VIEW-01 | `GET /v1/dashboards/overview` | `bank` for A/BA, or `hospital` for H; A can omit organization for network. Optional request `startTime`, `endTime`; inventory remains explicitly current. | DashboardResponse; counts/series needed by React charts, not HTML/chart images. Forecast summaries only for A/BA; never generate a forecast as a side effect. |
| CSV-01 | `GET /v1/banks/{bank}/components:export` | Same allowed product/group/state/time filters as INV-01; no pageToken; fixed safe column set. A/BA only, consistent with workbook export grants. | `text/csv; charset=utf-8`, `Content-Disposition: attachment`; permitted matching component rows with `name,componentType,confirmedAbo,confirmedRh,state,expireTime,storageLocation`. No donor/test/patient fields. |
| CSV-02 | `GET /v1/hospitals/{hospital}/requests:export` | Same allowed request filters as REQ-01; H own hospital or A permitted summary export. | CSV columns `name,componentType,recipientAbo,recipientRh,unitsRequested,urgency,requiredBy,status,sourceBank`. No patient/clinical fields. |
| CSV-03 | `GET /v1/banks/{bank}/forecastRuns/{run}:export` | No body/query; A/BA own permitted run. | CSV columns `targetDate,predictedUnits,componentType,abo,rh,dataSource,modelVersion`; include simulated provenance on every row. |

CSV uses the same authorized filter semantics as its source query, escapes quotes/newlines and neutralizes spreadsheet formula injection from user text. Proposed synchronous cap: 10,000 rows; if exceeded, return a JSON precondition error before streaming and require narrower filters, never silently truncate. Zero matches returns headers only. CSV endpoints return JSON error envelopes on non-2xx; clients check status/content type before treating a response as a file.

## 13. Demand forecasting endpoints

**Module:** Forecasting. **Requirement:** FR-16. Access A for permitted banks; BA **own bank only**. Forecast points inherit the run's bank. No hospital, collection, laboratory or fulfillment role gains forecast access merely because it can read requests.

| ID | Endpoint | Accepts | Returns and behavior |
| --- | --- | --- | --- |
| FC-01 | `POST /v1/banks/{bank}/forecastRuns` | ForecastCreate. Server builds authorized aggregated demand history and determines model/cutoff/attribution policy. | ForecastRun including seven points; successful validated run/points stored atomically. Missing/insufficient history: 400 `FAILED_PRECONDITION`, reason `INSUFFICIENT_DEMAND_HISTORY`; disabled simulation: 403; model timeout/unreachable: 503 `FORECAST_SERVICE_UNAVAILABLE`; invalid output: 503 `INVALID_FORECAST_OUTPUT`. No fabricated zero predictions or successful run saved on failure. |
| FC-02 | `GET /v1/banks/{bank}/forecastRuns` | Pagination; filters `componentType`, `abo`, `rh`, `dataSource`, `generateTime`; order `createTime`, `generateTime`. | `{forecastRuns: ForecastRunSummary[], nextPageToken}`. Metadata clearly identifies simulation. |
| FC-03 | `GET /v1/banks/{bank}/forecastRuns/{run}` | No body/query. | ForecastRun, including bounded seven-point basic display data. |
| FC-04 | `GET /v1/banks/{bank}/forecastRuns/{run}/forecastPoints` | Pagination; filters `targetDate`; order `targetDate`. | `{forecastPoints: ForecastPoint[], nextPageToken}`. Same values as run detail; no recalculation. |

**Initial transport choice:** bounded synchronous generation with a configurable timeout. Save no successful forecast on timeouts/invalid responses; the idempotency mechanism prevents duplicated persisted runs. If measured execution requires long-running work, revise this endpoint to return an Operation resource and define polling before implementation; do not return an undocumented mixture of ForecastRun and queued job shapes.

### 13.1 Data preparation and attribution contract

Before training, agree the section 1.2 target reconciliation and record the following as versioned policy metadata:

1. Series key: bank + component type + exact ABO + Rh; daily aggregation in a documented timezone (proposed UTC). Do not train on donation volume as a proxy for requested demand.
2. Observation date: request submit date. Count original requested units once, including unmet requests and subsequently fulfilled requests; record cancellation, rejection and replacement/deduplication policy. Proposed defaults: exclude CANCELLED; include REJECTED unmet demand unless audited as invalid/duplicate; exclude confirmed replacements/duplicates. This requires an explicit replacement link/audit convention and review before data preparation.
3. Attribution at the recorded cutoff: assigned requests contribute to their source bank. Unassigned demand is attributed to every active bank within configured radius of the requesting hospital, following ERD notes; out-of-range demand remains unassigned for network reporting. This intentionally can duplicate demand across bank series: **never sum bank forecasts as unique network demand**.
4. Historical backtests must reconstruct attribution/status **as of each historical cutoff**, not use future allocation/fulfillment/cancellation information. Retain the extraction policy/version and input cutoff for reproducibility.
5. Persist configured radius, attribution version, target version, source, training period and model version. Radius/model paths are trusted server settings, not frontend switches.
6. No-history is not zero demand. Fill internal gaps with zero only where the dataset's coverage supports an observed zero. Baraa defines minimum history and the saved model's supported series; Laravel validates the service's contract.
7. Validate seven distinct consecutive future dates, correct series/horizon, finite nonnegative predictions, recognized model/provenance and request correlation before saving. Never use a silent simulation fallback when real data is unavailable.

Baseline comparison, chronological holdout, MAE and leakage checks are AI test artifacts. They do not add evaluation/risk-analysis endpoint requirements or imply measured accuracy before testing.

### 13.2 Private Python service interface — proposed, not a public Laravel route

React calls Laravel only. Python receives aggregated non-identifying series, not patient/donor profiles or unrestricted database credentials. Baraa owns the model/service; application developers own Laravel's client, validation and persistence.

| ID | Private endpoint | Accepts | Returns |
| --- | --- | --- | --- |
| AI-01 | `POST /internal/v1/forecasts:predict` | Service credential in header, request correlation ID; body shown below. No end-user tokens. | PredictionResponse shown below. Private network access plus service authentication, timeout and response-size limits. |
| AI-02 | `GET /internal/health` | Service/network-level access, no clinical body. | `{status:"READY"}` or 503 `{status:"UNAVAILABLE"}`; no filesystem paths/credentials. This checks model service readiness, unlike Laravel `/up`. |

Internal request:

```json
{
  "requestId": "7dc7be8f-d7ac-4f9e-823d-fb3712788a4c",
  "series": {"bank": "banks/bank-1", "componentType": "RED_CELLS", "abo": "B", "rh": "POSITIVE"},
  "dataSource": "HISTORICAL",
  "targetPolicyVersion": "requested-units-v1",
  "attributionPolicyVersion": "radius-v1",
  "inputCutoffTime": "2026-10-16T00:00:00Z",
  "granularity": "DAILY",
  "horizonDays": 7,
  "observations": [
    {"date": "2026-10-14", "requestedUnits": 5},
    {"date": "2026-10-15", "requestedUnits": 7}
  ]
}
```

The two observations only illustrate structure, not sufficient training history. `observations` must be sorted, date-unique, nonnegative and earlier than cutoff. Define maximum history length with the selected model. Service identity is trusted but its output is still validated.

Internal success response (`PredictionResponse`):

```json
{
  "requestId": "7dc7be8f-d7ac-4f9e-823d-fb3712788a4c",
  "series": {"bank": "banks/bank-1", "componentType": "RED_CELLS", "abo": "B", "rh": "POSITIVE"},
  "modelVersion": "demand-demo-v1",
  "dataSource": "HISTORICAL",
  "points": [
    {"targetDate": "2026-10-16", "predictedUnits": 6.2},
    {"targetDate": "2026-10-17", "predictedUnits": 5.8},
    {"targetDate": "2026-10-18", "predictedUnits": 6.1},
    {"targetDate": "2026-10-19", "predictedUnits": 7.0},
    {"targetDate": "2026-10-20", "predictedUnits": 5.4},
    {"targetDate": "2026-10-21", "predictedUnits": 6.0},
    {"targetDate": "2026-10-22", "predictedUnits": 6.7}
  ]
}
```

Python errors use the same error envelope for this proposed private interface, with reasons `INSUFFICIENT_HISTORY`, `UNSUPPORTED_SERIES`, `MODEL_NOT_READY` or `INVALID_INPUT`. Laravel maps them to its public error contract and never forwards stack traces/internal credentials. This interface is a proposal for integration, not proof of a deployed Python route.

## 14. Transaction, history and concurrency requirements

These rules are part of the endpoint contracts, not optional frontend checks:

| Operation | One atomic application transaction must cover |
| --- | --- |
| Request creation | Patient registration/link validation, clinical/identity snapshot, request insert and initial status event. |
| Allocation confirmation | Lock request and deterministic component selection; validate current nearest source and full quantity; insert reservations with unique active component constraint; component/request state, source bank and audit/status events. |
| Cancellation | Lock request/reservation set; release all active reservations; restore only eligible components; request state and event. |
| Screening decision/correction | Current decision/result changes, effective component eligibility, release of every affected request's entire reservation set, status/audit events and notification outbox insertion when required. |
| Issue | Lock request, reservations and components; recheck approval/specification/expiry/source/full quantity; unique issue records; issued component/reservation states; FULFILLED event. |
| Disposal / expiry invalidation | Component/disposal change, whole-set reservation invalidation and request recovery, audit/status events. |
| Collection-time correction | Correct source timestamps, recalculate affected stored expiries with their original durations, revalidate reservations, preserve issue history and record old/new values. |
| Forecast persistence | Validated run/provenance and exactly seven unique points. Network model invocation is outside a long-held stock transaction. |

Use a consistent lock order across services, database uniqueness for active reservation/issue/disposal exclusions, and bounded deadlock retries. A retry must not duplicate audit events, issue records or notifications. Services inside Laravel call each other directly, not through localhost HTTP. Neither database transactions nor authorization are delegated to React or Python.

Queries never send email, generate forecasts, confirm allocations or mark requests fulfilled. Domain predicates enforce current expiry during reads/writes; background reconciliation persists expiry-related recovery separately. If issue discovers expired/invalid reserved stock, reject issuing and use the shared invalidation service to recover the complete reservation set; do not partially issue valid leftovers. In that failure branch, commit only the audited invalidation/recovery transaction before returning the precondition error, with no issue records; do not accidentally roll recovery back by throwing inside its transaction. Unexpected database failures roll everything back and leave reconciliation to a safe retry/background pass.

## 15. Requirement-to-endpoint coverage

| Requirement | Priority | Main endpoints / supporting behavior |
| --- | --- | --- |
| FR-01 Organizations | Must | ORG-01–ORG-10; ownership rules on all bank/hospital records. |
| FR-02 Authentication/roles | Must | AUTH-01–AUTH-06, AUTH-09 limited assignment; policies on every protected route. |
| FR-03 Donors | Must | DONOR-01–DONOR-04; DON-01 donor-filtered history. |
| FR-04 Donation/eligibility | Must | DON-01–DON-11. |
| FR-05 Screening/follow-up | Must | LAB-01–LAB-09; worker/outbox, no arbitrary email endpoint. |
| FR-06 Prepared components/expiry | Must | INV-03; catalog policy lookup and calculated fields. |
| FR-07 Inventory | Must | INV-01, INV-02, INV-04, INV-05. |
| FR-08 Reservation controls | Must | SRC-03; REQ-07/REQ-09; shared invalidation service. No duplicate reserve/release endpoint. |
| FR-09 Complete request | Must | PAT-01/PAT-02, REQ-04 and detailed validation/snapshot. |
| FR-10 Request lifecycle | Must | REQ-01–REQ-08 plus transitions triggered by reservation/issue. |
| FR-11 Exact search | Must | SRC-01; exact predicate shared with confirmation and issue. |
| FR-12 Single nearest source | Must | SRC-01/SRC-02; coordinates and stable tie-breaker. |
| FR-13 Staff confirmation | Must | SRC-02/SRC-03. |
| FR-14 Traceability | Must | TRACE-01–TRACE-04, status/reservation/issue lists and immutable auditing. |
| FR-15 Operational reports | Should | RPT-01–RPT-04. |
| FR-16 Demand forecasts | Must | FC-01–FC-04, AI-01/AI-02 private integration and offline model evidence. |
| FR-17 Staff accounts | Should | AUTH-07–AUTH-10; BA-only limited role change also supports Must FR-02. Seed accounts are the screen-deferral workaround. |
| FR-18 Issue/fulfillment | Must | REQ-10–REQ-12. |
| FR-19 Expiry/disposal | Must | INV-01 expired filter, INV-06/INV-07; issue guards and internal expiry reconciliation. |
| FR-20 Dashboard/export | Could | VIEW-01, CSV-01–CSV-03. |

## 16. Before implementation: review and verification checklist

- Approve the proposed specialist role mappings and bank inbox/acceptance access rule; do not grant broad clinical access through a list, trace or export shortcut.
- Approve token vs cookie authentication and align the login sequence, Laravel route registration and `/v1` JSON exception handling. The inspected bootstrap currently recognizes `api/*` for forced JSON, so `/v1/*` must be handled explicitly during implementation.
- Reconcile the donor Bank relationship, authoritative release decision, patient fields, idempotency persistence and forecast policy metadata in the master ERD/migrations.
- Agree the product/test/processing catalogs, normalized result interpretation, conditional clinical rules, units and demonstration shelf-life configuration. This is an academic prototype, not a clinically validated transfusion system.
- Resolve forecast target vs ERD unfulfilled-demand wording, repeated-request attribution and historical cutoff reconstruction with Baraa before model development. Do not invent an accuracy figure or claim a baseline has been beaten.
- Translate this reviewed contract into OpenAPI before route implementation, as required by API_CONVENTIONS.md. Use the schemas here for request validation, typed frontend integration and test fixtures.
- Test normal and invalid paths: inactive user/org, wrong role/bank/hospital, unknown group, deferral/failed collection, missing policy, pending tests, rejection/email failure/retry, complete clinical request validation, NO_MATCH, stable tie, concurrent confirmation, stale preview, expiry at issue, whole-set invalidation, full issue, terminal-state rejection, simulated provenance and failed forecast generation.
- Check each route's method/path, input schema, output projection, error reasons and FR mapping when adding it. Documentation of an endpoint is not evidence that it exists or passes tests.
