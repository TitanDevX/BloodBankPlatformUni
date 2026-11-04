# Blood Bank Platform — RTM

Updated 2 November 2026.

`RTM.xlsx` contains **one worksheet and one Excel table**, with one row for each of 29 requirements: 24 functional requirements and 5 draft nonfunctional requirements. No evidence-index or guide worksheet is present.

The table contains exactly the 12 fields in the supplied image, in the same order and spelling: ID, Title, Associated Dependency, SRS Section, High Level Design, Detailed Design, Coding, Component Test, Integration Test, System Test, Acceptance Test, Changed Requsets No. No additional or hidden fields are present.

## Direct links

- **ID:** shared high-level use case diagram, for FR rows; contextual actor/capability overview.
- **Title:** current functional requirement document, for FR rows.
- **SRS Section:** Use Case Specifications table number and use case ID, linked directly to the requested specification document. No language label is displayed.
- **High Level Design:** the specific activity diagram title, followed by its actual macro subsystems/swimlanes (such as Client, Server — Accounts, or Printer). FR-01–23 link directly to their activity PNGs.
- **Detailed Design:** the specific sequence diagram title and communicating objects, the involved domain classes from the shared class diagram, and the ERD entities read or written. FR-01–23 link directly to their sequence PNGs. FR-24 explicitly identifies its missing sequence and links to existing disposal-schema evidence instead.
- **Shared design shortcuts above the table:** direct links to the current class PNG, low-level ERD, architecture document and high-level ERD. They add no fields or tables.
- **System Test:** existing test-case workbook where a scenario exists; linked cases remain Planned / Not executed.
- **Acceptance Test:** existing forecasting plan where proposed acceptance evidence exists.

All 138 hyperlinks point directly to existing files in this `docs` folder or its subfolders using relative paths. Each linked cell opens its named primary artifact; other elements in the cell are supporting locators, not additional independent links. Shared design shortcuts provide the static source files named in Detailed Design. Keep the existing folder structure with the workbook.

## Reading the status columns

Dependencies point from a requirement to its prerequisite capability and are inferred workflow/data-producer relationships; existing accounts, profiles and stock may already exist. Ticket printing is optional. Processing and laboratory finalization can finish in either order.

Pending implementation means no completed requirement-specific implementation was established. Partial frontend indicates limited available client code, with server/operational work still pending. Pending test design means no concrete case at that level was found. Planned / Not executed identifies existing cases/criteria without a verified execution result. None recorded in the change column does not establish complete change history: no register was found.

The five NFR IDs and wording exist in the draft report §4.4, Table 4.3 (outside `docs`). They remain visibly marked draft, with the source identified as text; no unrelated `docs` file is linked as their specification. Approval is unrecorded. The nine HLRs summarize the FRs and are not duplicated as independent rows.

## Remaining gaps

- FR-24 activity and sequence diagrams were not found. High Level Design identifies its missing activity and uses architecture for documented macro context; Detailed Design identifies its missing sequence and links to the existing low-level ERD.
- Three editable UML sources are empty (FR-17/18 sequence and FR-16 activity); rendered PNG evidence is used where applicable.
- Performance workload, matching distance/tie policy, quality thresholds, server source availability, frontend-role alignment, and forecasting data readiness still need resolution. Existing overview diagrams do not prove runtime authorization or complete quality compliance.
- Literature/AI planning and the draft report disagree on offline synchronization scope; no additional offline requirement is invented.

## Validation

Verified one worksheet, one table, exactly 12 columns matching the image, 29 unique requirement IDs, specification-table locators, activity links in High Level Design, sequence links and class/entity listings in Detailed Design, all 138 direct file links and test-workbook locations. Actual diagram titles, object labels and subsystem lanes were extracted from available sources; rendered evidence supplies the three empty-source cases. Quality requirements retain explicit partial mappings or gaps instead of fabricated use cases. Frozen headings/identifiers and filters are present. The revised table was rendered and visually inspected, and no spreadsheet errors were found. Hyperlinks were checked structurally against the saved file and filesystem; they were not clicked in Microsoft Excel. Application tests were not executed for this RTM revision.

Original project documents and diagrams were not modified. Superseded RTM packages and copied evidence were moved out of `docs` to a temporary recoverable backup.
