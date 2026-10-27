# Current ERD documentation

Current schema: `../code/erd-low-level.mmd`. Current field dictionary: `erd-data-dictionary.md`. The class design in `../../Diagrams/Class/` shares the same entity/attribute names and role-specific associations.

The revision of 27 October 2026 preserves all 14 original entity identifiers and 135 original fields/types. It adds request destination/matching metadata, explicit manual/automatic rejection audit, and the two forecast entities already proposed in the AI plan. The current totals are 16 entities, 189 attributes and 26 associations. This is a schema design, not a migration/deployed database.

The accepting-bank relationship retains its original meaning; a separate required addressed-bank relationship identifies pending-request routing. Forecast runs belong to a bank and requester, with immutable saved daily result points. Request/expiry notices are scoped worklists of durable request/component records and do not require a separate notification table for the stated requirements.

`Blood Bank Platform ERD and Data Dictionary.docx` and `erd-persistence-review.md` describe an earlier schema. They are retained as historical originals; use the current Mermaid source and Markdown dictionary for exact fields. The high-level draw.io/image remains a capability overview (including Demand Forecasting), with the low-level source supplying table and actor detail. Root-level legacy ERD/SQL files are likewise not the current authoritative schema. The user's folder structure and manual high-level layout are preserved.

See `requirements-reconciliation.md` for the behavior and integrity changes behind this revision.
