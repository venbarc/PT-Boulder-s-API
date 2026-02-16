<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE pte_patients MODIFY pte_id VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE pte_appointments MODIFY pte_id VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE pte_appointments MODIFY pte_patient_id VARCHAR(64) NULL');
        DB::statement('ALTER TABLE pte_invoices MODIFY pte_id VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE pte_invoices MODIFY pte_patient_id VARCHAR(64) NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE pte_patients MODIFY pte_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE pte_appointments MODIFY pte_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE pte_appointments MODIFY pte_patient_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE pte_invoices MODIFY pte_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE pte_invoices MODIFY pte_patient_id BIGINT UNSIGNED NULL');
    }
};
