# Current ERD data dictionary

Source: `../code/erd-low-level.mmd`. Updated 27 October 2026. This is a design specification, not a deployed database. Existing names/types are preserved.

16 entities, 189 fields, 26 role-specific associations. The two request–bank roles are deliberately separate.

## requests

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| request_reference | string | UK |
| hospital_id | bigint | FK |
| patient_reference | string | Required unless a state rule below says otherwise |
| submitted_by_staff_id | bigint | FK |
| component_type | string | Required unless a state rule below says otherwise |
| abo_group | string | Required unless a state rule below says otherwise |
| rh_factor | string | Required unless a state rule below says otherwise |
| quantity_units | int | Required unless a state rule below says otherwise |
| needed_by_at | datetime | Required unless a state rule below says otherwise |
| urgency | string | Required unless a state rule below says otherwise |
| delivery_location | string | Required unless a state rule below says otherwise |
| requester_contact | string | Required unless a state rule below says otherwise |
| status | string | Required unless a state rule below says otherwise |
| submitted_at | datetime | Required unless a state rule below says otherwise |
| addressed_bank_id | bigint | FK |
| matched_at | datetime | Required unless a state rule below says otherwise |
| matching_policy_version | string | Required unless a state rule below says otherwise |
| accepted_bank_id | bigint | FK nullable |
| accepted_by_staff_id | bigint | FK nullable |
| accepted_at | datetime | nullable |
| rejected_by_staff_id | bigint | FK nullable for automatic rejection |
| rejected_at | datetime | nullable |
| rejection_reason | string | nullable |
| rejection_source | string | nullable; manual or automatic |

## hospitals

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| public_id | string | UK |
| display_name | string | Required unless a state rule below says otherwise |
| address | string | Required unless a state rule below says otherwise |
| contact_phone | string | nullable |
| contact_email | string | nullable |
| latitude | decimal | nullable |
| longitude | decimal | nullable |
| active | boolean | Required unless a state rule below says otherwise |

## blood_units

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| donation_id | bigint | FK, UK |
| bag_reference | string | UK nullable until bag assigned |
| volume_ml | decimal | nullable |
| collection_status | string | Required unless a state rule below says otherwise |
| collection_fail_reason | string | nullable |
| started_by_staff_id | bigint | FK |
| started_at | datetime | Required unless a state rule below says otherwise |
| completed_by_staff_id | bigint | FK nullable |
| completed_at | datetime | nullable |
| quarantined_at | datetime | nullable |
| separation_outcome | string | nullable |
| separation_failure_reason | string | nullable |
| separated_by_staff_id | bigint | FK nullable |
| separated_at | datetime | nullable |

## request_allocations

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| request_id | bigint | FK |
| blood_component_id | bigint | FK, UK |
| allocated_at | datetime | Required unless a state rule below says otherwise |

## component_disposals

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| blood_component_id | bigint | FK, UK |
| disposed_by_staff_id | bigint | FK |
| reason | string | Required unless a state rule below says otherwise |
| disposed_at | datetime | Required unless a state rule below says otherwise |

## blood_components

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| component_reference | string | UK |
| donation_id | bigint | FK |
| blood_unit_id | bigint | FK |
| component_type | string | Required unless a state rule below says otherwise |
| volume_ml | decimal | Required unless a state rule below says otherwise |
| abo_group | string | nullable until verified |
| rh_factor | string | nullable until verified |
| expires_at | datetime | Required unless a state rule below says otherwise |
| processing_decision | string | Required unless a state rule below says otherwise |
| processed_at | datetime | Required unless a state rule below says otherwise |
| status | string | Required unless a state rule below says otherwise |
| created_at | datetime | Required unless a state rule below says otherwise |
| released_at | datetime | nullable |

## donor_profiles

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| donor_reference | string | UK |
| full_name | string | Required unless a state rule below says otherwise |
| date_of_birth | date | Required unless a state rule below says otherwise |
| national_id | string | UK nullable |
| phone | string | Required unless a state rule below says otherwise |
| address | string | nullable |
| created_at | datetime | Required unless a state rule below says otherwise |

## staff_accounts

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| name | string | Required unless a state rule below says otherwise |
| email | string | UK |
| password_hash | string | Required unless a state rule below says otherwise |
| active | boolean | Required unless a state rule below says otherwise |
| created_at | datetime | Required unless a state rule below says otherwise |

## roles

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| code | string | UK |
| display_name | string | Required unless a state rule below says otherwise |
| scope | string | Required unless a state rule below says otherwise |

## staff_assignments

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| staff_account_id | bigint | FK |
| role_id | bigint | FK |
| scope | string | Required unless a state rule below says otherwise |
| blood_bank_id | bigint | FK nullable |
| hospital_id | bigint | FK nullable |
| assigned_by_staff_id | bigint | FK |
| assigned_at | datetime | Required unless a state rule below says otherwise |
| removed_by_staff_id | bigint | FK nullable |
| removed_at | datetime | nullable |

## donations

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| donation_reference | string | UK |
| donor_profile_id | bigint | FK |
| blood_bank_id | bigint | FK |
| registered_by_staff_id | bigint | FK |
| status | string | Required unless a state rule below says otherwise |
| registered_at | datetime | Required unless a state rule below says otherwise |

## blood_banks

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| public_id | string | UK |
| display_name | string | Required unless a state rule below says otherwise |
| address | string | Required unless a state rule below says otherwise |
| contact_phone | string | nullable |
| contact_email | string | nullable |
| latitude | decimal | nullable |
| longitude | decimal | nullable |
| active | boolean | Required unless a state rule below says otherwise |

