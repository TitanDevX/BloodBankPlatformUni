# FR-01 and FR-02 Use Case Specifications

These specifications follow BloodBank_Requirements.docx (15 October 2026) and BloodBank_UML_Diagram_Guide_Revised.docx (17 October 2026). The revised guide supplies scenario boundaries, different-user guards, audit, concurrency, cancellation, and API details. These refinements are not presented as verbatim additions to the original FR acceptance criteria. Business organization routes and limited bank-admin assignment describe required design, not completed implementation.

## UC-01.1 Create Bank

| Field | Specification |
|---|---|
| Related requirement | FR-01 |
| Primary actor | Administrator |
| Goal | Register one blood bank in the simulated network. |
| Preconditions | An active administrator is signed in. This is a new bank creation. |
| Trigger | The administrator chooses Create Bank and submits the new-bank form. |
| Inputs | Bank name, contact, location, active status and applicable validated coordinates. |
| Main success scenario | 1. Enter bank details. 2. Authenticate and authorize the administrator. 3. Validate required fields, contact and active-coordinate constraints. 4. Handle the creation idempotency key. 5. Save one bank and its creation audit in a short transaction. 6. Return its identifier and details. |
| Alternative and exception flows | Cancel entry: send no creation request. Invalid fields/coordinates: show errors and allow correction without saving. Unauthorized actor: deny access. Identical completed replay: return the original bank without creating another. Failed persistence: no partial creation. |
| Postconditions | One valid bank and its creation audit exist, or no new record is created. Linked donors, donations and requests are not created implicitly. |
| Rules and source distinctions | Active searchable banks require valid coordinates. Idempotency/audit details follow the revised guide and proposed organization API. |
| Traceability | AC-01.3 creation; SQ-01A; AD-01A (Essential). AC-01.1 is verified by seed/test evidence. |

## UC-01.2 Create Hospital

| Field | Specification |
|---|---|
| Related requirement | FR-01 |
| Primary actor | Administrator |
| Goal | Register one hospital in the simulated network. |
| Preconditions | An active administrator is signed in. This is a new hospital creation. |
| Trigger | The administrator chooses Create Hospital and submits the new-hospital form. |
| Inputs | Hospital name, contact, location, active status and applicable validated destination coordinates. |
| Main success scenario | 1. Enter hospital details. 2. Authenticate and authorize the administrator. 3. Validate required hospital fields, contact and destination coordinates. 4. Handle the creation idempotency key. 5. Save one hospital and its audit in a short transaction. 6. Return its identifier and details. |
| Alternative and exception flows | Cancel: no request and no change. Invalid fields/coordinates: return to correction without saving. Unauthorized actor: deny creation. Identical completed replay: return the original hospital. Failed persistence: no partial creation. |
| Postconditions | One valid hospital and its audit exist, or creation ends without a new record. |
| Rules and source distinctions | Invalid coordinates cannot publish an active request destination. Creating a hospital does not create requests or patients. |
| Traceability | AC-01.3 creation; SQ-01B; AD-01D (Essential). AC-01.1 is verified by seed/test evidence. |

## UC-01.3 Edit Organization

| Field | Specification |
|---|---|
| Related requirement | FR-01 |
| Primary actor | Administrator |
| Goal | Update permitted details of one existing bank or hospital. |
| Preconditions | An existing organization is selected. The administrator is signed in; current details are loaded before saving edits. |
| Trigger | The administrator saves edited organization details. |
| Inputs | Selected resource identifier, permitted changed name/contact/location fields and update mask. |
| Main success scenario | 1. Authorize access and load current details. 2. Edit permitted fields. 3. Submit changes. 4. Recheck administrator permission, organization scope and existence. 5. Validate mutable fields, mask and coordinate/contact constraints. 6. Save changes and old/new audit values together. 7. Display current details. |
| Alternative and exception flows | Cancel: send no update. Invalid fields, immutable identity/type changes or invalid coordinates: retain previous values and allow correction. Missing authorized resource: not found. Forbidden actor/scope: no mutation. |
| Postconditions | Permitted changes and their audit are saved; the identifier, organization type and historical links remain stable. On denial or validation failure, prior details remain. |
| Rules and source distinctions | Deactivation is a separate command. Location changes do not rewrite historical issue/allocation facts. |
| Traceability | AC-01.3 editing; SQ-01C; AD-01C (Essential). |

## UC-01.4 Deactivate Organization

