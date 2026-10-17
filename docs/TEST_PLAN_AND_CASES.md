# BloodBankPlatform test plan and requirement-linked cases

**Baseline:** `docs/BloodBank_Requirements.docx` (FR-01–FR-20 and their ACs, revised 15 October 2026). **Plan date:** 17 October 2026. **Status:** proposed cases unless an existing test file is named below. This plan is for the Graduation 1 scope; it does not introduce a new clinical rule or claim a test passed.

## 1. What we will test

The reference graduation report lists proposed tests by function, shows a Google Lighthouse result, and connects test identifiers to a traceability matrix. We use that structure, but our cases follow **our** requirements and architecture. A donor deletion, physical bag deletion, compatibility substitution, delivery confirmation, or automatic clinical decision from the reference is not a BloodBankPlatform requirement.

Use four test levels for each requirement:

| Level | Purpose | Where and how | Evidence |
|---|---|---|---|
| Component (`CT`) | Check a small rule or React component in isolation. | PHPUnit unit test for pure PHP rules; React Testing Library with Vitest for rendered components; existing Node tests may remain for pure JS helpers. | Test name, assertion, runner output. |
| Integration (`IT`) | Check Laravel routes, policies, validation, database transactions, queues, and module boundaries together. | Laravel PHPUnit feature tests with SQLite in memory, model factories, `RefreshDatabase`, and `Mail::fake()`/`Queue::fake()` when the interaction itself is the subject. | Request and response assertions plus persisted state. |
| System (`ST`) | Check the running React UI, Laravel API, and database as one user workflow. | Playwright browser tests against a disposable, seeded test environment. Use real HTTP and a test email sink. | Browser trace, screenshots on failure, API/database verification. |
| Acceptance (`AT`) | Demonstrate each approved AC to a product/domain reviewer. | Scripted role-based walkthrough on a reset demo environment using known data and expected values. Reviewer records pass/fail and evidence. | Signed result, build/version, data set, defects. |

Also run cross-cutting security, accessibility, performance, contract, concurrency, recovery, and forecast evaluation checks (§4). Lighthouse is useful for frontend accessibility and performance screening, but it is not proof that transactions, authorization, clinical data, or every requirement work.

## 2. How to execute each level

### Component tests

1. Identify one rule from an AC (for example, exact ABO/Rh matching in AC-11.1 or expiry at the current time in AC-19.1). Test valid, invalid, boundary, and missing-input cases. Keep the expected result independent of implementation internals.
2. For PHP, put pure-rule tests under the owning `backend/Modules/<Module>/tests/Unit/` directory. Use PHPUnit (`cd backend && php artisan test --compact Modules/<Module>/tests/Unit/<File>Test.php`). Mock only external collaborators; a component test should not need a database or network.
3. For rendered React behavior, add Vitest, `@testing-library/react`, `@testing-library/user-event`, and `jsdom` when those tests are implemented. Render the component, interact using accessible labels/roles, and assert visible text and enabled/disabled state. Run with `cd frontend && npm test` after the script is expanded to include these tests. The current `npm test` runs only Node tests for `src/lib/*.test.js`; it does **not** render React.
4. Keep `frontend/src/lib/access.test.js` for pure navigation-rule checks. A hidden menu is a UI cue, not authorization evidence; the Laravel policy test must also deny the operation.

### Laravel integration tests

1. Create the minimum bank, hospital, user, donor, request, and component data with factories. Name the role and ownership explicitly. Use `RefreshDatabase`; `backend/phpunit.xml` already configures SQLite `:memory:`, `MAIL_MAILER=array`, and synchronous queues.
2. Call the real JSON route with an authenticated actor. Assert status and the documented error code or response fields, then assert database rows and audit/status events. Repeat with unauthenticated, wrong-role, and wrong-organization actors.
3. For state changes, test an allowed transition, an illegal transition, and a repeated request. After a rejected operation, assert that all related records are unchanged. For reservation and issue, use a database engine supporting concurrent transactions in a separate CI job; SQLite in-memory tests alone cannot prove locking behavior.
4. For notification tests, fake the mail/queue to assert one enqueue and the persisted delivery state. Use a separate worker integration test with a disposable mail sink to prove delivery processing and retry behavior. Never send to a real donor address.
5. Run the focused test (`cd backend && php artisan test --compact Modules/<Module>/tests/Feature/<File>Test.php`), then the full backend suite before release (`php artisan test --compact`). Record the exact command, commit, environment, result, and report path.

### React and whole-system tests

