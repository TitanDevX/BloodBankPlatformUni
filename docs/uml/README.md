# Organization and Identity UML

## Scope and source hierarchy

This set covers FR-01 and FR-02 using the revised UML guide dated 17 October 2026. BloodBank_Requirements.docx remains the functional baseline. The guide defines the drawing boundaries and refinements; openapi.yaml supplies current identity contracts and BACKEND_API_REFERENCE.md supplies proposed business contracts. No application code is changed by this documentation.

A sequence models one invocation and one result. An activity models one bounded goal, with responsibility lanes. Use-case views group separate goals; actor associations do not imply chronological order. General staff administration is shown conditionally under FR-17 in UC-02, without adding SQ-17 scenarios to this scope.

## Figure index

| ID | Subject | Use case / pairing | Level | Source | PNG |
|---|---|---|---|---|---|
| UC-01 | Organization management | FR-01 | Essential | [PlantUML](<Use Case/UC-01-Organizations.puml>) | [PNG](<Use Case/UC-01-Organizations.png>) |
| UC-02 | Identity and staff permissions | FR-02; conditional FR-17 goals | Essential | [PlantUML](<Use Case/UC-02-Identity.puml>) | [PNG](<Use Case/UC-02-Identity.png>) |
| SQ-01A | Create bank | UC-01.1 | Essential | [PlantUML](<Sequence/SQ-01A-CreateBank.puml>) | [PNG](<Sequence/SQ-01A-CreateBank.png>) |
| SQ-01B | Create hospital | UC-01.2 | Essential | [PlantUML](<Sequence/SQ-01B-CreateHospital.puml>) | [PNG](<Sequence/SQ-01B-CreateHospital.png>) |
| SQ-01C | Update organization | UC-01.3 | Essential | [PlantUML](<Sequence/SQ-01C-UpdateOrganization.puml>) | [PNG](<Sequence/SQ-01C-UpdateOrganization.png>) |
| SQ-01D | Deactivate organization | UC-01.4 | Essential | [PlantUML](<Sequence/SQ-01D-DeactivateOrganization.puml>) | [PNG](<Sequence/SQ-01D-DeactivateOrganization.png>) |
| SQ-01E | View organization list | UC-01.5 | Essential | [PlantUML](<Sequence/SQ-01E-ViewOrganizations.puml>) | [PNG](<Sequence/SQ-01E-ViewOrganizations.png>) |
| SQ-02A | Log in | UC-02.1 | Essential | [PlantUML](<Sequence/SQ-02-Login.puml>) | [PNG](<Sequence/SQ-02-Login.png>) |
| SQ-02B | Log out | UC-02.2 | Supporting | [PlantUML](<Sequence/SQ-02B-Logout.puml>) | [PNG](<Sequence/SQ-02B-Logout.png>) |
| SQ-02C | Assign permitted bank staff role | UC-02.4 | Essential | [PlantUML](<Sequence/SQ-02C-AssignBankStaffRole.puml>) | [PNG](<Sequence/SQ-02C-AssignBankStaffRole.png>) |
| AD-01A | Create bank | UC-01.1 / SQ-01A | Essential | [PlantUML](<Activity/AD-01A-CreateBank.puml>) | [PNG](<Activity/AD-01A-CreateBank.png>) |
| AD-01B | Deactivate organization | UC-01.4 / SQ-01D | Essential | [PlantUML](<Activity/AD-01B-DeactivateOrganization.puml>) | [PNG](<Activity/AD-01B-DeactivateOrganization.png>) |
| AD-01C | Update organization | UC-01.3 / SQ-01C | Essential | [PlantUML](<Activity/AD-01C-UpdateOrganization.puml>) | [PNG](<Activity/AD-01C-UpdateOrganization.png>) |
| AD-01D | Create hospital | UC-01.2 / SQ-01B | Essential | [PlantUML](<Activity/AD-01D-CreateHospital.puml>) | [PNG](<Activity/AD-01D-CreateHospital.png>) |
| AD-01E | View organizations | UC-01.5 / SQ-01E | Supporting | [PlantUML](<Activity/AD-01E-ViewOrganizations.puml>) | [PNG](<Activity/AD-01E-ViewOrganizations.png>) |
| AD-02 | Authorize one protected operation | UC-02.3 / UC-02.4 guards | Essential | [PlantUML](<Activity/AD-02-AccessCheck.puml>) | [PNG](<Activity/AD-02-AccessCheck.png>) |

SQ-02A deliberately retains the existing SQ-02-Login.puml filename, as directed by the guide. SQ-02B is logout and SQ-02C is limited bank assignment. Activity letters are not interchangeable with sequence letters: hospital creation is SQ-01B/AD-01D; deactivation is SQ-01D/AD-01B.

## Acceptance evidence

| Criterion | Evidence |
|---|---|
| AC-01.1 | Seed/test at least two banks and two hospitals with distinct identifiers when persistence is available. This is not another runtime diagram. |
| AC-01.2 | Organization ownership is a structural invariant for CL-01/ERD-01 and later donation/component/request workflows. These figures do not claim to create those operational records. |
| AC-01.3 | Five distinct goals in UC-01; SQ-01A through SQ-01E and the activity pairings above. |
| AC-02.1 | SQ-02A login rejects invalid/inactive accounts; SQ-02B revokes only the presenting token. Passwords are hashed. |
| AC-02.2 / AC-02.3 | AD-02 verifies current authentication, account activity, action permission and organization scope. Initial accounts may be seeded. |
| AC-02.4 | SQ-02C and AD-02 conditional guards check same non-null bank and the three allowed roles. |

## Implementation distinctions

- Organizations currently has models but no implemented business API routes. Its sequences describe required design and proposed contracts.
- The current identity backend implements login/logout/own-account reads. Authentication and session service lifelines denote logical responsibilities, not a claim that additional concrete PHP service classes exist.
- Current manage-users authorization is administrator-only. The guide's bank-admin assignment policy, different-user restriction, lock/recheck and audit are required design that the current backend does not fully implement. No backend code is changed here.
- Self-change prohibition is explicit in the revised guide and proposed AUTH-09 contract; AC-02.4 itself explicitly names same non-null bank and the three permitted roles.
- No fixed clinical thresholds, shelf lives, AI features or infrastructure choices are invented. Sanctum tokens are opaque personal access tokens, not signed JWTs.

## Export and review

Each PNG is rendered directly from its neighboring PlantUML source. The user's configured PlantUML server is used because Java is unavailable locally. All figures are individually scoped, use English labels, and carry FR/use-case references. Essential/Supporting labels follow the revised catalogue; the catalogue is not a university-mandated submission count. A supervisor/peer review remains part of the guide's completion process.

The broad legacy management/access sources and their outdated PNGs are retired from this directory; their committed versions remain in Git history. Use this index instead of obsolete figure names.

[Complete use-case tables](Use-Case-Specifications.md)

[All PlantUML sources in one document](PlantUML-Sources.md)

Technical review: all 16 diagrams were accepted by the renderer and every final PNG was visually inspected. Validation/cancellation/denial outcomes, responsibility lanes, catalogue identifiers and use-case pairings were checked. The export review does not claim application acceptance tests or supervisor approval.