| Field | Specification |
|---|---|
| Related requirement | FR-01 |
| Primary actor | Administrator |
| Goal | Deactivate one selected facility while preserving historical associations. |
| Preconditions | An existing permitted bank or hospital is selected and visible to the administrator. |
| Trigger | The administrator confirms deactivation after reviewing its impact. |
| Inputs | Selected organization identifier, deactivation reason and command idempotency key. |
| Main success scenario | 1. Review impact and confirm the reason. 2. Submit deactivation. 3. Authenticate and authorize administrator/scope. 4. Validate command input. 5. Load and lock the organization. 6. Set inactive and append audit history. 7. Commit and return the resource. |
| Alternative and exception flows | Decline: no change. Invalid reason: no mutation. Forbidden scope: deny. Authorized absent resource: not found. Already inactive or completed replay: retain inactive state/history without unintended repeated effects. |
| Postconditions | The facility is inactive with historical records and links retained, or the operation ends unchanged. |
| Rules and source distinctions | No historical deletion or automatic bulk cancellation. Relevant new operations must respect inactive status. Reason/locking/audit refinements come from the revised guide. |
| Traceability | AC-01.3 deactivation; SQ-01D; AD-01B (Essential). |

## UC-01.5 View Organizations

| Field | Specification |
|---|---|
| Related requirement | FR-01 |
| Primary actor | Administrator or authorized staff member |
| Goal | View only permitted participating facility summaries or details. |
| Preconditions | Active authenticated staff; the current facility-visibility policy is known. |
| Trigger | Staff opens the organization list or a permitted facility detail. |
| Inputs | Facility type, allowed filters and pagination; one identifier for a separate detail invocation. |
| Main success scenario | 1. Request an allowed list query. 2. Authorize current visibility scope. 3. Validate filters and pagination. 4. Query scoped institutions. 5. Project permitted identifiers, names, contact and location. 6. Display the page or empty state. A detail lookup is a separate invocation using the same policy. |
| Alternative and exception flows | Empty list: normal empty result. Invalid query: correction feedback. Unauthenticated/forbidden request: deny. Authorized missing detail: not found. |
| Postconditions | Permitted information is displayed without record changes. |
| Rules and source distinctions | Not a public unrestricted directory. Facility visibility does not expose staff, donor or clinical records or authorize access to operational records. |
| Traceability | AC-01.3 viewing; SQ-01E (Essential); AD-01E (Supporting). |

## UC-02.1 Log In

| Field | Specification |
|---|---|
| Related requirement | FR-02 |
| Primary actor | Any staff role, including Administrator |
| Goal | Authenticate an active staff member and return one expiring session token and current user scope. |
| Preconditions | A user account exists; no authenticated session is required. Login rate limits apply. Initial accounts may be seeded. |
| Trigger | Staff submits email and password. |
| Inputs | Email and password; optional device display name under the identity contract. |
| Main success scenario | 1. React submits POST /v1/sessions. 2. Apply login rate limiting and validate fields. 3. Verify the password hash and active account. 4. Load current role and organization. 5. Store an expiring opaque token verifier. 6. Return the plaintext token once with user and expiry. 7. React retains local credential state and displays permitted workspace. |
| Alternative and exception flows | Invalid request: validation feedback. Invalid credentials or inactive account: generic unauthenticated feedback and no new token. Excessive attempts: rate-limit feedback. Connection failure: no successful authentication is assumed. |
| Postconditions | One expiring token and permitted user scope are returned on success; failed authentication creates no token. |
| Rules and source distinctions | Passwords are hashed. Sanctum personal access tokens are opaque, not signed JWTs. Rate limiting and concrete token behavior come from the revised guide/current identity contract. |
| Traceability | AC-02.1; AC-02.3 seed provisioning; SQ-02A (Essential). File: SQ-02-Login.puml. |

## UC-02.2 Log Out

| Field | Specification |
|---|---|
| Related requirement | FR-02 |
| Primary actor | Authenticated staff member |
| Goal | Revoke the presenting session token. |
| Preconditions | The request presents the current session token. |
| Trigger | Staff selects Log Out. |
| Inputs | Current bearer token; no password or new-token request. |
| Main success scenario | 1. React sends DELETE /v1/sessions/current. 2. Identify and verify the presenting token and current user. 3. Enforce the active-account guard. 4. Revoke that token only. 5. Return an empty success body. 6. React removes local credentials and displays sign-in. |
| Alternative and exception flows | Missing/expired/revoked token: unauthenticated feedback. Inactive account: permission-denied feedback under the current identity middleware. Network failure: local cleanup does not confirm server revocation. |
| Postconditions | On successful revocation the token cannot authenticate another request. No account or historical record is deleted. |
| Rules and source distinctions | Do not verify the password or issue a new token. Logout is distinct from account deactivation. |
| Traceability | AC-02.1; SQ-02B (Supporting). |