1. For React forms and role views, use Testing Library to verify field labels, validation messages, loading/empty/error states, and navigation. Mock HTTP at the component boundary only; use Playwright for real API behavior.
2. In a disposable environment, migrate and seed **synthetic** data: at least two banks, two hospitals, staff for each role, eligible and ineligible components, near-expiry components, and requests with known quantities. Start Laravel API, queue worker, frontend, and a test email sink. Reset this environment between runs.
3. In Playwright, log in as the required role, perform the workflow through the browser, assert the visible state, and verify the resulting API/database record. Repeat a permission-denied path and a failure/retry path. Capture a trace and screenshot on failure. A case stays `Blocked` if its screen or endpoint is not implemented.
4. Run browser checks in Chromium and one additional engine where the team supports it. Check keyboard-only use, labels, focus, error announcements, and responsive layout. Run Lighthouse on representative pages, recording URL, build, device profile, and scores as supporting evidence only.

### Acceptance tests

1. Convert each SRS `AC-xx.y` into a checklist item. The case catalogue below gives the linked `AT` case per FR; that case must enumerate **all** its ACs in the execution record.
2. Prepare a deterministic demo data sheet with expected identifiers, amounts, states, and distances. A domain reviewer checks any clinical catalog, shelf-life policy, and forecast target assumptions before sign-off. Do not substitute invented clinical thresholds.
3. Demonstrate the normal path and the key refusal path as the relevant staff role. Show final persisted records and audit history, not just a success message. Record observed result, screenshot/trace, defect ID, reviewer, date, and pass/fail/blocked outcome.
4. Sign off only when every Must Have AC passes, no critical/high defect remains, and the reviewer accepts the demonstrated behavior. Should/Could items are reported separately. An AC in the SRS is a criterion, not an executed acceptance test.

**Case record template:** `Test ID | FR ID | AC IDs | Level | Priority | Preconditions/data | Actor | Steps | Expected result | Automation path | Run/build/date | Actual result | Evidence | Defect | Status | Reviewer`. Allowed statuses: `Planned`, `Blocked`, `Passed`, `Failed`, `Skipped` (with reason). Keep test definitions and run results distinct; never infer a pass from a file existing.

## 3. Requirement-linked test catalogue

Each row is a compact test specification. `CT`, `IT`, `ST`, and `AT` suffixes give stable IDs for the RTM. The `AT` case covers **every** AC in the named FR; split the checklist into individual AC lines when executing. All cases below are planned. Existing files are listed in §5.

