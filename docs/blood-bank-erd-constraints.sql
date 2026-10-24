-- Constraint design for blood-bank-erd.mmd.
-- Apply in database migrations after choosing the database engine and creating
-- the tables. This file documents enforceable relational rules; it is not run.

-- Role scope and assignment organization must agree. System roles have no
-- organization; bank and hospital roles have exactly one of the matching type.
ALTER TABLE roles
    ADD CONSTRAINT ck_roles_scope
    CHECK (scope IS NOT NULL AND scope IN ('system', 'bank', 'hospital'));

ALTER TABLE roles
    ADD CONSTRAINT uq_roles_id_scope
    UNIQUE (id, scope);

ALTER TABLE staff_assignments
    ADD CONSTRAINT ck_staff_assignments_scope
    CHECK (
        role_id IS NOT NULL AND scope IS NOT NULL AND (
        (scope = 'system' AND blood_bank_id IS NULL AND hospital_id IS NULL)
        OR (scope = 'bank' AND blood_bank_id IS NOT NULL AND hospital_id IS NULL)
        OR (scope = 'hospital' AND blood_bank_id IS NULL AND hospital_id IS NOT NULL)
        )
    );

ALTER TABLE staff_assignments
    ADD CONSTRAINT fk_staff_assignments_role_scope
    FOREIGN KEY (role_id, scope) REFERENCES roles (id, scope);

-- A patient reference is unique within its hospital. The request's patient
-- must belong to the same hospital that submitted the request.
ALTER TABLE patients
    ADD CONSTRAINT pk_patients
    PRIMARY KEY (hospital_id, patient_reference);

ALTER TABLE hospital_requests
    ADD CONSTRAINT ck_request_patient_key
    CHECK (hospital_id IS NOT NULL AND patient_reference IS NOT NULL);

ALTER TABLE hospital_requests
    ADD CONSTRAINT fk_request_patient_hospital
    FOREIGN KEY (hospital_id, patient_reference)
    REFERENCES patients (hospital_id, patient_reference);

-- An allocation can only use a unit from the request's accepted bank.
-- The extra composite unique keys make the two composite foreign keys legal.
ALTER TABLE hospital_requests
    ADD CONSTRAINT uq_requests_id_accepted_bank
    UNIQUE (id, accepted_bank_id);

ALTER TABLE inventory_units
    ADD CONSTRAINT ck_inventory_bank
    CHECK (blood_bank_id IS NOT NULL);

ALTER TABLE inventory_units
    ADD CONSTRAINT uq_inventory_id_bank
    UNIQUE (id, blood_bank_id);

ALTER TABLE request_allocations
    ADD CONSTRAINT ck_allocation_bank
    CHECK (blood_bank_id IS NOT NULL);

ALTER TABLE request_allocations
    ADD CONSTRAINT fk_allocation_request_bank
    FOREIGN KEY (hospital_request_id, blood_bank_id)
    REFERENCES hospital_requests (id, accepted_bank_id);

ALTER TABLE request_allocations
    ADD CONSTRAINT fk_allocation_inventory_bank
    FOREIGN KEY (inventory_unit_id, blood_bank_id)
    REFERENCES inventory_units (id, blood_bank_id);

ALTER TABLE request_allocations
    ADD CONSTRAINT uq_allocation_inventory_unit
    UNIQUE (inventory_unit_id);

-- Eligible decisions have no deferral fields. A temporary deferral has an end
-- date; a permanent one does not. Both kinds require a reason.
ALTER TABLE eligibility_decisions
    ADD CONSTRAINT ck_eligibility_deferral
    CHECK (
        (decision = 'eligible' AND deferral_type IS NULL
            AND deferral_reason IS NULL AND deferral_until IS NULL)
        OR (decision = 'deferred' AND deferral_type = 'temporary'
            AND deferral_reason IS NOT NULL AND deferral_until IS NOT NULL)
        OR (decision = 'deferred' AND deferral_type = 'permanent'
            AND deferral_reason IS NOT NULL AND deferral_until IS NULL)
    );

-- FR-14 makes a separately recorded start optional, but actor and time must
-- either both be present or both be absent.
ALTER TABLE blood_collections
    ADD CONSTRAINT ck_collection_start_pair
    CHECK (
        (started_by_user_id IS NULL AND started_at IS NULL)
        OR (started_by_user_id IS NOT NULL AND started_at IS NOT NULL)
    );

-- Acceptance still needs a transaction: lock candidate inventory rows, verify
-- processing and lab clearance, exact component and ABO/Rh, unexpired status,
-- one bank, and exactly quantity_units rows; then create allocations and mark
-- those units reserved together. A row CHECK cannot enforce this aggregate rule.