## UC-02.3 Access Permitted Operation

| Field | Specification |
|---|---|
| Related requirement | FR-02 |
| Primary actor | Any staff role requesting a protected operation |
| Goal | Allow one operation only after authentication, activity, role and organization checks. |
| Preconditions | The protected route/action and target scope are known. |
| Trigger | One protected API request reaches the backend. |
| Inputs | Configured authentication token, requested action, target and operation data. |
| Main success scenario | 1. Verify the token against its stored verifier, expiry and revocation. 2. Load current user, role and organization. 3. Check active account. 4. Check action permission. 5. Check target scope. 6. For limited bank assignment, apply the UC-02.4 guards. 7. Execute only the authorized operation and return its permitted result. |
| Alternative and exception flows | Missing/invalid token: 401 UNAUTHENTICATED. Inactive account, forbidden role or disallowed organization: 403 PERMISSION_DENIED. Denied operations do not execute. |
| Postconditions | One operation is authorized and executed or denied without its requested mutation. |
| Rules and source distinctions | Backend enforcement is mandatory. React can handle errors or navigate to sign-in; the JSON API does not redirect. Staff cannot change another organization's records. |
| Traceability | AC-02.2, AC-02.3; AD-02 (Essential). UC-02.4 is detailed in SQ-02C. |

## UC-02.4 Assign Permitted Bank Staff Role

| Field | Specification |
|---|---|
| Related requirement | FR-02; related FR-17 |
| Primary actor | Bank Administrator |
| Goal | Change only the role of a different user within the same bank to one of three permitted roles. |
| Preconditions | Active authenticated Bank Administrator; actor and target exist, are different users and share the same non-null bank_id. |
| Trigger | The Bank Administrator submits a new role for a selected colleague. |
| Inputs | Target user reference and role-only payload. |
| Main success scenario | 1. Submit POST /v1/users/{user}:assignRole. 2. Authenticate and check current bank-admin privilege. 3. Validate role-only payload and target. 4. Check same non-null bank, different user and allowed role. 5. Lock target and recheck current scope. 6. Update only the role and audit old/new values in one transaction. 7. Return a safe target-user summary. |
| Alternative and exception flows | Cross-bank or null-bank assignment, self-change, prohibited role, extra organization/profile/status edits or guards failing on recheck: deny with no mutation. |
| Postconditions | Only the target role changes; target organization/profile/status and past history remain unchanged. Denied changes leave the role unchanged. |
| Rules and source distinctions | Allowed roles: bank_donor_collection, bank_inventory, bank_fulfillment. The different-user guard is explicitly specified by the revised guide/proposed AUTH-09 contract; it is not quoted as wording from AC-02.4. |
| Traceability | AC-02.4; SQ-02C; conditional guards in AD-02 (Essential even when general FR-17 screens are deferred). |

## UC-02.5 View Own Account

| Field | Specification |
|---|---|
| Related requirement | FR-02 support; revised UC-02 guide |
| Primary actor | Any active authenticated staff member |
| Goal | Read the current user's own safe account representation. |
| Preconditions | Valid authentication and an active account. |
| Trigger | Staff requests their own account details. |
| Inputs | Current bearer token; GET /v1/users/me. |
| Main success scenario | 1. Submit the own-account request. 2. Authenticate and enforce account activity. 3. Resolve the current user and current role/organization. 4. Return the safe user representation. |
| Alternative and exception flows | Invalid/expired credentials: unauthenticated feedback. Inactive account: permission denied. No account details are changed. |
| Postconditions | Own-account details are returned without mutation. |
| Rules and source distinctions | No password hash or token is returned. This is not arbitrary-user lookup. The revised guide explicitly adds this goal to UC-02; no additional mandatory sequence is inferred. |
| Traceability | Revised guide UC-02; current AUTH-03 contract; shared AD-02 guards. |

## Conditional FR-17 goals in the UC-02 overview

| Goal | Actor | Boundary |
|---|---|---|
| Create Staff Account | Administrator | Conditional FR-17 delivery; distinct SQ-17A, outside this FR-01/02 diagram set. |
| Update Staff Account Details | Administrator | Conditional FR-17 delivery; distinct SQ-17C; no role, organization or status mutation. |
| Assign Staff Role and Organization | Administrator | Conditional FR-17 delivery; distinct SQ-17D; not the limited bank-admin contract. |
| Deactivate Staff Account | Administrator | Conditional FR-17 delivery; distinct SQ-17B; not logout. |

No public signup, donor authentication or general permission editor is included.