| FR / AC scope | Component test (`CT`) | Integration test (`IT`) | System test (`ST`) | Acceptance test (`AT`) |
|---|---|---|---|---|
| FR-01 / AC-01.1–3 | `TC-01-CT`: organization ownership and active-state rules. | `TC-01-IT`: admin CRUD; wrong organization denied; record bank/hospital FKs persist. | `TC-01-ST`: admin manages two banks/two hospitals and staff see allowed records. | `TC-01-AT`: reviewer verifies organization list, identity, ownership and deactivation. |
| FR-02 / AC-02.1–4 | `TC-02-CT`: role-to-navigation and permission mapping, unknown role fails closed. | `TC-02-IT`: login/logout, hashed password, inactive user, expired token, route policy and scope denial. | `TC-02-ST`: log in as each role, confirm allowed view and server rejection of forbidden URL/action. | `TC-02-AT`: reviewer checks access matrix and session behavior. |
| FR-03 / AC-03.1–3 | `TC-03-CT`: donor identity/email validation and unknown group representation. | `TC-03-IT`: create/update/history; duplicate ID rejection; cross-bank read denied. | `TC-03-ST`: register donor and find all linked donation attempts in history. | `TC-03-AT`: reviewer checks profile, verification state and bank scope. |
| FR-04 / AC-04.1–5 | `TC-04-CT`: eligibility, collection timestamps and independent progress transitions. | `TC-04-IT`: register/assess/collect; deferred donor blocked; failed collection produces no components; audit retained. | `TC-04-ST`: staff record eligible and failed attempts; verify different outcomes. | `TC-04-AT`: reviewer checks original donation ID, collection and processing states. |
| FR-05 / AC-05.1–4 | `TC-05-CT`: required-test completion and duplicate rejection-notification key. | `TC-05-IT`: pending/rejected results block release; correction audit; one queued email; failure/retry retains rejection. | `TC-05-ST`: laboratory rejects donation, reservation invalidates, mail sink receives one follow-up. | `TC-05-AT`: reviewer checks decisions, history and delivery-status meaning. |
| FR-06 / AC-06.1–5 | `TC-06-CT`: expiry calculation from collection completion, policy version and missing configuration. | `TC-06-IT`: create one physical component; incomplete collection and absent shelf life rejected; old expiry unchanged after policy edit. | `TC-06-ST`: processing screen shows applied expiry and blocked invalid creation. | `TC-06-AT`: reviewer checks calculation against approved demo policy. |
| FR-07 / AC-07.1–3 | `TC-07-CT`: availability predicate and one-container-one-unit totals. | `TC-07-IT`: bank/type/group/status filters; excluded states absent from available count. | `TC-07-ST`: inventory filters and detail match seeded components. | `TC-07-AT`: reviewer reconciles list and available total. |
| FR-08 / AC-08.1–3 | `TC-08-CT`: reservation eligibility and full-quantity selection. | `TC-08-IT`: atomic reserve/cancel/invalidate; repeat and competing requests never share a component. | `TC-08-ST`: two requests compete; one obtains all units, other sees clear refusal. | `TC-08-AT`: reviewer checks no partial stock loss and release after cancel. |
| FR-09 / AC-09.1–5 | `TC-09-CT`: conditional patient fields, positive units, date and unknown/not-applicable rules. | `TC-09-IT`: full valid payload persists snapshots; invalid/missing values rejected; hospital derived from actor. | `TC-09-ST`: hospital creates request, sees Pending, then corrects form errors. | `TC-09-AT`: reviewer checks required clinical/administrative fields and unit meaning. |
| FR-10 / AC-10.1–3 | `TC-10-CT`: request transition table and terminal-state protection. | `TC-10-IT`: accept/reject/cancel/reserve/fulfill; invalidation returns Accepted; status events stored. | `TC-10-ST`: hospital and bank see consistent request history after each step. | `TC-10-AT`: reviewer walks allowed and forbidden transitions. |
| FR-11 / AC-11.1–4 | `TC-11-CT`: exact type, ABO, Rh and required attributes; no substitutions. | `TC-11-IT`: filter unavailable units, require whole quantity at one bank, no candidate list exposed. | `TC-11-ST`: search shows match/no match without changing inventory. | `TC-11-AT`: reviewer checks exact matching against known inventory. |
| FR-12 / AC-12.1–4 | `TC-12-CT`: Haversine distance, units, tie-break, invalid coordinates. | `TC-12-IT`: only nearest qualifying bank returned; missing coordinates block search publication. | `TC-12-ST`: same inputs repeatedly show one bank/distance; no-match state is clear. | `TC-12-AT`: reviewer checks known-distance fixture and stable tie. |
| FR-13 / AC-13.1–3 | `TC-13-CT`: confirmation recheck of source, quantity and eligibility. | `TC-13-IT`: authorized source confirms; stale or changed stock rejects without partial reservation. | `TC-13-ST`: staff review identifiers/distance then confirm; stale case displays refusal. | `TC-13-AT`: reviewer checks approval boundary and atomic result. |
| FR-14 / AC-14.1–3 | `TC-14-CT`: event redaction and trace-link formatting. | `TC-14-IT`: component-to-donor/request and request-to-components trace; correction/cancellation events immutable and scoped. | `TC-14-ST`: authorized roles trace both directions; restricted role sees redacted details. | `TC-14-AT`: reviewer audits a seeded component's complete chain. |
| FR-15 / AC-15.1–3 | `TC-15-CT`: report aggregation and zero-result formatting. | `TC-15-IT`: known fixture totals, date/bank filters, denied scope and read-only query. | `TC-15-ST`: report UI totals reconcile to seeded records; empty state visible. | `TC-15-AT`: reviewer validates totals against manual fixture sheet. |
| FR-16 / AC-16.1–5 | `TC-16-CT`: demand-series extraction, nonnegative output and horizon validation. | `TC-16-IT`: job/request permissions, versioned stored points, timeout/failure, no future-data leakage. | `TC-16-ST`: authorized bank views run and points; other bank denied. | `TC-16-AT`: reviewer approves target definition and checks backtest/baseline evidence. |
| FR-17 / AC-17.1–4 | `TC-17-CT`: valid role/organization combinations and bank-admin allowed-role list. | `TC-17-IT`: admin create/edit/deactivate; bank-admin same-bank limited role change; token invalidation and audit. | `TC-17-ST`: staff-management UI demonstrates allowed and denied edits. | `TC-17-AT`: reviewer checks account lifecycle and assignment restrictions. |
| FR-18 / AC-18.1–4 | `TC-18-CT`: issue eligibility and complete reserved-quantity rule. | `TC-18-IT`: issue transaction updates request/components/reservations together; duplicate/wrong-source/expired issue rejected. | `TC-18-ST`: bank issues units; fulfilled request traces issued containers; search excludes them. | `TC-18-AT`: reviewer verifies Issued means source-bank issue only. |
| FR-19 / AC-19.1–3 | `TC-19-CT`: expires-at-now boundary and disposal transition. | `TC-19-IT`: expired stock excluded before job; disposal audit; active reservation invalidated atomically. | `TC-19-ST`: expiry/disposal changes stock and request state; repeat disposal refused. | `TC-19-AT`: reviewer checks time boundary and disposal evidence. |
| FR-20 / AC-20.1–3 | `TC-20-CT`: dashboard count and CSV cell escaping rules. | `TC-20-IT`: figures reconcile; filtered CSV columns, scope, escaping and empty export. | `TC-20-ST`: dashboard/export display selected filters and downloadable safe data. | `TC-20-AT`: reviewer compares dashboard, lists, forecast and CSV. |

