<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->unique(
                ['hospital_id', 'medical_id'],
                'patients_hospital_medical_unique'
            );
        });

        Schema::table('forecast_points', function (Blueprint $table): void {
            $table->unique(
                ['run_id', 'target_date'],
                'forecast_points_run_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('forecast_points', function (Blueprint $table): void {
            $table->dropUnique('forecast_points_run_date_unique');
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropUnique('patients_hospital_medical_unique');
        });
    }
};