## lab_test_results

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| donation_id | bigint | FK, UK |
| results | json | Required unless a state rule below says otherwise |
| status | string | Required unless a state rule below says otherwise |
| requires_follow_up | boolean | Required unless a state rule below says otherwise |
| follow_up_reason | string | nullable |
| abo_group | string | nullable when Failed |
| rh_factor | string | nullable when Failed |
| failure_reason | string | nullable |
| recorded_by_staff_id | bigint | FK |
| completed_at | datetime | Required unless a state rule below says otherwise |

## assessment_results

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| donation_id | bigint | FK, UK |
| answers | json | Required unless a state rule below says otherwise |
| weight_kg | decimal | Required unless a state rule below says otherwise |
| hemoglobin_g_dl | decimal | Required unless a state rule below says otherwise |
| systolic_bp_mmhg | int | Required unless a state rule below says otherwise |
| diastolic_bp_mmhg | int | Required unless a state rule below says otherwise |
| pulse_bpm | int | Required unless a state rule below says otherwise |
| temperature_c | decimal | Required unless a state rule below says otherwise |
| final_decision | string | Required unless a state rule below says otherwise |
| deferral_type | string | nullable |
| deferral_reason | string | nullable |
| deferral_until | date | nullable |
| assessed_by_staff_id | bigint | FK |
| assessed_at | datetime | Required unless a state rule below says otherwise |

## forecast_runs

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| blood_bank_id | bigint | FK |
| requested_by_staff_id | bigint | FK |
| status | string | Required unless a state rule below says otherwise |
| idempotency_key | string | Required unless a state rule below says otherwise |
| input_fingerprint | string | Required unless a state rule below says otherwise |
| requested_at | datetime | Required unless a state rule below says otherwise |
| started_at | datetime | nullable |
| completed_at | datetime | nullable |
| as_of | datetime | Required unless a state rule below says otherwise |
| timezone | string | Required unless a state rule below says otherwise |
| history_start | date | nullable until snapshot validation |
| history_end | date | Required unless a state rule below says otherwise |
| data_complete_through | date | nullable until completeness validation |
| forecast_start | date | Required unless a state rule below says otherwise |
| horizon_days | int | Required unless a state rule below says otherwise |
| target_definition_version | string | Required unless a state rule below says otherwise |
| dataset_version | string | nullable until snapshot validation |
| feature_version | string | Required unless a state rule below says otherwise |
| attribution_version | string | Required unless a state rule below says otherwise |
| model_version | string | Required unless a state rule below says otherwise |
| model_artifact_reference | string | Required unless a state rule below says otherwise |
| model_checksum | string | Required unless a state rule below says otherwise |
| snapshot_reference | string | nullable until snapshot creation |
| snapshot_checksum | string | nullable until snapshot creation |
| evaluation_report_reference | string | nullable before validation; approved evaluation required for production publication |
| parameters | json | Required unless a state rule below says otherwise |
| result_manifest | json | Required unless a state rule below says otherwise |
| coverage | json | Required unless a state rule below says otherwise |
| quality_flags | json | Required unless a state rule below says otherwise |
| error_code | string | nullable |
| error_message | string | nullable |

## demand_forecasts

| Field | ERD type | Key / nullability |
| --- | --- | --- |
| id | bigint | PK |
| forecast_run_id | bigint | FK |
| component_type | string | Required unless a state rule below says otherwise |
| abo_group | string | Required unless a state rule below says otherwise |
| rh_factor | string | Required unless a state rule below says otherwise |
| forecast_date | date | Required unless a state rule below says otherwise |
| predicted_units | decimal | Required unless a state rule below says otherwise |
| p10_units | decimal | nullable when no validated interval exists |
| p50_units | decimal | nullable when no validated interval exists |
| p90_units | decimal | nullable when no validated interval exists |
| method | string | Required unless a state rule below says otherwise |
| result_status | string | Required unless a state rule below says otherwise |
| reason_code | string | nullable |
| quality_flags | json | Required unless a state rule below says otherwise |
| generated_at | datetime | Required unless a state rule below says otherwise |

## Composite keys and state constraints

- `forecast_runs`: unique `(blood_bank_id, requested_by_staff_id, idempotency_key)`; compare `input_fingerprint` on retry.
- `demand_forecasts`: unique `(forecast_run_id, component_type, abo_group, rh_factor, forecast_date)`.
- Matching is a transient query. A submitted request keeps its addressed bank, match timestamp and policy version. Accepted bank is optional until acceptance and must then equal addressed bank.
- Manual rejection requires actor/time/reason/source; automatic rejection has time/reason/source and no fabricated staff actor. Pending requests have no decision audit values.
- Submission facts are immutable under the current workflow; editing, corrections and rerouting require their own future event policy.
- Acceptance reserves exactly the full quantity atomically and reevaluates similar pending requests against remaining stock.
- Notice worklists derive from pending addressed requests and expired undisposed components. No notice table/read receipts/external delivery provider is introduced.
- Component availability requires accepted processing, passed tests, verified group, successful separation and current unexpired/unallocated/undisposed state.
- A forecast run may have zero points before success or on failure. Unavailable series are retained in `result_manifest` with reasons, never fabricated as zero demand.
- Predicted units are finite nonnegative expectations. Optional quantiles are all absent unless validated, then ordered and nonnegative.
- Published succeeded/partial run results and provenance are immutable. Viewing saved forecasts never reruns a model.
- Forecast snapshots use authorized aggregated original submissions attributed to `addressed_bank_id`, including later rejected requests. No donor/patient identifiers or credentials enter forecasting payloads.
- Offline synchronization is still a separately scoped architecture capability; completeness/provenance fields do not implement it.