## 4. Additional checks and release gates

| ID | Check | Exact method and pass rule |
|---|---|
| `TC-X-SEC` | Authorization and sensitive data | For each protected endpoint, test unauthenticated, wrong role, wrong bank/hospital, and inactive token. Assert 401/403 as documented, no unauthorized record changes, no donor/patient fields in restricted traces or CSV. Use synthetic data. |
| `TC-X-CON` | Concurrency | On the production database engine in isolated CI, send simultaneous confirmations/issues for the same components. Assert one success, one conflict, one active reservation or issue per component, and unchanged records on the losing transaction. |
| `TC-X-API` | Contract | Compare implemented routes and JSON error shapes to `docs/openapi.yaml` and `docs/BACKEND_API_REFERENCE.md`. Run schema/response checks for implemented endpoints; update the OpenAPI definition when an approved contract changes. |
| `TC-X-ACC` | Accessibility | Keyboard-only navigation and screen-reader-oriented label/focus/error checks in Playwright/Testing Library. Run axe on major screens and manual review of critical workflows; record issues by WCAG criterion. |
| `TC-X-PERF` | Performance | Seed realistic documented record volumes, measure p95 API latency, query count and frontend Lighthouse results on a fixed machine/build. Agree numeric targets before declaring pass; investigate N+1 queries and slow inventory search. |
| `TC-X-REC` | Failure/recovery | Kill/retry the email worker and forecast service, simulate transaction failure, and rerun idempotent requests. Assert no partial issue/reservation, one notification event per rejection, and visible actionable errors. |
| `TC-X-AI` | Forecast evaluation | Freeze a historical cutoff, train only on data available then, use chronological holdout, compare MAE to a simple baseline, test empty/sparse series and nonnegative outputs. Record model/data/policy versions. Agree acceptable accuracy with project reviewer before sign-off. |

**Release gate:** all Must Have ACs executed and passed; component, integration, and system suites green for implemented features; security and concurrency gates passed; no unresolved critical/high defect; acceptance sign-off recorded. Report coverage as `ACs passed / ACs applicable` and separately `automated cases passed / automated cases run`. Never convert `Planned` or `Blocked` to `Passed` from a design document.

## 5. Current repository evidence and next implementation order

Existing Laravel feature files: `backend/Modules/Identity/tests/Feature/AuthenticationTest.php`, `backend/Modules/Identity/tests/Feature/UserManagementTest.php`, `backend/Modules/Organizations/tests/Feature/OrganizationRecordsTest.php`. Existing frontend pure-JS test: `frontend/src/lib/access.test.js`. These support parts of FR-01, FR-02 and FR-17. Their presence is not execution evidence and does not cover the planned `TC` cases in full. The frontend currently has Node's built-in test runner, Vite and React; Testing Library, Vitest and Playwright are proposals for later implementation. No claim is made that they are installed.

Implement in requirement order with risk-first checks: (1) complete FR-01/02/17 access and ownership tests; (2) donor, donation, screening, component and inventory tests; (3) request, search, nearest source, reservation, issue and expiry tests with concurrency; (4) trace/report/forecast/export tests; (5) whole-system journeys and reviewer acceptance. For each feature: update documentation, implement, write focused tests, run the relevant suite, and add execution evidence to the RTM.

## 6. RTM link and update rule

The companion English RTM workbook adds a `Planned Test IDs` column to the existing RTM and a `Test Catalogue` sheet with the stable IDs above. The existing `Component Test`, `Integration Test`, `System Test`, and `Acceptance Test` columns remain **evidence** columns: enter an actual file/run or signed acceptance record only when it exists. The `Planned Test IDs` column is coverage intent, not proof. A change to an FR/AC requires updating its case definition and IDs before implementation, then recording the new run result and any defect or change request.
