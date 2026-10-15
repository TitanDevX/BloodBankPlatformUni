<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL 8+ integrity rules for the Graduation 1 ERD.
 * Run after the base table migrations and before accepting production data.
 * Existing rows must be backfilled before adding the non-null bank_id columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_requests', function (Blueprint $table): void {
            $table->foreignId('source_bank_id')->nullable()->constrained('banks');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['id', 'bank_id'], 'users_id_bank_unique');
            $table->unique(['id', 'hospital_id'], 'users_id_hospital_unique');
        });

        DB::statement('ALTER TABLE donations MODIFY collector_id BIGINT UNSIGNED NULL');
        Schema::table('donations', function (Blueprint $table): void {
            $table->unique(['id', 'bank_id'], 'donations_id_bank_unique');
        });

        Schema::table('components', function (Blueprint $table): void {
            $table->unique(['id', 'bank_id'], 'components_id_bank_unique');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('bank_id');
        });

        Schema::table('issue_records', function (Blueprint $table): void {
            $table->foreignId('bank_id');
        });

        Schema::table('disposal_records', function (Blueprint $table): void {
            $table->foreignId('bank_id');
        });

        // NULLs are allowed multiple times in a MySQL UNIQUE index. Only active
        // reservations carry a component id in this generated column.
        DB::statement("ALTER TABLE reservations ADD active_component_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN state = 'active' THEN component_id ELSE NULL END) STORED");
        DB::statement('CREATE UNIQUE INDEX reservations_one_active_component ON reservations (active_component_id)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_one_organization_check CHECK (bank_id IS NULL OR hospital_id IS NULL)');
        DB::statement("ALTER TABLE donations ADD CONSTRAINT donations_collector_for_started_collection CHECK (collection_started_at IS NULL OR collector_id IS NOT NULL)");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_state_check CHECK (state IN ('active', 'released', 'issued'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_active_release_check CHECK ((state = 'active' AND released_at IS NULL) OR (state <> 'active' AND released_at IS NOT NULL))");

        // Composite foreign keys bind records to their source component and
        // assigned staff. Active request-bank matching is checked by triggers
        // so historical reservations can survive a later bank reassignment.
        $foreignKeys = [
            'ALTER TABLE donations ADD CONSTRAINT donations_collector_bank_fk FOREIGN KEY (collector_id, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE donations ADD CONSTRAINT donations_assessor_bank_fk FOREIGN KEY (assessed_by, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE donations ADD CONSTRAINT donations_release_actor_bank_fk FOREIGN KEY (release_actor_id, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE components ADD CONSTRAINT components_donation_bank_fk FOREIGN KEY (donation_id, bank_id) REFERENCES donations (id, bank_id)',
            'ALTER TABLE components ADD CONSTRAINT components_creator_bank_fk FOREIGN KEY (created_by, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE hospital_requests ADD CONSTRAINT requests_creator_hospital_fk FOREIGN KEY (created_by, hospital_id) REFERENCES users (id, hospital_id)',
            'ALTER TABLE hospital_requests ADD CONSTRAINT requests_patient_hospital_fk FOREIGN KEY (patient_id, hospital_id) REFERENCES patients (id, hospital_id)',
            'ALTER TABLE reservations ADD CONSTRAINT reservations_component_bank_fk FOREIGN KEY (component_id, bank_id) REFERENCES components (id, bank_id)',
            'ALTER TABLE reservations ADD CONSTRAINT reservations_confirmer_bank_fk FOREIGN KEY (confirmed_by, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE issue_records ADD CONSTRAINT issues_component_bank_fk FOREIGN KEY (component_id, bank_id) REFERENCES components (id, bank_id)',
            'ALTER TABLE issue_records ADD CONSTRAINT issues_issuer_bank_fk FOREIGN KEY (issued_by, bank_id) REFERENCES users (id, bank_id)',
            'ALTER TABLE disposal_records ADD CONSTRAINT disposals_component_bank_fk FOREIGN KEY (component_id, bank_id) REFERENCES components (id, bank_id)',
            'ALTER TABLE disposal_records ADD CONSTRAINT disposals_actor_bank_fk FOREIGN KEY (disposed_by, bank_id) REFERENCES users (id, bank_id)',
        ];
        DB::statement('ALTER TABLE patients ADD UNIQUE INDEX patients_id_hospital_unique (id, hospital_id)');
        foreach ($foreignKeys as $sql) {
            DB::statement($sql);
        }

        // A donation's source bank is fixed once registered. This keeps every
        // derived screening/inventory bank stable over its lifetime.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER donations_before_update_bank BEFORE UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.bank_id <> OLD.bank_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Donation source bank is immutable';
    END IF;
END
SQL);

        // The bank of a screening result is derived from its donation. Check
        // the recorder's current organization without duplicating bank_id.
        foreach (['insert', 'update'] as $action) {
            DB::unprepared(<<<SQL
CREATE TRIGGER screening_results_before_{$action} BEFORE {$action} ON screening_results
FOR EACH ROW
BEGIN
    DECLARE donation_bank BIGINT UNSIGNED;
    DECLARE recorder_bank BIGINT UNSIGNED;
    SELECT bank_id INTO donation_bank FROM donations WHERE id = NEW.donation_id FOR UPDATE;
    SELECT bank_id INTO recorder_bank FROM users WHERE id = NEW.recorded_by FOR UPDATE;
    IF donation_bank IS NULL OR recorder_bank IS NULL OR donation_bank <> recorder_bank THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Screening recorder must belong to donation bank';
    END IF;
END
SQL);
        }

        // A request may switch banks only after all old reservations are
        // released. Released/issued rows retain their original bank_id.
        // Locking the request row serializes reassignment with allocation.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER reservations_before_insert BEFORE INSERT ON reservations
FOR EACH ROW
BEGIN
    DECLARE selected_bank BIGINT UNSIGNED;
    IF NEW.state <> 'active' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New reservations must start active';
    END IF;
    IF NEW.state = 'active' THEN
        SELECT source_bank_id INTO selected_bank
          FROM hospital_requests WHERE id = NEW.request_id FOR UPDATE;
        IF selected_bank IS NULL OR selected_bank <> NEW.bank_id THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active reservation bank must match request source bank';
        END IF;
    END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER reservations_before_update BEFORE UPDATE ON reservations
FOR EACH ROW
BEGIN
    DECLARE selected_bank BIGINT UNSIGNED;
    IF NEW.request_id <> OLD.request_id OR NEW.component_id <> OLD.component_id
       OR NEW.bank_id <> OLD.bank_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation identity and original bank are immutable';
    END IF;
    IF NEW.state = 'active' THEN
        SELECT source_bank_id INTO selected_bank
          FROM hospital_requests WHERE id = NEW.request_id FOR UPDATE;
        IF selected_bank IS NULL OR selected_bank <> NEW.bank_id THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active reservation bank must match request source bank';
        END IF;
    END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER hospital_requests_before_update_bank BEFORE UPDATE ON hospital_requests
FOR EACH ROW
BEGIN
    IF NOT (NEW.source_bank_id <=> OLD.source_bank_id) THEN
        IF EXISTS (
            SELECT 1 FROM reservations
            WHERE request_id = OLD.id AND state = 'active' FOR UPDATE
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Release active reservations before changing source bank';
        END IF;
        IF EXISTS (
            SELECT 1 FROM issue_records WHERE request_id = OLD.id FOR UPDATE
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issued request source bank cannot change';
        END IF;
    END IF;
END
SQL);

        // Both terminal triggers first take the same component-row lock. The
        // opposite-record check is then a current locking read, so concurrent
        // issue and disposal attempts cannot both pass.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER issue_records_before_insert BEFORE INSERT ON issue_records
FOR EACH ROW
BEGIN
    DECLARE locked_component BIGINT UNSIGNED;
    SELECT id INTO locked_component FROM components WHERE id = NEW.component_id FOR UPDATE;
    IF EXISTS (SELECT 1 FROM disposal_records WHERE component_id = NEW.component_id FOR UPDATE) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Component already disposed';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM hospital_requests
        WHERE id = NEW.request_id AND source_bank_id = NEW.bank_id FOR UPDATE
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issue bank must match request source bank';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM reservations
        WHERE request_id = NEW.request_id AND component_id = NEW.component_id
          AND bank_id = NEW.bank_id AND state = 'active' FOR UPDATE
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issue requires an active matching reservation';
    END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER disposal_records_before_insert BEFORE INSERT ON disposal_records
FOR EACH ROW
BEGIN
    DECLARE locked_component BIGINT UNSIGNED;
    SELECT id INTO locked_component FROM components WHERE id = NEW.component_id FOR UPDATE;
    IF EXISTS (SELECT 1 FROM issue_records WHERE component_id = NEW.component_id FOR UPDATE) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Component already issued';
    END IF;
END
SQL);
        foreach (['issue_records', 'disposal_records'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_before_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terminal records are immutable'");
            DB::unprepared("CREATE TRIGGER {$table}_before_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terminal records are immutable'");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS donations_before_update_bank');
        DB::statement('DROP TRIGGER IF EXISTS screening_results_before_update');
        DB::statement('DROP TRIGGER IF EXISTS screening_results_before_insert');
        DB::statement('DROP TRIGGER IF EXISTS hospital_requests_before_update_bank');
        DB::statement('DROP TRIGGER IF EXISTS reservations_before_update');
        DB::statement('DROP TRIGGER IF EXISTS reservations_before_insert');
        foreach (['issue_records', 'disposal_records'] as $table) {
            foreach (['insert', 'update', 'delete'] as $action) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_before_{$action}");
            }
        }
        foreach ([
            ['disposal_records', 'disposals_actor_bank_fk'], ['disposal_records', 'disposals_component_bank_fk'],
            ['issue_records', 'issues_issuer_bank_fk'], ['issue_records', 'issues_component_bank_fk'],
            ['reservations', 'reservations_confirmer_bank_fk'],
            ['reservations', 'reservations_component_bank_fk'],
            ['hospital_requests', 'requests_patient_hospital_fk'], ['hospital_requests', 'requests_creator_hospital_fk'],
            ['components', 'components_creator_bank_fk'], ['components', 'components_donation_bank_fk'],
            ['donations', 'donations_release_actor_bank_fk'], ['donations', 'donations_assessor_bank_fk'],
            ['donations', 'donations_collector_bank_fk'],
        ] as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$name}");
        }
        DB::statement('ALTER TABLE patients DROP INDEX patients_id_hospital_unique');
        DB::statement('ALTER TABLE reservations DROP CHECK reservations_active_release_check');
        DB::statement('ALTER TABLE reservations DROP CHECK reservations_state_check');
        DB::statement('ALTER TABLE donations DROP CHECK donations_collector_for_started_collection');
        DB::statement('ALTER TABLE users DROP CHECK users_one_organization_check');
        DB::statement('DROP INDEX reservations_one_active_component ON reservations');
        DB::statement('ALTER TABLE reservations DROP COLUMN active_component_id');
        foreach (['disposal_records', 'issue_records', 'reservations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('bank_id'));
        }
        Schema::table('components', fn (Blueprint $table) => $table->dropUnique('components_id_bank_unique'));
        Schema::table('donations', fn (Blueprint $table) => $table->dropUnique('donations_id_bank_unique'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_id_bank_unique');
            $table->dropUnique('users_id_hospital_unique');
        });
        Schema::table('hospital_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_bank_id');
        });
    }
};
